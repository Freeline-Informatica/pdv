<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import {
    ArrowDownCircle,
    ArrowUpCircle,
    LockKeyhole,
    Monitor,
    RefreshCcw,
} from 'lucide-vue-next';
import api from '../../lib/api';
import { getUser } from '../../lib/auth';
import SettingsPageHeader from '../../components/settings/SettingsPageHeader.vue';
import SettingsTableCard from '../../components/settings/SettingsTableCard.vue';
import SettingsEmptyState from '../../components/settings/SettingsEmptyState.vue';
import AppBadge from '../../components/ui/AppBadge.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppInput from '../../components/ui/AppInput.vue';
import AppModal from '../../components/ui/AppModal.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppTable from '../../components/ui/AppTable.vue';
import AppTextarea from '../../components/ui/AppTextarea.vue';

const currentUser = ref(getUser());
const loadingDashboard = ref(false);
const loadingDetail = ref(false);
const pageError = ref('');
const actionFeedback = ref('');

const terminals = ref([]);
const openRegisters = ref([]);
const historyRows = ref([]);
const selectedRegisterId = ref('');
const selectedRegister = ref(null);

const openCashModal = reactive({
    open: false,
    loading: false,
    error: '',
    terminalId: '',
    valorInicial: '',
    observacoes: '',
});

const movementModal = reactive({
    open: false,
    loading: false,
    error: '',
    type: 'sangria',
    valor: '',
    descricao: '',
});

const closeCashModal = reactive({
    open: false,
    loading: false,
    error: '',
    valorContado: '',
    observacoes: '',
});

const hasOpenRegisters = computed(() => openRegisters.value.length > 0);
const selectedSummary = computed(() => selectedRegister.value?.summary || {
    opening_amount: 0,
    cash_received_amount: 0,
    sangria_amount: 0,
    balance_amount: 0,
});
const paymentSummary = computed(() => selectedRegister.value?.payment_summary || {
    dinheiro: 0,
    pix: 0,
    debito: 0,
    credito: 0,
});
const operatorLabel = computed(() => {
    const name = currentUser.value?.name || '';
    const email = currentUser.value?.email || '';
    if (email) return email;
    if (name) return name;
    return 'Operador logado';
});
const movementModalTitle = computed(() => (movementModal.type === 'sangria' ? 'Registrar Sangria' : 'Registrar Suprimento'));
const closeExpectedAmount = computed(() => Number(selectedRegister.value?.summary?.expected_amount || 0));
const activeTerminalCodes = computed(() => new Set(openRegisters.value.map((item) => String(item.terminal_codigo || '').toUpperCase())));
const availableTerminals = computed(() => terminals.value.filter((item) => !activeTerminalCodes.value.has(item.identificador)));
const canOpenCash = computed(() => availableTerminals.value.length > 0);

async function loadTerminalOptions() {
    try {
        const { data } = await api.get('/pos-terminals');
        terminals.value = Array.isArray(data)
            ? data
                .map((item) => ({
                    id: String(item?.id || ''),
                    nome: String(item?.nome || ''),
                    identificador: String(item?.identificador || '').toUpperCase(),
                    ativo: Boolean(item?.ativo),
                }))
                .filter((item) => item.id && item.nome && item.identificador && item.ativo)
            : [];
    } catch {
        terminals.value = [];
    }
}

function normalizeMoneyInput(value) {
    return String(value ?? '')
        .trim()
        .replace(/\s+/g, '')
        .replace(',', '.');
}

function parseMoney(value) {
    const normalized = normalizeMoneyInput(value);
    if (!normalized) return null;
    const numeric = Number(normalized);
    if (!Number.isFinite(numeric) || numeric < 0) return null;
    return Math.round(numeric * 100) / 100;
}

function formatCurrency(value) {
    return Number(value || 0).toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    });
}

