<script setup>
import { Head, usePage } from '@inertiajs/vue3'
import { computed, ref, onMounted } from 'vue'
import {
    AppShell, HeroHeader, StatCard, SectionCard, ListRow, EmptyState,
    LoadingState, BottomSheet, UiButton, UiField, UiInput, MoneyText, IconCircle,
} from '@/Shared'

defineProps({ userName: String })

const page = usePage()
const branding = computed(() => page.props.branding ?? {})
const brandName = computed(() => branding.value.name || 'Pesantren')

const data = ref(null)
const error = ref('')
const loading = ref(true)
const busy = ref(false)

// BottomSheet approval
const sheetOpen = ref(false)
const activeItem = ref(null)
const mode = ref('confirm') // 'confirm' | 'reject'
const alasan = ref('')

const navItems = [
    { key: 'beranda', label: 'Beranda', href: '/pimpinan', active: true },
    { key: 'akun', label: 'Panel', href: '/admin', active: false },
]

const TIPE_LABEL = {
    JurnalManual: 'Jurnal Manual',
    Pencairan: 'Pencairan Dana',
    Pembiayaan: 'Pengajuan Pembiayaan',
    Payroll: 'Penggajian',
    Pengeluaran: 'Pengeluaran',
    Belanja: 'Belanja',
}
function tipeLabel(t) {
    return TIPE_LABEL[t] || (t || 'Permintaan').replace(/([a-z])([A-Z])/g, '$1 $2')
}

const MUTU_TONE = {
    hijau: 'bg-emerald-50 text-emerald-700 ring-emerald-100',
    kuning: 'bg-amber-50 text-amber-700 ring-amber-100',
    merah: 'bg-rose-50 text-rose-700 ring-rose-100',
}
const MUTU_DOT = { hijau: 'bg-emerald-500', kuning: 'bg-amber-500', merah: 'bg-rose-500' }

function persenText(k) {
    return k?.persen === null || k?.persen === undefined ? '—' : `${k.persen}%`
}

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content || ''
}

async function muat() {
    loading.value = true
    error.value = ''
    try {
        const res = await fetch('/pimpinan/api/ringkasan', {
            headers: { Accept: 'application/json' }, credentials: 'same-origin',
        })
        if (res.ok) data.value = await res.json()
        else error.value = 'Gagal memuat data.'
    } catch (e) {
        error.value = 'Tidak ada koneksi.'
    } finally {
        loading.value = false
    }
}

function bukaItem(item) {
    activeItem.value = item
    mode.value = 'confirm'
    alasan.value = ''
    sheetOpen.value = true
}

async function putuskan(aksi) {
    const item = activeItem.value
    if (!item) return
    busy.value = true
    try {
        const body = aksi === 'tolak' ? JSON.stringify({ alasan: alasan.value }) : '{}'
        const res = await fetch(`/pimpinan/api/approval/${item.id}/${aksi}`, {
            method: 'POST', credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
            body,
        })
        if (res.ok) {
            sheetOpen.value = false
            activeItem.value = null
            alasan.value = ''
            await muat()
        } else {
            const j = await res.json().catch(() => ({}))
            error.value = j.message || 'Gagal memproses.'
            sheetOpen.value = false
        }
    } catch (e) {
        error.value = 'Tidak ada koneksi.'
    } finally {
        busy.value = false
    }
}

onMounted(muat)
</script>

