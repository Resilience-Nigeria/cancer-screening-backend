<?php

namespace App\Http\Controllers\Api;

use App\Services\StoresServiceBookings;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCervicalScreeningRequest;
use App\Models\CervicalScreening;
use App\Models\ScreeningVisit;
use Illuminate\Http\JsonResponse;

class CervicalScreeningController extends Controller
{
    use StoresServiceBookings;

    public function store(StoreCervicalScreeningRequest $request, ScreeningVisit $visit): JsonResponse
    {
        $this->authorizeVisit($visit);

        $data = $request->validated();

        // Automated clinical logic, mirroring the breast screening controller's
        // malignant -> IHC/referral pattern. 'refer_further_evaluation' is a
        // single shared outcome — per the pathway chart, suspicious-for-invasion,
        // a negative VIA/colposcopy triage, and a post-LEEP "Cancer" histology
        // result all route into the same "Refer for evaluation & further
        // management" step.
        if (($data['ablationEligibility'] ?? null) === 'eligible') {
            $data['referralPathway'] = 'ablation';
            $data['treatmentReferral'] = 'referred';
        } elseif (($data['ablationEligibility'] ?? null) === 'not_eligible') {
            $data['referralPathway'] = 'leep';
            $data['treatmentReferral'] = 'referred';
        } elseif (($data['ablationEligibility'] ?? null) === 'suspicious_invasion') {
            $data['referralPathway'] = 'refer_further_evaluation';
            $data['treatmentReferral'] = 'referred';
        }

        // A negative colposcopy result (via the "Colposcopy done" ->
        // "Colposcopy Result" pair, mirroring biopsyDone/biopsyResult) routes
        // into the same further-evaluation outcome as suspicious invasion —
        // per the chart, NOT a standalone 12-month follow-up. A positive
        // result is handled by the clinician re-entering the ablation
        // eligibility step above (the wizard shows that field again when
        // colposcopyResult is 'positive'), so no auto-referral is set here
        // for a positive result.
        if (($data['colposcopyResult'] ?? null) === 'negative') {
            $data['referralPathway'] = 'refer_further_evaluation';
            $data['treatmentReferral'] = 'referred';
        }

        // Post-LEEP histology: Cancer overrides the referral pathway to the
        // same further-evaluation outcome; CIN1/CIN2/CIN3/AIS mean LEEP
        // stands as the resolution, so referralPathway is left as 'leep'.
        if (($data['histologyResult'] ?? null) === 'cancer') {
            $data['referralPathway'] = 'refer_further_evaluation';
            $data['treatmentReferral'] = 'referred';
        }

        $screening = CervicalScreening::updateOrCreate(
            ['visitId' => $visit->visitId],
            [
                ...$data,
                'visitId' => $visit->visitId,
                'clientId' => $visit->clientId,
            ]
        );

        // Persist the actual bookings — previously these checkboxes/dates/
        // facilities were saved on the screening row but never turned into
        // a Booking record, so nothing showed up wherever bookings are listed.
        if ($data['ablationBookNow'] ?? false) {
            $this->storeBooking(
                $visit->clientId,
                $visit->visitId,
                'ablation',
                'cervical',
                $data['ablationBookingDate'] ?? null,
                $data['ablationBookingFacilityId'] ?? null,
                $data['ablationBookingNotes'] ?? null
            );
        }

        if ($data['leepBookNow'] ?? false) {
            $this->storeBooking(
                $visit->clientId,
                $visit->visitId,
                'leep',
                'cervical',
                $data['leepBookingDate'] ?? null,
                $data['leepBookingFacilityId'] ?? null,
                $data['leepBookingNotes'] ?? null
            );
        }

        if ($data['colposcopyBookNow'] ?? false) {
            $this->storeBooking(
                $visit->clientId,
                $visit->visitId,
                'colposcopy',
                'cervical',
                $data['colposcopyBookingDate'] ?? null,
                $data['colposcopyBookingFacilityId'] ?? null,
                $data['colposcopyBookingNotes'] ?? null
            );
        }

        return response()->json([
            'message' => 'Cervical screening saved successfully',
            'screening' => $screening,
        ], 201);
    }

    public function show(ScreeningVisit $visit): JsonResponse
    {
        $this->authorizeVisit($visit);

        return response()->json([
            'screening' => $visit->cervicalScreening,
        ]);
    }

    protected function authorizeVisit(ScreeningVisit $visit): void
    {
        $user = auth('api')->user();

        if (!$user->canAccessFacility($visit->facilityId)) {
            abort(403, 'You cannot access this visit');
        }
    }
}