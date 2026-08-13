<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCervicalScreeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `screeningResult` is the NOT NULL column. Callers may send `result`
     * (modals) or `screeningResult` (wizard) — fold either into screeningResult
     * so `result` never reaches the model (no such column).
     *
     * Also coerces the *BookNow / *Done checkboxes from string "true"/"false"
     * to real booleans if they arrive as strings — mirrors
     * StoreBreastScreeningRequest's handling of biopsyDone etc. Without this,
     * a stray string "false" would still be truthy in a PHP `?? false` check
     * downstream in the controller.
     */
    protected function prepareForValidation(): void
    {
        $merge = [
            'screeningResult' => $this->input('screeningResult', $this->input('result')),
        ];

        foreach (['ablationBookNow', 'leepBookNow', 'colposcopyBookNow', 'colposcopyDone', 'biopsyDone'] as $bool) {
            if ($this->has($bool) && is_string($this->input($bool))) {
                $merge[$bool] = filter_var($this->input($bool), FILTER_VALIDATE_BOOLEAN);
            }
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        return [
            'clientId' => ['nullable', 'string'],
            'method' => ['required', 'in:methodVia,methodPap,methodHpv'],
            'screeningDate' => ['nullable', 'date'],
            'screeningResult' => ['nullable', 'in:negative,positive,suspicious,non_suspicious'],

            'hpvResult' => ['nullable', 'string', 'max:255'],
            'hpvGenotype' => ['nullable', 'in:16,18,35,45,other'],

            // Nothing here is required_if — per the wizard's existing "don't
            // make anything required" convention, since results/bookings
            // often aren't known in the same visit.
            'ablationEligibility' => ['nullable', 'in:eligible,not_eligible,suspicious_invasion'],
            'ablationBookNow' => ['nullable', 'boolean'],
            'ablationBookingDate' => ['nullable', 'date'],
            'ablationBookingFacilityId' => ['nullable', 'integer'],
            'ablationBookingNotes' => ['nullable', 'string', 'max:2000'],

            'leepBookNow' => ['nullable', 'boolean'],
            'leepBookingDate' => ['nullable', 'date'],
            'leepBookingFacilityId' => ['nullable', 'integer'],
            'leepBookingNotes' => ['nullable', 'string', 'max:2000'],
            'histologyResult' => ['nullable', 'in:cin1,cin2,cin3,ais,cancer'],

            'colposcopyDone' => ['nullable', 'boolean'],
            'colposcopyBookNow' => ['nullable', 'boolean'],
            'colposcopyBookingDate' => ['nullable', 'date'],
            'colposcopyBookingFacilityId' => ['nullable', 'integer'],
            'colposcopyBookingNotes' => ['nullable', 'string', 'max:2000'],
            'colposcopyResult' => ['nullable', 'in:positive,negative'],

            'biopsyDone' => ['nullable', 'boolean'],
            'biopsyResult' => ['nullable', 'required_if:biopsyDone,1,true', 'in:positive,negative'],

            'treatmentReferral' => ['nullable', 'in:referred,not_referred'],
            'referralPathway' => ['nullable', 'in:ablation,leep,refer_further_evaluation'],
            'followUpMonths' => ['nullable', 'integer', 'min:1', 'max:60'],

            // Cervical-specific risk factors
            'moreThanOnePartner' => ['nullable', 'in:yes,no'],
            'ageAtFirstIntercourse' => ['nullable', 'integer', 'min:0', 'max:100'],
            'numberOfChildbirths' => ['nullable', 'integer', 'min:0', 'max:50'],
            'contraceptiveUse' => ['nullable', 'in:none,oral_contraceptives,iud,barrier_methods,other'],

            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}