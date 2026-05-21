<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { ArrowLeft, PackageCheck, Plus, Send } from 'lucide-vue-next';
import { useRoute, useRouter } from 'vue-router';
import api from '../../lib/api';
import SettingsPageHeader from '../../components/settings/SettingsPageHeader.vue';
import SettingsTableCard from '../../components/settings/SettingsTableCard.vue';
import SettingsEmptyState from '../../components/settings/SettingsEmptyState.vue';
import AppTable from '../../components/ui/AppTable.vue';
import AppBadge from '../../components/ui/AppBadge.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppModal from '../../components/ui/AppModal.vue';
import AppTextarea from '../../components/ui/AppTextarea.vue';

const route = useRoute();
const router = useRouter();

const loadingSessions = ref(false);
const loadingInventory = ref(false);
const creatingInventory = ref(false);
const sendingAdjustments = ref(false);
const saveLoadingItemId = ref(null);

const sessions = ref([]);
const selectedInventory = ref(null);

const pageError = ref('');
const actionFeedback = ref('');

const createModal = reactive({
    open: false,
    observacoes: '',
    error: '',
});

const rowDrafts = reactive({});
const rowErrors = reactive({});

const currentInventoryId = computed(() => String(route.query.inventory_id || '').trim());
const inventoryItems = computed(() => (Array.isArray(selectedInventory.value?.items) ? selectedInventory.value.items : []));
const orderedInventoryItems = computed(() => [...inventoryItems.value].sort((a, b) => {
    const aCounted = a.quantidade_contada != null;
    const bCounted = b.quantidade_contada != null;

    if (aCounted !== bCounted) {
        return aCounted ? 1 : -1;
    }

    if (aCounted && bCounted) {
        const aSavedAt = a.saved_at ? new Date(a.saved_at).getTime() : 0;
        const bSavedAt = b.saved_at ? new Date(b.saved_at).getTime() : 0;
        if (aSavedAt !== bSavedAt) return aSavedAt - bSavedAt;
    }

    const aName = String(a.product?.nome || '').toLowerCase();
    const bName = String(b.product?.nome || '').toLowerCase();
    return aName.localeCompare(bName, 'pt-BR');
}));
const inventorySummary = computed(() => selectedInventory.value?.summary || {
    total_items: 0,
    counted_items: 0,
    divergent_items: 0,
});
const inventoryFinalized = computed(() => selectedInventory.value?.status === 'finalizado');

function normalizeDecimalInput(value) {
    return String(value ?? '')
        .trim()
        .replace(',', '.');
}

function parseDraftValue(rawValue) {
    const normalized = normalizeDecimalInput(rawValue);
    if (!normalized) return null;

    const numeric = Number(normalized);
    if (!Number.isFinite(numeric) || numeric < 0) return null;
    return round3(numeric);
}

function round3(value) {
    return Math.round(Number(value || 0) * 1000) / 1000;
}

function formatNumber(value) {
    const numeric = Number(value);
    if (!Number.isFinite(numeric)) return '0';

    return numeric.toLocaleString('pt-BR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 3,
    });
}

function formatQuantity(value, unit = 'UN') {
    return `${formatNumber(value)} ${String(unit || 'UN').toUpperCase()}`;
}

function formatDiff(value) {
    const numeric = Number(value || 0);
    if (Math.abs(numeric) < 0.000001) return '0';
    if (numeric > 0) return `+${formatNumber(numeric)}`;
    return `-${formatNumber(Math.abs(numeric))}`;
}

