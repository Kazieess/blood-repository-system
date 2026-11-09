<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicalField extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['choices' => 'array', 'required' => 'boolean', 'active' => 'boolean', 'version' => 'integer'];
    }
}
