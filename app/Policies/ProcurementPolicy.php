<?php

namespace App\Policies;

use App\Models\Procurement;
use App\Models\User;
use App\Services\GovernanceService;

class ProcurementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && in_array($user->role, ['superadmin', 'admin', 'staff'], true);
    }

    public function view(User $user, Procurement $procurement): bool
    {
        return $user->is_active && in_array($user->role, ['superadmin', 'admin', 'staff'], true);
    }

    public function create(User $user): bool
    {
        return GovernanceService::canManageProcurements($user);
    }

    public function update(User $user, Procurement $procurement): bool
    {
        return GovernanceService::canManageProcurements($user);
    }

    public function delete(User $user, Procurement $procurement): bool
    {
        return $user->is_active
            && in_array($user->role, ['superadmin', 'admin'], true)
            && ! $procurement->isGenerated();
    }
}
