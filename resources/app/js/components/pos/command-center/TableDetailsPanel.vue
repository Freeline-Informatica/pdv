<script setup>
import { ArrowLeftRight, ClipboardCheck, FolderInput, GitMerge, PencilLine } from 'lucide-vue-next';
import { computed } from 'vue';
import AppButton from '../../ui/AppButton.vue';
import CommandHeader from './CommandHeader.vue';
import CommandItemList from './CommandItemList.vue';
import EmptyStateBlock from './EmptyStateBlock.vue';
import PrintActionsDropdown from './PrintActionsDropdown.vue';
import QuickActionButton from './QuickActionButton.vue';

const props = defineProps({
    table: {
        type: Object,
        default: null,
    },
    command: {
        type: Object,
        default: null,
    },
    showUnitPrice: {
        type: Boolean,
        default: true,
    },
    canImportToPdv: {
        type: Boolean,
        default: false,
    },
    activeTab: {
        type: String,
        default: 'closed',
    },
    loading: {
        type: Boolean,
        default: false,
    },
    contextMode: {
        type: String,
        default: 'cashier',
    },
    formatCurrency: {
        type: Function,
        required: true,
    },
});

const emit = defineEmits([
    'import-to-pdv',
    'edit-command',
    'conference-command',
    'print-action',
    'open-transfer',
    'open-merge',
]);

const hasSelection = computed(() => Boolean(props.table && props.command));
const isCashierContext = computed(() => props.contextMode === 'cashier');
const isWaiterContext = computed(() => props.contextMode === 'waiter');
const isTerminalContext = computed(() => props.contextMode === 'terminal');
</script>

<template>
    <section class="table-details-panel">
        <template v-if="hasSelection">
            <CommandHeader :table="table" :command="command" :format-currency="formatCurrency" />

            <div class="table-details-panel__summary">
                <article class="table-details-panel__summary-card">
                    <p>Itens da ficha</p>
                    <strong>{{ command.itemsCount }}</strong>
                </article>
                <article class="table-details-panel__summary-card">
                    <p>Total da ficha</p>
                    <strong>{{ formatCurrency(command.total) }}</strong>
                </article>
                <article class="table-details-panel__summary-card">
                    <p>Total da mesa</p>
                    <strong>{{ formatCurrency(table.total) }}</strong>
                </article>
            </div>

            <CommandItemList
                class="table-details-panel__items"
                :items="command.items"
                :format-currency="formatCurrency"
                :show-unit-price="showUnitPrice"
            />

            <div class="table-details-panel__actions">
                <div class="table-details-panel__actions-group">
                    <QuickActionButton
                        v-if="!isTerminalContext"
                        :disabled="isTerminalContext || loading"
                        title="Editar comanda"
                        @click="emit('edit-command', { table, command })"
                    >
                        <template #icon>
                            <PencilLine class="h-4 w-4" aria-hidden="true" />
                        </template>
                        Editar
                    </QuickActionButton>

                    <QuickActionButton
                        variant="secondary"
                        :disabled="isTerminalContext || loading"
                        title="Conferência operacional"
                        @click="emit('conference-command', { table, command })"
                    >
                        <template #icon>
                            <ClipboardCheck class="h-4 w-4" aria-hidden="true" />
                        </template>
                        Conferência
                    </QuickActionButton>

                    <PrintActionsDropdown
                        v-if="!isTerminalContext"
                        :disabled="loading"
                        @select="emit('print-action', { action: $event, table, command })"
                    />
                </div>

                <div class="table-details-panel__actions-group">
                    <QuickActionButton
                        v-if="isCashierContext || isWaiterContext"
                        variant="ghost"
                        :disabled="isTerminalContext || loading"
                        title="Transferência total ou parcial"
                        @click="emit('open-transfer', { table, command })"
                    >
                        <template #icon>
                            <ArrowLeftRight class="h-4 w-4" aria-hidden="true" />
                        </template>
                        Transferir itens
                    </QuickActionButton>

                    <QuickActionButton
                        v-if="isCashierContext"
                        variant="ghost"
                        :disabled="isTerminalContext || loading"
                        title="Preparar junção de fichas"
                        @click="emit('open-merge', { table, command })"
                    >
                        <template #icon>
                            <GitMerge class="h-4 w-4" aria-hidden="true" />
                        </template>
                        Juntar fichas
                    </QuickActionButton>

                    <AppButton
                        v-if="activeTab === 'closed'"
                        :disabled="!canImportToPdv || loading"
                        @click="emit('import-to-pdv', { table, command })"
                    >
                        <FolderInput class="h-4 w-4" aria-hidden="true" />
                        Trazer itens ao PDV
                    </AppButton>
                </div>
            </div>
        </template>

        <EmptyStateBlock
            v-else
            title="Selecione uma mesa"
            description="Escolha uma mesa e uma ficha para visualizar detalhes e executar as ações operacionais."
        />
    </section>
</template>

<style scoped>
.table-details-panel {
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    background: color-mix(in srgb, var(--color-bg-elevated) 82%, var(--color-bg-surface));
    min-height: 0;
    padding: 0.68rem;
    display: grid;
    grid-template-rows: auto auto minmax(0, 1fr) auto;
    gap: 0.52rem;
}

.table-details-panel__summary {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.4rem;
}

.table-details-panel__summary-card {
    border-radius: var(--radius-sm);
    border: 1px solid color-mix(in srgb, var(--color-border) 72%, transparent);
    background: color-mix(in srgb, var(--color-bg-surface) 90%, var(--color-bg-elevated));
    padding: 0.42rem 0.48rem;
    display: grid;
    gap: 0.14rem;
}

.table-details-panel__summary-card p {
    margin: 0;
    font-size: 0.67rem;
    color: var(--color-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.02em;
}

.table-details-panel__summary-card strong {
    font-size: 0.88rem;
    color: var(--color-text);
}

.table-details-panel__items {
    min-height: 12rem;
}

.table-details-panel__actions {
    display: grid;
    gap: 0.4rem;
}

.table-details-panel__actions-group {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}

.table-details-panel__actions-group :deep(.ui-btn) {
    min-height: 2.15rem;
}

@media (max-width: 960px) {
    .table-details-panel__summary {
        grid-template-columns: 1fr;
    }

    .table-details-panel__actions-group {
        display: grid;
        grid-template-columns: 1fr;
    }
}
</style>
