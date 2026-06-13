<script setup>
import { Head } from '@inertiajs/vue3'
import { ref } from 'vue'
import { AppShell, PageHeader, SectionCard, StatCard, ListRow, MoneyText, StatusBadge, IconCircle, UiButton, BottomSheet, UiIcon, EmptyState } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    sesi: { type: Object, default: () => ({}) },
    transaksi: { type: Array, default: () => [] },
})

const sheetOpen = ref(false)
const ditutup = ref(false)
const toast = ref('')

function konfirmasiTutup() {
    ditutup.value = true
    toast.value = 'Sesi kantin berhasil ditutup. Rekap tersimpan.'
    setTimeout(() => {
        sheetOpen.value = false
        setTimeout(() => (toast.value = ''), 2200)
    }, 750)
}

function formatRupiah(n) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(n || 0)
}
</script>

<template>
    <Head title="POS Kantin — Riwayat" />

    <AppShell :nav-items="navigation" brand-name="POS Kantin">
        <div class="space-y-5">
            <PageHeader kicker="Sesi & Transaksi" title="Riwayat" subtitle="Rekap penjualan sesi berjalan" />

            <SectionCard>
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[15px] font-bold text-slate-900">{{ sesi.label }}</p>
                        <p class="mt-0.5 text-sm text-slate-500">Dibuka {{ sesi.dibuka }} · {{ sesi.kasir }}</p>
                    </div>
                    <StatusBadge :status="ditutup ? 'lunas' : 'aktif'" :label="ditutup ? 'Ditutup' : 'Aktif'" />
                </div>
                <div class="mt-4 grid grid-cols-2 gap-3">
                    <StatCard icon="cash" tone="emerald" label="Omzet Sesi" compact>
                        <template #value><MoneyText :amount="sesi.omzet" size="lg" tone="emerald" /></template>
                    </StatCard>
                    <StatCard icon="receipt" tone="blue" label="Transaksi" :value="String(sesi.transaksi)" compact />
                    <StatCard icon="wallet" tone="teal" label="Via Tabungan" compact>
                        <template #value><MoneyText :amount="sesi.tabungan" size="md" tone="default" /></template>
                    </StatCard>
                    <StatCard icon="cash" tone="amber" label="Via Tunai" compact>
                        <template #value><MoneyText :amount="sesi.tunai" size="md" tone="default" /></template>
                    </StatCard>
                </div>
            </SectionCard>

            <SectionCard kicker="Timeline" title="Transaksi Sesi" flush>
                <div v-if="transaksi.length" class="divide-y divide-slate-100">
                    <ListRow
                        v-for="t in transaksi"
                        :key="t.id"
                        :title="t.id"
                        :subtitle="t.item"
                    >
                        <template #leading>
                            <IconCircle
                                :icon="t.metode === 'tabungan' ? 'wallet' : 'cash'"
                                :tone="t.metode === 'tabungan' ? 'teal' : 'emerald'"
                                variant="tint"
                                size="md"
                            />
                        </template>
                        <template #trailing>
                            <MoneyText :amount="t.nominal" size="sm" tone="emerald" />
                            <p class="mt-0.5 text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ t.waktu }}</p>
                        </template>
                    </ListRow>
                </div>
                <div v-else class="p-2">
                    <EmptyState icon="receipt" title="Belum ada transaksi" message="Transaksi sesi akan tampil di sini." />
                </div>
            </SectionCard>

            <UiButton
                variant="danger"
                block
                size="lg"
                icon-left="check-circle"
                :disabled="ditutup"
                @click="sheetOpen = true"
            >
                {{ ditutup ? 'Sesi Sudah Ditutup' : 'Tutup Sesi' }}
            </UiButton>
        </div>

        <div
            v-if="toast"
            class="fixed inset-x-0 top-4 z-50 mx-auto w-full max-w-md px-5"
            role="status"
        >
            <div class="flex items-center gap-2.5 rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white shadow-soft">
                <UiIcon name="check-circle" class="h-5 w-5 shrink-0" />
                <span>{{ toast }}</span>
            </div>
        </div>

        <BottomSheet v-model="sheetOpen" title="Tutup Sesi Kantin">
            <div class="space-y-3">
                <p class="text-sm leading-6 text-slate-600">
                    Pastikan kas fisik sudah dihitung. Setelah ditutup, sesi terkunci dan rekap dikirim ke bendahara.
                </p>
                <div class="space-y-2 rounded-2xl bg-slate-50/70 p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500">Omzet sesi</span>
                        <span class="text-sm font-bold text-slate-800">{{ formatRupiah(sesi.omzet) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500">Tunai</span>
                        <span class="text-sm font-bold text-slate-800">{{ formatRupiah(sesi.tunai) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500">Tabungan santri</span>
                        <span class="text-sm font-bold text-slate-800">{{ formatRupiah(sesi.tabungan) }}</span>
                    </div>
                </div>
            </div>

            <template #footer>
                <UiButton variant="danger" block size="lg" icon-left="check" @click="konfirmasiTutup">
                    Tutup Sesi Sekarang
                </UiButton>
            </template>
        </BottomSheet>
    </AppShell>
</template>
