<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Client extends Model
{
    protected $table = 'clients';

    // protected $primaryKey = 'screeningId';
    protected $primaryKey = 'clientId';
public $incrementing = false;
protected $keyType = 'string';

    protected $fillable = [
        'facilityId',
        'clientId',
        'fullName',
        'gender',
        'dateOfBirth',
        'age',
        'phoneNumber',
        'screeningCategory',
        'stateOfOrigin',
        'lgaOfOrigin',
        'stateOResidence',
        'lgaOfResidence',
        'address',
        'registrationDate',
    ];

    protected $casts = [
        'dateOfBirth' => 'date',
        'registrationDate' => 'date',
    ];

    protected $appends = ['age'];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'facilityId');
    }

    public function riskProfiles(): HasMany
    {
        return $this->hasMany(ClientRiskProfile::class, 'clientId', 'clientId');
    }

    public function latestRiskProfile(): HasOne
    {
        return $this->hasOne(ClientRiskProfile::class, 'clientId', 'clientId')->latestOfMany('riskProfileId');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(ScreeningVisit::class, 'clientId');
    }

    public function outcome(): HasOne
    {
        return $this->hasOne(CaseOutcome::class, 'clientId');
    }

    public function getAgeAttribute(): ?int
    {
        return $this->dateOfBirth ? Carbon::parse($this->dateOfBirth)->age : null;
    }



    public function cervicalScreening(): HasOne
    {
        return $this->hasOne(CervicalScreening::class, 'clientId', 'clientId');
    }
 
    /**
     * Get the breast screening for the client
     */
    public function breastScreening(): HasOne
    {
        return $this->hasOne(BreastScreening::class, 'clientId', 'clientId');
    }
 
    /**
     * Get the prostate screening for the client
     */
    public function prostateScreening(): HasOne
    {
        return $this->hasOne(ProstateScreening::class, 'clientId', 'clientId');
    }
 
    /**
     * Get the case outcome for the client
     */
    public function caseOutcome(): HasOne
    {
        return $this->hasOne(CaseOutcome::class, 'clientId', 'clientId');
    }
}