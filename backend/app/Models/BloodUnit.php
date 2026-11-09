<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class BloodUnit extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $hidden = ['creation_key', 'creation_hash'];

    protected function casts(): array
    {
        return ['collected_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'verified_at' => 'immutable_datetime', 'version' => 'integer'];
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', 'available')->whereNotNull('verified_at')->where('expires_at', '>', now());
    }
}
