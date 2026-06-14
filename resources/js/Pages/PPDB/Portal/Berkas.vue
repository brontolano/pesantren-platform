<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import SpmbLayout from '@/Layouts/SpmbLayout.vue';
import MdCard from '@/spmb/components/MdCard.vue';

const props = defineProps({
    nomorPendaftaran: { type: String, default: '' },
    items: { type: Array, default: () => [] },
    kurangWajib: { type: Array, default: () => [] },
    lengkapWajib: { type: Boolean, default: false },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);

const form = useForm({ jenis_dokumen: null, file: null });

function onFileChange(event, jenis) {
    const file = event.target.files?.[0];
    if (!file) return;

    form.jenis_dokumen = jenis;
    form.file = file;
    form.post('/spmb/berkas/upload', {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            form.reset('file');
            event.target.value = '';
        },
    });
}

const sedangUnggah = (jenis) => form.processing && form.jenis_dokumen === jenis;

// Chip status verifikasi (emerald=verified, red=rejected, amber=pending/menunggu, abu=belum).
function chipClass(status, terunggah) {
    if (!terunggah) return 'border-stone-200 bg-stone-50 text-stone-500';
    return {
        verified: 'border-emerald-200 bg-emerald-50 text-emerald-800',
        rejected: 'border-red-200 bg-red-50 text-red-700',
        pending: 'border-amber-200 bg-amber-50 text-amber-800',
    }[status] ?? 'border-amber-200 bg-amber-50 text-amber-800';
}

function statusText(item) {
    if (!item.terunggah) return 'Belum diunggah';
    return item.statusLabel ?? 'Menunggu Verifikasi';
}
</script>

<template>
    <Head title="Berkas Pendaftaran" />

    <SpmbLayout show-back :back-href="'/spmb'" title="Berkas">
        <div class="space-y-4">
            <!-- Kepala -->
            <div class="px-1">
                <h1 class="text-[17px] font-bold leading-snug text-stone-900">Berkas Pendaftaran</h1>
                <p class="mt-0.5 text-[13px] text-stone-500">
                    Unggah dokumen pendaftaran<span v-if="nomorPendaftaran"> &middot; No. {{ nomorPendaftaran }}</span>
                </p>
            </div>

            <!-- Pesan unggah gagal (flash sukses ditangani layout) -->
            <div v-if="form.errors.file" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-[13px] font-medium text-red-700">
                {{ form.errors.file }}
            </div>

            <!-- Ringkasan kelengkapan wajib -->
            <MdCard>
                <div class="flex items-start gap-3">
                    <span
                        class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[13px] text-white"
                        :class="lengkapWajib ? 'bg-emerald-500' : 'bg-amber-400'"
                    >
                        {{ lengkapWajib ? '✓' : '!' }}
                    </span>
                    <div class="min-w-0">
                        <p class="text-[15px] font-bold text-stone-900">
                            {{ lengkapWajib ? 'Dokumen wajib lengkap' : 'Dokumen wajib belum lengkap' }}
                        </p>
                        <p v-if="lengkapWajib" class="mt-0.5 text-[13px] leading-relaxed text-stone-600">
                            Semua dokumen wajib sudah diunggah.
                        </p>
                        <p v-else class="mt-0.5 text-[13px] leading-relaxed text-stone-600">
                            Belum diunggah: <span class="font-semibold text-amber-800">{{ kurangWajib.join(', ') }}</span>
                        </p>
                    </div>
                </div>
            </MdCard>

            <!-- Daftar dokumen -->
            <div class="space-y-3">
                <MdCard v-for="item in items" :key="item.jenis">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-[15px] font-bold text-stone-900">{{ item.label }}</p>
                            <span
                                class="text-[11px] font-semibold uppercase tracking-wide"
                                :class="item.wajib ? 'text-red-600' : 'text-stone-400'"
                            >
                                {{ item.wajib ? 'Wajib' : 'Opsional' }}
                            </span>
                        </div>
                        <span
                            class="shrink-0 rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                            :class="chipClass(item.status, item.terunggah)"
                        >
                            {{ statusText(item) }}
                        </span>
                    </div>

                    <p
                        v-if="item.status === 'rejected' && item.catatan"
                        class="mt-2.5 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-[12px] leading-relaxed text-red-700"
                    >
                        Catatan panitia: {{ item.catatan }}
                    </p>

                    <!-- Kontrol unggah -->
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <input
                            :id="`f-${item.jenis}`"
                            type="file"
                            class="hidden"
                            accept=".pdf,.jpg,.jpeg,.png,.webp"
                            :disabled="form.processing"
                            @change="onFileChange($event, item.jenis)"
                        >
                        <label
                            :for="`f-${item.jenis}`"
                            class="inline-flex h-10 cursor-pointer items-center gap-1.5 rounded-full bg-[var(--spmb-primary)]/12 px-4 text-[13px] font-semibold text-[var(--spmb-primary)] transition active:scale-[0.98] active:bg-[var(--spmb-primary)]/20"
                            :class="form.processing ? 'pointer-events-none opacity-50' : ''"
                        >
                            <span aria-hidden="true">⬆️</span>
                            <span v-if="sedangUnggah(item.jenis)">Mengunggah…</span>
                            <span v-else>{{ item.terunggah ? 'Ganti file' : 'Unggah file' }}</span>
                        </label>
                        <a
                            v-if="item.terunggah && item.id"
                            :href="`/ppdb/file/dokumen/${item.id}`"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex h-10 items-center gap-1.5 rounded-full border border-stone-200 bg-white px-4 text-[13px] font-semibold text-stone-700 transition active:scale-[0.98] active:bg-stone-50"
                        >👁️ Lihat</a>
                        <span class="text-[11px] text-stone-400">maks 5 MB</span>
                    </div>
                </MdCard>
            </div>
        </div>
    </SpmbLayout>
</template>
