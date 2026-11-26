<?php

namespace App\Policies;

use App\Models\AssetCategory;
use App\Models\User;

class AssetCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && ! $user->isBorrower();
    }

    public function view(User $user, AssetCategory $category): bool
    {
        return $user->is_active && ! $user->isBorrower();
    }

    public function create(User $user): bool
    {
        return $user->is_active && in_array($user->role, ['superadmin', 'admin'], true);
    }

    public function update(User $user, AssetCategory $category): bool
    {
        return $user->is_active && in_array($user->role, ['superadmin', 'admin'], true);
    }

    public function delete(User $user, AssetCategory $category): bool
    {
        return $user->is_active
            && in_array($user->role, ['superadmin', 'admin'], true)
            && $category->assets()->count() === 0;
    }
}
