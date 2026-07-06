<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { BriefcaseBusiness, Building2, FileText, GripVertical, Image, Monitor, Save, ShieldCheck, Store, Trash2, Upload, UtensilsCrossed } from 'lucide-vue-next';
import api from '../../lib/api';
import { DEFAULT_CUPOM_LAYOUT, SECTION_LABELS, normalizeCupomLayout } from '../../lib/cupomLayout';
import SettingsPageHeader from '../../components/settings/SettingsPageHeader.vue';
import SettingsSectionCard from '../../components/settings/SettingsSectionCard.vue';
import AppInput from '../../components/ui/AppInput.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppToast from '../../components/ui/AppToast.vue';
import AppCard from '../../components/ui/AppCard.vue';

const loading = ref(false);
const saving = ref(false);
const provisioningWebhook = ref(false);
const rotatingWebhookSecret = ref(false);
const message = ref('');
const isManagedByErp = ref(false);
const activeTab = ref('empresa');
const selectedTerminalType = ref('varejo');
const certificateFileInput = ref(null);
const logoFileInput = ref(null);
const draggedSectionType = ref('');
const dragOverSectionType = ref('');
const selectedSectionType = ref('header');

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
    notagil_base_url: '',
    notagil_token: '',
    notagil_token_configured: false,
    notagil_company_id: '',
    notagil_operation_code_nfce: '',
    notagil_nfce_synchronous: false,
    notagil_operation_code_nfe: '',
    notagil_webhook_url: '',
    notagil_webhook_secret: '',
    notagil_webhook_secret_configured: false,
    notagil_webhook_tolerance_seconds: 300,
    notagil_webhook_id: '',
    notagil_webhook_status: '',
    notagil_webhook_last_synced_at: '',
    notagil_webhook_last_error: '',
    logo_url: '',
    layout_cupom: normalizeCupomLayout(null),
});

const certificate = reactive({
    tipo: 'a1',
    validade: '',
    arquivo_nome: '',
    senha_hash: '',
    managed_by_platform: false,
    source: '',
    platform: '',
    certificate_id: '',
    status: '',
    valid: null,
    has_private_content: null,
    numero_serie: '',
    cnpj_cpf: '',
    sujeito: '',
    emissor: '',
    valido_desde: '',
    dias_restantes: null,
});

const companyTabs = [
    { id: 'empresa', label: 'Empresa', icon: Building2 },
    { id: 'pdv', label: 'PDV', icon: Monitor },
    { id: 'fiscal', label: 'NF-e / NFC-e', icon: FileText },
    { id: 'layout', label: 'Layout', icon: Image },
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

const certificateManagedByPlatform = computed(() => {
    return Boolean(certificate.managed_by_platform || certificate.source === 'notagil');
});

const certificateLocked = computed(() => isManagedByErp.value || certificateManagedByPlatform.value);

const certificateStatusLabel = computed(() => {
    if (certificate.valid === true) return 'Válido';
    if (certificate.valid === false) return 'Inválido';
    return certificate.status || 'Cadastrado';
});

const thermalLayout = computed(() => normalizeCupomLayout(fiscal.layout_cupom));

const thermalSections = computed(() => thermalLayout.value.sections.slice().sort((left, right) => left.order - right.order));
const selectedThermalSection = computed(
    () => thermalSections.value.find((section) => section.type === selectedSectionType.value) || thermalSections.value[0] || null,
);

const previewCompany = computed(() => ({
    nome_fantasia: company.nome_fantasia || 'FREELINE INFORMATICA LTDA',
    cnpj: company.cnpj || '83.188.342/0001-04',
    inscricao_estadual: company.inscricao_estadual || '252290720',
    logradouro: company.logradouro || 'Rua Benjamin Constant',
    numero: company.numero || '4135',
    bairro: company.bairro || 'Glória',
    cidade: company.cidade || company.municipio || 'Joinville',
    uf: company.uf || 'SC',
}));

const previewItems = [
    { code: '00000000037495', description: 'MINI SALGADO FRIT', quantity: '0,092', unit: 'KG', unitPrice: '44,99', total: '4,14' },
    { code: '00000000038645', description: 'PAO DE QUEIJO SUP', quantity: '0,106', unit: 'KG', unitPrice: '54,90', total: '5,82' },
];

const thermalPreviewSections = computed(() => thermalSections.value.filter((section) => section.enabled));

function sectionTextAlign(section) {
    return section.align === 'right' ? 'right' : section.align === 'center' ? 'center' : 'left';
}

function sectionPreviewStyle(section) {
    return {
        textAlign: sectionTextAlign(section),
        marginTop: `${section.spacing_before_mm}mm`,
        marginBottom: `${Number(section.spacing_after_mm || 0) + Number(thermalLayout.value.block_spacing_mm || 0)}mm`,
        paddingLeft: `${section.padding_left_mm}mm`,
        paddingRight: `${section.padding_right_mm}mm`,
    };
}

function itemDescriptionPreviewStyle() {
    if (thermalLayout.value.item_layout.description_wrap === 'truncate') {
        return {
            overflow: 'hidden',
            textOverflow: 'ellipsis',
            whiteSpace: 'nowrap',
        };
    }

    return {
        display: '-webkit-box',
        overflow: 'hidden',
        WebkitBoxOrient: 'vertical',
        WebkitLineClamp: String(thermalLayout.value.item_layout.max_description_lines),
    };
}

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

function defaultNotagilWebhookUrl() {
    if (typeof window === 'undefined') return '/api/pdv/webhooks/notagil';
    return `${window.location.origin}/api/pdv/webhooks/notagil`;
}

function openCertificateFilePicker() {
    if (certificateLocked.value) return;

    certificateFileInput.value?.click();
}

function onCertificateFileChange(event) {
    if (certificateLocked.value) return;

    const [file] = event?.target?.files || [];
    if (!file) return;
    certificate.arquivo_nome = file.name;
}

function openLogoFilePicker() {
    if (isManagedByErp.value) return;
    logoFileInput.value?.click();
}

function onLogoFileChange(event) {
    const [file] = event?.target?.files || [];
    if (!file) return;

    if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) {
        message.value = 'Use uma logo em PNG, JPG ou WebP.';
        return;
    }

    if (file.size > 2 * 1024 * 1024) {
        message.value = 'A logo deve ter no máximo 2MB.';
        return;
    }

    const reader = new FileReader();
    reader.onload = () => {
        fiscal.logo_url = String(reader.result || '');
    };
    reader.readAsDataURL(file);
}

