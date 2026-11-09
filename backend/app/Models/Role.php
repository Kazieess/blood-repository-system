<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public const NAMES = [
        'Red Cross Administrator',
        'Red Cross Personnel',
        'Barangay Health Coordinator',
        'Report Viewer',
        'System Administrator',
    ];

    /** @return list<string> */
    public function permissions(): array
    {
        return match ($this->name) {
            'Red Cross Administrator' => ['donors.read', 'donors.create', 'donors.update', 'donors.all', 'audit.read'],
            'Red Cross Personnel' => ['donors.read', 'donors.create', 'donors.update', 'donors.all'],
            'Barangay Health Coordinator' => ['donors.read', 'donors.create', 'donors.update'],
            'System Administrator' => ['audit.read'],
            default => [],
        };
    }
}
