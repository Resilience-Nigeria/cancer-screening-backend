<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSelfAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // public self-assessment form
    }

    public function rules(): array
    {
        return [
            'registrationId' => ['required', 'integer', 'exists:awareness_registrations,registrationId'],

            'answers' => ['required', 'array'],
            'answers.age' => ['required', 'integer', 'min:1', 'max:120'],
            'answers.heightCm' => ['nullable', 'numeric', 'min:0'],
            'answers.weightKg' => ['nullable', 'numeric', 'min:0'],

            'answers.smoker' => ['nullable', 'in:never,former,current'],
            'answers.cigarettesPerDay' => ['nullable', 'integer', 'min:0'],
            'answers.smokingYears' => ['nullable', 'integer', 'min:0'],
            'answers.alcohol' => ['nullable', 'in:none,occasional,regular,heavy'],
            'answers.exerciseDaysPerWeek' => ['nullable', 'integer', 'min:0', 'max:7'],

            'answers.medicalHistory' => ['nullable', 'array'],
            'answers.medicalHistory.*' => ['string'],

            'answers.familyHistory' => ['nullable', 'array'],
            'answers.familyHistory.*.cancerType' => ['required_with:answers.familyHistory', 'string'],
            'answers.familyHistory.*.relation' => ['required_with:answers.familyHistory', 'string'],
            'answers.familyHistory.*.ageAtDiagnosis' => ['nullable', 'integer', 'min:0'],

            'answers.symptoms' => ['nullable', 'array'],
            'answers.symptoms.*' => ['string'],

            'answers.ageAtMenarche' => ['nullable', 'integer', 'min:0', 'max:30'],
            'answers.stillMenstruating' => ['nullable', 'boolean'],
            'answers.ageAtMenopause' => ['nullable', 'integer', 'min:0', 'max:100'],
            'answers.pregnancies' => ['nullable', 'integer', 'min:0'],
            'answers.ageAtFirstChildbirth' => ['nullable', 'integer', 'min:0'],
            'answers.breastfeedingHistory' => ['nullable', 'boolean'],
            'answers.hpvVaccinated' => ['nullable', 'boolean'],
            'answers.papSmearEver' => ['nullable', 'boolean'],
            'answers.papSmearResult' => ['nullable', 'string'],

            'answers.urinaryDifficulty' => ['nullable', 'boolean'],
            'answers.weakStream' => ['nullable', 'boolean'],
            'answers.nocturia' => ['nullable', 'boolean'],
            'answers.bloodInSemen' => ['nullable', 'boolean'],

            'answers.infections' => ['nullable', 'array'],
            'answers.infections.*' => ['string'],

            'answers.previousScreenings' => ['nullable', 'array'],
            'answers.previousScreenings.*.type' => ['required_with:answers.previousScreenings', 'string'],
            'answers.previousScreenings.*.date' => ['nullable', 'date'],
            'answers.previousScreenings.*.result' => ['nullable', 'string'],

            'answers.exposures' => ['nullable', 'array'],
            'answers.exposures.*' => ['string'],

            'answers.geneticSyndromes' => ['nullable', 'array'],
            'answers.geneticSyndromes.*' => ['string'],
        ];
    }
}
