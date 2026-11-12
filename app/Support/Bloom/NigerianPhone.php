<?php

namespace App\Support\Bloom;

/**
 * Nigerian mobile numbers. Everything is stored and compared as
 * 234XXXXXXXXXX (13 digits, no "+"), whichever way the person typed it.
 */
final class NigerianPhone
{
    /** Returns 234XXXXXXXXXX, or null when the input is not a Nigerian mobile number. */
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === null || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {          // 00234...
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '234')) {
            $rest = substr($digits, 3);
            if (str_starts_with($rest, '0')) {         // +234 (0) 803...
                $rest = substr($rest, 1);
            }
        } elseif (str_starts_with($digits, '0')) {     // 0803...
            $rest = substr($digits, 1);
        } else {                                       // 803...
            $rest = $digits;
        }

        // Mobile prefixes are 70x, 80x, 81x, 90x, 91x: the first digit is 7, 8 or 9.
        if (!preg_match('/^[789]\d{9}$/', $rest)) {
            return null;
        }

        return '234' . $rest;
    }

    /** 2348031234567 => 08031234567 (how people write it). */
    public static function local(string $normalized): string
    {
        return '0' . substr($normalized, 3);
    }
}
