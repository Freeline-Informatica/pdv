<script setup>
import { computed, ref, watch } from 'vue';
import AppButton from '../../ui/AppButton.vue';
import AppDrawer from '../../ui/AppDrawer.vue';
import AppInput from '../../ui/AppInput.vue';
import AppModal from '../../ui/AppModal.vue';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    tables: {
        type: Array,
        default: () => [],
    },
    selectedTableId: {
        type: String,
        default: null,
    },
    selectedCommandId: {
        type: String,
        default: null,
    },
    creatingFicha: {
        type: Boolean,
        default: false,
    },
    useModal: {
        type: Boolean,
        default: false,
    },
    formatCurrency: {
        type: Function,
        required: true,
    },
});

const emit = defineEmits(['close', 'confirm', 'create-ficha']);

const searchQuery = ref('');
const localTableId = ref(props.selectedTableId || null);
const localCommandId = ref(props.selectedCommandId || null);

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) return;
        localTableId.value = props.selectedTableId || props.tables[0]?.id || null;
        localCommandId.value = props.selectedCommandId || null;
        searchQuery.value = '';
    },
);

watch(localTableId, (nextTableId) => {
    const table = props.tables.find((entry) => entry.id === nextTableId);
    const fichas = Array.isArray(table?.fichas) ? table.fichas : [];
    if (!fichas.some((entry) => entry.id === localCommandId.value)) {
        localCommandId.value = fichas[0]?.id || null;
    }
});

const filteredTables = computed(() => {
    const token = String(searchQuery.value || '').trim().toLowerCase();
    if (!token) return props.tables;

    return props.tables.filter((table) => {
        const code = String(table?.code || '').toLowerCase();
        const name = String(table?.name || '').toLowerCase();
        return code.includes(token) || name.includes(token);
    });
});

const selectedTable = computed(() => props.tables.find((entry) => entry.id === localTableId.value) || null);
const selectedTableFichas = computed(() => Array.isArray(selectedTable.value?.fichas) ? selectedTable.value.fichas : []);
const containerComponent = computed(() => (props.useModal ? AppModal : AppDrawer));
const containerProps = computed(() => ({
    open: props.open,
    title: 'Trocar mesa/ficha',
    ...(props.useModal ? { widthClass: 'table-command-selector-modal' } : {}),
}));

function selectTable(tableId) {
    localTableId.value = tableId;
}

function selectFicha(fichaId) {
    localCommandId.value = fichaId;
}

function confirmSelection() {
    emit('confirm', {
        tableId: localTableId.value || null,
        commandId: localCommandId.value || null,
    });
}
</script>

<template>
    <component :is="containerComponent" v-bind="containerProps" @close="emit('close')">
        <div class="table-command-selector-drawer">
            <AppInput
                :model-value="searchQuery"
                label="Buscar mesa"
                placeholder="Ex: 01"
                @update:model-value="searchQuery = $event"
            />

            <section class="table-command-selector-drawer__tables">
                <button
                    v-for="table in filteredTables"
                    :key="table.id"
                    type="button"
                    class="table-command-selector-drawer__table"
                    :class="{ 'is-active': table.id === localTableId }"
                    @click="selectTable(table.id)"
                >
                    <strong>Mesa {{ table.code }}</strong>
                    <small>{{ table.fichasCount || 0 }} ficha(s) aberta(s)</small>
                    <small>Total em aberto: {{ formatCurrency(table.totalOpen || 0) }}</small>
                </button>
            </section>

            <section class="table-command-selector-drawer__fichas">
                <h4>Fichas da mesa</h4>
                <button
                    v-for="ficha in selectedTableFichas"
                    :key="ficha.id"
                    type="button"
                    class="table-command-selector-drawer__ficha"
                    :class="{ 'is-active': ficha.id === localCommandId }"
                    @click="selectFicha(ficha.id)"
                >
                    <strong>Ficha {{ ficha.code }}</strong>
                    <small>{{ ficha.status }}</small>
                    <small>{{ formatCurrency(ficha.total || 0) }}</small>
                </button>

                <p v-if="selectedTableFichas.length === 0" class="table-command-selector-drawer__empty">
                    Nenhuma ficha aberta nesta mesa.
                </p>
            </section>

            <div class="table-command-selector-drawer__actions">
                <AppButton variant="secondary" :loading="creatingFicha" @click="emit('create-ficha')">
                    Nova comanda
                </AppButton>
                <AppButton @click="confirmSelection">Confirmar seleção</AppButton>
            </div>
        </div>
    </component>
</template>

<style scoped>
.table-command-selector-drawer {
    display: grid;
    gap: 0.7rem;
}

.table-command-selector-drawer__tables,
.table-command-selector-drawer__fichas {
    display: grid;
    gap: 0.42rem;
}

.table-command-selector-drawer__fichas h4 {
    margin: 0;
    font-size: 0.9rem;
    font-weight: 800;
    color: var(--color-text);
}

.table-command-selector-drawer__table,
.table-command-selector-drawer__ficha {
    border: 1px solid var(--color-border);
    border-radius: 0.72rem;
    background: color-mix(in srgb, var(--color-bg-elevated) 74%, var(--color-bg-surface));
    color: var(--color-text);
    padding: 0.58rem 0.62rem;
    text-align: left;
    display: grid;
    gap: 0.16rem;
}

.table-command-selector-drawer__table small,
.table-command-selector-drawer__ficha small {
    color: var(--color-text-muted);
}

.table-command-selector-drawer__table.is-active,
.table-command-selector-drawer__ficha.is-active {
    border-color: color-mix(in srgb, var(--color-primary) 50%, transparent);
    box-shadow: 0 0 0 1px color-mix(in srgb, var(--color-primary) 32%, transparent) inset;
}

.table-command-selector-drawer__empty {
    margin: 0;
    font-size: 0.84rem;
    color: var(--color-text-muted);
}

.table-command-selector-drawer__actions {
    display: grid;
    gap: 0.45rem;
    grid-template-columns: 1fr 1fr;
}

:global(.ui-modal-panel.table-command-selector-modal) {
    width: min(54rem, 100%);
}
</style>
