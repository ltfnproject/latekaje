<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;

class GovernanceService
{
    public const KEY_DELEGATION_ENABLED = 'delegation_toolman_enabled';
    public const KEY_DELEGATION_UNTIL = 'delegation_toolman_until';
    public const KEY_PROCUREMENT_STRICT = 'procurement_strict_mode';

    public static function isToolmanDelegationActive(): bool
    {
        if (! Setting::boolean(self::KEY_DELEGATION_ENABLED, false)) {
            return false;
        }

        $until = self::toolmanDelegationUntil();

        if ($until !== null && $until->isPast()) {
            Setting::set(self::KEY_DELEGATION_ENABLED, '0');
            Setting::set(self::KEY_DELEGATION_UNTIL, '');

            return false;
        }

        return true;
    }

    public static function toolmanDelegationUntil(): ?Carbon
    {
        $raw = trim((string) Setting::get(self::KEY_DELEGATION_UNTIL, ''));

        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function setToolmanDelegation(bool $enabled, ?int $durationHours = null, ?User $actor = null): void
    {
        Setting::set(self::KEY_DELEGATION_ENABLED, $enabled ? '1' : '0');

        $until = null;
        if ($enabled && $durationHours !== null && $durationHours > 0) {
            $until = now()->addHours($durationHours);
            Setting::set(self::KEY_DELEGATION_UNTIL, $until->toIso8601String());
        } else {
            Setting::set(self::KEY_DELEGATION_UNTIL, '');
        }

        ActivityLogger::log(
            $enabled ? 'governance.delegation_on' : 'governance.delegation_off',
            $enabled
                ? ('Menyalakan delegasi KKK → Toolman'.($until ? ' sampai '.$until->format('d M Y H:i') : ' (manual OFF)'))
                : 'Mematikan delegasi KKK → Toolman',
            null,
            ['enabled' => $enabled, 'until' => $until?->toIso8601String()],
            $actor,
        );
    }

    public static function canManageAssets(?User $user): bool
    {
        if (! $user || ! $user->is_active) {
            return false;
        }

        if ($user->isSuperadmin() || $user->isKkk()) {
            return true;
        }

        return $user->isToolman() && self::isToolmanDelegationActive();
    }

    public static function canManageProcurements(?User $user): bool
    {
        return self::canManageAssets($user);
    }

    public static function isProcurementStrictMode(): bool
    {
        return Setting::boolean(self::KEY_PROCUREMENT_STRICT, false);
    }

    public static function setProcurementStrictMode(bool $enabled, ?User $actor = null): void
    {
        Setting::set(self::KEY_PROCUREMENT_STRICT, $enabled ? '1' : '0');

        ActivityLogger::log(
            $enabled ? 'governance.procurement_strict_on' : 'governance.procurement_strict_off',
            $enabled
                ? 'Mengaktifkan Mode Ketat Barang Baru (wajib Sumber Anggaran, Tanggal, Foto & Harga)'
                : 'Mematikan Mode Ketat Barang Baru (mode input barang lama / opsional)',
            null,
            ['strict_mode' => $enabled],
            $actor,
        );
    }
}
