<script setup>
import { ArrowLeft, CreditCard, EllipsisVertical, PencilLine, Percent, Plus, Settings2, Trash2 } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import api from '../../lib/api';
import { formatPercent } from '../../lib/format';
import SettingsPageHeader from '../../components/settings/SettingsPageHeader.vue';
import SettingsTableCard from '../../components/settings/SettingsTableCard.vue';
import SettingsEmptyState from '../../components/settings/SettingsEmptyState.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppTable from '../../components/ui/AppTable.vue';
import AppBadge from '../../components/ui/AppBadge.vue';

const acquirers = ref([]);
const terminals = ref([]);
const rates = ref([]);
const selectedAcquirer = ref(null);
const selectedTerminal = ref(null);
const rateFilter = ref('debito');
const acquirerActionMenuOpenId = ref(null);
const terminalActionMenuOpenId = ref(null);
const rateActionMenuOpenId = ref(null);
const TERMINAL_DETAIL_TAB = Object.freeze({
    GENERAL: 'general',
    RATES: 'rates',
    TEF: 'tef',
});
const activeTerminalDetailTab = ref(TERMINAL_DETAIL_TAB.GENERAL);

const acquirerDialog = ref(false);
const terminalDialog = ref(false);
const rateDialog = ref(false);

const editingAcquirerId = ref(null);
const editingTerminalId = ref(null);
const editingRateId = ref(null);

const limites = reactive({
    credito_administradora: '',
    credito_lojista: '',
});
const permiteLojista = ref(false);

const tef = reactive({
    id: '',
    tipo_integracao: 'discado',
    diretorio_gerenciador: '',
    diretorio_envio: '',
    diretorio_retorno: '',
    enviar_rede: false,
    enviar_cnc: false,
    v700: false,
    ativo: true,
});

const acquirerForm = reactive({
    nome: '',
    cnpj: '',
    ativo: true,
    observacoes: '',
});

const terminalForm = reactive({
    tipo: 'POS',
    estacao: '1',
    descricao: '',
    formula: 'resto_primeira',
});

const rateForm = reactive({
    tipo_credito: 'debito',
    taxa_operadora: '0',
    recebe_em: '1',
    parc_inicial: '1',
    parc_final: '1',
    parc_sugerida: '1',
    parc_maximo: '1',
    ativo: true,
});

const filteredRates = computed(() => rates.value.filter((item) => item.tipo_credito === rateFilter.value));

function resetAcquirerForm() {
    acquirerForm.nome = '';
    acquirerForm.cnpj = '';
    acquirerForm.ativo = true;
    acquirerForm.observacoes = '';
}

function resetTerminalForm() {
    terminalForm.tipo = 'POS';
    terminalForm.estacao = '1';
    terminalForm.descricao = '';
    terminalForm.formula = 'resto_primeira';
}

function resetRateForm() {
    rateForm.tipo_credito = rateFilter.value;
    rateForm.taxa_operadora = '0';
    rateForm.recebe_em = '1';
    rateForm.parc_inicial = '1';
    rateForm.parc_final = '1';
    rateForm.parc_sugerida = '1';
    rateForm.parc_maximo = limites[rateFilter.value] || '1';
    rateForm.ativo = true;
}

function resetTef() {
    tef.id = '';
    tef.tipo_integracao = 'discado';
    tef.diretorio_gerenciador = '';
    tef.diretorio_envio = '';
    tef.diretorio_retorno = '';
    tef.enviar_rede = false;
    tef.enviar_cnc = false;
    tef.v700 = false;
    tef.ativo = true;
}

async function loadAcquirers() {
    const { data } = await api.get('/acquirers');
    acquirers.value = data;
}

async function loadTerminals() {
    if (!selectedAcquirer.value) {
        terminals.value = [];
        return;
    }

    const { data } = await api.get(`/acquirers/${selectedAcquirer.value.id}/terminals`);
    terminals.value = data;
}

async function loadRates() {
    if (!selectedTerminal.value) {
        rates.value = [];
        return;
    }

    const { data } = await api.get(`/terminals/${selectedTerminal.value.id}/rates`);
    rates.value = data;

    const adminRate = data.find((item) => item.tipo_credito === 'credito_administradora' && item.parc_maximo);
    const lojistaRate = data.find((item) => item.tipo_credito === 'credito_lojista' && item.parc_maximo);

    limites.credito_administradora = adminRate?.parc_maximo ? String(adminRate.parc_maximo) : '';
    limites.credito_lojista = lojistaRate?.parc_maximo ? String(lojistaRate.parc_maximo) : '';
    permiteLojista.value = !!limites.credito_lojista;
}

async function loadTef() {
    if (!selectedTerminal.value) {
        resetTef();
        return;
    }

    const { data } = await api.get(`/terminals/${selectedTerminal.value.id}/tef`);

    if (!data) {
        resetTef();
        return;
    }

    tef.id = data.id;
    tef.tipo_integracao = data.tipo_integracao || 'discado';
    tef.diretorio_gerenciador = data.diretorio_gerenciador || '';
    tef.diretorio_envio = data.diretorio_envio || '';
    tef.diretorio_retorno = data.diretorio_retorno || '';
    tef.enviar_rede = !!data.enviar_rede;
    tef.enviar_cnc = !!data.enviar_cnc;
    tef.v700 = !!data.v700;
    tef.ativo = !!data.ativo;
}

