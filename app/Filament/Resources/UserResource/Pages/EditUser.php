<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource\UserResource;
use App\Services\ActivityLogger;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label(__('common.delete')),
        ];
    }

    protected function afterSave(): void
    {
        ActivityLogger::log(
            'user.updated',
            "Memperbarui akun pengguna {$this->record->name} ({$this->record->email})",
            $this->record,
            ['role' => $this->record->role, 'is_active' => $this->record->is_active],
        );
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
