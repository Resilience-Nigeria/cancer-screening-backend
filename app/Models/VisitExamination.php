<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitExamination extends Model
{
    protected $table = 'visit_examinations';
    protected $primaryKey = 'examinationId';

    protected $fillable = [
        'visitId',
        'heightCm',
        'weightKg',
        'bmi',
        'bloodPressureSystolic',
        'bloodPressureDiastolic',
        'pulse',
        'temperatureCelsius',
        'pallor',
        'weightLossNoted',
        'enlargedLymphNodes',
        'enlargedLymphNodesSite',
        'jaundice',
        'notes',
        'examinedBy',
        'examinedAt',
    ];

    protected $casts = [
        'pallor' => 'boolean',
        'weightLossNoted' => 'boolean',
        'enlargedLymphNodes' => 'boolean',
        'jaundice' => 'boolean',
        'examinedAt' => 'datetime',
    ];

    public function visit(): BelongsTo
    {
        return $this->belongsTo(ScreeningVisit::class, 'visitId', 'visitId');
    }

    public function examiner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'examinedBy', 'id');
    }
}
