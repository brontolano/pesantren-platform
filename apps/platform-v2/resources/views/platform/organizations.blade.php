@extends('layouts.app')
@section('title','Organisasi')
@section('content')
<x-pesantren-ui::page-header title="Organisasi" description="Buat draf, lanjutkan setup, dan pantau status layanan." />

<x-pesantren-ui::card class="panel-space" aria-labelledby="create-title">
    <x-slot:header><h2 id="create-title">Buat draf organisasi</h2><p class="field-hint">Isi empat langkah awal. Draf dapat dilanjutkan tanpa menggandakan domain atau database.</p></x-slot:header>
    <ol class="workflow" aria-label="Langkah penyiapan organisasi">
        <li class="workflow-step" data-wizard-indicator="1"><span class="workflow-index">1</span><span><span class="workflow-title">Identitas</span><br><span class="workflow-caption">Nama dan jenis</span></span></li>
        <li class="workflow-step" data-wizard-indicator="2"><span class="workflow-index">2</span><span><span class="workflow-title">Domain</span><br><span class="workflow-caption">Alamat kanonis</span></span></li>
        <li class="workflow-step" data-wizard-indicator="3"><span class="workflow-index">3</span><span><span class="workflow-title">Admin pertama</span><br><span class="workflow-caption">Email undangan</span></span></li>
        <li class="workflow-step" data-wizard-indicator="4"><span class="workflow-index">4</span><span><span class="workflow-title">Tinjau</span><br><span class="workflow-caption">Simpan sebagai draf</span></span></li>
    </ol>
    <form method="post" action="{{ route('platform.organizations.store') }}" data-onboarding-wizard data-error-step="{{ $errors->has('canonical_domain') ? 2 : ($errors->has('first_admin_email') ? 3 : 1) }}" novalidate>@csrf
        <section data-wizard-panel="1" aria-labelledby="wizard-identity-title" tabindex="-1"><h3 id="wizard-identity-title">Identitas organisasi</h3><div class="form-grid">
            <div class="pu-field"><label class="pu-field__label" for="name">Nama organisasi <span class="pu-field__required">(wajib)</span></label><input class="pu-input" id="name" name="name" required maxlength="160" value="{{ old('name') }}" autocomplete="organization"><p class="pu-field__hint">Contoh: Pondok Pesantren Demo Sejahtera.</p></div>
            <div class="pu-field"><label class="pu-field__label" for="organization_type">Jenis organisasi <span class="pu-field__required">(wajib)</span></label><select class="pu-input" id="organization_type" name="organization_type" required><option value="">Pilih jenis</option>@foreach(['pesantren'=>'Pesantren','yayasan'=>'Yayasan','organisasi_islam'=>'Organisasi Islam','majelis_taklim'=>'Majelis taklim'] as $key=>$label)<option value="{{ $key }}" @selected(old('organization_type')===$key)>{{ $label }}</option>@endforeach</select></div>
        </div></section>
        <section data-wizard-panel="2" aria-labelledby="wizard-domain-title" tabindex="-1"><h3 id="wizard-domain-title">Domain organisasi</h3><div class="form-grid">
            <div class="pu-field"><label class="pu-field__label" for="canonical_domain">Domain kanonis <span class="pu-field__required">(wajib)</span></label><input class="pu-input" id="canonical_domain" name="canonical_domain" required maxlength="253" placeholder="demo-pesantren-v2.test" value="{{ old('canonical_domain') }}" autocomplete="url"><p class="pu-field__hint">Tulis nama domain saja, tanpa https:// atau path.</p></div>
        </div></section>
        <section data-wizard-panel="3" aria-labelledby="wizard-admin-title" tabindex="-1"><h3 id="wizard-admin-title">Admin pertama</h3><div class="form-grid">
            <div class="pu-field"><label class="pu-field__label" for="first_admin_email">Email admin pertama <span class="pu-field__required">(wajib)</span></label><input class="pu-input" id="first_admin_email" name="first_admin_email" type="email" required maxlength="190" value="{{ old('first_admin_email') }}" autocomplete="email"><p class="pu-field__hint">Undangan sekali pakai dikirim melalui mail sink lokal.</p></div>
        </div></section>
        <section data-wizard-panel="4" aria-labelledby="wizard-review-title" tabindex="-1"><h3 id="wizard-review-title">Tinjau draf</h3><dl class="wizard-review"><dt>Nama</dt><dd data-review="name">{{ old('name', 'Belum diisi') }}</dd><dt>Jenis</dt><dd data-review="organization_type">{{ old('organization_type', 'Belum dipilih') }}</dd><dt>Domain</dt><dd data-review="canonical_domain">{{ old('canonical_domain', 'Belum diisi') }}</dd><dt>Email admin</dt><dd data-review="first_admin_email">{{ old('first_admin_email', 'Belum diisi') }}</dd></dl></section>
        <div class="wizard-navigation" style="margin-top:18px"><x-pesantren-ui::button type="button" variant="secondary" size="sm" data-wizard-previous hidden>Kembali</x-pesantren-ui::button><span></span><x-pesantren-ui::button type="button" size="sm" data-wizard-next>Lanjut</x-pesantren-ui::button><x-pesantren-ui::button type="submit" data-wizard-submit hidden>Simpan draf</x-pesantren-ui::button></div>
    </form>
</x-pesantren-ui::card>

