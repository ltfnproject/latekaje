<?php

namespace Tests\Unit;

use App\Models\Loan;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LoanDurationFormatTest extends TestCase
{
    public function test_hides_larger_units_until_reached_and_formats_months_days_hours_minutes(): void
    {
        app()->setLocale('id');

        $base = Carbon::create(2026, 11, 1, 8, 0, 0);

        // < 1 menit -> 0 menit (tanpa bulan, hari, jam, dan tanpa desimal float!)
        $this->assertSame('0 menit', Loan::formatDuration($base, $base->copy()->addSeconds(33)));

        // < 1 jam -> hanya menit
        $this->assertSame('25 menit', Loan::formatDuration($base, $base->copy()->addMinutes(25)));

        // >= 1 jam, < 1 hari -> jam & menit (tanpa bulan & hari)
        $this->assertSame('3 jam 15 menit', Loan::formatDuration($base, $base->copy()->addHours(3)->addMinutes(15)));

        // >= 1 hari, < 1 bulan -> hari, jam, menit (tanpa bulan)
        $this->assertSame('2 hari 4 jam 10 menit', Loan::formatDuration($base, $base->copy()->addDays(2)->addHours(4)->addMinutes(10)));

        // >= 1 bulan -> bulan, hari, jam, menit
        $this->assertSame('1 bulan 2 hari 3 jam 5 menit', Loan::formatDuration($base, $base->copy()->addMonth()->addDays(2)->addHours(3)->addMinutes(5)));

        // > 1 tahun dikonversi ke total bulan
        $this->assertSame('14 bulan 1 hari', Loan::formatDuration($base, $base->copy()->addYear()->addMonths(2)->addDay()));
    }
}
