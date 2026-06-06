<script setup>
/**
 * MosqueSilhouette — siluet masjid bersih & seimbang:
 * kubah utama (bawang) + bulan sabit puncak, dua menara ramping bertopi kubah,
 * garis atap/dinding, dan dua kubah kecil pendamping.
 *
 * Memakai currentColor — atur warna & opasitas via class di pemanggil
 * (mis. text-white/10). Dekoratif: aria-hidden + pointer-events-none.
 * viewBox lebar (320x120) agar pas menempel di dasar hero header.
 */
</script>

<template>
    <svg
        viewBox="0 0 320 120"
        fill="currentColor"
        aria-hidden="true"
        class="pointer-events-none block w-full"
        preserveAspectRatio="xMidYMax meet"
    >
        <!-- Menara kiri -->
        <path
            d="M44 120 V52 a6 6 0 0 1 12 0 V120 Z
               M50 40 c6 0 9 4 9 8 c0 5 -4 6 -9 6 c-5 0 -9 -1 -9 -6 c0 -4 3 -8 9 -8 Z
               M50 33 a3 3 0 0 0 -3 5 h6 a3 3 0 0 0 -3 -5 Z"
        />
        <!-- Menara kanan (cermin) -->
        <path
            d="M264 120 V52 a6 6 0 0 1 12 0 V120 Z
               M270 40 c6 0 9 4 9 8 c0 5 -4 6 -9 6 c-5 0 -9 -1 -9 -6 c0 -4 3 -8 9 -8 Z
               M270 33 a3 3 0 0 0 -3 5 h6 a3 3 0 0 0 -3 -5 Z"
        />

        <!-- Badan masjid (dinding + garis atap) -->
        <path d="M78 120 V74 a82 82 0 0 1 164 0 V120 Z" />

        <!-- Kubah kecil pendamping kiri & kanan -->
        <path d="M96 78 a18 18 0 0 1 36 0 c0 6 -8 9 -18 9 c-10 0 -18 -3 -18 -9 Z" />
        <path d="M188 78 a18 18 0 0 1 36 0 c0 6 -8 9 -18 9 c-10 0 -18 -3 -18 -9 Z" />

        <!-- Kubah utama (bentuk bawang) + leher -->
        <path
            d="M134 80 c0 -10 6 -18 14 -28 c6 -8 8 -16 12 -16 c4 0 6 8 12 16 c8 10 14 18 14 28
               c0 9 -12 14 -26 14 c-14 0 -26 -5 -26 -14 Z"
        />
        <!-- Puncak: tiang + bulan sabit -->
        <path d="M158 36 h4 v-9 h-4 Z" />
        <path
            d="M160 14 a8 8 0 1 0 5.5 13.8 a6 6 0 1 1 0 -11 A8 8 0 0 0 160 14 Z"
        />
    </svg>
</template>
