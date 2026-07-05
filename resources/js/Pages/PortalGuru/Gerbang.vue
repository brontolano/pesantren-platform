<script setup>
import { Head, router, useForm } from '@inertiajs/vue3'
import { onMounted, ref } from 'vue'
import PortalGuruLayout from '../../Layouts/PortalGuruLayout.vue'
import { PageHeader, SectionCard, UiCard, ListRow, IconCircle, StatusBadge, UiButton, EmptyState, UiIcon } from '@/Shared'

defineOptions({ layout: PortalGuruLayout })

const props = defineProps({
    nis: { type: String, default: '' },
    santriCard: { type: Object, default: null },
    jengukHariIni: { type: Array, default: () => [] },
    walkInBoleh: { type: Boolean, default: true },
    logTerbaru: { type: Array, default: () => [] },
})

// Pola keyboard-wedge: scanner QR mengetik NIS + Enter ke input autofocus.
const scanInput = ref(null)
const nisInput = ref('')

onMounted(() => scanInput.value?.focus())

function scan() {
    if (!nisInput.value.trim()) return
    router.get('/guru/gerbang', { nis: nisInput.value.trim() }, { preserveState: false })
}

function catat(arah) {
    router.post('/guru/gerbang/catat', { santri_id: props.santriCard.id, arah }, {
        preserveScroll: true,
        onSuccess: () => { nisInput.value = ''; scanInput.value?.focus() },
    })
}

function tandaiHadir(id) {
    router.post(`/guru/gerbang/jenguk/${id}/hadir`, {}, { preserveScroll: true })
}

const walkInForm = useForm({ santri_id: null, nama_pengunjung: '', hubungan: 'wali', keperluan: '' })

function simpanWalkIn() {
    walkInForm.santri_id = props.santriCard?.id
    walkInForm.post('/guru/gerbang/jenguk/walk-in', {
        preserveScroll: true,
        onSuccess: () => walkInForm.reset('nama_pengunjung', 'keperluan'),
    })
}

const warnaStatus = {
    hijau: 'border-emerald-400 bg-emerald-50',
    kuning: 'border-amber-400 bg-amber-50',
    merah: 'border-rose-400 bg-rose-50',
}

// Cache offline ringan: fetch daftar izin aktif berkala (TANPA service worker baru).
const izinCache = ref([])
let timer = null
async function muatIzinAktif() {
    try {
        const res = await fetch('/guru/gerbang/izin-aktif', { headers: { Accept: 'application/json' } })
        if (res.ok) {
            const json = await res.json()
            izinCache.value = json.izin ?? []
            localStorage.setItem('gerbang_izin_aktif', JSON.stringify(json))
        }
    } catch {
        const cached = localStorage.getItem('gerbang_izin_aktif')
        if (cached) izinCache.value = JSON.parse(cached).izin ?? []
    }
}
onMounted(() => {
    muatIzinAktif()
    timer = setInterval(muatIzinAktif, 5 * 60 * 1000)
})
</script>

