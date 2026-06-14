<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import SpmbLayout from '@/Layouts/SpmbLayout.vue';
import MdCard from '@/spmb/components/MdCard.vue';
import MdButton from '@/spmb/components/MdButton.vue';
import MdField from '@/spmb/components/MdField.vue';
import FormSection from '@/spmb/components/FormSection.vue';
import WilayahCascade from '@/spmb/components/WilayahCascade.vue';

const props = defineProps({
    candidate: { type: Object, required: true },
    fieldConfiguration: { type: Object, required: true },
    biodataSections: { type: Object, required: true },
    statusCard: { type: Object, required: true },
    stepper: { type: Array, required: true },
    timeline: { type: Array, default: () => [] },
    checklist: { type: Array, required: true },
    mainAction: { type: Object, required: true },
    tracking: { type: Object, required: true },
    bolehDaftarUlang: { type: Boolean, default: false },
});

const SECTION_ICONS = {
    pribadi: '🧑', alamat: '📍', pendidikan: '🎓',
    ayah: '👨', ibu: '👩', wali: '🧑‍🤝‍🧑', kesehatan: '❤️',
};

// Field yang TERLIHAT peserta (mode required|optional). hidden/staf disembunyikan.
const visibleFields = Object.entries(props.fieldConfiguration)
    .filter(([, d]) => d.mode === 'required' || d.mode === 'optional')
    .map(([key, d]) => ({ key, ...d }));

// Section terurut + field-nya (hanya yang punya field terlihat).
const sections = computed(() => Object.entries(props.biodataSections)
    .map(([key, title]) => ({
        key,
        title,
        icon: SECTION_ICONS[key] ?? '•',
        fields: visibleFields.filter((f) => f.section === key),
    }))
    .filter((s) => s.fields.length > 0));

const alamatRequired = computed(() => props.fieldConfiguration.provinsi?.mode === 'required');

// Form: seed dari nilai biodata saat ini (candidate.values), hanya field terlihat.
const form = useForm(
    Object.fromEntries(visibleFields.map((f) => [f.key, props.candidate.values?.[f.key] ?? ''])),
);

const canEdit = computed(() => props.candidate.canEditBiodata);

const submit = () => form.put('/spmb/biodata', { preserveScroll: true });
</script>

