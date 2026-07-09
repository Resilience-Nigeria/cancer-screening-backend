<?php
// app/Events/ClientReferredToMainHub.php

namespace App\Events;

use App\Models\Client;
use App\Models\Facility;
use App\Models\ClientReferral;
use Illuminate\Foundation\Events\Dispatchable;

class ClientReferredToMainHub
{
    use Dispatchable;

    public function __construct(
        public Client $client,
        public Facility $fromFacility,
        public Facility $toFacility,
        public ClientReferral $referral,
    ) {}
}