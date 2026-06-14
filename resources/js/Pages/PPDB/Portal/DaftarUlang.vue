<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import SpmbLayout from '@/Layouts/SpmbLayout.vue';
import MdCard from '@/spmb/components/MdCard.vue';
import MdButton from '@/spmb/components/MdButton.vue';

const props = defineProps({
    nomorPendaftaran: { type: String, default: '' },
    nama: { type: String, default: '' },
    asalSekolah: { type: String, default: '' },
    noHpWali: { type: String, default: '' },
});

const form = useForm({});

function konfirmasi() {
    form.post('/spmb/daftar-ulang', { preserveScroll: true });
}
</script>

<template>
    <Head title="Daftar Ulang" />

    <SpmbLayout show-back :back-href="'/spmb'" title="Daftar Ulang">
        <div class="space-y-4">
            <!-- Banner lulus -->
            <MdCard :padded="false">
                <div class="border border-emerald-200 bg-emerald-50 px-5 py-6 text-center text-emerald-800">
                    <div class="text-4xl leading-none">🎉</div>
                    <p class="mt-3 text-[17px] font-bold">Selamat, Anda DITERIMA!</p>
                    <p class="mt-1.5 text-[13px] leading-relaxed">Lengkapi daftar ulang untuk menjadi santri aktif.</p>
                </div>
            </MdCard>

            <div v-if="form.errors.daftar_ulang" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-[13px] font-medium text-red-700">
                {{ form.errors.daftar_ulang }}
            </div>

            <!-- Ringkasan data -->
            <MdCard>
                <p class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-stone-500">Data Anda</p>
                <dl class="space-y-2.5">
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-[13px] text-stone-500">Nama</dt>
                        <dd class="text-right text-[14px] font-semibold text-stone-900">{{ nama }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-[13px] text-stone-500">No. Pendaftaran</dt>
                        <dd class="text-right text-[14px] font-semibold text-stone-900">{{ nomorPendaftaran }}</dd>
                    </div>
                    <div v-if="asalSekolah" class="flex items-start justify-between gap-3">
                        <dt class="text-[13px] text-stone-500">Asal Sekolah</dt>
                        <dd class="text-right text-[14px] font-medium text-stone-800">{{ asalSekolah }}</dd>
                    </div>
                    <div v-if="noHpWali" class="flex items-start justify-between gap-3">
                        <dt class="text-[13px] text-stone-500">No. WA Wali</dt>
                        <dd class="text-right text-[14px] font-medium text-stone-800">{{ noHpWali }}</dd>
                    </div>
                </dl>
            </MdCard>

            <p class="px-1 text-[12px] leading-relaxed text-stone-500">
                Dengan menekan tombol di bawah, data Anda dikonversi menjadi akun santri aktif.
                Pastikan data sudah benar (perbarui di Dashboard bila perlu).
            </p>

            <MdButton
                variant="primary"
                :loading="form.processing"
                :disabled="form.processing"
                @click="konfirmasi"
            >
                {{ form.processing ? 'Memproses…' : 'Konfirmasi Daftar Ulang' }}
            </MdButton>
        </div>
    </SpmbLayout>
</template>
