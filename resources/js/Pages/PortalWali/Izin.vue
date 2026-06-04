<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import PortalWaliLayout from '../../Layouts/PortalWaliLayout.vue'

defineOptions({
    layout: PortalWaliLayout,
})

const props = defineProps({
    children: {
        type: Array,
        default: () => [],
    },
    submissions: {
        type: Array,
        default: () => [],
    },
})

const form = useForm({
    santri_id: props.children[0]?.id ?? '',
    jenis_izin: 'sakit',
    tanggal_mulai: '',
    tanggal_selesai: '',
    alasan: '',
    catatan_wali: '',
})

function submit() {
    form.post('/wali/izin')
}
</script>

<template>
    <div class="space-y-5">
        <Head>
            <title>Izin Wali</title>
        </Head>

        <section class="rounded-[2rem] bg-slate-950 px-5 py-5 text-white shadow-[0_24px_60px_-40px_rgba(15,23,42,0.8)]">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-white/60">
                Pengajuan Izin
            </p>
            <h2 class="mt-3 font-serif text-3xl leading-tight">
                Form Izin Santri
            </h2>
        </section>

        <form class="space-y-3 rounded-[1.75rem] border border-black/5 bg-white px-4 py-4 shadow-[0_20px_45px_-40px_rgba(15,23,42,0.7)]" @submit.prevent="submit">
            <label class="grid gap-2 text-sm font-semibold text-slate-700">
                Pilih santri
                <select v-model="form.santri_id" class="rounded-2xl border border-slate-200 px-4 py-3 text-sm">
                    <option
                        v-for="child in children"
                        :key="child.id"
                        :value="child.id"
                    >
                        {{ child.nama }}
                    </option>
                </select>
            </label>

            <label class="grid gap-2 text-sm font-semibold text-slate-700">
                Jenis izin
                <select v-model="form.jenis_izin" class="rounded-2xl border border-slate-200 px-4 py-3 text-sm">
                    <option value="sakit">Sakit</option>
                    <option value="pulang">Pulang</option>
                    <option value="khusus">Khusus</option>
                </select>
            </label>

            <div class="grid grid-cols-2 gap-3">
                <label class="grid gap-2 text-sm font-semibold text-slate-700">
                    Tanggal mulai
                    <input v-model="form.tanggal_mulai" type="date" class="rounded-2xl border border-slate-200 px-4 py-3 text-sm">
                </label>
                <label class="grid gap-2 text-sm font-semibold text-slate-700">
                    Tanggal selesai
                    <input v-model="form.tanggal_selesai" type="date" class="rounded-2xl border border-slate-200 px-4 py-3 text-sm">
                </label>
            </div>

            <label class="grid gap-2 text-sm font-semibold text-slate-700">
                Alasan
                <textarea v-model="form.alasan" rows="3" class="rounded-2xl border border-slate-200 px-4 py-3 text-sm"></textarea>
            </label>

            <label class="grid gap-2 text-sm font-semibold text-slate-700">
                Catatan wali
                <textarea v-model="form.catatan_wali" rows="3" class="rounded-2xl border border-slate-200 px-4 py-3 text-sm"></textarea>
            </label>

            <button
                type="submit"
                class="w-full rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-semibold uppercase tracking-[0.18em] text-white"
                :disabled="form.processing"
            >
                Kirim pengajuan
            </button>
        </form>

        <section class="space-y-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-700">
                    Riwayat
                </p>
                <h3 class="mt-1 font-serif text-2xl text-slate-900">
                    Pengajuan Terakhir
                </h3>
            </div>

            <div v-if="submissions.length" class="grid gap-3">
                <article
                    v-for="submission in submissions"
                    :key="submission.id"
                    class="rounded-[1.5rem] border border-black/5 bg-white px-4 py-4 shadow-[0_20px_45px_-40px_rgba(15,23,42,0.7)]"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                                {{ submission.jenisIzin }}
                            </p>
                            <h4 class="mt-1 text-lg font-semibold text-slate-900">
                                {{ submission.santri }}
                            </h4>
                        </div>
                        <div class="rounded-full bg-slate-100 px-3 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-slate-700">
                            {{ submission.status }}
                        </div>
                    </div>
                    <p class="mt-3 text-sm text-slate-500">
                        {{ submission.tanggalMulai }} - {{ submission.tanggalSelesai }}
                    </p>
                    <p class="mt-2 text-sm text-slate-600">
                        {{ submission.alasan }}
                    </p>
                </article>
            </div>
        </section>
    </div>
</template>
