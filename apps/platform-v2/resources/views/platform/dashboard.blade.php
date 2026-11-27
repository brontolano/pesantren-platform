@extends('layouts.app')
@section('title','Ringkasan')
@section('content')
<x-pesantren-ui::page-header title="Ringkasan platform" description="Lihat status penyiapan dan layanan organisasi."><x-slot:actions><a class="pu-button pu-button--primary" href="{{ route('platform.organizations.index') }}">Kelola organisasi</a></x-slot:actions></x-pesantren-ui::page-header>
<section class="cards" aria-label="Ringkasan jumlah organisasi">
    <article class="card metric"><div class="metric-label">Semua organisasi</div><div class="metric-value">{{ $counts['total'] }}</div><div class="metric-hint">Terdaftar pada platform</div></article>
    <article class="card metric"><div class="metric-label">Draf</div><div class="metric-value">{{ $counts['draft'] }}</div><div class="metric-hint">Menunggu dilanjutkan</div></article>
    <article class="card metric"><div class="metric-label">Penyiapan berjalan</div><div class="metric-value">{{ $counts['setup'] }}</div><div class="metric-hint">Status provisioning terpisah</div></article>
    <article class="card metric"><div class="metric-label">Perlu pemulihan</div><div class="metric-value">{{ $counts['failed'] }}</div><div class="metric-hint">Layanan aktif: {{ $counts['active'] }}</div></article>
</section>
<section class="card panel" aria-labelledby="recent-title"><div class="panel-head"><h2 id="recent-title">Aktivitas operator terbaru</h2><a href="{{ route('platform.activity') }}">Lihat semua</a></div>
    @if($recent->isEmpty())<p class="empty">Belum ada perubahan yang dicatat.</p>@else
        <div class="activity-list">@foreach($recent as $event)<div class="activity-row"><time class="activity-time">{{ $event->created_at?->timezone('Asia/Jakarta')->format('d M Y, H:i') }} WIB</time><div><div class="activity-title">{{ str_replace(['.','_'], ' ', $event->event) }}</div><div class="activity-meta">Operator {{ $event->actor_id ? '#'.$event->actor_id : 'sistem' }}@if($event->organization_id) · Organisasi #{{ $event->organization_id }}@endif</div></div></div>@endforeach</div>
    @endif
</section>
<section class="card panel"><div class="panel-head"><h2>Status organisasi</h2><span class="status status-green">Layanan aktif {{ $counts['active'] }}</span></div><p class="field-hint">Pemantauan pusat hanya menampilkan status dan hasil pemeriksaan teknis. Data santri tidak dibaca.</p></section>
@endsection
