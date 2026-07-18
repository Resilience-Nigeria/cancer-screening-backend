<?php

namespace App\Services;

/**
 * Bloom self-assessment risk classification.
 *
 * This is a decision-support triage, NOT a diagnosis. It classifies a
 * respondent into one of four tiers (low / average / increased /
 * symptomatic_high) and suggests which of the five cancer types this
 * platform screens for (cervical, breast, prostate, colorectal, liver)
 * are most relevant, so the eventual in-person screening visit can be
 * pointed in the right direction.
 *
 * The rule set below is a starting point — thresholds (ages, symptom
 * lists) should be reviewed and adjusted against NICRAT's actual national
 * screening guidelines before this goes live for real patients.
 */
class RiskClassificationService
{
    protected const URGENT_SYMPTOMS = [
        'blood_in_stool', 'blood_in_urine', 'coughing_blood',
        'vaginal_bleeding_after_menopause', 'bleeding_after_sex',
        'non_healing_ulcer', 'mole_changing_size',
    ];

    protected const LUMP_SYMPTOMS = [
        'lump_breast', 'lump_neck', 'lump_underarm', 'lump_groin', 'lump_elsewhere',
    ];

    protected const GENERAL_SYMPTOMS = [
        'unexplained_weight_loss', 'persistent_fatigue', 'night_sweats', 'persistent_fever',
    ];

    protected const RELEVANT_MEDICAL_HISTORY = [
        'cervical_dysplasia', 'colon_polyps', 'cancer',
    ];

    protected const CHRONIC_INFECTIONS = ['hiv', 'hepatitis_b', 'hepatitis_c'];

    /**
     * @param array $answers Structured self-assessment answers (see StoreSelfAssessmentRequest).
     * @param string $sex 'male' | 'female'
     * @return array{riskCategory: string, recommendation: string, flaggedReasons: string[], suggestedCancerTypes: string[]}
     */
    public function classify(array $answers, string $sex): array
    {
        $flags = [];
        $age = (int) ($answers['age'] ?? 0);
        $symptoms = $answers['symptoms'] ?? [];
        $familyHistory = $answers['familyHistory'] ?? [];
        $medicalHistory = $answers['medicalHistory'] ?? [];
        $infections = $answers['infections'] ?? [];
        $geneticSyndromes = $answers['geneticSyndromes'] ?? [];
        $exposures = $answers['exposures'] ?? [];

        // ── Tier 1: Symptomatic / High Risk ─────────────────────────────
        $urgentHit = array_intersect(self::URGENT_SYMPTOMS, $symptoms);
        $lumpHit = array_intersect(self::LUMP_SYMPTOMS, $symptoms);
        $generalHit = array_intersect(self::GENERAL_SYMPTOMS, $symptoms);
        // General symptoms alone are non-specific; only escalate when paired
        // with something else (another symptom, or a relevant risk factor).
        $otherSymptomPresent = count($symptoms) > count($generalHit);

        if (!empty($urgentHit) || !empty($lumpHit) || (!empty($generalHit) && $otherSymptomPresent)) {
            foreach (array_merge($urgentHit, $lumpHit, $otherSymptomPresent ? $generalHit : []) as $s) {
                $flags[] = 'Reported symptom: ' . $this->humanize($s);
            }

            $suggested = $this->suggestedTypesFromSymptoms($symptoms, $sex);
            return [
                'riskCategory' => 'symptomatic_high',
                'recommendation' => 'Some of the symptoms reported need prompt medical evaluation. '
                    . 'Please see a healthcare provider as soon as possible rather than waiting for a routine screening slot. '
                    . 'If symptoms are severe (heavy bleeding, coughing blood, or a rapidly growing lump), seek urgent care now.',
                'flaggedReasons' => $flags,
                'suggestedCancerTypes' => $suggested ?: $this->eligibleTypesByAgeSex($age, $sex),
            ];
        }

        // ── Tier 2: Increased Risk ──────────────────────────────────────
        $increasedFlags = [];

        foreach ($familyHistory as $fh) {
            $relation = strtolower($fh['relation'] ?? '');
            if (in_array($relation, ['parent', 'sibling', 'child'], true)) {
                $increasedFlags[] = 'First-degree family history of ' . $this->humanize($fh['cancerType'] ?? 'cancer');
            }
        }

        foreach (array_intersect(self::RELEVANT_MEDICAL_HISTORY, $medicalHistory) as $mh) {
            $increasedFlags[] = 'Personal history of ' . $this->humanize($mh);
        }

        foreach ($geneticSyndromes as $g) {
            $increasedFlags[] = 'Family history of ' . $this->humanize($g);
        }

        foreach (array_intersect(self::CHRONIC_INFECTIONS, $infections) as $inf) {
            $increasedFlags[] = 'Diagnosed with ' . $this->humanize($inf);
        }

        if (($answers['smoker'] ?? 'never') === 'current' && (int) ($answers['smokingYears'] ?? 0) >= 10) {
            $increasedFlags[] = 'Current smoker, 10+ years';
        }

        if (($answers['alcohol'] ?? 'none') === 'heavy') {
            $increasedFlags[] = 'Heavy alcohol use';
        }

        if (!empty($exposures)) {
            $increasedFlags[] = 'Occupational/environmental exposure: ' . implode(', ', array_map([$this, 'humanize'], $exposures));
        }

        if (!empty($increasedFlags)) {
            $suggested = $this->suggestedTypesFromRiskFactors($familyHistory, $medicalHistory, $infections);
            return [
                'riskCategory' => 'increased',
                'recommendation' => 'Based on your history, you may benefit from earlier or more frequent screening than the general population. '
                    . 'Please discuss a personalized screening plan with a healthcare provider.',
                'flaggedReasons' => $increasedFlags,
                'suggestedCancerTypes' => $suggested ?: $this->eligibleTypesByAgeSex($age, $sex),
            ];
        }

        // ── Tier 3: Average Risk (age/sex-eligible for routine screening) ──
        $eligible = $this->eligibleTypesByAgeSex($age, $sex);
        if (!empty($eligible)) {
            return [
                'riskCategory' => 'average',
                'recommendation' => 'You are due for routine, age-appropriate cancer screening. '
                    . 'We recommend booking a screening appointment at your nearest centre.',
                'flaggedReasons' => [],
                'suggestedCancerTypes' => $eligible,
            ];
        }

        // ── Tier 4: Low Risk ─────────────────────────────────────────────
        return [
            'riskCategory' => 'low',
            'recommendation' => 'No immediate concerns identified. Maintain a healthy lifestyle and plan to begin '
                . 'routine screening at the recommended age for your risk group.',
            'flaggedReasons' => [],
            'suggestedCancerTypes' => [],
        ];
    }

