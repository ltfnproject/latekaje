<?php

namespace App\Filament\Resources\Procurements\Pages;

use App\Filament\Resources\Procurements\ProcurementResource;
use App\Services\ProcurementService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditProcurement extends EditRecord
{
    protected static string $resource = ProcurementResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['photo_paths'] = ProcurementService::sanitizePhotos($data['photo_paths'] ?? []);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateInventory')
                ->label('Generate Unit & Stok Sekarang')
                ->icon('heroicon-o-bolt')
                ->color('success')
                ->visible(fn (): bool => ! $this->record->isGenerated())
                ->requiresConfirmation()
                ->action(function (): void {
                    try {
                        $result = ProcurementService::generateInventory($this->record);
                        Notification::make()
                            ->title('Inventaris berhasil digenerate')
                            ->body("Dibuat {$result['units_created']} unit satuan & +{$result['bulk_added']} stok pinjam massal.")
                            ->success()
                            ->send();
                        $this->refreshFormData(['generated_at']);
                    } catch (ValidationException $e) {
                        Notification::make()
                            ->title('Gagal generate inventaris')
                            ->body(collect($e->errors())->flatten()->first() ?? $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            DeleteAction::make()->label(__('common.delete')),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
