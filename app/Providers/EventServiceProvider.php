<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        \App\Events\ClientLinkedToScreeningCenter::class => [
            \App\Listeners\SendScreeningLinkageNotifications::class,
        ],
        \App\Events\ClientReferredToMainHub::class => [
            \App\Listeners\SendMainHubReferralNotifications::class,
        ],
        \App\Events\ClientReferredToTreatment::class => [
            \App\Listeners\SendTreatmentReferralNotifications::class,
        ],
    ];

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
    }
}