<x-pesantren-ui::card class="panel-space" aria-labelledby="list-title">
    <x-slot:header><div class="panel-head"><h2 id="list-title">Daftar organisasi</h2><span class="field-hint">{{ $organizations->total() }} organisasi</span></div></x-slot:header>
    <form class="toolbar" method="get" action="{{ route('platform.organizations.index') }}"><div class="field" style="flex:1;min-width:220px"><label for="q">Cari organisasi atau domain</label><input class="pu-input" id="q" name="q" value="{{ request('q') }}" placeholder="Nama atau domain"></div><x-pesantren-ui::button type="submit" variant="secondary">Cari</x-pesantren-ui::button></form>
    @if($organizations->isEmpty())
        <p class="empty">Belum ada organisasi. Buat draf pertama melalui formulir di atas.</p>
    @else
        <x-pesantren-ui::data-table label="Daftar organisasi">
            <x-slot:desktop>
                <table><caption>Daftar organisasi</caption><thead><tr><th scope="col">Organisasi</th><th scope="col">Penyiapan</th><th scope="col">Layanan</th><th scope="col">Pemeriksaan teknis</th><th scope="col">Tindakan</th></tr></thead><tbody>
                @foreach($organizations as $organization)
                    <tr>
                        <td><span class="cell-main">{{ $organization->name }}</span><span class="cell-sub">{{ $organization->canonical_domain }} · {{ ucfirst(str_replace('_',' ',$organization->organization_type)) }}</span><span class="cell-sub">Admin: {{ $organization->first_admin_email }}</span></td>
                        <td><x-pesantren-ui::badge :tone="$organization->setup_state==='ready'?'success':($organization->setup_state==='failed'?'danger':($organization->setup_state==='running'?'info':'warning'))">{{ ['draft'=>'Draf','pending'=>'Menunggu','running'=>'Berjalan','ready'=>'Siap','failed'=>'Gagal'][$organization->setup_state] ?? $organization->setup_state }}</x-pesantren-ui::badge>@if($organization->last_setup_error_code)<span class="cell-sub">Kode: {{ $organization->last_setup_error_code }}</span>@endif</td>
                        <td><x-pesantren-ui::badge :tone="$organization->service_state==='active'?'success':($organization->service_state==='suspended'?'danger':'neutral')">{{ ['inactive'=>'Nonaktif','active'=>'Aktif','suspended'=>'Ditangguhkan'][$organization->service_state] ?? $organization->service_state }}</x-pesantren-ui::badge></td>
                        <td><x-pesantren-ui::badge :tone="$organization->last_technical_check_state==='healthy'?'success':($organization->last_technical_check_state==='failed'?'danger':'neutral')">{{ $organization->last_technical_check_state ? ($organization->last_technical_check_state==='healthy'?'Sehat':'Gagal') : 'Belum dicek' }}</x-pesantren-ui::badge><span class="cell-sub">{{ $organization->last_technical_check_at?->timezone('Asia/Jakarta')->format('d M Y, H:i').' WIB' ?? 'Belum ada waktu cek' }}</span></td>
                        <td><div class="actions">@include('platform.partials.organization-actions', ['organization' => $organization])</div></td>
                    </tr>
                @endforeach
                </tbody></table>
            </x-slot:desktop>
            <x-slot:mobile>
                <ul class="pu-data-table__mobile-list">
                    @foreach($organizations as $organization)
                        <li class="pu-data-table__mobile-card"><dl>
                            <dt>Organisasi</dt><dd><strong>{{ $organization->name }}</strong><br>{{ $organization->canonical_domain }}<br>{{ ucfirst(str_replace('_',' ',$organization->organization_type)) }}<br>Admin: {{ $organization->first_admin_email }}</dd>
                            <dt>Penyiapan</dt><dd><x-pesantren-ui::badge :tone="$organization->setup_state==='ready'?'success':($organization->setup_state==='failed'?'danger':($organization->setup_state==='running'?'info':'warning'))">{{ ['draft'=>'Draf','pending'=>'Menunggu','running'=>'Berjalan','ready'=>'Siap','failed'=>'Gagal'][$organization->setup_state] ?? $organization->setup_state }}</x-pesantren-ui::badge>@if($organization->last_setup_error_code)<br>Kode: {{ $organization->last_setup_error_code }}@endif</dd>
                            <dt>Layanan</dt><dd><x-pesantren-ui::badge :tone="$organization->service_state==='active'?'success':($organization->service_state==='suspended'?'danger':'neutral')">{{ ['inactive'=>'Nonaktif','active'=>'Aktif','suspended'=>'Ditangguhkan'][$organization->service_state] ?? $organization->service_state }}</x-pesantren-ui::badge></dd>
                            <dt>Pemeriksaan teknis</dt><dd><x-pesantren-ui::badge :tone="$organization->last_technical_check_state==='healthy'?'success':($organization->last_technical_check_state==='failed'?'danger':'neutral')">{{ $organization->last_technical_check_state ? ($organization->last_technical_check_state==='healthy'?'Sehat':'Gagal') : 'Belum dicek' }}</x-pesantren-ui::badge><br>{{ $organization->last_technical_check_at?->timezone('Asia/Jakarta')->format('d M Y, H:i').' WIB' ?? 'Belum ada waktu cek' }}</dd>
                        </dl><div class="pu-data-table__mobile-actions">@include('platform.partials.organization-actions', ['organization' => $organization])</div></li>
                    @endforeach
                </ul>
            </x-slot:mobile>
        </x-pesantren-ui::data-table>
        <div class="pagination"><span>Halaman {{ $organizations->currentPage() }} dari {{ max(1,$organizations->lastPage()) }}</span><div class="pagination-links">@if($organizations->previousPageUrl())<a class="pu-button pu-button--secondary pu-button--sm" href="{{ $organizations->previousPageUrl() }}">Sebelumnya</a>@endif @if($organizations->nextPageUrl())<a class="pu-button pu-button--secondary pu-button--sm" href="{{ $organizations->nextPageUrl() }}">Berikutnya</a>@endif</div></div>
    @endif
</x-pesantren-ui::card>
@endsection
