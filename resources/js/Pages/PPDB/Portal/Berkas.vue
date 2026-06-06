<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import SpmbLayout from '../../../Layouts/SpmbLayout.vue';

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

function statusClass(status, terunggah) {
    if (!terunggah) return 'bg-stone-100 text-stone-500';
    return {
        verified: 'bg-emerald-100 text-emerald-700',
        rejected: 'bg-rose-100 text-rose-700',
        pending: 'bg-amber-100 text-amber-700',
    }[status] ?? 'bg-amber-100 text-amber-700';
}

function statusText(item) {
    if (!item.terunggah) return 'Belum diunggah';
    return item.statusLabel ?? 'Menunggu Verifikasi';
}
</script>

<template>
    <Head title="Berkas Pendaftaran" />

    <SpmbLayout>
        <div class="px-4 py-5 space-y-4">
            <div>
                <h1 class="text-lg font-semibold text-stone-900">Berkas Pendaftaran</h1>
                <p class="text-sm text-stone-500">
                    Unggah dokumen<span v-if="nomorPendaftaran"> &middot; No. {{ nomorPendaftaran }}</span>
                </p>
            </div>

            <div v-if="flashSuccess" class="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ flashSuccess }}
            </div>
            <div v-if="form.errors.file" class="rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ form.errors.file }}
            </div>

            <!-- Ringkasan kelengkapan wajib -->
            <div
                class="rounded-xl px-4 py-3 text-sm"
                :class="lengkapWajib ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800'"
            >
                <template v-if="lengkapWajib">✓ Semua dokumen wajib sudah diunggah.</template>
                <template v-else>
                    Dokumen wajib yang belum diunggah:
                    <span class="font-medium">{{ kurangWajib.join(', ') }}</span>
                </template>
            </div>

            <!-- Daftar dokumen -->
            <ul class="space-y-2">
                <li
                    v-for="item in items"
                    :key="item.jenis"
                    class="rounded-xl border border-black/5 bg-white px-4 py-3 shadow-sm"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-stone-900">{{ item.label }}</p>
                            <span
                                class="text-[11px] font-medium uppercase tracking-wide"
                                :class="item.wajib ? 'text-rose-600' : 'text-stone-400'"
                            >
                                {{ item.wajib ? 'Wajib' : 'Opsional' }}
                            </span>
                        </div>
                        <span
                            class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium"
                            :class="statusClass(item.status, item.terunggah)"
                        >
                            {{ statusText(item) }}
                        </span>
                    </div>

                    <p
                        v-if="item.status === 'rejected' && item.catatan"
                        class="mt-2 rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-700"
                    >
                        Catatan: {{ item.catatan }}
                    </p>

                    <!-- Kontrol unggah -->
                    <div class="mt-2">
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
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-[var(--spmb-primary)] px-3 py-1.5 text-xs font-medium text-[var(--spmb-primary)] transition hover:bg-[var(--spmb-primary)] hover:text-white"
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
                            class="ml-2 inline-flex items-center gap-1 rounded-lg border border-stone-200 px-3 py-1.5 text-xs font-medium text-stone-600 transition hover:border-stone-400"
                        >👁️ Lihat</a>
                        <span class="ml-2 text-[11px] text-stone-400">maks 5 MB</span>
                    </div>
                </li>
            </ul>
        </div>
    </SpmbLayout>
</template>
