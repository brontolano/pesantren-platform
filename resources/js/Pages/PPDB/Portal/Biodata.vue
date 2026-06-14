<script setup>
// Wizard pengisian Biodata — layar fokus terpisah dari Beranda. Satu bagian per
// langkah + langkah Review, progress bar, Kembali/Lanjut, auto-simpan tiap Lanjut
// (anti data hilang utk wali gaptek). Dropdown "Alamat sama dengan" tetap ada.
import { computed, reactive, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import SpmbLayout from '@/Layouts/SpmbLayout.vue';
import MdButton from '@/spmb/components/MdButton.vue';
import MdField from '@/spmb/components/MdField.vue';
import WilayahCascade from '@/spmb/components/WilayahCascade.vue';

const props = defineProps({
    candidate: { type: Object, required: true },
    fieldConfiguration: { type: Object, required: true },
    biodataSections: { type: Object, required: true },
});

const SECTION_ICONS = {
    pribadi: '🧑', alamat: '📍', pendidikan: '🎓',
    ayah: '👨', ibu: '👩', wali: '🧑‍🤝‍🧑', pembiayaan: '💳', kesehatan: '❤️',
};

const visibleFields = Object.entries(props.fieldConfiguration)
    .filter(([, d]) => d.mode === 'required' || d.mode === 'optional')
    .map(([key, d]) => ({ key, ...d }));

const sections = Object.entries(props.biodataSections)
    .map(([key, title]) => ({
        key, title, icon: SECTION_ICONS[key] ?? '•',
        fields: visibleFields.filter((f) => f.section === key),
    }))
    .filter((s) => s.fields.length > 0);

const form = useForm(
    Object.fromEntries(visibleFields.map((f) => [f.key, props.candidate.values?.[f.key] ?? ''])),
);
const canEdit = computed(() => props.candidate.canEditBiodata);

// ── Navigasi wizard ──────────────────────────────────────────────────────────
const step = ref(0);
const totalSteps = computed(() => sections.length + 1); // +1 = Review
const isReview = computed(() => step.value === sections.length);
const currentSection = computed(() => sections[step.value] ?? null);
const progress = computed(() => Math.round(((step.value + 1) / totalSteps.value) * 100));

const toTop = () => window.scrollTo({ top: 0, behavior: 'smooth' });

const next = () => {
    if (!canEdit.value) { if (step.value < sections.length) step.value++; toTop(); return; }
    form.put('/spmb/biodata', {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { if (step.value < sections.length) step.value++; toTop(); },
    });
};
const back = () => { if (step.value > 0) { step.value--; toTop(); } };
const goToStep = (i) => { step.value = i; toTop(); };
const finish = () => {
    if (!canEdit.value) { router.visit('/spmb'); return; }
    form.put('/spmb/biodata', { preserveScroll: true, onSuccess: () => router.visit('/spmb') });
};

// ── Cascade + salin alamat (sama seperti sebelumnya) ─────────────────────────
const scope = (key) => key.replace('provinsi', '');
const copySources = (pihak) => ({
    ayah: [{ key: 'santri', label: 'Domisili Santri', scope: '' }],
    ibu: [{ key: 'santri', label: 'Domisili Santri', scope: '' }, { key: 'ayah', label: 'Ayah', scope: '_ayah' }],
    wali: [{ key: 'santri', label: 'Domisili Santri', scope: '' }, { key: 'ayah', label: 'Ayah', scope: '_ayah' }, { key: 'ibu', label: 'Ibu', scope: '_ibu' }],
}[pihak] ?? []);
const alamatSama = reactive({ ayah: '', ibu: '', wali: '' });
const ALAMAT_BASES = ['alamat', 'rt_rw', 'provinsi', 'kabupaten_kota', 'kecamatan', 'desa_kelurahan', 'kode_pos'];
const copyAlamat = (fromScope, toPihak) => {
    if (!canEdit.value) return;
    const toScope = `_${toPihak}`;
    ALAMAT_BASES.forEach((base) => { form[base + toScope] = form[base + fromScope] ?? ''; });
};
const onSamaDengan = (pihak, key) => {
    alamatSama[pihak] = key;
    const src = copySources(pihak).find((s) => s.key === key);
    if (src) copyAlamat(src.scope, pihak);
};

// ── Review: tampilan nilai + kelengkapan ─────────────────────────────────────
const displayValue = (f) => {
    const v = form[f.key];
    if (v === '' || v === null || v === undefined) return '—';
    if (f.type === 'select' && f.options) {
        return f.options.find((o) => o.value === v)?.label ?? v;
    }
    return v;
};
// Field wajib yang masih kosong (untuk peringatan di Review).
const missingRequired = computed(() => visibleFields.filter(
    (f) => f.mode === 'required' && (form[f.key] === '' || form[f.key] === null),
));
</script>

<template>
    <Head title="Biodata Peserta" />

    <SpmbLayout title="Biodata Peserta" :subtitle="candidate.nomorPendaftaran" show-back back-href="/spmb" :bottom-nav="false">
        <!-- Progress -->
        <div class="mb-4">
            <div class="mb-1.5 flex items-center justify-between text-[12px] font-medium text-stone-500">
                <span>Langkah {{ step + 1 }} dari {{ totalSteps }}</span>
                <span>{{ progress }}%</span>
            </div>
            <div class="h-1.5 w-full overflow-hidden rounded-full bg-stone-200">
                <div class="h-full rounded-full bg-[var(--spmb-primary)] transition-all duration-300" :style="{ width: progress + '%' }" />
            </div>
        </div>

        <div v-if="!canEdit" class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-[13px] text-amber-800">
            Biodata sedang dikunci panitia — Anda hanya bisa melihat.
        </div>

        <!-- Error format (jika ada) -->
        <div v-if="Object.keys(form.errors).length" class="mb-4 rounded-2xl border border-red-200 bg-red-50 p-3.5 text-[13px] text-red-700">
            <p class="font-semibold">Periksa kembali isian berikut:</p>
            <ul class="mt-1.5 list-disc pl-5">
                <li v-for="(message, field) in form.errors" :key="field">{{ message }}</li>
            </ul>
        </div>

        <div class="pb-28">
            <!-- LANGKAH ISIAN -->
            <section v-if="!isReview && currentSection" :key="currentSection.key">
                <div class="mb-4 flex items-center gap-2.5">
                    <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-[var(--spmb-primary)]/12 text-lg" aria-hidden="true">{{ currentSection.icon }}</span>
                    <h1 class="text-lg font-bold text-stone-900">{{ currentSection.title }}</h1>
                </div>

                <div class="grid grid-cols-1 gap-4">
                    <template v-for="f in currentSection.fields" :key="f.key">
                        <!-- "Alamat sama dengan" untuk ayah/ibu/wali -->
                        <div
                            v-if="f.key === 'alamat_' + currentSection.key && copySources(currentSection.key).length"
                            class="rounded-xl bg-stone-100 px-3 py-3"
                        >
                            <MdField
                                type="select"
                                label="Alamat sama dengan"
                                :model-value="alamatSama[currentSection.key]"
                                :options="copySources(currentSection.key).map((s) => ({ value: s.key, label: s.label }))"
                                :disabled="!canEdit"
                                help="Pilih untuk menyalin alamat tersebut ke sini. Biarkan kosong untuk isi manual."
                                @update:model-value="(v) => onSamaDengan(currentSection.key, v)"
                            />
                        </div>

                        <WilayahCascade
                            v-if="f.type === 'wilayah' && f.wilayah === 'provinsi'"
                            :scope="scope(f.key)"
                            :provinsi="form['provinsi' + scope(f.key)]"
                            :kabupaten-kota="form['kabupaten_kota' + scope(f.key)]"
                            :kecamatan="form['kecamatan' + scope(f.key)]"
                            :desa-kelurahan="form['desa_kelurahan' + scope(f.key)]"
                            :required="f.mode === 'required'"
                            :disabled="!canEdit"
                            :errors="form.errors"
                            @update:provinsi="(v) => (form['provinsi' + scope(f.key)] = v)"
                            @update:kabupaten-kota="(v) => (form['kabupaten_kota' + scope(f.key)] = v)"
                            @update:kecamatan="(v) => (form['kecamatan' + scope(f.key)] = v)"
                            @update:desa-kelurahan="(v) => (form['desa_kelurahan' + scope(f.key)] = v)"
                        />
                        <template v-else-if="f.type === 'wilayah'" />
                        <MdField
                            v-else
                            v-model="form[f.key]"
                            :type="f.type"
                            :label="f.label"
                            :options="f.options || []"
                            :required="f.mode === 'required'"
                            :disabled="!canEdit"
                            :placeholder="f.placeholder || ''"
                            :help="f.help || ''"
                            :error="form.errors[f.key]"
                        />
                    </template>
                </div>
            </section>

            <!-- LANGKAH REVIEW -->
            <section v-else>
                <div class="mb-4 flex items-center gap-2.5">
                    <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-[var(--spmb-primary)]/12 text-lg" aria-hidden="true">✅</span>
                    <h1 class="text-lg font-bold text-stone-900">Periksa & Simpan</h1>
                </div>

                <div
                    v-if="missingRequired.length"
                    class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-[13px] text-amber-800"
                >
                    Masih ada <b>{{ missingRequired.length }}</b> data wajib yang kosong. Anda tetap bisa menyimpan dan melengkapinya nanti.
                </div>
                <div v-else class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-[13px] text-emerald-800">
                    Semua data wajib sudah terisi. Alhamdulillah 🎉
                </div>

                <div class="space-y-3">
                    <div v-for="(sec, i) in sections" :key="sec.key" class="rounded-2xl border border-stone-200 bg-white p-4 shadow-sm">
                        <div class="mb-2 flex items-center justify-between">
                            <h2 class="text-[14px] font-bold text-stone-900">{{ sec.icon }} {{ sec.title }}</h2>
                            <button type="button" class="text-[12px] font-semibold text-[var(--spmb-primary)]" @click="goToStep(i)">Ubah</button>
                        </div>
                        <dl class="space-y-1.5">
                            <div v-for="f in sec.fields" :key="f.key" class="flex justify-between gap-3 text-[13px]">
                                <dt class="text-stone-500">{{ f.label }}</dt>
                                <dd class="max-w-[60%] truncate text-right font-medium text-stone-800">{{ displayValue(f) }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </section>
        </div>

        <!-- Footer navigasi (fixed) -->
        <div class="fixed inset-x-0 bottom-0 z-30 border-t border-black/5 bg-white/95 backdrop-blur" style="padding-bottom: env(safe-area-inset-bottom)">
            <div class="mx-auto flex w-full max-w-screen-sm items-center gap-3 px-4 py-3">
                <MdButton v-if="step > 0" variant="ghost" :block="false" class="flex-1" @click="back">Kembali</MdButton>
                <MdButton v-if="!isReview" :loading="form.processing" :disabled="!canEdit && false" class="flex-[2]" @click="next">
                    Lanjut
                </MdButton>
                <MdButton v-else :loading="form.processing" class="flex-[2]" @click="finish">
                    {{ canEdit ? 'Simpan Biodata' : 'Selesai' }}
                </MdButton>
            </div>
        </div>
    </SpmbLayout>
</template>
