<script setup>
defineProps({
    text: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <span class="inline-flex" :title="text">
        <slot />
    </span>
</template>
