<script setup>
import { computed } from 'vue';
import SaleReceiptItem from './SaleReceiptItem.vue';

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
    formatCurrency: {
        type: Function,
        required: true,
    },
    emitter: {
        type: Object,
        default: () => ({}),
    },
    saleContext: {
        type: Object,
        default: null,
    },
});

function roundMoney(value) {
    return Math.round((Number(value) || 0) * 100) / 100;
}

const emitterData = computed(() => {
    const incoming = props.emitter || {};

    return {
        name: String(incoming.name || 'EMPRESA NÃO CONFIGURADA'),
        cnpj: String(incoming.cnpj || '--.--.---/----.--'),
        ie: String(incoming.ie || 'ISENTO'),
        address: String(incoming.address || 'Endereço não informado'),
        city: String(incoming.city || 'Cidade não informada'),
        state: String(incoming.state || 'UF'),
        phone: String(incoming.phone || '(00) 0000-0000'),
    };
});

const issueDateLabel = computed(() =>
    new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date()),
);

const documentNumber = computed(() => String(Math.max(props.items.length, 0)).padStart(6, '0'));

const documentTotal = computed(() =>
    roundMoney(
        props.items.reduce((total, item) => {
            return total + Number(item?.qty || 0) * Number(item?.preco_venda || 0);
        }, 0),
    ),
);

const commandContextLabel = computed(() => {
    const context = props.saleContext;
    if (!context || typeof context !== 'object') return '';

    const tableCode = String(context.tableCode || '').trim();
    const commandCode = String(context.commandCode || '').trim();
    if (!tableCode && !commandCode) return '';

    if (tableCode && commandCode) return `Mesa ${tableCode} · Ficha ${commandCode}`;
    if (tableCode) return `Mesa ${tableCode}`;
    return `Ficha ${commandCode}`;
});

function resolveItemKey(item) {
    const normalizedId = String(item?.id ?? item?.codigo ?? 'item');
    const normalizedAdjustment = String(item?.adjustment_signature ?? '');
    const normalizedPrice = String(Number(item?.preco_venda ?? 0));
    return `${normalizedId}:${normalizedAdjustment}:${normalizedPrice}`;
}
</script>

<template>
    <section class="sale-receipt-preview">
        <article class="sale-receipt-paper" aria-label="Prévia fiscal da venda">
            <header class="sale-receipt-paper__header">
                <p class="sale-receipt-paper__title">PRÉVIA NFC-e</p>
                <p class="sale-receipt-paper__subtitle">Documento auxiliar de conferência</p>
            </header>

            <section class="sale-receipt-paper__issuer">
                <p class="sale-receipt-paper__issuer-name">{{ emitterData.name }}</p>
                <p class="sale-receipt-paper__issuer-line">CNPJ: {{ emitterData.cnpj }} • IE: {{ emitterData.ie }}</p>
                <p class="sale-receipt-paper__issuer-line">{{ emitterData.address }}</p>
                <p class="sale-receipt-paper__issuer-line">{{ emitterData.city }} / {{ emitterData.state }} • Tel: {{ emitterData.phone }}</p>
            </section>

            <section class="sale-receipt-paper__meta">
                <p><span>Número:</span> <strong>{{ documentNumber }}</strong></p>
                <p><span>Emissão:</span> <strong>{{ issueDateLabel }}</strong></p>
                <p><span>Itens:</span> <strong>{{ items.length }}</strong></p>
            </section>

            <div class="sale-receipt-paper__table-wrap">
                <table class="sale-receipt-paper__table">
                    <thead>
                        <tr>
                            <th class="sale-receipt-paper__col sale-receipt-paper__col--item">ITEM</th>
                            <th class="sale-receipt-paper__col sale-receipt-paper__col--code">CÓDIGO</th>
                            <th class="sale-receipt-paper__col sale-receipt-paper__col--description">DESCRIÇÃO</th>
                            <th class="sale-receipt-paper__col sale-receipt-paper__col--qtd">QTD</th>
                            <th class="sale-receipt-paper__col sale-receipt-paper__col--unit">UN</th>
                            <th class="sale-receipt-paper__col sale-receipt-paper__col--unit-price">VL UNIT(R$)</th>
                            <th class="sale-receipt-paper__col sale-receipt-paper__col--line-total">VL ITEM(R$)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="items.length === 0">
                            <td colspan="7" class="sale-receipt-paper__empty">
                                Nenhum item lançado na nota.
                            </td>
                        </tr>
                        <SaleReceiptItem
                            v-for="(item, index) in items"
                            :key="resolveItemKey(item)"
                            :index="index"
                            :item="item"
                            :format-currency="formatCurrency"
                        />
                    </tbody>
                </table>
            </div>

            <footer class="sale-receipt-paper__totals">
                <div class="sale-receipt-paper__totals-row sale-receipt-paper__totals-row--total">
                    <span class="sale-receipt-paper__totals-label">
                        <span class="sale-receipt-paper__totals-title">TOTAL GERAL</span>
                        <small v-if="commandContextLabel" class="sale-receipt-paper__command-context">{{ commandContextLabel }}</small>
                    </span>
                    <strong>{{ formatCurrency(documentTotal) }}</strong>
                </div>
            </footer>
        </article>
    </section>
