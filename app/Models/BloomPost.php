<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** An article or announcement written on the admin dashboard and shown in the Bloom app. */
class BloomPost extends Model
{
    protected $table = 'bloom_posts';

    public const KINDS = ['article', 'announcement'];
    public const AUDIENCES = ['all', 'women', 'chw'];

    protected $fillable = [
        'kind', 'title', 'summary', 'body', 'audience', 'state', 'cover_path', 'link_url',
        'status', 'pinned', 'published_at', 'pushed_at', 'push_count', 'created_by', 'updated_by',
    ];

    protected $casts = ['pinned' => 'boolean', 'published_at' => 'datetime', 'pushed_at' => 'datetime'];

    public function coverUrl(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }

    /** Published posts a woman (role "women") or health worker (role "chw", with her state) may see. */
    public function scopeVisibleTo($q, string $role, ?string $state = null)
    {
        $q->where('status', 'published')->where('published_at', '<=', now())
          ->whereIn('audience', ['all', $role]);

        if ($role === 'chw') {
            $q->where(fn ($w) => $w->whereNull('state')->orWhere('state', (string) $state));
        } else {
            $q->whereNull('state'); // state targeting is for health workers only
        }

        return $q;
    }
}
