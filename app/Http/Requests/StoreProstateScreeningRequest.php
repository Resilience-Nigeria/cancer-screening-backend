<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreProstateScreeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [
            'screeningResult' => $this->input('screeningResult', $this->input('result')),
        ];

        if ($this->has('referral') && !$this->filled('treatmentReferral')) {
            $merge['treatmentReferral'] = $this->input('referral');
        }

        if ($this->has('biopsyDone') && is_string($this->input('biopsyDone'))) {
            $merge['biopsyDone'] = filter_var($this->input('biopsyDone'), FILTER_VALIDATE_BOOLEAN);
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        $yn = ['nullable', 'in:yes,no'];

        return [
            'clientId' => ['nullable', 'string'],
            'screeningDate' => ['nullable', 'date'],
            // Derived on the client from the national prostate algorithm
            'screeningResult' => ['nullable', 'in:negative,positive,suspicious,non_suspicious'],
            'treatmentReferral' => ['nullable', 'in:referred,not_referred'],

            // Step 1 — eligibility (men 45 to <75)
            'ageAtScreening' => ['nullable', 'integer', 'min:0', 'max:120'],
            'screeningPathway' => ['nullable', 'in:Symptomatic,Asymptomatic,Not Eligible'],

            // Steps 2–3 — consent, counselling, shared decision-making
            'consentGiven' => $yn,
            'counsellingDone' => $yn,
            'sharedDecision' => ['nullable', 'in:proceed,declined'],

            // Step 4 — primary tests
            'psaTestDate' => ['nullable', 'date'],
            'psaLevel' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'repeatPsaDate' => ['nullable', 'date'],
            'repeatPsaLevel' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'dreFinding' => ['nullable', 'in:normal,nodularity,induration,asymmetry,equivocal'],
            'dreResult' => ['nullable', 'in:positive,negative'], // derived from dreFinding
            'previousPsaLevel' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'previousPsaDate' => ['nullable', 'date'],
            'ipssScore' => ['nullable', 'integer', 'min:0', 'max:35'],

            // Warning symptoms
            'poorUrinaryStream' => $yn,
            'urgeIncontinence' => $yn,
            'delayStartingUrination' => $yn,
            'inabilityToHoldUrine' => $yn,
            'terminalDribbling' => $yn,
            'frequentDayUrination' => $yn,
            'nocturia' => $yn,
            'incompleteEmptying' => $yn,
            'bloodInUrine' => $yn,
            'inabilityToPassUrine' => $yn,
            'bonePainHipBack' => $yn,
            'unexplainedWeightLoss' => $yn,

            // PSA-deferment conditions
            'recentDre' => $yn,
            'recentEjaculation' => $yn,
            'recentUrinaryInfection' => $yn,
            'recentVigorousExercise' => $yn,
            'psaEligibility' => ['nullable', 'in:Eligible,Deferred,Not Eligible,Pending Consent'],
            'recallDate' => ['nullable', 'date'],
            'nextScreeningDate' => ['nullable', 'date'],
            'recommendedAction' => ['nullable', 'string', 'max:1000'],

            // Step 7 — secondary risk assessment
            'prostateVolumeCc' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'psaDensity' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'freePsaPercent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'psaVelocity' => ['nullable', 'numeric', 'min:-100', 'max:100'],
            'mpMriResult' => ['nullable', 'in:not_done,negative,suspicious'],
            'priorNegativeBiopsy' => $yn,
            'highClinicalSuspicion' => $yn,
            'biopsyIndicated' => $yn,
            'biopsyIndicationReasons' => ['nullable', 'string', 'max:1000'],

            // Procedures
            'biopsyDone' => ['nullable', 'boolean'],
            'gleasonScore' => ['nullable', 'required_if:biopsyDone,1,true', 'string', 'max:50'],

            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Protocol rule: a repeat PSA must be at least 2 weeks after the first.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $level = $this->input('repeatPsaLevel');
            if ($level === null || $level === '') {
                return;
            }

            $first = $this->input('psaTestDate') ?: $this->input('screeningDate');
            $repeat = $this->input('repeatPsaDate');

            if (!$first || !$repeat) {
                $v->errors()->add('repeatPsaDate', 'Repeat PSA date is required with a repeat PSA level.');
                return;
            }

            if (strtotime($repeat) - strtotime($first) < 14 * 86400) {
                $v->errors()->add('repeatPsaDate', 'Repeat PSA must be at least 2 weeks after the first PSA.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'psaLevel.numeric' => 'PSA level must be a number.',
            'psaLevel.min' => 'PSA level cannot be negative.',
            'psaLevel.max' => 'PSA level value is too high.',
            'repeatPsaLevel.numeric' => 'Repeat PSA level must be a number.',
            'ipssScore.integer' => 'IPSS score must be a whole number.',
            'ipssScore.min' => 'IPSS score cannot be less than 0.',
            'ipssScore.max' => 'IPSS score cannot exceed 35.',
            'gleasonScore.required_if' => 'Gleason score is required when biopsy is done.',
            'treatmentReferral.in' => 'Referral status must be referred or not_referred.',
            'dreFinding.in' => 'DRE finding must be normal, nodularity, induration, asymmetry or equivocal.',
            'freePsaPercent.max' => '% free PSA cannot exceed 100.',
        ];
    }
}