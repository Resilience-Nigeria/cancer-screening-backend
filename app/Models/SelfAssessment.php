<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SelfAssessment extends Model
{
    protected $table = 'self_assessments';
    protected $primaryKey = 'assessmentId';

    protected $fillable = [
        'registrationId',
        'clientId',
        'answersJson',
        'riskCategory',
        'recommendation',
        'flaggedReasonsJson',
        'suggestedCancerTypesJson',
        'completedAt',
    ];

    protected $casts = [
        'answersJson' => 'array',
        'flaggedReasonsJson' => 'array',
        'suggestedCancerTypesJson' => 'array',
        'completedAt' => 'datetime',
    ];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(AwarenessRegistration::class, 'registrationId', 'registrationId');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'clientId', 'clientId');
    }
}
