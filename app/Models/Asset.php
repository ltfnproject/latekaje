<?php

namespace App\Models;

use App\Services\AssetCodeGenerator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    use HasFactory;

    public const MODE_SERIALIZED = 'serialized';
    public const MODE_BULK_RETURNABLE = 'bulk_returnable';

    protected $fillable = [
        'category_id',
        'kode_aset',
        'nama_alat',
        'jenis',
        'spesifikasi',
        'kegunaan',
        'tracking_mode',
        'requires_approval',
        'default_qr_preset',
        'stok',
    ];

    protected function casts(): array
    {
        return [
            'stok' => 'integer',
            'requires_approval' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Asset $asset): void {
            $category = $asset->category_id ? AssetCategory::find($asset->category_id) : null;

            if ($category) {
                if (blank($asset->jenis)) {
                    $asset->jenis = $category->name;
                }
                if (blank($asset->tracking_mode)) {
                    $asset->tracking_mode = $category->default_tracking_mode;
                }
                if (blank($asset->default_qr_preset)) {
                    $asset->default_qr_preset = $category->default_qr_preset;
                }
            }

            if (blank($asset->kode_aset)) {
                $asset->kode_aset = AssetCodeGenerator::nextAssetCode($category);
            }

            if (blank($asset->kegunaan)) {
                $asset->kegunaan = 'umum';
            }
        });
    }

    public static function trackingModeOptions(): array
    {
        return [
            self::MODE_SERIALIZED => 'Satuan Ber-QR per Unit (Serialized)',
            self::MODE_BULK_RETURNABLE => 'Pinjam Massal 1 QR/Wadah (Bulk Returnable)',
        ];
    }

    public static function qrPresetOptions(): array
    {
        return [
            'micro_12x12' => 'XXS / Micro 12×12mm (Pita 12mm Putih — Sensor Mini)',
            'mini_12' => 'XS / Mini 12mm (Pita 12mm Putih — ESP/RAM/Tools)',
            'flag_12' => 'CF / Cable Flag 12mm (Pita 12mm Kuning — Kabel/Adaptor)',
            'medium_24' => 'M / Sedang 24mm (Pita 24mm Putih/Kuning — Router/Switch/Box)',
            'detail_a4' => 'L / XL Detail A4/A3 (PC Lab, Monitor, Meja/Kursi KIR)',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }

    public function assetItems(): HasMany
    {
        return $this->hasMany(AssetItem::class);
    }

    public function bulkStocks(): HasMany
    {
        return $this->hasMany(BulkStock::class);
    }

    public function procurementItems(): HasMany
    {
        return $this->hasMany(ProcurementItem::class);
    }

    public function isBulkReturnable(): bool
    {
        return $this->tracking_mode === self::MODE_BULK_RETURNABLE;
    }

    public function readyUnitsCount(): int
    {
        return $this->assetItems()
            ->where('status', AssetItem::STATUS_AVAILABLE)
            ->where('kondisi', 'baik')
            ->count();
    }
}
