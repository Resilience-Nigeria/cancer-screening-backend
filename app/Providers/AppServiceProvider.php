<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
  // app/Providers/AppServiceProvider.php
public function register(): void
{
    $this->app->singleton(\App\Services\BrevoService::class);
    $this->app->singleton(\App\Services\WhatsAppService::class);
    $this->app->singleton(\App\Services\FacilityService::class);
    $this->app->singleton(\App\Services\ReferralService::class);
    $this->app->singleton(\App\Services\OtpService::class);
}

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
