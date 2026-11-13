<?php

namespace App\Http\Controllers\Api\Bloom;

use App\Http\Controllers\Controller;
use App\Models\BloomPost;
use App\Models\BloomPushToken;
use App\Services\Bloom\BloomPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Admin dashboard: write articles and announcements for the Bloom app (women, health workers or both),
 * publish them, and optionally send them as a phone notification. Super admin and NICRAT admin only.
 */
class AdminPostController extends Controller
{
    use CaseConcerns;

    private function canManage(): bool
    {
        $u = $this->staff();

        return $u && ($u->isSuperAdmin() || $u->isNicratAdmin());
    }

    /** GET /bloom-admin/posts?status=&audience=&search= */
    public function index(Request $request): JsonResponse
    {
        if (!$this->canManage()) {
            return $this->deny('Only NICRAT admins can manage Bloom articles.');
        }
        $v = $request->validate([
            'status'   => ['nullable', 'in:draft,published,all'],
            'audience' => ['nullable', 'in:all,women,chw'],
            'search'   => ['nullable', 'string', 'max:80'],
        ]);

        $base = BloomPost::query();
        $summary = [
            'published' => (clone $base)->where('status', 'published')->count(),
            'draft'     => (clone $base)->where('status', 'draft')->count(),
            'phones'    => BloomPushToken::active()->count(),
            'phonesWomen' => BloomPushToken::active()->where('app_role', 'woman')->count(),
            'phonesChw' => BloomPushToken::active()->where('app_role', 'chw')->count(),
        ];

        $rows = $base
            ->when(($v['status'] ?? 'all') !== 'all', fn ($q) => $q->where('status', $v['status']))
            ->when(!empty($v['audience']), fn ($q) => $q->where('audience', $v['audience']))
            ->when(!empty($v['search']), fn ($q) => $q->where(fn ($w) => $w->where('title', 'like', "%{$v['search']}%")->orWhere('summary', 'like', "%{$v['search']}%")))
            ->orderByRaw("status = 'draft' DESC")->orderByDesc('pinned')->orderByDesc('published_at')->orderByDesc('id')
            ->limit(200)->get();

        return response()->json(['status' => true, 'summary' => $summary, 'data' => $rows->map(fn ($p) => $this->present($p))->values()]);
    }

    /** POST /bloom-admin/posts (multipart; `cover` image optional) */
    public function store(Request $request): JsonResponse
    {
        if (!$this->canManage()) {
            return $this->deny('Only NICRAT admins can manage Bloom articles.');
        }
        $data = $this->validated($request, true);
        $post = new BloomPost();
        $this->fill($post, $data, $request);
        $post->status = 'draft';
        $post->created_by = $this->staff()->id;
        $post->save();

        return response()->json(['status' => true, 'message' => 'Saved as a draft.', 'data' => $this->present($post)], 201);
    }

    /** POST /bloom-admin/posts/{id} (multipart; POST so files upload everywhere). removeCover=1 deletes the picture. */
    public function update(Request $request, int $id): JsonResponse
    {
        if (!$this->canManage()) {
            return $this->deny('Only NICRAT admins can manage Bloom articles.');
        }
        $post = BloomPost::find($id);
        if (!$post) {
            return $this->missing();
        }
        $data = $this->validated($request, false);
        $this->fill($post, $data, $request);
        $post->updated_by = $this->staff()->id;
        $post->save();

        return response()->json(['status' => true, 'message' => 'Saved.', 'data' => $this->present($post)]);
    }

    /** POST /bloom-admin/posts/{id}/publish  { notify: bool } */
    public function publish(Request $request, int $id): JsonResponse
    {
        if (!$this->canManage()) {
            return $this->deny();
        }
        $post = BloomPost::find($id);
        if (!$post) {
            return $this->missing();
        }
        $notify = $request->boolean('notify');

        $post->status = 'published';
        $post->published_at = $post->published_at ?? now();
        $post->updated_by = $this->staff()->id;
        $post->save();

        $phones = $notify ? $this->push($post) : 0;

        return response()->json([
            'status'  => true,
            'message' => $notify ? "Published and sent to {$phones} " . ($phones === 1 ? 'phone' : 'phones') . '.' : 'Published. It now shows in the app.',
            'data'    => $this->present($post->fresh()),
        ]);
    }

    /** POST /bloom-admin/posts/{id}/notify — send (again) as a phone notification. */
    public function notify(int $id): JsonResponse
    {
        if (!$this->canManage()) {
            return $this->deny();
        }
        $post = BloomPost::find($id);
        if (!$post) {
            return $this->missing();
        }
        if ($post->status !== 'published') {
            return response()->json(['status' => false, 'code' => 'NOT_PUBLISHED', 'message' => 'Publish it first.'], 422);
        }
        $phones = $this->push($post);

        return response()->json(['status' => true, 'message' => "Sent to {$phones} " . ($phones === 1 ? 'phone' : 'phones') . '.', 'data' => $this->present($post->fresh())]);
    }

