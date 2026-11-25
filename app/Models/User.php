<?php

namespace App\Models;

use App\Services\ActivityLogger;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'code', 'member_id', 'password', 'role', 'is_active', 'must_change_password', 'permissions'])]
class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'code',
        'member_id',
        'password',
        'role',
        'is_active',
        'must_change_password',
        'permissions',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'permissions' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (User $user): void {
            if ($user->isDirty('password') && ! $user->isDirty('must_change_password')) {
                $user->must_change_password = false;
            }
        });

        static::updated(function (User $user): void {
            if ($user->wasChanged('is_active') && ! $user->is_active) {
                try {
                    DB::table('sessions')->where('user_id', $user->id)->delete();
                } catch (\Throwable) {
                    // Ignore when session driver is not database.
                }

                ActivityLogger::log(
                    'user.deactivated',
                    "Menonaktifkan akun {$user->name} ({$user->email})",
                    $user,
                    ['target_role' => $user->role],
                );
            }
        });
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->is_active;
    }

    public function member(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function isSuperadmin(): bool
    {
        return $this->role === 'superadmin';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isKkk(): bool
    {
        return $this->isAdmin();
    }

    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }

    public function isToolman(): bool
    {
        return $this->isStaff();
    }

    public function isAssistant(): bool
    {
        return $this->role === 'assistant';
    }

    public function isBorrower(): bool
    {
        return $this->role === 'users';
    }

    public function isPersonalBorrower(): bool
    {
        return $this->isBorrower() && (filled($this->code) || filled($this->member_id));
    }

    public function isGenericKiosk(): bool
    {
        return $this->isBorrower() && blank($this->code) && blank($this->member_id);
    }

    public function canApproveLoans(): bool
    {
        return in_array($this->role, ['superadmin', 'admin', 'staff'], true);
    }

    public function canViewFinancials(): bool
    {
        return in_array($this->role, ['superadmin', 'admin'], true);
    }

    public function canPrintQr(): bool
    {
        return $this->is_active && ! $this->isBorrower();
    }

    public function ownsLoan(Loan $loan): bool
    {
        if (! $this->isBorrower()) {
            return true;
        }

        if ($this->code && $loan->code === $this->code) {
            return true;
        }

        return (bool) ($this->member_id && (int) $loan->member_id === (int) $this->member_id);
    }
}
