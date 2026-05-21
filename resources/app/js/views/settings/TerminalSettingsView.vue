<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { Monitor, Pencil, Plus, Trash2, X } from 'lucide-vue-next';
import api from '../../lib/api';
import { getTerminalSession } from '../../lib/auth';
import SettingsPageHeader from '../../components/settings/SettingsPageHeader.vue';
import AppBadge from '../../components/ui/AppBadge.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppCheckbox from '../../components/ui/AppCheckbox.vue';
import AppIconButton from '../../components/ui/AppIconButton.vue';
import AppInput from '../../components/ui/AppInput.vue';
import AppSelect from '../../components/ui/AppSelect.vue';

const items = ref([]);
const dialogOpen = ref(false);
const editingId = ref(null);
const error = ref('');
const pageError = ref('');
const actionFeedback = ref('');
const loading = ref(false);
const saving = ref(false);
const removingId = ref(null);
const companyLayoutMode = ref('varejo');
const activeBranchLabel = ref('');
const layoutModeLabels = Object.freeze({
    varejo: 'Ponto de Venda Varejo',
    restaurante: 'PDV Restaurante',
    servicos: 'PDV Servicos',
});
const restaurantModeOptions = [
    { id: 'auto_atendimento', label: 'Auto atendimento' },
    { id: 'totem', label: 'Totem' },
    { id: 'caixa', label: 'Caixa' },
    { id: 'comanda_bar', label: 'Comanda bar' },
    { id: 'comanda_cozinha', label: 'Comanda cozinha' },
    { id: 'comanda_garcom', label: 'Comanda garcom' },
];
const defaultRestaurantMode = 'comanda_garcom';
const connectedTerminalSession = getTerminalSession();

const form = reactive({
    nome: '',
    identificador: '',
    ativo: true,
    pdv_restaurant_mode: defaultRestaurantMode,
});

const dialogTitle = computed(() => (editingId.value ? 'Editar Terminal' : 'Novo Terminal'));
const saveLabel = computed(() => (editingId.value ? 'Salvar Terminal' : 'Criar Terminal'));
const connectedTerminalId = computed(() => String(connectedTerminalSession?.id || ''));
const connectedTerminalCode = computed(() => String(connectedTerminalSession?.code || '').toUpperCase());
const connectedTerminalLabel = computed(() => String(connectedTerminalSession?.label || '').trim());
const isRestaurantCompanyMode = computed(() => companyLayoutMode.value === 'restaurante');
const companyLayoutModeLabel = computed(() => layoutModeLabels[companyLayoutMode.value] || layoutModeLabels.varejo);

function normalizeIdentifier(value) {
    return String(value || '')
        .trim()
        .toUpperCase()
        .replace(/\s+/g, '');
}

function normalizeLayoutMode(value) {
    const normalized = String(value || '').trim().toLowerCase();
    return Object.hasOwn(layoutModeLabels, normalized) ? normalized : 'varejo';
}

function normalizeRestaurantMode(value) {
    const normalized = String(value || '').trim().toLowerCase();
    return restaurantModeOptions.some((option) => option.id === normalized)
        ? normalized
        : defaultRestaurantMode;
}

function restaurantModeLabel(value) {
    const normalized = normalizeRestaurantMode(value);
    const match = restaurantModeOptions.find((option) => option.id === normalized);
    return match?.label || 'Comanda garcom';
}

function layoutLabel(layoutMode, restaurantMode) {
    const normalizedLayout = normalizeLayoutMode(layoutMode);
    const label = layoutModeLabels[normalizedLayout] || layoutModeLabels.varejo;

    if (normalizedLayout !== 'restaurante') {
        return label;
    }

    return `${label} - ${restaurantModeLabel(restaurantMode)}`;
}

function isConnectedTerminal(item) {
    const itemId = String(item?.id || '');
    const itemCode = String(item?.identificador || '').trim().toUpperCase();

    if (connectedTerminalId.value && itemId) {
        return connectedTerminalId.value === itemId;
    }

    return connectedTerminalCode.value !== '' && connectedTerminalCode.value === itemCode;
}

