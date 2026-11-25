<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * GET /clients/list — the client list with filters (NCSR Mobile App; the web can use it too).
 *   ?scope=all|mine         mine = clients registered by me (needs clients.registeredById)
 *   &search=                name, phone, Client ID, hospital number
 *   &facilityId=            one facility (only facilities the user can see; national users any)
 *   &gender=female|male
 *   &ageGroup=0-24|25-44|45-59|60-74|75+
 *   &screened=yes|no        has / has no clinical screening visit
 *   &dateFrom=&dateTo=      registration date
 *   &page=&perPage=
 * Same scope rules as GET /clients: national users see every facility, others their own facilities.
 * Response: Laravel paginator (data, current_page, last_page, total).
 */
class ClientListController extends Controller
{
    private const AGE_GROUPS = ['0-24' => [0, 24], '25-44' => [25, 44], '45-59' => [45, 59], '60-74' => [60, 74], '75+' => [75, 150]];

    public function index(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $visible = $user->visibleFacilityIds(); // null = every facility
        $search = trim((string) $request->query('search', ''));
        $facilityId = $request->filled('facilityId') && $request->facilityId !== 'all' ? (int) $request->facilityId : null;
        if ($facilityId && $visible !== null && !in_array($facilityId, array_map('intval', $visible), true)) {
            abort(403, 'You cannot see clients of this facility.');
        }

        $q = Client::query()->with(['facility'])
            ->when($visible !== null, fn ($x) => $x->whereIn('facilityId', $visible))
            ->when($facilityId, fn ($x) => $x->where('facilityId', $facilityId));

        if ($request->query('scope') === 'mine') {
            if (!Schema::hasColumn('clients', 'registeredById')) {
                return response()->json(['data' => [], 'current_page' => 1, 'last_page' => 1, 'total' => 0, 'message' => 'Run php artisan migrate to enable My clients.']);
            }
            $q->where('registeredById', $user->id);
        }

        if ($search !== '') {
            $q->where(function ($s) use ($search) {
                $s->where('fullName', 'like', "%{$search}%")
                  ->orWhere('phoneNumber', 'like', "%{$search}%")
                  ->orWhere('clientId', 'like', "%{$search}%")
                  ->orWhere('hospitalNumber', 'like', "%{$search}%");
            });
        }

        if (in_array($request->query('gender'), ['female', 'male'], true)) {
            $q->where('gender', $request->query('gender'));
        }

        $group = self::AGE_GROUPS[(string) $request->query('ageGroup')] ?? null;
        if ($group) {
            [$min, $max] = $group;
            $today = Carbon::today();
            $q->whereNotNull('dateOfBirth')
              ->whereDate('dateOfBirth', '<=', $today->copy()->subYears($min))
              ->whereDate('dateOfBirth', '>', $today->copy()->subYears($max + 1));
        }

        if ($request->query('screened') === 'yes') $q->whereHas('visits');
        if ($request->query('screened') === 'no') $q->whereDoesntHave('visits');

        if ($request->filled('dateFrom')) $q->whereDate('created_at', '>=', $request->query('dateFrom'));
        if ($request->filled('dateTo')) $q->whereDate('created_at', '<=', $request->query('dateTo'));

        $perPage = max(5, min((int) $request->query('perPage', 20), 50));

        return response()->json($q->latest()->paginate($perPage));
    }
}
