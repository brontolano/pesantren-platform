<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { UiCard, UiButton, EmptyState } from '@/Components/Ui';

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

const statusStyle = {
    hadir: 'border-emerald-600 bg-emerald-50 text-emerald-700',
    izin: 'border-sky-600 bg-sky-50 text-sky-700',
    sakit: 'border-amber-500 bg-amber-50 text-amber-700',
    alpha: 'border-rose-600 bg-rose-50 text-rose-700',
};
const inputClass = 'mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition focus:border-[var(--brand-primary)] focus:ring-2 focus:ring-[var(--brand-primary)]/20';
</script>

<template>
    <div class="min-h-screen bg-[#fffaf3] pb-10">
        <main class="mx-auto max-w-md space-y-5 px-5 pt-5">
            <!-- HERO -->
            <UiCard variant="hero-dark" padding="lg">
                <Link href="/app/pengurus" class="text-xs font-semibold text-white/70 transition hover:text-white">← Mode Pengurus</Link>
                <h1 class="mt-1 text-2xl font-extrabold leading-tight tracking-tight">Presensi Kegiatan</h1>
                <p class="mt-1 text-sm text-white/80">Domain: {{ qisms.join(', ') }}</p>
            </UiCard>

            <EmptyState
                v-if="kegiatanList.length === 0"
                icon="calendar"
                title="Belum ada kegiatan"
                message="Belum ada kegiatan untuk domain qism Anda."
            />

            <UiCard v-else padding="md">
                <form class="space-y-4" @submit.prevent="submit">
                    <p v-if="$page.props.flash?.sukses" class="rounded-xl bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700">
                        {{ $page.props.flash.sukses }}
                    </p>

                    <div>
                        <label class="text-xs font-bold text-slate-600">Kegiatan</label>
                        <select v-model="kegiatanId" :class="inputClass">
                            <option v-for="k in kegiatanList" :key="k.id" :value="k.id">{{ k.nama }} ({{ k.qism }})</option>
                        </select>
                    </div>

                    <div>
                        <label class="text-xs font-bold text-slate-600">Santri</label>
                        <select v-model="form.santri_id" required :class="inputClass">
                            <option value="" disabled>Pilih santri…</option>
                            <option v-for="s in santriList" :key="s.id" :value="s.id">{{ s.nama }} ({{ s.nis }})</option>
                        </select>
                    </div>

                    <div>
                        <label class="text-xs font-bold text-slate-600">Status</label>
                        <div class="mt-1 grid grid-cols-4 gap-2">
                            <button v-for="st in ['hadir', 'izin', 'sakit', 'alpha']" :key="st" type="button"
                                class="rounded-xl border px-2 py-2 text-xs font-bold capitalize transition active:scale-95"
                                :class="form.status === st ? statusStyle[st] : 'border-slate-200 text-slate-500'"
                                @click="form.status = st">
                                {{ st }}
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-bold text-slate-600">Keterangan (opsional)</label>
                        <input v-model="form.keterangan" type="text" :class="inputClass" />
                    </div>

                    <UiButton type="submit" variant="primary" size="lg" block
                        :loading="form.processing" :disabled="form.processing || !form.santri_id">
                        Simpan Presensi
                    </UiButton>
                </form>
            </UiCard>
        </main>
    </div>
</template>
