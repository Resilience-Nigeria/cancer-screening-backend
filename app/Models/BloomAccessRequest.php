<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A health worker asking to attend to an NCSR client at her facility. */
class BloomAccessRequest extends Model
{
    protected $table = 'bloom_access_requests';

    protected $fillable = ['user_id', 'client_id', 'facility_id', 'reason', 'status', 'decision_note', 'decided_by', 'decided_at', 'case_id'];

    protected $casts = ['decided_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id', 'clientId');
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'facility_id', 'facilityId');
    }
}
