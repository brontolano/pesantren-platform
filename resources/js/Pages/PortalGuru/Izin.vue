<script setup>
import { Head, useForm, router } from '@inertiajs/vue3'
import PortalGuruLayout from '../../Layouts/PortalGuruLayout.vue'

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

        <section>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-700">Izin Pegawai</p>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900">Izin Harian</h2>
        </section>

        <!-- Antrean approver -->
        <section v-if="antrean.length > 0" class="space-y-3">
            <h3 class="text-sm font-semibold text-slate-700">Menunggu Persetujuan Anda</h3>
            <div v-for="item in antrean" :key="item.id" class="rounded-[1.5rem] bg-white px-5 py-4 shadow">
                <p class="text-base font-semibold text-slate-900">{{ item.employee }}</p>
                <p class="text-sm text-slate-500">
                    {{ item.jenis_label }} · {{ item.tanggal_mulai }} s.d. {{ item.tanggal_selesai }} ({{ item.jumlah_hari }} hari)
                </p>
                <p v-if="item.alasan" class="mt-1 text-sm text-slate-600">{{ item.alasan }}</p>
                <div class="mt-3 flex gap-2">
                    <button class="rounded-full bg-emerald-600 px-4 py-1.5 text-sm font-semibold text-white" @click="putuskan(item.id, 'setujui')">
                        Setujui
                    </button>
                    <button class="rounded-full bg-rose-600 px-4 py-1.5 text-sm font-semibold text-white" @click="putuskan(item.id, 'tolak')">
                        Tolak
                    </button>
                </div>
            </div>
        </section>

        <!-- Form ajukan -->
        <section v-if="employee" class="rounded-[1.5rem] bg-white px-5 py-4 shadow">
            <h3 class="text-sm font-semibold text-slate-700">Ajukan Izin</h3>
            <form class="mt-3 space-y-3" @submit.prevent="ajukan">
                <select v-model="form.jenis" class="w-full rounded-xl border-slate-300 text-sm">
                    <option v-for="j in jenisIzin" :key="j.value" :value="j.value">{{ j.label }}</option>
                </select>
                <div class="grid grid-cols-2 gap-3">
                    <input v-model="form.tanggal_mulai" type="date" required class="rounded-xl border-slate-300 text-sm" />
                    <input v-model="form.tanggal_selesai" type="date" required class="rounded-xl border-slate-300 text-sm" />
                </div>
                <textarea v-model="form.alasan" rows="2" placeholder="Alasan (opsional)" class="w-full rounded-xl border-slate-300 text-sm"></textarea>
                <p v-if="Object.keys(form.errors).length" class="text-sm text-rose-600">
                    {{ Object.values(form.errors)[0] }}
                </p>
                <button type="submit" :disabled="form.processing" class="w-full rounded-full bg-amber-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">
                    Kirim Pengajuan
                </button>
            </form>
        </section>

        <section v-else class="rounded-[1.5rem] bg-white px-5 py-8 text-center shadow">
            <p class="text-base font-semibold text-slate-800">Akun belum tertaut data pegawai</p>
            <p class="mt-2 text-sm leading-6 text-slate-500">Hubungi admin pesantren agar akun Anda ditautkan ke data pegawai (HR).</p>
        </section>

        <!-- Riwayat saya -->
        <section v-if="izinSaya.length > 0" class="space-y-3">
            <h3 class="text-sm font-semibold text-slate-700">Izin Saya</h3>
            <div v-for="item in izinSaya" :key="item.id" class="rounded-[1.5rem] bg-white px-5 py-4 shadow">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold text-slate-900">{{ item.jenis_label }}</p>
                    <span class="rounded-full px-3 py-0.5 text-xs font-semibold" :class="warnaStatus[item.status] || 'bg-slate-100 text-slate-600'">
                        {{ item.status }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-slate-500">{{ item.tanggal_mulai }} s.d. {{ item.tanggal_selesai }} ({{ item.jumlah_hari }} hari)</p>
            </div>
        </section>
    </div>
</template>
