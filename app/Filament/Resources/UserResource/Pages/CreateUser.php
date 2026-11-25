<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource\UserResource;
use App\Services\ActivityLogger;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        ActivityLogger::log(
            'user.created',
            "Membuat akun pengguna {$this->record->name} ({$this->record->email})",
            $this->record,
            ['role' => $this->record->role, 'is_active' => $this->record->is_active],
        );
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
