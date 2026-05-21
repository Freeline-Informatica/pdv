<script setup>
defineProps({
    elevated: {
        type: Boolean,
        default: false,
    },
    padding: {
        type: String,
        default: 'p-5',
    },
});
</script>

<template>
    <section :class="[elevated ? 'ui-card-elevated' : 'ui-card', padding]">
        <slot />
    </section>
</template>
