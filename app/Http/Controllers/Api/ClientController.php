<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Client;
use App\Models\Facility;
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
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('facilityId', $user->facilityId))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('fullName', 'like', "%{$search}%")
                        ->orWhere('phoneNumber', 'like', "%{$search}%")
                        ->orWhere('screeningId', 'like', "%{$search}%");
                });
            })
            ->latest();

        return response()->json($query->paginate(20));
    }

    // public function store(StoreClientRequest $request): JsonResponse
    // {
    //     $user = auth('api')->user();
    //     $facilityId = $user->facilityId;

    //     $client = DB::transaction(function () use ($request, $facilityId) {
    //         $facility = Facility::findOrFail($facilityId);

    //         $client = Client::create([
    //             ...$request->validated(),
    //             'facilityId' => $facilityId,
    //             'screeningId' => $this->generateScreeningId($facility),
    //         ]);

    //         return $client->load('facility');
    //     });

    //     return response()->json([
    //         'message' => 'Client created successfully',
    //         'client' => $client,
    //     ], 201);
    // }


    public function store(StoreClientRequest $request): JsonResponse
{
    $user = auth('api')->user();
    $facilityId = $user->facilityId;

    $client = DB::transaction(function () use ($request, $facilityId) {
        $validated = $request->validated();
        
        // Generate clientId based on state and LGA
        $clientId = $this->generateClientId(
            $validated['state'], 
            $validated['lga']
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

    public function show(Client $client): JsonResponse
    {
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

    public function update(UpdateClientRequest $request, Client $client): JsonResponse
    {
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

        if (!$user->isSuperAdmin() && $client->facilityId !== $user->facilityId) {
            abort(403, 'You cannot access this client');
        }
    }

    protected function generateScreeningId(Facility $facility): string
    {
        $year = Carbon::now()->format('Y');
        $prefix = strtoupper($facility->code) . '-' . $year . '-';

        $lastClient = Client::where('screeningId', 'like', $prefix . '%')
            ->orderByDesc('clientId')
            ->first();

        $nextNumber = 1;

        if ($lastClient) {
            $parts = explode('-', $lastClient->screening_id);
            $lastNumber = (int) end($parts);
            $nextNumber = $lastNumber + 1;
        }

        return $prefix . str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
    }



    protected function generateClientId(string $state, string $lga): string
{
    // Get state code (first 3 letters)
    $stateCode = strtoupper(substr(str_replace(' ', '', $state), 0, 3));
    
    // Get LGA code (first 3 letters)
    $lgaCode = strtoupper(substr(str_replace(' ', '', $lga), 0, 3));
    
    // Find last client with this state/LGA combo
    $prefix = $stateCode . '/' . $lgaCode . '/';
    
    $lastClient = Client::where('clientId', 'like', $prefix . '%')
        ->orderByDesc('clientId')
        ->first();
    
    $nextNumber = 1;
    
    if ($lastClient) {
        // Extract number from format: PLA/JON/000001
        $parts = explode('/', $lastClient->clientId);
        if (count($parts) === 3) {
            $lastNumber = (int) $parts[2];
            $nextNumber = $lastNumber + 1;
        }
    }
    
    return $prefix . str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
}
}