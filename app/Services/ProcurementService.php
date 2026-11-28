<?php

namespace App\Services;

use App\Models\AssetItem;
use App\Models\BulkStock;
use App\Models\Consumable;
use App\Models\ConsumableTransaction;
use App\Models\Loan;
use App\Models\Procurement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
     * Generate seluruh Unit Fisik (serialized), tambah stok Wadah Massal (bulk_returnable),
     * & tambah stok Bahan Habis Pakai (consumables) secara atomik dari satu batch Barang Masuk.
     *
     * @return array{units_created: int, bulk_added: int, consumables_added: int}
     *
     * @throws ValidationException
     */
    public static function generateInventory(Procurement $procurement): array
    {
        return DB::transaction(function () use ($procurement): array {
            /** @var Procurement $locked */
            $locked = Procurement::lockForUpdate()
                ->with(['items.asset.category', 'consumableItems.consumable', 'fundingSource'])
                ->findOrFail($procurement->getKey());

            if ($locked->isGenerated()) {
                throw ValidationException::withMessages([
                    'generated_at' => 'Unit / stok untuk batch barang masuk ini sudah pernah digenerate.',
                ]);
            }

            if ($locked->items->isEmpty() && $locked->consumableItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Tambahkan minimal 1 baris Aset atau Bahan Habis Pakai sebelum menyimpan batch.',
                ]);
            }

            $unitsCreated = 0;
            $bulkAdded = 0;
            $consumablesAdded = 0;

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

            foreach ($locked->consumableItems as $cLine) {
                $consumable = $cLine->consumable;
                if (! $consumable) {
                    continue;
                }

                $qty = max(1, (int) $cLine->quantity);

                ConsumableService::recordTransaction(
                    $consumable,
                    ConsumableTransaction::TYPE_IN,
                    $qty,
                    [
                        'procurement_id' => $locked->id,
                        'funding_source_id' => $locked->funding_source_id,
                        'unit_price' => $cLine->unit_price,
                        'purpose' => trim("Barang Masuk {$locked->batch_code}".(filled($cLine->notes) ? " — {$cLine->notes}" : '')),
                    ],
                );

                $consumablesAdded += $qty;
            }

            $locked->update(['generated_at' => now()]);

            ActivityLogger::log(
                'procurement.generated',
                "Generate inventaris batch {$locked->batch_code} ({$unitsCreated} unit satuan, {$bulkAdded} stok massal, {$consumablesAdded} stok BHP)",
                $locked,
                [
                    'batch_code' => $locked->batch_code,
                    'entry_type' => $locked->entry_type,
                    'funding_source' => $locked->fundingSource?->name,
                    'units_created' => $unitsCreated,
                    'bulk_added' => $bulkAdded,
                    'consumables_added' => $consumablesAdded,
                ],
            );

            return [
                'units_created' => $unitsCreated,
                'bulk_added' => $bulkAdded,
                'consumables_added' => $consumablesAdded,
            ];
        });
    }

    /**
     * Batalkan hasil generate sebuah batch (hapus seluruh unit fisik batch, kurangi stok wadah massal,
     * dan kembalikan stok Bahan Habis Pakai) secara atomik agar batch bisa diedit ulang atau dihapus.
     *
     * @return array{units_deleted: int, bulk_reverted: int, consumables_reverted: int}
     *
     * @throws ValidationException
     */
    public static function rollbackGeneration(Procurement $procurement): array
    {
        return DB::transaction(function () use ($procurement): array {
            /** @var Procurement $locked */
            $locked = Procurement::lockForUpdate()
                ->with(['items.asset', 'consumableItems.consumable'])
                ->findOrFail($procurement->getKey());

            if (! $locked->isGenerated()) {
                return [
                    'units_deleted' => 0,
                    'bulk_reverted' => 0,
                    'consumables_reverted' => 0,
                ];
            }

            // 1. Validasi unit fisik satuan dari batch ini
            $units = AssetItem::query()
                ->where('procurement_id', $locked->id)
                ->lockForUpdate()
                ->get();

            $unitIds = $units->pluck('id')->all();

            if ($unitIds !== []) {
                $borrowedUnits = $units->filter(fn (AssetItem $u): bool => $u->status === AssetItem::STATUS_BORROWED);
                $activeLoanUnitIds = Loan::query()
                    ->whereIn('asset_item_id', $unitIds)
                    ->where('status', 'aktif')
                    ->pluck('asset_item_id')
                    ->all();

                if ($borrowedUnits->isNotEmpty() || $activeLoanUnitIds !== []) {
                    $sampleQr = $borrowedUnits->first()?->nomor_seri_atau_qr
                        ?? $units->firstWhere('id', $activeLoanUnitIds[0] ?? null)?->nomor_seri_atau_qr
                        ?? '-';

                    throw ValidationException::withMessages([
                        'procurement' => "Batch {$locked->batch_code} tidak dapat dibatalkan karena masih ada unit yang sedang dipinjam (cth: {$sampleQr}). Kembalikan pinjaman terlebih dahulu.",
                    ]);
                }

                $externalMounted = AssetItem::query()
                    ->whereIn('parent_asset_item_id', $unitIds)
                    ->whereNotIn('id', $unitIds)
                    ->first();

                if ($externalMounted) {
                    throw ValidationException::withMessages([
                        'procurement' => "Batch {$locked->batch_code} tidak dapat dibatalkan karena unit {$externalMounted->nomor_seri_atau_qr} dari luar batch sedang terpasang pada perangkat di batch ini. Copot komponen tersebut lebih dahulu.",
                    ]);
                }
            }

            // 2. Validasi & kumpulkan pengurangan Wadah Pinjam Massal (bulk_stocks)
            $bulkReverts = [];
            foreach ($locked->items as $line) {
                $asset = $line->asset;
                if (! $asset || ! $asset->isBulkReturnable()) {
                    continue;
                }

                $qty = max(1, (int) $line->quantity);
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
                    continue;
                }

                $isDamaged = in_array($line->initial_condition, ['rusak', 'rusak_total'], true);
                $freeQty = max(0, (int) $bulk->total_qty - (int) $bulk->borrowed_qty);

                if ($freeQty < $qty) {
                    throw ValidationException::withMessages([
                        'procurement' => "Stok bebas wadah {$bulk->label} ({$freeQty}) lebih kecil dari jumlah batch ({$qty}) karena sebagian sedang dipinjam. Kembalikan pinjaman terlebih dahulu.",
                    ]);
                }

                $bulkReverts[] = [
                    'bulk' => $bulk,
                    'qty' => $qty,
                    'is_damaged' => $isDamaged,
                ];
            }

            // 3. Validasi pengembalian stok Bahan Habis Pakai (consumables)
            $consumableTxs = ConsumableTransaction::query()
                ->where('procurement_id', $locked->id)
                ->where('type', ConsumableTransaction::TYPE_IN)
                ->lockForUpdate()
                ->get();

            $consumableDeltas = [];
            foreach ($consumableTxs->groupBy('consumable_id') as $consumableId => $txGroup) {
                $addedQty = (int) $txGroup->sum('quantity');
                /** @var Consumable|null $consumable */
                $consumable = Consumable::lockForUpdate()->find($consumableId);
                if (! $consumable) {
                    continue;
                }

                if ((int) $consumable->stock < $addedQty) {
                    throw ValidationException::withMessages([
                        'procurement' => "Stok Bahan Habis Pakai '{$consumable->name}' saat ini tersisa {$consumable->stock} {$consumable->unit}, lebih kecil dari jumlah masuk batch ({$addedQty} {$consumable->unit}) karena sebagian sudah dipakai.",
                    ]);
                }

                $consumableDeltas[] = [
                    'consumable' => $consumable,
                    'qty' => $addedQty,
                ];
            }

            // Semua pengecekan aman → eksekusi rollback atomik
            $unitsDeleted = 0;
            if ($unitIds !== []) {
                // Lepas relasi parent internal sebelum hapus massal
                AssetItem::whereIn('id', $unitIds)->update(['parent_asset_item_id' => null]);
                $unitsDeleted = AssetItem::whereIn('id', $unitIds)->delete();
            }

            $bulkReverted = 0;
            foreach ($bulkReverts as $entry) {
                /** @var BulkStock $bulk */
                $bulk = $entry['bulk']->fresh();
                if (! $bulk) {
                    continue;
                }
                $qty = (int) $entry['qty'];

                $newTotal = max(0, (int) $bulk->total_qty - $qty);
                $newAvail = (int) $bulk->available_qty;
                $newDamaged = (int) $bulk->damaged_qty;

                if ($entry['is_damaged']) {
                    $takeDamaged = min($newDamaged, $qty);
                    $newDamaged -= $takeDamaged;
                    $remainder = $qty - $takeDamaged;
                    $newAvail = max(0, $newAvail - $remainder);
                } else {
                    $takeAvail = min($newAvail, $qty);
                    $newAvail -= $takeAvail;
                    $remainder = $qty - $takeAvail;
                    $newDamaged = max(0, $newDamaged - $remainder);
                }

                if ($newTotal === 0 && (int) $bulk->borrowed_qty === 0 && $newAvail === 0 && $newDamaged === 0) {
                    $bulk->delete();
                } else {
                    $bulk->update([
                        'total_qty' => $newTotal,
                        'available_qty' => $newAvail,
                        'damaged_qty' => $newDamaged,
                    ]);
                }

                $bulkReverted += $qty;
            }

            $consumablesReverted = 0;
            foreach ($consumableDeltas as $entry) {
                /** @var Consumable $consumable */
                $consumable = $entry['consumable'];
                $qty = (int) $entry['qty'];
                $consumable->decrement('stock', $qty);
                $consumablesReverted += $qty;
            }

            if ($consumableTxs->isNotEmpty()) {
                ConsumableTransaction::whereIn('id', $consumableTxs->pluck('id')->all())->delete();
            }

            $locked->update(['generated_at' => null]);

            ActivityLogger::log(
                'procurement.rolled_back',
                "Membatalkan generate batch {$locked->batch_code} (menghapus {$unitsDeleted} unit satuan, mengembalikan {$bulkReverted} stok massal & {$consumablesReverted} stok BHP)",
                $locked,
                [
                    'batch_code' => $locked->batch_code,
                    'units_deleted' => $unitsDeleted,
                    'bulk_reverted' => $bulkReverted,
                    'consumables_reverted' => $consumablesReverted,
                ],
            );

            return [
                'units_deleted' => $unitsDeleted,
                'bulk_reverted' => $bulkReverted,
                'consumables_reverted' => $consumablesReverted,
            ];
        });
    }

    /**
     * Batalkan dan hapus total satu batch Barang Masuk beserta seluruh unit fisik, stok massal,
     * stok BHP, dan file foto privatnya secara atomik.
     *
     * @return array{units_deleted: int, bulk_reverted: int, consumables_reverted: int}
     *
     * @throws ValidationException
     */
    public static function cancelAndDelete(Procurement $procurement): array
    {
        $photosToDelete = [];
        $batchCode = $procurement->batch_code;

        $summary = DB::transaction(function () use ($procurement, &$photosToDelete, &$batchCode): array {
            /** @var Procurement $locked */
            $locked = Procurement::lockForUpdate()->findOrFail($procurement->getKey());
            $batchCode = $locked->batch_code;
            $photosToDelete = is_array($locked->photo_paths) ? $locked->photo_paths : [];

            $result = self::rollbackGeneration($locked);

            ActivityLogger::log(
                'procurement.cancelled',
                "Membatalkan & menghapus batch {$batchCode} ({$result['units_deleted']} unit dihapus, {$result['bulk_reverted']} stok massal & {$result['consumables_reverted']} stok BHP dikembalikan)",
                null,
                array_merge(['batch_code' => $batchCode], $result),
            );

            $locked->delete();

            return $result;
        });

        // Hapus file foto privat setelah transaksi DB berhasil commit
        foreach ($photosToDelete as $path) {
            if (is_string($path) && trim($path) !== '') {
                Storage::disk('local')->delete(trim($path));
            }
        }

        return $summary;
    }
}