<template>
    <div class="space-y-5">
        <Head><title>Pos Gerbang</title></Head>

        <PageHeader kicker="Mode Keamanan" title="Pos Gerbang" />

        <!-- Input scan (keyboard-wedge) — WAJIB native: ref + autofocus + autocomplete -->
        <form class="flex gap-2" @submit.prevent="scan">
            <input
                ref="scanInput"
                v-model="nisInput"
                type="text"
                inputmode="numeric"
                autocomplete="off"
                placeholder="Scan QR kartu santri / ketik NIS lalu Enter"
                class="min-h-[48px] w-full rounded-2xl bg-white px-4 py-3 text-lg font-medium tabular-nums text-slate-900 ring-1 ring-inset ring-slate-200 transition-all duration-200 placeholder:font-normal placeholder:text-slate-400 hover:ring-slate-300 focus:outline-none focus:ring-2 focus:ring-[var(--wali-primary,#059669)]"
            >
            <UiButton type="submit" variant="primary" size="lg" icon-left="qr">Cari</UiButton>
        </form>

        <!-- Kartu santri: tidak ditemukan -->
        <UiCard v-if="santriCard?.tidak_ditemukan">
            <div class="flex items-start gap-3">
                <IconCircle icon="x-circle" tone="rose" />
                <p class="text-sm text-rose-700">
                    NIS <strong class="tabular-nums">{{ santriCard.nis }}</strong> tidak ditemukan.
                </p>
            </div>
        </UiCard>

        <!-- Kartu santri: hasil scan -->
        <UiCard v-else-if="santriCard" padding="md">
            <div :class="['rounded-2xl border-2 p-4', warnaStatus[santriCard.status]]">
                <div class="flex items-start gap-4">
                    <img
                        :src="santriCard.foto || `https://ui-avatars.com/api/?background=059669&color=fff&size=256&name=${encodeURIComponent(santriCard.nama)}`"
                        :alt="santriCard.nama"
                        class="h-36 w-36 rounded-2xl object-cover shadow-md"
                    >
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-2xl font-bold tracking-tight text-slate-900">{{ santriCard.nama }}</p>
                        <p class="text-sm text-slate-600">NIS <span class="tabular-nums">{{ santriCard.nis }}</span> · Kelas {{ santriCard.kelas }}</p>

                        <div v-if="santriCard.status === 'hijau'" class="mt-2 flex items-center gap-2">
                            <IconCircle icon="check-circle" tone="emerald" size="sm" />
                            <StatusBadge
                                status="aktif"
                                :label="`Izin ${santriCard.izin.jenis_izin} aktif (${santriCard.izin.tanggal_mulai} – ${santriCard.izin.tanggal_selesai})`"
                            />
                        </div>
                        <div v-else-if="santriCard.status === 'kuning'" class="mt-2 flex items-center gap-2">
                            <IconCircle icon="clock" tone="amber" size="sm" />
                            <StatusBadge
                                status="menunggu"
                                :label="`TERLAMBAT KEMBALI — izin habis ${santriCard.izin.tanggal_selesai}`"
                            />
                        </div>
                        <div v-else class="mt-2 flex items-center gap-2">
                            <IconCircle icon="ban" tone="rose" size="sm" />
                            <StatusBadge status="ditolak" label="TANPA IZIN AKTIF" />
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <template v-if="santriCard.status === 'hijau'">
                        <UiButton variant="primary" icon-left="arrow-up-right" @click="catat('keluar')">Catat Keluar</UiButton>
                        <UiButton variant="primary" icon-left="arrow-down-left" @click="catat('masuk')">Catat Masuk &amp; Tandai Kembali</UiButton>
                    </template>
                    <template v-else-if="santriCard.status === 'kuning'">
                        <UiButton variant="primary" icon-left="arrow-down-left" @click="catat('masuk')">Catat Masuk &amp; Tandai Kembali</UiButton>
                    </template>
                    <template v-else>
                        <UiButton variant="danger" icon-left="arrow-up-right" @click="catat('keluar')">Catat Keluar (tanpa izin)</UiButton>
                        <UiButton variant="secondary" icon-left="arrow-down-left" @click="catat('masuk')">Catat Masuk</UiButton>
                    </template>
                </div>
            </div>

            <!-- WP-30: registrasi tamu penjengukan -->
            <div class="mt-5 border-t border-slate-100 pt-4">
                <h3 class="flex items-center gap-2 text-sm font-bold tracking-tight text-slate-700">
                    <UiIcon name="user" class="h-4 w-4 text-violet-600" />
                    Penjengukan Hari Ini
                </h3>

                <div v-if="jengukHariIni.length" class="mt-2 divide-y divide-slate-100 overflow-hidden rounded-2xl ring-1 ring-slate-100">
                    <ListRow
                        v-for="p in jengukHariIni"
                        :key="p.id"
                        :title="p.pengaju ?? 'Wali'"
                        :subtitle="p.catatan || undefined"
                    >
                        <template #leading>
                            <IconCircle icon="user" tone="teal" size="sm" />
                        </template>
                        <template #trailing>
                            <UiButton variant="primary" size="sm" icon-left="check" @click="tandaiHadir(p.id)">Tandai Hadir</UiButton>
                        </template>
                    </ListRow>
                </div>
                <p v-else class="mt-2 text-xs text-slate-500">Tidak ada pengajuan jenguk terkonfirmasi hari ini.</p>

                <form v-if="walkInBoleh" class="mt-3 space-y-2" @submit.prevent="simpanWalkIn">
                    <p class="text-xs font-semibold text-slate-600">Tamu tanpa pengajuan (walk-in):</p>
                    <div class="flex flex-wrap gap-2">
                        <input v-model="walkInForm.nama_pengunjung" required placeholder="Nama pengunjung" class="min-h-[44px] flex-1 rounded-xl bg-white px-3 py-2 text-sm font-medium text-slate-900 ring-1 ring-inset ring-slate-200 transition-all duration-200 placeholder:font-normal placeholder:text-slate-400 hover:ring-slate-300 focus:outline-none focus:ring-2 focus:ring-[var(--wali-primary,#059669)]">
                        <select v-model="walkInForm.hubungan" class="min-h-[44px] rounded-xl bg-white px-3 py-2 text-sm font-medium text-slate-900 ring-1 ring-inset ring-slate-200 transition-all duration-200 hover:ring-slate-300 focus:outline-none focus:ring-2 focus:ring-[var(--wali-primary,#059669)]">
                            <option value="ayah">Ayah</option>
                            <option value="ibu">Ibu</option>
                            <option value="saudara">Saudara</option>
                            <option value="wali">Wali</option>
                        </select>
                        <UiButton type="submit" variant="secondary" size="sm" icon-left="plus">Catat Walk-in</UiButton>
                    </div>
                    <p v-if="walkInForm.errors.nama_pengunjung" class="text-xs font-medium text-rose-600">{{ walkInForm.errors.nama_pengunjung }}</p>
                </form>
                <p v-else class="mt-2 flex items-start gap-1.5 text-xs font-medium text-rose-600">
                    <UiIcon name="ban" class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                    Walk-in tidak diizinkan — tamu wajib mengajukan slot via Portal Wali.
                </p>
            </div>
        </UiCard>

        <!-- Log hari ini -->
        <SectionCard title="Log Gerbang Hari Ini" :flush="logTerbaru.length > 0">
            <div v-if="logTerbaru.length" class="divide-y divide-slate-100">
                <ListRow
                    v-for="log in logTerbaru"
                    :key="log.id"
                    :title="log.nama"
                >
                    <template #leading>
                        <IconCircle
                            :icon="log.arah === 'keluar' ? 'arrow-up-right' : 'arrow-down-left'"
                            :tone="log.arah === 'keluar' ? 'amber' : 'emerald'"
                            size="sm"
                        />
                    </template>
                    <template #trailing>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-semibold tracking-tight" :class="log.arah === 'keluar' ? 'text-amber-700' : 'text-emerald-700'">{{ log.arah }}</span>
                            <span class="text-xs tabular-nums text-slate-400">{{ log.waktu }}</span>
                            <StatusBadge v-if="log.is_tanpa_izin" status="ditolak" label="tanpa izin" size="sm" />
                        </div>
                    </template>
                </ListRow>
            </div>
            <EmptyState
                v-else
                icon="inbox"
                title="Belum ada catatan"
                message="Belum ada catatan keluar-masuk gerbang hari ini."
            />
        </SectionCard>
    </div>
</template>
