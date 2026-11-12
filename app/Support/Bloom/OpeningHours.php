<?php

namespace App\Support\Bloom;

use DateTimeInterface;

/**
 * Works out "open now" from the facility's clinicDays / clinicOpenTime /
 * clinicCloseTime. Those columns are free text, so this is forgiving:
 * days may be "Mon" or "Monday"; times may be "09:00", "9:00 AM" or "09:00:00".
 */
final class OpeningHours
{
    /**
     * @param  array<int, string>|null $days  e.g. ["Mon","Wed"]; empty/null = days not recorded
     * @return bool|null true/false, or null when the opening times are not recorded
     */
    public static function isOpen(?array $days, ?string $open, ?string $close, DateTimeInterface $now): ?bool
    {
        $openMin  = self::minutes($open);
        $closeMin = self::minutes($close);
        if ($openMin === null || $closeMin === null) {
            return null;
        }

        $nowMin = ((int) $now->format('G')) * 60 + (int) $now->format('i');

        if (!empty($days)) {
            $today = strtolower($now->format('D'));                   // mon, tue...
            $listed = array_map(
                static fn ($d) => strtolower(substr(trim((string) $d), 0, 3)),
                $days
            );
            if (!in_array($today, $listed, true)) {
                return false;
            }
        }

        if ($openMin === $closeMin) {
            return true;                                              // treat as all day
        }
        if ($openMin < $closeMin) {
            return $nowMin >= $openMin && $nowMin < $closeMin;
        }

        return $nowMin >= $openMin || $nowMin < $closeMin;            // overnight, e.g. 20:00-06:00
    }

    /** "9:30 PM" / "21:30" / "21:30:00" => minutes after midnight, or null. */
    public static function minutes(?string $time): ?int
    {
        if ($time === null) {
            return null;
        }
        if (!preg_match('/^\s*(\d{1,2})(?::(\d{2}))?(?::\d{2})?\s*([AaPp][Mm])?\s*$/', $time, $m)) {
            return null;
        }

        $h   = (int) $m[1];
        $min = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 0;
        $ap  = isset($m[3]) ? strtolower($m[3]) : null;

        if ($min > 59) {
            return null;
        }
        if ($ap !== null) {
            if ($h < 1 || $h > 12) {
                return null;
            }
            $h = ($h % 12) + ($ap === 'pm' ? 12 : 0);
        } elseif ($h > 23) {
            return null;
        }

        return $h * 60 + $min;
    }
}
