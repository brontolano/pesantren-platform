<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\CMS\Models\Article;
use Modules\CMS\Models\Comment;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * API komentar native: GET (approved publik) + POST (token + izin komentar +
 * pra/pasca moderasi). Sejajar web CommentController.
 */
class KomentarApiTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private const SLUG = 'artikel-uji-komentar';

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'tenant_storage', 'users', 'roles', 'permissions',
            'model_has_roles', 'settings', 'articles', 'comments', 'personal_access_tokens',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    /** @return array{0: Tenant, 1: string} */
    private function bootTenant(): array
    {
        $host = 'komentar-'.Str::lower(Str::random(6)).'.localhost';
        $tenantId = explode('.', $host)[0];
        $tenant = $this->createProvisionedTenant($tenantId, 'Pesantren Uji', $host);

        $tenant->run(function () {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $perm = Permission::firstOrCreate(['name' => 'komentar']);
            Role::firstOrCreate(['name' => 'jemaah'])->givePermissionTo($perm);

            Article::create([
                'title' => 'Artikel Uji Komentar',
                'slug' => self::SLUG,
                'content' => 'Isi artikel.',
                'is_published' => true,
                'published_at' => Carbon::now()->subDay(),
            ]);
        });

        return [$tenant, $host];
    }

    private function headers(string $host): array
    {
        return ['HTTP_HOST' => $host, 'SERVER_NAME' => $host];
    }

    /** Buat user + token Sanctum di konteks tenant (pola AuthApiTest yang terbukti). */
    private function tokenUntuk(Tenant $tenant, string $email, ?string $role): string
    {
        return $tenant->run(function () use ($email, $role) {
            $u = User::create([
                'name' => $role === 'jemaah' ? 'Warga Komentar' : 'Tanpa Izin',
                'email' => $email,
                'password' => 'rahasia123',
            ]);
            if ($role !== null) {
                $u->assignRole($role);
            }
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $u->createToken('test')->plainTextToken;
        });
    }

    private function bearer(string $host, string $token): array
    {
        return array_merge($this->headers($host), ['Authorization' => "Bearer {$token}"]);
    }

    public function test_index_hanya_komentar_approved(): void
    {
        [$tenant, $host] = $this->bootTenant();

        $tenant->run(function () {
            $article = Article::where('slug', self::SLUG)->firstOrFail();
            Comment::create([
                'article_id' => $article->id, 'author_name' => 'Disetujui',
                'body' => 'Komentar tayang.', 'status' => Comment::STATUS_APPROVED,
            ]);
            Comment::create([
                'article_id' => $article->id, 'author_name' => 'Menunggu',
                'body' => 'Komentar pending.', 'status' => Comment::STATUS_PENDING,
            ]);
        });

        $resp = $this->getJson("http://{$host}/api/v1/posts/".self::SLUG.'/komentar', $this->headers($host));

        $resp->assertOk()->assertJsonPath('ok', true);
        $resp->assertJsonCount(1, 'data');
        $resp->assertJsonPath('data.0.author', 'Disetujui');
    }

    public function test_store_pra_moderasi_jadi_pending(): void
    {
        [$tenant, $host] = $this->bootTenant();
        $token = $this->tokenUntuk($tenant, 'warga.komentar@uji.test', 'jemaah');

        $resp = $this->postJson(
            "http://{$host}/api/v1/posts/".self::SLUG.'/komentar',
            ['body' => 'Assalamualaikum, semoga berkah.'],
            $this->bearer($host, $token),
        );

        $resp->assertStatus(201)->assertJsonPath('data.pending', true);
        $resp->assertJsonPath('data.comment', null);

        $jumlah = $tenant->run(fn () => Comment::query()->pending()->count());
        $this->assertSame(1, $jumlah);
    }

    public function test_store_pasca_moderasi_langsung_tayang(): void
    {
        [$tenant, $host] = $this->bootTenant();
        $tenant->run(function () use ($tenant) {
            Setting::updateOrCreate(
                ['tenant_id' => $tenant->getTenantKey(), 'key' => 'komentar.moderasi_dulu'],
                ['value' => false],
            );
        });
        $token = $this->tokenUntuk($tenant, 'warga.komentar@uji.test', 'jemaah');

        $resp = $this->postJson(
            "http://{$host}/api/v1/posts/".self::SLUG.'/komentar',
            ['body' => 'Komentar langsung tayang.'],
            $this->bearer($host, $token),
        );

        $resp->assertStatus(201)->assertJsonPath('data.pending', false);
        $resp->assertJsonPath('data.comment.author', 'Warga Komentar');
    }

    public function test_store_tanpa_token_ditolak(): void
    {
        [, $host] = $this->bootTenant();

        $this->postJson(
            "http://{$host}/api/v1/posts/".self::SLUG.'/komentar',
            ['body' => 'Tanpa login.'],
            $this->headers($host),
        )->assertUnauthorized();
    }

    public function test_store_tanpa_izin_komentar_ditolak(): void
    {
        [$tenant, $host] = $this->bootTenant();
        $token = $this->tokenUntuk($tenant, 'tanpa.izin@uji.test', null);

        $this->postJson(
            "http://{$host}/api/v1/posts/".self::SLUG.'/komentar',
            ['body' => 'Tak boleh komentar.'],
            $this->bearer($host, $token),
        )->assertForbidden();
    }
}
