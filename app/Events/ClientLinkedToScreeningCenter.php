<?php
// app/Events/ClientLinkedToScreeningCenter.php

namespace App\Events;

use App\Models\Client;
use App\Models\Facility;
use Illuminate\Foundation\Events\Dispatchable;

class ClientLinkedToScreeningCenter
{
    use Dispatchable;

    public function __construct(
        public readonly object $client,  // 👈 object instead of Client
        public readonly Facility $facility,
    ) {}
}