<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Support\Str;
use Modules\CMS\Models\Article;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/** Widget embed publik: data.json (CORS, hanya berita published) + widget.js. */
class EmbedWidgetTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    private string $host;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains', 'articles']);
        $this->truncatePpdbTables(['articles', 'domains', 'tenants']);

        $suffix = Str::lower(Str::random(6));
        $tenantId = 'embed-'.$suffix;
        $this->host = $tenantId.'.localhost';
        $this->tenant = $this->createProvisionedTenant($tenantId, 'Pesantren Embed', $this->host);
    }

    public function test_data_json_hanya_memuat_berita_published_dengan_cors(): void
    {
        tenancy()->initialize($this->tenant);
        Article::create([
            'tenant_id' => $this->tenant->id, 'title' => 'Berita Tayang', 'slug' => 'berita-tayang',
            'content' => 'isi', 'is_published' => true, 'published_at' => now(),
        ]);
        Article::create([
            'tenant_id' => $this->tenant->id, 'title' => 'Draf Rahasia', 'slug' => 'draf-rahasia',
            'content' => 'isi', 'is_published' => false,
        ]);
        tenancy()->end();

        $response = $this->call('GET', "http://{$this->host}/embed/data.json", [], [], [], [
            'HTTP_HOST' => $this->host, 'SERVER_NAME' => $this->host,
        ]);

        $response->assertOk();
        $response->assertHeader('Access-Control-Allow-Origin', '*');
        $response->assertJsonPath('nama', 'Pesantren Embed');
        $response->assertJsonFragment(['judul' => 'Berita Tayang']);
        $response->assertJsonMissing(['judul' => 'Draf Rahasia']);
        $this->assertStringContainsString('/ppdb', (string) $response->json('ppdb_url'));
    }

    public function test_widget_js_dilayani_sebagai_javascript(): void
    {
        $response = $this->call('GET', "http://{$this->host}/embed/widget.js", [], [], [], [
            'HTTP_HOST' => $this->host, 'SERVER_NAME' => $this->host,
        ]);

        $response->assertOk();
        $this->assertStringContainsString('application/javascript', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('pesantren-widget', $response->getContent());
        $this->assertStringContainsString('/embed/data.json', $response->getContent());
    }
}
