<div class="space-y-3 text-sm">
    <p>Berikut modul yang <strong>aktif</strong> untuk pondok Anda saat ini. Setelah fondasi ini selesai, Anda bisa <strong>memasang atau menonaktifkan fitur sendiri</strong> kapan saja lewat halaman <strong>Pusat Modul</strong> — tak perlu menghubungi siapa pun.</p>

    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
        @foreach ($modul as $m)
            <div class="flex items-center gap-2 rounded-lg px-3 py-2 ring-1
                        {{ $m['aktif'] ? 'bg-green-50 ring-green-200 dark:bg-green-500/10 dark:ring-green-500/20' : 'bg-gray-50 ring-gray-200 dark:bg-white/5 dark:ring-white/10' }}">
                @if ($m['aktif'])
                    <x-filament::icon icon="heroicon-s-check-circle" class="h-4 w-4 text-green-600 dark:text-green-400" />
                @else
                    <x-filament::icon icon="heroicon-o-minus-circle" class="h-4 w-4 text-gray-400" />
                @endif
                <span class="{{ $m['aktif'] ? 'text-gray-800 dark:text-gray-100' : 'text-gray-400' }}">
                    {{ $m['label'] }}@if ($m['inti'])&nbsp;🔒@endif
                </span>
            </div>
        @endforeach
    </div>

    <p class="text-xs text-gray-500">🔒 = modul inti (selalu aktif, tak bisa dimatikan). Langkah ini hanya informasi — fitur tambahan Anda atur sendiri nanti di Pusat Modul.</p>
</div>
