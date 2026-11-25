<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Member;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MemberAccountSync
{
    private static bool $syncing = false;

    public static function isSyncing(): bool
    {
        return self::$syncing;
    }

    /**
     * Run callback while suppressing recursive User <-> Member sync hooks.
     */
    public static function withoutSync(callable $callback): mixed
    {
        $prev = self::$syncing;
        self::$syncing = true;

        try {
            return $callback();
        } finally {
            self::$syncing = $prev;
        }
    }

    /**
     * Normalize & ensure a group label exists in `groups` master table so dropdowns never mismatch.
     */
    public static function ensureGroupExists(?string $label): ?string
    {
        $clean = trim((string) $label);

        if ($clean === '') {
            return null;
        }

        $existing = Group::query()
            ->whereRaw('LOWER(label) = ?', [mb_strtolower($clean)])
            ->first();

        if ($existing) {
            return $existing->label;
        }

        $key = Str::slug($clean, '_');
        if ($key === '') {
            $key = 'grup_'.substr(md5($clean), 0, 8);
        }

        $byKey = Group::where('key', $key)->first();
        if ($byKey) {
            return $byKey->label;
        }

        return Group::create([
            'key' => $key,
            'label' => $clean,
        ])->label;
    }

    public static function defaultGroupForType(string $memberType): string
    {
        $label = match ($memberType) {
            Member::TYPE_TEACHER => 'GURU',
            Member::TYPE_EMPLOYEE => 'KARYAWAN',
            default => Group::query()
                ->whereNotIn('key', ['guru', 'karyawan', 'guru_staf', 'tamu_eksternal'])
                ->orderBy('label')
                ->value('label') ?? 'X TJKT 1',
        };

        return self::ensureGroupExists($label) ?? $label;
    }

    public static function normalizeMemberType(?string $raw): string
    {
        return match (strtolower(trim((string) $raw))) {
            'teacher', 'guru' => Member::TYPE_TEACHER,
            'employee', 'karyawan', 'staf', 'staff' => Member::TYPE_EMPLOYEE,
            default => Member::TYPE_STUDENT,
        };
    }

    /**
     * Ensure every personal borrower user (`role=users`) has a synced `Member` record.
     *
     * @param  array{member_id?: int|string|null, member_type?: string|null, group?: string|null, birth_date?: mixed}  $profile
     */
    public static function syncFromUser(User $user, array $profile = []): ?Member
    {
        if (! $user->isBorrower()) {
            return null;
        }

        return self::withoutSync(function () use ($user, $profile): ?Member {
            $profileMemberId = isset($profile['member_id']) && $profile['member_id'] !== ''
                ? (int) $profile['member_id']
                : null;

            $member = null;

            if ($profileMemberId) {
                $member = Member::find($profileMemberId);
            }

            if (! $member && $user->member_id) {
                $member = Member::find($user->member_id);
            }

            $userCode = filled($user->code) ? trim((string) $user->code) : null;

            if (! $member && $userCode) {
                $member = Member::where('code', $userCode)->first();
            }

            $hasExplicitProfile = array_key_exists('member_type', $profile)
                || array_key_exists('group', $profile)
                || array_key_exists('birth_date', $profile)
                || $profileMemberId !== null;

            // Jangan paksa membuat Member untuk akun kiosk generik tanpa identitas & tanpa profil.
            if (! $member && ! $userCode && ! $hasExplicitProfile && $user->email === 'kiosk@latekaje.net') {
                return null;
            }

            $memberType = array_key_exists('member_type', $profile) && filled($profile['member_type'])
                ? self::normalizeMemberType((string) $profile['member_type'])
                : ($member?->member_type ?? Member::TYPE_STUDENT);

            $rawGroup = array_key_exists('group', $profile) && filled($profile['group'])
                ? (string) $profile['group']
                : ($member?->group ?? self::defaultGroupForType($memberType));

            $group = self::ensureGroupExists($rawGroup) ?? self::defaultGroupForType($memberType);

            $birthDate = $member?->birth_date;
            if (array_key_exists('birth_date', $profile)) {
                $rawDob = $profile['birth_date'];
                $birthDate = filled($rawDob)
                    ? ($rawDob instanceof Carbon ? $rawDob->format('Y-m-d') : Carbon::parse((string) $rawDob)->format('Y-m-d'))
                    : null;
            }

            if (! $member) {
                $member = new Member;
            }

            $resolvedCode = $userCode ?? $member->code;

            // Pastikan code tidak bentrok unique constraint dengan Member lain.
            if ($resolvedCode && Member::where('code', $resolvedCode)->when($member->exists, fn ($q) => $q->whereKeyNot($member->getKey()))->exists()) {
                $resolvedCode = $member->code;
            }

            $member->fill([
                'code' => $resolvedCode,
                'name' => $user->name,
                'member_type' => $memberType,
                'group' => $group,
                'birth_date' => $birthDate,
                'aktif' => (bool) $user->is_active,
            ]);
            $member->save();

            $userUpdates = [];
            if ((int) $user->member_id !== (int) $member->id) {
                $userUpdates['member_id'] = $member->id;
            }
            if (blank($user->code) && filled($member->code)) {
                $userUpdates['code'] = $member->code;
            }

            if ($userUpdates !== []) {
                $user->forceFill($userUpdates)->saveQuietly();
            }

            $user->setRelation('member', $member);

            return $member;
        });
    }

    /**
     * Sync basic identity fields (name, code, is_active) from User -> linked Member on model updates.
     */
    public static function syncBasicFieldsFromUser(User $user): void
    {
        if (self::$syncing || ! $user->member_id) {
            return;
        }

        self::withoutSync(function () use ($user): void {
            $member = Member::find($user->member_id);
            if (! $member) {
                return;
            }

            $updates = [
                'name' => $user->name,
                'aktif' => (bool) $user->is_active,
            ];

            if (filled($user->code) && $user->code !== $member->code) {
                $conflict = Member::where('code', $user->code)->whereKeyNot($member->id)->exists();
                if (! $conflict) {
                    $updates['code'] = trim((string) $user->code);
                }
            }

            $member->update($updates);
        });
    }

    /**
     * Sync basic identity fields (name, code, aktif -> is_active) from Member -> linked User(s).
     */
    public static function syncFromMember(Member $member): void
    {
        if (self::$syncing) {
            return;
        }

        self::withoutSync(function () use ($member): void {
            $query = User::query()->where('member_id', $member->id);

            if (filled($member->code)) {
                $query->orWhere(function ($q) use ($member): void {
                    $q->where('role', 'users')->where('code', $member->code);
                });
            }

            $users = $query->get();

            foreach ($users as $user) {
                $updates = [
                    'name' => $member->name,
                    'member_id' => $member->id,
                    'is_active' => (bool) $member->aktif,
                ];

                if (filled($member->code) && $user->code !== $member->code) {
                    $conflict = User::where('code', $member->code)->whereKeyNot($user->id)->exists();
                    if (! $conflict) {
                        $updates['code'] = $member->code;
                    }
                }

                $wasActive = (bool) $user->is_active;
                $user->forceFill($updates)->saveQuietly();

                if ($wasActive && ! $member->aktif) {
                    try {
                        DB::table('sessions')->where('user_id', $user->id)->delete();
                    } catch (\Throwable) {
                        // Ignore when session driver is not database.
                    }
                }
            }
        });
    }

    /**
     * Create or update a borrower login account for a Member using DDMMYYYY default password.
     * Existing user passwords are preserved unless $explicitPassword is provided or $resetExistingPassword is true.
     */
    public static function provisionAccountForMember(
        Member $member,
        ?string $explicitPassword = null,
        bool $forcePasswordChange = true,
        bool $resetExistingPassword = false,
    ): User {
        return self::withoutSync(function () use ($member, $explicitPassword, $forcePasswordChange, $resetExistingPassword): User {
            $code = filled($member->code) ? trim((string) $member->code) : null;

            $user = User::query()
                ->where('member_id', $member->id)
                ->when($code, fn ($q) => $q->orWhere(fn ($sub) => $sub->where('role', 'users')->where('code', $code)))
                ->first();

            $initialPassword = filled($explicitPassword)
                ? trim((string) $explicitPassword)
                : $member->defaultInitialPassword();

            if ($initialPassword === '') {
                $initialPassword = 'member'.str_pad((string) $member->id, 4, '0', STR_PAD_LEFT);
            }

            if ($user) {
                $updates = [
                    'name' => $member->name,
                    'member_id' => $member->id,
                    'is_active' => (bool) $member->aktif,
                ];

                if ($code && ! User::where('code', $code)->whereKeyNot($user->id)->exists()) {
                    $updates['code'] = $code;
                }

                if (filled($explicitPassword) || $resetExistingPassword) {
                    $updates['password'] = $initialPassword;
                    $updates['must_change_password'] = $forcePasswordChange;
                }

                $user->forceFill($updates)->save();

                return $user;
            }

            $baseLocal = $code ? strtolower(preg_replace('/[^a-zA-Z0-9._-]/', '', $code)) : 'member'.$member->id;
            $email = $baseLocal.'@member.latekaje';
            if (User::where('email', $email)->exists()) {
                $email = $baseLocal.'-'.$member->id.'@member.latekaje';
            }

            return User::create([
                'name' => $member->name,
                'email' => $email,
                'code' => $code,
                'member_id' => $member->id,
                'role' => 'users',
                'password' => $initialPassword,
                'is_active' => (bool) $member->aktif,
                'must_change_password' => $forcePasswordChange,
            ]);
        });
    }

    /**
     * Repair orphaned borrower users (`role=users` with `member_id=NULL`) and seed standard groups.
     */
    public static function repairOrphanedBorrowers(): int
    {
        self::ensureGroupExists('GURU');
        self::ensureGroupExists('KARYAWAN');

        // Normalisasi seluruh group yang sudah dipakai pada members agar terdaftar di tabel groups
        Member::query()->whereNotNull('group')->where('group', '!=', '')->chunkById(100, function ($members): void {
            foreach ($members as $member) {
                $normalized = self::ensureGroupExists($member->group);
                if ($normalized && $normalized !== $member->group) {
                    self::withoutSync(fn () => $member->update(['group' => $normalized]));
                }
            }
        });

        $repaired = 0;

        User::query()
            ->where('role', 'users')
            ->whereNull('member_id')
            ->where('email', '!=', 'kiosk@latekaje.net')
            ->chunkById(100, function ($users) use (&$repaired): void {
                foreach ($users as $user) {
                    // Tautkan bila sudah punya code atau bukan akun kiosk generik
                    if (filled($user->code) || ! config('latekaje.allow_generic_kiosk', false)) {
                        $member = self::syncFromUser($user);
                        if ($member) {
                            $repaired++;
                        }
                    }
                }
            });

        return $repaired;
    }
}
