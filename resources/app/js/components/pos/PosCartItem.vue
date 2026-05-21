<script setup>
const props = defineProps({
    item: {
        type: Object,
        required: true,
    },
    formatCurrency: {
        type: Function,
        required: true,
    },
});

</script>

<template>
    <article class="ui-card p-4 pos-cart-item">
        <p class="text-base font-black leading-tight text-main">{{ props.item.nome }}</p>
        <p class="mt-1 text-sm text-muted">{{ formatCurrency(props.item.preco_venda) }} x {{ props.item.qty }}</p>
        <p class="mt-2 text-sm font-semibold text-main">Quantidade lançada: {{ props.item.qty }}</p>
    </article>
</template>

<style scoped>
.pos-cart-item:focus-within {
    border-color: color-mix(in srgb, var(--color-primary) 46%, var(--color-border));
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary) 20%, transparent);
}
</style>
