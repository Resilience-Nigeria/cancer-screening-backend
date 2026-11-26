<?php

namespace App\Support;

use App\Models\User;

/** Where staff profile pictures live, and their public link. */
class StaffPhoto
{
    public const DISK = 'local';
    public const DIR = 'staff-photos';

    /** Public link (the file name is random, so it can't be guessed). Null when there's no photo. */
    public static function url(?User $u): ?string
    {
        $p = $u?->photoPath ?? null;
        return $p ? url('/api/staff-photos/' . basename($p)) : null;
    }
}
