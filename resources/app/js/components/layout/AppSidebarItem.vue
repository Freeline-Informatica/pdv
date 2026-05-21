<script setup>
defineProps({
    active: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <button type="button" class="sidebar-item" :class="active && 'is-active'">
        <slot />
    </button>
</template>
