<?php

namespace App\Http\Controllers\Api\Bloom;

use App\Models\BloomCase;
use App\Models\User;
use App\Support\Bloom\NigerianPhone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/** Shared helpers for the health-worker (navigator) and admin case endpoints. */
trait CaseConcerns
{
    private const TYPE_LABEL = [
        'general'    => 'Breast screening',
        'mammogram'  => 'Mammogram',
        'cbe'        => 'Clinical breast exam',
        'ultrasound' => 'Ultrasound',
        'biopsy'     => 'Biopsy',
        'mri'        => 'MRI',
    ];

    protected function staff(): ?User
    {
        return Auth::guard('api')->user();
    }

    /** Super admin, NICRAT admin and hospital admins review health workers and assign cases. */
    protected function canReview(?User $u): bool
    {
        return $u && ($u->isSuperAdmin() || $u->isNicratAdmin() || $u->isHospitalAdminRole());
    }

    protected function isActiveNavigator(?User $u): bool
    {
        return $u && $u->isNavigator() && $u->status === 'active';
    }

    protected function deny(string $message = 'You do not have access to this.'): JsonResponse
    {
        return response()->json(['status' => false, 'code' => 'FORBIDDEN', 'message' => $message], 403);
    }

    /** Restricts a facility-keyed query to what the reviewer may see. */
    protected function scopeFacilities(Builder $q, User $u, string $column = 'facility_id'): Builder
    {
        $ids = $u->visibleFacilityIds();
        if ($ids !== null) {
            $q->whereIn($column, $ids ?: [0]);
        }

        return $q;
    }

    /** SQL version of BloomCase::stage() for filtering. */
    protected function whereStage(Builder $q, string $stage): Builder
    {
        return match ($stage) {
            'new'       => $q->whereNull('closed_at')->where('step_community', 'not-started'),
            'community' => $q->whereNull('closed_at')->where('step_community', 'in-progress'),
            'screening' => $q->whereNull('closed_at')->where('step_community', 'completed')->where('step_phc', '!=', 'completed'),
            'referral'  => $q->whereNull('closed_at')->where('step_phc', 'completed'),
            'completed' => $q->whereNotNull('closed_at')->where('step_community', '!=', 'not-started'),
            'withdrawn' => $q->whereNotNull('closed_at')->where('step_community', 'not-started'),
            'open'      => $q->whereNull('closed_at'),
            default     => $q,
        };
    }

    /** Age from the NCSR client record (uses its age attribute if the model has one). */
    protected static function clientAge($client): ?int
    {
        if (isset($client->age) && is_numeric($client->age)) {
            return (int) $client->age;
        }
        try {
            return $client->dateOfBirth ? \Carbon\Carbon::parse($client->dateOfBirth)->age : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function presentCase(BloomCase $c, bool $withClient): array
    {
        $a  = $c->appointment;
        $f  = $c->facility;
        $n  = $c->navigator;
        $ac = $c->account;
        // Opened from the NCSR registry (access request): no Bloom booking, details come from the client record.
        $nc = (!$ac && $c->ncsr_client_id) ? $c->ncsrClient : null;

        return [
            'id'              => $c->id,
            'code'            => $c->code(),
            'ncsrClientId'    => $c->ncsr_client_id,
            'stage'           => $c->stage(),
            'screening'       => $a ? (self::TYPE_LABEL[$a->type ?? 'general'] ?? 'Breast screening') : 'Breast follow-up (from registry)',
            'source'          => $a ? 'booking' : 'registry',
            'facility'        => $f ? ['id' => (int) $f->facilityId, 'name' => $f->facilityName, 'lga' => $f->facilityLga ?? null, 'state' => $f->facilityState ?? null] : null,
            'area'            => $f ? ($f->facilityLga ?? $f->facilityState ?? $f->facilityName) : null,
            'requestedAt'     => optional($c->created_at)->toIso8601String(),
            'appointment'     => $a ? [
                'id'            => $a->id,
                'status'        => $a->status,
                'date'          => optional($a->preferred_date)->toDateString(),
                'time'          => $a->preferred_time,
                'confirmedDate' => optional($a->confirmed_date)->toDateString(),
                'confirmedTime' => $a->confirmed_time,
            ] : null,
            'client'          => !$withClient ? null : ($ac ? [
                'name'  => $ac->name,
                'phone' => $ac->phone ? NigerianPhone::local($ac->phone) : null,
                'age'   => $ac->age,
            ] : ($nc ? [
                'name'  => $nc->fullName,
                'phone' => $nc->phoneNumber,
                'age'   => self::clientAge($nc),
            ] : null)),
            'steps'           => [$c->step_community, $c->step_phc, $c->step_referral, $c->step_followup],
            'outcome'         => $c->outcome,
            'notes'           => $c->notes,
            'referralFacility' => $c->referral_facility,
            'referralDate'    => $c->referral_date,
            'followUpDate'    => $c->follow_up_date,
            'navigator'       => $n ? ['id' => $n->id, 'name' => trim("{$n->firstName} {$n->lastName}"), 'phone' => $n->phoneNumber] : null,
            'assignedAt'      => optional($c->assigned_at)->toIso8601String(),
            'assignedBy'      => $c->assigned_by ? 'admin' : ($c->navigator_id ? 'auto' : null),
            'closedAt'        => optional($c->closed_at)->toIso8601String(),
        ];
    }
}
