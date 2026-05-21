<script setup>
import { computed } from 'vue';
import EmptyStateBlock from './EmptyStateBlock.vue';

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
    formatCurrency: {
        type: Function,
        required: true,
    },
    showUnitPrice: {
        type: Boolean,
        default: true,
    },
});

function formatDecimal(value, decimals = 3) {
    return Number(value || 0).toLocaleString('pt-BR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: decimals,
    });
}

function roundMoney(value) {
    return Math.round((Number(value) || 0) * 100) / 100;
}

const normalizedItems = computed(() => props.items.map((item, index) => {
    const quantity = Number(item?.qty || 0);
    const unitPrice = Number(item?.preco_venda || 0);
    const total = roundMoney(quantity * unitPrice);
    const latestHistory = Array.isArray(item?.history) && item.history.length ? item.history[item.history.length - 1] : null;

    return {
        id: `${item?.id || 'item'}-${index}`,
        nome: String(item?.nome || 'Item sem descrição'),
        codigo: String(item?.codigo || ''),
        unidade: String(item?.unidade || 'UN'),
        quantity,
        unitPrice,
        total,
        observation: String(item?.observation || '').trim(),
        sellerName: String(item?.sellerName || latestHistory?.by || 'Equipe'),
        latestHistory,
    };
}));
</script>

<template>
    <div class="command-item-list">
        <article v-for="item in normalizedItems" :key="item.id" class="command-item-row">
            <header class="command-item-row__head">
                <div>
                    <p class="command-item-row__name">{{ item.nome }}</p>
                    <p class="command-item-row__meta">
                        {{ formatDecimal(item.quantity) }} {{ item.unidade }}
                        <span v-if="item.codigo">• Cód. {{ item.codigo }}</span>
                        <span>• Atendente: {{ item.sellerName }}</span>
                    </p>
                </div>

                <div class="command-item-row__totals">
                    <p v-if="showUnitPrice" class="command-item-row__unit-price">
                        {{ formatCurrency(item.unitPrice) }} un.
                    </p>
                    <strong>{{ formatCurrency(item.total) }}</strong>
                </div>
            </header>

            <p v-if="item.observation" class="command-item-row__observation">
                Obs.: {{ item.observation }}
            </p>

            <p v-if="item.latestHistory" class="command-item-row__history">
                Último movimento: {{ item.latestHistory.action }} por {{ item.latestHistory.by }} ({{ item.latestHistory.atLabel }})
            </p>
        </article>

        <EmptyStateBlock
            v-if="!normalizedItems.length"
            title="Sem itens na comanda"
            description="Adicione itens para iniciar a operação desta ficha."
        />
    </div>
</template>

<style scoped>
.command-item-list {
    display: grid;
    align-content: start;
    gap: 0.45rem;
    min-height: 0;
    overflow: auto;
    padding-right: 0.1rem;
}

.command-item-row {
    border-radius: 0.7rem;
    border: 1px solid color-mix(in srgb, var(--color-border) 70%, transparent);
    background: color-mix(in srgb, var(--color-bg-surface) 92%, var(--color-bg-elevated));
    padding: 0.52rem 0.58rem;
    display: grid;
    gap: 0.15rem;
}

.command-item-row__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.58rem;
}

.command-item-row__name {
    margin: 0;
    font-size: 0.82rem;
    font-weight: 800;
    color: var(--color-text);
}

.command-item-row__meta {
    margin: 0.12rem 0 0;
    font-size: 0.72rem;
    color: var(--color-text-muted);
}

.command-item-row__totals {
    text-align: right;
    display: grid;
    gap: 0.05rem;
    justify-items: end;
}

.command-item-row__totals strong {
    font-size: 0.84rem;
    color: var(--color-text);
}

.command-item-row__unit-price {
    margin: 0;
    font-size: 0.68rem;
    color: var(--color-text-muted);
}

.command-item-row__observation {
    margin: 0;
    font-size: 0.7rem;
    color: var(--color-text-muted);
}

.command-item-row__history {
    margin: 0;
    font-size: 0.66rem;
    color: color-mix(in srgb, var(--color-text-muted) 82%, transparent);
}
</style>
