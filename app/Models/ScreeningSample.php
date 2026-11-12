<?php

namespace App\Models;

use App\Support\ScreeningTestCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Facility;

class ScreeningSample extends Model
{
    use SoftDeletes;

    // Soft deletes use this column (see the soft-delete migration).
    const DELETED_AT = 'deletedAt';

    protected $table = 'screening_samples';
    protected $primaryKey = 'sampleId';

    protected $fillable = [
        'clientId', 'visitId', 'facilityId',
        'cancerType', 'testType', 'specimenType', 'collectionMethod',
        'sampleDate', 'labSampleNumber', 'collectedBy', 'collectedById', 'notes',
        'status', 'bookedFacilityId',
        'result', 'resultSeverity', 'resultValue', 'resultDetail', 'resultMeta', 'resultAttachmentPath', 'resultAttachmentName', 'resultAttachmentMime', 'resultDate', 'resultNotes', 'resultEnteredById',
    ];

    protected $casts = [
        'sampleDate'  => 'date:Y-m-d',
        'resultDate'  => 'date:Y-m-d',
        'resultValue' => 'float',
        'resultMeta'  => 'array',
    ];

    // The storage path is internal; the browser only ever gets the file through the authenticated endpoint.
    protected $hidden = ['resultAttachmentPath'];

    protected $appends = ['sampleRef', 'hasAttachment'];

    public function getHasAttachmentAttribute(): bool
    {
        return !empty($this->resultAttachmentPath);
    }

    /** Reference to write on the sample / request form, e.g. HPV-000042, PSA-000007. */
    public function getSampleRefAttribute(): string
    {
        $prefix = ScreeningTestCatalog::find($this->testType)['refPrefix'] ?? 'SMP';
        return $prefix . '-' . str_pad((string) $this->sampleId, 6, '0', STR_PAD_LEFT);
    }

    /** Facility where the sample was collected / the test was performed. */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'facilityId', 'facilityId');
    }

    /** Where a booked test will be done (status "booked"). */
    public function bookedFacility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'bookedFacilityId', 'facilityId');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'clientId', 'clientId');
    }
}