function openNewAcquirer() {
    editingAcquirerId.value = null;
    resetAcquirerForm();
    acquirerDialog.value = true;
}

function openEditAcquirer(item) {
    editingAcquirerId.value = item.id;
    acquirerForm.nome = item.nome;
    acquirerForm.cnpj = item.cnpj || '';
    acquirerForm.ativo = !!item.ativo;
    acquirerForm.observacoes = item.observacoes || '';
    acquirerDialog.value = true;
}

function openAcquirerTerminals(item) {
    selectedAcquirer.value = item;
    acquirerActionMenuOpenId.value = null;
}

function toggleAcquirerActionMenu(acquirerId) {
    const normalizedId = String(acquirerId || '');
    acquirerActionMenuOpenId.value = acquirerActionMenuOpenId.value === normalizedId ? null : normalizedId;
}

function closeAcquirerActionMenu() {
    acquirerActionMenuOpenId.value = null;
}

function openTerminalDetails(item) {
    selectedTerminal.value = item;
    terminalActionMenuOpenId.value = null;
}

function toggleTerminalActionMenu(terminalId) {
    const normalizedId = String(terminalId || '');
    terminalActionMenuOpenId.value = terminalActionMenuOpenId.value === normalizedId ? null : normalizedId;
}

function closeTerminalActionMenu() {
    terminalActionMenuOpenId.value = null;
}

function toggleRateActionMenu(rateId) {
    const normalizedId = String(rateId || '');
    rateActionMenuOpenId.value = rateActionMenuOpenId.value === normalizedId ? null : normalizedId;
}

function closeRateActionMenu() {
    rateActionMenuOpenId.value = null;
}

function setTerminalDetailTab(tabId) {
    if (!Object.values(TERMINAL_DETAIL_TAB).includes(tabId)) {
        activeTerminalDetailTab.value = TERMINAL_DETAIL_TAB.GENERAL;
        return;
    }
    activeTerminalDetailTab.value = tabId;
}

function handleEditAcquirerFromMenu(item) {
    closeAcquirerActionMenu();
    openEditAcquirer(item);
}

async function handleRemoveAcquirerFromMenu(item) {
    closeAcquirerActionMenu();
    await removeAcquirer(item);
}

function handleEditTerminalFromMenu(item) {
    closeTerminalActionMenu();
    openEditTerminal(item);
}

async function handleRemoveTerminalFromMenu(item) {
    closeTerminalActionMenu();
    await removeTerminal(item);
}

function handleEditRateFromMenu(item) {
    closeRateActionMenu();
    openEditRate(item);
}

async function handleRemoveRateFromMenu(item) {
    closeRateActionMenu();
    await removeRate(item);
}

function handleWindowClick(event) {
    const target = event?.target;
    if (!(target instanceof Element)) return;
    if (!target.closest('.acquirer-actions-menu')) {
        closeAcquirerActionMenu();
    }
    if (!target.closest('.terminal-actions-menu')) {
        closeTerminalActionMenu();
    }
    if (!target.closest('.rate-actions-menu')) {
        closeRateActionMenu();
    }
}

async function saveAcquirer() {
    const payload = {
        nome: acquirerForm.nome,
        cnpj: acquirerForm.cnpj || null,
        ativo: acquirerForm.ativo,
        observacoes: acquirerForm.observacoes || null,
    };

    if (editingAcquirerId.value) {
        await api.put(`/acquirers/${editingAcquirerId.value}`, payload);
    } else {
        await api.post('/acquirers', payload);
    }

    acquirerDialog.value = false;
    await loadAcquirers();
}

async function removeAcquirer(item) {
    if (!window.confirm(`Excluir adquirente \"${item.nome}\"?`)) return;
    await api.delete(`/acquirers/${item.id}`);

    if (selectedAcquirer.value?.id === item.id) {
        selectedAcquirer.value = null;
        selectedTerminal.value = null;
    }

    await loadAcquirers();
}

function openNewTerminal() {
    editingTerminalId.value = null;
    resetTerminalForm();
    terminalDialog.value = true;
}

function openEditTerminal(item) {
    editingTerminalId.value = item.id;
    terminalForm.tipo = item.tipo || 'POS';
    terminalForm.estacao = String(item.estacao || 1);
    terminalForm.descricao = item.descricao || '';
    terminalForm.formula = item.formula || 'resto_primeira';
    terminalDialog.value = true;
}

async function saveTerminal() {
    const payload = {
        tipo: terminalForm.tipo,
        estacao: Number(terminalForm.estacao || 1),
        descricao: terminalForm.descricao || null,
        formula: terminalForm.formula,
    };

    if (editingTerminalId.value) {
        await api.put(`/terminals/${editingTerminalId.value}`, payload);
    } else {
        await api.post(`/acquirers/${selectedAcquirer.value.id}/terminals`, payload);
    }

    terminalDialog.value = false;
    await loadTerminals();
}

