<script setup>
import AppButton from '../../ui/AppButton.vue';

const props = defineProps({
    formatCurrency: {
        type: Function,
        required: true,
    },
    subtotal: {
        type: Number,
        default: 0,
    },
    totalItems: {
        type: Number,
        default: 0,
    },
    confirmLabel: {
        type: String,
        default: 'Confirmar pedido',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['confirm']);
</script>

<template>
    <section class="restaurant-order-summary ui-card">
        <div class="restaurant-order-summary__row">
            <span>Itens</span>
            <strong>{{ totalItems }}</strong>
        </div>
        <div class="restaurant-order-summary__row">
            <span>Subtotal</span>
            <strong>{{ formatCurrency(subtotal) }}</strong>
        </div>

        <AppButton block :disabled="disabled" @click="emit('confirm')">{{ confirmLabel }}</AppButton>
    </section>
</template>

<style scoped>
.restaurant-order-summary {
    padding: 0.72rem;
    display: grid;
    gap: 0.62rem;
}

.restaurant-order-summary__row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: var(--color-text-muted);
    font-size: 0.9rem;
}

.restaurant-order-summary__row strong {
    color: var(--color-text);
    font-size: 1rem;
}
</style>
