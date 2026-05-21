<script setup>
import { AlertTriangle, BadgePercent, Search, Undo2, XCircle } from 'lucide-vue-next';
import { computed, nextTick, reactive, ref, watch } from 'vue';
import AppModal from '../ui/AppModal.vue';
import AppButton from '../ui/AppButton.vue';
import AppInput from '../ui/AppInput.vue';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    rows: {
        type: Array,
        default: () => [],
    },
    hasActiveAdjustments: {
        type: Boolean,
        default: false,
    },
    hasPendingSaleChanges: {
        type: Boolean,
        default: false,
    },
    formatCurrency: {
        type: Function,
        default: (value) => String(value ?? 0),
    },
});

const emit = defineEmits(['close', 'confirm-last-item', 'confirm-cancel-sale', 'confirm-cancel-adjustments']);

const productCodeFieldRef = ref(null);
const activeMode = ref('last-item');
const filterDraft = reactive({
    productCode: '',
    seq: '',
});
const appliedFilters = reactive({
    productCode: '',
    seq: '',
});
const selectedIndexes = ref([]);

const modeOptions = [
    {
        id: 'last-item',
        title: 'Cancelar último item',
        description: 'Remove itens selecionados seguindo a regra atual do carrinho.',
        icon: Undo2,
        tone: 'default',
    },
    {
        id: 'sale',
        title: 'Cancelar venda',
        description: 'Limpa toda a venda e reinicia a operação atual.',
        icon: XCircle,
        tone: 'danger',
    },
    {
        id: 'adjustments',
        title: 'Cancelar desconto/acréscimos',
        description: 'Remove descontos e acréscimos dos itens marcados e ajustes ativos.',
        icon: BadgePercent,
        tone: 'default',
    },
];

function roundMoney(value) {
    return Math.round((Number(value) || 0) * 100) / 100;
}

function normalizeToken(value) {
    return String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim()
        .toLowerCase();
}

function isEditableTarget(target) {
    if (!(target instanceof HTMLElement)) return false;
    if (target.isContentEditable) return true;

    const field = target.closest('input, textarea, select');
    return field instanceof HTMLElement;
}

const activeModeMeta = computed(
    () => modeOptions.find((mode) => mode.id === activeMode.value) || modeOptions[0],
);

const filteredRows = computed(() => {
    const codeNeedle = normalizeToken(appliedFilters.productCode);
    const seqNeedle = String(appliedFilters.seq || '').trim();

    return props.rows.filter((row) => {
        if (codeNeedle) {
            const matchesCode = normalizeToken(row.productCode).includes(codeNeedle);
            const matchesDescription = normalizeToken(row.description).includes(codeNeedle);
            if (!matchesCode && !matchesDescription) return false;
        }

        if (seqNeedle) {
            const expectedSeq = Number(seqNeedle);
            if (!Number.isInteger(expectedSeq) || expectedSeq <= 0) return false;
            return Number(row.seq) === expectedSeq;
        }

        return true;
    });
});

const selectedRows = computed(() => {
    const selectedSet = new Set(selectedIndexes.value);
    return props.rows.filter((row) => selectedSet.has(row.sourceIndex));
});

const selectedCount = computed(() => selectedRows.value.length);
const selectedAdjustableCount = computed(() => selectedRows.value.filter((row) => row.hasAdjustments).length);
const totalOriginal = computed(() => roundMoney(props.rows.reduce((total, row) => total + Number(row.subtotal || 0), 0)));

const projectedTotal = computed(() => {
    if (activeMode.value === 'sale') return 0;

    if (activeMode.value === 'last-item') {
        const selectedSubtotal = selectedRows.value.reduce((total, row) => total + Number(row.subtotal || 0), 0);
        return roundMoney(Math.max(0, totalOriginal.value - selectedSubtotal));
    }

    const adjustmentImpact = selectedRows.value.reduce((total, row) => total + Number(row.adjustmentNet || 0), 0);
    return roundMoney(totalOriginal.value - adjustmentImpact);
});

