<script setup>
import { Head } from '@inertiajs/vue3'
import { computed, onMounted, reactive, ref } from 'vue'
import { enqueue, flush, gagalItems, pendingCount, uuid } from '@/keuangan-pwa/offline-sync.js'
import { AppShell, PageHeader, UiCard, UiButton, UiInput, StatusBadge, IconCircle, MoneyText, EmptyState } from '@/Shared'

// WP-32: Setor Keliling — petugas keliling kamar mencatat setoran tabungan
// sebagai DRAF (saldo tersentuh hanya setelah bendahara menyetujui).
// Offline-first: gagal kirim → antrian IndexedDB (pola offline-sync.js),
// idempoten via client_ref per entri.

defineProps({ navigation: { type: Array, default: () => [] } })

const blok = ref([])
const kamarTerpilih = ref(null)
const santri = ref([])
const nominal = reactive({}) // santri_id → nominal
const catatan = reactive({})
const status = ref('')
const belumSinkron = ref(0)
const gagal = ref([])
const sesi = ref(null)
const memuat = ref(false)

const sesiDraf = computed(() => !sesi.value || sesi.value.status === 'draf')

async function muatBlok() {
    try {
        const res = await fetch('/api/keuangan/setor-keliling/kamar', { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        if (res.ok) blok.value = (await res.json()).blok
    } catch (e) {
        status.value = 'Offline — daftar kamar butuh koneksi pertama kali.'
    }
}

async function muatSesi() {
    try {
        const res = await fetch('/api/keuangan/setor-keliling/sesi', { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        if (res.ok) sesi.value = (await res.json()).sesi
    } catch (e) { /* offline: biarkan */ }
}

async function pilihKamar(k) {
    kamarTerpilih.value = k
    santri.value = []
    memuat.value = true
    try {
        const res = await fetch(`/api/keuangan/setor-keliling/kamar/${k.id}/santri`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        if (res.ok) santri.value = (await res.json()).santri
    } catch (e) {
        status.value = 'Offline — daftar santri butuh koneksi.'
    } finally {
        memuat.value = false
    }
}

async function refreshBadge() {
    belumSinkron.value = await pendingCount()
    gagal.value = await gagalItems()
}

async function setor(s) {
    const n = parseFloat(nominal[s.id])
    if (!n || n <= 0) {
        status.value = 'Isi nominal lebih dari 0 untuk ' + s.nama + '.'
        return
    }
    const payload = {
        client_ref: uuid(),
        santri_id: s.id,
        nominal: n,
        catatan: catatan[s.id] || null,
        lokasi: kamarTerpilih.value ? `Kamar ${kamarTerpilih.value.nomor_kamar}` : null,
    }
    try {
        const res = await fetch('/api/keuangan/setor-keliling/setoran', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        })
        if (res.ok) {
            status.value = `Tercatat (draf): ${s.nama} — Rp ${n.toLocaleString('id-ID')}.`
            nominal[s.id] = ''
            catatan[s.id] = ''
            await muatSesi()
        } else {
            const err = await res.json().catch(() => ({}))
            status.value = 'Gagal: ' + (err.message || res.status)
        }
    } catch (e) {
        await enqueue('/api/keuangan/setor-keliling/setoran', payload)
        nominal[s.id] = ''
        catatan[s.id] = ''
        status.value = 'Tidak ada koneksi — setoran masuk antrian luring.'
    }
    await refreshBadge()
}

async function ajukanSesi() {
    if (belumSinkron.value > 0) {
        status.value = 'Masih ada setoran belum tersinkron — sambungkan internet dulu.'
        return
    }
    try {
        const res = await fetch('/api/keuangan/setor-keliling/ajukan', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            credentials: 'same-origin',
            body: '{}',
        })
        const data = await res.json().catch(() => ({}))
        status.value = res.ok
            ? `Sesi diajukan ke bendahara — total Rp ${Number(data.total).toLocaleString('id-ID')}.`
            : 'Gagal: ' + (data.message || res.status)
        await muatSesi()
    } catch (e) {
        status.value = 'Tidak ada koneksi — coba ajukan lagi saat online.'
    }
}

async function sinkronUlang() {
    await flush(document.querySelector('meta[name=csrf-token]')?.content)
    await refreshBadge()
    await muatSesi()
}

window.addEventListener('online', sinkronUlang)

onMounted(async () => {
    await Promise.all([muatBlok(), muatSesi(), refreshBadge()])
})
</script>

<template>
    <Head title="Setor Keliling" />

    <AppShell :nav-items="navigation" brand-name="Keuangan Lapangan">
        <div class="space-y-4">
            <PageHeader kicker="Tabungan" title="Setor Keliling" subtitle="Catat setoran keliling kamar (draf)">
                <template #right>
                    <UiButton
                        v-if="belumSinkron > 0"
                        variant="secondary" size="sm" icon-left="wifi-off"
                        @click="sinkronUlang"
                    >
                        {{ belumSinkron }} antri
                    </UiButton>
                </template>
            </PageHeader>

            <!-- Ringkasan sesi -->
            <UiCard v-if="sesi" padding="md">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-slate-500">Sesi hari ini · {{ sesi.jumlah_item }} setoran</p>
                        <MoneyText :amount="sesi.total" size="lg" tone="emerald" class="mt-0.5 block" />
                        <StatusBadge
                            :status="sesi.status === 'draf' ? 'info' : 'aktif'"
                            :label="sesi.status === 'draf' ? 'Draf' : 'Diajukan'"
                            size="sm" class="mt-1.5"
                        />
                    </div>
                    <UiButton
                        v-if="sesi.status === 'draf' && sesi.jumlah_item > 0"
                        variant="primary" size="sm" icon-left="check"
                        @click="ajukanSesi"
                    >
                        Ajukan
                    </UiButton>
                </div>
            </UiCard>

            <!-- Setoran luring ditolak server -->
            <div v-if="gagal.length" class="rounded-2xl bg-rose-50 p-4 text-xs text-rose-800 ring-1 ring-inset ring-rose-100">
                <p class="font-jost font-bold">{{ gagal.length }} setoran luring DITOLAK server (tak diulang otomatis):</p>
                <p v-for="g in gagal" :key="g.client_ref" class="mt-1 tabular-nums">
                    Rp {{ Number(g.payload?.nominal || 0).toLocaleString('id-ID') }} — {{ g.error }}
                </p>
                <p class="mt-1.5">Catat ulang setoran tersebut secara manual.</p>
            </div>

            <p v-if="!sesiDraf" class="rounded-2xl bg-blue-50 px-4 py-3 text-sm font-medium text-blue-700 ring-1 ring-inset ring-blue-100">
                Sesi sudah diajukan — setoran baru masuk sesi berikutnya setelah bendahara memproses.
            </p>

            <!-- Pilih kamar -->
            <div v-if="!kamarTerpilih" class="space-y-4">
                <EmptyState v-if="!blok.length" icon="home" title="Belum ada data kamar" message="Data blok/kamar asrama belum tersedia." />
                <div v-for="b in blok" :key="b.id" class="space-y-2">
                    <h2 class="font-jost text-sm font-bold text-slate-700">{{ b.nama_blok }}</h2>
                    <div class="grid grid-cols-3 gap-2">
                        <button
                            v-for="k in b.kamar" :key="k.id"
                            type="button"
                            class="rounded-2xl bg-white p-3 text-center ring-1 ring-slate-100 shadow-soft transition-all duration-200 active:scale-[.96]"
                            @click="pilihKamar(k)"
                        >
                            <div class="font-jost text-base font-bold text-slate-900">{{ k.nomor_kamar }}</div>
                            <div class="text-[11px] text-slate-500">{{ k.jumlah_santri }} santri</div>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Daftar santri per kamar -->
            <div v-else class="space-y-3">
                <UiButton variant="ghost" size="sm" icon-left="arrow-left" @click="kamarTerpilih = null; santri = []">
                    Pilih kamar lain
                </UiButton>
                <h2 class="font-jost text-sm font-bold text-slate-700">Kamar {{ kamarTerpilih.nomor_kamar }}</h2>

                <p v-if="memuat" class="text-sm text-slate-500">Memuat santri…</p>
                <EmptyState v-else-if="!santri.length" icon="user" title="Kamar kosong" message="Tidak ada penghuni aktif di kamar ini." />

                <UiCard v-for="s in santri" :key="s.id" padding="md">
                    <div class="flex items-center gap-3">
                        <IconCircle icon="user" tone="emerald" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-jost text-[15px] font-bold text-slate-900">{{ s.nama }}</p>
                            <p class="text-xs text-slate-500">NIS {{ s.nis }}</p>
                        </div>
                    </div>
                    <div class="mt-3 flex gap-2">
                        <UiInput v-model="nominal[s.id]" type="number" inputmode="numeric" :min="1" placeholder="Nominal setoran" />
                        <UiButton variant="primary" icon-left="plus" @click="setor(s)">Setor</UiButton>
                    </div>
                    <UiInput v-model="catatan[s.id]" placeholder="Catatan (opsional)" class="mt-2" />
                </UiCard>
            </div>
        </div>

        <!-- Toast status (di atas bottom-nav) -->
        <Transition name="toast-pop">
            <p
                v-if="status"
                class="fixed inset-x-0 bottom-28 z-40 mx-auto w-full max-w-md px-6"
            >
                <span class="block rounded-2xl bg-slate-900/90 px-4 py-3 text-sm font-medium text-white shadow-soft-lg backdrop-blur">
                    {{ status }}
                </span>
            </p>
        </Transition>
    </AppShell>
</template>

<style scoped>
.toast-pop-enter-active { transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
.toast-pop-leave-active { transition: all 0.2s ease-in; }
.toast-pop-enter-from, .toast-pop-leave-to { opacity: 0; transform: translateY(12px); }
</style>
