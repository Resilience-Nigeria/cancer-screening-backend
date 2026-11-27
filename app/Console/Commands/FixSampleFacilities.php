<?php

namespace App\Console\Commands;

use App\Models\ScreeningSample;
use App\Models\User;
use App\Observers\SampleFacilityObserver;
use Illuminate\Console\Command;

/**
 * php artisan ncsr:fix-sample-facilities --dry-run
 * php artisan ncsr:fix-sample-facilities
 *
 * Corrects existing samples that were filed under the facility of whoever entered them instead of where
 * the screening was done (same rules as SampleFacilityObserver). Booked tests are left alone.
 */
class FixSampleFacilities extends Command
{
    protected $signature = 'ncsr:fix-sample-facilities {--dry-run : Only list what would change}';
    protected $description = 'Move samples to the facility where the screening was done';

    public function handle(): int
    {
        $users = [];
        $changed = 0;
        $dry = (bool) $this->option('dry-run');
        ScreeningSample::with('facility:facilityId,facilityName')->where('status', '!=', 'booked')
            ->chunkById(500, function ($chunk) use (&$users, &$changed, $dry) {
                foreach ($chunk as $s) {
                    $uid = $s->collectedById;
                    if ($uid && !array_key_exists($uid, $users)) $users[$uid] = User::find($uid);
                    $target = SampleFacilityObserver::facilityFor($s, $uid ? $users[$uid] : null);
                    if (!$target || (int) $target === (int) $s->facilityId) continue;
                    $changed++;
                    $this->line(sprintf('%s (%s): %s → facility %d', $s->sampleRef, $s->clientId, $s->facility->facilityName ?? $s->facilityId, $target));
                    if (!$dry) ScreeningSample::where('sampleId', $s->sampleId)->update(['facilityId' => $target]);
                }
            }, 'sampleId');
        $this->info(($dry ? 'Would change ' : 'Changed ') . $changed . ' sample(s).');
        return self::SUCCESS;
    }
}