const totalCanceled = computed(() => roundMoney(Math.abs(totalOriginal.value - projectedTotal.value)));

const estimatedImpactLabel = computed(() => {
    if (activeMode.value === 'adjustments') {
        const net = roundMoney(selectedRows.value.reduce((total, row) => total + Number(row.adjustmentNet || 0), 0));
        if (net === 0) return 'Sem impacto financeiro detectado';
        if (net > 0) return 'Cancelamento tende a reduzir o valor final da venda';
        return 'Cancelamento tende a aumentar o valor final da venda';
    }

    if (activeMode.value === 'sale') return 'Toda a venda atual será cancelada';
    return 'Somente itens selecionados serão processados';
});

const canConfirm = computed(() => {
    if (activeMode.value === 'sale') {
        return props.hasPendingSaleChanges || totalOriginal.value > 0;
    }

    if (activeMode.value === 'last-item') {
        return selectedCount.value > 0;
    }

    return selectedAdjustableCount.value > 0 || props.hasActiveAdjustments;
});

const confirmLabel = computed(() => {
    if (activeMode.value === 'sale') return 'Cancelar venda';
    if (activeMode.value === 'adjustments') return 'Cancelar desconto/acréscimo';
    if (selectedCount.value > 1) return 'Cancelar itens selecionados';
    return 'Cancelar item selecionado';
});

const allVisibleSelected = computed(() => {
    if (!filteredRows.value.length) return false;
    const selectedSet = new Set(selectedIndexes.value);
    return filteredRows.value.every((row) => selectedSet.has(row.sourceIndex));
});

const primarySelectedRow = computed(() => selectedRows.value[0] || null);

function focusProductCodeField() {
    const inputElement = productCodeFieldRef.value?.$el?.querySelector('input');
    if (inputElement instanceof HTMLInputElement) {
        inputElement.focus();
        inputElement.select();
    }
}

function selectLastVisibleRow() {
    if (!filteredRows.value.length) {
        selectedIndexes.value = [];
        return;
    }

    const lastVisible = filteredRows.value[filteredRows.value.length - 1];
    selectedIndexes.value = [lastVisible.sourceIndex];
}

function resetDialogState() {
    activeMode.value = 'last-item';
    filterDraft.productCode = '';
    filterDraft.seq = '';
    appliedFilters.productCode = '';
    appliedFilters.seq = '';
    selectedIndexes.value = [];
    selectLastVisibleRow();
}

function pruneSelectionToVisibleRows() {
    const visibleSet = new Set(filteredRows.value.map((row) => row.sourceIndex));
    selectedIndexes.value = selectedIndexes.value.filter((index) => visibleSet.has(index));
}

function applyFilters() {
    appliedFilters.productCode = filterDraft.productCode;
    appliedFilters.seq = filterDraft.seq;
    pruneSelectionToVisibleRows();

    if (activeMode.value === 'last-item' && selectedIndexes.value.length === 0) {
        selectLastVisibleRow();
    }
}

function selectMode(modeId) {
    activeMode.value = modeId;

    if (modeId === 'last-item' && selectedIndexes.value.length === 0) {
        selectLastVisibleRow();
    }
}

function isRowSelected(sourceIndex) {
    return selectedIndexes.value.includes(sourceIndex);
}

function toggleRowSelection(sourceIndex) {
    const index = selectedIndexes.value.indexOf(sourceIndex);
    if (index >= 0) {
        selectedIndexes.value.splice(index, 1);
        return;
    }

    selectedIndexes.value.push(sourceIndex);
}

function toggleAllVisibleRows() {
    if (allVisibleSelected.value) {
        selectedIndexes.value = [];
        return;
    }

    selectedIndexes.value = filteredRows.value.map((row) => row.sourceIndex);
}

function clearSelection() {
    selectedIndexes.value = [];
}

function closeDialog() {
    emit('close');
}

function confirmCurrentMode() {
    if (!canConfirm.value) return;

    if (activeMode.value === 'sale') {
        emit('confirm-cancel-sale');
        return;
    }

    if (activeMode.value === 'adjustments') {
        emit('confirm-cancel-adjustments', {
            selectedIndexes: [...selectedIndexes.value],
        });
        return;
    }

    emit('confirm-last-item', {
        selectedIndexes: [...selectedIndexes.value],
    });
}

