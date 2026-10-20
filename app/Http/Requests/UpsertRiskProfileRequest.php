<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertRiskProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Numeric fields sent as an empty string (very common from a number
     * input left blank, e.g. age at menopause for a client who is male
     * or hasn't reached menopause) need to become null before
     * validation - Laravel's `nullable` rule only skips validation for
     * an actual null value, not an empty string, so "" still fails an
     * `integer`/`numeric` check even with `nullable` present.
     */
    protected function prepareForValidation(): void
    {
        $numericFields = [
            'ageAtFirstMenstruation',
            'ageAtMenopause',
            'weightKg',
            'heightCm',
            'breastfeedingDuration',
            'avgBreastfeedingMonthsPerChild',
            'numberOfChildbirths',
            'ageAtFirstIntercourse',
            'numberOfPreviousMarriages',
            'numberOfSexualPartners',
        ];

        $normalized = [];
        foreach ($numericFields as $field) {
            if ($this->has($field) && trim((string) $this->input($field)) === '') {
                $normalized[$field] = null;
            }
        }

        if ($normalized) {
            $this->merge($normalized);
        }
    }

    public function rules(): array
    {
        return [
            // Now nullable: the risk assessment only asks the questions relevant to the cancer
            // type(s) being screened, and family history is not asked for liver-only screening.
            'familyHistory' => ['nullable', 'in:yes,no,unknown'],
            'smokingStatus' => ['nullable', 'in:non_smoker,never,active_smoker,current_smoker,passive_smoker,ocassional,occasional,former_smoker,ex_smoker'],
            'alcoholConsumption' => ['nullable', 'in:none,never,non_drinker,occasional,occasionally,light_drinker,regular,regularly,weekly,daily,heavy_drinker'],
            'physicalActivityLevel' => ['nullable', 'in:regular,sometimes,rarely'],
            'occupationCategory' => ['nullable', 'string', 'max:10'],
            'weightKg' => ['nullable', 'numeric', 'min:0'],
            'heightCm' => ['nullable', 'numeric', 'min:0'],
            'hivStatus' => ['nullable', 'in:positive,negative,unknown'],
            'hbvStatus' => ['nullable', 'in:positive,negative,unknown'],
            'hcvStatus' => ['nullable', 'in:positive,negative,unknown'],
            'comorbiditiesJson' => ['nullable', 'array'],
            'comorbiditiesJson.*' => ['string'],
            'ageAtFirstMenstruation' => ['nullable', 'integer', 'min:0', 'max:100'],
            'ageAtMenopause' => ['nullable', 'integer', 'min:0', 'max:100'],

            // Breastfeeding: cumulative duration = children x average months per child, so it can
            // be fractional (was integer). The UI calculates it; the average is stored alongside.
            'breastfeedingHistory' => ['nullable', 'in:yes,no'],
            'avgBreastfeedingMonthsPerChild' => ['nullable', 'numeric', 'min:0', 'max:60'],
            'breastfeedingDuration' => ['nullable', 'numeric', 'min:0', 'max:3000'],

            // Legacy: no longer collected by the UI, kept so older records/clients still validate.
            'previousBreastSurgery' => ['nullable', 'in:yes,no'],

            // Sexual & reproductive history (cervical risk assessment; moved here from the
            // cervical screening step). numberOfChildbirths is shared with the breast section.
            'previousMarriage' => ['nullable', 'in:yes,no'],
            'numberOfPreviousMarriages' => ['nullable', 'integer', 'min:0', 'max:50'],
            'numberOfSexualPartners' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'moreThanOnePartner' => ['nullable', 'in:yes,no'],
            'ageAtFirstIntercourse' => ['nullable', 'integer', 'min:0', 'max:100'],
            'numberOfChildbirths' => ['nullable', 'integer', 'min:0', 'max:50'],
            'contraceptiveUse' => ['nullable', 'in:none,oral_contraceptives,iud,barrier_methods,other'],

            // Stage 2, Section C — medical history confirm checklist
            'previousCancer' => ['nullable', 'in:yes,no,unknown'],
            // Now holds the cancer type (breast, cervical, prostate, colorectal, liver)
            'previousCancerDetails' => ['nullable', 'string', 'max:255'],
            // Legacy: previous surgeries is no longer collected by the UI
            'previousSurgeries' => ['nullable', 'in:yes,no,unknown'],
            'previousSurgeriesDetails' => ['nullable', 'string', 'max:255'],
            'diabetes' => ['nullable', 'in:yes,no,unknown'],
            'hypertension' => ['nullable', 'in:yes,no,unknown'],
            'previousScreening' => ['nullable', 'in:yes,no,unknown'],
            // Now holds the cancer type that was screened
            'previousScreeningDetails' => ['nullable', 'string', 'max:255'],

            // Previous exposure to ionizing radiation
            'ionizingRadiationExposure' => ['nullable', 'in:yes,no'],
            'ionizingRadiationType' => ['nullable', 'in:xray,ct_scan,other'],
            'ionizingRadiationOther' => ['nullable', 'string', 'max:255'],

            'hivArtStatus' => ['nullable', 'in:on_art,not_on_art'],
            'hpvVaccination' => ['nullable', 'in:yes,no'],
            'lastMenstrualPeriod' => ['nullable', 'date'],
            'pregnancyStatus' => ['nullable', 'in:pregnant,not_pregnant,tbd'],
            'menopausalStatus' => ['nullable', 'in:pre,peri,post'],

            'symptomAbnormalBleeding' => ['nullable', 'boolean'],
            'symptomPostCoitalBleeding' => ['nullable', 'boolean'],
            'symptomPelvicPain' => ['nullable', 'boolean'],
            'symptomDischarge' => ['nullable', 'boolean'],
            'symptomOther' => ['nullable', 'boolean'],
            'symptomNone' => ['nullable', 'boolean'],
            'symptomOtherDetail' => ['nullable', 'string', 'max:255'],
            'symptomStatus' => ['nullable', 'in:Symptomatic,Asymptomatic'],

            'prevCervicalScreened' => ['nullable', 'in:yes,no'],
            'prevCervicalScreeningDate' => ['nullable', 'date'],
            'prevCervicalTestHpv' => ['nullable', 'boolean'],
            'prevCervicalTestVia' => ['nullable', 'boolean'],
            'prevCervicalTestPap' => ['nullable', 'boolean'],
            'prevCervicalTestColposcopy' => ['nullable', 'boolean'],
            'prevCervicalTestOther' => ['nullable', 'boolean'],
            'prevCervicalTestOtherDetail' => ['nullable', 'string', 'max:255'],
            'prevCervicalResult' => ['nullable', 'in:negative,positive_precancer,suspected_cancer,unknown'],
            'prevCervicalTreated' => ['nullable', 'in:yes,no,unknown'],
            'prevCervicalTreatmentType' => ['nullable', 'in:cryotherapy,thermal_ablation,excision_leep,unknown'],
        ];
    }
}