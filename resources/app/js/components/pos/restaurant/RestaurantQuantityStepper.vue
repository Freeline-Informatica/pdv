<script setup>
defineProps({
    modelValue: {
        type: Number,
        default: 1,
    },
    min: {
        type: Number,
        default: 1,
    },
});

const emit = defineEmits(['update:modelValue']);

function decrease(value, min) {
    emit('update:modelValue', Math.max(min, Number(value || 1) - 1));
}
</script>

<template>
    <div class="qty-stepper">
        <button type="button" class="qty-stepper__btn" @click="decrease(modelValue, min)">-</button>
        <strong class="qty-stepper__value">{{ modelValue }}</strong>
        <button type="button" class="qty-stepper__btn" @click="emit('update:modelValue', modelValue + 1)">+</button>
    </div>
</template>

<style scoped>
.qty-stepper {
    display: inline-flex;
    align-items: center;
    border: 1px solid var(--color-border-strong);
    border-radius: 0.68rem;
    overflow: hidden;
}

.qty-stepper__btn {
    width: 2rem;
    height: 2rem;
    border: 0;
    background: color-mix(in srgb, var(--color-bg-elevated) 85%, var(--color-bg-surface));
    color: var(--color-text);
    font-size: 1.05rem;
    font-weight: 800;
    cursor: pointer;
}

.qty-stepper__value {
    width: 2.1rem;
    text-align: center;
    font-size: 0.94rem;
    color: var(--color-text);
}
</style>
