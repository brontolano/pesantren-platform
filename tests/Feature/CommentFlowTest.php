<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Modules\CMS\Models\Article;
use Modules\CMS\Models\Comment;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Komentar publik artikel (1-aplikasi, Fase 2c): tamu read-only; user login
 * (jemaah / peran internal) boleh kirim; pra/pasca moderasi via setting; hanya
 * komentar approved tampil. Pola test sama dengan Jemaah/PesertaAuthFlow.
 */
class CommentFlowTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'tenant_storage', 'users', 'roles', 'permissions',
            'model_has_roles', 'articles', 'comments', 'settings',
        ]);
        $this->truncatePpdbTables([
            'comments', 'articles', 'settings', 'model_has_roles', 'roles', 'users',
            'domains', 'tenants', 'tenant_storage',
        ]);
    }

    /** @return array{0: Tenant, 1: string, 2: Article} */
    private function bootWithArticle(string $prefix): array
    {
        $host = $prefix.'-'.Str::lower(Str::random(6)).'.localhost';
        $tenantId = explode('.', $host)[0];
        $tenant = $this->createProvisionedTenant($tenantId, 'Pesantren Komentar', $host);

        tenancy()->initialize($tenant);
        $article = Article::create([
            'tenant_id' => $tenant->id,
            'title' => 'Kegiatan Santri',
            'slug' => 'kegiatan-santri',
            'excerpt' => 'ringkas',
            'content' => 'isi artikel',
            'is_published' => true,
            'published_at' => now(),
        ]);
        tenancy()->end();

        return [$tenant, $host, $article];
    }

    private function makeUser(Tenant $tenant, string $role): User
    {
        $u = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'U '.$role,
            'email' => Str::lower(Str::random(8)).'@'.$tenant->id.'.localhost',
            'password' => 'password',
            'must_reset_password' => false,
        ]);
        $u->assignRole($role);

        return $u->fresh();
    }

    /** @return array{HTTP_HOST: string, SERVER_NAME: string} */
    private function headers(string $host): array
    {
        return ['HTTP_HOST' => $host, 'SERVER_NAME' => $host];
    }

    public function test_tamu_tidak_bisa_komentar(): void
    {
        [$tenant, $host, $article] = $this->bootWithArticle('tamu');

        $this->post("http://{$host}/artikel/{$article->slug}/komentar", ['body' => 'halo'], $this->headers($host));

        $this->assertGuest();
        tenancy()->initialize($tenant);
        $this->assertSame(0, Comment::query()->count(), 'tamu tak boleh membuat komentar');
        tenancy()->end();
    }

    public function test_jemaah_komentar_masuk_pending(): void
    {
        [$tenant, $host, $article] = $this->bootWithArticle('jem');

        tenancy()->initialize($tenant);
        $jemaah = $this->makeUser($tenant, 'jemaah');
        tenancy()->end();

        $resp = $this->actingAs($jemaah)
            ->post("http://{$host}/artikel/{$article->slug}/komentar", ['body' => 'Barakallah'], $this->headers($host));

        $resp->assertStatus(302);
        $resp->assertSessionHas('success');

        tenancy()->initialize($tenant);
        $c = Comment::query()->firstOrFail();
        $this->assertSame(Comment::STATUS_PENDING, $c->status, 'pra-moderasi: status menunggu');
        $this->assertSame($jemaah->id, $c->user_id);
        tenancy()->end();
    }

    public function test_peran_internal_juga_bisa_komentar(): void
    {
        [$tenant, $host, $article] = $this->bootWithArticle('guru');

        tenancy()->initialize($tenant);
        $guru = $this->makeUser($tenant, 'guru');
        tenancy()->end();

        $this->actingAs($guru)
            ->post("http://{$host}/artikel/{$article->slug}/komentar", ['body' => 'Mantap'], $this->headers($host))
            ->assertStatus(302);

        tenancy()->initialize($tenant);
        $this->assertSame(1, Comment::query()->where('user_id', $guru->id)->count());
        tenancy()->end();
    }

    public function test_peserta_eksternal_tidak_bisa_komentar(): void
    {
        [$tenant, $host, $article] = $this->bootWithArticle('peserta');

        tenancy()->initialize($tenant);
        $peserta = $this->makeUser($tenant, 'peserta');
        tenancy()->end();

        // Gerbang 'can:komentar' → peserta (eksternal, tanpa izin komentar) ditolak.
        $this->actingAs($peserta)
            ->post("http://{$host}/artikel/{$article->slug}/komentar", ['body' => 'halo'], $this->headers($host))
            ->assertForbidden();

        tenancy()->initialize($tenant);
        $this->assertSame(0, Comment::query()->count());
        tenancy()->end();
    }

    public function test_hanya_komentar_approved_tampil_di_artikel(): void
    {
        [$tenant, $host, $article] = $this->bootWithArticle('show');

        tenancy()->initialize($tenant);
        $u = $this->makeUser($tenant, 'jemaah');
        Comment::create(['tenant_id' => $tenant->id, 'article_id' => $article->id, 'user_id' => $u->id, 'author_name' => 'A', 'body' => 'sudah disetujui', 'status' => Comment::STATUS_APPROVED]);
        Comment::create(['tenant_id' => $tenant->id, 'article_id' => $article->id, 'user_id' => $u->id, 'author_name' => 'B', 'body' => 'masih menunggu', 'status' => Comment::STATUS_PENDING]);
        tenancy()->end();

        $this->get("http://{$host}/artikel/{$article->slug}", $this->headers($host))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('CMS/ArticleDetail')
                ->has('comments', 1)
                ->where('comments.0.body', 'sudah disetujui')
                ->where('comment.isLoggedIn', false));
    }

    public function test_pasca_moderasi_langsung_tayang(): void
    {
        [$tenant, $host, $article] = $this->bootWithArticle('pasca');

        tenancy()->initialize($tenant);
        Setting::create(['tenant_id' => $tenant->id, 'key' => 'komentar.moderasi_dulu', 'value' => false]);
        $u = $this->makeUser($tenant, 'jemaah');
        tenancy()->end();

        $this->actingAs($u)
            ->post("http://{$host}/artikel/{$article->slug}/komentar", ['body' => 'langsung tayang'], $this->headers($host))
            ->assertStatus(302);

        tenancy()->initialize($tenant);
        $this->assertSame(Comment::STATUS_APPROVED, Comment::query()->firstOrFail()->status);
        tenancy()->end();
    }

    public function test_body_disimpan_mentah_untuk_di_escape_klien(): void
    {
        // Kontrak XSS: server menyimpan apa adanya; Vue {{ }} (bukan v-html) +
        // Filament TextColumn meng-escape saat render. Test mengunci no-mangle server.
        [$tenant, $host, $article] = $this->bootWithArticle('xss');

        tenancy()->initialize($tenant);
        $u = $this->makeUser($tenant, 'jemaah');
        tenancy()->end();

        $payload = '<script>alert(1)</script>';
        $this->actingAs($u)
            ->post("http://{$host}/artikel/{$article->slug}/komentar", ['body' => $payload], $this->headers($host))
            ->assertStatus(302);

        tenancy()->initialize($tenant);
        $this->assertSame($payload, Comment::query()->firstOrFail()->body);
        tenancy()->end();
    }
}
