<script setup>
// Permukaan kartu Material: sudut membulat, border halus, bayangan tipis.
defineProps({
    padded: { type: Boolean, default: true },
});
</script>

<template>
    <section
        class="overflow-hidden rounded-2xl border border-stone-200/80 bg-white shadow-sm"
        :class="padded ? 'p-4' : ''"
    >
        <slot />
    </section>
</template>
