<script setup>
import { Trash2 } from 'lucide-vue-next';
import AppButton from '../../ui/AppButton.vue';
import RestaurantQuantityStepper from './RestaurantQuantityStepper.vue';
import RestaurantEmptyState from './RestaurantEmptyState.vue';

defineProps({
    items: {
        type: Array,
        default: () => [],
    },
    formatCurrency: {
        type: Function,
        required: true,
    },
    title: {
        type: String,
        default: 'Pedido atual',
    },
});

const emit = defineEmits(['increase', 'decrease', 'remove']);
</script>

<template>
    <section class="restaurant-order-cart ui-card">
        <h3 class="restaurant-order-cart__title">{{ title }}</h3>

        <div v-if="items.length" class="restaurant-order-cart__list">
            <article v-for="item in items" :key="item.id" class="restaurant-order-cart__item">
                <div class="restaurant-order-cart__item-top">
                    <div>
                        <strong class="restaurant-order-cart__item-name">{{ item.nome }}</strong>
                        <p v-if="item.observation" class="restaurant-order-cart__item-note">Obs.: {{ item.observation }}</p>
                        <p v-if="item.selectedOptions?.length" class="restaurant-order-cart__item-note">
                            + {{ item.selectedOptions.map((opt) => opt.nome).join(', ') }}
                        </p>
                        <p v-if="item.removedIngredients?.length" class="restaurant-order-cart__item-note">
                            Sem {{ item.removedIngredients.join(', ') }}
                        </p>
                    </div>
                    <AppButton variant="ghost" @click="emit('remove', item.id)">
                        <Trash2 class="h-4 w-4" aria-hidden="true" />
                    </AppButton>
                </div>

                <div class="restaurant-order-cart__item-bottom">
                    <RestaurantQuantityStepper
                        :model-value="item.quantity"
                        @update:model-value="$event > item.quantity ? emit('increase', item.id) : emit('decrease', item.id)"
                    />
                    <strong>{{ formatCurrency(item.lineTotal) }}</strong>
                </div>
            </article>
        </div>

        <RestaurantEmptyState
            v-else
            title="Carrinho vazio"
            description="Adicione produtos para montar o pedido."
        />
    </section>
</template>

<style scoped>
.restaurant-order-cart {
    padding: 0.72rem;
    display: grid;
    gap: 0.58rem;
}

.restaurant-order-cart__title {
    margin: 0;
    font-size: 0.98rem;
    font-weight: 800;
    color: var(--color-text);
}

.restaurant-order-cart__list {
    display: grid;
    gap: 0.46rem;
}

.restaurant-order-cart__item {
    border: 1px solid var(--color-border);
    border-radius: 0.68rem;
    padding: 0.52rem;
    background: color-mix(in srgb, var(--color-bg-elevated) 76%, var(--color-bg-surface));
}

.restaurant-order-cart__item-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.45rem;
}

.restaurant-order-cart__item-name {
    color: var(--color-text);
    font-size: 0.88rem;
}

.restaurant-order-cart__item-note {
    margin: 0.2rem 0 0;
    color: var(--color-text-muted);
    font-size: 0.74rem;
}

.restaurant-order-cart__item-bottom {
    margin-top: 0.45rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.restaurant-order-cart__item-bottom strong {
    color: var(--color-primary);
    font-size: 0.95rem;
}
</style>
