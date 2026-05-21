<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
    title: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <button :type="type" :title="title" class="ui-icon-btn" :aria-label="title || undefined">
        <slot />
    </button>
</template>
