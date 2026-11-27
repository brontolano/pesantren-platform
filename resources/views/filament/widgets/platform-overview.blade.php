@php
    $tenantSummary = $snapshot['tenants'];
    $supportSummary = $snapshot['support'];
    $failedJobs = $snapshot['failed_jobs'];
    $serviceStatus = static fn (string $status): array => match ($status) {
        'ok' => ['class' => 'is-good', 'label' => 'Berjalan'],
        'warning', 'stale', 'missing' => ['class' => 'is-warning', 'label' => 'Perlu ditinjau'],
        'error' => ['class' => 'is-danger', 'label' => 'Gangguan'],
        default => ['class' => 'is-neutral', 'label' => 'Belum diketahui'],
    };
    $serviceRows = collect($snapshot['checks'])->concat($snapshot['runtime']);
    $attentionCount = $serviceRows->filter(fn (array $service): bool => $service['status'] !== 'ok')->count();
    $enabledPercent = ($tenantSummary['available'] && $tenantSummary['total'] > 0)
        ? min(100, max(0, (int) round(($tenantSummary['enabled'] / $tenantSummary['total']) * 100)))
        : 0;
@endphp

<div class="po-dashboard">
    <header class="po-dashboard-header">
        <div class="po-dashboard-intro">
            <p class="po-overline">RUANG KERJA <span aria-hidden="true">/</span> SUPERADMIN</p>
            <h1>Selamat datang, {{ auth()->user()?->name ?? 'Pengelola' }}</h1>
            <p class="po-dashboard-summary">Pantau kesiapan layanan dan lanjutkan pekerjaan platform dari satu tempat.</p>
        </div>
        <div class="po-dashboard-header-actions">
            <p class="po-last-checked">Pemeriksaan terakhir <time datetime="{{ $snapshot['checked_at']->toIso8601String() }}">{{ $snapshot['checked_at']->format('d M Y, H:i') }}</time></p>
            <a href="{{ $createTenantUrl }}" class="po-primary-action">
                <svg aria-hidden="true" viewBox="0 0 20 20" fill="none"><path d="M10 4v12M4 10h12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                <span>Tambah pesantren</span>
            </a>
        </div>
    </header>

    <section class="po-stat-grid" aria-label="Ringkasan platform">
        <article class="po-stat-card">
            <div class="po-stat-topline">
                <p class="po-stat-label">Pesantren</p>
                <span class="po-stat-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M3.5 20.5h17M5.5 20V9.5L12 5l6.5 4.5V20M9 20v-5h6v5M9 10.5h.01M15 10.5h.01" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><path d="M10 5V3.5h4V5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                </span>
            </div>
            <p class="po-stat-value">{{ $tenantSummary['available'] ? number_format($tenantSummary['total'], 0, ',', '.') : '—' }}</p>
            <p class="po-stat-note">{{ $tenantSummary['available'] ? number_format($tenantSummary['enabled'], 0, ',', '.').' aktif · '.number_format($tenantSummary['disabled'], 0, ',', '.').' nonaktif' : 'Data belum tersedia' }}</p>
        </article>

        <article class="po-stat-card">
            <div class="po-stat-topline">
                <p class="po-stat-label">Layanan aktif</p>
                <span class="po-stat-icon is-green" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="m5 12.5 4.3 4.2L19 7" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
            </div>
            <p class="po-stat-value">{{ $tenantSummary['available'] ? number_format($tenantSummary['enabled'], 0, ',', '.') : '—' }}</p>
            <p class="po-stat-note">{{ $tenantSummary['available'] ? 'Dari '.number_format($tenantSummary['total'], 0, ',', '.').' pesantren terdaftar' : 'Data belum tersedia' }}</p>
        </article>

        <article class="po-stat-card">
            <div class="po-stat-topline">
                <p class="po-stat-label">Kasus dukungan</p>
                <span class="po-stat-icon is-amber" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3h11A2.5 2.5 0 0 1 20 5.5v8a2.5 2.5 0 0 1-2.5 2.5H11l-5 4v-4.3A2.5 2.5 0 0 1 4 13.5v-8Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M8 8h8M8 11.5h5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                </span>
            </div>
            <p class="po-stat-value">{{ $supportSummary['available'] ? number_format($supportSummary['open'], 0, ',', '.') : '—' }}</p>
            <p class="po-stat-note">{{ $supportSummary['available'] ? number_format($supportSummary['urgent'], 0, ',', '.').' berdampak tinggi atau mendesak' : 'Data dukungan belum tersedia' }}</p>
        </article>

        <article class="po-stat-card">
            <div class="po-stat-topline">
                <p class="po-stat-label">Pekerjaan gagal</p>
                <span class="po-stat-icon {{ ($failedJobs ?? 0) > 0 ? 'is-rose' : '' }}" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M12 3.5 21 19H3l9-15.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M12 9v4.5M12 16.5h.01" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
                </span>
            </div>
            <p class="po-stat-value">{{ is_null($failedJobs) ? '—' : number_format($failedJobs, 0, ',', '.') }}</p>
            <p class="po-stat-note">{{ is_null($failedJobs) ? 'Data belum tersedia' : 'Catatan pada antrean pusat' }}</p>
        </article>
    </section>

    <section class="po-panel po-health-panel" aria-labelledby="po-health-heading">
        <div class="po-panel-heading">
            <div>
                <p class="po-overline">KESEHATAN LAYANAN</p>
                <h2 id="po-health-heading">Kondisi operasional</h2>
                <p class="po-panel-description">Koneksi diperiksa langsung. Heartbeat yang hilang atau lebih lama dari 3 menit perlu ditinjau.</p>
            </div>
            <div class="po-panel-heading-actions">
                <span class="po-attention-pill {{ $attentionCount > 0 ? 'has-attention' : 'is-clear' }}">
                    <span class="po-indicator" aria-hidden="true"></span>
                    {{ $attentionCount > 0 ? $attentionCount.' perlu ditinjau' : 'Semua sinyal aktif' }}
                </span>
                <button type="button" wire:click="$refresh" wire:loading.attr="disabled" wire:target="$refresh" aria-label="Periksa ulang kondisi operasional platform" class="po-refresh-action">
                    <svg wire:loading wire:target="$refresh" aria-hidden="true" class="po-spinner" viewBox="0 0 20 20" fill="none"><path d="M16.7 8A7 7 0 0 0 4.4 5.2L3 6.5M3 3.5v3h3M3.3 12A7 7 0 0 0 15.6 14.8l1.4-1.3m0 3v-3h-3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <svg wire:loading.remove wire:target="$refresh" aria-hidden="true" viewBox="0 0 20 20" fill="none"><path d="M16.7 8A7 7 0 0 0 4.4 5.2L3 6.5M3 3.5v3h3M3.3 12A7 7 0 0 0 15.6 14.8l1.4-1.3m0 3v-3h-3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span wire:loading.remove wire:target="$refresh">Periksa ulang</span>
                    <span wire:loading wire:target="$refresh" aria-live="polite">Memeriksa…</span>
                </button>
            </div>
        </div>

        <div class="po-service-grid">
            @foreach ($serviceRows as $service)
                @php($state = $serviceStatus($service['status']))
                <article class="po-service-card {{ $state['class'] }}">
                    <div class="po-service-heading">
                        <span class="po-service-dot" aria-hidden="true"></span>
                        <h3>{{ $service['label'] }}</h3>
                    </div>
                    <span class="po-service-status">{{ $state['label'] }}</span>
                    <p>{{ $service['detail'] }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <div class="po-dashboard-columns">
        <section class="po-panel po-tenants-panel" aria-labelledby="po-tenants-heading">
            <div class="po-panel-heading compact">
                <div>
                    <p class="po-overline">JARINGAN PESANTREN</p>
                    <h2 id="po-tenants-heading">Tenant platform</h2>
                    <p class="po-panel-description">Status layanan mengikuti pengaturan setiap pesantren.</p>
                </div>
                <a href="{{ $tenantsUrl }}" class="po-text-action">Lihat daftar <span aria-hidden="true">↗</span></a>
            </div>

            @if ($tenantSummary['available'])
                <div class="po-tenant-bar" role="img" aria-label="{{ number_format($tenantSummary['enabled'], 0, ',', '.') }} pesantren aktif dan {{ number_format($tenantSummary['disabled'], 0, ',', '.') }} nonaktif">
                    <span class="is-active" style="width: {{ $enabledPercent }}%"></span>
                    <span class="is-inactive" style="width: {{ 100 - $enabledPercent }}%"></span>
                </div>
                <dl class="po-tenant-breakdown">
                    <div>
                        <dt><span class="po-legend-dot is-active" aria-hidden="true"></span>Aktif</dt>
                        <dd>{{ number_format($tenantSummary['enabled'], 0, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt><span class="po-legend-dot is-inactive" aria-hidden="true"></span>Nonaktif</dt>
                        <dd>{{ number_format($tenantSummary['disabled'], 0, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt>Total</dt>
                        <dd>{{ number_format($tenantSummary['total'], 0, ',', '.') }}</dd>
                    </div>
                </dl>
            @else
                <div class="po-empty-state" role="status">Ringkasan belum tersedia. Buka daftar pesantren untuk memeriksa koneksi data.</div>
            @endif
        </section>

        <section class="po-panel po-support-panel" aria-labelledby="po-support-heading">
            <div class="po-panel-heading compact">
                <div>
                    <p class="po-overline">BANTUAN TENANT</p>
                    <h2 id="po-support-heading">Dukungan</h2>
                </div>
                <a href="{{ $supportUrl }}" class="po-text-action">Buka meja dukungan <span aria-hidden="true">↗</span></a>
            </div>
            @if ($supportSummary['available'])
                <p class="po-support-count">{{ number_format($supportSummary['open'], 0, ',', '.') }} <span>kasus menunggu tindak lanjut</span></p>
                <p class="po-support-note">{{ number_format($supportSummary['urgent'], 0, ',', '.') }} kasus berdampak tinggi atau mendesak.</p>
            @else
                <div class="po-empty-state" role="status">Data dukungan belum tersedia. Periksa migrasi komunikasi platform.</div>
            @endif
        </section>
    </div>

    <section class="po-quick-work" aria-labelledby="po-quick-heading">
        <div class="po-quick-heading">
            <div>
                <p class="po-overline">LANJUTKAN PEKERJAAN</p>
                <h2 id="po-quick-heading">Tindakan cepat</h2>
            </div>
            <p class="po-panel-description">Pintasan untuk tugas platform yang sering dilakukan.</p>
        </div>

        <nav class="po-action-grid" aria-label="Tindakan cepat platform">
            <a href="{{ $tenantsUrl }}" class="po-action-card">
                <span class="po-action-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M4 20V9l8-5 8 5v11M2.5 20.5h19M9 20v-5h6v5M8 10h.01M16 10h.01" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                <span class="po-action-copy"><strong>Kelola pesantren</strong><small>Profil, alamat, dan status layanan</small></span>
                <span class="po-action-arrow" aria-hidden="true">↗</span>
            </a>
            <a href="{{ $modulesUrl }}" class="po-action-card">
                <span class="po-action-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><rect x="4" y="4" width="6" height="6" rx="1.3" stroke="currentColor" stroke-width="1.7"/><rect x="14" y="4" width="6" height="6" rx="1.3" stroke="currentColor" stroke-width="1.7"/><rect x="4" y="14" width="6" height="6" rx="1.3" stroke="currentColor" stroke-width="1.7"/><rect x="14" y="14" width="6" height="6" rx="1.3" stroke="currentColor" stroke-width="1.7"/></svg></span>
                <span class="po-action-copy"><strong>Atur modul tenant</strong><small>Aktifkan fitur sesuai kebutuhan</small></span>
                <span class="po-action-arrow" aria-hidden="true">↗</span>
            </a>
            <a href="{{ $announcementsUrl }}" class="po-action-card">
                <span class="po-action-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M4 10v4a2 2 0 0 0 2 2h2l8 4V4l-8 4H6a2 2 0 0 0-2 2ZM8 16l1.5 4M18 9a4 4 0 0 1 0 6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                <span class="po-action-copy"><strong>Kirim pengumuman</strong><small>Informasi teknis dan pembaruan</small></span>
                <span class="po-action-arrow" aria-hidden="true">↗</span>
            </a>
            <a href="{{ $supportUrl }}" class="po-action-card">
                <span class="po-action-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3h11A2.5 2.5 0 0 1 20 5.5v8a2.5 2.5 0 0 1-2.5 2.5H11l-5 4v-4.3A2.5 2.5 0 0 1 4 13.5v-8Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M8 8h8M8 11.5h5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg></span>
                <span class="po-action-copy"><strong>Tindak lanjuti bantuan</strong><small>Kasus dan komunikasi tenant</small></span>
                <span class="po-action-arrow" aria-hidden="true">↗</span>
            </a>
        </nav>
    </section>

    <details class="po-developer-tools">
        <summary><span>Ruang pengembang</span><span class="po-developer-note">Proyek, tugas, dan kontak teknis</span></summary>
        <nav aria-label="Pintasan pengembang" class="po-developer-links">
            <a href="{{ $projectsUrl }}">Pantau proyek</a>
            <a href="{{ $tasksUrl }}">Tinjau tugas</a>
            <a href="{{ route('legal.contact') }}">Kontak layanan</a>
        </nav>
    </details>
</div>
