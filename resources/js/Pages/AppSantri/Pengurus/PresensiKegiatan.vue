<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    qisms: Array,
    kegiatanList: Array, // [{id, nama, qism, mulai, status}]
    santriList: Array, // [{id, nama, nis}]
});

const kegiatanId = ref(props.kegiatanList[0]?.id ?? null);

const form = useForm({
    santri_id: '',
    status: 'hadir',
    keterangan: '',
});

function submit() {
    if (!kegiatanId.value) return;
    form.post(`/app/pengurus/kegiatan/${kegiatanId.value}/presensi`, {
        preserveScroll: true,
        onSuccess: () => form.reset('santri_id', 'keterangan'),
    });
}
</script>

<template>
    <div class="min-h-screen bg-stone-50 pb-10">
        <header class="bg-gradient-to-b from-amber-700 to-amber-500 px-5 pb-10 pt-8 text-white">
            <div class="mx-auto max-w-md">
                <Link href="/app/pengurus" class="text-xs text-amber-100">← Mode Pengurus</Link>
                <h1 class="mt-1 text-xl font-bold">Presensi Kegiatan</h1>
                <p class="mt-1 text-xs text-amber-100">Domain: {{ qisms.join(', ') }}</p>
            </div>
        </header>

        <main class="mx-auto -mt-6 max-w-md px-5">
            <div v-if="kegiatanList.length === 0"
                class="rounded-2xl border border-dashed border-stone-300 bg-white/60 px-5 py-6 text-center text-sm text-stone-500">
                Belum ada kegiatan untuk domain qism Anda.
            </div>

            <form v-else class="space-y-4 rounded-2xl border border-stone-200 bg-white p-5 shadow-sm" @submit.prevent="submit">
                <p v-if="$page.props.flash?.sukses" class="rounded-lg bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700">
                    {{ $page.props.flash.sukses }}
                </p>

                <div>
                    <label class="text-xs font-semibold text-stone-600">Kegiatan</label>
                    <select v-model="kegiatanId" class="mt-1 w-full rounded-lg border-stone-300 text-sm">
                        <option v-for="k in kegiatanList" :key="k.id" :value="k.id">{{ k.nama }} ({{ k.qism }})</option>
                    </select>
                </div>

                <div>
                    <label class="text-xs font-semibold text-stone-600">Santri</label>
                    <select v-model="form.santri_id" required class="mt-1 w-full rounded-lg border-stone-300 text-sm">
                        <option value="" disabled>Pilih santri…</option>
                        <option v-for="s in santriList" :key="s.id" :value="s.id">{{ s.nama }} ({{ s.nis }})</option>
                    </select>
                </div>

                <div>
                    <label class="text-xs font-semibold text-stone-600">Status</label>
                    <div class="mt-1 grid grid-cols-4 gap-2">
                        <button v-for="st in ['hadir', 'izin', 'sakit', 'alpha']" :key="st" type="button"
                            class="rounded-lg border px-2 py-2 text-xs font-semibold capitalize"
                            :class="form.status === st ? 'border-amber-600 bg-amber-50 text-amber-700' : 'border-stone-200 text-stone-500'"
                            @click="form.status = st">
                            {{ st }}
                        </button>
                    </div>
                </div>

                <div>
                    <label class="text-xs font-semibold text-stone-600">Keterangan (opsional)</label>
                    <input v-model="form.keterangan" type="text" class="mt-1 w-full rounded-lg border-stone-300 text-sm" />
                </div>

                <button type="submit" :disabled="form.processing || !form.santri_id"
                    class="w-full rounded-xl bg-amber-600 py-3 text-sm font-bold text-white disabled:opacity-50">
                    Simpan Presensi
                </button>
            </form>
        </main>
    </div>
</template>
