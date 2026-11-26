<?php

namespace App\Policies;

use App\Models\FundingSource;
use App\Models\User;

class FundingSourcePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && in_array($user->role, ['superadmin', 'admin', 'staff'], true);
    }

    public function view(User $user, FundingSource $source): bool
    {
        return $user->is_active && in_array($user->role, ['superadmin', 'admin', 'staff'], true);
    }

    public function create(User $user): bool
    {
        return $user->is_active && in_array($user->role, ['superadmin', 'admin'], true);
    }

    public function update(User $user, FundingSource $source): bool
    {
        return $user->is_active && in_array($user->role, ['superadmin', 'admin'], true);
    }

    public function delete(User $user, FundingSource $source): bool
    {
        return $user->is_active
            && in_array($user->role, ['superadmin', 'admin'], true)
            && $source->procurements()->count() === 0;
    }
}
