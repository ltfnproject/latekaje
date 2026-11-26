<?php

namespace App\Policies;

use App\Models\Consumable;
use App\Models\User;

class ConsumablePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && ! $user->isBorrower();
    }

    public function view(User $user, Consumable $consumable): bool
    {
        return $user->is_active && ! $user->isBorrower();
    }

    public function create(User $user): bool
    {
        return $user->is_active && in_array($user->role, ['superadmin', 'admin', 'staff'], true);
    }

    public function update(User $user, Consumable $consumable): bool
    {
        return $user->is_active && in_array($user->role, ['superadmin', 'admin', 'staff'], true);
    }

    public function recordUsage(User $user, Consumable $consumable): bool
    {
        return $user->is_active && in_array($user->role, ['superadmin', 'admin', 'staff', 'assistant'], true);
    }

    public function restock(User $user, Consumable $consumable): bool
    {
        return $user->is_active && in_array($user->role, ['superadmin', 'admin', 'staff'], true);
    }

    public function delete(User $user, Consumable $consumable): bool
    {
        return $user->is_active && in_array($user->role, ['superadmin', 'admin'], true);
    }
}
