<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { BriefcaseBusiness, Building2, FileText, Monitor, Save, ShieldCheck, Store, UtensilsCrossed } from 'lucide-vue-next';
import api from '../../lib/api';
import SettingsPageHeader from '../../components/settings/SettingsPageHeader.vue';
import SettingsSectionCard from '../../components/settings/SettingsSectionCard.vue';
import AppInput from '../../components/ui/AppInput.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppToast from '../../components/ui/AppToast.vue';
import AppCard from '../../components/ui/AppCard.vue';

const loading = ref(false);
const saving = ref(false);
const message = ref('');
const isManagedByErp = ref(false);
const activeTab = ref('empresa');
const selectedTerminalType = ref('varejo');
const certificateFileInput = ref(null);

const STORAGE_KEY = 'simples_pdv_terminal_type';

const company = reactive({
    cnpj: '',
    razao_social: '',
    nome_fantasia: '',
    inscricao_estadual: '',
    inscricao_municipal: '',
    regime_tributario: '',
    cnae: '',
    telefone: '',
    email: '',
    cep: '',
    logradouro: '',
    numero: '',
    complemento: '',
    bairro: '',
    cidade: '',
    uf: '',
    pdv_layout_mode: 'varejo',
});

const fiscal = reactive({
    ambiente: 'homologacao',
    serie_nfe: '1',
    serie_nfce: '1',
    proximo_numero_nfe: '1',
    proximo_numero_nfce: '1',
    csc: '',
    id_csc: '',
    emitir_nfce: true,
    emitir_nfe: false,
    impressao_automatica: true,
    notagil_enabled: false,
    notagil_company_id: '',
    notagil_operation_code_nfce: '',
    notagil_operation_code_nfe: '',
});

const certificate = reactive({
    tipo: 'a1',
    validade: '',
    arquivo_nome: '',
    senha_hash: '',
});

const companyTabs = [
    { id: 'empresa', label: 'Empresa', icon: Building2 },
    { id: 'pdv', label: 'PDV', icon: Monitor },
    { id: 'fiscal', label: 'NF-e / NFC-e', icon: FileText },
    { id: 'certificado', label: 'Certificado', icon: ShieldCheck },
];

const terminalOptions = [
    {
        id: 'varejo',
        label: 'Varejo',
        icon: Store,
        description: 'Fluxo completo com grade visual e navegação por categorias.',
        features: ['Grid visual de produtos', 'Navegação por categorias', 'Seleção por clique/toque', 'Ideal para vendas assistidas'],
    },
    {
        id: 'restaurante',
        label: 'Restaurante',
        icon: UtensilsCrossed,
        description: 'Layout para atendimento rápido com foco em balcão e comanda.',
        features: ['Foco em atendimento ágil', 'Visual otimizado para comanda', 'Acesso rápido aos produtos', 'Ideal para operação de balcão'],
    },
    {
        id: 'servicos',
        label: 'Serviços',
        icon: BriefcaseBusiness,
        description: 'Interface enxuta para lançamentos rápidos e finalização objetiva.',
        features: ['Cadastro rápido de itens', 'Fluxo simplificado', 'Tela limpa para atendimento', 'Ideal para prestação de serviços'],
    },
];

const regimeOptions = ['Simples Nacional', 'Lucro Presumido', 'Lucro Real', 'MEI'];

const ufOptions = [
    'AC',
    'AL',
    'AP',
    'AM',
    'BA',
    'CE',
    'DF',
    'ES',
    'GO',
    'MA',
    'MT',
    'MS',
    'MG',
    'PA',
    'PB',
    'PR',
    'PE',
    'PI',
    'RJ',
    'RN',
    'RS',
    'RO',
    'RR',
    'SC',
    'SP',
    'SE',
    'TO',
];

const selectedTerminalLabel = computed(() => {
    return terminalOptions.find((option) => option.id === selectedTerminalType.value)?.label || 'Varejo';
});

function loadTerminalType() {
    if (typeof window === 'undefined') return;
    const persisted = window.localStorage.getItem(STORAGE_KEY);
    const valid = terminalOptions.some((option) => option.id === persisted);
    if (valid) {
        selectedTerminalType.value = persisted;
        company.pdv_layout_mode = persisted;
    }
}

