<?php

namespace App\Filament\Resources\AssetItems\Pages;

use App\Filament\Resources\AssetItems\AssetItemResource;
use App\Models\Asset;
use App\Models\AssetItem;
use App\Services\AssetItemService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAssetItem extends CreateRecord
{
    protected static string $resource = AssetItemResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $serials = collect(preg_split('/\r\n|\r|\n/', (string) ($data['sn_manual'] ?? '')))
            ->map(fn ($s) => trim((string) $s))
            ->filter()
            ->values()
            ->all();

        $typed = trim((string) ($data['nomor_seri_atau_qr'] ?? ''));

        if ($typed !== '' && ! in_array($typed, $serials, true)) {
            array_unshift($serials, $typed);
        }

        $qty = max((int) ($data['jumlah'] ?? 1), count($serials), 1);

        $asset = Asset::findOrFail($data['asset_id']);

        $purchasePrice = auth()->user()?->canViewFinancials() && filled($data['purchase_price'] ?? null)
            ? (int) $data['purchase_price']
            : null;

        $factorySerials = filled($data['serial_number_pabrik'] ?? null)
            ? [trim((string) $data['serial_number_pabrik'])]
            : [];

        $units = AssetItemService::bulkCreate(
            asset: $asset,
            qty: $qty,
            locationId: (int) $data['location_id'],
            kondisi: (string) ($data['kondisi'] ?? 'baik'),
            status: (string) ($data['status'] ?? AssetItem::STATUS_AVAILABLE),
            serials: $serials,
            storageSpot: $data['storage_spot'] ?? null,
            procurementId: null,
            purchasePrice: $purchasePrice,
            factorySerials: $factorySerials,
        );

        // Jika status awal terpasang dengan perangkat induk/catatan pada pembuatan 1 unit
        if ($units->count() === 1 && ($data['status'] ?? null) === AssetItem::STATUS_INSTALLED) {
            $units->first()->update([
                'parent_asset_item_id' => $data['parent_asset_item_id'] ?? null,
                'installed_note' => $data['installed_note'] ?? null,
                'installed_at' => now(),
            ]);
        }

        if ($units->count() > 1) {
            Notification::make()
                ->title($units->count().' unit berhasil dibuat')
                ->body('Kode: '.$units->first()->nomor_seri_atau_qr.' s/d '.$units->last()->nomor_seri_atau_qr)
                ->success()
                ->send();
        }

        return $units->first();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
