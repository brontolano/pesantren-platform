<script>
export default { inheritAttrs: false }
</script>

<script setup>
import { computed } from 'vue'

/**
 * IslamicPattern — overlay tekstur geometris islami (girih / bintang 8-titik
 * "Rub el Hizb": dua persegi tumpang-tindih rotasi 45° membentuk bintang),
 * di-tile via <pattern> sebagai lapisan absolut full-cover.
 *
 * Dipakai sebagai tekstur latar di belakang teks hero. Selalu dekoratif:
 * aria-hidden + pointer-events-none, opacity rendah agar kontras teks aman.
 *
 * Props:
 * - color   : warna garis motif (default '#ffffff')
 * - opacity : opasitas lapisan (default 0.06 — sangat subtil)
 * - size    : ukuran ubin px (default 56) — makin besar makin renggang
 * - class   : kelas tambahan (diteruskan ke wrapper)
 */
const props = defineProps({
    color: { type: String, default: '#ffffff' },
    opacity: { type: [Number, String], default: 0.06 },
    size: { type: [Number, String], default: 56 },
})

// id unik agar beberapa instance tidak saling menimpa definisi <pattern>.
const uid = `igp-${Math.random().toString(36).slice(2, 9)}`
const tile = computed(() => Number(props.size) || 56)
</script>

<template>
    <div
        aria-hidden="true"
        class="pointer-events-none absolute inset-0 h-full w-full"
        :class="$attrs.class"
        :style="{ opacity: Number(opacity) }"
    >
        <svg class="h-full w-full" :aria-hidden="true" preserveAspectRatio="xMidYMid slice">
            <defs>
                <!-- Satu ubin = bintang 8-titik (girih) di tengah + simpul belah ketupat di sudut,
                     sehingga saat di-tile garis bertemu mulus membentuk jejaring islami. -->
                <pattern
                    :id="uid"
                    :width="tile"
                    :height="tile"
                    patternUnits="userSpaceOnUse"
                >
                    <g
                        :transform="`translate(${tile / 2} ${tile / 2})`"
                        fill="none"
                        :stroke="color"
                        stroke-width="1"
                        stroke-linejoin="round"
                        stroke-linecap="round"
                    >
                        <!-- Persegi 1 (tegak) + Persegi 2 (rotasi 45°) → bintang 8 titik (Rub el Hizb). -->
                        <rect x="-12" y="-12" width="24" height="24" />
                        <rect x="-12" y="-12" width="24" height="24" transform="rotate(45)" />
                        <!-- Garis bintang internal (oktagon penghubung titik) -->
                        <polygon
                            points="-12,0 -8.49,-8.49 0,-12 8.49,-8.49 12,0 8.49,8.49 0,12 -8.49,8.49"
                        />
                    </g>
                    <!-- Belah ketupat kecil di tiap sudut ubin → simpul sambungan antar bintang. -->
                    <g fill="none" :stroke="color" stroke-width="1" stroke-linejoin="round">
                        <path :d="`M0,-5 L5,0 L0,5 L-5,0 Z`" />
                        <path :d="`M${tile},-5 L${tile + 5},0 L${tile},5 L${tile - 5},0 Z`" />
                        <path :d="`M0,${tile - 5} L5,${tile} L0,${tile + 5} L-5,${tile} Z`" />
                        <path :d="`M${tile},${tile - 5} L${tile + 5},${tile} L${tile},${tile + 5} L${tile - 5},${tile} Z`" />
                    </g>
                </pattern>
            </defs>
            <rect width="100%" height="100%" :fill="`url(#${uid})`" />
        </svg>
    </div>
</template>
