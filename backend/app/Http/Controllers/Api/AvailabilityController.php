<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\AvailabilitySnapshot;
use App\Models\BloodUnit;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AvailabilityController extends Controller
{
    public function public(): JsonResponse
    {
        $record = AvailabilitySnapshot::orderByDesc('sequence')->first();
        $state = ! $record || $record->withdrawn_at ? 'unavailable' : ($record->valid_until->lessThanOrEqualTo(now()) ? 'stale' : 'available');

        return response()->json(['state' => $state, 'data' => $state === 'available' ? $record->payload : [], 'published_at' => $record?->published_at?->toISOString(), 'valid_until' => $record?->valid_until?->toISOString(), 'message' => 'Availability is a published snapshot, not a reservation or guarantee of release. Contact authorized staff to confirm.'])->header('Cache-Control', 'no-store');
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('availability.publish'), 403);

        return response()->json(['data' => AvailabilitySnapshot::orderByDesc('sequence')->limit(10)->get(['id', 'payload', 'published_at', 'valid_until', 'withdrawn_at']), 'locations' => BloodUnit::distinct()->orderBy('location')->pluck('location')]);
    }

    public function publish(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('availability.publish'), 403);
        $input = $request->validate(['location' => ['required', 'string', 'max:100'], 'public_label' => ['required', 'string', 'min:2', 'max:100'], 'valid_minutes' => ['required', 'integer', 'between:1,1440'], 'confirmed' => ['accepted'], 'reason' => ['required', 'string', 'min:3', 'max:500']]);
        $key = $request->header('Idempotency-Key');
        abort_unless(Str::isUuid($key), 422);
        $input['valid_minutes'] = (int) $input['valid_minutes'];
        $input['confirmed'] = true;
        $hash = hash('sha256', json_encode($input, JSON_THROW_ON_ERROR));
        try {
            $record = DB::transaction(function () use ($request, $input, $key, $hash): AvailabilitySnapshot {
                if ($record = AvailabilitySnapshot::find($key)) {
                    abort_unless($record->published_by === $request->user()->id && hash_equals($record->request_hash, $hash), 409);

                    return $record;
                }
                abort_unless(BloodUnit::where('location', $input['location'])->exists(), 422);
                $query = BloodUnit::available()->where('location', $input['location']);
                $units = $query->orderBy('id')->lockForUpdate()->get(['id', 'blood_type', 'component', 'expires_at']);
                $rows = $units->groupBy(fn ($unit) => json_encode([$unit->blood_type, $unit->component]))->map(fn ($group) => (object) ['blood_type' => $group->first()->blood_type, 'component' => $group->first()->component, 'units' => $group->count()])->values();
                $expiry = $units->min('expires_at');
                $until = now()->addMinutes($input['valid_minutes']);
                if ($expiry && $until->gt($expiry)) {
                    $until = Carbon::parse($expiry);
                }
                $record = new AvailabilitySnapshot(['request_hash' => $hash, 'payload' => $rows->map(fn ($row) => ['blood_type' => $row->blood_type, 'component' => $row->component, 'location' => $input['public_label'], 'units' => (int) $row->units])->all(), 'published_by' => $request->user()->id, 'published_at' => now(), 'valid_until' => $until]);
                $record->id = $key;
                $record->save();
                AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'availability.published', 'subject_id' => $key, 'reason' => $input['reason'], 'changed_fields' => ['aggregate_snapshot'], 'request_id' => $request->attributes->get('request_id')]);

                return $record;
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            $record = AvailabilitySnapshot::find($key);
            if (! $record) {
                throw $exception;
            }
            abort_unless($record->published_by === $request->user()->id && hash_equals($record->request_hash, $hash), 409);
        }

        return response()->json(['data' => ['id' => $record->id, 'published_at' => $record->published_at->toISOString()]], 201);
    }

    public function withdraw(Request $request, string $snapshot): JsonResponse
    {
        abort_unless($request->user()->hasPermission('availability.publish'), 403);
        $input = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        DB::transaction(function () use ($request, $snapshot, $input): void {
            $record = AvailabilitySnapshot::whereKey($snapshot)->lockForUpdate()->firstOrFail();
            if ($record->withdrawn_at) {
                return;
            }
            $record->update(['withdrawn_at' => now()]);
            AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'availability.withdrawn', 'subject_id' => $snapshot, 'reason' => $input['reason'], 'changed_fields' => ['withdrawn_at'], 'request_id' => $request->attributes->get('request_id')]);
        });

        return response()->json(['message' => 'Publication withdrawn.']);
    }
}