function formatDiff(value) {
    const numeric = Number(value || 0);
    if (Math.abs(numeric) < 0.000001) return 'R$ 0,00';
    if (numeric > 0) return `+ ${formatCurrency(numeric)}`;
    return `- ${formatCurrency(Math.abs(numeric))}`;
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

function movementValueClass(signedAmount) {
    const numeric = Number(signedAmount || 0);
    if (numeric > 0) return 'is-positive';
    if (numeric < 0) return 'is-negative';
    return '';
}

function movementValueLabel(signedAmount) {
    const numeric = Number(signedAmount || 0);
    if (numeric > 0) return `+ ${formatCurrency(numeric)}`;
    if (numeric < 0) return `- ${formatCurrency(Math.abs(numeric))}`;
    return formatCurrency(0);
}

function historyDiffClass(value) {
    const numeric = Number(value || 0);
    if (numeric > 0) return 'history-diff is-positive';
    if (numeric < 0) return 'history-diff is-negative';
    return 'history-diff';
}

async function loadDashboard() {
    loadingDashboard.value = true;
    pageError.value = '';

    try {
        const { data } = await api.get('/cash-registers');
        openRegisters.value = Array.isArray(data?.open_registers) ? data.open_registers : [];
        historyRows.value = Array.isArray(data?.history) ? data.history : [];

        const selectedExists = openRegisters.value.some((item) => item.id === selectedRegisterId.value);

        if (!selectedExists) {
            selectedRegisterId.value = openRegisters.value[0]?.id || '';
            selectedRegister.value = null;
        }
    } catch (requestError) {
        openRegisters.value = [];
        historyRows.value = [];
        selectedRegisterId.value = '';
        selectedRegister.value = null;
        pageError.value = requestError?.response?.data?.message ?? 'Falha ao carregar os caixas.';
    } finally {
        loadingDashboard.value = false;
    }
}

async function loadSelectedRegister(registerId) {
    if (!registerId) {
        selectedRegister.value = null;
        return;
    }

    loadingDetail.value = true;
    pageError.value = '';

    try {
        const { data } = await api.get(`/cash-registers/${registerId}`);
        selectedRegister.value = data;
    } catch (requestError) {
        selectedRegister.value = null;
        pageError.value = requestError?.response?.data?.message ?? 'Falha ao carregar detalhes do caixa.';
    } finally {
        loadingDetail.value = false;
    }
}

async function refreshPage() {
    actionFeedback.value = '';
    await loadTerminalOptions();
    await loadDashboard();

    if (selectedRegisterId.value) {
        await loadSelectedRegister(selectedRegisterId.value);
    }
}

async function selectOpenRegister(registerId) {
    if (!registerId || loadingDetail.value) return;
    if (selectedRegisterId.value === registerId && selectedRegister.value) return;

    selectedRegisterId.value = registerId;
    await loadSelectedRegister(registerId);
}

function openOpenCashModal() {
    if (!canOpenCash.value) {
        actionFeedback.value = '';
        pageError.value = 'Todos os terminais ativos já possuem caixa aberto.';
        return;
    }

    openCashModal.error = '';
    openCashModal.loading = false;
    openCashModal.valorInicial = '';
    openCashModal.observacoes = '';
    openCashModal.terminalId = availableTerminals.value[0]?.id || '';
    openCashModal.open = true;
}

function closeOpenCashModal() {
    openCashModal.open = false;
    openCashModal.error = '';
    openCashModal.loading = false;
}

async function submitOpenCash() {
    openCashModal.error = '';

    const terminal = availableTerminals.value.find((item) => item.id === openCashModal.terminalId);
    if (!terminal) {
        openCashModal.error = 'Selecione um terminal disponível.';
        return;
    }

    const openingAmount = parseMoney(openCashModal.valorInicial);
    if (openingAmount == null) {
        openCashModal.error = 'Informe um valor inicial válido.';
        return;
    }

    openCashModal.loading = true;
    pageError.value = '';
    actionFeedback.value = '';

    try {
        const { data } = await api.post('/cash-registers/open', {
            terminal_id: terminal.id,
            valor_inicial: openingAmount,
            observacoes: openCashModal.observacoes.trim() || null,
        });

        closeOpenCashModal();
        await loadDashboard();
        selectedRegisterId.value = data?.id || '';
        await loadSelectedRegister(selectedRegisterId.value);
        actionFeedback.value = 'Caixa aberto com sucesso.';
    } catch (requestError) {
        const validationErrors = requestError?.response?.data?.errors || {};
        if (Array.isArray(validationErrors.terminal_id) && validationErrors.terminal_id.length > 0) {
            openCashModal.error = validationErrors.terminal_id[0];
        } else {
            openCashModal.error = requestError?.response?.data?.message ?? 'Não foi possível abrir o caixa.';
        }
    } finally {
        openCashModal.loading = false;
    }
}

function openMovement(type) {
    if (!selectedRegister.value || selectedRegister.value.status !== 'aberto') return;
    movementModal.open = true;
    movementModal.error = '';
    movementModal.loading = false;
    movementModal.type = type;
    movementModal.valor = '';
    movementModal.descricao = '';
}

function closeMovementModal() {
    movementModal.open = false;
    movementModal.error = '';
    movementModal.loading = false;
}

async function submitMovement() {
    if (!selectedRegister.value) return;

    movementModal.error = '';
    const amount = parseMoney(movementModal.valor);

    if (amount == null || amount <= 0) {
        movementModal.error = 'Informe um valor válido maior que zero.';
        return;
    }

    movementModal.loading = true;
    pageError.value = '';
    actionFeedback.value = '';

    try {
        const { data } = await api.post(`/cash-registers/${selectedRegister.value.id}/movements`, {
            type: movementModal.type,
            valor: amount,
            descricao: movementModal.descricao.trim() || null,
        });

        selectedRegister.value = data;
        closeMovementModal();
        await loadDashboard();
        actionFeedback.value = movementModal.type === 'sangria'
            ? 'Sangria registrada com sucesso.'
            : 'Suprimento registrado com sucesso.';
    } catch (requestError) {
        const validationErrors = requestError?.response?.data?.errors || {};
        if (Array.isArray(validationErrors.status) && validationErrors.status.length > 0) {
            movementModal.error = validationErrors.status[0];
        } else {
            movementModal.error = requestError?.response?.data?.message ?? 'Não foi possível registrar a movimentação.';
        }
    } finally {
        movementModal.loading = false;
    }
}

function openCloseModal() {
    if (!selectedRegister.value || selectedRegister.value.status !== 'aberto') return;
    closeCashModal.open = true;
    closeCashModal.loading = false;
    closeCashModal.error = '';
    closeCashModal.valorContado = String(closeExpectedAmount.value.toFixed(2));
    closeCashModal.observacoes = '';
}

function closeCloseModal() {
    closeCashModal.open = false;
    closeCashModal.loading = false;
    closeCashModal.error = '';
}

async function submitCloseCash() {
    if (!selectedRegister.value) return;

    closeCashModal.error = '';
    const counted = parseMoney(closeCashModal.valorContado);

    if (counted == null) {
        closeCashModal.error = 'Informe o valor contado corretamente.';
        return;
    }

    closeCashModal.loading = true;
    pageError.value = '';
    actionFeedback.value = '';

    try {
        await api.post(`/cash-registers/${selectedRegister.value.id}/close`, {
            valor_contado: counted,
            observacoes: closeCashModal.observacoes.trim() || null,
        });

        closeCloseModal();
        await loadDashboard();

        if (selectedRegisterId.value) {
            await loadSelectedRegister(selectedRegisterId.value);
        }

        actionFeedback.value = 'Caixa fechado com sucesso.';
    } catch (requestError) {
        const validationErrors = requestError?.response?.data?.errors || {};
        if (Array.isArray(validationErrors.status) && validationErrors.status.length > 0) {
            closeCashModal.error = validationErrors.status[0];
        } else {
            closeCashModal.error = requestError?.response?.data?.message ?? 'Não foi possível fechar o caixa.';
        }
    } finally {
        closeCashModal.loading = false;
    }
}

onMounted(async () => {
    await loadTerminalOptions();
    await loadDashboard();

    if (selectedRegisterId.value) {
        await loadSelectedRegister(selectedRegisterId.value);
    }
});
</script>

<template>
    <div class="space-y-4">
        <SettingsPageHeader title="Caixa" subtitle="Gerenciamento de caixas e movimentações">
            <template #actions>
                <div class="cash-header-actions">
                    <AppButton variant="secondary" :loading="loadingDashboard" @click="refreshPage">
                        <RefreshCcw class="h-4 w-4" aria-hidden="true" />
                        Atualizar
                    </AppButton>
                    <AppButton :disabled="!canOpenCash" @click="openOpenCashModal">
                        <LockKeyhole class="h-4 w-4" aria-hidden="true" />
                        Abrir Caixa
                    </AppButton>
                </div>
            </template>
        </SettingsPageHeader>

        <p v-if="pageError" class="text-sm text-danger">{{ pageError }}</p>
        <p v-else-if="actionFeedback" class="text-sm text-success">{{ actionFeedback }}</p>

        <SettingsTableCard v-if="!hasOpenRegisters && !loadingDashboard">
            <SettingsEmptyState
                title="Nenhum caixa aberto"
                description="Abra um caixa para iniciar as operações."
            >
                <template #actions>
                    <AppButton :disabled="!canOpenCash" @click="openOpenCashModal">
                        <LockKeyhole class="h-4 w-4" aria-hidden="true" />
                        Abrir Caixa
                    </AppButton>
                </template>
            </SettingsEmptyState>
        </SettingsTableCard>

        <template v-if="hasOpenRegisters">
            <section class="ui-card cash-open-wrap">
                <header class="cash-open-header">
                    <h3 class="cash-open-title">
                        <Monitor class="h-4 w-4" aria-hidden="true" />
                        Caixas Abertos ({{ openRegisters.length }})
                    </h3>
                </header>

                <div class="cash-open-grid">
                    <button
                        v-for="register in openRegisters"
                        :key="register.id"
                        type="button"
                        class="cash-open-card"
                        :class="{ 'is-active': selectedRegisterId === register.id }"
                        @click="selectOpenRegister(register.id)"
                    >
                        <div class="cash-open-card-top">
                            <strong>{{ register.terminal_nome }}</strong>
                            <AppBadge variant="success">Aberto</AppBadge>
                        </div>
                        <p class="cash-open-card-sub">{{ register.terminal_codigo }}</p>
                        <p class="cash-open-card-sub">Aberto em {{ formatDateTime(register.opened_at) }}</p>
                        <p class="cash-open-card-value">{{ formatCurrency(register.opening_amount) }}</p>
                    </button>
                </div>
            </section>

            <section class="ui-card cash-selected-wrap">
                <header class="cash-selected-head">
                    <div>
                        <h3 class="cash-selected-title">
                            <Monitor class="h-5 w-5" aria-hidden="true" />
                            {{ selectedRegister?.terminal_nome || 'Caixa' }}
                        </h3>
                        <p class="cash-selected-subtitle">
                            Aberto em {{ formatDateTime(selectedRegister?.opened_at) }}
                            · {{ selectedRegister?.opened_by?.email || selectedRegister?.opened_by?.name || 'Sem operador' }}
                        </p>
                    </div>

                    <div class="cash-selected-actions">
                        <AppButton variant="secondary" :disabled="!selectedRegister || selectedRegister.status !== 'aberto'" @click="openMovement('sangria')">
                            <ArrowDownCircle class="h-4 w-4" aria-hidden="true" />
                            Sangria
                        </AppButton>
                        <AppButton variant="secondary" :disabled="!selectedRegister || selectedRegister.status !== 'aberto'" @click="openMovement('suprimento')">
                            <ArrowUpCircle class="h-4 w-4" aria-hidden="true" />
                            Suprimento
                        </AppButton>
                        <AppButton variant="danger" :disabled="!selectedRegister || selectedRegister.status !== 'aberto'" @click="openCloseModal">
                            <LockKeyhole class="h-4 w-4" aria-hidden="true" />
                            Fechar Caixa
                        </AppButton>
                    </div>
                </header>

                <p v-if="loadingDetail" class="text-sm text-muted">Carregando detalhes do caixa...</p>

                <template v-else-if="selectedRegister">
                    <div class="cash-kpis-grid">
                        <article class="cash-kpi-card">
                            <p class="cash-kpi-label">Abertura</p>
                            <p class="cash-kpi-value">{{ formatCurrency(selectedSummary.opening_amount) }}</p>
                        </article>
                        <article class="cash-kpi-card">
                            <p class="cash-kpi-label">Recebido (dinheiro)</p>
                            <p class="cash-kpi-value text-success">{{ formatCurrency(selectedSummary.cash_received_amount) }}</p>
                        </article>
                        <article class="cash-kpi-card">
                            <p class="cash-kpi-label">Sangrias</p>
                            <p class="cash-kpi-value text-danger">{{ formatCurrency(selectedSummary.sangria_amount) }}</p>
                        </article>
                        <article class="cash-kpi-card is-balance">
                            <p class="cash-kpi-label">Saldo Atual em Caixa</p>
                            <p class="cash-kpi-value text-success">{{ formatCurrency(selectedSummary.balance_amount) }}</p>
                        </article>
                    </div>

                    <article class="cash-block">
                        <h4 class="cash-block-title">Resumo por Meio de Pagamento</h4>
                        <div class="cash-payment-grid">
                            <div class="cash-payment-card">
                                <p class="cash-payment-label">Dinheiro (líquido)</p>
                                <p class="cash-payment-value">{{ formatCurrency(paymentSummary.dinheiro) }}</p>
                            </div>
                            <div class="cash-payment-card">
                                <p class="cash-payment-label">PIX</p>
                                <p class="cash-payment-value">{{ formatCurrency(paymentSummary.pix) }}</p>
                            </div>
                            <div class="cash-payment-card">
                                <p class="cash-payment-label">Débito</p>
                                <p class="cash-payment-value">{{ formatCurrency(paymentSummary.debito) }}</p>
                            </div>
                            <div class="cash-payment-card">
                                <p class="cash-payment-label">Crédito</p>
                                <p class="cash-payment-value">{{ formatCurrency(paymentSummary.credito) }}</p>
                            </div>
                        </div>
                    </article>

                    <article class="cash-block">
                        <h4 class="cash-block-title">Movimentações</h4>
                        <div v-if="(selectedRegister.movements || []).length === 0" class="cash-empty-movements">
                            Nenhuma movimentação registrada para este caixa.
                        </div>
                        <div v-else class="cash-movements-list">
                            <div v-for="movement in selectedRegister.movements" :key="movement.id" class="cash-movement-row">
                                <div class="cash-movement-left">
                                    <span class="cash-movement-time">{{ formatDateTime(movement.happened_at) }}</span>
                                    <AppBadge variant="default">{{ movement.label }}</AppBadge>
                                    <span class="cash-movement-desc">{{ movement.description || '—' }}</span>
                                </div>
                                <strong :class="movementValueClass(movement.signed_amount)">
                                    {{ movementValueLabel(movement.signed_amount) }}
                                </strong>
                            </div>
                        </div>
                    </article>
                </template>
            </section>
        </template>

        <SettingsTableCard>
            <div class="cash-history-title-wrap">
                <h3 class="cash-history-title">Histórico de Caixas</h3>
            </div>

            <AppTable>
                <thead>
                    <tr>
                        <th class="text-left">Terminal</th>
                        <th class="text-left">Operador</th>
                        <th class="text-left">Abertura</th>
                        <th class="text-left">Fechamento</th>
                        <th class="text-right">Valor Inicial</th>
                        <th class="text-right">Esperado</th>
                        <th class="text-right">Contado</th>
                        <th class="text-right">Diferença</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loadingDashboard">
                        <td colspan="8" class="text-center text-muted py-6">Carregando histórico de caixas...</td>
                    </tr>

                    <tr v-else-if="historyRows.length === 0">
                        <td colspan="8" class="p-0">
                            <SettingsEmptyState
                                title="Sem registros de caixa"
                                description="Abra um caixa para iniciar o histórico de movimentações."
                            />
                        </td>
                    </tr>

                    <tr v-for="row in historyRows" :key="row.id">
                        <td class="font-semibold text-main">
                            {{ row.terminal_nome }}
                        </td>
                        <td class="text-muted">{{ row.opened_by?.name || row.opened_by?.email || '—' }}</td>
                        <td class="text-muted">{{ formatDateTime(row.opened_at) }}</td>
                        <td class="text-muted">{{ formatDateTime(row.closed_at) }}</td>
                        <td class="text-right font-semibold">{{ formatCurrency(row.opening_amount) }}</td>
                        <td class="text-right font-semibold">{{ formatCurrency(row.expected_amount) }}</td>
                        <td class="text-right font-semibold">{{ row.counted_amount == null ? '—' : formatCurrency(row.counted_amount) }}</td>
                        <td class="text-right font-semibold">
                            <span :class="historyDiffClass(row.difference_amount)">
                                {{ row.difference_amount == null ? '—' : formatDiff(row.difference_amount) }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </AppTable>
        </SettingsTableCard>

        <AppModal
            :open="openCashModal.open"
            title="Abertura de Caixa"
            width-class="max-w-2xl"
            @close="closeOpenCashModal"
        >
            <div class="space-y-4">
                <p class="text-sm text-muted">
                    Informe o valor inicial disponível no caixa (troco).
                </p>

                <div class="cash-open-time-chip">
                    Abertura em: {{ formatDateTime(new Date()) }}
                </div>

                <AppSelect v-model="openCashModal.terminalId" label="Terminal">
                    <option value="" disabled>Selecione um terminal...</option>
                    <option v-for="terminal in availableTerminals" :key="terminal.id" :value="terminal.id">
                        {{ terminal.nome }} ({{ terminal.identificador }})
                    </option>
                </AppSelect>

                <AppInput :model-value="operatorLabel" label="Operador" disabled />

                <AppInput
                    v-model="openCashModal.valorInicial"
                    label="Valor Inicial (fundo de troco)"
                    type="number"
                    min="0"
                    step="0.01"
                    placeholder="0,00"
                />

                <AppTextarea
                    v-model="openCashModal.observacoes"
                    label="Observação"
                    rows="3"
                    placeholder="Opcional"
                />

                <p v-if="openCashModal.error" class="text-sm text-danger">{{ openCashModal.error }}</p>

                <div class="cash-modal-actions">
                    <AppButton variant="secondary" @click="closeOpenCashModal">Cancelar</AppButton>
                    <AppButton :loading="openCashModal.loading" @click="submitOpenCash">Confirmar Abertura</AppButton>
                </div>
            </div>
        </AppModal>

        <AppModal
            :open="movementModal.open"
            :title="movementModalTitle"
            width-class="max-w-xl"
            @close="closeMovementModal"
        >
            <div class="space-y-4">
                <AppInput
                    v-model="movementModal.valor"
                    label="Valor"
                    type="number"
                    min="0"
                    step="0.01"
                    placeholder="0,00"
                />

                <AppTextarea
                    v-model="movementModal.descricao"
                    label="Observação"
                    rows="3"
                    placeholder="Descreva o motivo da movimentação"
                />

                <p v-if="movementModal.error" class="text-sm text-danger">{{ movementModal.error }}</p>

                <div class="cash-modal-actions">
                    <AppButton variant="secondary" @click="closeMovementModal">Cancelar</AppButton>
                    <AppButton :loading="movementModal.loading" @click="submitMovement">Confirmar</AppButton>
                </div>
            </div>
        </AppModal>

        <AppModal
            :open="closeCashModal.open"
            title="Fechar Caixa"
            width-class="max-w-xl"
            @close="closeCloseModal"
        >
            <div class="space-y-4">
                <p class="text-sm text-muted">
                    Valor esperado para fechamento: <strong class="text-main">{{ formatCurrency(closeExpectedAmount) }}</strong>
                </p>

                <AppInput
                    v-model="closeCashModal.valorContado"
                    label="Valor contado"
                    type="number"
                    min="0"
                    step="0.01"
                    placeholder="0,00"
                />

                <AppTextarea
                    v-model="closeCashModal.observacoes"
                    label="Observação de fechamento"
                    rows="3"
                    placeholder="Opcional"
                />

                <p v-if="closeCashModal.error" class="text-sm text-danger">{{ closeCashModal.error }}</p>

                <div class="cash-modal-actions">
                    <AppButton variant="secondary" @click="closeCloseModal">Cancelar</AppButton>
                    <AppButton variant="danger" :loading="closeCashModal.loading" @click="submitCloseCash">
                        Confirmar Fechamento
                    </AppButton>
                </div>
            </div>
        </AppModal>
    </div>
</template>

<style scoped>
.cash-header-actions {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    flex-wrap: wrap;
}

.cash-open-wrap,
.cash-selected-wrap {
    padding: 1rem;
    display: grid;
    gap: 0.9rem;
}

.cash-open-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
}

.cash-open-title {
    margin: 0;
    color: var(--color-text);
    font-size: 1.42rem;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
}

.cash-open-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr));
    gap: 0.7rem;
}

