<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetItem;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssetItemService
{
    /**
     * Buat N unit sekaligus untuk satu aset.
     * - $serials: override QR/kode manual bila diisi di form lama.
     * - $factorySerials: SN pabrik asli (disimpan di `serial_number_pabrik` sementara QR tetap kode pendek).
     */
    public static function bulkCreate(
        Asset $asset,
        int $qty,
        int $locationId,
        string $kondisi = 'baik',
        string $status = AssetItem::STATUS_AVAILABLE,
        array $serials = [],
        ?string $storageSpot = null,
        ?int $procurementId = null,
        ?int $purchasePrice = null,
        array $factorySerials = [],
    ): Collection {
        $qty = max(1, $qty);
        $serials = array_values(array_filter(array_map('trim', $serials)));
        $factorySerials = array_values(array_filter(array_map('trim', $factorySerials)));

        if (count($serials) > $qty) {
            throw ValidationException::withMessages([
                'sn_manual' => __('units.sn_manual_too_many', ['count' => count($serials), 'qty' => $qty]),
            ]);
        }

        if (count($factorySerials) > $qty) {
            throw ValidationException::withMessages([
                'serial_numbers_text' => __('units.sn_manual_too_many', ['count' => count($factorySerials), 'qty' => $qty]),
            ]);
        }

        $asset->loadMissing('category');

        return DB::transaction(function () use (
            $asset,
            $qty,
            $locationId,
            $kondisi,
            $status,
            $serials,
            $storageSpot,
            $procurementId,
            $purchasePrice,
            $factorySerials
        ) {
            $units = collect();

            for ($i = 0; $i < $qty; $i++) {
                $manualQr = $serials[$i] ?? null;
                $factorySn = $factorySerials[$i] ?? null;

                $units->push(self::createOne(
                    $asset,
                    $locationId,
                    $kondisi,
                    $status,
                    $manualQr,
                    $storageSpot,
                    $procurementId,
                    $purchasePrice,
                    $factorySn,
                ));
            }

            return $units;
        });
    }

    private static function createOne(
        Asset $asset,
        int $locationId,
        string $kondisi,
        string $status,
        ?string $manualQr,
        ?string $storageSpot,
        ?int $procurementId,
        ?int $purchasePrice,
        ?string $factorySn,
    ): AssetItem {
        $tries = 0;

        while (true) {
            $code = ($manualQr && $tries === 0)
                ? $manualQr
                : AssetCodeGenerator::nextUnitQr($asset);

            try {
                return AssetItem::create([
                    'asset_id' => $asset->getKey(),
                    'nomor_seri_atau_qr' => $code,
                    'serial_number_pabrik' => filled($factorySn) ? $factorySn : null,
                    'kondisi' => $kondisi,
                    'location_id' => $locationId,
                    'storage_spot' => filled($storageSpot) ? trim($storageSpot) : null,
                    'procurement_id' => $procurementId,
                    'purchase_price' => $purchasePrice,
                    'status' => $status,
                    'installed_at' => $status === AssetItem::STATUS_INSTALLED ? now() : null,
                ]);
            } catch (UniqueConstraintViolationException | QueryException $e) {
                if (! self::isDuplicate($e)) {
                    throw $e;
                }

                if ($manualQr && $tries === 0) {
                    throw ValidationException::withMessages([
                        'sn_manual' => __('units.sn_duplicate', ['serial' => $manualQr]),
                    ]);
                }

                if (++$tries > 10) {
                    throw $e;
                }
            }
        }
    }

    private static function isDuplicate(QueryException $e): bool
    {
        return $e instanceof UniqueConstraintViolationException
            || str_contains($e->getMessage(), 'Duplicate entry');
    }
}
