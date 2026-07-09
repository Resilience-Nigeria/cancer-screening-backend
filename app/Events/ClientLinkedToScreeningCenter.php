// app/Events/ClientLinkedToScreeningCenter.php
<?php

namespace App\Events;

use App\Models\Client;
use App\Models\Facility;
use Illuminate\Foundation\Events\Dispatchable;

class ClientLinkedToScreeningCenter
{
    use Dispatchable;

    public function __construct(
        public Client $client,
        public Facility $facility,
    ) {}
}