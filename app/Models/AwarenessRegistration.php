<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AwarenessRegistration extends Model
{
    protected $table = 'awareness_registrations';
    protected $primaryKey = 'registrationId';
    public $incrementing = true;

    protected $fillable = [
        'fullName',
        'gender',
        'phoneNumber',
        'email',
        'stateOfResidence',
        'lgaOfResidence',
        'areaOfResidence',
        'campaignSource',
        'status',
        'clientId',
        'linkedFacilityId',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'clientId', 'clientId');
    }

    public function facility()
    {
        return $this->belongsTo(Facility::class, 'linkedFacilityId', 'facilityId');
    }

    public function selfAssessments()
    {
        return $this->hasMany(SelfAssessment::class, 'registrationId', 'registrationId');
    }
}