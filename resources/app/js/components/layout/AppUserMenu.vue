<script setup>
defineProps({
    compact: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div
        class="inline-flex items-center gap-2 rounded-[var(--radius-sm)] border border-[var(--color-border)] bg-[var(--color-bg-surface)] px-3 py-2 text-sm text-main"
        :class="compact ? 'justify-center w-full px-2' : 'w-full justify-start'"
    >
        <span class="h-2 w-2 rounded-full bg-[var(--color-success)]" aria-hidden="true" />
        <slot v-if="!compact">Operador</slot>
    </div>
</template>
