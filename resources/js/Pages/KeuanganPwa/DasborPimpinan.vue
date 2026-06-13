<script setup>
import { Head } from '@inertiajs/vue3'
import { ref, onMounted } from 'vue'
import { AppShell, PageHeader, StatCard, MoneyText, LoadingState, UiButton, IconCircle } from '@/Shared'

defineProps({ navigation: { type: Array, default: () => [] } })

const data = ref(null)
const error = ref('')
const loading = ref(true)

async function muat() {
    loading.value = true
    error.value = ''
    try {
        const res = await fetch('/api/keuangan/dashboard-pimpinan', {
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

onMounted(muat)
</script>

<template>
    <Head title="Dasbor Pimpinan" />

    <AppShell :nav-items="navigation" brand-name="Keuangan Lapangan">
        <div class="space-y-5">
            <PageHeader kicker="Keuangan" title="Dasbor Pimpinan" subtitle="Ringkasan kas & keuangan terkini" />

            <LoadingState v-if="loading && !data" variant="stat" :count="4" />

            <div
                v-else-if="error && !data"
                class="rounded-3xl bg-white p-6 text-center ring-1 ring-rose-100 shadow-soft"
            >
                <IconCircle icon="wifi-off" tone="rose" size="lg" class="mx-auto" />
                <p class="mt-3 text-sm font-semibold text-slate-700">{{ error }}</p>
                <UiButton variant="secondary" size="sm" icon-left="arrow-up-right" class="mt-4" @click="muat">Coba lagi</UiButton>
            </div>

            <div v-else-if="data" class="grid grid-cols-2 gap-3">
                <StatCard class="col-span-2" icon="bank" tone="emerald" label="Kas Konsolidasi">
                    <template #value><MoneyText :amount="data.kas_konsolidasi" tone="emerald" size="xl" /></template>
                </StatCard>
                <StatCard
                    :icon="data.surplus_defisit < 0 ? 'arrow-down-left' : 'arrow-up-right'"
                    :tone="data.surplus_defisit < 0 ? 'rose' : 'emerald'"
                    label="Surplus / Defisit"
                >
                    <template #value>
                        <MoneyText :amount="data.surplus_defisit" :tone="data.surplus_defisit < 0 ? 'rose' : 'emerald'" size="lg" signed />
                    </template>
                </StatCard>
                <StatCard icon="cash" tone="teal" label="Pemasukan">
                    <template #value><MoneyText :amount="data.total_pendapatan" tone="teal" size="lg" /></template>
                </StatCard>
                <StatCard icon="receipt" tone="slate" label="Pengeluaran">
                    <template #value><MoneyText :amount="data.total_beban" size="lg" /></template>
                </StatCard>
                <StatCard class="col-span-2" icon="clock" tone="amber" label="Tunggakan SPP">
                    <template #value><MoneyText :amount="data.tunggakan" tone="default" size="lg" /></template>
                </StatCard>
            </div>
        </div>
    </AppShell>
</template>
