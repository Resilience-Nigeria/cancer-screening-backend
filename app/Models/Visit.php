<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Visit extends Model
{
    protected $table = 'screening_visits';
    protected $primaryKey = 'visitId';

    protected $fillable = [
        'clientId',
        'visitDate',
        'visitType',
        'notes',
        treatmentReferral,
    ];

    protected $casts = [
        'visitDate' => 'datetime',
    ];

    /**
     * Get the client for this visit
     */
    public function client()
    {
        return $this->belongsTo(Client::class, 'clientId', 'clientId');
    }

    /**
     * Get the cervical screening for this visit
     */
    public function cervicalScreening()
    {
        return $this->hasOne(CervicalScreening::class, 'visitId', 'visitId');
    }

    /**
     * Get the breast screening for this visit
     */
    public function breastScreening()
    {
        return $this->hasOne(BreastScreening::class, 'visitId', 'visitId');
    }

    /**
     * Get the prostate screening for this visit
     */
    public function prostateScreening()
    {
        return $this->hasOne(ProstateScreening::class, 'visitId', 'visitId');
    }

    /**
     * Get the colorectal screening for this visit
     */
    public function colorectalScreening()
    {
        return $this->hasOne(ColorectalScreening::class, 'visitId', 'visitId');
    }

    /**
     * Get the liver screening for this visit
     */
    public function liverScreening()
    {
        return $this->hasOne(LiverScreening::class, 'visitId', 'visitId');
    }

    /**
     * Get the case outcome for this visit (if linked via clientId)
     */
    public function caseOutcome()
    {
        return $this->hasOne(CaseOutcome::class, 'clientId', 'clientId');
    }
}