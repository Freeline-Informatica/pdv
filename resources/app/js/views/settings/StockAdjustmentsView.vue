<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { Check, Clock3, History, PencilLine, Plus, Trash2, XCircle } from 'lucide-vue-next';
import { useRoute, useRouter } from 'vue-router';
import api from '../../lib/api';
import { getUser } from '../../lib/auth';
import SettingsPageHeader from '../../components/settings/SettingsPageHeader.vue';
import SettingsFilterBar from '../../components/settings/SettingsFilterBar.vue';
import SettingsEmptyState from '../../components/settings/SettingsEmptyState.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppSearchField from '../../components/ui/AppSearchField.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppModal from '../../components/ui/AppModal.vue';
import AppInput from '../../components/ui/AppInput.vue';
import AppTextarea from '../../components/ui/AppTextarea.vue';
import AppBadge from '../../components/ui/AppBadge.vue';

const route = useRoute();
const router = useRouter();

const loading = ref(false);
const error = ref('');
const adjustments = ref([]);
const products = ref([]);

const search = ref('');
const statusFilter = ref('todos');

const createEditModal = reactive({
    open: false,
    mode: 'create',
    loading: false,
    error: '',
    fieldErrors: {
        productId: '',
        newQuantity: '',
    },
    recordId: null,
    form: {
        productId: '',
        tipo: 'correcao',
        newQuantity: '',
        complemento: '',
    },
});

const decisionModal = reactive({
    open: false,
    mode: 'approve',
    loading: false,
    error: '',
    record: null,
    note: '',
});

const actionLoadingId = ref(null);
const currentUser = getUser() || {};

const isSuperAdmin = computed(() => {
    const role = String(currentUser.role || currentUser.perfil || '').toLowerCase();
    return currentUser.is_super_admin === true || role === 'super_admin' || role === 'superadmin';
});

const typeOptions = [
    { value: 'correcao', label: 'Correção' },
    { value: 'entrada', label: 'Entrada' },
    { value: 'saida', label: 'Saída' },
    { value: 'inventario', label: 'Inventário' },
    { value: 'avaria', label: 'Avaria' },
    { value: 'quebra', label: 'Quebra' },
    { value: 'outro', label: 'Outro' },
];

const statusChips = computed(() => {
    const source = adjustments.value;

    return [
        { id: 'todos', label: 'Todos', count: source.length, icon: 'box' },
        { id: 'pendente', label: 'Pendentes', count: source.filter((item) => item.status === 'pendente').length, icon: 'clock' },
        { id: 'aprovado', label: 'Aprovados', count: source.filter((item) => item.status === 'aprovado').length, icon: 'check' },
        { id: 'rejeitado', label: 'Rejeitados', count: source.filter((item) => item.status === 'rejeitado').length, icon: 'x' },
    ];
});

const highlightedId = computed(() => String(route.query.created || route.query.adjustment_id || ''));

const productOptions = computed(() =>
    products.value.map((item) => ({
        id: String(item.id),
        label: `${item.nome}${item.codigo ? ` (${item.codigo})` : ''}`,
    })),
);

const selectedCreateEditProduct = computed(() => {
    if (!createEditModal.form.productId) return null;
    return products.value.find((item) => String(item.id) === String(createEditModal.form.productId)) || null;
});

const editingRecord = computed(() => {
    if (!createEditModal.recordId) return null;
    return adjustments.value.find((item) => String(item.id) === String(createEditModal.recordId)) || null;
});

const currentQuantityForForm = computed(() => {
    if (editingRecord.value) return Number(editingRecord.value.quantidade_atual || 0);
    return Number(selectedCreateEditProduct.value?.estoque_atual || 0);
});

const filteredAdjustments = computed(() => {
    const needle = String(search.value || '').trim().toLowerCase();

    return adjustments.value.filter((item) => {
        if (statusFilter.value !== 'todos' && item.status !== statusFilter.value) return false;

        if (!needle) return true;

        const productName = String(item.product?.nome || '').toLowerCase();
        const productCode = String(item.product?.codigo || '').toLowerCase();
        const note = String(item.complemento || '').toLowerCase();

        return productName.includes(needle) || productCode.includes(needle) || note.includes(needle);
    });
});

