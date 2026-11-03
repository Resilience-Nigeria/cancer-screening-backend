<?php

namespace App\Models;

use App\Support\ScreeningTestCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScreeningSample extends Model
{
    protected $table = 'screening_samples';
    protected $primaryKey = 'sampleId';

    protected $fillable = [
        'clientId', 'visitId', 'facilityId',
        'cancerType', 'testType', 'specimenType', 'collectionMethod',
        'sampleDate', 'labSampleNumber', 'collectedBy', 'collectedById', 'notes',
        'status',
        'result', 'resultSeverity', 'resultValue', 'resultDetail', 'resultDate', 'resultNotes', 'resultEnteredById',
    ];

    protected $casts = [
        'sampleDate'  => 'date:Y-m-d',
        'resultDate'  => 'date:Y-m-d',
        'resultValue' => 'float',
    ];

    protected $appends = ['sampleRef'];

    /** Reference to write on the sample / request form, e.g. HPV-000042, PSA-000007. */
    public function getSampleRefAttribute(): string
    {
        $prefix = ScreeningTestCatalog::find($this->testType)['refPrefix'] ?? 'SMP';
        return $prefix . '-' . str_pad((string) $this->sampleId, 6, '0', STR_PAD_LEFT);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'clientId', 'clientId');
    }
}
