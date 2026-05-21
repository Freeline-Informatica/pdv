<script setup>
import RestaurantProductCard from './RestaurantProductCard.vue';
import RestaurantEmptyState from './RestaurantEmptyState.vue';

defineProps({
    products: {
        type: Array,
        default: () => [],
    },
    compact: {
        type: Boolean,
        default: false,
    },
    formatCurrency: {
        type: Function,
        required: true,
    },
});

const emit = defineEmits(['add']);
</script>

<template>
    <div v-if="products.length" class="restaurant-product-grid">
        <RestaurantProductCard
            v-for="product in products"
            :key="product.id"
            :product="product"
            :compact="compact"
            :format-currency="formatCurrency"
            @add="emit('add', $event)"
        />
    </div>

    <RestaurantEmptyState
        v-else
        title="Nenhum produto encontrado"
        description="Ajuste os filtros de busca e categoria para listar itens do cardapio."
    />
</template>

<style scoped>
.restaurant-product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
    gap: 0.65rem;
}

@media (max-width: 768px) {
    .restaurant-product-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>
