@php($simple = \App\Support\UiMode::simple())
{{-- Switch mode tampilan — hanya visibilitas menu, bukan izin. --}}
<form method="POST" action="{{ url('/ui-mode/toggle') }}" class="flex items-center">
    @csrf
    <button
        type="submit"
        title="{{ $simple ? 'Tampilkan semua menu modul' : 'Sederhanakan menu (fungsi tetap aktif)' }}"
        style="display:inline-flex;align-items:center;gap:6px;border-radius:9999px;padding:6px 14px;font-size:12px;font-weight:600;border:1px solid {{ $simple ? '#a7f3d0' : '#d6d3d1' }};background:{{ $simple ? '#ecfdf5' : 'transparent' }};color:{{ $simple ? '#047857' : '#57534e' }};cursor:pointer"
    >
        {{ $simple ? '✨ Mode Sederhana' : '🛠 Mode Lengkap' }}
    </button>
</form>
