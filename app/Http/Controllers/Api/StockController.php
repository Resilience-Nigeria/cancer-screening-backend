<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\StockItem;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Stock of screening kits per facility. Staff see and update their own facility; national users any facility.
 * Taking a sample deducts automatically (see StockSampleObserver); low / out-of-stock alerts go to admins and the facility.
 */
class StockController extends Controller
{
    private function facilityFor(Request $request, string $key = 'facilityId'): int
    {
        $user = auth('api')->user();
        $fid = (int) ($request->input($key) ?: $request->query($key) ?: $user->facilityId);
        abort_if(!$fid, 422, 'Choose a facility.');
        abort_if(!$user->canAccessFacility($fid), 403, 'You cannot see this facility\'s stock.');
        return $fid;
    }

    private function row(StockItem $i, ?StockLevel $l, array $used30): array
    {
        $qty = (float) ($l->quantity ?? 0);
        $low = $l ? StockService::lowLevel($l, $i) : (int) $i->defaultLowLevel;
        $perDay = ($used30[$i->id] ?? 0) / 30;
        return [
            'itemId' => $i->id, 'code' => $i->code, 'name' => $i->name, 'unit' => $i->unit,
            'testTypes' => $i->testTypes ?? [], 'perTest' => $i->perTest,
            'quantity' => $qty, 'lowLevel' => $low,
            'status' => $qty <= 0 ? 'out' : ($qty <= $low ? 'low' : 'ok'),
            'usedLast30' => round($used30[$i->id] ?? 0, 2),
            'daysLeft' => $perDay > 0 ? (int) floor($qty / $perDay) : null,
            'updatedAt' => $l?->updated_at?->toIso8601String(),
        ];
    }

    /** GET /stock?facilityId= */
    public function index(Request $request): JsonResponse
    {
        $fid = $this->facilityFor($request);
        $items = StockItem::where('active', true)->orderBy('name')->get();
        $levels = StockLevel::where('facilityId', $fid)->get()->keyBy('itemId');
        $used30 = StockMovement::where('facilityId', $fid)->where('type', 'use')->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('itemId, SUM(-quantity) q')->groupBy('itemId')->pluck('q', 'itemId')->map(fn ($v) => (float) $v)->all();
        $rows = $items->map(fn ($i) => $this->row($i, $levels->get($i->id), $used30))->values();
        return response()->json([
            'facility' => Facility::find($fid, ['facilityId', 'facilityName', 'facilityState']),
            'items' => $rows,
            'summary' => ['out' => $rows->where('status', 'out')->count(), 'low' => $rows->where('status', 'low')->count()],
        ]);
    }

    /** GET /stock/overview — every facility the user can see, with low / out counts (national dashboard). */
    public function overview(): JsonResponse
    {
        $user = auth('api')->user();
        $visible = $user->visibleFacilityIds();
        $items = StockItem::where('active', true)->get()->keyBy('id');
        $levels = StockLevel::when($visible !== null, fn ($q) => $q->whereIn('facilityId', $visible))->get();
        $names = Facility::whereIn('facilityId', $levels->pluck('facilityId')->unique())->get(['facilityId', 'facilityName', 'facilityState'])->keyBy('facilityId');
        $rows = $levels->groupBy('facilityId')->map(function ($ls, $fid) use ($items, $names) {
            $out = $low = [];
            foreach ($ls as $l) {
                $i = $items->get($l->itemId);
                if (!$i) continue;
                if ($l->quantity <= 0) $out[] = $i->name;
                elseif ($l->quantity <= StockService::lowLevel($l, $i)) $low[] = $i->name;
            }
            return ['facilityId' => (int) $fid, 'facilityName' => $names[$fid]->facilityName ?? 'Unknown', 'facilityState' => $names[$fid]->facilityState ?? null, 'out' => $out, 'low' => $low];
        })->sortByDesc(fn ($r) => count($r['out']) * 100 + count($r['low']))->values();
        return response()->json(['facilities' => $rows]);
    }

    /** GET /stock/movements?facilityId=&itemId= */
    public function movements(Request $request): JsonResponse
    {
        $fid = $this->facilityFor($request);
        $rows = StockMovement::where('facilityId', $fid)
            ->when($request->query('itemId'), fn ($q, $v) => $q->where('itemId', $v))
            ->orderByDesc('id')->limit(200)->get();
        $names = User::whereIn('id', $rows->pluck('userId')->filter())->get()->mapWithKeys(fn ($u) => [$u->id => trim("{$u->firstName} {$u->lastName}")]);
        $other = Facility::whereIn('facilityId', $rows->pluck('otherFacilityId')->filter())->pluck('facilityName', 'facilityId');
        $items = StockItem::pluck('name', 'id');
        return response()->json(['movements' => $rows->map(fn ($m) => $m->toArray() + [
            'itemName' => $items[$m->itemId] ?? null, 'by' => $names[$m->userId] ?? null, 'otherFacility' => $other[$m->otherFacilityId] ?? null,
        ])]);
    }

