<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { CalendarDays, History, Search, X } from 'lucide-vue-next';
import { useRoute, useRouter } from 'vue-router';
import api from '../../lib/api';
import SettingsPageHeader from '../../components/settings/SettingsPageHeader.vue';
import AppCard from '../../components/ui/AppCard.vue';
import SettingsEmptyState from '../../components/settings/SettingsEmptyState.vue';

const route = useRoute();
const router = useRouter();

const loading = ref(false);
const error = ref('');
const products = ref([]);
const movements = ref([]);

const search = ref('');
const selectedProduct = ref('todos');
const dateFrom = ref('');
const dateTo = ref('');
const typeFilter = ref('todos');
const activeMovementId = ref(null);

const highlightedAdjustmentId = computed(() => String(route.query.adjustment_id || '').trim());

function normalizeToken(value) {
    return String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim()
        .toLowerCase();
}

function toDateKey(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function formatMovementDateLabel(date) {
    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    }).format(date);
}

function formatMovementTimeLabel(date) {
    return new Intl.DateTimeFormat('pt-BR', {
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

function formatStock(value, unit = 'UN', options = { withUnit: true }) {
    const normalizedUnit = String(unit || 'UN').toUpperCase();
    const precision = normalizedUnit === 'KG' ? 3 : 0;
    const amount = Number(value || 0).toLocaleString('pt-BR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: precision,
    });

    if (options?.withUnit === false) return amount;
    return `${amount} ${normalizedUnit}`;
}

function formatDeltaValue(delta, unit = 'UN') {
    const prefix = Number(delta) >= 0 ? '+' : '-';
    return `${prefix}${formatStock(Math.abs(Number(delta) || 0), unit, { withUnit: false })}`;
}

function movementLabel(type) {
    const labels = {
        venda: 'Venda',
        entrada: 'Entrada',
        saida: 'Saída',
        ajuste: 'Ajuste',
        inventario: 'Inventário',
        correcao: 'Correção',
        avaria: 'Avaria',
        quebra: 'Quebra',
        outro: 'Ajuste',
    };

    return labels[String(type || '').toLowerCase()] || 'Movimentação';
}

function movementLinkLabel(item) {
    if (item.stock_adjustment_id) return 'Abrir ajuste';

    const type = String(item.tipo || '').toLowerCase();
    if (type === 'venda') return 'Abrir venda';
    if (type === 'entrada') return 'Abrir entrada';
    return 'Abrir detalhes';
}

function movementTone(delta) {
    if (Number(delta) > 0) return 'increase';
    if (Number(delta) < 0) return 'reduction';
    return 'neutral';
}

function shouldShowMovementLink(item) {
    return Boolean(item.stock_adjustment_id) || ['venda', 'entrada'].includes(String(item.tipo || '').toLowerCase());
}

function openMovementSource(item) {
    if (item.stock_adjustment_id) {
        router.push({
            path: '/configuracoes/estoque/ajustes',
            query: {
                adjustment_id: item.stock_adjustment_id,
            },
        });
        return;
    }

    const type = String(item.tipo || '').toLowerCase();

    if (type === 'venda') {
        router.push('/configuracoes/vendas');
        return;
    }

    if (type === 'entrada') {
        router.push('/configuracoes/compras');
    }
}

const productOptions = computed(() => {
    const map = new Map();

    products.value.forEach((item) => {
        map.set(String(item.id), {
            id: String(item.id),
            label: `${item.nome}${item.codigo ? ` (${item.codigo})` : ''}`,
        });
    });

    movements.value.forEach((item) => {
        if (!item?.product_id || map.has(String(item.product_id))) return;

        const fallbackName = item.product?.nome || 'Produto';
        const fallbackCode = item.product?.codigo ? ` (${item.product.codigo})` : '';

        map.set(String(item.product_id), {
            id: String(item.product_id),
            label: `${fallbackName}${fallbackCode}`,
        });
    });

    return [{ id: 'todos', label: 'Todos os produtos' }, ...Array.from(map.values())];
});

const selectedProductMeta = computed(() => {
    if (selectedProduct.value === 'todos') return null;

    const option = productOptions.value.find((item) => String(item.id) === String(selectedProduct.value));

    return option || {
        id: String(selectedProduct.value),
        label: `Produto ${selectedProduct.value}`,
    };
});

const normalizedMovements = computed(() => {
    return movements.value
        .map((item) => {
            const dateSource = item?.happened_at || item?.created_at;
            const date = dateSource ? new Date(dateSource) : new Date();
            const happenedAt = Number.isNaN(date.getTime()) ? new Date() : date;

            const unit = String(item?.product?.unidade || 'UN').toUpperCase();
            const delta = Number(item?.quantidade_movimentada || 0);
            const afterStock = Number(item?.quantidade_atual || 0);
            const beforeStock = Number(item?.quantidade_anterior ?? afterStock - delta);

            return {
                ...item,
                unit,
                delta,
                beforeStock,
                afterStock,
                happenedAt,
                dateKey: toDateKey(happenedAt),
                dateLabel: formatMovementDateLabel(happenedAt),
                timeLabel: formatMovementTimeLabel(happenedAt),
                label: movementLabel(item?.tipo),
                linkLabel: movementLinkLabel(item),
                tone: movementTone(delta),
                sourceRef: String(item?.referencia || item?.descricao || movementLabel(item?.tipo)),
                productName: String(item?.product?.nome || 'Produto removido'),
                productCode: String(item?.product?.codigo || '—'),
            };
        })
        .sort((left, right) => right.happenedAt - left.happenedAt);
});

const baseFilteredMovements = computed(() => {
    const needle = normalizeToken(search.value);

    return normalizedMovements.value.filter((item) => {
        if (selectedProduct.value !== 'todos' && String(item.product_id) !== String(selectedProduct.value)) return false;
        if (dateFrom.value && item.dateKey < dateFrom.value) return false;
        if (dateTo.value && item.dateKey > dateTo.value) return false;

        if (!needle) return true;

        const haystack = normalizeToken(
            `${item.productName} ${item.productCode} ${item.sourceRef} ${item.label} ${item.referencia || ''} ${item.descricao || ''}`,
        );

        return haystack.includes(needle);
    });
});

const totals = computed(() => {
    const source = baseFilteredMovements.value;

    return {
        all: source.length,
        increases: source.filter((item) => item.delta > 0).length,
        reductions: source.filter((item) => item.delta < 0).length,
    };
});

const filteredMovements = computed(() => {
    if (typeFilter.value === 'aumentos') {
        return baseFilteredMovements.value.filter((item) => item.delta > 0);
    }

    if (typeFilter.value === 'reducoes') {
        return baseFilteredMovements.value.filter((item) => item.delta < 0);
    }

    return baseFilteredMovements.value;
});

const groupedMovements = computed(() => {
    const map = new Map();

    filteredMovements.value.forEach((item) => {
        if (!map.has(item.dateKey)) {
            map.set(item.dateKey, {
                dateKey: item.dateKey,
                dateLabel: item.dateLabel,
                movements: [],
            });
        }

        map.get(item.dateKey).movements.push(item);
    });

    return Array.from(map.values())
        .sort((left, right) => right.dateKey.localeCompare(left.dateKey))
        .map((group) => ({
            ...group,
            movements: [...group.movements].sort((left, right) => right.happenedAt - left.happenedAt),
        }));
});

function isMovementOpen(movementId) {
    return String(activeMovementId.value) === String(movementId);
}

function openMovement(movementId) {
    activeMovementId.value = String(movementId);
}

function closeMovement(movementId) {
    if (!isMovementOpen(movementId)) return;
    activeMovementId.value = null;
}

function toggleMovement(movementId) {
    if (isMovementOpen(movementId)) {
        activeMovementId.value = null;
        return;
    }

    openMovement(movementId);
}

function handleMovementFocusOut(movementId, event) {
    const currentTarget = event?.currentTarget;
    const nextTarget = event?.relatedTarget;

    if (currentTarget instanceof HTMLElement && nextTarget instanceof HTMLElement && currentTarget.contains(nextTarget)) {
        return;
    }

    closeMovement(movementId);
}

function clearDateFilters() {
    dateFrom.value = '';
    dateTo.value = '';
}

function clearProductFilter() {
    selectedProduct.value = 'todos';
}

function syncProductQuery() {
    const nextQuery = { ...route.query };

    if (selectedProduct.value !== 'todos') {
        nextQuery.product_id = String(selectedProduct.value);
    } else {
        delete nextQuery.product_id;
        delete nextQuery.adjustment_id;
    }

    router.replace({
        path: route.path,
        query: nextQuery,
    });
}

watch(selectedProduct, syncProductQuery);

async function load() {
    loading.value = true;
    error.value = '';

    try {
        const [movementsRes, productsRes] = await Promise.all([
            api.get('/stock-movements'),
            api.get('/products'),
        ]);

        movements.value = Array.isArray(movementsRes.data) ? movementsRes.data : [];
        products.value = Array.isArray(productsRes.data) ? productsRes.data : [];

        const queryProductId = String(route.query.product_id || '').trim();
        const queryFrom = String(route.query.from || '').trim();
        const queryTo = String(route.query.to || '').trim();
        const querySearch = String(route.query.search || '').trim();

        if (queryProductId) {
            selectedProduct.value = queryProductId;
        }

        if (/^\d{4}-\d{2}-\d{2}$/.test(queryFrom)) {
            dateFrom.value = queryFrom;
        }

        if (/^\d{4}-\d{2}-\d{2}$/.test(queryTo)) {
            dateTo.value = queryTo;
        }

        if (querySearch) {
            search.value = querySearch;
        }
    } catch (requestError) {
        error.value = requestError?.response?.data?.message || 'Falha ao carregar movimentações de estoque.';
        movements.value = [];
        products.value = [];
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="kardex-page">
        <SettingsPageHeader
            title="Movimentações (Kardex)"
            subtitle="Histórico completo de movimentações de estoque"
        />

        <AppCard class="kardex-filters-card p-4">
            <div class="kardex-filters-grid">
                <label class="kardex-field-search">
                    <Search class="kardex-field-icon h-4 w-4" aria-hidden="true" />
                    <input
                        v-model="search"
                        type="search"
                        class="ui-field kardex-field-input"
                        placeholder="Buscar por produto..."
                    >
                </label>

                <select v-model="selectedProduct" class="ui-field kardex-field-input">
                    <option
                        v-for="option in productOptions"
                        :key="option.id"
                        :value="option.id"
                    >
                        {{ option.label }}
                    </option>
                </select>

                <button
                    v-if="selectedProduct !== 'todos'"
                    type="button"
                    class="kardex-remove-product"
                    title="Limpar filtro de produto"
                    @click="clearProductFilter"
                >
                    <X class="h-4 w-4" aria-hidden="true" />
                </button>

                <label class="kardex-field-date">
                    <CalendarDays class="kardex-field-icon h-4 w-4" aria-hidden="true" />
                    <input v-model="dateFrom" type="date" class="ui-field kardex-field-input" placeholder="De">
                </label>

                <label class="kardex-field-date">
                    <CalendarDays class="kardex-field-icon h-4 w-4" aria-hidden="true" />
                    <input v-model="dateTo" type="date" class="ui-field kardex-field-input" placeholder="Até">
                </label>
            </div>
        </AppCard>

        <article v-if="selectedProductMeta" class="kardex-timeline-banner">
            <p>
                <History class="h-4 w-4" aria-hidden="true" />
                Timeline de: <strong>{{ selectedProductMeta.label }}</strong>
            </p>
            <button type="button" @click="clearProductFilter">Limpar filtro</button>
        </article>

        <div class="kardex-filters-actions">
            <button
                type="button"
                class="kardex-chip"
                :class="{ 'is-active': typeFilter === 'todos' }"
                @click="typeFilter = 'todos'"
            >
                Todos <span>{{ totals.all }}</span>
            </button>
            <button
                type="button"
                class="kardex-chip"
                :class="{ 'is-active': typeFilter === 'aumentos' }"
                @click="typeFilter = 'aumentos'"
            >
                Aumentos <span>{{ totals.increases }}</span>
            </button>
            <button
                type="button"
                class="kardex-chip"
                :class="{ 'is-active': typeFilter === 'reducoes' }"
                @click="typeFilter = 'reducoes'"
            >
                Reduções <span>{{ totals.reductions }}</span>
            </button>

            <button
                v-if="dateFrom || dateTo"
                type="button"
                class="kardex-clear-dates"
                @click="clearDateFilters"
            >
                Limpar datas
            </button>
        </div>

        <p v-if="error" class="text-sm text-danger">{{ error }}</p>

        <AppCard v-if="loading" class="p-5 text-muted">
            Carregando movimentações...
        </AppCard>

        <section v-else-if="groupedMovements.length" class="kardex-groups">
            <article v-for="group in groupedMovements" :key="group.dateKey" class="kardex-group">
                <header class="kardex-group-header">
                    <h3>{{ group.dateLabel }}</h3>
                    <span>{{ group.movements.length }} movimentações</span>
                </header>

                <div class="kardex-list">
                    <article
                        v-for="movement in group.movements"
                        :key="movement.id"
                        class="kardex-row"
                        :class="[
                            movement.tone,
                            {
                                'is-open': isMovementOpen(movement.id),
                                'is-highlight': highlightedAdjustmentId && String(movement.stock_adjustment_id || '') === highlightedAdjustmentId,
                            },
                        ]"
                        tabindex="0"
                        @mouseenter="openMovement(movement.id)"
                        @mouseleave="closeMovement(movement.id)"
                        @focusin="openMovement(movement.id)"
                        @focusout="handleMovementFocusOut(movement.id, $event)"
                        @click="toggleMovement(movement.id)"
                    >
                        <div class="kardex-row-qty">
                            <strong>{{ formatDeltaValue(movement.delta, movement.unit) }}</strong>
                            <span>{{ movement.unit }}</span>
                        </div>

                        <div class="kardex-row-main">
                            <p class="kardex-row-product">
                                {{ movement.productName }}
                                <small>#{{ movement.productCode }}</small>
                            </p>
                            <p class="kardex-row-meta">
                                <span class="kardex-row-kind">{{ movement.label }}</span>
                                <span>·</span>
                                <span>
                                    {{ formatStock(movement.beforeStock, movement.unit, { withUnit: false }) }}
                                    →
                                    {{ formatStock(movement.afterStock, movement.unit, { withUnit: false }) }}
                                </span>
                            </p>
                            <p class="kardex-row-time">
                                {{ movement.timeLabel }}
                                <button
                                    v-if="shouldShowMovementLink(movement)"
                                    type="button"
                                    class="kardex-row-link"
                                    @click.stop="openMovementSource(movement)"
                                >
                                    {{ movement.linkLabel }}
                                </button>
                            </p>

                            <div class="kardex-row-hover">
                                {{ movement.sourceRef }}
                            </div>
                        </div>

                        <div class="kardex-row-balance">
                            <span>SALDO</span>
                            <strong>{{ formatStock(movement.afterStock, movement.unit, { withUnit: false }) }}</strong>
                        </div>
                    </article>
                </div>
            </article>
        </section>

        <SettingsEmptyState
            v-else
            title="Nenhuma movimentação encontrada"
            description="Ajuste os filtros para visualizar o histórico do Kardex."
        />
    </div>
</template>

<style scoped>
.kardex-page {
    display: grid;
    gap: 0.75rem;
}

.kardex-filters-card {
    display: grid;
    gap: 0.75rem;
}

.kardex-filters-grid {
    display: grid;
    grid-template-columns: minmax(16rem, 1fr) minmax(11rem, 16rem) auto minmax(9rem, 11rem) minmax(9rem, 11rem);
    gap: 0.75rem;
    align-items: center;
}

.kardex-field-search,
.kardex-field-date {
    position: relative;
}

.kardex-field-icon {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--color-text-muted);
    pointer-events: none;
    z-index: 1;
}

.kardex-field-input {
    min-height: 2.45rem;
    padding-left: 2.2rem;
    font-size: 0.9rem;
}

.kardex-remove-product {
    width: 2.45rem;
    height: 2.45rem;
    border-radius: 0.7rem;
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 58%, transparent);
    background: var(--color-bg-surface);
    color: var(--color-text-muted);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all var(--transition-fast);
}

.kardex-remove-product:hover {
    color: var(--color-text);
    border-color: color-mix(in srgb, var(--color-primary) 50%, transparent);
}

.kardex-timeline-banner {
    border-radius: var(--radius-lg);
    border: 1px solid color-mix(in srgb, var(--color-success) 42%, transparent);
    background: color-mix(in srgb, var(--color-success) 10%, var(--color-bg-surface));
    padding: 0.7rem 0.95rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}

.kardex-timeline-banner p {
    margin: 0;
    color: var(--color-success);
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
}

.kardex-timeline-banner p strong {
    color: color-mix(in srgb, var(--color-success) 90%, var(--color-text));
}

.kardex-timeline-banner button {
    border: 0;
    background: transparent;
    color: var(--color-success);
    font-weight: 700;
}

.kardex-filters-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
}

