<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class MyClientsController extends Controller
{
    /**
     * GET /clients/mine?search=&page=
     * Clients registered by the logged-in user (newest first), same shape as GET /clients (paginated).
     * The hospital-wide list stays GET /clients, which already follows the user's facility scope
     * (national admins see every facility).
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $search = trim((string) $request->query('search', ''));

        if (!Schema::hasColumn('clients', 'registeredById')) {
            return response()->json(['data' => [], 'current_page' => 1, 'last_page' => 1, 'total' => 0, 'per_page' => 20,
                'message' => 'Run php artisan migrate to enable My clients.']);
        }

        $query = Client::with(['facility'])
            ->where('registeredById', $user->id)
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('fullName', 'like', "%{$search}%")
                        ->orWhere('phoneNumber', 'like', "%{$search}%")
                        ->orWhere('clientId', 'like', "%{$search}%")
                        ->orWhere('hospitalNumber', 'like', "%{$search}%");
                });
            })
            ->latest();

        $perPage = max(5, min((int) $request->query('perPage', 20), 50));

        return response()->json($query->paginate($perPage));
    }
}
