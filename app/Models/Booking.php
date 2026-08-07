<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    protected $primaryKey = 'bookingId';

    protected $fillable = [
        'clientId', 'visitId', 'serviceId', 'facilityId',
        'cancerType', 'bookingDate', 'notes', 'status',
    ];

    protected $casts = ['bookingDate' => 'date'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'clientId', 'clientId');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'serviceId', 'serviceId');
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'facilityId', 'facilityId');
    }
}