<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Consumable extends Model
{
    protected $fillable = [
        'name',
        'category_label',
        'specification',
        'unit',
        'stock',
        'min_stock',
        'location_id',
        'storage_spot',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'stock' => 'integer',
            'min_stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public static function categoryOptions(): array
    {
        return [
            'Kabel Roll/Meteran' => 'Kabel Roll / Meteran (UTP, FO)',
            'Konektor & Material FO/LAN' => 'Konektor & Material FO/LAN (RJ45, SC-APC/UPC, Barrel, Baut)',
            'Bahan Servis & IoT' => 'Bahan Servis & IoT (Timah, Flux, Solatip, Jumper, Alkohol)',
            'ATK & Operasional' => 'ATK & Operasional (Tinta, CD-R, Ribbon, Baterai)',
            'Umum' => 'Umum / Lainnya',
        ];
    }

    public static function unitOptions(): array
    {
        return [
            'pcs' => 'Pcs / Buah',
            'pack' => 'Pack / Kotak',
            'meter' => 'Meter',
            'feet' => 'Feet',
            'roll' => 'Roll / Gulung',
            'botol' => 'Botol',
            'strip' => 'Strip',
            'set' => 'Set',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(ConsumableTransaction::class);
    }

    public function isLowStock(): bool
    {
        return $this->is_active && $this->stock <= $this->min_stock;
    }
}
