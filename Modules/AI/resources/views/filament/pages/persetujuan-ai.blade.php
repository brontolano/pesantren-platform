<x-filament-panels::page>
    @php($daftar = $this->approvalMenunggu())

    @if ($daftar->isEmpty())
        <div class="rounded-xl border border-gray-200 bg-white p-6 text-center text-sm text-gray-500 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
            Alhamdulillah, tidak ada pengajuan kebijakan AI yang menunggu keputusan saat ini.
        </div>
    @endif

    @foreach ($daftar as $approval)
        <div class="space-y-4 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800" wire:key="approval-{{ $approval->id }}">
            <div>
                <h2 class="text-base font-semibold text-gray-800 dark:text-gray-100">
                    Pengajuan kebijakan versi {{ $approval->setting?->versi }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Diajukan {{ $approval->created_at?->translatedFormat('d F Y H:i') }}.
                    {{ $approval->setting?->ringkasan_perubahan }}
                </p>
            </div>

            @include('ai::filament.partials.ringkasan-kebijakan', [
                'ringkasan' => $this->ringkasanUntuk($approval),
            ])

            <div class="space-y-3">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200" for="alasan-{{ $approval->id }}">
                    Catatan / alasan keputusan (dianjurkan diisi bila menolak)
                </label>
                <textarea
                    id="alasan-{{ $approval->id }}"
                    wire:model="alasan.{{ $approval->id }}"
                    rows="2"
                    class="w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                    placeholder="Contoh: Mohon batas jawaban keagamaan dikaji ulang bersama Dewan Ustadz."
                ></textarea>

                <div class="flex flex-wrap justify-end gap-3">
                    <x-filament::button color="danger" icon="heroicon-o-x-circle"
                        wire:click="tolak({{ $approval->id }})">
                        Tolak — kembalikan sebagai draf
                    </x-filament::button>
                    <x-filament::button color="success" icon="heroicon-o-check-circle"
                        wire:click="setujui({{ $approval->id }})">
                        Setujui — berlakukan kebijakan ini
                    </x-filament::button>
                </div>
            </div>
        </div>
    @endforeach
</x-filament-panels::page>
