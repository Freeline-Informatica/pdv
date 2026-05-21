<script setup>
import AppButton from '../../ui/AppButton.vue';

defineProps({
    payments: {
        type: Array,
        default: () => [],
    },
    formatCurrency: {
        type: Function,
        required: true,
    },
});

const emit = defineEmits(['remove']);

function formatRate(value) {
    return `${Number(value || 0).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}%`;
}
</script>

<template>
    <div class="payment-list-wrap">
        <div v-if="payments.length === 0" class="payment-empty">Nenhum pagamento lançado ainda.</div>
        <div v-else class="payment-list">
            <article v-for="payment in payments" :key="payment.id" class="payment-item">
                <div>
                    <p class="payment-item-name">{{ payment.methodName }}</p>
                    <p class="payment-item-value">{{ formatCurrency(payment.amount) }}</p>
                    <p v-if="payment.installments > 1 || payment.interestAmount > 0" class="payment-item-meta">
                        <span v-if="payment.installments > 1">
                            {{ payment.installments }}x de {{ formatCurrency(payment.installmentAmount || payment.amount) }}
                        </span>
                        <span v-if="payment.interestAmount > 0">
                            • Acrecimo {{ formatCurrency(payment.interestAmount) }} ({{ formatRate(payment.interestRate) }})
                        </span>
                    </p>
                </div>
                <AppButton variant="ghost" @click="emit('remove', payment.id)">Remover</AppButton>
            </article>
        </div>
    </div>
</template>

<style scoped>
.payment-list-wrap {
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    background: color-mix(in srgb, var(--color-bg-elevated) 80%, var(--color-bg-surface));
}

.payment-empty {
    padding: 0.9rem;
    font-size: 0.82rem;
    color: var(--color-text-muted);
}

.payment-list {
    display: grid;
    gap: 0;
    max-height: 220px;
    overflow: auto;
}

.payment-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.6rem;
    padding: 0.75rem 0.85rem;
    border-bottom: 1px solid color-mix(in srgb, var(--color-border) 72%, transparent);
}

.payment-item:last-child {
    border-bottom: 0;
}

.payment-item:focus-within {
    background: color-mix(in srgb, var(--color-primary) 8%, var(--color-bg-surface));
}

.payment-item-name {
    margin: 0;
    font-size: 0.83rem;
    font-weight: 700;
    color: var(--color-text);
}

.payment-item-value {
    margin: 0.2rem 0 0;
    font-size: 0.82rem;
    color: var(--color-text-muted);
}

.payment-item-meta {
    margin: 0.25rem 0 0;
    font-size: 0.73rem;
    color: var(--color-text-muted);
}
</style>
