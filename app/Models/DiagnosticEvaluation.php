<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiagnosticEvaluation extends Model
{
    protected $primaryKey = 'evaluationId';

    protected $fillable = [
        'clientId',
        'facilityId',
        'referralId',
        'evaluationDate',
        'suspectedCancerType',
        'consultationNotes',
        'consultedBy',
        'consultedAt',
        'advancedExaminationFindings',
        'diagnosticTests',
        'bloodInvestigations',
        'histopathologyResult',
        'pathologyNotes',
        'pathologyDate',
        'status',
        'completedBy',
        'completedAt',
    ];

    protected $casts = [
        'diagnosticTests' => 'array',
        'bloodInvestigations' => 'array',
        'consultedAt' => 'datetime',
        'completedAt' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'clientId', 'clientId');
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'facilityId', 'facilityId');
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(ClientReferral::class, 'referralId', 'referralId');
    }

    public function consultant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consultedBy', 'id');
    }
}
