<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SpmbLayout from '@/Layouts/SpmbLayout.vue';
import MdCard from '@/spmb/components/MdCard.vue';

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

// Chip status tagihan (emerald=lunas, amber=menunggu verifikasi, abu=dibatalkan, red=belum bayar).
function chipClass(item) {
    if (item.lunas) return 'border-emerald-200 bg-emerald-50 text-emerald-800';
    return {
        menunggu_verifikasi: 'border-amber-200 bg-amber-50 text-amber-800',
        dibatalkan: 'border-stone-200 bg-stone-50 text-stone-500',
    }[item.status] ?? 'border-red-200 bg-red-50 text-red-700';
}

const labelTipe = (t) => (t || '').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
const labelStatus = (s) => (s || '').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
</script>

<template>
    <Head title="Tagihan Pendaftaran" />

    <SpmbLayout show-back :back-href="'/spmb'" title="Tagihan">
        <div class="space-y-4">
            <!-- Kepala -->
            <div class="px-1">
                <h1 class="text-[17px] font-bold leading-snug text-stone-900">Tagihan Pendaftaran</h1>
                <p class="mt-0.5 text-[13px] text-stone-500">
                    Rincian biaya pendaftaran<span v-if="nomorPendaftaran"> &middot; No. {{ nomorPendaftaran }}</span>
                </p>
            </div>

            <!-- Pesan unggah gagal (flash sukses ditangani layout) -->
            <div v-if="form.errors.file" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-[13px] font-medium text-red-700">
                {{ form.errors.file }}
            </div>

            <!-- Ringkasan sisa tagihan -->
            <MdCard :padded="false">
                <div class="bg-[var(--spmb-primary)] px-5 py-5 text-white">
                    <div class="text-[11px] font-semibold uppercase tracking-wider text-white/70">Sisa tagihan</div>
                    <div class="mt-1 text-2xl font-bold">{{ rupiah(sisa) }}</div>
                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <div class="rounded-xl bg-white/15 px-3 py-2">
                            <div class="text-[10px] uppercase tracking-wide text-white/60">Total</div>
                            <div class="mt-0.5 text-[15px] font-bold">{{ rupiah(totalTagihan) }}</div>
                        </div>
                        <div class="rounded-xl bg-white/15 px-3 py-2">
                            <div class="text-[10px] uppercase tracking-wide text-white/60">Terbayar</div>
                            <div class="mt-0.5 text-[15px] font-bold">{{ rupiah(totalLunas) }}</div>
                        </div>
                    </div>
                </div>
            </MdCard>

            <!-- Daftar tagihan -->
            <div v-if="items.length" class="space-y-3">
                <MdCard v-for="item in items" :key="item.id">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-[15px] font-bold text-stone-900">{{ labelTipe(item.tipe) || 'Tagihan' }}</p>
                            <p v-if="item.jatuhTempo" class="mt-0.5 text-[12px] text-stone-500">Jatuh tempo: {{ item.jatuhTempo }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-[15px] font-bold text-stone-900">{{ rupiah(item.nominal) }}</p>
                            <span class="mt-1 inline-block rounded-full border px-2.5 py-0.5 text-[11px] font-semibold" :class="chipClass(item)">
                                {{ labelStatus(item.status) || 'Belum Bayar' }}
                            </span>
                        </div>
                    </div>
                    <p v-if="item.keterangan" class="mt-2 text-[12px] leading-relaxed text-stone-500">{{ item.keterangan }}</p>

                    <!-- Unggah bukti transfer (hanya bila belum lunas) -->
                    <div v-if="!item.lunas" class="mt-3 flex flex-wrap items-center gap-2">
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
                            class="inline-flex h-10 cursor-pointer items-center gap-1.5 rounded-full bg-[var(--spmb-primary)]/12 px-4 text-[13px] font-semibold text-[var(--spmb-primary)] transition active:scale-[0.98] active:bg-[var(--spmb-primary)]/20"
                            :class="form.processing ? 'pointer-events-none opacity-50' : ''"
                        >
                            <span aria-hidden="true">🧾</span>
                            <span v-if="uploadingId === item.id">Mengunggah…</span>
                            <span v-else>{{ item.buktiAda ? 'Ganti bukti' : 'Unggah bukti transfer' }}</span>
                        </label>
                        <span v-if="item.buktiAda" class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-600">✓ bukti terkirim</span>
                    </div>
                </MdCard>
            </div>

            <!-- Empty state -->
            <MdCard v-else>
                <div class="py-4 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-stone-100 text-2xl">🧾</div>
                    <p class="mt-3 text-[15px] font-bold text-stone-900">Belum ada tagihan</p>
                    <p class="mt-1 text-[13px] leading-relaxed text-stone-500">
                        Tagihan pendaftaran akan tampil di sini bila sudah diterbitkan panitia.
                    </p>
                </div>
            </MdCard>
        </div>
    </SpmbLayout>
</template>