function handleDialogKeydown(event) {
    if (event.key === 'Escape') {
        event.preventDefault();
        closeDialog();
        return;
    }

    if (event.key !== 'Enter' && event.code !== 'NumpadEnter') return;
    if (event.altKey || event.ctrlKey || event.metaKey) return;

    const target = event.target;
    if (!(target instanceof HTMLElement)) return;

    if (target.closest('.cancel-dialog__filters')) {
        event.preventDefault();
        applyFilters();
        return;
    }

    if (isEditableTarget(target)) return;
    if (target.closest('button, a[href], [role="button"], input, select, textarea')) return;
    if (!canConfirm.value) return;

    event.preventDefault();
    confirmCurrentMode();
}

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) return;

        resetDialogState();
        nextTick(() => {
            focusProductCodeField();
        });
    },
);

watch(
    () => props.rows.length,
    () => {
        const validIndexes = new Set(props.rows.map((row) => row.sourceIndex));
        selectedIndexes.value = selectedIndexes.value.filter((index) => validIndexes.has(index));

        if (props.open && activeMode.value === 'last-item' && selectedIndexes.value.length === 0) {
            selectLastVisibleRow();
        }
    },
);
</script>

<template>
    <AppModal
        :open="open"
        title="Cancelamento"
        width-class="pos-cancel-dialog-modal"
        @close="closeDialog"
    >
        <div class="cancel-dialog" @keydown="handleDialogKeydown">
            <header class="cancel-dialog__header-copy">
                <p class="cancel-dialog__subtitle">
                    Revise o tipo de cancelamento, selecione itens quando necessário e confirme com segurança.
                </p>
                <span class="cancel-dialog__esc-hint">Esc fecha o dialog</span>
            </header>

            <section class="cancel-dialog__modes" aria-label="Modos de cancelamento">
                <button
                    v-for="mode in modeOptions"
                    :key="mode.id"
                    type="button"
                    class="cancel-mode-card"
                    :class="{
                        'is-active': activeMode === mode.id,
                        'is-danger': mode.tone === 'danger',
                    }"
                    @click="selectMode(mode.id)"
                >
                    <span class="cancel-mode-card__icon">
                        <component :is="mode.icon" class="h-4 w-4" aria-hidden="true" />
                    </span>
                    <span class="cancel-mode-card__copy">
                        <span class="cancel-mode-card__title">{{ mode.title }}</span>
                        <span class="cancel-mode-card__description">{{ mode.description }}</span>
                    </span>
                </button>
            </section>

            <section class="cancel-dialog__topline">
                <div class="cancel-dialog__filters">
                    <AppInput
                        ref="productCodeFieldRef"
                        v-model="filterDraft.productCode"
                        label="Código do produto"
                        placeholder="Informe o código ou descrição"
                        @keydown.enter.prevent="applyFilters"
                    />
                    <AppInput
                        v-model="filterDraft.seq"
                        label="Seq."
                        type="number"
                        min="1"
                        placeholder="Seq"
                        @keydown.enter.prevent="applyFilters"
                    />
                    <AppButton
                        variant="secondary"
                        class="cancel-dialog__search-btn"
                        @click="applyFilters"
                    >
                        <Search class="h-4 w-4" aria-hidden="true" />
                        Buscar
                    </AppButton>
                </div>

                <div class="cancel-dialog__summary-metrics">
                    <article class="cancel-metric-card">
                        <p class="cancel-metric-card__label">Total original</p>
                        <p class="cancel-metric-card__value">{{ formatCurrency(totalOriginal) }}</p>
                    </article>
                    <article class="cancel-metric-card">
                        <p class="cancel-metric-card__label">Total cancelado</p>
                        <p class="cancel-metric-card__value text-danger">{{ formatCurrency(totalCanceled) }}</p>
                    </article>
                    <article class="cancel-metric-card">
                        <p class="cancel-metric-card__label">Total alterado</p>
                        <p class="cancel-metric-card__value text-main">{{ formatCurrency(projectedTotal) }}</p>
                    </article>
                </div>
            </section>

            <section class="cancel-dialog__body">
                <div class="cancel-dialog__table-wrapper">
                    <div class="cancel-dialog__table-scroll">
                        <table class="ui-table cancel-dialog__table">
                            <thead>
                                <tr>
                                    <th class="w-[4.25rem]">
                                        <label class="cancel-dialog__table-check-all">
                                            <input
                                                type="checkbox"
                                                class="ui-checkbox"
                                                :checked="allVisibleSelected"
                                                :disabled="!filteredRows.length"
                                                @change="toggleAllVisibleRows"
                                            >
                                        </label>
                                    </th>
                                    <th class="w-[4.25rem]">Seq.</th>
                                    <th class="w-[8rem]">Produto</th>
                                    <th>Descrição</th>
                                    <th class="w-[7rem] text-right">Quantidade</th>
                                    <th class="w-[8.25rem] text-right">Sub-total</th>
                                    <th class="w-[8.25rem] text-right">Desconto</th>
                                    <th class="w-[8.25rem] text-right">Acréscimo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!filteredRows.length">
                                    <td colspan="8">
                                        <div class="cancel-dialog__empty-state">
                                            <p class="ui-section-title">Nenhum item localizado</p>
                                            <p class="ui-page-subtitle">
                                                Ajuste os filtros de código e sequência para localizar os itens.
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                                <tr
                                    v-for="row in filteredRows"
                                    :key="row.sourceIndex"
                                    class="cancel-dialog__row"
                                    :class="{ 'is-selected': isRowSelected(row.sourceIndex) }"
                                    @click="toggleRowSelection(row.sourceIndex)"
                                >
                                    <td @click.stop>
                                        <label class="cancel-dialog__table-check-all">
                                            <input
                                                type="checkbox"
                                                class="ui-checkbox"
                                                :checked="isRowSelected(row.sourceIndex)"
                                                @change="toggleRowSelection(row.sourceIndex)"
                                            >
                                        </label>
                                    </td>
                                    <td>{{ row.seq }}</td>
                                    <td class="font-mono">{{ row.productCode || '—' }}</td>
                                    <td class="cancel-dialog__description-cell">{{ row.description }}</td>
                                    <td class="text-right">{{ row.quantityLabel }}</td>
                                    <td class="text-right">{{ formatCurrency(row.subtotal) }}</td>
                                    <td class="text-right text-danger">{{ formatCurrency(row.discountTotal) }}</td>
                                    <td class="text-right text-success">{{ formatCurrency(row.surchargeTotal) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <aside
                    class="cancel-dialog__selection-panel"
                    :class="{ 'is-danger': activeMode === 'sale' }"
                >
                    <div class="cancel-dialog__panel-block">
                        <p class="cancel-dialog__panel-label">Tipo de cancelamento ativo</p>
                        <p class="cancel-dialog__panel-value">{{ activeModeMeta.title }}</p>
                    </div>

                    <div class="cancel-dialog__panel-block">
                        <p class="cancel-dialog__panel-label">Itens selecionados</p>
                        <p class="cancel-dialog__panel-value">{{ selectedCount }}</p>
                    </div>

                    <div class="cancel-dialog__panel-block">
                        <p class="cancel-dialog__panel-label">Valor estimado do cancelamento</p>
                        <p class="cancel-dialog__panel-value text-danger">{{ formatCurrency(totalCanceled) }}</p>
                        <p class="cancel-dialog__panel-hint">{{ estimatedImpactLabel }}</p>
                    </div>

                    <div v-if="primarySelectedRow" class="cancel-dialog__panel-block">
                        <p class="cancel-dialog__panel-label">Item selecionado</p>
                        <p class="cancel-dialog__panel-value">{{ primarySelectedRow.description }}</p>
                        <p class="cancel-dialog__panel-hint">Seq. {{ primarySelectedRow.seq }} • Cód. {{ primarySelectedRow.productCode || '—' }}</p>
                    </div>

                    <div v-if="activeMode === 'sale'" class="cancel-dialog__danger-alert">
                        <AlertTriangle class="h-4 w-4" aria-hidden="true" />
                        <p>
                            Esta ação remove toda a venda atual. Revise os valores antes de confirmar.
                        </p>
                    </div>

                    <div v-if="activeMode === 'adjustments' && selectedAdjustableCount === 0 && !hasActiveAdjustments" class="cancel-dialog__panel-empty">
                        Selecione itens com desconto/acréscimo para habilitar o cancelamento.
                    </div>

                    <div v-if="activeMode !== 'sale' && selectedCount === 0" class="cancel-dialog__panel-empty">
                        Nenhum item selecionado. Marque pelo menos um registro na tabela.
                    </div>
                </aside>
            </section>

            <footer class="cancel-dialog__footer">
                <p class="cancel-dialog__records">
                    {{ filteredRows.length }} registro(s) listado(s)
                </p>

                <div class="cancel-dialog__footer-actions">
                    <AppButton variant="ghost" @click="closeDialog">Fechar</AppButton>
                    <AppButton
                        variant="secondary"
                        :disabled="selectedCount === 0"
                        @click="clearSelection"
                    >
                        Limpar seleção
                    </AppButton>
                    <AppButton
                        variant="danger"
                        :disabled="!canConfirm"
                        @click="confirmCurrentMode"
                    >
                        {{ confirmLabel }}
                    </AppButton>
                </div>
            </footer>
        </div>
    </AppModal>
</template>

<style scoped>
:global(.ui-modal-panel.pos-cancel-dialog-modal) {
    width: min(86rem, 100%);
    max-height: calc(100vh - 1rem);
    padding: 0.8rem;
}

.cancel-dialog {
    display: grid;
    gap: 0.62rem;
}

.cancel-dialog__header-copy {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.55rem;
    margin-top: 0;
}

.cancel-dialog__subtitle {
    margin: 0;
    font-size: 0.82rem;
    color: var(--color-text-muted);
}

.cancel-dialog__esc-hint {
    flex: 0 0 auto;
    border-radius: 999px;
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 50%, transparent);
    background: color-mix(in srgb, var(--color-bg-elevated) 80%, var(--color-bg-surface));
    padding: 0.2rem 0.5rem;
    font-size: 0.66rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    color: var(--color-text-muted);
}

.cancel-dialog__modes {
    display: grid;
    gap: 0.5rem;
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.cancel-mode-card {
    border-radius: var(--radius-md);
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 55%, transparent);
    background: linear-gradient(
        160deg,
        color-mix(in srgb, var(--color-bg-elevated) 88%, var(--color-bg-surface)),
        color-mix(in srgb, var(--color-bg-surface) 86%, var(--color-bg-elevated))
    );
    color: var(--color-text);
    padding: 0.56rem;
    text-align: left;
    display: flex;
    align-items: flex-start;
    gap: 0.45rem;
    transition: all var(--transition-fast);
}

.cancel-mode-card:hover {
    border-color: color-mix(in srgb, var(--color-primary) 58%, transparent);
    box-shadow: 0 0 0 1px color-mix(in srgb, var(--color-primary) 18%, transparent);
}

.cancel-mode-card.is-active {
    border-color: color-mix(in srgb, var(--color-primary) 70%, transparent);
    background: linear-gradient(
        160deg,
        color-mix(in srgb, var(--color-primary) 16%, var(--color-bg-elevated)),
        color-mix(in srgb, var(--color-bg-surface) 78%, var(--color-bg-elevated))
    );
    box-shadow: 0 0 0 1px color-mix(in srgb, var(--color-primary) 35%, transparent), var(--shadow-sm);
}

.cancel-mode-card.is-danger:hover,
.cancel-mode-card.is-danger.is-active {
    border-color: color-mix(in srgb, var(--color-danger) 68%, transparent);
    box-shadow: 0 0 0 1px color-mix(in srgb, var(--color-danger) 28%, transparent);
}

.cancel-mode-card__icon {
    width: 1.65rem;
    height: 1.65rem;
    border-radius: 999px;
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 52%, transparent);
    background: color-mix(in srgb, var(--color-bg-surface) 85%, var(--color-bg-elevated));
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--color-text-muted);
}

