<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\HpvSample;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HpvSampleController extends Controller
{
    private const GENOTYPES = ['16', '18', '31', '33', '35', '39', '45', '51', '52', '56', '58', '59', '66', '68', 'other'];

    /**
     * Samples for the facilities this user can see.
     * status=collected (awaiting result, oldest first) | result_released (newest first) | all
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $status = $request->query('status', 'collected');
        $search = trim((string) $request->query('search', ''));
        $visible = $user->visibleFacilityIds();

        $query = HpvSample::with('client:clientId,fullName,gender,dateOfBirth,phoneNumber')
            ->when($visible !== null, fn ($q) => $q->whereIn('facilityId', $visible))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('clientId', 'like', "%{$search}%")
                        ->orWhere('labSampleNumber', 'like', "%{$search}%")
                        ->orWhereHas('client', function ($c) use ($search) {
                            $c->where('fullName', 'like', "%{$search}%")
                              ->orWhere('phoneNumber', 'like', "%{$search}%");
                        });
                });
            });

        $query = $status === 'collected'
            ? $query->orderBy('sampleDate')
            : $query->orderByDesc('resultDate')->orderByDesc('hpvSampleId');

        return response()->json(['samples' => $query->limit(200)->get()]);
    }

    /** Record that a sample was taken. */
    public function store(Request $request, string $clientId): JsonResponse
    {
        $user = auth('api')->user();
        $client = Client::where('clientId', $clientId)->firstOrFail();

        if (!$user->canAccessFacility($client->facilityId)) {
            abort(403, 'You cannot access this client');
        }

        if ($client->gender !== 'female') {
            return response()->json(['message' => 'HPV samples are only recorded for female clients.'], 422);
        }

        $data = $request->validate([
            'sampleDate'       => ['required', 'date', 'before_or_equal:today'],
            'collectionMethod' => ['required', 'in:clinician_collected,self_collected'],
            'labSampleNumber'  => ['nullable', 'string', 'max:100'],
            'collectedBy'      => ['nullable', 'string', 'max:255'],
            'notes'            => ['nullable', 'string', 'max:1000'],
        ]);

        $sample = HpvSample::create([
            ...$data,
            'clientId'      => $client->clientId,
            'facilityId'    => $user->facilityId,
            'collectedById' => $user->id,
            'status'        => 'collected',
        ]);

        return response()->json([
            'message' => 'HPV sample recorded.',
            'sample'  => $sample->load('client:clientId,fullName,gender,dateOfBirth,phoneNumber'),
        ], 201);
    }

    /** Enter (or correct) the lab result for a sample. */
    public function recordResult(Request $request, int $id): JsonResponse
    {
        $user = auth('api')->user();
        $sample = HpvSample::findOrFail($id);

        if (!$user->canAccessFacility($sample->facilityId)) {
            abort(403, 'You cannot access this sample');
        }

        $data = $request->validate([
            'result'      => ['required', 'in:positive,negative,indeterminate'],
            'hpvGenotype' => ['required_if:result,positive', 'nullable', 'in:' . implode(',', self::GENOTYPES)],
            'resultDate'  => ['required', 'date', 'before_or_equal:today', 'after_or_equal:' . $sample->sampleDate->toDateString()],
            'resultNotes' => ['nullable', 'string', 'max:1000'],
        ], [
            'resultDate.after_or_equal' => 'The result date cannot be earlier than the date the sample was taken.',
            'hpvGenotype.required_if'   => 'Select the HPV genotype for a positive result.',
        ]);

        $sample->update([
            'result'            => $data['result'],
            'hpvGenotype'       => $data['result'] === 'positive' ? ($data['hpvGenotype'] ?? null) : null,
            'resultDate'        => $data['resultDate'],
            'resultNotes'       => $data['resultNotes'] ?? null,
            'status'            => 'result_released',
            'resultEnteredById' => $user->id,
        ]);

        return response()->json([
            'message' => 'Result recorded.',
            'sample'  => $sample->fresh()->load('client:clientId,fullName,gender,dateOfBirth,phoneNumber'),
        ]);
    }
}
