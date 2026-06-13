<script setup>
import { computed, onMounted, ref } from 'vue'
import KepalaKeuanganLayout from '@/Layouts/KepalaKeuanganLayout.vue'
import { api } from '@/keuangan-pwa/api.js'
import { formatRupiah } from '@/keuangan-pwa/format.js'
import { PageHeader, SectionCard, StatCard, StatusBadge, MoneyText, LoadingState, EmptyState } from '@/Shared'

defineOptions({ layout: KepalaKeuanganLayout })

const loading = ref(true)
const rows = ref([])
const error = ref(null)

const totalDebit = computed(() => rows.value.reduce((s, r) => s + (r.debit ?? 0), 0))
const totalKredit = computed(() => rows.value.reduce((s, r) => s + (r.kredit ?? 0), 0))
const seimbang = computed(() => Math.abs(totalDebit.value - totalKredit.value) < 0.01)

onMounted(async () => {
    try {
        rows.value = await api.get('/neraca-saldo')
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
})
</script>

<template>
    <div class="space-y-5">
        <PageHeader kicker="Laporan" title="Neraca Saldo" subtitle="Ringkasan saldo debit & kredit seluruh akun">
            <template v-if="!loading && !error" #right>
                <StatusBadge
                    :status="seimbang ? 'aktif' : 'ditolak'"
                    :label="seimbang ? 'Seimbang' : 'Tidak Seimbang'"
                    size="sm"
                />
            </template>
        </PageHeader>

        <LoadingState v-if="loading" variant="list" :count="6" />

        <EmptyState
            v-else-if="error"
            icon="x-circle"
            title="Gagal memuat"
            :message="error"
        />

        <template v-else-if="!rows.length">
            <EmptyState
                icon="document"
                title="Belum ada data"
                message="Neraca saldo belum memiliki entri akun."
            />
        </template>

        <template v-else>
            <SectionCard flush>
                <div class="overflow-hidden rounded-3xl">
                    <div class="grid grid-cols-[1fr_auto_auto] gap-2 border-b border-slate-100 bg-slate-50/70 px-4 py-2.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                        <span>Akun</span><span class="w-24 text-right">Debit</span><span class="w-24 text-right">Kredit</span>
                    </div>
                    <div
                        v-for="r in rows"
                        :key="r.akun_id"
                        class="grid grid-cols-[1fr_auto_auto] items-center gap-2 border-b border-slate-100 px-4 py-2.5 last:border-0"
                    >
                        <div class="min-w-0">
                            <div class="truncate text-sm font-semibold text-slate-900">{{ r.nama }}</div>
                            <div class="text-[11px] font-medium text-slate-400">{{ r.kode }} · {{ r.tipe }}</div>
                        </div>
                        <span class="w-24 text-right tabular-nums">
                            <MoneyText v-if="r.debit" :amount="r.debit" size="sm" tone="emerald" />
                            <span v-else class="text-xs font-medium text-slate-300">–</span>
                        </span>
                        <span class="w-24 text-right tabular-nums">
                            <MoneyText v-if="r.kredit" :amount="r.kredit" size="sm" tone="emerald" />
                            <span v-else class="text-xs font-medium text-slate-300">–</span>
                        </span>
                    </div>
                    <div class="grid grid-cols-[1fr_auto_auto] items-center gap-2 bg-[var(--wali-primary,#059669)] px-4 py-3 text-white">
                        <span class="text-sm font-bold tracking-tight">TOTAL</span>
                        <span class="w-24 text-right tabular-nums">
                            <MoneyText :amount="totalDebit" size="sm" tone="inherit" />
                        </span>
                        <span class="w-24 text-right tabular-nums">
                            <MoneyText :amount="totalKredit" size="sm" tone="inherit" />
                        </span>
                    </div>
                </div>
            </SectionCard>

            <div class="grid grid-cols-2 gap-3">
                <StatCard icon="arrow-down-left" tone="emerald" label="Total Debit">
                    <template #value>
                        <MoneyText :amount="totalDebit" size="lg" tone="emerald" />
                    </template>
                </StatCard>
                <StatCard icon="arrow-up-right" tone="teal" label="Total Kredit">
                    <template #value>
                        <MoneyText :amount="totalKredit" size="lg" tone="emerald" />
                    </template>
                </StatCard>
            </div>
        </template>
    </div>
</template>