function selectTerminalType(typeId) {
    if (isManagedByErp.value) return;

    selectedTerminalType.value = typeId;
    company.pdv_layout_mode = typeId;
    if (typeof window !== 'undefined') {
        window.localStorage.setItem(STORAGE_KEY, typeId);
    }
}

function notifyLayoutModeChange(mode) {
    if (typeof window === 'undefined') return;

    window.dispatchEvent(
        new CustomEvent('company-layout-mode-updated', {
            detail: { pdv_layout_mode: mode },
        }),
    );
}

function openCertificateFilePicker() {
    if (isManagedByErp.value) return;

    certificateFileInput.value?.click();
}

function onCertificateFileChange(event) {
    const [file] = event?.target?.files || [];
    if (!file) return;
    certificate.arquivo_nome = file.name;
}

function assignReactive(target, source) {
    Object.keys(target).forEach((key) => {
        target[key] = source?.[key] ?? target[key] ?? '';
    });
}

async function load() {
    loading.value = true;
    try {
        const [companyRes, fiscalRes, certRes] = await Promise.all([
            api.get('/settings/company'),
            api.get('/settings/fiscal'),
            api.get('/settings/certificate'),
        ]);

        isManagedByErp.value = Boolean(companyRes.data?.managed_by_erp);

        if (companyRes.data) assignReactive(company, companyRes.data);
        const serverLayoutMode = String(companyRes.data?.pdv_layout_mode || '').trim();
        const hasValidServerLayoutMode = terminalOptions.some((option) => option.id === serverLayoutMode);
        if (hasValidServerLayoutMode) {
            selectedTerminalType.value = serverLayoutMode;
            notifyLayoutModeChange(serverLayoutMode);
        } else {
            company.pdv_layout_mode = selectedTerminalType.value;
            notifyLayoutModeChange(selectedTerminalType.value);
        }

        if (fiscalRes.data) assignReactive(fiscal, fiscalRes.data);
        const certificatePayload = certRes.data?.certificate ?? certRes.data;
        if (certificatePayload) assignReactive(certificate, certificatePayload);
    } finally {
        loading.value = false;
    }
}

async function save() {
    if (isManagedByErp.value) {
        message.value = 'Configurações gerenciadas pelo ERP.';
        return;
    }

    saving.value = true;
    message.value = '';

    try {
        const [companyResult] = await Promise.all([
            api.put('/settings/company', company),
            api.put('/settings/fiscal', fiscal),
            api.put('/settings/certificate', certificate),
        ]);

        const persistedMode = String(companyResult?.data?.pdv_layout_mode || selectedTerminalType.value).trim().toLowerCase();
        const validPersistedMode = terminalOptions.some((option) => option.id === persistedMode) ? persistedMode : selectedTerminalType.value;
        selectedTerminalType.value = validPersistedMode;
        company.pdv_layout_mode = validPersistedMode;
        notifyLayoutModeChange(validPersistedMode);

        if (typeof window !== 'undefined') {
            window.localStorage.setItem(STORAGE_KEY, selectedTerminalType.value);
        }

        message.value = 'Configurações salvas com sucesso.';
    } catch (error) {
        message.value = error?.response?.data?.message ?? 'Falha ao salvar configurações.';
    } finally {
        saving.value = false;
    }
}

onMounted(() => {
    loadTerminalType();
    load();
});
</script>

