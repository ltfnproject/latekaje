<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->must_change_password) {
            return $next($request);
        }

        if (
            $request->routeIs('filament.admin.auth.profile', 'filament.admin.auth.logout')
            || ($request->isMethod('POST') && ($request->routeIs('livewire.*') || str_starts_with($request->path(), 'livewire')))
        ) {
            return $next($request);
        }

        $profileUrl = Filament::getProfileUrl();

        if ($profileUrl) {
            Notification::make()
                ->title(__('users.must_change_password_title'))
                ->body(__('users.must_change_password_body'))
                ->warning()
                ->persistent()
                ->send();

            return redirect()->to($profileUrl);
        }

        return $next($request);
    }
}
