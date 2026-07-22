<?php

namespace App\Services;

/**
 * NICRAT Cancer Risk Stratification Model.
 *
 * Scores four lifestyle factors (body weight/BMI, smoking, alcohol,
 * physical activity) plus HIV status into a single cancer risk score,
 * classified as low (0-3), intermediate (4-7), or high (8-11).
 *
 * Also implements the accompanying socio-economic classification scheme
 * (occupation-based, 6 tiers -> upper/middle/lower class).
 *
 * Input values are normalized loosely (see the map*() methods) since the
 * app's risk profile form has historically used several different label
 * variants for the same underlying category (e.g. smoking status has
 * been captured as "non_smoker", "never", etc. across different points
 * in this app's history) — normalizing here keeps the scoring correct
 * regardless of which variant a given record happens to use.
 */
class CancerRiskStratificationService
{
    /**
     * @param float|null $bmi
     * @param string|null $smokingStatus
     * @param string|null $alcoholConsumption
     * @param string|null $physicalActivityLevel
     * @param string|null $hivStatus
     * @return array{
     *   lifestyleRiskScore: int,
     *   hivRiskScore: int,
     *   totalCancerRiskScore: int,
     *   cancerRiskCategory: string,
     * }
     */
    public function classify(
        ?float $bmi,
        ?string $smokingStatus,
        ?string $alcoholConsumption,
        ?string $physicalActivityLevel,
        ?string $hivStatus,
    ): array {
        $bmiScore = $this->bmiScore($bmi);
        $smokingScore = $this->smokingScore($smokingStatus);
        $alcoholScore = $this->alcoholScore($alcoholConsumption);
        $activityScore = $this->physicalActivityScore($physicalActivityLevel);
        $hivScore = $this->hivScore($hivStatus);

        $lifestyleRiskScore = $bmiScore + $smokingScore + $alcoholScore + $activityScore;
        $totalCancerRiskScore = $lifestyleRiskScore + $hivScore;

        return [
            'lifestyleRiskScore' => $lifestyleRiskScore,
            'hivRiskScore' => $hivScore,
            'totalCancerRiskScore' => $totalCancerRiskScore,
            'cancerRiskCategory' => $this->riskCategory($totalCancerRiskScore),
        ];
    }

    protected function riskCategory(int $totalScore): string
    {
        return match (true) {
            $totalScore >= 8 => 'high',
            $totalScore >= 4 => 'intermediate',
            default => 'low',
        };
    }

    /**
     * Body weight categories:
     * Normal BMI (<30.0)        -> 0
     * Class I obesity (30-34.9) -> 1
     * Class II obesity (35-39.9)-> 2
     * Class III obesity (>=40)  -> 3
     */
    protected function bmiScore(?float $bmi): int
    {
        if ($bmi === null) {
            return 0;
        }
        return match (true) {
            $bmi >= 40.0 => 3,
            $bmi >= 35.0 => 2,
            $bmi >= 30.0 => 1,
            default => 0,
        };
    }

    /**
     * Tobacco smoking status: non-smoker -> 0, former smoker -> 1,
     * current smoker -> 2. Passive/secondhand smoke exposure isn't part
     * of the NICRAT model's own smoking behaviour score, so it's treated
     * as non-smoker here (0) for scoring purposes only.
     */
    protected function smokingScore(?string $status): int
    {
        $normalized = strtolower(trim($status ?? ''));
        return match ($normalized) {
            'active_smoker', 'current_smoker', 'smoker' => 2,
            'former_smoker', 'ex_smoker' => 1,
            default => 0, // non_smoker, never, passive_smoker, empty
        };
    }

    /**
     * Alcohol consumption: non-drinker (0g/day) -> 0,
     * light drinker (<=50g/day) -> 1, heavy drinker (>50g/day) -> 2.
     * The app doesn't currently capture exact grams/day, so this maps
     * from the categorical frequency values the form actually offers.
     */
    protected function alcoholScore(?string $consumption): int
    {
        $normalized = strtolower(trim($consumption ?? ''));
        return match ($normalized) {
            'daily', 'regularly', 'regular', 'heavy_drinker', 'heavy' => 2,
            'weekly', 'occasionally', 'occasional', 'light_drinker', 'light' => 1,
            default => 0, // none, never, non_drinker, empty
        };
    }

    /**
     * Physical activity by weekly exercise frequency:
     * Regular (5-7x/week) -> 0, Sometimes (1-4x/week) -> 1,
     * Rarely (<1x/week) -> 2.
     */
    protected function physicalActivityScore(?string $level): int
    {
        $normalized = strtolower(trim($level ?? ''));
        return match ($normalized) {
            'rarely' => 2,
            'sometimes' => 1,
            default => 0, // regular, empty
        };
    }

    /**
     * HIV status: negative -> 0, unknown -> 1, positive -> 2.
     */
    protected function hivScore(?string $status): int
    {
        $normalized = strtolower(trim($status ?? ''));
        return match ($normalized) {
            'positive' => 2,
            'unknown', '' => 1,
            default => 0, // negative
        };
    }

    /**
     * Socio-economic classification from occupation category (1a-6b as
     * defined in the NICRAT revised scoring scheme).
     *
     * @return array{socioeconomicScore: int, socioeconomicClass: string}|null
     */
    public function classifySocioeconomicStatus(?string $occupationCategory): ?array
    {
        if (!$occupationCategory) {
            return null;
        }

        $category = strtolower(trim($occupationCategory));
        // Categories are labelled 1a/1b through 6a/6b in the source
        // document; the leading digit is the score, "a"/"b" are just
        // two example groupings within the same score tier.
        if (!preg_match('/^([1-6])[ab]?$/', $category, $matches)) {
            return null;
        }

        $score = (int) $matches[1];

        $class = match (true) {
            $score <= 2 => 'upper',
            $score <= 4 => 'middle',
            default => 'lower',
        };

        return [
            'socioeconomicScore' => $score,
            'socioeconomicClass' => $class,
        ];
    }
}
