<script setup>
import { Head, useForm, router } from '@inertiajs/vue3'
import PortalGuruLayout from '../../Layouts/PortalGuruLayout.vue'
import { PageHeader, SectionCard, ListRow, StatusBadge, UiField, UiInput, UiButton, EmptyState, IconCircle } from '@/Shared'

defineOptions({
    layout: PortalGuruLayout,
})

const props = defineProps({
    employee: { type: Object, default: null },
    jenisIzin: { type: Array, default: () => [] },
    izinSaya: { type: Array, default: () => [] },
    antrean: { type: Array, default: () => [] },
})

const form = useForm({
    jenis: props.jenisIzin[0]?.value ?? 'pulang_kampung',
    tanggal_mulai: '',
    tanggal_selesai: '',
    alasan: '',
    client_ref: crypto.randomUUID(),
})

function ajukan() {
    form.post('/guru/izin', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('tanggal_mulai', 'tanggal_selesai', 'alasan')
            form.client_ref = crypto.randomUUID()
        },
    })
}

function putuskan(id, aksi) {
    router.post(`/guru/izin/${id}/${aksi}`, {}, { preserveScroll: true })
}

const warnaStatus = {
    diajukan: 'bg-amber-50 text-amber-700',
    disetujui: 'bg-emerald-50 text-emerald-700',
    ditolak: 'bg-rose-50 text-rose-700',
}
</script>

<template>
    <div class="space-y-5">
        <Head>
            <title>Izin Pegawai</title>
        </Head>

        <PageHeader
            kicker="Izin Pegawai"
            title="Izin Harian"
        />

        <!-- Antrean approver -->
        <SectionCard
            v-if="antrean.length > 0"
            title="Menunggu Persetujuan Anda"
            flush
        >
            <div class="divide-y divide-slate-100">
                <ListRow
                    v-for="item in antrean"
                    :key="item.id"
                    :title="item.employee"
                    :subtitle="`${item.jenis_label} · ${item.tanggal_mulai} s.d. ${item.tanggal_selesai} (${item.jumlah_hari} hari)`"
                >
                    <template #leading>
                        <IconCircle icon="calendar" tone="amber" />
                    </template>
                    <p v-if="item.alasan" class="text-sm text-slate-600">{{ item.alasan }}</p>
                    <div class="mt-3 flex gap-2">
                        <UiButton variant="primary" size="sm" iconLeft="check" @click="putuskan(item.id, 'setujui')">
                            Setujui
                        </UiButton>
                        <UiButton variant="danger" size="sm" iconLeft="x-circle" @click="putuskan(item.id, 'tolak')">
                            Tolak
                        </UiButton>
                    </div>
                </ListRow>
            </div>
        </SectionCard>

        <!-- Form ajukan -->
        <SectionCard v-if="employee" title="Ajukan Izin">
            <form class="space-y-3" @submit.prevent="ajukan">
                <UiField label="Jenis Izin">
                    <template #default="{ fieldId, describedby }">
                        <select
                            :id="fieldId"
                            v-model="form.jenis"
                            :aria-describedby="describedby"
                            class="min-h-[48px] w-full rounded-2xl bg-white px-4 text-sm font-medium text-slate-900 ring-1 ring-inset ring-slate-200 transition-all duration-200 hover:ring-slate-300 focus:outline-none focus:ring-2 focus:ring-[var(--wali-primary,#059669)]"
                        >
                            <option v-for="j in jenisIzin" :key="j.value" :value="j.value">{{ j.label }}</option>
                        </select>
                    </template>
                </UiField>
                <div class="grid grid-cols-2 gap-3">
                    <UiField label="Tanggal Mulai" required>
                        <template #default="{ fieldId, describedby, invalid }">
                            <UiInput :id="fieldId" v-model="form.tanggal_mulai" type="date" :aria-describedby="describedby" :invalid="invalid" required />
                        </template>
                    </UiField>
                    <UiField label="Tanggal Selesai" required>
                        <template #default="{ fieldId, describedby, invalid }">
                            <UiInput :id="fieldId" v-model="form.tanggal_selesai" type="date" :aria-describedby="describedby" :invalid="invalid" required />
                        </template>
                    </UiField>
                </div>
                <UiField label="Alasan" hint="Opsional">
                    <template #default="{ fieldId, describedby }">
                        <UiInput :id="fieldId" v-model="form.alasan" type="textarea" :rows="2" placeholder="Alasan (opsional)" :aria-describedby="describedby" />
                    </template>
                </UiField>
                <p v-if="Object.keys(form.errors).length" class="text-sm font-medium text-rose-600">
                    {{ Object.values(form.errors)[0] }}
                </p>
                <UiButton type="submit" variant="primary" size="md" block :loading="form.processing" :disabled="form.processing">
                    Kirim Pengajuan
                </UiButton>
            </form>
        </SectionCard>

        <EmptyState
            v-else
            icon="user"
            title="Akun belum tertaut data pegawai"
            message="Hubungi admin pesantren agar akun Anda ditautkan ke data pegawai (HR)."
        />

        <!-- Riwayat saya -->
        <SectionCard
            v-if="izinSaya.length > 0"
            title="Izin Saya"
            flush
        >
            <div class="divide-y divide-slate-100">
                <ListRow
                    v-for="item in izinSaya"
                    :key="item.id"
                    :title="item.jenis_label"
                    :subtitle="`${item.tanggal_mulai} s.d. ${item.tanggal_selesai} (${item.jumlah_hari} hari)`"
                >
                    <template #trailing>
                        <StatusBadge
                            :status="item.status === 'disetujui' ? 'terverifikasi' : item.status === 'diajukan' ? 'menunggu' : item.status"
                            :label="item.status"
                            size="sm"
                        />
                    </template>
                </ListRow>
            </div>
        </SectionCard>
    </div>
</template>
