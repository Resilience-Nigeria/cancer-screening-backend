<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
        RateLimiter::for('login', fn (Request $r) =>
    Limit::perMinute(5)->by(Str::lower((string) $r->input('email')) . '|' . $r->ip()));
    }
}