.cancel-mode-card.is-active .cancel-mode-card__icon {
    border-color: color-mix(in srgb, var(--color-primary) 52%, transparent);
    color: var(--color-primary);
}

.cancel-mode-card.is-danger.is-active .cancel-mode-card__icon {
    border-color: color-mix(in srgb, var(--color-danger) 56%, transparent);
    color: var(--color-danger);
}

.cancel-mode-card__copy {
    min-width: 0;
    display: grid;
    gap: 0.15rem;
}

.cancel-mode-card__title {
    font-size: 0.79rem;
    font-weight: 800;
    line-height: 1.2;
}

.cancel-mode-card__description {
    font-size: 0.69rem;
    color: var(--color-text-muted);
    line-height: 1.2;
}

.cancel-dialog__topline {
    display: grid;
    gap: 0.55rem;
    grid-template-columns: 1.25fr 1fr;
    align-items: end;
}

.cancel-dialog__filters {
    display: grid;
    gap: 0.5rem;
    grid-template-columns: minmax(0, 1fr) 6.25rem auto;
}

.cancel-dialog__search-btn {
    align-self: end;
    min-height: 2.35rem;
}

.cancel-dialog__summary-metrics {
    display: grid;
    gap: 0.45rem;
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.cancel-metric-card {
    border-radius: var(--radius-md);
    border: 1px solid color-mix(in srgb, var(--color-border) 76%, transparent);
    background: color-mix(in srgb, var(--color-bg-elevated) 83%, var(--color-bg-surface));
    padding: 0.5rem 0.56rem;
}

.cancel-metric-card__label {
    margin: 0;
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--color-text-muted);
}

