<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DonorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name,
            'blood_type' => $this->blood_type, 'municipality' => $this->municipality,
            'record_status' => $this->record_status, 'version' => $this->version,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'can_update' => $request->user()->can('update', $this->resource),
            'donation_count' => $this->donations()->where('status', 'completed')->count(),
            'clinical_permissions' => $this->assignments()->where('user_id', $request->user()->id)->first()?->clinical_permissions ?? [],
        ];
    }
}
