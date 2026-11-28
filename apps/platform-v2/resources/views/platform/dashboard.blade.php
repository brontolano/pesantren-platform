@extends('layouts.app')
@section('title', 'Ringkasan')

@section('content')
<x-pesantren-ui::page-header title="Ringkasan platform" description="Pantau organisasi dan aktivitas operator dari satu tempat.">
    <x-slot:actions>
        <a class="pu-button pu-button--primary" href="{{ route('platform.organizations.index') }}"><x-pesantren-ui::icon name="building" size="sm" />Kelola organisasi</a>
    </x-slot:actions>
</x-pesantren-ui::page-header>

<section class="cards" aria-label="Metrik platform">
    <x-pesantren-ui::metric-card label="Total organisasi" :value="number_format($counts['total'], 0, ',', '.')" hint="Terdaftar di pusat" icon="building" />
    <x-pesantren-ui::metric-card label="Perlu perhatian" :value="number_format($counts['failed'], 0, ',', '.')" hint="Penyiapan berstatus gagal" icon="activity" />
    <x-pesantren-ui::metric-card label="Modul tersedia" :value="number_format($counts['modules'], 0, ',', '.')" hint="Di katalog layanan platform" icon="layers" />
</section>

<div class="dashboard-grid">
    <section class="pu-card panel" aria-labelledby="trend-title">
        <div class="panel-head"><div><h2 id="trend-title">Aktivitas 7 hari terakhir</h2><p class="field-hint">Jumlah catatan audit operator per hari.</p></div><x-pesantren-ui::icon name="activity" /></div>
        @if($trend->sum('count') === 0)
            <x-pesantren-ui::empty-state title="Belum ada aktivitas" description="Aktivitas operator akan muncul di sini setelah tercatat." icon="activity" />
        @else
            <div class="trend" role="img" aria-label="Grafik aktivitas operator selama tujuh hari">
                @foreach($trend as $day)
                    <div class="trend__day" title="{{ \Illuminate\Support\Carbon::parse($day['date'])->format('d M Y') }}: {{ $day['count'] }} aktivitas">
                        <div class="trend__bar-wrap"><span class="trend__bar" style="height: {{ $day['count'] > 0 ? max(5, (int) round($day['count'] / $trendMax * 124)) : 4 }}px"></span></div>
                        <span class="trend__count">{{ $day['count'] }}</span>
                        <span class="trend__label"><time datetime="{{ $day['date'] }}">{{ substr($day['date'], 8, 2) }}</time></span>
                    </div>
                @endforeach
            </div>
            <p class="trend-caption">Tanggal ditampilkan dari tujuh hari terakhir, termasuk hari tanpa aktivitas.</p>
        @endif
    </section>

    <section class="pu-card panel" aria-labelledby="recent-title">
        <div class="panel-head"><div><h2 id="recent-title">Aktivitas terbaru</h2><p class="field-hint">Lima catatan terbaru dari audit pusat.</p></div><a href="{{ route('platform.activity') }}">Lihat semua</a></div>
        @if($recent->isEmpty())
            <x-pesantren-ui::empty-state title="Belum ada catatan" description="Perubahan operator akan tercatat dan tampil di sini." icon="activity" />
        @else
            <div class="activity-list">
                @foreach($recent as $event)
                    <article class="activity-row">
                        <time class="activity-time" datetime="{{ $event->created_at?->toIso8601String() }}">{{ $event->created_at?->timezone('Asia/Jakarta')->format('d M Y, H:i') }} WIB</time>
                        <div><div class="activity-title">{{ \Illuminate\Support\Str::of($event->event)->replace(['.', '_'], ' ')->ucfirst() }}</div><div class="activity-meta">Operator {{ $event->actor_id ? '#'.$event->actor_id : 'sistem' }}@if($event->organization_id) · Organisasi #{{ $event->organization_id }}@endif</div></div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</div>
<p class="dashboard-note">Ringkasan ini memakai registri, katalog modul, dan log pusat. Database tenant tidak diperiksa saat halaman dibuka.</p>
@endsection
