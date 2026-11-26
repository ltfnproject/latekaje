<?php

namespace App\Services;

use App\Models\AssetItem;
use App\Models\BulkStock;
use App\Models\Procurement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProcurementService
{
    /**
     * Sanitize any newly uploaded raw photos on private disk into `procurements/YYYY/MM/<uuid>.jpg`.
     *
     * @param  array<int, string>|null  $paths
     * @return list<string>
     */
    public static function sanitizePhotos(?array $paths): array
    {
        if (! is_array($paths) || $paths === []) {
            return [];
        }

        $clean = [];

        foreach ($paths as $path) {
            if (! is_string($path) || trim($path) === '') {
                continue;
            }

            $path = trim($path);

            // Sudah melewati sanitasi sebelumnya (berada di procurements/YYYY/MM/...)
            if (preg_match('#^procurements/\d{4}/\d{2}/[a-f0-9-]+\.jpg$#i', $path)) {
                $clean[] = $path;
                continue;
            }

            $clean[] = SecureImageService::sanitizeStoredImage($path, 'local', 'procurements');
        }

        return $clean;
    }

    /**
     * Generate seluruh Unit Fisik (serialized) & tambah stok Wadah Massal (bulk_returnable)
     * secara atomik dari satu batch Barang Masuk.
     *
     * @return array{units_created: int, bulk_added: int}
     *
     * @throws ValidationException
     */
    public static function generateInventory(Procurement $procurement): array
    {
        return DB::transaction(function () use ($procurement): array {
            /** @var Procurement $locked */
            $locked = Procurement::lockForUpdate()->with(['items.asset.category', 'fundingSource'])->findOrFail($procurement->getKey());

            if ($locked->isGenerated()) {
                throw ValidationException::withMessages([
                    'generated_at' => 'Unit / stok untuk batch barang masuk ini sudah pernah digenerate.',
                ]);
            }

            if ($locked->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Tambahkan minimal 1 baris barang sebelum generate unit/stok.',
                ]);
            }

            $unitsCreated = 0;
            $bulkAdded = 0;

            foreach ($locked->items as $line) {
                $asset = $line->asset;
                if (! $asset) {
                    continue;
                }

                $qty = max(1, (int) $line->quantity);

                if ($asset->isBulkReturnable()) {
                    $spot = filled($line->storage_spot) ? trim((string) $line->storage_spot) : null;

                    $bulk = BulkStock::query()
                        ->where('asset_id', $asset->id)
                        ->where('location_id', $line->location_id)
                        ->where(function ($q) use ($spot): void {
                            if ($spot === null) {
                                $q->whereNull('storage_spot');
                            } else {
                                $q->where('storage_spot', $spot);
                            }
                        })
                        ->lockForUpdate()
                        ->first();

                    if (! $bulk) {
                        $bulk = BulkStock::create([
                            'asset_id' => $asset->id,
                            'location_id' => $line->location_id,
                            'storage_spot' => $spot,
                            'container_qr' => AssetCodeGenerator::nextUnitQr($asset),
                            'label' => trim($asset->nama_alat.($spot ? " ({$spot})" : '')),
                            'total_qty' => 0,
                            'available_qty' => 0,
                            'borrowed_qty' => 0,
                            'damaged_qty' => 0,
                        ]);
                    }

                    $isDamaged = in_array($line->initial_condition, ['rusak', 'rusak_total'], true);

                    $bulk->increment('total_qty', $qty);
                    if ($isDamaged) {
                        $bulk->increment('damaged_qty', $qty);
                    } else {
                        $bulk->increment('available_qty', $qty);
                    }

                    $bulkAdded += $qty;
                } else {
                    $factorySerials = collect(preg_split('/\r\n|\r|\n/', (string) ($line->serial_numbers_text ?? '')))
                        ->map(fn ($s) => trim((string) $s))
                        ->filter()
                        ->values()
                        ->all();

                    $units = AssetItemService::bulkCreate(
                        asset: $asset,
                        qty: $qty,
                        locationId: (int) $line->location_id,
                        kondisi: (string) ($line->initial_condition ?: 'baik'),
                        status: (string) ($line->initial_status ?: AssetItem::STATUS_AVAILABLE),
                        serials: [],
                        storageSpot: $line->storage_spot,
                        procurementId: $locked->id,
                        purchasePrice: $line->unit_price,
                        factorySerials: $factorySerials,
                    );

                    $unitsCreated += $units->count();
                }
            }

            $locked->update(['generated_at' => now()]);

            ActivityLogger::log(
                'procurement.generated',
                "Generate inventaris batch {$locked->batch_code} ({$unitsCreated} unit satuan, {$bulkAdded} stok massal)",
                $locked,
                [
                    'batch_code' => $locked->batch_code,
                    'entry_type' => $locked->entry_type,
                    'funding_source' => $locked->fundingSource?->name,
                    'units_created' => $unitsCreated,
                    'bulk_added' => $bulkAdded,
                ],
            );

            return [
                'units_created' => $unitsCreated,
                'bulk_added' => $bulkAdded,
            ];
        });
    }
}