</template>

<style scoped>
.sale-receipt-preview {
    height: 100%;
    min-height: 0;
    display: flex;
}

.sale-receipt-paper {
    --receipt-paper-bg: #eef1bc;
    --receipt-paper-bg-alt: #e7ebb0;
    --receipt-paper-head: #e2e7a0;
    --receipt-paper-line: #cfd69a;
    --receipt-paper-text: #171717;
    --receipt-paper-muted: #2c2c2c;

    width: 100%;
    height: 100%;
    min-height: 0;
    display: grid;
    grid-template-rows: auto auto auto minmax(0, 1fr) auto;
    border-radius: 0.58rem;
    border: 1px solid var(--receipt-paper-line);
    background: linear-gradient(180deg, var(--receipt-paper-bg), var(--receipt-paper-bg-alt));
    box-shadow: 0 10px 24px rgb(0 0 0 / 0.24);
    color: var(--receipt-paper-text);
    overflow: hidden;
}

.sale-receipt-paper__header {
    border-bottom: 1px solid var(--receipt-paper-line);
    padding: 0.66rem 0.78rem 0.56rem;
    text-align: center;
}

.sale-receipt-paper__title {
    margin: 0;
    font-size: 0.95rem;
    line-height: 1.15;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    font-weight: 900;
}

.sale-receipt-paper__subtitle {
    margin: 0.15rem 0 0;
    font-size: 0.67rem;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    color: var(--receipt-paper-muted);
    font-weight: 700;
}

.sale-receipt-paper__issuer {
    border-bottom: 1px solid var(--receipt-paper-line);
    padding: 0.5rem 0.72rem;
    display: grid;
    gap: 0.12rem;
}

.sale-receipt-paper__issuer-name {
    margin: 0;
    font-size: 0.83rem;
    line-height: 1.2;
    font-weight: 900;
    text-transform: uppercase;
}

.sale-receipt-paper__issuer-line {
    margin: 0;
    font-size: 0.68rem;
    line-height: 1.28;
    color: var(--receipt-paper-muted);
}

.sale-receipt-paper__meta {
    border-bottom: 1px solid var(--receipt-paper-line);
    background: color-mix(in srgb, var(--receipt-paper-head) 88%, var(--receipt-paper-bg));
    padding: 0.36rem 0.72rem;
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.35rem;
}

.sale-receipt-paper__meta p {
    margin: 0;
    font-size: 0.65rem;
    line-height: 1.25;
    color: var(--receipt-paper-muted);
    text-transform: uppercase;
    letter-spacing: 0.02em;
}

