<script>
export default { inheritAttrs: false }
</script>

<script setup>
/**
 * OrnamentDivider — pembatas ornamental islami tipis:
 * dua garis hairline (fade ke tepi) + bintang/belah-ketupat kecil emas di tengah.
 * Subtil & elegan, dipakai opsional untuk memisah seksi.
 *
 * Props:
 * - color : warna ornamen (default token emas --wali-gold)
 * - class : kelas tambahan untuk wrapper (mis. spasi/lebar)
 */
defineProps({
    color: { type: String, default: 'var(--wali-gold, #c9a24a)' },
})
</script>

<template>
    <div
        aria-hidden="true"
        class="flex w-full items-center gap-3"
        :class="$attrs.class"
    >
        <span
            class="h-px flex-1 rounded-full"
            :style="{ backgroundImage: `linear-gradient(90deg, transparent, ${color})`, opacity: 0.55 }"
        />
        <svg
            width="34"
            height="14"
            viewBox="0 0 34 14"
            fill="none"
            :stroke="color"
            stroke-width="1"
            stroke-linejoin="round"
            class="shrink-0"
        >
            <!-- Bintang 8-titik mini (belah ketupat + persegi rotasi) di tengah -->
            <path d="M17 1 L20 7 L17 13 L14 7 Z" />
            <path d="M11 7 L17 4 L23 7 L17 10 Z" />
            <!-- Titik emas kecil di kiri-kanan -->
            <circle cx="4" cy="7" r="1.1" :fill="color" stroke="none" />
            <circle cx="30" cy="7" r="1.1" :fill="color" stroke="none" />
        </svg>
        <span
            class="h-px flex-1 rounded-full"
            :style="{ backgroundImage: `linear-gradient(90deg, ${color}, transparent)`, opacity: 0.55 }"
        />
    </div>
</template>