<template>
    <div class="space-y-4 pb-16">
        <SettingsPageHeader title="Dados da Empresa" subtitle="Cadastro, parâmetros fiscais e certificado digital">
            <template #actions>
                <AppButton :loading="saving || loading" :disabled="isManagedByErp" @click="save">
                    <Save class="h-4 w-4" aria-hidden="true" />
                    {{ isManagedByErp ? 'Gerenciado pelo ERP' : 'Salvar' }}
                </AppButton>
            </template>
        </SettingsPageHeader>

        <AppToast :show="!!message" :tone="message.includes('sucesso') || message.includes('ERP') ? 'success' : 'danger'">
            {{ message }}
        </AppToast>

        <AppCard v-if="isManagedByErp" class="p-4 erp-managed-card">
            <p class="erp-managed-title">Dados carregados da filial ativa no ERP</p>
            <p class="erp-managed-copy">A manutenção de empresa, fiscal e certificado deve ser feita no cadastro do ERP.</p>
        </AppCard>

        <AppCard class="company-tabs-wrap" padding="p-2">
            <div class="company-tabs-grid">
                <button
                    v-for="tab in companyTabs"
                    :key="tab.id"
                    type="button"
                    class="company-tab-btn"
                    :class="{ 'is-active': activeTab === tab.id }"
                    @click="activeTab = tab.id"
                >
                    <component :is="tab.icon" class="h-4 w-4" aria-hidden="true" />
                    <span>{{ tab.label }}</span>
                </button>
            </div>
        </AppCard>

        <AppCard v-if="loading" class="p-8 text-center text-muted">
            Carregando configurações da empresa...
        </AppCard>

        <template v-else-if="activeTab === 'empresa'">
            <fieldset :disabled="isManagedByErp" class="company-managed-fieldset">
                <SettingsSectionCard title="Identificação" subtitle="CNPJ, razão social e regime tributário">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <AppInput v-model="company.cnpj" label="CNPJ *" />
                        <AppInput v-model="company.inscricao_estadual" label="Inscrição Estadual" />

                        <AppInput v-model="company.razao_social" label="Razão Social *" class="md:col-span-2" />
                        <AppInput v-model="company.nome_fantasia" label="Nome Fantasia" class="md:col-span-2" />

                        <AppSelect v-model="company.regime_tributario" label="Regime Tributário *">
                            <option value="" disabled>Selecione...</option>
                            <option v-for="option in regimeOptions" :key="option" :value="option">{{ option }}</option>
                        </AppSelect>
                        <AppInput v-model="company.inscricao_municipal" label="Inscrição Municipal" />

                        <AppInput v-model="company.cnae" label="CNAE Principal" />
                        <AppInput v-model="company.telefone" label="Telefone" />

                        <AppInput v-model="company.email" type="email" label="E-mail" class="md:col-span-2" />
                    </div>
                </SettingsSectionCard>

                <SettingsSectionCard title="Endereço" subtitle="Endereço do estabelecimento">
                    <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
                        <AppInput v-model="company.cep" label="CEP *" class="md:col-span-3" />

                        <AppSelect v-model="company.uf" label="UF *" class="md:col-span-3">
                            <option value="" disabled>Selecione...</option>
                            <option v-for="uf in ufOptions" :key="uf" :value="uf">{{ uf }}</option>
                        </AppSelect>

                        <AppInput v-model="company.logradouro" label="Logradouro *" class="md:col-span-4" />
                        <AppInput v-model="company.numero" label="Nº *" class="md:col-span-2" />

                        <AppInput v-model="company.complemento" label="Complemento" class="md:col-span-6" placeholder="Sala, Bloco, Andar..." />

                        <AppInput v-model="company.bairro" label="Bairro *" class="md:col-span-3" />
                        <AppInput v-model="company.cidade" label="Cidade *" class="md:col-span-3" />
                    </div>
                </SettingsSectionCard>
            </fieldset>
        </template>

        <template v-else-if="activeTab === 'pdv'">
            <SettingsSectionCard title="Modo de Operação do PDV" subtitle="Define o template do PDV e também o contexto gerencial da retaguarda.">
                <div class="pdv-mode-grid">
                    <button
                        v-for="option in terminalOptions"
                        :key="option.id"
                        type="button"
                        class="pdv-mode-card"
                        :class="{ 'is-active': selectedTerminalType === option.id }"
                        :disabled="isManagedByErp"
                        @click="selectTerminalType(option.id)"
                    >
                        <div class="pdv-preview" :class="`is-${option.id}`">
                            <div class="pdv-preview-topbar">
                                <span class="pdv-preview-dot" />
                                <span class="pdv-preview-title">Template {{ option.label }}</span>
                            </div>

                            <div v-if="option.id === 'varejo'" class="preview-varejo-layout">
                                <aside class="pv-sidebar">
                                    <span class="pv-logo-block" />
                                    <span class="pv-cat is-active" />
                                    <span class="pv-cat" />
                                    <span class="pv-cat" />
                                    <span class="pv-cat" />
                                </aside>
                                <main class="pv-main">
                                    <span class="pv-search" />
                                    <span class="pv-divider" />
                                    <div class="pv-grid">
                                        <span v-for="cardIndex in 8" :key="`pv-card-${cardIndex}`" class="pv-card" />
                                    </div>
                                </main>
                                <aside class="pv-cart">
                                    <span class="pv-cart-box" />
                                    <span class="pv-cart-box" />
                                    <span class="pv-action is-ok" />
                                </aside>
                            </div>

                            <div v-else class="preview-body">
                                <div class="preview-rail">
                                    <span class="rail-pill is-strong" />
                                    <span class="rail-pill" />
                                    <span class="rail-pill" />
                                </div>
                                <div class="preview-products">
                                    <span class="preview-search" />
                                    <div class="preview-products-grid">
                                        <span class="preview-product is-strong" />
                                        <span class="preview-product" />
                                        <span class="preview-product" />
                                        <span class="preview-product" />
                                    </div>
                                </div>
                                <div class="preview-cart">
                                    <span class="cart-line is-strong" />
                                    <span class="cart-line" />
                                    <span class="cart-total" />
                                </div>
                            </div>
                        </div>

                        <div class="pdv-mode-card-content">
                            <div class="pdv-mode-card-head">
                                <div class="pdv-mode-title-wrap">
                                    <span class="pdv-radio-indicator" :class="{ 'is-active': selectedTerminalType === option.id }" />
                                    <component :is="option.icon" class="h-4 w-4" aria-hidden="true" />
                                    <p class="pdv-mode-title">{{ option.label }}</p>
                                </div>
                                <span v-if="selectedTerminalType === option.id" class="pdv-active-pill">Ativo</span>
                            </div>

                            <p class="pdv-mode-description">{{ option.description }}</p>

                            <ul class="pdv-features">
                                <li v-for="feature in option.features" :key="feature">{{ feature }}</li>
                            </ul>
                        </div>
                    </button>
                </div>

                <p class="pdv-mode-selected">Template selecionado: <strong>{{ selectedTerminalLabel }}</strong></p>
            </SettingsSectionCard>
        </template>

        <template v-else-if="activeTab === 'fiscal'">
            <fieldset :disabled="isManagedByErp" class="company-managed-fieldset">
            <SettingsSectionCard title="Ambiente e Emissão" subtitle="Defina o ambiente e os tipos de documento fiscal">
                <div class="grid grid-cols-1 gap-4">
                    <AppSelect v-model="fiscal.ambiente" label="Ambiente de Emissão *">
                        <option value="homologacao">Homologação (Testes)</option>
                        <option value="producao">Produção</option>
                    </AppSelect>

                    <div class="fiscal-toggle-card">
                        <div>
                            <p class="fiscal-toggle-title">Emitir NFC-e (Nota do Consumidor)</p>
                            <p class="fiscal-toggle-subtitle">Cupom fiscal eletrônico para vendas no balcão</p>
                        </div>
                        <button
                            type="button"
                            class="fiscal-switch"
                            :class="{ 'is-on': fiscal.emitir_nfce }"
                            :aria-pressed="fiscal.emitir_nfce"
                            @click="fiscal.emitir_nfce = !fiscal.emitir_nfce"
                        >
                            <span class="fiscal-switch-knob" />
                        </button>
                    </div>

                    <div class="fiscal-toggle-card">
                        <div>
                            <p class="fiscal-toggle-title">Emitir NF-e (Nota Fiscal Eletrônica)</p>
                            <p class="fiscal-toggle-subtitle">Para vendas com CNPJ ou operações interestaduais</p>
                        </div>
                        <button
                            type="button"
                            class="fiscal-switch"
                            :class="{ 'is-on': fiscal.emitir_nfe }"
                            :aria-pressed="fiscal.emitir_nfe"
                            @click="fiscal.emitir_nfe = !fiscal.emitir_nfe"
                        >
                            <span class="fiscal-switch-knob" />
                        </button>
                    </div>

                    <div class="fiscal-toggle-card">
                        <div>
                            <p class="fiscal-toggle-title">Impressão automática</p>
                            <p class="fiscal-toggle-subtitle">Imprimir cupom ao finalizar a venda</p>
                        </div>
                        <button
                            type="button"
                            class="fiscal-switch"
                            :class="{ 'is-on': fiscal.impressao_automatica }"
                            :aria-pressed="fiscal.impressao_automatica"
                            @click="fiscal.impressao_automatica = !fiscal.impressao_automatica"
                        >
                            <span class="fiscal-switch-knob" />
                        </button>
                    </div>
                </div>
            </SettingsSectionCard>

            <SettingsSectionCard title="Séries e Numeração" subtitle="Configure a série e o próximo número dos documentos">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <AppInput v-model="fiscal.serie_nfe" label="Série NF-e" />
                    <AppInput v-model="fiscal.proximo_numero_nfe" label="Próximo Nº NF-e" />
                    <AppInput v-model="fiscal.serie_nfce" label="Série NFC-e" />
                    <AppInput v-model="fiscal.proximo_numero_nfce" label="Próximo Nº NFC-e" />
                </div>
            </SettingsSectionCard>

            <SettingsSectionCard title="CSC (Código de Segurança do Contribuinte)" subtitle="Obrigatório para emissão de NFC-e">
                <div class="grid grid-cols-1 gap-4">
                    <AppInput v-model="fiscal.id_csc" label="ID do CSC" placeholder="Número de identificação" />
                    <AppInput v-model="fiscal.csc" label="Token CSC" placeholder="Token gerado na SEFAZ" />
                </div>
            </SettingsSectionCard>

            <SettingsSectionCard title="NotaAgil API" subtitle="Integração para emissão real de NF-e e NFC-e">
                <div class="grid grid-cols-1 gap-4">
                    <div class="fiscal-toggle-card">
                        <div>
                            <p class="fiscal-toggle-title">Usar NotaAgilApi no fechamento</p>
                            <p class="fiscal-toggle-subtitle">Token e URL base ficam nas variáveis de ambiente do servidor</p>
                        </div>
                        <button
                            type="button"
                            class="fiscal-switch"
                            :class="{ 'is-on': fiscal.notagil_enabled }"
                            :aria-pressed="fiscal.notagil_enabled"
                            @click="fiscal.notagil_enabled = !fiscal.notagil_enabled"
                        >
                            <span class="fiscal-switch-knob" />
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <AppInput v-model="fiscal.notagil_company_id" label="Company ID NotaAgil" placeholder="Opcional quando o token já define a empresa" />
                        <AppInput v-model="fiscal.notagil_operation_code_nfce" label="Operation code NFC-e" placeholder="Ex.: VENDA_BALCAO_NFCE" />
                        <AppInput v-model="fiscal.notagil_operation_code_nfe" label="Operation code NF-e" placeholder="Ex.: VENDA_FATURAMENTO_NFE" />
                    </div>
                </div>
            </SettingsSectionCard>
            </fieldset>
        </template>

        <template v-else-if="activeTab === 'certificado'">
            <fieldset :disabled="isManagedByErp" class="company-managed-fieldset">
            <SettingsSectionCard title="Certificado Digital" subtitle="Necessário para assinar e transmitir documentos fiscais">
                <div class="grid grid-cols-1 gap-4">
                    <AppSelect v-model="certificate.tipo" label="Tipo do Certificado">
                        <option value="a1">A1 (Arquivo digital)</option>
                        <option value="a3">A3 (Token/Cartão)</option>
                    </AppSelect>

                    <div class="certificate-file-field">
                        <AppInput v-model="certificate.arquivo_nome" label="Arquivo do Certificado (.pfx)" />
                        <AppButton variant="secondary" class="certificate-file-btn" @click="openCertificateFilePicker">
                            Selecionar
                        </AppButton>
                        <input
                            ref="certificateFileInput"
                            type="file"
                            class="hidden"
                            accept=".pfx,application/x-pkcs12"
                            @change="onCertificateFileChange"
                        >
                    </div>

                    <AppInput v-model="certificate.senha_hash" type="password" label="Senha do Certificado" />
                    <AppInput v-model="certificate.validade" type="date" label="Validade do Certificado" />

                    <div class="certificate-tip-card">
                        <p class="certificate-tip-text">
                            <strong>Dica:</strong> O certificado digital é obrigatório para a emissão de NF-e e NFC-e.
                            Certifique-se de que ele esteja válido e vinculado ao CNPJ da empresa.
                        </p>
                    </div>
                </div>
            </SettingsSectionCard>
            </fieldset>
        </template>

        <AppCard v-else class="p-8 text-center">
            <p class="text-sm font-semibold text-main">
                Aba {{ companyTabs.find((tab) => tab.id === activeTab)?.label }} em construção.
            </p>
            <p class="text-sm text-muted mt-1">
                Próximo passo: montar os campos desta aba igual ao layout que você enviou.
            </p>
        </AppCard>
    </div>
