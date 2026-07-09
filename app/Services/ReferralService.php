<?php
// app/Services/ReferralService.php

namespace App\Services;

use App\Events\ClientReferredToMainHub;
use App\Events\ClientReferredToTreatment;
use App\Models\Client;
use App\Models\ClientReferral;
use App\Models\Facility;

class ReferralService
{
    public function referToMainHub(
        Client $client,
        Facility $fromFacility,
        Facility $toFacility,
        ?string $notes = null,
    ): ClientReferral {
        $referral = ClientReferral::create([
            'clientId'      => $client->clientId,
            'fromFacilityId'=> $fromFacility->facilityId,
            'toFacilityId'  => $toFacility->facilityId,
            'referralType'  => 'screening_to_confirmation',
            'referralDate'  => now()->toDateString(),
            'status'        => 'pending',
            'notes'         => $notes,
        ]);

        $client->update([
            'journeyStage'     => 'confirmation',
            'linkedFacilityId' => $toFacility->facilityId,
        ]);

        ClientReferredToMainHub::dispatch($client, $fromFacility, $toFacility, $referral);

        return $referral;
    }

    public function referToTreatment(
        Client $client,
        Facility $fromFacility,
        Facility $toFacility,
        ?string $notes = null,
    ): ClientReferral {
        $referral = ClientReferral::create([
            'clientId'       => $client->clientId,
            'fromFacilityId' => $fromFacility->facilityId,
            'toFacilityId'   => $toFacility->facilityId,
            'referralType'   => 'confirmation_to_treatment',
            'referralDate'   => now()->toDateString(),
            'status'         => 'pending',
            'notes'          => $notes,
        ]);

        $client->update([
            'journeyStage'     => 'treatment',
            'linkedFacilityId' => $toFacility->facilityId,
        ]);

        ClientReferredToTreatment::dispatch($client, $fromFacility, $toFacility, $referral);

        return $referral;
    }
}