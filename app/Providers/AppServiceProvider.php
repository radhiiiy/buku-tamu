<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::composer('layouts.admin', function ($view) {
            $view->with([
                'office' => Setting::office(),
                'me' => Auth::user(),
                'unread' => Auth::user()?->unreadCount() ?? 0,
            ]);
        });
        View::composer(['layouts.public', 'public.*'], fn ($view) => $view->with('office', Setting::office()));
    }
}