</template>

<style scoped>
.company-tabs-wrap {
    background: color-mix(in srgb, var(--color-bg-surface) 92%, var(--color-bg-muted));
}

.erp-managed-card {
    border: 1px solid color-mix(in srgb, var(--color-primary) 32%, var(--color-border));
    background: color-mix(in srgb, var(--color-primary) 8%, var(--color-bg-surface));
}

.erp-managed-title {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 800;
    color: var(--color-text);
}

.erp-managed-copy {
    margin: 0.2rem 0 0;
    font-size: 0.88rem;
    color: var(--color-text-muted);
}

.company-managed-fieldset {
    border: 0;
    display: grid;
    gap: var(--space-4);
    padding: 0;
    margin: 0;
}

.company-managed-fieldset:disabled {
    opacity: 0.78;
}

.company-tabs-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.4rem;
}

.company-tab-btn {
    min-height: 2.55rem;
    border-radius: var(--radius-sm);
    border: 1px solid transparent;
    background: transparent;
    color: var(--color-text-muted);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    font-size: 1rem;
    font-weight: 600;
    transition: all var(--transition-fast);
}

.company-tab-btn:hover {
    color: var(--color-text);
    background: color-mix(in srgb, var(--color-bg-surface) 88%, transparent);
}

.company-tab-btn.is-active {
    color: var(--color-text);
    background: var(--color-bg-surface);
    border-color: color-mix(in srgb, var(--color-border-strong) 45%, transparent);
    box-shadow: var(--shadow-xs);
}

