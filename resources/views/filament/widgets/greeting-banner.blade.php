<x-filament-widgets::widget>
    {{-- ===== Banner sambutan — gradient WA-green (light) / TikTok-dark (dark) ===== --}}
    <div class="relative isolate overflow-hidden rounded-2xl px-6 py-5 text-white shadow-sm sm:px-8 sm:py-6
                bg-gradient-to-r from-[#004a38] via-[#008069] to-[#00a884]
                dark:from-[#0f1c14] dark:via-[#161823] dark:to-[#1a243a]">

        {{-- Ornamen bulat samar kanan atas --}}
        <div class="pointer-events-none absolute -right-8 -top-8 h-36 w-36 rounded-full bg-white/10 blur-2xl"></div>
        <div class="pointer-events-none absolute right-32 -bottom-6 h-24 w-24 rounded-full bg-white/5 blur-xl"></div>

        {{-- Siluet masjid dekoratif (latar, samar) — ilustrasi khas pesantren --}}
        <svg class="pointer-events-none absolute -bottom-1 left-1/2 hidden h-20 w-auto -translate-x-1/2 text-white/[0.07] sm:block lg:left-[38%]"
             viewBox="0 0 320 110" fill="currentColor" aria-hidden="true">
            {{-- Menara kiri & kanan + bulan sabit --}}
            <path d="M40 110V46a8 8 0 0 1 16 0v64H40Zm8-72a6 6 0 1 1 0-12 9 9 0 1 0 0 12Z" />
            <path d="M264 110V46a8 8 0 0 1 16 0v64h-16Zm8-72a6 6 0 1 1 0-12 9 9 0 1 0 0 12Z" />
            {{-- Bangunan utama + kubah besar + dua kubah kecil --}}
            <path d="M72 110V72c0-26 19-47 44-52a4 4 0 0 1 0-1 44 44 0 0 1 88 0 4 4 0 0 1 0 1c25 5 44 26 44 52v38H72Z" opacity="0.85" />
            <path d="M160 18a44 44 0 0 1 44 44h-88a44 44 0 0 1 44-44Zm0-14a6 6 0 1 1 0-12 9 9 0 1 0 0 12Z" />
            {{-- Pintu lengkung tengah --}}
            <path d="M148 110V92a12 12 0 0 1 24 0v18h-24Z" fill="#004a38" opacity="0.5" />
        </svg>

        <div class="relative z-10 flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
            {{-- Kiri: sambutan --}}
            <div class="max-w-xl">
                <p class="mb-0.5 text-xs font-medium tracking-widest text-white/60 uppercase">
                    {{ now()->translatedFormat('l, d F Y') }}
                </p>
                <h2 class="text-xl font-bold tracking-tight sm:text-2xl">
                    Assalamu&rsquo;alaikum, <span class="text-white/90">{{ $nama }}</span>
                    <span class="inline-block">&#128075;</span>
                </h2>
                <p class="mt-1 text-sm text-white/75">
                    Semoga semua aktivitas hari ini bernilai ibadah.
                </p>
            </div>

            {{-- ════════════════════════════════════════════════════════════
                 Kanan: JADWAL SHOLAT interaktif (Alpine.js)
                 - Jam saat ini (update tiap detik, waktu perangkat)
                 - Hitung mundur menuju sholat berikutnya
                 - Waktu sholat dihitung dari GPS perangkat; fallback koordinat
                   tenant (default Bandung) bila izin GPS ditolak/tak tersedia.
                 Algoritma = port PrayerTimesService (metode Kemenag).
                 ════════════════════════════════════════════════════════════ --}}
            <div
                x-data="{
                    fallbackLat: {{ $lat }},
                    fallbackLng: {{ $lng }},
                    fallbackTz: {{ $tz }},
                    prayers: [
                        @foreach ($prayers as $p){ key: '{{ $p['key'] }}', label: '{{ $p['label'] }}', time: '{{ $p['time'] }}' },@endforeach
                    ],
                    now: '--:--:--',
                    countdown: '--:--:--',
                    nextKey: '{{ $nextPrayer }}',
                    nextLabel: '',
                    locLabel: 'lokasi default',
                    _timer: null,

                    init() {
                        this.tick();
                        // Guard: cegah interval menumpuk saat navigasi SPA (wire:navigate).
                        if (window.__jadwalSholatTimer) clearInterval(window.__jadwalSholatTimer);
                        window.__jadwalSholatTimer = setInterval(() => this.tick(), 1000);
                        this._timer = window.__jadwalSholatTimer;
                        this.loadCachedGps();
                        this.requestGps();
                    },

                    loadCachedGps() {
                        try {
                            const c = JSON.parse(localStorage.getItem('jadwal_sholat_gps') || 'null');
                            if (c && typeof c.lat === 'number' && typeof c.lng === 'number') {
                                this.applyCoords(c.lat, c.lng);
                            }
                        } catch (e) { /* abaikan */ }
                    },

                    requestGps() {
                        if (! navigator.geolocation) return;
                        navigator.geolocation.getCurrentPosition(
                            (pos) => {
                                const lat = pos.coords.latitude, lng = pos.coords.longitude;
                                try { localStorage.setItem('jadwal_sholat_gps', JSON.stringify({ lat, lng })); } catch (e) {}
                                this.applyCoords(lat, lng);
                            },
                            () => { /* ditolak / gagal → tetap pakai fallback */ },
                            { enableHighAccuracy: false, timeout: 8000, maximumAge: 3600000 }
                        );
                    },

                    applyCoords(lat, lng) {
                        const tz = -new Date().getTimezoneOffset() / 60; // zona perangkat
                        this.prayers = this.calcFive(lat, lng, tz, new Date());
                        this.locLabel = 'lokasi GPS';
                        this.tick();
                    },

                    pad(n) { return String(n).padStart(2, '0'); },

                    tick() {
                        const d = new Date();
                        this.now = this.pad(d.getHours()) + ':' + this.pad(d.getMinutes()) + ':' + this.pad(d.getSeconds());

                        let next = null;
                        for (const p of this.prayers) {
                            const [h, mi] = p.time.split(':').map(Number);
                            const t = new Date(d); t.setHours(h, mi, 0, 0);
                            if (t > d) { next = { p, t }; break; }
                        }
                        if (! next) {
                            const [h, mi] = this.prayers[0].time.split(':').map(Number);
                            const t = new Date(d); t.setDate(t.getDate() + 1); t.setHours(h, mi, 0, 0);
                            next = { p: this.prayers[0], t };
                        }
                        this.nextKey = next.p.key;
                        this.nextLabel = next.p.label;

                        let diff = Math.max(0, Math.floor((next.t - d) / 1000));
                        const hh = Math.floor(diff / 3600); diff %= 3600;
                        const mm = Math.floor(diff / 60); const ss = diff % 60;
                        this.countdown = this.pad(hh) + ':' + this.pad(mm) + ':' + this.pad(ss);
                    },

                    /* Port PrayerTimesService.php → JS (metode Kemenag: Subuh 20°, Isya 18°,
                       Ashar Syafi'i, ihtiyati 2 mnt). */
                    calcFive(lat, lng, tz, date) {
                        const dsin = x => Math.sin(x * Math.PI / 180), dcos = x => Math.cos(x * Math.PI / 180), dtan = x => Math.tan(x * Math.PI / 180);
                        const darcsin = x => Math.asin(x) * 180 / Math.PI, darccos = x => Math.acos(x) * 180 / Math.PI;
                        const darctan2 = (y, x) => Math.atan2(y, x) * 180 / Math.PI, darccot = x => Math.atan2(1, x) * 180 / Math.PI;
                        const fixangle = a => { a -= 360 * Math.floor(a / 360); return a < 0 ? a + 360 : a; };
                        const fixhour = a => { a -= 24 * Math.floor(a / 24); return a < 0 ? a + 24 : a; };
                        const julian = (y, m, dd) => { if (m <= 2) { y -= 1; m += 12; } const A = Math.floor(y / 100), B = 2 - A + Math.floor(A / 4); return Math.floor(365.25 * (y + 4716)) + Math.floor(30.6001 * (m + 1)) + dd + B - 1524.5; };
                        const sunPos = jd => { const D = jd - 2451545.0; const g = fixangle(357.529 + 0.98560028 * D); const q = fixangle(280.459 + 0.98564736 * D); const L = fixangle(q + 1.915 * dsin(g) + 0.020 * dsin(2 * g)); const e = 23.439 - 0.00000036 * D; const RA = darctan2(dcos(e) * dsin(L), dcos(L)) / 15; const decl = darcsin(dsin(e) * dsin(L)); const eqt = q / 15 - fixhour(RA); return [decl, eqt]; };
                        const midDay = (jd, t) => fixhour(12 - sunPos(jd + t)[1]);
                        const sunAngle = (angle, jd, la, t, ccw) => { const decl = sunPos(jd + t)[0]; const noon = midDay(jd, t); let x = (-dsin(angle) - dsin(decl) * dsin(la)) / (dcos(decl) * dcos(la)); x = Math.max(-1, Math.min(1, x)); const tt = (1 / 15) * darccos(x); return noon + (ccw ? -tt : tt); };
                        const asr = (factor, jd, la, t) => { const decl = sunPos(jd + t)[0]; const angle = -darccot(factor + dtan(Math.abs(la - decl))); return sunAngle(angle, jd, la, t, false); };
                        const fmt = t => { t = fixhour(t + 0.5 / 60); const h = Math.floor(t); const mi = Math.floor((t - h) * 60); return String(h).padStart(2, '0') + ':' + String(mi).padStart(2, '0'); };

                        const y = date.getFullYear(), m = date.getMonth() + 1, dd = date.getDate();
                        const jDate = julian(y, m, dd) - lng / (15 * 24);
                        const off = tz - lng / 15, ih = 2 / 60;
                        let fajr = sunAngle(20, jDate, lat, 5 / 24, true) + off + ih;
                        let dhuhr = midDay(jDate, 12 / 24) + off + ih;
                        let ashar = asr(1, jDate, lat, 13 / 24) + off + ih;
                        let maghrib = sunAngle(0.833, jDate, lat, 18 / 24, false) + off + ih;
                        let isya = sunAngle(18, jDate, lat, 18 / 24, false) + off + ih;

                        return [
                            { key: 'subuh', label: 'Subuh', time: fmt(fajr) },
                            { key: 'dzuhur', label: 'Dzuhur', time: fmt(dhuhr) },
                            { key: 'ashar', label: 'Ashar', time: fmt(ashar) },
                            { key: 'maghrib', label: 'Maghrib', time: fmt(maghrib) },
                            { key: 'isya', label: 'Isya', time: fmt(isya) },
                        ];
                    },
                }"
                x-init="init()"
                class="shrink-0 rounded-2xl bg-white/10 p-3 ring-1 ring-inset ring-white/15 backdrop-blur-sm
                       dark:bg-white/[0.06] dark:ring-white/10"
            >
                {{-- Header: judul + label sumber lokasi --}}
                <div class="mb-2 flex items-center justify-between gap-3 px-1">
                    <div class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider text-white/70">
                        <svg viewBox="0 0 16 16" class="h-3.5 w-3.5" fill="currentColor" aria-hidden="true">
                            <path d="M9.4 2.2A5.2 5.2 0 1 0 13 11a4 4 0 1 1-3.6-8.8Z" />
                        </svg>
                        Jadwal Sholat
                    </div>
                    <span class="inline-flex items-center gap-1 rounded-full bg-white/10 px-2 py-0.5 text-[9px] font-medium text-white/60">
                        <svg viewBox="0 0 24 24" class="h-2.5 w-2.5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 21s-6-5.2-6-10a6 6 0 1 1 12 0c0 4.8-6 10-6 10Z" /><circle cx="12" cy="11" r="2" />
                        </svg>
                        <span x-text="locLabel"></span>
                    </span>
                </div>

                {{-- Jam saat ini + hitung mundur --}}
                <div class="mb-2.5 flex items-stretch gap-2 px-1">
                    <div class="flex-1 rounded-xl bg-white/10 px-3 py-1.5">
                        <p class="text-[9px] font-medium uppercase tracking-wider text-white/50">Sekarang</p>
                        <p class="text-lg font-bold tabular-nums leading-tight" x-text="now"></p>
                    </div>
                    <div class="flex-1 rounded-xl bg-white/10 px-3 py-1.5">
                        <p class="text-[9px] font-medium uppercase tracking-wider text-white/50">
                            Menuju <span x-text="nextLabel"></span>
                        </p>
                        <p class="text-lg font-bold tabular-nums leading-tight text-amber-200" x-text="countdown"></p>
                    </div>
                </div>

                {{-- Chips 5 waktu sholat (highlight berikutnya, reaktif) --}}
                <div class="flex items-stretch gap-1.5">
                    <template x-for="p in prayers" :key="p.key">
                        <div
                            class="flex min-w-[3.25rem] flex-col items-center rounded-xl px-2.5 py-2 text-center transition"
                            :class="p.key === nextKey
                                ? 'bg-white text-[#008069] shadow-sm dark:bg-[#00a884] dark:text-white'
                                : 'text-white/85'"
                        >
                            <span class="text-[10px] font-semibold uppercase tracking-wide"
                                  :class="p.key === nextKey ? 'text-[#008069]/80 dark:text-white/90' : 'text-white/60'"
                                  x-text="p.label"></span>
                            <span class="mt-0.5 text-sm font-bold tabular-nums" x-text="p.time"></span>
                        </div>
                    </template>
                </div>

                <p class="mt-1.5 px-1 text-[10px] text-white/45">
                    Perkiraan waktu (metode Kemenag) · izinkan GPS untuk akurasi lokasi
                </p>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
