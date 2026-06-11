<script setup>
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PortalGuruLayout from '../../Layouts/PortalGuruLayout.vue'

defineOptions({
    layout: PortalGuruLayout,
})

const props = defineProps({
    kajian: { type: Object, required: true },
    peserta: { type: Array, default: () => [] },
    riwayatKelompok: { type: Array, default: () => [] },
    riwayatPerSantri: { type: Object, default: () => ({}) },
})

const isSorogan = computed(() => props.kajian.metode === 'sorogan')

const form = ref({
    santri_id: null,
    posisi: '',
    status: 'lanjut',
    catatan: '',
})
const saving = ref(false)
const savedAt = ref(null)
const errorMessage = ref(null)

const riwayatTerpilih = computed(() => {
    if (!isSorogan.value) {
        return props.riwayatKelompok
    }

    return form.value.santri_id ? (props.riwayatPerSantri[form.value.santri_id] || []) : []
})

function submit() {
    saving.value = true
    errorMessage.value = null

    router.post(`/guru/kitab/${props.kajian.id}`, {
        santri_id: isSorogan.value ? form.value.santri_id : null,
        posisi: form.value.posisi,
        status: form.value.status,
        catatan: form.value.catatan || null,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            savedAt.value = new Date().toLocaleTimeString()
            form.value.posisi = ''
            form.value.catatan = ''
        },
        onError: (errors) => {
            errorMessage.value = Object.values(errors)[0] || 'Data tidak valid.'
        },
        onFinish: () => {
            saving.value = false
        },
    })
}
</script>

<template>
    <div class="space-y-5">
        <Head>
            <title>Capaian Kitab</title>
        </Head>

        <section>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-700">
                Capaian Kitab · {{ kajian.metode }}
            </p>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900">{{ kajian.kitab }}</h2>
            <p class="text-sm text-slate-500">{{ kajian.kelompok || '—' }}</p>
        </section>

        <section class="space-y-3 rounded-[1.5rem] bg-white px-5 py-5 shadow">
            <div v-if="isSorogan">
                <label class="text-xs font-semibold uppercase tracking-wider text-slate-400">Santri</label>
                <select
                    v-model="form.santri_id"
                    class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 focus:border-emerald-600 focus:outline-none"
                >
                    <option :value="null" disabled>— pilih santri —</option>
                    <option v-for="s in peserta" :key="s.id" :value="s.id">{{ s.nama }} ({{ s.nis }})</option>
                </select>
            </div>
            <p v-else class="rounded-2xl bg-amber-50 px-4 py-2 text-sm text-amber-700">
                Satu update untuk SEMUA peserta ({{ peserta.length }} santri) — posisi kelompok.
            </p>

            <div>
                <label class="text-xs font-semibold uppercase tracking-wider text-slate-400">Posisi (halaman/bab)</label>
                <input
                    v-model="form.posisi"
                    type="text"
                    placeholder='Misal: "Bab Thaharah hal. 12"'
                    class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 focus:border-emerald-600 focus:outline-none"
                />
            </div>

            <div class="flex flex-wrap gap-2">
                <button
                    v-for="opsi in ['lanjut', 'ulang', 'khatam']"
                    :key="opsi"
                    type="button"
                    class="rounded-full px-4 py-1.5 text-xs font-bold uppercase tracking-wider transition"
                    :class="form.status === opsi ? 'bg-emerald-700 text-white' : 'bg-slate-100 text-slate-600'"
                    @click="form.status = opsi"
                >
                    {{ opsi }}
                </button>
            </div>

            <textarea
                v-model="form.catatan"
                rows="2"
                placeholder="Catatan (opsional)"
                class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 focus:border-emerald-600 focus:outline-none"
            ></textarea>

            <p v-if="errorMessage" class="rounded-2xl bg-rose-50 px-4 py-2 text-sm font-medium text-rose-700">{{ errorMessage }}</p>
            <p v-if="savedAt" class="rounded-2xl bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-700">
                Capaian tersimpan {{ savedAt }}.
            </p>

            <button
                type="button"
                class="w-full rounded-full bg-emerald-700 px-5 py-3 text-sm font-semibold uppercase tracking-wider text-white disabled:opacity-60"
                :disabled="saving || (isSorogan && !form.santri_id)"
                @click="submit()"
            >
                {{ saving ? 'Menyimpan…' : 'Simpan Capaian' }}
            </button>
        </section>

        <section v-if="riwayatTerpilih.length > 0" class="space-y-2">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Riwayat Terakhir</p>
            <div
                v-for="(r, i) in riwayatTerpilih"
                :key="i"
                class="flex items-center justify-between gap-3 rounded-2xl bg-white px-4 py-3 shadow-sm"
            >
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-900">{{ r.posisi }}</p>
                    <p class="text-xs text-slate-400">{{ r.tanggal }}<span v-if="r.catatan"> · {{ r.catatan }}</span></p>
                </div>
                <span
                    class="rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wider"
                    :class="{
                        'bg-emerald-50 text-emerald-700': r.status === 'lanjut',
                        'bg-amber-50 text-amber-700': r.status === 'ulang',
                        'bg-sky-50 text-sky-700': r.status === 'khatam',
                    }"
                >
                    {{ r.status }}
                </span>
            </div>
        </section>
    </div>
</template>
