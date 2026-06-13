<script setup>
import { onMounted, ref } from 'vue'
import KepalaKeuanganLayout from '@/Layouts/KepalaKeuanganLayout.vue'
import { api } from '@/keuangan-pwa/api.js'
import { formatRupiah, formatRupiahFull } from '@/keuangan-pwa/format.js'
import {
    PageHeader,
    SectionCard,
    UiCard,
    ListRow,
    StatCard,
    MoneyText,
    IconCircle,
    EmptyState,
    LoadingState,
    UiButton,
} from '@/Shared'

defineOptions({ layout: KepalaKeuanganLayout })
const props = defineProps({ can: Object })

const loading = ref(true)
const data = ref(null)
const error = ref(null)

const shortcuts = [
    { label: 'Tagihan', icon: 'receipt', href: '/keuangan/penerimaan/tagihan', color: 'emerald' },
    { label: 'Verifikasi', icon: 'check', href: '/keuangan/penerimaan/verifikasi', color: 'blue' },
    { label: 'Loket Kas', icon: 'banknotes', href: '/keuangan/penerimaan/loket', color: 'amber' },
    { label: 'Tabungan', icon: 'piggy', href: '/keuangan/kas-harian/tabungan', color: 'teal' },
    { label: 'Approval', icon: 'stamp', href: '/keuangan/pengeluaran/approval', color: 'rose' },
    { label: 'Payroll', icon: 'users', href: '/keuangan/pengeluaran/payroll', color: 'purple' },
    { label: 'Jurnal', icon: 'book', href: '/keuangan/ledger/jurnal', color: 'indigo' },
    { label: 'Laporan', icon: 'chart', href: '/keuangan/laporan', color: 'orange' },
]

onMounted(async () => {
    try {
        data.value = await api.get('/beranda')
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
})
</script>

<template>
    <div class="space-y-5">
        <!-- Posisi Kas -->
        <section class="space-y-3">
            <PageHeader kicker="Bendahara" title="Posisi Kas" />

            <!-- Skeleton -->
            <div v-if="loading" class="space-y-3">
                <LoadingState variant="hero" :count="1" />
                <LoadingState variant="stat" :count="2" />
            </div>

            <UiCard v-else-if="error" padding="md" class="text-sm text-rose-700 ring-rose-100">
                Gagal memuat data: {{ error }}
            </UiCard>

            <div v-else class="space-y-3">
                <!-- Total kas card -->
                <UiCard variant="hero-dark" padding="md">
                    <div class="text-[11px] font-semibold uppercase tracking-wider text-white/70">Total Posisi Kas</div>
                    <div class="mt-1">
                        <MoneyText :amount="data?.totalKas ?? 0" size="xl" tone="inherit" class="text-white" />
                    </div>
                    <div class="mt-2 text-xs text-white/75">
                        Periode: {{ data?.periodeAktif ?? '-' }}
                    </div>
                </UiCard>

                <!-- Kas & Bank breakdown -->
                <div class="grid grid-cols-2 gap-3">
                    <StatCard icon="cash" tone="emerald" label="Kas Tunai" compact>
                        <template #value>
                            <MoneyText :amount="data?.kasTunai ?? 0" size="lg" tone="emerald" />
                        </template>
                    </StatCard>
                    <StatCard icon="bank" tone="teal" label="Bank" compact>
                        <template #value>
                            <MoneyText :amount="data?.kasBank ?? 0" size="lg" tone="emerald" />
                        </template>
                    </StatCard>
                </div>
            </div>
        </section>

        <!-- Approval Inbox -->
        <SectionCard
            v-if="props.can?.approve_pencairan"
            kicker="Pengeluaran"
            title="Menunggu Persetujuan"
            flush
        >
            <template #right>
                <UiButton href="/keuangan/pengeluaran/approval" variant="ghost" size="sm" iconRight="chevron-right">
                    Lihat Semua
                </UiButton>
            </template>

            <LoadingState v-if="loading" variant="list" :count="3" class="p-2" />

            <div v-else-if="data?.pendingApproval?.length" class="divide-y divide-slate-100">
                <ListRow
                    v-for="item in data.pendingApproval.slice(0, 5)"
                    :key="item.id"
                    :title="item.keterangan"
                >
                    <template #leading>
                        <IconCircle icon="clock" tone="amber" size="sm" />
                    </template>
                    <template #subtitle>
                        {{ item.pemohon }} · {{ formatRupiah(item.nominal) }}
                    </template>
                </ListRow>
            </div>

            <EmptyState
                v-else
                icon="check-circle"
                tone="emerald"
                title="Tidak ada pengajuan menunggu"
                message="Semua pengajuan pencairan sudah ditindaklanjuti."
            />
        </SectionCard>

        <!-- Quick Shortcuts -->
        <SectionCard kicker="Akses cepat" title="Pintasan">
            <div class="grid grid-cols-4 gap-2.5">
                <a
                    v-for="s in shortcuts"
                    :key="s.label"
                    :href="s.href"
                    class="flex flex-col items-center gap-1.5 rounded-2xl px-2 py-3 text-center transition-all duration-200 hover:bg-slate-50/70 active:scale-[.97]"
                >
                    <IconCircle
                        :icon="{ receipt: 'receipt', check: 'check', banknotes: 'cash', piggy: 'wallet', stamp: 'check-circle', users: 'user', book: 'book', chart: 'chart' }[s.icon] || 'chart'"
                        :tone="{ emerald: 'emerald', blue: 'blue', amber: 'amber', teal: 'teal', rose: 'rose', purple: 'blue', indigo: 'blue', orange: 'amber' }[s.color] || 'slate'"
                        size="sm"
                    />
                    <span class="text-[10px] font-semibold leading-tight text-slate-600">{{ s.label }}</span>
                </a>
            </div>
        </SectionCard>

        <!-- Ringkasan Harian -->
        <SectionCard
            v-if="!loading && data?.ringkasanHarian"
            kicker="Hari ini"
            title="Ringkasan Hari Ini"
            flush
        >
            <div class="divide-y divide-slate-100">
                <ListRow title="Penerimaan">
                    <template #leading>
                        <IconCircle icon="arrow-down-left" tone="emerald" size="sm" />
                    </template>
                    <template #trailing>
                        <MoneyText :amount="data.ringkasanHarian.penerimaan ?? 0" size="sm" tone="emerald" sign="plus" />
                    </template>
                </ListRow>
                <ListRow title="Pengeluaran">
                    <template #leading>
                        <IconCircle icon="arrow-up-right" tone="rose" size="sm" />
                    </template>
                    <template #trailing>
                        <MoneyText :amount="data.ringkasanHarian.pengeluaran ?? 0" size="sm" tone="rose" sign="minus" />
                    </template>
                </ListRow>
                <ListRow title="Belum Diverifikasi">
                    <template #leading>
                        <IconCircle icon="clock" tone="amber" size="sm" />
                    </template>
                    <template #trailing>
                        <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold tabular-nums text-amber-700 ring-1 ring-inset ring-amber-100">
                            {{ data.ringkasanHarian.belumVerifikasi ?? 0 }}
                        </span>
                    </template>
                </ListRow>
            </div>
        </SectionCard>
    </div>
</template>