.kardex-chip {
    border-radius: 999px;
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 72%, transparent);
    background: color-mix(in srgb, var(--color-bg-surface) 78%, var(--color-bg-elevated));
    color: var(--color-text);
    font-size: 0.9rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.38rem 0.74rem;
    transition: all var(--transition-fast);
}

.kardex-chip span {
    color: var(--color-text-muted);
    font-size: 0.84rem;
    font-weight: 600;
}

.kardex-chip.is-active {
    border-color: color-mix(in srgb, var(--color-primary) 64%, transparent);
    background: var(--color-primary);
    color: var(--color-text-inverse);
}

.kardex-chip.is-active span {
    color: color-mix(in srgb, var(--color-text-inverse) 82%, transparent);
}

.kardex-chip:hover {
    border-color: color-mix(in srgb, var(--color-primary) 50%, transparent);
}

.kardex-clear-dates {
    margin-left: auto;
    border: 0;
    background: transparent;
    color: var(--color-text-muted);
    font-weight: 700;
    font-size: 0.75rem;
    text-decoration: underline;
}

.kardex-groups {
    display: grid;
    gap: 1rem;
}

.kardex-group {
    display: grid;
    gap: 0.5rem;
}

.kardex-group-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.kardex-group-header::after {
    content: '';
    flex: 1;
    height: 1px;
    background: color-mix(in srgb, var(--color-border) 84%, transparent);
}

