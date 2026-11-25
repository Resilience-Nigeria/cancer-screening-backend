<?php

namespace App\Services;

use App\Models\Facility;
use App\Models\StockItem;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Support\StaffAudience;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Moves stock and raises low-stock alerts (bell + phone) to admins and the facility's staff. */
class StockService
{
    public static function move(int $facilityId, StockItem $item, string $type, float $qty, array $extra = []): StockLevel
    {
        return DB::transaction(function () use ($facilityId, $item, $type, $qty, $extra) {
            $level = StockLevel::lockForUpdate()->firstOrCreate(['facilityId' => $facilityId, 'itemId' => $item->id], ['quantity' => 0]);
            $before = (float) $level->quantity;
            $after = $type === 'adjust' && array_key_exists('setTo', $extra) ? (float) $extra['setTo'] : $before + $qty;
            $level->quantity = $after;
            $level->save();
            StockMovement::create([
                'facilityId' => $facilityId, 'itemId' => $item->id, 'type' => $type, 'quantity' => $after - $before, 'balanceAfter' => $after,
                'sampleId' => $extra['sampleId'] ?? null, 'otherFacilityId' => $extra['otherFacilityId'] ?? null,
                'batchNo' => $extra['batchNo'] ?? null, 'expiryDate' => $extra['expiryDate'] ?? null,
                'note' => $extra['note'] ?? null, 'userId' => auth('api')->id(),
            ]);
            self::maybeAlert($level, $item, $before);
            return $level;
        });
    }

    /** A sample / test was taken: deduct what it uses at that facility. */
    public static function deductForSample($sample): void
    {
        try {
            if (!$sample->facilityId || !$sample->testType) return;
            $items = StockItem::where('active', true)->where('perTest', '>', 0)->get()
                ->filter(fn ($i) => in_array($sample->testType, $i->testTypes ?? [], true));
            foreach ($items as $item) {
                if (StockMovement::where('sampleId', $sample->sampleId)->where('itemId', $item->id)->where('type', 'use')->exists()) continue;
                self::move((int) $sample->facilityId, $item, 'use', -1 * (float) $item->perTest, ['sampleId' => $sample->sampleId, 'note' => 'Used for ' . $sample->testType]);
            }
        } catch (\Throwable $e) {
            Log::warning('Stock deduction skipped: ' . $e->getMessage());
        }
    }

    /** A sample was deleted: give its stock back. */
    public static function returnForSample($sample): void
    {
        try {
            foreach (StockMovement::where('sampleId', $sample->sampleId)->where('type', 'use')->get() as $m) {
                $item = StockItem::find($m->itemId);
                if ($item) self::move((int) $m->facilityId, $item, 'adjust', abs($m->quantity), ['sampleId' => null, 'note' => "Returned: sample {$sample->sampleId} deleted"]);
            }
        } catch (\Throwable $e) {
            Log::warning('Stock return skipped: ' . $e->getMessage());
        }
    }

    public static function lowLevel(StockLevel $l, StockItem $i): int
    {
        return (int) ($l->lowLevel ?? $i->defaultLowLevel);
    }

    private static function maybeAlert(StockLevel $level, StockItem $item, float $before): void
    {
        $low = self::lowLevel($level, $item);
        $now = (float) $level->quantity;
        // Only when it crosses the line (or hits zero), at most once a day per item & facility.
        $crossed = ($before > $low && $now <= $low) || ($before > 0 && $now <= 0);
        if (!$crossed || ($level->lastAlertAt && $level->lastAlertAt->gt(now()->subDay()))) return;
        $level->lastAlertAt = now();
        $level->save();
        $fac = Facility::find($level->facilityId)->facilityName ?? 'a facility';
        $out = $now <= 0;
        $users = StaffAudience::admins()->pluck('id')->merge(StaffAudience::atFacility($level->facilityId)->pluck('id'))->unique()->all();
        StaffPushService::notify($users, $out ? "Out of stock: {$item->name}" : "Low stock: {$item->name}",
            $out ? "{$fac} has no {$item->name} left. Screening that needs it will stop until it is restocked."
                 : "{$fac} has only " . rtrim(rtrim(number_format($now, 2), '0'), '.') . " {$item->unit} of {$item->name} left (alert level {$low}).",
            ['type' => 'stock', 'route' => '/stock', 'data' => ['facilityId' => $level->facilityId, 'itemId' => $item->id]]);
    }
}
