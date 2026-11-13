<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Bloom's copy of a breast screening sample/result that lives in NCSR screening_samples. */
class BloomCaseTest extends Model
{
    protected $table = 'bloom_case_tests';

    protected $fillable = [
        'case_id', 'sample_id', 'ncsr_client_id', 'cancer_type', 'test_type', 'status', 'sample_ref', 'sample_date',
        'specimen_type', 'collection_method', 'result', 'result_severity', 'result_detail', 'result_value',
        'result_meta', 'result_date', 'result_notes', 'recorded_by',
    ];

    protected $casts = ['sample_date' => 'date', 'result_date' => 'date', 'result_meta' => 'array', 'result_value' => 'float'];
}
