<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource\UserResource;
use App\Services\ActivityLogger;
use App\Services\MemberAccountSync;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected array $borrowerProfile = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->borrowerProfile = [
            'member_id' => $data['member_id'] ?? null,
            'member_type' => $data['member_type'] ?? null,
            'group' => $data['member_group'] ?? null,
            'birth_date' => $data['member_birth_date'] ?? null,
        ];

        unset($data['member_type'], $data['member_group'], $data['member_birth_date']);

        if (($data['role'] ?? 'users') !== 'users') {
            $data['member_id'] = null;
        } elseif (blank($data['password'] ?? null)) {
            $initialPassword = null;

            if (filled($this->borrowerProfile['birth_date'])) {
                $initialPassword = Carbon::parse((string) $this->borrowerProfile['birth_date'])->format('dmY');
            } elseif (filled($data['code'] ?? null)) {
                $initialPassword = trim((string) $data['code']);
            }

            if (filled($initialPassword)) {
                $data['password'] = Hash::make($initialPassword);
                $data['must_change_password'] = true;
            }
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->record->isBorrower()) {
            MemberAccountSync::syncFromUser($this->record, $this->borrowerProfile);
        }

        ActivityLogger::log(
            'user.created',
            "Membuat akun pengguna {$this->record->name} ({$this->record->email})",
            $this->record,
            [
                'role' => $this->record->role,
                'member_type' => $this->record->memberType(),
                'group' => $this->record->borrowerGroup(),
                'is_active' => $this->record->is_active,
            ],
        );
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