function formatDateTime(value) {
    if (!value) return '—';

    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

function statusVariant(status) {
    if (status === 'finalizado') return 'success';
    if (status === 'em_andamento') return 'warning';
    return 'default';
}

function statusLabel(status) {
    if (status === 'finalizado') return 'Finalizado';
    if (status === 'em_andamento') return 'Em andamento';
    return 'Aberto';
}

function clearRowLocalState() {
    Object.keys(rowDrafts).forEach((key) => {
        delete rowDrafts[key];
    });

    Object.keys(rowErrors).forEach((key) => {
        delete rowErrors[key];
    });
}

function toInputValue(value) {
    const numeric = Number(value);
    if (!Number.isFinite(numeric)) return '';
    return String(round3(numeric));
}

function hydrateRowDrafts(items, { preserveUnsaved = false } = {}) {
    const nextIds = new Set(items.map((item) => item.id));

    Object.keys(rowDrafts).forEach((key) => {
        if (!nextIds.has(key)) delete rowDrafts[key];
    });

    Object.keys(rowErrors).forEach((key) => {
        if (!nextIds.has(key)) delete rowErrors[key];
    });

    items.forEach((item) => {
        const currentDraft = String(rowDrafts[item.id] ?? '');
        const hasDraft = currentDraft.trim() !== '';
        const shouldKeepDraft = preserveUnsaved && item.quantidade_contada == null && hasDraft;

        rowDrafts[item.id] = shouldKeepDraft
            ? currentDraft
            : (item.quantidade_contada == null ? '' : toInputValue(item.quantidade_contada));

        rowErrors[item.id] = '';
    });
}

function hasDifference(item) {
    if (item.quantidade_contada == null) return false;
    return Math.abs(Number(item.diferenca || 0)) > 0.000001;
}

function rowToneClass(item) {
    if (item.quantidade_contada == null) return '';
    if (Math.abs(Number(item.diferenca || 0)) < 0.000001) return 'is-balanced';
    return hasDifference(item) ? 'is-divergent' : '';
}

function canSendAdjustments() {
    if (!selectedInventory.value || inventoryFinalized.value) return false;
    return Number(inventorySummary.value.divergent_items || 0) > 0;
}

function canSaveRow(item) {
    if (inventoryFinalized.value) return false;

    const nextValue = parseDraftValue(rowDrafts[item.id]);
    if (nextValue == null) return false;

    const currentValue = item.quantidade_contada == null ? null : Number(item.quantidade_contada);
    if (currentValue == null) return true;

    return Math.abs(nextValue - currentValue) > 0.000001;
}

function openCreateModal() {
    createModal.error = '';
    createModal.observacoes = '';
    createModal.open = true;
}

function closeCreateModal() {
    createModal.open = false;
    createModal.error = '';
}

async function loadSessions() {
    loadingSessions.value = true;
    pageError.value = '';

    try {
        const { data } = await api.get('/stock-inventories');
        sessions.value = Array.isArray(data) ? data : [];
    } catch (requestError) {
        sessions.value = [];
        pageError.value = requestError?.response?.data?.message ?? 'Falha ao carregar as sessões de inventário.';
    } finally {
        loadingSessions.value = false;
    }
}

async function loadInventory(inventoryId) {
    if (!inventoryId) {
        selectedInventory.value = null;
        clearRowLocalState();
        return;
    }

    loadingInventory.value = true;
    pageError.value = '';

    try {
        const { data } = await api.get(`/stock-inventories/${inventoryId}`);
        selectedInventory.value = data;
        hydrateRowDrafts(inventoryItems.value);
    } catch (requestError) {
        selectedInventory.value = null;
        clearRowLocalState();
        pageError.value = requestError?.response?.data?.message ?? 'Falha ao carregar o inventário selecionado.';
    } finally {
        loadingInventory.value = false;
    }
}

async function createInventory() {
    createModal.error = '';
    creatingInventory.value = true;

    try {
        const payload = {
            observacoes: createModal.observacoes.trim() || null,
        };

        const { data } = await api.post('/stock-inventories', payload);
        closeCreateModal();

        await loadSessions();
        await router.replace({
            path: '/configuracoes/estoque/inventario',
            query: {
                inventory_id: data.id,
            },
        });
    } catch (requestError) {
        createModal.error = requestError?.response?.data?.message ?? 'Não foi possível criar o inventário.';
    } finally {
        creatingInventory.value = false;
    }
}

async function openInventory(inventoryId) {
    await router.replace({
        path: '/configuracoes/estoque/inventario',
        query: {
            inventory_id: inventoryId,
        },
    });
}

async function backToSessions() {
    actionFeedback.value = '';
    await router.replace({
        path: '/configuracoes/estoque/inventario',
        query: {},
    });
}

async function saveCount(item) {
    if (!selectedInventory.value) return;

    rowErrors[item.id] = '';
    const nextValue = parseDraftValue(rowDrafts[item.id]);

    if (nextValue == null) {
        rowErrors[item.id] = 'Informe uma quantidade válida maior ou igual a zero.';
        return;
    }

    saveLoadingItemId.value = item.id;
    pageError.value = '';

    try {
        const { data } = await api.put(
            `/stock-inventories/${selectedInventory.value.id}/items/${item.id}`,
            { quantidade_contada: nextValue },
        );

        selectedInventory.value = data;
        hydrateRowDrafts(inventoryItems.value, { preserveUnsaved: true });
        await loadSessions();
    } catch (requestError) {
        rowErrors[item.id] = requestError?.response?.data?.message ?? 'Não foi possível salvar a contagem deste item.';
    } finally {
        saveLoadingItemId.value = null;
    }
}

async function sendToAdjustments() {
    if (!selectedInventory.value || !canSendAdjustments()) return;

    const confirmed = window.confirm('Enviar as divergências deste inventário para ajustes de estoque?');
    if (!confirmed) return;

    sendingAdjustments.value = true;
    pageError.value = '';
    actionFeedback.value = '';

    try {
        const { data } = await api.post(`/stock-inventories/${selectedInventory.value.id}/send-to-adjustments`);
        actionFeedback.value = data?.message || 'Inventário enviado para ajustes.';
        await loadSessions();

        await router.push({
            path: '/configuracoes/estoque/ajustes',
            query: data?.first_adjustment_id ? { created: data.first_adjustment_id } : {},
        });
    } catch (requestError) {
        pageError.value = requestError?.response?.data?.message ?? 'Não foi possível enviar para ajustes.';
    } finally {
        sendingAdjustments.value = false;
    }
}

watch(
    () => currentInventoryId.value,
    async (nextId) => {
        if (!nextId) {
            selectedInventory.value = null;
            clearRowLocalState();
            return;
        }

        if (selectedInventory.value?.id === nextId) return;
        await loadInventory(nextId);
    },
);

onMounted(async () => {
    await loadSessions();

    if (currentInventoryId.value) {
        await loadInventory(currentInventoryId.value);
    }
});
</script>

<template>
    <div class="space-y-4">
        <SettingsPageHeader title="Inventário" subtitle="Contagem física e comparação com o sistema">
            <template #actions>
                <AppButton @click="openCreateModal">
                    <Plus class="h-4 w-4" aria-hidden="true" />
                    Novo Inventário
                </AppButton>
            </template>
        </SettingsPageHeader>

        <p v-if="pageError" class="text-sm text-danger">{{ pageError }}</p>
        <p v-else-if="actionFeedback" class="text-sm text-success">{{ actionFeedback }}</p>

        <template v-if="selectedInventory">
            <div class="inventory-toolbar">
                <AppButton variant="secondary" @click="backToSessions">
                    <ArrowLeft class="h-4 w-4" aria-hidden="true" />
                    Voltar
                </AppButton>

                <div class="inventory-toolbar-right">
                    <p class="inventory-counter">
                        Contados:
                        <strong>{{ inventorySummary.counted_items }}/{{ inventorySummary.total_items }}</strong>
                        <span class="inventory-counter-sep">·</span>
                        Divergências:
                        <strong class="text-danger">{{ inventorySummary.divergent_items }}</strong>
                    </p>

                    <AppButton
                        :disabled="!canSendAdjustments()"
                        :loading="sendingAdjustments"
                        @click="sendToAdjustments"
                    >
                        <Send class="h-4 w-4" aria-hidden="true" />
                        Enviar para Ajustes
                    </AppButton>
                </div>
            </div>

            <SettingsTableCard>
                <AppTable>
                    <thead>
                        <tr>
                            <th class="text-left">Produto</th>
                            <th class="text-left">Código</th>
                            <th class="text-right">Qtd. Sistema</th>
                            <th class="text-center">Qtd. Contada</th>
                            <th class="text-center">Diferença</th>
                            <th class="text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loadingInventory">
                            <td colspan="6" class="text-center text-muted py-6">Carregando itens do inventário...</td>
                        </tr>

                        <tr v-else-if="inventoryItems.length === 0">
                            <td colspan="6" class="p-0">
                                <SettingsEmptyState
                                    title="Sem itens para contagem"
                                    description="Não há produtos ativos para iniciar este inventário."
                                />
                            </td>
                        </tr>

                        <tr
                            v-for="item in orderedInventoryItems"
                            :key="item.id"
                            :class="rowToneClass(item)"
                        >
                            <td class="font-semibold text-main">{{ item.product?.nome || 'Produto removido' }}</td>
                            <td class="text-muted">{{ item.product?.codigo || '—' }}</td>
                            <td class="text-right font-semibold">
                                {{ formatQuantity(item.quantidade_sistema, item.product?.unidade) }}
                            </td>
                            <td class="text-center">
                                <div class="inventory-input-wrap">
                                    <input
                                        v-model="rowDrafts[item.id]"
                                        type="number"
                                        min="0"
                                        step="0.001"
                                        class="inventory-count-input"
                                        :disabled="inventoryFinalized"
                                        placeholder="—"
                                    >
                                    <span v-if="rowErrors[item.id]" class="inventory-row-error">{{ rowErrors[item.id] }}</span>
                                </div>
                            </td>
                            <td class="text-center">
                                <span
                                    class="inventory-diff"
                                    :class="{
                                        'is-muted': item.quantidade_contada == null,
                                        'is-negative': Number(item.diferenca) < 0,
                                        'is-positive': Number(item.diferenca) > 0,
                                    }"
                                >
                                    {{ item.quantidade_contada == null ? '—' : formatDiff(item.diferenca) }}
                                </span>
                            </td>
                            <td class="text-right">
                                <button
                                    type="button"
                                    class="inventory-save-btn"
                                    :disabled="saveLoadingItemId === item.id || !canSaveRow(item)"
                                    @click="saveCount(item)"
                                >
                                    {{ saveLoadingItemId === item.id ? 'Salvando...' : 'Salvar' }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </AppTable>
            </SettingsTableCard>
        </template>

        <template v-else>
            <SettingsTableCard>
                <div class="inventory-sessions-title-wrap">
                    <h3 class="inventory-sessions-title">Sessões de Inventário</h3>
                </div>

                <AppTable>
                    <thead>
                        <tr>
                            <th class="text-left">Data</th>
                            <th class="text-left">Responsável</th>
                            <th class="text-center">Status</th>
                            <th class="text-left">Observações</th>
                            <th class="text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loadingSessions">
                            <td colspan="5" class="text-center text-muted py-6">Carregando inventários...</td>
                        </tr>

                        <tr v-else-if="sessions.length === 0">
                            <td colspan="5" class="p-0">
                                <SettingsEmptyState
                                    title="Nenhum inventário criado"
                                    description="Clique em Novo Inventário para abrir uma sessão de contagem."
                                >
                                    <template #actions>
                                        <AppButton @click="openCreateModal">
                                            <Plus class="h-4 w-4" aria-hidden="true" />
                                            Novo Inventário
                                        </AppButton>
                                    </template>
                                </SettingsEmptyState>
                            </td>
                        </tr>

                        <tr v-for="session in sessions" :key="session.id">
                            <td class="font-semibold text-main">{{ formatDateTime(session.created_at) }}</td>
                            <td class="text-muted">{{ session.creator?.name || '—' }}</td>
                            <td class="text-center">
                                <AppBadge :variant="statusVariant(session.status)">
                                    {{ statusLabel(session.status) }}
                                </AppBadge>
                            </td>
                            <td class="text-muted">
                                {{ session.observacoes || '—' }}
                            </td>
                            <td class="text-right">
                                <AppButton variant="secondary" @click="openInventory(session.id)">
                                    <PackageCheck class="h-4 w-4" aria-hidden="true" />
                                    Abrir
                                </AppButton>
                            </td>
                        </tr>
                    </tbody>
                </AppTable>
            </SettingsTableCard>
        </template>

        <AppModal
            :open="createModal.open"
            title="Novo Inventário"
            width-class="max-w-2xl"
            @close="closeCreateModal"
        >
            <div class="space-y-4">
                <AppTextarea
                    v-model="createModal.observacoes"
                    label="Observações (opcional)"
                    rows="4"
                    placeholder="Ex: Contagem mensal de janeiro..."
                />

                <p v-if="createModal.error" class="text-sm text-danger">{{ createModal.error }}</p>

                <div class="inventory-modal-actions">
                    <AppButton variant="secondary" @click="closeCreateModal">Cancelar</AppButton>
                    <AppButton :loading="creatingInventory" @click="createInventory">Criar Inventário</AppButton>
                </div>
            </div>
        </AppModal>
    </div>
</template>

<style scoped>
.inventory-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.8rem;
    flex-wrap: wrap;
}

