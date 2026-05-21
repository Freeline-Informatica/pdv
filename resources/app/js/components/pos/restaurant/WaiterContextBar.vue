<script setup>
import AppBadge from '../../ui/AppBadge.vue';
import AppButton from '../../ui/AppButton.vue';

defineProps({
    waiterName: {
        type: String,
        default: 'Equipe',
    },
    tableLabel: {
        type: String,
        default: '--',
    },
    commandLabel: {
        type: String,
        default: '--',
    },
    statusLabel: {
        type: String,
        default: 'Sem status',
    },
    statusVariant: {
        type: String,
        default: 'default',
    },
    totalLabel: {
        type: String,
        default: 'R$ 0,00',
    },
});

const emit = defineEmits(['switch']);
</script>

<template>
    <section class="waiter-context-bar ui-card">
        <div class="waiter-context-bar__meta">
            <AppBadge variant="success">Garcom: {{ waiterName || 'Equipe' }}</AppBadge>
            <AppBadge variant="default">Mesa: {{ tableLabel || '--' }}</AppBadge>
            <AppBadge variant="default">Ficha: {{ commandLabel || '--' }}</AppBadge>
            <AppBadge :variant="statusVariant">{{ statusLabel }}</AppBadge>
            <AppBadge variant="warning">Total: {{ totalLabel }}</AppBadge>
        </div>

        <AppButton variant="secondary" class="waiter-context-bar__switch" @click="emit('switch')">
            Trocar mesa/ficha
        </AppButton>
    </section>
</template>

<style scoped>
.waiter-context-bar {
    position: sticky;
    top: 0.5rem;
    z-index: 20;
    padding: 0.66rem;
    display: grid;
    gap: 0.55rem;
    border-color: color-mix(in srgb, var(--color-primary) 32%, var(--color-border));
}

.waiter-context-bar__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.45rem;
}

.waiter-context-bar__meta :deep(.ui-badge) {
    padding: 0.32rem 0.72rem;
    font-size: 0.84rem;
    line-height: 1.2;
}

.waiter-context-bar__switch {
    justify-self: start;
}

@media (min-width: 960px) {
    .waiter-context-bar {
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: center;
    }

    .waiter-context-bar__switch {
        justify-self: end;
    }
}
</style>
