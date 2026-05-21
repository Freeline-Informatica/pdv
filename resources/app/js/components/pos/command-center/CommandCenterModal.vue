<script setup>
import { RefreshCw } from 'lucide-vue-next';
import { computed } from 'vue';
import AppModal from '../../ui/AppModal.vue';
import QuickActionButton from './QuickActionButton.vue';
import CommandFilters from './CommandFilters.vue';
import TableListPanel from './TableListPanel.vue';
import TableDetailsPanel from './TableDetailsPanel.vue';
import TransferActionSheet from './TransferActionSheet.vue';
import MergeCommandsDialog from './MergeCommandsDialog.vue';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
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
    contextMode: {
        type: String,
        default: 'cashier',
    },
    error: {
        type: String,
        default: '',
    },
    closedTables: {
        type: Array,
        default: () => [],
    },
    openedTables: {
        type: Array,
        default: () => [],
    },
    allTables: {
        type: Array,
        default: () => [],
    },
    selectedTable: {
        type: Object,
        default: null,
    },
    selectedCommand: {
        type: Object,
        default: null,
    },
    selectedTableId: {
        type: String,
        default: '',
    },
    selectedCommandId: {
        type: String,
        default: '',
    },
    canImportToPdv: {
        type: Boolean,
        default: false,
    },
    transferActionOpen: {
        type: Boolean,
        default: false,
    },
    mergeDialogOpen: {
        type: Boolean,
        default: false,
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

const emit = defineEmits([
    'close',
    'reintegrate',
    'update:activeTab',
    'update:searchQuery',
    'update:onlyPendingFiscal',
    'update:showUnitPrice',
    'select-table',
    'select-command',
    'import-to-pdv',
    'edit-command',
    'conference-command',
    'print-action',
    'open-transfer',
    'close-transfer',
    'transfer-configured',
    'open-merge',
    'close-merge',
    'merge-configured',
]);

const activeTables = computed(() => (props.activeTab === 'opened' ? props.openedTables : props.closedTables));

function handleSelectCommand(payload) {
    if (!payload || typeof payload !== 'object') return;

    if (payload.tableId) {
        emit('select-table', payload.tableId);
    }

    if (payload.commandId) {
        emit('select-command', payload.commandId);
    }
}
</script>

<template>
    <AppModal
        :open="open"
        title="Central de Comandas e Mesas"
        width-class="command-center-modal"
        @close="emit('close')"
    >
        <template #header-actions>
            <QuickActionButton variant="secondary" :disabled="loading" @click="emit('reintegrate')">
                <template #icon>
                    <RefreshCw class="h-4 w-4" aria-hidden="true" />
                </template>
                Integrar novamente
            </QuickActionButton>
        </template>

        <div class="command-center-root">
            <CommandFilters
                :active-tab="activeTab"
                :summary="summary"
                :search-query="searchQuery"
                :only-pending-fiscal="onlyPendingFiscal"
                :show-unit-price="showUnitPrice"
                :disabled="loading"
                @update:active-tab="emit('update:activeTab', $event)"
                @update:search-query="emit('update:searchQuery', $event)"
                @update:only-pending-fiscal="emit('update:onlyPendingFiscal', $event)"
                @update:show-unit-price="emit('update:showUnitPrice', $event)"
            />

            <p v-if="error" class="text-sm text-danger">{{ error }}</p>
            <p v-if="loading" class="text-sm text-muted">Sincronizando central operacional...</p>

            <div class="command-center-layout">
                <TableListPanel
                    :tables="activeTables"
                    :active-tab="activeTab"
                    :selected-table-id="selectedTableId"
                    :selected-command-id="selectedCommandId"
                    :loading="loading"
                    :format-currency="formatCurrency"
                    @select-table="emit('select-table', $event)"
                    @select-command="handleSelectCommand"
                />

                <TableDetailsPanel
                    :table="selectedTable"
                    :command="selectedCommand"
                    :show-unit-price="showUnitPrice"
                    :can-import-to-pdv="canImportToPdv"
                    :active-tab="activeTab"
                    :context-mode="contextMode"
                    :loading="loading"
                    :format-currency="formatCurrency"
                    @import-to-pdv="emit('import-to-pdv', $event)"
                    @edit-command="emit('edit-command', $event)"
                    @conference-command="emit('conference-command', $event)"
                    @print-action="emit('print-action', $event)"
                    @open-transfer="emit('open-transfer', $event)"
                    @open-merge="emit('open-merge', $event)"
                />
            </div>
        </div>

        <TransferActionSheet
            :open="transferActionOpen"
            :table="selectedTable"
            :command="selectedCommand"
            @close="emit('close-transfer')"
            @confirm="emit('transfer-configured', $event)"
        />

        <MergeCommandsDialog
            :open="mergeDialogOpen"
            :tables="allTables"
            :selected-table-id="selectedTableId"
            @close="emit('close-merge')"
            @confirm="emit('merge-configured', $event)"
        />
    </AppModal>
</template>

<style scoped>
:global(.ui-modal-panel.command-center-modal) {
    width: min(76rem, calc(100vw - 2rem));
    max-width: 76rem;
}

.command-center-root {
    min-height: min(68vh, 40rem);
    display: grid;
    grid-template-rows: auto auto minmax(0, 1fr);
    gap: 0.65rem;
}

.command-center-layout {
    min-height: 0;
    display: grid;
    gap: 0.7rem;
    grid-template-columns: minmax(0, 1fr) minmax(18rem, 23rem);
}

@media (max-width: 1140px) {
    .command-center-layout {
        grid-template-columns: 1fr;
    }
}
</style>
