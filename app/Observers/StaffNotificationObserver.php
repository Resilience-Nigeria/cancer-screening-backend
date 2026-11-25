<?php

namespace App\Observers;

use App\Services\StaffPushService;
use App\Support\StaffAudience;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Automatic staff notifications (bell + phone), without touching the existing controllers:
 *  - a client is referred to a facility        → staff of that facility
 *  - a sample deletion request is sent          → super admins / NICRAT admins
 *  - a deletion request is approved or rejected → the person who asked
 * Registered in AppServiceProvider for ClientReferral and SampleDeletionRequest.
 * A failure here never stops the referral / request itself.
 */
class StaffNotificationObserver
{
    public function created(Model $m): void
    {
        $this->safely(function () use ($m) {
            $me = auth('api')->id();
            $class = class_basename($m);

            if ($class === 'ClientReferral') {
                $to = (int) ($m->toFacilityId ?? 0);
                $users = StaffAudience::atFacility($to)->pluck('id')->reject(fn ($id) => $id === $me)->all();
                if (!$users) return;
                $client = $m->client->fullName ?? $m->clientId;
                $from = $m->fromFacility->facilityName ?? null;
                $urgent = in_array($m->referralType ?? '', ['confirmation_to_treatment'], true) ? 'for treatment' : 'for further diagnosis';
                StaffPushService::notify($users, 'New referral', "{$client} was referred to your facility {$urgent}" . ($from ? " from {$from}." : '.'), [
                    'type' => 'referral', 'route' => '/referrals', 'data' => ['clientId' => $m->clientId, 'referralId' => $m->getKey()],
                ]);
            }

            if ($class === 'SampleDeletionRequest') {
                $admins = StaffAudience::admins()->pluck('id')->reject(fn ($id) => $id === $me)->all();
                if (!$admins) return;
                $client = $m->client->fullName ?? $m->clientId;
                StaffPushService::notify($admins, 'Deletion request waiting', "A deletion was requested for a test of {$client}: \"" . mb_substr((string) $m->reason, 0, 120) . '"', [
                    'type' => 'deletion_request', 'route' => '/deletion-requests', 'data' => ['requestId' => $m->getKey(), 'clientId' => $m->clientId],
                ]);
            }
        });
    }

    public function updated(Model $m): void
    {
        $this->safely(function () use ($m) {
            if (class_basename($m) !== 'SampleDeletionRequest' || !$m->wasChanged('status')) return;
            if (!in_array($m->status, ['approved', 'rejected'], true) || !$m->requestedById) return;
            if ((int) $m->requestedById === (int) auth('api')->id()) return;
            $client = $m->client->fullName ?? $m->clientId;
            $approved = $m->status === 'approved';
            StaffPushService::notify([(int) $m->requestedById], $approved ? 'Deletion approved' : 'Deletion request rejected',
                ($approved ? "The test record of {$client} you asked to delete was deleted." : "Your request to delete a test record of {$client} was rejected.")
                . ($m->decisionNote ? " Note: {$m->decisionNote}" : ''), [
                    'type' => 'deletion_decision', 'route' => '/tests', 'data' => ['requestId' => $m->getKey(), 'clientId' => $m->clientId],
                ]);
        });
    }

    private function safely(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            Log::warning('Staff notification skipped: ' . $e->getMessage());
        }
    }
}
