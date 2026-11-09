<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Donor extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['name', 'blood_type', 'municipality', 'record_status', 'created_by', 'updated_by', 'version', 'creation_key', 'creation_hash'];

    protected $hidden = ['creation_key', 'creation_hash'];

    protected $attributes = ['record_status' => 'for_review', 'version' => 1];

    protected function casts(): array
    {
        return ['version' => 'integer', 'created_by' => 'integer', 'updated_by' => 'integer'];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->hasPermission('donors.all') ? $query : $query->where('created_by', $user->id);
    }
}