.inventory-toolbar-right {
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.inventory-counter {
    margin: 0;
    color: var(--color-text-muted);
    font-size: 1.02rem;
}

.inventory-counter strong {
    color: var(--color-text);
}

.inventory-counter-sep {
    margin: 0 0.2rem;
}

.inventory-input-wrap {
    display: inline-flex;
    flex-direction: column;
    gap: 0.25rem;
    align-items: center;
}

.inventory-count-input {
    width: 6.2rem;
    border-radius: 0.72rem;
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 62%, transparent);
    background: var(--color-bg-surface);
    color: var(--color-text);
    text-align: center;
    padding: 0.42rem 0.5rem;
    font-size: 1.08rem;
    font-weight: 650;
    transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
}

.inventory-count-input:focus {
    outline: none;
    border-color: color-mix(in srgb, var(--color-primary) 74%, transparent);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary) 20%, transparent);
}

.inventory-count-input:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.inventory-row-error {
    color: var(--color-danger);
    font-size: 0.76rem;
    font-weight: 600;
}

.inventory-diff {
    font-size: 1.48rem;
    line-height: 1;
    font-weight: 800;
}

.inventory-diff.is-muted {
    color: var(--color-text-muted);
}

.inventory-diff.is-negative {
    color: var(--color-danger);
}

.inventory-diff.is-positive {
    color: var(--color-success);
}

.inventory-save-btn {
    border: 1px solid transparent;
    background: transparent;
    color: var(--color-text);
    border-radius: 0.65rem;
    font-size: 0.97rem;
    font-weight: 700;
    padding: 0.3rem 0.56rem;
    transition: all var(--transition-fast);
}

.inventory-save-btn:hover:enabled {
    color: var(--color-primary);
}

.inventory-save-btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

.inventory-sessions-title-wrap {
    padding: 0.2rem 0 0.7rem;
}

.inventory-sessions-title {
    color: var(--color-text);
    font-size: 1.2rem;
    margin-left: 0.6rem;
    font-weight: 900;
}

.inventory-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
}

:deep(.ui-table tbody tr.is-divergent) {
    background: color-mix(in srgb, var(--color-danger) 7%, var(--color-bg-surface));
}

:deep(.ui-table tbody tr.is-balanced) {
    background: color-mix(in srgb, var(--color-success) 6%, var(--color-bg-surface));
}

@media (max-width: 960px) {
    .inventory-toolbar-right {
        width: 100%;
        justify-content: flex-start;
    }

    .inventory-counter {
        width: 100%;
    }
}
</style>
