<script setup>
defineProps({
    item: {
        type: Object,
        default: null,
    },
    itemTotal: {
        type: Number,
        default: 0,
    },
    formatCurrency: {
        type: Function,
        required: true,
    },
    formatDecimal: {
        type: Function,
        required: true,
    },
});
</script>

<template>
    <article class="ui-card pos-last-item-review">
        <header class="flex items-center justify-between gap-2">
            <p class="pos-last-item-heading text-sm font-semibold uppercase tracking-wide text-muted">Último item selecionado</p>
            <span
                v-if="item"
                class="rounded-full border border-[var(--color-border)] bg-[var(--color-bg-elevated)] px-3 py-1 text-xs font-semibold text-main"
            >
                {{ String(item.codigo || 'Sem código') }}
            </span>
        </header>

        <div v-if="item" class="pos-last-item-content mt-4 grid gap-3 md:grid-cols-3">
            <div class="md:col-span-3">
                <p class="pos-last-item-name text-base font-black text-main">
                    {{ item.nome }}
                </p>
                <p class="pos-last-item-unit text-sm text-muted">
                    Unitário: {{ formatCurrency(item.preco_venda) }}
                </p>
            </div>
            <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-bg-elevated)] p-3">
                <p class="text-xs uppercase tracking-wide text-muted">Quantidade</p>
                <p class="pos-last-item-qty-value mt-1 text-2xl font-black text-main">
                    {{ formatDecimal(item.qty) }}
                </p>
            </div>
            <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-bg-elevated)] p-3 md:col-span-2">
                <p class="text-xs uppercase tracking-wide text-muted">Valor do item</p>
                <p class="pos-last-item-total-value mt-1 text-2xl font-black text-[var(--color-primary)]">
                    {{ formatCurrency(itemTotal) }}
                </p>
            </div>
        </div>

        <div v-else class="mt-4 rounded-lg border border-dashed border-[var(--color-border)] p-4">
            <p class="text-sm font-semibold text-main">Nenhum item lançado ainda.</p>
            <p class="text-sm text-muted">Assim que um produto for confirmado, ele aparece aqui para conferência rápida.</p>
        </div>
    </article>
</template>
