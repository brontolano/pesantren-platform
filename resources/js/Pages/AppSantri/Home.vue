<script setup>
import { router } from '@inertiajs/vue3';

defineProps({
    nama: String,
    nis: String,
    status: String,
    saldo: Number,
});

const rupiah = (n) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n || 0);

function logout() {
    router.post('/app/logout');
}
</script>

<template>
    <div class="min-h-screen bg-[#fffaf3] pb-10">
        <header class="bg-gradient-to-b from-emerald-800 to-emerald-600 px-5 pb-12 pt-8 text-white">
            <div class="mx-auto flex max-w-md items-center justify-between">
                <div>
                    <p class="text-sm text-emerald-100">Assalamu'alaikum,</p>
                    <h1 class="text-xl font-bold">{{ nama }}</h1>
                    <p class="mt-1 text-xs text-emerald-200">NIS {{ nis }} · {{ status }}</p>
                </div>
                <button type="button" class="rounded-full bg-white/15 px-4 py-2 text-xs font-semibold" @click="logout">
                    Keluar
                </button>
            </div>
        </header>

        <main class="mx-auto -mt-7 max-w-md space-y-4 px-5">
            <section class="rounded-2xl border border-stone-200 bg-white px-5 py-4 shadow-sm">
                <p class="text-xs font-semibold text-stone-500">Saldo Tabungan</p>
                <p class="mt-1 text-2xl font-bold text-emerald-700">{{ rupiah(saldo) }}</p>
                <p class="mt-1 text-xs text-stone-400">Setor & tarik lewat petugas atau loket pesantren.</p>
            </section>

            <section class="rounded-2xl border border-dashed border-stone-300 bg-white/60 px-5 py-4 text-sm text-stone-500">
                Fitur berikutnya: login sidik jari, kartu santri NFC, jadwal & hafalan.
            </section>
        </main>
    </div>
</template>