    /** POST /bloom-admin/posts/{id}/unpublish — hides it in the app (back to draft). */
    public function unpublish(int $id): JsonResponse
    {
        if (!$this->canManage()) {
            return $this->deny();
        }
        $post = BloomPost::find($id);
        if (!$post) {
            return $this->missing();
        }
        $post->update(['status' => 'draft', 'updated_by' => $this->staff()->id]);

        return response()->json(['status' => true, 'message' => 'Unpublished. It no longer shows in the app.', 'data' => $this->present($post)]);
    }

    /** DELETE /bloom-admin/posts/{id} */
    public function destroy(int $id): JsonResponse
    {
        if (!$this->canManage()) {
            return $this->deny();
        }
        $post = BloomPost::find($id);
        if (!$post) {
            return $this->missing();
        }
        if ($post->cover_path) {
            Storage::disk('public')->delete($post->cover_path);
        }
        $post->delete();

        return response()->json(['status' => true, 'message' => 'Deleted.']);
    }

    /** Sends the post to the phones in its audience; the tap opens it in the app. */
    private function push(BloomPost $post): int
    {
        $title = ($post->kind === 'announcement' ? '' : 'New article: ') . $post->title;
        $body  = $post->summary ?: mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags((string) $post->body))), 0, 140);

        $count = 0;
        if (in_array($post->audience, ['all', 'women'], true) && !$post->state) {
            $count += BloomPushService::broadcast('women', null, $title, $body, ['href' => "/posts/{$post->id}", 'postId' => $post->id, 'title' => $post->title]);
        }
        if (in_array($post->audience, ['all', 'chw'], true)) {
            $count += BloomPushService::broadcast('chw', $post->state, $title, $body, ['href' => "/chw/post/{$post->id}", 'postId' => $post->id, 'title' => $post->title]);
        }

        $post->update(['pushed_at' => now(), 'push_count' => (int) $post->push_count + 1]);

        return $count;
    }

    private function validated(Request $request, bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'kind'        => [$req, 'in:article,announcement'],
            'title'       => [$req, 'string', 'min:3', 'max:150'],
            'summary'     => ['nullable', 'string', 'max:300'],
            'body'        => ['nullable', 'string', 'max:60000'],
            'audience'    => [$req, 'in:all,women,chw'],
            'state'       => ['nullable', 'string', 'max:60'],
            'linkUrl'     => ['nullable', 'url', 'max:500'],
            'pinned'      => ['nullable', 'boolean'],
            'cover'       => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'removeCover' => ['nullable', 'boolean'],
        ], [
            'cover.max' => 'The picture must be 3 MB or smaller.',
        ]);
    }

    private function fill(BloomPost $post, array $data, Request $request): void
    {
        foreach (['kind' => 'kind', 'title' => 'title', 'summary' => 'summary', 'body' => 'body', 'audience' => 'audience', 'linkUrl' => 'link_url'] as $in => $col) {
            if (array_key_exists($in, $data)) {
                $post->{$col} = is_string($data[$in]) ? (trim($data[$in]) ?: null) : $data[$in];
            }
        }
        if (array_key_exists('state', $data)) {
            $post->state = trim((string) $data['state']) ?: null;
        }
        if ($post->audience === 'women') {
            $post->state = null; // state targeting is for health workers only
        }
        if (array_key_exists('pinned', $data)) {
            $post->pinned = (bool) $data['pinned'];
        }
        if ($request->boolean('removeCover') && $post->cover_path) {
            Storage::disk('public')->delete($post->cover_path);
            $post->cover_path = null;
        }
        if ($request->hasFile('cover')) {
            if ($post->cover_path) {
                Storage::disk('public')->delete($post->cover_path);
            }
            $post->cover_path = $request->file('cover')->store('bloom-posts', 'public');
        }
    }

    private function present(BloomPost $p): array
    {
        return PostController::present($p, true) + [
            'status'   => $p->status,
            'audience' => $p->audience,
            'state'    => $p->state,
            'pushedAt' => optional($p->pushed_at)->toIso8601String(),
            'pushCount' => (int) $p->push_count,
            'updatedAt' => optional($p->updated_at)->toIso8601String(),
        ];
    }

    private function missing(): JsonResponse
    {
        return response()->json(['status' => false, 'code' => 'NOT_FOUND', 'message' => 'Article not found.'], 404);
    }
}