.kardex-group-header h3 {
    margin: 0;
    font-size: 1.45rem;
    line-height: 1.1;
    font-weight: 800;
    color: var(--color-text);
}

.kardex-group-header span {
    color: var(--color-text-muted);
    font-size: 0.75rem;
    font-weight: 600;
}

.kardex-list {
    display: grid;
    gap: 0.5rem;
}

.kardex-row {
    border-radius: var(--radius-lg);
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 55%, transparent);
    background: color-mix(in srgb, var(--color-bg-surface) 90%, var(--color-bg-elevated));
    padding: 0.64rem 0.72rem;
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 0.62rem;
    transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
    cursor: pointer;
}

.kardex-row:hover,
.kardex-row:focus-within,
.kardex-row.is-open {
    border-color: color-mix(in srgb, var(--color-primary) 48%, var(--color-border));
    box-shadow: 0 0 0 1px color-mix(in srgb, var(--color-primary) 18%, transparent);
}

.kardex-row.is-highlight {
    box-shadow: 0 0 0 2px color-mix(in srgb, var(--color-primary) 26%, transparent);
    border-color: color-mix(in srgb, var(--color-primary) 60%, var(--color-border));
}

.kardex-row-qty {
    min-width: 5.2rem;
    border-radius: 0.75rem;
    border: 1px solid transparent;
    padding: 0.4rem 0.5rem;
    display: grid;
    justify-items: center;
    gap: 0.12rem;
}

