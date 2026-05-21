<script setup>
import { computed, ref, watch } from 'vue';
import { CircleCheck } from 'lucide-vue-next';
import AppButton from '../ui/AppButton.vue';
import AppIconButton from '../ui/AppIconButton.vue';

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
    formatCurrency: {
        type: Function,
        required: true,
    },
    selectedQty: {
        type: Number,
        default: 1,
    },
});

const emit = defineEmits(['decrease-qty', 'increase-qty', 'add']);
const imageLoadFailed = ref(false);
const productImageUrl = computed(() => String(props.product?.imagem_url || '').trim());
const hasProductImage = computed(() => productImageUrl.value !== '');
const shouldShowImage = computed(() => productImageUrl.value !== '' && !imageLoadFailed.value);

watch(productImageUrl, () => {
    imageLoadFailed.value = false;
});
</script>

<template>
    <article class="pos-product-card">
        <div v-if="hasProductImage" class="pos-product-card-media" :class="{ 'is-empty': !shouldShowImage }">
            <img
                v-if="shouldShowImage"
                :src="productImageUrl"
                :alt="`Foto de ${product.nome}`"
                class="pos-product-card-image"
                loading="lazy"
                @error="imageLoadFailed = true"
            >
            <span v-else class="pos-product-card-image-fallback">Sem foto</span>
        </div>
        <p class="text-xs text-muted font-mono">{{ product.codigo || '—' }}</p>
        <p class="mt-2 text-sm font-bold text-main">{{ product.nome }}</p>
        <p class="mt-2 text-base font-black text-[var(--color-primary)]">{{ formatCurrency(product.preco_venda) }}</p>
        <p class="mt-2 text-xs text-muted">Estoque: {{ product.estoque_atual ?? '—' }}</p>

        <div class="mt-3 pos-product-card-controls">
            <div class="pos-product-card-qty">
                <AppIconButton title="Diminuir quantidade" @click="emit('decrease-qty')">-</AppIconButton>
                <span class="pos-product-card-qty-value">{{ selectedQty }}</span>
                <AppIconButton title="Aumentar quantidade" @click="emit('increase-qty')">+</AppIconButton>
            </div>
            <AppButton
                class="pos-product-card-add pos-product-card-add-icon"
                title="Adicionar item"
                aria-label="Adicionar item"
                @click="emit('add')"
            >
                <CircleCheck class="h-5 w-5" aria-hidden="true" />
            </AppButton>
        </div>
    </article>
</template>
