<?php

namespace App\Filament\Imports;

use App\Models\Member;
use App\Services\MemberAccountSync;
use Carbon\Carbon;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class MemberImporter extends Importer
{
    protected static ?string $model = Member::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('code')
                ->label(__('members.import_col_code'))
                ->rules(['nullable', 'string', 'max:64'])
                ->examples(['1001', ''])
                ->fillRecordUsing(fn () => null),

            ImportColumn::make('name')
                ->label(__('members.import_col_name'))
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255'])
                ->examples(['Budi Santoso'])
                ->fillRecordUsing(fn () => null),

            ImportColumn::make('group')
                ->label(__('members.import_col_group'))
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255'])
                ->examples(['X TJKT 1', 'GURU', 'KARYAWAN'])
                ->fillRecordUsing(fn () => null),

            ImportColumn::make('member_type')
                ->label(__('members.import_col_member_type'))
                ->rules(['nullable', 'string', 'max:20'])
                ->examples(['student', 'teacher', 'employee'])
                ->fillRecordUsing(fn () => null),

            ImportColumn::make('birth_date')
                ->label(__('members.import_col_birth_date'))
                ->rules(['nullable', 'string', 'max:32'])
                ->examples(['2009-05-14', '14/05/2009'])
                ->fillRecordUsing(fn () => null),

            ImportColumn::make('password')
                ->label(__('members.import_col_password'))
                ->rules(['nullable', 'string', 'max:255'])
                ->examples(['14052009'])
                ->fillRecordUsing(fn () => null),
        ];
    }

    public function resolveRecord(): ?Member
    {
        $name = trim((string) ($this->data['name'] ?? ''));
        $rawGroup = trim((string) ($this->data['group'] ?? ''));
        $code = trim((string) ($this->data['code'] ?? ''));
        $memberType = MemberAccountSync::normalizeMemberType((string) ($this->data['member_type'] ?? ''));
        $birthDate = self::parseBirthDate((string) ($this->data['birth_date'] ?? ''));

        if ($name === '' || $rawGroup === '') {
            return null;
        }

        $group = MemberAccountSync::ensureGroupExists($rawGroup) ?? $rawGroup;

        if ($code !== '') {
            $member = Member::firstOrNew(['code' => $code]);
            $member->fill([
                'name' => $name,
                'group' => $group,
                'member_type' => $memberType,
                'birth_date' => $birthDate,
                'aktif' => true,
            ]);
            $member->save();

            $explicitPassword = trim((string) ($this->data['password'] ?? ''));

            MemberAccountSync::provisionAccountForMember(
                $member,
                explicitPassword: $explicitPassword !== '' ? $explicitPassword : null,
                forcePasswordChange: true,
                resetExistingPassword: false,
            );

            return $member;
        }

        return new Member([
            'name' => $name,
            'group' => $group,
            'member_type' => $memberType,
            'birth_date' => $birthDate,
            'aktif' => true,
        ]);
    }

    private static function parseBirthDate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'dmY'] as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $raw);
                if ($parsed !== false) {
                    return $parsed->format('Y-m-d');
                }
            } catch (\Throwable) {
                // try next format
            }
        }

        try {
            return Carbon::parse($raw)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = __('members.import_completed', ['success' => number_format($import->successful_rows)]);

        if ($failed = $import->getFailedRowsCount()) {
            $body .= __('members.import_failed_suffix', ['failed' => number_format($failed)]);
        }

        return $body.'.';
    }
}
