<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AvailabilitySnapshot extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'published_at' => 'immutable_datetime', 'valid_until' => 'immutable_datetime', 'withdrawn_at' => 'immutable_datetime'];
    }
}
