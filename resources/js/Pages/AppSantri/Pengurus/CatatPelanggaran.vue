<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { HeroHeader, SectionCard, UiField, UiInput, UiButton } from '@/Shared';

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
    <div class="min-h-screen bg-[#fffaf3] pb-10">
        <HeroHeader name="Catat Pelanggaran" greeting="Mode Pengurus">
            <template #action>
                <Link href="/app/pengurus" class="text-xs text-white/80">← Kembali</Link>
            </template>
        </HeroHeader>

        <main class="mx-auto -mt-7 max-w-md space-y-4 px-5">
            <SectionCard>
                <form class="space-y-4" @submit.prevent="submit">
                    <p v-if="$page.props.flash?.sukses" class="rounded-lg bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700">
                        {{ $page.props.flash.sukses }}
                    </p>

                    <UiField label="Santri" required>
                        <template #default="{ fieldId, describedby }">
                            <select :id="fieldId" :aria-describedby="describedby" v-model="form.santri_id" required
                                class="w-full rounded-2xl bg-white px-4 py-3 text-sm font-medium text-slate-900 ring-1 ring-inset ring-slate-200 focus:outline-none focus:ring-2 focus:ring-[var(--wali-primary,#059669)]">
                                <option value="" disabled>Pilih santri…</option>
                                <option v-for="s in santriList" :key="s.id" :value="s.id">{{ s.nama }} ({{ s.nis }})</option>
                            </select>
                        </template>
                    </UiField>

                    <UiField label="Jenis pelanggaran" required>
                        <template #default="{ fieldId, describedby }">
                            <select :id="fieldId" :aria-describedby="describedby" v-model="form.jenis_pelanggaran_id" required
                                class="w-full rounded-2xl bg-white px-4 py-3 text-sm font-medium text-slate-900 ring-1 ring-inset ring-slate-200 focus:outline-none focus:ring-2 focus:ring-[var(--wali-primary,#059669)]">
                                <option value="" disabled>Pilih jenis…</option>
                                <option v-for="j in jenisList" :key="j.id" :value="j.id">{{ j.nama }} ({{ j.poin }} poin)</option>
                            </select>
                        </template>
                    </UiField>

                    <UiField label="Kronologi" required>
                        <template #default="{ fieldId, describedby }">
                            <UiInput :id="fieldId" :aria-describedby="describedby" v-model="form.kronologi" type="textarea" :rows="3" />
                        </template>
                    </UiField>

                    <UiField label="Lokasi (opsional)">
                        <template #default="{ fieldId, describedby }">
                            <UiInput :id="fieldId" :aria-describedby="describedby" v-model="form.lokasi" type="text" />
                        </template>
                    </UiField>

                    <UiButton type="submit" variant="primary" size="lg" block :loading="form.processing" :disabled="form.processing">
                        Kirim Laporan
                    </UiButton>
                    <p class="text-center text-[11px] text-stone-400">
                        Laporan akan diperiksa & diverifikasi ustadz sebelum poin berlaku.
                    </p>
                </form>
            </SectionCard>
        </main>
    </div>
</template>
