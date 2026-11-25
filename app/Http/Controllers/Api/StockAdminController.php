<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Facility;
use App\Models\StockItem;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Web "Stock Management" page.
 *  - Stock across facilities (facility × item table), bulk delivery to several facilities, movement history + CSV export,
 *    batches expiring soon — for anyone, limited to the facilities they can see;
 *  - the stock items catalogue (add / edit / deactivate items, which tests use them) — super admins and NICRAT admins.
 * Single-facility actions (receive, count, expired, transfer, alert level) reuse the /stock/* endpoints the app uses.
 */
class StockAdminController extends Controller
{
    /** Tests a stock item can be linked to (deducted automatically when that test's sample is taken). */
    private const TESTS = [
        'hpv_dna' => 'HPV DNA test', 'cervical_cytology' => 'Pap smear / cytology', 'via' => 'VIA / VILI', 'cervical_biopsy' => 'Cervical biopsy',
        'cbe' => 'Clinical breast exam', 'mammography' => 'Mammography', 'breast_ultrasound' => 'Breast ultrasound', 'breast_biopsy' => 'Breast biopsy / FNA',
        'psa' => 'PSA blood test', 'dre' => 'Digital rectal exam', 'prostate_biopsy' => 'Prostate biopsy',
        'afp' => 'AFP blood test', 'liver_ultrasound' => 'Liver ultrasound', 'hbsag' => 'Hepatitis B (HBsAg)', 'anti_hcv' => 'Hepatitis C (anti-HCV)',
        'fit' => 'FIT / FOBT', 'colonoscopy' => 'Colonoscopy', 'colorectal_biopsy' => 'Colorectal biopsy',
    ];

    private const TYPE_LABEL = [
        'receive' => 'Received', 'use' => 'Used (sample)', 'adjust' => 'Count / adjustment', 'expired' => 'Expired / damaged',
        'transfer_out' => 'Sent to facility', 'transfer_in' => 'Received from facility',
    ];

    private function me(): User
    {
        return auth('api')->user();
    }

    private function isAdmin(User $u): bool
    {
        return $u->isSuperAdmin() || $u->isNicratStaff();
    }

    private function admin(): User
    {
        $u = $this->me();
        abort_if(!$this->isAdmin($u), 403, 'Only a super admin or NICRAT admin can change the stock items list.');
        return $u;
    }

    /** Facilities this user may see, optionally narrowed by state / search. */
    private function facilityQuery(Request $request)
    {
        $visible = $this->me()->visibleFacilityIds();
        $state = trim((string) $request->query('state', ''));
        $search = trim((string) $request->query('search', ''));
        return Facility::query()
            ->when($visible !== null, fn ($q) => $q->whereIn('facilityId', $visible))
            ->when($state !== '', fn ($q) => $q->where('facilityState', $state))
            ->when($search !== '', fn ($q) => $q->where('facilityName', 'like', "%{$search}%"));
    }

    private function log(string $action, string $text): void
    {
        if (class_exists(ActivityLog::class)) {
            try {
                ActivityLog::record($action, $text, ['subjectType' => 'stock']);
            } catch (\Throwable $e) {
            }
        }
    }

    // ── Who am I / pickers ───────────────────────────────────────────────

    /** GET /stock-admin/meta — facilities and states for pickers, item list, whether the user can edit the catalogue. */
    public function meta(Request $request): JsonResponse
    {
        $u = $this->me();
        $facilities = $this->facilityQuery(new Request())->orderBy('facilityName')->get(['facilityId', 'facilityName', 'facilityState']);
        return response()->json([
            'canManageItems' => $this->isAdmin($u),
            'national' => $u->visibleFacilityIds() === null,
            'myFacilityId' => $u->facilityId,
            'facilities' => $facilities,
            'states' => $facilities->pluck('facilityState')->filter()->unique()->sort()->values(),
            'items' => StockItem::orderBy('name')->get(['id', 'code', 'name', 'unit', 'active']),
            'tests' => collect(self::TESTS)->map(fn ($l, $k) => ['value' => $k, 'label' => $l])->values(),
            'movementTypes' => collect(self::TYPE_LABEL)->map(fn ($l, $k) => ['value' => $k, 'label' => $l])->values(),
        ]);
    }

    // ── Stock across facilities ──────────────────────────────────────────