.kardex-row-qty strong {
    font-size: 1.55rem;
    line-height: 1;
    font-weight: 900;
}

.kardex-row-qty span {
    font-size: 0.64rem;
    color: var(--color-text-muted);
}

.kardex-row.reduction .kardex-row-qty {
    background: color-mix(in srgb, var(--color-danger) 10%, var(--color-bg-surface));
}

.kardex-row.reduction .kardex-row-qty strong {
    color: var(--color-danger);
}

.kardex-row.increase .kardex-row-qty {
    background: color-mix(in srgb, var(--color-success) 11%, var(--color-bg-surface));
}

.kardex-row.increase .kardex-row-qty strong {
    color: var(--color-success);
}

.kardex-row.neutral .kardex-row-qty {
    background: color-mix(in srgb, var(--color-warning) 12%, var(--color-bg-surface));
}

.kardex-row.neutral .kardex-row-qty strong {
    color: var(--color-warning);
}

.kardex-row-main {
    min-width: 0;
    display: grid;
    gap: 0.12rem;
}

.kardex-row-product {
    margin: 0;
    font-size: 1rem;
    line-height: 1.2;
    font-weight: 800;
    color: var(--color-text);
    display: inline-flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.45rem;
}

.kardex-row-product small {
    color: var(--color-text-muted);
    font-size: 0.78rem;
    font-weight: 600;
}

