<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Enums\ThemeMode;
use Filament\Facades\Filament;
use Tests\TestCase;

class ThemeModeTest extends TestCase
{
    public function test_dark_and_light_mode_enabled_with_quick_toggle_and_css_tokens(): void
    {
        $panel = Filament::getDefaultPanel();

        $this->assertTrue($panel->hasDarkMode());
        $this->assertSame(ThemeMode::System, $panel->getDefaultThemeMode());

        $css = file_get_contents(public_path('css/latekaje.css'));
        $this->assertIsString($css);
        $this->assertStringContainsString(':root {', $css);
        $this->assertStringContainsString('.dark {', $css);
        $this->assertStringContainsString('--lk-surface:', $css);
        $this->assertStringContainsString('.lk-theme-toggle', $css);

        // Login page includes quick theme toggle + v2 CSS tokens
        $loginRes = $this->get('/admin/login');
        $loginRes->assertOk();
        $loginRes->assertSee('/css/latekaje.css?v=3', false);
        $loginRes->assertSee('lk-auth-theme-floating', false);
        $loginRes->assertSee('lk-theme-toggle', false);

        // Authenticated topbar includes quick theme toggle
        $superadmin = User::where('role', 'superadmin')->firstOrFail();
        $this->flushSession();
        $panelRes = $this->actingAs($superadmin)->get('/admin');
        $panelRes->assertOk();
        $panelRes->assertSee('lk-theme-toggle', false);
    }
}
