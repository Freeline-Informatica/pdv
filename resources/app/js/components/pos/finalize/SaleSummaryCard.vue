<script setup>
defineProps({
    customerLabel: {
        type: String,
        default: 'Consumidor final',
    },
    itemsCount: {
        type: Number,
        default: 0,
    },
    productsTotal: {
        type: Number,
        default: 0,
    },
    discountTotal: {
        type: Number,
        default: 0,
    },
    surchargeTotal: {
        type: Number,
        default: 0,
    },
    surchargeLabel: {
        type: String,
        default: 'Acréscimo',
    },
    netTotal: {
        type: Number,
        default: 0,
    },
    paidTotal: {
        type: Number,
        default: 0,
    },
    remainingTotal: {
        type: Number,
        default: 0,
    },
    changeTotal: {
        type: Number,
        default: 0,
    },
    statusLabel: {
        type: String,
        default: 'Em preenchimento',
    },
    noteSummary: {
        type: String,
        default: '',
    },
    formatCurrency: {
        type: Function,
        required: true,
    },
});
</script>

<template>
    <aside class="summary-card">
        <header class="summary-head">
            <p class="summary-title">Resumo da venda</p>
            <span class="summary-status">{{ statusLabel }}</span>
        </header>

        <div class="summary-scroll">
            <div class="summary-block">
                <p class="summary-label">Cliente</p>
                <p class="summary-customer">{{ customerLabel }}</p>
            </div>

            <div class="summary-row">
                <span>Itens</span>
                <strong>{{ itemsCount }}</strong>
            </div>
            <div class="summary-row">
                <span>Total de produtos</span>
                <strong>{{ formatCurrency(productsTotal) }}</strong>
            </div>
            <div class="summary-row">
                <span>Desconto</span>
                <strong>- {{ formatCurrency(discountTotal) }}</strong>
            </div>
            <div class="summary-row">
                <span>{{ surchargeLabel }}</span>
                <strong>+ {{ formatCurrency(surchargeTotal) }}</strong>
            </div>
            <div class="summary-row">
                <span>Pago</span>
                <strong>{{ formatCurrency(paidTotal) }}</strong>
            </div>
            <div class="summary-row">
                <span>Restante</span>
                <strong>{{ formatCurrency(remainingTotal) }}</strong>
            </div>
            <div class="summary-row" v-if="changeTotal > 0">
                <span>Troco</span>
                <strong>{{ formatCurrency(changeTotal) }}</strong>
            </div>

            <div class="summary-block" v-if="noteSummary">
                <p class="summary-label">Observação</p>
                <p class="summary-note">{{ noteSummary }}</p>
            </div>
        </div>

        <footer class="summary-total">
            <span>Total final</span>
            <p>{{ formatCurrency(netTotal) }}</p>
        </footer>
    </aside>
</template>

<style scoped>
.summary-card {
    height: 100%;
    min-height: 0;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    background: color-mix(in srgb, var(--color-bg-elevated) 82%, var(--color-bg-surface));
    padding: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.summary-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.summary-title {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 800;
    color: var(--color-text);
}

.summary-status {
    border-radius: 999px;
    border: 1px solid color-mix(in srgb, var(--color-primary) 42%, transparent);
    background: color-mix(in srgb, var(--color-primary) 12%, var(--color-bg-surface));
    color: var(--color-primary);
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.15rem 0.5rem;
}

.summary-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    font-size: 0.84rem;
    color: var(--color-text-muted);
}

.summary-row strong {
    color: var(--color-text);
    font-weight: 800;
}

.summary-scroll {
    min-height: 0;
    flex: 1;
    overflow: auto;
    display: grid;
    align-content: start;
    gap: 0.6rem;
}

.summary-total {
    margin-top: auto;
    border-radius: var(--radius-md);
    border: 1px solid color-mix(in srgb, var(--color-primary) 44%, transparent);
    background: color-mix(in srgb, var(--color-primary) 12%, var(--color-bg-surface));
    padding: 0.75rem;
}

.summary-total span {
    font-size: 0.75rem;
    color: var(--color-text-muted);
    font-weight: 700;
}

.summary-total p {
    margin: 0.15rem 0 0;
    color: var(--color-primary);
    font-size: 1.55rem;
    line-height: 1;
    font-weight: 900;
}

.summary-block {
    border-top: 1px dashed color-mix(in srgb, var(--color-border) 75%, transparent);
    padding-top: 0.55rem;
}

.summary-label {
    margin: 0;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    color: var(--color-text-muted);
}

.summary-customer {
    margin: 0.25rem 0 0;
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--color-text);
}

.summary-note {
    margin: 0.25rem 0 0;
    font-size: 0.82rem;
    color: var(--color-text-muted);
}
</style>
