@extends('layouts.app')
@section('title','Modul')
@section('content')
<x-pesantren-ui::page-header title="Modul" description="Ketersediaan katalog dibedakan dari implementasi dan status tiap organisasi." />
<section class="cards" aria-label="Katalog modul">
@forelse($modules as $module)<article class="card panel"><div class="panel-head"><h2>{{ $module->name }}</h2><span class="status {{ $module->available && $module->implemented ? 'status-green':'status-yellow' }}">{{ $module->available && $module->implemented ? 'Tersedia':'Belum tersedia' }}</span></div><p>{{ $module->description }}</p><p class="field-hint">Ketersediaan: {{ $module->available?'aktif':'nonaktif' }} · Implementasi: {{ $module->implemented?'ada':'belum ada' }}</p></article>@empty<p class="empty">Katalog belum diisi.</p>@endforelse
</section>
@endsection
