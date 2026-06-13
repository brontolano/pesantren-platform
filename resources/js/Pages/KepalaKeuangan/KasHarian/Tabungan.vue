<script setup>
import { onMounted, ref } from 'vue'
import KepalaKeuanganLayout from '@/Layouts/KepalaKeuanganLayout.vue'
import { api } from '@/keuangan-pwa/api.js'
import { formatRupiah } from '@/keuangan-pwa/format.js'
import {
    PageHeader,
    SectionCard,
    ListRow,
    IconCircle,
    MoneyText,
    EmptyState,
    LoadingState,
} from '@/Shared'

defineOptions({ layout: KepalaKeuanganLayout })
defineProps({ can: Object })

const loading = ref(true)
const data = ref(null)

onMounted(async () => {
    try { data.value = await api.get('/tabungan/ringkasan') } catch { /* */ }
    loading.value = false
})
</script>

<template>
    <div class="space-y-5">
        <PageHeader kicker="Kas Harian" title="Tabungan Santri" />

        <div v-if="loading" class="space-y-4">
            <LoadingState variant="hero" :count="1" />
            <LoadingState variant="list" :count="4" />
        </div>

        <template v-else-if="data">
            <!-- Total dana titipan -->
            <div class="relative overflow-hidden rounded-3xl bg-brand-grad-hero px-6 py-6 text-white shadow-soft">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/75">Total Dana Titipan</p>
                <div class="mt-1.5">
                    <MoneyText :amount="data.totalSaldo" size="xl" tone="inherit" />
                </div>
                <p class="mt-2 text-xs text-white/70">{{ data.jumlahRekening ?? 0 }} rekening aktif</p>
            </div>

            <!-- Mutasi terakhir -->
            <SectionCard title="Mutasi Terakhir" flush>
                <div v-if="data.mutasiTerakhir?.length" class="divide-y divide-slate-100">
                    <ListRow
                        v-for="m in data.mutasiTerakhir"
                        :key="m.id"
                        :title="m.nama_santri"
                        :subtitle="m.jenis"
                    >
                        <template #leading>
                            <IconCircle
                                :icon="m.jenis === 'setor' ? 'arrow-down-left' : 'arrow-up-right'"
                                :tone="m.jenis === 'setor' ? 'emerald' : 'rose'"
                            />
                        </template>
                        <template #trailing>
                            <MoneyText
                                :amount="m.nominal"
                                size="sm"
                                :tone="m.jenis === 'setor' ? 'emerald' : 'rose'"
                                :sign="m.jenis === 'setor' ? 'plus' : 'minus'"
                            />
                        </template>
                    </ListRow>
                </div>
                <EmptyState
                    v-else
                    icon="inbox"
                    title="Belum ada mutasi"
                />
            </SectionCard>
        </template>
    </div>
</template>
