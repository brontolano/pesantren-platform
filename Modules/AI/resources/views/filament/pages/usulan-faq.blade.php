<x-filament-panels::page>
    @php($daftar = $this->daftarUsulan())

    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 p-4 dark:border-gray-700">
            <div>
                <h2 class="text-base font-semibold text-gray-800 dark:text-gray-100">Usulan FAQ dari Pertanyaan Pengguna</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Pertanyaan yang sering diajukan (sudah terredaksi, ditambang otomatis setiap pekan).
                    Isi jawaban lalu <strong>Sahkan</strong> agar asisten dapat menjawabnya — tanpa pengesahan,
                    tidak ada yang masuk pengetahuan asisten.
                </p>
            </div>
            <select
                wire:model.live="filterStatus"
                class="rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
            >
                <option value="">Semua status</option>
                <option value="usulan">Usulan</option>
                <option value="dijawab">Dijawab</option>
                <option value="disahkan">Disahkan</option>
                <option value="diabaikan">Diabaikan</option>
            </select>
        </div>

        @if ($daftar->isEmpty())
            <div class="p-6 text-center text-sm text-gray-500 dark:text-gray-400">
                Belum ada usulan FAQ pada filter ini. Usulan baru muncul setelah penambangan mingguan
                menemukan pertanyaan yang berulang.
            </div>
        @else
            <div class="divide-y divide-gray-100 dark:divide-gray-700/50">
                @foreach ($daftar as $usulan)
                    <div class="space-y-3 p-4" wire:key="usulan-faq-{{ $usulan->id }}">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-filament::badge :color="match ($usulan->status) {
                                'usulan' => 'warning',
                                'dijawab' => 'info',
                                'disahkan' => 'success',
                                default => 'gray',
                            }">
                                {{ $usulan->status }}
                            </x-filament::badge>
                            <x-filament::badge color="gray">sering ditanya: {{ $usulan->frekuensi }}×</x-filament::badge>
                            <x-filament::badge color="gray">sumber: {{ $usulan->sumber }}</x-filament::badge>
                            <span class="ms-auto text-xs text-gray-500 dark:text-gray-400">
                                Periode {{ $usulan->dibuat_periode?->translatedFormat('F Y') }}
                            </span>
                        </div>

                        <p class="text-sm font-medium text-gray-800 dark:text-gray-100">
                            {{ $usulan->pertanyaan_perwakilan }}
                        </p>

                        @if (! empty($usulan->variasi))
                            <details class="text-sm">
                                <summary class="cursor-pointer text-xs font-medium text-primary-600 dark:text-primary-400">
                                    Lihat {{ count($usulan->variasi) }} variasi pertanyaan serupa
                                </summary>
                                <ul class="mt-2 list-disc space-y-1 pl-5 text-gray-600 dark:text-gray-300">
                                    @foreach ($usulan->variasi as $variasi)
                                        <li>{{ $variasi }}</li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif

                        @if ($usulan->status === 'disahkan')
                            <div class="rounded-lg bg-gray-50 p-3 text-sm text-gray-700 dark:bg-gray-900/40 dark:text-gray-200">
                                <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-400">Jawaban tersahkan</span>
                                {{ $usulan->jawaban }}
                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    Disahkan {{ $usulan->disahkan_pada?->translatedFormat('d F Y H:i') }}
                                    — masuk Sumber Pengetahuan (kategori FAQ).
                                </p>
                            </div>
                        @elseif (in_array($usulan->status, ['usulan', 'dijawab'], true))
                            @if ($usulan->jawaban)
                                <div class="rounded-lg bg-gray-50 p-3 text-sm text-gray-700 dark:bg-gray-900/40 dark:text-gray-200">
                                    <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-400">Jawaban tersimpan (belum disahkan)</span>
                                    {{ $usulan->jawaban }}
                                </div>
                            @endif

                            <div class="space-y-2">
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300" for="jawab-faq-{{ $usulan->id }}">
                                    {{ $usulan->jawaban ? 'Perbarui jawaban' : 'Jawaban FAQ' }}
                                </label>
                                <textarea
                                    id="jawab-faq-{{ $usulan->id }}"
                                    wire:model="jawaban.{{ $usulan->id }}"
                                    rows="2"
                                    class="w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                                    placeholder="Tulis jawaban resmi pesantren dengan bahasa yang santun…"
                                ></textarea>
                                <div class="flex flex-wrap justify-end gap-2">
                                    <x-filament::button size="sm" color="gray" icon="heroicon-o-x-circle"
                                        wire:click="abaikan({{ $usulan->id }})"
                                        wire:confirm="Abaikan usulan ini? Penambang tidak akan mengusulkannya kembali.">
                                        Abaikan
                                    </x-filament::button>
                                    <x-filament::button size="sm" color="info" icon="heroicon-o-pencil-square"
                                        wire:click="simpanJawaban({{ $usulan->id }})">
                                        Simpan jawaban
                                    </x-filament::button>
                                    <x-filament::button size="sm" color="success" icon="heroicon-o-check-badge"
                                        wire:click="sahkan({{ $usulan->id }})"
                                        wire:confirm="Sahkan FAQ ini? Teksnya akan masuk Sumber Pengetahuan dan dapat dijawab asisten.">
                                        Sahkan ke Pengetahuan
                                    </x-filament::button>
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
