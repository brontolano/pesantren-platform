<script setup>
import { Link } from '@inertiajs/vue3';
import { HeroHeader, ListRow, IconCircle, SectionCard } from '@/Shared';

defineProps({
    nama: String,
    penugasan: Array, // [{qism, label, periode_selesai}]
});
</script>

<template>
    <div class="min-h-screen bg-[#fffaf3] pb-10">
        <HeroHeader :name="nama" greeting="Mode Pengurus">
            <template #subtitle>
                <p class="mt-1 text-xs text-white/75">
                    <span v-for="(p, i) in penugasan" :key="p.qism">
                        {{ p.label }}<span v-if="i < penugasan.length - 1"> · </span>
                    </span>
                </p>
            </template>
        </HeroHeader>

        <main class="mx-auto -mt-7 max-w-md space-y-4 px-5">
            <SectionCard flush>
                <div class="divide-y divide-slate-100">
                    <ListRow href="/app/pengurus/pelanggaran">
                        <template #leading><IconCircle icon="exclamation" tone="amber" /></template>
                        <template #title>Catat Pelanggaran</template>
                        <template #subtitle>Laporan masuk berstatus "dicatat" — menunggu verifikasi ustadz.</template>
                    </ListRow>
                    <ListRow href="/app/pengurus/kegiatan">
                        <template #leading><IconCircle icon="calendar" tone="amber" /></template>
                        <template #title>Presensi Kegiatan</template>
                        <template #subtitle>Hanya kegiatan domain qism Anda.</template>
                    </ListRow>
                </div>
            </SectionCard>

            <Link href="/app" class="block text-center text-xs font-semibold text-stone-400">
                ← Kembali ke beranda santri
            </Link>
        </main>
    </div>
</template>
