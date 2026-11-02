<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HpvSample extends Model
{
    protected $table = 'hpv_samples';
    protected $primaryKey = 'hpvSampleId';

    protected $fillable = [
        'clientId',
        'facilityId',
        'sampleDate',
        'collectionMethod',
        'labSampleNumber',
        'collectedBy',
        'collectedById',
        'notes',
        'status',
        'result',
        'hpvGenotype',
        'resultDate',
        'resultNotes',
        'resultEnteredById',
    ];

    protected $casts = [
        'sampleDate' => 'date:Y-m-d',
        'resultDate' => 'date:Y-m-d',
    ];

    protected $appends = ['sampleRef'];

    /** Reference to write on the sample and lab form, e.g. HPV-000042. */
    public function getSampleRefAttribute(): string
    {
        return 'HPV-' . str_pad((string) $this->hpvSampleId, 6, '0', STR_PAD_LEFT);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'clientId', 'clientId');
    }
}
