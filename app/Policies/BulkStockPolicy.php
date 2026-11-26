<?php

namespace App\Policies;

use App\Models\BulkStock;
use App\Models\User;

class BulkStockPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && ! $user->isBorrower();
    }

    public function view(User $user, BulkStock $bulkStock): bool
    {
        return $user->is_active && ! $user->isBorrower();
    }

    public function create(User $user): bool
    {
        return $user->is_active && in_array($user->role, ['superadmin', 'admin', 'staff'], true);
    }

    public function update(User $user, BulkStock $bulkStock): bool
    {
        return $user->is_active && in_array($user->role, ['superadmin', 'admin', 'staff'], true);
    }

    public function delete(User $user, BulkStock $bulkStock): bool
    {
        return $user->is_active
            && in_array($user->role, ['superadmin', 'admin'], true)
            && $bulkStock->borrowed_qty === 0;
    }
}
