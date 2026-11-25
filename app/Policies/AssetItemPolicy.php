<?php

namespace App\Policies;

use App\Models\AssetItem;
use App\Models\User;

class AssetItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && ! $user->isBorrower();
    }

    public function view(User $user, AssetItem $assetItem): bool
    {
        return $user->is_active && ! $user->isBorrower();
    }

    public function create(User $user): bool
    {
        return $user->is_active && in_array($user->role, ['superadmin', 'admin', 'staff', 'assistant'], true);
    }

    public function update(User $user, AssetItem $assetItem): bool
    {
        return $user->is_active && in_array($user->role, ['superadmin', 'admin', 'staff', 'assistant'], true);
    }

    public function print(User $user): bool
    {
        return $user->canPrintQr();
    }

    public function delete(User $user, AssetItem $assetItem): bool
    {
        // Hanya Superadmin & KKK (admin) yang boleh hapus unit, bila tidak ada pinjaman AKTIF.
        // Toolman (staff) dan Admin Lab/Prakerin (assistant) tidak boleh menghapus permanen.
        return $user->is_active
            && in_array($user->role, ['superadmin', 'admin'], true)
            && ! $assetItem->loans()->where('status', 'aktif')->exists();
    }
}
