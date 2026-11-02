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
    $this->app->singleton(\App\Services\SmsService::class);
    $this->app->singleton(\App\Services\FacilityService::class);
    $this->app->singleton(\App\Services\ReferralService::class);
    $this->app->singleton(\App\Services\OtpService::class);
}

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // API docs (Scramble). Guarded so the app still boots before
        // `composer require dedoc/scramble` has been run.
        if (class_exists(\Dedoc\Scramble\Scramble::class)) {
            // Docs page (/docs/api) and spec (/docs/api.json) are always open
            // locally. Elsewhere, only in the environments listed here.
            \Illuminate\Support\Facades\Gate::define('viewApiDocs', function ($user = null) {
                return app()->environment(['local', 'staging']);
            });

            \Dedoc\Scramble\Scramble::configure()
                ->withDocumentTransformers(function (\Dedoc\Scramble\Support\Generator\OpenApi $openApi) {
                    $openApi->info->title = 'NCSR API';
                    $openApi->info->description = 'National Cancer Screening Registry API. See docs/api/FRONTEND_GUIDE.md for auth flows and conventions.';
                    $openApi->secure(\Dedoc\Scramble\Support\Generator\SecurityScheme::http('bearer', 'JWT'));
                });
        }
    }
}
