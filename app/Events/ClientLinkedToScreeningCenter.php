<?php

namespace App\Events;

use App\Models\Client;
use App\Models\Facility;
use App\Models\Navigator;
use Illuminate\Foundation\Events\Dispatchable;

class ClientLinkedToScreeningCenter
{
    use Dispatchable;

    public function __construct(
        public readonly object $client,
        public readonly Facility $facility,
        public readonly ?Navigator $navigator = null,
    ) {}
}