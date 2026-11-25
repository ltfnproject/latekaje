<?php

namespace App\Providers;

use App\Auth\CodeUserProvider;
use App\Models\User;
use App\Support\Acl;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL; // 🟢 Wajib import class URL ini di atas!

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Auth::provider('code-eloquent', fn ($app, array $config) => new CodeUserProvider($app['hash'], $config['model']));
        $this->app->bind(
            \Filament\Auth\Http\Responses\Contracts\LoginResponse::class,
            \App\Http\Responses\LoginResponse::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(function (User $user, string $ability, array $arguments) {
            if (! $user->is_active) {
                return false;
            }

            $subject = $arguments[0] ?? null;
            $subjectName = is_object($subject) ? class_basename($subject) : (is_string($subject) ? class_basename($subject) : null);

            // Audit log bersifat append-only (immutable), bahkan untuk superadmin.
            if ($subjectName === 'ActivityLog' && in_array($ability, ['create', 'update', 'delete', 'restore', 'forceDelete'], true)) {
                return false;
            }

            if ($user->isSuperadmin()) {
                return true;
            }

            $perms = $user->permissions ?? [];
            $key = Acl::key($ability, $subject);

            if (in_array($key, $perms['deny'] ?? [], true)) {
                return false;
            }

            if (in_array($key, $perms['allow'] ?? [], true)) {
                return true;
            }

            return null;
        });

        // 🟢 Paksa semua asset, link, dan request AJAX (Livewire) menggunakan HTTPS di server
        if (config('app.env') === 'production' || app()->environment('production')) {
            URL::forceScheme('https');
        }
    }
}