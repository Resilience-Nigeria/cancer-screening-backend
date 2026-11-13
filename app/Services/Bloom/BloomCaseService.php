<?php

namespace App\Services\Bloom;

use App\Models\BloomAppointmentRequest;
use App\Models\BloomCase;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Turns Bloom bookings into cases and hands them to a community health worker
 * (an active NAVIGATOR user at the booking's facility).
 */
class BloomCaseService
{
    public static function navigatorRoleId(): ?int
    {
        static $id = false;
        if ($id === false) {
            $id = Role::where('roleName', 'NAVIGATOR')->value('roleId');
        }

        return $id ? (int) $id : null;
    }

    /** Active navigators at a facility. */
    public static function navigatorsAt(int $facilityId)
    {
        $roleId = self::navigatorRoleId();
        if (!$roleId) {
            return collect();
        }

        return User::where('role', $roleId)->where('facilityId', $facilityId)->where('status', 'active')
            ->orderBy('firstName')->get(['id', 'firstName', 'lastName', 'email', 'phoneNumber', 'facilityId']);
    }

    /** Creates the case for a booking (once) and assigns it automatically. */
    public static function openFor(BloomAppointmentRequest $appt): ?BloomCase
    {
        try {
            $case = BloomCase::firstOrCreate(
                ['appointment_id' => $appt->id],
                ['account_id' => $appt->account_id, 'facility_id' => $appt->facility_id]
            );
            if ($case->wasRecentlyCreated) {
                self::autoAssign($case);
            }

            return $case;
        } catch (\Throwable $e) {
            Log::error('Bloom case not created', ['appointment' => $appt->id, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /** Creates missing cases for bookings made before cases existed (or if creation failed). */
    public static function sync(): void
    {
        // whereNotNull matters: a NULL in a NOT IN list makes it match nothing (cases opened from the registry have no booking).
        BloomAppointmentRequest::whereNotIn('id', BloomCase::select('appointment_id')->whereNotNull('appointment_id'))
            ->whereIn('status', ['pending', 'confirmed', 'completed'])
            ->orderBy('id')->limit(200)->get()
            ->each(fn ($appt) => self::openFor($appt));
    }

    /** Gives the case to the navigator at its facility with the fewest open cases. */
    public static function autoAssign(BloomCase $case): void
    {
        $navigators = self::navigatorsAt((int) $case->facility_id);
        if ($navigators->isEmpty()) {
            return; // an admin assigns it, or it goes to the first navigator approved at this facility
        }

        $load = BloomCase::whereIn('navigator_id', $navigators->pluck('id'))->whereNull('closed_at')
            ->selectRaw('navigator_id, COUNT(*) as n')->groupBy('navigator_id')->pluck('n', 'navigator_id');

        $pick = $navigators->sortBy(fn ($u) => (int) ($load[$u->id] ?? 0))->first();
        $case->update(['navigator_id' => $pick->id, 'assigned_by' => null, 'assigned_at' => now()]);
        BloomStaffNotifier::notify($pick->id, 'case_assigned', 'New case assigned: ' . $case->code(), 'A woman booked screening at your facility. Open the case to start the community follow-up.', $case->id);
    }

    /** After a navigator is approved: give them the facility's waiting, unassigned cases. */
    public static function assignWaiting(int $facilityId): void
    {
        BloomCase::where('facility_id', $facilityId)->whereNull('navigator_id')->whereNull('closed_at')
            ->get()->each(fn ($c) => self::autoAssign($c));
    }

    /** A booking the woman cancelled (or the centre declined) before any follow-up closes the case. */
    public static function closeIfWithdrawn(BloomAppointmentRequest $appt): void
    {
        $case = BloomCase::where('appointment_id', $appt->id)->first();
        if ($case && !$case->closed_at && $case->step_community === 'not-started' && in_array($appt->status, ['cancelled', 'declined'], true)) {
            $case->update(['closed_at' => now()]);
        }
    }
}
