<script setup>
import { onMounted, ref, watch } from 'vue'
import KepalaKeuanganLayout from '@/Layouts/KepalaKeuanganLayout.vue'
import { api } from '@/keuangan-pwa/api.js'
import { formatRupiah, formatTanggal } from '@/keuangan-pwa/format.js'
import {
    PageHeader,
    SectionCard,
    ListRow,
    StatCard,
    SegmentedControl,
    MoneyText,
    EmptyState,
    LoadingState,
} from '@/Shared'

defineOptions({ layout: KepalaKeuanganLayout })

const tabs = [
    { key: 'harian', label: 'Harian' },
    { key: 'mingguan', label: 'Mingguan' },
    { key: 'bulanan', label: 'Bulanan' },
]

const aktif = ref(new URLSearchParams(window.location.search).get('tab') ?? 'harian')
const loading = ref(true)
const data = ref(null)
const error = ref(null)

async function muat() {
    loading.value = true
    error.value = null
    try {
        data.value = await api.get(`/laporan/operasional?jenis=${aktif.value}`)
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
}

watch(aktif, muat)
onMounted(muat)
</script>

<template>
    <div class="space-y-5">
        <PageHeader kicker="Laporan" title="Laporan Operasional" />

        <SegmentedControl
            :model-value="aktif"
            :options="tabs.map((t) => ({ value: t.key, label: t.label }))"
            @update:model-value="aktif = $event"
        />

        <LoadingState v-if="loading" variant="list" :count="3" />

        <div
            v-else-if="error"
            class="rounded-3xl bg-rose-50 px-4 py-5 text-sm text-rose-700 ring-1 ring-rose-100 shadow-soft"
        >
            Gagal memuat: {{ error }}
        </div>

        <template v-else-if="data">
            <!-- Harian -->
            <template v-if="aktif === 'harian'">
                <div class="relative overflow-hidden rounded-3xl bg-brand-grad-hero p-5 text-white shadow-soft">
                    <div class="text-[11px] uppercase tracking-wider text-white/70">Total Penerimaan · {{ formatTanggal(data.tanggal) }}</div>
                    <MoneyText :amount="data.total_pembayaran" size="xl" tone="inherit" class="mt-1 block text-white" />
                    <div class="mt-1 text-xs text-white/75">{{ data.jumlah_transaksi }} transaksi terverifikasi</div>
                </div>

                <SectionCard v-if="data.per_jenis_tagihan?.length" flush>
                    <div class="divide-y divide-slate-100">
                        <ListRow v-for="j in data.per_jenis_tagihan" :key="j.jenis">
                            <template #title><span class="uppercase">{{ j.jenis }}</span></template>
                            <template #subtitle>{{ j.jumlah_transaksi }} transaksi</template>
                            <template #trailing>
                                <MoneyText :amount="j.total" size="sm" tone="emerald" />
                            </template>
                        </ListRow>
                    </div>
                </SectionCard>
                <EmptyState
                    v-else
                    icon="inbox"
                    title="Belum ada pembayaran"
                    message="Belum ada pembayaran terverifikasi hari ini"
                />
            </template>

            <!-- Mingguan -->
            <template v-else-if="aktif === 'mingguan'">
                <div class="relative overflow-hidden rounded-3xl bg-brand-grad-hero p-5 text-white shadow-soft">
                    <div class="text-[11px] uppercase tracking-wider text-white/70">
                        {{ formatTanggal(data.dari) }} – {{ formatTanggal(data.sampai) }}
                    </div>
                    <MoneyText :amount="data.total" size="xl" tone="inherit" class="mt-1 block text-white" />
                    <div class="mt-1 text-xs text-white/75">{{ data.jumlah_transaksi }} transaksi dalam 7 hari</div>
                </div>

                <SectionCard v-if="data.per_hari?.length" flush>
                    <div class="divide-y divide-slate-100">
                        <ListRow
                            v-for="h in data.per_hari"
                            :key="h.tanggal"
                            :title="formatTanggal(h.tanggal)"
                            :subtitle="`${h.jumlah} transaksi`"
                        >
                            <template #trailing>
                                <MoneyText :amount="h.total" size="sm" tone="emerald" sign="plus" />
                            </template>
                        </ListRow>
                    </div>
                </SectionCard>
                <EmptyState
                    v-else
                    icon="inbox"
                    title="Tidak ada pembayaran"
                    message="Tidak ada pembayaran pada rentang ini"
                />
            </template>

            <!-- Bulanan -->
            <template v-else>
                <div class="relative overflow-hidden rounded-3xl bg-brand-grad-hero p-5 text-white shadow-soft">
                    <div class="text-[11px] uppercase tracking-wider text-white/70">Konsolidasi {{ data.bulan }}</div>
                    <MoneyText :amount="data.total_terkumpul" size="xl" tone="inherit" class="mt-1 block text-white" />
                    <div class="mt-1 text-xs text-white/75">terkumpul dari tagihan {{ formatRupiah(data.total_tagihan) }}</div>
                </div>

                <div class="rounded-3xl bg-white p-4 ring-1 ring-slate-100 shadow-soft">
                    <div class="mb-1.5 flex justify-between text-xs">
                        <span class="text-slate-500">Kolektibilitas</span>
                        <span class="font-bold tabular-nums text-violet-700">{{ data.persentase_terkumpul }}%</span>
                    </div>
                    <div class="h-2.5 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-gradient-to-r from-[var(--wali-primary,#0A5C36)] to-[var(--wali-accent,#C9A24A)]"
                             :style="{ width: Math.min(data.persentase_terkumpul, 100) + '%' }"></div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <StatCard tone="emerald" label="Tagihan Lunas" :value="data.jumlah_lunas" />
                    <StatCard tone="amber" label="Belum Lunas" :value="data.jumlah_belum_lunas" />
                    <div class="col-span-2">
                        <StatCard tone="rose" label="Sisa Piutang">
                            <template #value>
                                <MoneyText :amount="data.sisa_piutang" size="lg" tone="rose" />
                            </template>
                        </StatCard>
                    </div>
                </div>
            </template>
        </template>
    </div>
</template>
