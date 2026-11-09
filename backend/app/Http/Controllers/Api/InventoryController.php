<?php

namespace App\Http\Controllers\Api;

use App\Actions\StockEvaluator;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BloodUnit;
use App\Models\InventoryEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    private function permit(Request $request, string $action = 'read'): void
    {
        abort_unless($request->user()->hasPermission('inventory.'.$action), 403);
    }

    private function present(BloodUnit $unit): array
    {
        return ['id' => $unit->id, 'reference' => $unit->reference, 'blood_type' => $unit->blood_type, 'component' => $unit->component, 'location' => $unit->location, 'collected_at' => $unit->collected_at->toISOString(), 'expires_at' => $unit->expires_at->toISOString(), 'status' => $unit->status, 'expired' => $unit->expires_at->lessThanOrEqualTo(now()), 'verified_at' => $unit->verified_at?->toISOString(), 'version' => $unit->version];
    }

    public function index(Request $request): JsonResponse
    {
        $this->permit($request);
        $input = $request->validate(['q' => ['nullable', 'string', 'max:80'], 'blood_type' => ['nullable', Rule::in(['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'])], 'component' => ['nullable', 'string', 'max:80'], 'location' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['pending', 'available', 'quarantined', 'spoiled', 'released', 'discarded', 'expired'])], 'expires_before' => ['nullable', 'date'], 'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'between:1,50']]);
        $query = BloodUnit::query();
        foreach (['blood_type', 'component', 'location'] as $field) {
            if (! empty($input[$field])) {
                $query->where($field, $input[$field]);
            }
        }
        if (isset($input['q']) && $input['q'] !== '') {
            $query->where('reference', $input['q']);
        }
        if (($input['status'] ?? '') === 'available') {
            $query->available();
        } elseif (($input['status'] ?? '') === 'expired') {
            $query->where('expires_at', '<=', now())->whereNotIn('status', ['released', 'discarded']);
        } elseif (! empty($input['status'])) {
            $query->where('status', $input['status']);
        }
        if (! empty($input['expires_before'])) {
            $query->where('expires_at', '<=', CarbonImmutable::parse($input['expires_before'])->utc());
        }
        $units = $query->orderBy('expires_at')->orderBy('id')->paginate($input['per_page'] ?? 10);

        return response()->json(['data' => array_map(fn ($unit) => $this->present($unit), $units->items()), 'meta' => ['total' => $units->total(), 'current_page' => $units->currentPage(), 'last_page' => $units->lastPage()], 'available_total' => BloodUnit::available()->count(), 'components' => BloodUnit::distinct()->orderBy('component')->pluck('component'), 'locations' => BloodUnit::distinct()->orderBy('location')->pluck('location')]);
    }

    public function show(Request $request, string $unit): JsonResponse
    {
        $this->permit($request);
        $record = BloodUnit::findOrFail($unit);

        return response()->json(['data' => [...$this->present($record), 'history' => InventoryEvent::where('blood_unit_id', $unit)->orderByDesc('id')->get(['action', 'actor_id', 'reason', 'created_at'])]]);
    }

    public function store(Request $request, StockEvaluator $evaluator): JsonResponse
    {
        $this->permit($request, 'write');
        $dateRules = ['required', 'date', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/'];
        $input = $request->validate(['reference' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9_-]+$/'], 'blood_type' => ['required', Rule::in(['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'])], 'component' => ['required', 'string', 'min:2', 'max:80'], 'location' => ['required', 'string', 'min:2', 'max:100'], 'collected_at' => [...$dateRules, 'before_or_equal:now'], 'expires_at' => [...$dateRules, 'after:collected_at'], 'reason' => ['required', 'string', 'min:3', 'max:500']]);
        $key = $request->header('Idempotency-Key');
        abort_unless(Str::isUuid($key), 422, 'A UUID registration key is required.');
        $hash = hash('sha256', json_encode($input, JSON_THROW_ON_ERROR));
        $lookup = fn () => BloodUnit::where('created_by', $request->user()->id)->where('creation_key', $key)->first();
        if ($record = $lookup()) {
            abort_unless(hash_equals($record->creation_hash, $hash), 409);

            return response()->json(['data' => $this->present($record)]);
        }
        try {
            $record = DB::transaction(function () use ($request, $input, $key, $hash, $evaluator): BloodUnit {
                $fields = Arr::except($input, ['reason']);
                $record = BloodUnit::create([...$fields, 'collected_at' => CarbonImmutable::parse($input['collected_at'])->utc(), 'expires_at' => CarbonImmutable::parse($input['expires_at'])->utc(), 'status' => 'pending', 'version' => 1, 'created_by' => $request->user()->id, 'creation_key' => $key, 'creation_hash' => $hash]);
                $this->event($request, $record, 'registered', $input['reason']);
                $evaluator->evaluate();

                return $record;
            });
        } catch (QueryException $exception) {
            if ($record = $lookup()) {
                abort_unless(hash_equals($record->creation_hash, $hash), 409);

                return response()->json(['data' => $this->present($record)]);
            }
            if (BloodUnit::where('reference', $input['reference'])->exists()) {
                abort(409, 'This bag reference is already recorded.');
            }
            throw $exception;
        }

        return response()->json(['data' => $this->present($record)], 201);
    }

    public function transition(Request $request, string $unit, StockEvaluator $evaluator): JsonResponse
    {
        $this->permit($request, 'write');
        $input = $request->validate(['version' => ['required', 'integer', 'min:1'], 'action' => ['required', Rule::in(['verify', 'quarantine', 'spoil', 'release', 'discard'])], 'reason' => ['required', 'string', 'min:3', 'max:500'], 'verification_confirmed' => ['required_if:action,verify', 'accepted_if:action,verify']]);
        DB::transaction(function () use ($request, $unit, $input, $evaluator): void {
            $record = BloodUnit::findOrFail($unit);
            $allowed = ['verify' => ['pending'], 'quarantine' => ['pending', 'available'], 'spoil' => ['pending', 'available', 'quarantined'], 'release' => ['available'], 'discard' => ['pending', 'available', 'quarantined', 'spoiled']];
            abort_unless(in_array($record->status, $allowed[$input['action']], true), 409, 'This transition is not available.');
            if (in_array($input['action'], ['verify', 'release'], true)) {
                abort_if($record->expires_at->lessThanOrEqualTo(now()), 409, 'This unit has expired.');
            }
            if ($input['action'] === 'release') {
                abort_unless($record->verified_at, 409);
            }
            $status = ['verify' => 'available', 'quarantine' => 'quarantined', 'spoil' => 'spoiled', 'release' => 'released', 'discard' => 'discarded'][$input['action']];
            $changes = ['status' => $status, 'version' => DB::raw('version + 1'), 'updated_at' => now()];
            if ($input['action'] === 'verify') {
                $changes['verified_at'] = now();
            }
            $updated = BloodUnit::whereKey($unit)->where('version', $input['version'])->update($changes);
            abort_if($updated !== 1, 409);
            $this->event($request, $record, $input['action'], $input['reason']);
            $evaluator->evaluate();
        });

        return $this->show($request, $unit);
    }

    private function event(Request $request, BloodUnit $unit, string $action, string $reason): void
    {
        InventoryEvent::create(['blood_unit_id' => $unit->id, 'actor_id' => $request->user()->id, 'action' => $action, 'reason' => $reason, 'created_at' => now()]);
        AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'inventory.'.$action, 'subject_id' => $unit->id, 'reason' => $reason, 'changed_fields' => ['inventory_status'], 'request_id' => $request->attributes->get('request_id')]);
    }
}