.pdv-mode-grid {
    display: grid;
    gap: var(--space-4);
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
}

.pdv-mode-card {
    width: 100%;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    background: var(--color-bg-surface);
    text-align: left;
    overflow: hidden;
    transition: border-color var(--transition-fast), background var(--transition-fast), transform var(--transition-fast);
}

.pdv-mode-card:hover {
    border-color: var(--color-border-strong);
    transform: translateY(-1px);
}

.pdv-mode-card.is-active {
    border-color: color-mix(in srgb, var(--color-primary) 68%, var(--color-border));
    background: color-mix(in srgb, var(--color-primary) 10%, var(--color-bg-surface));
}

.pdv-mode-card-content {
    padding: var(--space-4);
}

.pdv-mode-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-2);
}

.pdv-mode-title-wrap {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
}

.pdv-mode-title {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--color-text);
}

.pdv-radio-indicator {
    width: 1.08rem;
    height: 1.08rem;
    border-radius: 999px;
    border: 2px solid color-mix(in srgb, var(--color-border-strong) 46%, transparent);
    box-shadow: inset 0 0 0 2px transparent;
    transition: all var(--transition-fast);
}

.pdv-radio-indicator.is-active {
    border-color: color-mix(in srgb, var(--color-primary) 80%, var(--color-border));
    box-shadow: inset 0 0 0 3px var(--color-bg-surface), inset 0 0 0 99px var(--color-primary);
}

