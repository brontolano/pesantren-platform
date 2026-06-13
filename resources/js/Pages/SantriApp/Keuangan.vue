<script setup>
import { Head } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { AppShell, PageHeader, StatCard, SectionCard, ListRow, StatusBadge, MoneyText, IconCircle, UiButton, BottomSheet } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    saldo: { type: Number, default: 0 },
    tagihan: { type: Array, default: () => [] },
    mutasi: { type: Array, default: () => [] },
})

const statusLabel = { belum_bayar: 'Belum bayar', lunas: 'Lunas' }

const totalTagihan = computed(() =>
    props.tagihan.filter((t) => t.status === 'belum_bayar').reduce((sum, t) => sum + (t.jumlah || 0), 0),
)

const caraBayarOpen = ref(false)
</script>

<template>
    <Head title="Keuangan Santri" />

    <AppShell :nav-items="navigation" brand-name="Aplikasi Santri">
        <div class="space-y-5">
            <PageHeader kicker="Dompet" title="Keuangan" subtitle="Tabungan, tagihan & mutasi" />

            <div class="grid grid-cols-2 gap-3">
                <StatCard icon="wallet" tone="emerald" label="Saldo Tabungan">
                    <template #value>
                        <MoneyText :amount="saldo" size="lg" tone="emerald" />
                    </template>
                </StatCard>
                <StatCard icon="receipt" tone="rose" label="Tagihan Belum Lunas">
                    <template #value>
                        <MoneyText :amount="totalTagihan" size="lg" tone="default" />
                    </template>
                </StatCard>
            </div>

            <SectionCard kicker="Tagihan" title="Tagihan Saya" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow
                        v-for="t in tagihan"
                        :key="t.id"
                        :title="t.nama"
                        :subtitle="`Jatuh tempo ${t.jatuhTempo}`"
                    >
                        <template #leading>
                            <IconCircle icon="receipt" :tone="t.status === 'lunas' ? 'emerald' : 'rose'" size="md" />
                        </template>
                        <template #trailing>
                            <div class="flex flex-col items-end gap-1.5">
                                <MoneyText :amount="t.jumlah" size="sm" tone="default" />
                                <StatusBadge :status="t.status" :label="statusLabel[t.status]" size="sm" />
                            </div>
                        </template>
                    </ListRow>
                </div>
            </SectionCard>

            <UiButton variant="primary" block icon-left="info" @click="caraBayarOpen = true">
                Cara Bayar
            </UiButton>

            <SectionCard kicker="Riwayat" title="Mutasi Tabungan" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow
                        v-for="m in mutasi"
                        :key="m.id"
                        :title="m.keterangan"
                        :subtitle="m.tanggal"
                    >
                        <template #leading>
                            <IconCircle
                                :icon="m.arah === 'masuk' ? 'arrow-down-left' : 'arrow-up-right'"
                                :tone="m.arah === 'masuk' ? 'emerald' : 'amber'"
                                size="md"
                            />
                        </template>
                        <template #trailing>
                            <MoneyText
                                :amount="m.jumlah"
                                :sign="m.arah === 'masuk' ? 'plus' : 'minus'"
                                size="sm"
                                :tone="m.arah === 'masuk' ? 'emerald' : 'default'"
                            />
                        </template>
                    </ListRow>
                </div>
            </SectionCard>

            <p class="px-1 text-xs leading-5 text-slate-400">
                Data peragaan. Saldo & tagihan tidak benar-benar terhubung ke bendahara.
            </p>
        </div>

        <BottomSheet v-model="caraBayarOpen" title="Cara Bayar Tagihan">
            <div class="space-y-3 text-sm leading-6 text-slate-600">
                <p>Tagihan dapat dilunasi melalui salah satu cara berikut:</p>
                <ul class="space-y-2">
                    <li class="flex items-start gap-3">
                        <IconCircle icon="bank" tone="blue" size="sm" />
                        <span>Transfer ke rekening pesantren, lalu konfirmasi ke bagian keuangan.</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <IconCircle icon="wallet" tone="emerald" size="sm" />
                        <span>Potong otomatis dari saldo tabungan (minta persetujuan wali).</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <IconCircle icon="cash" tone="amber" size="sm" />
                        <span>Setor tunai langsung ke loket koperasi santri.</span>
                    </li>
                </ul>
            </div>
            <template #footer>
                <UiButton variant="primary" block icon-left="check" @click="caraBayarOpen = false">Mengerti</UiButton>
            </template>
        </BottomSheet>
    </AppShell>
</template>
