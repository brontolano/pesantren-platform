<script setup>
import { Head, router, useForm } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import { AppShell, PageHeader, SectionCard, UiField, UiInput, UiButton, IconCircle, MoneyText, EmptyState } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    santri: { type: Object, default: null },
    nisDicari: { type: String, default: '' },
})

const nis = ref(props.nisDicari ?? '')

function cari() {
    router.get('/pos-kantin/kasir', { nis: nis.value }, { preserveState: true, replace: true })
}

const form = useForm({
    santri_id: props.santri?.id ?? '',
    nominal: '',
    pin: '',
    keterangan: '',
})

watch(() => props.santri, (s) => {
    form.santri_id = s?.id ?? ''
})

function bayar() {
    form.post('/pos-kantin/kasir/belanja', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('nominal', 'pin', 'keterangan')
        },
    })
}
</script>

<template>
    <Head title="POS Kantin — Kasir" />

    <AppShell :nav-items="navigation" brand-name="POS Kantin">
        <div class="space-y-5">
            <PageHeader kicker="Transaksi" title="Kasir" subtitle="Cari santri via NIS, lalu catat belanja" />

            <SectionCard>
                <div class="flex gap-2">
                    <UiInput v-model="nis" placeholder="Ketik/scan NIS santri…" class="flex-1" @keydown.enter="cari" />
                    <UiButton variant="secondary" icon-left="search" @click="cari">Cari</UiButton>
                </div>
            </SectionCard>

            <SectionCard v-if="santri">
                <div class="flex items-center gap-3">
                    <IconCircle icon="user" tone="emerald" variant="gradient" size="lg" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-base font-bold text-slate-900">{{ santri.nama }}</p>
                        <p class="text-xs text-slate-500">NIS {{ santri.nis }}</p>
                    </div>
                    <MoneyText :amount="santri.saldo" size="sm" tone="emerald" />
                </div>
            </SectionCard>

            <EmptyState
                v-else-if="nisDicari"
                icon="user"
                title="Santri tidak ditemukan"
                :message="`Tidak ada santri dengan NIS ${nisDicari}.`"
            />

            <SectionCard v-if="santri">
                <form class="space-y-4" @submit.prevent="bayar">
                    <UiField label="Nominal Belanja" :error="form.errors.nominal" required>
                        <template #default="{ fieldId }">
                            <UiInput :id="fieldId" v-model="form.nominal" type="number" placeholder="0" />
                        </template>
                    </UiField>

                    <UiField label="PIN Santri" :error="form.errors.pin" required>
                        <template #default="{ fieldId }">
                            <UiInput :id="fieldId" v-model="form.pin" type="password" inputmode="numeric" placeholder="••••" />
                        </template>
                    </UiField>

                    <UiField label="Keterangan (opsional)" :error="form.errors.keterangan">
                        <template #default="{ fieldId }">
                            <UiInput :id="fieldId" v-model="form.keterangan" placeholder="mis. Jajan siang" />
                        </template>
                    </UiField>

                    <UiButton type="submit" variant="primary" block size="lg" icon-left="check" :disabled="form.processing">
                        Catat Belanja
                    </UiButton>
                </form>
            </SectionCard>
        </div>
    </AppShell>
</template>
