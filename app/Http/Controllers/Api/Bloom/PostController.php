<?php

namespace App\Http\Controllers\Api\Bloom;

use App\Http\Controllers\Controller;
use App\Models\BloomPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Articles and announcements in the app.
 *  - Women (public, no sign-in needed): GET /bloom/posts, GET /bloom/posts/{id}
 *  - Health workers: GET /bloom-chw/posts, GET /bloom-chw/posts/{id} (also posts targeted at their state)
 */
class PostController extends Controller
{
    use CaseConcerns;

    public function womenIndex(Request $request): JsonResponse
    {
        return $this->list(BloomPost::visibleTo('women'), $request);
    }

    public function womenShow(int $id): JsonResponse
    {
        return $this->one(BloomPost::visibleTo('women'), $id);
    }

    public function chwIndex(Request $request): JsonResponse
    {
        $me = $this->staff();
        if (!$this->isActiveNavigator($me)) {
            return $this->deny();
        }

        return $this->list(BloomPost::visibleTo('chw', $me->facility->facilityState ?? null), $request);
    }

    public function chwShow(int $id): JsonResponse
    {
        $me = $this->staff();
        if (!$this->isActiveNavigator($me)) {
            return $this->deny();
        }

        return $this->one(BloomPost::visibleTo('chw', $me->facility->facilityState ?? null), $id);
    }

    private function list($q, Request $request): JsonResponse
    {
        $v = $request->validate(['kind' => ['nullable', 'in:article,announcement'], 'limit' => ['nullable', 'integer', 'min:1', 'max:50'], 'before' => ['nullable', 'integer']]);
        $rows = $q->when(!empty($v['kind']), fn ($w) => $w->where('kind', $v['kind']))
            ->when(!empty($v['before']), fn ($w) => $w->where('id', '<', (int) $v['before']))
            ->orderByDesc('pinned')->orderByDesc('published_at')->orderByDesc('id')
            ->limit((int) ($v['limit'] ?? 20))->get();

        return response()->json(['status' => true, 'data' => $rows->map(fn ($p) => self::present($p, false))->values()]);
    }

    private function one($q, int $id): JsonResponse
    {
        $p = $q->find($id);
        if (!$p) {
            return response()->json(['status' => false, 'code' => 'NOT_FOUND', 'message' => 'This article is no longer available.'], 404);
        }

        return response()->json(['status' => true, 'data' => self::present($p, true)]);
    }

    public static function present(BloomPost $p, bool $withBody): array
    {
        return [
            'id'          => $p->id,
            'kind'        => $p->kind,
            'title'       => $p->title,
            'summary'     => $p->summary,
            'body'        => $withBody ? $p->body : null,
            'coverUrl'    => $p->coverUrl(),
            'linkUrl'     => $p->link_url,
            'pinned'      => (bool) $p->pinned,
            'publishedAt' => optional($p->published_at)->toIso8601String(),
        ];
    }
}
