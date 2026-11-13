<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BloomAppointmentRequest extends Model
{
    protected $table = 'bloom_appointment_requests';

    protected $fillable = [
        'account_id', 'facility_id', 'type', 'preferred_date',
        'preferred_time', 'note', 'status', 'confirmed_date', 'confirmed_time',
        'staff_note', 'decided_by', 'decided_at', 'reschedule_count',
    ];

    protected $casts = [
        'preferred_date' => 'date',
        'confirmed_date' => 'date',
        'decided_at'     => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(BloomAccount::class, 'account_id');
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'facility_id', 'facilityId');
    }
}
