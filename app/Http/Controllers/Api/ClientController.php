<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Http\Requests\SearchClientRequest;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\ClientReferral;
use App\Models\Facility;
use App\Services\LgaCodeMapping;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Schema;

class ClientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $search = $request->string('search')->toString();

        $query = Client::with(['facility', 'latestRiskProfile', 'outcome', 'latestReferral'])
    ->when($user->visibleFacilityIds() !== null, fn ($q) => $q->whereIn('facilityId', $user->visibleFacilityIds()))
    ->when($search, function ($q) use ($search) {
        $q->where(function ($sub) use ($search) {
            $sub->where('fullName', 'like', "%{$search}%")
                ->orWhere('phoneNumber', 'like', "%{$search}%")
                ->orWhere('clientId', 'like', "%{$search}%")
                ->orWhere('hospitalNumber', 'like', "%{$search}%");
        });
    })
    ->latest();

        return response()->json($query->paginate(20));
    }

    /**
     * Clients currently linked/referred to my facility — the default
     * "inbox" view of who's been sent here from a previous stage.
     *
     * Hub facilities get a "history" mode: instead of only pending/
     * accepted referrals, load every referral of a selected stage
     * transition (including completed ones), so a Hub can see its full
     * referral history rather than just the current queue.
     */
    public function linked(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $facility = $user->facility;
        $visibleIds = $user->visibleFacilityIds();

        $isHub = $facility && $facility->facilityLevel === 'hub';
        $historyMode = $isHub && $request->boolean('history');

        $referralsQuery = ClientReferral::with(['client.facility', 'fromFacility', 'toFacility'])
            ->when($visibleIds !== null, fn ($q) => $q->whereIn('toFacilityId', $visibleIds))
            ->when($visibleIds === null && $facility, fn ($q) => $q->where('toFacilityId', $facility->facilityId));

        if (!$historyMode) {
            $referralsQuery->whereIn('status', ['pending', 'accepted']);
        }

        if ($request->filled('stage')) {
            $referralsQuery->where('referralType', $request->stage);
        }

        $referrals = $referralsQuery->latest('referralDate')->paginate(20);

        return response()->json([
            'referrals' => $referrals,
            'isHub' => $isHub,
        ]);
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $user = auth('api')->user();
        $facilityId = $user->facilityId;

        // Caregiver / OTP-skip fields are read straight from the request, so they are saved even
        // when StoreClientRequest has no rules for them and Client::$fillable doesn't list them.
        $extra = $this->caregiverAndOtpFields($request);

        $client = DB::transaction(function () use ($request, $facilityId, $extra) {
            $validated = $request->validated();
            
            // Get facility for code
            $facility = Facility::findOrFail($facilityId);
            
            // Generate clientId based on facility, state and LGA of residence
            $clientId = $this->generateClientId(
                $facility,
                $validated['stateOfResidence'], 
                $validated['lgaOfResidence']
            );

            $client = Client::create([
                ...$this->existingColumns($validated),
                'clientId' => $clientId,
                'facilityId' => $facilityId,
            ]);

            // forceFill bypasses $fillable, so this works before the model is updated.
            $client->forceFill($extra)->save();

            return $client->load('facility');
        });

        return response()->json([
            'message' => 'Client created successfully',
            'client' => $client,
        ], 201);
    }

    /**
     * Show client by clientId (string format: FMCJ-ABI-ABS-000001)
     */
    public function show(string $clientId): JsonResponse
    {
        $client = Client::where('clientId', $clientId)->firstOrFail();
        
        $this->authorizeClient($client);

        $client->load([
            'facility',
            'riskProfiles',
            'visits.cervicalScreening',
            'visits.breastScreening',
            'visits.colorectalScreening',
            'visits.liverScreening',
            'visits.prostateScreening',
            'visits.examination',
            'outcome',
        ]);

        return response()->json([
            'client' => $client,
        ]);
    }

    /**
     * Update client by clientId (string format: FMCJ-ABI-ABS-000001)
     */
    public function update(UpdateClientRequest $request, string $clientId): JsonResponse
    {
        $client = Client::where('clientId', $clientId)->firstOrFail();
        
        $this->authorizeClient($client);

        $validated = $request->validated();

        // The Client ID is built from the state and LGA of residence. Only an admin can change them, and
        // doing so issues a new Client ID. The first save returns 409 with the new ID so the screen can ask
        // the admin to confirm; the confirmed save (confirmIdChange + idChangeReason) makes the change.
        $newState = array_key_exists('stateOfResidence', $validated) ? trim((string) $validated['stateOfResidence']) : trim((string) $client->stateOfResidence);
        $newLga   = array_key_exists('lgaOfResidence', $validated) ? trim((string) $validated['lgaOfResidence']) : trim((string) $client->lgaOfResidence);
        unset($validated['stateOfResidence'], $validated['lgaOfResidence']);

        $residenceChanged = $newState !== trim((string) $client->stateOfResidence)
            || $newLga !== trim((string) $client->lgaOfResidence);

        if ($residenceChanged) {
            $user = auth('api')->user();
            if (!$this->canChangeResidence($user)) {
                throw ValidationException::withMessages([
                    'stateOfResidence' => ["Only an admin can change the state or LGA of residence, because the Client ID is based on them."],
                ]);
            }
            if ($newState === '' || $newLga === '') {
                throw ValidationException::withMessages(['lgaOfResidence' => ['Select both the state and the LGA of residence.']]);
            }

            if (!$request->boolean('confirmIdChange')) {
                $preview = $this->generateClientId(Facility::findOrFail($client->facilityId), $newState, $newLga);
                return response()->json([
                    'message'          => 'Changing the state or LGA of residence changes the Client ID. Please confirm.',
                    'requiresIdChange' => true,
                    'oldClientId'      => $client->clientId,
                    'newClientId'      => $preview,
                    'stateOfResidence' => $newState,
                    'lgaOfResidence'   => $newLga,
                ], 409);
            }

            $reason = trim((string) $request->input('idChangeReason', ''));
            if (mb_strlen($reason) < 5) {
                throw ValidationException::withMessages(['idChangeReason' => ['Give a short reason for changing the Client ID.']]);
            }

            $newId = $this->applyResidenceChange($client, $newState, $newLga, $reason, $user);
            $client = Client::where('clientId', $newId)->firstOrFail();
        }

        $client->update($this->existingColumns($validated));

        if ($request->has('phoneNotClients')) {
            $extra = $this->caregiverAndOtpFields($request);
            $client->forceFill([
                'phoneNotClients' => $extra['phoneNotClients'],
                'caregiverName' => $extra['caregiverName'],
                'caregiverPhoneNumber' => $extra['caregiverPhoneNumber'],
            ])->save();
        }

        return response()->json([
            'message' => 'Client updated successfully',
            'client' => $client->fresh('facility'),
        ]);
    }

    /**
     * Validates and normalises the caregiver-phone and OTP-skip fields sent by the registration form.
     * The caregiver name is kept only when the supplied number is the caregiver's, and the skip
     * reason / user are kept only when the OTP was actually skipped.
     */
    protected function caregiverAndOtpFields(Request $request): array
    {
        $data = $request->validate([
            'phoneNotClients' => ['sometimes', 'nullable', 'boolean'],
            'caregiverName'   => ['sometimes', 'nullable', 'string', 'max:255'],
            'caregiverPhoneNumber' => ['sometimes', 'nullable', 'string', 'max:30'],
            'otpSkipped'      => ['sometimes', 'nullable', 'boolean'],
            'otpSkipReason'   => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $notClients = (bool) ($data['phoneNotClients'] ?? false);
        $skipped = (bool) ($data['otpSkipped'] ?? false);

        return [
            'phoneNotClients' => $notClients,
            'caregiverName'   => ($data['caregiverName'] ?? null) ?: null,
            'caregiverPhoneNumber' => ($data['caregiverPhoneNumber'] ?? null) ?: null,
            'otpSkipped'      => $skipped,
            'otpSkipReason'   => $skipped ? ($data['otpSkipReason'] ?? null) : null,
            'otpSkippedBy'    => $skipped ? auth('api')->id() : null,
        ];
    }

    /**
     * Drops fields whose column does not exist yet (e.g. dobEstimated / offlineId before their migration
     * has run), so a missing migration can't break saving a client.
     */
    private function existingColumns(array $data): array
    {
        static $columns = null;
        $columns ??= array_flip(Schema::getColumnListing('clients'));
        foreach (['dobEstimated', 'offlineId'] as $optional) {
            if (!isset($columns[$optional])) {
                unset($data[$optional]);
            }
        }
        return $data;
    }

    private function canChangeResidence($user): bool
    {
        // NICRAT super admin, NICRAT admin and Hospital Admin.
        return $user->isSuperAdmin()
            || $user->isNicratStaff()
            || (method_exists($user, 'isHospitalAdminRole') && $user->isHospitalAdminRole());
    }

    /** GET /clients/id-change-access — lets the UI know whether to offer the state/LGA correction. */
    public function idChangeAccess(): JsonResponse
    {
        return response()->json(['canChangeResidence' => $this->canChangeResidence(auth('api')->user())]);
    }

    /**
     * PATCH /clients/{clientId}/residence
     * { stateOfResidence, lgaOfResidence, reason, confirm: true }  — or { ..., preview: true } to only see the new ID.
     *
     * Corrects the state / LGA of residence and issues the Client ID that goes with it. The new ID is then
     * written to every table that has a clientId column (visits, samples, referrals, risk profiles, ...), in
     * one transaction, and the change is logged in client_id_changes so the old ID still finds the client.
     */
    public function changeResidence(Request $request, string $clientId): JsonResponse
    {
        $user = auth('api')->user();
        abort_if(!$this->canChangeResidence($user), 403, 'Only an admin can change a client\'s state or LGA of residence.');

        $client = Client::where('clientId', $clientId)->firstOrFail();
        $this->authorizeClient($client);

        $data = $request->validate([
            'stateOfResidence' => ['required', 'string', 'max:255'],
            'lgaOfResidence'   => ['required', 'string', 'max:255'],
            'preview'          => ['sometimes', 'boolean'],
            'reason'           => ['required_unless:preview,true', 'nullable', 'string', 'min:5', 'max:500'],
            'confirm'          => ['required_unless:preview,true', 'nullable', 'accepted'],
        ]);

        $facility = Facility::findOrFail($client->facilityId);
        $oldId = $client->clientId;

        $sameResidence = trim($data['stateOfResidence']) === trim((string) $client->stateOfResidence)
            && trim($data['lgaOfResidence']) === trim((string) $client->lgaOfResidence);
        if ($sameResidence) {
            return response()->json(['changed' => false, 'oldClientId' => $oldId, 'newClientId' => $oldId, 'client' => $client]);
        }

        $newId = $this->generateClientId($facility, $data['stateOfResidence'], $data['lgaOfResidence']);

        if (filter_var($data['preview'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return response()->json(['changed' => false, 'preview' => true, 'oldClientId' => $oldId, 'newClientId' => $newId]);
        }

        $this->applyResidenceChange($client, $data['stateOfResidence'], $data['lgaOfResidence'], $data['reason'], $user, $newId);

        return response()->json([
            'changed' => true,
            'message' => "Client ID changed from {$oldId} to {$newId}.",
            'oldClientId' => $oldId,
            'newClientId' => $newId,
            'client' => Client::where('clientId', $newId)->first()?->load('facility'),
        ]);
    }

    /**
     * Gives the client the Client ID that matches the new state/LGA of residence and writes it to every
     * table with a clientId column, in one transaction. Logged in client_id_changes. Returns the new ID.
     */
    private function applyResidenceChange(Client $client, string $state, string $lga, string $reason, $user, ?string $newId = null): string
    {
        $oldId = $client->clientId;
        $newId ??= $this->generateClientId(Facility::findOrFail($client->facilityId), $state, $lga);
        $oldState = $client->stateOfResidence;
        $oldLga = $client->lgaOfResidence;
        $driver = DB::connection()->getDriverName();

        try {
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
            }

            DB::transaction(function () use ($state, $lga, $reason, $user, $oldId, $newId, $oldState, $oldLga) {
                // Every real table that has a clientId column (the clients table itself is the key, handled below).
                $tables = collect(DB::select(
                    "SELECT DISTINCT c.TABLE_NAME AS t
                       FROM information_schema.COLUMNS c
                       JOIN information_schema.TABLES tb
                         ON tb.TABLE_SCHEMA = c.TABLE_SCHEMA AND tb.TABLE_NAME = c.TABLE_NAME
                      WHERE c.TABLE_SCHEMA = DATABASE()
                        AND tb.TABLE_TYPE = 'BASE TABLE'
                        AND LOWER(c.COLUMN_NAME) = 'clientid'"
                ))->pluck('t')->all();

                DB::table('clients')->where('clientId', $oldId)->update([
                    'clientId'         => $newId,
                    'stateOfResidence' => $state,
                    'lgaOfResidence'   => $lga,
                    'updated_at'       => now(),
                ]);

                foreach ($tables as $table) {
                    if (strtolower($table) === 'clients') continue;
                    DB::table($table)->where('clientId', $oldId)->update(['clientId' => $newId]);
                }

                DB::table('client_id_changes')->insert([
                    'oldClientId' => $oldId,
                    'newClientId' => $newId,
                    'oldStateOfResidence' => $oldState,
                    'oldLgaOfResidence'   => $oldLga,
                    'newStateOfResidence' => $state,
                    'newLgaOfResidence'   => $lga,
                    'reason'      => $reason,
                    'changedById' => $user->id,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            });
        } finally {
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        }

        ActivityLog::record('client_id_changed', "Client ID changed from {$oldId} to {$newId} (residence corrected).", [
            'subjectType' => 'client',
            'subjectId'   => $newId,
            'clientId'    => $newId,
            'facilityId'  => $client->facilityId,
            'details'     => [
                'oldClientId'  => $oldId,
                'newClientId'  => $newId,
                'clientName'   => $client->fullName,
                'oldResidence' => trim(($oldLga ?? '') . ', ' . ($oldState ?? ''), ', '),
                'newResidence' => trim("{$lga}, {$state}", ', '),
                'reason'       => $reason,
            ],
        ]);

        return $newId;
    }

    protected function authorizeClient(Client $client): void
    {
        $user = auth('api')->user();

        if (!$user->canAccessFacility($client->facilityId)) {
            abort(403, 'You cannot access this client');
        }
    }

    /**
     * Generate unique client ID based on facility, state and LGA of residence
     * Format: FAC-XXX-YYY-000001
     * Where FAC = Facility code, XXX = State code, YYY = LGA code, 000001 = sequential number
     * 
     * Uses LgaCodeMapping service to ensure unique LGA codes (e.g., Aba North = ABN, Aba South = ABS)
     */
    protected function generateClientId(Facility $facility, string $state, string $lga): string
    {
        // Get facility code (uppercase)
        $facilityCode = strtoupper($facility->facilityCode);
        
        // Get state code (first 3 letters, remove spaces)
        $stateCode = strtoupper(substr(str_replace(' ', '', $state), 0, 3));
        
        // Get unique LGA code from the mapping service
        $lgaCode = LgaCodeMapping::getLgaCode($state, $lga);
        
        // Fallback if LGA not found in mapping (shouldn't happen with proper validation)
        if (!$lgaCode) {
            // Log this for debugging - it means the LGA isn't in our mapping
            \Log::warning("LGA not found in mapping", [
                'state' => $state,
                'lga' => $lga
            ]);
            
            // Use first 3 characters as fallback
            $lgaCode = strtoupper(substr(str_replace(' ', '', $lga), 0, 3));
        }
        
        // Create prefix: FACILITY-STATE-LGA-
        $prefix = $facilityCode . '-' . $stateCode . '-' . $lgaCode . '-';
        
        // Find the last client with this facility/state/LGA combination
        $lastClient = Client::where('clientId', 'like', $prefix . '%')
            ->orderByDesc('clientId')
            ->first();
        
        $nextNumber = 1;
        
        if ($lastClient) {
            // Extract number from format: UCTH-PLA-JNO-000001
            $parts = explode('-', $lastClient->clientId);
            if (count($parts) === 4) {
                $lastNumber = (int) end($parts);
                $nextNumber = $lastNumber + 1;
            }
        }
        
        // Return formatted client ID: UCTH-PLA-JNO-000001
        return $prefix . str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
    }



    public function search(Request $request)
{
    $search = trim($request->search);

    // Exact Client ID wins. A caregiver's number can be shared by several clients, so for a phone
    // search prefer the client who actually owns the number (phoneNotClients = false) first.
    $client = Client::where('clientId', $search)->first()
        ?? Client::where('hospitalNumber', $search)->first()
        ?? Client::where('phoneNumber', $search)->orderBy('phoneNotClients')->first();

    // A card or record may still carry an ID that was replaced after a state/LGA correction.
    if (!$client) {
        $oldId = DB::table('client_id_changes')->where('oldClientId', $search)->orderByDesc('id')->value('newClientId');
        $client = $oldId ? Client::where('clientId', $oldId)->first() : null;
    }

    if (!$client) {
        return response()->json([
            'message' => 'Client not found'
        ]);
    }

    return response()->json([
        'client' => $client
    ]);
}

/**
 * Checks whether a phone number or email is already registered to a
 * client, so Stage 2's biodata form can warn before creating a
 * duplicate record. Returns each match with which field it hit.
 */
public function checkDuplicate(Request $request): JsonResponse
{
    $phone = trim((string) $request->query('phone', ''));
    $email = trim((string) $request->query('email', ''));

    $matches = [];

    if ($phone) {
        // Records whose number is a caregiver's are not duplicates of someone using that number as their own.
        $byPhone = Client::where('phoneNumber', $phone)
            ->where('phoneNotClients', false)
            ->first();
        if ($byPhone) {
            $matches[] = ['field' => 'phone', 'client' => $byPhone];
        }
    }

    if ($email) {
        $byEmail = Client::where('email', $email)->first();
        if ($byEmail && (!$phone || $byEmail->clientId !== ($matches[0]['client']->clientId ?? null))) {
            $matches[] = ['field' => 'email', 'client' => $byEmail];
        }
    }

    return response()->json(['matches' => $matches]);
}


//  public function search(SearchClientRequest $request): JsonResponse
//     {
//         return $search = trim($request->search);
//         $client = Client::where('clientId', $search)->firstOrFail();
        
//         $this->authorizeClient($client);

//         $client->update($request->validated());

//         return response()->json([
//             'message' => 'Client found',
//             'client' => $client,
//         ]);
//     }

}