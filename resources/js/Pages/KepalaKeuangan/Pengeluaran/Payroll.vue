<script setup>
import { onMounted, ref } from 'vue'
import KepalaKeuanganLayout from '@/Layouts/KepalaKeuanganLayout.vue'
import { api } from '@/keuangan-pwa/api.js'
import { formatRupiah, formatTanggal } from '@/keuangan-pwa/format.js'
import {
    PageHeader,
    SectionCard,
    ListRow,
    StatusBadge,
    Chip,
    UiButton,
    MoneyText,
    EmptyState,
    LoadingState,
} from '@/Shared'

defineOptions({ layout: KepalaKeuanganLayout })
const props = defineProps({ can: Object })

const loading = ref(true)
const periodes = ref([])
const selectedPeriode = ref(null)
const slips = ref([])
const ringkasan = ref(null)
const processing = ref(false)

async function fetchPeriodes() {
    loading.value = true
    try {
        const res = await api.get('/payroll/periode')
        periodes.value = res.data ?? []
        if (periodes.value.length) {
            selectedPeriode.value = periodes.value[0]
            await fetchSlips(periodes.value[0].id)
        }
    } catch { /* */ }
    loading.value = false
}

async function fetchSlips(periodeId) {
    try {
        const res = await api.get(`/payroll/slip?periode_id=${periodeId}`)
        slips.value = res.data ?? []
        ringkasan.value = res.ringkasan ?? null
    } catch { /* */ }
}

async function prosesGaji() {
    if (!selectedPeriode.value || processing.value) return
    if (!confirm(`Proses gaji periode ${selectedPeriode.value.label}? Aksi ini tidak dapat dibatalkan.`)) return
    if (!confirm('Anda yakin? Slip gaji akan diterbitkan dan jurnal akan diposting.')) return
    processing.value = true
    try {
        await api.post('/payroll/proses', { periode_id: selectedPeriode.value.id })
        await fetchSlips(selectedPeriode.value.id)
    } catch { /* */ }
    processing.value = false
}

onMounted(fetchPeriodes)
</script>

<template>
    <div class="space-y-5">
        <PageHeader kicker="Pengeluaran" title="Payroll & PPh21" />

        <LoadingState v-if="loading" variant="stat" :count="4" />

        <template v-else>
            <!-- Periode selector -->
            <div class="flex gap-2 overflow-x-auto pb-1">
                <Chip
                    v-for="p in periodes"
                    :key="p.id"
                    size="sm"
                    :active="selectedPeriode?.id === p.id"
                    @click="selectedPeriode = p; fetchSlips(p.id)"
                >
                    {{ p.label }}
                </Chip>
            </div>

            <!-- Status periode -->
            <div v-if="selectedPeriode?.diproses" class="flex justify-start">
                <StatusBadge status="terverifikasi" label="Sudah diproses" />
            </div>

            <!-- Ringkasan -->
            <div
                v-if="ringkasan"
                class="rounded-3xl bg-gradient-to-br from-[var(--wali-primary,#059669)] to-emerald-900 p-5 text-white shadow-soft ring-1 ring-[var(--wali-accent,#C9A24A)]/30"
            >
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-wider text-white/70">Total Gaji Kotor</div>
                        <MoneyText :amount="ringkasan.totalKotor" size="lg" tone="inherit" class="mt-1 block text-white" />
                    </div>
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-wider text-white/70">Total Bersih</div>
                        <MoneyText :amount="ringkasan.totalBersih" size="lg" tone="inherit" class="mt-1 block text-white" />
                    </div>
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-wider text-white/70">PPh21</div>
                        <MoneyText :amount="ringkasan.totalPph" size="sm" tone="inherit" class="mt-1 block text-white" />
                    </div>
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-wider text-white/70">Pegawai</div>
                        <div class="mt-1 text-sm font-semibold tabular-nums">{{ ringkasan.jumlahPegawai }} orang</div>
                    </div>
                </div>
            </div>

            <!-- Proses button -->
            <UiButton
                v-if="props.can?.run_payroll && selectedPeriode && !selectedPeriode.diproses"
                variant="primary"
                block
                icon-left="check-circle"
                :loading="processing"
                :disabled="processing"
                @click="prosesGaji"
            >
                {{ processing ? 'Memproses...' : 'Proses Gaji Periode Ini' }}
            </UiButton>

            <!-- Slip list -->
            <SectionCard v-if="slips.length" kicker="Payroll" title="Daftar Slip" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow v-for="s in slips" :key="s.id" :title="s.nama_pegawai" :subtitle="s.jabatan ?? '-'">
                        <template #trailing>
                            <MoneyText :amount="s.gaji_bersih" size="sm" tone="emerald" />
                            <div v-if="s.pph" class="mt-0.5 text-[10px] text-slate-400">
                                PPh: {{ formatRupiah(s.pph) }}
                            </div>
                        </template>
                    </ListRow>
                </div>
            </SectionCard>
            <div v-else class="space-y-3">
                <PageHeader kicker="Payroll" title="Daftar Slip" />
                <EmptyState
                    icon="wallet"
                    title="Belum ada slip"
                    message="Belum ada slip gaji di periode ini"
                />
            </div>
        </template>
    </div>
</template>
