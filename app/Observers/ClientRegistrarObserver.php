<?php

namespace App\Observers;

use App\Models\Client;
use Illuminate\Support\Facades\Schema;

/**
 * Stamps every new client with the staff member who registered it (web app or mobile app),
 * without touching ClientController::store.
 */
class ClientRegistrarObserver
{
    private static ?bool $hasColumn = null;

    public function creating(Client $client): void
    {
        self::$hasColumn ??= Schema::hasColumn($client->getTable(), 'registeredById');
        if (!self::$hasColumn || $client->getAttribute('registeredById')) {
            return;
        }
        $userId = auth('api')->id();
        if ($userId) {
            $client->setAttribute('registeredById', $userId);
        }
    }
}
