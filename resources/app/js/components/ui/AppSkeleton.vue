<script setup>
defineProps({
    height: {
        type: String,
        default: 'h-4',
    },
});
</script>

<template>
    <div :class="['animate-pulse rounded-[var(--radius-sm)] bg-[color:color-mix(in_srgb,var(--color-border)_60%,var(--color-bg-elevated))]', height]" />
</template>
