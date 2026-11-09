<?php

namespace App\Policies;

use App\Models\Donor;
use App\Models\User;

class DonorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('donors.read');
    }

    public function view(User $user, Donor $donor): bool
    {
        return $user->hasPermission('donors.read') && ($donor->relationLoaded('assignments') ? $donor->assignments->contains(fn ($assignment) => $assignment->user_id === $user->id && $assignment->active) : $donor->assignments()->where('user_id', $user->id)->where('active', true)->exists());
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('donors.create');
    }

    public function update(User $user, Donor $donor): bool
    {
        return $user->hasPermission('donors.update') && $this->view($user, $donor);
    }
}
