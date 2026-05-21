<script setup>
defineProps({
    label: {
        type: String,
        required: true,
    },
    value: {
        type: [String, Number],
        default: '',
    },
});
</script>

<template>
    <article class="ui-card p-4">
        <p class="text-xs uppercase tracking-wide text-muted">{{ label }}</p>
        <p class="mt-2 text-xl font-black text-main">{{ value }}</p>
        <slot />
    </article>
</template>
