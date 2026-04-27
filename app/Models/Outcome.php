<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Outcome extends Model
{
    protected $table = 'case_outcomes';
    protected $primaryKey = 'outcomeId';

    protected $fillable = [
        'clientId',
        'visitId',
        'finalOutcome',
        'recommendationsJson',
        'referralFacility',
        'recordedAt',
        'recordedBy',
    ];

    protected $casts = [
        'recommendationsJson' => 'array',
        'recordedAt' => 'datetime',
    ];

    /**
     * Get the client for this outcome
     */
    public function client()
    {
        return $this->belongsTo(Client::class, 'clientId', 'clientId');
    }

    /**
     * Get the visit for this outcome
     */
    public function visit()
    {
        return $this->belongsTo(Visit::class, 'visitId', 'visitId');
    }
}