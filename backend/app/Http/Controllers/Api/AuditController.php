<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditResource;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->hasPermission('audit.read'), 403);
        $input = $request->validate([
            'q' => ['nullable', 'string', 'max:120'], 'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,50'],
        ]);
        $query = AuditLog::with('actor:id,name');

        if (! empty($input['q'])) {
            $query->where(function ($query) use ($input): void {
                $query->where('action', $input['q'])->orWhere('subject_id', $input['q'])->orWhere('request_id', $input['q']);
            });
        }

        return AuditResource::collection($query->orderByDesc('id')->paginate($input['per_page'] ?? 20)->withQueryString());
    }
}
