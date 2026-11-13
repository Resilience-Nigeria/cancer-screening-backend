<?php

namespace App\Services\Bloom;

use App\Models\BloomCase;
use App\Models\Client;
use App\Models\Facility;
use App\Models\User;
use App\Services\LgaCodeMapping;
use App\Support\Bloom\NigerianPhone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Links a Bloom woman to her NCSR client record: finds her by phone or email, or registers her
 * (the Client ID is generated the same way as ClientController::generateClientId).
 */
class BloomNcsrClientService
{
    /** Phone formats an NCSR client record may hold for the same number. */
    public static function phoneVariants(?string $raw): array
    {
        $n = NigerianPhone::normalize($raw);
        return $n ? [NigerianPhone::local($n), $n, '+' . $n] : array_filter([$raw]);
    }

    /** The existing NCSR client for this case's woman, if any (does not create). */
    public static function find(BloomCase $case): ?Client
    {
        if ($case->ncsr_client_id) {
            $c = Client::where('clientId', $case->ncsr_client_id)->first();
            if ($c) {
                return $c;
            }
            // The Client ID may have been replaced after a state/LGA correction.
            $newId = DB::table('client_id_changes')->where('oldClientId', $case->ncsr_client_id)->orderByDesc('id')->value('newClientId');
            if ($newId && ($c = Client::where('clientId', $newId)->first())) {
                $case->update(['ncsr_client_id' => $c->clientId]);
                return $c;
            }
        }

        $account = $case->account;
        if (!$account) {
            return null;
        }
        $c = Client::whereIn('phoneNumber', self::phoneVariants($account->phone))->orderBy('phoneNotClients')->first();
        if (!$c && $account->email) {
            $c = Client::where('email', $account->email)->first();
        }

        return $c;
    }

    /**
     * Returns the NCSR client for the case, registering her if needed.
     * $input: dateOfBirth (Y-m-d) or age, stateOfResidence, lgaOfResidence, address (all optional except
     * that a date of birth or age is required to register).
     */
    public static function ensure(BloomCase $case, User $user, array $input): Client
    {
        $existing = self::find($case);
        if ($existing) {
            if ($case->ncsr_client_id !== $existing->clientId) {
                $case->update(['ncsr_client_id' => $existing->clientId]);
            }
            return $existing;
        }

        $account  = $case->account;
        $facility = Facility::where('facilityId', $user->facilityId)->firstOrFail();

        $state = trim((string) ($input['stateOfResidence'] ?? '')) ?: (string) $facility->facilityState;
        $lga   = trim((string) ($input['lgaOfResidence'] ?? '')) ?: (string) $facility->facilityLga;

        $dob = $input['dateOfBirth'] ?? null;
        $estimated = false;
        if (!$dob) {
            $age = $input['age'] ?? $account->age;
            $dob = now()->subYears((int) $age)->startOfYear()->toDateString();
            $estimated = true;
        }

        $phone = NigerianPhone::normalize($account->phone);

        return DB::transaction(function () use ($case, $account, $facility, $state, $lga, $dob, $estimated, $phone, $input) {
            $fields = [
                'clientId'          => self::generateClientId($facility, $state, $lga),
                'facilityId'        => $facility->facilityId,
                'fullName'          => $account->name,
                'gender'            => 'female',
                'dateOfBirth'       => $dob,
                'dobEstimated'      => $estimated,
                'phoneNumber'       => $phone ? NigerianPhone::local($phone) : $account->phone,
                'phoneNotClients'   => false,
                'email'             => $account->email,
                'screeningCategory' => 'new_client',
                // Origin is not asked in Bloom; residence is used until a clinician corrects it.
                'stateOfOrigin'     => $state,
                'lgaOfOrigin'       => $lga,
                'stateOfResidence'  => $state,
                'lgaOfResidence'    => $lga,
                'address'           => isset($input['address']) ? trim((string) $input['address']) ?: null : null,
                'registrationDate'  => now()->toDateString(),
            ];

            // Same safety as ClientController::existingColumns: skip columns not migrated yet.
            $columns = array_flip(Schema::getColumnListing('clients'));
            $fields = array_filter($fields, fn ($k) => isset($columns[$k]), ARRAY_FILTER_USE_KEY);

            $client = new Client();
            $client->forceFill($fields)->save();

            $case->update(['ncsr_client_id' => $client->clientId]);

            return $client;
        });
    }

    /** Copy of ClientController::generateClientId (it is protected there). FAC-STA-LGA-000001 */
    public static function generateClientId(Facility $facility, string $state, string $lga): string
    {
        $facilityCode = strtoupper((string) $facility->facilityCode);
        $stateCode = strtoupper(substr(str_replace(' ', '', $state), 0, 3));
        $lgaCode = LgaCodeMapping::getLgaCode($state, $lga);
        if (!$lgaCode) {
            Log::warning('LGA not found in mapping (Bloom registration)', ['state' => $state, 'lga' => $lga]);
            $lgaCode = strtoupper(substr(str_replace(' ', '', $lga), 0, 3));
        }

        $prefix = "{$facilityCode}-{$stateCode}-{$lgaCode}-";
        $last = Client::where('clientId', 'like', $prefix . '%')->orderByDesc('clientId')->lockForUpdate()->first();
        $next = 1;
        if ($last) {
            $parts = explode('-', $last->clientId);
            if (count($parts) === 4) {
                $next = (int) end($parts) + 1;
            }
        }

        return $prefix . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
