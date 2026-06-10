<x-filament-panels::page>
    <div class="rounded-xl border border-gray-200 bg-white p-4 text-sm text-gray-600 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
        Assalamu'alaikum. Lewat "Wawancara Digital" ini, pesantren menentukan adab dan batasan
        asisten AI — apa yang boleh dijawab, data apa yang boleh dibaca, dan siapa yang boleh memakainya.
        Isian Anda tersimpan otomatis sebagai draf setiap berpindah langkah; perubahan yang menyangkut
        kebijakan sensitif baru berlaku setelah disetujui Kyai/Mudir.
    </div>

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
