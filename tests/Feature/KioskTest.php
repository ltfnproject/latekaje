<?php

namespace Tests\Feature;

use App\Filament\Resources\Loans\LoanResource;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class KioskTest extends TestCase
{
    public function test_kiosk_generik_fail_closed_by_default_and_opt_in(): void
    {
        $kiosk = User::firstOrCreate(
            ['email' => 'kiosk@latekaje.net'],
            ['name' => 'Kiosk Lab', 'password' => 'kiosk123', 'role' => 'users'],
        );
        $kiosk->forceFill(['password' => 'kiosk123', 'role' => 'users', 'is_active' => true, 'must_change_password' => false])->save();
        $kiosk->update(['code' => null, 'member_id' => null]);

        $this->assertTrue(Auth::attempt(['email' => 'kiosk@latekaje.net', 'password' => 'kiosk123']), 'login kiosk gagal');
        $this->actingAs($kiosk);

        // Default LATEKAJE v4: fail-closed (unlinked users tidak boleh melihat semua pinjaman)
        config(['latekaje.allow_generic_kiosk' => false]);
        $failClosedSql = LoanResource::getEloquentQuery()->toSql();
        $this->assertStringContainsString('1 = 0', $failClosedSql, 'default unlinked users wajib fail-closed');

        // Bila kiosk diaktifkan eksplisit via env ALLOW_GENERIC_KIOSK=true
        config(['latekaje.allow_generic_kiosk' => true]);
        $optInSql = LoanResource::getEloquentQuery()->toSql();
        $this->assertStringNotContainsString('1 = 0', $optInSql, 'opt-in kiosk harus bisa lihat semua');

        $target = (new \App\Http\Responses\LoginResponse)->toResponse(request())->getTargetUrl();
        $this->assertStringEndsWith('/admin/loans', $target);

        $res = $this->get('/admin/loans/create');
        $res->assertOk();

        // Reset ke default aman
        config(['latekaje.allow_generic_kiosk' => false]);
    }
}
