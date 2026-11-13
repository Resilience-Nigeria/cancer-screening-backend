<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BloomNavigatorSignup extends Model
{
    protected $table = 'bloom_navigator_signups';

    protected $fillable = ['user_id', 'facility_id', 'profession', 'staff_id', 'status', 'applicant_note', 'review_note', 'reviewed_by', 'reviewed_at'];

    protected $casts = ['reviewed_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'facility_id', 'facilityId');
    }
}
