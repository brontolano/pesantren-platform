<?php

namespace Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\CMS\Models\Article;
use Modules\CMS\Models\Comment;

/**
 * Kirim komentar publik pada artikel (1-aplikasi, Fase 2c). HANYA user login
 * (jemaah / peran internal) — route di grup 'auth'; tamu read-only. Pra/pasca
 * moderasi diatur setting 'komentar.moderasi_dulu' (default pra-moderasi).
 *
 * Keamanan: body disimpan APA ADANYA (mentah) lalu di-escape saat render Vue
 * ({{ }} auto-escape) — bukan HTML. NOL logika uang/nilai.
 */
class CommentController extends Controller
{
    public function store(Request $request, string $slug): RedirectResponse
    {
        $article = Article::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->firstOrFail();

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:2000'],
        ], [], [
            'body' => 'komentar',
        ]);

        $user = $request->user();
        $praModerasi = (bool) Setting::getTenantValue('komentar.moderasi_dulu', true);

        Comment::create([
            'tenant_id' => tenant()?->getTenantKey(),
            'article_id' => $article->id,
            'user_id' => $user->id,
            'author_name' => (string) $user->name,
            'body' => $validated['body'],
            'status' => $praModerasi ? Comment::STATUS_PENDING : Comment::STATUS_APPROVED,
        ]);

        return back()->with('success', $praModerasi
            ? 'Komentar terkirim dan menunggu persetujuan pengurus. Terima kasih.'
            : 'Komentar Anda sudah tayang. Terima kasih.');
    }
}
