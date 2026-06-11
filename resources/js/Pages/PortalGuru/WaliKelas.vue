<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PortalGuruLayout from '../../Layouts/PortalGuruLayout.vue'

defineOptions({
    layout: PortalGuruLayout,
})

const props = defineProps({
    kelas: { type: Object, required: true },
    periode: { type: Object, required: true },
    rekap: { type: Array, default: () => [] },
    rubrik_ubudiyah_url: { type: String, default: null },
})

const terbuka = ref(null) // santri_id yang form catatannya terbuka
const catatanDraft = ref('')
const saving = ref(false)
const errorMessage = ref(null)

function bukaCatatan(row) {
    terbuka.value = row.santri_id
    catatanDraft.value = row.catatan_walikelas || ''
    errorMessage.value = null
}

function simpanCatatan() {
    saving.value = true
    errorMessage.value = null

    router.post(`/guru/wali-kelas/${props.kelas.id}/catatan`, {
        santri_id: terbuka.value,
        catatan: catatanDraft.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            terbuka.value = null
        },
        onError: (errors) => {
            errorMessage.value = Object.values(errors)[0] || 'Catatan tidak valid.'
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
            <title>Wali Kelas</title>
        </Head>

        <section>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-700">Wali Kelas</p>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900">{{ kelas.nama }}</h2>
            <p class="text-sm text-slate-500">{{ periode.nama }} · kehadiran, rata nilai, poin disiplin</p>
            <a
                v-if="rubrik_ubudiyah_url"
                :href="rubrik_ubudiyah_url"
                class="mt-2 inline-block rounded-full bg-slate-100 px-4 py-1.5 text-xs font-semibold text-slate-600"
            >
                Input Rubrik Ubudiyah →
            </a>
        </section>

        <section v-if="rekap.length === 0" class="rounded-[1.5rem] bg-white px-5 py-8 text-center shadow">
            <p class="text-base font-semibold text-slate-800">Belum ada santri aktif di kelas ini</p>
        </section>

        <section v-else class="space-y-2">
            <div v-for="row in rekap" :key="row.santri_id" class="rounded-2xl bg-white px-4 py-3 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-slate-900">{{ row.nama }}</p>
                        <p class="text-xs text-slate-400">{{ row.nis }}</p>
                    </div>
                    <button
                        type="button"
                        class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600"
                        @click="bukaCatatan(row)"
                    >
                        {{ row.catatan_walikelas ? 'Edit Catatan' : '+ Catatan' }}
                    </button>
                </div>

                <div class="mt-2 flex flex-wrap gap-2 text-xs font-semibold">
                    <span class="rounded-full bg-emerald-50 px-3 py-1 text-emerald-700">
                        Hadir {{ row.kehadiran_persen }}%
                    </span>
                    <span class="rounded-full bg-sky-50 px-3 py-1 text-sky-700">
                        Rata Nilai {{ row.rata_nilai ?? '—' }}
                    </span>
                    <span class="rounded-full bg-rose-50 px-3 py-1 text-rose-700">
                        Poin {{ row.poin_disiplin }}
                    </span>
                </div>

                <p v-if="row.catatan_walikelas && terbuka !== row.santri_id" class="mt-2 text-xs italic text-slate-500">
                    "{{ row.catatan_walikelas }}"
                </p>

                <div v-if="terbuka === row.santri_id" class="mt-3 space-y-2">
                    <textarea
                        v-model="catatanDraft"
                        rows="3"
                        placeholder="Catatan wali kelas untuk rapor…"
                        class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 focus:border-emerald-600 focus:outline-none"
                    ></textarea>
                    <p v-if="errorMessage" class="rounded-2xl bg-rose-50 px-4 py-2 text-sm font-medium text-rose-700">
                        {{ errorMessage }}
                    </p>
                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="flex-1 rounded-full bg-emerald-700 px-4 py-2 text-xs font-semibold uppercase tracking-wider text-white disabled:opacity-60"
                            :disabled="saving || !catatanDraft"
                            @click="simpanCatatan()"
                        >
                            {{ saving ? 'Menyimpan…' : 'Simpan ke Rapor' }}
                        </button>
                        <button
                            type="button"
                            class="rounded-full bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-600"
                            @click="terbuka = null"
                        >
                            Batal
                        </button>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