async function removeTerminal(item) {
    if (!window.confirm('Excluir terminal?')) return;
    await api.delete(`/terminals/${item.id}`);

    if (selectedTerminal.value?.id === item.id) {
        selectedTerminal.value = null;
    }

    await loadTerminals();
    await loadRates();
    await loadTef();
}

function openNewRate() {
    editingRateId.value = null;
    resetRateForm();
    rateDialog.value = true;
}

function openEditRate(item) {
    editingRateId.value = item.id;
    rateForm.tipo_credito = item.tipo_credito;
    rateForm.taxa_operadora = String(item.taxa_operadora || 0);
    rateForm.recebe_em = String(item.recebe_em || 1);
    rateForm.parc_inicial = String(item.parc_inicial || 1);
    rateForm.parc_final = String(item.parc_final || 1);
    rateForm.parc_sugerida = String(item.parc_sugerida || 1);
    rateForm.parc_maximo = String(item.parc_maximo || 1);
    rateForm.ativo = !!item.ativo;
    rateDialog.value = true;
}

async function saveRate() {
    const payload = {
        tipo_credito: rateFilter.value,
        taxa_operadora: Number(rateForm.taxa_operadora || 0),
        recebe_em: Number(rateForm.recebe_em || 1),
        parc_inicial: Number(rateForm.parc_inicial || 1),
        parc_final: Number(rateForm.parc_final || 1),
        parc_sugerida: Number(rateForm.parc_sugerida || 1),
        parc_maximo: Number((rateFilter.value === 'debito' ? rateForm.parc_maximo : (limites[rateFilter.value] || rateForm.parc_maximo)) || 1),
        ativo: rateForm.ativo,
    };

    if (editingRateId.value) {
        await api.put(`/rates/${editingRateId.value}`, payload);
    } else {
        await api.post(`/terminals/${selectedTerminal.value.id}/rates`, payload);
    }

    rateDialog.value = false;
    await loadRates();
}

async function removeRate(item) {
    if (!window.confirm('Excluir taxa?')) return;
    await api.delete(`/rates/${item.id}`);
    await loadRates();
}

async function saveLimits() {
    const types = ['credito_administradora'];
    if (permiteLojista.value) types.push('credito_lojista');

    for (const type of types) {
        const max = Number(limites[type] || 0);
        if (!max) continue;

        const { data } = await api.get(`/terminals/${selectedTerminal.value.id}/rates?tipo_credito=${type}`);

        for (const row of data) {
            await api.put(`/rates/${row.id}`, {
                tipo_credito: row.tipo_credito,
                taxa_operadora: Number(row.taxa_operadora || 0),
                recebe_em: Number(row.recebe_em || 1),
                parc_inicial: Number(row.parc_inicial || 1),
                parc_final: Number(row.parc_final || 1),
                parc_sugerida: Number(row.parc_sugerida || 1),
                parc_maximo: max,
                ativo: !!row.ativo,
            });
        }
    }

    if (!permiteLojista.value) {
        limites.credito_lojista = '';
    }

    await loadRates();
}

async function saveTef() {
    const payload = {
        tipo_integracao: tef.tipo_integracao,
        diretorio_gerenciador: tef.diretorio_gerenciador || null,
        diretorio_envio: tef.diretorio_envio || null,
        diretorio_retorno: tef.diretorio_retorno || null,
        enviar_rede: tef.enviar_rede,
        enviar_cnc: tef.enviar_cnc,
        v700: tef.v700,
        ativo: tef.ativo,
        provedor: tef.tipo_integracao,
    };

    await api.put(`/terminals/${selectedTerminal.value.id}/tef`, payload);
    await loadTef();
}

async function removeTef() {
    if (!tef.id) return;
    if (!window.confirm('Excluir integração TEF?')) return;

    await api.delete(`/tef/${tef.id}`);
    resetTef();
}

watch(selectedAcquirer, async () => {
    selectedTerminal.value = null;
    closeTerminalActionMenu();
    activeTerminalDetailTab.value = TERMINAL_DETAIL_TAB.GENERAL;
    await loadTerminals();
});

watch(selectedTerminal, async () => {
    closeTerminalActionMenu();
    closeRateActionMenu();
    activeTerminalDetailTab.value = TERMINAL_DETAIL_TAB.GENERAL;
    await loadRates();
    await loadTef();
});

onMounted(() => {
    loadAcquirers();
    window.addEventListener('click', handleWindowClick);
});

onBeforeUnmount(() => {
    window.removeEventListener('click', handleWindowClick);
});
</script>