.pdv-active-pill {
    display: inline-flex;
    align-items: center;
    height: 1.45rem;
    padding: 0 0.55rem;
    border-radius: 999px;
    border: 1px solid color-mix(in srgb, var(--color-primary) 44%, transparent);
    background: color-mix(in srgb, var(--color-primary) 14%, var(--color-bg-surface));
    color: color-mix(in srgb, var(--color-primary) 84%, var(--color-text));
    font-size: 0.74rem;
    font-weight: 700;
}

.pdv-mode-description {
    margin: 0.55rem 0 0;
    color: var(--color-text-muted);
    font-size: 0.9rem;
}

.pdv-features {
    margin: 0.65rem 0 0;
    padding-left: 1.05rem;
    display: grid;
    gap: 0.22rem;
    color: var(--color-text-muted);
    font-size: 0.88rem;
}

.pdv-mode-selected {
    margin: var(--space-3) 0 0;
    color: var(--color-text-muted);
    font-size: 0.9rem;
}

.pdv-mode-selected strong {
    color: var(--color-text);
}

.pdv-preview {
    padding: 0.62rem;
    border-bottom: 1px solid color-mix(in srgb, var(--color-border) 65%, transparent);
    background: linear-gradient(160deg, #0a1324 0%, #12213a 54%, #0b172b 100%);
}

.pdv-preview.is-restaurante {
    background: linear-gradient(160deg, #21110a 0%, #3f2211 54%, #241207 100%);
}

.pdv-preview.is-servicos {
    background: linear-gradient(160deg, #091627 0%, #15355d 54%, #0a1e35 100%);
}

.pdv-preview-topbar {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    margin-bottom: 0.45rem;
}

.pdv-preview-dot {
    width: 0.35rem;
    height: 0.35rem;
    border-radius: 999px;
    background: #32d583;
}

.pdv-preview-title {
    font-size: 0.64rem;
    color: #e2e8f0;
    font-weight: 700;
    letter-spacing: 0.02em;
}

.preview-varejo-layout {
    display: grid;
    grid-template-columns: 0.5fr 2.2fr 1fr;
    gap: 0.34rem;
}

.pv-sidebar,
.pv-main,
.pv-cart {
    border-radius: 0.5rem;
    border: 1px solid rgb(255 255 255 / 0.16);
    background: rgb(13 33 54 / 0.66);
    min-height: 4.1rem;
}

.pv-sidebar {
    display: grid;
    align-content: start;
    gap: 0.16rem;
    padding: 0.34rem;
}

.pv-logo-block {
    display: block;
    height: 0.86rem;
    border-radius: 0.28rem;
    background: rgb(44 180 64 / 0.92);
}

.pv-cat,
.pv-search,
.pv-card,
.pv-cart-box,
.pv-action {
    display: block;
    border-radius: 0.28rem;
    background: rgb(92 123 157 / 0.42);
}

.pv-cat {
    height: 0.42rem;
}

.pv-cat.is-active {
    background: rgb(44 180 64 / 0.92);
}

.pv-main {
    padding: 0.34rem;
    display: grid;
    gap: 0.18rem;
    align-content: start;
}

.pv-search {
    height: 0.42rem;
}

.pv-divider {
    display: block;
    height: 0.03rem;
    border-radius: 999px;
    background: rgb(27 161 255 / 0.95);
}

.pv-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.12rem;
}

.pv-card {
    height: 0.9rem;
}

.pv-cart {
    padding: 0.34rem;
    display: grid;
    align-content: start;
    gap: 0.14rem;
}

.pv-cart-box {
    height: 0.95rem;
}

.pv-action {
    height: 0.4rem;
}

.pv-action.is-ok {
    background: rgb(44 180 64 / 0.92);
}

.preview-body {
    display: grid;
    grid-template-columns: 0.55fr 1fr 0.75fr;
    gap: 0.35rem;
}

.preview-rail,
.preview-products,
.preview-cart {
    border-radius: 0.45rem;
    border: 1px solid rgb(255 255 255 / 0.16);
    background: rgb(8 18 33 / 0.66);
    min-height: 3.15rem;
    padding: 0.28rem;
}

.pdv-preview.is-restaurante .preview-rail,
.pdv-preview.is-restaurante .preview-products,
.pdv-preview.is-restaurante .preview-cart {
    background: rgb(44 22 10 / 0.62);
}

.pdv-preview.is-servicos .preview-rail,
.pdv-preview.is-servicos .preview-products,
.pdv-preview.is-servicos .preview-cart {
    background: rgb(10 28 50 / 0.62);
}

.preview-rail {
    display: grid;
    gap: 0.24rem;
    align-content: start;
}

.rail-pill,
.preview-search,
.preview-product,
.cart-line,
.cart-total {
    display: block;
    border-radius: 0.3rem;
    background: rgb(226 232 240 / 0.25);
}

.rail-pill {
    height: 0.34rem;
}

.rail-pill.is-strong,
.preview-product.is-strong,
.cart-line.is-strong {
    background: rgb(34 197 131 / 0.72);
}

.pdv-preview.is-restaurante .rail-pill.is-strong,
.pdv-preview.is-restaurante .preview-product.is-strong,
.pdv-preview.is-restaurante .cart-line.is-strong {
    background: rgb(245 158 11 / 0.75);
}

.pdv-preview.is-servicos .rail-pill.is-strong,
.pdv-preview.is-servicos .preview-product.is-strong,
.pdv-preview.is-servicos .cart-line.is-strong {
    background: rgb(96 165 250 / 0.75);
}

.preview-search {
    height: 0.35rem;
    margin-bottom: 0.25rem;
}

.preview-products-grid {
    display: grid;
    gap: 0.2rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.preview-product {
    height: 0.7rem;
}

.preview-cart {
    display: grid;
    gap: 0.24rem;
    align-content: start;
}

.cart-line {
    height: 0.34rem;
}

.cart-total {
    height: 0.5rem;
    margin-top: 0.18rem;
}

.fiscal-toggle-card {
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    background: color-mix(in srgb, var(--color-bg-surface) 96%, var(--color-bg-muted));
    padding: 1rem 0.9rem;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
    gap: var(--space-3);
}

.fiscal-toggle-title {
    margin: 0;
    font-size: 1.05rem;
    line-height: 1.3;
    font-weight: 700;
    color: var(--color-text);
}

.fiscal-toggle-subtitle {
    margin: 0.15rem 0 0;
    font-size: 0.92rem;
    line-height: 1.3;
    color: var(--color-text-muted);
}

.fiscal-switch {
    width: 3.5rem;
    height: 2rem;
    border-radius: 999px;
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 55%, transparent);
    background: color-mix(in srgb, var(--color-bg-muted) 78%, var(--color-bg-surface));
    position: relative;
    transition: background var(--transition-fast), border-color var(--transition-fast);
}

.fiscal-switch.is-on {
    background: var(--color-primary);
    border-color: color-mix(in srgb, var(--color-primary) 72%, var(--color-border));
}

.fiscal-switch-knob {
    width: 1.6rem;
    height: 1.6rem;
    border-radius: 999px;
    background: var(--color-bg-surface);
    position: absolute;
    top: 0.14rem;
    left: 0.16rem;
    box-shadow: var(--shadow-sm);
    transition: transform var(--transition-fast);
}

.fiscal-switch.is-on .fiscal-switch-knob {
    transform: translateX(1.45rem);
}

.certificate-file-field {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: end;
    gap: 0.55rem;
}

.certificate-file-btn {
    min-width: 7.8rem;
}

.certificate-tip-card {
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 28%, transparent);
    border-radius: var(--radius-md);
    background: color-mix(in srgb, var(--color-bg-muted) 74%, var(--color-bg-surface));
    padding: 0.85rem;
}

.certificate-tip-text {
    margin: 0;
    color: var(--color-text-muted);
    font-size: 0.93rem;
    line-height: 1.4;
}

@media (max-width: 900px) {
    .company-tabs-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .preview-varejo-layout,
    .preview-body {
        grid-template-columns: 1fr;
    }

    .fiscal-toggle-card {
        grid-template-columns: 1fr;
        justify-items: start;
    }

    .certificate-file-field {
        grid-template-columns: 1fr;
    }

    .certificate-file-btn {
        width: 100%;
    }
}
</style>
