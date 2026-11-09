<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DonorAssignment extends Model
{
    protected $guarded = ['id'];

    public const CLINICAL = ['screening.read', 'screening.write', 'screening.review', 'laboratory.read', 'laboratory.write', 'laboratory.review', 'eligibility.read', 'eligibility.write', 'eligibility.review'];

    protected function casts(): array
    {
        return ['clinical_permissions' => 'array', 'version' => 'integer', 'active' => 'boolean'];
    }
}
