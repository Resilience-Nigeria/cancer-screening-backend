<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FollowUpProtocolTemplate extends Model
{
    protected $primaryKey = 'templateId';

    protected $fillable = [
        'cancerType',
        'monthsAfterTreatment',
        'activities',
        'isRecurringAnnually',
        'isActive',
    ];

    protected $casts = [
        'isRecurringAnnually' => 'boolean',
        'isActive' => 'boolean',
    ];
}
