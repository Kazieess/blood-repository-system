<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ClinicalEntry extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $hidden = ['creation_key', 'creation_hash'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'version' => 'integer', 'observed_at' => 'immutable_datetime', 'reviewed_at' => 'immutable_datetime'];
    }
}
