<?php

namespace Tests\Feature;

use App\Models\PlatformAnnouncement;
use App\Models\PlatformSupportTicket;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformCommunicationPostgresTest extends TestCase
{
    private string $runId;

    private string $tenantA;

    private string $tenantB;

    private string $ticketPrefix;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('tenancy.database.central_connection', 'pgsql');
        $this->runId = Str::lower(Str::random(12));
        $this->tenantA = 'test-'.$this->runId.'-a';
        $this->tenantB = 'test-'.$this->runId.'-b';
        $this->ticketPrefix = 'PO-TEST-'.$this->runId;
    }

    protected function tearDown(): void
    {
        PlatformSupportTicket::query()->where('ticket_number', 'like', $this->ticketPrefix.'%')->delete();
        PlatformAnnouncement::query()->where('title', 'like', 'TEST-'.$this->runId.'%')->delete();

        parent::tearDown();
    }

    public function test_postgres_membatasi_pengumuman_sesuai_tenant_dan_jadwal(): void
    {
        $this->announcement('TEST-'.$this->runId.'-all', [
            'target_scope' => 'all',
            'tenant_id' => null,
        ]);
        $this->announcement('TEST-'.$this->runId.'-tenant-a', [
            'target_scope' => 'tenant',
            'tenant_id' => $this->tenantA,
        ]);
        $this->announcement('TEST-'.$this->runId.'-tenant-b', [
            'target_scope' => 'tenant',
            'tenant_id' => $this->tenantB,
        ]);
        $this->announcement('TEST-'.$this->runId.'-draft', [
            'target_scope' => 'all',
            'tenant_id' => null,
            'status' => 'draft',
        ]);
        $this->announcement('TEST-'.$this->runId.'-expired', [
            'target_scope' => 'all',
            'tenant_id' => null,
            'ends_at' => now()->subSecond(),
        ]);

        $titles = PlatformAnnouncement::query()
            ->visibleTo($this->tenantA)
            ->where('title', 'like', 'TEST-'.$this->runId.'%')
            ->pluck('title')
            ->all();

        $this->assertEqualsCanonicalizing([
            'TEST-'.$this->runId.'-all',
            'TEST-'.$this->runId.'-tenant-a',
        ], $titles);
    }

    public function test_postgres_menyaring_tiket_tenant_lain_dan_catatan_internal(): void
    {
        $ticket = PlatformSupportTicket::query()->create([
            'ticket_number' => $this->ticketPrefix.'-01',
            'tenant_id' => $this->tenantA,
            'created_by_user_id' => 1,
            'category' => 'teknis',
            'impact' => 'sebagian_terhambat',
            'priority' => 'normal',
            'subject' => 'Website belum tampil',
            'description' => 'Website belum dapat dibuka oleh pengunjung pesantren.',
            'status' => 'open',
        ]);
        PlatformSupportTicket::query()->create([
            'ticket_number' => $this->ticketPrefix.'-02',
            'tenant_id' => $this->tenantB,
            'created_by_user_id' => 2,
            'category' => 'website',
            'impact' => 'pertanyaan',
            'priority' => 'normal',
            'subject' => 'Kasus tenant lain',
            'description' => 'Detail yang hanya milik tenant lain.',
            'status' => 'open',
        ]);
        $ticket->replies()->createMany([
            ['author_user_id' => 10, 'author_name' => 'Tim bantuan', 'is_internal' => false, 'body' => 'Balasan untuk tenant.'],
            ['author_user_id' => 10, 'author_name' => 'Tim bantuan', 'is_internal' => true, 'body' => 'Catatan internal.'],
        ]);

        $tickets = PlatformSupportTicket::query()
            ->forTenant($this->tenantA)
            ->with(['replies' => fn ($query) => $query->public()])
            ->get();

        $this->assertCount(1, $tickets);
        $this->assertSame($this->tenantA, $tickets->first()->tenant_id);
        $this->assertSame(['Balasan untuk tenant.'], $tickets->first()->replies->pluck('body')->all());
    }

    private function announcement(string $title, array $attributes): PlatformAnnouncement
    {
        return PlatformAnnouncement::query()->create(array_merge([
            'target_scope' => 'all',
            'tenant_id' => null,
            'title' => $title,
            'body' => 'Isi pengujian.',
            'category' => 'informasi',
            'priority' => 'normal',
            'status' => 'published',
            'published_at' => now()->subMinute(),
            'ends_at' => null,
        ], $attributes));
    }
}
