<?php

namespace App\Services;

use App\Models\AssetItem;
use App\Models\Location;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssetInstallationService
{
    /**
     * Pasang unit tetap ke ruangan (cth: Switch di Server Room) atau ke perangkat induk (cth: RAM/SSD di PC-04).
     *
     * @throws ValidationException
     */
    public static function install(
        AssetItem $item,
        ?int $locationId = null,
        ?int $parentItemId = null,
        ?string $storageSpot = null,
        ?string $note = null,
    ): AssetItem {
        return DB::transaction(function () use ($item, $locationId, $parentItemId, $storageSpot, $note): AssetItem {
            /** @var AssetItem $locked */
            $locked = AssetItem::lockForUpdate()->with('asset')->findOrFail($item->getKey());

            if ($locked->status === AssetItem::STATUS_BORROWED || $locked->activeLoan()->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'Unit sedang dipinjam, kembalikan dulu sebelum dipasang tetap.',
                ]);
            }

            if ($locked->status === AssetItem::STATUS_RETIRED) {
                throw ValidationException::withMessages([
                    'status' => 'Unit berstatus afkir tidak dapat dipasang.',
                ]);
            }

            $parent = null;
            if ($parentItemId) {
                if ((int) $parentItemId === (int) $locked->getKey()) {
                    throw ValidationException::withMessages([
                        'parent_asset_item_id' => 'Unit tidak boleh dipasang ke dirinya sendiri.',
                    ]);
                }

                $parent = AssetItem::with('asset', 'location')->findOrFail($parentItemId);
                $locationId = $locationId ?: $parent->location_id;
            }

            if (! $locationId) {
                $locationId = $locked->location_id;
            }

            $locked->update([
                'status' => AssetItem::STATUS_INSTALLED,
                'location_id' => $locationId,
                'storage_spot' => filled($storageSpot) ? trim($storageSpot) : $locked->storage_spot,
                'parent_asset_item_id' => $parent?->getKey(),
                'installed_at' => now(),
                'installed_note' => filled($note) ? trim($note) : null,
            ]);

            $locLabel = Location::whereKey($locationId)->value('label') ?? '-';
            $desc = $parent
                ? "Memasang {$locked->nomor_seri_atau_qr} ke perangkat induk {$parent->nomor_seri_atau_qr} ({$locLabel})"
                : "Memasang tetap {$locked->nomor_seri_atau_qr} di {$locLabel}";

            ActivityLogger::log(
                'asset_item.installed',
                $desc,
                $locked,
                [
                    'qr' => $locked->nomor_seri_atau_qr,
                    'location' => $locLabel,
                    'parent_id' => $parent?->getKey(),
                    'parent_qr' => $parent?->nomor_seri_atau_qr,
                    'note' => $locked->installed_note,
                ],
            );

            return $locked->fresh();
        });
    }

    /**
     * Copot barang terpasang kembali ke stok tersedia (default kembali ke Gudang TJKT).
     *
     * @throws ValidationException
     */
    public static function unmountToStock(
        AssetItem $item,
        ?int $targetLocationId = null,
        ?string $storageSpot = null,
        ?string $note = null,
    ): AssetItem {
        return DB::transaction(function () use ($item, $targetLocationId, $storageSpot, $note): AssetItem {
            /** @var AssetItem $locked */
            $locked = AssetItem::lockForUpdate()->with('parentItem')->findOrFail($item->getKey());

            if ($locked->status === AssetItem::STATUS_BORROWED) {
                throw ValidationException::withMessages([
                    'status' => 'Unit sedang dipinjam.',
                ]);
            }

            $prevParentQr = $locked->parentItem?->nomor_seri_atau_qr;
            $gudangId = Location::where('key', 'gudang')->value('id');
            $finalLocationId = $targetLocationId ?: $gudangId ?: $locked->location_id;

            $locked->update([
                'status' => AssetItem::STATUS_AVAILABLE,
                'location_id' => $finalLocationId,
                'storage_spot' => filled($storageSpot) ? trim($storageSpot) : $locked->storage_spot,
                'parent_asset_item_id' => null,
                'installed_at' => null,
                'installed_note' => filled($note) ? trim($note) : null,
            ]);

            $locLabel = Location::whereKey($finalLocationId)->value('label') ?? '-';

            ActivityLogger::log(
                'asset_item.unmounted',
                "Mencopot {$locked->nomor_seri_atau_qr}".($prevParentQr ? " dari {$prevParentQr}" : '')." kembali ke stok ({$locLabel})",
                $locked,
                [
                    'qr' => $locked->nomor_seri_atau_qr,
                    'previous_parent_qr' => $prevParentQr,
                    'target_location' => $locLabel,
                    'note' => $locked->installed_note,
                ],
            );

            return $locked->fresh();
        });
    }

    /**
     * Tandai unit sebagai Afkir / Penghapusan Aset (khusus Super Admin & KKK).
     *
     * @throws ValidationException
     */
    public static function retire(AssetItem $item, ?string $reason = null): AssetItem
    {
        return DB::transaction(function () use ($item, $reason): AssetItem {
            /** @var AssetItem $locked */
            $locked = AssetItem::lockForUpdate()->findOrFail($item->getKey());

            if ($locked->status === AssetItem::STATUS_BORROWED || $locked->activeLoan()->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'Unit sedang dipinjam, tidak dapat diafkirkan.',
                ]);
            }

            $locked->update([
                'status' => AssetItem::STATUS_RETIRED,
                'parent_asset_item_id' => null,
                'installed_note' => filled($reason) ? trim($reason) : $locked->installed_note,
            ]);

            ActivityLogger::log(
                'asset_item.retired',
                "Menandai unit {$locked->nomor_seri_atau_qr} sebagai Afkir".($reason ? " ({$reason})" : ''),
                $locked,
                ['qr' => $locked->nomor_seri_atau_qr, 'reason' => $reason],
            );

            return $locked->fresh();
        });
    }
}
