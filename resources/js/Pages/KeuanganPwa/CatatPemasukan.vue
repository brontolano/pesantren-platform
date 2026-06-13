<script setup>
import { Head } from '@inertiajs/vue3'
import { reactive, ref, computed } from 'vue'
import { AppShell, PageHeader, UiField, UiInput, UiButton } from '@/Shared'

defineProps({ navigation: { type: Array, default: () => [] } })

const form = reactive({ tagihan_id: '', nominal: '', metode: 'tunai' })
const status = ref('')
const statusTone = ref('info')
const saving = ref(false)

const statusClass = computed(() => ({
    success: 'bg-emerald-50 text-emerald-800 ring-emerald-100',
    error: 'bg-rose-50 text-rose-800 ring-rose-100',
    info: 'bg-slate-50 text-slate-700 ring-slate-200',
}[statusTone.value]))

async function simpan() {
    saving.value = true
    status.value = 'Menyimpan...'
    statusTone.value = 'info'
    try {
        const res = await fetch('/api/keuangan/pemasukan', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(form),
        })
        const data = await res.json().catch(() => ({}))
        if (res.ok) {
            status.value = `Pembayaran tercatat & dibukukan (Rp ${data.nominal}).`
            statusTone.value = 'success'
        } else {
            status.value = 'Gagal: ' + (data.message || res.status)
            statusTone.value = 'error'
        }
    } catch (e) {
        status.value = 'Tidak ada koneksi. Coba lagi saat online.'
        statusTone.value = 'error'
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <Head title="Catat Pemasukan" />

    <AppShell :nav-items="navigation" brand-name="Keuangan Lapangan">
        <form class="space-y-4" @submit.prevent="simpan">
            <PageHeader kicker="Penerimaan" title="Catat Pemasukan" subtitle="Pembayaran tunai dari santri / wali" />

            <UiField label="ID Tagihan" required hint="Nomor tagihan yang akan dibayar">
                <template #default="{ fieldId }">
                    <UiInput :id="fieldId" v-model="form.tagihan_id" type="number" inputmode="numeric" placeholder="mis. 1024" />
                </template>
            </UiField>

            <UiField label="Nominal bayar" required>
                <template #default="{ fieldId }">
                    <UiInput :id="fieldId" v-model="form.nominal" type="number" inputmode="numeric" placeholder="0" />
                </template>
            </UiField>

            <UiButton type="submit" variant="primary" block :loading="saving" icon-left="check">Catat &amp; Verifikasi</UiButton>

            <p v-if="status" class="rounded-2xl px-4 py-3 text-sm font-medium ring-1 ring-inset" :class="statusClass">
                {{ status }}
            </p>
        </form>
    </AppShell>
</template>
