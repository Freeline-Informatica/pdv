<script setup>
import CommandStatusBadge from './CommandStatusBadge.vue';

const props = defineProps({
    table: {
        type: Object,
        default: null,
    },
    command: {
        type: Object,
        default: null,
    },
    formatCurrency: {
        type: Function,
        required: true,
    },
});
</script>

<template>
    <header class="command-header" v-if="table && command">
        <div class="command-header__main">
            <p class="command-header__title">
                Mesa {{ table.code }} • Comanda {{ command.code }}
            </p>
            <p class="command-header__subtitle">
                {{ table.customerName }}
                <span>• Garçom: {{ command.waiterName || table.waiterName || 'Equipe' }}</span>
                <span v-if="table.status === 'closed'">• {{ table.closedAtLabel }}</span>
                <span v-else>• {{ table.openedAtLabel }}</span>
            </p>
        </div>

        <div class="command-header__meta">
            <CommandStatusBadge :status="command.status" />
            <strong>{{ formatCurrency(command.total) }}</strong>
        </div>
    </header>
</template>

<style scoped>
.command-header {
    border-bottom: 1px dashed color-mix(in srgb, var(--color-border) 75%, transparent);
    padding-bottom: 0.48rem;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.68rem;
}

.command-header__main {
    display: grid;
    gap: 0.14rem;
}

.command-header__title {
    margin: 0;
    font-size: 0.92rem;
    color: var(--color-text);
    font-weight: 800;
}

.command-header__subtitle {
    margin: 0;
    font-size: 0.74rem;
    color: var(--color-text-muted);
    display: flex;
    flex-wrap: wrap;
    gap: 0.2rem;
}

.command-header__meta {
    display: grid;
    justify-items: end;
    gap: 0.16rem;
}

.command-header__meta strong {
    color: var(--color-primary);
    font-size: 1rem;
    line-height: 1;
}

@media (max-width: 960px) {
    .command-header {
        flex-direction: column;
        align-items: stretch;
    }

    .command-header__meta {
        justify-items: start;
    }
}
</style>
