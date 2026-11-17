<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BloodUnit;
use App\Models\Donation;
use App\Models\Donor;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DonationController extends Controller
{
    private function donor(Request $request, string $id): Donor
    {
        abort_unless($request->user()->hasPermission('donors.read'), 403);

        return Donor::visibleTo($request->user())->findOrFail($id);
    }

    public function index(Request $request, string $donor): JsonResponse
    {
        $this->donor($request, $donor);
        $input = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])], 'status' => ['nullable', Rule::in(['completed', 'voided'])], 'page' => ['nullable', 'integer', 'min:1']]);
        $records = Donation::where('donor_id', $donor)->when($input['from'] ?? null, fn ($q, $v) => $q->where('donated_on', '>=', $v))->when($input['to'] ?? null, fn ($q, $v) => $q->where('donated_on', '<=', $v))->when($input['status'] ?? null, fn ($q, $v) => $q->where('status', $v))->orderByDesc('donated_on')->orderBy('id')->paginate(10);

        return response()->json(['data' => $records->items(), 'meta' => ['total' => $records->total(), 'current_page' => $records->currentPage(), 'last_page' => $records->lastPage()], 'completed_count' => Donation::where('donor_id', $donor)->where('status', 'completed')->count()]);
    }

    public function store(Request $request, string $donor): JsonResponse
    {
        $this->donor($request, $donor);
        abort_unless($request->user()->hasPermission('donors.update'), 403);
        $input = $request->validate(['reference' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9_-]+$/'], 'donated_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Manila')->toDateString()], 'location' => ['required', 'string', 'min:2', 'max:100']]);
        $key = $request->header('Idempotency-Key');
        abort_unless(Str::isUuid($key), 422, 'A UUID registration key is required.');
        $hash = hash('sha256', json_encode([$donor, $input], JSON_THROW_ON_ERROR));
        $lookup = fn () => Donation::where('created_by', $request->user()->id)->where('creation_key', $key)->first();
        if ($existing = $lookup()) {
            abort_unless(hash_equals($existing->creation_hash, $hash), 409);

            return response()->json(['data' => $existing]);
        }
        try {
            $record = DB::transaction(function () use ($request, $donor, $input, $key, $hash): Donation {
                $record = Donation::create([...$input, 'donor_id' => $donor, 'status' => 'completed', 'version' => 1, 'created_by' => $request->user()->id, 'creation_key' => $key, 'creation_hash' => $hash]);
                AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'donation.created', 'subject_id' => $record->id, 'changed_fields' => array_keys($input), 'request_id' => $request->attributes->get('request_id')]);

                return $record;
            });
        } catch (QueryException $exception) {
            if ($existing = $lookup()) {
                abort_unless(hash_equals($existing->creation_hash, $hash), 409);

                return response()->json(['data' => $existing]);
            }
            if (Donation::where('reference', $input['reference'])->exists()) {
                abort(409, 'This donation reference is already recorded.');
            }
            throw $exception;
        }

        return response()->json(['data' => $record], 201);
    }

    public function update(Request $request, string $donor, string $donation): JsonResponse
    {
        $this->donor($request, $donor);
        abort_unless($request->user()->hasPermission('donors.update'), 403);
        $record = Donation::where('donor_id', $donor)->findOrFail($donation);
        $input = $request->validate(['donated_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Manila')->toDateString()], 'location' => ['required', 'string', 'min:2', 'max:100'], 'status' => ['required', Rule::in(['completed', 'voided'])], 'version' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'min:3', 'max:500']]);
        abort_if($record->status === 'voided' && $input['status'] !== 'voided', 409, 'A voided event cannot be counted again.');
        DB::transaction(function () use ($request, $record, $input): void {
            $record = Donation::whereKey($record->id)->lockForUpdate()->firstOrFail();
            if ($input['status'] === 'voided' || $input['donated_on'] !== $record->donated_on) {
                abort_if(BloodUnit::where('donation_id', $record->id)->exists(), 409, 'Linked stock prevents voiding or changing the collection date.');
            }
            $fields = Arr::only($input, ['donated_on', 'location', 'status']);
            $changed = Donation::whereKey($record->id)->where('version', $input['version'])->update([...$fields, 'version' => DB::raw('version + 1'), 'updated_at' => now()]);
            abort_if($changed !== 1, 409);
            AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'donation.corrected', 'subject_id' => $record->id, 'reason' => $input['reason'], 'changed_fields' => array_keys($fields), 'request_id' => $request->attributes->get('request_id')]);
        });

        return response()->json(['data' => $record->fresh()]);
    }
}