function resetForm() {
    form.nome = '';
    form.identificador = '';
    form.ativo = true;
    form.pdv_restaurant_mode = defaultRestaurantMode;
}

async function loadItems() {
    loading.value = true;
    pageError.value = '';

    try {
        const [{ data: terminalsData }, { data: companyData }] = await Promise.all([
            api.get('/pos-terminals'),
            api.get('/settings/company'),
        ]);

        items.value = Array.isArray(terminalsData) ? terminalsData : [];
        companyLayoutMode.value = normalizeLayoutMode(companyData?.pdv_layout_mode);
        activeBranchLabel.value = String(companyData?.nome_fantasia || companyData?.razao_social || companyData?.codigo_filial || '').trim();
    } catch (requestError) {
        items.value = [];
        companyLayoutMode.value = 'varejo';
        activeBranchLabel.value = '';
        pageError.value = requestError?.response?.data?.message ?? 'Falha ao carregar os terminais.';
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    editingId.value = null;
    error.value = '';
    actionFeedback.value = '';
    resetForm();
    dialogOpen.value = true;
}

function openEdit(item) {
    editingId.value = item.id;
    error.value = '';
    actionFeedback.value = '';
    form.nome = item.nome;
    form.identificador = item.identificador;
    form.ativo = item.ativo;
    form.pdv_restaurant_mode = normalizeRestaurantMode(item.pdv_restaurant_mode);
    dialogOpen.value = true;
}

function closeDialog() {
    dialogOpen.value = false;
    error.value = '';
}

function validateForm() {
    const nome = form.nome.trim();
    const rawIdentifier = String(form.identificador || '').trim();
    const identificador = normalizeIdentifier(rawIdentifier);

    if (!nome || !identificador) {
        error.value = 'Preencha os campos obrigatórios.';
        return false;
    }

    if (/\s/.test(rawIdentifier)) {
        error.value = 'O identificador não pode conter espaços.';
        return false;
    }

    const duplicated = items.value.some((item) => {
        if (editingId.value && item.id === editingId.value) return false;
        return normalizeIdentifier(item.identificador) === identificador;
    });

    if (duplicated) {
        error.value = 'Já existe um terminal com este identificador.';
        return false;
    }

    form.identificador = identificador;
    error.value = '';
    return true;
}

async function save() {
    if (!validateForm()) return;

    saving.value = true;
    pageError.value = '';
    actionFeedback.value = '';

    try {
        const payload = {
            nome: form.nome.trim(),
            identificador: normalizeIdentifier(form.identificador),
            ativo: form.ativo,
            pdv_restaurant_mode: isRestaurantCompanyMode.value
                ? normalizeRestaurantMode(form.pdv_restaurant_mode)
                : null,
        };

        if (editingId.value) {
            await api.put(`/pos-terminals/${editingId.value}`, payload);
            actionFeedback.value = 'Terminal atualizado com sucesso.';
        } else {
            await api.post('/pos-terminals', payload);
            actionFeedback.value = 'Terminal criado com sucesso.';
        }

        closeDialog();
        await loadItems();
    } catch (requestError) {
        const validationErrors = requestError?.response?.data?.errors || {};

        if (Array.isArray(validationErrors.identificador) && validationErrors.identificador.length > 0) {
            error.value = validationErrors.identificador[0];
        } else if (Array.isArray(validationErrors.nome) && validationErrors.nome.length > 0) {
            error.value = validationErrors.nome[0];
        } else {
            error.value = requestError?.response?.data?.message ?? 'Não foi possível salvar o terminal.';
        }
    } finally {
        saving.value = false;
    }
}

async function removeItem(item) {
    if (!window.confirm(`Excluir o terminal "${item.nome}"?`)) return;

    removingId.value = item.id;
    pageError.value = '';
    actionFeedback.value = '';

    try {
        await api.delete(`/pos-terminals/${item.id}`);
        actionFeedback.value = 'Terminal excluído com sucesso.';
        await loadItems();
    } catch (requestError) {
        pageError.value = requestError?.response?.data?.message ?? 'Não foi possível excluir o terminal.';
    } finally {
        removingId.value = null;
    }
}

onMounted(loadItems);
</script>

<template>
    <div class="space-y-4">
        <SettingsPageHeader title="Terminais" subtitle="Cadastro de terminais de PDV para operação multi-caixa">
            <template #actions>
                <AppButton :disabled="loading" @click="openCreate">
                    <Plus class="h-4 w-4" aria-hidden="true" />
                    Novo Terminal
                </AppButton>
            </template>
        </SettingsPageHeader>

        <p v-if="activeBranchLabel" class="terminals-branch-context">
            Filial ativa: <strong>{{ activeBranchLabel }}</strong>
        </p>

        <p v-if="pageError" class="text-sm text-danger">{{ pageError }}</p>
        <p v-else-if="actionFeedback" class="text-sm text-success">{{ actionFeedback }}</p>
        <div v-if="connectedTerminalId || connectedTerminalCode" class="terminal-connection-hint">
            <Monitor class="h-4 w-4" aria-hidden="true" />
            <span>
                PDV conectado neste navegador:
                <strong>{{ connectedTerminalLabel || connectedTerminalCode || connectedTerminalId }}</strong>
            </span>
        </div>

        <div class="ui-table-wrap terminals-table-shell">
            <table class="ui-table terminals-table">
                <thead>
                    <tr>
                        <th class="terminals-col-name">Nome</th>
                        <th class="terminals-col-identifier">Identificador</th>
                        <th class="terminals-col-layout">Tipo de PDV</th>
                        <th class="terminals-col-status">Status</th>
                        <th class="terminals-col-actions">Ações</th>
                    </tr>
                </thead>

                <tbody>
                    <tr v-if="loading">
                        <td colspan="5" class="terminals-empty">Carregando terminais...</td>
                    </tr>

                    <tr v-else-if="items.length === 0">
                        <td colspan="5" class="terminals-empty">Nenhum terminal cadastrado.</td>
                    </tr>

                    <tr v-for="item in items" :key="item.id" :class="{ 'is-connected-terminal': isConnectedTerminal(item) }">
                        <td class="terminals-name-cell">
                            <div class="terminals-name-wrap">
                                <span>{{ item.nome }}</span>
                                <AppBadge v-if="isConnectedTerminal(item)" variant="info">
                                    Conectado
                                </AppBadge>
                            </div>
                        </td>
                        <td class="terminals-identifier-cell">{{ item.identificador }}</td>
                        <td class="terminals-layout-cell">{{ layoutLabel(companyLayoutMode, item.pdv_restaurant_mode) }}</td>
                        <td>
                            <div class="terminals-status-cell">
                                <AppBadge :variant="item.ativo ? 'success' : 'default'">
                                    {{ item.ativo ? 'Ativo' : 'Inativo' }}
                                </AppBadge>
                            </div>
                        </td>
                        <td>
                            <div class="terminals-actions-cell">
                                <AppIconButton title="Editar terminal" :disabled="removingId === item.id" @click="openEdit(item)">
                                    <Pencil class="h-4 w-4" aria-hidden="true" />
                                </AppIconButton>
                                <AppIconButton
                                    class="terminals-delete-btn"
                                    title="Excluir terminal"
                                    :disabled="removingId === item.id"
                                    @click="removeItem(item)"
                                >
                                    <Trash2 class="h-4 w-4" aria-hidden="true" />
                                </AppIconButton>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="dialogOpen" class="ui-modal-backdrop" @click.self="closeDialog">
            <section class="terminals-modal-panel">
                <header class="terminals-modal-header">
                    <div>
                        <h3 class="terminals-modal-title">{{ dialogTitle }}</h3>
                        <p class="terminals-modal-subtitle">Informe os dados do terminal de PDV.</p>
                    </div>
                    <button type="button" class="terminals-close-btn" title="Fechar" @click="closeDialog">
                        <X class="h-5 w-5" aria-hidden="true" />
                    </button>
                </header>

                <div class="space-y-3">
                    <AppInput v-model="form.nome" label="Nome" placeholder="Ex: Caixa 1" />
                    <AppInput
                        v-model="form.identificador"
                        label="Identificador"
                        placeholder="Ex: CX01"
                        hint="Identificador único do terminal (sem espaços)"
                    />
                    <div class="space-y-1">
                        <p class="ui-label">Tipo de PDV</p>
                        <div class="terminals-static-field">{{ companyLayoutModeLabel }}</div>
                        <p class="terminals-help">Tipo de PDV definido em Dados da Empresa &gt; PDV.</p>
                    </div>
                    <div v-if="isRestaurantCompanyMode" class="space-y-1">
                        <AppSelect v-model="form.pdv_restaurant_mode" label="Versão do PDV Restaurante">
                            <option v-for="option in restaurantModeOptions" :key="option.id" :value="option.id">
                                {{ option.label }}
                            </option>
                        </AppSelect>
                        <p class="terminals-help">Essa opção aparece quando o tipo global está como PDV Restaurante.</p>
                    </div>
                    <AppCheckbox v-model="form.ativo" label="Ativo" />
                </div>

                <p v-if="error" class="terminals-error">{{ error }}</p>

                <footer class="terminals-modal-actions">
                    <AppButton variant="secondary" @click="closeDialog">Cancelar</AppButton>
                    <AppButton :loading="saving" @click="save">{{ saveLabel }}</AppButton>
                </footer>
            </section>
        </div>
    </div>
</template>

<style scoped>
.terminals-table-shell {
    border-radius: var(--radius-xl);
}

.terminals-branch-context {
    margin: 0;
    font-size: 0.92rem;
    color: var(--color-text-muted);
}

.terminals-branch-context strong {
    color: var(--color-text);
}

.terminals-table {
    table-layout: fixed;
}

.terminals-col-name,
.terminals-col-identifier,
.terminals-col-layout,
.terminals-col-status,
.terminals-col-actions {
    text-transform: none;
    font-size: 0.95rem;
    font-weight: 700;
    letter-spacing: 0;
    text-align: left;
}

.terminals-col-name {
    width: 28%;
}

.terminals-col-identifier {
    width: 22%;
}

.terminals-col-layout {
    width: 22%;
}

.terminals-col-status {
    width: 16%;
}

.terminals-col-actions {
    width: 12%;
    text-align: right;
}

.terminals-name-cell,
.terminals-identifier-cell,
.terminals-layout-cell {
    font-size: 1rem;
    font-weight: 700;
    color: var(--color-text);
}

.terminals-identifier-cell {
    color: var(--color-text-muted);
    letter-spacing: 0.02em;
}

.terminals-name-wrap {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
}

.terminals-table tbody tr.is-connected-terminal {
    background: color-mix(in srgb, var(--color-primary) 10%, transparent);
}

.terminals-table tbody tr.is-connected-terminal td {
    box-shadow: inset 0 -1px 0 color-mix(in srgb, var(--color-primary) 30%, transparent);
}

.terminals-status-cell {
    display: inline-flex;
    align-items: center;
}

.terminals-actions-cell {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 0.45rem;
}

.terminals-delete-btn {
    color: var(--color-danger);
}

.terminals-delete-btn:hover {
    background: color-mix(in srgb, var(--color-danger) 14%, var(--color-bg-surface));
}

.terminals-empty {
    text-align: center;
    color: var(--color-text-muted);
    padding: 1.25rem;
}

.terminals-modal-panel {
    width: min(40rem, 100%);
    border-radius: var(--radius-lg);
    border: 1px solid var(--color-border);
    background: var(--color-bg-surface);
    padding: var(--space-5);
    box-shadow: var(--shadow-lg);
}

.terminals-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: var(--space-3);
    margin-bottom: var(--space-4);
}