.cash-open-card {
    border-radius: var(--radius-md);
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 65%, transparent);
    background: var(--color-bg-surface);
    padding: 0.8rem;
    display: grid;
    gap: 0.22rem;
    text-align: left;
    transition: all var(--transition-fast);
}

.cash-open-card:hover {
    border-color: color-mix(in srgb, var(--color-primary) 62%, transparent);
    background: color-mix(in srgb, var(--color-primary) 6%, var(--color-bg-surface));
}

.cash-open-card.is-active {
    border-color: color-mix(in srgb, var(--color-primary) 80%, transparent);
    background: color-mix(in srgb, var(--color-primary) 10%, var(--color-bg-surface));
}

.cash-open-card-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
}

.cash-open-card-top strong {
    font-size: 1rem;
    color: var(--color-text);
}

.cash-open-card-sub {
    margin: 0;
    color: var(--color-text-muted);
    font-size: 0.88rem;
}

.cash-open-card-value {
    margin: 0.2rem 0 0;
    color: var(--color-text);
    font-size: 1.35rem;
    font-weight: 800;
}

.cash-selected-head {
    display: flex;
    justify-content: space-between;
    gap: 0.8rem;
    flex-wrap: wrap;
}

.cash-selected-title {
    margin: 0;
    color: var(--color-text);
    font-size: 1.58rem;
    font-weight: 800;
    display: inline-flex;
    gap: 0.4rem;
    align-items: center;
}

