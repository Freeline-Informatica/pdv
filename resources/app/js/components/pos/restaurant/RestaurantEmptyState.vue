<script setup>
import AppCard from '../../ui/AppCard.vue';
import AppButton from '../../ui/AppButton.vue';

defineProps({
    title: {
        type: String,
        default: 'Nenhum resultado encontrado',
    },
    description: {
        type: String,
        default: 'Ajuste os filtros e tente novamente.',
    },
    actionLabel: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['action']);
</script>

<template>
    <AppCard class="restaurant-empty">
        <h3 class="restaurant-empty__title">{{ title }}</h3>
        <p class="restaurant-empty__text">{{ description }}</p>
        <AppButton v-if="actionLabel" variant="secondary" @click="emit('action')">{{ actionLabel }}</AppButton>
    </AppCard>
</template>

<style scoped>
.restaurant-empty {
    padding: 1.05rem;
    text-align: center;
    display: grid;
    gap: 0.62rem;
}

.restaurant-empty__title {
    margin: 0;
    font-size: 1.02rem;
    color: var(--color-text);
}

.restaurant-empty__text {
    margin: 0;
    color: var(--color-text-muted);
    font-size: 0.9rem;
}
</style>
