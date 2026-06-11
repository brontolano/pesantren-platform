<script setup>
import { Link, useForm } from '@inertiajs/vue3';

defineProps({
    qisms: Array,
    santriList: Array, // [{id, nama, nis}]
    jenisList: Array, // [{id, nama, kategori, poin}]
});

const form = useForm({
    santri_id: '',
    jenis_pelanggaran_id: '',
    kronologi: '',
    lokasi: '',
});

function submit() {
    form.post('/app/pengurus/pelanggaran', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <div class="min-h-screen bg-stone-50 pb-10">
        <header class="bg-gradient-to-b from-amber-700 to-amber-500 px-5 pb-10 pt-8 text-white">
            <div class="mx-auto max-w-md">
                <Link href="/app/pengurus" class="text-xs text-amber-100">← Mode Pengurus</Link>
                <h1 class="mt-1 text-xl font-bold">Catat Pelanggaran</h1>
            </div>
        </header>

        <main class="mx-auto -mt-6 max-w-md px-5">
            <form class="space-y-4 rounded-2xl border border-stone-200 bg-white p-5 shadow-sm" @submit.prevent="submit">
                <p v-if="$page.props.flash?.sukses" class="rounded-lg bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700">
                    {{ $page.props.flash.sukses }}
                </p>

                <div>
                    <label class="text-xs font-semibold text-stone-600">Santri</label>
                    <select v-model="form.santri_id" required class="mt-1 w-full rounded-lg border-stone-300 text-sm">
                        <option value="" disabled>Pilih santri…</option>
                        <option v-for="s in santriList" :key="s.id" :value="s.id">{{ s.nama }} ({{ s.nis }})</option>
                    </select>
                </div>

                <div>
                    <label class="text-xs font-semibold text-stone-600">Jenis pelanggaran</label>
                    <select v-model="form.jenis_pelanggaran_id" required class="mt-1 w-full rounded-lg border-stone-300 text-sm">
                        <option value="" disabled>Pilih jenis…</option>
                        <option v-for="j in jenisList" :key="j.id" :value="j.id">{{ j.nama }} ({{ j.poin }} poin)</option>
                    </select>
                </div>

                <div>
                    <label class="text-xs font-semibold text-stone-600">Kronologi</label>
                    <textarea v-model="form.kronologi" required rows="3" class="mt-1 w-full rounded-lg border-stone-300 text-sm" />
                </div>

                <div>
                    <label class="text-xs font-semibold text-stone-600">Lokasi (opsional)</label>
                    <input v-model="form.lokasi" type="text" class="mt-1 w-full rounded-lg border-stone-300 text-sm" />
                </div>

                <button type="submit" :disabled="form.processing"
                    class="w-full rounded-xl bg-amber-600 py-3 text-sm font-bold text-white disabled:opacity-50">
                    Kirim Laporan
                </button>
                <p class="text-center text-[11px] text-stone-400">
                    Laporan akan diperiksa & diverifikasi ustadz sebelum poin berlaku.
                </p>
            </form>
        </main>
    </div>
</template>
