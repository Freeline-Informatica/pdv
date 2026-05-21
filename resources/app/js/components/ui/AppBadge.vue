<script setup>
import { computed } from 'vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'default',
    },
});

const classes = computed(() => [
    'ui-badge',
    props.variant === 'default' && 'ui-badge-default',
    props.variant === 'success' && 'ui-badge-success',
    props.variant === 'warning' && 'ui-badge-warning',
    props.variant === 'danger' && 'ui-badge-danger',
]);
</script>

<template>
    <span :class="classes"><slot /></span>
</template>
