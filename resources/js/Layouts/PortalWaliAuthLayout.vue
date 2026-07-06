<script setup>
import { computed } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'

const page = usePage()

const branding = computed(() => page.props.branding ?? {})

const rootStyle = computed(() => ({
    '--wali-primary': branding.value.primaryColor ?? '#059669',
    '--wali-secondary': branding.value.secondaryColor ?? '#047857',
}))
</script>

<template>
    <Head>
        <meta name="theme-color" :content="branding.primaryColor ?? '#059669'">
    </Head>

    <div class="min-h-screen bg-[#fffaf3] text-[#111] antialiased" :style="rootStyle">
        <div class="mx-auto flex min-h-screen max-w-md flex-col px-7 pt-[calc(env(safe-area-inset-top)+40px)]">
            <!-- Brand mark -->
            <div class="mb-9 flex items-center gap-2.5">
                <span
                    v-if="branding.logoUrl"
                    class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-full bg-gradient-to-br from-emerald-600 to-emerald-800"
                >
                    <img :src="branding.logoUrl" :alt="branding.name ?? 'Logo'" class="h-full w-full object-contain p-1">
                </span>
                <span
                    v-else
                    class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-emerald-600 to-emerald-800"
                >
                    <span class="h-4 w-4 rounded-full bg-[#e7d3a1]" style="-webkit-mask:radial-gradient(circle at 68% 32%,transparent 52%,#000 53%);mask:radial-gradient(circle at 68% 32%,transparent 52%,#000 53%);" />
                </span>
                <span class="font-['Jost',sans-serif] text-[17px] font-bold text-[#111]">
                    {{ branding.name || 'Pesantren' }}<span class="text-[#c9a24a]">.</span>online
                </span>
            </div>

            <div class="flex-1">
                <slot />
            </div>

            <div class="pb-14 pt-8 text-center text-[12.5px] leading-relaxed text-[#94a3b8]">
                Butuh bantuan? Hubungi admin pesantren<br>melalui WhatsApp resmi.
            </div>
        </div>
    </div>
</template>
