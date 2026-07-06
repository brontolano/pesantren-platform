<script setup>
import { Head, router, useForm } from '@inertiajs/vue3'
import PortalWaliLayout from '../../Layouts/PortalWaliLayout.vue'
import { UiCard, UiButton, EmptyState } from '@/Components/Ui'

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
    diajukan: 'bg-amber-100 text-amber-700',
    dikonfirmasi: 'bg-emerald-100 text-emerald-700',
    ditolak: 'bg-rose-100 text-rose-700',
    hadir: 'bg-sky-100 text-sky-700',
    batal: 'bg-slate-100 text-slate-500',
}

const inputClass = 'w-full rounded-2xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm font-normal text-slate-900 shadow-sm outline-none transition focus:border-[var(--brand-primary)] focus:ring-2 focus:ring-[var(--brand-primary)]/20'
</script>

<template>
    <div class="space-y-5">
        <Head><title>Jenguk Santri</title></Head>

        <!-- HERO -->
        <UiCard variant="hero-dark" padding="lg">
            <p class="text-[11px] font-bold uppercase tracking-wide text-white/70">Penjengukan</p>
            <h2 class="mt-1 text-2xl font-extrabold leading-tight tracking-tight">Ajukan Slot Jenguk</h2>
            <p class="mt-2 text-sm leading-6 text-white/80">
                Hari jenguk: <strong class="capitalize">{{ (jadwal.hari ?? []).join(', ') }}</strong>
                · {{ jadwal.jam_mulai }}–{{ jadwal.jam_selesai }}
                · kuota {{ jadwal.kuota_per_slot }} keluarga/hari.
            </p>
        </UiCard>

        <!-- FORM -->
        <UiCard padding="md">
            <form class="space-y-3.5" @submit.prevent="submit">
                <label class="grid gap-1.5 text-sm font-bold text-slate-700">
                    Anak
                    <select v-model="form.santri_id" required :class="inputClass">
                        <option v-for="anak in children" :key="anak.id" :value="anak.id">{{ anak.name ?? anak.nama }}</option>
                    </select>
                </label>

                <label class="grid gap-1.5 text-sm font-bold text-slate-700">
                    Tanggal jenguk
                    <input v-model="form.tanggal" type="date" required :class="inputClass">
                </label>
                <p v-if="form.errors.tanggal" class="-mt-1.5 text-xs font-medium text-rose-600">{{ form.errors.tanggal }}</p>

                <label class="grid gap-1.5 text-sm font-bold text-slate-700">
                    Catatan (opsional)
                    <textarea v-model="form.catatan" rows="2" :class="inputClass" placeholder="Misal: datang 4 orang"></textarea>
                </label>

                <UiButton type="submit" variant="primary" size="lg" block :loading="form.processing" :disabled="form.processing">
                    Ajukan Jenguk
                </UiButton>
            </form>
        </UiCard>

        <!-- RIWAYAT -->
        <section class="space-y-3">
            <div class="flex items-center gap-2.5">
                <span class="h-1.5 w-1.5 rounded-full bg-brand-grad" />
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Riwayat Pengajuan</p>
            </div>

            <div v-if="pengajuan.length" class="space-y-3">
                <UiCard v-for="p in pengajuan" :key="p.id" padding="md">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-bold text-slate-900">{{ p.santri }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ p.tanggal }}<span v-if="p.catatan"> · {{ p.catatan }}</span></p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <span :class="['rounded-full px-3 py-1 text-xs font-bold capitalize', warnaStatus[p.status] ?? 'bg-slate-100 text-slate-600']">{{ p.status }}</span>
                            <button
                                v-if="['diajukan', 'dikonfirmasi'].includes(p.status)"
                                type="button"
                                class="rounded-full border border-rose-200 px-3 py-1 text-xs font-bold text-rose-600 transition active:scale-95"
                                @click="batalkan(p.id)"
                            >
                                Batalkan
                            </button>
                        </div>
                    </div>
                </UiCard>
            </div>
            <EmptyState v-else icon="calendar" title="Belum ada pengajuan" message="Slot jenguk yang Anda ajukan akan tampil di sini." />
        </section>
    </div>
</template>