.terminals-modal-title {
    margin: 0;
    font-size: 1.85rem;
    line-height: 1.1;
    color: var(--color-text);
    font-weight: 800;
}

.terminals-modal-subtitle {
    margin: 0.4rem 0 0;
    color: var(--color-text-muted);
    font-size: 1rem;
}

.terminals-close-btn {
    border: 0;
    background: transparent;
    color: var(--color-text-muted);
    cursor: pointer;
    padding: 0.15rem;
}

.terminals-close-btn:hover {
    color: var(--color-text);
}

.terminals-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: var(--space-2);
    margin-top: var(--space-4);
}

.terminals-error {
    margin: var(--space-3) 0 0;
    color: var(--color-danger);
    font-size: 0.9rem;
}

.terminals-help {
    margin: 0;
    color: var(--color-text-muted);
    font-size: 0.84rem;
}

.terminals-static-field {
    min-height: 2.85rem;
    display: flex;
    align-items: center;
    border-radius: 1rem;
    border: 1px solid var(--color-border);
    background: var(--color-bg-surface);
    padding: 0 0.95rem;
    font-size: 1rem;
    font-weight: 600;
    color: var(--color-text);
}

.terminal-connection-hint {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border: 1px solid color-mix(in srgb, var(--color-primary) 35%, var(--color-border));
    background: color-mix(in srgb, var(--color-primary) 12%, var(--color-bg-surface));
    color: var(--color-text);
    border-radius: 0.7rem;
    padding: 0.45rem 0.7rem;
    font-size: 0.9rem;
}

