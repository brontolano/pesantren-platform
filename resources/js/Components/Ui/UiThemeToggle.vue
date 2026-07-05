<script setup>
// Tombol ganti tema gelap/terang "Nur". Menyimpan pilihan (localStorage) via
// helper theme.js. Anti-flash awal ditangani inline di app.blade.php.
import { ref, onMounted } from 'vue'
import { isDark, toggleTheme } from '@/Shared/theme'

// Gaya default cocok utk hero hijau (putih semi-transparan). Override lewat
// prop `btnClass` bila header terang.
const props = defineProps({
    btnClass: {
        type: String,
        default: 'h-10 w-10 bg-white/16 text-white ring-1 ring-inset ring-white/25',
    },
})

const dark = ref(false)
onMounted(() => { dark.value = isDark() })
function onToggle() { dark.value = toggleTheme() }
</script>

<template>
    <button
        type="button"
        @click="onToggle"
        :aria-label="dark ? 'Aktifkan mode terang' : 'Aktifkan mode gelap'"
        :class="['flex shrink-0 items-center justify-center rounded-full transition active:scale-95', btnClass]"
    >
        <!-- Bulan (saat terang) -->
        <svg v-if="!dark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-[19px] w-[19px]">
            <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z" />
        </svg>
        <!-- Matahari (saat gelap) -->
        <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-[19px] w-[19px]">
            <circle cx="12" cy="12" r="4" />
            <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" />
        </svg>
    </button>
</template>
