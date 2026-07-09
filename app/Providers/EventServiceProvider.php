<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
