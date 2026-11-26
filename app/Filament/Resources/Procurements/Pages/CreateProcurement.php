<?php

namespace App\Filament\Resources\Procurements\Pages;

use App\Filament\Resources\Procurements\ProcurementResource;
use App\Services\ProcurementService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateProcurement extends CreateRecord
{
    protected static string $resource = ProcurementResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['photo_paths'] = ProcurementService::sanitizePhotos($data['photo_paths'] ?? []);

        return $data;
    }

    protected function afterCreate(): void
    {
        if (! $this->record || $this->record->isGenerated()) {
            return;
        }

        try {
            $result = ProcurementService::generateInventory($this->record);

            Notification::make()
                ->title('Barang masuk & inventaris berhasil dibuat!')
                ->body("Batch {$this->record->batch_code}: {$result['units_created']} unit satuan + {$result['bulk_added']} stok pinjam massal otomatis masuk inventaris.")
                ->success()
                ->send();
        } catch (ValidationException $e) {
            Notification::make()
                ->title('Batch tersimpan, namun unit belum digenerate')
                ->body(collect($e->errors())->flatten()->first() ?? $e->getMessage())
                ->warning()
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
