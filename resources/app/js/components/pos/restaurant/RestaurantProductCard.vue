<script setup>
import { computed, ref, watch } from 'vue';
import AppButton from '../../ui/AppButton.vue';
import RestaurantStatusBadge from './RestaurantStatusBadge.vue';

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
    formatCurrency: {
        type: Function,
        required: true,
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['add']);

const imageLoadFailed = ref(false);
const productImageUrl = computed(() =>
    String(props.product?.imagemUrl || props.product?.imagem_url || '').trim(),
);
const shouldShowImage = computed(() => productImageUrl.value !== '' && !imageLoadFailed.value);

watch(productImageUrl, () => {
    imageLoadFailed.value = false;
});
</script>

<template>
    <article class="restaurant-product-card ui-card" :class="{ 'is-compact': compact }">
        <div class="restaurant-product-card__media" aria-hidden="true">
            <img
                v-if="shouldShowImage"
                :src="productImageUrl"
                :alt="`Foto de ${product.nome}`"
                class="restaurant-product-card__image"
                loading="lazy"
                @error="imageLoadFailed = true"
            >
            <span v-else>{{ product.nome.slice(0, 1) }}</span>
        </div>

        <div class="restaurant-product-card__body">
            <h3 class="restaurant-product-card__title">{{ product.nome }}</h3>
            <p class="restaurant-product-card__description">{{ product.descricao }}</p>
            <p class="restaurant-product-card__price">{{ formatCurrency(product.preco) }}</p>
        </div>

        <div class="restaurant-product-card__footer">
            <RestaurantStatusBadge v-if="Number(product.estoque || 0) <= 0" status="out_of_stock" />
            <AppButton :disabled="Number(product.estoque || 0) <= 0" @click="emit('add', product)">
                Adicionar
            </AppButton>
        </div>
    </article>
</template>

<style scoped>
.restaurant-product-card {
    padding: 0.7rem;
    display: grid;
    gap: 0.55rem;
}

.restaurant-product-card__media {
    border-radius: 0.7rem;
    min-height: 5.5rem;
    background: linear-gradient(135deg, color-mix(in srgb, var(--color-primary) 22%, var(--color-bg-surface)), var(--color-bg-elevated));
    display: grid;
    place-items: center;
    color: var(--color-primary);
    font-weight: 900;
    font-size: 1.48rem;
    overflow: hidden;
}

.restaurant-product-card__image {
    width: 100%;
    height: 100%;
    display: block;
    object-fit: cover;
}

.restaurant-product-card__title {
    margin: 0;
    font-size: 0.95rem;
    color: var(--color-text);
}

.restaurant-product-card__description {
    margin: 0.22rem 0 0;
    font-size: 0.78rem;
    color: var(--color-text-muted);
    line-height: 1.36;
    min-height: 2.1rem;
}

.restaurant-product-card__price {
    margin: 0.42rem 0 0;
    color: var(--color-primary);
    font-size: 1rem;
    font-weight: 900;
}

.restaurant-product-card__footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.4rem;
}

.restaurant-product-card.is-compact .restaurant-product-card__media {
    min-height: 4.2rem;
}

.restaurant-product-card.is-compact .restaurant-product-card__description {
    min-height: 0;
}
</style>