    protected function eligibleTypesByAgeSex(int $age, string $sex): array
    {
        $types = [];
        if ($sex === 'female' && $age >= 25 && $age <= 49) {
            $types[] = 'cervical';
        }
        if ($sex === 'female' && $age >= 40) {
            $types[] = 'breast';
        }
        if ($sex === 'male' && $age >= 50) {
            $types[] = 'prostate';
        }
        if ($age >= 45) {
            $types[] = 'colorectal';
        }
        return $types;
    }

    protected function suggestedTypesFromSymptoms(array $symptoms, string $sex): array
    {
        $types = [];
        if (in_array('lump_breast', $symptoms, true)) $types[] = 'breast';
        if (in_array('vaginal_bleeding_after_menopause', $symptoms, true)
            || in_array('bleeding_after_sex', $symptoms, true)) $types[] = 'cervical';
        if (in_array('blood_in_stool', $symptoms, true)) $types[] = 'colorectal';
        if (in_array('blood_in_urine', $symptoms, true) && $sex === 'male') $types[] = 'prostate';
        return array_values(array_unique($types));
    }

    protected function suggestedTypesFromRiskFactors(array $familyHistory, array $medicalHistory, array $infections): array
    {
        $types = [];
        foreach ($familyHistory as $fh) {
            $ct = strtolower($fh['cancerType'] ?? '');
            if (in_array($ct, ['breast', 'cervical', 'prostate', 'colorectal', 'liver'], true)) {
                $types[] = $ct;
            }
        }
        if (in_array('colon_polyps', $medicalHistory, true)) $types[] = 'colorectal';
        if (in_array('cervical_dysplasia', $medicalHistory, true)) $types[] = 'cervical';
        if (!empty(array_intersect(['hepatitis_b', 'hepatitis_c'], $infections))) $types[] = 'liver';
        return array_values(array_unique($types));
    }

    protected function humanize(string $key): string
    {
        return str_replace('_', ' ', $key);
    }
}
