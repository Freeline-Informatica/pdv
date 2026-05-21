<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { AlertCircle, Save, UtensilsCrossed } from 'lucide-vue-next';
import { useRouter } from 'vue-router';
import api from '../../lib/api';
import SettingsPageHeader from '../../components/settings/SettingsPageHeader.vue';
import RestaurantOperationModeCards from '../../components/settings/restaurant/RestaurantOperationModeCards.vue';
import RestaurantTablesConfig from '../../components/settings/restaurant/RestaurantTablesConfig.vue';
import RestaurantTicketsConfig from '../../components/settings/restaurant/RestaurantTicketsConfig.vue';
import AppBadge from '../../components/ui/AppBadge.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppCard from '../../components/ui/AppCard.vue';
import AppToast from '../../components/ui/AppToast.vue';
import {
    applyOperationMode,
    createDefaultRestaurantParameters,
    normalizeRestaurantParameters,
} from '../../lib/restaurantParameters';

const router = useRouter();

const loading = ref(false);
const saving = ref(false);
const loadError = ref('');
const feedbackMessage = ref('');
const feedbackTone = ref('success');
const activeTab = ref('tables_tickets');

const tabItems = [
    { id: 'tables_tickets', label: 'Mesas e Fichas', enabled: true },
    { id: 'service', label: 'Atendimento', enabled: false },
    { id: 'taxes_payment', label: 'Taxas e Pagamento', enabled: false },
    { id: 'kitchen_bar', label: 'Cozinha/Bar', enabled: false },
    { id: 'printing', label: 'Impressão', enabled: false },
];

const parameters = reactive(createDefaultRestaurantParameters());
const errors = ref({});

const isAutomaticMode = computed(() => parameters.operation_mode === 'automatic');
const isManualMode = computed(() => parameters.operation_mode === 'manual');
const isHybridMode = computed(() => parameters.operation_mode === 'hybrid');

const validationErrorMessages = computed(() => {
    const current = errors.value || {};
    return Object.values(current)
        .flatMap((value) => (Array.isArray(value) ? value : [value]))
        .filter((message) => Boolean(message));
});

function updateFromNormalized(nextParameters) {
    const normalized = normalizeRestaurantParameters(nextParameters);

    parameters.operation_mode = normalized.operation_mode;
    parameters.tables = normalized.tables;
    parameters.tabs_or_tickets = normalized.tabs_or_tickets;
}

function setOperationMode(mode) {
    updateFromNormalized(applyOperationMode(parameters, mode));
    errors.value = {};
}

function updateTablesConfig(nextTables) {
    updateFromNormalized({
        ...parameters,
        tables: nextTables,
    });
}

function updateTicketsConfig(nextTickets) {
    updateFromNormalized({
        ...parameters,
        tabs_or_tickets: nextTickets,
    });
}

function normalizeErrorPayload(errorPayload) {
    const normalized = {};

    Object.entries(errorPayload || {}).forEach(([key, value]) => {
        if (Array.isArray(value) && value.length) {
            normalized[key] = value[0];
            return;
        }

        if (typeof value === 'string') {
            normalized[key] = value;
        }
    });

    return normalized;
}

function validateLocally() {
    const nextErrors = {};

    const tables = parameters.tables;
    const tickets = parameters.tabs_or_tickets;

    if ((tables.quantity ?? 0) < 0) {
        nextErrors['tables.quantity'] = 'A quantidade de mesas não pode ser negativa.';
    }

    if ((tickets.quantity ?? 0) < 0) {
        nextErrors['tabs_or_tickets.quantity'] = 'A quantidade de fichas/comandas não pode ser negativa.';
    }

    if (String(tables.prefix || '').length > 30) {
        nextErrors['tables.prefix'] = 'Use no máximo 30 caracteres no prefixo das mesas.';
    }

    if (String(tickets.prefix || '').length > 30) {
        nextErrors['tabs_or_tickets.prefix'] = 'Use no máximo 30 caracteres no prefixo das fichas/comandas.';
    }

    if ((tables.padding ?? 0) < 1 || (tables.padding ?? 0) > 6) {
        nextErrors['tables.padding'] = 'O preenchimento das mesas deve ficar entre 1 e 6.';
    }

    if ((tickets.padding ?? 0) < 1 || (tickets.padding ?? 0) > 6) {
        nextErrors['tabs_or_tickets.padding'] = 'O preenchimento das fichas/comandas deve ficar entre 1 e 6.';
    }

    if ((tickets.random_code_length ?? 0) < 3 || (tickets.random_code_length ?? 0) > 10) {
        nextErrors['tabs_or_tickets.random_code_length'] = 'O tamanho do código aleatório deve ficar entre 3 e 10.';
    }

    if (tickets.allow_without_table && tickets.require_table) {
        nextErrors['tabs_or_tickets.require_table'] = 'Se permitir comanda sem mesa, a mesa não pode ser obrigatória.';
    }

    if (parameters.operation_mode === 'manual') {
        if ((tables.quantity ?? 0) <= 0) {
            nextErrors['tables.quantity'] = 'No modo manual, a quantidade de mesas deve ser maior que zero.';
        }

        if ((tickets.quantity ?? 0) <= 0) {
            nextErrors['tabs_or_tickets.quantity'] = 'No modo manual, a quantidade de fichas/comandas deve ser maior que zero.';
        }
    }

    if (parameters.operation_mode === 'hybrid' && (tables.quantity ?? 0) <= 0) {
        nextErrors['tables.quantity'] = 'No modo híbrido, a quantidade de mesas deve ser maior que zero.';
    }

    errors.value = nextErrors;
    return Object.keys(nextErrors).length === 0;
}