<template>
    <Head :title="`Portal SPMB ${candidate.nomorPendaftaran}`" />

    <SpmbLayout brand-header :subtitle="candidate.statusCardSubtitle">
        <div class="space-y-4">
            <!-- Hero status -->
            <MdCard>
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <span class="inline-flex rounded-full bg-[var(--spmb-primary)]/12 px-2.5 py-1 text-[11px] font-semibold text-[var(--spmb-primary)]">
                            {{ statusCard.badge }}
                        </span>
                        <h1 class="mt-2 text-lg font-bold leading-snug text-stone-900">{{ statusCard.title }}</h1>
                        <p class="mt-1 text-[13px] leading-relaxed text-stone-600">{{ statusCard.description }}</p>
                    </div>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <div class="rounded-xl bg-stone-900 px-3 py-2.5 text-white">
                        <div class="text-[10px] uppercase tracking-wider text-white/60">No. Pendaftaran</div>
                        <div class="mt-0.5 text-[15px] font-bold">{{ candidate.nomorPendaftaran }}</div>
                    </div>
                    <div class="rounded-xl bg-stone-100 px-3 py-2.5">
                        <div class="text-[10px] uppercase tracking-wider text-stone-500">Kelengkapan</div>
                        <div class="mt-0.5 text-[15px] font-bold text-stone-900">
                            {{ candidate.completion.filled }} / {{ candidate.completion.total }}
                            <span class="text-[11px] font-medium text-stone-500">wajib</span>
                        </div>
                    </div>
                </div>
            </MdCard>

            <!-- CTA Daftar Ulang (peserta LULUS) -->
            <Link v-if="bolehDaftarUlang" href="/spmb/daftar-ulang" class="block">
                <div class="flex items-center justify-between rounded-2xl bg-emerald-600 px-4 py-3.5 text-white shadow-sm active:brightness-95">
                    <span class="text-[15px] font-semibold">🎉 Anda LULUS — Daftar Ulang</span>
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6" /></svg>
                </div>
            </Link>

            <!-- Aksi cepat (yang tak ada di bottom nav) -->
            <div class="grid grid-cols-2 gap-3">
                <Link href="/spmb/kartu-ujian" class="flex items-center gap-2 rounded-2xl border border-stone-200 bg-white px-3 py-3 text-[13px] font-medium text-stone-800 shadow-sm active:bg-stone-50">
                    <span class="text-lg">🪪</span> Kartu Ujian
                </Link>
                <Link href="/spmb/pengumuman" class="flex items-center gap-2 rounded-2xl border border-stone-200 bg-white px-3 py-3 text-[13px] font-medium text-stone-800 shadow-sm active:bg-stone-50">
                    <span class="text-lg">📣</span> Pengumuman
                </Link>
            </div>

            <!-- Tahap pendaftaran (stepper horizontal) -->
            <div>
                <h2 class="mb-2 px-1 text-[13px] font-semibold text-stone-500">Tahap pendaftaran</h2>
                <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1" style="scrollbar-width: none">
                    <div
                        v-for="(step, index) in stepper"
                        :key="step.title"
                        class="w-32 shrink-0 rounded-2xl border px-3 py-3"
                        :class="{
                            'border-[var(--spmb-primary)]/30 bg-[var(--spmb-primary)]/8': step.state === 'current',
                            'border-stone-200 bg-white': step.state === 'upcoming',
                            'border-stone-900 bg-stone-900 text-white': step.state === 'done',
                        }"
                    >
                        <div class="text-[10px] font-semibold uppercase tracking-wide" :class="step.state === 'done' ? 'text-white/50' : 'text-stone-400'">
                            Tahap {{ index + 1 }}
                        </div>
                        <div class="mt-1 text-[13px] font-bold leading-tight">{{ step.title }}</div>
                    </div>
                </div>
            </div>

            <!-- Biodata peserta (form bertahap) -->
            <div id="biodata-form" class="space-y-3">
                <div class="flex items-center justify-between px-1">
                    <h2 class="text-base font-bold text-stone-900">Biodata peserta</h2>
                    <span v-if="!canEdit" class="rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-[11px] font-semibold text-amber-800">
                        Terkunci
                    </span>
                </div>
                <p class="px-1 text-[13px] leading-relaxed text-stone-600">
                    Lengkapi data ini sejujur-jujurnya. Panitia memakai data yang Anda isi di sini.
                </p>

                <form class="space-y-3" @submit.prevent="submit">
                    <div v-if="Object.keys(form.errors).length" class="rounded-2xl border border-red-200 bg-red-50 p-3.5 text-[13px] text-red-700">
                        <p class="font-semibold">Periksa kembali isian berikut:</p>
                        <ul class="mt-1.5 list-disc pl-5">
                            <li v-for="(message, field) in form.errors" :key="field">{{ message }}</li>
                        </ul>
                    </div>

                    <FormSection
                        v-for="(sec, idx) in sections"
                        :key="sec.key"
                        :title="sec.title"
                        :icon="sec.icon"
                        :default-open="idx === 0"
                    >
                        <div class="grid grid-cols-1 gap-4">
                            <template v-for="f in sec.fields" :key="f.key">
                                <!-- Cascade wilayah: dirender sekali di posisi 'provinsi' -->
                                <WilayahCascade
                                    v-if="f.type === 'wilayah' && f.wilayah === 'provinsi'"
                                    v-model:provinsi="form.provinsi"
                                    v-model:kabupaten-kota="form.kabupaten_kota"
                                    v-model:kecamatan="form.kecamatan"
                                    v-model:desa-kelurahan="form.desa_kelurahan"
                                    :required="alamatRequired"
                                    :disabled="!canEdit"
                                    :errors="form.errors"
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
                    </FormSection>

                    <div class="sticky bottom-2 z-10 pt-1">
                        <MdButton type="submit" :loading="form.processing" :disabled="!canEdit">
                            {{ form.processing ? 'Menyimpan…' : 'Simpan biodata' }}
                        </MdButton>
                    </div>
                </form>
            </div>

            <!-- Checklist -->
            <MdCard>
                <h2 class="mb-3 text-[15px] font-bold text-stone-900">Checklist peserta</h2>
                <div class="space-y-2.5">
                    <div
                        v-for="item in checklist"
                        :key="item.title"
                        class="flex items-start gap-3 rounded-xl border px-3 py-2.5"
                        :class="item.done ? 'border-emerald-200 bg-emerald-50' : 'border-stone-200 bg-stone-50'"
                    >
                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[11px] text-white" :class="item.done ? 'bg-emerald-500' : 'bg-amber-400'">
                            {{ item.done ? '✓' : '!' }}
                        </span>
                        <div>
                            <div class="text-[13px] font-semibold text-stone-900">{{ item.title }}</div>
                            <p class="mt-0.5 text-[12px] leading-relaxed text-stone-600">{{ item.description }}</p>
                        </div>
                    </div>
                </div>
            </MdCard>

            <!-- Riwayat aktivitas -->
            <MdCard v-if="timeline.length">
                <h2 class="mb-3 text-[15px] font-bold text-stone-900">Riwayat aktivitas</h2>
                <ol class="space-y-2.5">
                    <li v-for="(item, idx) in timeline" :key="idx" class="flex items-start gap-3">
                        <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full" :class="idx === 0 ? 'bg-emerald-500' : 'bg-stone-300'" />
                        <div>
                            <div class="text-[13px] font-semibold text-stone-900">{{ item.label }}</div>
                            <div class="text-[11px] text-stone-500">{{ item.tanggal }}</div>
                        </div>
                    </li>
                </ol>
            </MdCard>

            <!-- Tautan status tanpa login -->
            <MdCard>
                <h2 class="text-[15px] font-bold text-stone-900">Status cepat tanpa login</h2>
                <p class="mt-1 text-[12px] leading-relaxed text-stone-500">
                    Tautan ini hanya untuk melihat status ringkas. Mengubah biodata atau unggah dokumen tetap wajib login.
                </p>
                <div class="mt-3 break-all rounded-xl bg-stone-100 px-3 py-2.5 text-[12px] text-stone-700">{{ tracking.url }}</div>
                <a :href="tracking.url" target="_blank" rel="noopener noreferrer" class="mt-3 block">
                    <MdButton variant="tonal">Buka link status</MdButton>
                </a>
            </MdCard>
        </div>
    </SpmbLayout>
</template>
