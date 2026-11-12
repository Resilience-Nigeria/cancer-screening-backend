<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BloomAppointmentRequest extends Model
{
    protected $table = 'bloom_appointment_requests';

    protected $fillable = [
        'account_id', 'facility_id', 'type', 'preferred_date',
        'preferred_time', 'note', 'status',
    ];

    protected $casts = [
        'preferred_date' => 'date',
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