<template>
    <div class="acquirers-page space-y-4">
        <template v-if="!selectedAcquirer">
            <SettingsPageHeader title="Adquirentes" subtitle="Gerencie adquirentes e terminais.">
                <template #actions>
                    <AppButton @click="openNewAcquirer">
                        <Plus class="h-4 w-4" aria-hidden="true" />
                        Novo adquirente
                    </AppButton>
                </template>
            </SettingsPageHeader>

            <SettingsTableCard>
                <AppTable>
                    <thead>
                        <tr>
                            <th class="text-left">Nome</th>
                            <th class="text-left">CNPJ</th>
                            <th class="text-center">Status</th>
                            <th class="text-left">Observações</th>
                            <th class="text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="acquirers.length === 0">
                            <td colspan="5" class="p-0">
                                <SettingsEmptyState
                                    title="Nenhum adquirente cadastrado"
                                    description="Cadastre um adquirente para configurar terminais, taxas e TEF."
                                >
                                    <template #actions>
                                        <AppButton @click="openNewAcquirer">
                                            <Plus class="h-4 w-4" aria-hidden="true" />
                                            Cadastrar adquirente
                                        </AppButton>
                                    </template>
                                </SettingsEmptyState>
                            </td>
                        </tr>
                        <tr v-for="item in acquirers" :key="item.id">
                            <td class="font-semibold text-main">
                                <button class="text-left hover:text-emerald-700" @click="selectedAcquirer = item">{{ item.nome }}</button>
                            </td>
                            <td class="text-muted">{{ item.cnpj || '—' }}</td>
                            <td class="text-center">
                                <AppBadge :variant="item.ativo ? 'success' : 'default'">
                                    {{ item.ativo ? 'Ativo' : 'Inativo' }}
                                </AppBadge>
                            </td>
                            <td class="text-muted">{{ item.observacoes || '—' }}</td>
                            <td class="text-right">
                                <div class="acquirer-row-actions">
                                    <div class="acquirer-actions-menu" @click.stop>
                                        <AppButton
                                            variant="ghost"
                                            class="acquirer-actions-trigger"
                                            @click.stop="toggleAcquirerActionMenu(item.id)"
                                        >
                                            <EllipsisVertical class="h-4 w-4" aria-hidden="true" />
                                        </AppButton>

                                        <div v-if="acquirerActionMenuOpenId === String(item.id)" class="acquirer-actions-panel">
                                            <button type="button" class="acquirer-actions-item" @click="openAcquirerTerminals(item)">
                                                <CreditCard class="h-4 w-4" aria-hidden="true" />
                                                Editar terminais de cartão
                                            </button>
                                            <button type="button" class="acquirer-actions-item" @click="handleEditAcquirerFromMenu(item)">
                                                <PencilLine class="h-4 w-4" aria-hidden="true" />
                                                Editar adquirente
                                            </button>
                                            <button type="button" class="acquirer-actions-item is-danger" @click="handleRemoveAcquirerFromMenu(item)">
                                                <Trash2 class="h-4 w-4" aria-hidden="true" />
                                                Excluir adquirente
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </AppTable>
            </SettingsTableCard>
        </template>

        <template v-else-if="selectedAcquirer && !selectedTerminal">
            <AppButton variant="ghost" @click="selectedAcquirer = null">
                <ArrowLeft class="h-4 w-4" aria-hidden="true" />
                Voltar
            </AppButton>

            <SettingsPageHeader :title="selectedAcquirer.nome" subtitle="Cadastro de terminais.">
                <template #actions>
                    <AppButton @click="openNewTerminal">
                        <Plus class="h-4 w-4" aria-hidden="true" />
                        Novo terminal
                    </AppButton>
                </template>
            </SettingsPageHeader>

            <SettingsTableCard>
                <AppTable>
                    <thead>
                        <tr>
                            <th class="text-left">Tipo</th>
                            <th class="text-left">Estação</th>
                            <th class="text-left">Descrição</th>
                            <th class="text-left">Fórmula</th>
                            <th class="text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="terminals.length === 0">
                            <td colspan="5" class="p-0">
                                <SettingsEmptyState
                                    title="Nenhum terminal cadastrado"
                                    description="Cadastre um terminal para configurar taxas e integração TEF."
                                >
                                    <template #actions>
                                        <AppButton @click="openNewTerminal">
                                            <Plus class="h-4 w-4" aria-hidden="true" />
                                            Cadastrar terminal
                                        </AppButton>
                                    </template>
                                </SettingsEmptyState>
                            </td>
                        </tr>
                        <tr v-for="item in terminals" :key="item.id">
                            <td class="font-semibold text-main">{{ item.tipo }}</td>
                            <td class="text-muted">{{ item.estacao }}</td>
                            <td class="text-muted">{{ item.descricao || `Estação ${item.estacao}` }}</td>
                            <td class="text-muted">{{ item.formula }}</td>
                            <td class="text-right">
                                <div class="terminal-row-actions">
                                    <div class="terminal-actions-menu" @click.stop>
                                        <AppButton
                                            variant="ghost"
                                            class="terminal-actions-trigger"
                                            @click.stop="toggleTerminalActionMenu(item.id)"
                                        >
                                            <EllipsisVertical class="h-4 w-4" aria-hidden="true" />
                                        </AppButton>

                                        <div v-if="terminalActionMenuOpenId === String(item.id)" class="terminal-actions-panel">
                                            <button type="button" class="terminal-actions-item" @click="openTerminalDetails(item)">
                                                <Settings2 class="h-4 w-4" aria-hidden="true" />
                                                Configurar terminal
                                            </button>
                                            <button type="button" class="terminal-actions-item" @click="handleEditTerminalFromMenu(item)">
                                                <PencilLine class="h-4 w-4" aria-hidden="true" />
                                                Editar terminal
                                            </button>
                                            <button type="button" class="terminal-actions-item is-danger" @click="handleRemoveTerminalFromMenu(item)">
                                                <Trash2 class="h-4 w-4" aria-hidden="true" />
                                                Excluir terminal
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </AppTable>
            </SettingsTableCard>
        </template>

        <template v-else>
            <div class="space-y-4">
                <AppButton variant="ghost" @click="selectedTerminal = null">
                    <ArrowLeft class="h-4 w-4" aria-hidden="true" />
                    Voltar para terminais
                </AppButton>

                <SettingsPageHeader
                    :title="`Terminal ${selectedTerminal.descricao || selectedTerminal.estacao}`"
                    :subtitle="`${selectedAcquirer.nome} • ${selectedTerminal.tipo}`"
                />

                <div class="terminal-top-tabs" role="tablist" aria-label="Abas de configuração do terminal">
                    <button
                        type="button"
                        class="terminal-tab-btn"
                        :class="{ 'is-active': activeTerminalDetailTab === TERMINAL_DETAIL_TAB.GENERAL }"
                        @click="setTerminalDetailTab(TERMINAL_DETAIL_TAB.GENERAL)"
                    >
                        <Settings2 class="h-4 w-4" aria-hidden="true" />
                        Dados gerais
                    </button>
                    <button
                        type="button"
                        class="terminal-tab-btn"
                        :class="{ 'is-active': activeTerminalDetailTab === TERMINAL_DETAIL_TAB.RATES }"
                        @click="setTerminalDetailTab(TERMINAL_DETAIL_TAB.RATES)"
                    >
                        <Percent class="h-4 w-4" aria-hidden="true" />
                        Taxas
                    </button>
                    <button
                        type="button"
                        class="terminal-tab-btn"
                        :class="{ 'is-active': activeTerminalDetailTab === TERMINAL_DETAIL_TAB.TEF }"
                        @click="setTerminalDetailTab(TERMINAL_DETAIL_TAB.TEF)"
                    >
                        <CreditCard class="h-4 w-4" aria-hidden="true" />
                        Integração TEF
                    </button>
                </div>

                <section v-if="activeTerminalDetailTab === TERMINAL_DETAIL_TAB.GENERAL" class="terminal-tab-panel ui-card">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold text-slate-900">Dados gerais do terminal</h3>
                        <AppButton variant="ghost" @click="openEditTerminal(selectedTerminal)">
                            <PencilLine class="h-4 w-4" aria-hidden="true" />
                            Editar
                        </AppButton>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div><span class="text-slate-500">Tipo:</span> {{ selectedTerminal.tipo }}</div>
                        <div><span class="text-slate-500">Estação:</span> {{ selectedTerminal.estacao }}</div>
                        <div class="md:col-span-2"><span class="text-slate-500">Descrição:</span> {{ selectedTerminal.descricao || '—' }}</div>
                        <div class="md:col-span-2"><span class="text-slate-500">Fórmula:</span> {{ selectedTerminal.formula }}</div>
                    </div>

                    <div class="pt-4 border-t border-slate-200 space-y-3">
                        <h4 class="font-medium text-slate-900">Limites de parcelamento</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm font-medium">Parcelado pela administradora</label>
                                <input v-model="limites.credito_administradora" type="number" min="1" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                            </div>
                            <div class="flex items-end">
                                <label class="flex items-center gap-2 text-sm">
                                    <input v-model="permiteLojista" type="checkbox" /> Permite parcelamento pelo lojista
                                </label>
                            </div>
                            <div v-if="permiteLojista">
                                <label class="text-sm font-medium">Parcelado pelo lojista</label>
                                <input v-model="limites.credito_lojista" type="number" min="1" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                            </div>
                        </div>

                        <AppButton variant="secondary" @click="saveLimits">Salvar limites</AppButton>
                    </div>
                </section>

                <section v-if="activeTerminalDetailTab === TERMINAL_DETAIL_TAB.RATES" class="terminal-tab-panel ui-card">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold text-slate-900">Taxas</h3>
                        <AppButton @click="openNewRate">
                            <Plus class="h-4 w-4" aria-hidden="true" />
                            Nova taxa
                        </AppButton>
                    </div>

                    <div class="terminal-rate-tabs">
                        <button class="terminal-rate-tab-btn" :class="{ 'is-active': rateFilter === 'debito' }" @click="rateFilter = 'debito'">Débito</button>
                        <button
                            class="terminal-rate-tab-btn"
                            :class="{ 'is-active': rateFilter === 'credito_administradora' }"
                            @click="rateFilter = 'credito_administradora'"
                        >
                            Crédito pela administradora
                        </button>
                        <button
                            v-if="permiteLojista"
                            class="terminal-rate-tab-btn"
                            :class="{ 'is-active': rateFilter === 'credito_lojista' }"
                            @click="rateFilter = 'credito_lojista'"
                        >
                            Crédito pelo lojista
                        </button>
                    </div>

                    <SettingsTableCard>
                        <AppTable>
                            <thead>
                                <tr>
                                    <th class="text-left">Parcela inicial</th>
                                    <th class="text-left">Parcela final</th>
                                    <th class="text-left">Taxa</th>
                                    <th class="text-left">Recebe em</th>
                                    <th class="text-left">Status</th>
                                    <th class="text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="filteredRates.length === 0">
                                    <td colspan="6" class="text-center text-muted py-6">Nenhuma taxa cadastrada para este tipo.</td>
                                </tr>
                                <tr v-for="item in filteredRates" :key="item.id">
                                    <td>{{ item.parc_inicial }}</td>
                                    <td>{{ item.parc_final }}</td>
                                    <td>{{ formatPercent(item.taxa_operadora) }}</td>
                                    <td>{{ item.recebe_em || '—' }} dia(s)</td>
                                    <td>
                                        <AppBadge :variant="item.ativo ? 'success' : 'default'">
                                            {{ item.ativo ? 'Ativo' : 'Inativo' }}
                                        </AppBadge>
                                    </td>
                                    <td class="text-right">
                                        <div class="rate-row-actions">
                                            <div class="rate-actions-menu" @click.stop>
                                                <AppButton
                                                    variant="ghost"
                                                    class="rate-actions-trigger"
                                                    @click.stop="toggleRateActionMenu(item.id)"
                                                >
                                                    <EllipsisVertical class="h-4 w-4" aria-hidden="true" />
                                                </AppButton>

                                                <div v-if="rateActionMenuOpenId === String(item.id)" class="rate-actions-panel">
                                                    <button type="button" class="rate-actions-item" @click="handleEditRateFromMenu(item)">
                                                        <PencilLine class="h-4 w-4" aria-hidden="true" />
                                                        Editar taxa
                                                    </button>
                                                    <button type="button" class="rate-actions-item is-danger" @click="handleRemoveRateFromMenu(item)">
                                                        <Trash2 class="h-4 w-4" aria-hidden="true" />
                                                        Excluir taxa
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </AppTable>
                    </SettingsTableCard>
                </section>

                <section v-if="activeTerminalDetailTab === TERMINAL_DETAIL_TAB.TEF" class="terminal-tab-panel ui-card">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold text-slate-900">Integração TEF</h3>
                        <AppButton v-if="tef.id" variant="danger" @click="removeTef">
                            <Trash2 class="h-4 w-4" aria-hidden="true" />
                            Excluir
                        </AppButton>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-medium">Tipo de integração</label>
                            <select v-model="tef.tipo_integracao" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option value="discado">Discado</option>
                            </select>
                        </div>
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="tef.ativo" type="checkbox" /> Integração ativa
                        </label>
                        <div>
                            <label class="text-sm font-medium">Aplicativo TEF</label>
                            <input v-model="tef.diretorio_gerenciador" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="C:\\TEF\\Gerenciador" />
                        </div>
                        <div>
                            <label class="text-sm font-medium">Pasta envio</label>
                            <input v-model="tef.diretorio_envio" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="C:\\TEF\\Envio" />
                        </div>
                        <div>
                            <label class="text-sm font-medium">Pasta retorno</label>
                            <input v-model="tef.diretorio_retorno" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="C:\\TEF\\Retorno" />
                        </div>
                        <label class="flex items-center gap-2 text-sm"><input v-model="tef.enviar_rede" type="checkbox" /> Comunicação em rede</label>
                        <label class="flex items-center gap-2 text-sm"><input v-model="tef.enviar_cnc" type="checkbox" /> Envio CNC</label>
                        <label class="flex items-center gap-2 text-sm"><input v-model="tef.v700" type="checkbox" /> Compatibilidade V700</label>
                    </div>

                    <AppButton @click="saveTef">
                        <Settings2 class="h-4 w-4" aria-hidden="true" />
                        Salvar TEF
                    </AppButton>
                </section>
            </div>
        </template>

        <div v-if="acquirerDialog" class="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50">
            <div class="w-full max-w-lg rounded-xl bg-white p-5 space-y-4">
                <h3 class="text-lg font-bold text-slate-900">{{ editingAcquirerId ? 'Editar Adquirente' : 'Novo Adquirente' }}</h3>
                <div>
                    <label class="text-sm font-medium">Nome</label>
                    <input v-model="acquirerForm.nome" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-sm font-medium">CNPJ</label>
                    <input v-model="acquirerForm.cnpj" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                </div>
                <label class="flex items-center gap-2 text-sm"><input v-model="acquirerForm.ativo" type="checkbox" /> Ativo</label>
                <div>
                    <label class="text-sm font-medium">Observações</label>
                    <textarea v-model="acquirerForm.observacoes" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" rows="3" />
                </div>
                <div class="flex justify-end gap-2">
                    <button class="rounded-lg border px-4 py-2 text-sm" @click="acquirerDialog = false">Cancelar</button>
                    <button class="rounded-lg bg-emerald-600 text-white px-4 py-2 text-sm font-semibold" @click="saveAcquirer">Salvar</button>
                </div>
            </div>
        </div>

        <div v-if="terminalDialog" class="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50">
            <div class="w-full max-w-lg rounded-xl bg-white p-5 space-y-4">
                <h3 class="text-lg font-bold text-slate-900">{{ editingTerminalId ? 'Editar Terminal' : 'Novo Terminal' }}</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium">Tipo</label>
                        <select v-model="terminalForm.tipo" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="POS">POS</option>
                            <option value="TEF">TEF</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium">Estação</label>
                        <input v-model="terminalForm.estacao" type="number" min="1" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-sm font-medium">Descrição</label>
                        <input v-model="terminalForm.descricao" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-sm font-medium">Fórmula</label>
                        <select v-model="terminalForm.formula" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="resto_primeira">Resto na primeira</option>
                            <option value="resto_ultima">Resto na última</option>
                            <option value="arredonda_cima">Arredonda para cima</option>
                            <option value="arredonda_baixo">Arredonda para baixo</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <button class="rounded-lg border px-4 py-2 text-sm" @click="terminalDialog = false">Cancelar</button>
                    <button class="rounded-lg bg-emerald-600 text-white px-4 py-2 text-sm font-semibold" @click="saveTerminal">Salvar</button>
                </div>
            </div>
        </div>

        <div v-if="rateDialog" class="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50">
            <div class="w-full max-w-lg rounded-xl bg-white p-5 space-y-4">
                <h3 class="text-lg font-bold text-slate-900">{{ editingRateId ? 'Editar Taxa' : 'Nova Taxa' }}</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium">Taxa (%)</label>
                        <input v-model="rateForm.taxa_operadora" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="text-sm font-medium">Recebe em (dias)</label>
                        <input v-model="rateForm.recebe_em" type="number" min="1" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div v-if="rateFilter !== 'debito'">
                        <label class="text-sm font-medium">Parcela inicial</label>
                        <input v-model="rateForm.parc_inicial" type="number" min="1" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div v-if="rateFilter !== 'debito'">
                        <label class="text-sm font-medium">Parcela final</label>
                        <input v-model="rateForm.parc_final" type="number" min="1" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div v-if="rateFilter !== 'debito'">
                        <label class="text-sm font-medium">Parcela sugerida</label>
                        <input v-model="rateForm.parc_sugerida" type="number" min="1" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="text-sm font-medium">Máx. parcelas</label>
                        <input v-model="rateForm.parc_maximo" type="number" min="1" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" :disabled="rateFilter !== 'debito'" />
                    </div>
                    <label class="flex items-center gap-2 text-sm md:col-span-2"><input v-model="rateForm.ativo" type="checkbox" /> Ativo</label>
                </div>
                <div class="flex justify-end gap-2">
                    <button class="rounded-lg border px-4 py-2 text-sm" @click="rateDialog = false">Cancelar</button>
                    <button class="rounded-lg bg-emerald-600 text-white px-4 py-2 text-sm font-semibold" @click="saveRate">Salvar</button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.text-slate-900 {
    color: var(--color-text) !important;
}

