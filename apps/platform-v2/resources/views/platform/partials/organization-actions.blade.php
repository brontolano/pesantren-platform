@if(in_array($organization->setup_state,['draft','failed','pending','running'],true))
    <form method="post" action="{{ route('platform.organizations.setup',$organization) }}">@csrf<x-pesantren-ui::button type="submit" size="sm">{{ $organization->setup_state==='failed'?'Lanjutkan setup':'Siapkan tenant' }}</x-pesantren-ui::button></form>
@endif
@if($organization->setup_state==='ready')
    <form method="post" action="{{ route('platform.organizations.check',$organization) }}">@csrf<x-pesantren-ui::button type="submit" variant="secondary" size="sm">Cek teknis</x-pesantren-ui::button></form>
    <form method="post" action="{{ route('platform.organizations.service-state',$organization) }}">@csrf<select class="pu-input" aria-label="Status layanan {{ $organization->name }}" name="service_state" onchange="this.form.submit()">@foreach(['inactive'=>'Nonaktif','active'=>'Aktif','suspended'=>'Ditangguhkan'] as $key=>$label)<option value="{{ $key }}" @selected($organization->service_state===$key)>{{ $label }}</option>@endforeach</select><noscript><x-pesantren-ui::button type="submit" variant="secondary" size="sm">Simpan status</x-pesantren-ui::button></noscript></form>
@endif
