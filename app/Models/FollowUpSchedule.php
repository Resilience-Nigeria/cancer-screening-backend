<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowUpSchedule extends Model
{
    protected $primaryKey = 'scheduleId';

    protected $fillable = [
        'treatmentPlanId',
        'dueDate',
        'activities',
        'status',
        'reminderSentAt',
        'escalationSentAt',
        'completedDate',
        'completionNotes',
    ];

    protected $casts = [
        'reminderSentAt' => 'datetime',
        'escalationSentAt' => 'datetime',
    ];

    public function treatmentPlan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class, 'treatmentPlanId', 'treatmentPlanId');
    }
}
