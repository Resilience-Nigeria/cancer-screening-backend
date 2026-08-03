<?php

namespace App\Services;

class SymptomIdMapper
{
    private const MAP = [
        'weight_loss'          => 'unexplained_weight_loss',
        'fatigue'              => 'persistent_fatigue',
        'night_sweats'         => 'night_sweats',
        'fever'                => 'persistent_fever',
        'breast_lump'          => 'lump_breast',
        'nipple_discharge'     => 'nipple_discharge',
        'breast_skin_changes'  => 'breast_skin_changes',
        'neck_lump'            => 'lump_neck',
        'underarm_lump'        => 'lump_underarm',
        'groin_lump'           => 'lump_groin',
        'lump_elsewhere'       => 'lump_elsewhere',
        'blood_stool'          => 'blood_in_stool',
        'blood_urine'          => 'blood_in_urine',
        'vaginal_bleeding'     => 'vaginal_bleeding_after_menopause',
        'bleeding_after_sex'   => 'bleeding_after_sex',
        'breast_pain'          => 'breast_pain',
        'abdominal_pain'       => 'persistent_abdominal_pain',
        'back_pain'            => 'back_pain',
        'bowel_habit_change'   => 'bowel_habit_change',
        'persistent_diarrhea'  => 'persistent_diarrhea',
        'difficulty_urinating' => 'difficulty_urinating',
        'frequent_urination'   => 'frequent_urination',
        'abdominal_swelling'   => 'abdominal_swelling',
        'jaundice'             => 'jaundice',
        'abnormal_periods'     => 'abnormal_periods',
        'pelvic_pain'          => 'pelvic_pain',
    ];

    /** @param string[] $reportedIds  frontend symptom ids the person answered "yes" to */
    public static function toBackendIds(array $reportedIds): array
    {
        return array_values(array_filter(array_map(
            fn ($id) => self::MAP[$id] ?? null,
            $reportedIds
        )));
    }
}