@extends('layouts.app')
@section('title','Log Aktivitas')
@section('content')
<x-pesantren-ui::page-header title="Log Aktivitas" description="Perubahan operator dicatat. Data operasional organisasi tidak ditampilkan." />
<section class="card panel"><div class="activity-list">@forelse($events as $event)<article class="activity-row"><time class="activity-time">{{ $event->created_at?->timezone('Asia/Jakarta')->format('d M Y, H:i') }} WIB</time><div><div class="activity-title">{{ str_replace(['.','_'], ' ', $event->event) }}</div><div class="activity-meta">{{ $event->actor_type==='platform_operator'?'Operator pusat':'Proses platform' }} @if($event->organization_id) · Organisasi #{{ $event->organization_id }}@endif @if($event->subject_type) · {{ class_basename($event->subject_type) }} #{{ $event->subject_id }}@endif</div></div></article>@empty<p class="empty">Belum ada aktivitas yang dicatat.</p>@endforelse</div><div class="pagination"><span>{{ $events->total() }} catatan</span><div class="pagination-links">{{ $events->links() }}</div></div></section>
@endsection
