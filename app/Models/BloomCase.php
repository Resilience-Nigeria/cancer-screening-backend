<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A Bloom booking followed up by a community health worker (NAVIGATOR) in four steps. */
class BloomCase extends Model
{
    protected $table = 'bloom_cases';

    public const STEPS = ['step_community', 'step_phc', 'step_referral', 'step_followup'];

    protected $fillable = [
        'appointment_id', 'account_id', 'ncsr_client_id', 'facility_id', 'navigator_id', 'assigned_by', 'assigned_at',
        'step_community', 'step_phc', 'step_referral', 'step_followup',
        'outcome', 'notes', 'referral_facility', 'referral_date', 'follow_up_date', 'closed_at',
    ];

    protected $casts = ['assigned_at' => 'datetime', 'closed_at' => 'datetime'];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(BloomAppointmentRequest::class, 'appointment_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(BloomAccount::class, 'account_id');
    }

    /** The NCSR registry record (cases opened from an access request have no Bloom booking/account). */
    public function ncsrClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'ncsr_client_id', 'clientId');
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'facility_id', 'facilityId');
    }

    public function navigator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'navigator_id');
    }

    public function code(): string
    {
        return 'BLM-' . str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    /** new | community | screening | referral | completed | withdrawn */
    public function stage(): string
    {
        $steps = array_map(fn ($s) => $this->{$s}, self::STEPS);
        if ($this->closed_at) {
            return in_array('completed', $steps, true) ? 'completed' : 'withdrawn';
        }
        if (count(array_filter($steps, fn ($s) => $s === 'not-started')) === 4) {
            return 'new';
        }
        foreach ($steps as $i => $s) {
            if ($s === 'in-progress' || $s === 'not-started') {
                return ['community', 'screening', 'referral', 'referral'][$i];
            }
        }

        return 'completed';
    }
}