function removeLogo() {
    if (isManagedByErp.value) return;
    fiscal.logo_url = '';
    if (logoFileInput.value) logoFileInput.value.value = '';
}

function setLayout(next) {
    fiscal.layout_cupom = normalizeCupomLayout(next);
}

function updateLayoutRoot(key, value) {
    setLayout({
        ...thermalLayout.value,
        [key]: Number(value),
    });
}

function updatePaper(key, value) {
    setLayout({
        ...thermalLayout.value,
        paper: {
            ...thermalLayout.value.paper,
            [key]: key === 'cut_enabled' ? Boolean(value) : Number(value),
        },
    });
}

function updateTypography(key, value) {
    setLayout({
        ...thermalLayout.value,
        typography: {
            ...thermalLayout.value.typography,
            [key]: Number(value),
        },
    });
}

function updateItemLayout(key, value) {
    setLayout({
        ...thermalLayout.value,
        item_layout: {
            ...thermalLayout.value.item_layout,
            [key]: key === 'description_wrap' ? value : Number(value),
        },
    });
}

function updateSection(type, changes) {
    setLayout({
        ...thermalLayout.value,
        sections: thermalSections.value.map((section) =>
            section.type === type ? { ...section, ...changes } : section,
        ),
    });
}

function reorderSection(sourceType, targetType) {
    if (isManagedByErp.value || !sourceType || !targetType || sourceType === targetType) return;

    const sections = thermalSections.value.slice();
    const sourceIndex = sections.findIndex((section) => section.type === sourceType);
    const targetIndex = sections.findIndex((section) => section.type === targetType);
    if (sourceIndex < 0 || targetIndex < 0) return;

    const [section] = sections.splice(sourceIndex, 1);
    sections.splice(targetIndex, 0, section);
    setLayout({
        ...thermalLayout.value,
        sections: sections.map((item, itemIndex) => ({ ...item, order: itemIndex + 1 })),
    });
    selectedSectionType.value = sourceType;
}

function startSectionDrag(event, type) {
    if (isManagedByErp.value) {
        event?.preventDefault();
        return;
    }

    draggedSectionType.value = type;
    selectedSectionType.value = type;
    if (event?.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', type);
    }
}

function enterSectionDropZone(type) {
    if (draggedSectionType.value && draggedSectionType.value !== type) {
        dragOverSectionType.value = type;
    }
}

function dropSection(event, targetType) {
    event?.preventDefault();
    const sourceType = draggedSectionType.value || event?.dataTransfer?.getData('text/plain');
    reorderSection(sourceType, targetType);
    endSectionDrag();
}

function endSectionDrag() {
    draggedSectionType.value = '';
    dragOverSectionType.value = '';
}

function moveSection(type, direction) {
    if (isManagedByErp.value) return;
    const sections = thermalSections.value.slice();
    const index = sections.findIndex((section) => section.type === type);
    const targetIndex = index + direction;
    if (index < 0 || targetIndex < 0 || targetIndex >= sections.length) return;

    const [section] = sections.splice(index, 1);
    sections.splice(targetIndex, 0, section);
    setLayout({
        ...thermalLayout.value,
        sections: sections.map((item, itemIndex) => ({ ...item, order: itemIndex + 1 })),
    });
    selectedSectionType.value = type;
}

function resetThermalLayout() {
    if (isManagedByErp.value) return;
    setLayout(DEFAULT_CUPOM_LAYOUT);
    selectedSectionType.value = 'header';
}

function assignReactive(target, source) {
    Object.keys(target).forEach((key) => {
        target[key] = source?.[key] ?? target[key] ?? '';
    });
}

function buildFiscalPayload() {
    const payload = {
        ...fiscal,
        notagil_webhook_tolerance_seconds: Number(fiscal.notagil_webhook_tolerance_seconds || 0),
    };

    delete payload.notagil_webhook_secret_configured;
    delete payload.notagil_token_configured;

    if (!String(payload.notagil_token || '').trim()) {
        delete payload.notagil_token;
    }

    if (!String(payload.notagil_webhook_secret || '').trim()) {
        delete payload.notagil_webhook_secret;
    }

    return payload;
}

