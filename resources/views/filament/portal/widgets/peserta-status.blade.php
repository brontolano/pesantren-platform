<x-filament-widgets::widget>
    <x-filament::section>
        @if ($calon)
            <div class="space-y-4">
                <div>
                    <h2 class="text-xl font-bold tracking-tight">
                        Assalamu'alaikum, {{ $calon->nama }}
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Selamat datang di Portal PPDB.
                    </p>
                </div>

                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Nomor Pendaftaran</dt>
                        <dd class="text-lg font-semibold">{{ $calon->nomor_pendaftaran }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Status Pendaftaran</dt>
                        <dd class="mt-1">
                            <x-filament::badge>
                                {{ \Illuminate\Support\Str::headline($calon->status_pendaftaran ?? '-') }}
                            </x-filament::badge>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Program Diminati</dt>
                        <dd class="font-medium">{{ $calon->program_diminati ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">No. WhatsApp</dt>
                        <dd class="font-medium">{{ $calon->no_hp_wali ?? '-' }}</dd>
                    </div>
                </dl>

                <div class="rounded-xl bg-primary-50 p-4 text-sm text-primary-700 dark:bg-primary-400/10 dark:text-primary-300">
                    Informasi jadwal seleksi, pengumuman, dan daftar ulang akan tampil di sini serta
                    dikirim melalui WhatsApp. Tahap melengkapi data &amp; unggah dokumen menyusul.
                </div>
            </div>
        @else
            <p class="text-gray-500 dark:text-gray-400">
                Data pendaftaran tidak ditemukan untuk akun ini. Silakan hubungi panitia PPDB.
            </p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
