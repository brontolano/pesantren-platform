<?php

namespace Tests\Feature;

use App\Filament\Pages\PlatformDashboard;
use App\Filament\Pages\SupportDesk;
use App\Filament\Resources\PlatformAnnouncements\PlatformAnnouncementResource;
use App\Filament\Widgets\PlatformOverview;
use App\Jobs\RecordPlatformWorkerHeartbeat;
use App\Models\PlatformRuntimeHeartbeat;
use App\Models\PlatformSupportTicket;
use App\Models\User;
use App\Services\PlatformOperationsSnapshot;
use Filament\Facades\Filament;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

class PlatformOperationsConsoleTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->ensurePpdbTestingSchemaReady([
            'users', 'tenants', 'domains', 'roles', 'permissions', 'model_has_roles',
            'platform_support_tickets', 'platform_support_replies', 'platform_announcements',
            'platform_announcement_reads', 'failed_jobs', 'jobs',
            'platform_runtime_heartbeats',
        ]);
        $this->truncatePpdbTables([
            'platform_support_replies', 'platform_support_tickets', 'platform_announcement_reads',
            'platform_announcements', 'model_has_roles', 'domains', 'tenants', 'users', 'roles', 'permissions',
            'platform_runtime_heartbeats',
        ]);
        Role::query()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_superadmin_platform_dapat_dibuat_dengan_sandi_rahasia_dan_konteks_central(): void
    {
        $password = 'Sandi-Uji-Aman-2026!';

        $this->artisan('platform:super-admin', [
            'email' => 'pemilik@pesantren.test',
            '--name' => 'Pemilik Platform',
        ])
            ->expectsQuestion('Kata sandi baru (minimal 12 karakter)', $password)
            ->expectsQuestion('Ulangi kata sandi', $password)
            ->assertSuccessful();

        $user = User::query()->where('email', 'pemilik@pesantren.test')->firstOrFail();
        $this->assertNull($user->tenant_id);
        $this->assertTrue($user->isPlatformSuperAdmin());
        $this->assertTrue(Hash::check($password, $user->getAuthPassword()));
    }

    public function test_platform_access_menolak_superadmin_tenant_dan_akun_pusat_biasa(): void
    {
        $platformAdmin = $this->makeUser('pusat@pesantren.test', null, 'super_admin');
        $tenant = $this->createTestTenant('tenant-operasi', 'Pesantren Uji');
        $tenantAdmin = $this->makeUser('tenant@pesantren.test', $tenant->id, 'super_admin');
        $centralStaff = $this->makeUser('staf@pesantren.test', null, null);
        $panel = Filament::getPanel('admin');

        $this->assertTrue($platformAdmin->canAccessPanel($panel));
        $this->assertFalse($tenantAdmin->canAccessPanel($panel));
        $this->assertFalse($centralStaff->canAccessPanel($panel));
        $this->assertTrue($platformAdmin->isPlatformSuperAdmin());
        $this->assertFalse($tenantAdmin->isPlatformSuperAdmin());
    }

    public function test_dashboard_menampilkan_pemeriksaan_aktual_dan_beban_dukungan(): void
    {
        Queue::fake();
        config([
            'queue.default' => 'redis',
            'queue.connections.redis.driver' => 'redis',
            'queue.connections.redis-ai.driver' => 'redis',
        ]);
        $admin = $this->makeUser('operator@pesantren.test', null, 'super_admin');
        $tenant = $this->createTestTenant('tenant-bantuan', 'Pesantren Bantuan');
        PlatformSupportTicket::query()->create([
            'ticket_number' => 'PO-TEST-001',
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $admin->id,
            'category' => 'teknis',
            'impact' => 'banyak_pengguna',
            'priority' => 'urgent',
            'subject' => 'Website tidak dapat dibuka',
            'description' => 'Pemeriksaan tenant uji.',
            'status' => 'open',
        ]);

        $this->actingAs($admin);
        Livewire::test(PlatformOverview::class)
            ->assertSee('Kondisi operasional platform')
            ->assertSee('Database pusat')
            ->assertSee('Antrean pekerjaan')
            ->assertSee('Scheduler Laravel')
            ->assertSee('Worker antrean umum')
            ->assertSee('Worker siaran')
            ->assertSee('Worker modul AI')
            ->assertSee('Periksa ulang')
            ->assertSeeHtml('wire:click="$refresh"')
            ->assertSee('Kasus dukungan')
            ->assertSee('kasus menunggu tindak lanjut')
            ->assertSee('berdampak tinggi/mendesak')
            ->assertSeeHtml('<strong>1</strong> kasus menunggu tindak lanjut')
            ->assertSeeHtml('<strong>1</strong> berdampak tinggi/mendesak')
            ->assertSee('Kirim informasi ke pesantren')
            ->assertSee('Kelola proyek pengembangan')
            ->assertSee('Tinjau tugas pengembang');
    }

    public function test_dashboard_meringkas_status_tenant_dengan_satu_query(): void
    {
        $this->createTestTenant('tenant-ringkas-a', 'Pesantren Aktif');
        $inactiveTenant = $this->createTestTenant('tenant-ringkas-b', 'Pesantren Nonaktif');
        $inactiveTenant->update(['is_active' => false]);

        $tenantQueries = [];
        DB::listen(function (QueryExecuted $query) use (&$tenantQueries): void {
            if (str_contains(strtolower($query->sql), 'from "tenants"')) {
                $tenantQueries[] = $query->sql;
            }
        });

        $snapshot = app(PlatformOperationsSnapshot::class)->capture();

        $this->assertSame(2, $snapshot['tenants']['total']);
        $this->assertSame(1, $snapshot['tenants']['enabled']);
        $this->assertSame(1, $snapshot['tenants']['disabled']);
        $this->assertCount(1, $tenantQueries, 'Dashboard seharusnya merangkum total tenant dengan satu query agregat.');
    }

    public function test_operator_dapat_mengubah_status_dan_membalas_kasus_dengan_izin_pusat(): void
    {
        $admin = $this->makeUser('support@pesantren.test', null, 'super_admin');
        $tenant = $this->createTestTenant('tenant-support', 'Pesantren Support');
        $ticket = PlatformSupportTicket::query()->create([
            'ticket_number' => 'PO-TEST-002',
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $admin->id,
            'category' => 'akun',
            'subject' => 'Perlu bantuan akun',
            'description' => 'Akun operator perlu diperiksa.',
            'status' => 'open',
            'priority' => 'normal',
        ]);

        $this->actingAs($admin);
        Livewire::test(SupportDesk::class)
            ->set('selectedTicketId', $ticket->id)
            ->set('replyBody', 'Kami sedang memeriksa akses akun Anda.')
            ->call('reply')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('platform_support_replies', [
            'platform_support_ticket_id' => $ticket->id,
            'is_internal' => false,
            'body' => 'Kami sedang memeriksa akses akun Anda.',
        ]);
        $this->assertSame('waiting', $ticket->fresh()->status);
    }

    public function test_admin_surface_mengharuskan_superadmin_platform(): void
    {
        $platformAdmin = $this->makeUser('owner@pesantren.test', null, 'super_admin');
        $tenant = $this->createTestTenant('tenant-gate', 'Pesantren Gate');
        $tenantAdmin = $this->makeUser('owner@tenant.test', $tenant->id, 'super_admin');

        $this->actingAs($platformAdmin);
        $this->assertTrue(PlatformDashboard::canAccess());
        $this->assertTrue(SupportDesk::canAccess());
        $this->assertTrue(PlatformAnnouncementResource::canAccess());
        $this->assertTrue(PlatformOverview::canView());

        $this->actingAs($tenantAdmin);
        $this->assertFalse(PlatformDashboard::canAccess());
        $this->assertFalse(SupportDesk::canAccess());
        $this->assertFalse(PlatformAnnouncementResource::canAccess());
        $this->assertFalse(PlatformOverview::canView());
    }

    public function test_heartbeat_mencatat_scheduler_dan_mengirim_ping_ke_antrian_worker(): void
    {
        Queue::fake();
        config([
            'queue.default' => 'redis',
            'queue.connections.redis.driver' => 'redis',
            'queue.connections.redis-ai.driver' => 'redis',
        ]);

        $this->artisan('platform:heartbeat')->assertSuccessful();

        $this->assertDatabaseHas('platform_runtime_heartbeats', ['service_key' => 'scheduler']);
        Queue::assertPushed(RecordPlatformWorkerHeartbeat::class, 3);
        Queue::assertPushedOn('broadcast', RecordPlatformWorkerHeartbeat::class);
        Queue::assertPushedOn('ai', RecordPlatformWorkerHeartbeat::class);
    }

    public function test_worker_heartbeat_munculkan_sinyal_terkini_di_dasbor(): void
    {
        Queue::fake();
        config([
            'queue.default' => 'redis',
            'queue.connections.redis.driver' => 'redis',
            'queue.connections.redis-ai.driver' => 'redis',
        ]);
        $admin = $this->makeUser('monitor@pesantren.test', null, 'super_admin');
        PlatformRuntimeHeartbeat::query()->create([
            'service_key' => 'scheduler',
            'last_seen_at' => now(),
            'detail' => 'Penjadwal aktif untuk uji.',
        ]);
        PlatformRuntimeHeartbeat::query()->create([
            'service_key' => 'worker-default',
            'last_seen_at' => now(),
            'detail' => 'Worker umum aktif untuk uji.',
        ]);

        $this->actingAs($admin);
        Livewire::test(PlatformOverview::class)
            ->assertSee('Sinyal baru')
            ->assertSee('Penjadwal aktif untuk uji.')
            ->assertSee('Worker antrean umum')
            ->assertSee('Worker umum aktif untuk uji.');
    }

    private function makeUser(string $email, ?string $tenantId, ?string $role): User
    {
        $user = User::withoutGlobalScopes()->create([
            'name' => 'Operator Uji',
            'email' => $email,
            'tenant_id' => $tenantId,
            'password' => 'kata-sandi-uji',
        ]);
        if ($role !== null) {
            $user->assignRole($role);
        }

        return $user;
    }
}