.kardex-row-meta {
    margin: 0;
    display: inline-flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.35rem;
    color: var(--color-text-muted);
    font-size: 0.82rem;
}

.kardex-row-kind {
    color: var(--color-text);
    font-weight: 700;
}

.kardex-row-time {
    margin: 0;
    display: inline-flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.45rem;
    color: var(--color-text-muted);
    font-size: 0.76rem;
}

.kardex-row-link {
    border: 0;
    background: transparent;
    color: var(--color-primary);
    font-size: 0.8rem;
    font-weight: 600;
    text-decoration: underline;
    cursor: pointer;
}

.kardex-row-hover {
    margin-top: 0.15rem;
    border-radius: var(--radius-sm);
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 35%, transparent);
    background: color-mix(in srgb, var(--color-bg-elevated) 74%, var(--color-bg-surface));
    color: var(--color-text-muted);
    font-size: 0.82rem;
    font-weight: 600;
    padding: 0.34rem 0.55rem;
    max-height: 0;
    opacity: 0;
    overflow: hidden;
    pointer-events: none;
    transform: translateY(-2px);
    transition: max-height var(--transition-base), opacity var(--transition-base), transform var(--transition-base);
}

.kardex-row:hover .kardex-row-hover,
.kardex-row:focus-within .kardex-row-hover,
.kardex-row.is-open .kardex-row-hover {
    max-height: 2rem;
    opacity: 1;
    pointer-events: auto;
    transform: translateY(0);
}