.cash-selected-subtitle {
    margin: 0.2rem 0 0;
    color: var(--color-text-muted);
    font-size: 0.95rem;
}

.cash-selected-actions {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.cash-kpis-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.7rem;
}

.cash-kpi-card {
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 66%, transparent);
    border-radius: var(--radius-md);
    background: var(--color-bg-surface);
    padding: 0.72rem 0.82rem;
    display: grid;
    gap: 0.35rem;
}

.cash-kpi-card.is-balance {
    border-color: color-mix(in srgb, var(--color-success) 38%, var(--color-border));
    background: color-mix(in srgb, var(--color-success) 7%, var(--color-bg-surface));
}

.cash-kpi-label {
    margin: 0;
    color: var(--color-text-muted);
    font-size: 0.86rem;
    font-weight: 600;
}

.cash-kpi-value {
    margin: 0;
    color: var(--color-text);
    font-size: 1.8rem;
    font-weight: 850;
    line-height: 1.05;
}

.cash-block {
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 60%, transparent);
    border-radius: var(--radius-lg);
    background: var(--color-bg-surface);
    padding: 0.84rem;
    display: grid;
    gap: 0.75rem;
}

.cash-block-title {
    margin: 0;
    color: var(--color-text);
    font-size: 1.16rem;
    font-weight: 800;
}

