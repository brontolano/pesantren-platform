<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import UiIcon from '@/Components/Ui/UiIcon.vue'
import IslamicPattern from '@/Components/Ui/IslamicPattern.vue'

/**
 * HeroHeader — header beranda gradient emerald (ekstrak dari PortalWaliLayout home).
 * Avatar + sapaan + nama + (opsional) chip peran + lonceng notifikasi,
 * dengan overlay pola islami halus. Reusable lintas peran.
 *
 * Slot:
 *   #subtitle — di bawah nama (mis. chip peran)
 *   #actions  — aksi ekstra di kanan (additif; default kosong — utk bell/avatar)
 *   #action   — override area kanan (default: lonceng -> notifHref)
 *   #switcher — baris pills horizontal (child/role/periode switcher)
 *   default   — konten ringkasan di bawah (mis. grid StatCard kaca)
 */
const props = defineProps({
    name: { type: String, default: '' },
    greeting: { type: String, default: "Assalamu'alaikum," },
    notifHref: { type: String, default: null },
    hasNotif: { type: Boolean, default: false },
    avatarText: { type: String, default: null },
})

const initial = computed(() =>
    (props.avatarText || props.name || '?').charAt(0).toUpperCase(),
)
</script>

<template>
    <div
        class="relative overflow-hidden rounded-b-[30px] bg-brand-grad-hero px-6 pb-6 pt-[calc(env(safe-area-inset-top)+1.25rem)] text-white shadow-soft-lg"
    >
        <IslamicPattern color="#ffffff" :opacity="0.06" :size="46" />

        <div class="relative z-10">
            <div class="flex items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    <span
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-white/20 text-[17px] font-bold text-white ring-1 ring-inset ring-white/35 shadow-[0_2px_8px_rgba(0,0,0,0.12)]"
                    >
                        {{ initial }}
                    </span>
                    <div class="min-w-0">
                        <p class="text-[12.5px] text-white/75">{{ greeting }}</p>
                        <p class="truncate font-jost text-base font-semibold text-white">
                            {{ name || '—' }}
                        </p>
                        <div v-if="$slots.subtitle" class="mt-1">
                            <slot name="subtitle" />
                        </div>
                    </div>
                </div>

                <!-- #actions (F0, additif): tempat aksi ekstra di kanan header
                     (mis. bell/avatar tambahan nanti). Default kosong. -->
                <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2">
                    <slot name="actions" />
                </div>

                <slot name="action">
                    <Link
                        v-if="notifHref"
                        :href="notifHref"
                        class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/16 ring-1 ring-inset ring-white/25"
                        aria-label="Notifikasi"
                    >
                        <UiIcon name="bell" class="h-[19px] w-[19px] text-white" />
                        <span
                            v-if="hasNotif"
                            class="absolute right-[9px] top-2 h-2 w-2 rounded-full bg-amber-400 ring-2 ring-emerald-600"
                        />
                    </Link>
                </slot>
            </div>

            <div
                v-if="$slots.switcher"
                class="-mx-1 mt-4 flex gap-2 overflow-x-auto px-1 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
            >
                <slot name="switcher" />
            </div>

            <div v-if="$slots.default" class="mt-4">
                <slot />
            </div>
        </div>
    </div>
</template>
