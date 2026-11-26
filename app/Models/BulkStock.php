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
    ];

    protected function casts(): array
    {
        return [
            'total_qty' => 'integer',
            'available_qty' => 'integer',
            'borrowed_qty' => 'integer',
            'damaged_qty' => 'integer',
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
}
