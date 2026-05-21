<script setup>
import AppDrawer from '../../ui/AppDrawer.vue';
import RestaurantOrderCart from './RestaurantOrderCart.vue';
import RestaurantOrderSummary from './RestaurantOrderSummary.vue';

defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    items: {
        type: Array,
        default: () => [],
    },
    subtotal: {
        type: Number,
        default: 0,
    },
    totalItems: {
        type: Number,
        default: 0,
    },
    formatCurrency: {
        type: Function,
        required: true,
    },
    confirmLabel: {
        type: String,
        default: 'Confirmar pedido',
    },
    title: {
        type: String,
        default: 'Pedido atual',
    },
});

const emit = defineEmits(['close', 'increase', 'decrease', 'remove', 'confirm']);
</script>

<template>
    <AppDrawer :open="open" :title="title" @close="emit('close')">
        <div class="restaurant-cart-drawer">
            <RestaurantOrderCart
                :items="items"
                :format-currency="formatCurrency"
                @increase="emit('increase', $event)"
                @decrease="emit('decrease', $event)"
                @remove="emit('remove', $event)"
            />
            <RestaurantOrderSummary
                :format-currency="formatCurrency"
                :subtotal="subtotal"
                :total-items="totalItems"
                :confirm-label="confirmLabel"
                :disabled="!items.length"
                @confirm="emit('confirm')"
            />
        </div>
    </AppDrawer>
</template>

<style scoped>
.restaurant-cart-drawer {
    display: grid;
    gap: 0.58rem;
}
</style>
