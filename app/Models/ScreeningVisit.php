<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ScreeningVisit extends Model
{
    protected $table = 'screening_visits';

    protected $primaryKey = 'visitId';
    protected $fillable = [
        'clientId',
        'facilityId',
        'visitDate',
        'visitType',
        'notes',
        'createdBy',
    ];

    protected $casts = [
        'visitDate' => 'date',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'clientId', 'clientId');
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'facilityId', 'facilityId');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'createdBy', 'id');
    }

    public function cervicalScreening(): HasOne
    {
        return $this->hasOne(CervicalScreening::class, 'visitId', 'visitId');
    }

    public function breastScreening(): HasOne
    {
        return $this->hasOne(BreastScreening::class, 'visitId', 'visitId');
    }

    public function colorectalScreening(): HasOne
    {
        return $this->hasOne(ColorectalScreening::class, 'visitId', 'visitId');
    }

    public function liverScreening(): HasOne
    {
        return $this->hasOne(LiverScreening::class, 'visitId', 'visitId');
    }

    public function prostateScreening(): HasOne
    {
        return $this->hasOne(ProstateScreening::class, 'visitId', 'visitId');
    }

    public function caseOutcome(): HasOne
    {
        return $this->hasOne(CaseOutcome::class, 'clientId', 'clientId');
    }
}