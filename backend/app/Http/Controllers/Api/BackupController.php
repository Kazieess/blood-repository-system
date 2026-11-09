<?php

namespace App\Http\Controllers\Api;

use App\Actions\BackupStore;
use App\Http\Controllers\Controller;
use App\Models\BackupRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BackupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('backups.manage'), 403);
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);
        $runs = BackupRun::orderByDesc('created_at')->paginate(10, ['id', 'status', 'bytes', 'error_code', 'created_at', 'completed_at', 'verified_at', 'restore_copy']);

        return response()->json(['data' => $runs->items(), 'meta' => ['total' => $runs->total(), 'current_page' => $runs->currentPage(), 'last_page' => $runs->lastPage()], 'adapter' => DB::connection()->getDriverName(), 'supported' => DB::connection()->getDriverName() === 'sqlite', 'schedule_pht' => config('repository.backup_at'), 'max_snapshot_bytes' => config('repository.backup_max_bytes')]);
    }

    public function store(Request $request, BackupStore $store): JsonResponse
    {
        abort_unless($request->user()->hasPermission('backups.manage'), 403);
        $input = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $record = $store->create($request->user()->id, (string) $request->header('Idempotency-Key'), $input['reason'], $request->attributes->get('request_id'));

        return response()->json(['data' => ['id' => $record->id, 'status' => $record->status, 'bytes' => $record->bytes, 'completed_at' => $record->completed_at]]);
    }

    public function restore(Request $request, string $backup, BackupStore $store): JsonResponse
    {
        abort_unless($request->user()->hasPermission('backups.manage'), 403);
        $request->validate(['isolated_copy_confirmed' => ['required', 'accepted']]);

        return response()->json(['data' => $store->restoreCopy(BackupRun::findOrFail($backup), $request->user()->id, $request->attributes->get('request_id'))]);
    }
}
