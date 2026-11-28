<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Procurement extends Model
{
    public const TYPE_NEW = 'new_procurement';
    public const TYPE_LEGACY = 'legacy_stock';

    protected $fillable = [
        'batch_code',
        'entry_type',
        'funding_source_id',
        'received_at',
        'vendor',
        'notes',
        'photo_paths',
        'created_by',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'date',
            'photo_paths' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public static function entryTypeOptions(): array
    {
        return [
            self::TYPE_NEW => 'Pengadaan / Barang Baru',
            self::TYPE_LEGACY => 'Inventaris Lama / Saldo Awal',
        ];
    }

    public function fundingSource(): BelongsTo
    {
        return $this->belongsTo(FundingSource::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProcurementItem::class);
    }

    public function consumableItems(): HasMany
    {
        return $this->hasMany(ProcurementConsumable::class);
    }

    public function assetItems(): HasMany
    {
        return $this->hasMany(AssetItem::class);
    }

    public function consumableTransactions(): HasMany
    {
        return $this->hasMany(ConsumableTransaction::class);
    }

    public function isGenerated(): bool
    {
        return $this->generated_at !== null;
    }

    public function totalUnits(): int
    {
        return (int) $this->items()->sum('quantity')
            + (int) $this->consumableItems()->sum('quantity');
    }

    public function totalPrice(): int
    {
        $assetTotal = (int) $this->items()
            ->whereNotNull('unit_price')
            ->selectRaw('COALESCE(SUM(quantity * unit_price), 0) as total')
            ->value('total');

        $consumableTotal = (int) $this->consumableItems()
            ->whereNotNull('unit_price')
            ->selectRaw('COALESCE(SUM(quantity * unit_price), 0) as total')
            ->value('total');

        return $assetTotal + $consumableTotal;
    }
}