.sale-receipt-paper__meta strong {
    color: var(--receipt-paper-text);
    font-family: var(--font-mono);
    font-size: 0.7rem;
}

.sale-receipt-paper__table-wrap {
    min-height: 0;
    overflow-x: auto;
    overflow-y: auto;
    background: color-mix(in srgb, var(--receipt-paper-bg) 95%, #ffffff);
}

.sale-receipt-paper__table {
    width: 100%;
    min-width: 30rem;
    border-collapse: collapse;
    table-layout: fixed;
}

.sale-receipt-paper__table th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: var(--receipt-paper-head);
    border-bottom: 1px solid var(--receipt-paper-line);
    padding: 0.28rem 0.3rem;
    color: var(--receipt-paper-text);
    font-size: 0.58rem;
    line-height: 1.2;
    letter-spacing: 0.02em;
    text-transform: uppercase;
    font-weight: 800;
    text-align: left;
    white-space: nowrap;
}

.sale-receipt-paper__col--item {
    width: 2.2rem;
    text-align: center;
}

.sale-receipt-paper__col--code {
    width: 4.8rem;
    text-align: center;
}

.sale-receipt-paper__col--description {
    width: auto;
}

.sale-receipt-paper__col--qtd {
    width: 2.7rem;
    text-align: right;
}

.sale-receipt-paper__col--unit {
    width: 2.3rem;
    text-align: center;
}

.sale-receipt-paper__col--unit-price,
.sale-receipt-paper__col--line-total {
    width: 4.9rem;
    text-align: right;
}

.sale-receipt-paper__empty {
    padding: 1.35rem 0.8rem;
    text-align: center;
    font-size: 0.78rem;
    color: var(--receipt-paper-muted);
}

.sale-receipt-paper__totals {
    border-top: 1px solid var(--receipt-paper-line);
    padding: 0.44rem 0.72rem 0.56rem;
    display: grid;
    gap: 0.24rem;
    background: color-mix(in srgb, var(--receipt-paper-head) 76%, var(--receipt-paper-bg));
}

.sale-receipt-paper__totals-row {
    display: flex;
    align-items: baseline;
    justify-content: flex-end;
    gap: 1rem;
    font-size: 0.7rem;
    color: var(--receipt-paper-muted);
}

.sale-receipt-paper__totals-row strong {
    min-width: 8.25rem;
    text-align: right;
    color: var(--receipt-paper-text);
    font-family: var(--font-mono);
    font-size: 0.77rem;
    font-weight: 700;
}

.sale-receipt-paper__totals-row--total {
    color: var(--receipt-paper-text);
    font-weight: 800;
    letter-spacing: 0.02em;
    justify-content: space-between;
    align-items: flex-end;
}

.sale-receipt-paper__totals-row--total strong {
    font-size: 0.92rem;
    font-weight: 900;
}

.sale-receipt-paper__totals-label {
    display: grid;
    gap: 0.08rem;
}

.sale-receipt-paper__command-context {
    font-size: 0.61rem;
    font-family: var(--font-mono);
    color: color-mix(in srgb, var(--receipt-paper-text) 78%, var(--receipt-paper-muted));
    text-transform: none;
}

.sale-receipt-paper__totals-title {
    font-size: 0.73rem;
    font-weight: 800;
}

@media (max-width: 768px) {
    .sale-receipt-paper {
        grid-template-rows: auto auto auto minmax(13rem, 1fr) auto;
    }

    .sale-receipt-paper__meta {
        grid-template-columns: 1fr;
    }

    .sale-receipt-paper__totals-row {
        justify-content: space-between;
        gap: 0.5rem;
    }

    .sale-receipt-paper__totals-row strong {
        min-width: 0;
    }

    .sale-receipt-paper__table {
        min-width: 27.5rem;
    }
}
</style>