.terminal-connection-hint strong {
    font-weight: 800;
}

.terminals-modal-panel :deep(.ui-label) {
    font-size: 1rem;
    font-weight: 700;
    color: var(--color-text);
}

.terminals-modal-panel :deep(.ui-field) {
    min-height: 2.85rem;
    border-radius: 1rem;
    font-size: 1rem;
}

.terminals-modal-panel :deep(.ui-field-hint) {
    font-size: 0.88rem;
}

.terminals-modal-panel :deep(.ui-checkbox-wrap) {
    margin-top: 0.1rem;
    font-size: 1.05rem;
    color: var(--color-text);
}

.terminals-modal-panel :deep(.ui-checkbox) {
    width: 1.12rem;
    height: 1.12rem;
}

.terminals-modal-panel :deep(.ui-btn) {
    min-width: 7.2rem;
    min-height: 2.7rem;
    font-size: 0.95rem;
}

@media (max-width: 1024px) {
    .terminals-col-name,
    .terminals-col-identifier,
    .terminals-col-layout,
    .terminals-col-status,
    .terminals-col-actions {
        font-size: 0.88rem;
    }

    .terminals-name-cell,
    .terminals-identifier-cell,
    .terminals-layout-cell {
        font-size: 0.94rem;
    }

    .terminals-modal-title {
        font-size: 1.5rem;
    }

    .terminals-modal-subtitle {
        font-size: 0.94rem;
    }

    .terminals-modal-panel :deep(.ui-field) {
        font-size: 0.95rem;
        min-height: 2.65rem;
    }

    .terminals-modal-panel :deep(.ui-checkbox-wrap) {
        font-size: 1rem;
    }

    .terminals-modal-panel :deep(.ui-btn) {
        min-height: 2.55rem;
        font-size: 0.92rem;
    }
}

@media (max-width: 720px) {
    .terminals-table-shell {
        overflow-x: auto;
    }

    .terminals-table {
        min-width: 41rem;
    }

    .terminals-modal-panel {
        padding: var(--space-4);
    }

    .terminals-modal-actions {
        justify-content: stretch;
    }

    .terminals-modal-actions :deep(.ui-btn) {
        flex: 1;
    }
}
</style>
