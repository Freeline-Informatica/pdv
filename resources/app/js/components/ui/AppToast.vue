<script setup>
import { computed } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    tone: {
        type: String,
        default: 'success',
    },
});

const classes = computed(() => [
    'fixed right-4 top-4 z-[70] rounded-[var(--radius-md)] border px-4 py-3 text-sm font-semibold shadow-[var(--shadow-md)]',
    props.tone === 'success' && 'border-[color:color-mix(in_srgb,var(--color-success)_45%,transparent)] bg-[color:color-mix(in_srgb,var(--color-success)_20%,var(--color-bg-surface))] text-[var(--color-success)]',
    props.tone === 'danger' && 'border-[color:color-mix(in_srgb,var(--color-danger)_45%,transparent)] bg-[color:color-mix(in_srgb,var(--color-danger)_20%,var(--color-bg-surface))] text-[var(--color-danger)]',
]);
</script>

<template>
    <div v-if="show" :class="classes">
        <slot />
    </div>
</template>
