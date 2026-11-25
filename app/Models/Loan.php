<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Loan extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal_pinjam' => 'datetime',
            'tanggal_kembali' => 'datetime',
        ];
    }

    public function assetItem(): BelongsTo
    {
        return $this->belongsTo(AssetItem::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function getNamaAlatAttribute(): string
    {
        return $this->assetItem?->asset?->nama_alat ?? '(unit dihapus)';
    }

    public function getLamaHariAttribute(): string
    {
        return self::formatDuration($this->tanggal_pinjam, $this->tanggal_kembali);
    }

    /**
     * Format durasi peminjaman: Bulan, Hari, Jam, Menit.
     * - Sembunyikan bulan jika < 1 bulan
     * - Sembunyikan hari jika < 1 hari
     * - Sembunyikan jam jika < 1 jam
     */
    public static function formatDuration(mixed $start, mixed $end = null): string
    {
        if (! $start) {
            return '-';
        }

        $from = \Illuminate\Support\Carbon::parse($start);
        $to = $end ? \Illuminate\Support\Carbon::parse($end) : \Illuminate\Support\Carbon::now();

        if ($to->lessThanOrEqualTo($from)) {
            return __('dashboard.duration_minutes', ['count' => 0]);
        }

        $diff = $from->diff($to);
        $months = ($diff->y * 12) + $diff->m;
        $days = $diff->d;
        $hours = $diff->h;
        $minutes = $diff->i;

        $parts = [];

        if ($months > 0) {
            $parts[] = __('dashboard.duration_months', ['count' => $months]);
        }

        if ($days > 0) {
            $parts[] = __('dashboard.duration_days', ['count' => $days]);
        }

        if ($hours > 0) {
            $parts[] = __('dashboard.duration_hours', ['count' => $hours]);
        }

        if ($minutes > 0 || $parts === []) {
            $parts[] = __('dashboard.duration_minutes', ['count' => $minutes]);
        }

        return implode(' ', $parts);
    }

    protected static function booted(): void
    {
        static::created(function (Loan $loan): void {
            if ($loan->status === 'aktif') {
                $loan->assetItem()->update(['status' => 'dipinjam']);
            }
        });
    }
}
