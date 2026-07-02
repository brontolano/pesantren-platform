<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\CMS\Models\Article;
use Modules\CMS\Models\Comment;

/**
 * API komentar artikel untuk aplikasi NATIVE (1-aplikasi). Sejajar dgn web
 * CommentController (Modules/CMS) — sumber kebenaran logika pra/pasca moderasi.
 *
 * - index : publik, hanya komentar `approved` (tanpa auth).
 * - store : butuh token (auth:sanctum) + izin `komentar` + throttle:komentar
 *           (digerbang di route). Status awal ikut setting `komentar.moderasi_dulu`.
 *
 * Per-tenant (resolve domain). Pra-aktivasi (tabel `comments` belum di
 * tenants:migrate) → degradasi ramah (list kosong / 503), bukan 500.
 * Body disimpan APA ADANYA; klien WAJIB meng-escape saat render (anti-XSS).
 * NOL logika uang/nilai.
 */
class KomentarApiController extends Controller
{
    /** GET /api/v1/posts/{slug}/komentar — daftar komentar tayang. */
    public function index(string $slug): JsonResponse
    {
        if (! $this->hasTenant() || ! Schema::hasTable('comments')) {
            return response()->json(['ok' => true, 'data' => []]);
        }

        $article = $this->resolveArticle($slug);
        if ($article === null) {
            return response()->json(['ok' => false, 'message' => 'Artikel tidak ditemukan'], 404);
        }

        $data = Comment::query()
            ->where('article_id', $article->id)
            ->approved()
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn (Comment $c) => $this->mapComment($c))
            ->all();

        return response()->json(['ok' => true, 'data' => $data]);
    }

    /** POST /api/v1/posts/{slug}/komentar — kirim komentar (auth + izin di route). */
    public function store(Request $request, string $slug): JsonResponse
    {
        if (! $this->hasTenant()) {
            return response()->json(['ok' => false, 'message' => 'Pesantren tidak dikenali.'], 404);
        }

        if (! Schema::hasTable('comments')) {
            return response()->json([
                'ok' => false,
                'message' => 'Fitur komentar belum diaktifkan. Hubungi admin pesantren.',
            ], 503);
        }

        $article = $this->resolveArticle($slug);
        if ($article === null) {
            return response()->json(['ok' => false, 'message' => 'Artikel tidak ditemukan'], 404);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:2000'],
        ], [], [
            'body' => 'komentar',
        ]);

        $user = $request->user();
        $praModerasi = (bool) Setting::getTenantValue('komentar.moderasi_dulu', true);

        $comment = Comment::create([
            'tenant_id' => tenant()?->getTenantKey(),
            'article_id' => $article->id,
            'user_id' => $user->id,
            'author_name' => (string) $user->name,
            'body' => $validated['body'],
            'status' => $praModerasi ? Comment::STATUS_PENDING : Comment::STATUS_APPROVED,
        ]);

        return response()->json([
            'ok' => true,
            'data' => [
                'pending' => $praModerasi,
                'message' => $praModerasi
                    ? 'Komentar terkirim dan menunggu persetujuan pengurus.'
                    : 'Komentar Anda sudah tayang. Terima kasih.',
                // Komentar pra-moderasi belum tampil publik → kirim null agar klien
                // tak menampilkannya sebelum disetujui.
                'comment' => $praModerasi ? null : $this->mapComment($comment),
            ],
        ], 201);
    }

    private function hasTenant(): bool
    {
        return tenancy()->initialized && tenant() !== null;
    }

    private function resolveArticle(string $slug): ?Article
    {
        return Article::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->first();
    }

    /** @return array<string, mixed> */
    private function mapComment(Comment $c): array
    {
        return [
            'id' => (string) $c->id,
            'author' => $c->author_name,
            'body' => $c->body,
            'createdAt' => optional($c->created_at)?->toIso8601String(),
        ];
    }
}
