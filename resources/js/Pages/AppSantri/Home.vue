<script setup>
import { router } from '@inertiajs/vue3';
import { HeroHeader, SectionCard, MoneyText, UiButton } from '@/Shared';

defineProps({
    nama: String,
    nis: String,
    status: String,
    saldo: Number,
});

function logout() {
    router.post('/app/logout');
}
</script>

<template>
    <div class="min-h-screen bg-[#fffaf3] pb-10">
        <HeroHeader :name="nama">
            <template #subtitle>
                <p class="text-xs text-white/75">NIS {{ nis }} · {{ status }}</p>
            </template>
            <template #action>
                <UiButton size="sm" variant="secondary" class="!bg-white/15 !text-white !ring-white/30" @click="logout">
                    Keluar
                </UiButton>
            </template>
        </HeroHeader>

        <main class="mx-auto -mt-7 max-w-md space-y-4 px-5">
            <SectionCard>
                <p class="text-xs font-semibold text-stone-500">Saldo Tabungan</p>
                <MoneyText :amount="saldo" size="xl" tone="emerald" class="mt-1" />
                <p class="mt-1 text-xs text-stone-400">Setor & tarik lewat petugas atau loket pesantren.</p>
            </SectionCard>

            <section class="rounded-2xl border border-dashed border-stone-300 bg-white/60 px-5 py-4 text-sm text-stone-500">
                Fitur berikutnya: login sidik jari, kartu santri NFC, jadwal & hafalan.
            </section>
        </main>
    </div>
</template>
