<?php

namespace App\Observers;

use App\Services\StockService;
use Illuminate\Database\Eloquent\Model;

/** Stock follows samples: taken → deducted; deleted → given back. Registered for ScreeningSample in AppServiceProvider. */
class StockSampleObserver
{
    public function created(Model $s): void
    {
        if (($s->status ?? null) === 'collected') StockService::deductForSample($s);
    }

    public function updated(Model $s): void
    {
        // A booked test that is now actually taken.
        if ($s->wasChanged('status') && $s->getOriginal('status') === 'booked' && $s->status === 'collected') StockService::deductForSample($s);
    }

    public function deleted(Model $s): void
    {
        StockService::returnForSample($s);
    }
}
