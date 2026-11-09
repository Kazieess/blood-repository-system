<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Donor;
use App\Models\DonorAssignment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AssignmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('assignments.manage'), 403);
        $input = $request->validate(['donor_id' => ['nullable', 'uuid'], 'page' => ['nullable', 'integer', 'min:1']]);
        $donors = Donor::query()->when($input['donor_id'] ?? null, fn ($query, $id) => $query->whereKey($id))->select(['id', 'created_at'])->withCount('assignments')->latest()->paginate(10);

        return response()->json(['data' => $donors->items(), 'meta' => ['total' => $donors->total(), 'current_page' => $donors->currentPage(), 'last_page' => $donors->lastPage()], 'staff' => User::where('active', true)->whereHas('role', fn ($query) => $query->whereIn('name', ['Red Cross Administrator', 'Red Cross Personnel', 'Barangay Health Coordinator']))->get(['id', 'name']), 'clinical_permissions' => DonorAssignment::CLINICAL]);
    }

    public function show(Request $request, string $donor): JsonResponse
    {
        abort_unless($request->user()->hasPermission('assignments.manage'), 403);
        Donor::findOrFail($donor);

        return response()->json(['data' => DonorAssignment::where('donor_id', $donor)->get(['user_id', 'clinical_permissions', 'version', 'active'])]);
    }

    public function store(Request $request, string $donor): JsonResponse
    {
        abort_unless($request->user()->hasPermission('assignments.manage'), 403);
        Donor::findOrFail($donor);
        $input = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id'], 'version' => ['required', 'integer', 'min:0'], 'clinical_permissions' => ['present', 'array', 'max:9'], 'clinical_permissions.*' => ['string', 'distinct', Rule::in(DonorAssignment::CLINICAL)], 'reason' => ['required', 'string', 'min:3', 'max:500'], 'revoke' => ['sometimes', 'boolean']]);
        $staff = User::findOrFail($input['user_id']);
        abort_unless(! empty($input['revoke']) || $staff->hasPermission('donors.read'), 422, 'Choose active operational staff.');
        DB::transaction(function () use ($request, $donor, $input): void {
            $query = DonorAssignment::where('donor_id', $donor)->where('user_id', $input['user_id']);
            $record = (clone $query)->first();
            abort_if(($record?->version ?? 0) !== $input['version'], 409);
            if ($record) {
                $match = $query->where('version', $input['version']);
                $changed = $match->update(['active' => empty($input['revoke']), 'clinical_permissions' => json_encode(! empty($input['revoke']) ? [] : $input['clinical_permissions'], JSON_THROW_ON_ERROR), 'assigned_by' => $request->user()->id, 'version' => DB::raw('version + 1'), 'updated_at' => now()]);
                abort_if($changed !== 1, 409);
            } else {
                abort_if(! empty($input['revoke']), 409);
                try {
                    DonorAssignment::create(['donor_id' => $donor, 'user_id' => $input['user_id'], 'assigned_by' => $request->user()->id, 'clinical_permissions' => $input['clinical_permissions']]);
                } catch (QueryException $exception) {
                    if (DonorAssignment::where('donor_id', $donor)->where('user_id', $input['user_id'])->exists()) {
                        abort(409);
                    }
                    throw $exception;
                }
            }
            AuditLog::create(['actor_id' => $request->user()->id, 'action' => ! empty($input['revoke']) ? 'assignment.revoked' : 'assignment.granted', 'subject_id' => $donor, 'reason' => $input['reason'], 'changed_fields' => ['staff_assignment', 'clinical_permissions'], 'request_id' => $request->attributes->get('request_id')]);
        });

        return $this->show($request, $donor);
    }
}
