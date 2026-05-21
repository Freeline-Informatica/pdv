<script setup>
import { computed } from 'vue';

const props = defineProps({
    item: {
        type: Object,
        required: true,
    },
    index: {
        type: Number,
        required: true,
    },
    formatCurrency: {
        type: Function,
        required: true,
    },
});

const quantity = computed(() => Number(props.item?.qty || 0));
const unitPrice = computed(() => Number(props.item?.preco_venda || 0));
const lineTotal = computed(() => Math.round(quantity.value * unitPrice.value * 100) / 100);
const itemNumber = computed(() => String(props.index + 1).padStart(3, '0'));
const productCode = computed(() => String(props.item?.codigo || '-'));
const unitLabel = computed(() => String(props.item?.unidade || 'UN'));
const quantityLabel = computed(() =>
    quantity.value.toLocaleString('pt-BR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 3,
    }),
);
</script>

<template>
    <tr class="sale-receipt-row">
        <td class="sale-receipt-row__cell sale-receipt-row__cell--index">{{ itemNumber }}</td>
        <td class="sale-receipt-row__cell sale-receipt-row__cell--code">{{ productCode }}</td>
        <td class="sale-receipt-row__cell sale-receipt-row__cell--description">
            {{ props.item.nome }}
        </td>
        <td class="sale-receipt-row__cell sale-receipt-row__cell--numeric">{{ quantityLabel }}</td>
        <td class="sale-receipt-row__cell sale-receipt-row__cell--unit">{{ unitLabel }}</td>
        <td class="sale-receipt-row__cell sale-receipt-row__cell--money">{{ formatCurrency(unitPrice) }}</td>
        <td class="sale-receipt-row__cell sale-receipt-row__cell--money sale-receipt-row__cell--line-total">{{ formatCurrency(lineTotal) }}</td>
    </tr>
</template>

<style scoped>
.sale-receipt-row {
    border-bottom: 1px solid var(--receipt-paper-line, #cfd69a);
}

.sale-receipt-row:last-child {
    border-bottom: 0;
}

.sale-receipt-row__cell {
    padding: 0.24rem 0.3rem;
    color: var(--receipt-paper-text, #171717);
    font-size: 0.68rem;
    vertical-align: top;
    line-height: 1.22;
}

.sale-receipt-row__cell--index,
.sale-receipt-row__cell--code,
.sale-receipt-row__cell--numeric,
.sale-receipt-row__cell--unit,
.sale-receipt-row__cell--money {
    font-family: var(--font-mono);
}

.sale-receipt-row__cell--description {
    word-break: normal;
    overflow-wrap: anywhere;
    white-space: normal;
    font-weight: 600;
}

.sale-receipt-row__cell--numeric,
.sale-receipt-row__cell--money,
.sale-receipt-row__cell--line-total {
    text-align: right;
}

.sale-receipt-row__cell--unit,
.sale-receipt-row__cell--index,
.sale-receipt-row__cell--code {
    text-align: center;
}

.sale-receipt-row__cell--code {
    overflow: hidden;
    text-overflow: ellipsis;
}

.sale-receipt-row__cell--index,
.sale-receipt-row__cell--code,
.sale-receipt-row__cell--numeric,
.sale-receipt-row__cell--unit,
.sale-receipt-row__cell--money {
    white-space: nowrap;
}

.sale-receipt-row__cell--line-total {
    font-weight: 700;
}
</style>
