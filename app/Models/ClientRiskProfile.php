<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientRiskProfile extends Model
{
    protected $table = 'client_risk_profiles';

    protected $primaryKey = 'riskProfileId';
    protected $fillable = [
        'clientId',
        'familyHistory',
        'smokingStatus',
        'alcoholConsumption',
        'physicalActivityLevel',
        'weightKg',
        'heightCm',
        'bmi',
        'hivStatus',
        'hbvStatus',
        'hcvStatus',
        'occupationCategory',
        'lifestyleRiskScore',
        'hivRiskScore',
        'totalCancerRiskScore',
        'cancerRiskCategory',
        'socioeconomicScore',
        'socioeconomicClass',
        'comorbiditiesJson',
        'recordedAt',
        'recordedBy',
        'ageAtMenopause',
        'ageAtFirstMenstruation',
        'breastfeedingHistory',
        'breastfeedingDuration',
        'previousBreastSurgery',
        'previousCancer',
        'previousCancerDetails',
        'previousSurgeries',
        'previousSurgeriesDetails',
        'diabetes',
        'hypertension',
        'previousScreening',
        'previousScreeningDetails',
        'temperatureCelsius',
        'bloodPressureSystolic',
        'bloodPressureDiastolic',
        'pulse',

        'hivArtStatus', 'hpvVaccination', 'lastMenstrualPeriod', 'pregnancyStatus', 'menopausalStatus',
'symptomAbnormalBleeding', 'symptomPostCoitalBleeding', 'symptomPelvicPain', 'symptomDischarge',
'symptomOther', 'symptomNone', 'symptomOtherDetail', 'symptomStatus',
'prevCervicalScreened', 'prevCervicalScreeningDate',
'prevCervicalTestHpv', 'prevCervicalTestVia', 'prevCervicalTestPap',
'prevCervicalTestColposcopy', 'prevCervicalTestOther', 'prevCervicalTestOtherDetail',
'prevCervicalResult', 'prevCervicalTreated', 'prevCervicalTreatmentType',
    ];




    protected $casts = [
        // 'familyHistory' => 'boolean',
        'comorbiditiesJson' => 'array',
        'recordedAt' => 'datetime',

        'lastMenstrualPeriod' => 'date',
'prevCervicalScreeningDate' => 'date',
'symptomAbnormalBleeding' => 'boolean',
'symptomPostCoitalBleeding' => 'boolean',
'symptomPelvicPain' => 'boolean',
'symptomDischarge' => 'boolean',
'symptomOther' => 'boolean',
'symptomNone' => 'boolean',
'prevCervicalTestHpv' => 'boolean',
'prevCervicalTestVia' => 'boolean',
'prevCervicalTestPap' => 'boolean',
'prevCervicalTestColposcopy' => 'boolean',
'prevCervicalTestOther' => 'boolean',
    ];

    public function uniqueIds()
    {
        return ['riskProfileId'];
    }
    
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'clientId');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recordedBy');
    }
}