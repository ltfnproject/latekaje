<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetItem;
use App\Models\BulkStock;
use App\Models\Procurement;

class AssetCodeGenerator
{
    /**
     * Generate kode katalog aset otomatis: TJKT-<KAT>-### (cth: TJKT-NET-001).
     */
    public static function nextAssetCode(?AssetCategory $category = null): string
    {
        $catCode = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) ($category?->code ?? 'GEN')) ?: 'GEN');
        $prefix = "TJKT-{$catCode}-";

        $existing = Asset::query()
            ->where('kode_aset', 'like', $prefix.'%')
            ->pluck('kode_aset');

        $max = 0;
        foreach ($existing as $code) {
            if (preg_match('/-(\d+)$/', (string) $code, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return $prefix.str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Generate kode QR pendek unit/wadah: TJKT-<KAT>-#### (12-13 karakter, ideal untuk stiker Micro 12x12mm & USB Scanner).
     */
    public static function nextUnitQr(?Asset $asset = null, ?AssetCategory $fallbackCategory = null): string
    {
        $category = $asset?->category ?? $fallbackCategory;

        if (! $category && $asset?->category_id) {
            $category = AssetCategory::find($asset->category_id);
        }

        if (! $category) {
            return AssetItemCode::next();
        }

        $catCode = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $category->code) ?: 'GEN');
        $prefix = "TJKT-{$catCode}-";

        $unitCodes = AssetItem::query()
            ->where('nomor_seri_atau_qr', 'like', $prefix.'%')
            ->pluck('nomor_seri_atau_qr');

        $bulkCodes = BulkStock::query()
            ->where('container_qr', 'like', $prefix.'%')
            ->pluck('container_qr');

        $max = 0;
        foreach ($unitCodes->merge($bulkCodes) as $code) {
            if (preg_match('/-(\d+)$/', (string) $code, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return $prefix.str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Generate kode batch Barang Masuk: BM-YYYY-#### (cth: BM-2026-0001).
     */
    public static function nextProcurementBatchCode(): string
    {
        $prefix = 'BM-'.date('Y').'-';

        $existing = Procurement::query()
            ->where('batch_code', 'like', $prefix.'%')
            ->pluck('batch_code');

        $max = 0;
        foreach ($existing as $code) {
            if (preg_match('/-(\d+)$/', (string) $code, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return $prefix.str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }
}