    /**
     * GET /stock-admin/matrix?state=&search=&show=all|tracked|problems&page=
     * One row per facility, one cell per active item: quantity, alert level, status (ok | low | out | none = never stocked).
     */
    public function matrix(Request $request): JsonResponse
    {
        $show = (string) $request->query('show', 'tracked');
        $items = StockItem::where('active', true)->orderBy('name')->get();
        $q = $this->facilityQuery($request);

        $levelsQ = StockLevel::whereIn('itemId', $items->pluck('id'));
        if ($show === 'tracked' || $show === 'problems') {
            $tracked = (clone $levelsQ)->distinct()->pluck('facilityId');
            $q->whereIn('facilityId', $tracked);
        }
        if ($show === 'problems') {
            $problem = StockLevel::query()->join('stock_items', 'stock_items.id', '=', 'stock_levels.itemId')
                ->where('stock_items.active', true)
                ->whereRaw('stock_levels.quantity <= COALESCE(stock_levels.lowLevel, stock_items.defaultLowLevel)')
                ->distinct()->pluck('stock_levels.facilityId');
            $q->whereIn('facilityId', $problem);
        }

        $page = $q->orderBy('facilityState')->orderBy('facilityName')->paginate(min(100, max(10, (int) $request->query('perPage', 30))), ['facilityId', 'facilityName', 'facilityState']);
        $fids = collect($page->items())->pluck('facilityId');
        $levels = StockLevel::whereIn('facilityId', $fids)->get()->groupBy('facilityId');

        $rows = collect($page->items())->map(function ($f) use ($items, $levels) {
            $mine = ($levels[$f->facilityId] ?? collect())->keyBy('itemId');
            $cells = [];
            $out = $low = 0;
            foreach ($items as $i) {
                $l = $mine->get($i->id);
                $low_ = $l ? StockService::lowLevel($l, $i) : (int) $i->defaultLowLevel;
                $qty = $l ? (float) $l->quantity : null;
                $status = $l === null ? 'none' : ($qty <= 0 ? 'out' : ($qty <= $low_ ? 'low' : 'ok'));
                if ($status === 'out') $out++;
                if ($status === 'low') $low++;
                $cells[$i->id] = ['quantity' => $qty, 'lowLevel' => $low_, 'status' => $status];
            }
            return ['facilityId' => $f->facilityId, 'facilityName' => $f->facilityName, 'facilityState' => $f->facilityState, 'out' => $out, 'low' => $low, 'cells' => $cells];
        })->values();

        // Totals for the summary cards (whole filtered set, not just this page).
        $allF = $this->facilityQuery($request)->pluck('facilityId');
        $all = StockLevel::whereIn('facilityId', $allF)->whereIn('itemId', $items->pluck('id'))->get();
        $byItem = $items->keyBy('id');
        $outFac = $all->filter(fn ($l) => $l->quantity <= 0)->pluck('facilityId')->unique()->count();
        $lowFac = $all->filter(fn ($l) => $l->quantity > 0 && $l->quantity <= StockService::lowLevel($l, $byItem[$l->itemId]))->pluck('facilityId')->unique()->count();

        return response()->json([
            'items' => $items->map(fn ($i) => ['id' => $i->id, 'code' => $i->code, 'name' => $i->name, 'unit' => $i->unit,
                'total' => (float) $all->where('itemId', $i->id)->sum('quantity')])->values(),
            'facilities' => $rows,
            'summary' => ['tracking' => $all->pluck('facilityId')->unique()->count(), 'withOut' => $outFac, 'withLow' => $lowFac],
            'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(),
        ]);
    }

    /**
     * POST /stock-admin/receive-bulk { itemId, batchNo?, expiryDate?, note?, lines: [{ facilityId, quantity }] }
     * One delivery shared out to several facilities.
     */
    public function receiveBulk(Request $request): JsonResponse
    {
        $u = $this->me();
        $d = $request->validate([
            'itemId' => ['required', 'integer', 'exists:stock_items,id'],
            'batchNo' => ['nullable', 'string', 'max:60'],
            'expiryDate' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1', 'max:500'],
            'lines.*.facilityId' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
        ]);
        $item = StockItem::findOrFail($d['itemId']);
        foreach ($d['lines'] as $l) {
            abort_if(!$u->canAccessFacility((int) $l['facilityId']), 403, 'You cannot record stock for one of the chosen facilities.');
        }
        $total = 0;
        DB::transaction(function () use ($d, $item, &$total) {
            foreach ($d['lines'] as $l) {
                StockService::move((int) $l['facilityId'], $item, 'receive', (float) $l['quantity'], [
                    'batchNo' => $d['batchNo'] ?? null, 'expiryDate' => $d['expiryDate'] ?? null, 'note' => $d['note'] ?? 'Delivery',
                ]);
                $total += (float) $l['quantity'];
            }
        });
        $n = count($d['lines']);
        $qty = rtrim(rtrim(number_format($total, 2), '0'), '.');
        $this->log('stock_bulk_received', "Recorded a delivery of {$qty} {$item->unit} of {$item->name} to {$n} facilities.");
        return response()->json(['message' => "Delivery recorded: {$qty} {$item->unit} of {$item->name} to {$n} " . ($n === 1 ? 'facility' : 'facilities') . '.']);
    }

