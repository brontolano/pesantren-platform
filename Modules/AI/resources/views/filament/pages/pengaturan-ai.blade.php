<x-filament-panels::page>
    <div class="rounded-xl border border-gray-200 bg-white p-4 text-sm text-gray-600 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
        Assalamu'alaikum. Lewat "Wawancara Digital" ini, pesantren menentukan adab dan batasan
        asisten AI — apa yang boleh dijawab, data apa yang boleh dibaca, dan siapa yang boleh memakainya.
        Isian Anda tersimpan otomatis sebagai draf setiap berpindah langkah; perubahan yang menyangkut
        kebijakan sensitif baru berlaku setelah disetujui Kyai/Mudir.
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
