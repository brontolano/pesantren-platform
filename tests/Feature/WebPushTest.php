<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Models\Tenant;
use App\Models\User;
use Modules\Notifikasi\Services\PushNotificationService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * F6 — Web Push (VAPID). Kanal SUPLEMEN opt-in: subscribe/unsubscribe +
 * isolasi antar user + PushNotificationService aman tanpa jaringan nyata
 * (kunci VAPID kosong di testing → send() no-op, tak melempar).
 */
class WebPushTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    private User $userA;

    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->ensurePpdbTestingSchemaReady(['push_subscriptions', 'tenants', 'domains', 'users']);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(): void
    {
        $this->tenant = $this->createRuntimeTenant('f6push', 'Pesantren F6 Push');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);

        $this->userA = User::withoutGlobalScopes()->create([
            'name' => 'User A',
            'email' => 'usera@'.tenant('id').'.localhost',
            'password' => 'password',
            'tenant_id' => tenant('id'),
        ]);

        $this->userB = User::withoutGlobalScopes()->create([
            'name' => 'User B',
            'email' => 'userb@'.tenant('id').'.localhost',
            'password' => 'password',
            'tenant_id' => tenant('id'),
        ]);

        tenancy()->end();
    }

    private function host(): string
    {
        return $this->tenant->id.'.localhost';
    }

    public function test_subscribe_valid_payload_creates_row(): void
    {
        $this->boot();

        $response = $this->actingAs($this->userA)->postJson('http://'.$this->host().'/push/subscribe', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
            'keys' => [
                'p256dh' => 'p256dh-key-value',
                'auth' => 'auth-secret-value',
            ],
        ]);

        $response->assertStatus(201);

        tenancy()->initialize($this->tenant);
        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $this->userA->id,
            'endpoint_hash' => PushSubscription::hashEndpoint('https://fcm.googleapis.com/fcm/send/abc123'),
        ], 'tenant');
        tenancy()->end();
    }

    public function test_subscribe_rejects_invalid_payload(): void
    {
        $this->boot();

        $response = $this->actingAs($this->userA)
            ->postJson('http://'.$this->host().'/push/subscribe', [
                'endpoint' => '',
                'keys' => ['p256dh' => '', 'auth' => ''],
            ]);

        $response->assertStatus(422);
    }

    public function test_unsubscribe_removes_row(): void
    {
        $this->boot();

        $endpoint = 'https://fcm.googleapis.com/fcm/send/xyz789';
        tenancy()->initialize($this->tenant);
        PushSubscription::create([
            'tenant_id' => tenant('id'),
            'user_id' => $this->userA->id,
            'endpoint' => $endpoint,
            'endpoint_hash' => PushSubscription::hashEndpoint($endpoint),
            'p256dh' => 'p',
            'auth' => 'a',
        ]);
        tenancy()->end();

        $response = $this->actingAs($this->userA)->deleteJson('http://'.$this->host().'/push/unsubscribe', [
            'endpoint' => $endpoint,
        ]);

        $response->assertStatus(200);

        tenancy()->initialize($this->tenant);
        $this->assertDatabaseMissing('push_subscriptions', ['endpoint_hash' => PushSubscription::hashEndpoint($endpoint)], 'tenant');
        tenancy()->end();
    }

    public function test_isolation_user_cannot_unsubscribe_others_subscription(): void
    {
        $this->boot();

        $endpoint = 'https://fcm.googleapis.com/fcm/send/belongs-to-a';
        tenancy()->initialize($this->tenant);
        PushSubscription::create([
            'tenant_id' => tenant('id'),
            'user_id' => $this->userA->id,
            'endpoint' => $endpoint,
            'endpoint_hash' => PushSubscription::hashEndpoint($endpoint),
            'p256dh' => 'p',
            'auth' => 'a',
        ]);
        tenancy()->end();

        // User B mencoba unsubscribe endpoint milik User A — tidak boleh terhapus.
        $response = $this->actingAs($this->userB)->deleteJson('http://'.$this->host().'/push/unsubscribe', [
            'endpoint' => $endpoint,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('deleted', 0);

        tenancy()->initialize($this->tenant);
        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $this->userA->id,
            'endpoint_hash' => PushSubscription::hashEndpoint($endpoint),
        ], 'tenant');
        tenancy()->end();
    }

    public function test_push_service_send_without_subscription_does_not_throw(): void
    {
        $this->boot();
        tenancy()->initialize($this->tenant);

        $service = app(PushNotificationService::class);

        $this->assertFalse($service->hasSubscription($this->userA));

        // Tanpa subscription → send() harus no-op, tak melempar.
        $service->send($this->userA, 'Judul', 'Isi pesan');

        $this->assertDatabaseCount('log_notifikasi', 0, 'tenant');
        tenancy()->end();
    }

    public function test_push_service_send_with_subscription_does_not_throw_when_vapid_unconfigured(): void
    {
        $this->boot();
        tenancy()->initialize($this->tenant);

        $endpoint = 'https://fcm.googleapis.com/fcm/send/has-sub';
        PushSubscription::create([
            'tenant_id' => tenant('id'),
            'user_id' => $this->userA->id,
            'endpoint' => $endpoint,
            'endpoint_hash' => PushSubscription::hashEndpoint($endpoint),
            'p256dh' => 'p',
            'auth' => 'a',
        ]);

        $service = app(PushNotificationService::class);
        $this->assertTrue($service->hasSubscription($this->userA));

        // Kunci VAPID kosong di testing (.env.example placeholder) → send()
        // harus tetap no-op aman (skip diam-diam), TIDAK melempar, TIDAK
        // melakukan panggilan jaringan nyata.
        config(['services.webpush.public_key' => '', 'services.webpush.private_key' => '']);
        $service->send($this->userA, 'Judul', 'Isi pesan');

        $this->assertDatabaseCount('log_notifikasi', 0, 'tenant');
        tenancy()->end();
    }
}
