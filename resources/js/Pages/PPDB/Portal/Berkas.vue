<script setup>
import { Head } from '@inertiajs/vue3';
import SpmbLayout from '../../../Layouts/SpmbLayout.vue';

const props = defineProps({
    nomorPendaftaran: { type: String, default: '' },
    items: { type: Array, default: () => [] },
    kurangWajib: { type: Array, default: () => [] },
    lengkapWajib: { type: Boolean, default: false },
});

// Warna badge per status verifikasi dokumen.
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
                    Kelengkapan dokumen<span v-if="nomorPendaftaran"> &middot; No. {{ nomorPendaftaran }}</span>
                </p>
            </div>

            <!-- Ringkasan kelengkapan wajib -->
            <div
                class="rounded-xl px-4 py-3 text-sm"
                :class="lengkapWajib ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800'"
            >
                <template v-if="lengkapWajib">
                    ✓ Semua dokumen wajib sudah diunggah.
                </template>
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
                </li>
            </ul>
        </div>
    </SpmbLayout>
</template>
