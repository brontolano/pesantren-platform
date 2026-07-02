<x-filament-panels::page>
    <div class="grid gap-6 lg:grid-cols-3">
        {{-- ── Kolom kiri: cari / daftar baru / antrian ── --}}
        <div class="space-y-6">
            <x-filament::section heading="Panggil Santri">
                <x-filament::input.wrapper>
                    <x-filament::input type="text" wire:model.live.debounce.400ms="cari" placeholder="Cari NIS / nama…" />
                </x-filament::input.wrapper>
                <ul class="mt-2 divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($this->hasilCari as $s)
                        <li>
                            <button type="button" wire:click="pilih({{ $s->id }})"
                                class="w-full px-2 py-2 text-left text-sm hover:bg-gray-50 dark:hover:bg-white/5">
                                <span class="font-medium">{{ $s->nama }}</span>
                                <span class="text-gray-500"> — {{ $s->nis }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </x-filament::section>

            <x-filament::section heading="Santri Baru" description="NIS berikutnya: {{ $this->previewNis }}">
                <div class="space-y-3">
                    <x-filament::input.wrapper>
                        <x-filament::input type="text" wire:model="baruNama" placeholder="Nama lengkap" />
                    </x-filament::input.wrapper>
                    <div class="grid grid-cols-2 gap-3">
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model="baruJk">
                                <option value="L">Laki-laki</option>
                                <option value="P">Perempuan</option>
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                        <x-filament::input.wrapper>
                            <x-filament::input type="date" wire:model="baruTanggalLahir" />
                        </x-filament::input.wrapper>
                    </div>
                    @error('baruNama') <p class="text-xs text-danger-600">{{ $message }}</p> @enderror
                    @error('baruTanggalLahir') <p class="text-xs text-danger-600">{{ $message }}</p> @enderror
                    <x-filament::button wire:click="buatBaru" icon="heroicon-o-user-plus" class="w-full">
                        Terbitkan NIS & Mulai
                    </x-filament::button>
                </div>
            </x-filament::section>

            <x-filament::section heading="Antrian hari ini">
                <ul class="space-y-1 text-sm">
                    @forelse ($this->antrianHariIni as $s)
                        <li class="flex items-center justify-between">
                            <button type="button" wire:click="pilih({{ $s->id }})" class="text-left hover:underline">{{ $s->nama }}</button>
                            <x-filament::badge :color="$s->selesai_count >= 6 ? 'success' : 'warning'">{{ $s->selesai_count }}/7</x-filament::badge>
                        </li>
                    @empty
                        <li class="text-gray-400">Belum ada.</li>
                    @endforelse
                </ul>
            </x-filament::section>
        </div>

        {{-- ── Kolom tengah+kanan: santri aktif + checklist ── --}}
        <div class="space-y-6 lg:col-span-2">
            @if (! $this->santri)
                <x-filament::section>
                    <p class="py-10 text-center text-gray-500">Cari santri atau daftarkan santri baru untuk memulai.</p>
                </x-filament::section>
            @else
                @php($st = $this->statusSteps)
                @php($badge = fn ($k) => ($st[$k] ?? 'pending') === 'done' ? 'success' : (($st[$k] ?? 'pending') === 'skipped' ? 'gray' : 'warning'))

                <x-filament::section>
                    <div class="flex items-center gap-4">
                        {{-- Privasi foto putri (#340): foto tersimpan hanya tampil bila petugas berhak
                             atau foto baru dijepret sesi ini; selain itu avatar inisial. --}}
                        <img src="{{ ($this->santri->photo_url && $this->fotoLoketBolehTampil) ? url('/_tenant-storage/'.$this->santri->photo_url) : $this->santri->avatarInisialUrl() }}"
                            class="h-20 w-16 rounded-xl object-cover" alt="foto">
                        <div>
                            <h2 class="text-lg font-bold">{{ $this->santri->nama }}</h2>
                            <p class="text-sm text-gray-500">NIS {{ $this->santri->nis }} · {{ $this->santri->status }}
                                @if ($this->santri->nfc_uid) · 💳 {{ substr($this->santri->nfc_uid, 0, 8) }}… @endif
                            </p>
                            <p class="mt-1 text-xs text-gray-400">
                                Kelengkapan: {{ collect($st)->filter(fn ($v) => $v === 'done')->count() }}/{{ count($st) }} langkah
                            </p>
                        </div>
                    </div>
                </x-filament::section>

                <div class="grid gap-6 md:grid-cols-2">
                    {{-- FOTO --}}
                    <x-filament::section>
                        <x-slot name="heading">📷 Foto <x-filament::badge :color="$badge('foto')">{{ $st['foto'] }}</x-filament::badge></x-slot>
                        <div x-data="kameraStasiun()" class="space-y-2">
                            <video x-ref="video" x-show="aktif" class="w-full rounded-xl bg-black" playsinline muted></video>
                            <canvas x-ref="canvas" class="hidden"></canvas>
                            <div class="flex gap-2">
                                <x-filament::button x-show="!aktif" color="gray" icon="heroicon-o-video-camera" @click="mulai()">Buka Kamera</x-filament::button>
                                <x-filament::button x-show="aktif" icon="heroicon-o-camera" @click="jepret()">Jepret & Simpan</x-filament::button>
                                <x-filament::button x-show="aktif" color="gray" @click="stop()">Tutup</x-filament::button>
                            </div>
                            <p x-text="pesan" class="text-xs text-gray-500"></p>
                        </div>
                    </x-filament::section>

                    {{-- TANDA TANGAN --}}
                    <x-filament::section>
                        <x-slot name="heading">✍️ Tanda Tangan <x-filament::badge :color="$badge('ttd')">{{ $st['ttd'] }}</x-filament::badge></x-slot>
                        <div x-data="ttdStasiun()" class="space-y-2">
                            <canvas x-ref="pad" class="h-40 w-full touch-none rounded-xl border border-gray-300 bg-white"
                                @pointerdown="mulaiGores($event)" @pointermove="gores($event)" @pointerup="selesaiGores()" @pointerleave="selesaiGores()"></canvas>
                            <div class="flex gap-2">
                                <x-filament::button icon="heroicon-o-check" @click="simpan()">Simpan TTD</x-filament::button>
                                <x-filament::button color="gray" @click="bersih()">Ulangi</x-filament::button>
                            </div>
                        </div>
                    </x-filament::section>

                    {{-- NFC --}}
                    <x-filament::section>
                        <x-slot name="heading">💳 Kartu NFC <x-filament::badge :color="$badge('nfc')">{{ $st['nfc'] }}</x-filament::badge></x-slot>
                        <form wire:submit="tautkanNfc" class="space-y-2">
                            <x-filament::input.wrapper>
                                <x-filament::input type="text" wire:model="nfcUid" placeholder="Tempelkan kartu ke reader… (UID terketik otomatis)" autocomplete="off" />
                            </x-filament::input.wrapper>
                            <div class="flex gap-2">
                                <x-filament::button type="submit" icon="heroicon-o-link">Tautkan</x-filament::button>
                                <x-filament::button color="gray" wire:click="lewati('nfc')">Lewati</x-filament::button>
                            </div>
                        </form>
                    </x-filament::section>

                    {{-- PIN --}}
                    <x-filament::section>
                        <x-slot name="heading">🔑 PIN Login <x-filament::badge :color="$badge('pin_qr')">{{ $st['pin_qr'] }}</x-filament::badge></x-slot>
                        <form wire:submit="setPin" class="space-y-2">
                            <x-filament::input.wrapper>
                                <x-filament::input type="text" wire:model="pin" maxlength="6" placeholder="PIN 6 angka" autocomplete="off" />
                            </x-filament::input.wrapper>
                            @error('pin') <p class="text-xs text-danger-600">{{ $message }}</p> @enderror
                            <x-filament::button type="submit" icon="heroicon-o-key">Simpan PIN</x-filament::button>
                            <p class="text-xs text-gray-400">Santri login App Santri dgn NIS + PIN ini.</p>
                        </form>
                    </x-filament::section>

                    {{-- SAMPEL TULISAN --}}
                    <x-filament::section>
                        <x-slot name="heading">📝 Tulisan Arab <x-filament::badge :color="$badge('tulisan_arab')">{{ $st['tulisan_arab'] }}</x-filament::badge></x-slot>
                        <div x-data="sampelStasiun('arab')" class="space-y-2">
                            <input type="file" accept="image/*" capture="environment" x-ref="file" @change="unggah()" class="block w-full text-sm">
                            <p class="text-xs text-gray-400">Foto formulir tulisan Arab santri (1-5 paragraf).</p>
                            <x-filament::button color="gray" size="sm" wire:click="lewati('tulisan_arab')">Lewati</x-filament::button>
                        </div>
                    </x-filament::section>

                    <x-filament::section>
                        <x-slot name="heading">📝 Tulisan Indonesia <x-filament::badge :color="$badge('tulisan_latin')">{{ $st['tulisan_latin'] }}</x-filament::badge></x-slot>
                        <div x-data="sampelStasiun('latin')" class="space-y-2">
                            <input type="file" accept="image/*" capture="environment" x-ref="file" @change="unggah()" class="block w-full text-sm">
                            <p class="text-xs text-gray-400">Foto formulir tulisan Indonesia santri.</p>
                            <x-filament::button color="gray" size="sm" wire:click="lewati('tulisan_latin')">Lewati</x-filament::button>
                        </div>
                    </x-filament::section>
                </div>

                <x-filament::section>
                    <div class="flex flex-wrap items-center gap-3">
                        <x-filament::button wire:click="cetakKartu" icon="heroicon-o-printer">
                            Cetak Kartu Santri (QR)
                            <x-filament::badge :color="$badge('cetak_kartu')" class="ms-2">{{ $st['cetak_kartu'] }}</x-filament::badge>
                        </x-filament::button>
                        <span class="text-xs text-gray-400">Sidik jari: menyusul saat perangkat tersedia (lihat Pengaturan App Santri).</span>
                    </div>
                </x-filament::section>
            @endif
        </div>
    </div>

    <script>
        function kameraStasiun() {
            return {
                aktif: false, pesan: '', stream: null,
                async mulai() {
                    try {
                        this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: 1280 } });
                        this.$refs.video.srcObject = this.stream;
                        await this.$refs.video.play();
                        this.aktif = true; this.pesan = '';
                    } catch (e) { this.pesan = 'Kamera tidak tersedia: ' + e.message; }
                },
                jepret() {
                    const v = this.$refs.video, c = this.$refs.canvas;
                    // Crop tengah rasio 3:4 (foto identitas).
                    const h = v.videoHeight, w = Math.min(v.videoWidth, h * 0.75);
                    c.width = 900; c.height = 1200;
                    c.getContext('2d').drawImage(v, (v.videoWidth - w) / 2, 0, w, h, 0, 0, 900, 1200);
                    this.$wire.call('simpanFoto', c.toDataURL('image/jpeg', 0.85));
                    this.stop();
                },
                stop() { this.aktif = false; this.stream?.getTracks().forEach(t => t.stop()); this.stream = null; },
            };
        }

        function ttdStasiun() {
            return {
                gambar: false, ada: false, ctx: null, paths: [],
                init() {
                    const c = this.$refs.pad;
                    c.width = c.offsetWidth * 2; c.height = c.offsetHeight * 2;
                    this.ctx = c.getContext('2d');
                    this.ctx.scale(2, 2); this.ctx.lineWidth = 2; this.ctx.lineCap = 'round'; this.ctx.strokeStyle = '#111';
                },
                pos(e) { const r = this.$refs.pad.getBoundingClientRect(); return [e.clientX - r.left, e.clientY - r.top]; },
                mulaiGores(e) { this.gambar = true; this.ada = true; const [x, y] = this.pos(e); this.ctx.beginPath(); this.ctx.moveTo(x, y); this.paths.push([[x, y]]); },
                gores(e) { if (!this.gambar) return; const [x, y] = this.pos(e); this.ctx.lineTo(x, y); this.ctx.stroke(); this.paths.at(-1).push([x, y]); },
                selesaiGores() { this.gambar = false; },
                bersih() { this.init(); this.ada = false; this.paths = []; },
                simpan() {
                    if (!this.ada) return;
                    const c = this.$refs.pad;
                    const d = this.paths.map(p => 'M' + p.map(([x, y]) => x.toFixed(1) + ' ' + y.toFixed(1)).join(' L ')).join(' ');
                    const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${c.offsetWidth} ${c.offsetHeight}"><path d="${d}" fill="none" stroke="#111" stroke-width="2" stroke-linecap="round"/></svg>`;
                    this.$wire.call('simpanTtd', svg, c.toDataURL('image/png'));
                    this.bersih();
                },
            };
        }

        function sampelStasiun(script) {
            return {
                unggah() {
                    const f = this.$refs.file.files[0];
                    if (!f) return;
                    const r = new FileReader();
                    r.onload = () => this.$wire.call('simpanSampel', script, r.result);
                    r.readAsDataURL(f);
                    this.$refs.file.value = '';
                },
            };
        }
    </script>
</x-filament-panels::page>
