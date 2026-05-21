<script setup>
import AppCheckbox from '../../ui/AppCheckbox.vue';
import TableSearchInput from './TableSearchInput.vue';

const props = defineProps({
    activeTab: {
        type: String,
        default: 'closed',
    },
    summary: {
        type: Object,
        default: () => ({
            tablesClosed: 0,
            tablesOpened: 0,
            commandsClosed: 0,
            commandsOpened: 0,
        }),
    },
    searchQuery: {
        type: String,
        default: '',
    },
    onlyPendingFiscal: {
        type: Boolean,
        default: false,
    },
    showUnitPrice: {
        type: Boolean,
        default: true,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits([
    'update:activeTab',
    'update:searchQuery',
    'update:onlyPendingFiscal',
    'update:showUnitPrice',
]);

function isClosedTab() {
    return props.activeTab === 'closed';
}
</script>

<template>
    <div class="command-filters-root">
        <div class="command-filters-tabs" role="tablist" aria-label="Visão de comandas">
            <button
                type="button"
                class="command-filters-tab"
                :class="{ 'is-active': activeTab === 'closed' }"
                :aria-selected="activeTab === 'closed'"
                :disabled="disabled"
                @click="emit('update:activeTab', 'closed')"
            >
                <span>Mesas aguardando pagamento</span>
                <strong>{{ summary.tablesClosed }}</strong>
                <small>{{ summary.commandsClosed }} comandas</small>
            </button>

            <button
                type="button"
                class="command-filters-tab"
                :class="{ 'is-active': activeTab === 'opened' }"
                :aria-selected="activeTab === 'opened'"
                :disabled="disabled"
                @click="emit('update:activeTab', 'opened')"
            >
                <span>Mesas em andamento</span>
                <strong>{{ summary.tablesOpened }}</strong>
                <small>{{ summary.commandsOpened }} comandas</small>
            </button>
        </div>

        <div class="command-filters-controls">
            <TableSearchInput
                :model-value="searchQuery"
                :disabled="disabled"
                placeholder="Pesquisar número da mesa e listar todas as fichas vinculadas"
                @update:model-value="emit('update:searchQuery', $event)"
            />

            <div class="command-filters-toggles">
                <AppCheckbox
                    v-if="isClosedTab()"
                    :model-value="onlyPendingFiscal"
                    :disabled="disabled"
                    label="Somente pendências fiscais"
                    @update:model-value="emit('update:onlyPendingFiscal', $event)"
                />
                <AppCheckbox
                    :model-value="showUnitPrice"
                    :disabled="disabled"
                    label="Mostrar valor unitário"
                    @update:model-value="emit('update:showUnitPrice', $event)"
                />
            </div>
        </div>
    </div>
</template>

<style scoped>
.command-filters-root {
    display: grid;
    gap: 0.7rem;
}

.command-filters-tabs {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.5rem;
}

.command-filters-tab {
    border-radius: var(--radius-sm);
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 40%, transparent);
    background: color-mix(in srgb, var(--color-bg-surface) 92%, var(--color-bg-elevated));
    color: var(--color-text);
    text-align: left;
    padding: 0.58rem 0.7rem;
    display: grid;
    gap: 0.08rem;
    transition: all var(--transition-fast);
}

.command-filters-tab span {
    font-size: 0.86rem;
    font-weight: 700;
}

.command-filters-tab strong {
    color: var(--color-primary);
    font-size: 1rem;
    line-height: 1;
    font-weight: 800;
}

.command-filters-tab small {
    font-size: 0.72rem;
    color: var(--color-text-muted);
}

.command-filters-tab:hover {
    border-color: color-mix(in srgb, var(--color-primary) 52%, transparent);
    background: color-mix(in srgb, var(--color-primary) 11%, var(--color-bg-surface));
}

.command-filters-tab.is-active {
    border-color: color-mix(in srgb, var(--color-primary) 62%, transparent);
    background: color-mix(in srgb, var(--color-primary) 16%, var(--color-bg-surface));
}

.command-filters-tab:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

.command-filters-controls {
    display: grid;
    gap: 0.45rem;
}

.command-filters-toggles {
    display: flex;
    flex-wrap: wrap;
    gap: 0.85rem;
}

@media (max-width: 960px) {
    .command-filters-tabs {
        grid-template-columns: 1fr;
    }
}
</style>
