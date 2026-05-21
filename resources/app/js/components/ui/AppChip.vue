<script setup>
defineProps({
    active: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <button
        type="button"
        class="rounded-full border px-3 py-1 text-xs font-semibold transition"
        :class="active
            ? 'bg-[var(--color-primary)] text-[var(--color-text-inverse)] border-[var(--color-primary)]'
            : 'bg-[var(--color-bg-surface)] text-[var(--color-text-muted)] border-[var(--color-border-strong)] hover:border-[var(--color-primary)] hover:text-[var(--color-text)]'"
    >
        <slot />
    </button>
</template>
