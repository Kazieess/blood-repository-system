<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'actor' => $this->actor?->name ?? 'System',
            'action' => $this->action, 'subject_id' => $this->subject_id,
            'reason' => $this->reason, 'changed_fields' => $this->changed_fields,
            'request_id' => $this->request_id,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