.text-slate-700,
.text-slate-600,
.text-slate-500 {
    color: var(--color-text-muted) !important;
}

.text-blue-600 {
    color: var(--color-primary) !important;
}

.text-red-600 {
    color: var(--color-danger) !important;
}

.text-emerald-700 {
    color: var(--color-success) !important;
}

.text-white {
    color: var(--color-text-inverse) !important;
}

.bg-white {
    background-color: var(--color-bg-surface) !important;
}

.bg-slate-50,
.bg-slate-200 {
    background-color: var(--color-bg-elevated) !important;
}

.bg-emerald-600 {
    background-color: var(--color-primary) !important;
}

.bg-emerald-100 {
    background-color: color-mix(in srgb, var(--color-success) 22%, var(--color-bg-surface)) !important;
}

.border-slate-100,
.border-slate-200,
.border-slate-300 {
    border-color: var(--color-border) !important;
}

.hover\:text-emerald-700:hover {
    color: var(--color-primary-hover) !important;
}

.acquirer-row-actions {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.acquirer-actions-menu {
    position: relative;
}

.acquirer-actions-trigger {
    min-width: 2.3rem;
    display: inline-flex;
    justify-content: center;
    align-items: center;
}

.acquirer-actions-panel {
    position: absolute;
    top: calc(100% + 0.35rem);
    right: 0;
    z-index: var(--z-dropdown);
    min-width: 13.5rem;
    display: grid;
    gap: 0.2rem;
    border-radius: var(--radius-sm);
    border: 1px solid var(--color-border);
    background: var(--color-bg-surface);
    padding: 0.35rem;
    box-shadow: var(--shadow-md);
}

.acquirer-actions-item {
    width: 100%;
    border: 1px solid transparent;
    border-radius: var(--radius-sm);
    background: transparent;
    color: var(--color-text);
    text-align: left;
    padding: 0.5rem 0.55rem;
    display: inline-flex;
    align-items: center;
    gap: 0.42rem;
    font-size: 0.82rem;
    font-weight: 600;
    transition: all var(--transition-fast);
}

.acquirer-actions-item:hover {
    border-color: color-mix(in srgb, var(--color-primary) 42%, transparent);
    background: color-mix(in srgb, var(--color-primary) 11%, var(--color-bg-surface));
}

.acquirer-actions-item.is-danger {
    color: var(--color-danger);
}

.acquirer-actions-item.is-danger:hover {
    border-color: color-mix(in srgb, var(--color-danger) 44%, transparent);
    background: color-mix(in srgb, var(--color-danger) 12%, var(--color-bg-surface));
}

.terminal-row-actions {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.terminal-actions-menu {
    position: relative;
}

.terminal-actions-trigger {
    min-width: 2.3rem;
    display: inline-flex;
    justify-content: center;
    align-items: center;
}

.terminal-actions-panel {
    position: absolute;
    top: calc(100% + 0.35rem);
    right: 0;
    z-index: var(--z-dropdown);
    min-width: 13.5rem;
    display: grid;
    gap: 0.2rem;
    border-radius: var(--radius-sm);
    border: 1px solid var(--color-border);
    background: var(--color-bg-surface);
    padding: 0.35rem;
    box-shadow: var(--shadow-md);
}

.terminal-actions-item {
    width: 100%;
    border: 1px solid transparent;
    border-radius: var(--radius-sm);
    background: transparent;
    color: var(--color-text);
    text-align: left;
    padding: 0.5rem 0.55rem;
    display: inline-flex;
    align-items: center;
    gap: 0.42rem;
    font-size: 0.82rem;
    font-weight: 600;
    transition: all var(--transition-fast);
}

.terminal-actions-item:hover {
    border-color: color-mix(in srgb, var(--color-primary) 42%, transparent);
    background: color-mix(in srgb, var(--color-primary) 11%, var(--color-bg-surface));
}

.terminal-actions-item.is-danger {
    color: var(--color-danger);
}

.terminal-actions-item.is-danger:hover {
    border-color: color-mix(in srgb, var(--color-danger) 44%, transparent);
    background: color-mix(in srgb, var(--color-danger) 12%, var(--color-bg-surface));
}

.rate-row-actions {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.rate-actions-menu {
    position: relative;
}

.rate-actions-trigger {
    min-width: 2.3rem;
    display: inline-flex;
    justify-content: center;
    align-items: center;
}

.rate-actions-panel {
    position: absolute;
    top: calc(100% + 0.35rem);
    right: 0;
    z-index: var(--z-dropdown);
    min-width: 12.5rem;
    display: grid;
    gap: 0.2rem;
    border-radius: var(--radius-sm);
    border: 1px solid var(--color-border);
    background: var(--color-bg-surface);
    padding: 0.35rem;
    box-shadow: var(--shadow-md);
}

.rate-actions-item {
    width: 100%;
    border: 1px solid transparent;
    border-radius: var(--radius-sm);
    background: transparent;
    color: var(--color-text);
    text-align: left;
    padding: 0.5rem 0.55rem;
    display: inline-flex;
    align-items: center;
    gap: 0.42rem;
    font-size: 0.82rem;
    font-weight: 600;
    transition: all var(--transition-fast);
}

.rate-actions-item:hover {
    border-color: color-mix(in srgb, var(--color-primary) 42%, transparent);
    background: color-mix(in srgb, var(--color-primary) 11%, var(--color-bg-surface));
}

.rate-actions-item.is-danger {
    color: var(--color-danger);
}

.rate-actions-item.is-danger:hover {
    border-color: color-mix(in srgb, var(--color-danger) 44%, transparent);
    background: color-mix(in srgb, var(--color-danger) 12%, var(--color-bg-surface));
}

.terminal-top-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.terminal-tab-btn {
    border-radius: 999px;
    border: 1px solid var(--color-border);
    background: color-mix(in srgb, var(--color-bg-elevated) 82%, var(--color-bg-surface));
    color: var(--color-text-muted);
    padding: 0.5rem 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 0.38rem;
    font-size: 0.82rem;
    font-weight: 700;
    transition: all var(--transition-fast);
}

.terminal-tab-btn:hover {
    border-color: color-mix(in srgb, var(--color-primary) 52%, transparent);
    color: var(--color-text);
}

.terminal-tab-btn.is-active {
    border-color: color-mix(in srgb, var(--color-primary) 58%, transparent);
    background: color-mix(in srgb, var(--color-primary) 16%, var(--color-bg-surface));
    color: var(--color-text);
}

.terminal-tab-panel {
    padding: 1rem;
    display: grid;
    gap: 0.9rem;
}

.terminal-rate-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 0.45rem;
}

.terminal-rate-tab-btn {
    border-radius: 999px;
    border: 1px solid var(--color-border);
    background: color-mix(in srgb, var(--color-bg-elevated) 85%, var(--color-bg-surface));
    color: var(--color-text-muted);
    padding: 0.38rem 0.8rem;
    font-size: 0.8rem;
    font-weight: 700;
    transition: all var(--transition-fast);
}

.terminal-rate-tab-btn:hover {
    border-color: color-mix(in srgb, var(--color-primary) 48%, transparent);
    color: var(--color-text);
}

.terminal-rate-tab-btn.is-active {
    border-color: color-mix(in srgb, var(--color-primary) 56%, transparent);
    background: color-mix(in srgb, var(--color-primary) 18%, var(--color-bg-surface));
    color: var(--color-text);
}

.acquirers-page :deep(.ui-table-wrap) {
    overflow: visible;
}
</style>
