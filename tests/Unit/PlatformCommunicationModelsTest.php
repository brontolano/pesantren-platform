<?php

namespace Tests\Unit;

use App\Models\PlatformAnnouncement;
use App\Models\PlatformSupportReply;
use App\Models\PlatformSupportTicket;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlatformCommunicationModelsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.connections.platform_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        Config::set('database.default', 'platform_test');
        Config::set('tenancy.database.central_connection', 'platform_test');
        DB::purge('platform_test');

        (require base_path('database/migrations/2026_11_27_000001_create_platform_announcements_table.php'))->up();
        (require base_path('database/migrations/2026_11_27_000002_create_platform_support_tickets_table.php'))->up();
    }

    public function test_pengumuman_hanya_muncul_pada_tenant_dan_periode_yang_sesuai(): void
    {
        $this->announcement(['title' => 'Untuk semua']);
        $this->announcement(['title' => 'Khusus tenant A', 'target_scope' => 'tenant', 'tenant_id' => 'tenant-a']);
        $this->announcement(['title' => 'Tenant lain', 'target_scope' => 'tenant', 'tenant_id' => 'tenant-b']);
        $this->announcement(['title' => 'Masih draf', 'status' => 'draft']);
        $this->announcement(['title' => 'Belum tayang', 'published_at' => now()->addHour()]);
        $this->announcement(['title' => 'Sudah berakhir', 'ends_at' => now()->subSecond()]);

        $titles = PlatformAnnouncement::query()->visibleTo('tenant-a')->pluck('title')->all();

        $this->assertEqualsCanonicalizing(['Untuk semua', 'Khusus tenant A'], $titles);
    }

    public function test_tiket_dan_balasan_internal_tersaring_oleh_scope_tenant(): void
    {
        $ticket = PlatformSupportTicket::query()->create([
            'ticket_number' => 'PO-TEST-000001',
            'tenant_id' => 'tenant-a',
            'created_by_user_id' => 1,
            'category' => 'teknis',
            'impact' => 'sebagian_terhambat',
            'priority' => 'normal',
            'subject' => 'Website belum tampil',
            'description' => 'Website pesantren belum dapat dibuka oleh pengunjung.',
            'status' => 'open',
        ]);
        PlatformSupportTicket::query()->create([
            'ticket_number' => 'PO-TEST-000002',
            'tenant_id' => 'tenant-b',
            'created_by_user_id' => 2,
            'category' => 'website',
            'impact' => 'pertanyaan',
            'priority' => 'normal',
            'subject' => 'Pertanyaan tenant lain',
            'description' => 'Detail bantuan untuk tenant lain.',
            'status' => 'open',
        ]);

        $ticket->replies()->createMany([
            ['author_user_id' => 10, 'author_name' => 'Tim bantuan', 'is_internal' => false, 'body' => 'Balasan untuk tenant.'],
            ['author_user_id' => 10, 'author_name' => 'Tim bantuan', 'is_internal' => true, 'body' => 'Catatan internal.'],
        ]);

        $tickets = PlatformSupportTicket::query()
            ->forTenant('tenant-a')
            ->with(['replies' => fn ($query) => $query->public()])
            ->get();

        $this->assertCount(1, $tickets);
        $this->assertSame('tenant-a', $tickets->first()->tenant_id);
        $this->assertSame(['Balasan untuk tenant.'], $tickets->first()->replies->pluck('body')->all());
        $this->assertSame(1, PlatformSupportReply::query()->public()->count());
    }

    private function announcement(array $attributes = []): PlatformAnnouncement
    {
        return PlatformAnnouncement::query()->create(array_merge([
            'target_scope' => 'all',
            'title' => 'Informasi platform',
            'body' => 'Isi informasi platform.',
            'category' => 'informasi',
            'priority' => 'normal',
            'status' => 'published',
            'published_at' => now()->subMinute(),
            'ends_at' => null,
        ], $attributes));
    }
}
