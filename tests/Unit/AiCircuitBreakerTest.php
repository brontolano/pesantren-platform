<?php

namespace Tests\Unit;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Modules\AI\Services\Gateway\CircuitBreaker;
use Tests\TestCase;

/**
 * Unit circuit breaker gateway AI (T-A001): buka setelah N kegagalan beruntun,
 * half-open setelah cooldown, pulih saat sukses.
 */
class AiCircuitBreakerTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function breaker(): CircuitBreaker
    {
        return new CircuitBreaker(failureThreshold: 3, windowSeconds: 300, cooldownSeconds: 60, store: 'array');
    }

    public function test_sirkuit_terbuka_setelah_n_kegagalan_beruntun(): void
    {
        Cache::store('array')->flush();
        $breaker = $this->breaker();

        $breaker->recordFailure('provider:uji');
        $breaker->recordFailure('provider:uji');
        $this->assertTrue($breaker->allowRequest('provider:uji'), 'Di bawah ambang: masih closed');
        $this->assertSame('closed', $breaker->state('provider:uji'));

        $breaker->recordFailure('provider:uji'); // kegagalan ke-3 = ambang
        $this->assertFalse($breaker->allowRequest('provider:uji'), 'Mencapai ambang: open');
        $this->assertSame('open', $breaker->state('provider:uji'));
    }

    public function test_half_open_setelah_cooldown_dan_terbuka_lagi_bila_gagal(): void
    {
        Cache::store('array')->flush();
        $breaker = $this->breaker();

        foreach (range(1, 3) as $i) {
            $breaker->recordFailure('provider:uji');
        }
        $this->assertSame('open', $breaker->state('provider:uji'));

        // Cooldown 60 dtk lewat → half-open, percobaan diizinkan.
        Carbon::setTestNow(now()->addSeconds(61));
        $this->assertSame('half_open', $breaker->state('provider:uji'));
        $this->assertTrue($breaker->allowRequest('provider:uji'));

        // Gagal saat half-open → sirkuit terbuka lagi.
        $breaker->recordFailure('provider:uji');
        $this->assertSame('open', $breaker->state('provider:uji'));
        $this->assertFalse($breaker->allowRequest('provider:uji'));
    }

    public function test_sukses_saat_half_open_memulihkan_sirkuit(): void
    {
        Cache::store('array')->flush();
        $breaker = $this->breaker();

        foreach (range(1, 3) as $i) {
            $breaker->recordFailure('provider:uji');
        }

        Carbon::setTestNow(now()->addSeconds(61));
        $this->assertSame('half_open', $breaker->state('provider:uji'));

        $breaker->recordSuccess('provider:uji');
        $this->assertSame('closed', $breaker->state('provider:uji'));
        $this->assertTrue($breaker->allowRequest('provider:uji'));
    }

    public function test_kegagalan_di_luar_jendela_tidak_membuka_sirkuit(): void
    {
        Cache::store('array')->flush();
        $breaker = $this->breaker();

        $breaker->recordFailure('provider:uji');
        $breaker->recordFailure('provider:uji');

        // Jendela 300 dtk lewat → penghitung kadaluarsa.
        Carbon::setTestNow(now()->addSeconds(301));

        $breaker->recordFailure('provider:uji');
        $this->assertSame('closed', $breaker->state('provider:uji'), 'Hitungan dimulai ulang setelah jendela lewat');
        $this->assertTrue($breaker->allowRequest('provider:uji'));
    }
}
