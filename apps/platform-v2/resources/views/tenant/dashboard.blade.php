@extends('layouts.app')
@section('title','Beranda organisasi')
@section('panelType','tenant')
@section('content')
<div class="demo-banner"><strong>Ruang kerja organisasi.</strong> Data di sini hanya milik {{ $organization->name }}.</div>
<x-pesantren-ui::page-header title="Selamat datang, {{ $admin->name }}" description="{{ $organization->canonical_domain }} · Ini ruang kerja dasar organisasi Anda." />
<section class="cards"><article class="card metric"><div class="metric-label">Identitas</div><div class="metric-value" style="font-size:1.2rem">{{ $organization->name }}</div><div class="metric-hint">{{ ucfirst(str_replace('_',' ',$organization->organization_type)) }}</div></article><article class="card metric"><div class="metric-label">Status layanan</div><div class="metric-value" style="font-size:1.2rem">Aktif</div><div class="metric-hint">Dikelola melalui platform</div></article></section>
<section class="card panel"><div class="panel-head"><h2>Modul Anda</h2></div><p>Modul operasional akan ditampilkan setelah tersedia dan diaktifkan untuk organisasi Anda.</p><div class="notice notice-info" role="status">PPDB, Kesantrian, Keuangan, dan Portal Orang Tua belum dibuka di fondasi ini.</div></section>
@endsection
