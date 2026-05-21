<script setup>
import { computed } from 'vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'primary',
    },
    type: {
        type: String,
        default: 'button',
    },
    loading: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    block: {
        type: Boolean,
        default: false,
    },
});

const classes = computed(() => [
    'ui-btn',
    props.variant === 'primary' && 'ui-btn-primary',
    props.variant === 'secondary' && 'ui-btn-secondary',
    props.variant === 'ghost' && 'ui-btn-ghost',
    props.variant === 'danger' && 'ui-btn-danger',
    props.block && 'w-full',
]);
</script>

<template>
    <button :type="type" :disabled="disabled || loading" :class="classes">
        <span v-if="loading">Carregando...</span>
        <slot v-else />
    </button>
</template>
