<div class="space-y-4 text-sm">
    @if ($wajibLengkap)
        <div class="rounded-lg bg-green-50 px-4 py-3 ring-1 ring-green-200 dark:bg-green-500/10 dark:ring-green-500/20">
            <p class="font-semibold text-green-800 dark:text-green-300">Fondasi sudah lengkap ✓</p>
            <p class="text-green-700 dark:text-green-400">Tekan <strong>Selesai &amp; Simpan</strong> untuk menyimpan, lalu lanjut ke <strong>Pusat Setup</strong> mengisi data tiap unit.</p>
        </div>
    @else
        <div class="rounded-lg bg-amber-50 px-4 py-3 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:ring-amber-500/20">
            <p class="font-semibold text-amber-800 dark:text-amber-300">Masih ada langkah wajib</p>
            <p class="text-amber-700 dark:text-amber-400">Lengkapi nama pesantren (dan lembaga + periode akademik bila modul akademik aktif). Anda tetap bisa menyimpan progres yang sudah diisi.</p>
        </div>
    @endif

    @if (! empty($cek))
        <div>
            <p class="mb-2 font-medium text-gray-700 dark:text-gray-300">Cek kesehatan sistem</p>
            <ul class="space-y-1">
                @foreach ($cek as $c)
                    <li class="flex items-start gap-2">
                        @if ($c['ok'])
                            <x-filament::icon icon="heroicon-s-check-circle" class="mt-0.5 h-4 w-4 shrink-0 text-green-600 dark:text-green-400" />
                        @else
                            <x-filament::icon icon="heroicon-s-exclamation-triangle" class="mt-0.5 h-4 w-4 shrink-0 text-amber-500" />
                        @endif
                        <span class="text-gray-700 dark:text-gray-300">{{ $c['label'] }}@if (! $c['ok'] && ! empty($c['detail']))<span class="text-gray-400"> — {{ $c['detail'] }}</span>@endif</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