<template>
    <Head title="Dasbor Pimpinan" />

    <AppShell :nav-items="navItems" :brand-name="brandName" :theme-color="branding.primaryColor || '#059669'">
        <template #header>
            <HeroHeader :name="userName || 'Pimpinan'" greeting="Assalamu'alaikum,">
                <!-- KPI headline: Saldo Kas + Surplus/Defisit (kartu kaca di atas gradient) -->
                <div v-if="data" class="grid grid-cols-2 gap-[11px]">
                    <div class="col-span-2 rounded-[20px] bg-white/15 px-4 py-[14px] ring-1 ring-inset ring-white/20 backdrop-blur-sm">
                        <p class="font-jost text-[10.5px] font-semibold uppercase tracking-[0.14em] text-white/70">Saldo Kas</p>
                        <MoneyText :amount="data.kas.saldo" tone="inherit" size="xl" class="mt-1 block text-white" />
                    </div>
                    <div class="rounded-[20px] bg-white/15 px-4 py-[14px] ring-1 ring-inset ring-white/20 backdrop-blur-sm">
                        <p class="font-jost text-[10.5px] font-semibold uppercase tracking-[0.14em] text-white/70">Pemasukan</p>
                        <MoneyText :amount="data.kas.pendapatan" tone="inherit" size="md" class="mt-1 block text-white" />
                    </div>
                    <div class="rounded-[20px] bg-white/15 px-4 py-[14px] ring-1 ring-inset ring-white/20 backdrop-blur-sm">
                        <p class="font-jost text-[10.5px] font-semibold uppercase tracking-[0.14em]"
                           :class="data.kas.surplus_defisit < 0 ? 'text-rose-200' : 'text-[#e7d3a1]'">
                            {{ data.kas.surplus_defisit < 0 ? 'Defisit' : 'Surplus' }}
                        </p>
                        <MoneyText :amount="data.kas.surplus_defisit" tone="inherit" size="md" signed class="mt-1 block text-white" />
                    </div>
                </div>
            </HeroHeader>
        </template>

        <div class="space-y-5">
            <!-- Loading -->
            <LoadingState v-if="loading && !data" variant="stat" :count="4" />

            <!-- Error -->
            <div
                v-else-if="error && !data"
                class="rounded-3xl bg-white p-6 text-center ring-1 ring-rose-100 shadow-soft"
            >
                <IconCircle icon="wifi-off" tone="rose" size="lg" class="mx-auto" />
                <p class="mt-3 text-sm font-semibold text-slate-700">{{ error }}</p>
                <UiButton variant="secondary" size="sm" icon-left="arrow-up-right" class="mt-4" @click="muat">
                    Coba lagi
                </UiButton>
            </div>

            <template v-else-if="data">
                <!-- Kehadiran hari ini -->
                <div class="grid grid-cols-2 gap-3">
                    <StatCard
                        icon="user" tone="emerald" label="Hadir Santri"
                        :value="persenText(data.kehadiran.santri)"
                        :hint="`${data.kehadiran.santri.hadir}/${data.kehadiran.santri.total} catatan`"
                    />
                    <StatCard
                        icon="user" tone="teal" label="Hadir Guru"
                        :value="persenText(data.kehadiran.guru)"
                        :hint="`${data.kehadiran.guru.hadir}/${data.kehadiran.guru.total} catatan`"
                    />
                </div>

                <!-- Antrean operasional -->
                <div class="grid grid-cols-2 gap-3">
                    <StatCard icon="document" tone="amber" label="Izin Menunggu" :value="data.antrean.izin_diajukan" />
                    <StatCard icon="ban" tone="rose" label="Pelanggaran Baru" :value="data.antrean.pelanggaran_menunggu" />
                </div>

                <!-- Lampu Mutu (defensif) -->
                <SectionCard v-if="data.mutu && Object.keys(data.mutu).length" kicker="Mutu" title="Lampu Mutu">
                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="(jumlah, warna) in data.mutu"
                            :key="warna"
                            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 font-jost text-xs font-semibold capitalize ring-1 ring-inset"
                            :class="MUTU_TONE[warna] || 'bg-slate-100 text-slate-600 ring-slate-200'"
                        >
                            <span class="h-2 w-2 rounded-full" :class="MUTU_DOT[warna] || 'bg-slate-400'" />
                            {{ warna }} · {{ jumlah }}
                        </span>
                    </div>
                </SectionCard>

                <!-- Inbox approval -->
                <SectionCard kicker="Persetujuan" title="Menunggu Persetujuan Anda" flush>
                    <EmptyState
                        v-if="!data.antrean.approval.length"
                        icon="check-circle" tone="emerald"
                        title="Tidak ada antrean"
                        message="Semua permintaan persetujuan sudah diproses."
                    />
                    <div v-else class="divide-y divide-slate-100">
                        <ListRow
                            v-for="item in data.antrean.approval"
                            :key="item.id"
                            chevron
                            class="cursor-pointer"
                            @click="bukaItem(item)"
                        >
                            <template #leading>
                                <IconCircle icon="inbox" tone="amber" />
                            </template>
                            <template #title>{{ tipeLabel(item.tipe) }} #{{ item.approvable_id }}</template>
                            <template #subtitle>
                                {{ item.pemohon ? `Oleh ${item.pemohon}` : (item.diajukan_pada || '—') }}
                            </template>
                            <template #trailing>
                                <MoneyText v-if="item.nominal != null" :amount="item.nominal" size="sm" />
                            </template>
                        </ListRow>
                    </div>
                </SectionCard>
            </template>
        </div>

        <!-- Sheet konfirmasi / penolakan -->
        <BottomSheet v-model="sheetOpen" :title="activeItem ? tipeLabel(activeItem.tipe) : ''">
            <div v-if="activeItem" class="space-y-3">
                <dl class="space-y-2.5 rounded-2xl bg-slate-50 p-4 text-sm">
                    <div v-if="activeItem.nominal != null" class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500">Nominal</dt>
                        <dd><MoneyText :amount="activeItem.nominal" size="md" /></dd>
                    </div>
                    <div v-if="activeItem.pemohon" class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500">Pemohon</dt>
                        <dd class="font-semibold text-slate-800">{{ activeItem.pemohon }}</dd>
                    </div>
                    <div v-if="activeItem.keterangan" class="flex flex-col gap-1">
                        <dt class="text-slate-500">Keterangan</dt>
                        <dd class="text-slate-800">{{ activeItem.keterangan }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500">Diajukan</dt>
                        <dd class="text-slate-700">{{ activeItem.diajukan_pada || '—' }}</dd>
                    </div>
                </dl>

                <UiField v-if="mode === 'reject'" label="Alasan penolakan" required>
                    <template #default="{ fieldId, describedby, invalid }">
                        <UiInput
                            :id="fieldId" v-model="alasan" type="textarea" :rows="3"
                            :aria-describedby="describedby" :invalid="invalid"
                            placeholder="Tuliskan alasan penolakan (wajib)…"
                        />
                    </template>
                </UiField>
            </div>

            <template #footer>
                <div v-if="mode === 'confirm'" class="flex gap-2">
                    <UiButton variant="danger" block :disabled="busy" @click="mode = 'reject'">Tolak…</UiButton>
                    <UiButton variant="primary" block :loading="busy" @click="putuskan('setujui')">Setujui</UiButton>
                </div>
                <div v-else class="flex gap-2">
                    <UiButton variant="ghost" block :disabled="busy" @click="mode = 'confirm'">Batal</UiButton>
                    <UiButton variant="danger" block :loading="busy" :disabled="!alasan.trim()" @click="putuskan('tolak')">
                        Kirim Penolakan
                    </UiButton>
                </div>
            </template>
        </BottomSheet>
    </AppShell>
</template>
