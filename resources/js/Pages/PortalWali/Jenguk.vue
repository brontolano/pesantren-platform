<script setup>
import { Head, router, useForm } from '@inertiajs/vue3'
import PortalWaliLayout from '../../Layouts/PortalWaliLayout.vue'

defineOptions({ layout: PortalWaliLayout })

const props = defineProps({
    children: { type: Array, default: () => [] },
    jadwal: { type: Object, default: () => ({}) },
    pengajuan: { type: Array, default: () => [] },
})

const form = useForm({
    santri_id: props.children[0]?.id ?? '',
    tanggal: '',
    catatan: '',
    client_ref: crypto.randomUUID(),
})

function submit() {
    form.post('/wali/jenguk', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('tanggal', 'catatan')
            form.client_ref = crypto.randomUUID()
        },
    })
}

function batalkan(id) {
    if (!confirm('Batalkan pengajuan jenguk ini?')) return
    router.post(`/wali/jenguk/${id}/batal`, {}, { preserveScroll: true })
}

const warnaStatus = {
    diajukan: 'bg-amber-50 text-amber-700',
    dikonfirmasi: 'bg-emerald-50 text-emerald-700',
    ditolak: 'bg-rose-50 text-rose-700',
    hadir: 'bg-sky-50 text-sky-700',
    batal: 'bg-slate-100 text-slate-500',
}
</script>

<template>
    <div class="space-y-5">
        <Head><title>Jenguk Santri</title></Head>

        <section class="rounded-[2rem] bg-slate-950 px-5 py-5 text-white shadow-[0_24px_60px_-40px_rgba(15,23,42,0.8)]">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-white/60">Penjengukan</p>
            <h2 class="mt-3 font-jost text-3xl leading-tight">Ajukan Slot Jenguk</h2>
            <p class="mt-2 text-sm text-white/70">
                Hari jenguk: <strong class="capitalize">{{ (jadwal.hari ?? []).join(', ') }}</strong>
                · {{ jadwal.jam_mulai }}–{{ jadwal.jam_selesai }}
                · kuota {{ jadwal.kuota_per_slot }} keluarga/hari.
            </p>
        </section>

        <form class="space-y-3 rounded-[1.75rem] border border-black/5 bg-white px-4 py-4 shadow" @submit.prevent="submit">
            <label class="grid gap-2 text-sm font-semibold text-slate-700">
                Anak
                <select v-model="form.santri_id" required class="rounded-xl border border-slate-300 px-3 py-2 font-normal">
                    <option v-for="anak in children" :key="anak.id" :value="anak.id">{{ anak.name ?? anak.nama }}</option>
                </select>
            </label>

            <label class="grid gap-2 text-sm font-semibold text-slate-700">
                Tanggal jenguk
                <input v-model="form.tanggal" type="date" required class="rounded-xl border border-slate-300 px-3 py-2 font-normal">
            </label>
            <p v-if="form.errors.tanggal" class="text-xs text-rose-600">{{ form.errors.tanggal }}</p>

            <label class="grid gap-2 text-sm font-semibold text-slate-700">
                Catatan (opsional)
                <textarea v-model="form.catatan" rows="2" class="rounded-xl border border-slate-300 px-3 py-2 font-normal" placeholder="Misal: datang 4 orang"></textarea>
            </label>

            <button type="submit" :disabled="form.processing" class="w-full rounded-2xl bg-violet-600 px-4 py-3 font-semibold text-white shadow disabled:opacity-50">
                Ajukan Jenguk
            </button>
        </form>

        <section class="space-y-2">
            <h3 class="text-sm font-semibold text-slate-700">Riwayat Pengajuan</h3>
            <div v-for="p in pengajuan" :key="p.id" class="flex items-center justify-between rounded-2xl bg-white px-4 py-3 shadow-sm">
                <div>
                    <p class="font-semibold text-slate-900">{{ p.santri }}</p>
                    <p class="text-xs text-slate-500">{{ p.tanggal }}<span v-if="p.catatan"> · {{ p.catatan }}</span></p>
                </div>
                <div class="flex items-center gap-2">
                    <span :class="['rounded-full px-3 py-1 text-xs font-semibold capitalize', warnaStatus[p.status] ?? 'bg-slate-100 text-slate-600']">{{ p.status }}</span>
                    <button
                        v-if="['diajukan', 'dikonfirmasi'].includes(p.status)"
                        type="button"
                        class="rounded-full border border-rose-200 px-3 py-1 text-xs font-semibold text-rose-600"
                        @click="batalkan(p.id)"
                    >
                        Batalkan
                    </button>
                </div>
            </div>
            <p v-if="pengajuan.length === 0" class="text-xs text-slate-500">Belum ada pengajuan.</p>
        </section>
    </div>
</template>