.kardex-row-balance {
    min-width: 4.4rem;
    text-align: right;
    display: grid;
    gap: 0.08rem;
}

.kardex-row-balance span {
    color: color-mix(in srgb, var(--color-text-muted) 92%, transparent);
    font-size: 0.78rem;
    letter-spacing: 0.04em;
}

.kardex-row-balance strong {
    color: var(--color-text);
    font-size: 1.6rem;
    line-height: 1;
    font-weight: 900;
}

@media (max-width: 1200px) {
    .kardex-filters-grid {
        grid-template-columns: minmax(14rem, 1fr) minmax(10rem, 1fr) auto repeat(2, minmax(8rem, 9rem));
    }
}

@media (max-width: 920px) {
    .kardex-filters-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .kardex-remove-product {
        display: none;
    }

    .kardex-clear-dates {
        margin-left: 0;
    }

    .kardex-group-header h3 {
        font-size: 1.24rem;
    }

    .kardex-row {
        grid-template-columns: 1fr;
    }

    .kardex-row-qty,
    .kardex-row-balance {
        min-width: 0;
        justify-self: start;
        text-align: left;
    }

    .kardex-row-balance strong {
        font-size: 1.2rem;
    }
}

@media (max-width: 640px) {
    .kardex-filters-grid {
        grid-template-columns: 1fr;
    }

    .kardex-chip {
        font-size: 0.82rem;
    }

    .kardex-row-product {
        font-size: 0.9rem;
    }

    .kardex-row-meta {
        font-size: 0.78rem;
    }

    .kardex-timeline-banner {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