.cancel-metric-card__value {
    margin: 0.12rem 0 0;
    font-size: 0.94rem;
    line-height: 1.15;
    font-weight: 800;
    color: var(--color-text);
}

.cancel-dialog__body {
    min-height: min(44vh, 26rem);
    display: grid;
    gap: 0.6rem;
    grid-template-columns: minmax(0, 1fr) 16.5rem;
}

.cancel-dialog__table-wrapper {
    border-radius: var(--radius-lg);
    border: 1px solid color-mix(in srgb, var(--color-border) 72%, transparent);
    background: color-mix(in srgb, var(--color-bg-surface) 90%, var(--color-bg-elevated));
    overflow: hidden;
}

.cancel-dialog__table-scroll {
    max-height: min(40vh, 23rem);
    overflow: auto;
}

.cancel-dialog__table th,
.cancel-dialog__table td {
    padding: 0.62rem 0.68rem;
}

.cancel-dialog__table th {
    font-size: 0.68rem;
}

.cancel-dialog :deep(.ui-label) {
    font-size: 0.76rem;
}

.cancel-dialog :deep(.ui-field) {
    min-height: 2.35rem;
    padding: 0.48rem 0.68rem;
    font-size: 0.86rem;
}

.cancel-dialog__table thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: linear-gradient(
        180deg,
        color-mix(in srgb, var(--color-bg-elevated) 94%, var(--color-bg-surface)),
        color-mix(in srgb, var(--color-bg-surface) 94%, var(--color-bg-elevated))
    );
}