    /** POST /stock/receive { facilityId, itemId, quantity, batchNo?, expiryDate?, note? } */
    public function receive(Request $request): JsonResponse
    {
        $fid = $this->facilityFor($request);
        $d = $request->validate(['itemId' => ['required', 'integer'], 'quantity' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'batchNo' => ['nullable', 'string', 'max:60'], 'expiryDate' => ['nullable', 'date'], 'note' => ['nullable', 'string', 'max:500']]);
        $item = StockItem::findOrFail($d['itemId']);
        $l = StockService::move($fid, $item, 'receive', (float) $d['quantity'], $d);
        return response()->json(['message' => 'Stock received.', 'quantity' => $l->quantity]);
    }

    /** POST /stock/adjust { facilityId, itemId, countedQuantity, note } — after a physical count */
    public function adjust(Request $request): JsonResponse
    {
        $fid = $this->facilityFor($request);
        $d = $request->validate(['itemId' => ['required', 'integer'], 'countedQuantity' => ['required', 'numeric', 'min:0', 'max:1000000'], 'note' => ['required', 'string', 'min:3', 'max:500']]);
        $item = StockItem::findOrFail($d['itemId']);
        $l = StockService::move($fid, $item, 'adjust', 0, ['setTo' => (float) $d['countedQuantity'], 'note' => $d['note']]);
        return response()->json(['message' => 'Stock count saved.', 'quantity' => $l->quantity]);
    }

    /** POST /stock/expired { facilityId, itemId, quantity, note? } */
    public function expired(Request $request): JsonResponse
    {
        $fid = $this->facilityFor($request);
        $d = $request->validate(['itemId' => ['required', 'integer'], 'quantity' => ['required', 'numeric', 'min:0.01'], 'note' => ['nullable', 'string', 'max:500']]);
        $item = StockItem::findOrFail($d['itemId']);
        $l = StockService::move($fid, $item, 'expired', -1 * (float) $d['quantity'], $d);
        return response()->json(['message' => 'Removed as expired / damaged.', 'quantity' => $l->quantity]);
    }

    /** POST /stock/transfer { facilityId (from), toFacilityId, itemId, quantity, note? } */
    public function transfer(Request $request): JsonResponse
    {
        $from = $this->facilityFor($request);
        $d = $request->validate(['toFacilityId' => ['required', 'integer', 'exists:facilities,facilityId'], 'itemId' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'min:0.01'], 'note' => ['nullable', 'string', 'max:500']]);
        abort_if((int) $d['toFacilityId'] === $from, 422, 'Choose another facility.');
        $item = StockItem::findOrFail($d['itemId']);
        StockService::move($from, $item, 'transfer_out', -1 * (float) $d['quantity'], ['otherFacilityId' => $d['toFacilityId'], 'note' => $d['note'] ?? null]);
        StockService::move((int) $d['toFacilityId'], $item, 'transfer_in', (float) $d['quantity'], ['otherFacilityId' => $from, 'note' => $d['note'] ?? null]);
        return response()->json(['message' => 'Stock sent.']);
    }

    /** PUT /stock/level { facilityId, itemId, lowLevel } */
    public function setLowLevel(Request $request): JsonResponse
    {
        $fid = $this->facilityFor($request);
        $d = $request->validate(['itemId' => ['required', 'integer'], 'lowLevel' => ['required', 'integer', 'min:0', 'max:100000']]);
        StockLevel::updateOrCreate(['facilityId' => $fid, 'itemId' => $d['itemId']], ['lowLevel' => $d['lowLevel']]);
        return response()->json(['message' => 'Alert level saved.']);
    }

    /** GET /stock/check?facilityId=&testType= — what a test needs and whether it's in stock (warning before sampling) */
    public function check(Request $request): JsonResponse
    {
        $fid = $this->facilityFor($request);
        $test = (string) $request->query('testType');
        $items = StockItem::where('active', true)->where('perTest', '>', 0)->get()->filter(fn ($i) => in_array($test, $i->testTypes ?? [], true));
        $levels = StockLevel::where('facilityId', $fid)->whereIn('itemId', $items->pluck('id'))->get()->keyBy('itemId');
        $rows = $items->map(fn ($i) => ['name' => $i->name, 'unit' => $i->unit, 'quantity' => (float) ($levels[$i->id]->quantity ?? 0), 'needed' => $i->perTest,
            'tracked' => $levels->has($i->id)])->values();
        // Only warn for items the facility actually tracks (has had stock recorded).
        $missing = $rows->filter(fn ($r) => $r['tracked'] && $r['quantity'] < $r['needed'])->values();
        return response()->json(['items' => $rows, 'missing' => $missing, 'ok' => $missing->isEmpty()]);
    }
}
