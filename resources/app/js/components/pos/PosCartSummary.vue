<script setup>
import { computed } from 'vue';

const props = defineProps({
    subtotal: {
        type: [String, Number],
        default: '',
    },
    saleContext: {
        type: Object,
        default: null,
    },
});

const commandContextLabel = computed(() => {
    const context = props.saleContext;
    if (!context || typeof context !== 'object') return '';

    const tableCode = String(context.tableCode || '').trim();
    const commandCode = String(context.commandCode || '').trim();
    if (!tableCode && !commandCode) return '';

    if (tableCode && commandCode) {
        return `Mesa ${tableCode} · Ficha ${commandCode}`;
    }

    if (tableCode) return `Mesa ${tableCode}`;
    return `Ficha ${commandCode}`;
});

</script>

<template>
    <section class="sale-summary">
        <header class="sale-summary__head">
            <p class="sale-summary__eyebrow">Fechamento fiscal</p>
            <span class="sale-summary__chip">NFC-e</span>
        </header>

        <div class="sale-summary__rows">
            <div class="sale-summary__row">
                <span class="sale-summary__label">Subtotal</span>
                <span class="sale-summary__value">{{ subtotal }}</span>
            </div>
            <div class="sale-summary__row">
                <span class="sale-summary__label">Desconto</span>
                <span class="sale-summary__value">R$ 0,00</span>
            </div>
            <div class="sale-summary__divider" />
            <div class="sale-summary__row sale-summary__row--total">
                <span class="sale-summary__total-wrap">
                    <span class="sale-summary__total-label">Total :</span>
                    <small v-if="commandContextLabel" class="sale-summary__command-context">{{ commandContextLabel }}</small>
                </span>
                <span class="sale-summary__total-value">{{ subtotal }}</span>
            </div>
        </div>
    </section>
</template>

<style scoped>
.sale-summary {
    border-radius: var(--radius-md);
    border: 1px solid color-mix(in srgb, var(--color-primary) 52%, transparent);
    background: linear-gradient(
        165deg,
        color-mix(in srgb, var(--color-primary) 10%, var(--color-bg-elevated)),
        color-mix(in srgb, var(--color-bg-surface) 84%, var(--color-bg-elevated))
    );
    box-shadow:
        0 0 0 1px color-mix(in srgb, var(--color-primary) 18%, transparent),
        0 0 24px -8px color-mix(in srgb, var(--color-primary) 40%, transparent);
    padding: 0.72rem 0.78rem;
    display: grid;
    gap: 0.62rem;
}

.sale-summary__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.sale-summary__eyebrow {
    margin: 0;
    color: var(--color-text-muted);
    font-size: 0.68rem;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    font-weight: 700;
}

.sale-summary__chip {
    border-radius: 999px;
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 64%, transparent);
    background: color-mix(in srgb, var(--color-bg-surface) 86%, var(--color-bg-elevated));
    color: var(--color-text-muted);
    font-size: 0.67rem;
    font-family: var(--font-mono);
    font-weight: 700;
    padding: 0.2rem 0.48rem;
}

.sale-summary__rows {
    display: grid;
    gap: 0.45rem;
}

.sale-summary__row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.45rem;
}

.sale-summary__label {
    color: var(--color-text-muted);
    font-size: 0.86rem;
}

.sale-summary__value {
    color: var(--color-text);
    font-family: var(--font-mono);
    font-size: 0.94rem;
    font-weight: 700;
}

.sale-summary__divider {
    height: 1px;
    background: color-mix(in srgb, var(--color-border) 84%, transparent);
}

.sale-summary__row--total {
    margin-top: 0.12rem;
    border-radius: calc(var(--radius-sm) + 0.1rem);
    border: 1px solid color-mix(in srgb, var(--color-primary) 82%, transparent);
    background: linear-gradient(
        155deg,
        color-mix(in srgb, var(--color-primary) 26%, var(--color-bg-elevated)),
        color-mix(in srgb, var(--color-primary) 14%, var(--color-bg-surface))
    );
    box-shadow:
        inset 0 1px 0 color-mix(in srgb, var(--color-primary) 34%, transparent),
        0 0 0 1px color-mix(in srgb, var(--color-primary) 42%, transparent),
        0 0 18px -7px color-mix(in srgb, var(--color-primary) 62%, transparent);
    padding: 0.62rem 0.68rem;
}

.sale-summary__total-label {
    color: color-mix(in srgb, var(--color-text) 90%, var(--color-primary));
    font-size: 1.6rem;
    font-weight: 800;
    line-height: 1;
}

.sale-summary__total-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.18rem;
}

.sale-summary__command-context {
    color: color-mix(in srgb, var(--color-text) 74%, var(--color-primary));
    font-size: 0.7rem;
    font-family: var(--font-mono);
    font-weight: 700;
}

.sale-summary__total-value {
    color: var(--color-primary);
    font-family: var(--font-mono);
    font-size: 2rem;
    font-weight: 900;
    line-height: 1;
    text-shadow: 0 0 12px color-mix(in srgb, var(--color-primary) 42%, transparent);
}
</style>
