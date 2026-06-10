@php($simple = \App\Support\UiMode::simple())
{{-- Switch mode tampilan — hanya visibilitas menu, bukan izin. Komponen
     native Filament agar adaptif light/dark & konsisten dengan tema. --}}
<form method="POST" action="{{ url('/ui-mode/toggle') }}" class="flex items-center">
    @csrf
    <x-filament::button
        type="submit"
        size="sm"
        :color="$simple ? 'primary' : 'gray'"
        :icon="$simple ? 'heroicon-m-sparkles' : 'heroicon-m-squares-2x2'"
        :tooltip="$simple ? 'Tampilkan semua fitur (Mode Lengkap)' : 'Sederhanakan menu — fungsi tetap aktif'"
    >
        {{ $simple ? 'Mode Sederhana' : 'Mode Lengkap' }}
    </x-filament::button>
</form>
