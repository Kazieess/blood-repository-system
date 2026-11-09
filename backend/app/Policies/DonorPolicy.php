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
        return $user->hasPermission('donors.read') && ($user->hasPermission('donors.all') || $donor->created_by === $user->id);
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
