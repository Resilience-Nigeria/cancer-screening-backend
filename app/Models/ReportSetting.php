<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportSetting extends Model
{
    protected $table = 'report_settings';
    protected $primaryKey = 'reportKey';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['reportKey', 'enabled', 'frequency', 'dayOfWeek', 'sendTime', 'options', 'lastRunAt', 'updatedBy'];

    protected $casts = [
        'enabled'   => 'boolean',
        'dayOfWeek' => 'integer',
        'options'   => 'array',
        'lastRunAt' => 'datetime',
    ];

    public const TIMEZONE = 'Africa/Lagos';

    public const DEFAULTS = [
        'overdue_results' => ['enabled' => true, 'frequency' => 'daily', 'dayOfWeek' => null, 'sendTime' => '09:30', 'options' => ['days' => 7, 'applyToPopup' => false]],
        'weekly_summary'  => ['enabled' => true, 'frequency' => 'weekly', 'dayOfWeek' => 5, 'sendTime' => '17:00', 'options' => []],
    ];

    public static function for(string $key): self
    {
        return static::find($key) ?? static::create(['reportKey' => $key] + (self::DEFAULTS[$key] ?? ['enabled' => false]));
    }

    public function option(string $name, $default = null)
    {
        return ($this->options ?? [])[$name] ?? $default;
    }

    /** The send time that applies today (Lagos time), or null when it doesn't send today. */
    public function dueAtToday(): ?\Carbon\Carbon
    {
        $now = now(self::TIMEZONE);
        $dow = (int) $now->dayOfWeek; // 0 = Sunday
        if ($this->frequency === 'weekdays' && ($dow === 0 || $dow === 6)) return null;
        if ($this->frequency === 'weekly' && $dow !== (int) ($this->dayOfWeek ?? 5)) return null;
        [$h, $m] = array_map('intval', explode(':', $this->sendTime ?: '09:00') + [0, 0]);
        return $now->copy()->setTime($h, $m);
    }

    /** True when today's send time has passed and it hasn't run since. */
    public function isDue(): bool
    {
        if (!$this->enabled) return false;
        $at = $this->dueAtToday();
        if (!$at || now(self::TIMEZONE)->lt($at)) return false;
        return !$this->lastRunAt || $this->lastRunAt->lt($at);
    }

    /** Next send time (Lagos), for the settings page. */
    public function nextRunAt(): ?\Carbon\Carbon
    {
        if (!$this->enabled) return null;
        [$h, $m] = array_map('intval', explode(':', $this->sendTime ?: '09:00') + [0, 0]);
        $now = now(self::TIMEZONE);
        for ($i = 0; $i < 8; $i++) {
            $d = $now->copy()->addDays($i)->setTime($h, $m);
            $dow = (int) $d->dayOfWeek;
            if ($this->frequency === 'weekdays' && ($dow === 0 || $dow === 6)) continue;
            if ($this->frequency === 'weekly' && $dow !== (int) ($this->dayOfWeek ?? 5)) continue;
            if ($d->gt($now) || ($i === 0 && $this->isDue())) return $d;
        }
        return null;
    }
}