.cash-payment-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.65rem;
}

.cash-payment-card {
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 60%, transparent);
    border-radius: var(--radius-md);
    padding: 0.68rem 0.75rem;
    background: color-mix(in srgb, var(--color-bg-elevated) 74%, var(--color-bg-surface));
}

.cash-payment-label {
    margin: 0;
    color: var(--color-text-muted);
    font-size: 0.84rem;
}

.cash-payment-value {
    margin: 0.22rem 0 0;
    color: var(--color-text);
    font-size: 1.9rem;
    font-weight: 850;
    line-height: 1.05;
}

.cash-empty-movements {
    color: var(--color-text-muted);
    font-size: 0.9rem;
}

.cash-movements-list {
    display: grid;
    gap: 0.42rem;
}

.cash-movement-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.6rem;
    border-top: 1px solid color-mix(in srgb, var(--color-border) 70%, transparent);
    padding: 0.58rem 0;
}

.cash-movement-row:first-child {
    border-top: 0;
    padding-top: 0.1rem;
}

.cash-movement-left {
    display: inline-flex;
    align-items: center;
    gap: 0.42rem;
    flex-wrap: wrap;
    min-width: 0;
}

.cash-movement-time {
    color: var(--color-text-muted);
    font-size: 0.88rem;
}

.cash-movement-desc {
    color: var(--color-text-muted);
    font-size: 0.9rem;
}

