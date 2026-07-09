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
        'campaignSource',
        'status',
        'clientId',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'clientId', 'clientId');
    }
}