<?php

namespace App\Services;

use App\Models\AwarenessRegistration;
use App\Models\Client;
use App\Models\SelfAssessment;
use App\Models\Facility;
use Illuminate\Support\Facades\Log;

class BloomClientConversionService
{
    /**
     * Converts an AwarenessRegistration into a full Client, using the
     * same clientId format as staff-created clients. Idempotent — if the
     * registration is already converted, returns the existing Client
     * rather than creating a duplicate (safe to call on every
     * self-assessment submission, including re-assessments).
     */
    public function convert(AwarenessRegistration $registration, Facility $originFacility): Client
    {
        if ($registration->clientId) {
            return Client::where('clientId', $registration->clientId)->firstOrFail();
        }

        $clientId = $this->generateClientId(
            $originFacility,
            $registration->stateOfResidence,
            $registration->lgaOfResidence,
        );

        $client = Client::create([
            'clientId'         => $clientId,
            'facilityId'       => $originFacility->facilityId,
            'linkedFacilityId' => $originFacility->facilityId,
            'fullName'         => $registration->fullName,
            'gender'           => $registration->gender,
            'phoneNumber'      => $registration->phoneNumber,
            'email'            => $registration->email,
            'stateOfResidence' => $registration->stateOfResidence,
            'lgaOfResidence'   => $registration->lgaOfResidence,
            'registrationDate' => now(),
            'journeyStage'     => 'awareness',
        ]);

        $registration->update(['clientId' => $client->clientId]);
       
        return $client;
    }

    /**
     * Same algorithm as ClientController::generateClientId, duplicated
     * rather than shared so this addition can't alter behavior of the
     * existing staff-facing client creation flow. Worth consolidating
     * into one shared service later if you'd rather not maintain two copies.
     */
    protected function generateClientId(Facility $facility, string $state, string $lga): string
    {
        $facilityCode = strtoupper($facility->facilityCode);
        $stateCode = strtoupper(substr(str_replace(' ', '', $state), 0, 3));
        $lgaCode = LgaCodeMapping::getLgaCode($state, $lga);

        if (!$lgaCode) {
            Log::warning('LGA not found in mapping', ['state' => $state, 'lga' => $lga]);
            $lgaCode = strtoupper(substr(str_replace(' ', '', $lga), 0, 3));
        }

        $prefix = $facilityCode . '-' . $stateCode . '-' . $lgaCode . '-';

        $lastClient = Client::where('clientId', 'like', $prefix . '%')
            ->orderByDesc('clientId')
            ->first();

        $nextNumber = 1;
        if ($lastClient) {
            $parts = explode('-', $lastClient->clientId);
            if (count($parts) === 4) {
                $nextNumber = ((int) end($parts)) + 1;
            }
        }

        return $prefix . str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
    }
}