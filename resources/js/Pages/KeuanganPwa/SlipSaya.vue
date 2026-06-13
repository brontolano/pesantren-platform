<script setup>
import { Head } from '@inertiajs/vue3'
import { ref, onMounted } from 'vue'
import { AppShell, PageHeader, SectionCard, ListRow, StatusBadge, MoneyText, EmptyState, LoadingState, IconCircle } from '@/Shared'

defineProps({ navigation: { type: Array, default: () => [] } })

const slip = ref([])
const error = ref('')
const loading = ref(true)

onMounted(async () => {
    try {
        const res = await fetch('/api/keuangan/slip-saya', {
            headers: { Accept: 'application/json' }, credentials: 'same-origin',
        })
        if (res.ok) slip.value = (await res.json()).slip || []
        else error.value = 'Gagal memuat slip.'
    } catch (e) {
        error.value = 'Tidak ada koneksi.'
    } finally {
        loading.value = false
    }
})
</script>

<template>
    <Head title="Slip Gaji Saya" />

    <AppShell :nav-items="navigation" brand-name="Keuangan Lapangan">
        <div class="space-y-5">
            <PageHeader kicker="Penggajian" title="Slip Gaji Saya" subtitle="Riwayat slip final Anda" />

            <LoadingState v-if="loading" variant="list" :count="3" />

            <template v-else>
                <SectionCard v-if="slip.length" flush>
                    <div class="divide-y divide-slate-100">
                        <ListRow v-for="s in slip" :key="s.periode">
                            <template #leading><IconCircle icon="cash" tone="emerald" /></template>
                            <template #title>{{ s.periode }}</template>
                            <template #subtitle>Bruto <MoneyText :amount="s.bruto" size="sm" tone="muted" /> · PPh21 <MoneyText :amount="s.pph21" size="sm" tone="muted" /></template>
                            <template #trailing>
                                <MoneyText :amount="s.neto" tone="emerald" size="md" />
                                <StatusBadge status="lunas" label="Diterima" size="sm" class="mt-1" />
                            </template>
                        </ListRow>
                    </div>
                </SectionCard>

                <EmptyState
                    v-else
                    icon="document"
                    :title="error || 'Belum ada slip'"
                    :message="error ? 'Periksa koneksi lalu coba lagi.' : 'Slip gaji final Anda akan muncul di sini.'"
                />
            </template>
        </div>
    </AppShell>
</template>
