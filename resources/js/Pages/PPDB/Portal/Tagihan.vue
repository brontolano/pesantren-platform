<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SpmbLayout from '../../../Layouts/SpmbLayout.vue';

const props = defineProps({
    nomorPendaftaran: { type: String, default: '' },
    items: { type: Array, default: () => [] },
    totalTagihan: { type: Number, default: 0 },
    totalLunas: { type: Number, default: 0 },
    sisa: { type: Number, default: 0 },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);

const form = useForm({ file: null });
const uploadingId = ref(null);

function onBuktiChange(event, id) {
    const file = event.target.files?.[0];
    if (!file) return;
    uploadingId.value = id;
    form.file = file;
    form.post(`/spmb/tagihan/${id}/bukti`, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            form.reset('file');
            event.target.value = '';
            uploadingId.value = null;
        },
    });
}

const rupiah = (n) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n || 0);

function statusClass(item) {
    if (item.lunas) return 'bg-emerald-100 text-emerald-700';
    return {
        menunggu_verifikasi: 'bg-amber-100 text-amber-700',
        dibatalkan: 'bg-stone-100 text-stone-500',
    }[item.status] ?? 'bg-rose-100 text-rose-700';
}

const labelTipe = (t) => (t || '').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
const labelStatus = (s) => (s || '').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
</script>

<template>
    <Head title="Tagihan Pendaftaran" />

    <SpmbLayout>
        <div class="px-4 py-5 space-y-4">
            <div>
                <h1 class="text-lg font-semibold text-stone-900">Tagihan Pendaftaran</h1>
                <p class="text-sm text-stone-500">
                    Rincian biaya<span v-if="nomorPendaftaran"> &middot; No. {{ nomorPendaftaran }}</span>
                </p>
            </div>

            <div v-if="flashSuccess" class="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ flashSuccess }}
            </div>
            <div v-if="form.errors.file" class="rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ form.errors.file }}
            </div>

            <!-- Ringkasan -->
            <div class="rounded-2xl bg-[var(--spmb-primary)] px-4 py-4 text-white">
                <div class="text-xs uppercase tracking-wide opacity-80">Sisa tagihan</div>
                <div class="mt-1 text-2xl font-semibold">{{ rupiah(sisa) }}</div>
                <div class="mt-2 flex justify-between text-xs opacity-90">
                    <span>Total: {{ rupiah(totalTagihan) }}</span>
                    <span>Terbayar: {{ rupiah(totalLunas) }}</span>
                </div>
            </div>

            <!-- Daftar tagihan -->
            <ul v-if="items.length" class="space-y-2">
                <li
                    v-for="item in items"
                    :key="item.id"
                    class="rounded-xl border border-black/5 bg-white px-4 py-3 shadow-sm"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-stone-900">{{ labelTipe(item.tipe) || 'Tagihan' }}</p>
                            <p v-if="item.jatuhTempo" class="text-xs text-stone-500">Jatuh tempo: {{ item.jatuhTempo }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="font-semibold text-stone-900">{{ rupiah(item.nominal) }}</p>
                            <span class="mt-0.5 inline-block rounded-full px-2 py-0.5 text-[11px] font-medium" :class="statusClass(item)">
                                {{ labelStatus(item.status) || 'Belum Bayar' }}
                            </span>
                        </div>
                    </div>
                    <p v-if="item.keterangan" class="mt-1 text-xs text-stone-500">{{ item.keterangan }}</p>

                    <!-- Unggah bukti transfer (hanya bila belum lunas) -->
                    <div v-if="!item.lunas" class="mt-2 flex items-center gap-2">
                        <input
                            :id="`b-${item.id}`"
                            type="file"
                            class="hidden"
                            accept=".pdf,.jpg,.jpeg,.png,.webp"
                            :disabled="form.processing"
                            @change="onBuktiChange($event, item.id)"
                        >
                        <label
                            :for="`b-${item.id}`"
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-[var(--spmb-primary)] px-3 py-1.5 text-xs font-medium text-[var(--spmb-primary)] transition hover:bg-[var(--spmb-primary)] hover:text-white"
                            :class="form.processing ? 'pointer-events-none opacity-50' : ''"
                        >
                            <span aria-hidden="true">🧾</span>
                            <span v-if="uploadingId === item.id">Mengunggah…</span>
                            <span v-else>{{ item.buktiAda ? 'Ganti bukti' : 'Unggah bukti transfer' }}</span>
                        </label>
                        <span v-if="item.buktiAda" class="text-[11px] text-emerald-600">✓ bukti terkirim</span>
                    </div>
                </li>
            </ul>

            <div v-else class="rounded-xl border border-black/5 bg-white px-4 py-6 text-center text-sm text-stone-500 shadow-sm">
                Belum ada tagihan.
            </div>
        </div>
    </SpmbLayout>
</template>
