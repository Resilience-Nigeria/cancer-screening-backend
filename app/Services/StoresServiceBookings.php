<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Service;
use Illuminate\Support\Facades\Log;

trait StoresServiceBookings
{
    /**
     * Creates or updates the Booking row backing a "book for X" checkbox on
     * a screening form (e.g. ablationBookNow, biopsyBookNow). Keyed on
     * client + visit + service so re-saving the same screening updates the
     * existing booking instead of creating duplicates.
     *
     * Services are looked up by their `slug` column (confirmed against the
     * actual Service model — e.g. 'ablation', 'leep', 'colposcopy', 'biopsy').
     */
    protected function storeBooking(
        ?string $clientId,
        ?int $visitId,
        string $serviceCode,
        string $cancerType,
        ?string $bookingDate,
        ?int $facilityId,
        ?string $notes
    ): void {
        if (!$clientId || !$visitId) {
            Log::warning('storeBooking skipped: missing clientId or visitId', [
                'clientId' => $clientId,
                'visitId' => $visitId,
                'serviceCode' => $serviceCode,
            ]);
            return;
        }

        $serviceId = Service::where('slug', $serviceCode)->value('serviceId');

        if (!$serviceId) {
            Log::warning('storeBooking skipped: no matching service', [
                'serviceCode' => $serviceCode,
                'clientId' => $clientId,
                'visitId' => $visitId,
            ]);
            return;
        }

        $booking = Booking::updateOrCreate(
            [
                'clientId' => $clientId,
                'visitId' => $visitId,
                'serviceId' => $serviceId,
            ],
            [
                'facilityId' => $facilityId,
                'cancerType' => $cancerType,
                'bookingDate' => $bookingDate,
                'notes' => $notes,
                'status' => 'pending',
            ]
        );

        Log::info('storeBooking saved', [
            'bookingId' => $booking->bookingId,
            'serviceCode' => $serviceCode,
            'clientId' => $clientId,
            'visitId' => $visitId,
            'facilityId' => $facilityId,
        ]);
    }
}