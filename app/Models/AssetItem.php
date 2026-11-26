<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AssetItem extends Model
{
    use HasFactory;

    public const STATUS_AVAILABLE = 'tersedia';
    public const STATUS_BORROWED = 'dipinjam';
    public const STATUS_INSTALLED = 'terpasang';
    public const STATUS_RETIRED = 'afkir';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'integer',
            'installed_at' => 'datetime',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_AVAILABLE => __('units.status_available'),
            self::STATUS_BORROWED => __('units.status_borrowed'),
            self::STATUS_INSTALLED => __('units.status_installed'),
            self::STATUS_RETIRED => __('units.status_retired'),
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class);
    }

    public function parentItem(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_asset_item_id');
    }

    public function installedComponents(): HasMany
    {
        return $this->hasMany(self::class, 'parent_asset_item_id');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function activeLoan(): HasOne
    {
        return $this->hasOne(Loan::class)->where('status', 'aktif');
    }

    public function isInstalled(): bool
    {
        return $this->status === self::STATUS_INSTALLED;
    }

    public function isRetired(): bool
    {
        return $this->status === self::STATUS_RETIRED;
    }

    public function displayLabel(): string
    {
        $tool = $this->asset?->nama_alat ?? 'Unit';

        return "{$this->nomor_seri_atau_qr} — {$tool}";
    }

    protected static function booted(): void
    {
        static::saving(function (AssetItem $item): void {
            // Jangan pindahkan paksa lokasi barang yang sedang terpasang tetap (misal Exhaust Fan / PC Lab rusak sebelum dicopot)
            if (
                in_array($item->kondisi, ['rusak', 'rusak_total'], true)
                && ! in_array($item->status, [self::STATUS_INSTALLED, self::STATUS_RETIRED], true)
            ) {
                $gudang = Location::where('key', 'gudang')->first();
                if ($gudang) {
                    $item->location_id = $gudang->id;
                }
            }
        });
    }
}
