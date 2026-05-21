<script setup>
import AppButton from '../../ui/AppButton.vue';

defineProps({
    variant: {
        type: String,
        default: 'secondary',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <AppButton class="quick-action-btn" :variant="variant" :disabled="disabled" :title="title">
        <span v-if="$slots.icon" class="quick-action-btn__icon">
            <slot name="icon" />
        </span>
        <span class="quick-action-btn__label">
            <slot />
        </span>
    </AppButton>
</template>

<style scoped>
.quick-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.42rem;
}

.quick-action-btn__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.quick-action-btn__label {
    white-space: nowrap;
}
</style>
