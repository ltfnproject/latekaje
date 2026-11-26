<?php

namespace App\Filament\Pages;

use App\Models\RecapLog;
use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Services\GovernanceService;
use App\Services\GowaClient;
use App\Services\RecapService;
use App\Services\WebhookClient;
use App\Support\SafeUrl;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Foundation\Http\MaintenanceModeBypassCookie;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cookie;

class NotificationSettings extends Page
{
    private const ALLOWED_KEYS = [
        'app_locale',
        'recap_enabled',
        'recap_time',
        'gowa_enabled',
        'gowa_base_url',
        'gowa_user',
        'gowa_pass',
        'gowa_target',
        'gowa_relay_url',
        'gowa_relay_secret',
        'webhook_enabled',
        'webhook_url',
        'webhook_auth',
        'webhook_secret',
        'webhook_user',
        'webhook_pass',
    ];

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    public static function getNavigationLabel(): string
    {
        return __('settings.nav_label');
    }

    protected static ?string $slug = 'setting';

    public function getTitle(): string | \Illuminate\Contracts\Support\Htmlable
    {
        return __('settings.title');
    }

    protected static ?int $navigationSort = 31;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('common.nav_group_system');
    }

    protected string $view = 'filament.pages.notification-settings';

    public array $form = [];

    public string $gowaTest = '';

    public string $webhookTest = '';

    public string $testTarget = '';

    public string $apiTest = '';

    public string $newTokenName = '';

    public ?string $newTokenPlain = null;

    public ?string $maintenanceBypassUrl = null;

    public int $delegationHours = 24;

    public function toggleToolmanDelegation(): void
    {
        abort_unless(self::canAccess(), 403);

        $enable = ! GovernanceService::isToolmanDelegationActive();
        $hours = $enable && $this->delegationHours > 0 ? $this->delegationHours : null;

        GovernanceService::setToolmanDelegation($enable, $hours);

        Notification::make()
            ->title($enable ? __('settings.delegation_on_title') : __('settings.delegation_off_title'))
            ->body($enable
                ? ($hours ? __('settings.delegation_on_timed', ['hours' => $hours]) : __('settings.delegation_on_manual'))
                : __('settings.delegation_off_body'))
            ->{ $enable ? 'warning' : 'success' }()
            ->send();
    }

    public function toggleProcurementStrictMode(): void
    {
        abort_unless(self::canAccess(), 403);

        $enable = ! GovernanceService::isProcurementStrictMode();
        GovernanceService::setProcurementStrictMode($enable);

        Notification::make()
            ->title($enable ? __('settings.strict_mode_on_title') : __('settings.strict_mode_off_title'))
            ->body($enable ? __('settings.strict_mode_on_body') : __('settings.strict_mode_off_body'))
            ->success()
            ->send();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->is_active && ($user->isSuperadmin() || $user->isAdmin());
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);

        $this->form = [
            'app_locale' => Setting::get('app_locale', config('app.locale', 'id')),
            'recap_enabled' => Setting::boolean('recap_enabled', true),
            'recap_time' => Setting::get('recap_time', '16:00'),
            'gowa_enabled' => Setting::boolean('gowa_enabled', false),
            'gowa_base_url' => Setting::get('gowa_base_url', ''),
            'gowa_user' => Setting::get('gowa_user', ''),
            'gowa_pass' => Setting::get('gowa_pass', ''),
            'gowa_target' => Setting::get('gowa_target', ''),
            'gowa_relay_url' => Setting::get('gowa_relay_url', ''),
            'gowa_relay_secret' => Setting::get('gowa_relay_secret', ''),
            'webhook_enabled' => Setting::boolean('webhook_enabled', false),
            'webhook_url' => Setting::get('webhook_url', ''),
            'webhook_auth' => Setting::get('webhook_auth', 'header'),
            'webhook_secret' => Setting::get('webhook_secret', ''),
            'webhook_user' => Setting::get('webhook_user', ''),
            'webhook_pass' => Setting::get('webhook_pass', ''),
        ];
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);

        if (isset($this->form['app_locale']) && ! in_array($this->form['app_locale'], \App\Http\Middleware\SetLocale::SUPPORTED, true)) {
            $this->form['app_locale'] = config('app.locale', 'id');
        }

        // Validasi anti-SSRF pada semua field URL sebelum disimpan
        $urlChecks = [
            'gowa_base_url' => GowaClient::normalizeBase((string) ($this->form['gowa_base_url'] ?? '')),
            'gowa_relay_url' => trim((string) ($this->form['gowa_relay_url'] ?? '')),
            'webhook_url' => trim((string) ($this->form['webhook_url'] ?? '')),
        ];

        foreach ($urlChecks as $field => $urlValue) {
            if ($urlValue === '') {
                continue;
            }

            $check = SafeUrl::validate($urlValue);
            if (! $check['ok']) {
                Notification::make()
                    ->title(__('settings.unsafe_url_title'))
                    ->body("{$field}: {$check['error']}")
                    ->danger()
                    ->persistent()
                    ->send();

                return;
            }
        }

        $savedKeys = [];
        foreach (self::ALLOWED_KEYS as $key) {
            if (! array_key_exists($key, $this->form)) {
                continue;
            }

            $value = $this->form[$key];
            Setting::set($key, is_bool($value) ? ($value ? '1' : '0') : $value);
            $savedKeys[] = $key;
        }

        app()->setLocale(\App\Http\Middleware\SetLocale::resolve());

        ActivityLogger::log(
            'settings.updated',
            'Memperbarui pengaturan sistem dan notifikasi',
            null,
            ['keys' => $savedKeys],
        );

        Notification::make()->title(__('settings.saved'))->success()->send();
    }

    public function testGowa(): void
    {
        abort_unless(self::canAccess(), 403);
        $this->save();

        $res = GowaClient::fromSettings()->devices();
        $this->gowaTest = $res['ok']
            ? __('settings.gowa_connected', ['status' => $res['status']])
            : __('settings.gowa_failed', ['body' => $res['body']]);

        Notification::make()
            ->title($res['ok'] ? __('settings.gowa_ok_title') : __('settings.gowa_fail_title'))
            ->body(mb_substr($res['body'], 0, 200))
            ->{ $res['ok'] ? 'success' : 'danger' }()
            ->send();
    }

    public function sendTestWa(): void
    {
        abort_unless(self::canAccess(), 403);
        $this->save();

        $target = trim($this->testTarget) !== '' ? trim($this->testTarget) : (string) Setting::get('gowa_target', '');

        if ($target === '') {
            $this->gowaTest = __('settings.fill_test_number');

            return;
        }

        $res = GowaClient::fromSettings()->sendMessage(
            $target,
            __('settings.test_wa_body', ['time' => now()->format('d M Y H:i')])
        );
        $this->gowaTest = $res['ok']
            ? __('settings.test_sent_to', ['target' => $target, 'status' => $res['status']])
            : __('settings.test_send_fail', ['target' => $target, 'body' => $res['body']]);

        Notification::make()
            ->title($res['ok'] ? __('settings.test_sent_title') : __('settings.test_failed_title'))
            ->body(mb_substr($this->gowaTest, 0, 200))
            ->{ $res['ok'] ? 'success' : 'danger' }()
            ->send();
    }

    public function testWebhook(): void
    {
        abort_unless(self::canAccess(), 403);
        $this->save();

        $url = (string) Setting::get('webhook_url', '');
        if ($url === '') {
            $this->webhookTest = __('settings.fill_webhook');

            return;
        }

        $res = WebhookClient::post($url, ['event' => 'recap.test', 'message' => __('settings.webhook_test_message')]);
        $this->webhookTest = $res['ok']
            ? __('settings.webhook_sent', ['status' => $res['status']])
            : __('settings.webhook_failed', ['status' => $res['status'], 'body' => $res['body']]);
    }

    public function toggleMaintenance(): void
    {
        abort_unless(self::canAccess(), 403);

        if (app()->isDownForMaintenance()) {
            Artisan::call('up');
            $this->maintenanceBypassUrl = null;

            ActivityLogger::log('system.maintenance_off', 'Mematikan mode perawatan aplikasi');

            Notification::make()->title(__('settings.maintenance_off'))->success()->send();
        } else {
            $secret = bin2hex(random_bytes(16));

            Artisan::call('down', [
                '--render' => 'errors::503',
                '--secret' => $secret,
            ]);

            if (class_exists(MaintenanceModeBypassCookie::class)) {
                Cookie::queue(MaintenanceModeBypassCookie::create($secret));
            }

            $this->maintenanceBypassUrl = url('/'.$secret);

            ActivityLogger::log('system.maintenance_on', 'Menyalakan mode perawatan aplikasi dengan bypass secret');

            Notification::make()
                ->title(__('settings.maintenance_on'))
                ->body(__('settings.maintenance_bypass_notice', ['url' => $this->maintenanceBypassUrl]))
                ->warning()
                ->persistent()
                ->send();
        }
    }

    public function createApiToken(): void
    {
        abort_unless((bool) auth()->user()?->isSuperadmin(), 403);

        $name = trim($this->newTokenName);

        if ($name === '') {
            Notification::make()->title(__('settings.token_name_required'))->danger()->send();

            return;
        }

        $token = auth()->user()->createToken($name, ['*']);
        $this->newTokenPlain = $token->plainTextToken;
        $this->newTokenName = '';

        ActivityLogger::log('api_token.created', "Membuat API token '{$name}'", null, ['token_name' => $name]);

        Notification::make()->title(__('settings.token_created'))->success()->persistent()->send();
    }

    public function revokeApiToken(int $id): void
    {
        abort_unless((bool) auth()->user()?->isSuperadmin(), 403);

        $deleted = auth()->user()->tokens()->whereKey($id)->delete();

        if ($deleted > 0) {
            ActivityLogger::log('api_token.revoked', "Mencabut API token #{$id}", null, ['token_id' => $id]);
        }

        Notification::make()->title(__('settings.token_revoked'))->success()->send();
    }

    public function sendNow(): void
    {
        abort_unless(self::canAccess(), 403);

        $result = RecapService::sendNow();

        Notification::make()
            ->title(__('recap.sent_title', ['total' => $result['total']]))
            ->body(implode(' | ', array_map(fn ($k, $v) => "[$k] $v", array_keys($result['channels']), $result['channels'])))
            ->success()
            ->send();
    }

    protected function getViewData(): array
    {
        return [
            'logs' => RecapLog::latest()->limit(10)->get(),
            'unreturnedCount' => \App\Models\Loan::where('status', 'aktif')->count(),
            'scheduleTime' => Setting::get('recap_time', '16:00'),
            'maintenance' => app()->isDownForMaintenance(),
            'tokens' => auth()->user()?->isSuperadmin() ? auth()->user()->tokens()->latest()->get() : collect(),
            'delegationActive' => GovernanceService::isToolmanDelegationActive(),
            'delegationUntil' => GovernanceService::toolmanDelegationUntil(),
            'procurementStrict' => GovernanceService::isProcurementStrictMode(),
        ];
    }
}
