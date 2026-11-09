<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        return $query->whereHas('assignments', fn (Builder $assignments) => $assignments->where('user_id', $user->id)->where('active', true));
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DonorAssignment::class);
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    protected static function booted(): void
    {
        static::created(function (Donor $donor): void {
            DonorAssignment::create(['donor_id' => $donor->id, 'user_id' => $donor->created_by, 'assigned_by' => $donor->created_by, 'clinical_permissions' => []]);
        });
    }
}
