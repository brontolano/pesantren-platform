<x-filament-panels::page>
    <div class="rounded-xl border border-gray-200 bg-white p-4 text-sm text-gray-600 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
        Assalamu'alaikum. Lewat "Wawancara Digital" ini, pesantren menentukan adab dan batasan
        asisten AI — apa yang boleh dijawab, data apa yang boleh dibaca, dan siapa yang boleh memakainya.
        Isian Anda tersimpan otomatis sebagai draf setiap berpindah langkah; perubahan yang menyangkut
        kebijakan sensitif baru berlaku setelah disetujui Kyai/Mudir.
    </div>

    {{-- gap-136: status infrastruktur AI (read-only) — Ollama, antrian, anggaran. --}}
    @php($infra = $this->statusInfra())
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-center gap-2">
            <x-filament::icon icon="heroicon-o-signal" class="h-5 w-5 text-primary-600 dark:text-primary-400" />
            <h2 class="text-base font-semibold text-gray-800 dark:text-gray-100">Status Asisten AI</h2>
        </div>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Ringkasan kesiapan layanan AI saat halaman ini dibuka. Bersifat informasi saja —
            tidak ada tombol yang mengubah pengaturan di sini.
        </p>

        <dl class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            {{-- Provider (Ollama dst) --}}
            @php($providerOk = collect($infra['provider'])->contains(fn ($p) => $p['ok']))
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900/40">
                <dt class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                    <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-full {{ $providerOk ? 'bg-green-500' : 'bg-red-500' }}"></span>
                    Mesin AI (Ollama)
                </dt>
                <dd class="mt-1.5 space-y-1.5 text-xs text-gray-600 dark:text-gray-300">
                    @forelse ($infra['provider'] as $p)
                        <div>
                            <span class="font-medium">{{ ucfirst($p['nama']) }}:</span>
                            {{ $p['ok'] ? 'Terhubung' : 'Tidak terhubung' }} — {{ $p['detail'] }}
                            @if ($p['model'] !== [])
                                <div class="mt-0.5 text-gray-500 dark:text-gray-400">
                                    Model: {{ implode(', ', array_slice($p['model'], 0, 6)) }}@if (count($p['model']) > 6), …@endif
                                </div>
                            @endif
                        </div>
                    @empty
                        <div>Tidak ada provider AI nyata yang dikonfigurasi.</div>
                    @endforelse
                </dd>
            </div>

            {{-- Antrian queue 'ai' --}}
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900/40">
                <dt class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                    <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-full {{ $infra['antrian']['ok'] ? 'bg-green-500' : 'bg-amber-500' }}"></span>
                    Antrian pekerjaan AI
                </dt>
                <dd class="mt-1.5 text-xs text-gray-600 dark:text-gray-300">
                    {{ $infra['antrian']['detail'] }}
                </dd>
            </div>

            {{-- Anggaran kredit AI tenant --}}
            @php($anggaran = $infra['anggaran'])
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900/40">
                <dt class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                    <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-full {{ $anggaran['killSwitch'] ? 'bg-red-500' : ($anggaran['ok'] ? 'bg-green-500' : 'bg-amber-500') }}"></span>
                    Anggaran kredit AI
                </dt>
                <dd class="mt-1.5 text-xs text-gray-600 dark:text-gray-300">
                    {{ $anggaran['detail'] }}
                </dd>
            </div>
        </dl>
    </div>

    {{-- T-A012: tautan ringkasan utk kyai — tampil selama ada pengajuan menunggu. --}}
    @php($tautanKyai = $this->tautanRingkasanKyai())
    @if ($tautanKyai !== null)
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-950"
            x-data="{ tersalin: false, salin() {
                navigator.clipboard.writeText(@js($tautanKyai)).then(() => {
                    this.tersalin = true; setTimeout(() => this.tersalin = false, 2500);
                });
            } }">
            <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">
                Ada pengajuan menunggu persetujuan Kyai/Mudir
            </p>
            <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">
                Salin tautan ringkasan di bawah lalu kirimkan kepada Kyai/Mudir melalui
                WhatsApp Anda. Tautan hanya <em>menampilkan</em> Ringkasan Kebijakan (tanpa
                login dan tanpa tombol persetujuan), berlaku {{ $this->tautanRingkasanJam() }} jam;
                keputusan tetap dilakukan setelah beliau masuk ke panel.
            </p>
            <div class="mt-3 flex flex-wrap items-center gap-3">
                <x-filament::button color="warning" icon="heroicon-o-clipboard-document"
                    x-on:click="salin()">
                    <span x-show="! tersalin">Salin tautan untuk Kyai</span>
                    <span x-show="tersalin" x-cloak>Tersalin — silakan tempel di WhatsApp</span>
                </x-filament::button>
                <a href="{{ $tautanKyai }}" target="_blank" rel="noopener"
                    class="text-sm font-medium text-amber-800 underline dark:text-amber-200">
                    Pratinjau halaman ringkasan
                </a>
            </div>
        </div>
    @endif

    <form wire:submit="ajukan" class="space-y-6">
        {{ $this->form }}
    </form>

    @php($ringkasan = $this->ringkasanKebijakan())
    <div class="space-y-2">
        <h2 class="text-base font-semibold text-gray-800 dark:text-gray-100">Ringkasan Kebijakan</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Disusun otomatis dari isian Anda saat ini — inilah yang akan dibaca Kyai/Mudir saat memberi persetujuan.
        </p>
        @include('ai::filament.partials.ringkasan-kebijakan', ['ringkasan' => $ringkasan])
    </div>
</x-filament-panels::page>
