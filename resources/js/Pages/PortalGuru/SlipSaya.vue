<script setup>
import { Head } from '@inertiajs/vue3'
import PortalGuruLayout from '../../Layouts/PortalGuruLayout.vue'
import { PageHeader, SectionCard, ListRow, StatusBadge, MoneyText, EmptyState, UiButton } from '@/Shared'

defineOptions({
    layout: PortalGuruLayout,
})

defineProps({
    slip: { type: Array, default: () => [] },
    bisaCetak: { type: Boolean, default: false },
})

import { formatRupiah as rupiah } from '@/Shared/format'
</script>

<template>
    <div class="space-y-5">
        <Head>
            <title>Slip Saya</title>
        </Head>

        <PageHeader
            kicker="Slip Gaji"
            title="Slip Saya"
            subtitle="Hanya slip milik Anda sendiri yang tampil di sini."
        />

        <EmptyState
            v-if="slip.length === 0"
            icon="receipt"
            title="Belum ada slip gaji"
            message="Slip gaji Anda akan muncul di sini setelah diterbitkan."
        />

        <SectionCard v-for="s in slip" :key="s.id">
            <div class="flex items-center justify-between gap-2">
                <p class="text-base font-semibold text-slate-900">{{ s.periode }}<span v-if="s.tipe && s.tipe !== 'gaji'" class="ml-1 text-xs text-slate-400">({{ s.tipe }})</span></p>
                <StatusBadge
                    :status="s.status === 'final' ? 'terverifikasi' : 'menunggu'"
                    :label="s.status === 'final' ? 'Final' : 'Draf'"
                    size="sm"
                />
            </div>

            <div class="mt-3 divide-y divide-slate-100">
                <ListRow title="Bruto">
                    <template #trailing>
                        <MoneyText :amount="s.bruto" size="sm" tone="default" />
                    </template>
                </ListRow>
                <ListRow title="PPh 21">
                    <template #trailing>
                        <MoneyText :amount="s.pph21" size="sm" tone="rose" signed sign="minus" />
                    </template>
                </ListRow>
                <ListRow v-if="s.bpjs" title="BPJS">
                    <template #trailing>
                        <MoneyText :amount="s.bpjs" size="sm" tone="rose" signed sign="minus" />
                    </template>
                </ListRow>
                <ListRow v-if="s.potongan_cuti" title="Potongan Cuti">
                    <template #trailing>
                        <MoneyText :amount="s.potongan_cuti" size="sm" tone="rose" signed sign="minus" />
                    </template>
                </ListRow>
                <ListRow>
                    <template #title>
                        <span class="font-semibold text-slate-900">Neto</span>
                    </template>
                    <template #trailing>
                        <MoneyText :amount="s.neto" size="md" tone="emerald" />
                    </template>
                </ListRow>
            </div>

            <UiButton
                v-if="s.print_url && bisaCetak"
                as="a"
                :href="s.print_url"
                target="_blank"
                rel="noopener"
                variant="secondary"
                size="md"
                block
                iconLeft="receipt"
                class="mt-3"
            >
                Cetak Slip
            </UiButton>
        </SectionCard>
    </div>
</template>