.cancel-dialog__table-check-all {
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.cancel-dialog__row {
    cursor: pointer;
}

.cancel-dialog__row.is-selected {
    background: color-mix(in srgb, var(--color-primary) 18%, var(--color-bg-surface));
}

.cancel-dialog__description-cell {
    min-width: 12rem;
    max-width: 32rem;
}

.cancel-dialog__empty-state {
    padding: 1.3rem 0.8rem;
    text-align: center;
}

.cancel-dialog__selection-panel {
    border-radius: var(--radius-lg);
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 60%, transparent);
    background: linear-gradient(
        180deg,
        color-mix(in srgb, var(--color-bg-elevated) 92%, var(--color-bg-surface)),
        color-mix(in srgb, var(--color-bg-surface) 85%, var(--color-bg-elevated))
    );
    padding: 0.62rem;
    display: grid;
    align-content: start;
    gap: 0.5rem;
}

.cancel-dialog__selection-panel.is-danger {
    border-color: color-mix(in srgb, var(--color-danger) 55%, var(--color-border-strong));
    box-shadow: 0 0 0 1px color-mix(in srgb, var(--color-danger) 24%, transparent);
}

.cancel-dialog__panel-block {
    border-radius: var(--radius-sm);
    border: 1px solid color-mix(in srgb, var(--color-border) 74%, transparent);
    background: color-mix(in srgb, var(--color-bg-surface) 83%, var(--color-bg-elevated));
    padding: 0.5rem;
}

