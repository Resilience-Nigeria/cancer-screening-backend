<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Http\Requests\SearchClientRequest;
use App\Models\Client;
use App\Models\Facility;
use App\Services\LgaCodeMapping;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ClientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $search = $request->string('search')->toString();

        $query = Client::with(['facility', 'latestRiskProfile', 'outcome'])
            ->when(!$user->isSuperAdmin() || !$user->isPartner(), fn ($q) => $q->where('facilityId', $user->facilityId))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('fullName', 'like', "%{$search}%")
                        ->orWhere('phoneNumber', 'like', "%{$search}%")
                        ->orWhere('clientId', 'like', "%{$search}%");
                });
            })
            ->latest();

        return response()->json($query->paginate(20));
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $user = auth('api')->user();
        $facilityId = $user->facilityId;

        $client = DB::transaction(function () use ($request, $facilityId) {
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
                ...$validated,
                'clientId' => $clientId,
                'facilityId' => $facilityId,
            ]);

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

        $client->update($request->validated());

        return response()->json([
            'message' => 'Client updated successfully',
            'client' => $client->fresh('facility'),
        ]);
    }

    protected function authorizeClient(Client $client): void
    {
        $user = auth('api')->user();

        if (!$user->isSuperAdmin()  || || !$user->isPartner()   && $client->facilityId !== $user->facilityId) {
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
        return $prefix . str_pad((string) $nextNumber, 10, '0', STR_PAD_LEFT);
    }



    public function search(Request $request)
{
    $search = trim($request->search);

    $client = Client::where('clientId', $search)
        ->orWhere('phoneNumber', $search)
        ->first();

    if (!$client) {
        return response()->json([
            'message' => 'Client not found'
        ]);
    }

    return response()->json([
        'client' => $client
    ]);
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