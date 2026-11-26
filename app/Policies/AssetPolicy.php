<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;
use App\Services\GovernanceService;

class AssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && ! $user->isBorrower();
    }

    public function view(User $user, Asset $asset): bool
    {
        return $user->is_active && ! $user->isBorrower();
    }

    public function create(User $user): bool
    {
        return GovernanceService::canManageAssets($user);
    }

    public function update(User $user, Asset $asset): bool
    {
        return GovernanceService::canManageAssets($user);
    }

    public function delete(User $user, Asset $asset): bool
    {
        return $user->is_active
            && in_array($user->role, ['superadmin', 'admin'], true)
            && $asset->assetItems()->count() === 0
            && $asset->bulkStocks()->count() === 0;
    }
}
