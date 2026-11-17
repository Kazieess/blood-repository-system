<?php

namespace App\Http\Controllers\Api;

use App\Actions\StockEvaluator;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\AvailabilitySnapshot;
use App\Models\BloodUnit;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\InventoryEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TraceabilityController extends Controller
{
    public function index(Request $request, string $donor): JsonResponse
    {
        abort_unless($request->user()->hasPermission('donors.read') && $request->user()->hasPermission('inventory.read'), 403);
        Donor::visibleTo($request->user())->findOrFail($donor);
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);
        $records = BloodUnit::whereHas('donation', fn ($q) => $q->where('donor_id', $donor))->with('donation:id,reference')->orderBy('expires_at')->orderBy('id')->paginate(10);

        return response()->json(['data' => collect($records->items())->map(fn ($unit) => ['id' => $unit->id, 'reference' => $unit->reference, 'donation_reference' => $unit->donation->reference, 'component' => $unit->component, 'status' => $unit->status, 'expires_at' => $unit->expires_at->toISOString()]), 'meta' => ['total' => $records->total(), 'current_page' => $records->currentPage(), 'last_page' => $records->lastPage()]]);
    }

    public function link(Request $request, string $unit, StockEvaluator $evaluator): JsonResponse
    {
        abort_unless($request->user()->hasPermission('inventory.write') && $request->user()->hasPermission('donors.read'), 403);
        $input = $request->validate(['donation_reference' => ['required', 'string', 'max:80'], 'version' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'min:3', 'max:500']]);
        DB::transaction(function () use ($request, $unit, $input, $evaluator): void {
            $source = Donation::where('reference', $input['donation_reference'])->whereIn('donor_id', Donor::visibleTo($request->user())->select('id'))->lockForUpdate()->firstOrFail();
            $record = BloodUnit::whereKey($unit)->lockForUpdate()->firstOrFail();
            abort_unless($source->status === 'completed', 409);
            abort_unless($record->version === (int) $input['version'] && $record->donation_id === null && in_array($record->status, ['pending', 'available'], true), 409);
            if ($record->collected_at->timezone('Asia/Manila')->toDateString() !== $source->donated_on) {
                throw ValidationException::withMessages(['donation_reference' => ['The donation date must match the recorded collection date in Philippine time.']]);
            }
            $record->update(['donation_id' => $source->id, 'status' => 'pending', 'verified_at' => null, 'version' => $record->version + 1]);
            AvailabilitySnapshot::whereNull('withdrawn_at')->where('valid_until', '>', now())->update(['valid_until' => now()]);
            InventoryEvent::create(['blood_unit_id' => $unit, 'actor_id' => $request->user()->id, 'action' => 'source_linked', 'reason' => $input['reason'], 'created_at' => now()]);
            AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'inventory.source_linked', 'subject_id' => $unit, 'changed_fields' => ['donation_id'], 'reason' => $input['reason'], 'request_id' => $request->attributes->get('request_id')]);
            $evaluator->evaluate();
        });

        return response()->json(['message' => 'Source linked. Authorized verification is required.']);
    }
}
