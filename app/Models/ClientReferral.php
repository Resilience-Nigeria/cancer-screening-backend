<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientReferral extends Model
{
    protected $primaryKey = 'referralId';
    public $incrementing = true;

    protected $fillable = [
        'clientId',
        'fromFacilityId',
        'toFacilityId',
        'referralType',
        'status',
        'referralDate',
        'notes',
        'notifiedAt',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'clientId', 'clientId');
    }

    public function fromFacility()
    {
        return $this->belongsTo(Facility::class, 'fromFacilityId', 'facilityId');
    }

    public function toFacility()
    {
        return $this->belongsTo(Facility::class, 'toFacilityId', 'facilityId');
    }
}