.cash-movement-row strong {
    color: var(--color-text);
    font-size: 0.95rem;
}

.cash-movement-row strong.is-positive {
    color: var(--color-success);
}

.cash-movement-row strong.is-negative {
    color: var(--color-danger);
}

.cash-history-title-wrap {
    padding: 0.78rem 0.95rem;
    border-bottom: 1px solid color-mix(in srgb, var(--color-border) 70%, transparent);
}

.cash-history-title {
    margin: 0;
    color: var(--color-text);
    font-size: 1.34rem;
    font-weight: 800;
}

.history-diff {
    color: var(--color-text);
}

.history-diff.is-positive {
    color: var(--color-success);
}

.history-diff.is-negative {
    color: var(--color-danger);
}

.cash-open-time-chip {
    border-radius: var(--radius-sm);
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 50%, transparent);
    background: color-mix(in srgb, var(--color-bg-elevated) 82%, var(--color-bg-surface));
    color: var(--color-text-muted);
    font-size: 0.9rem;
    padding: 0.55rem 0.75rem;
}

.cash-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
}

@media (max-width: 1200px) {
    .cash-kpis-grid,
    .cash-payment-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 820px) {
    .cash-kpis-grid,
    .cash-payment-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    .cash-selected-actions {
        width: 100%;
    }
}
</style>