    // ── History ──────────────────────────────────────────────────────────

    private function movementQuery(Request $request)
    {
        $visible = $this->me()->visibleFacilityIds();
        $fid = (int) $request->query('facilityId', 0);
        if ($fid) abort_if(!$this->me()->canAccessFacility($fid), 403, 'You cannot see this facility\'s stock.');
        $state = trim((string) $request->query('state', ''));
        return StockMovement::query()
            ->when($visible !== null, fn ($q) => $q->whereIn('facilityId', $visible))
            ->when($fid, fn ($q) => $q->where('facilityId', $fid))
            ->when($state !== '', fn ($q) => $q->whereIn('facilityId', Facility::where('facilityState', $state)->pluck('facilityId')))
            ->when($request->query('itemId'), fn ($q, $v) => $q->where('itemId', (int) $v))
            ->when($request->query('type'), fn ($q, $v) => $q->where('type', $v))
            ->when($request->query('from'), fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->query('to'), fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->orderByDesc('id');
    }

    private function decorate($rows): array
    {
        $users = User::whereIn('id', $rows->pluck('userId')->filter()->unique())->get()->mapWithKeys(fn ($u) => [$u->id => trim("{$u->firstName} {$u->lastName}")]);
        $fac = Facility::whereIn('facilityId', $rows->pluck('facilityId')->merge($rows->pluck('otherFacilityId'))->filter()->unique())->get(['facilityId', 'facilityName', 'facilityState'])->keyBy('facilityId');
        $items = StockItem::get(['id', 'name', 'unit'])->keyBy('id');
        return $rows->map(fn ($m) => [
            'id' => $m->id, 'at' => $m->created_at?->toIso8601String(), 'type' => $m->type, 'typeLabel' => self::TYPE_LABEL[$m->type] ?? $m->type,
            'facilityId' => $m->facilityId, 'facility' => $fac[$m->facilityId]->facilityName ?? null, 'state' => $fac[$m->facilityId]->facilityState ?? null,
            'itemId' => $m->itemId, 'item' => $items[$m->itemId]->name ?? null, 'unit' => $items[$m->itemId]->unit ?? null,
            'quantity' => (float) $m->quantity, 'balanceAfter' => (float) $m->balanceAfter,
            'otherFacility' => $m->otherFacilityId ? ($fac[$m->otherFacilityId]->facilityName ?? null) : null,
            'batchNo' => $m->batchNo, 'expiryDate' => $m->expiryDate?->toDateString(), 'sampleId' => $m->sampleId,
            'note' => $m->note, 'by' => $users[$m->userId] ?? null,
        ])->values()->all();
    }

    /** GET /stock-admin/movements?facilityId=&state=&itemId=&type=&from=&to=&page= */
    public function movements(Request $request): JsonResponse
    {
        $page = $this->movementQuery($request)->paginate(min(100, max(10, (int) $request->query('perPage', 30))));
        return response()->json(['movements' => $this->decorate(collect($page->items())), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]);
    }

    /** GET /stock-admin/movements/export (same filters) — CSV for audit. */
    public function export(Request $request): StreamedResponse
    {
        $q = $this->movementQuery($request);
        $name = 'ncsr-stock-movements-' . now()->format('Y-m-d') . '.csv';
        return response()->streamDownload(function () use ($q) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Facility', 'State', 'Item', 'Movement', 'Quantity', 'Unit', 'Balance after', 'Other facility', 'Batch', 'Expiry', 'Sample ID', 'Note', 'By']);
            $q->chunk(1000, function ($chunk) use ($out) {
                foreach ($this->decorate($chunk) as $m) {
                    fputcsv($out, [
                        $m['at'] ? date('Y-m-d H:i', strtotime($m['at'])) : '', $m['facility'], $m['state'], $m['item'], $m['typeLabel'], $m['quantity'], $m['unit'],
                        $m['balanceAfter'], $m['otherFacility'], $m['batchNo'], $m['expiryDate'], $m['sampleId'], $m['note'], $m['by'],
                    ]);
                }
            });
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * GET /stock-admin/expiring?days=60&state=
     * Batches received with an expiry date in the next N days (or already expired), at facilities that still hold that item.
     */
    public function expiring(Request $request): JsonResponse
    {
        $days = max(1, min((int) $request->query('days', 60), 365));
        $fids = $this->facilityQuery($request)->pluck('facilityId');
        $rows = StockMovement::whereIn('facilityId', $fids)->whereIn('type', ['receive', 'transfer_in'])
            ->whereNotNull('expiryDate')->whereDate('expiryDate', '<=', now()->addDays($days))
            ->orderBy('expiryDate')->limit(500)->get();
        $levels = StockLevel::whereIn('facilityId', $rows->pluck('facilityId')->unique())->get()->keyBy(fn ($l) => "{$l->facilityId}-{$l->itemId}");
        $rows = $rows->filter(fn ($m) => (float) ($levels["{$m->facilityId}-{$m->itemId}"]->quantity ?? 0) > 0)->values();
        $list = collect($this->decorate($rows))->map(fn ($m) => $m + [
            'inStock' => (float) ($levels["{$m['facilityId']}-{$m['itemId']}"]->quantity ?? 0),
            'daysLeft' => $m['expiryDate'] ? (int) now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($m['expiryDate']), false) : null,
        ]);
        return response()->json(['batches' => $list->values(), 'days' => $days]);
    }

    // ── Items catalogue ──────────────────────────────────────────────────

    /** GET /stock-admin/items — every item (active and not), with how many facilities stock it and the total held. */
    public function items(): JsonResponse
    {
        $visible = $this->me()->visibleFacilityIds();
        $agg = StockLevel::when($visible !== null, fn ($q) => $q->whereIn('facilityId', $visible))
            ->selectRaw('itemId, COUNT(*) facilities, SUM(quantity) total')->groupBy('itemId')->get()->keyBy('itemId');
        return response()->json(['items' => StockItem::orderByDesc('active')->orderBy('name')->get()->map(fn ($i) => [
            'id' => $i->id, 'code' => $i->code, 'name' => $i->name, 'unit' => $i->unit, 'testTypes' => $i->testTypes ?? [],
            'perTest' => (float) $i->perTest, 'defaultLowLevel' => (int) $i->defaultLowLevel, 'active' => (bool) $i->active,
            'facilities' => (int) ($agg[$i->id]->facilities ?? 0), 'total' => (float) ($agg[$i->id]->total ?? 0),
        ])->values()]);
    }

    private function itemData(Request $request, ?StockItem $item = null): array
    {
        $d = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'unit' => ['required', 'string', 'max:30'],
            'testTypes' => ['nullable', 'array'],
            'testTypes.*' => ['string', 'in:' . implode(',', array_keys(self::TESTS))],
            'perTest' => ['required', 'numeric', 'min:0', 'max:100'],
            'defaultLowLevel' => ['required', 'integer', 'min:0', 'max:100000'],
            'active' => ['nullable', 'boolean'],
        ]);
        $code = strtoupper($d['code'] ?? '') ?: strtoupper(Str::slug($d['name'], '_'));
        $code = mb_substr($code, 0, 40);
        abort_if(StockItem::where('code', $code)->when($item, fn ($q) => $q->where('id', '!=', $item->id))->exists(), 422, "Another item already uses the code {$code}.");
        return [
            'name' => $d['name'], 'code' => $code, 'unit' => $d['unit'], 'testTypes' => array_values(array_unique($d['testTypes'] ?? [])),
            'perTest' => $d['perTest'], 'defaultLowLevel' => $d['defaultLowLevel'], 'active' => $d['active'] ?? ($item->active ?? true),
        ];
    }

    /** POST /stock-admin/items */
    public function storeItem(Request $request): JsonResponse
    {
        $this->admin();
        $item = StockItem::create($this->itemData($request));
        $this->log('stock_item_created', "Added stock item {$item->name}.");
        return response()->json(['message' => "{$item->name} added.", 'item' => $item], 201);
    }

    /** PUT /stock-admin/items/{id} — also used to deactivate / reactivate ({ ..., active: false }). */
    public function updateItem(Request $request, int $id): JsonResponse
    {
        $this->admin();
        $item = StockItem::findOrFail($id);
        $was = (bool) $item->active;
        $item->update($this->itemData($request, $item));
        $this->log('stock_item_updated', "Updated stock item {$item->name}" . ($was !== (bool) $item->active ? ($item->active ? ' (reactivated)' : ' (deactivated)') : '') . '.');
        return response()->json(['message' => $was && !$item->active ? "{$item->name} deactivated — it is no longer tracked or deducted." : "{$item->name} saved."]);
    }
}