.cancel-dialog__panel-label {
    margin: 0;
    font-size: 0.69rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--color-text-muted);
    font-weight: 700;
}

.cancel-dialog__panel-value {
    margin: 0.14rem 0 0;
    font-size: 0.88rem;
    color: var(--color-text);
    font-weight: 700;
    line-height: 1.3;
}

.cancel-dialog__panel-hint {
    margin: 0.12rem 0 0;
    font-size: 0.73rem;
    color: var(--color-text-muted);
}

.cancel-dialog__danger-alert {
    border-radius: var(--radius-sm);
    border: 1px solid color-mix(in srgb, var(--color-danger) 55%, transparent);
    background: color-mix(in srgb, var(--color-danger) 18%, var(--color-bg-surface));
    color: var(--color-text);
    padding: 0.5rem;
    display: flex;
    align-items: flex-start;
    gap: 0.45rem;
    font-size: 0.75rem;
    line-height: 1.35;
}

.cancel-dialog__danger-alert p {
    margin: 0;
}

.cancel-dialog__panel-empty {
    border-radius: var(--radius-sm);
    border: 1px dashed color-mix(in srgb, var(--color-border-strong) 80%, transparent);
    background: color-mix(in srgb, var(--color-bg-surface) 86%, var(--color-bg-elevated));
    color: var(--color-text-muted);
    padding: 0.5rem;
    font-size: 0.75rem;
    line-height: 1.35;
}

.cancel-dialog__footer {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.45rem;
    border-radius: var(--radius-md);
    border: 1px solid color-mix(in srgb, var(--color-border) 74%, transparent);
    background: color-mix(in srgb, var(--color-bg-surface) 88%, var(--color-bg-elevated));
    padding: 0.46rem 0.6rem;
}

.cancel-dialog__records {
    margin: 0;
    font-size: 0.74rem;
    color: var(--color-text-muted);
    font-weight: 600;
}

.cancel-dialog__footer-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 0.4rem;
}

@media (max-width: 1200px) {
    .cancel-dialog__modes {
        grid-template-columns: 1fr;
    }

    .cancel-dialog__topline {
        grid-template-columns: 1fr;
    }

    .cancel-dialog__summary-metrics {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .cancel-dialog__body {
        grid-template-columns: 1fr;
    }

    .cancel-dialog__selection-panel {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .cancel-dialog__danger-alert,
    .cancel-dialog__panel-empty {
        grid-column: 1 / -1;
    }
}

@media (max-width: 768px) {
    :global(.ui-modal-panel.pos-cancel-dialog-modal) {
        width: 100%;
        padding: 0.8rem;
    }

    .cancel-dialog__filters {
        grid-template-columns: 1fr;
    }

    .cancel-dialog__search-btn {
        width: 100%;
    }

    .cancel-dialog__summary-metrics {
        grid-template-columns: 1fr;
    }

    .cancel-dialog__selection-panel {
        grid-template-columns: 1fr;
    }

    .cancel-dialog__footer-actions {
        width: 100%;
    }

    .cancel-dialog__footer-actions :deep(.ui-btn) {
        flex: 1 1 auto;
        min-width: 0;
    }
}
</style>
