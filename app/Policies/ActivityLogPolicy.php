<?php

namespace App\Policies;

use App\Models\ActivityLog;
use App\Models\Loan;
use App\Models\User;

class ActivityLogPolicy
{
    public const OPERATIONAL_PREFIXES = [
        'loan.',
        'asset_item.',
        'bulk_stock.',
        'consumable.',
    ];

    public function viewAny(User $user): bool
    {
        return (bool) $user->is_active;
    }

    public function view(User $user, ActivityLog $activityLog): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if (in_array($user->role, ['superadmin', 'admin'], true)) {
            return true;
        }

        if (in_array($user->role, ['staff', 'assistant'], true)) {
            foreach (self::OPERATIONAL_PREFIXES as $prefix) {
                if (str_starts_with((string) $activityLog->action, $prefix)) {
                    return true;
                }
            }

            return false;
        }

        // Peminjam hanya boleh melihat log pinjaman miliknya sendiri
        if ($user->isBorrower() && str_starts_with((string) $activityLog->action, 'loan.') && $activityLog->subject_type === 'Loan' && $activityLog->subject_id) {
            $loan = Loan::find($activityLog->subject_id);

            return $loan !== null && $user->ownsLoan($loan);
        }

        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ActivityLog $activityLog): bool
    {
        return false;
    }

    public function delete(User $user, ActivityLog $activityLog): bool
    {
        return false;
    }

    public function restore(User $user, ActivityLog $activityLog): bool
    {
        return false;
    }

    public function forceDelete(User $user, ActivityLog $activityLog): bool
    {
        return false;
    }
}