function formatQuantity(value, unit = 'UN') {
    const normalizedUnit = String(unit || 'UN').toUpperCase();
    const precision = normalizedUnit === 'KG' ? 3 : 0;

    return Number(value || 0).toLocaleString('pt-BR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: precision,
    });
}

function formatDelta(value, unit = 'UN') {
    const numeric = Number(value || 0);
    const sign = numeric >= 0 ? '+' : '-';
    return `${sign}${formatQuantity(Math.abs(numeric), unit)}`;
}

function typeLabel(type) {
    const option = typeOptions.find((item) => item.value === type);
    return option?.label || 'Ajuste';
}

function statusMeta(status) {
    if (status === 'aprovado') return { label: 'Aprovado', variant: 'success' };
    if (status === 'rejeitado') return { label: 'Rejeitado', variant: 'danger' };
    if (status === 'cancelado') return { label: 'Cancelado', variant: 'default' };
    return { label: 'Pendente', variant: 'warning' };
}

function rowTone(status) {
    if (status === 'aprovado') return 'is-approved';
    if (status === 'rejeitado') return 'is-rejected';
    if (status === 'cancelado') return 'is-canceled';
    return 'is-pending';
}

function formatDateLine(value) {
    if (!value) return '—';

    const date = new Date(value);
    const today = new Date();
    const isToday =
        date.getDate() === today.getDate()
        && date.getMonth() === today.getMonth()
        && date.getFullYear() === today.getFullYear();

    const timeLabel = new Intl.DateTimeFormat('pt-BR', {
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);

    if (isToday) return `Hoje, ${timeLabel}`;

    const dateLabel = new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(date);

    return `${dateLabel} ${timeLabel}`;
}

function normalizeCreateEditForm() {
    createEditModal.form.productId = productOptions.value[0]?.id || '';
    createEditModal.form.tipo = 'correcao';
    createEditModal.form.newQuantity = '';
    createEditModal.form.complemento = '';
    createEditModal.error = '';
    createEditModal.fieldErrors.productId = '';
    createEditModal.fieldErrors.newQuantity = '';
}

function openCreateModal() {
    createEditModal.mode = 'create';
    createEditModal.recordId = null;
    normalizeCreateEditForm();

    if (selectedCreateEditProduct.value) {
        createEditModal.form.newQuantity = String(selectedCreateEditProduct.value.estoque_atual || 0);
    }

    createEditModal.open = true;
}

function openEditModal(item) {
    createEditModal.mode = 'edit';
    createEditModal.recordId = item.id;
    createEditModal.form.productId = String(item.product_id);
    createEditModal.form.tipo = item.tipo;
    createEditModal.form.newQuantity = String(item.nova_quantidade);
    createEditModal.form.complemento = item.complemento || '';
    createEditModal.error = '';
    createEditModal.fieldErrors.productId = '';
    createEditModal.fieldErrors.newQuantity = '';
    createEditModal.open = true;
}

function closeCreateEditModal() {
    createEditModal.open = false;
    createEditModal.recordId = null;
    createEditModal.error = '';
    createEditModal.fieldErrors.productId = '';
    createEditModal.fieldErrors.newQuantity = '';
}

function openDecisionModal(mode, record) {
    decisionModal.mode = mode;
    decisionModal.record = record;
    decisionModal.note = '';
    decisionModal.error = '';
    decisionModal.open = true;
}

function closeDecisionModal() {
    decisionModal.open = false;
    decisionModal.record = null;
    decisionModal.note = '';
    decisionModal.error = '';
}

function validateCreateEditForm() {
    createEditModal.fieldErrors.productId = '';
    createEditModal.fieldErrors.newQuantity = '';

    if (!createEditModal.form.productId) {
        createEditModal.fieldErrors.productId = 'Selecione um produto.';
        return false;
    }

    const nextQuantity = Number(createEditModal.form.newQuantity);
    if (!Number.isFinite(nextQuantity) || nextQuantity < 0) {
        createEditModal.fieldErrors.newQuantity = 'Informe uma quantidade válida maior ou igual a zero.';
        return false;
    }

    if (Math.abs(nextQuantity - Number(currentQuantityForForm.value || 0)) < 0.000001) {
        createEditModal.fieldErrors.newQuantity = 'A nova quantidade deve ser diferente da atual.';
        return false;
    }

    return true;
}

async function saveCreateEdit() {
    createEditModal.error = '';

    if (!validateCreateEditForm()) return;

    createEditModal.loading = true;
    try {
        const payload = {
            product_id: createEditModal.form.productId,
            tipo: createEditModal.form.tipo,
            nova_quantidade: Number(createEditModal.form.newQuantity),
            complemento: createEditModal.form.complemento.trim() || null,
        };

        if (createEditModal.mode === 'edit' && createEditModal.recordId) {
            await api.put(`/stock-adjustments/${createEditModal.recordId}`, payload);
        } else {
            await api.post('/stock-adjustments', payload);
        }

        closeCreateEditModal();
        await load();
    } catch (requestError) {
        const validationErrors = requestError?.response?.data?.errors || {};

        if (Array.isArray(validationErrors.product_id) && validationErrors.product_id.length) {
            createEditModal.fieldErrors.productId = validationErrors.product_id[0];
        }

        if (Array.isArray(validationErrors.nova_quantidade) && validationErrors.nova_quantidade.length) {
            createEditModal.fieldErrors.newQuantity = validationErrors.nova_quantidade[0];
        }

        createEditModal.error = requestError?.response?.data?.message ?? 'Não foi possível salvar o ajuste.';
    } finally {
        createEditModal.loading = false;
    }
}

async function submitDecision() {
    if (!decisionModal.record) return;

    const nextStatus = decisionModal.mode === 'approve' ? 'aprovado' : 'rejeitado';
    decisionModal.error = '';
    decisionModal.loading = true;

    try {
        const note = decisionModal.note.trim();
        const payload = {
            status: nextStatus,
            ...(note ? { complemento: note } : {}),
        };

        await api.put(`/stock-adjustments/${decisionModal.record.id}`, payload);
        closeDecisionModal();
        await load();
    } catch (requestError) {
        decisionModal.error = requestError?.response?.data?.message ?? 'Não foi possível atualizar a solicitação.';
    } finally {
        decisionModal.loading = false;
    }
}

async function removePending(record) {
    if (!window.confirm(`Excluir solicitação do produto "${record.product?.nome || 'produto'}"?`)) return;

    actionLoadingId.value = record.id;
    try {
        await api.delete(`/stock-adjustments/${record.id}`);
        await load();
    } catch (requestError) {
        error.value = requestError?.response?.data?.message ?? 'Não foi possível excluir a solicitação.';
    } finally {
        actionLoadingId.value = null;
    }
}

function goToHistory(record) {
    router.push({
        path: '/configuracoes/estoque/movimentacoes',
        query: {
            product_id: record.product_id,
            adjustment_id: record.id,
        },
    });
}

async function load() {
    loading.value = true;
    error.value = '';

    try {
        const [adjustmentsRes, productsRes] = await Promise.all([
            api.get('/stock-adjustments'),
            api.get('/products'),
        ]);

        adjustments.value = Array.isArray(adjustmentsRes.data) ? adjustmentsRes.data : [];
        products.value = Array.isArray(productsRes.data) ? productsRes.data : [];
    } catch (requestError) {
        error.value = requestError?.response?.data?.message ?? 'Falha ao carregar ajustes de estoque.';
        adjustments.value = [];
        products.value = [];
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="space-y-4">
        <SettingsPageHeader
            title="Ajustes de Estoque"
            subtitle="Solicitações de ajuste com fluxo de aprovação"
        />

        <SettingsFilterBar>
            <div class="adjustments-filter-search">
                <AppSearchField v-model="search" placeholder="Buscar por produto, código ou observação..." />
            </div>
            <div class="adjustments-filter-select">
                <AppSelect v-model="statusFilter">
                    <option value="todos">Todos os status</option>
                    <option value="pendente">Pendentes</option>
                    <option value="aprovado">Aprovados</option>
                    <option value="rejeitado">Rejeitados</option>
                    <option value="cancelado">Cancelados</option>
                </AppSelect>
            </div>
            <AppButton @click="openCreateModal">
                <Plus class="h-4 w-4" aria-hidden="true" />
                Novo ajuste
            </AppButton>
        </SettingsFilterBar>

        <div class="adjustments-chips">
            <button
                v-for="chip in statusChips"
                :key="chip.id"
                type="button"
                class="adjustments-chip"
                :class="{ 'is-active': statusFilter === chip.id }"
                @click="statusFilter = chip.id"
            >
                <span class="adjustments-chip-icon" aria-hidden="true">
                    <Clock3 v-if="chip.icon === 'clock'" class="h-3.5 w-3.5" />
                    <Check v-else-if="chip.icon === 'check'" class="h-3.5 w-3.5" />
                    <XCircle v-else-if="chip.icon === 'x'" class="h-3.5 w-3.5" />
                    <Plus v-else class="h-3.5 w-3.5" />
                </span>
                <span>{{ chip.label }}</span>
                <span class="adjustments-chip-count">{{ chip.count }}</span>
            </button>
        </div>

        <p v-if="error" class="text-sm text-danger">{{ error }}</p>

        <div v-if="loading" class="ui-card p-5 text-muted">Carregando ajustes...</div>

        <template v-else-if="filteredAdjustments.length">
            <article
                v-for="item in filteredAdjustments"
                :key="item.id"
                class="ui-card adjustment-row"
                :class="[rowTone(item.status), { 'is-highlight': highlightedId && String(item.id) === highlightedId }]"
            >
                <div class="adjustment-row-delta" :class="{ 'is-negative': Number(item.diferenca) < 0 }">
                    <strong>{{ formatDelta(item.diferenca, item.product?.unidade) }}</strong>
                    <span>{{ item.product?.unidade || 'UN' }}</span>
                </div>

                <div class="adjustment-row-main">
                    <p class="adjustment-row-title">
                        {{ item.product?.nome || 'Produto removido' }}
                        <small>#{{ item.product?.codigo || '—' }}</small>
                    </p>
                    <p class="adjustment-row-meta">
                        {{ typeLabel(item.tipo) }}
                        <span>·</span>
                        {{ formatQuantity(item.quantidade_atual, item.product?.unidade) }}
                        <span>→</span>
                        {{ formatQuantity(item.nova_quantidade, item.product?.unidade) }}
                    </p>
                    <p class="adjustment-row-time">{{ formatDateLine(item.created_at) }}</p>
                    <p v-if="item.complemento" class="adjustment-row-note adjustment-row-note-hover">{{ item.complemento }}</p>
                </div>

                <div class="adjustment-row-actions">
                    <template v-if="item.status === 'pendente'">
                        <button
                            type="button"
                            class="adjustment-action-btn is-history is-hover-only"
                            title="Ver histórico do produto"
                            @click="goToHistory(item)"
                        >
                            <History class="h-4 w-4" aria-hidden="true" />
                        </button>
                        <button
                            type="button"
                            class="adjustment-action-btn is-approve"
                            title="Aprovar ajuste"
                            @click="openDecisionModal('approve', item)"
                        >
                            <Check class="h-4 w-4" aria-hidden="true" />
                        </button>
                        <button
                            type="button"
                            class="adjustment-action-btn is-reject"
                            title="Rejeitar ajuste"
                            @click="openDecisionModal('reject', item)"
                        >
                            <XCircle class="h-4 w-4" aria-hidden="true" />
                        </button>
                        <template v-if="isSuperAdmin">
                            <button
                                type="button"
                                class="adjustment-action-btn"
                                title="Editar solicitação"
                                @click="openEditModal(item)"
                            >
                                <PencilLine class="h-4 w-4" aria-hidden="true" />
                            </button>
                            <button
                                type="button"
                                class="adjustment-action-btn is-danger"
                                :disabled="actionLoadingId === item.id"
                                title="Excluir solicitação"
                                @click="removePending(item)"
                            >
                                <Trash2 class="h-4 w-4" aria-hidden="true" />
                            </button>
                        </template>
                    </template>

                    <template v-else>
                        <button
                            type="button"
                            class="adjustment-action-btn is-history is-hover-only"
                            title="Ver histórico do produto"
                            @click="goToHistory(item)"
                        >
                            <History class="h-4 w-4" aria-hidden="true" />
                        </button>
                        <AppBadge :variant="statusMeta(item.status).variant">
                            {{ statusMeta(item.status).label }}
                        </AppBadge>
                    </template>
                </div>
            </article>
        </template>

        <SettingsEmptyState
            v-else
            title="Nenhuma solicitação encontrada"
            description="Crie uma solicitação de ajuste ou altere os filtros para visualizar registros."
        >
            <template #actions>
                <AppButton @click="openCreateModal">Solicitar ajuste</AppButton>
            </template>
        </SettingsEmptyState>

        <AppModal
            :open="createEditModal.open"
            :title="createEditModal.mode === 'edit' ? 'Editar Ajuste' : 'Solicitar Ajuste de Estoque'"
            width-class="max-w-2xl"
            @close="closeCreateEditModal"
        >
            <div class="space-y-4">
                <AppSelect
                    v-model="createEditModal.form.productId"
                    label="Produto"
                    :error="createEditModal.fieldErrors.productId"
                    :disabled="createEditModal.mode === 'edit'"
                >
                    <option value="" disabled>Selecione um produto</option>
                    <option v-for="item in productOptions" :key="item.id" :value="item.id">{{ item.label }}</option>
                </AppSelect>

                <AppInput
                    :model-value="formatQuantity(currentQuantityForForm, selectedCreateEditProduct?.unidade || editingRecord?.product?.unidade)"
                    label="Quantidade Atual"
                    disabled
                />

                <AppInput
                    v-model="createEditModal.form.newQuantity"
                    label="Nova Quantidade"
                    type="number"
                    min="0"
                    step="0.001"
                    :error="createEditModal.fieldErrors.newQuantity"
                />

                <AppSelect v-model="createEditModal.form.tipo" label="Tipo de Ajuste">
                    <option v-for="item in typeOptions" :key="item.value" :value="item.value">{{ item.label }}</option>
                </AppSelect>

                <AppTextarea
                    v-model="createEditModal.form.complemento"
                    label="Complemento (opcional)"
                    rows="3"
                    placeholder="Descreva detalhes adicionais..."
                />

                <p v-if="createEditModal.error" class="text-sm text-danger">{{ createEditModal.error }}</p>

                <div class="adjustment-modal-actions">
                    <AppButton variant="secondary" @click="closeCreateEditModal">Cancelar</AppButton>
                    <AppButton :loading="createEditModal.loading" @click="saveCreateEdit">
                        {{ createEditModal.mode === 'edit' ? 'Salvar' : 'Enviar Solicitação' }}
                    </AppButton>
                </div>
            </div>
        </AppModal>

        <AppModal
            :open="decisionModal.open"
            :title="decisionModal.mode === 'approve' ? 'Aprovar Ajuste' : 'Rejeitar Ajuste'"
            width-class="max-w-2xl"
            @close="closeDecisionModal"
        >
            <div v-if="decisionModal.record" class="space-y-4">
                <article class="decision-summary">
                    <div class="decision-summary-delta" :class="{ 'is-negative': Number(decisionModal.record.diferenca) < 0 }">
                        <strong>{{ formatDelta(decisionModal.record.diferenca, decisionModal.record.product?.unidade) }}</strong>
                        <span>{{ decisionModal.record.product?.unidade || 'UN' }}</span>
                    </div>
                    <div>
                        <p class="decision-summary-title">
                            {{ decisionModal.record.product?.nome || 'Produto removido' }}
                        </p>
                        <p class="decision-summary-meta">
                            {{ typeLabel(decisionModal.record.tipo) }} ·
                            {{ formatQuantity(decisionModal.record.quantidade_atual, decisionModal.record.product?.unidade) }}
                            → {{ formatQuantity(decisionModal.record.nova_quantidade, decisionModal.record.product?.unidade) }}
                        </p>
                    </div>
                </article>

                <p v-if="decisionModal.record.complemento" class="adjustment-row-note">
                    {{ decisionModal.record.complemento }}
                </p>

                <AppTextarea
                    v-model="decisionModal.note"
                    label="Observações (opcional)"
                    rows="3"
                    placeholder="Justificativa..."
                />

                <p v-if="decisionModal.error" class="text-sm text-danger">{{ decisionModal.error }}</p>

                <div class="adjustment-modal-actions">
                    <AppButton variant="secondary" @click="closeDecisionModal">Cancelar</AppButton>
                    <AppButton
                        :variant="decisionModal.mode === 'approve' ? 'primary' : 'danger'"
                        :loading="decisionModal.loading"
                        @click="submitDecision"
                    >
                        {{ decisionModal.mode === 'approve' ? 'Aprovar' : 'Rejeitar' }}
                    </AppButton>
                </div>
            </div>
        </AppModal>
    </div>
</template>

<style scoped>
.adjustments-filter-search {
    flex: 1 1 22rem;
    min-width: 16rem;
}

.adjustments-filter-select {
    flex: 0 1 13rem;
    min-width: 11rem;
}

.adjustments-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.adjustments-chip {
    border-radius: 999px;
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 72%, transparent);
    background: color-mix(in srgb, var(--color-bg-surface) 78%, var(--color-bg-elevated));
    color: var(--color-text);
    font-size: 0.92rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.38rem 0.78rem;
    transition: all var(--transition-fast);
}

.adjustments-chip.is-active {
    border-color: color-mix(in srgb, var(--color-primary) 64%, transparent);
    background: var(--color-primary);
    color: var(--color-text-inverse);
}

.adjustments-chip.is-active .adjustments-chip-count {
    color: color-mix(in srgb, var(--color-text-inverse) 82%, transparent);
}

.adjustments-chip-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.adjustments-chip-count {
    color: var(--color-text-muted);
}

.adjustment-row {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 0.72rem;
    padding: 0.72rem 0.85rem;
    border-left: 4px solid transparent;
    transition: box-shadow var(--transition-base), background-color var(--transition-base), transform var(--transition-fast);
}

.adjustment-row:hover,
.adjustment-row:focus-within {
    background: color-mix(in srgb, var(--color-bg-elevated) 55%, var(--color-bg-surface));
    box-shadow: 0 0 0 2px color-mix(in srgb, var(--color-primary) 18%, transparent);
    transform: translateY(-1px);
}

.adjustment-row.is-pending {
    border-left-color: #eab308;
}

.adjustment-row.is-approved {
    border-left-color: color-mix(in srgb, var(--color-success) 78%, transparent);
}

.adjustment-row.is-rejected {
    border-left-color: color-mix(in srgb, var(--color-danger) 78%, transparent);
}

.adjustment-row.is-canceled {
    border-left-color: color-mix(in srgb, var(--color-border-strong) 78%, transparent);
}

.adjustment-row.is-highlight {
    box-shadow: 0 0 0 2px color-mix(in srgb, var(--color-primary) 28%, transparent);
}

.adjustment-row-delta {
    width: 6rem;
    border-radius: var(--radius-md);
    background: color-mix(in srgb, var(--color-success) 11%, var(--color-bg-surface));
    display: grid;
    justify-items: center;
    gap: 0.12rem;
    padding: 0.45rem 0.4rem;
}

.adjustment-row-delta.is-negative {
    background: color-mix(in srgb, var(--color-danger) 11%, var(--color-bg-surface));
}

.adjustment-row-delta strong {
    font-size: 2rem;
    line-height: 1;
    font-weight: 900;
    color: var(--color-success);
}

.adjustment-row-delta.is-negative strong {
    color: var(--color-danger);
}

.adjustment-row-delta span {
    font-size: 0.7rem;
    color: var(--color-text-muted);
}

.adjustment-row-main {
    min-width: 0;
    display: grid;
    gap: 0.15rem;
}

.adjustment-row-title {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--color-text);
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
}

.adjustment-row-title small {
    color: var(--color-text-muted);
    font-size: 0.94rem;
    font-weight: 600;
}

.adjustment-row-meta {
    margin: 0;
    color: var(--color-text-muted);
    font-size: 0.98rem;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.adjustment-row-time {
    margin: 0;
    color: var(--color-text-muted);
    font-size: 0.9rem;
}

.adjustment-row-note {
    margin: 0;
    border-radius: var(--radius-sm);
    background: color-mix(in srgb, var(--color-bg-elevated) 85%, var(--color-bg-surface));
    color: var(--color-text-muted);
    font-size: 0.95rem;
    padding: 0.5rem 0.68rem;
    white-space: pre-wrap;
}

.adjustment-row-note-hover {
    margin-top: 0;
    max-height: 0;
    opacity: 0;
    overflow: hidden;
    pointer-events: none;
    transform: translateY(-2px);
    padding-top: 0;
    padding-bottom: 0;
    transition:
        max-height var(--transition-base),
        opacity var(--transition-base),
        transform var(--transition-base),
        margin-top var(--transition-base),
        padding var(--transition-base);
}

.adjustment-row:hover .adjustment-row-note-hover,
.adjustment-row:focus-within .adjustment-row-note-hover {
    margin-top: 0.35rem;
    max-height: 8.5rem;
    opacity: 1;
    pointer-events: auto;
    transform: translateY(0);
    padding-top: 0.5rem;
    padding-bottom: 0.5rem;
}

.adjustment-row-actions {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    justify-content: flex-end;
}

.adjustment-action-btn {
    width: 2.15rem;
    height: 2.15rem;
    border-radius: 0.75rem;
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 60%, transparent);
    background: var(--color-bg-surface);
    color: var(--color-text-muted);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all var(--transition-fast);
}

.adjustment-action-btn.is-hover-only {
    opacity: 0;
    pointer-events: none;
    transform: translateY(-2px) scale(0.96);
}

.adjustment-row:hover .adjustment-action-btn.is-hover-only,
.adjustment-row:focus-within .adjustment-action-btn.is-hover-only {
    opacity: 1;
    pointer-events: auto;
    transform: translateY(0) scale(1);
}

.adjustment-action-btn:hover {
    color: var(--color-text);
    border-color: color-mix(in srgb, var(--color-primary) 55%, transparent);
}

.adjustment-action-btn.is-history {
    color: #ca8a04;
}

.adjustment-action-btn.is-history:hover {
    background: color-mix(in srgb, #f59e0b 20%, var(--color-bg-surface));
    border-color: color-mix(in srgb, #f59e0b 60%, transparent);
}

.adjustment-action-btn.is-approve {
    color: var(--color-success);
}

.adjustment-action-btn.is-approve:hover {
    background: color-mix(in srgb, var(--color-success) 18%, var(--color-bg-surface));
}

.adjustment-action-btn.is-reject,
.adjustment-action-btn.is-danger {
    color: var(--color-danger);
}

.adjustment-action-btn.is-reject:hover,
.adjustment-action-btn.is-danger:hover {
    background: color-mix(in srgb, var(--color-danger) 15%, var(--color-bg-surface));
}

.adjustment-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
}

.decision-summary {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    align-items: center;
    gap: 0.75rem;
}

.decision-summary-delta {
    width: 4.4rem;
    border-radius: var(--radius-md);
    background: color-mix(in srgb, var(--color-success) 11%, var(--color-bg-surface));
    padding: 0.35rem;
    display: grid;
    justify-items: center;
    gap: 0.1rem;
}

.decision-summary-delta.is-negative {
    background: color-mix(in srgb, var(--color-danger) 11%, var(--color-bg-surface));
}

.decision-summary-delta strong {
    font-size: 2rem;
    line-height: 1;
    color: var(--color-success);
}

.decision-summary-delta.is-negative strong {
    color: var(--color-danger);
}

.decision-summary-delta span {
    color: var(--color-text-muted);
    font-size: 0.68rem;
}

.decision-summary-title {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--color-text);
}

.decision-summary-meta {
    margin: 0.18rem 0 0;
    color: var(--color-text-muted);
    font-size: 0.95rem;
}

@media (max-width: 900px) {
    .adjustment-row {
        grid-template-columns: 1fr;
    }

    .adjustment-row-actions {
        justify-content: flex-start;
    }

    .adjustments-filter-search,
    .adjustments-filter-select {
        min-width: 100%;
    }
}

@media (hover: none) {
    .adjustment-row-note-hover {
        margin-top: 0.35rem;
        max-height: 8.5rem;
        opacity: 1;
        pointer-events: auto;
        transform: translateY(0);
        padding-top: 0.5rem;
        padding-bottom: 0.5rem;
    }

    .adjustment-action-btn.is-hover-only {
        opacity: 1;
        pointer-events: auto;
        transform: translateY(0) scale(1);
    }
}
</style>
