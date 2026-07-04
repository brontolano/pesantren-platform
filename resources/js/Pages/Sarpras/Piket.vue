<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import { AppShell, PageHeader, SectionCard, ListRow, IconCircle, StatusBadge, EmptyState, UiField, UiInput, UiButton } from '@/Shared'

const props = defineProps({
    areaOptions: { type: Array, default: () => [] },
    ceklisHariIni: { type: Array, default: () => [] },
    navigation: { type: Array, default: () => [] },
})

const navItems = computed(() => props.navigation ?? [])

const form = useForm({
    kode_qr: '',
    status: 'bersih',
    catatan: '',
})

const selectClass =
    'w-full rounded-2xl bg-white px-4 min-h-[48px] py-3 text-sm font-medium text-slate-900 ring-1 ring-inset ring-slate-200 transition-all duration-200 hover:ring-slate-300 focus:outline-none focus:ring-2 focus:ring-[var(--wali-primary,#059669)]'

function simpan() {
    form.post('/sarpras/piket/scan', {
        preserveScroll: true,
        onSuccess: () => { form.reset('kode_qr', 'catatan') },
    })
}
</script>

<template>
    <Head title="Sarpras — Piket" />

    <AppShell :nav-items="navItems" brand-name="Sarpras">
        <div class="space-y-6">
            <PageHeader kicker="Sarpras" title="Ceklis Piket" subtitle="Scan/ketik kode QR area piket" />

            <form class="space-y-4" @submit.prevent="simpan">
                <UiField label="Kode Area (scan atau ketik)" :error="form.errors.kode_qr" required>
                    <template #default="{ fieldId }">
                        <UiInput :id="fieldId" v-model="form.kode_qr" placeholder="mis. AREA-01" autofocus />
                    </template>
                </UiField>

                <UiField label="Status">
                    <template #default="{ fieldId }">
                        <select :id="fieldId" v-model="form.status" :class="selectClass">
                            <option value="bersih">Bersih</option>
                            <option value="kotor">Kotor</option>
                            <option value="rusak">Rusak</option>
                        </select>
                    </template>
                </UiField>

                <UiField label="Catatan (opsional)" :error="form.errors.catatan">
                    <template #default="{ fieldId }">
                        <UiInput :id="fieldId" v-model="form.catatan" type="textarea" :rows="2" />
                    </template>
                </UiField>

                <UiButton type="submit" variant="primary" block icon-left="check" :disabled="form.processing">
                    Catat Ceklis
                </UiButton>
            </form>

            <section>
                <p class="mb-3 font-jost text-[11px] font-semibold uppercase tracking-wider text-emerald-700">
                    Ceklis Hari Ini
                </p>
                <SectionCard flush>
                    <div v-if="ceklisHariIni.length" class="divide-y divide-slate-100">
                        <ListRow v-for="c in ceklisHariIni" :key="c.id" :title="c.area ?? '—'" :subtitle="c.petugas">
                            <template #leading>
                                <IconCircle icon="check-circle" tone="emerald" size="sm" />
                            </template>
                            <template #trailing>
                                <div class="flex flex-col items-end gap-1">
                                    <StatusBadge :status="c.status" size="sm" />
                                    <span class="text-xs text-slate-400">{{ c.jam }}</span>
                                </div>
                            </template>
                        </ListRow>
                    </div>
                    <EmptyState v-else icon="check-circle" title="Belum ada ceklis" message="Belum ada area yang di-ceklis hari ini." />
                </SectionCard>
            </section>
        </div>
    </AppShell>
</template>
