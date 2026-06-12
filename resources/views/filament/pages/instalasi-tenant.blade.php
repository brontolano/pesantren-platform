<x-filament-panels::page>
    @if (! $result)
        {{-- Wizard 4 langkah --}}
        <form wire:submit="install">
            {{ $this->form }}
        </form>
    @else
        {{-- Hasil instalasi --}}
        <div class="space-y-6">
            <x-filament::section>
                <x-slot name="heading">
                    {{ $result['all_ok'] ? '✅' : '⚠️' }} Tenant "{{ $result['tenant_name'] }}" terinstal
                </x-slot>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left">
                            <th class="py-2 pr-3">Pemeriksaan</th>
                            <th class="py-2 pr-3">Status</th>
                            <th class="py-2">Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($result['checks'] as $check)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-2 pr-3 font-medium">{{ $check['label'] }}</td>
                                <td class="py-2 pr-3">
                                    @if ($check['ok'])
                                        <span class="text-success-600 font-semibold">✓ OK</span>
                                    @else
                                        <span class="text-danger-600 font-semibold">✗ GAGAL</span>
                                    @endif
                                </td>
                                <td class="py-2 text-gray-500">{{ $check['detail'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @unless ($result['all_ok'])
                    <div class="mt-4">
                        <x-filament::button color="warning" icon="heroicon-o-wrench-screwdriver"
                            wire:click="repair" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="repair">Perbaiki Otomatis (reprovision)</span>
                            <span wire:loading wire:target="repair">Memperbaiki…</span>
                        </x-filament::button>
                        <p class="mt-2 text-xs text-gray-500">
                            Menjalankan ulang provisioning (idempotent — data yang sudah benar tidak disentuh), lalu cek ulang.
                        </p>
                    </div>
                @endunless
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">🔑 Kredensial Admin Pesantren</x-slot>

                @if ($result['password'])
                    <div class="rounded-lg border border-warning-300 bg-warning-50 dark:bg-warning-950 p-4 space-y-2"
                         x-data="{ copied: false,
                                   copy() {
                                       navigator.clipboard.writeText(`URL: {{ $result['admin_url'] }}\nEmail: {{ $result['email'] }}\nPassword: {{ $result['password'] }}`);
                                       this.copied = true; setTimeout(() => this.copied = false, 2000);
                                   } }">
                        <div class="grid gap-1 font-mono text-sm">
                            <div><span class="inline-block w-24 text-gray-500">URL</span> <a href="{{ $result['admin_url'] }}" target="_blank" class="text-primary-600 underline">{{ $result['admin_url'] }}</a></div>
                            <div><span class="inline-block w-24 text-gray-500">Email</span> {{ $result['email'] }}</div>
                            <div><span class="inline-block w-24 text-gray-500">Password</span> <b>{{ $result['password'] }}</b></div>
                        </div>
                        <div class="flex items-center gap-3 pt-2">
                            <x-filament::button size="sm" icon="heroicon-o-clipboard" x-on:click="copy()">
                                <span x-show="!copied">Salin Kredensial</span>
                                <span x-show="copied" x-cloak>✓ Tersalin</span>
                            </x-filament::button>
                            <span class="text-xs text-danger-600 font-semibold">
                                Ditampilkan SEKALI — salin sekarang, berikan ke admin pesantren, minta ganti setelah login pertama.
                            </span>
                        </div>
                    </div>
                @else
                    <p class="text-sm text-gray-600">
                        User <b>{{ $result['email'] }}</b> sudah ada sebelumnya — password lama tetap berlaku.
                        Panel: <a href="{{ $result['admin_url'] }}" target="_blank" class="text-primary-600 underline">{{ $result['admin_url'] }}</a>
                    </p>
                @endif
            </x-filament::section>

            <div>
                <x-filament::button color="gray" icon="heroicon-o-plus" wire:click="resetWizard">
                    Instal Tenant Lain
                </x-filament::button>
            </div>
        </div>
    @endif
</x-filament-panels::page>
