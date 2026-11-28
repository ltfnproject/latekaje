<?php

namespace App\Filament\Resources\Procurements\Pages;

use App\Filament\Resources\Procurements\ProcurementResource;
use App\Models\AssetItem;
use App\Services\ProcurementService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Gate;
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

            $notification = Notification::make()
                ->title('Barang masuk & inventaris berhasil dibuat!')
                ->body("Batch {$this->record->batch_code}: {$result['units_created']} unit satuan + {$result['bulk_added']} stok pinjam massal otomatis masuk inventaris.")
                ->success();

            if ($result['units_created'] > 0 && Gate::allows('print', AssetItem::class)) {
                $notification->actions([
                    Action::make('printBatchQr')
                        ->label('Cetak QR Batch Ini')
                        ->button()
                        ->url(route('print.qr', ['procurement_id' => $this->record->id]), shouldOpenInNewTab: true),
                ])->persistent();
            }

            $notification->send();
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