async function loadParameters() {
    loading.value = true;
    loadError.value = '';

    try {
        const { data } = await api.get('/settings/restaurant-parameters');
        updateFromNormalized(data?.restaurant_parameters);
    } catch (requestError) {
        loadError.value = requestError?.response?.data?.message ?? 'Não foi possível carregar os parâmetros do restaurante.';
    } finally {
        loading.value = false;
    }
}

async function saveParameters() {
    if (!validateLocally()) {
        feedbackTone.value = 'danger';
        feedbackMessage.value = 'Existem campos com erro. Revise e tente novamente.';
        return;
    }

    saving.value = true;
    feedbackMessage.value = '';
    feedbackTone.value = 'success';

    try {
        const payload = normalizeRestaurantParameters(parameters);
        const { data } = await api.put('/settings/restaurant-parameters', payload);
        updateFromNormalized(data?.restaurant_parameters ?? payload);
        errors.value = {};
        feedbackTone.value = 'success';
        feedbackMessage.value = 'Parâmetros do restaurante salvos com sucesso.';
    } catch (requestError) {
        feedbackTone.value = 'danger';
        feedbackMessage.value = requestError?.response?.data?.message ?? 'Falha ao salvar os parâmetros do restaurante.';
        errors.value = normalizeErrorPayload(requestError?.response?.data?.errors);
    } finally {
        saving.value = false;
    }
}

function goBack() {
    if (window.history.length > 1) {
        router.back();
        return;
    }

    router.push('/configuracoes/empresa');
}

onMounted(loadParameters);
</script>

<template>
    <div class="space-y-4 restaurant-params-page pb-16">
        <SettingsPageHeader
            title="Parâmetros do Restaurante"
            subtitle="Configure como o restaurante trabalha com mesas, fichas, comandas e atendimento."
        >
            <template #actions>
                <AppButton :disabled="saving" variant="secondary" @click="goBack">Cancelar / Voltar</AppButton>
                <AppButton :loading="saving" :disabled="loading" @click="saveParameters">
                    <Save class="h-4 w-4" aria-hidden="true" />
                    Salvar parâmetros
                </AppButton>
            </template>
        </SettingsPageHeader>

        <AppToast :show="!!feedbackMessage" :tone="feedbackTone">
            {{ feedbackMessage }}
        </AppToast>

        <AppCard class="restaurant-warning-card" padding="p-4">
            <div class="restaurant-warning-card__icon" aria-hidden="true">
                <AlertCircle class="h-4 w-4" />
            </div>
            <p>
                Essas configurações afetam diretamente o fluxo do garçom, abertura de comandas, ocupação de mesas e fechamento de contas.
            </p>
        </AppCard>

        <p v-if="loadError" class="text-sm text-danger">{{ loadError }}</p>

        <AppCard class="restaurant-tabs-card" padding="p-3">
            <div class="restaurant-tabs-grid">
                <button
                    v-for="tab in tabItems"
                    :key="tab.id"
                    type="button"
                    class="restaurant-tab-btn"
                    :class="{ 'is-active': activeTab === tab.id }"
                    :disabled="!tab.enabled"
                    @click="activeTab = tab.id"
                >
                    <span>{{ tab.label }}</span>
                    <AppBadge v-if="!tab.enabled" variant="warning">Em breve</AppBadge>
                </button>
            </div>
        </AppCard>

        <AppCard v-if="loading" class="p-6 text-center text-muted">Carregando parâmetros do restaurante...</AppCard>

        <template v-else>
            <AppCard v-if="activeTab !== 'tables_tickets'" class="restaurant-upcoming-card" padding="p-5">
                <div class="restaurant-upcoming-card__icon" aria-hidden="true">
                    <UtensilsCrossed class="h-5 w-5" />
                </div>
                <h3>Configuração em breve</h3>
                <p>Essa aba será liberada em uma próxima etapa, mantendo o mesmo padrão operacional desta tela.</p>
            </AppCard>

            <div v-else class="space-y-4">
                <AppCard class="restaurant-mode-wrapper">
                    <div class="restaurant-mode-wrapper__head">
                        <h2>Modo de operação</h2>
                        <p>Escolha como mesas e fichas/comandas serão controladas na operação.</p>
                    </div>
                    <RestaurantOperationModeCards :model-value="parameters.operation_mode" @update:model-value="setOperationMode" />
                </AppCard>

                <div v-if="validationErrorMessages.length" class="restaurant-errors">
                    <p v-for="(message, index) in validationErrorMessages" :key="`validation-${index}`">{{ message }}</p>
                </div>

                <RestaurantTicketsConfig
                    v-if="isAutomaticMode"
                    :model-value="parameters.tabs_or_tickets"
                    variant="automatic"
                    :errors="errors"
                    @update:model-value="updateTicketsConfig"
                />

                <RestaurantTablesConfig
                    v-if="isAutomaticMode"
                    :model-value="parameters.tables"
                    variant="automatic"
                    :errors="errors"
                    @update:model-value="updateTablesConfig"
                />

                <RestaurantTablesConfig
                    v-if="isManualMode || isHybridMode"
                    :model-value="parameters.tables"
                    variant="manual"
                    :errors="errors"
                    @update:model-value="updateTablesConfig"
                />

                <RestaurantTicketsConfig
                    v-if="isManualMode"
                    :model-value="parameters.tabs_or_tickets"
                    variant="manual"
                    :errors="errors"
                    @update:model-value="updateTicketsConfig"
                />

                <RestaurantTicketsConfig
                    v-if="isHybridMode"
                    :model-value="parameters.tabs_or_tickets"
                    variant="automatic"
                    :errors="errors"
                    @update:model-value="updateTicketsConfig"
                />
            </div>
        </template>
    </div>
