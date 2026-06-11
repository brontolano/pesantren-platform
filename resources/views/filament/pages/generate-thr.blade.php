<x-filament-panels::page>
    <x-filament::section heading="Tahun &amp; Formula">
        <div class="max-w-xs">
            <x-filament::input.wrapper>
                <x-filament::input type="number" min="2000" max="2100" wire:model.live="tahun" />
            </x-filament::input.wrapper>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Formula tenant: <x-filament::badge color="info">{{ $formula }}</x-filament::badge>
                — diatur via Setting <code>payroll.thr_formula</code>
                (<code>nominal_tetap</code> / <code>kali_honor_pokok</code> / <code>manual</code>).
                Slip THR dibuat <strong>draft</strong> tanpa potongan otomatis; satu THR per pegawai per tahun.
            </p>
        </div>
    </x-filament::section>

    <x-filament::section :heading="'Pratinjau THR ' . $tahun">
        @if (empty($baris))
            <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada pegawai aktif.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left rtl:text-right">
                            <th class="px-3 py-2 font-medium">Pegawai</th>
                            <th class="px-3 py-2 font-medium">Status</th>
                            <th class="px-3 py-2 font-medium text-right">Nominal THR (Rp)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($baris as $b)
                            <tr class="border-b border-gray-100 dark:border-white/5">
                                <td class="px-3 py-2 font-medium">{{ $b['employee']->nama }}</td>
                                <td class="px-3 py-2">
                                    @if ($b['sudah_ada'])
                                        <x-filament::badge color="warning">Sudah ada — dilewati</x-filament::badge>
                                    @elseif ($b['tanpa_akun'])
                                        <x-filament::badge color="danger">Tanpa akun login — dilewati</x-filament::badge>
                                    @else
                                        <x-filament::badge color="success">Akan dibuat</x-filament::badge>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right">
                                    @if ($formula === 'manual' && ! $b['sudah_ada'] && ! $b['tanpa_akun'])
                                        <div class="ms-auto max-w-40">
                                            <x-filament::input.wrapper>
                                                <x-filament::input type="number" min="0" step="1000"
                                                    wire:model.blur="manual.{{ $b['employee']->id }}" />
                                            </x-filament::input.wrapper>
                                        </div>
                                    @else
                                        <span class="font-semibold">{{ number_format($b['nominal'], 0, ',', '.') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
