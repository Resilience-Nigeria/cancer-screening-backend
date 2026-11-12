<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SampleDeletionRequest extends Model
{
    protected $table = 'screening_sample_deletion_requests';
    protected $primaryKey = 'requestId';

    protected $fillable = [
        'sampleId', 'clientId', 'facilityId', 'requestedById', 'reason',
        'status', 'decidedById', 'decidedAt', 'decisionNote',
    ];

    protected $casts = ['decidedAt' => 'datetime'];

    public function sample(): BelongsTo
    {
        return $this->belongsTo(ScreeningSample::class, 'sampleId', 'sampleId')->withTrashed();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'clientId', 'clientId');
    }
}