async function load() {
    loading.value = true;
    try {
        const [companyRes, fiscalRes] = await Promise.all([
            api.get('/settings/company'),
            api.get('/settings/fiscal'),
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
        if (!String(fiscal.notagil_webhook_url || '').trim()) {
            fiscal.notagil_webhook_url = defaultNotagilWebhookUrl();
        }
        fiscal.layout_cupom = normalizeCupomLayout(fiscal.layout_cupom);
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
        const saveRequests = [
            api.put('/settings/company', company),
            api.put('/settings/fiscal', buildFiscalPayload()),
        ];

        if (!certificateManagedByPlatform.value) {
            saveRequests.push(api.put('/settings/certificate', certificate));
        }

        const [companyResult, fiscalResult] = await Promise.all(saveRequests);
        if (fiscalResult?.data) {
            assignReactive(fiscal, fiscalResult.data);
        }

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

async function provisionNotagilWebhook() {
    if (isManagedByErp.value) {
        message.value = 'Configurações gerenciadas pelo ERP.';
        return;
    }

    provisioningWebhook.value = true;
    message.value = '';

    try {
        const response = await api.post('/settings/fiscal/notagil/webhook', buildFiscalPayload());

        if (response.data?.fiscal) {
            assignReactive(fiscal, response.data.fiscal);
            fiscal.notagil_webhook_last_error = response.data.fiscal.notagil_webhook_last_error || '';
        }

        message.value = 'Webhook NotaAgil sincronizado.';
    } catch (error) {
        message.value = error?.response?.data?.message ?? 'Falha ao sincronizar webhook NotaAgil.';
    } finally {
        provisioningWebhook.value = false;
    }
}

async function rotateNotagilWebhookSecret() {
    if (isManagedByErp.value) {
        message.value = 'Configurações gerenciadas pelo ERP.';
        return;
    }

    rotatingWebhookSecret.value = true;
    message.value = '';

    try {
        const response = await api.post('/settings/fiscal/notagil/webhook/secret', buildFiscalPayload());

        if (response.data?.fiscal) {
            assignReactive(fiscal, response.data.fiscal);
            fiscal.notagil_webhook_last_error = response.data.fiscal.notagil_webhook_last_error || '';
        }

        message.value = 'Segredo do webhook NotaAgil rotacionado.';
    } catch (error) {
        message.value = error?.response?.data?.message ?? 'Falha ao rotacionar segredo do webhook NotaAgil.';
    } finally {
        rotatingWebhookSecret.value = false;
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
                            <p class="fiscal-toggle-subtitle">Emissão fiscal usando as credenciais configuradas no PDV</p>
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
                        <AppInput
                            v-model="fiscal.notagil_base_url"
                            label="URL base da API NotaAgil"
                            placeholder="https://notagil_api.vora-sys.com/api/v2/integrations"
                            hint="Informe a URL do ambiente NotaAgil usado para emissão."
                        />
                        <AppInput
                            v-model="fiscal.notagil_token"
                            type="password"
                            autocomplete="new-password"
                            label="Token NotaAgil"
                            :placeholder="fiscal.notagil_token_configured ? 'Token já configurado' : 'Informe o token da API'"
                            hint="Deixe em branco para manter o token atual."
                        />
                    </div>

                    <div class="fiscal-toggle-card">
                        <div>
                            <p class="fiscal-toggle-title">Emissão síncrona NFC-e</p>
                            <p class="fiscal-toggle-subtitle">Aguarda a resposta da SEFAZ no fechamento para liberar a impressão térmica imediata</p>
                        </div>
                        <button
                            type="button"
                            class="fiscal-switch"
                            :class="{ 'is-on': fiscal.notagil_nfce_synchronous }"
                            :aria-pressed="fiscal.notagil_nfce_synchronous"
                            @click="fiscal.notagil_nfce_synchronous = !fiscal.notagil_nfce_synchronous"
                        >
                            <span class="fiscal-switch-knob" />
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <AppInput
                            v-model="fiscal.notagil_webhook_url"
                            label="URL do webhook NotaAgil"
                            placeholder="https://seudominio.com/api/pdv/webhooks/notagil"
                            hint="Cadastre esta URL no painel da NotaAgil."
                        />
                        <AppInput
                            v-model="fiscal.notagil_webhook_tolerance_seconds"
                            type="number"
                            min="0"
                            max="86400"
                            label="Tolerância da assinatura"
                            hint="Tempo máximo em segundos para aceitar X-NotaAgil-Timestamp."
                        />
                        <AppInput
                            v-model="fiscal.notagil_webhook_secret"
                            type="password"
                            autocomplete="new-password"
                            label="Segredo do webhook"
                            :placeholder="fiscal.notagil_webhook_secret_configured ? 'Segredo já configurado' : 'Informe o segredo compartilhado'"
                            hint="Deixe em branco para manter o segredo atual."
                        />
                    </div>

                    <div class="webhook-events-panel">
                        <div class="webhook-events-header">
                            <div>
                                <p class="webhook-events-title">Eventos aceitos</p>
                                <p v-if="fiscal.notagil_webhook_id" class="webhook-events-meta">
                                    ID {{ fiscal.notagil_webhook_id }}
                                    <span v-if="fiscal.notagil_webhook_status">· {{ fiscal.notagil_webhook_status }}</span>
                                </p>
                                <p v-else class="webhook-events-meta">Webhook ainda não sincronizado no NotaAgil.</p>
                            </div>
                            <div class="webhook-events-actions">
                                <AppButton
                                    variant="secondary"
                                    :loading="provisioningWebhook"
                                    :disabled="saving || loading || isManagedByErp || rotatingWebhookSecret"
                                    @click="provisionNotagilWebhook"
                                >
                                    <RefreshCw class="h-4 w-4" aria-hidden="true" />
                                    Sincronizar
                                </AppButton>
                                <AppButton
                                    variant="secondary"
                                    :loading="rotatingWebhookSecret"
                                    :disabled="saving || loading || isManagedByErp || provisioningWebhook || !fiscal.notagil_webhook_id"
                                    @click="rotateNotagilWebhookSecret"
                                >
                                    <RefreshCw class="h-4 w-4" aria-hidden="true" />
                                    Rotacionar segredo
                                </AppButton>
                            </div>
                        </div>
                        <div class="webhook-events-list">
                            <code>fiscal_document.created</code>
                            <code>fiscal_document.authorized</code>
                            <code>fiscal_document.rejected</code>
                            <code>fiscal_document.failed</code>
                            <code>fiscal_document.cancelled</code>
                            <code>fiscal_document.corrected</code>
                        </div>
                        <p v-if="fiscal.notagil_webhook_last_error" class="webhook-events-error">
                            {{ fiscal.notagil_webhook_last_error }}
                        </p>
                    </div>
                </div>
            </SettingsSectionCard>
            </fieldset>
        </template>

        <template v-else-if="activeTab === 'layout'">
            <fieldset :disabled="isManagedByErp" class="company-managed-fieldset">
            <SettingsSectionCard title="Layout do Cupom Fiscal" subtitle="Configure o DANFC-e térmico em blocos, com medidas em milímetros e prévia do papel.">
                <div class="thermal-editor-shell">
                    <div class="thermal-editor-controls">
                        <div class="thermal-config-card thermal-logo-card">
                            <div class="thermal-logo-preview">
                                <img v-if="fiscal.logo_url" :src="fiscal.logo_url" alt="Logo do cupom">
                                <Image v-else class="h-8 w-8" aria-hidden="true" />
                            </div>
                            <div class="thermal-logo-actions">
                                <p class="thermal-config-title">Logo do cupom</p>
                                <p class="thermal-config-copy">PNG, JPG ou WebP até 2MB. A impressão usa raster ESC/POS.</p>
                                <div class="thermal-actions-inline">
                                    <AppButton variant="secondary" @click="openLogoFilePicker">
                                        <Upload class="h-4 w-4" aria-hidden="true" />
                                        {{ fiscal.logo_url ? 'Trocar logo' : 'Enviar logo' }}
                                    </AppButton>
                                    <AppButton v-if="fiscal.logo_url" variant="ghost" @click="removeLogo">
                                        <Trash2 class="h-4 w-4" aria-hidden="true" />
                                        Remover
                                    </AppButton>
                                </div>
                                <input ref="logoFileInput" type="file" class="hidden" accept="image/png,image/jpeg,image/webp" @change="onLogoFileChange">
                            </div>
                        </div>

                        <div class="thermal-config-card">
                            <div class="thermal-config-head">
                                <div>
                                    <p class="thermal-config-title">Papel térmico</p>
                                    <p class="thermal-config-copy">A largura fica restrita a 58 mm ou 80 mm para manter a saída previsível.</p>
                                </div>
                            </div>
                            <div class="thermal-controls-grid">
                                <label class="thermal-field">
                                    <span>Largura</span>
                                    <select :value="thermalLayout.paper.width_mm" @change="updatePaper('width_mm', $event.target.value)">
                                        <option value="80">80 mm</option>
                                        <option value="58">58 mm</option>
                                    </select>
                                </label>
                                <label class="thermal-range-field">
                                    <span>QR Code</span>
                                    <div class="thermal-range-row">
                                        <input type="range" min="20" max="40" :value="thermalLayout.paper.qr_size_mm" @input="updatePaper('qr_size_mm', $event.target.value)">
                                        <strong>{{ thermalLayout.paper.qr_size_mm }}mm</strong>
                                    </div>
                                </label>
                                <label class="thermal-field">
                                    <span>Margem superior</span>
                                    <input type="number" min="0" :value="thermalLayout.paper.margin_top_mm" @input="updatePaper('margin_top_mm', $event.target.value)">
                                </label>
                                <label class="thermal-field">
                                    <span>Margem inferior</span>
                                    <input type="number" min="0" :value="thermalLayout.paper.margin_bottom_mm" @input="updatePaper('margin_bottom_mm', $event.target.value)">
                                </label>
                                <label class="thermal-field">
                                    <span>Margem esquerda</span>
                                    <input type="number" min="0" :value="thermalLayout.paper.margin_left_mm" @input="updatePaper('margin_left_mm', $event.target.value)">
                                </label>
                                <label class="thermal-field">
                                    <span>Margem direita</span>
                                    <input type="number" min="0" :value="thermalLayout.paper.margin_right_mm" @input="updatePaper('margin_right_mm', $event.target.value)">
                                </label>
                                <label class="thermal-range-field thermal-wide-field">
                                    <span>Avanço antes do corte</span>
                                    <div class="thermal-range-row">
                                        <input type="range" min="0" max="40" step="1" :value="thermalLayout.paper.feed_before_cut_mm" @input="updatePaper('feed_before_cut_mm', $event.target.value)">
                                        <strong>{{ thermalLayout.paper.feed_before_cut_mm }}mm</strong>
                                    </div>
                                </label>
                                <label class="thermal-section-toggle thermal-wide-field">
                                    <input type="checkbox" :checked="thermalLayout.paper.cut_enabled" @change="updatePaper('cut_enabled', $event.target.checked)">
                                    <span>Cortar automaticamente ao final</span>
                                </label>
                            </div>
                        </div>

                        <div class="thermal-config-card">
                            <p class="thermal-config-title">Tipografia</p>
                            <p class="thermal-config-copy">Ajuste o corpo base, números mono e o destaque do total.</p>
                            <label class="thermal-range-field">
                                <span>Fonte base</span>
                                <div class="thermal-range-row">
                                    <input type="range" min="6" max="12" :value="thermalLayout.typography.base_font_pt" @input="updateTypography('base_font_pt', $event.target.value)">
                                    <strong>{{ thermalLayout.typography.base_font_pt }}pt</strong>
                                </div>
                            </label>
                            <label class="thermal-range-field">
                                <span>Fonte mono</span>
                                <div class="thermal-range-row">
                                    <input type="range" min="6" max="12" :value="thermalLayout.typography.mono_font_pt" @input="updateTypography('mono_font_pt', $event.target.value)">
                                    <strong>{{ thermalLayout.typography.mono_font_pt }}pt</strong>
                                </div>
                            </label>
                            <label class="thermal-range-field">
                                <span>Fonte do total</span>
                                <div class="thermal-range-row">
                                    <input type="range" min="7" max="16" :value="thermalLayout.typography.total_font_pt" @input="updateTypography('total_font_pt', $event.target.value)">
                                    <strong>{{ thermalLayout.typography.total_font_pt }}pt</strong>
                                </div>
                            </label>
                        </div>

                        <div class="thermal-config-card">
                            <p class="thermal-config-title">Itens do cupom</p>
                            <p class="thermal-config-copy">Ajuste como descrições longas ocupam linhas na prévia e na impressão local.</p>
                            <div class="thermal-controls-grid">
                                <label class="thermal-field">
                                    <span>Linhas da descrição</span>
                                    <input type="number" min="1" max="6" :value="thermalLayout.item_layout.max_description_lines" @input="updateItemLayout('max_description_lines', $event.target.value)">
                                </label>
                                <label class="thermal-field">
                                    <span>Descrição</span>
                                    <select :value="thermalLayout.item_layout.description_wrap" @change="updateItemLayout('description_wrap', $event.target.value)">
                                        <option value="wrap">Quebrar linhas</option>
                                        <option value="truncate">Cortar texto</option>
                                    </select>
                                </label>
                                <label class="thermal-range-field thermal-wide-field">
                                    <span>Espaço entre itens</span>
                                    <div class="thermal-range-row">
                                        <input type="range" min="0" max="8" step="0.2" :value="thermalLayout.item_layout.item_spacing_mm" @input="updateItemLayout('item_spacing_mm', $event.target.value)">
                                        <strong>{{ thermalLayout.item_layout.item_spacing_mm }}mm</strong>
                                    </div>
                                </label>
                                <label class="thermal-range-field thermal-wide-field">
                                    <span>Espaço entre linhas</span>
                                    <div class="thermal-range-row">
                                        <input type="range" min="2" max="6" step="0.1" :value="thermalLayout.item_layout.line_spacing_mm" @input="updateItemLayout('line_spacing_mm', $event.target.value)">
                                        <strong>{{ thermalLayout.item_layout.line_spacing_mm }}mm</strong>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="thermal-config-card">
                            <div class="thermal-section-list-head">
                                <div>
                                    <p class="thermal-config-title">Blocos do cupom</p>
                                    <p class="thermal-config-copy">A ordem abaixo é a ordem real enviada para a impressora.</p>
                                </div>
                                <AppButton variant="ghost" @click="resetThermalLayout">Restaurar padrão</AppButton>
                            </div>
                            <label class="thermal-range-field thermal-wide-field">
                                <span>Espaço entre blocos</span>
                                <div class="thermal-range-row">
                                    <input type="range" min="0" max="8" step="0.2" :value="thermalLayout.block_spacing_mm" @input="updateLayoutRoot('block_spacing_mm', $event.target.value)">
                                    <strong>{{ thermalLayout.block_spacing_mm }}mm</strong>
                                </div>
                            </label>
                            <div class="thermal-section-list">
                                <div
                                    v-for="(section, index) in thermalSections"
                                    :key="section.type"
                                    class="thermal-section-row"
                                    :class="{
                                        'is-selected': selectedSectionType === section.type,
                                        'is-dragging': draggedSectionType === section.type,
                                        'is-drag-over': dragOverSectionType === section.type,
                                    }"
                                    :draggable="!isManagedByErp"
                                    @click="selectedSectionType = section.type"
                                    @dragstart="startSectionDrag($event, section.type)"
                                    @dragenter.prevent="enterSectionDropZone(section.type)"
                                    @dragover.prevent
                                    @drop="dropSection($event, section.type)"
                                    @dragend="endSectionDrag"
                                >
                                    <GripVertical class="thermal-drag-handle" aria-hidden="true" />
                                    <label class="thermal-section-toggle">
                                        <input type="checkbox" :checked="section.enabled" :disabled="section.required" @change="updateSection(section.type, { enabled: $event.target.checked })">
                                        <span>{{ SECTION_LABELS[section.type] || section.type }}</span>
                                        <small v-if="section.required">Obrigatório</small>
                                    </label>
                                    <select :value="section.align" @change="updateSection(section.type, { align: $event.target.value })">
                                        <option value="left">Esquerda</option>
                                        <option value="center">Centro</option>
                                        <option value="right">Direita</option>
                                    </select>
                                    <div class="thermal-order-actions">
                                        <button type="button" :aria-label="`Mover ${SECTION_LABELS[section.type] || section.type} para cima`" :disabled="index === 0" @click="moveSection(section.type, -1)">↑</button>
                                        <button type="button" :aria-label="`Mover ${SECTION_LABELS[section.type] || section.type} para baixo`" :disabled="index === thermalSections.length - 1" @click="moveSection(section.type, 1)">↓</button>
                                    </div>
                                </div>
                            </div>

                            <div v-if="selectedThermalSection" class="thermal-section-inspector">
                                <div>
                                    <p class="thermal-config-title">Ajustes de {{ SECTION_LABELS[selectedThermalSection.type] || selectedThermalSection.type }}</p>
                                    <p class="thermal-config-copy">Os espaçamentos e recuos também são usados na impressão.</p>
                                </div>
                                <div class="thermal-controls-grid">
                                    <label class="thermal-field">
                                        <span>Espaço antes</span>
                                        <input type="number" min="0" step="0.5" :value="selectedThermalSection.spacing_before_mm" @input="updateSection(selectedThermalSection.type, { spacing_before_mm: Number($event.target.value) })">
                                    </label>
                                    <label class="thermal-field">
                                        <span>Espaço depois</span>
                                        <input type="number" min="0" step="0.5" :value="selectedThermalSection.spacing_after_mm" @input="updateSection(selectedThermalSection.type, { spacing_after_mm: Number($event.target.value) })">
                                    </label>
                                    <label class="thermal-field">
                                        <span>Recuo esquerdo</span>
                                        <input type="number" min="0" step="0.5" :value="selectedThermalSection.padding_left_mm" @input="updateSection(selectedThermalSection.type, { padding_left_mm: Number($event.target.value) })">
                                    </label>
                                    <label class="thermal-field">
                                        <span>Recuo direito</span>
                                        <input type="number" min="0" step="0.5" :value="selectedThermalSection.padding_right_mm" @input="updateSection(selectedThermalSection.type, { padding_right_mm: Number($event.target.value) })">
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <aside class="thermal-preview-panel">
                        <div class="thermal-preview-head">
                            <div>
                                <p class="thermal-config-title">Prévia em milímetros</p>
                                <p class="thermal-config-copy">Arraste os blocos no papel para alterar a ordem de impressão.</p>
                            </div>
                            <span>{{ thermalLayout.paper.width_mm }}mm</span>
                        </div>

                        <div class="thermal-preview-canvas">
                        <div class="thermal-preview" :style="{ width: `${thermalLayout.paper.width_mm}mm`, paddingTop: `${thermalLayout.paper.margin_top_mm}mm`, paddingRight: `${thermalLayout.paper.margin_right_mm}mm`, paddingBottom: `${thermalLayout.paper.margin_bottom_mm}mm`, paddingLeft: `${thermalLayout.paper.margin_left_mm}mm`, fontSize: `${thermalLayout.typography.base_font_pt}pt`, lineHeight: `${thermalLayout.item_layout.line_spacing_mm}mm` }">
                            <section
                                v-for="section in thermalPreviewSections"
                                :key="section.type"
                                class="thermal-preview-block"
                                :class="{
                                    'is-selected': selectedSectionType === section.type,
                                    'is-dragging': draggedSectionType === section.type,
                                    'is-drag-over': dragOverSectionType === section.type,
                                }"
                                :style="sectionPreviewStyle(section)"
                                :draggable="!isManagedByErp"
                                @click="selectedSectionType = section.type"
                                @dragstart="startSectionDrag($event, section.type)"
                                @dragenter.prevent="enterSectionDropZone(section.type)"
                                @dragover.prevent
                                @drop="dropSection($event, section.type)"
                                @dragend="endSectionDrag"
                            >
                                <span class="thermal-preview-block-label">
                                    <GripVertical aria-hidden="true" />
                                    {{ SECTION_LABELS[section.type] || section.type }}
                                </span>
                                <template v-if="section.type === 'logo'">
                                    <div v-if="fiscal.logo_url" class="thermal-preview-logo"><img :src="fiscal.logo_url" alt="Logo"></div>
                                    <div v-else class="thermal-preview-logo-placeholder">LOGO</div>
                                </template>
                                <template v-else-if="section.type === 'header'">
                                    <strong class="thermal-preview-brand">{{ previewCompany.nome_fantasia }}</strong>
                                    <span>{{ previewCompany.nome_fantasia }}</span>
                                    <span>{{ previewCompany.logradouro }}, {{ previewCompany.numero }} - {{ previewCompany.bairro }}</span>
                                    <span>CNPJ: {{ previewCompany.cnpj }} IE: {{ previewCompany.inscricao_estadual }}</span>
                                    <hr><strong>DOC. AUXILIAR DA NOTA FISCAL DE CONSUMIDOR ELETRONICA</strong>
                                </template>
                                <template v-else-if="section.type === 'recipient'"><strong>CONSUMIDOR NAO IDENTIFICADO</strong><hr></template>
                                <template v-else-if="section.type === 'items'">
                                    <div class="thermal-preview-table">
                                        <div class="thermal-preview-table-row is-head"><span>CODIGO</span><span>DESCRICAO</span><span>QTD</span><span>UN</span><span>VLR-UN</span><span>TOTAL</span></div>
                                        <div v-for="item in previewItems" :key="item.code" class="thermal-preview-table-row" :style="{ marginBottom: `${thermalLayout.item_layout.item_spacing_mm}mm` }">
                                            <span>{{ item.code }}</span><span :style="itemDescriptionPreviewStyle()">{{ item.description }}</span><span>{{ item.quantity }}</span><span>{{ item.unit }}</span><span>{{ item.unitPrice }}</span><span>{{ item.total }}</span>
                                        </div>
                                    </div><hr>
                                </template>
                                <template v-else-if="section.type === 'totals'"><span>Subtotal 9,96</span><strong class="thermal-preview-total" :style="{ fontSize: `${thermalLayout.typography.total_font_pt}pt` }">TOTAL R$ 9,96</strong></template>
                                <template v-else-if="section.type === 'payments'"><span>CARTAO 9,96</span></template>
                                <template v-else-if="section.type === 'ibpt'"><span>Trib aprox R$ 1,34 Fed. e 1,20 Est.</span><span>Fonte: IBPT SC</span></template>
                                <template v-else-if="section.type === 'messages'"><span>Obrigado pela preferencia. Volte sempre.</span></template>
                                <template v-else-if="section.type === 'protocol_footer'"><hr><strong>N:214694 Serie:4 Data:02/06/26 09:46:46-Via Consumidor</strong></template>
                                <template v-else-if="section.type === 'consultation'"><span>Consulte pela Chave de Acesso em</span><span>https://sat.sef.sc.gov.br/nfce/consulta</span><span>4226 0628 4462 9400 0107 6500 4000 2146 9413 4964 1296</span></template>
                                <template v-else-if="section.type === 'qr_code'"><div class="thermal-preview-qr" :style="{ width: `${thermalLayout.paper.qr_size_mm}mm`, height: `${thermalLayout.paper.qr_size_mm}mm` }">QR</div></template>
                            </section>
                        </div>
                        </div>
                    </aside>
                </div>
            </SettingsSectionCard>
            </fieldset>
        </template>

        <template v-else-if="activeTab === 'certificado'">
            <fieldset :disabled="certificateLocked" class="company-managed-fieldset">
            <SettingsSectionCard title="Certificado Digital" subtitle="Necessário para assinar e transmitir documentos fiscais">
                <div class="grid grid-cols-1 gap-4">
                    <div v-if="certificateManagedByPlatform" class="certificate-platform-card">
                        <p class="certificate-platform-title">Certificado carregado da NotaAgil</p>
                        <p class="certificate-platform-copy">
                            O PDV vai usar o certificado já cadastrado na plataforma. Upload e senha locais ficam desativados.
                        </p>
                        <div class="certificate-platform-grid">
                            <span><strong>Status:</strong> {{ certificateStatusLabel }}</span>
                            <span v-if="certificate.validade"><strong>Validade:</strong> {{ certificate.validade }}</span>
                            <span v-if="certificate.cnpj_cpf"><strong>Documento:</strong> {{ certificate.cnpj_cpf }}</span>
                            <span v-if="certificate.emissor"><strong>Emissor:</strong> {{ certificate.emissor }}</span>
                            <span v-if="certificate.dias_restantes !== null"><strong>Dias restantes:</strong> {{ certificate.dias_restantes }}</span>
                            <span v-if="certificate.numero_serie"><strong>Série:</strong> {{ certificate.numero_serie }}</span>
                        </div>
                    </div>

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
    grid-template-columns: repeat(auto-fit, minmax(8.5rem, 1fr));
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
    border: 1px solid var(--color-border-strong);
    background: color-mix(in srgb, var(--color-border-strong) 55%, var(--color-bg-surface));
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

.certificate-platform-card {
    border: 1px solid color-mix(in srgb, var(--color-success, #16a34a) 36%, var(--color-border));
    border-radius: var(--radius-md);
    background: color-mix(in srgb, var(--color-success, #16a34a) 9%, var(--color-bg-surface));
    padding: 1rem;
}

.certificate-platform-title {
    margin: 0;
    color: var(--color-text);
    font-size: 1rem;
    font-weight: 800;
}

.certificate-platform-copy {
    margin: 0.25rem 0 0;
    color: var(--color-text-muted);
    font-size: 0.9rem;
    line-height: 1.4;
}

.certificate-platform-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
    gap: 0.45rem 0.9rem;
    margin-top: 0.8rem;
    color: var(--color-text);
    font-size: 0.88rem;
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

.thermal-editor-shell {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(20rem, 25rem);
    gap: var(--space-5);
    align-items: start;
}

.thermal-editor-controls,
.thermal-logo-actions,
.thermal-section-list {
    display: grid;
    gap: var(--space-3);
}

.thermal-config-card {
    display: grid;
    gap: var(--space-4);
    padding: var(--space-4);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    background: var(--color-bg-surface);
}

.thermal-logo-card {
    display: grid;
    grid-template-columns: 7rem minmax(0, 1fr);
    gap: var(--space-4);
    align-items: center;
    background: var(--color-bg-elevated);
}

.thermal-logo-preview {
    width: 7rem;
    height: 7rem;
    border: 1px dashed var(--color-border-strong);
    border-radius: var(--radius-sm);
    display: grid;
    place-items: center;
    color: var(--color-text-muted);
    background: var(--color-bg-surface);
    overflow: hidden;
}

.thermal-logo-preview img,
.thermal-preview-logo img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.thermal-config-title {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 800;
    color: var(--color-text);
}

.thermal-config-copy {
    margin: 0;
    font-size: 0.85rem;
    line-height: 1.45;
    color: var(--color-text-muted);
}

.thermal-config-head,
.thermal-actions-inline,
.thermal-section-list-head,
.thermal-section-row,
.thermal-section-toggle,
.thermal-order-actions {
    display: flex;
    align-items: center;
    gap: var(--space-2);
}

.thermal-config-head,
.thermal-section-list-head,
.thermal-section-row {
    justify-content: space-between;
}

.thermal-controls-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: var(--space-3);
}

.thermal-field,
.thermal-range-field {
    display: grid;
    gap: 0.35rem;
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--color-text-muted);
}

.thermal-field input,
.thermal-field select,
.thermal-section-row select {
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
    min-height: 2.5rem;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    padding: 0 0.7rem;
    background: var(--color-bg-surface);
    color: var(--color-text);
}

.thermal-range-row {
    min-height: 2.5rem;
    display: grid;
    grid-template-columns: minmax(0, 1fr) 4.5rem;
    align-items: center;
    gap: var(--space-3);
}

.thermal-range-row input[type='range'] {
    width: 100%;
    accent-color: var(--color-primary);
}

.thermal-range-row strong {
    text-align: right;
    color: var(--color-text);
    font-size: 0.9rem;
}

.thermal-wide-field {
    grid-column: 1 / -1;
}

.thermal-section-row {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) minmax(7.5rem, auto) auto;
    align-items: center;
    gap: var(--space-2);
    min-height: 3rem;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    padding: var(--space-2);
    cursor: grab;
    transition: border-color var(--transition-fast), background var(--transition-fast), opacity var(--transition-fast);
}

.thermal-section-row.is-selected {
    border-color: color-mix(in srgb, var(--color-primary) 65%, var(--color-border));
    background: color-mix(in srgb, var(--color-primary) 7%, var(--color-bg-surface));
}

.thermal-section-row.is-dragging {
    opacity: 0.45;
}

.thermal-section-row.is-drag-over {
    border-top-color: var(--color-primary);
    box-shadow: inset 0 2px 0 var(--color-primary);
}

.thermal-drag-handle {
    width: 1rem;
    height: 1rem;
    color: var(--color-text-muted);
    cursor: grab;
}

.thermal-section-toggle {
    min-width: 0;
    flex: 1;
    font-size: 0.9rem;
    font-weight: 700;
}

.thermal-section-toggle small {
    color: var(--color-text-muted);
    font-weight: 600;
}

.thermal-order-actions button {
    width: 2rem;
    height: 2rem;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    background: var(--color-bg-surface);
    color: var(--color-text);
}

.thermal-order-actions button:disabled {
    opacity: 0.4;
}

.thermal-section-inspector {
    display: grid;
    gap: var(--space-3);
    margin-top: var(--space-2);
    padding: var(--space-4);
    border: 1px solid color-mix(in srgb, var(--color-primary) 24%, var(--color-border));
    border-radius: var(--radius-md);
    background: color-mix(in srgb, var(--color-primary) 5%, var(--color-bg-surface));
}

.thermal-preview-panel {
    display: grid;
    gap: var(--space-4);
    position: sticky;
    top: var(--space-4);
    max-height: calc(100vh - (var(--space-4) * 2));
    overflow: auto;
    padding: 1px var(--space-2) var(--space-3) 1px;
}

.webhook-events-panel {
    display: grid;
    gap: var(--space-2);
    padding: var(--space-4);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    background: var(--color-bg-muted);
}

.webhook-events-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: var(--space-3);
}

.webhook-events-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: var(--space-2);
}

.webhook-events-title {
    margin: 0;
    font-size: 0.82rem;
    font-weight: 800;
    color: var(--color-text);
}

.webhook-events-meta {
    margin: 0.2rem 0 0;
    font-size: 0.78rem;
    color: var(--color-text-muted);
}

.webhook-events-list {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-2);
}

.webhook-events-list code {
    padding: 0.2rem 0.45rem;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    background: var(--color-bg-surface);
    color: var(--color-text-muted);
    font-size: 0.76rem;
}

.webhook-events-error {
    margin: 0;
    color: var(--color-danger, #b42318);
    font-size: 0.82rem;
}

.thermal-preview-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: var(--space-3);
    padding: var(--space-4);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    background: var(--color-bg-surface);
}

.thermal-preview-head span {
    font-size: 0.8rem;
    font-weight: 800;
    color: var(--color-primary);
}

.thermal-preview-canvas {
    display: grid;
    justify-items: center;
    min-height: 28rem;
    padding: var(--space-4) 2.5rem var(--space-6);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    background:
        linear-gradient(var(--color-border) 1px, transparent 1px),
        linear-gradient(90deg, var(--color-border) 1px, transparent 1px),
        var(--color-bg-elevated);
    background-size: 1rem 1rem;
    overflow: hidden;
}

.thermal-preview {
    max-width: 100%;
    margin-inline: auto;
    border: 1px dashed var(--color-border-strong);
    border-radius: var(--radius-md);
    background: #fff;
    color: #111;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    line-height: 1.35;
    display: grid;
    gap: 0.18rem;
    box-shadow: var(--shadow-md);
}

.thermal-preview-block {
    position: relative;
    cursor: grab;
    outline: 1px solid transparent;
    outline-offset: 1mm;
    transition: opacity var(--transition-fast), outline-color var(--transition-fast), background var(--transition-fast);
}

.thermal-preview-block:hover,
.thermal-preview-block.is-selected {
    outline-color: color-mix(in srgb, var(--color-primary) 75%, transparent);
    background: color-mix(in srgb, var(--color-primary) 5%, transparent);
}

.thermal-preview-block.is-dragging {
    opacity: 0.35;
}

.thermal-preview-block.is-drag-over {
    box-shadow: inset 0 2px 0 var(--color-primary);
}

.thermal-preview-block-label {
    position: absolute;
    top: 0;
    right: 0;
    z-index: 1;
    display: none !important;
    align-items: center;
    gap: 0.2rem;
    max-width: 8rem;
    padding: 0.18rem 0.35rem;
    border-radius: 0.35rem;
    background: var(--color-primary);
    color: var(--color-text-inverse);
    font-family: var(--font-sans);
    font-size: 0.62rem;
    font-weight: 800;
    line-height: 1;
    white-space: nowrap;
}

.thermal-preview-block-label svg {
    width: 0.7rem;
    height: 0.7rem;
}

.thermal-preview-block:hover .thermal-preview-block-label,
.thermal-preview-block.is-selected .thermal-preview-block-label {
    display: inline-flex !important;
}

.thermal-preview span,
.thermal-preview strong {
    display: block;
    overflow-wrap: anywhere;
}

.thermal-preview-logo {
    width: 26mm;
    height: 16mm;
    margin: 0 auto;
}

.thermal-preview-logo-placeholder {
    width: 26mm;
    min-height: 10mm;
    margin: 0 auto;
    border: 1px dashed #b8b8b8;
    display: grid;
    place-items: center;
    color: #777;
}

.thermal-preview hr {
    width: 100%;
    border: 0;
    border-top: 1px dashed #999;
}

.thermal-preview-total {
    text-align: right;
}

.thermal-preview-table {
    display: grid;
    gap: 0.35mm;
}

.thermal-preview-table-row {
    display: grid;
    grid-template-columns: 13mm minmax(0, 1fr) 7mm 4mm 8mm 9mm;
    gap: 0.8mm;
    align-items: start;
}

.thermal-preview-table-row.is-head {
    font-weight: 800;
}

.thermal-preview-table-row span {
    overflow: hidden;
    text-overflow: clip;
    white-space: nowrap;
}

.thermal-preview-table-row span:nth-child(n + 3) {
    text-align: right;
}

.thermal-preview-table-row span:nth-child(2) {
    white-space: normal;
}

.thermal-preview-brand,
.thermal-preview-item strong {
    font-weight: 800;
}

.thermal-preview-item {
    border-bottom: 1px dotted #d4d4d4;
    padding-bottom: 0.4mm;
}

.thermal-preview-qr {
    margin: 0.35rem auto 0;
    border: 1px dashed #777;
    display: grid;
    place-items: center;
    font-size: 10px;
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

    .thermal-editor-shell,
    .thermal-logo-card {
        grid-template-columns: 1fr;
    }

    .thermal-preview-panel {
        position: static;
        max-height: none;
        overflow: visible;
        padding: 0;
    }
}

@media (max-width: 640px) {
    .thermal-controls-grid,
    .thermal-section-row {
        grid-template-columns: 1fr;
    }

    .thermal-drag-handle {
        display: none;
    }

    .thermal-order-actions {
        justify-content: flex-end;
    }
}
</style>
