<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulkStock extends Model
{
    protected $fillable = [
        'asset_id',
        'location_id',
        'storage_spot',
        'container_qr',
        'label',
        'total_qty',
        'available_qty',
        'borrowed_qty',
        'damaged_qty',
        'notes',
        'qr_printed_at',
        'qr_print_count',
    ];

    protected function casts(): array
    {
        return [
            'total_qty' => 'integer',
            'available_qty' => 'integer',
            'borrowed_qty' => 'integer',
            'damaged_qty' => 'integer',
            'qr_printed_at' => 'datetime',
            'qr_print_count' => 'integer',
        ];
    }

    public function isQrPrinted(): bool
    {
        return $this->qr_printed_at !== null;
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