</template>

<style scoped>
.restaurant-warning-card {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    gap: 0.75rem;
    border-color: color-mix(in srgb, var(--color-warning) 42%, transparent);
    background: color-mix(in srgb, var(--color-warning) 12%, var(--color-bg-surface));
}

.restaurant-warning-card__icon {
    width: 1.85rem;
    height: 1.85rem;
    border-radius: var(--radius-sm);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid color-mix(in srgb, var(--color-warning) 45%, transparent);
    color: var(--color-warning);
}

.restaurant-warning-card p {
    margin: 0;
    font-size: 0.85rem;
    color: var(--color-text);
    line-height: 1.45;
}

.restaurant-tabs-card {
    overflow: hidden;
}

.restaurant-tabs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr));
    gap: 0.5rem;
}

.restaurant-tab-btn {
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 42%, transparent);
    border-radius: var(--radius-sm);
    background: color-mix(in srgb, var(--color-bg-surface) 88%, transparent);
    color: var(--color-text);
    min-height: 2.7rem;
    padding: 0.4rem 0.6rem;
    font-size: 0.82rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    transition: all var(--transition-fast);
}

.restaurant-tab-btn:disabled {
    cursor: not-allowed;
}

.restaurant-tab-btn.is-active {
    border-color: color-mix(in srgb, var(--color-primary) 56%, transparent);
    background: color-mix(in srgb, var(--color-primary) 14%, var(--color-bg-surface));
    color: var(--color-primary);
}

.restaurant-upcoming-card {
    display: grid;
    justify-items: center;
    text-align: center;
    gap: 0.65rem;
}

.restaurant-upcoming-card__icon {
    width: 2.35rem;
    height: 2.35rem;
    border-radius: var(--radius-sm);
    border: 1px solid color-mix(in srgb, var(--color-primary) 48%, transparent);
    color: var(--color-primary);
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.restaurant-upcoming-card h3 {
    margin: 0;
    font-size: 1.05rem;
    color: var(--color-text);
}

.restaurant-upcoming-card p {
    margin: 0;
    color: var(--color-text-muted);
}

.restaurant-mode-wrapper {
    display: grid;
    gap: 1rem;
}

.restaurant-mode-wrapper__head h2 {
    margin: 0;
    font-size: 1.06rem;
    font-weight: 800;
    color: var(--color-text);
}

.restaurant-mode-wrapper__head p {
    margin: 0.3rem 0 0;
    font-size: 0.84rem;
    color: var(--color-text-muted);
}

.restaurant-errors {
    border: 1px solid color-mix(in srgb, var(--color-danger) 44%, transparent);
    background: color-mix(in srgb, var(--color-danger) 13%, var(--color-bg-surface));
    border-radius: var(--radius-sm);
    padding: 0.65rem 0.75rem;
    display: grid;
    gap: 0.2rem;
}

.restaurant-errors p {
    margin: 0;
    font-size: 0.8rem;
    color: var(--color-danger);
}
</style>
