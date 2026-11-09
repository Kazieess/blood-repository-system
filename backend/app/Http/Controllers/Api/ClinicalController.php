<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ClinicalEntry;
use App\Models\ClinicalField;
use App\Models\ClinicalRevision;
use App\Models\Donor;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ClinicalController extends Controller
{
    private function permit(Request $request, string $donor, string $category, string $action): Donor
    {
        abort_unless(in_array($category, ['screening', 'laboratory', 'eligibility'], true), 404);
        abort_unless($request->user()->hasPermission('donors.read'), 403);
        $record = Donor::visibleTo($request->user())->findOrFail($donor);
        $assignment = $record->assignments()->where('user_id', $request->user()->id)->where('active', true)->firstOrFail();
        abort_unless(in_array($category.'.'.$action, $assignment->clinical_permissions ?? [], true), 403);
        if ($action === 'review') {
            abort_unless(in_array($category.'.read', $assignment->clinical_permissions ?? [], true), 403);
        }

        return $record;
    }

    private function present(ClinicalEntry $entry): array
    {
        return ['id' => $entry->id, 'category' => $entry->category, 'payload' => $entry->payload, 'source_reference' => $entry->source_reference, 'observed_at' => $entry->observed_at->toISOString(), 'status' => $entry->status, 'recorded_by' => $entry->recorded_by, 'updated_by' => $entry->updated_by, 'reviewed_by' => $entry->reviewed_by, 'reviewed_at' => $entry->reviewed_at?->toISOString(), 'version' => $entry->version];
    }

    private function audit(Request $request, string $action, string $subject): void
    {
        AuditLog::create(['actor_id' => $request->user()->id, 'action' => $action, 'subject_id' => $subject, 'changed_fields' => ['clinical_record'], 'request_id' => $request->attributes->get('request_id')]);
    }

    public function index(Request $request, string $donor, string $category): JsonResponse
    {
        $this->permit($request, $donor, $category, 'read');
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);
        $records = ClinicalEntry::where('donor_id', $donor)->where('category', $category)->latest()->orderBy('id')->paginate(5);
        $data = array_map(fn ($entry) => $this->present($entry), $records->items());
        $this->audit($request, 'clinical.'.$category.'.read', $donor);

        return response()->json(['data' => $data, 'meta' => ['total' => $records->total(), 'current_page' => $records->currentPage(), 'last_page' => $records->lastPage()], 'catalog' => $category === 'laboratory' ? ClinicalField::where('active', true)->orderBy('id')->get() : [], 'allow_self_review' => config('repository.allow_clinical_self_review')]);
    }

    public function history(Request $request, string $donor, string $category, string $entry): JsonResponse
    {
        $this->permit($request, $donor, $category, 'read');
        $record = ClinicalEntry::where('donor_id', $donor)->where('category', $category)->findOrFail($entry);
        $revisions = ClinicalRevision::where('clinical_entry_id', $entry)->orderByDesc('version')->paginate(5);
        $this->audit($request, 'clinical.'.$category.'.history_read', $record->id);

        return response()->json(['data' => collect($revisions->items())->map(fn ($revision) => ['version' => $revision->version, 'snapshot' => $revision->snapshot, 'created_at' => $revision->created_at]), 'meta' => ['total' => $revisions->total(), 'current_page' => $revisions->currentPage(), 'last_page' => $revisions->lastPage()]]);
    }

    private function fields(Request $request, string $category): array
    {
        $input = $request->validate(['source_reference' => ['required', 'string', 'max:120'], 'observed_at' => ['required', 'date', 'before_or_equal:now', 'regex:/^\d{4}-\d{2}-\d{2}T.*(?:Z|[+-]\d{2}:\d{2})$/'], 'payload' => ['required', 'array', 'max:10']]);
        if ($category === 'laboratory') {
            $catalog = ClinicalField::where('active', true)->orderBy('id')->get();
            abort_if($catalog->isEmpty(), 422, 'An authorized administrator must configure the laboratory catalog first.');
            $rules = ['payload' => ['required', 'array:results,notes'], 'payload.notes' => ['nullable', 'string', 'max:5000'], 'payload.results' => ['required', 'array:'.$catalog->pluck('key')->implode(',')]];
            foreach ($catalog as $field) {
                $valueRule = $field->format === 'number' ? 'numeric' : 'string';
                $rules['payload.results.'.$field->key] = [$field->required ? 'required' : 'nullable', $valueRule];
                if ($field->format === 'choice') {
                    $rules['payload.results.'.$field->key][] = Rule::in($field->choices);
                }
                if ($field->format !== 'number') {
                    $rules['payload.results.'.$field->key][] = 'max:1000';
                }
            }
            Validator::make($input, $rules)->validate();
            $input['payload']['definitions'] = $catalog->map(fn ($field) => Arr::only($field->toArray(), ['key', 'label', 'format', 'unit', 'reference_text', 'choices', 'version']))->all();
        } else {
            $rules = ['payload' => ['required', 'array:decision,notes,deferred_until'], 'payload.notes' => ['required', 'string', 'max:5000'], 'payload.decision' => ['required', Rule::in($category === 'screening' ? ['pending', 'recorded', 'requires_review'] : ['not_assessed', 'eligible', 'deferred', 'not_eligible'])], 'payload.deferred_until' => ['nullable', 'date_format:Y-m-d']];
            Validator::make($input, $rules)->validate();
            abort_if(! empty($input['payload']['deferred_until']) && ($category !== 'eligibility' || $input['payload']['decision'] !== 'deferred'), 422, 'A deferral date applies only to a recorded deferral.');
        }
        $input['observed_at'] = CarbonImmutable::parse($input['observed_at'])->utc();

        return $input;
    }

    public function store(Request $request, string $donor, string $category): JsonResponse
    {
        $this->permit($request, $donor, $category, 'write');
        $input = $this->fields($request, $category);
        $key = $request->header('Idempotency-Key');
        abort_unless(Str::isUuid($key), 422, 'A UUID recording key is required.');
        $hash = hash('sha256', json_encode([$donor, $category, $input], JSON_THROW_ON_ERROR));
        $lookup = fn () => ClinicalEntry::where('recorded_by', $request->user()->id)->where('creation_key', $key)->first();
        if ($existing = $lookup()) {
            abort_unless(hash_equals($existing->creation_hash, $hash), 409);

            return response()->json(['data' => $this->present($existing)]);
        }
        try {
            $record = DB::transaction(function () use ($request, $input, $key, $hash, $donor, $category): ClinicalEntry {
                $record = ClinicalEntry::create([...$input, 'donor_id' => $donor, 'category' => $category, 'recorded_by' => $request->user()->id, 'updated_by' => $request->user()->id, 'status' => 'recorded', 'version' => 1, 'creation_key' => $key, 'creation_hash' => $hash]);
                $this->revision($record, 'Initial recording');
                $this->audit($request, 'clinical.'.$category.'.created', $record->id);

                return $record;
            });
        } catch (QueryException $exception) {
            if ($existing = $lookup()) {
                abort_unless(hash_equals($existing->creation_hash, $hash), 409);

                return response()->json(['data' => $this->present($existing)]);
            }
            throw $exception;
        }

        return response()->json(['data' => $this->present($record)], 201);
    }

    public function update(Request $request, string $donor, string $category, string $entry): JsonResponse
    {
        $this->permit($request, $donor, $category, 'write');
        $input = $this->fields($request, $category);
        $extra = $request->validate(['version' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'min:3', 'max:1000']]);
        $record = DB::transaction(function () use ($request, $donor, $category, $entry, $input, $extra): ClinicalEntry {
            $record = ClinicalEntry::where('donor_id', $donor)->where('category', $category)->findOrFail($entry);
            $record->fill([...$input, 'updated_by' => $request->user()->id, 'status' => 'recorded', 'reviewed_by' => null, 'reviewed_at' => null, 'version' => $extra['version'] + 1]);
            $changed = ClinicalEntry::whereKey($entry)->where('version', $extra['version'])->update($record->getDirty());
            abort_if($changed !== 1, 409);
            $record->refresh();
            $this->revision($record, $extra['reason']);
            $this->audit($request, 'clinical.'.$category.'.corrected', $entry);

            return $record;
        });

        return response()->json(['data' => $this->present($record)]);
    }

    public function review(Request $request, string $donor, string $category, string $entry): JsonResponse
    {
        $this->permit($request, $donor, $category, 'review');
        $input = $request->validate(['version' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'min:3', 'max:1000']]);
        $record = DB::transaction(function () use ($request, $donor, $category, $entry, $input): ClinicalEntry {
            $record = ClinicalEntry::where('donor_id', $donor)->where('category', $category)->findOrFail($entry);
            abort_if(! config('repository.allow_clinical_self_review') && $record->updated_by === $request->user()->id, 403, 'A different authorized staff member must review this entry.');
            abort_if($record->status !== 'recorded', 409);
            $changed = ClinicalEntry::whereKey($entry)->where('version', $input['version'])->update(['status' => 'reviewed', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'version' => DB::raw('version + 1'), 'updated_at' => now()]);
            abort_if($changed !== 1, 409);
            $record->refresh();
            $this->revision($record, $input['reason']);
            $this->audit($request, 'clinical.'.$category.'.reviewed', $entry);

            return $record;
        });

        return response()->json(['data' => ['id' => $record->id, 'status' => $record->status, 'version' => $record->version]]);
    }

    private function revision(ClinicalEntry $entry, string $reason): void
    {
        ClinicalRevision::create(['clinical_entry_id' => $entry->id, 'version' => $entry->version, 'snapshot' => [...$this->present($entry), 'reason' => $reason], 'created_at' => now()]);
    }

    public function catalog(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('clinical.catalog'), 403);

        return response()->json(['data' => ClinicalField::orderBy('id')->get()]);
    }

    public function saveCatalog(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('clinical.catalog'), 403);
        $input = $request->validate(['id' => ['nullable', 'integer'], 'version' => ['required', 'integer', 'min:0'], 'key' => ['required', 'string', 'max:60', 'regex:/^[a-z][a-z0-9_]*$/'], 'label' => ['required', 'string', 'max:100'], 'format' => ['required', Rule::in(['text', 'number', 'choice'])], 'choices' => ['nullable', 'required_if:format,choice', 'array', 'max:30'], 'choices.*' => ['string', 'max:100', 'distinct'], 'unit' => ['nullable', 'string', 'max:40'], 'reference_text' => ['nullable', 'string', 'max:500'], 'required' => ['required', 'boolean'], 'active' => ['required', 'boolean'], 'reason' => ['required', 'string', 'min:3', 'max:500']]);
        $record = DB::transaction(function () use ($request, $input): ClinicalField {
            $fields = Arr::except($input, ['id', 'version', 'reason']);
            if (! empty($input['id'])) {
                $record = ClinicalField::findOrFail($input['id']);
                abort_unless($record->key === $input['key'] && $record->format === $input['format'], 422, 'Field keys and formats are immutable; deactivate and add a new definition.');
                $record->fill([...$fields, 'version' => $input['version'] + 1]);
                abort_if(ClinicalField::whereKey($record->id)->where('version', $input['version'])->update($record->getDirty()) !== 1, 409);
                $record->refresh();
            } else {
                abort_unless($input['version'] === 0, 409);
                abort_if(ClinicalField::where('key', $input['key'])->exists(), 409);
                $record = ClinicalField::create([...$fields, 'version' => 1]);
            }
            $this->audit($request, 'clinical.catalog.saved', (string) $record->id);

            return $record;
        });

        return response()->json(['data' => $record]);
    }
}
