<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource\UserResource;
use App\Models\Member;
use App\Services\ActivityLogger;
use App\Services\MemberAccountSync;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected array $borrowerProfile = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label(__('common.delete')),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $member = $this->record->resolvedMember();

        $data['member_id'] = $member?->id ?? ($data['member_id'] ?? null);
        $data['member_type'] = $member?->member_type ?? Member::TYPE_STUDENT;
        $data['member_group'] = $member?->group;
        $data['member_birth_date'] = $member?->birth_date?->format('Y-m-d');

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
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
        }

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->record->isBorrower()) {
            MemberAccountSync::syncFromUser($this->record, $this->borrowerProfile);
        }

        ActivityLogger::log(
            'user.updated',
            "Memperbarui akun pengguna {$this->record->name} ({$this->record->email})",
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
