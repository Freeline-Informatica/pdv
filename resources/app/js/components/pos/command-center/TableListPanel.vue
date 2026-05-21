<script setup>
import CommandStatusBadge from './CommandStatusBadge.vue';
import EmptyStateBlock from './EmptyStateBlock.vue';

const props = defineProps({
    tables: {
        type: Array,
        default: () => [],
    },
    selectedTableId: {
        type: String,
        default: '',
    },
    selectedCommandId: {
        type: String,
        default: '',
    },
    activeTab: {
        type: String,
        default: 'closed',
    },
    loading: {
        type: Boolean,
        default: false,
    },
    formatCurrency: {
        type: Function,
        required: true,
    },
});

const emit = defineEmits(['select-table', 'select-command']);
</script>

<template>
    <section class="table-list-panel">
        <article
            v-for="table in tables"
            :key="table.id"
            class="table-list-card"
            :class="{ 'is-selected': selectedTableId === table.id }"
        >
            <button type="button" class="table-list-card__head" :disabled="loading" @click="emit('select-table', table.id)">
                <div>
                    <p class="table-list-card__title">Mesa {{ table.code }}</p>
                    <p class="table-list-card__subtitle">{{ table.customerName }}</p>
                    <p class="table-list-card__meta">
                        <span v-if="activeTab === 'closed'">{{ table.closedAtLabel }}</span>
                        <span v-else>{{ table.openedAtLabel }}</span>
                        <span>• {{ table.itemsCount }} item(ns)</span>
                        <span>• {{ table.commandsCount }} ficha(s)</span>
                    </p>
                </div>

                <div class="table-list-card__right">
                    <CommandStatusBadge :status="table.status" small />
                    <strong>{{ formatCurrency(table.total) }}</strong>
                </div>
            </button>

            <div class="table-list-card__commands">
                <button
                    v-for="command in table.commands"
                    :key="command.id"
                    type="button"
                    class="table-list-command-chip"
                    :class="{ 'is-active': selectedCommandId === command.id && selectedTableId === table.id }"
                    :disabled="loading"
                    @click="emit('select-command', { tableId: table.id, commandId: command.id })"
                >
                    <span>Ficha {{ command.code }}</span>
                    <CommandStatusBadge :status="command.status" small />
                </button>
            </div>
        </article>

        <EmptyStateBlock
            v-if="!tables.length && !loading"
            :title="activeTab === 'closed' ? 'Sem mesas fechadas' : 'Sem mesas abertas'"
            :description="activeTab === 'closed'
                ? 'Nenhuma comanda fechada disponível para faturamento no momento.'
                : 'Nenhuma mesa aberta disponível para operação no momento.'"
        />
        <p v-if="loading" class="table-list-loading">Carregando mesas e fichas...</p>
    </section>
</template>

<style scoped>
.table-list-panel {
    min-height: 0;
    overflow: auto;
    padding-right: 0.1rem;
    display: grid;
    align-content: start;
    gap: 0.45rem;
}

.table-list-card {
    border-radius: 0.75rem;
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 45%, transparent);
    background: color-mix(in srgb, var(--color-bg-surface) 92%, var(--color-bg-elevated));
    padding: 0.42rem;
    display: grid;
    gap: 0.38rem;
    transition: all var(--transition-fast);
}

.table-list-card:hover {
    border-color: color-mix(in srgb, var(--color-primary) 55%, transparent);
}

.table-list-card.is-selected {
    border-color: color-mix(in srgb, var(--color-primary) 68%, transparent);
    background: color-mix(in srgb, var(--color-primary) 14%, var(--color-bg-surface));
}

.table-list-card__head {
    width: 100%;
    text-align: left;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.55rem;
    border-radius: var(--radius-sm);
    padding: 0.25rem;
    color: inherit;
}

.table-list-card__title {
    margin: 0;
    color: var(--color-text);
    font-size: 0.9rem;
    font-weight: 800;
}

.table-list-card__subtitle {
    margin: 0.1rem 0 0;
    color: var(--color-text-muted);
    font-size: 0.76rem;
}

.table-list-card__meta {
    margin: 0.15rem 0 0;
    color: var(--color-text-muted);
    font-size: 0.7rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.2rem;
}

.table-list-card__right {
    display: grid;
    justify-items: end;
    gap: 0.16rem;
}

.table-list-card__right strong {
    color: var(--color-text);
    font-size: 0.86rem;
}

.table-list-card__commands {
    display: grid;
    gap: 0.3rem;
}

.table-list-command-chip {
    width: 100%;
    border-radius: var(--radius-sm);
    border: 1px solid color-mix(in srgb, var(--color-border) 72%, transparent);
    background: color-mix(in srgb, var(--color-bg-elevated) 78%, var(--color-bg-surface));
    color: var(--color-text);
    display: inline-flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.4rem;
    padding: 0.3rem 0.45rem;
    font-size: 0.73rem;
    font-weight: 700;
    transition: all var(--transition-fast);
}

.table-list-command-chip:hover {
    border-color: color-mix(in srgb, var(--color-primary) 52%, transparent);
}

.table-list-command-chip.is-active {
    border-color: color-mix(in srgb, var(--color-primary) 62%, transparent);
    background: color-mix(in srgb, var(--color-primary) 12%, var(--color-bg-surface));
}

.table-list-card__head:disabled,
.table-list-command-chip:disabled {
    cursor: not-allowed;
    opacity: 0.72;
}

.table-list-loading {
    margin: 0;
    font-size: 0.78rem;
    color: var(--color-text-muted);
    padding: 0.4rem 0.2rem;
}
</style>
