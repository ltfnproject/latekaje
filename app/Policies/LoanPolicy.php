<?php

namespace App\Policies;

use App\Models\Loan;
use App\Models\User;

class LoanPolicy
{
    public function viewAny(?User $user): bool
    {
        return $user !== null && $user->is_active;
    }

    public function view(?User $user, Loan $loan): bool
    {
        if (! $user || ! $user->is_active) {
            return false;
        }

        if (! $user->isBorrower()) {
            return true;
        }

        return $user->ownsLoan($loan);
    }

    public function create(?User $user): bool
    {
        return $user !== null && $user->is_active && ! $user->isSuperadmin();
    }

    public function update(User $user, Loan $loan): bool
    {
        return $user->is_active && in_array($user->role, ['superadmin', 'admin', 'staff'], true);
    }

    public function delete(User $user, Loan $loan): bool
    {
        return $user->is_active && in_array($user->role, ['superadmin', 'admin'], true);
    }

    public function approve(User $user, ?Loan $loan = null): bool
    {
        return $user->is_active && $user->canApproveLoans();
    }
}
