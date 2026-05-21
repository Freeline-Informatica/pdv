<script setup>
import AppBadge from '../../ui/AppBadge.vue';

defineProps({
    title: {
        type: String,
        default: '',
    },
    subtitle: {
        type: String,
        default: '',
    },
    tableLabel: {
        type: String,
        default: '',
    },
    commandLabel: {
        type: String,
        default: '',
    },
    waiterName: {
        type: String,
        default: '',
    },
    statusLabel: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <header class="restaurant-header ui-card">
        <div class="restaurant-header__main">
            <h2 class="restaurant-header__title">{{ title }}</h2>
            <p v-if="subtitle" class="restaurant-header__subtitle">{{ subtitle }}</p>
            <div class="restaurant-header__meta">
                <AppBadge v-if="tableLabel" variant="default">Mesa: {{ tableLabel }}</AppBadge>
                <AppBadge v-if="commandLabel" variant="default">Ficha: {{ commandLabel }}</AppBadge>
                <AppBadge v-if="waiterName" variant="success">Garcom: {{ waiterName }}</AppBadge>
                <AppBadge v-if="statusLabel" variant="warning">{{ statusLabel }}</AppBadge>
            </div>
        </div>

        <div class="restaurant-header__actions">
            <slot name="actions" />
        </div>
    </header>
</template>

<style scoped>
.restaurant-header {
    padding: 0.78rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.72rem;
    flex-wrap: wrap;
}

.restaurant-header__title {
    margin: 0;
    font-size: 1.03rem;
    font-weight: 800;
    color: var(--color-text);
}

.restaurant-header__subtitle {
    margin: 0.26rem 0 0;
    color: var(--color-text-muted);
    font-size: 0.84rem;
}

.restaurant-header__meta {
    margin-top: 0.5rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.restaurant-header__actions {
    display: inline-flex;
    align-items: center;
    gap: 0.48rem;
    flex-wrap: wrap;
}
</style>
