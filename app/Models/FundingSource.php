<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FundingSource extends Model
{
    public const TYPE_PURCHASE = 'pembelian';
    public const TYPE_BANPEM = 'banpem';
    public const TYPE_COMMITTEE = 'komite';
    public const TYPE_CSR = 'csr';
    public const TYPE_OTHER = 'lainnya';

    protected $fillable = [
        'name',
        'source_type',
        'fiscal_year',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public static function sourceTypeOptions(): array
    {
        return [
            self::TYPE_PURCHASE => 'Pembelian (Kas / BOS / BOP)',
            self::TYPE_BANPEM => 'BANPEM (Hibah / Bantuan Pemerintah)',
            self::TYPE_COMMITTEE => 'Komite / Siswa / Yayasan',
            self::TYPE_CSR => 'Hibah Industri / CSR',
            self::TYPE_OTHER => 'Swadaya / Lainnya',
        ];
    }

    public function sourceTypeLabel(): string
    {
        return self::sourceTypeOptions()[$this->source_type] ?? $this->source_type;
    }

    public function procurements(): HasMany
    {
        return $this->hasMany(Procurement::class);
    }

    public function totalUnitsEntered(): int
    {
        return (int) ProcurementItem::query()
            ->whereIn('procurement_id', $this->procurements()->select('id'))
            ->sum('quantity');
    }

    public function totalEstimatedValue(): int
    {
        return (int) ProcurementItem::query()
            ->whereIn('procurement_id', $this->procurements()->select('id'))
            ->whereNotNull('unit_price')
            ->selectRaw('COALESCE(SUM(quantity * unit_price), 0) as total')
            ->value('total');
    }
}
