<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import {
    BookText,
    Boxes,
    BriefcaseBusiness,
    Camera,
    Clock3,
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
    CirclePlus,
    FileText,
    Filter,
    Info,
    ImagePlus,
    Layers3,
    Link as LinkIcon,
    Link2,
    MapPin,
    Network,
    PackageOpen,
    Percent,
    Plus,
    Ruler,
    Search,
    ShieldCheck,
    Snowflake,
    Flame,
    AlertTriangle,
    Bug,
    MoreHorizontal,
    ShoppingCart,
    Sprout,
    Trash2,
    Droplets,
    DollarSign,
} from 'lucide-vue-next';
import api from '../../lib/api';
import AppButton from '../../components/ui/AppButton.vue';
import AppInput from '../../components/ui/AppInput.vue';
import AppCombobox from '../../components/ui/AppCombobox.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppTextarea from '../../components/ui/AppTextarea.vue';
import AppCheckbox from '../../components/ui/AppCheckbox.vue';
import AppTable from '../../components/ui/AppTable.vue';
import AppBadge from '../../components/ui/AppBadge.vue';
import AppModal from '../../components/ui/AppModal.vue';
import AppToast from '../../components/ui/AppToast.vue';
import SettingsEmptyState from '../../components/settings/SettingsEmptyState.vue';
import {
    calculateCompositionCosts,
    calculateSuggestedPricing,
    parseCompositionOrderValue,
    resolveCompositionBranchColors,
} from '../../composables/useProductCompositionTree';

const route = useRoute();
const router = useRouter();

const loading = ref(false);
const saving = ref(false);
const error = ref('');
const validationIssues = ref([]);
const toastVisible = ref(false);
const toastMessage = ref('');
const toastTone = ref('success');
const activeTab = ref('dados_basicos');
const barcodeSearch = ref('');
const barcodeRowsPerPage = ref(10);
const barcodePage = ref(1);
const barcodeModalOpen = ref(false);
const barcodeModalEditingIndex = ref(null);
const compositionModalOpen = ref(false);
const compositionModalEditingRowId = ref('');
const compositionProductOptions = ref([]);
const compositionProductLoading = ref(false);
const photoPickerModalOpen = ref(false);
const activePhotoIndex = ref(null);
const photoLinkDraft = ref('');
const estoqueSubTab = ref('dados_basicos');
const gerencialMemoryExpanded = ref(true);
const infoAdicionalSubTab = ref('composicao');
const composicaoViewMode = ref('grid');
const composicaoSearch = ref('');
const composicaoRowsPerPage = ref(10);
const composicaoPage = ref(1);
const compositionOrgZoom = ref(0.82);
const compositionOrgSelectedNodeId = ref('');
const compositionOrgSelectedNodeIds = ref([]);
const compositionOrgHoveredNodeId = ref('');
const compositionOrgPendingParentId = ref('');
const compositionGridPendingParentId = ref('');
const compositionOrgPan = reactive({ x: 0, y: 0 });
const compositionOrgRootPosition = reactive({ x: 520, y: 80 });
const compositionOrgPointer = reactive({
    mode: '',
    nodeId: '',
    targetNodeId: '',
    startX: 0,
    startY: 0,
    startPanX: 0,
    startPanY: 0,
    startNodeX: 0,
    startNodeY: 0,
    startNodePositions: {},
    dragX: 0,
    dragY: 0,
    hasDragged: false,
});
const compositionGridPointer = reactive({
    active: false,
    parentId: '',
    targetId: '',
    startX: 0,
    startY: 0,
    x: 0,
    y: 0,
    hasDragged: false,
});
const compositionPricingDraft = reactive({
    taxes_percent: '0.10',
    desired_margin: '0.25',
});
const historicoPage = ref(1);
const historicoRowsPerPage = ref(10);
const historicoModalOpen = ref(false);
const historicoModalAudit = ref(null);
const historicoFilterDraft = reactive({
    data_inicio: '',
    data_fim: '',
    evento: '',
    usuario: '',
});
const historicoFilterApplied = reactive({
    data_inicio: '',
    data_fim: '',
    evento: '',
    usuario: '',
});

const tabs = [
    { id: 'dados_basicos', label: 'Dados Básicos' },
    { id: 'dados_opcionais', label: 'Dados Opcionais' },
    { id: 'codigo_barras', label: 'Código de Barras' },
    { id: 'informacao_adicional', label: 'Informação Adicional' },
    { id: 'estoque', label: 'Estoque' },
    { id: 'gerencial', label: 'Gerencial' },
    { id: 'historico', label: 'Histórico' },
];

const estoqueSubTabs = [
    { id: 'dados_basicos', label: 'Dados Basicos' },
    { id: 'codigo_fornecedor', label: 'Codigo do Fornecedor' },
    { id: 'saldo_lotes', label: 'Saldo de Lotes' },
    { id: 'dimensoes', label: 'Dimensoes' },
    { id: 'unidades_embalagens', label: 'Unidades e Embalagens' },
];

const infoAdicionalSubTabs = [
    { id: 'composicao', label: 'Composição' },
    { id: 'foto', label: 'Foto' },
];

const supportData = reactive({
    unidades_medida: [],
    familias: [],
    classificacoes_mercadologicas: [],
    fiscal_item_profiles: [],
    tipos_preco: [],
    situacoes: [],
    produto_tipos: [],
});

function createEmptyPhotoSlot() {
    return { nome: '', url: '' };
}

function normalizePhotoSlots(source) {
    const normalized = (Array.isArray(source) ? source : [])
        .map((photo) => ({
            nome: String(photo?.nome || '').trim(),
            url: String(photo?.url || '').trim(),
        }))
        .filter((photo) => photo.nome !== '' || photo.url !== '');

    return normalized.length > 0 ? normalized : [createEmptyPhotoSlot()];
}

const parameterQuickModalOpen = ref(false);
const parameterQuickModalType = ref('familia');
const parameterQuickModalSaving = ref(false);
const parameterQuickModalError = ref('');
const parameterQuickFormErrors = reactive({
    familiaCodigo: '',
    familiaNome: '',
    unidadeCodigo: '',
    unidadeDescricao: '',
    unidadeDecimais: '',
    classificacaoCodigo: '',
    classificacaoDescricao: '',
});
const quickFamilyForm = reactive({
    codigo: '',
    nome: '',
    ativo: true,
});
const quickUnitForm = reactive({
    unidade: '',
    descricao: '',
    decimais: '0',
    status: true,
});
const quickClassificationForm = reactive({
    codigo: '',
    descricao: '',
    parent_id: '',
    ativo: true,
});

const form = reactive({
    id: '',
    estabelecimento_id: '',
    produto_mestre_id: '',
    fiscal_item_profile_id: '',
    fiscal_item_profile_entrada_id: '',
    fiscal_item_profile_saida_id: '',
    classificacao_mercadologica_id: '',
    unidade_medida_id: '',
    produto_familia_id: '',
    cod_sku: '',
    codigo_operacional: '',
    codigo_operacional_manual: false,
    descricao: '',
    descricao_curta: '',
    produto_tipo: 'mercadoria',
    situacao: 'ativo',
    liberado: 'sim',
    marca: '',
    palavra_chave: '',
    conta_contabil: '',
    nr_contrato: '',
    classificacoes_niveis_adicionais: [''],
    descricao_site: '',
    descricao_detalhada: '',
    empresa_combo: '',
    cliente_combo: '',
    empresas_vinculadas: [],
    clientes_vinculados: [],
    ean_tipo: 'GTIN-13',
    ean_codigo: '',
    fiscal_ncm: '',
    fiscal_ncm_ex: '',
    fiscal_cest: '',
    created_at: '',
    updated_at: '',
    permite_fracionamento: false,
    atributos_logisticos_json: '{}',
    precos: [],
    codigos_barras: [],
    estoque: {
        quantidade: '0',
        quantidade_minima: '',
        quantidade_maxima: '',
        numero_lote: '',
        reduzir_estoque: true,
        quantidade_minima_vendavel: '',
        quantidade_alerta: '',
    },
    estoque_detalhado: {
        consumo_medio_diario: '',
        lead_time_compra: '',
        lead_time_entrega: '',
        lead_time_recebimento: '',
        estoque_seguranca: '',
        lote_minimo_compra: '',
        frequencia_compra: '',
        ponto_pedido: '',
        ponto_pedido_override: false,
        nao_fracionado: 'nao',
        controla_validade_lote: 'sim',
        vida_util_padrao: '',
        controla_enderecamento: 'nao',
        transgenico: 'nao',
        atributos_logisticos_flags: {
            controla_lote: false,
            refrigerado: false,
            controla_enderecamento: false,
            inflamavel: false,
            fragil: false,
            empilhavel: false,
            pesavel: false,
            toxico: false,
            corrosivo: false,
            e_commerce: false,
            agronomico: false,
        },
        endereco_controlado: false,
        filial: '',
        deposito_armazem: '',
        local_estoque: '',
        rua: '',
        modulo: '',
        prateleira: '',
        nivel: '',
        posicao: '',
        codigo_fornecedor: '',
        fornecedor_ultima_referencia: '',
        referencia_custo_data: '',
        codigo_barras_fornecedor: '',
        custo_ultima_compra: '',
        lead_time_fornecedor: '',
        lote_minimo_fornecedor: '',
        saldo_lotes_rows: [],
        saldo_consolidado_rows: [],
        dimensoes_embalado: {
            peso_bruto: '',
            altura: '',
            largura: '',
            comprimento: '',
            volume: '',
        },
        dimensoes_sem_embalagem: {
            peso_liquido: '',
            altura: '',
            largura: '',
            comprimento: '',
            volume: '',
        },
        espessura: '',
        densidade: '',
        unidade_base_estoque: '',
        unidade_compra: '',
        unidade_venda: '',
        embalagens: [],
    },
    gerencial_memoria: {
        custo_compra: '',
        frete: '',
        seguro: '',
        despesas_acessorias: '',
        desconto: '',
        ipi: '',
        icms_st: '',
        impostos_recuperaveis: '',
        custo_financeiro: '',
        custo_reposicao: '',
        custo_real: '',
        preco_venda_atual: '',
        margem_nominal: '',
        margem_real: '',
        custo_referencial_manual: '',
    },
    informacao_adicional: {
        composicoes: [],
        fotos: [createEmptyPhotoSlot()],
    },
    auditoria: [],
});

const isEditing = computed(() => !!form.id);
const currentProductId = computed(() => String(route.params.produtoId || ''));
const isCreateRoute = computed(() => currentProductId.value === '' || currentProductId.value === 'novo');
const canSave = computed(() => form.descricao.trim() !== '');
const shortDescriptionLength = computed(() => String(form.descricao_curta || '').trim().length);
const descricaoSiteLength = computed(() => String(form.descricao_site || '').trim().length);
const descricaoDetalhadaLength = computed(() => String(form.descricao_detalhada || '').trim().length);
const parameterQuickModalTitle = computed(() => {
    if (parameterQuickModalType.value === 'familia') return 'Nova família';
    if (parameterQuickModalType.value === 'unidade') return 'Nova unidade de medida';
    return 'Nova classificação mercadológica';
});

const formattedCreatedAt = computed(() => normalizeDateString(form.created_at));
const formattedUpdatedAt = computed(() => normalizeDateString(form.updated_at));
const barcodeModalForm = reactive({
    tipo_codigo: 'GTIN-13',
    codigo: '',
    informacoes_complementares: '',
    situacao: 'ativo',
    tipo_codigo_caixa: 'GTIN-14',
    codigo_caixa: '',
    sku: '',
});
const compositionModalForm = reactive({
    produto_id: '',
    produto: '',
    quantidade: '1',
    ordem: '',
    observacao: '',
    calculate_cost: true,
    operational_cost: '0',
    campos_adicionais: [],
});

let compositionProductSearchTimer = null;
let toastTimeout = null;

const compositionFieldNameTemplates = [
    { id: 'personalizado', label: 'Personalizado' },
    { id: 'tempo_preparo', label: 'Tempo de preparo' },
    { id: 'tempo_cura_secagem', label: 'Tempo de cura/secagem' },
    { id: 'temperatura_aplicacao', label: 'Temperatura de aplicacao' },
    { id: 'lote_origem', label: 'Lote de origem' },
    { id: 'validade_final', label: 'Validade final' },
    { id: 'instrucao_tecnica', label: 'Instrucao tecnica' },
    { id: 'aplicacao_uso', label: 'Aplicacao/uso' },
    { id: 'quantidade_por_lote', label: 'Quantidade por lote' },
    { id: 'unidade_saida', label: 'Unidade de saida' },
    { id: 'exige_epi', label: 'Exige EPI' },
];

const compositionFieldTypeOptions = [
    { id: 'texto_curto', label: 'Texto curto' },
    { id: 'texto_longo', label: 'Texto longo' },
    { id: 'numero_inteiro', label: 'Numero inteiro' },
    { id: 'numero_decimal', label: 'Numero decimal' },
    { id: 'data', label: 'Data' },
    { id: 'sim_nao', label: 'Sim/Nao' },
    { id: 'checkbox_texto', label: 'Checkbox + texto' },
];

const compositionProductOptionLabels = computed(() => (
    Array.isArray(compositionProductOptions.value)
        ? compositionProductOptions.value
            .map((option) => String(option?.label || '').trim())
            .filter(Boolean)
        : []
));

const empresasComboboxOptions = [
    'Freeline Matriz',
    'Freeline Sul',
    'Freeline Norte',
    'Filial Centro',
];

const clientesComboboxOptions = [
    'Cliente Varejo A',
    'Cliente Atacado B',
    'Cliente Key Account C',
    'Cliente Marketplace D',
];

const barcodeFilteredRows = computed(() => {
    const needle = String(barcodeSearch.value || '').trim().toLowerCase();
    if (!needle) {
        return form.codigos_barras;
    }

    return form.codigos_barras.filter((row) => {
        const code = String(row.codigo || '').toLowerCase();
        const extra = String(row.informacoes_complementares || '').toLowerCase();
        const tipo = String(row.tipo_codigo || '').toLowerCase();
        return code.includes(needle) || extra.includes(needle) || tipo.includes(needle);
    });
});

const barcodeTotalPages = computed(() => {
    const total = barcodeFilteredRows.value.length;
    const perPage = Number(barcodeRowsPerPage.value || 10);
    return Math.max(1, Math.ceil(total / perPage));
});

const barcodePagedRows = computed(() => {
    const page = Math.min(barcodePage.value, barcodeTotalPages.value);
    const perPage = Number(barcodeRowsPerPage.value || 10);
    const start = (page - 1) * perPage;
    const end = start + perPage;
    return barcodeFilteredRows.value.slice(start, end);
});

const composicaoFilteredRows = computed(() => {
    const needle = String(composicaoSearch.value || '').trim().toLowerCase();
    if (!needle) {
        return form.informacao_adicional.composicoes;
    }

    return form.informacao_adicional.composicoes.filter((row) => {
        const produto = String(row.produto || '').toLowerCase();
        const observacao = String(row.observacao || '').toLowerCase();
        return produto.includes(needle) || observacao.includes(needle);
    });
});

const compositionTreeState = computed(() => resolveCompositionBranchColors(form.informacao_adicional.composicoes, { rootId: 'root' }));

const composicaoOrderedRows = computed(() => {
    const needle = String(composicaoSearch.value || '').trim().toLowerCase();
    const orderedRows = Array.isArray(compositionTreeState.value.orderedRows) ? compositionTreeState.value.orderedRows : [];
    if (!needle) return orderedRows;

    return orderedRows.filter((entry) => {
        const row = entry?.source || {};
        const produto = String(row.produto || '').toLowerCase();
        const observacao = String(row.observacao || '').toLowerCase();
        return produto.includes(needle) || observacao.includes(needle);
    });
});

const composicaoTotalPages = computed(() => {
    const total = composicaoOrderedRows.value.length;
    const perPage = Number(composicaoRowsPerPage.value || 10);
    return Math.max(1, Math.ceil(total / perPage));
});

const composicaoPagedRows = computed(() => {
    const page = Math.min(composicaoPage.value, composicaoTotalPages.value);
    const perPage = Number(composicaoRowsPerPage.value || 10);
    const start = (page - 1) * perPage;
    return composicaoOrderedRows.value.slice(start, start + perPage);
});

const isEditingCompositionModal = computed(() => String(compositionModalEditingRowId.value || '').trim() !== '');

const compositionCurrentProductLabel = computed(() => {
    const descricao = String(form.descricao || '').trim();
    const sku = String(form.cod_sku || '').trim();
    const codigoOperacional = String(form.codigo_operacional || '').trim();
    const pieces = [];
    if (sku) pieces.push(`SKU: ${sku}`);
    if (codigoOperacional) pieces.push(`COD: ${codigoOperacional}`);
    if (!descricao && pieces.length === 0) return 'Produto atual em edição';
    if (pieces.length === 0) return descricao;
    return `${descricao || 'Produto atual'} (${pieces.join(' • ')})`;
});

function getCompositionRootBaseCost() {
    const fromMemory = toDecimal(form.gerencial_memoria.custo_real);
    if (fromMemory > 0) return fromMemory;
    const activePrice = form.precos.find((row) => row.ativo) || form.precos[0] || null;
    return toDecimal(activePrice?.custo_referencial);
}

function getCompositionSalePrice() {
    const fromMemory = toDecimal(form.gerencial_memoria.preco_venda_atual);
    if (fromMemory > 0) return fromMemory;
    const activePrice = form.precos.find((row) => row.ativo) || form.precos[0] || null;
    return toDecimal(activePrice?.valor);
}

const compositionCostState = computed(() => calculateCompositionCosts(form.informacao_adicional.composicoes, {
    rootId: 'root',
    rootBaseCost: getCompositionRootBaseCost(),
    rootOperationalCost: 0,
    rootCalculateCost: true,
}));

const compositionCostSummary = computed(() => compositionCostState.value.summary || {
    own_cost: 0,
    accumulated_cost: 0,
    component_cost: 0,
    operational_cost_total: 0,
    total_items: 1,
    cost_participants: 1,
    cost_ignored: 0,
});

const compositionPricingSummary = computed(() => {
    const taxesRate = Math.max(0, parseDecimalRate(compositionPricingDraft.taxes_percent, 0.1));
    const desiredMargin = Math.max(0, parseDecimalRate(compositionPricingDraft.desired_margin, 0.25));

    return calculateSuggestedPricing(compositionCostSummary.value, {
        taxesRate,
        desiredMargin,
        salePrice: getCompositionSalePrice(),
    });
});

const compositionGridChildrenCountByParent = computed(() => {
    const childrenByParent = compositionTreeState.value.childrenByParent || {};
    return Object.keys(childrenByParent).reduce((acc, parentId) => {
        acc[parentId] = Array.isArray(childrenByParent[parentId]) ? childrenByParent[parentId].length : 0;
        return acc;
    }, {});
});

function getCompositionNodeBranchMeta(nodeId = 'root') {
    return compositionTreeState.value.branchByNodeId?.[String(nodeId || 'root')]
        || compositionTreeState.value.branchByNodeId?.root
        || {
            branchColor: '#f43f5e',
            branchIndex: -1,
            level: 1,
            parentId: null,
            rootChildId: 'root',
        };
}

function getCompositionNodeBranchColor(nodeId = 'root') {
    return getCompositionNodeBranchMeta(nodeId).branchColor || '#f43f5e';
}

const COMPOSITION_ORG_NODE_WIDTH = 300;
const COMPOSITION_ORG_NODE_HEIGHT = 150;

const compositionOrgNodes = computed(() => {
    const costsById = compositionCostState.value.nodeCostById || {};
    const rootCosts = costsById.root || {
        own_cost: 0,
        accumulated_cost: 0,
    };
    const rootNode = {
        id: 'root',
        type: 'root',
        label: compositionCurrentProductLabel.value,
        subtitle: 'Produto atual',
        sku: String(form.cod_sku || '-').trim() || '-',
        ean: String(form.ean_codigo || '-').trim() || '-',
        quantity: '',
        level: 1,
        color: getCompositionNodeBranchColor('root'),
        branchColor: getCompositionNodeBranchColor('root'),
        branchIndex: -1,
        childrenCount: form.informacao_adicional.composicoes.length,
        ownCost: rootCosts.own_cost || 0,
        accumulatedCost: rootCosts.accumulated_cost || 0,
        position: { x: compositionOrgRootPosition.x, y: compositionOrgRootPosition.y },
        source: null,
    };

    const componentNodes = (compositionTreeState.value.orderedRows || []).map((item, index) => {
        const row = item.source || {};
        const nodeId = String(row.id || `component-${index}`);
        const branchMeta = getCompositionNodeBranchMeta(nodeId);
        const parentId = String(row.parent_id || 'root');
        const nodeCosts = costsById[nodeId] || { own_cost: 0, accumulated_cost: 0 };
        const level = parentId === 'root' ? 2 : 3;
        const x = Number.isFinite(Number(row.org_x))
            ? Number(row.org_x)
            : 220 + ((index % 3) * 320);
        const y = Number.isFinite(Number(row.org_y))
            ? Number(row.org_y)
            : 300 + (Math.floor(index / 3) * 180);
        return {
            id: nodeId,
            type: 'component',
            label: String(row.produto || 'Componente'),
            subtitle: 'Componente',
            sku: String(row.produto_sku || '-').trim() || '-',
            ean: String(row.produto_ean || '-').trim() || '-',
            quantity: row.quantidade,
            observation: String(row.observacao || ''),
            level,
            color: branchMeta.branchColor || getCompositionNodeBranchColor(nodeId),
            branchColor: branchMeta.branchColor || getCompositionNodeBranchColor(nodeId),
            branchIndex: branchMeta.branchIndex,
            parentId,
            childrenCount: compositionGridChildrenCountByParent.value[nodeId] || 0,
            additionalFieldsCount: Array.isArray(row.campos_adicionais) ? row.campos_adicionais.length : 0,
            sequenceLabel: item.sequenciaLabel,
            ownCost: nodeCosts.own_cost || 0,
            accumulatedCost: nodeCosts.accumulated_cost || 0,
            position: { x, y },
            source: row,
        };
    });

    return [rootNode, ...componentNodes];
});

const compositionOrgNodeMap = computed(() => {
    return compositionOrgNodes.value.reduce((acc, node) => {
        acc[node.id] = node;
        return acc;
    }, {});
});

const compositionOrgEdges = computed(() => {
    return compositionOrgNodes.value
        .filter((node) => node.type === 'component')
        .map((node) => ({
            id: `${node.parentId || 'root'}-${node.id}`,
            from: compositionOrgNodeMap.value[node.parentId] || compositionOrgNodeMap.value.root,
            to: node,
            color: node.color || getCompositionNodeBranchColor(node.id),
        }))
        .filter((edge) => edge.from && edge.to);
});

const compositionOrgSelectedNode = computed(() => {
    return compositionOrgNodeMap.value[compositionOrgSelectedNodeId.value] || null;
});

const compositionOrgHoveredNode = computed(() => {
    return compositionOrgNodeMap.value[compositionOrgHoveredNodeId.value] || null;
});

const compositionOrgDraftEdge = computed(() => {
    if (compositionOrgPointer.mode !== 'connect') return null;
    const from = compositionOrgNodeMap.value[compositionOrgPointer.nodeId];
    if (!from) return null;
    return {
        from,
        toPosition: {
            x: compositionOrgPointer.dragX,
            y: compositionOrgPointer.dragY,
        },
        color: from.color || getCompositionNodeBranchColor(from.id),
    };
});

const compositionOrgSelectionBox = computed(() => {
    if (compositionOrgPointer.mode !== 'select') return null;
    const left = Math.min(compositionOrgPointer.startNodeX, compositionOrgPointer.dragX);
    const top = Math.min(compositionOrgPointer.startNodeY, compositionOrgPointer.dragY);
    const width = Math.abs(compositionOrgPointer.dragX - compositionOrgPointer.startNodeX);
    const height = Math.abs(compositionOrgPointer.dragY - compositionOrgPointer.startNodeY);
    return { left, top, width, height };
});

const estoqueAtributosAtivosCount = computed(() => {
    return Object.values(form.estoque_detalhado.atributos_logisticos_flags || {}).filter(Boolean).length;
});

const gerencialAtivo = computed(() => form.precos.find((row) => row.ativo) || form.precos[0] || null);

const gerencialCustoBase = computed(() => toDecimal(gerencialAtivo.value?.custo_referencial));
const gerencialCustoReal = computed(() => {
    const fromMemory = toDecimal(form.gerencial_memoria.custo_real);
    return fromMemory > 0 ? fromMemory : gerencialCustoBase.value;
});
const gerencialPrecoVendaAtual = computed(() => {
    const fromMemory = toDecimal(form.gerencial_memoria.preco_venda_atual);
    return fromMemory > 0 ? fromMemory : toDecimal(gerencialAtivo.value?.valor);
});
const gerencialMargemReal = computed(() => {
    if (gerencialPrecoVendaAtual.value <= 0) return 0;
    return ((gerencialPrecoVendaAtual.value - gerencialCustoReal.value) / gerencialPrecoVendaAtual.value) * 100;
});
const gerencialPrecoSugerido = computed(() => {
    const margemAlvo = toDecimal(gerencialAtivo.value?.margem);
    if (margemAlvo <= 0 || margemAlvo >= 100) return gerencialPrecoVendaAtual.value;
    const denominator = 1 - (margemAlvo / 100);
    return denominator > 0 ? gerencialCustoReal.value / denominator : gerencialPrecoVendaAtual.value;
});
const gerencialPrecoMinimo = computed(() => {
    const margemMinima = toDecimal(gerencialAtivo.value?.margem_preco_minimo);
    if (margemMinima <= 0 || margemMinima >= 100) return 0;
    const denominator = 1 - (margemMinima / 100);
    return denominator > 0 ? gerencialCustoReal.value / denominator : 0;
});
const gerencialMarkupAtual = computed(() => {
    if (gerencialCustoReal.value <= 0) return 0;
    return ((gerencialPrecoVendaAtual.value / gerencialCustoReal.value) - 1) * 100;
});
const gerencialSubtotal = computed(() => {
    return toDecimal(form.gerencial_memoria.custo_compra)
        + toDecimal(form.gerencial_memoria.frete)
        + toDecimal(form.gerencial_memoria.seguro)
        + toDecimal(form.gerencial_memoria.despesas_acessorias)
        + toDecimal(form.gerencial_memoria.custo_financeiro)
        + toDecimal(form.gerencial_memoria.custo_reposicao)
        + toDecimal(form.gerencial_memoria.ipi)
        + toDecimal(form.gerencial_memoria.icms_st);
});
const gerencialTotal = computed(() => {
    return gerencialSubtotal.value - toDecimal(form.gerencial_memoria.desconto) - toDecimal(form.gerencial_memoria.impostos_recuperaveis);
});

const estoqueAtualTotalCalc = computed(() => toDecimal(form.estoque.quantidade));
const saldoTotalLotesCalc = computed(() => {
    const rows = Array.isArray(form.estoque_detalhado.saldo_lotes_rows) ? form.estoque_detalhado.saldo_lotes_rows : [];
    if (!rows.length) {
        return estoqueAtualTotalCalc.value;
    }

    return rows.reduce((acc, row) => acc + toDecimal(row?.qtd_saldo ?? row?.quantidade ?? 0), 0);
});
const ultimoCustoCalc = computed(() => toDecimal(form.estoque_detalhado.custo_ultima_compra));
const custoMedioHistoricoCalc = computed(() => {
    const rows = Array.isArray(form.estoque_detalhado.saldo_lotes_rows) ? form.estoque_detalhado.saldo_lotes_rows : [];
    if (!rows.length) {
        return ultimoCustoCalc.value;
    }

    let quantityTotal = 0;
    let weightedTotal = 0;

    rows.forEach((row) => {
        const quantity = toDecimal(row?.qtd_saldo ?? row?.quantidade ?? 0);
        const cost = toDecimal(row?.custo_unitario ?? 0);
        quantityTotal += quantity;
        weightedTotal += quantity * cost;
    });

    if (quantityTotal <= 0) {
        return ultimoCustoCalc.value;
    }

    return weightedTotal / quantityTotal;
});
const custoAtualMedioCalc = computed(() => {
    if (custoMedioHistoricoCalc.value > 0) return custoMedioHistoricoCalc.value;
    if (ultimoCustoCalc.value > 0) return ultimoCustoCalc.value;
    if (gerencialCustoReal.value > 0) return gerencialCustoReal.value;
    return gerencialCustoBase.value;
});
const margemRealOperacionalCalc = computed(() => gerencialMargemReal.value);
const fornecedorUltimaReferenciaLabel = computed(() => {
    const value = String(form.estoque_detalhado.fornecedor_ultima_referencia || form.estoque_detalhado.codigo_fornecedor || '').trim();
    return value || 'Nao identificado';
});
const referenciaCustoDataLabel = computed(() => {
    const value = String(form.estoque_detalhado.referencia_custo_data || '').trim();
    if (!value) return 'Sem referencia historica';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return value;
    return parsed.toLocaleDateString('pt-BR');
});

const historicoUsuariosOptions = computed(() => {
    const users = new Set();
    form.auditoria.forEach((row) => {
        const user = String(row?.usuario || '').trim();
        if (user) users.add(user);
    });
    return Array.from(users).sort((a, b) => a.localeCompare(b));
});

const historicoEventsOptions = [
    { id: 'created', label: 'created' },
    { id: 'updated', label: 'updated' },
    { id: 'deleted', label: 'deleted' },
];

const historicoFilteredRows = computed(() => {
    return form.auditoria.filter((row) => {
        const event = String(row?.evento || '').trim().toLowerCase();
        const user = String(row?.usuario || '').trim();
        const date = parseAuditDate(row?.created_at);

        if (historicoFilterApplied.evento && event !== historicoFilterApplied.evento) {
            return false;
        }

        if (historicoFilterApplied.usuario && user !== historicoFilterApplied.usuario) {
            return false;
        }

        if (historicoFilterApplied.data_inicio) {
            const start = new Date(`${historicoFilterApplied.data_inicio}T00:00:00`);
            if (!(date instanceof Date) || Number.isNaN(date.getTime()) || date < start) {
                return false;
            }
        }

        if (historicoFilterApplied.data_fim) {
            const end = new Date(`${historicoFilterApplied.data_fim}T23:59:59`);
            if (!(date instanceof Date) || Number.isNaN(date.getTime()) || date > end) {
                return false;
            }
        }

        return true;
    });
});

const historicoTotalPages = computed(() => Math.max(1, Math.ceil(historicoFilteredRows.value.length / Number(historicoRowsPerPage.value || 10))));
const historicoPagedRows = computed(() => {
    const page = Math.min(historicoPage.value, historicoTotalPages.value);
    const perPage = Number(historicoRowsPerPage.value || 10);
    const start = (page - 1) * perPage;
    return historicoFilteredRows.value.slice(start, start + perPage);
});
const historicoModalChanges = computed(() => {
    if (!historicoModalAudit.value) {
        return [];
    }
    return extractAuditChanges(historicoModalAudit.value);
});

watch(
    () => route.fullPath,
    () => {
        bootstrap();
    },
);

watch([barcodeSearch, barcodeRowsPerPage], () => {
    barcodePage.value = 1;
});

watch([composicaoSearch, composicaoRowsPerPage], () => {
    composicaoPage.value = 1;
});

watch(historicoRowsPerPage, () => {
    historicoPage.value = 1;
});

function resetForm() {
    form.id = '';
    form.estabelecimento_id = '';
    form.produto_mestre_id = '';
    form.fiscal_item_profile_id = '';
    form.fiscal_item_profile_entrada_id = '';
    form.fiscal_item_profile_saida_id = '';
    form.classificacao_mercadologica_id = '';
    form.unidade_medida_id = '';
    form.produto_familia_id = '';
    form.cod_sku = '';
    form.codigo_operacional = '';
    form.codigo_operacional_manual = false;
    form.descricao = '';
    form.descricao_curta = '';
    form.produto_tipo = 'mercadoria';
    form.situacao = 'ativo';
    form.liberado = 'sim';
    form.marca = '';
    form.palavra_chave = '';
    form.conta_contabil = '';
    form.nr_contrato = '';
    form.classificacoes_niveis_adicionais = [''];
    form.descricao_site = '';
    form.descricao_detalhada = '';
    form.empresa_combo = '';
    form.cliente_combo = '';
    form.empresas_vinculadas = [];
    form.clientes_vinculados = [];
    form.ean_tipo = 'GTIN-13';
    form.ean_codigo = '';
    form.fiscal_ncm = '';
    form.fiscal_ncm_ex = '';
    form.fiscal_cest = '';
    form.created_at = '';
    form.updated_at = '';
    form.permite_fracionamento = false;
    form.atributos_logisticos_json = '{}';
    form.precos = [];
    form.codigos_barras = [];
    form.estoque = {
        quantidade: '0',
        quantidade_minima: '',
        quantidade_maxima: '',
        numero_lote: '',
        reduzir_estoque: true,
        quantidade_minima_vendavel: '',
        quantidade_alerta: '',
    };
    form.estoque_detalhado = {
        consumo_medio_diario: '',
        lead_time_compra: '',
        lead_time_entrega: '',
        lead_time_recebimento: '',
        estoque_seguranca: '',
        lote_minimo_compra: '',
        frequencia_compra: '',
        ponto_pedido: '',
        ponto_pedido_override: false,
        nao_fracionado: 'nao',
        controla_validade_lote: 'sim',
        vida_util_padrao: '',
        controla_enderecamento: 'nao',
        transgenico: 'nao',
        atributos_logisticos_flags: {
            controla_lote: false,
            refrigerado: false,
            controla_enderecamento: false,
            inflamavel: false,
            fragil: false,
            empilhavel: false,
            pesavel: false,
            toxico: false,
            corrosivo: false,
            e_commerce: false,
            agronomico: false,
        },
        endereco_controlado: false,
        filial: '',
        deposito_armazem: '',
        local_estoque: '',
        rua: '',
        modulo: '',
        prateleira: '',
        nivel: '',
        posicao: '',
        codigo_fornecedor: '',
        fornecedor_ultima_referencia: '',
        referencia_custo_data: '',
        codigo_barras_fornecedor: '',
        custo_ultima_compra: '',
        lead_time_fornecedor: '',
        lote_minimo_fornecedor: '',
        saldo_lotes_rows: [],
        saldo_consolidado_rows: [],
        dimensoes_embalado: {
            peso_bruto: '',
            altura: '',
            largura: '',
            comprimento: '',
            volume: '',
        },
        dimensoes_sem_embalagem: {
            peso_liquido: '',
            altura: '',
            largura: '',
            comprimento: '',
            volume: '',
        },
        espessura: '',
        densidade: '',
        unidade_base_estoque: '',
        unidade_compra: '',
        unidade_venda: '',
        embalagens: [],
    };
    form.gerencial_memoria = {
        custo_compra: '',
        frete: '',
        seguro: '',
        despesas_acessorias: '',
        desconto: '',
        ipi: '',
        icms_st: '',
        impostos_recuperaveis: '',
        custo_financeiro: '',
        custo_reposicao: '',
        custo_real: '',
        preco_venda_atual: '',
        margem_nominal: '',
        margem_real: '',
        custo_referencial_manual: '',
    };
    form.informacao_adicional = {
        composicoes: [],
        fotos: [createEmptyPhotoSlot()],
    };
    form.auditoria = [];
}

function applyFormPayload(payload) {
    resetForm();

    form.id = payload.id || '';
    form.estabelecimento_id = payload.estabelecimento_id || '';
    form.produto_mestre_id = payload.produto_mestre_id || '';
    form.fiscal_item_profile_id = payload.fiscal_item_profile_id || '';
    form.fiscal_item_profile_entrada_id = payload.fiscal_item_profile_entrada_id || '';
    form.fiscal_item_profile_saida_id = payload.fiscal_item_profile_saida_id || '';
    form.classificacao_mercadologica_id = payload.classificacao_mercadologica_id || '';
    form.unidade_medida_id = payload.unidade_medida_id || '';
    form.produto_familia_id = payload.produto_familia_id || '';
    form.cod_sku = payload.cod_sku || '';
    form.codigo_operacional = payload.codigo_operacional || '';
    form.codigo_operacional_manual = !!payload.codigo_operacional_manual;
    form.descricao = payload.descricao || '';
    form.descricao_curta = payload.descricao_curta || '';
    form.produto_tipo = payload.produto_tipo || 'mercadoria';
    form.situacao = payload.situacao || 'ativo';
    form.liberado = payload.liberado || 'sim';
    form.marca = payload.marca || '';
    form.palavra_chave = payload.palavra_chave || '';
    form.created_at = payload.created_at || '';
    form.updated_at = payload.updated_at || '';
    form.permite_fracionamento = !!payload.permite_fracionamento;
    form.atributos_logisticos_json = JSON.stringify(payload.atributos_logisticos || {}, null, 2);
    form.conta_contabil = String(payload.atributos_logisticos?.conta_contabil || '').trim();
    form.nr_contrato = String(payload.atributos_logisticos?.nr_contrato || '').trim();
    form.classificacoes_niveis_adicionais = Array.isArray(payload.atributos_logisticos?.classificacoes_niveis_adicionais)
        ? payload.atributos_logisticos.classificacoes_niveis_adicionais.map((value) => String(value || '').trim()).filter(Boolean)
        : [''];
    if (form.classificacoes_niveis_adicionais.length === 0) {
        form.classificacoes_niveis_adicionais = [''];
    }
    form.descricao_site = String(payload.atributos_logisticos?.descricao_site || '').trim();
    form.descricao_detalhada = String(payload.atributos_logisticos?.descricao_detalhada || '').trim();
    form.empresas_vinculadas = Array.isArray(payload.atributos_logisticos?.empresas_vinculadas)
        ? payload.atributos_logisticos.empresas_vinculadas.map((value) => String(value || '').trim()).filter(Boolean)
        : [];
    form.clientes_vinculados = Array.isArray(payload.atributos_logisticos?.clientes_vinculados)
        ? payload.atributos_logisticos.clientes_vinculados.map((value) => String(value || '').trim()).filter(Boolean)
        : [];

    form.fiscal_ncm = String(payload.atributos_logisticos?.fiscal_ncm || '').trim();
    form.fiscal_ncm_ex = String(payload.atributos_logisticos?.fiscal_ncm_ex || '').trim();
    form.fiscal_cest = String(payload.atributos_logisticos?.fiscal_cest || '').trim();

    form.precos = Array.isArray(payload.precos)
        ? payload.precos.map((row) => ({
            id: row.id || '',
            tipo: row.tipo || 'venda',
            codigo: row.codigo || '',
            canal: row.canal || '',
            valor: row.valor != null ? String(row.valor) : '0',
            percentual: row.percentual != null ? String(row.percentual) : '',
            custo_referencial: row.custo_referencial != null ? String(row.custo_referencial) : '',
            margem: row.margem != null ? String(row.margem) : '',
            margem_preco_minimo: row.margem_preco_minimo != null ? String(row.margem_preco_minimo) : '',
            vigencia_inicio: row.vigencia_inicio || '',
            vigencia_fim: row.vigencia_fim || '',
            ativo: row.ativo !== false,
        }))
        : [];

    form.codigos_barras = Array.isArray(payload.codigos_barras)
        ? payload.codigos_barras.map((row) => ({
            id: row.id || '',
            produto_apresentacao_id: row.produto_apresentacao_id || '',
            codigo: row.codigo || '',
            tipo_codigo: row.tipo_codigo || 'GTIN-13',
            principal: !!row.principal,
            informacoes_complementares: row.informacoes_complementares || '',
            ativo: row.ativo !== false,
        }))
        : [];

    const principalCode = form.codigos_barras.find((row) => row.principal) || form.codigos_barras[0] || null;
    form.ean_tipo = principalCode?.tipo_codigo || 'GTIN-13';
    form.ean_codigo = principalCode?.codigo || '';

    const stock = payload.estoque || {};
    form.estoque = {
        quantidade: stock.quantidade != null ? String(stock.quantidade) : '0',
        quantidade_minima: stock.quantidade_minima != null ? String(stock.quantidade_minima) : '',
        quantidade_maxima: stock.quantidade_maxima != null ? String(stock.quantidade_maxima) : '',
        numero_lote: stock.numero_lote || '',
        reduzir_estoque: stock.reduzir_estoque !== false,
        quantidade_minima_vendavel: stock.quantidade_minima_vendavel != null ? String(stock.quantidade_minima_vendavel) : '',
        quantidade_alerta: stock.quantidade_alerta != null ? String(stock.quantidade_alerta) : '',
    };

    const estoqueDetalhado = payload.atributos_logisticos?.estoque_detalhado || {};
    form.estoque_detalhado = {
        ...form.estoque_detalhado,
        consumo_medio_diario: String(estoqueDetalhado.consumo_medio_diario || ''),
        lead_time_compra: String(estoqueDetalhado.lead_time_compra || ''),
        lead_time_entrega: String(estoqueDetalhado.lead_time_entrega || ''),
        lead_time_recebimento: String(estoqueDetalhado.lead_time_recebimento || ''),
        estoque_seguranca: String(estoqueDetalhado.estoque_seguranca || ''),
        lote_minimo_compra: String(estoqueDetalhado.lote_minimo_compra || ''),
        frequencia_compra: String(estoqueDetalhado.frequencia_compra || ''),
        ponto_pedido: String(estoqueDetalhado.ponto_pedido || ''),
        ponto_pedido_override: !!estoqueDetalhado.ponto_pedido_override,
        nao_fracionado: String(estoqueDetalhado.nao_fracionado || 'nao'),
        controla_validade_lote: String(estoqueDetalhado.controla_validade_lote || 'sim'),
        vida_util_padrao: String(estoqueDetalhado.vida_util_padrao || ''),
        controla_enderecamento: String(estoqueDetalhado.controla_enderecamento || 'nao'),
        transgenico: String(estoqueDetalhado.transgenico || 'nao'),
        atributos_logisticos_flags: {
            ...form.estoque_detalhado.atributos_logisticos_flags,
            ...(estoqueDetalhado.atributos_logisticos_flags || {}),
        },
        endereco_controlado: !!estoqueDetalhado.endereco_controlado,
        filial: String(estoqueDetalhado.filial || ''),
        deposito_armazem: String(estoqueDetalhado.deposito_armazem || ''),
        local_estoque: String(estoqueDetalhado.local_estoque || ''),
        rua: String(estoqueDetalhado.rua || ''),
        modulo: String(estoqueDetalhado.modulo || ''),
        prateleira: String(estoqueDetalhado.prateleira || ''),
        nivel: String(estoqueDetalhado.nivel || ''),
        posicao: String(estoqueDetalhado.posicao || ''),
        codigo_fornecedor: String(estoqueDetalhado.codigo_fornecedor || ''),
        fornecedor_ultima_referencia: String(estoqueDetalhado.fornecedor_ultima_referencia || ''),
        referencia_custo_data: String(estoqueDetalhado.referencia_custo_data || ''),
        codigo_barras_fornecedor: String(estoqueDetalhado.codigo_barras_fornecedor || ''),
        custo_ultima_compra: String(estoqueDetalhado.custo_ultima_compra || ''),
        lead_time_fornecedor: String(estoqueDetalhado.lead_time_fornecedor || ''),
        lote_minimo_fornecedor: String(estoqueDetalhado.lote_minimo_fornecedor || ''),
        saldo_lotes_rows: Array.isArray(estoqueDetalhado.saldo_lotes_rows) ? estoqueDetalhado.saldo_lotes_rows : [],
        saldo_consolidado_rows: Array.isArray(estoqueDetalhado.saldo_consolidado_rows) ? estoqueDetalhado.saldo_consolidado_rows : [],
        dimensoes_embalado: {
            ...form.estoque_detalhado.dimensoes_embalado,
            ...(estoqueDetalhado.dimensoes_embalado || {}),
        },
        dimensoes_sem_embalagem: {
            ...form.estoque_detalhado.dimensoes_sem_embalagem,
            ...(estoqueDetalhado.dimensoes_sem_embalagem || {}),
        },
        espessura: String(estoqueDetalhado.espessura || ''),
        densidade: String(estoqueDetalhado.densidade || ''),
        unidade_base_estoque: String(estoqueDetalhado.unidade_base_estoque || ''),
        unidade_compra: String(estoqueDetalhado.unidade_compra || ''),
        unidade_venda: String(estoqueDetalhado.unidade_venda || ''),
        embalagens: Array.isArray(estoqueDetalhado.embalagens) ? estoqueDetalhado.embalagens : [],
    };

    const gerencialMemoria = payload.atributos_logisticos?.gerencial_memoria || {};
    form.gerencial_memoria = {
        ...form.gerencial_memoria,
        custo_compra: String(gerencialMemoria.custo_compra || ''),
        frete: String(gerencialMemoria.frete || ''),
        seguro: String(gerencialMemoria.seguro || ''),
        despesas_acessorias: String(gerencialMemoria.despesas_acessorias || ''),
        desconto: String(gerencialMemoria.desconto || ''),
        ipi: String(gerencialMemoria.ipi || ''),
        icms_st: String(gerencialMemoria.icms_st || ''),
        impostos_recuperaveis: String(gerencialMemoria.impostos_recuperaveis || ''),
        custo_financeiro: String(gerencialMemoria.custo_financeiro || ''),
        custo_reposicao: String(gerencialMemoria.custo_reposicao || ''),
        custo_real: String(gerencialMemoria.custo_real || ''),
        preco_venda_atual: String(gerencialMemoria.preco_venda_atual || ''),
        margem_nominal: String(gerencialMemoria.margem_nominal || ''),
        margem_real: String(gerencialMemoria.margem_real || ''),
        custo_referencial_manual: String(gerencialMemoria.custo_referencial_manual || ''),
    };

    const infoAdicional = payload.atributos_logisticos?.informacao_adicional || {};
    const parsedFotos = Array.isArray(infoAdicional.fotos) ? infoAdicional.fotos : [];
    form.informacao_adicional = {
        composicoes: Array.isArray(infoAdicional.composicoes)
            ? infoAdicional.composicoes.map((row) => normalizeCompositionRow(row))
            : [],
        fotos: normalizePhotoSlots(parsedFotos),
    };

    form.auditoria = Array.isArray(payload.auditoria) ? payload.auditoria : [];
}

function safeParseAtributosLogisticos(raw) {
    const normalized = String(raw || '').trim();
    if (normalized === '') {
        return null;
    }

    try {
        const parsed = JSON.parse(normalized);
        if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
            return parsed;
        }

        return null;
    } catch {
        return null;
    }
}

function normalizeDateString(value) {
    const text = String(value || '').trim();
    if (!text) {
        return '';
    }

    const source = text.includes(' ') ? text.replace(' ', 'T') : text;
    const date = new Date(source);
    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const dd = String(date.getDate()).padStart(2, '0');
    const mm = String(date.getMonth() + 1).padStart(2, '0');
    const yyyy = date.getFullYear();
    return `${dd}/${mm}/${yyyy}`;
}

function parseLocaleNumber(value, fallback = 0) {
    if (typeof value === 'number') {
        return Number.isFinite(value) ? value : fallback;
    }

    const text = String(value ?? '').trim();
    if (!text) return fallback;

    const compact = text.replace(/\s/g, '').replace(/[^\d.,-]/g, '');
    if (!compact) return fallback;

    const lastComma = compact.lastIndexOf(',');
    const lastDot = compact.lastIndexOf('.');
    let normalized = compact;

    if (lastComma !== -1 && lastDot !== -1) {
        if (lastComma > lastDot) {
            // pt-BR: 1.234,56
            normalized = compact.replace(/\./g, '').replace(',', '.');
        } else {
            // en-US/API: 1,234.56
            normalized = compact.replace(/,/g, '');
        }
    } else if (lastComma !== -1) {
        normalized = compact.replace(',', '.');
    }

    const parsed = Number(normalized);
    return Number.isFinite(parsed) ? parsed : fallback;
}

function toDecimal(value) {
    return parseLocaleNumber(value, 0);
}

function parseDecimalRate(value, fallback = 0) {
    return parseLocaleNumber(value, fallback);
}

function formatCurrency(value) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value || 0));
}

function formatPercent(value) {
    return `${new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0))}%`;
}

function formatDecimal(value, precision = 3) {
    return new Intl.NumberFormat('pt-BR', {
        minimumFractionDigits: precision,
        maximumFractionDigits: precision,
    }).format(Number(value || 0));
}

function parseAuditDate(value) {
    const text = String(value || '').trim();
    if (!text) return null;

    // Backend envia "Y-m-d H:i:s" em UTC sem sufixo de timezone.
    // Quando não há timezone explícito, convertemos manualmente para UTC.
    const rawUtcMatch = text.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?$/);
    if (rawUtcMatch) {
        const [, y, m, d, hh, mm, ss = '00'] = rawUtcMatch;
        const utcDate = new Date(Date.UTC(
            Number(y),
            Number(m) - 1,
            Number(d),
            Number(hh),
            Number(mm),
            Number(ss),
        ));
        return Number.isNaN(utcDate.getTime()) ? null : utcDate;
    }

    const normalized = text.includes(' ') ? text.replace(' ', 'T') : text;
    const parsed = new Date(normalized);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
}

function formatAuditDateTime(value) {
    const date = parseAuditDate(value);
    if (!date) return '—';
    const dd = String(date.getDate()).padStart(2, '0');
    const mm = String(date.getMonth() + 1).padStart(2, '0');
    const yyyy = date.getFullYear();
    const hh = String(date.getHours()).padStart(2, '0');
    const min = String(date.getMinutes()).padStart(2, '0');
    const ss = String(date.getSeconds()).padStart(2, '0');
    return `${dd}/${mm}/${yyyy}, ${hh}:${min}:${ss}`;
}

function normalizeAuditKeyLabel(field) {
    const labels = {
        estabelecimento_id: 'Estabelecimento',
        produto_mestre_id: 'Produto mestre',
        fiscal_item_profile_id: 'Perfil fiscal',
        fiscal_item_profile_entrada_id: 'Perfil fiscal entrada',
        fiscal_item_profile_saida_id: 'Perfil fiscal saída',
        classificacao_mercadologica_id: 'Classificação mercadológica',
        unidade_medida_id: 'Unidade de medida',
        produto_familia_id: 'Família',
        cod_sku: 'SKU',
        codigo_operacional: 'Código operacional',
        codigo_operacional_manual: 'Código operacional manual',
        descricao: 'Descrição completa',
        descricao_curta: 'Descrição curta',
        produto_tipo: 'Tipo de produto',
        situacao: 'Status',
        liberado: 'Liberado',
        marca: 'Marca',
        palavra_chave: 'Palavras-chave',
        permite_fracionamento: 'Permite fracionamento',
        atributos_logisticos: 'Atributos logísticos',
        estoque: 'Estoque',
        precos: 'Tabelas de preço',
        codigos_barras: 'Códigos de barras',
        created_at: 'Criado em',
        updated_at: 'Atualizado em',
    };

    if (labels[field]) {
        return labels[field];
    }

    if (String(field || '').includes('.')) {
        const tokens = String(field || '').split('.');
        const tokenLabels = {
            atributos_logisticos: 'Atributos logísticos',
            informacao_adicional: 'Informação adicional',
            composicoes: 'Composições',
            fotos: 'Fotos',
            estoque_detalhado: 'Estoque detalhado',
            gerencial_memoria: 'Memória gerencial',
            codigos_barras: 'Códigos de barras',
            precos: 'Preços',
            atributos_logisticos_flags: 'Flags logísticas',
            descricao_site: 'Descrição para site',
            descricao_detalhada: 'Descrição detalhada',
            classificacoes_niveis_adicionais: 'Classificações adicionais',
            parent_id: 'Componente pai',
            produto_id: 'Produto',
            quantidade: 'Quantidade',
            ordem: 'Ordem',
            observacao: 'Observação',
            calculate_cost: 'Incluir no custo',
            operational_cost: 'Custo operacional',
            nome_template: 'Template',
            nome_personalizado: 'Nome personalizado',
            tipo_campo: 'Tipo de campo',
            valor: 'Valor',
            valor_booleano: 'Valor booleano',
            texto_checkbox: 'Texto checkbox',
            custo_compra: 'Custo compra',
            frete: 'Frete',
            seguro: 'Seguro',
            despesas_acessorias: 'Despesas acessórias',
            desconto: 'Desconto',
            ipi: 'IPI',
            icms_st: 'ICMS ST',
            impostos_recuperaveis: 'Impostos recuperáveis',
            custo_financeiro: 'Custo financeiro',
            custo_reposicao: 'Custo reposição',
            custo_real: 'Custo real',
            preco_venda_atual: 'Preço venda atual',
            margem_nominal: 'Margem nominal',
            margem_real: 'Margem real',
            custo_referencial_manual: 'Custo referencial manual',
        };

        const formatted = tokens
            .map((token) => {
                if (/^\d+$/.test(token)) {
                    return `#${Number(token) + 1}`;
                }
                if (tokenLabels[token]) {
                    return tokenLabels[token];
                }
                return token
                    .replace(/_/g, ' ')
                    .replace(/\b\w/g, (m) => m.toUpperCase());
            })
            .join(' • ');

        return formatted;
    }

    return String(field || '')
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (m) => m.toUpperCase());
}

function hydrateAuditValue(field, value) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const textValue = String(value);
    const lookups = {
        unidade_medida_id: supportData.unidades_medida.find((row) => String(row.id) === textValue)?.unidade,
        produto_familia_id: supportData.familias.find((row) => String(row.id) === textValue)?.nome,
        classificacao_mercadologica_id: supportData.classificacoes_mercadologicas.find((row) => String(row.id) === textValue)?.descricao,
        fiscal_item_profile_id: supportData.fiscal_item_profiles.find((row) => String(row.id) === textValue)?.display_name,
        fiscal_item_profile_entrada_id: supportData.fiscal_item_profiles.find((row) => String(row.id) === textValue)?.display_name,
        fiscal_item_profile_saida_id: supportData.fiscal_item_profiles.find((row) => String(row.id) === textValue)?.display_name,
        situacao: supportData.situacoes.find((row) => String(row.id) === textValue)?.label,
        produto_tipo: supportData.produto_tipos.find((row) => String(row.id) === textValue)?.label,
    };

    if (lookups[field]) {
        return lookups[field];
    }

    if (field === 'liberado') {
        return textValue === 'sim' ? 'Sim' : 'Não';
    }

    if (field === 'created_at' || field === 'updated_at') {
        return formatAuditDateTime(textValue);
    }

    if (typeof value === 'boolean') {
        return value ? 'Sim' : 'Não';
    }

    if (Array.isArray(value)) {
        if (value.length === 0) {
            return '[]';
        }

        if (value.every((item) => item === null || ['string', 'number', 'boolean'].includes(typeof item))) {
            return value.map((item) => String(item ?? '—')).join(', ');
        }

        if (value.every((item) => item && typeof item === 'object' && !Array.isArray(item))) {
            const readableKeys = ['descricao', 'nome', 'codigo', 'id', 'url'];
            const sample = value
                .map((item) => {
                    const matchKey = readableKeys.find((key) => String(item?.[key] || '').trim() !== '');
                    return matchKey ? String(item?.[matchKey]) : null;
                })
                .filter(Boolean)
                .slice(0, 3);

            if (sample.length > 0) {
                const suffix = value.length > sample.length ? ` (+${value.length - sample.length})` : '';
                return `${sample.join(', ')}${suffix}`;
            }

            return `${value.length} registro(s)`;
        }

        return `${value.length} item(ns)`;
    }

    if (typeof value === 'object') {
        return JSON.stringify(value, null, 2);
    }

    return textValue;
}

function isAuditObject(value) {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
}

function normalizeAuditComparable(value) {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    if (typeof value === 'boolean') {
        return value ? '1' : '0';
    }

    if (Array.isArray(value)) {
        return JSON.stringify(value.map((item) => normalizeAuditComparable(item)));
    }

    if (isAuditObject(value)) {
        return JSON.stringify(
            Object.keys(value)
                .sort()
                .reduce((acc, key) => {
                    acc[key] = normalizeAuditComparable(value[key]);
                    return acc;
                }, {}),
        );
    }

    return String(value);
}

function auditValuesAreDifferent(before, after) {
    return normalizeAuditComparable(before) !== normalizeAuditComparable(after);
}

function normalizeAuditRawValue(value) {
    if (typeof value !== 'string') {
        return value;
    }

    const text = value.trim();
    if (!text) return value;
    if ((text.startsWith('{') && text.endsWith('}')) || (text.startsWith('[') && text.endsWith(']'))) {
        try {
            return JSON.parse(text);
        } catch {
            return value;
        }
    }

    return value;
}

function buildAuditChange(field, before, after) {
    return {
        field,
        label: normalizeAuditKeyLabel(field),
        before: hydrateAuditValue(field, before),
        after: hydrateAuditValue(field, after),
    };
}

function flattenAuditObjectChanges(baseField, before, after) {
    const beforeObj = isAuditObject(before) ? before : {};
    const afterObj = isAuditObject(after) ? after : {};
    const keys = Array.from(new Set([...Object.keys(beforeObj), ...Object.keys(afterObj)]));
    const entries = [];

    keys.forEach((key) => {
        const childField = baseField ? `${baseField}.${key}` : key;
        const beforeValue = beforeObj[key];
        const afterValue = afterObj[key];
        if (!auditValuesAreDifferent(beforeValue, afterValue)) {
            return;
        }

        const beforeParsed = normalizeAuditRawValue(beforeValue);
        const afterParsed = normalizeAuditRawValue(afterValue);

        if (isAuditObject(beforeParsed) || isAuditObject(afterParsed)) {
            entries.push(...flattenAuditObjectChanges(childField, beforeParsed, afterParsed));
            return;
        }

        entries.push(buildAuditChange(childField, beforeParsed, afterParsed));
    });

    return entries;
}

function expandAuditFieldChanges(field, beforeValue, afterValue) {
    const beforeParsed = normalizeAuditRawValue(beforeValue);
    const afterParsed = normalizeAuditRawValue(afterValue);

    if (!auditValuesAreDifferent(beforeParsed, afterParsed)) {
        return [];
    }

    if (isAuditObject(beforeParsed) || isAuditObject(afterParsed)) {
        const flattened = flattenAuditObjectChanges(field, beforeParsed, afterParsed);
        if (flattened.length > 0) {
            return flattened;
        }
    }

    return [buildAuditChange(field, beforeParsed, afterParsed)];
}

function extractAuditChanges(auditRow) {
    const changes = auditRow?.alteracoes || {};

    if (changes && typeof changes === 'object' && changes.fields && typeof changes.fields === 'object') {
        return Object.entries(changes.fields)
            .flatMap(([field, delta]) => expandAuditFieldChanges(field, delta?.before, delta?.after))
            .filter((entry) => String(entry.field || '').trim() !== '');
    }

    const before = changes?.before && typeof changes.before === 'object' ? changes.before : {};
    const after = changes?.after && typeof changes.after === 'object' ? changes.after : {};

    const keys = Array.from(new Set([...Object.keys(before), ...Object.keys(after)]));
    return keys
        .filter((field) => String(field) !== 'id')
        .flatMap((field) => expandAuditFieldChanges(field, before[field], after[field]));
}

function auditChangesCount(auditRow) {
    return extractAuditChanges(auditRow).length;
}

function openAuditModal(auditRow) {
    historicoModalAudit.value = auditRow;
    historicoModalOpen.value = true;
}

function closeAuditModal() {
    historicoModalOpen.value = false;
    historicoModalAudit.value = null;
}

function applyHistoricoFilter() {
    historicoFilterApplied.data_inicio = historicoFilterDraft.data_inicio;
    historicoFilterApplied.data_fim = historicoFilterDraft.data_fim;
    historicoFilterApplied.evento = historicoFilterDraft.evento;
    historicoFilterApplied.usuario = historicoFilterDraft.usuario;
    historicoPage.value = 1;
}

function clearHistoricoFilter() {
    historicoFilterDraft.data_inicio = '';
    historicoFilterDraft.data_fim = '';
    historicoFilterDraft.evento = '';
    historicoFilterDraft.usuario = '';
    historicoFilterApplied.data_inicio = '';
    historicoFilterApplied.data_fim = '';
    historicoFilterApplied.evento = '';
    historicoFilterApplied.usuario = '';
    historicoPage.value = 1;
}

function goToHistoricoPrevPage() {
    if (historicoPage.value > 1) {
        historicoPage.value -= 1;
    }
}

function goToHistoricoNextPage() {
    if (historicoPage.value < historicoTotalPages.value) {
        historicoPage.value += 1;
    }
}

function syncPrimaryBarcodeFromBasic() {
    const code = String(form.ean_codigo || '').trim();
    const type = String(form.ean_tipo || 'GTIN-13').trim() || 'GTIN-13';

    if (!code) {
        return;
    }

    let primary = form.codigos_barras.find((row) => row.principal);
    if (!primary) {
        primary = form.codigos_barras[0];
    }

    if (!primary) {
        form.codigos_barras.unshift({
            id: '',
            produto_apresentacao_id: '',
            codigo: code,
            tipo_codigo: type,
            principal: true,
            informacoes_complementares: '',
            ativo: true,
        });
        return;
    }

    primary.codigo = code;
    primary.tipo_codigo = type;
    primary.principal = true;
}

function addNivelAdicional() {
    if (form.classificacoes_niveis_adicionais.length >= 4) {
        return;
    }

    form.classificacoes_niveis_adicionais.push('');
}

function vincularEmpresa() {
    const value = String(form.empresa_combo || '').trim();
    if (!value) return;
    if (!form.empresas_vinculadas.includes(value)) {
        form.empresas_vinculadas.push(value);
    }
    form.empresa_combo = '';
}

function removerEmpresa(index) {
    form.empresas_vinculadas.splice(index, 1);
}

function vincularCliente() {
    const value = String(form.cliente_combo || '').trim();
    if (!value) return;
    if (!form.clientes_vinculados.includes(value)) {
        form.clientes_vinculados.push(value);
    }
    form.cliente_combo = '';
}

function removerCliente(index) {
    form.clientes_vinculados.splice(index, 1);
}

function addPreco() {
    form.precos.push({
        id: '',
        tipo: 'venda',
        codigo: '',
        canal: '',
        valor: '0',
        percentual: '',
        custo_referencial: '',
        margem: '',
        margem_preco_minimo: '',
        vigencia_inicio: '',
        vigencia_fim: '',
        ativo: true,
    });
}

function removePreco(index) {
    form.precos.splice(index, 1);
}

function addCodigoBarras() {
    form.codigos_barras.push({
        id: '',
        produto_apresentacao_id: '',
        codigo: '',
        tipo_codigo: 'GTIN-13',
        principal: form.codigos_barras.length === 0,
        informacoes_complementares: '',
        ativo: true,
    });
}

function removeCodigoBarras(index) {
    form.codigos_barras.splice(index, 1);
}

function resetBarcodeModalForm() {
    barcodeModalForm.tipo_codigo = 'GTIN-13';
    barcodeModalForm.codigo = '';
    barcodeModalForm.informacoes_complementares = '';
    barcodeModalForm.situacao = 'ativo';
    barcodeModalForm.tipo_codigo_caixa = 'GTIN-14';
    barcodeModalForm.codigo_caixa = '';
    barcodeModalForm.sku = form.cod_sku || '';
}

function openBarcodeModal(index = null) {
    barcodeModalEditingIndex.value = typeof index === 'number' ? index : null;
    resetBarcodeModalForm();

    if (typeof index === 'number' && form.codigos_barras[index]) {
        const row = form.codigos_barras[index];
        barcodeModalForm.tipo_codigo = row.tipo_codigo || 'GTIN-13';
        barcodeModalForm.codigo = row.codigo || '';
        barcodeModalForm.informacoes_complementares = row.informacoes_complementares || '';
        barcodeModalForm.situacao = row.ativo ? 'ativo' : 'inativo';
        barcodeModalForm.sku = form.cod_sku || '';
    } else {
        barcodeModalForm.codigo = form.ean_codigo || '';
        barcodeModalForm.tipo_codigo = form.ean_tipo || 'GTIN-13';
    }

    barcodeModalOpen.value = true;
}

function closeBarcodeModal() {
    barcodeModalOpen.value = false;
    barcodeModalEditingIndex.value = null;
}

function saveBarcodeModal() {
    const code = String(barcodeModalForm.codigo || '').trim();
    if (!code) {
        return;
    }

    const target = {
        id: '',
        produto_apresentacao_id: '',
        codigo: code,
        tipo_codigo: String(barcodeModalForm.tipo_codigo || 'GTIN-13'),
        principal: false,
        informacoes_complementares: String(barcodeModalForm.informacoes_complementares || '').trim(),
        ativo: barcodeModalForm.situacao === 'ativo',
    };

    if (barcodeModalEditingIndex.value !== null && form.codigos_barras[barcodeModalEditingIndex.value]) {
        form.codigos_barras[barcodeModalEditingIndex.value] = {
            ...form.codigos_barras[barcodeModalEditingIndex.value],
            ...target,
        };
    } else {
        target.principal = form.codigos_barras.length === 0;
        form.codigos_barras.unshift(target);
    }

    const codeBox = String(barcodeModalForm.codigo_caixa || '').trim();
    if (codeBox !== '') {
        form.codigos_barras.unshift({
            id: '',
            produto_apresentacao_id: '',
            codigo: codeBox,
            tipo_codigo: String(barcodeModalForm.tipo_codigo_caixa || 'GTIN-14'),
            principal: false,
            informacoes_complementares: 'Código da caixa',
            ativo: barcodeModalForm.situacao === 'ativo',
        });
    }

    if (String(barcodeModalForm.sku || '').trim() !== '') {
        form.cod_sku = String(barcodeModalForm.sku || '').trim();
    }

    syncPrimaryBarcodeFromBasic();
    closeBarcodeModal();
}

function getCompositionFieldTemplateLabel(templateId) {
    return compositionFieldNameTemplates.find((option) => option.id === templateId)?.label || 'Personalizado';
}

function getCompositionFieldTypeLabel(typeId) {
    return compositionFieldTypeOptions.find((option) => option.id === typeId)?.label || 'Texto curto';
}

function createCompositionAdditionalField() {
    return {
        id: crypto?.randomUUID?.() || String(Date.now() + Math.random()),
        nome_template: 'personalizado',
        nome_personalizado: '',
        tipo_campo: 'texto_curto',
        valor: '',
        valor_booleano: false,
        texto_checkbox: '',
        operational_cost: '0',
    };
}

function normalizeCompositionAdditionalField(row) {
    const tipoCampo = String(row?.tipo_campo || 'texto_curto');
    const templateId = String(row?.nome_template || row?.nome_campo || 'personalizado');
    const valorRaw = String(row?.valor || '').trim().toLowerCase();
    const valorBooleano = typeof row?.valor_booleano === 'boolean'
        ? row.valor_booleano
        : (valorRaw === 'sim' || valorRaw === 'marcado' || valorRaw === 'true');
    return {
        id: row?.id || crypto?.randomUUID?.() || String(Date.now() + Math.random()),
        nome_template: templateId,
        nome_personalizado: String(row?.nome_personalizado || ''),
        tipo_campo: tipoCampo,
        valor: String(row?.valor ?? ''),
        valor_booleano: valorBooleano,
        texto_checkbox: String(row?.texto_checkbox || ''),
        operational_cost: String(row?.operational_cost ?? row?.custo_operacional ?? '0'),
    };
}

function normalizeCompositionRow(row) {
    const base = row && typeof row === 'object' ? row : {};
    return {
        ...base,
        id: base.id || crypto?.randomUUID?.() || String(Date.now() + Math.random()),
        produto_id: String(base.produto_id || ''),
        produto: String(base.produto || ''),
        parent_id: String(base.parent_id || 'root'),
        org_x: base.org_x,
        org_y: base.org_y,
        quantidade: Number(base.quantidade || 0),
        ordem: String(base.ordem || ''),
        observacao: String(base.observacao || ''),
        calculate_cost: base.calculate_cost !== false,
        operational_cost: Math.max(0, Number(base.operational_cost ?? base.custo_operacional ?? 0)),
        preco_custo: Number(base.preco_custo || 0),
        custo_total: Number(base.custo_total || 0),
        campos_adicionais: Array.isArray(base.campos_adicionais)
            ? base.campos_adicionais.map((field) => normalizeCompositionAdditionalField(field))
            : [],
    };
}

function addCompositionAdditionalField() {
    compositionModalForm.campos_adicionais.push(createCompositionAdditionalField());
}

function removeCompositionAdditionalField(index) {
    compositionModalForm.campos_adicionais.splice(index, 1);
}

function onCompositionFieldTemplateChange(field) {
    if (!field || typeof field !== 'object') return;
    if (String(field.nome_template || '') !== 'personalizado') {
        field.nome_personalizado = '';
    }
}

function onCompositionFieldTypeChange(field) {
    if (!field || typeof field !== 'object') return;
    const tipo = String(field.tipo_campo || 'texto_curto');
    if (tipo !== 'checkbox_texto') {
        field.texto_checkbox = '';
    }
    if (tipo === 'sim_nao' && !['sim', 'nao'].includes(String(field.valor || '').toLowerCase())) {
        field.valor = 'nao';
    }
    if (tipo !== 'sim_nao' && tipo !== 'checkbox_texto') {
        field.valor_booleano = false;
    }
}

function getCompositionProductLabel(product) {
    const descricao = String(product?.descricao || '').trim();
    const sku = String(product?.cod_sku || '').trim();
    const codigoOperacional = String(product?.codigo_operacional || '').trim();
    const extras = [sku ? `SKU: ${sku}` : '', codigoOperacional ? `COD: ${codigoOperacional}` : ''].filter(Boolean);
    if (extras.length === 0) return descricao;
    return `${descricao} (${extras.join(' • ')})`;
}

async function fetchCompositionProductOptions(search = '') {
    compositionProductLoading.value = true;
    try {
        const { data } = await api.get('/catalog/products', {
            params: {
                search: String(search || '').trim() || undefined,
                per_page: 30,
            },
        });
        const rows = Array.isArray(data?.data) ? data.data : [];
        compositionProductOptions.value = rows.map((row) => ({
            id: String(row.id || ''),
            label: getCompositionProductLabel(row),
            descricao: String(row.descricao || ''),
        }));
    } finally {
        compositionProductLoading.value = false;
    }
}

function queueCompositionProductSearch(term = '') {
    if (compositionProductSearchTimer) {
        clearTimeout(compositionProductSearchTimer);
    }
    compositionProductSearchTimer = setTimeout(() => {
        fetchCompositionProductOptions(term);
    }, 250);
}

function onCompositionProductInput(value = '') {
    const typed = String(value || compositionModalForm.produto || '');
    const matched = compositionProductOptions.value.find((option) => option.label === typed);
    compositionModalForm.produto_id = matched ? matched.id : '';
    queueCompositionProductSearch(typed);
}

function onCompositionProductSelect(selectedLabel = '') {
    const selected = String(selectedLabel || compositionModalForm.produto || '').trim();
    if (!selected) {
        compositionModalForm.produto_id = '';
        return;
    }

    const matched = compositionProductOptions.value.find((option) => option.label === selected);
    compositionModalForm.produto_id = matched ? matched.id : '';
}

function setCompositionOrgNodePosition(nodeId, x, y) {
    if (nodeId === 'root') {
        compositionOrgRootPosition.x = x;
        compositionOrgRootPosition.y = y;
        return;
    }

    const row = form.informacao_adicional.composicoes.find((item) => String(item.id || '') === String(nodeId));
    if (row) {
        row.org_x = x;
        row.org_y = y;
    }
}

function getCompositionOrgEdgePath(edge) {
    const fromX = edge.from.position.x + (COMPOSITION_ORG_NODE_WIDTH / 2);
    const fromY = edge.from.position.y + COMPOSITION_ORG_NODE_HEIGHT + 2;
    const toX = edge.to.position.x + (COMPOSITION_ORG_NODE_WIDTH / 2);
    const toY = edge.to.position.y - 6;
    const direction = toY >= fromY ? 1 : -1;
    const distanceY = Math.min(150, Math.max(56, Math.abs(toY - fromY) * 0.38));
    const midY = fromY + (direction * distanceY);
    return `M ${fromX} ${fromY} C ${fromX} ${midY}, ${toX} ${midY}, ${toX} ${toY}`;
}

function getCompositionOrgDraftEdgePath(edge) {
    const fromX = edge.from.position.x + (COMPOSITION_ORG_NODE_WIDTH / 2);
    const fromY = edge.from.position.y + COMPOSITION_ORG_NODE_HEIGHT + 2;
    const toX = edge.toPosition.x;
    const toY = edge.toPosition.y;
    const direction = toY >= fromY ? 1 : -1;
    const distanceY = Math.min(150, Math.max(56, Math.abs(toY - fromY) * 0.38));
    const midY = fromY + (direction * distanceY);
    return `M ${fromX} ${fromY} C ${fromX} ${midY}, ${toX} ${midY}, ${toX} ${toY}`;
}

function getCompositionOrgCanvasPoint(event) {
    const canvas = event.target?.closest?.('.composition-org-canvas');
    const rect = canvas?.getBoundingClientRect?.();
    const canvasLeft = rect?.left || 0;
    const canvasTop = rect?.top || 0;

    return {
        x: (event.clientX - canvasLeft - compositionOrgPan.x) / compositionOrgZoom.value,
        y: (event.clientY - canvasTop - compositionOrgPan.y) / compositionOrgZoom.value,
    };
}

function findCompositionOrgNodeAtPoint(point, sourceNodeId = '') {
    return [...compositionOrgNodes.value]
        .reverse()
        .find((node) => {
            if (String(node.id) === String(sourceNodeId)) return false;
            return point.x >= node.position.x
                && point.x <= node.position.x + COMPOSITION_ORG_NODE_WIDTH
                && point.y >= node.position.y
                && point.y <= node.position.y + COMPOSITION_ORG_NODE_HEIGHT;
        }) || null;
}

function getCompositionOrgSelectionIds(rect) {
    const rectRight = rect.left + rect.width;
    const rectBottom = rect.top + rect.height;
    return compositionOrgNodes.value
        .filter((node) => {
            const nodeRight = node.position.x + COMPOSITION_ORG_NODE_WIDTH;
            const nodeBottom = node.position.y + COMPOSITION_ORG_NODE_HEIGHT;
            return node.position.x <= rectRight
                && nodeRight >= rect.left
                && node.position.y <= rectBottom
                && nodeBottom >= rect.top;
        })
        .map((node) => node.id);
}

function getCompositionOrgSelectedStartPositions(nodeId) {
    const selectedIds = compositionOrgSelectedNodeIds.value.includes(nodeId)
        ? compositionOrgSelectedNodeIds.value
        : [nodeId];

    return selectedIds.reduce((acc, id) => {
        const node = compositionOrgNodeMap.value[id];
        if (node) {
            acc[id] = { x: node.position.x, y: node.position.y };
        }
        return acc;
    }, {});
}

function startCompositionOrgPan(event) {
    if (event.button === 2) {
        const point = getCompositionOrgCanvasPoint(event);
        compositionOrgPointer.mode = 'select';
        compositionOrgPointer.startX = event.clientX;
        compositionOrgPointer.startY = event.clientY;
        compositionOrgPointer.startNodeX = point.x;
        compositionOrgPointer.startNodeY = point.y;
        compositionOrgPointer.dragX = point.x;
        compositionOrgPointer.dragY = point.y;
        event.currentTarget?.setPointerCapture?.(event.pointerId);
        return;
    }

    if (event.button !== 0) return;
    compositionOrgPointer.mode = 'pan';
    compositionOrgPointer.startX = event.clientX;
    compositionOrgPointer.startY = event.clientY;
    compositionOrgPointer.startPanX = compositionOrgPan.x;
    compositionOrgPointer.startPanY = compositionOrgPan.y;
    event.currentTarget?.setPointerCapture?.(event.pointerId);
}

function startCompositionOrgNodeDrag(node, event) {
    if (event.button === 2) {
        startCompositionOrgPan(event);
        return;
    }

    if (event.button !== 0) return;
    if (compositionOrgPointer.mode === 'connect') return;
    if (!compositionOrgSelectedNodeIds.value.includes(node.id)) {
        compositionOrgSelectedNodeIds.value = [node.id];
    }
    compositionOrgPointer.mode = 'node';
    compositionOrgPointer.nodeId = node.id;
    compositionOrgPointer.startX = event.clientX;
    compositionOrgPointer.startY = event.clientY;
    compositionOrgPointer.startNodeX = node.position.x;
    compositionOrgPointer.startNodeY = node.position.y;
    compositionOrgPointer.startNodePositions = getCompositionOrgSelectedStartPositions(node.id);
    event.currentTarget?.setPointerCapture?.(event.pointerId);
}

function startCompositionOrgConnection(node, event) {
    if (event.button !== 0) return;
    const point = getCompositionOrgCanvasPoint(event);
    compositionOrgPointer.mode = 'connect';
    compositionOrgPointer.nodeId = node.id;
    compositionOrgPointer.targetNodeId = '';
    compositionOrgPointer.startX = event.clientX;
    compositionOrgPointer.startY = event.clientY;
    compositionOrgPointer.dragX = point.x;
    compositionOrgPointer.dragY = point.y;
    compositionOrgPointer.hasDragged = false;
    compositionOrgPendingParentId.value = node.id;
    event.currentTarget?.setPointerCapture?.(event.pointerId);
}

function moveCompositionOrgPointer(event) {
    if (!compositionOrgPointer.mode) return;
    const deltaX = (event.clientX - compositionOrgPointer.startX) / compositionOrgZoom.value;
    const deltaY = (event.clientY - compositionOrgPointer.startY) / compositionOrgZoom.value;

    if (compositionOrgPointer.mode === 'pan') {
        compositionOrgPan.x = compositionOrgPointer.startPanX + (event.clientX - compositionOrgPointer.startX);
        compositionOrgPan.y = compositionOrgPointer.startPanY + (event.clientY - compositionOrgPointer.startY);
    }

    if (compositionOrgPointer.mode === 'node') {
        Object.entries(compositionOrgPointer.startNodePositions || {}).forEach(([nodeId, position]) => {
            setCompositionOrgNodePosition(
                nodeId,
                Math.max(24, position.x + deltaX),
                Math.max(24, position.y + deltaY),
            );
        });
    }

    if (compositionOrgPointer.mode === 'connect') {
        const point = getCompositionOrgCanvasPoint(event);
        const targetNode = findCompositionOrgNodeAtPoint(point, compositionOrgPointer.nodeId);
        compositionOrgPointer.dragX = point.x;
        compositionOrgPointer.dragY = point.y;
        compositionOrgPointer.targetNodeId = targetNode?.id || '';
        if (Math.hypot(event.clientX - compositionOrgPointer.startX, event.clientY - compositionOrgPointer.startY) > 6) {
            compositionOrgPointer.hasDragged = true;
        }
    }

    if (compositionOrgPointer.mode === 'select') {
        const point = getCompositionOrgCanvasPoint(event);
        compositionOrgPointer.dragX = point.x;
        compositionOrgPointer.dragY = point.y;
    }
}

function stopCompositionOrgPointer(event = null) {
    if (compositionOrgPointer.mode === 'select') {
        const rect = compositionOrgSelectionBox.value;
        compositionOrgSelectedNodeIds.value = rect && rect.width > 8 && rect.height > 8
            ? getCompositionOrgSelectionIds(rect)
            : [];
    }

    if (compositionOrgPointer.mode === 'connect') {
        if (event) {
            const point = getCompositionOrgCanvasPoint(event);
            const targetNode = findCompositionOrgNodeAtPoint(point, compositionOrgPointer.nodeId);
            compositionOrgPointer.targetNodeId = targetNode?.id || compositionOrgPointer.targetNodeId;
        }

        const parentId = String(compositionOrgPointer.nodeId || '');
        const childId = String(compositionOrgPointer.targetNodeId || '');
        if (parentId && childId) {
            connectCompositionOrgNodes(parentId, childId);
        }
        compositionOrgPendingParentId.value = '';
    }

    compositionOrgPointer.mode = '';
    compositionOrgPointer.nodeId = '';
    compositionOrgPointer.targetNodeId = '';
    compositionOrgPointer.startNodePositions = {};
    setTimeout(() => {
        compositionOrgPointer.hasDragged = false;
    }, 0);
}

function zoomCompositionOrg(delta) {
    const nextZoom = Math.min(1.45, Math.max(0.55, compositionOrgZoom.value + delta));
    compositionOrgZoom.value = Number(nextZoom.toFixed(2));
}

function onCompositionOrgWheel(event) {
    event.preventDefault();
    const rect = event.currentTarget.getBoundingClientRect();
    const oldZoom = compositionOrgZoom.value;
    const delta = event.deltaY > 0 ? -0.08 : 0.08;
    const nextZoom = Math.min(1.45, Math.max(0.55, oldZoom + delta));
    const screenX = event.clientX - rect.left;
    const screenY = event.clientY - rect.top;
    const worldX = (screenX - compositionOrgPan.x) / oldZoom;
    const worldY = (screenY - compositionOrgPan.y) / oldZoom;

    compositionOrgZoom.value = Number(nextZoom.toFixed(2));
    compositionOrgPan.x = screenX - (worldX * compositionOrgZoom.value);
    compositionOrgPan.y = screenY - (worldY * compositionOrgZoom.value);
}

function centerCompositionOrg() {
    compositionOrgPan.x = 0;
    compositionOrgPan.y = 0;
    compositionOrgZoom.value = 0.82;
}

function centerCompositionOrgNode(node) {
    if (!node) return;
    compositionOrgPan.x = 520 - (node.position.x * compositionOrgZoom.value);
    compositionOrgPan.y = 180 - (node.position.y * compositionOrgZoom.value);
}

function arrangeCompositionOrg() {
    compositionOrgRootPosition.x = 520;
    compositionOrgRootPosition.y = 70;
    composicaoOrderedRows.value.forEach((item, index) => {
        const row = item.source;
        if (!row) return;
        row.parent_id = row.parent_id || 'root';
        row.org_x = 160 + ((index % 3) * 340);
        row.org_y = 285 + (Math.floor(index / 3) * 185);
    });
}

function selectCompositionOrgNode(node) {
    compositionOrgSelectedNodeId.value = node.id;
    if (!compositionOrgSelectedNodeIds.value.includes(node.id)) {
        compositionOrgSelectedNodeIds.value = [node.id];
    }
}

function closeCompositionOrgDrawer() {
    compositionOrgSelectedNodeId.value = '';
}

function isCompositionOrgDescendant(candidateId, possibleAncestorId) {
    let current = form.informacao_adicional.composicoes.find((row) => String(row.id || '') === String(candidateId));
    while (current) {
        const parentId = String(current.parent_id || 'root');
        if (parentId === String(possibleAncestorId)) return true;
        if (parentId === 'root') return false;
        current = form.informacao_adicional.composicoes.find((row) => String(row.id || '') === parentId);
    }
    return false;
}

function handleCompositionOrgConnector(node) {
    if (compositionOrgPointer.hasDragged) {
        return;
    }

    const nodeId = String(node.id || '');
    if (!compositionOrgPendingParentId.value) {
        compositionOrgPendingParentId.value = nodeId;
        return;
    }

    const parentId = String(compositionOrgPendingParentId.value || 'root');
    compositionOrgPendingParentId.value = '';

    if (nodeId === parentId || nodeId === 'root') {
        return;
    }

    if (isCompositionOrgDescendant(parentId, nodeId)) {
        return;
    }

    const childRow = form.informacao_adicional.composicoes.find((row) => String(row.id || '') === nodeId);
    if (childRow) {
        childRow.parent_id = parentId;
    }
}

function connectCompositionOrgNodes(parentId, childId) {
    const normalizedParentId = String(parentId || '');
    const normalizedChildId = String(childId || '');
    if (!normalizedParentId || !normalizedChildId) return;
    if (normalizedParentId === normalizedChildId) return;

    if (normalizedChildId === 'root') {
        const sourceRow = form.informacao_adicional.composicoes.find((row) => String(row.id || '') === normalizedParentId);
        if (sourceRow) {
            sourceRow.parent_id = 'root';
        }
        return;
    }

    if (isCompositionOrgDescendant(normalizedParentId, normalizedChildId)) return;

    const childRow = form.informacao_adicional.composicoes.find((row) => String(row.id || '') === normalizedChildId);
    if (childRow) {
        childRow.parent_id = normalizedParentId;
    }
}

function cancelCompositionGridLinkMode() {
    compositionGridPendingParentId.value = '';
    compositionGridPointer.active = false;
    compositionGridPointer.parentId = '';
    compositionGridPointer.targetId = '';
    compositionGridPointer.hasDragged = false;
}

function getCompositionGridNodeId(row) {
    return String(row?.id || '');
}

function getCompositionGridChildrenCount(nodeId) {
    const normalizedNodeId = String(nodeId || 'root');
    return compositionGridChildrenCountByParent.value[normalizedNodeId] || 0;
}

function getCompositionResolvedParentId(row) {
    const nodeId = getCompositionGridNodeId(row);
    return compositionTreeState.value.parentById?.[nodeId] || 'root';
}

function hasCompositionGridParent(row) {
    return getCompositionResolvedParentId(row) !== 'root';
}

function getCompositionGridParentLabel(row) {
    const parentId = getCompositionResolvedParentId(row);
    if (parentId === 'root') return 'Produto atual';
    const parentRow = form.informacao_adicional.composicoes.find((candidate) => String(candidate?.id || '') === parentId);
    return parentRow?.produto || 'Produto conectado';
}

function getCompositionGridNodeLabel(nodeId) {
    const normalizedNodeId = String(nodeId || 'root');
    if (normalizedNodeId === 'root') return 'Produto atual';
    const row = form.informacao_adicional.composicoes.find((candidate) => String(candidate?.id || '') === normalizedNodeId);
    return row?.produto || 'Produto selecionado';
}

function isCompositionCostEnabled(row) {
    return row?.calculate_cost !== false;
}

function getCompositionRowOperationalCost(row) {
    return Math.max(0, Number(row?.operational_cost || 0));
}

function getCompositionGridDepth(row) {
    const nodeId = getCompositionGridNodeId(row);
    const meta = getCompositionNodeBranchMeta(nodeId || 'root');
    return Math.min(Math.max(1, Number(meta.level || 2) - 1), 6);
}

function getCompositionGridBranchStyle(row, item = null) {
    const nodeId = getCompositionGridNodeId(row);
    const branchMeta = getCompositionNodeBranchMeta(nodeId || 'root');
    return {
        '--branch-color': branchMeta.branchColor || getCompositionNodeBranchColor('root'),
        '--composition-depth': item?.depth || getCompositionGridDepth(row),
    };
}

function getCompositionGridProductStyle(row, item = null) {
    const depth = item?.depth || getCompositionGridDepth(row);
    return {
        '--composition-depth': depth,
    };
}

function getCompositionGridTreeSegments(item) {
    return Array.isArray(item?.ancestorLastFlags) ? item.ancestorLastFlags : [];
}

function getCompositionGridFlowCellClass(row, item = null) {
    const nodeId = getCompositionGridNodeId(row);
    const hasChildren = getCompositionGridChildrenCount(nodeId) > 0;
    const depth = item?.depth || getCompositionGridDepth(row);
    return {
        'has-child-branch': hasChildren,
        'has-parent-branch': depth > 1,
    };
}

function findCompositionGridTargetFromPoint(clientX, clientY, parentId = '') {
    const target = document
        .elementFromPoint(clientX, clientY)
        ?.closest?.('[data-composition-grid-node-id]');
    const nodeId = String(target?.dataset?.compositionGridNodeId || '');
    if (!nodeId || nodeId === String(parentId || '')) return '';
    return nodeId;
}

function startCompositionGridLinkDrag(nodeId, event) {
    if (event?.button !== undefined && event.button !== 0) return;
    const normalizedNodeId = String(nodeId || '');
    if (!normalizedNodeId) return;

    compositionGridPointer.active = true;
    compositionGridPointer.parentId = normalizedNodeId;
    compositionGridPointer.targetId = '';
    compositionGridPointer.startX = event.clientX;
    compositionGridPointer.startY = event.clientY;
    compositionGridPointer.x = event.clientX;
    compositionGridPointer.y = event.clientY;
    compositionGridPointer.hasDragged = false;
    event.preventDefault?.();
    event.currentTarget?.setPointerCapture?.(event.pointerId);
}

function moveCompositionGridLinkDrag(event) {
    if (!compositionGridPointer.active) return;
    compositionGridPointer.x = event.clientX;
    compositionGridPointer.y = event.clientY;

    const distance = Math.hypot(event.clientX - compositionGridPointer.startX, event.clientY - compositionGridPointer.startY);
    if (distance > 6) {
        compositionGridPointer.hasDragged = true;
        compositionGridPendingParentId.value = compositionGridPointer.parentId;
        compositionOrgPendingParentId.value = '';
        compositionGridPointer.targetId = findCompositionGridTargetFromPoint(
            event.clientX,
            event.clientY,
            compositionGridPointer.parentId,
        );
    }
}

function stopCompositionGridLinkDrag(event) {
    if (!compositionGridPointer.active) return;
    const parentId = String(compositionGridPointer.parentId || '');
    const childId = String(
        compositionGridPointer.targetId
        || findCompositionGridTargetFromPoint(event.clientX, event.clientY, parentId)
        || '',
    );

    if (compositionGridPointer.hasDragged && parentId && childId && parentId !== childId) {
        if (childId === 'root' || !isCompositionOrgDescendant(parentId, childId)) {
            connectCompositionOrgNodes(parentId, childId);
        }
    }

    cancelCompositionGridLinkMode();
}

onBeforeUnmount(() => {
    cancelCompositionGridLinkMode();
    if (toastTimeout) clearTimeout(toastTimeout);
});

function resetCompositionModalForm() {
    compositionModalEditingRowId.value = '';
    compositionModalForm.produto_id = '';
    compositionModalForm.produto = '';
    compositionModalForm.quantidade = '1';
    compositionModalForm.ordem = '';
    compositionModalForm.observacao = '';
    compositionModalForm.calculate_cost = true;
    compositionModalForm.operational_cost = '0';
    compositionModalForm.campos_adicionais = [];
}

function openCompositionModal() {
    resetCompositionModalForm();
    compositionModalOpen.value = true;
    fetchCompositionProductOptions();
}

function openCompositionEditModal(row) {
    const normalized = normalizeCompositionRow(row);
    compositionModalEditingRowId.value = String(normalized.id || '');
    compositionModalForm.produto_id = String(normalized.produto_id || '');
    compositionModalForm.produto = String(normalized.produto || '');
    compositionModalForm.quantidade = String(normalized.quantidade || '1');
    compositionModalForm.ordem = String(normalized.ordem || '');
    compositionModalForm.observacao = String(normalized.observacao || '');
    compositionModalForm.calculate_cost = normalized.calculate_cost !== false;
    compositionModalForm.operational_cost = String(normalized.operational_cost ?? 0);
    compositionModalForm.campos_adicionais = Array.isArray(normalized.campos_adicionais)
        ? normalized.campos_adicionais.map((field) => normalizeCompositionAdditionalField(field))
        : [];
    compositionModalOpen.value = true;
    fetchCompositionProductOptions(normalized.produto || '');
}

function closeCompositionModal() {
    compositionModalOpen.value = false;
    compositionModalEditingRowId.value = '';
    if (compositionProductSearchTimer) {
        clearTimeout(compositionProductSearchTimer);
        compositionProductSearchTimer = null;
    }
}

function saveCompositionModal() {
    const produto = String(compositionModalForm.produto || '').trim();
    if (!produto) {
        return;
    }
    const produtoOption = compositionProductOptions.value.find((option) => option.label === produto);
    const produtoId = String(compositionModalForm.produto_id || produtoOption?.id || '').trim();

    const quantidade = Number(compositionModalForm.quantidade || 0);
    const ordemInformada = String(compositionModalForm.ordem || '').trim();
    const ordemNumerica = parseCompositionOrderValue(ordemInformada);
    const ordem = ordemNumerica !== null ? String(ordemNumerica) : String(form.informacao_adicional.composicoes.length + 1);
    const observacao = String(compositionModalForm.observacao || '').trim();
    const calculateCost = compositionModalForm.calculate_cost !== false;
    const operationalCost = Math.max(0, Number(compositionModalForm.operational_cost || 0));
    const camposAdicionais = compositionModalForm.campos_adicionais
        .map((field) => normalizeCompositionAdditionalField(field))
        .filter((field) => {
            const templateId = String(field.nome_template || 'personalizado');
            const hasTemplateName = templateId !== 'personalizado';
            const hasCustomName = String(field.nome_personalizado || '').trim() !== '';
            return hasTemplateName || hasCustomName;
        })
        .map((field) => {
            const templateId = String(field.nome_template || 'personalizado');
            const campoTipo = String(field.tipo_campo || 'texto_curto');
            const nomeExibicao = templateId === 'personalizado'
                ? String(field.nome_personalizado || '').trim()
                : getCompositionFieldTemplateLabel(templateId);

            const parsed = {
                id: field.id,
                nome_template: templateId,
                nome_campo: templateId,
                nome_campo_label: nomeExibicao,
                nome_personalizado: String(field.nome_personalizado || '').trim(),
                tipo_campo: campoTipo,
                tipo_campo_label: getCompositionFieldTypeLabel(campoTipo),
                valor: String(field.valor || ''),
                valor_booleano: !!field.valor_booleano,
                texto_checkbox: String(field.texto_checkbox || '').trim(),
                operational_cost: Math.max(0, Number(field.operational_cost || 0)),
            };

            if (campoTipo === 'sim_nao') {
                parsed.valor_booleano = String(field.valor || '').toLowerCase() === 'sim';
                parsed.valor = parsed.valor_booleano ? 'sim' : 'nao';
            }

            if (campoTipo === 'checkbox_texto') {
                parsed.valor = parsed.valor_booleano ? 'marcado' : 'desmarcado';
            }

            return parsed;
        });

    const editingRow = isEditingCompositionModal.value
        ? form.informacao_adicional.composicoes.find((row) => String(row?.id || '') === String(compositionModalEditingRowId.value || ''))
        : null;
    const normalizedPayload = normalizeCompositionRow({
        id: String(compositionModalEditingRowId.value || '') || crypto?.randomUUID?.() || String(Date.now()),
        produto_id: produtoId,
        produto,
        parent_id: editingRow?.parent_id || 'root',
        org_x: editingRow?.org_x,
        org_y: editingRow?.org_y,
        quantidade: quantidade > 0 ? quantidade : 0,
        ordem,
        observacao,
        calculate_cost: calculateCost,
        operational_cost: operationalCost,
        preco_custo: 0,
        custo_total: 0,
        campos_adicionais: camposAdicionais,
    });
    if (isEditingCompositionModal.value) {
        const idx = form.informacao_adicional.composicoes.findIndex((row) => String(row?.id || '') === String(compositionModalEditingRowId.value || ''));
        if (idx >= 0) {
            form.informacao_adicional.composicoes[idx] = normalizedPayload;
        } else {
            form.informacao_adicional.composicoes.unshift(normalizedPayload);
        }
    } else {
        form.informacao_adicional.composicoes.unshift(normalizedPayload);
    }

    closeCompositionModal();
}

function removeComposicaoRow(row) {
    const idx = form.informacao_adicional.composicoes.indexOf(row);
    if (idx >= 0) {
        form.informacao_adicional.composicoes.splice(idx, 1);
    }
}

function closeCompositionActionsMenuFromEvent(event) {
    const details = event?.currentTarget?.closest('details');
    if (details instanceof HTMLDetailsElement) {
        details.open = false;
    }
}

function openCompositionEditFromMenu(row, event) {
    closeCompositionActionsMenuFromEvent(event);
    openCompositionEditModal(row);
}

function removeCompositionFromMenu(row, event) {
    closeCompositionActionsMenuFromEvent(event);
    removeComposicaoRow(row);
}

function addPhotoSlot() {
    form.informacao_adicional.fotos.push(createEmptyPhotoSlot());
}

function openPhotoPickerModal(index) {
    const slot = form.informacao_adicional.fotos[index] || { nome: '', url: '' };
    activePhotoIndex.value = index;
    photoLinkDraft.value = String(slot.url || '').trim();
    photoPickerModalOpen.value = true;
}

function closePhotoPickerModal() {
    photoPickerModalOpen.value = false;
    activePhotoIndex.value = null;
    photoLinkDraft.value = '';
}

function selectPhotoFile(index) {
    const input = document.getElementById(`produto-foto-input-${index}`);
    if (input instanceof HTMLInputElement) {
        input.click();
    }
}

function selectPhotoCamera(index) {
    const input = document.getElementById(`produto-foto-camera-input-${index}`);
    if (input instanceof HTMLInputElement) {
        input.click();
    }
}

async function choosePhotoUpload() {
    const index = Number(activePhotoIndex.value);
    if (!Number.isInteger(index) || index < 0) {
        closePhotoPickerModal();
        return;
    }

    photoPickerModalOpen.value = false;
    await nextTick();
    selectPhotoFile(index);
    activePhotoIndex.value = null;
    photoLinkDraft.value = '';
}

async function choosePhotoCamera() {
    const index = Number(activePhotoIndex.value);
    if (!Number.isInteger(index) || index < 0) {
        closePhotoPickerModal();
        return;
    }

    photoPickerModalOpen.value = false;
    await nextTick();
    selectPhotoCamera(index);
    activePhotoIndex.value = null;
    photoLinkDraft.value = '';
}

function resolvePhotoNameFromLink(link) {
    try {
        const parsed = new URL(link);
        const candidate = decodeURIComponent(parsed.pathname.split('/').pop() || '').trim();
        if (candidate) return candidate;
    } catch {
        // fallback below for plain text links/paths
    }

    const fallback = decodeURIComponent(String(link || '').split('/').pop() || '').trim();
    return fallback || 'Foto por link';
}

function savePhotoLink() {
    const index = Number(activePhotoIndex.value);
    if (!Number.isInteger(index) || index < 0) {
        closePhotoPickerModal();
        return;
    }

    const url = String(photoLinkDraft.value || '').trim();
    if (!url) {
        return;
    }

    form.informacao_adicional.fotos[index] = {
        nome: resolvePhotoNameFromLink(url),
        url,
    };

    closePhotoPickerModal();
}

function onPhotoFileSelected(index, event) {
    const target = event?.target;
    if (!(target instanceof HTMLInputElement) || !target.files || target.files.length === 0) {
        return;
    }

    const file = target.files[0];
    const reader = new FileReader();
    reader.onload = () => {
        form.informacao_adicional.fotos[index] = {
            nome: file.name,
            url: String(reader.result || ''),
        };
        target.value = '';
    };
    reader.readAsDataURL(file);
}

function removePhotoFile(index) {
    if (index <= 0) {
        form.informacao_adicional.fotos[0] = createEmptyPhotoSlot();
        return;
    }

    form.informacao_adicional.fotos.splice(index, 1);

    if (form.informacao_adicional.fotos.length === 0) {
        form.informacao_adicional.fotos.push(createEmptyPhotoSlot());
    }
}

function goToComposicaoFirstPage() {
    composicaoPage.value = 1;
}

function goToComposicaoPrevPage() {
    if (composicaoPage.value > 1) {
        composicaoPage.value -= 1;
    }
}

function goToComposicaoNextPage() {
    if (composicaoPage.value < composicaoTotalPages.value) {
        composicaoPage.value += 1;
    }
}

function goToComposicaoLastPage() {
    composicaoPage.value = composicaoTotalPages.value;
}

function removeBarcodeRow(row) {
    const idx = form.codigos_barras.indexOf(row);
    if (idx >= 0) {
        form.codigos_barras.splice(idx, 1);
    }
}

function toggleEstoqueAtributo(key) {
    const current = !!form.estoque_detalhado.atributos_logisticos_flags[key];
    form.estoque_detalhado.atributos_logisticos_flags[key] = !current;
}

function addEmbalagemComercial() {
    form.estoque_detalhado.embalagens.push({
        descricao: '',
        unidade_comercial: '',
        quantidade_contida: '',
        unidade_base_referencia: '',
        fator_conversao: '',
        custo_embalagem: '',
        preco_embalagem: '',
        codigo_barras: '',
    });
}

function removeEmbalagemComercial(index) {
    form.estoque_detalhado.embalagens.splice(index, 1);
}

function goToBarcodeFirstPage() {
    barcodePage.value = 1;
}

function goToBarcodePrevPage() {
    if (barcodePage.value > 1) {
        barcodePage.value -= 1;
    }
}

function goToBarcodeNextPage() {
    if (barcodePage.value < barcodeTotalPages.value) {
        barcodePage.value += 1;
    }
}

function goToBarcodeLastPage() {
    barcodePage.value = barcodeTotalPages.value;
}

function buildPayload() {
    syncPrimaryBarcodeFromBasic();

    const parsedLogistics = safeParseAtributosLogisticos(form.atributos_logisticos_json) || {};

    parsedLogistics.fiscal_ncm = String(form.fiscal_ncm || '').trim() || null;
    parsedLogistics.fiscal_ncm_ex = String(form.fiscal_ncm_ex || '').trim() || null;
    parsedLogistics.fiscal_cest = String(form.fiscal_cest || '').trim() || null;
    parsedLogistics.conta_contabil = String(form.conta_contabil || '').trim() || null;
    parsedLogistics.nr_contrato = String(form.nr_contrato || '').trim() || null;
    parsedLogistics.classificacoes_niveis_adicionais = form.classificacoes_niveis_adicionais
        .map((value) => String(value || '').trim())
        .filter(Boolean);
    parsedLogistics.descricao_site = String(form.descricao_site || '').trim() || null;
    parsedLogistics.descricao_detalhada = String(form.descricao_detalhada || '').trim() || null;
    parsedLogistics.empresas_vinculadas = form.empresas_vinculadas;
    parsedLogistics.clientes_vinculados = form.clientes_vinculados;
    parsedLogistics.estoque_detalhado = form.estoque_detalhado;
    parsedLogistics.gerencial_memoria = form.gerencial_memoria;
    const normalizedCompositions = (Array.isArray(form.informacao_adicional.composicoes)
        ? form.informacao_adicional.composicoes
        : []
    ).map((row) => normalizeCompositionRow(row)).map((row) => ({
        ...row,
        calculate_cost: row.calculate_cost !== false,
        operational_cost: Math.max(0, Number(row.operational_cost || 0)),
        campos_adicionais: (Array.isArray(row.campos_adicionais) ? row.campos_adicionais : [])
            .map((field) => normalizeCompositionAdditionalField(field))
            .map((field) => ({
                ...field,
                operational_cost: Math.max(0, Number(field.operational_cost || 0)),
            })),
    }));

    parsedLogistics.informacao_adicional = {
        ...form.informacao_adicional,
        composicoes: normalizedCompositions,
        fotos: normalizePhotoSlots(form.informacao_adicional.fotos),
    };

    return {
        estabelecimento_id: form.estabelecimento_id || null,
        produto_mestre_id: form.produto_mestre_id || null,
        fiscal_item_profile_id: form.fiscal_item_profile_id || null,
        fiscal_item_profile_entrada_id: form.fiscal_item_profile_entrada_id || null,
        fiscal_item_profile_saida_id: form.fiscal_item_profile_saida_id || null,
        classificacao_mercadologica_id: form.classificacao_mercadologica_id || null,
        unidade_medida_id: form.unidade_medida_id || null,
        produto_familia_id: form.produto_familia_id || null,
        cod_sku: form.cod_sku || null,
        codigo_operacional: form.codigo_operacional || null,
        codigo_operacional_manual: !!form.codigo_operacional_manual,
        descricao: form.descricao,
        descricao_curta: form.descricao_curta || null,
        produto_tipo: form.produto_tipo || null,
        situacao: form.situacao || null,
        liberado: form.liberado || 'sim',
        marca: form.marca || null,
        palavra_chave: form.palavra_chave || null,
        permite_fracionamento: !!form.permite_fracionamento,
        atributos_logisticos: parsedLogistics,
        precos: form.precos.map((row) => ({
            id: row.id || undefined,
            tipo: row.tipo,
            codigo: row.codigo || null,
            canal: row.canal || null,
            valor: toDecimal(row.valor),
            percentual: row.percentual === '' ? null : toDecimal(row.percentual),
            custo_referencial: row.custo_referencial === '' ? null : toDecimal(row.custo_referencial),
            margem: row.margem === '' ? null : toDecimal(row.margem),
            margem_preco_minimo: row.margem_preco_minimo === '' ? null : toDecimal(row.margem_preco_minimo),
            vigencia_inicio: row.vigencia_inicio || null,
            vigencia_fim: row.vigencia_fim || null,
            ativo: !!row.ativo,
        })),
        codigos_barras: form.codigos_barras.map((row) => ({
            id: row.id || undefined,
            produto_apresentacao_id: row.produto_apresentacao_id || null,
            codigo: row.codigo,
            tipo_codigo: row.tipo_codigo || null,
            principal: !!row.principal,
            informacoes_complementares: row.informacoes_complementares || null,
            ativo: !!row.ativo,
        })),
        estoque: {
            quantidade: Number(form.estoque.quantidade || 0),
            quantidade_minima: form.estoque.quantidade_minima === '' ? null : Number(form.estoque.quantidade_minima),
            quantidade_maxima: form.estoque.quantidade_maxima === '' ? null : Number(form.estoque.quantidade_maxima),
            numero_lote: form.estoque.numero_lote || null,
            reduzir_estoque: !!form.estoque.reduzir_estoque,
            quantidade_minima_vendavel: form.estoque.quantidade_minima_vendavel === '' ? null : Number(form.estoque.quantidade_minima_vendavel),
            quantidade_alerta: form.estoque.quantidade_alerta === '' ? null : Number(form.estoque.quantidade_alerta),
        },
    };
}

async function loadSupportData() {
    const { data } = await api.get('/catalog/products/support-data');

    supportData.unidades_medida = Array.isArray(data?.unidades_medida) ? data.unidades_medida : [];
    supportData.familias = Array.isArray(data?.familias) ? data.familias : [];
    supportData.classificacoes_mercadologicas = Array.isArray(data?.classificacoes_mercadologicas) ? data.classificacoes_mercadologicas : [];
    supportData.fiscal_item_profiles = Array.isArray(data?.fiscal_item_profiles) ? data.fiscal_item_profiles : [];
    supportData.tipos_preco = Array.isArray(data?.tipos_preco) ? data.tipos_preco : [];
    supportData.situacoes = Array.isArray(data?.situacoes) ? data.situacoes : [];
    supportData.produto_tipos = Array.isArray(data?.produto_tipos) ? data.produto_tipos : [];
}

async function loadProduct(productId) {
    const { data } = await api.get(`/catalog/products/${productId}`);
    applyFormPayload(data || {});
}

async function bootstrap() {
    loading.value = true;
    error.value = '';
    validationIssues.value = [];
    activeTab.value = 'dados_basicos';

    try {
        await loadSupportData();

        if (isCreateRoute.value) {
            resetForm();
            addPreco();
            addCodigoBarras();
        } else {
            await loadProduct(currentProductId.value);
        }
    } catch (requestError) {
        error.value = requestError?.response?.data?.message ?? 'Falha ao carregar o editor de produto.';
    } finally {
        loading.value = false;
    }
}

function showSaveToast(message, tone = 'success') {
    toastMessage.value = String(message || '').trim();
    toastTone.value = tone;
    toastVisible.value = toastMessage.value !== '';

    if (toastTimeout) clearTimeout(toastTimeout);
    toastTimeout = setTimeout(() => {
        toastVisible.value = false;
    }, 3200);
}

function normalizeValidationFieldKey(field) {
    return String(field || '').replace(/\.\d+(?=\.|$)/g, '.*');
}

function resolveValidationFieldLabel(field) {
    const normalizedField = normalizeValidationFieldKey(field);
    const labels = {
        descricao: 'Descrição completa',
        liberado: 'Liberação',
        unidade_medida_id: 'Unidade de medida',
        produto_familia_id: 'Família',
        classificacao_mercadologica_id: 'Classificação mercadológica',
        fiscal_item_profile_id: 'Perfil fiscal',
        'precos.*.tipo': 'Tipo de preço',
        'precos.*.valor': 'Valor de preço',
        'codigos_barras.*.codigo': 'Código de barras',
        'estoque.quantidade': 'Quantidade em estoque',
        'estoque.quantidade_minima': 'Quantidade mínima',
        'estoque.quantidade_maxima': 'Quantidade máxima',
        'atributos_logisticos.informacao_adicional.composicoes.*.calculate_cost': 'Composição: incluir no custo',
        'atributos_logisticos.informacao_adicional.composicoes.*.operational_cost': 'Composição: custo operacional',
        'atributos_logisticos.informacao_adicional.composicoes.*.campos_adicionais.*.operational_cost': 'Composição: custo operacional do campo',
    };

    if (labels[normalizedField]) return labels[normalizedField];

    const leaf = normalizedField.split('.').pop() || normalizedField;
    const readable = leaf.replace(/\*/g, '').replace(/_/g, ' ').trim();
    return readable ? readable.charAt(0).toUpperCase() + readable.slice(1) : 'Campo';
}

function resolveTabByValidationField(field) {
    const normalizedField = normalizeValidationFieldKey(field);
    if (normalizedField.startsWith('precos.')) return 'gerencial';
    if (normalizedField.startsWith('codigos_barras.')) return 'codigo_barras';
    if (normalizedField.startsWith('estoque.')) return 'estoque';
    if (normalizedField.startsWith('atributos_logisticos.informacao_adicional')) return 'informacao_adicional';
    return 'dados_basicos';
}

function buildFriendlyValidationMessage(field, backendMessage) {
    const label = resolveValidationFieldLabel(field);
    const message = String(backendMessage || '').trim();
    const normalizedField = normalizeValidationFieldKey(field);

    if (!message || message.startsWith('validation.')) {
        if (message.includes('required_with')) {
            if (normalizedField === 'precos.*.tipo') return `${label}: informe o tipo em cada linha de preço preenchida.`;
            if (normalizedField === 'precos.*.valor') return `${label}: informe o valor em cada linha de preço preenchida.`;
            if (normalizedField === 'codigos_barras.*.codigo') return `${label}: informe o código em cada linha de código de barras.`;
            return `${label}: campo obrigatório quando o bloco relacionado estiver preenchido.`;
        }
        if (message.includes('required')) return `${label}: campo obrigatório.`;
        if (message.includes('numeric')) return `${label}: informe um número válido.`;
        if (message.includes('uuid')) return `${label}: selecione uma opção válida.`;
        return `${label}: valor inválido.`;
    }

    if (/required/i.test(message)) return `${label}: campo obrigatório.`;
    if (/numeric|number/i.test(message)) return `${label}: informe um número válido.`;

    return `${label}: ${message}`;
}

function extractValidationIssues(rawErrors) {
    if (!rawErrors || typeof rawErrors !== 'object') return [];

    return Object.entries(rawErrors)
        .map(([field, messages]) => {
            const first = Array.isArray(messages) && messages.length ? messages[0] : '';
            return buildFriendlyValidationMessage(field, first);
        })
        .filter(Boolean);
}

async function save() {
    if (!canSave.value) {
        error.value = 'Preencha pelo menos a descrição completa do produto.';
        validationIssues.value = ['Descrição completa: campo obrigatório.'];
        activeTab.value = 'dados_basicos';
        showSaveToast('Revise os campos obrigatórios antes de salvar.', 'danger');
        return;
    }

    saving.value = true;
    error.value = '';
    validationIssues.value = [];

    try {
        const payload = buildPayload();

        if (isEditing.value) {
            const { data } = await api.put(`/catalog/products/${form.id}`, payload);
            applyFormPayload(data || {});
        } else {
            const { data } = await api.post('/catalog/products', payload);
            applyFormPayload(data || {});
            if (data?.id) {
                await router.replace(`/configuracoes/produtos/${data.id}/editar`);
            }
        }

        showSaveToast(
            isEditing.value
                ? 'Alterações salvas com sucesso.'
                : 'Produto cadastrado com sucesso.',
            'success',
        );
    } catch (requestError) {
        if (requestError?.response?.status === 422) {
            const raw = requestError.response.data?.errors || {};
            const firstField = Object.keys(raw)[0];
            const firstMessage = firstField ? raw[firstField]?.[0] : '';
            validationIssues.value = extractValidationIssues(raw);
            error.value = validationIssues.value[0] || firstMessage || 'Erro de validação no formulário.';
            if (firstField) {
                activeTab.value = resolveTabByValidationField(firstField);
            }
            showSaveToast('Não foi possível salvar. Verifique os campos destacados.', 'danger');
        } else {
            error.value = requestError?.response?.data?.message ?? 'Falha ao salvar produto.';
            validationIssues.value = [];
            showSaveToast(error.value, 'danger');
        }
    } finally {
        saving.value = false;
    }
}

function goList() {
    router.push('/configuracoes/produtos');
}

function clearParameterQuickErrors() {
    parameterQuickModalError.value = '';
    Object.keys(parameterQuickFormErrors).forEach((key) => {
        parameterQuickFormErrors[key] = '';
    });
}

function toQuickCodeSeed(value, fallback = 'NOVO') {
    const seed = String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-zA-Z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .toUpperCase();
    return seed || fallback;
}

function openParameterQuickModal(type) {
    parameterQuickModalType.value = type;
    clearParameterQuickErrors();

    if (type === 'familia') {
        const seedName = String(form.descricao || '').trim();
        quickFamilyForm.nome = seedName;
        quickFamilyForm.codigo = toQuickCodeSeed(seedName, 'FAMILIA').slice(0, 30);
        quickFamilyForm.ativo = true;
    } else if (type === 'unidade') {
        quickUnitForm.unidade = '';
        quickUnitForm.descricao = '';
        quickUnitForm.decimais = '0';
        quickUnitForm.status = true;
    } else {
        const seedName = String(form.descricao_curta || form.descricao || '').trim();
        quickClassificationForm.descricao = seedName;
        quickClassificationForm.codigo = toQuickCodeSeed(seedName, 'CLASS').slice(0, 30);
        quickClassificationForm.parent_id = '';
        quickClassificationForm.ativo = true;
    }

    parameterQuickModalOpen.value = true;
}

function closeParameterQuickModal() {
    if (parameterQuickModalSaving.value) return;
    parameterQuickModalOpen.value = false;
}

function goToParameterManagement(type) {
    const routesByType = {
        familias: '/configuracoes/catalogo/parametros/familias',
        unidades: '/configuracoes/catalogo/parametros/unidades-medida',
        classificacoes: '/configuracoes/catalogo/parametros/classificacoes-mercadologicas',
        central: '/configuracoes/catalogo/parametros',
    };
    router.push(routesByType[type] || routesByType.central);
}

function applyQuickValidationErrors(errors = {}) {
    if (parameterQuickModalType.value === 'familia') {
        if (Array.isArray(errors.codigo) && errors.codigo.length) parameterQuickFormErrors.familiaCodigo = errors.codigo[0];
        if (Array.isArray(errors.nome) && errors.nome.length) parameterQuickFormErrors.familiaNome = errors.nome[0];
        return;
    }

    if (parameterQuickModalType.value === 'unidade') {
        if (Array.isArray(errors.unidade) && errors.unidade.length) parameterQuickFormErrors.unidadeCodigo = errors.unidade[0];
        if (Array.isArray(errors.descricao) && errors.descricao.length) parameterQuickFormErrors.unidadeDescricao = errors.descricao[0];
        if (Array.isArray(errors.decimais) && errors.decimais.length) parameterQuickFormErrors.unidadeDecimais = errors.decimais[0];
        return;
    }

    if (Array.isArray(errors.codigo) && errors.codigo.length) parameterQuickFormErrors.classificacaoCodigo = errors.codigo[0];
    if (Array.isArray(errors.descricao) && errors.descricao.length) parameterQuickFormErrors.classificacaoDescricao = errors.descricao[0];
}

async function saveQuickParameter() {
    clearParameterQuickErrors();
    parameterQuickModalSaving.value = true;

    try {
        if (parameterQuickModalType.value === 'familia') {
            if (!String(quickFamilyForm.codigo || '').trim()) {
                parameterQuickFormErrors.familiaCodigo = 'Informe o código da família.';
                return;
            }
            if (!String(quickFamilyForm.nome || '').trim()) {
                parameterQuickFormErrors.familiaNome = 'Informe o nome da família.';
                return;
            }

            const payload = {
                codigo: String(quickFamilyForm.codigo || '').trim(),
                nome: String(quickFamilyForm.nome || '').trim(),
                descricao: null,
                codigo_prefixo: null,
                modo_geracao_codigo: 'SEQUENCIAL_FAMILIA',
                faixa_inicial: null,
                faixa_final: null,
                proximo_numero: null,
                ativo: !!quickFamilyForm.ativo,
            };
            const { data } = await api.post('/catalog/families', payload);
            await loadSupportData();
            form.produto_familia_id = data?.id || '';
            showSaveToast('Família criada com sucesso.', 'success');
        } else if (parameterQuickModalType.value === 'unidade') {
            const decimais = Number(String(quickUnitForm.decimais || '').trim());

            if (!String(quickUnitForm.unidade || '').trim()) {
                parameterQuickFormErrors.unidadeCodigo = 'Informe a sigla da unidade.';
                return;
            }
            if (!String(quickUnitForm.descricao || '').trim()) {
                parameterQuickFormErrors.unidadeDescricao = 'Informe a descrição da unidade.';
                return;
            }
            if (!Number.isInteger(decimais) || decimais < 0 || decimais > 6) {
                parameterQuickFormErrors.unidadeDecimais = 'Informe um número entre 0 e 6.';
                return;
            }

            const payload = {
                unidade: String(quickUnitForm.unidade || '').trim().toUpperCase(),
                descricao: String(quickUnitForm.descricao || '').trim(),
                descricao_plural: null,
                artigo: null,
                codigo_fiscal: null,
                decimais,
                status: !!quickUnitForm.status,
            };
            const { data } = await api.post('/catalog/units', payload);
            await loadSupportData();
            form.unidade_medida_id = data?.id || '';
            showSaveToast('Unidade criada com sucesso.', 'success');
        } else {
            if (!String(quickClassificationForm.codigo || '').trim()) {
                parameterQuickFormErrors.classificacaoCodigo = 'Informe o código da classificação.';
                return;
            }
            if (!String(quickClassificationForm.descricao || '').trim()) {
                parameterQuickFormErrors.classificacaoDescricao = 'Informe a descrição da classificação.';
                return;
            }

            const payload = {
                parent_id: String(quickClassificationForm.parent_id || '').trim() || null,
                codigo: String(quickClassificationForm.codigo || '').trim(),
                descricao: String(quickClassificationForm.descricao || '').trim(),
                descricao_reduzida: null,
                ordem: null,
                ativo: !!quickClassificationForm.ativo,
                parametros_observacoes: [],
            };
            const { data } = await api.post('/catalog/classifications', payload);
            await loadSupportData();
            form.classificacao_mercadologica_id = data?.id || '';
            showSaveToast('Classificação criada com sucesso.', 'success');
        }

        parameterQuickModalOpen.value = false;
    } catch (requestError) {
        const validationErrors = requestError?.response?.data?.errors || {};
        applyQuickValidationErrors(validationErrors);
        parameterQuickModalError.value = requestError?.response?.data?.message ?? 'Não foi possível salvar o cadastro rápido.';
    } finally {
        parameterQuickModalSaving.value = false;
    }
}

onMounted(bootstrap);
</script>

<template>
    <div class="space-y-4">
        <section class="product-editor-layout">
            <header class="product-editor-header">
                <div>
                    <p class="product-breadcrumb">Catálogo > Produtos</p>
                    <h1 class="product-editor-title">{{ isEditing ? 'Edição de Produto' : 'Novo Produto' }}</h1>
                </div>
                <div class="product-editor-actions">
                    <AppButton :loading="saving" :disabled="!canSave" @click="save">Salvar</AppButton>
                    <AppButton variant="secondary" @click="goList">Cancelar</AppButton>
                </div>
            </header>

            <div class="product-top-tabs">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    type="button"
                    class="product-tab-btn"
                    :class="{ 'is-active': activeTab === tab.id }"
                    @click="activeTab = tab.id"
                >
                    {{ tab.label }}
                </button>
            </div>

            <section class="product-stage">
                <p v-if="error" class="text-sm text-danger">{{ error }}</p>
                <ul v-if="validationIssues.length > 1" class="product-validation-list">
                    <li v-for="issue in validationIssues" :key="issue">{{ issue }}</li>
                </ul>
                <p v-if="loading" class="text-sm text-muted">Carregando...</p>

                <template v-if="!loading">
                    <section v-if="activeTab === 'dados_basicos'" class="space-y-4">
                        <div class="product-basic-title-wrap">
                            <h2 class="product-basic-title">Dados Basicos</h2>
                            <p class="product-basic-subtitle">Adicione os Dados Basicos do Produto</p>
                        </div>

                        <div class="product-card">
                            <div class="card-section-header">
                                <div class="card-section-icon">
                                    <FileText :size="18" />
                                </div>
                                <div>
                                    <h2>Identificação do produto</h2>
                                    <p>Dados principais para reconhecimento, status e descrição do item.</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-start">
                                <div class="md:col-span-2">
                                    <AppInput v-model="form.codigo_operacional" label="Código operacional" placeholder="Definido conforme configurações da loja" />
                                    <p class="field-caption">Código imutável após criação do produto.</p>
                                </div>
                                <AppInput :model-value="formattedCreatedAt" label="Cadastro" placeholder="dd/mm/aaaa" disabled />
                                <AppInput :model-value="formattedUpdatedAt" label="Alteração" placeholder="dd/mm/aaaa" disabled />

                                <AppInput v-model="form.estabelecimento_id" label="Estabelecimento*" placeholder="Freeline" />
                                <AppSelect v-model="form.situacao" label="Status*">
                                    <option v-for="option in supportData.situacoes" :key="option.id" :value="option.id">{{ option.label }}</option>
                                </AppSelect>
                                <AppInput v-model="form.cod_sku" label="SKU" />
                                <div>
                                    <label class="ui-field-wrap">
                                        <span class="ui-label">EAN</span>
                                        <div class="ean-group">
                                            <select v-model="form.ean_tipo" class="ui-field ean-type">
                                                <option value="GTIN-13">GTIN-13</option>
                                                <option value="GTIN-14">GTIN-14</option>
                                                <option value="EAN-8">EAN-8</option>
                                            </select>
                                            <input v-model="form.ean_codigo" class="ui-field ean-code" placeholder="0000000000000">
                                        </div>
                                    </label>
                                </div>

                                <AppInput v-model="form.descricao" label="Descrição completa*" class="md:col-span-2" />
                                <div class="md:col-span-2">
                                    <AppInput v-model="form.descricao_curta" label="Descrição curta" />
                                    <p class="field-caption">Máximo de 20 caracteres.</p>
                                </div>

                                <div class="md:col-span-4">
                                    <label class="ui-field-wrap">
                                        <span class="ui-label">Família</span>
                                        <div class="select-action-row">
                                            <select v-model="form.produto_familia_id" class="ui-field">
                                                <option value="">Sem família</option>
                                                <option v-for="row in supportData.familias" :key="row.id" :value="row.id">{{ row.nome }}</option>
                                            </select>
                                            <button type="button" class="icon-action-btn" title="Adicionar família" @click="openParameterQuickModal('familia')">
                                                <Plus :size="16" />
                                            </button>
                                            <AppButton type="button" class="link-action-btn" title="Gerenciar família" @click="goToParameterManagement('familias')">
                                                <LinkIcon :size="15" />
                                                Gerenciar
                                            </AppButton>
                                        </div>
                                    </label>
                                </div>

                            </div>
                        </div>

                        <div class="product-card">
                            <div class="card-section-header">
                                <div class="card-section-icon">
                                    <Boxes :size="18" />
                                </div>
                                <div>
                                    <h2>Organização comercial e cadastro auxiliar</h2>
                                    <p>Estruture unidade e marca para uso comercial diário.</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="md:col-span-2">
                                    <label class="ui-field-wrap">
                                        <span class="ui-label">Unidade de medida*</span>
                                        <div class="select-action-row">
                                            <select v-model="form.unidade_medida_id" class="ui-field">
                                                <option value="">Selecione uma unidade de medida</option>
                                                <option v-for="row in supportData.unidades_medida" :key="row.id" :value="row.id">{{ row.unidade }} - {{ row.descricao }}</option>
                                            </select>
                                            <button type="button" class="icon-action-btn" title="Adicionar unidade" @click="openParameterQuickModal('unidade')">
                                                <Plus :size="16" />
                                            </button>
                                            <AppButton type="button" class="link-action-btn" title="Gerenciar unidade" @click="goToParameterManagement('unidades')">
                                                <LinkIcon :size="15" />
                                                Gerenciar
                                            </AppButton>
                                        </div>
                                    </label>
                                </div>
                                <AppInput v-model="form.marca" label="Marca*" />
                            </div>
                            <div class="note-banner">
                                <div class="note-banner-text">
                                    <Info :size="16" />
                                    <span>Famílias, unidades e classificações possuem backoffice dedicado para manutenção e governança do cadastro.</span>
                                </div>
                                <AppButton variant="secondary" @click="goToParameterManagement('central')">Abrir parâmetros de produto</AppButton>
                            </div>
                        </div>

                        <div class="product-card">
                            <div class="card-section-header">
                                <div class="card-section-icon">
                                    <ShieldCheck :size="18" />
                                </div>
                                <div>
                                    <h2>Classificação fiscal e mercadológica</h2>
                                    <p>Defina dados fiscais e organize a classificação de mercado em até 5 níveis.</p>
                                </div>
                            </div>
                            <div class="product-fiscal-grid">
                                <div class="product-fiscal-classification">
                                    <label class="ui-field-wrap">
                                        <span class="ui-label">Classificação mercadológica (nível principal)</span>
                                        <div class="select-action-row">
                                            <select v-model="form.classificacao_mercadologica_id" class="ui-field">
                                                <option value="">Selecione uma classificação</option>
                                                <option v-for="row in supportData.classificacoes_mercadologicas" :key="row.id" :value="row.id">
                                                    Nível {{ row.nivel }} - {{ row.descricao }}
                                                </option>
                                            </select>
                                            <button type="button" class="icon-action-btn" title="Adicionar classificação" @click="openParameterQuickModal('classificacao')">
                                                <Plus :size="16" />
                                            </button>
                                            <AppButton type="button" class="link-action-btn" title="Gerenciar classificação" @click="goToParameterManagement('classificacoes')">
                                                <LinkIcon :size="15" />
                                                Gerenciar
                                            </AppButton>
                                        </div>
                                    </label>
                                </div>
                                <label class="ui-field-wrap product-fiscal-ncm">
                                    <span class="ui-label">NCM*</span>
                                    <div class="field-with-icon">
                                        <input v-model="form.fiscal_ncm" class="ui-field product-fiscal-short-input" placeholder="Ex: 1234.56.78">
                                        <button type="button" class="input-icon-btn" title="Buscar NCM">
                                            <Search :size="15" />
                                        </button>
                                    </div>
                                </label>
                                <AppInput v-model="form.fiscal_ncm_ex" label="NCM Ex" placeholder="Ex: 01" class="product-fiscal-short-field" />
                                <AppInput v-model="form.fiscal_cest" label="CEST" placeholder="Ex: 1234567" class="product-fiscal-short-field" />
                            </div>
                        </div>
                    </section>

                    <section v-if="activeTab === 'dados_opcionais'" class="space-y-4">
                        <div class="product-basic-title-wrap">
                            <h2 class="product-basic-title">Opcionais</h2>
                            <p class="product-basic-subtitle">Adicione os Opcionais do Produto</p>
                        </div>

                        <div class="product-card">
                            <h2>Dados Opcionais</h2>
                            <p>Informações complementares para comercial, site e vínculos de uso do produto.</p>

                            <div class="product-card optional-inner-card">
                                <div class="card-section-header">
                                    <div class="card-section-icon">
                                        <BriefcaseBusiness :size="18" />
                                    </div>
                                    <div>
                                        <h2>Configurações comerciais</h2>
                                        <p>Defina parâmetros de operação comercial e classificação auxiliar.</p>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <AppInput v-model="form.conta_contabil" label="Conta contábil" placeholder="Ex: 3.1.101" />
                                        <p class="field-caption">Usada para integração e classificação contábil.</p>
                                    </div>
                                    <div>
                                        <AppInput v-model="form.nr_contrato" label="Nr contrato" placeholder="Ex: 900" />
                                        <p class="field-caption">Identificador contratual relacionado ao produto.</p>
                                    </div>
                                    <div>
                                        <AppInput v-model="form.palavra_chave" label="Palavras-chave" placeholder="seed,teste" />
                                        <p class="field-caption">Termos para facilitar busca, filtros e localização do produto.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="product-card optional-inner-card">
                                <div class="card-section-header">
                                    <div class="card-section-icon">
                                        <Layers3 :size="18" />
                                    </div>
                                    <div>
                                        <h2>Classificação Mercadológica Adicional</h2>
                                        <p>Níveis complementares (2 a 5) para detalhar o enquadramento do produto.</p>
                                    </div>
                                </div>
                                <div class="optional-level-grid">
                                    <div
                                        v-for="(nivel, index) in form.classificacoes_niveis_adicionais"
                                        :key="`nivel-adicional-${index}`"
                                        class="optional-level-box"
                                    >
                                        <label class="ui-field-wrap">
                                            <span class="ui-label">NÍVEL {{ index + 2 }}</span>
                                            <select v-model="form.classificacoes_niveis_adicionais[index]" class="ui-field">
                                                <option value="">Selecione</option>
                                                <option
                                                    v-for="row in supportData.classificacoes_mercadologicas"
                                                    :key="`${row.id}-nivel-${index}`"
                                                    :value="row.id"
                                                >
                                                    {{ row.descricao }}
                                                </option>
                                            </select>
                                        </label>
                                    </div>
                                </div>
                                <div class="optional-level-action">
                                    <button type="button" class="add-level-btn" @click="addNivelAdicional">
                                        <Plus :size="16" />
                                        Adicionar nível
                                    </button>
                                </div>
                            </div>

                            <div class="product-card optional-inner-card">
                                <div class="card-section-header">
                                    <div class="card-section-icon">
                                        <BookText :size="18" />
                                    </div>
                                    <div>
                                        <h2>Conteúdo e apresentação</h2>
                                        <p>Textos para contexto comercial e exibição em canais digitais.</p>
                                    </div>
                                </div>
                                <div class="space-y-4">
                                    <div>
                                        <AppInput v-model="form.descricao_site" label="Descrição p/ Site" placeholder="Resumo para listagens e cards de produto" />
                                        <div class="counter-row">
                                            <span class="field-caption">Resumo curto usado em listagens e exibição simplificada do produto.</span>
                                            <span class="field-caption">{{ descricaoSiteLength }} caracteres</span>
                                        </div>
                                    </div>
                                    <div>
                                        <AppTextarea
                                            v-model="form.descricao_detalhada"
                                            label="Descrição Detalhada"
                                            rows="7"
                                            placeholder="Texto completo para apresentação comercial e contexto detalhado do produto"
                                        />
                                        <div class="counter-row">
                                            <span class="field-caption">Texto completo para apresentação comercial ou página detalhada do produto.</span>
                                            <span class="field-caption">{{ descricaoDetalhadaLength }} caracteres</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="product-card optional-inner-card">
                                <div class="card-section-header">
                                    <div class="card-section-icon">
                                        <Link2 :size="18" />
                                    </div>
                                    <div>
                                        <h2>Vinculações</h2>
                                        <p>Associe o produto a contextos de uso para facilitar operação e governança.</p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="ui-field-wrap">
                                            <span class="ui-label">Empresas vinculadas</span>
                                            <div class="combobox-row">
                                                <AppCombobox
                                                    v-model="form.empresa_combo"
                                                    :options="empresasComboboxOptions"
                                                    placeholder="Pesquise ou selecione"
                                                />
                                                <button type="button" class="combobox-link-btn" @click="vincularEmpresa">Vincular</button>
                                            </div>
                                        </label>
                                        <ul class="chips-list">
                                            <li v-for="(empresa, index) in form.empresas_vinculadas" :key="`empresa-vinculada-${empresa}-${index}`" class="chip-item">
                                                <span>{{ empresa }}</span>
                                                <button type="button" class="chip-remove-btn" @click="removerEmpresa(index)">x</button>
                                            </li>
                                            <li v-if="form.empresas_vinculadas.length === 0" class="chip-empty">Nenhuma empresa vinculada</li>
                                        </ul>
                                    </div>

                                    <div>
                                        <label class="ui-field-wrap">
                                            <span class="ui-label">Clientes vinculados</span>
                                            <div class="combobox-row">
                                                <AppCombobox
                                                    v-model="form.cliente_combo"
                                                    :options="clientesComboboxOptions"
                                                    placeholder="Pesquise ou selecione"
                                                />
                                                <button type="button" class="combobox-link-btn" @click="vincularCliente">Vincular</button>
                                            </div>
                                        </label>
                                        <ul class="chips-list">
                                            <li v-for="(cliente, index) in form.clientes_vinculados" :key="`cliente-vinculado-${cliente}-${index}`" class="chip-item">
                                                <span>{{ cliente }}</span>
                                                <button type="button" class="chip-remove-btn" @click="removerCliente(index)">x</button>
                                            </li>
                                            <li v-if="form.clientes_vinculados.length === 0" class="chip-empty">Nenhum cliente vinculado</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section v-if="activeTab === 'codigo_barras'" class="space-y-4">
                        <div class="product-basic-title-wrap">
                            <h2 class="product-basic-title">Codigo de Barras</h2>
                            <p class="product-basic-subtitle">Adicione os codigos de barras do produto.</p>
                        </div>

                        <div class="product-card">
                            <div class="barcode-top-actions">
                                <AppButton @click="openBarcodeModal()">
                                    <CirclePlus :size="16" />
                                    Adicionar
                                </AppButton>
                                <AppButton variant="secondary">
                                    <Filter :size="16" />
                                    Filtrar
                                </AppButton>
                            </div>

                            <div class="barcode-grid-shell">
                                <div class="barcode-grid-controls">
                                    <select class="ui-field barcode-columns-select">
                                        <option>Colunas</option>
                                    </select>
                                    <div class="barcode-search-wrap">
                                        <input v-model="barcodeSearch" class="ui-field" placeholder="Pesquisar...">
                                        <Search :size="18" class="barcode-search-icon" />
                                    </div>
                                    <div class="barcode-pages-wrap">
                                        <span>Linhas por pagina</span>
                                        <select v-model="barcodeRowsPerPage" class="ui-field barcode-page-select">
                                            <option :value="10">10</option>
                                            <option :value="20">20</option>
                                            <option :value="50">50</option>
                                        </select>
                                        <span>Pagina {{ barcodePage }} de {{ barcodeTotalPages }}</span>
                                        <div class="barcode-nav-btns">
                                            <button type="button" class="nav-icon-btn" @click="goToBarcodeFirstPage"><ChevronsLeft :size="16" /></button>
                                            <button type="button" class="nav-icon-btn" @click="goToBarcodePrevPage"><ChevronLeft :size="16" /></button>
                                            <button type="button" class="nav-icon-btn" @click="goToBarcodeNextPage"><ChevronRight :size="16" /></button>
                                            <button type="button" class="nav-icon-btn" @click="goToBarcodeLastPage"><ChevronsRight :size="16" /></button>
                                        </div>
                                    </div>
                                </div>

                                <AppTable>
                                    <thead>
                                        <tr>
                                            <th class="text-left">Ações</th>
                                            <th class="text-left">Código</th>
                                            <th class="text-left">Complemento</th>
                                            <th class="text-left">Saldo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="barcodePagedRows.length === 0">
                                            <td colspan="4" class="text-center text-muted py-14">Sem Resultados...</td>
                                        </tr>
                                        <tr v-for="(row, idx) in barcodePagedRows" :key="`${row.id || row.codigo}-${idx}`">
                                            <td>
                                                <div class="inline-flex items-center gap-2">
                                                    <AppButton variant="ghost" @click="openBarcodeModal(form.codigos_barras.indexOf(row))">Editar</AppButton>
                                                    <AppButton variant="danger" @click="removeBarcodeRow(row)">Remover</AppButton>
                                                </div>
                                            </td>
                                            <td class="text-xs font-mono">{{ row.tipo_codigo }} - {{ row.codigo }}</td>
                                            <td>{{ row.informacoes_complementares || '—' }}</td>
                                            <td>{{ row.ativo ? 'Ativo' : 'Inativo' }}</td>
                                        </tr>
                                    </tbody>
                                </AppTable>

                                <p class="barcode-grid-footer">0 de {{ barcodeFilteredRows.length }} linha(s) selecionada(s).</p>
                            </div>
                        </div>

                        <AppModal
                            :open="barcodeModalOpen"
                            title="Novo Código de barras"
                            width-class="max-w-5xl"
                            @close="closeBarcodeModal"
                        >
                            <div class="barcode-modal-grid">
                                <div class="barcode-modal-primary">
                                    <label class="ui-field-wrap">
                                        <span class="ui-label">Código de Barras</span>
                                        <div class="ean-group barcode-ean-group">
                                            <select v-model="barcodeModalForm.tipo_codigo" class="ui-field ean-type barcode-ean-type">
                                                <option value="GTIN-13">GTIN-13</option>
                                                <option value="GTIN-14">GTIN-14</option>
                                                <option value="EAN-8">EAN-8</option>
                                            </select>
                                            <input v-model="barcodeModalForm.codigo" class="ui-field ean-code barcode-ean-code" placeholder="7891234567895">
                                        </div>
                                    </label>
                                </div>
                                <AppInput
                                    v-model="barcodeModalForm.informacoes_complementares"
                                    class="barcode-modal-compact-field barcode-modal-info-field"
                                    label="Informações Complementares"
                                    placeholder="Ex: Código da unidade avulsa para venda"
                                />
                                <AppSelect
                                    v-model="barcodeModalForm.situacao"
                                    class="barcode-modal-compact-field barcode-modal-status-field"
                                    label="Situação"
                                >
                                    <option value="ativo">Ativo</option>
                                    <option value="inativo">Inativo</option>
                                </AppSelect>
                                <div class="barcode-modal-primary">
                                    <label class="ui-field-wrap">
                                        <span class="ui-label">Código de Barras da Caixa</span>
                                        <div class="ean-group barcode-ean-group">
                                            <select v-model="barcodeModalForm.tipo_codigo_caixa" class="ui-field ean-type barcode-ean-type">
                                                <option value="GTIN-14">GTIN-14</option>
                                                <option value="GTIN-13">GTIN-13</option>
                                            </select>
                                            <input v-model="barcodeModalForm.codigo_caixa" class="ui-field ean-code barcode-ean-code" placeholder="17891234567892">
                                        </div>
                                    </label>
                                </div>
                                <AppInput
                                    v-model="barcodeModalForm.sku"
                                    class="barcode-modal-compact-field barcode-modal-sku-field"
                                    label="SKU"
                                    placeholder="Ex: ARROZ-TIPO1-5KG-CX"
                                />
                            </div>

                            <div class="barcode-modal-actions">
                                <AppButton variant="secondary" @click="closeBarcodeModal">Cancelar</AppButton>
                                <AppButton @click="saveBarcodeModal">Salvar</AppButton>
                            </div>
                        </AppModal>
                    </section>

                    <section v-if="activeTab === 'informacao_adicional'" class="space-y-4">
                        <div class="info-adicional-subtabs">
                            <button
                                v-for="sub in infoAdicionalSubTabs"
                                :key="sub.id"
                                type="button"
                                class="info-subtab-btn"
                                :class="{ 'is-active': infoAdicionalSubTab === sub.id }"
                                @click="infoAdicionalSubTab = sub.id"
                            >
                                <Network v-if="sub.id === 'composicao'" :size="15" />
                                <ImagePlus v-else :size="15" />
                                {{ sub.label }}
                            </button>
                        </div>

                        <div v-if="infoAdicionalSubTab === 'composicao'" class="space-y-4">
                            <div class="product-basic-title-wrap">
                                <h2 class="product-basic-title">Código da Composição</h2>
                                <p class="product-basic-subtitle">Adicione os Códigos de Composição do Produto</p>
                            </div>

                            <div class="product-card">
                                <div class="section-inline-header">
                                    <div>
                                        <h2>Visualização da composição</h2>
                                        <p>Use grid para cadastro e organograma para leitura hierárquica.</p>
                                    </div>
                                    <div class="composition-view-mode">
                                        <button type="button" class="mode-btn" :class="{ 'is-active': composicaoViewMode === 'grid' }" @click="composicaoViewMode = 'grid'">Grid</button>
                                        <button type="button" class="mode-btn" :class="{ 'is-active': composicaoViewMode === 'organograma' }" @click="composicaoViewMode = 'organograma'">Organograma</button>
                                    </div>
                                </div>

                                <div v-if="composicaoViewMode === 'grid'" class="barcode-top-actions">
                                    <AppButton @click="openCompositionModal">
                                        <CirclePlus :size="16" />
                                        Adicionar
                                    </AppButton>
                                    <AppButton variant="secondary">
                                        <Filter :size="16" />
                                        Filtrar
                                    </AppButton>
                                </div>

                                <div v-if="composicaoViewMode === 'grid'" class="barcode-grid-shell">
                                    <div class="barcode-grid-controls">
                                        <select class="ui-field barcode-columns-select">
                                            <option>Colunas</option>
                                        </select>
                                        <div class="barcode-search-wrap">
                                            <input v-model="composicaoSearch" class="ui-field" placeholder="Pesquisar...">
                                            <Search :size="18" class="barcode-search-icon" />
                                        </div>
                                        <div class="barcode-pages-wrap">
                                            <span>Linhas por pagina</span>
                                            <select v-model="composicaoRowsPerPage" class="ui-field barcode-page-select">
                                                <option :value="10">10</option>
                                                <option :value="20">20</option>
                                                <option :value="50">50</option>
                                            </select>
                                            <span>Pagina {{ composicaoPage }} de {{ composicaoTotalPages }}</span>
                                            <div class="barcode-nav-btns">
                                                <button type="button" class="nav-icon-btn" @click="goToComposicaoFirstPage"><ChevronsLeft :size="16" /></button>
                                                <button type="button" class="nav-icon-btn" @click="goToComposicaoPrevPage"><ChevronLeft :size="16" /></button>
                                                <button type="button" class="nav-icon-btn" @click="goToComposicaoNextPage"><ChevronRight :size="16" /></button>
                                                <button type="button" class="nav-icon-btn" @click="goToComposicaoLastPage"><ChevronsRight :size="16" /></button>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="compositionGridPendingParentId" class="composition-grid-link-banner">
                                        <div>
                                            <strong>Arrastando ramo:</strong>
                                            <span>{{ getCompositionGridNodeLabel(compositionGridPendingParentId) }}</span>
                                        </div>
                                        <p>Solte sobre o produto que deve virar filho. Solte fora da tabela para cancelar.</p>
                                        <button type="button" @click="cancelCompositionGridLinkMode">Cancelar ligação</button>
                                    </div>

                                    <div
                                        v-if="compositionGridPointer.active && compositionGridPointer.hasDragged"
                                        class="composition-grid-drag-ghost"
                                        :class="{ 'has-target': compositionGridPointer.targetId }"
                                        :style="{ left: `${compositionGridPointer.x + 14}px`, top: `${compositionGridPointer.y + 14}px` }"
                                    >
                                        {{ compositionGridPointer.targetId ? 'Solte para conectar' : 'Arraste ate um produto' }}
                                    </div>

                                    <AppTable class="composition-table">
                                        <thead>
                                            <tr>
                                                <th class="composition-flow-heading"></th>
                                                <th class="text-left">Ações</th>
                                                <th class="text-left">Produto</th>
                                                <th class="text-left">Quantidade</th>
                                                <th class="text-left">Ordem</th>
                                                <th class="text-left">Observação</th>
                                                <th class="text-left">Campos adicionais</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                class="composition-current-row"
                                                :class="{
                                                    'is-grid-link-parent': compositionGridPendingParentId === 'root',
                                                    'is-grid-link-target': compositionGridPointer.targetId === 'root',
                                                    'has-grid-children': getCompositionGridChildrenCount('root') > 0,
                                                }"
                                                data-composition-grid-node-id="root"
                                                :style="{ '--branch-color': getCompositionNodeBranchColor('root') }"
                                                @pointerdown="startCompositionGridLinkDrag('root', $event)"
                                                @pointermove="moveCompositionGridLinkDrag"
                                                @pointerup="stopCompositionGridLinkDrag"
                                                @pointercancel="cancelCompositionGridLinkMode"
                                            >
                                                <td class="composition-root-cell">
                                                    <span class="composition-root-node"></span>
                                                    <span v-if="getCompositionGridChildrenCount('root')" class="composition-grid-child-count">{{ getCompositionGridChildrenCount('root') }}</span>
                                                </td>
                                                <td colspan="6">
                                                    <div class="composition-current-row__content">
                                                        <span class="composition-current-row__badge">Produto atual</span>
                                                        <span class="composition-current-row__name">{{ compositionCurrentProductLabel }}</span>
                                                        <span v-if="getCompositionGridChildrenCount('root')" class="composition-grid-parent-chip">{{ getCompositionGridChildrenCount('root') }} ramo(s) direto(s)</span>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr v-if="composicaoPagedRows.length === 0">
                                                <td colspan="7" class="text-center text-muted py-14">Sem Resultados...</td>
                                            </tr>
                                            <tr
                                                v-for="(item, idx) in composicaoPagedRows"
                                                :key="`${item.source?.id || item.source?.produto}-${idx}`"
                                                class="composition-connected-row"
                                                :class="{
                                                    'is-first-sequence': item.sequencia === 1,
                                                    'is-last-sequence': item.sequencia === composicaoOrderedRows.length,
                                                    'has-grid-parent': hasCompositionGridParent(item.source),
                                                    'has-grid-children': getCompositionGridChildrenCount(getCompositionGridNodeId(item.source)) > 0,
                                                    'is-grid-link-parent': compositionGridPendingParentId === getCompositionGridNodeId(item.source),
                                                    'is-grid-link-target': compositionGridPointer.targetId === getCompositionGridNodeId(item.source),
                                                }"
                                                :data-composition-grid-node-id="getCompositionGridNodeId(item.source)"
                                                :style="getCompositionGridBranchStyle(item.source, item)"
                                                @pointerdown="startCompositionGridLinkDrag(getCompositionGridNodeId(item.source), $event)"
                                                @pointermove="moveCompositionGridLinkDrag"
                                                @pointerup="stopCompositionGridLinkDrag"
                                                @pointercancel="cancelCompositionGridLinkMode"
                                            >
                                                <td class="composition-flow-cell" :class="getCompositionGridFlowCellClass(item.source, item)">
                                                    <span class="composition-tree-prefix" aria-hidden="true">
                                                        <span
                                                            v-for="(isAncestorLast, segmentIndex) in getCompositionGridTreeSegments(item)"
                                                            :key="`tree-segment-${item.source?.id}-${segmentIndex}`"
                                                            class="composition-tree-segment"
                                                            :class="{ 'is-empty': isAncestorLast }"
                                                        ></span>
                                                        <span class="composition-tree-elbow" :class="{ 'is-last': item.isLastChild }"></span>
                                                    </span>
                                                    <span class="composition-flow-node"></span>
                                                    <span v-if="getCompositionGridChildrenCount(getCompositionGridNodeId(item.source))" class="composition-grid-child-count">{{ getCompositionGridChildrenCount(getCompositionGridNodeId(item.source)) }}</span>
                                                </td>
                                                <td>
                                                    <div class="composition-actions-cell" @pointerdown.stop @click.stop>
                                                        <details class="composition-actions-menu">
                                                            <summary class="composition-actions-trigger" aria-label="Abrir ações">
                                                                <MoreHorizontal :size="16" />
                                                            </summary>
                                                            <div class="composition-actions-dropdown">
                                                                <button type="button" class="composition-actions-option" @click.stop="openCompositionEditFromMenu(item.source, $event)">
                                                                    Editar
                                                                </button>
                                                                <button type="button" class="composition-actions-option is-danger" @click.stop="removeCompositionFromMenu(item.source, $event)">
                                                                    Remover
                                                                </button>
                                                            </div>
                                                        </details>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="composition-product-cell" :style="getCompositionGridProductStyle(item.source, item)">
                                                        <span class="composition-order-chip">{{ item.sequenciaLabel }}</span>
                                                        <div class="composition-product-stack">
                                                            <span>{{ item.source?.produto || '-' }}</span>
                                                            <small v-if="hasCompositionGridParent(item.source)">Ligado a {{ getCompositionGridParentLabel(item.source) }}</small>
                                                            <small v-if="getCompositionGridChildrenCount(getCompositionGridNodeId(item.source))">{{ getCompositionGridChildrenCount(getCompositionGridNodeId(item.source)) }} produto(s) conectado(s)</small>
                                                            <small>
                                                                {{ isCompositionCostEnabled(item.source) ? 'Participa do custo' : 'Ignorado no custo' }}
                                                                • Custo op.: {{ formatCurrency(getCompositionRowOperationalCost(item.source)) }}
                                                            </small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>{{ item.source?.quantidade }}</td>
                                                <td>
                                                    <span class="composition-order-pill">{{ item.sequenciaLabel }}</span>
                                                </td>
                                                <td>{{ item.source?.observacao || '-' }}</td>
                                                <td>{{ Array.isArray(item.source?.campos_adicionais) ? item.source.campos_adicionais.length : 0 }}</td>
                                            </tr>
                                        </tbody>
                                    </AppTable>

                                    <p class="barcode-grid-footer">0 de {{ composicaoFilteredRows.length }} linha(s) selecionada(s).</p>
                                </div>

                                <div v-else class="composition-org-shell" :class="{ 'has-open-drawer': compositionOrgSelectedNode }">
                                    <div class="composition-org-blur-layer">
                                        <div class="composition-org-header">
                                            <div>
                                                <h3>Organograma da composição</h3>
                                                <p>Nível raiz representa o produto atual. Em seguida serão aplicadas as regras de composição.</p>
                                            </div>
                                            <AppButton @click="openCompositionModal">
                                                <Plus :size="16" />
                                                Adicionar componente
                                            </AppButton>
                                        </div>

                                        <div class="composition-org-toolbar">
                                            <button type="button" class="composition-org-tool" @click="arrangeCompositionOrg">Expandir tudo</button>
                                            <button type="button" class="composition-org-tool" @click="centerCompositionOrg">Centralizar</button>
                                            <button type="button" class="composition-org-tool" @click="zoomCompositionOrg(-0.1)">Zoom -</button>
                                            <button type="button" class="composition-org-tool" @click="zoomCompositionOrg(0.1)">Zoom +</button>
                                            <span class="composition-org-zoom">{{ Math.round(compositionOrgZoom * 100) }}%</span>
                                        </div>

                                        <div class="composition-org-path">
                                            <strong>Caminho:</strong>
                                            <span>{{ compositionCurrentProductLabel }}</span>
                                            <span v-if="compositionOrgPendingParentId" class="composition-org-pending">
                                                Escolha o componente filho para concluir a ligação.
                                            </span>
                                        </div>

                                        <div
                                            class="composition-org-canvas"
                                            @pointerdown="startCompositionOrgPan"
                                            @pointermove="moveCompositionOrgPointer"
                                            @pointerup="stopCompositionOrgPointer($event)"
                                            @pointerleave="stopCompositionOrgPointer"
                                            @contextmenu.prevent
                                            @wheel="onCompositionOrgWheel"
                                        >
                                            <div
                                                class="composition-org-stage"
                                                :style="{ transform: `translate(${compositionOrgPan.x}px, ${compositionOrgPan.y}px) scale(${compositionOrgZoom})` }"
                                            >
                                                <svg class="composition-org-edges" viewBox="0 0 1400 900" aria-hidden="true">
                                                    <path
                                                        v-for="edge in compositionOrgEdges"
                                                        :key="`halo-${edge.id}`"
                                                        :d="getCompositionOrgEdgePath(edge)"
                                                        class="composition-org-edge-halo"
                                                    />
                                                    <path
                                                        v-for="edge in compositionOrgEdges"
                                                        :key="edge.id"
                                                        :d="getCompositionOrgEdgePath(edge)"
                                                    class="composition-org-edge"
                                                    :style="{ '--edge-color': edge.color }"
                                                />
                                                <path
                                                    v-if="compositionOrgDraftEdge"
                                                    :d="getCompositionOrgDraftEdgePath(compositionOrgDraftEdge)"
                                                    class="composition-org-edge-halo is-draft"
                                                />
                                                <path
                                                    v-if="compositionOrgDraftEdge"
                                                    :d="getCompositionOrgDraftEdgePath(compositionOrgDraftEdge)"
                                                    class="composition-org-edge is-draft"
                                                    :style="{ '--edge-color': compositionOrgDraftEdge.color }"
                                                />
                                            </svg>

                                                <div
                                                    v-for="node in compositionOrgNodes"
                                                    :key="`org-node-${node.id}`"
                                                    class="composition-org-node"
                                                    :class="{
                                                        'is-root': node.type === 'root',
                                                        'is-selected': compositionOrgSelectedNodeId === node.id,
                                                        'is-multi-selected': compositionOrgSelectedNodeIds.includes(node.id),
                                                        'is-pending-parent': compositionOrgPendingParentId === node.id,
                                                        'is-connect-target': compositionOrgPointer.mode === 'connect' && compositionOrgPointer.targetNodeId === node.id,
                                                    }"
                                                    :style="{ left: `${node.position.x}px`, top: `${node.position.y}px`, '--node-color': node.color }"
                                                    @pointerdown.stop="startCompositionOrgNodeDrag(node, $event)"
                                                    @pointerenter="compositionOrgPointer.mode === 'connect' && node.id !== compositionOrgPointer.nodeId ? compositionOrgPointer.targetNodeId = node.id : null"
                                                    @pointerleave="compositionOrgPointer.mode === 'connect' && compositionOrgPointer.targetNodeId === node.id ? compositionOrgPointer.targetNodeId = '' : null"
                                                    @mouseenter="compositionOrgHoveredNodeId = node.id"
                                                    @mouseleave="compositionOrgHoveredNodeId = ''"
                                                    @click.stop="selectCompositionOrgNode(node)"
                                                >
                                                    <button
                                                    type="button"
                                                    class="composition-org-connect-btn"
                                                    title="Criar ligação manual"
                                                    @pointerdown.stop="startCompositionOrgConnection(node, $event)"
                                                    @click.stop="handleCompositionOrgConnector(node)"
                                                >
                                                        +
                                                    </button>
                                                    <h4>{{ node.label }}</h4>
                                                    <div class="composition-org-tags">
                                                        <span>{{ node.subtitle }}</span>
                                                        <span>Nível {{ node.level }}</span>
                                                        <span v-if="node.childrenCount">{{ node.childrenCount }} filho(s)</span>
                                                    </div>
                                                    <p><strong>SKU:</strong> {{ node.sku }}</p>
                                                    <p><strong>EAN:</strong> {{ node.ean }}</p>
                                                    <p v-if="node.type === 'component'"><strong>Quantidade:</strong> {{ node.quantity }}</p>
                                                </div>

                                                <div
                                                    v-if="compositionOrgHoveredNode"
                                                    class="composition-org-hover"
                                                    :style="{ left: `${compositionOrgHoveredNode.position.x - 12}px`, top: `${compositionOrgHoveredNode.position.y - 156}px` }"
                                                >
                                                    <h4>{{ compositionOrgHoveredNode.label }}</h4>
                                                    <div class="composition-org-hover-grid">
                                                        <span><strong>Tipo:</strong> {{ compositionOrgHoveredNode.subtitle }}</span>
                                                        <span><strong>Nível:</strong> {{ compositionOrgHoveredNode.level }}</span>
                                                        <span><strong>SKU:</strong> {{ compositionOrgHoveredNode.sku }}</span>
                                                        <span><strong>EAN:</strong> {{ compositionOrgHoveredNode.ean }}</span>
                                                        <span><strong>Filhos:</strong> {{ compositionOrgHoveredNode.childrenCount }}</span>
                                                    </div>
                                                    <p>Use o botão + no topo para criar ligação manual com outro item.</p>
                                                </div>

                                                <div
                                                    v-if="compositionOrgSelectionBox"
                                                    class="composition-org-selection-box"
                                                    :style="{
                                                        left: `${compositionOrgSelectionBox.left}px`,
                                                        top: `${compositionOrgSelectionBox.top}px`,
                                                        width: `${compositionOrgSelectionBox.width}px`,
                                                        height: `${compositionOrgSelectionBox.height}px`,
                                                    }"
                                                ></div>
                                            </div>
                                        </div>
                                    </div>

                                    <button
                                        v-if="compositionOrgSelectedNode"
                                        type="button"
                                        class="composition-drawer-backdrop"
                                        aria-label="Fechar detalhes do nó"
                                        @click="closeCompositionOrgDrawer"
                                    ></button>

                                    <div
                                        v-if="compositionOrgSelectedNode"
                                        class="composition-node-spotlight"
                                        :style="{ '--node-color': compositionOrgSelectedNode.color }"
                                    >
                                        <span class="composition-node-spotlight__eyebrow">Nó selecionado</span>
                                        <h4>{{ compositionOrgSelectedNode.label }}</h4>
                                        <div class="composition-org-tags">
                                            <span>{{ compositionOrgSelectedNode.subtitle }}</span>
                                            <span>Nível {{ compositionOrgSelectedNode.level }}</span>
                                            <span v-if="compositionOrgSelectedNode.childrenCount">{{ compositionOrgSelectedNode.childrenCount }} filho(s)</span>
                                        </div>
                                        <p><strong>SKU:</strong> {{ compositionOrgSelectedNode.sku }}</p>
                                        <p><strong>EAN:</strong> {{ compositionOrgSelectedNode.ean }}</p>
                                        <p v-if="compositionOrgSelectedNode.type === 'component'"><strong>Quantidade:</strong> {{ compositionOrgSelectedNode.quantity }}</p>
                                    </div>

                                    <aside v-if="compositionOrgSelectedNode" class="composition-node-drawer">
                                        <button type="button" class="composition-node-drawer__close" @click="closeCompositionOrgDrawer">x</button>
                                        <h3>Detalhes do Nó</h3>
                                        <p>Análise rápida do item selecionado no organograma da composição.</p>

                                        <div class="composition-node-detail-card">
                                            <div class="composition-node-detail-title">
                                                <h4>{{ compositionOrgSelectedNode.label }}</h4>
                                                <span>Nível {{ compositionOrgSelectedNode.level }}</span>
                                            </div>
                                            <p><strong>ID:</strong> {{ compositionOrgSelectedNode.id }}</p>
                                            <p><strong>SKU:</strong> {{ compositionOrgSelectedNode.sku }}</p>
                                            <p><strong>EAN:</strong> {{ compositionOrgSelectedNode.ean }}</p>
                                            <p v-if="compositionOrgSelectedNode.type === 'component'"><strong>Quantidade:</strong> {{ compositionOrgSelectedNode.quantity }}</p>
                                            <p><strong>Filhos:</strong> {{ compositionOrgSelectedNode.childrenCount }}</p>
                                            <p v-if="compositionOrgSelectedNode.observation"><strong>Observação:</strong> {{ compositionOrgSelectedNode.observation }}</p>
                                            <p v-if="compositionOrgSelectedNode.type === 'component'"><strong>Campos adicionais:</strong> {{ compositionOrgSelectedNode.additionalFieldsCount }}</p>
                                            <p v-if="compositionOrgSelectedNode.type === 'component'"><strong>Participa do custo:</strong> {{ compositionOrgSelectedNode.source?.calculate_cost === false ? 'Não' : 'Sim' }}</p>
                                        </div>

                                        <div class="composition-node-detail-card">
                                            <h4>Caminho hierárquico</h4>
                                            <p>{{ compositionCurrentProductLabel }}</p>
                                        </div>

                                        <AppButton variant="secondary" @click="centerCompositionOrgNode(compositionOrgSelectedNode)">Centralizar nó</AppButton>
                                    </aside>
                                </div>

                                <section class="composition-cost-summary">
                                    <div class="composition-cost-summary__header">
                                        <div>
                                            <h3>Resumo de Custos</h3>
                                            <p>Base inicial para precificação da composição sem alterar o fluxo atual da tela.</p>
                                        </div>
                                    </div>

                                    <div class="composition-cost-summary__grid">
                                        <article class="composition-cost-card">
                                            <span>Custo dos componentes</span>
                                            <strong>{{ formatCurrency(compositionCostSummary.component_cost) }}</strong>
                                        </article>
                                        <article class="composition-cost-card">
                                            <span>Custo operacional total</span>
                                            <strong>{{ formatCurrency(compositionCostSummary.operational_cost_total) }}</strong>
                                        </article>
                                        <article class="composition-cost-card">
                                            <span>Custo total acumulado</span>
                                            <strong>{{ formatCurrency(compositionCostSummary.accumulated_cost) }}</strong>
                                        </article>
                                        <article class="composition-cost-card">
                                            <span>Itens na composição</span>
                                            <strong>{{ compositionCostSummary.total_items }}</strong>
                                        </article>
                                        <article class="composition-cost-card">
                                            <span>Itens que participam do custo</span>
                                            <strong>{{ compositionCostSummary.cost_participants }}</strong>
                                        </article>
                                        <article class="composition-cost-card">
                                            <span>Itens ignorados no custo</span>
                                            <strong>{{ compositionCostSummary.cost_ignored }}</strong>
                                        </article>
                                    </div>

                                    <div class="composition-pricing-box">
                                        <div class="composition-pricing-box__inputs">
                                            <AppInput
                                                v-model="compositionPricingDraft.taxes_percent"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                label="Taxas percentuais (decimal)"
                                                placeholder="0.10"
                                            />
                                            <AppInput
                                                v-model="compositionPricingDraft.desired_margin"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                label="Margem desejada (decimal)"
                                                placeholder="0.25"
                                            />
                                        </div>
                                        <div class="composition-pricing-box__metrics">
                                            <span>Preço atual: <strong>{{ formatCurrency(getCompositionSalePrice()) }}</strong></span>
                                            <span>Preço mínimo sugerido: <strong>{{ compositionPricingSummary.minimum_price === null ? '—' : formatCurrency(compositionPricingSummary.minimum_price) }}</strong></span>
                                            <span>Preço sugerido: <strong>{{ compositionPricingSummary.suggested_price === null ? '—' : formatCurrency(compositionPricingSummary.suggested_price) }}</strong></span>
                                            <span>Lucro unitário estimado: <strong>{{ compositionPricingSummary.unit_profit === null ? '—' : formatCurrency(compositionPricingSummary.unit_profit) }}</strong></span>
                                            <span>Margem real estimada: <strong>{{ compositionPricingSummary.real_margin === null ? '—' : formatPercent(compositionPricingSummary.real_margin * 100) }}</strong></span>
                                        </div>
                                    </div>
                                </section>
                            </div>
                        </div>

                        <div v-if="infoAdicionalSubTab === 'foto'" class="space-y-4">
                            <div class="product-basic-title-wrap">
                                <h2 class="product-basic-title">Foto</h2>
                                <p class="product-basic-subtitle">Adicione as fotos do produto.</p>
                            </div>

                            <div class="product-card">
                                <h2>Coloque o Arquivo no quadro abaixo!</h2>
                                <p>JPG, PNG, WEBP ou GIF</p>

                                <button type="button" class="foto-add-btn" @click="addPhotoSlot">
                                    <Plus :size="18" />
                                </button>

                                <div class="foto-grid">
                                    <div v-for="(photo, idx) in form.informacao_adicional.fotos" :key="`produto-foto-slot-${idx}`" class="foto-slot-card">
                                        <input
                                            :id="`produto-foto-input-${idx}`"
                                            type="file"
                                            accept="image/png,image/jpeg,image/webp,image/gif"
                                            class="hidden"
                                            @change="onPhotoFileSelected(idx, $event)"
                                        >
                                        <input
                                            :id="`produto-foto-camera-input-${idx}`"
                                            type="file"
                                            accept="image/*"
                                            capture="environment"
                                            class="hidden"
                                            @change="onPhotoFileSelected(idx, $event)"
                                        >
                                        <button type="button" class="foto-drop-zone" @click="openPhotoPickerModal(idx)">
                                            <img v-if="photo.url" :src="photo.url" alt="Preview da foto do produto" class="foto-preview">
                                            <span v-else class="foto-drop-placeholder">
                                                <ImagePlus :size="22" />
                                                Adicionar foto
                                            </span>
                                        </button>
                                        <p class="foto-file-name">{{ photo.nome || 'Nenhum arquivo selecionado' }}</p>
                                        <div class="foto-actions">
                                            <button type="button" class="icon-action-btn" @click="openPhotoPickerModal(idx)">
                                                <Camera :size="16" />
                                            </button>
                                            <button type="button" class="icon-action-btn" @click="removePhotoFile(idx)">
                                                <Trash2 :size="16" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <AppModal
                            :open="photoPickerModalOpen"
                            title="Adicionar foto do produto"
                            width-class="max-w-xl"
                            @close="closePhotoPickerModal"
                        >
                            <div class="photo-modal-content">
                                <div class="photo-modal-actions-grid">
                                    <button type="button" class="photo-modal-option-btn" @click="choosePhotoUpload">
                                        <ImagePlus :size="18" />
                                        <span>Fazer upload</span>
                                    </button>
                                    <button type="button" class="photo-modal-option-btn" @click="choosePhotoCamera">
                                        <Camera :size="18" />
                                        <span>Usar câmera (mobile)</span>
                                    </button>
                                </div>

                                <div class="photo-modal-link-block">
                                    <AppInput
                                        v-model="photoLinkDraft"
                                        label="Ou cole o link da imagem"
                                        placeholder="https://exemplo.com/foto-do-produto.jpg"
                                    />
                                </div>
                            </div>

                            <div class="barcode-modal-actions">
                                <AppButton variant="secondary" @click="closePhotoPickerModal">Cancelar</AppButton>
                                <AppButton :disabled="!String(photoLinkDraft || '').trim()" @click="savePhotoLink">
                                    Salvar link
                                </AppButton>
                            </div>
                        </AppModal>

                        <AppModal
                            :open="compositionModalOpen"
                            :title="isEditingCompositionModal ? 'Editar Composição de Produto' : 'Nova Composição de Produto'"
                            width-class="max-w-6xl"
                            @close="closeCompositionModal"
                        >
                            <div class="space-y-5">
                                <h3 class="text-base font-semibold text-foreground">Produtos da composicao</h3>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 composition-modal-product-grid">
                                    <div class="ui-field-wrap">
                                        <span class="ui-label">Produto</span>
                                        <AppCombobox
                                            v-model="compositionModalForm.produto"
                                            :options="compositionProductOptionLabels"
                                            placeholder="Pesquise e selecione um sub-produto"
                                            no-results-text="Nenhum produto encontrado"
                                            @update:model-value="onCompositionProductInput"
                                            @select="onCompositionProductSelect"
                                        />
                                        <span class="field-caption">
                                            {{ compositionProductLoading ? 'Buscando produtos...' : `${compositionProductOptions.length} opcao(oes) encontradas` }}
                                        </span>
                                    </div>
                                    <label class="ui-field-wrap">
                                        <span class="ui-label">Quantidade</span>
                                        <input
                                            v-model="compositionModalForm.quantidade"
                                            type="number"
                                            step="0.0001"
                                            min="0"
                                            class="ui-field"
                                        >
                                    </label>
                                    <label class="ui-field-wrap">
                                        <span class="ui-label">Ordem</span>
                                        <input
                                            v-model="compositionModalForm.ordem"
                                            class="ui-field"
                                            placeholder="Ex: 1"
                                        >
                                    </label>
                                </div>

                                <AppTextarea
                                    v-model="compositionModalForm.observacao"
                                    rows="2"
                                    label="Observacao"
                                    placeholder="Ex: observacao do item"
                                />

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <AppInput
                                        v-model="compositionModalForm.operational_cost"
                                        type="number"
                                        step="0.0001"
                                        min="0"
                                        label="Custo operacional"
                                        placeholder="0,00"
                                    />
                                    <div class="flex items-end pb-1">
                                        <AppCheckbox
                                            v-model="compositionModalForm.calculate_cost"
                                            label="Incluir este item no cálculo de custo"
                                        />
                                    </div>
                                </div>

                                <div class="border border-white/10 rounded-xl p-4 space-y-4">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <h4 class="text-sm font-semibold text-foreground">Campos adicionais</h4>
                                            <p class="text-sm text-muted">Selecione apenas os campos que fazem sentido para o segmento.</p>
                                        </div>
                                        <AppButton type="button" variant="secondary" @click="addCompositionAdditionalField">
                                            <Plus :size="16" />
                                            Adicionar campo
                                        </AppButton>
                                    </div>

                                    <div v-if="compositionModalForm.campos_adicionais.length === 0" class="border border-dashed border-white/15 rounded-lg px-4 py-5 text-sm text-muted">
                                        Nenhum campo adicional selecionado neste item.
                                    </div>

                                    <div v-else class="space-y-4">
                                        <div
                                            v-for="(field, fieldIndex) in compositionModalForm.campos_adicionais"
                                            :key="field.id || `composition-field-${fieldIndex}`"
                                            class="border border-white/10 rounded-xl p-3 space-y-3"
                                        >
                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                                <AppSelect
                                                    v-model="field.nome_template"
                                                    label="Nome do campo"
                                                    @update:model-value="onCompositionFieldTemplateChange(field)"
                                                >
                                                    <option v-for="template in compositionFieldNameTemplates" :key="template.id" :value="template.id">
                                                        {{ template.label }}
                                                    </option>
                                                </AppSelect>

                                                <AppSelect
                                                    v-model="field.tipo_campo"
                                                    label="Tipo do campo"
                                                    @update:model-value="onCompositionFieldTypeChange(field)"
                                                >
                                                    <option v-for="typeOption in compositionFieldTypeOptions" :key="typeOption.id" :value="typeOption.id">
                                                        {{ typeOption.label }}
                                                    </option>
                                                </AppSelect>

                                                <div class="flex items-end">
                                                    <AppButton type="button" variant="danger" @click="removeCompositionAdditionalField(fieldIndex)">
                                                        <Trash2 :size="16" />
                                                        Remover
                                                    </AppButton>
                                                </div>
                                            </div>

                                            <AppInput
                                                v-if="field.nome_template === 'personalizado'"
                                                v-model="field.nome_personalizado"
                                                label="Nome personalizado"
                                                placeholder="Ex: Tempo de cura"
                                            />

                                            <AppInput
                                                v-if="field.tipo_campo === 'texto_curto'"
                                                v-model="field.valor"
                                                label="Valor"
                                                placeholder="Digite o valor"
                                            />
                                            <AppTextarea
                                                v-else-if="field.tipo_campo === 'texto_longo'"
                                                v-model="field.valor"
                                                rows="2"
                                                label="Valor"
                                                placeholder="Digite o valor"
                                            />
                                            <AppInput
                                                v-else-if="field.tipo_campo === 'numero_inteiro'"
                                                v-model="field.valor"
                                                type="number"
                                                step="1"
                                                label="Valor"
                                                placeholder="0"
                                            />
                                            <AppInput
                                                v-else-if="field.tipo_campo === 'numero_decimal'"
                                                v-model="field.valor"
                                                type="number"
                                                step="0.01"
                                                label="Valor"
                                                placeholder="0,00"
                                            />
                                            <AppInput
                                                v-else-if="field.tipo_campo === 'data'"
                                                v-model="field.valor"
                                                type="date"
                                                label="Valor"
                                            />
                                            <AppSelect
                                                v-else-if="field.tipo_campo === 'sim_nao'"
                                                v-model="field.valor"
                                                label="Valor"
                                            >
                                                <option value="sim">Sim</option>
                                                <option value="nao">Nao</option>
                                            </AppSelect>
                                            <div v-else-if="field.tipo_campo === 'checkbox_texto'" class="space-y-3">
                                                <AppInput
                                                    v-model="field.texto_checkbox"
                                                    label="Texto ao lado do checkbox"
                                                    placeholder="Ex: Exige assinatura do tecnico"
                                                />
                                                <AppCheckbox
                                                    v-model="field.valor_booleano"
                                                    :label="String(field.texto_checkbox || '').trim() || 'Texto do checkbox'"
                                                />
                                            </div>
                                            <AppInput
                                                v-else
                                                v-model="field.valor"
                                                label="Valor"
                                                placeholder="Digite o valor"
                                            />

                                            <AppInput
                                                v-model="field.operational_cost"
                                                type="number"
                                                step="0.0001"
                                                min="0"
                                                label="Custo operacional do campo"
                                                placeholder="0,00"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="barcode-modal-actions">
                                <AppButton variant="secondary" @click="closeCompositionModal">Cancelar</AppButton>
                                <AppButton @click="saveCompositionModal">{{ isEditingCompositionModal ? 'Atualizar' : 'Salvar' }}</AppButton>
                            </div>
                        </AppModal>
                    </section>

                    <section v-if="activeTab === 'estoque'" class="space-y-4">
                        <div class="estoque-subtabs">
                            <button
                                v-for="sub in estoqueSubTabs"
                                :key="sub.id"
                                type="button"
                                class="estoque-subtab-btn"
                                :class="{ 'is-active': estoqueSubTab === sub.id }"
                                @click="estoqueSubTab = sub.id"
                            >
                                <FileText v-if="sub.id === 'dados_basicos'" :size="15" />
                                <BriefcaseBusiness v-else-if="sub.id === 'codigo_fornecedor'" :size="15" />
                                <Layers3 v-else-if="sub.id === 'saldo_lotes'" :size="15" />
                                <Ruler v-else-if="sub.id === 'dimensoes'" :size="15" />
                                <PackageOpen v-else :size="15" />
                                {{ sub.label }}
                            </button>
                        </div>

                        <div v-if="estoqueSubTab === 'dados_basicos'" class="space-y-4">
                            <div class="product-basic-title-wrap">
                                <h2 class="product-basic-title">Dados Basicos</h2>
                                <p class="product-basic-subtitle">Dados operacionais de estoque e planejamento de reposicao.</p>
                            </div>

                            <div class="product-card">
                                <div class="card-section-header">
                                    <div class="card-section-icon"><Boxes :size="18" /></div>
                                    <div>
                                        <h2>Dados operacionais de estoque</h2>
                                        <p>Indicadores consolidados de custo, saldo e histórico para acompanhamento operacional.</p>
                                    </div>
                                </div>
                                <div class="stock-kpi-grid">
                                    <div class="stock-kpi-card">
                                        <div class="stock-kpi-head">
                                            <span class="stock-kpi-label"><DollarSign :size="14" />Custo atual medio</span>
                                            <span class="calc-chip">CALCULADO</span>
                                        </div>
                                        <div class="stock-kpi-value">
                                            <span class="stock-kpi-prefix">R$</span>
                                            <input :value="formatDecimal(custoAtualMedioCalc, 2)" class="ui-field stock-kpi-input" readonly>
                                        </div>
                                    </div>

                                    <div class="stock-kpi-card">
                                        <div class="stock-kpi-head">
                                            <span class="stock-kpi-label"><Boxes :size="14" />Estoque atual total</span>
                                            <span class="calc-chip">CALCULADO</span>
                                        </div>
                                        <div class="stock-kpi-value">
                                            <input :value="formatDecimal(estoqueAtualTotalCalc, 3)" class="ui-field stock-kpi-input" readonly>
                                            <span class="stock-kpi-suffix">un</span>
                                        </div>
                                    </div>

                                    <div class="stock-kpi-card">
                                        <div class="stock-kpi-head">
                                            <span class="stock-kpi-label"><Layers3 :size="14" />Saldo total em lotes</span>
                                            <span class="calc-chip">CALCULADO</span>
                                        </div>
                                        <div class="stock-kpi-value">
                                            <input :value="formatDecimal(saldoTotalLotesCalc, 3)" class="ui-field stock-kpi-input" readonly>
                                            <span class="stock-kpi-suffix">un</span>
                                        </div>
                                    </div>

                                    <div class="stock-kpi-card">
                                        <div class="stock-kpi-head">
                                            <span class="stock-kpi-label"><Ruler :size="14" />Margem real</span>
                                            <span class="calc-chip">CALCULADO</span>
                                        </div>
                                        <div class="stock-kpi-value">
                                            <input :value="formatDecimal(margemRealOperacionalCalc, 2)" class="ui-field stock-kpi-input" readonly>
                                            <span class="stock-kpi-suffix">%</span>
                                        </div>
                                    </div>

                                    <div class="stock-kpi-card">
                                        <div class="stock-kpi-head">
                                            <span class="stock-kpi-label"><DollarSign :size="14" />Ultimo custo</span>
                                            <span class="calc-chip">CALCULADO</span>
                                        </div>
                                        <div class="stock-kpi-value">
                                            <span class="stock-kpi-prefix">R$</span>
                                            <input :value="formatDecimal(ultimoCustoCalc, 2)" class="ui-field stock-kpi-input" readonly>
                                        </div>
                                    </div>

                                    <div class="stock-kpi-card">
                                        <div class="stock-kpi-head">
                                            <span class="stock-kpi-label"><BookText :size="14" />Custo medio historico</span>
                                            <span class="calc-chip">CALCULADO</span>
                                        </div>
                                        <div class="stock-kpi-value">
                                            <span class="stock-kpi-prefix">R$</span>
                                            <input :value="formatDecimal(custoMedioHistoricoCalc, 2)" class="ui-field stock-kpi-input" readonly>
                                        </div>
                                    </div>
                                </div>

                                <div class="stock-reference-grid">
                                    <div class="stock-reference-card">
                                        <h4>Ultima referencia de custo</h4>
                                        <p>{{ referenciaCustoDataLabel }}</p>
                                    </div>
                                    <div class="stock-reference-card">
                                        <h4>Fornecedor da ultima referencia</h4>
                                        <p>{{ fornecedorUltimaReferenciaLabel }}</p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                                    <label class="stock-mini-field">
                                        <span class="stock-mini-label"><Boxes :size="14" />Estoque atual total</span>
                                        <div class="stock-mini-control">
                                            <input v-model="form.estoque.quantidade" type="number" step="0.000001" class="ui-field stock-mini-input">
                                            <span class="stock-mini-suffix">un</span>
                                        </div>
                                    </label>
                                    <label class="stock-mini-field">
                                        <span class="stock-mini-label"><Filter :size="14" />Quantidade minima</span>
                                        <div class="stock-mini-control">
                                            <input v-model="form.estoque.quantidade_minima" type="number" step="0.000001" class="ui-field stock-mini-input">
                                        </div>
                                    </label>
                                    <label class="stock-mini-field">
                                        <span class="stock-mini-label"><Filter :size="14" />Quantidade maxima</span>
                                        <div class="stock-mini-control">
                                            <input v-model="form.estoque.quantidade_maxima" type="number" step="0.000001" class="ui-field stock-mini-input">
                                        </div>
                                    </label>
                                    <label class="stock-mini-field">
                                        <span class="stock-mini-label"><ShieldCheck :size="14" />Quantidade minima vendavel</span>
                                        <div class="stock-mini-control">
                                            <input v-model="form.estoque.quantidade_minima_vendavel" type="number" step="0.000001" class="ui-field stock-mini-input">
                                        </div>
                                    </label>
                                    <label class="stock-mini-field">
                                        <span class="stock-mini-label"><AlertTriangle :size="14" />Quantidade de alerta</span>
                                        <div class="stock-mini-control">
                                            <input v-model="form.estoque.quantidade_alerta" type="number" step="0.000001" class="ui-field stock-mini-input">
                                        </div>
                                    </label>
                                    <label class="stock-mini-field">
                                        <span class="stock-mini-label"><Layers3 :size="14" />Lote padrao</span>
                                        <div class="stock-mini-control">
                                            <input v-model="form.estoque.numero_lote" class="ui-field stock-mini-input">
                                        </div>
                                    </label>
                                    <label class="stock-mini-field">
                                        <span class="stock-mini-label"><BriefcaseBusiness :size="14" />Fornecedor da referencia</span>
                                        <div class="stock-mini-control">
                                            <input v-model="form.estoque_detalhado.fornecedor_ultima_referencia" class="ui-field stock-mini-input" placeholder="Ex: Distribuidora XPTO">
                                        </div>
                                    </label>
                                    <label class="stock-mini-field">
                                        <span class="stock-mini-label"><Clock3 :size="14" />Data da referencia de custo</span>
                                        <div class="stock-mini-control">
                                            <input v-model="form.estoque_detalhado.referencia_custo_data" type="date" class="ui-field stock-mini-input">
                                        </div>
                                    </label>
                                    <label class="stock-mini-field">
                                        <span class="stock-mini-label"><DollarSign :size="14" />Custo ultima compra</span>
                                        <div class="stock-mini-control">
                                            <input v-model="form.estoque_detalhado.custo_ultima_compra" type="number" step="0.0001" class="ui-field stock-mini-input">
                                        </div>
                                    </label>
                                    <div class="md:col-span-3">
                                        <AppCheckbox v-model="form.estoque.reduzir_estoque" label="Reduzir estoque nas saídas" />
                                    </div>
                                </div>
                            </div>

                            <div class="product-card">
                                <h2>Planejamento de Reposicao</h2>
                                <p>Formula: ponto_pedido = consumo_medio_diario x (lead_compra + lead_entrega + lead_recebimento) + estoque_seguranca</p>
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <label class="stock-mini-field">
                                        <span class="stock-mini-label"><ShoppingCart :size="14" />Consumo medio diario</span>
                                        <div class="stock-mini-control">
                                            <span class="stock-mini-leading-icon"><ShoppingCart :size="14" /></span>
                                            <input v-model="form.estoque_detalhado.consumo_medio_diario" type="number" step="0.0001" class="ui-field stock-mini-input stock-mini-input--with-icon">
                                            <span class="stock-mini-suffix">un/dia</span>
                                        </div>
                                    </label>
                                    <label class="stock-mini-field">
                                        <span class="stock-mini-label"><Clock3 :size="14" />Lead time compra</span>
                                        <div class="stock-mini-control">
                                            <span class="stock-mini-leading-icon"><Clock3 :size="14" /></span>
                                            <input v-model="form.estoque_detalhado.lead_time_compra" type="number" step="0.01" class="ui-field stock-mini-input stock-mini-input--with-icon">
                                            <span class="stock-mini-suffix">dias</span>
                                        </div>
                                    </label>
                                    <label class="stock-mini-field">
                                        <span class="stock-mini-label"><Clock3 :size="14" />Lead time entrega</span>
                                        <div class="stock-mini-control">
                                            <span class="stock-mini-leading-icon"><Clock3 :size="14" /></span>
                                            <input v-model="form.estoque_detalhado.lead_time_entrega" type="number" step="0.01" class="ui-field stock-mini-input stock-mini-input--with-icon">
                                            <span class="stock-mini-suffix">dias</span>
                                        </div>
                                    </label>
                                    <label class="stock-mini-field">
                                        <span class="stock-mini-label"><Clock3 :size="14" />Lead time recebimento</span>
                                        <div class="stock-mini-control">
                                            <span class="stock-mini-leading-icon"><Clock3 :size="14" /></span>
                                            <input v-model="form.estoque_detalhado.lead_time_recebimento" type="number" step="0.01" class="ui-field stock-mini-input stock-mini-input--with-icon">
                                            <span class="stock-mini-suffix">dias</span>
                                        </div>
                                    </label>
                                    <label class="stock-mini-field">
                                        <span class="stock-mini-label"><ShieldCheck :size="14" />Estoque seguranca</span>
                                        <div class="stock-mini-control">
                                            <span class="stock-mini-leading-icon"><ShieldCheck :size="14" /></span>
                                            <input v-model="form.estoque_detalhado.estoque_seguranca" type="number" step="0.0001" class="ui-field stock-mini-input stock-mini-input--with-icon">
                                            <span class="stock-mini-suffix">un</span>
                                        </div>
                                    </label>
                                    <label class="stock-mini-field">
                                        <span class="stock-mini-label"><PackageOpen :size="14" />Lote minimo compra</span>
                                        <div class="stock-mini-control">
                                            <span class="stock-mini-leading-icon"><PackageOpen :size="14" /></span>
                                            <input v-model="form.estoque_detalhado.lote_minimo_compra" type="number" step="0.0001" class="ui-field stock-mini-input stock-mini-input--with-icon">
                                            <span class="stock-mini-suffix">un</span>
                                        </div>
                                    </label>
                                    <label class="stock-mini-field">
                                        <span class="stock-mini-label"><Clock3 :size="14" />Frequencia compra (dias)</span>
                                        <div class="stock-mini-control">
                                            <span class="stock-mini-leading-icon"><Clock3 :size="14" /></span>
                                            <input v-model="form.estoque_detalhado.frequencia_compra" type="number" step="1" class="ui-field stock-mini-input stock-mini-input--with-icon">
                                            <span class="stock-mini-suffix">dias</span>
                                        </div>
                                    </label>
                                    <label class="stock-mini-field">
                                        <span class="stock-mini-label"><Network :size="14" />Ponto de pedido <span class="calc-chip">CALCULADO</span></span>
                                        <div class="stock-mini-control">
                                            <span class="stock-mini-leading-icon"><Network :size="14" /></span>
                                            <input v-model="form.estoque_detalhado.ponto_pedido" type="number" step="0.0001" class="ui-field stock-mini-input stock-mini-input--with-icon">
                                            <span class="stock-mini-suffix">un</span>
                                        </div>
                                    </label>
                                </div>
                                <div class="mt-3">
                                    <AppCheckbox v-model="form.estoque_detalhado.ponto_pedido_override" label="Permitir override manual do ponto de pedido" />
                                </div>
                            </div>

                            <div class="product-card">
                                <div class="card-section-header">
                                    <div class="card-section-icon"><ShieldCheck :size="18" /></div>
                                    <div>
                                        <h2>Regras logisticas</h2>
                                        <p>Configure regras operacionais e atributos de movimentação/armazenagem.</p>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                                    <AppSelect v-model="form.estoque_detalhado.nao_fracionado" label="Nao fracionado">
                                        <option value="nao">Não</option>
                                        <option value="sim">Sim</option>
                                    </AppSelect>
                                    <AppSelect v-model="form.estoque_detalhado.controla_validade_lote" label="Controla validade por lote">
                                        <option value="sim">Sim</option>
                                        <option value="nao">Não</option>
                                    </AppSelect>
                                    <AppInput v-model="form.estoque_detalhado.vida_util_padrao" label="Vida util padrao" />
                                    <AppSelect v-model="form.estoque_detalhado.controla_enderecamento" label="Controla endereçamento">
                                        <option value="nao">Não</option>
                                        <option value="sim">Sim</option>
                                    </AppSelect>
                                    <AppSelect v-model="form.estoque_detalhado.transgenico" label="Transgenico">
                                        <option value="nao">Não</option>
                                        <option value="sim">Sim</option>
                                    </AppSelect>
                                </div>

                                <div class="estoque-atributos-box">
                                    <div class="estoque-atributos-header">
                                        <div>
                                            <h3>Atributos logisticos</h3>
                                            <p>Clique para ativar ou desativar rapidamente cada comportamento logistico.</p>
                                        </div>
                                        <span class="attr-counter">{{ estoqueAtributosAtivosCount }} de 11 ativos</span>
                                    </div>
                                    <div class="attr-grid">
                                        <button type="button" class="attr-tile" :class="{ 'is-active': form.estoque_detalhado.atributos_logisticos_flags.controla_lote }" @click="toggleEstoqueAtributo('controla_lote')"><Boxes :size="16" />Controla lote</button>
                                        <button type="button" class="attr-tile" :class="{ 'is-active': form.estoque_detalhado.atributos_logisticos_flags.refrigerado }" @click="toggleEstoqueAtributo('refrigerado')"><Snowflake :size="16" />Refrigerado</button>
                                        <button type="button" class="attr-tile" :class="{ 'is-active': form.estoque_detalhado.atributos_logisticos_flags.controla_enderecamento }" @click="toggleEstoqueAtributo('controla_enderecamento')"><MapPin :size="16" />Controla endereçamento</button>
                                        <button type="button" class="attr-tile" :class="{ 'is-active': form.estoque_detalhado.atributos_logisticos_flags.inflamavel }" @click="toggleEstoqueAtributo('inflamavel')"><Flame :size="16" />Inflamável</button>
                                        <button type="button" class="attr-tile" :class="{ 'is-active': form.estoque_detalhado.atributos_logisticos_flags.fragil }" @click="toggleEstoqueAtributo('fragil')"><AlertTriangle :size="16" />Frágil</button>
                                        <button type="button" class="attr-tile" :class="{ 'is-active': form.estoque_detalhado.atributos_logisticos_flags.empilhavel }" @click="toggleEstoqueAtributo('empilhavel')"><Layers3 :size="16" />Empilhável</button>
                                        <button type="button" class="attr-tile" :class="{ 'is-active': form.estoque_detalhado.atributos_logisticos_flags.pesavel }" @click="toggleEstoqueAtributo('pesavel')"><Ruler :size="16" />Pesável</button>
                                        <button type="button" class="attr-tile" :class="{ 'is-active': form.estoque_detalhado.atributos_logisticos_flags.toxico }" @click="toggleEstoqueAtributo('toxico')"><Bug :size="16" />Tóxico</button>
                                        <button type="button" class="attr-tile" :class="{ 'is-active': form.estoque_detalhado.atributos_logisticos_flags.corrosivo }" @click="toggleEstoqueAtributo('corrosivo')"><Droplets :size="16" />Corrosivo</button>
                                        <button type="button" class="attr-tile" :class="{ 'is-active': form.estoque_detalhado.atributos_logisticos_flags.e_commerce }" @click="toggleEstoqueAtributo('e_commerce')"><ShoppingCart :size="16" />E-commerce</button>
                                        <button type="button" class="attr-tile" :class="{ 'is-active': form.estoque_detalhado.atributos_logisticos_flags.agronomico }" @click="toggleEstoqueAtributo('agronomico')"><Sprout :size="16" />Agronômico</button>
                                    </div>
                                </div>
                            </div>

                            <div class="product-card">
                                <div class="card-section-header">
                                    <div class="card-section-icon"><MapPin :size="18" /></div>
                                    <div>
                                        <h2>Endereçamento logistico</h2>
                                        <p>Defina o local físico padrão para armazenagem e movimentação do produto.</p>
                                    </div>
                                </div>
                                <div class="note-banner">
                                    <div class="note-banner-text"><Info :size="16" /><span>Locais e endereços de estoque agora possuem cadastro administrativo centralizado.</span></div>
                                    <AppButton variant="secondary">Abrir cadastros de estoque</AppButton>
                                </div>
                                <div class="mt-3">
                                    <AppCheckbox v-model="form.estoque_detalhado.endereco_controlado" label="Controla endereçamento por filial, depósito, local e endereço físico" />
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-3">
                                    <AppInput v-model="form.estoque_detalhado.filial" label="Filial" placeholder="Ex: Matriz" />
                                    <AppInput v-model="form.estoque_detalhado.deposito_armazem" label="Deposito/Armazem" placeholder="Ex: Armazem Central" />
                                    <AppInput v-model="form.estoque_detalhado.local_estoque" label="Local de estoque" placeholder="Ex: Pulmao A" />
                                    <AppInput v-model="form.estoque_detalhado.rua" label="Rua" />
                                    <AppInput v-model="form.estoque_detalhado.modulo" label="Modulo" />
                                    <AppInput v-model="form.estoque_detalhado.prateleira" label="Prateleira" />
                                    <AppInput v-model="form.estoque_detalhado.nivel" label="Nivel" />
                                    <AppInput v-model="form.estoque_detalhado.posicao" label="Posicao" />
                                </div>
                            </div>

                            <div class="product-card">
                                <div class="section-inline-header">
                                    <div>
                                        <h2>Saldo em estoque (consolidado)</h2>
                                        <p>Leitura centralizada dos saldos por lote e custo para consulta operacional.</p>
                                    </div>
                                    <AppButton variant="secondary"><Filter :size="16" />Filtrar</AppButton>
                                </div>

                                <div class="barcode-grid-shell">
                                    <div class="barcode-grid-controls">
                                        <select class="ui-field barcode-columns-select">
                                            <option>Colunas</option>
                                        </select>
                                        <div class="barcode-search-wrap">
                                            <input class="ui-field" placeholder="Pesquisar...">
                                            <Search :size="18" class="barcode-search-icon" />
                                        </div>
                                        <div class="barcode-pages-wrap">
                                            <span>Linhas por pagina</span>
                                            <select class="ui-field barcode-page-select">
                                                <option>10</option>
                                            </select>
                                            <span>Pagina 1 de 1</span>
                                            <div class="barcode-nav-btns">
                                                <button type="button" class="nav-icon-btn"><ChevronsLeft :size="16" /></button>
                                                <button type="button" class="nav-icon-btn"><ChevronLeft :size="16" /></button>
                                                <button type="button" class="nav-icon-btn"><ChevronRight :size="16" /></button>
                                                <button type="button" class="nav-icon-btn"><ChevronsRight :size="16" /></button>
                                            </div>
                                        </div>
                                    </div>

                                    <AppTable>
                                        <thead>
                                            <tr>
                                                <th>Filial</th>
                                                <th>Deposito</th>
                                                <th>Local Estoque</th>
                                                <th>Endereco</th>
                                                <th>Lote</th>
                                                <th>Quantidade</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-if="(form.estoque_detalhado.saldo_consolidado_rows || []).length === 0">
                                                <td colspan="6" class="text-center text-muted py-10">Sem resultados...</td>
                                            </tr>
                                            <tr v-for="(row, idx) in form.estoque_detalhado.saldo_consolidado_rows" :key="`saldo-row-${idx}`">
                                                <td>{{ row.filial }}</td>
                                                <td>{{ row.deposito }}</td>
                                                <td>{{ row.local_estoque }}</td>
                                                <td>{{ row.endereco }}</td>
                                                <td>{{ row.lote }}</td>
                                                <td>{{ row.quantidade }}</td>
                                            </tr>
                                        </tbody>
                                    </AppTable>

                                    <p class="barcode-grid-footer">0 de {{ (form.estoque_detalhado.saldo_consolidado_rows || []).length }} linha(s) selecionada(s).</p>
                                </div>
                            </div>
                        </div>

                        <div v-if="estoqueSubTab === 'codigo_fornecedor'" class="space-y-4">
                            <div class="product-basic-title-wrap">
                                <h2 class="product-basic-title">Codigo do Fornecedor</h2>
                                <p class="product-basic-subtitle">Dados de compra, homologação e referência de fornecedor.</p>
                            </div>
                            <div class="product-card">
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <AppInput v-model="form.estoque_detalhado.codigo_fornecedor" label="Código do fornecedor" />
                                    <AppInput v-model="form.estoque_detalhado.codigo_barras_fornecedor" label="Código barras fornecedor" />
                                    <AppInput v-model="form.estoque_detalhado.custo_ultima_compra" label="Custo ultima compra" />
                                    <AppInput v-model="form.estoque_detalhado.lead_time_fornecedor" label="Lead time fornecedor" />
                                    <AppInput v-model="form.estoque_detalhado.lote_minimo_fornecedor" label="Lote minimo fornecedor" />
                                </div>
                            </div>
                        </div>

                        <div v-if="estoqueSubTab === 'saldo_lotes'" class="space-y-4">
                            <div class="product-basic-title-wrap">
                                <h2 class="product-basic-title">Saldo de Lotes</h2>
                                <p class="product-basic-subtitle">Leitura consolidada por lote, custo e endereçamento.</p>
                            </div>
                            <div class="product-card">
                                <h2>Saldo de Lotes e Camadas de Custo</h2>
                                <p>A validade real é controlada por lote. O cadastro de produto apenas exibe leitura consolidada.</p>
                                <div class="barcode-grid-shell">
                                    <div class="barcode-grid-controls">
                                        <select class="ui-field barcode-columns-select">
                                            <option>Colunas</option>
                                        </select>
                                        <div class="barcode-search-wrap">
                                            <input class="ui-field" placeholder="Pesquisar...">
                                            <Search :size="18" class="barcode-search-icon" />
                                        </div>
                                        <div class="barcode-pages-wrap">
                                            <span>Linhas por pagina</span>
                                            <select class="ui-field barcode-page-select">
                                                <option>10</option>
                                            </select>
                                            <span>Pagina 1 de 1</span>
                                            <div class="barcode-nav-btns">
                                                <button type="button" class="nav-icon-btn"><ChevronsLeft :size="16" /></button>
                                                <button type="button" class="nav-icon-btn"><ChevronLeft :size="16" /></button>
                                                <button type="button" class="nav-icon-btn"><ChevronRight :size="16" /></button>
                                                <button type="button" class="nav-icon-btn"><ChevronsRight :size="16" /></button>
                                            </div>
                                        </div>
                                    </div>

                                    <AppTable>
                                        <thead>
                                            <tr>
                                                <th>Lote</th>
                                                <th>Data Fabricação</th>
                                                <th>Data Validade</th>
                                                <th>Qtd Inicial</th>
                                                <th>Qtd Saldo</th>
                                                <th>Custo Unitário</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-if="(form.estoque_detalhado.saldo_lotes_rows || []).length === 0">
                                                <td colspan="6" class="text-center text-muted py-10">Sem resultados...</td>
                                            </tr>
                                            <tr v-for="(row, idx) in form.estoque_detalhado.saldo_lotes_rows" :key="`lote-row-${idx}`">
                                                <td>{{ row.lote }}</td>
                                                <td>{{ row.data_fabricacao }}</td>
                                                <td>{{ row.data_validade }}</td>
                                                <td>{{ row.qtd_inicial }}</td>
                                                <td>{{ row.qtd_saldo }}</td>
                                                <td>{{ row.custo_unitario }}</td>
                                            </tr>
                                        </tbody>
                                    </AppTable>

                                    <p class="barcode-grid-footer">0 de {{ (form.estoque_detalhado.saldo_lotes_rows || []).length }} linha(s) selecionada(s).</p>
                                </div>
                            </div>
                        </div>

                        <div v-if="estoqueSubTab === 'dimensoes'" class="space-y-4">
                            <div class="product-basic-title-wrap">
                                <h2 class="product-basic-title">Dimensoes</h2>
                                <p class="product-basic-subtitle">Dimensões físicas e características logísticas.</p>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="product-card">
                                    <h2>Produto Embalado</h2>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                        <label class="stock-mini-field">
                                            <span class="stock-mini-label"><PackageOpen :size="14" />Peso Bruto</span>
                                            <div class="stock-mini-control">
                                                <span class="stock-mini-leading-icon"><PackageOpen :size="14" /></span>
                                                <input v-model="form.estoque_detalhado.dimensoes_embalado.peso_bruto" type="number" step="0.0001" class="ui-field stock-mini-input stock-mini-input--with-icon">
                                                <span class="stock-mini-suffix">KG</span>
                                            </div>
                                        </label>
                                        <label class="stock-mini-field">
                                            <span class="stock-mini-label"><Ruler :size="14" />Altura</span>
                                            <div class="stock-mini-control">
                                                <span class="stock-mini-leading-icon"><Ruler :size="14" /></span>
                                                <input v-model="form.estoque_detalhado.dimensoes_embalado.altura" type="number" step="0.0001" class="ui-field stock-mini-input stock-mini-input--with-icon">
                                                <span class="stock-mini-suffix">M</span>
                                            </div>
                                        </label>
                                        <label class="stock-mini-field">
                                            <span class="stock-mini-label"><Ruler :size="14" />Largura</span>
                                            <div class="stock-mini-control">
                                                <span class="stock-mini-leading-icon"><Ruler :size="14" /></span>
                                                <input v-model="form.estoque_detalhado.dimensoes_embalado.largura" type="number" step="0.0001" class="ui-field stock-mini-input stock-mini-input--with-icon">
                                                <span class="stock-mini-suffix">M</span>
                                            </div>
                                        </label>
                                        <label class="stock-mini-field">
                                            <span class="stock-mini-label"><Ruler :size="14" />Comprimento</span>
                                            <div class="stock-mini-control">
                                                <span class="stock-mini-leading-icon"><Ruler :size="14" /></span>
                                                <input v-model="form.estoque_detalhado.dimensoes_embalado.comprimento" type="number" step="0.0001" class="ui-field stock-mini-input stock-mini-input--with-icon">
                                                <span class="stock-mini-suffix">M</span>
                                            </div>
                                        </label>
                                        <label class="stock-mini-field md:col-span-2">
                                            <span class="stock-mini-label"><Boxes :size="14" />Volume</span>
                                            <div class="stock-mini-control">
                                                <span class="stock-mini-leading-icon"><Boxes :size="14" /></span>
                                                <input v-model="form.estoque_detalhado.dimensoes_embalado.volume" type="number" step="0.0001" class="ui-field stock-mini-input stock-mini-input--with-icon">
                                                <span class="stock-mini-suffix">L</span>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                <div class="product-card">
                                    <h2>Produto Sem Embalagem</h2>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                        <label class="stock-mini-field">
                                            <span class="stock-mini-label"><PackageOpen :size="14" />Peso Liquido</span>
                                            <div class="stock-mini-control">
                                                <span class="stock-mini-leading-icon"><PackageOpen :size="14" /></span>
                                                <input v-model="form.estoque_detalhado.dimensoes_sem_embalagem.peso_liquido" type="number" step="0.0001" class="ui-field stock-mini-input stock-mini-input--with-icon">
                                                <span class="stock-mini-suffix">KG</span>
                                            </div>
                                        </label>
                                        <label class="stock-mini-field">
                                            <span class="stock-mini-label"><Ruler :size="14" />Altura</span>
                                            <div class="stock-mini-control">
                                                <span class="stock-mini-leading-icon"><Ruler :size="14" /></span>
                                                <input v-model="form.estoque_detalhado.dimensoes_sem_embalagem.altura" type="number" step="0.0001" class="ui-field stock-mini-input stock-mini-input--with-icon">
                                                <span class="stock-mini-suffix">M</span>
                                            </div>
                                        </label>
                                        <label class="stock-mini-field">
                                            <span class="stock-mini-label"><Ruler :size="14" />Largura</span>
                                            <div class="stock-mini-control">
                                                <span class="stock-mini-leading-icon"><Ruler :size="14" /></span>
                                                <input v-model="form.estoque_detalhado.dimensoes_sem_embalagem.largura" type="number" step="0.0001" class="ui-field stock-mini-input stock-mini-input--with-icon">
                                                <span class="stock-mini-suffix">M</span>
                                            </div>
                                        </label>
                                        <label class="stock-mini-field">
                                            <span class="stock-mini-label"><Ruler :size="14" />Comprimento</span>
                                            <div class="stock-mini-control">
                                                <span class="stock-mini-leading-icon"><Ruler :size="14" /></span>
                                                <input v-model="form.estoque_detalhado.dimensoes_sem_embalagem.comprimento" type="number" step="0.0001" class="ui-field stock-mini-input stock-mini-input--with-icon">
                                                <span class="stock-mini-suffix">M</span>
                                            </div>
                                        </label>
                                        <label class="stock-mini-field md:col-span-2">
                                            <span class="stock-mini-label"><Boxes :size="14" />Volume</span>
                                            <div class="stock-mini-control">
                                                <span class="stock-mini-leading-icon"><Boxes :size="14" /></span>
                                                <input v-model="form.estoque_detalhado.dimensoes_sem_embalagem.volume" type="number" step="0.0001" class="ui-field stock-mini-input stock-mini-input--with-icon">
                                                <span class="stock-mini-suffix">L</span>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <label class="stock-mini-field">
                                    <span class="stock-mini-label"><Ruler :size="14" />Espessura</span>
                                    <div class="stock-mini-control">
                                        <span class="stock-mini-leading-icon"><Ruler :size="14" /></span>
                                        <input v-model="form.estoque_detalhado.espessura" type="number" step="0.0001" class="ui-field stock-mini-input stock-mini-input--with-icon">
                                        <span class="stock-mini-suffix">M</span>
                                    </div>
                                </label>
                                <label class="stock-mini-field">
                                    <span class="stock-mini-label"><Layers3 :size="14" />Densidade</span>
                                    <div class="stock-mini-control">
                                        <span class="stock-mini-leading-icon"><Layers3 :size="14" /></span>
                                        <input v-model="form.estoque_detalhado.densidade" type="number" step="0.0001" class="ui-field stock-mini-input stock-mini-input--with-icon">
                                        <span class="stock-mini-suffix">KG/M³</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div v-if="estoqueSubTab === 'unidades_embalagens'" class="space-y-4">
                            <div class="product-basic-title-wrap">
                                <h2 class="product-basic-title">Unidades e Embalagens</h2>
                                <p class="product-basic-subtitle">Conversões comerciais com foco em usabilidade de embalagem.</p>
                            </div>
                            <div class="product-card">
                                <h2>Unidades e Embalagens</h2>
                                <p>O fator de conversão existe no modelo e é preenchido automaticamente a partir da quantidade contida.</p>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <AppSelect v-model="form.estoque_detalhado.unidade_base_estoque" label="Unidade base estoque">
                                        <option value="">Selecione</option>
                                        <option v-for="u in supportData.unidades_medida" :key="`ub-${u.id}`" :value="u.unidade">{{ u.unidade }}</option>
                                    </AppSelect>
                                    <AppSelect v-model="form.estoque_detalhado.unidade_compra" label="Unidade compra">
                                        <option value="">Selecione</option>
                                        <option v-for="u in supportData.unidades_medida" :key="`uc-${u.id}`" :value="u.unidade">{{ u.unidade }}</option>
                                    </AppSelect>
                                    <AppSelect v-model="form.estoque_detalhado.unidade_venda" label="Unidade venda">
                                        <option value="">Selecione</option>
                                        <option v-for="u in supportData.unidades_medida" :key="`uv-${u.id}`" :value="u.unidade">{{ u.unidade }}</option>
                                    </AppSelect>
                                </div>

                                <div class="section-inline-header mt-5">
                                    <div><h2>Embalagens comerciais</h2></div>
                                    <AppButton variant="secondary" @click="addEmbalagemComercial"><Plus :size="15" />Adicionar embalagem</AppButton>
                                </div>

                                <div class="space-y-3">
                                    <div v-for="(emb, idx) in form.estoque_detalhado.embalagens" :key="`emb-${idx}`" class="child-card">
                                        <div class="child-card-header">
                                            <h4>Embalagem {{ idx + 1 }}</h4>
                                            <AppButton variant="danger" @click="removeEmbalagemComercial(idx)">Remover</AppButton>
                                        </div>
                                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                            <AppInput v-model="emb.descricao" label="Descrição" />
                                            <AppInput v-model="emb.unidade_comercial" label="Unidade comercial" />
                                            <AppInput v-model="emb.quantidade_contida" label="Quantidade contida" />
                                            <AppInput v-model="emb.unidade_base_referencia" label="Unidade base referência" />
                                            <AppInput v-model="emb.fator_conversao" label="Fator conversão calculado" />
                                            <AppInput v-model="emb.custo_embalagem" label="Custo embalagem" />
                                            <AppInput v-model="emb.preco_embalagem" label="Preço embalagem" />
                                            <AppInput v-model="emb.codigo_barras" label="Código barras" />
                                        </div>
                                    </div>
                                    <SettingsEmptyState
                                        v-if="form.estoque_detalhado.embalagens.length === 0"
                                        title="Sem embalagens comerciais"
                                        description="Adicione embalagens para operar com múltiplas apresentações."
                                    />
                                </div>
                            </div>
                        </div>
                    </section>

                    <section v-if="activeTab === 'gerencial'" class="space-y-4">
                        <div class="product-basic-title-wrap">
                            <h2 class="product-basic-title">Gerencial</h2>
                            <p class="product-basic-subtitle">Adicione os dados gerenciais do produto.</p>
                        </div>

                        <div class="gerencial-layout">
                            <aside class="product-card gerencial-summary-card">
                                <h2>Resumo Gerencial</h2>
                                <div class="gerencial-summary-list">
                                    <div class="gerencial-summary-item">
                                        <div class="gerencial-summary-label-wrap">
                                            <span class="gerencial-summary-label">Custo base precificação</span>
                                            <span class="calc-chip">CALCULADO</span>
                                        </div>
                                        <div class="gerencial-summary-value">{{ formatCurrency(gerencialCustoBase) }}</div>
                                    </div>
                                    <div class="gerencial-summary-item">
                                        <div class="gerencial-summary-label-wrap">
                                            <span class="gerencial-summary-label">Custo real</span>
                                            <span class="calc-chip">CALCULADO</span>
                                        </div>
                                        <div class="gerencial-summary-value">{{ formatCurrency(gerencialCustoReal) }}</div>
                                    </div>
                                    <div class="gerencial-summary-item">
                                        <div class="gerencial-summary-label-wrap">
                                            <span class="gerencial-summary-label">Preço de venda atual</span>
                                            <span class="calc-chip">CALCULADO</span>
                                        </div>
                                        <div class="gerencial-summary-value">{{ formatCurrency(gerencialPrecoVendaAtual) }}</div>
                                    </div>
                                    <div class="gerencial-summary-item">
                                        <div class="gerencial-summary-label-wrap">
                                            <span class="gerencial-summary-label">Margem real</span>
                                            <span class="calc-chip">CALCULADO</span>
                                        </div>
                                        <div class="gerencial-summary-value">{{ formatPercent(gerencialMargemReal) }}</div>
                                    </div>
                                    <div class="gerencial-summary-item">
                                        <div class="gerencial-summary-label-wrap">
                                            <span class="gerencial-summary-label">Preço sugerido</span>
                                            <span class="calc-chip">CALCULADO</span>
                                        </div>
                                        <div class="gerencial-summary-value">{{ formatCurrency(gerencialPrecoSugerido) }}</div>
                                    </div>
                                    <div class="gerencial-summary-item">
                                        <div class="gerencial-summary-label-wrap">
                                            <span class="gerencial-summary-label">Preço minimo</span>
                                            <span class="calc-chip">CALCULADO</span>
                                        </div>
                                        <div class="gerencial-summary-value">{{ formatCurrency(gerencialPrecoMinimo) }}</div>
                                    </div>
                                    <div class="gerencial-summary-item">
                                        <div class="gerencial-summary-label-wrap">
                                            <span class="gerencial-summary-label">Markup atual</span>
                                            <span class="calc-chip">CALCULADO</span>
                                        </div>
                                        <div class="gerencial-summary-value">{{ formatPercent(gerencialMarkupAtual) }}</div>
                                    </div>
                                </div>

                                <div class="gerencial-reference-box">
                                    <span class="reference-caption">TABELA DE REFERÊNCIA</span>
                                    <strong>CUSTO</strong>
                                    <p>Fonte do custo-base: Tabela de preço</p>
                                </div>

                                <div class="gerencial-mini-grid">
                                    <div class="gerencial-mini-box">
                                        <span>CUSTO REFERENCIAL MANUAL</span>
                                        <strong>{{ formatCurrency(toDecimal(form.gerencial_memoria.custo_referencial_manual)) }}</strong>
                                    </div>
                                    <div class="gerencial-mini-box">
                                        <span>DIFERENÇA VS CUSTO REAL</span>
                                        <strong>{{ formatCurrency(toDecimal(form.gerencial_memoria.custo_referencial_manual) - gerencialCustoReal) }}</strong>
                                    </div>
                                </div>
                            </aside>

                            <section class="product-card gerencial-pricing-card">
                                <div class="section-inline-header">
                                    <div>
                                        <h2>Preco de Venda</h2>
                                        <p>Campos com R$ representam valores monetarios; campos com % representam percentuais.</p>
                                    </div>
                                    <AppButton variant="secondary" @click="addPreco"><Plus :size="15" />Adicionar preço</AppButton>
                                </div>

                                <div class="gerencial-price-grid">
                                    <div v-for="(row, index) in form.precos" :key="`${row.id || 'new'}-${index}`" class="gerencial-price-card">
                                        <div class="gerencial-price-card-header">
                                            <h2>Tabela {{ index + 1 }}</h2>
                                            <button type="button" class="gerencial-remove-btn" @click="removePreco(index)">Remover</button>
                                        </div>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <AppSelect v-model="row.tipo" label="Tipo de tabela">
                                                <option v-for="option in supportData.tipos_preco" :key="option.id" :value="option.id">{{ option.label }}</option>
                                            </AppSelect>
                                            <label class="ger-field">
                                                <span class="ger-field-label"><FileText :size="14" />Código da tabela</span>
                                                <div class="ger-field-control">
                                                    <span class="ger-field-leading-icon"><FileText :size="14" /></span>
                                                    <input v-model="row.codigo" class="ui-field ger-field-input ger-field-input--with-icon">
                                                </div>
                                            </label>
                                            <label class="ger-field">
                                                <span class="ger-field-label"><DollarSign :size="14" />Valor de venda</span>
                                                <div class="ger-field-control">
                                                    <span class="ger-field-leading-icon"><DollarSign :size="14" /></span>
                                                    <input v-model="row.valor" type="number" step="0.0001" min="0" class="ui-field ger-field-input ger-field-input--with-icon">
                                                </div>
                                            </label>
                                            <label class="ger-field">
                                                <span class="ger-field-label"><Network :size="14" />Canal</span>
                                                <div class="ger-field-control">
                                                    <span class="ger-field-leading-icon"><Network :size="14" /></span>
                                                    <input v-model="row.canal" class="ui-field ger-field-input ger-field-input--with-icon">
                                                </div>
                                            </label>
                                            <label class="ger-field">
                                                <span class="ger-field-label"><DollarSign :size="14" />Custo referencial</span>
                                                <div class="ger-field-control">
                                                    <span class="ger-field-leading-icon"><DollarSign :size="14" /></span>
                                                    <input v-model="row.custo_referencial" type="number" step="0.0001" min="0" class="ui-field ger-field-input ger-field-input--with-icon">
                                                </div>
                                            </label>
                                            <label class="ger-field">
                                                <span class="ger-field-label"><Percent :size="14" />Margem alvo</span>
                                                <div class="ger-field-control">
                                                    <span class="ger-field-leading-icon"><Percent :size="14" /></span>
                                                    <input v-model="row.margem" type="number" step="0.01" class="ui-field ger-field-input ger-field-input--with-icon ger-field-input--with-suffix">
                                                    <span class="ger-field-suffix">%</span>
                                                </div>
                                            </label>
                                            <label class="ger-field">
                                                <span class="ger-field-label"><Percent :size="14" />Margem minima</span>
                                                <div class="ger-field-control">
                                                    <span class="ger-field-leading-icon"><Percent :size="14" /></span>
                                                    <input v-model="row.margem_preco_minimo" type="number" step="0.01" class="ui-field ger-field-input ger-field-input--with-icon ger-field-input--with-suffix">
                                                    <span class="ger-field-suffix">%</span>
                                                </div>
                                            </label>
                                            <label class="ger-field">
                                                <span class="ger-field-label"><Percent :size="14" />Percentual</span>
                                                <div class="ger-field-control">
                                                    <span class="ger-field-leading-icon"><Percent :size="14" /></span>
                                                    <input v-model="row.percentual" type="number" step="0.01" class="ui-field ger-field-input ger-field-input--with-icon ger-field-input--with-suffix">
                                                    <span class="ger-field-suffix">%</span>
                                                </div>
                                            </label>
                                            <label class="ger-field">
                                                <span class="ger-field-label"><Clock3 :size="14" />Vigencia inicio</span>
                                                <div class="ger-field-control">
                                                    <span class="ger-field-leading-icon"><Clock3 :size="14" /></span>
                                                    <input v-model="row.vigencia_inicio" type="date" class="ui-field ger-field-input ger-field-input--with-icon">
                                                </div>
                                            </label>
                                            <label class="ger-field">
                                                <span class="ger-field-label"><Clock3 :size="14" />Vigencia fim</span>
                                                <div class="ger-field-control">
                                                    <span class="ger-field-leading-icon"><Clock3 :size="14" /></span>
                                                    <input v-model="row.vigencia_fim" type="date" class="ui-field ger-field-input ger-field-input--with-icon">
                                                </div>
                                            </label>
                                        </div>
                                        <div class="mt-3">
                                            <AppCheckbox v-model="row.ativo" label="Preco ativo" />
                                        </div>
                                    </div>
                                </div>

                                <SettingsEmptyState
                                    v-if="form.precos.length === 0"
                                    title="Sem tabelas de preço"
                                    description="Adicione uma tabela para configuração comercial."
                                />
                            </section>
                        </div>

                        <div class="product-card gerencial-memory-card">
                            <div class="gerencial-memory-header">
                                <div class="gerencial-memory-title">
                                    <h2>Memoria de Calculo</h2>
                                    <span class="exp-chip">Expandivel</span>
                                </div>
                                <button type="button" class="memory-toggle-btn" @click="gerencialMemoryExpanded = !gerencialMemoryExpanded">
                                    <ChevronRight v-if="!gerencialMemoryExpanded" :size="16" />
                                    <ChevronRight v-else :size="16" class="rotate-90" />
                                </button>
                            </div>
                            <p>Campos com R$ representam valores monetarios e campos com % representam percentuais.</p>

                            <div v-if="gerencialMemoryExpanded" class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <label class="ger-field">
                                        <span class="ger-field-label"><DollarSign :size="14" />Custo compra</span>
                                        <div class="ger-field-control">
                                            <span class="ger-field-leading-icon"><DollarSign :size="14" /></span>
                                            <input v-model="form.gerencial_memoria.custo_compra" class="ui-field ger-field-input ger-field-input--with-icon">
                                        </div>
                                    </label>
                                    <label class="ger-field">
                                        <span class="ger-field-label"><DollarSign :size="14" />Frete</span>
                                        <div class="ger-field-control">
                                            <span class="ger-field-leading-icon"><DollarSign :size="14" /></span>
                                            <input v-model="form.gerencial_memoria.frete" class="ui-field ger-field-input ger-field-input--with-icon">
                                        </div>
                                    </label>
                                    <label class="ger-field">
                                        <span class="ger-field-label"><DollarSign :size="14" />Seguro</span>
                                        <div class="ger-field-control">
                                            <span class="ger-field-leading-icon"><DollarSign :size="14" /></span>
                                            <input v-model="form.gerencial_memoria.seguro" class="ui-field ger-field-input ger-field-input--with-icon">
                                        </div>
                                    </label>
                                    <label class="ger-field">
                                        <span class="ger-field-label"><DollarSign :size="14" />Despesas acessorias</span>
                                        <div class="ger-field-control">
                                            <span class="ger-field-leading-icon"><DollarSign :size="14" /></span>
                                            <input v-model="form.gerencial_memoria.despesas_acessorias" class="ui-field ger-field-input ger-field-input--with-icon">
                                        </div>
                                    </label>
                                    <label class="ger-field">
                                        <span class="ger-field-label"><DollarSign :size="14" />Desconto</span>
                                        <div class="ger-field-control">
                                            <span class="ger-field-leading-icon"><DollarSign :size="14" /></span>
                                            <input v-model="form.gerencial_memoria.desconto" class="ui-field ger-field-input ger-field-input--with-icon">
                                        </div>
                                    </label>
                                    <label class="ger-field">
                                        <span class="ger-field-label"><DollarSign :size="14" />IPI</span>
                                        <div class="ger-field-control">
                                            <span class="ger-field-leading-icon"><DollarSign :size="14" /></span>
                                            <input v-model="form.gerencial_memoria.ipi" class="ui-field ger-field-input ger-field-input--with-icon">
                                        </div>
                                    </label>
                                    <label class="ger-field">
                                        <span class="ger-field-label"><DollarSign :size="14" />ICMS ST</span>
                                        <div class="ger-field-control">
                                            <span class="ger-field-leading-icon"><DollarSign :size="14" /></span>
                                            <input v-model="form.gerencial_memoria.icms_st" class="ui-field ger-field-input ger-field-input--with-icon">
                                        </div>
                                    </label>
                                    <label class="ger-field">
                                        <span class="ger-field-label"><DollarSign :size="14" />Impostos recuperaveis</span>
                                        <div class="ger-field-control">
                                            <span class="ger-field-leading-icon"><DollarSign :size="14" /></span>
                                            <input v-model="form.gerencial_memoria.impostos_recuperaveis" class="ui-field ger-field-input ger-field-input--with-icon">
                                        </div>
                                    </label>
                                    <label class="ger-field">
                                        <span class="ger-field-label"><DollarSign :size="14" />Custo financeiro</span>
                                        <div class="ger-field-control">
                                            <span class="ger-field-leading-icon"><DollarSign :size="14" /></span>
                                            <input v-model="form.gerencial_memoria.custo_financeiro" class="ui-field ger-field-input ger-field-input--with-icon">
                                        </div>
                                    </label>
                                    <label class="ger-field">
                                        <span class="ger-field-label"><DollarSign :size="14" />Custo reposicao</span>
                                        <div class="ger-field-control">
                                            <span class="ger-field-leading-icon"><DollarSign :size="14" /></span>
                                            <input v-model="form.gerencial_memoria.custo_reposicao" class="ui-field ger-field-input ger-field-input--with-icon">
                                        </div>
                                    </label>
                                    <label class="ger-field">
                                        <span class="ger-field-label"><DollarSign :size="14" />Custo real</span>
                                        <div class="ger-field-control">
                                            <span class="ger-field-leading-icon"><DollarSign :size="14" /></span>
                                            <input v-model="form.gerencial_memoria.custo_real" class="ui-field ger-field-input ger-field-input--with-icon">
                                        </div>
                                    </label>
                                    <label class="ger-field">
                                        <span class="ger-field-label"><DollarSign :size="14" />Preco venda atual</span>
                                        <div class="ger-field-control">
                                            <span class="ger-field-leading-icon"><DollarSign :size="14" /></span>
                                            <input v-model="form.gerencial_memoria.preco_venda_atual" class="ui-field ger-field-input ger-field-input--with-icon">
                                        </div>
                                    </label>
                                    <label class="ger-field">
                                        <span class="ger-field-label"><Percent :size="14" />Margem nominal</span>
                                        <div class="ger-field-control">
                                            <span class="ger-field-leading-icon"><Percent :size="14" /></span>
                                            <input v-model="form.gerencial_memoria.margem_nominal" class="ui-field ger-field-input ger-field-input--with-icon ger-field-input--with-suffix">
                                            <span class="ger-field-suffix">%</span>
                                        </div>
                                    </label>
                                    <label class="ger-field">
                                        <span class="ger-field-label"><Percent :size="14" />Margem real</span>
                                        <div class="ger-field-control">
                                            <span class="ger-field-leading-icon"><Percent :size="14" /></span>
                                            <input v-model="form.gerencial_memoria.margem_real" class="ui-field ger-field-input ger-field-input--with-icon ger-field-input--with-suffix">
                                            <span class="ger-field-suffix">%</span>
                                        </div>
                                    </label>
                                    <label class="ger-field">
                                        <span class="ger-field-label"><DollarSign :size="14" />Custo referencial manual</span>
                                        <div class="ger-field-control">
                                            <span class="ger-field-leading-icon"><DollarSign :size="14" /></span>
                                            <input v-model="form.gerencial_memoria.custo_referencial_manual" class="ui-field ger-field-input ger-field-input--with-icon">
                                        </div>
                                    </label>
                                </div>

                                <div class="gerencial-kpi-grid">
                                    <div class="kpi-box">
                                        <span>SUBTOTAL</span>
                                        <strong>{{ formatCurrency(gerencialSubtotal) }}</strong>
                                    </div>
                                    <div class="kpi-box">
                                        <span>TOTAL</span>
                                        <strong>{{ formatCurrency(gerencialTotal) }}</strong>
                                    </div>
                                    <div class="kpi-box is-positive">
                                        <span>MARGEM REAL</span>
                                        <strong>{{ formatPercent(gerencialMargemReal) }}</strong>
                                    </div>
                                </div>

                                <div class="gerencial-final-box">
                                    <h4>Resumo Final</h4>
                                    <p>Custo real: <strong>{{ formatCurrency(gerencialCustoReal) }}</strong></p>
                                    <p>Preço atual: <strong>{{ formatCurrency(gerencialPrecoVendaAtual) }}</strong></p>
                                    <p>Margem real: <strong>{{ formatPercent(gerencialMargemReal) }}</strong></p>
                                    <p>Margem alvo configurada: <strong>{{ formatPercent(toDecimal(gerencialAtivo?.margem)) }}</strong></p>
                                    <p>Margem minima configurada: <strong>{{ formatPercent(toDecimal(gerencialAtivo?.margem_preco_minimo)) }}</strong></p>
                                    <p>Markup atual: <strong>{{ formatPercent(gerencialMarkupAtual) }}</strong></p>
                                </div>
                            </div>
                        </div>

                        <div class="gerencial-bottom-cards">
                            <div class="product-card">
                                <h2>Ultima entrada</h2>
                                <p>NF: -</p>
                                <p>Série: -</p>
                                <p>Recebimento: -</p>
                                <p>Status: -</p>
                                <p>Fornecedor: -</p>
                            </div>
                            <div class="product-card">
                                <h2>Lote de origem/fabricação</h2>
                                <p>Lote: -</p>
                                <p>Fabricação: -</p>
                                <p>Validade: -</p>
                                <p>Status: -</p>
                            </div>
                            <div class="product-card">
                                <h2>Histórico referencial</h2>
                                <p>Histórico: #-</p>
                                <p>Documento: -</p>
                                <p>Compra: -</p>
                                <p>Recebimento: -</p>
                            </div>
                        </div>
                    </section>

                    <section v-if="activeTab === 'historico'" class="space-y-4">
                        <div class="product-basic-title-wrap">
                            <h2 class="product-basic-title">Historico</h2>
                            <p class="product-basic-subtitle">Adicione o historico do produto.</p>
                        </div>

                        <div class="product-card">
                            <h2>Histórico de Alterações do Produto</h2>
                            <p>Exibe somente alterações do produto atual.</p>

                            <div class="historico-filters">
                                <AppInput v-model="historicoFilterDraft.data_inicio" type="date" placeholder="dd/mm/aaaa" />
                                <AppInput v-model="historicoFilterDraft.data_fim" type="date" placeholder="dd/mm/aaaa" />
                                <AppSelect v-model="historicoFilterDraft.evento">
                                    <option value="">Todos os eventos</option>
                                    <option v-for="event in historicoEventsOptions" :key="`hist-event-${event.id}`" :value="event.id">{{ event.label }}</option>
                                </AppSelect>
                                <AppSelect v-model="historicoFilterDraft.usuario">
                                    <option value="">Todos os usuários</option>
                                    <option v-for="user in historicoUsuariosOptions" :key="`hist-user-${user}`" :value="user">{{ user }}</option>
                                </AppSelect>
                                <AppButton @click="applyHistoricoFilter">
                                    <Filter :size="16" />
                                    Filtrar
                                </AppButton>
                                <AppButton variant="secondary" @click="clearHistoricoFilter">Limpar</AppButton>
                            </div>

                            <div class="historico-grid-shell">
                                <AppTable>
                                    <thead>
                                        <tr>
                                            <th class="text-left">Data</th>
                                            <th class="text-left">Evento</th>
                                            <th class="text-left">Usuário</th>
                                            <th class="text-left">Alterações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="historicoPagedRows.length === 0">
                                            <td colspan="4" class="text-center text-muted py-12">Sem resultados...</td>
                                        </tr>
                                        <tr v-for="row in historicoPagedRows" :key="row.id">
                                            <td>{{ formatAuditDateTime(row.created_at) }}</td>
                                            <td>
                                                <AppBadge :variant="row.evento === 'deleted' ? 'warning' : 'success'">
                                                    {{ row.evento || 'updated' }}
                                                </AppBadge>
                                            </td>
                                            <td>{{ row.usuario || 'Sistema' }}</td>
                                            <td>
                                                <button
                                                    type="button"
                                                    class="historico-see-btn"
                                                    :disabled="auditChangesCount(row) === 0"
                                                    @click="openAuditModal(row)"
                                                >
                                                    Ver mudanças ({{ auditChangesCount(row) }})
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </AppTable>

                                <div class="historico-footer">
                                    <span>Exibindo {{ historicoFilteredRows.length === 0 ? 0 : ((historicoPage - 1) * historicoRowsPerPage) + 1 }}-{{ Math.min(historicoPage * historicoRowsPerPage, historicoFilteredRows.length) }} de {{ historicoFilteredRows.length }}</span>
                                    <div class="historico-footer-actions">
                                        <select v-model="historicoRowsPerPage" class="ui-field historico-page-select">
                                            <option :value="10">10</option>
                                            <option :value="20">20</option>
                                            <option :value="50">50</option>
                                        </select>
                                        <AppButton variant="secondary" :disabled="historicoPage <= 1" @click="goToHistoricoPrevPage">Anterior</AppButton>
                                        <AppButton variant="secondary" :disabled="historicoPage >= historicoTotalPages" @click="goToHistoricoNextPage">Próxima</AppButton>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <AppModal
                            :open="historicoModalOpen"
                            title="Detalhes da alteração"
                            width-class="max-w-6xl"
                            @close="closeAuditModal"
                        >
                            <p class="field-caption">Campos alterados com os valores de antes e depois.</p>

                            <div v-if="historicoModalChanges.length === 0" class="product-card mt-4">
                                <p>Nenhum campo alterado encontrado para este evento.</p>
                            </div>

                            <div v-else class="space-y-3 mt-4">
                                <div v-for="change in historicoModalChanges" :key="`audit-change-${change.field}`" class="history-change-card">
                                    <h4>{{ change.label }}</h4>
                                    <div class="history-change-grid">
                                        <div class="history-change-col">
                                            <span>Antes</span>
                                            <p>{{ change.before }}</p>
                                        </div>
                                        <div class="history-change-col">
                                            <span>Depois</span>
                                            <p>{{ change.after }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </AppModal>
                    </section>
                </template>
            </section>
        </section>

        <AppModal
            :open="parameterQuickModalOpen"
            :title="parameterQuickModalTitle"
            width-class="max-w-2xl"
            @close="closeParameterQuickModal"
        >
            <div v-if="parameterQuickModalType === 'familia'" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <AppInput
                        v-model="quickFamilyForm.codigo"
                        label="Código *"
                        placeholder="Ex: BEBIDAS"
                        :error="parameterQuickFormErrors.familiaCodigo"
                    />
                    <AppInput
                        v-model="quickFamilyForm.nome"
                        label="Nome *"
                        placeholder="Ex: Bebidas"
                        :error="parameterQuickFormErrors.familiaNome"
                    />
                </div>
                <AppCheckbox v-model="quickFamilyForm.ativo" label="Ativo" />
            </div>

            <div v-else-if="parameterQuickModalType === 'unidade'" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <AppInput
                        v-model="quickUnitForm.unidade"
                        label="Sigla *"
                        placeholder="Ex: UN"
                        :error="parameterQuickFormErrors.unidadeCodigo"
                    />
                    <AppInput
                        v-model="quickUnitForm.descricao"
                        label="Descrição *"
                        placeholder="Ex: Unidade"
                        class="md:col-span-2"
                        :error="parameterQuickFormErrors.unidadeDescricao"
                    />
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <AppInput
                        v-model="quickUnitForm.decimais"
                        label="Casas decimais *"
                        type="number"
                        min="0"
                        max="6"
                        placeholder="0"
                        :error="parameterQuickFormErrors.unidadeDecimais"
                    />
                    <div class="flex items-end pb-1">
                        <AppCheckbox v-model="quickUnitForm.status" label="Ativa" />
                    </div>
                </div>
            </div>

            <div v-else class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <AppInput
                        v-model="quickClassificationForm.codigo"
                        label="Código *"
                        placeholder="Ex: BEB-001"
                        :error="parameterQuickFormErrors.classificacaoCodigo"
                    />
                    <AppInput
                        v-model="quickClassificationForm.descricao"
                        label="Descrição *"
                        placeholder="Ex: Bebidas sem álcool"
                        :error="parameterQuickFormErrors.classificacaoDescricao"
                    />
                </div>
                <AppSelect v-model="quickClassificationForm.parent_id" label="Classificação pai (opcional)">
                    <option value="">Sem classificação pai (nível 1)</option>
                    <option
                        v-for="row in supportData.classificacoes_mercadologicas"
                        :key="`quick-parent-${row.id}`"
                        :value="row.id"
                    >
                        Nível {{ row.nivel }} - {{ row.descricao }}
                    </option>
                </AppSelect>
                <AppCheckbox v-model="quickClassificationForm.ativo" label="Ativa" />
            </div>

            <p v-if="parameterQuickModalError" class="parameter-quick-modal-error">{{ parameterQuickModalError }}</p>

            <div class="barcode-modal-actions">
                <AppButton variant="secondary" @click="closeParameterQuickModal">Cancelar</AppButton>
                <AppButton :loading="parameterQuickModalSaving" @click="saveQuickParameter">Salvar</AppButton>
            </div>
        </AppModal>

        <AppToast :show="toastVisible" :tone="toastTone">{{ toastMessage }}</AppToast>
    </div>
</template>

<style scoped>
.product-editor-layout {
    border: 1px solid color-mix(in srgb, var(--color-border) 85%, transparent);
    border-radius: 1rem;
    background: linear-gradient(180deg, color-mix(in srgb, var(--color-bg-surface) 97%, #000), color-mix(in srgb, var(--color-bg-surface) 92%, #000));
    padding: 1rem;
}

.product-editor-header {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    align-items: center;
    margin-bottom: 0.9rem;
}

.product-breadcrumb {
    margin: 0;
    font-size: 0.75rem;
    color: var(--color-text-muted);
}

.product-editor-title {
    margin: 0.2rem 0 0;
    font-size: 2.1rem;
    line-height: 1.1;
    font-weight: 900;
    color: var(--color-text);
}

.product-editor-actions {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
}

.product-top-tabs {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    overflow-x: auto;
    padding-bottom: 0.65rem;
    margin-bottom: 0.8rem;
    border-bottom: 1px solid var(--color-border);
}

.product-tab-btn {
    border: 1px solid color-mix(in srgb, var(--color-border) 90%, transparent);
    background: color-mix(in srgb, var(--color-bg-surface) 90%, #0b0f17);
    color: var(--color-text-muted);
    border-radius: 0.7rem;
    padding: 0.48rem 0.92rem;
    font-size: 0.92rem;
    font-weight: 700;
    white-space: nowrap;
}

.product-tab-btn.is-active {
    color: #ffffff;
    border-color: color-mix(in srgb, var(--color-primary) 52%, transparent);
    background: color-mix(in srgb, var(--color-primary) 20%, #101420);
}

.product-stage {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.product-validation-list {
    margin: 0;
    padding: 0.25rem 0 0.25rem 1.2rem;
    color: var(--color-danger);
    font-size: 0.84rem;
    display: grid;
    gap: 0.2rem;
}

.product-basic-title-wrap {
    margin-bottom: 0.2rem;
}

.product-basic-title {
    margin: 0;
    font-size: 2rem;
    font-weight: 900;
    color: var(--color-text);
}

.product-basic-subtitle {
    margin: 0.25rem 0 0;
    color: var(--color-text-muted);
    font-size: 1rem;
}

.product-card {
    border: 1px solid color-mix(in srgb, var(--color-border) 90%, transparent);
    border-radius: 0.9rem;
    background: color-mix(in srgb, var(--color-bg-surface) 92%, #070b12);
    padding: 1rem;
}

.product-card h2 {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--color-text);
}

.product-card > p {
    margin: 0.25rem 0 1rem;
    font-size: 0.85rem;
    color: var(--color-text-muted);
}

.card-section-header {
    display: flex;
    align-items: flex-start;
    gap: 0.8rem;
    margin-bottom: 1rem;
}

.card-section-icon {
    width: 2.6rem;
    height: 2.6rem;
    border-radius: 0.8rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid color-mix(in srgb, var(--color-border) 85%, transparent);
    background: color-mix(in srgb, var(--color-bg-surface) 80%, #1b2231);
    color: var(--color-text);
    flex-shrink: 0;
}

.card-section-header h3 {
    margin: 0;
    font-size: 1.85rem;
    font-weight: 800;
    color: var(--color-text);
}

.card-section-header p {
    margin: 0.2rem 0 0;
    color: var(--color-text-muted);
}

.field-caption {
    margin: 0.28rem 0 0;
    font-size: 0.8rem;
    color: var(--color-text-muted);
}

.select-action-row {
    display: grid;
    grid-template-columns: 1fr auto auto;
    gap: 0.5rem;
    align-items: stretch;
}

.icon-action-btn {
    border: 1px solid color-mix(in srgb, var(--color-border) 85%, transparent);
    background: color-mix(in srgb, var(--color-bg-surface) 84%, transparent);
    color: var(--color-text);
    border-radius: 0.6rem;
    width: 2.6rem;
    height: 2.6rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all var(--transition-fast);
}

.icon-action-btn:hover {
    border-color: color-mix(in srgb, var(--color-primary) 42%, transparent);
    background: color-mix(in srgb, var(--color-primary) 12%, var(--color-bg-surface));
}

.link-action-btn {
    border: 1px solid color-mix(in srgb, var(--color-primary) 52%, transparent);
    background: color-mix(in srgb, var(--color-primary) 16%, var(--color-bg-surface));
    color: var(--color-primary);
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    white-space: nowrap;
    min-height: 2.6rem;
    padding: 0 0.92rem;
}

.link-action-btn:hover {
    background: color-mix(in srgb, var(--color-primary) 26%, var(--color-bg-surface));
    border-color: color-mix(in srgb, var(--color-primary) 70%, transparent);
}

.ean-group {
    display: grid;
    grid-template-columns: minmax(6.4rem, 7rem) minmax(0, 1fr);
    gap: 0.5rem;
}

.field-with-icon {
    position: relative;
}

.field-with-icon .ui-field {
    padding-right: 2.5rem;
}

.input-icon-btn {
    position: absolute;
    right: 0.5rem;
    top: 50%;
    transform: translateY(-50%);
    border: 1px solid color-mix(in srgb, var(--color-border) 82%, transparent);
    border-radius: 0.5rem;
    width: 1.85rem;
    height: 1.85rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--color-text-muted);
    background: color-mix(in srgb, var(--color-bg-surface) 92%, #131827);
}

.product-fiscal-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
    align-items: start;
}

.product-fiscal-short-input {
    min-width: 0;
}

.product-fiscal-short-field :deep(.ui-field) {
    min-width: 0;
}

.parameter-quick-modal-error {
    margin: 0.85rem 0 0;
    color: var(--color-danger);
    font-size: 0.84rem;
    font-weight: 700;
}

@media (min-width: 1024px) {
    .product-fiscal-grid {
        grid-template-columns: minmax(22rem, 2.3fr) minmax(12rem, 0.95fr) minmax(7rem, 0.55fr) minmax(9.5rem, 0.75fr);
    }
}

.note-banner {
    margin-top: 1rem;
    border: 1px solid color-mix(in srgb, var(--color-border) 80%, transparent);
    border-radius: 0.8rem;
    padding: 0.8rem 0.9rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.8rem;
    background: color-mix(in srgb, var(--color-bg-surface) 72%, #1d2230);
}

.note-banner-text {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--color-text-muted);
    font-size: 0.9rem;
}

.optional-inner-card {
    margin-top: 1rem;
    background: color-mix(in srgb, var(--color-bg-surface) 88%, #060a11);
}

.optional-level-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.75rem;
}

.optional-level-box {
    border: 1px solid color-mix(in srgb, var(--color-border) 88%, transparent);
    border-radius: 0.75rem;
    padding: 0.75rem;
    background: color-mix(in srgb, var(--color-bg-surface) 94%, #0f1520);
}

.optional-level-action {
    display: flex;
    justify-content: flex-end;
    margin-top: 0.5rem;
}

.add-level-btn {
    border: 1px solid color-mix(in srgb, var(--color-border) 84%, transparent);
    border-radius: 0.65rem;
    background: color-mix(in srgb, var(--color-bg-surface) 84%, #151c2a);
    color: var(--color-text);
    font-weight: 700;
    padding: 0.52rem 0.85rem;
    display: inline-flex;
    gap: 0.42rem;
    align-items: center;
}

.counter-row {
    display: flex;
    justify-content: space-between;
    gap: 0.8rem;
    align-items: center;
}

.combobox-row {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 0.5rem;
    align-items: center;
}

.combobox-link-btn {
    border: 1px solid color-mix(in srgb, var(--color-border) 82%, transparent);
    border-radius: 0.65rem;
    background: color-mix(in srgb, var(--color-bg-surface) 86%, #151c28);
    color: var(--color-text);
    font-weight: 700;
    padding: 0.52rem 0.95rem;
    white-space: nowrap;
}

.barcode-top-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.6rem;
    margin-bottom: 0.9rem;
}

.barcode-grid-shell {
    border: 1px solid color-mix(in srgb, var(--color-border) 90%, transparent);
    border-radius: 0.8rem;
    padding: 0.85rem;
}

.barcode-grid-controls {
    display: grid;
    grid-template-columns: auto 1fr auto;
    gap: 0.75rem;
    align-items: center;
    margin-bottom: 0.75rem;
}

.barcode-columns-select {
    min-width: 8.5rem;
}

.barcode-search-wrap {
    position: relative;
}

.barcode-search-wrap .ui-field {
    padding-right: 2.2rem;
}

.barcode-search-icon {
    position: absolute;
    right: 0.65rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--color-text-muted);
}

.barcode-pages-wrap {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--color-text-muted);
    font-size: 0.9rem;
}

.barcode-page-select {
    width: 5.4rem;
}

.barcode-nav-btns {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.nav-icon-btn {
    border: 1px solid color-mix(in srgb, var(--color-border) 82%, transparent);
    border-radius: 0.45rem;
    width: 2rem;
    height: 2rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: color-mix(in srgb, var(--color-bg-surface) 86%, #0f1522);
    color: var(--color-text-muted);
}

.barcode-grid-footer {
    margin: 0.75rem 0 0;
    font-size: 0.88rem;
    color: var(--color-text-muted);
}

.composition-grid-link-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.9rem;
    flex-wrap: wrap;
    margin-bottom: 0.85rem;
    border: 1px solid color-mix(in srgb, var(--color-primary) 36%, var(--color-border));
    border-radius: 0.85rem;
    padding: 0.8rem 0.95rem;
    background:
        radial-gradient(circle at top left, color-mix(in srgb, var(--color-primary) 18%, transparent), transparent 42%),
        color-mix(in srgb, var(--color-primary) 8%, var(--color-bg-surface));
    color: var(--color-text);
    box-shadow: 0 12px 28px color-mix(in srgb, var(--color-primary) 10%, transparent);
}

.composition-grid-link-banner div {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.composition-grid-link-banner strong {
    font-weight: 850;
}

.composition-grid-link-banner span {
    color: var(--color-primary);
    font-weight: 850;
}

.composition-grid-link-banner p {
    margin: 0;
    color: var(--color-text-muted);
    font-size: 0.88rem;
}

.composition-grid-link-banner button {
    border: 1px solid color-mix(in srgb, var(--color-border) 90%, transparent);
    border-radius: 0.6rem;
    min-height: 2.15rem;
    padding: 0 0.8rem;
    background: var(--color-bg-surface);
    color: var(--color-text);
    font-weight: 800;
    cursor: pointer;
}

.composition-grid-drag-ghost {
    position: fixed;
    z-index: 80;
    pointer-events: none;
    border: 1px solid color-mix(in srgb, var(--color-primary) 40%, var(--color-border));
    border-radius: 999px;
    padding: 0.35rem 0.65rem;
    background: color-mix(in srgb, var(--color-bg-elevated) 95%, #ffffff);
    color: var(--color-text-muted);
    font-size: 0.78rem;
    font-weight: 850;
    box-shadow: 0 12px 28px color-mix(in srgb, #000 18%, transparent);
}

.composition-grid-drag-ghost.has-target {
    border-color: color-mix(in srgb, #22c55e 70%, var(--color-border));
    color: #15803d;
}

.composition-current-row td {
    background: color-mix(in srgb, var(--branch-color, var(--color-primary)) 13%, var(--color-bg-surface));
    border-bottom: 1px solid color-mix(in srgb, var(--branch-color, var(--color-primary)) 35%, var(--color-border));
    transition: background 150ms ease, box-shadow 150ms ease;
}

.composition-current-row td:first-child,
.composition-connected-row td:first-child {
    border-left: 3px solid color-mix(in srgb, var(--branch-color, var(--color-primary)) 68%, var(--color-border));
}

.composition-current-row__content {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    flex-wrap: wrap;
}

.composition-current-row__badge {
    display: inline-flex;
    align-items: center;
    border: 1px solid color-mix(in srgb, var(--color-primary) 58%, transparent);
    background: color-mix(in srgb, var(--color-primary) 22%, transparent);
    color: var(--color-primary);
    border-radius: 999px;
    font-size: 0.76rem;
    font-weight: 800;
    letter-spacing: 0.01em;
    padding: 0.18rem 0.55rem;
}

.composition-current-row__name {
    color: var(--color-text);
    font-weight: 700;
}

.composition-connected-row td {
    background: color-mix(in srgb, var(--branch-color, var(--color-primary)) 4%, var(--color-bg-surface));
    transition: background 150ms ease, box-shadow 150ms ease;
}

.composition-connected-row:hover td {
    background: color-mix(in srgb, var(--branch-color, var(--color-primary)) 9%, var(--color-bg-surface));
}

.composition-connected-row.has-grid-parent td {
    background: color-mix(in srgb, var(--branch-color, var(--color-primary)) 7%, var(--color-bg-surface));
}

.composition-current-row.is-grid-link-parent td,
.composition-connected-row.is-grid-link-parent td {
    background: color-mix(in srgb, var(--branch-color, var(--color-primary)) 18%, var(--color-bg-surface));
    box-shadow: inset 0 0 0 2px color-mix(in srgb, var(--branch-color, var(--color-primary)) 72%, transparent);
}

.composition-current-row.is-grid-link-target td,
.composition-connected-row.is-grid-link-target td,
.composition-current-row.is-grid-link-target:hover td,
.composition-connected-row.is-grid-link-target:hover td {
    background: color-mix(in srgb, #22c55e 13%, var(--color-bg-surface));
    box-shadow: inset 0 0 0 2px color-mix(in srgb, #22c55e 55%, transparent);
    cursor: crosshair;
}

.composition-product-cell {
    display: inline-flex;
    align-items: flex-start;
    gap: 0.55rem;
    min-height: 2rem;
}

.composition-tree-prefix {
    display: inline-flex;
    align-items: stretch;
    position: absolute;
    left: 0.72rem;
    top: 0;
    bottom: 0;
    z-index: 1;
    height: auto;
    min-height: 100%;
    pointer-events: none;
}

.composition-tree-segment,
.composition-tree-elbow {
    position: relative;
    display: inline-block;
    width: 1.05rem;
    min-width: 1.05rem;
    height: 100%;
}

.composition-tree-segment::before {
    content: '';
    position: absolute;
    left: 0.48rem;
    top: 0;
    bottom: 0;
    width: 2px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--branch-color, var(--color-primary)) 42%, var(--color-border));
}

.composition-tree-segment.is-empty::before {
    display: none;
}

.composition-tree-elbow {
    width: 1.35rem;
    min-width: 1.35rem;
}

.composition-tree-elbow::before {
    content: '';
    position: absolute;
    left: 0.48rem;
    top: 0;
    bottom: 0;
    width: 2px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--branch-color, var(--color-primary)) 54%, var(--color-border));
}

.composition-tree-elbow::after {
    content: '';
    position: absolute;
    left: 0.48rem;
    top: 50%;
    width: 0.95rem;
    height: 2px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--branch-color, var(--color-primary)) 68%, var(--color-border));
}

.composition-tree-elbow.is-last::before {
    bottom: 50%;
}

.composition-product-stack {
    display: inline-flex;
    flex-direction: column;
    gap: 0.18rem;
}

.composition-product-stack > span {
    font-weight: 650;
}

.composition-product-stack small {
    display: inline-flex;
    align-items: center;
    width: max-content;
    max-width: 42rem;
    border: 1px solid color-mix(in srgb, var(--branch-color, var(--color-primary)) 24%, transparent);
    border-radius: 999px;
    padding: 0.12rem 0.48rem;
    background: color-mix(in srgb, var(--branch-color, var(--color-primary)) 10%, transparent);
    color: color-mix(in srgb, var(--branch-color, var(--color-primary)) 62%, var(--color-text));
    font-size: 0.72rem;
    font-weight: 800;
    line-height: 1.2;
}

.composition-actions-cell {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.composition-actions-menu {
    position: relative;
}

.composition-actions-trigger {
    list-style: none;
    border: 1px solid color-mix(in srgb, var(--color-border) 86%, transparent);
    border-radius: 0.6rem;
    width: 2.35rem;
    height: 2.35rem;
    background: color-mix(in srgb, var(--color-bg-surface) 90%, transparent);
    color: var(--color-text);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all var(--transition-fast);
}

.composition-actions-trigger::-webkit-details-marker {
    display: none;
}

.composition-actions-trigger:hover {
    border-color: color-mix(in srgb, var(--color-primary) 44%, transparent);
    background: color-mix(in srgb, var(--color-primary) 12%, var(--color-bg-surface));
}

.composition-actions-menu[open] .composition-actions-trigger {
    border-color: color-mix(in srgb, var(--color-primary) 56%, transparent);
}

.composition-actions-dropdown {
    position: absolute;
    z-index: 20;
    top: calc(100% + 0.35rem);
    left: 0;
    min-width: 9rem;
    border: 1px solid color-mix(in srgb, var(--color-border) 86%, transparent);
    border-radius: 0.65rem;
    padding: 0.32rem;
    background: color-mix(in srgb, var(--color-bg-surface) 96%, #090f18);
    box-shadow: var(--shadow-md);
    display: grid;
    gap: 0.2rem;
}

.composition-actions-option {
    border: 1px solid transparent;
    border-radius: 0.45rem;
    background: transparent;
    color: var(--color-text);
    text-align: left;
    font-weight: 700;
    padding: 0.45rem 0.58rem;
    cursor: pointer;
}

.composition-actions-option:hover {
    border-color: color-mix(in srgb, var(--color-primary) 44%, transparent);
    background: color-mix(in srgb, var(--color-primary) 12%, var(--color-bg-surface));
}

.composition-actions-option.is-danger {
    color: var(--color-danger);
}

.composition-actions-option.is-danger:hover {
    border-color: color-mix(in srgb, var(--color-danger) 40%, transparent);
    background: color-mix(in srgb, var(--color-danger) 14%, var(--color-bg-surface));
}

.composition-flow-heading,
.composition-root-cell,
.composition-flow-cell {
    width: 6.7rem;
    min-width: 6.7rem;
    max-width: 6.7rem;
    padding-left: 0.9rem !important;
    padding-right: 0 !important;
}

.composition-root-cell,
.composition-flow-cell {
    position: relative;
    overflow: hidden;
    vertical-align: middle;
}

.composition-root-cell::after {
    content: '';
    position: absolute;
    left: 1.45rem;
    top: 50%;
    bottom: 0;
    width: 2px;
    background: linear-gradient(
        to bottom,
        color-mix(in srgb, var(--branch-color, var(--color-primary)) 58%, var(--color-border)),
        color-mix(in srgb, var(--branch-color, var(--color-primary)) 22%, var(--color-border))
    );
}

.composition-root-node {
    position: absolute;
    left: 1.04rem;
    top: 50%;
    transform: translateY(-50%);
    z-index: 1;
    display: block;
    width: 0.82rem;
    height: 0.82rem;
    border-radius: 999px;
    background: var(--branch-color, var(--color-primary));
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--branch-color, var(--color-primary)) 22%, transparent);
}

.composition-flow-cell::before,
.composition-flow-cell::after {
    content: '';
    position: absolute;
    pointer-events: none;
    opacity: 0;
}

.composition-flow-cell.has-child-branch::before {
    opacity: 1;
    left: calc(2.24rem + ((var(--composition-depth, 1) - 1) * 1.05rem));
    top: 50%;
    bottom: -1px;
    width: 2px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--branch-color, var(--color-primary)) 56%, var(--color-border));
}

.composition-flow-cell.has-child-branch::after {
    opacity: 1;
    left: calc(1.19rem + ((var(--composition-depth, 1) - 1) * 1.05rem));
    top: calc(50% - 1px);
    width: 1.05rem;
    height: 2px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--branch-color, var(--color-primary)) 64%, var(--color-border));
}

.composition-flow-node {
    position: absolute;
    left: calc(0.9rem + ((var(--composition-depth, 1) - 1) * 1.05rem));
    top: 50%;
    transform: translateY(-50%);
    z-index: 2;
    display: block;
    width: 0.58rem;
    height: 0.58rem;
    border-radius: 999px;
    border: 2px solid color-mix(in srgb, var(--branch-color, var(--color-primary)) 76%, #ffffff);
    background: var(--color-bg-surface);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--branch-color, var(--color-primary)) 14%, transparent);
}

.composition-connected-row.has-grid-children .composition-flow-node {
    background: var(--branch-color, var(--color-primary));
    box-shadow:
        0 0 0 3px color-mix(in srgb, var(--branch-color, var(--color-primary)) 16%, transparent),
        0 0 0 7px color-mix(in srgb, var(--branch-color, var(--color-primary)) 7%, transparent);
}

.composition-grid-child-count {
    position: absolute;
    z-index: 2;
    right: 0.18rem;
    top: 50%;
    transform: translateY(-50%);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.05rem;
    height: 1.05rem;
    border-radius: 999px;
    border: 1px solid color-mix(in srgb, var(--branch-color, var(--color-primary)) 62%, #ffffff);
    background: var(--color-bg-surface);
    color: var(--branch-color, var(--color-primary));
    font-size: 0.63rem;
    font-weight: 900;
    line-height: 1;
}

.composition-grid-parent-chip {
    display: inline-flex;
    align-items: center;
    border: 1px solid color-mix(in srgb, var(--branch-color, var(--color-primary)) 30%, transparent);
    border-radius: 999px;
    padding: 0.14rem 0.5rem;
    background: color-mix(in srgb, var(--branch-color, var(--color-primary)) 9%, transparent);
    color: color-mix(in srgb, var(--branch-color, var(--color-primary)) 68%, var(--color-text));
    font-size: 0.74rem;
    font-weight: 850;
}

.composition-connected-row.is-first-sequence .composition-flow-cell::before {
    top: 50%;
}

.composition-connected-row.is-last-sequence .composition-flow-cell::before {
    bottom: 50%;
}

.composition-order-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2.2rem;
    border-radius: 999px;
    border: 1px solid color-mix(in srgb, var(--branch-color, var(--color-primary)) 45%, transparent);
    background: color-mix(in srgb, var(--branch-color, var(--color-primary)) 18%, transparent);
    color: var(--branch-color, var(--color-primary));
    font-weight: 800;
    font-size: 0.76rem;
    padding: 0.18rem 0.5rem;
}

.composition-order-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2.05rem;
    border-radius: 999px;
    border: 1px solid color-mix(in srgb, var(--branch-color, var(--color-primary)) 40%, transparent);
    background: color-mix(in srgb, var(--branch-color, var(--color-primary)) 16%, transparent);
    color: var(--branch-color, var(--color-primary));
    font-weight: 800;
    font-size: 0.78rem;
    padding: 0.14rem 0.48rem;
}

.composition-cost-summary {
    margin-top: 1rem;
    border: 1px solid color-mix(in srgb, var(--color-border) 88%, transparent);
    border-radius: 0.8rem;
    padding: 0.95rem;
    background:
        radial-gradient(circle at top right, color-mix(in srgb, var(--color-primary) 10%, transparent), transparent 42%),
        color-mix(in srgb, var(--color-bg-surface) 95%, #05070d);
}

.composition-cost-summary__header h3 {
    margin: 0;
    color: var(--color-text);
    font-size: 1rem;
    font-weight: 850;
}

.composition-cost-summary__header p {
    margin: 0.24rem 0 0;
    color: var(--color-text-muted);
    font-size: 0.86rem;
}

.composition-cost-summary__grid {
    margin-top: 0.82rem;
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.55rem;
}

.composition-cost-card {
    border: 1px solid color-mix(in srgb, var(--color-border) 86%, transparent);
    border-radius: 0.7rem;
    padding: 0.6rem 0.7rem;
    background: color-mix(in srgb, var(--color-bg-app) 84%, #050910);
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.composition-cost-card span {
    color: var(--color-text-muted);
    font-size: 0.77rem;
    font-weight: 650;
}

.composition-cost-card strong {
    color: var(--color-text);
    font-size: 1rem;
    font-weight: 850;
    line-height: 1.2;
}

.composition-pricing-box {
    margin-top: 0.82rem;
    border: 1px solid color-mix(in srgb, var(--color-border) 86%, transparent);
    border-radius: 0.72rem;
    padding: 0.7rem;
    background: color-mix(in srgb, var(--color-bg-elevated) 78%, #04070d);
}

.composition-pricing-box__inputs {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.65rem;
}

.composition-pricing-box__metrics {
    margin-top: 0.65rem;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.4rem 0.7rem;
    color: var(--color-text-muted);
    font-size: 0.81rem;
}

.composition-pricing-box__metrics strong {
    color: var(--color-text);
    font-weight: 850;
}

.composition-org-shell {
    position: relative;
    border: 1px solid color-mix(in srgb, var(--color-border) 90%, transparent);
    border-radius: 0.8rem;
    padding: 1rem;
    background: color-mix(in srgb, var(--color-bg-surface) 96%, #060a11);
}

.composition-org-blur-layer {
    transition: filter 160ms ease, opacity 160ms ease;
}

.composition-org-shell.has-open-drawer .composition-org-blur-layer {
    filter: blur(3px);
    opacity: 0.42;
    pointer-events: none;
}

.composition-drawer-backdrop {
    position: fixed;
    inset: 0;
    z-index: 55;
    border: 0;
    background: color-mix(in srgb, #000 62%, transparent);
    backdrop-filter: blur(5px);
    cursor: default;
}

.composition-org-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 0.85rem;
}

.composition-org-header h3 {
    margin: 0;
    color: var(--color-text);
    font-size: 1.08rem;
    font-weight: 800;
}

.composition-org-header p {
    margin: 0.18rem 0 0;
    color: var(--color-text-muted);
    font-size: 0.9rem;
}

.composition-org-toolbar {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-bottom: 0.65rem;
}

.composition-org-tool {
    border: 1px solid color-mix(in srgb, var(--color-border) 86%, transparent);
    border-radius: 0.5rem;
    min-height: 2.25rem;
    padding: 0 0.78rem;
    background: color-mix(in srgb, var(--color-bg-surface) 88%, #101622);
    color: var(--color-text);
    font-weight: 700;
    cursor: pointer;
}

.composition-org-tool:hover {
    border-color: color-mix(in srgb, var(--color-primary) 44%, var(--color-border));
    background: color-mix(in srgb, var(--color-primary) 9%, var(--color-bg-surface));
}

.composition-org-zoom {
    color: var(--color-text-muted);
    font-size: 0.85rem;
    font-weight: 700;
    padding-left: 0.25rem;
}

.composition-org-path {
    border: 1px solid color-mix(in srgb, var(--color-border) 86%, transparent);
    border-radius: 0.52rem;
    min-height: 2.45rem;
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
    padding: 0.48rem 0.7rem;
    color: var(--color-text-muted);
    margin-bottom: 0.85rem;
}

.composition-org-path strong {
    color: var(--color-text);
}

.composition-org-pending {
    color: var(--color-primary);
    font-weight: 800;
    margin-left: auto;
}

.composition-org-canvas {
    position: relative;
    height: 31rem;
    overflow: hidden;
    border: 1px solid color-mix(in srgb, var(--color-border) 84%, transparent);
    border-radius: 0.78rem;
    background:
        radial-gradient(circle, color-mix(in srgb, var(--color-text-muted) 32%, transparent) 1px, transparent 1px) 0 0 / 24px 24px,
        color-mix(in srgb, var(--color-bg-app) 82%, #05070c);
    cursor: grab;
    touch-action: none;
}

.composition-org-canvas:active {
    cursor: grabbing;
}

.composition-org-stage {
    position: absolute;
    inset: 0;
    width: 1400px;
    height: 900px;
    transform-origin: 0 0;
}

.composition-org-edges {
    position: absolute;
    inset: 0;
    width: 1400px;
    height: 900px;
    overflow: visible;
    pointer-events: none;
}

.composition-org-edge-halo,
.composition-org-edge {
    fill: none;
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-dasharray: 0;
}

.composition-org-edge-halo {
    stroke: color-mix(in srgb, var(--color-bg-surface) 70%, #ffffff);
    stroke-width: 7;
    opacity: 0.9;
}

.composition-org-edge {
    stroke: var(--edge-color, var(--color-primary));
    stroke-width: 3.5;
    opacity: 0.94;
    filter: drop-shadow(0 1px 1px color-mix(in srgb, #000 22%, transparent))
        drop-shadow(0 0 5px color-mix(in srgb, var(--edge-color, var(--color-primary)) 22%, transparent));
}

.composition-org-edge.is-draft {
    stroke-width: 4;
    stroke-dasharray: 10 8;
    opacity: 1;
}

.composition-org-edge-halo.is-draft {
    stroke-width: 8;
    opacity: 0.82;
}

.composition-org-node {
    position: absolute;
    width: 300px;
    height: 150px;
    overflow: hidden;
    border: 2px solid color-mix(in srgb, var(--node-color, var(--color-border)) 62%, var(--color-border));
    border-radius: 0.7rem;
    background: var(--color-bg-surface);
    color: var(--color-text);
    padding: 0.82rem 0.92rem 0.72rem;
    box-shadow:
        0 0 0 4px color-mix(in srgb, var(--node-color, var(--color-primary)) 10%, transparent),
        0 14px 30px color-mix(in srgb, #000 24%, transparent);
    cursor: grab;
    user-select: none;
}

.composition-org-node:hover,
.composition-org-node.is-selected {
    border-color: var(--node-color, var(--color-primary));
    box-shadow:
        0 0 0 4px color-mix(in srgb, var(--node-color, var(--color-primary)) 18%, transparent),
        0 18px 34px color-mix(in srgb, #000 34%, transparent);
}

.composition-org-node.is-multi-selected {
    border-color: var(--node-color, var(--color-primary));
    box-shadow:
        0 0 0 5px color-mix(in srgb, var(--node-color, var(--color-primary)) 22%, transparent),
        0 18px 34px color-mix(in srgb, #000 30%, transparent);
}

.composition-org-shell.has-open-drawer .composition-org-node.is-selected {
    z-index: 58;
    filter: none;
    opacity: 1;
    box-shadow:
        0 0 0 5px color-mix(in srgb, var(--node-color, var(--color-primary)) 38%, transparent),
        0 24px 48px color-mix(in srgb, #000 54%, transparent);
}

.composition-node-spotlight {
    position: fixed;
    left: clamp(1rem, 8vw, 7rem);
    top: 50%;
    transform: translateY(-50%);
    z-index: 65;
    width: min(23rem, calc(100vw - 32rem));
    min-width: 18rem;
    border: 2px solid var(--node-color, var(--color-primary));
    border-radius: 0.78rem;
    background: var(--color-bg-surface);
    color: var(--color-text);
    padding: 1rem;
    box-shadow:
        0 0 0 5px color-mix(in srgb, var(--node-color, var(--color-primary)) 22%, transparent),
        0 24px 60px color-mix(in srgb, #000 52%, transparent);
}

.composition-node-spotlight__eyebrow {
    display: inline-flex;
    margin-bottom: 0.55rem;
    border: 1px solid color-mix(in srgb, var(--node-color, var(--color-primary)) 42%, var(--color-border));
    border-radius: 999px;
    color: var(--node-color, var(--color-primary));
    font-size: 0.72rem;
    font-weight: 850;
    padding: 0.14rem 0.52rem;
}

.composition-node-spotlight h4 {
    margin: 0 0 0.55rem;
    color: var(--color-text);
    font-size: 1rem;
    font-weight: 850;
    line-height: 1.28;
}

.composition-node-spotlight p {
    margin: 0.22rem 0;
    color: var(--color-text-muted);
    font-size: 0.82rem;
}

.composition-node-spotlight strong {
    color: var(--color-text);
}

@media (max-width: 900px) {
    .composition-node-spotlight {
        display: none;
    }
}

.composition-org-node.is-root {
    border-color: var(--node-color, var(--color-primary));
    background: color-mix(in srgb, var(--node-color, var(--color-primary)) 7%, var(--color-bg-surface));
}

.composition-org-node.is-pending-parent {
    border-color: color-mix(in srgb, var(--color-success) 70%, var(--color-border));
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-success) 22%, transparent);
}

.composition-org-node.is-connect-target {
    border-color: var(--color-success);
    box-shadow:
        0 0 0 5px color-mix(in srgb, var(--color-success) 26%, transparent),
        0 18px 34px color-mix(in srgb, #000 34%, transparent);
}

.composition-org-node h4 {
    margin: 0 1.2rem 0.42rem 0;
    font-size: 0.9rem;
    line-height: 1.22;
    font-weight: 850;
    color: var(--color-text);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.composition-org-node p {
    margin: 0.12rem 0;
    color: var(--color-text-muted);
    font-size: 0.73rem;
    line-height: 1.22;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.composition-org-node p strong {
    color: var(--color-text);
}

.composition-org-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.28rem;
    margin-bottom: 0.35rem;
}

.composition-org-tags span {
    border: 1px solid color-mix(in srgb, var(--color-border) 86%, transparent);
    border-radius: 999px;
    color: var(--color-text);
    font-size: 0.68rem;
    font-weight: 800;
    padding: 0.08rem 0.42rem;
    background: color-mix(in srgb, var(--color-bg-elevated) 70%, transparent);
}

.composition-org-connect-btn {
    position: absolute;
    left: 50%;
    top: 0.32rem;
    transform: translateX(-50%);
    border: 1px solid color-mix(in srgb, var(--color-primary) 60%, var(--color-border));
    border-radius: 999px;
    width: 1.25rem;
    height: 1.25rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: color-mix(in srgb, var(--color-bg-surface) 92%, #070b12);
    color: var(--color-primary);
    font-weight: 900;
    line-height: 1;
    cursor: pointer;
}

.composition-org-connect-btn:hover {
    background: var(--color-primary);
    color: #ffffff;
}

.composition-org-selection-box {
    position: absolute;
    z-index: 12;
    border: 1px solid color-mix(in srgb, var(--color-primary) 75%, #ffffff);
    border-radius: 0.35rem;
    background: color-mix(in srgb, var(--color-primary) 14%, transparent);
    box-shadow: inset 0 0 0 1px color-mix(in srgb, #ffffff 28%, transparent);
    pointer-events: none;
}

.composition-org-hover {
    position: absolute;
    z-index: 6;
    width: 360px;
    border: 1px solid color-mix(in srgb, var(--color-border) 86%, transparent);
    border-radius: 0.7rem;
    background: color-mix(in srgb, var(--color-bg-elevated) 94%, #05070c);
    padding: 0.85rem 0.95rem;
    box-shadow: 0 18px 40px color-mix(in srgb, #000 36%, transparent);
    pointer-events: none;
}

.composition-org-hover h4 {
    margin: 0 0 0.65rem;
    font-size: 1rem;
    color: var(--color-text);
    font-weight: 850;
}

.composition-org-hover-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.42rem 0.8rem;
    color: var(--color-text-muted);
    font-size: 0.82rem;
}

.composition-org-hover-grid strong,
.composition-org-hover p {
    color: var(--color-text);
}

.composition-org-hover p {
    margin: 0.75rem 0 0;
    font-size: 0.8rem;
}

.composition-node-drawer {
    position: fixed;
    right: 0;
    top: 0;
    bottom: 0;
    z-index: 70;
    width: min(28rem, 92vw);
    border-left: 1px solid color-mix(in srgb, var(--color-border) 86%, transparent);
    background: color-mix(in srgb, var(--color-bg-elevated) 96%, #05070c);
    box-shadow: -22px 0 50px color-mix(in srgb, #000 42%, transparent);
    padding: 1.25rem;
    overflow-y: auto;
}

.composition-node-drawer__close {
    position: absolute;
    top: 0.85rem;
    right: 0.85rem;
    border: 0;
    background: transparent;
    color: var(--color-text-muted);
    font-size: 1.4rem;
    cursor: pointer;
}

.composition-node-drawer h3 {
    margin: 0 2rem 0.45rem 0;
    color: var(--color-text);
    font-size: 1.25rem;
    font-weight: 850;
}

.composition-node-drawer > p {
    margin: 0 0 1rem;
    color: var(--color-text-muted);
}

.composition-node-detail-card {
    border: 1px solid color-mix(in srgb, var(--color-border) 86%, transparent);
    border-radius: 0.72rem;
    background: color-mix(in srgb, var(--color-bg-surface) 78%, transparent);
    padding: 0.85rem;
    margin-bottom: 0.8rem;
}

.composition-node-detail-title {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.6rem;
    margin-bottom: 0.6rem;
}

.composition-node-detail-title h4,
.composition-node-detail-card h4 {
    margin: 0;
    color: var(--color-text);
    font-size: 1rem;
    font-weight: 850;
}

.composition-node-detail-title span {
    border: 1px solid color-mix(in srgb, var(--color-border) 86%, transparent);
    border-radius: 999px;
    padding: 0.14rem 0.55rem;
    color: var(--color-text);
    font-size: 0.75rem;
    font-weight: 800;
    white-space: nowrap;
}

.composition-node-detail-card p {
    margin: 0.36rem 0;
    color: var(--color-text-muted);
}

.composition-node-detail-card strong {
    color: var(--color-text);
}

.barcode-modal-grid {
    display: grid;
    grid-template-columns: repeat(12, minmax(0, 1fr));
    align-items: start;
    gap: 0.9rem;
}

.barcode-modal-primary {
    grid-column: span 5;
}

.barcode-modal-info-field {
    grid-column: span 4;
}

.barcode-modal-status-field {
    grid-column: span 3;
}

.barcode-modal-sku-field {
    grid-column: span 7;
}

.barcode-ean-group {
    grid-template-columns: minmax(5.5rem, 6rem) minmax(0, 1fr);
}

.barcode-modal-compact-field :deep(.ui-field) {
    min-height: 2.35rem;
    padding-top: 0.48rem;
    padding-bottom: 0.48rem;
}

.barcode-modal-actions {
    margin-top: 1rem;
    display: flex;
    justify-content: flex-end;
    gap: 0.55rem;
}

.composition-modal-product-grid :deep(.ui-field) {
    min-height: 2.55rem;
    height: 2.55rem;
}

.info-adicional-subtabs {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    border-bottom: 1px solid color-mix(in srgb, var(--color-border) 84%, transparent);
    padding-bottom: 0.2rem;
}

.info-subtab-btn {
    border: none;
    border-bottom: 2px solid transparent;
    background: transparent;
    color: var(--color-text-muted);
    font-size: 1rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.56rem 0.85rem;
}

.info-subtab-btn.is-active {
    color: var(--color-text);
    border-bottom-color: color-mix(in srgb, var(--color-primary) 85%, white 8%);
}

.composition-view-mode {
    border: 1px solid color-mix(in srgb, var(--color-border) 86%, transparent);
    border-radius: 0.75rem;
    padding: 0.2rem;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.mode-btn {
    border: none;
    border-radius: 0.58rem;
    padding: 0.42rem 0.9rem;
    background: transparent;
    color: var(--color-text-muted);
    font-weight: 700;
}

.mode-btn.is-active {
    background: color-mix(in srgb, var(--color-primary) 20%, #111826);
    color: var(--color-text);
}

.foto-add-btn {
    margin-left: auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 3rem;
    height: 3rem;
    border-radius: 0.8rem;
    border: 1px solid color-mix(in srgb, var(--color-border) 84%, transparent);
    background: color-mix(in srgb, var(--color-primary) 18%, #171e2d);
    color: var(--color-text);
}

.foto-grid {
    margin-top: 0.8rem;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
}

.foto-slot-card {
    border: 1px solid color-mix(in srgb, var(--color-border) 90%, transparent);
    border-radius: 0.8rem;
    padding: 0.8rem;
    background: color-mix(in srgb, var(--color-bg-surface) 92%, #0d1320);
}

.foto-drop-zone {
    width: 100%;
    height: clamp(14rem, 32vw, 20rem);
    border: 1px dashed color-mix(in srgb, var(--color-border) 76%, transparent);
    border-radius: 0.7rem;
    background: color-mix(in srgb, var(--color-bg-surface) 88%, #0a0f18);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.foto-drop-placeholder {
    color: var(--color-text-muted);
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    gap: 0.45rem;
}

.foto-preview {
    width: 100%;
    height: 100%;
    object-fit: contain;
    display: block;
}

.foto-file-name {
    margin: 0.55rem 0 0;
    color: var(--color-text-muted);
}

.foto-actions {
    margin-top: 0.55rem;
    display: inline-flex;
    gap: 0.45rem;
}

.photo-modal-content {
    display: grid;
    gap: 0.85rem;
}

.photo-modal-actions-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.6rem;
}

.photo-modal-option-btn {
    border: 1px solid color-mix(in srgb, var(--color-primary) 52%, var(--color-border));
    border-radius: 0.72rem;
    background: color-mix(in srgb, var(--color-primary) 14%, var(--color-bg-surface));
    color: var(--color-primary);
    min-height: 2.85rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    font-weight: 800;
    cursor: pointer;
}

.photo-modal-link-block {
    border-top: 1px solid color-mix(in srgb, var(--color-border) 84%, transparent);
    padding-top: 0.2rem;
}

.stock-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.65rem;
}

.stock-kpi-card {
    border: 1px solid color-mix(in srgb, var(--color-border) 84%, transparent);
    border-radius: 0.76rem;
    background: color-mix(in srgb, var(--color-bg-surface) 92%, #101522);
    padding: 0.62rem;
}

.stock-kpi-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.45rem;
    margin-bottom: 0.4rem;
}

.stock-kpi-label {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    color: var(--color-text);
    font-size: 0.82rem;
    font-weight: 700;
}

.stock-kpi-value {
    position: relative;
}

.stock-kpi-input {
    width: 100%;
    min-height: 2.25rem;
    padding-right: 2rem;
    padding-left: 2rem;
    font-weight: 700;
}

.stock-kpi-prefix,
.stock-kpi-suffix {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    color: var(--color-text-muted);
    font-size: 0.8rem;
    font-weight: 700;
    pointer-events: none;
}

.stock-kpi-prefix {
    left: 0.7rem;
}

.stock-kpi-suffix {
    right: 0.7rem;
}

.stock-reference-grid {
    margin-top: 0.75rem;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.65rem;
}

.stock-reference-card {
    border: 1px solid color-mix(in srgb, var(--color-border) 84%, transparent);
    border-radius: 0.76rem;
    background: color-mix(in srgb, var(--color-bg-surface) 95%, #0d121d);
    padding: 0.7rem 0.82rem;
}

.stock-reference-card h4 {
    margin: 0;
    color: var(--color-text);
    font-size: 0.88rem;
    font-weight: 800;
}

.stock-reference-card p {
    margin: 0.28rem 0 0;
    color: var(--color-text-muted);
    font-size: 0.84rem;
}

.stock-mini-field {
    display: grid;
    gap: 0.34rem;
}

.stock-mini-label {
    display: inline-flex;
    align-items: center;
    gap: 0.34rem;
    color: var(--color-text);
    font-size: 0.78rem;
    font-weight: 700;
}

.stock-mini-control {
    position: relative;
}

.stock-mini-leading-icon {
    position: absolute;
    top: 50%;
    left: 0.65rem;
    transform: translateY(-50%);
    color: var(--color-text-muted);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
}

.stock-mini-input {
    width: 100%;
}

.stock-mini-input--with-icon {
    padding-left: 2.05rem;
    padding-right: 3.25rem;
}

.stock-mini-suffix {
    position: absolute;
    top: 50%;
    right: 0.7rem;
    transform: translateY(-50%);
    color: var(--color-text-muted);
    font-size: 0.8rem;
    font-weight: 700;
}

@media (max-width: 640px) {
    .photo-modal-actions-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 1200px) {
    .stock-kpi-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 768px) {
    .stock-reference-grid {
        grid-template-columns: 1fr;
    }
}

.estoque-subtabs {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    overflow-x: auto;
    border-bottom: 1px solid color-mix(in srgb, var(--color-border) 84%, transparent);
    padding-bottom: 0.2rem;
}

.estoque-subtab-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    border: none;
    border-bottom: 2px solid transparent;
    background: transparent;
    color: var(--color-text-muted);
    font-size: 0.85rem;
    font-weight: 700;
    padding: 0.58rem 0.9rem;
    white-space: nowrap;
}

.estoque-subtab-btn.is-active {
    color: var(--color-text);
    border-bottom-color: color-mix(in srgb, var(--color-primary) 85%, white 8%);
}

.estoque-atributos-box {
    margin-top: 1rem;
    border: 1px solid color-mix(in srgb, var(--color-border) 88%, transparent);
    border-radius: 0.8rem;
    background: color-mix(in srgb, var(--color-bg-surface) 90%, #0a111d);
    padding: 0.8rem;
}

.estoque-atributos-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.8rem;
    margin-bottom: 0.8rem;
}

.estoque-atributos-header h3 {
    margin: 0;
    font-size: 1.05rem;
    color: var(--color-text);
    font-weight: 800;
}

.estoque-atributos-header p {
    margin: 0.18rem 0 0;
    color: var(--color-text-muted);
    font-size: 0.84rem;
}

.attr-counter {
    border: 1px solid color-mix(in srgb, var(--color-border) 80%, transparent);
    border-radius: 999px;
    padding: 0.2rem 0.6rem;
    color: var(--color-text-muted);
    font-size: 0.8rem;
    font-weight: 700;
    white-space: nowrap;
}

.attr-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.65rem;
}

.attr-tile {
    border: 1px solid color-mix(in srgb, var(--color-border) 82%, transparent);
    border-radius: 0.78rem;
    min-height: 2.65rem;
    background: color-mix(in srgb, var(--color-bg-surface) 96%, #ffffff);
    color: var(--color-text-muted);
    display: inline-flex;
    align-items: center;
    justify-content: flex-start;
    gap: 0.52rem;
    font-size: 0.86rem;
    font-weight: 700;
    padding: 0.55rem 0.75rem;
    cursor: pointer;
    transition: border-color 120ms ease, background-color 120ms ease, color 120ms ease;
}

.attr-tile:hover {
    border-color: color-mix(in srgb, var(--color-primary) 42%, var(--color-border));
    background: color-mix(in srgb, var(--color-primary) 8%, var(--color-bg-surface));
}

.attr-tile.is-active {
    color: var(--color-primary);
    border-color: color-mix(in srgb, var(--color-primary) 74%, transparent);
    background: color-mix(in srgb, var(--color-primary) 18%, var(--color-bg-surface));
}

.attr-tile:focus-visible {
    outline: 2px solid color-mix(in srgb, var(--color-primary) 55%, transparent);
    outline-offset: 1px;
}

.chips-list {
    margin: 0.5rem 0 0;
    padding: 0;
    list-style: none;
    display: flex;
    flex-wrap: wrap;
    gap: 0.45rem;
}

.chip-item {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border: 1px solid color-mix(in srgb, var(--color-border) 84%, transparent);
    border-radius: 999px;
    background: color-mix(in srgb, var(--color-bg-surface) 82%, #131a28);
    color: var(--color-text);
    font-size: 0.82rem;
    padding: 0.24rem 0.45rem 0.24rem 0.65rem;
}

.chip-remove-btn {
    border: none;
    background: transparent;
    color: var(--color-text-muted);
    cursor: pointer;
    font-size: 0.78rem;
    width: 1.05rem;
    height: 1.05rem;
    border-radius: 999px;
}

.chip-empty {
    font-size: 0.82rem;
    color: var(--color-text-muted);
}

.section-inline-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.8rem;
    margin-bottom: 0.8rem;
}

.section-inline-header h2 {
    margin: 0;
}

.section-inline-header p {
    margin: 0.2rem 0 0;
    font-size: 0.82rem;
    color: var(--color-text-muted);
}

.child-card {
    border: 1px solid color-mix(in srgb, var(--color-border) 90%, transparent);
    border-radius: 0.75rem;
    padding: 0.8rem;
}

.child-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
}

.child-card-header h4 {
    margin: 0;
    font-size: 0.95rem;
    color: var(--color-text);
    font-weight: 800;
}

.gerencial-layout {
    display: grid;
    grid-template-columns: minmax(20rem, 24rem) minmax(0, 1.45fr);
    gap: 0.9rem;
    align-items: start;
}

.gerencial-summary-card {
    position: sticky;
    top: 0.5rem;
}

.gerencial-summary-list {
    margin-top: 0.9rem;
    display: grid;
    gap: 0.7rem;
}

.gerencial-summary-item {
    display: grid;
    gap: 0.35rem;
}

.gerencial-summary-label-wrap {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.gerencial-summary-label {
    color: var(--color-text);
    font-size: 1rem;
    font-weight: 700;
}

.calc-chip {
    border: 1px solid color-mix(in srgb, var(--color-border) 80%, transparent);
    border-radius: 999px;
    padding: 0.12rem 0.5rem;
    color: var(--color-text);
    font-size: 0.78rem;
    font-weight: 800;
}

.gerencial-summary-value {
    border: 1px solid color-mix(in srgb, var(--color-border) 86%, transparent);
    border-radius: 0.68rem;
    min-height: 2.65rem;
    padding: 0.62rem 0.85rem;
    display: inline-flex;
    align-items: center;
    font-size: 1.06rem;
    color: var(--color-text);
    background: color-mix(in srgb, var(--color-bg-surface) 88%, #111724);
}

.gerencial-reference-box {
    margin-top: 0.9rem;
    border: 1px solid color-mix(in srgb, var(--color-border) 86%, transparent);
    border-radius: 0.76rem;
    padding: 0.8rem;
    background: color-mix(in srgb, var(--color-bg-surface) 93%, #0f141f);
}

.reference-caption {
    color: var(--color-text-muted);
    font-size: 0.74rem;
    letter-spacing: 0.04em;
}

.gerencial-reference-box strong {
    display: block;
    margin-top: 0.25rem;
    font-size: 1.7rem;
}

.gerencial-reference-box p {
    margin: 0.25rem 0 0;
    color: var(--color-text-muted);
    font-size: 0.86rem;
}

.gerencial-mini-grid {
    margin-top: 0.8rem;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.7rem;
}

.gerencial-mini-box {
    border: 1px solid color-mix(in srgb, var(--color-border) 86%, transparent);
    border-radius: 0.76rem;
    padding: 0.7rem;
    background: color-mix(in srgb, var(--color-bg-surface) 93%, #101623);
}

.gerencial-mini-box span {
    color: var(--color-text-muted);
    font-size: 0.78rem;
}

.gerencial-mini-box strong {
    margin-top: 0.25rem;
    display: block;
    font-size: 1.05rem;
    color: var(--color-text);
}

.gerencial-price-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
    gap: 0.8rem;
}

.gerencial-price-card {
    border: 1px solid color-mix(in srgb, var(--color-border) 90%, transparent);
    border-radius: 0.8rem;
    padding: 0.85rem;
    background: color-mix(in srgb, var(--color-bg-surface) 94%, #0f151f);
}

.ger-field {
    display: grid;
    gap: 0.35rem;
}

.ger-field-label {
    display: inline-flex;
    align-items: center;
    gap: 0.34rem;
    color: var(--color-text);
    font-size: 0.84rem;
    font-weight: 700;
}

.ger-field-control {
    position: relative;
}

.ger-field-leading-icon {
    position: absolute;
    top: 50%;
    left: 0.65rem;
    transform: translateY(-50%);
    color: var(--color-text-muted);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
}

.ger-field-input {
    width: 100%;
}

.ger-field-input--with-icon {
    padding-left: 2.02rem;
}

.ger-field-input--with-suffix {
    padding-right: 2.2rem;
}

.ger-field-suffix {
    position: absolute;
    top: 50%;
    right: 0.7rem;
    transform: translateY(-50%);
    color: var(--color-text-muted);
    font-size: 0.8rem;
    font-weight: 700;
    pointer-events: none;
}

.gerencial-price-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.75rem;
}

.gerencial-price-card-header h4 {
    margin: 0;
    font-size: 1.9rem;
    font-weight: 800;
}

.gerencial-remove-btn {
    border: none;
    background: transparent;
    color: var(--color-text);
    font-size: 0.95rem;
    font-weight: 700;
}

.gerencial-memory-card {
    padding-top: 0.9rem;
}

.gerencial-memory-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.65rem;
    margin-bottom: 0.35rem;
}

.gerencial-memory-title {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.exp-chip {
    border: 1px solid color-mix(in srgb, var(--color-border) 84%, transparent);
    border-radius: 999px;
    padding: 0.15rem 0.52rem;
    color: var(--color-text);
    font-size: 0.85rem;
    font-weight: 700;
}

.memory-toggle-btn {
    border: 1px solid color-mix(in srgb, var(--color-border) 82%, transparent);
    border-radius: 0.58rem;
    width: 2rem;
    height: 2rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: color-mix(in srgb, var(--color-bg-surface) 88%, #111826);
    color: var(--color-text-muted);
}

.gerencial-kpi-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.8rem;
}

.kpi-box {
    border: 1px solid color-mix(in srgb, var(--color-border) 86%, transparent);
    border-radius: 0.8rem;
    padding: 0.8rem;
    background: color-mix(in srgb, var(--color-bg-surface) 92%, #101520);
}

.kpi-box span {
    color: var(--color-text-muted);
    font-size: 0.82rem;
}

.kpi-box strong {
    display: block;
    margin-top: 0.3rem;
    font-size: 2.35rem;
    line-height: 1;
    color: var(--color-text);
}

.kpi-box.is-positive {
    border-color: color-mix(in srgb, #0a7f55 50%, var(--color-border));
    background: color-mix(in srgb, #0a7f55 18%, #0f1622);
}

.gerencial-final-box {
    border: 1px solid color-mix(in srgb, var(--color-border) 86%, transparent);
    border-radius: 0.8rem;
    padding: 0.8rem;
}

.gerencial-final-box h4 {
    margin: 0 0 0.35rem;
    font-size: 1.45rem;
}

.gerencial-final-box p {
    margin: 0.1rem 0;
    color: var(--color-text);
}

.gerencial-bottom-cards {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.9rem;
}

.historico-filters {
    margin-top: 0.9rem;
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 0.7rem;
    align-items: end;
}

.historico-grid-shell {
    margin-top: 0.9rem;
    border: 1px solid color-mix(in srgb, var(--color-border) 90%, transparent);
    border-radius: 0.8rem;
    padding: 0.9rem;
}

.historico-see-btn {
    border: none;
    background: transparent;
    color: var(--color-text);
    font-size: 0.95rem;
    font-weight: 700;
    text-decoration: underline;
    text-underline-offset: 0.16rem;
}

.historico-see-btn:disabled {
    color: var(--color-text-muted);
    text-decoration: none;
    cursor: not-allowed;
}

.historico-footer {
    margin-top: 0.8rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.8rem;
    color: var(--color-text-muted);
    font-size: 0.88rem;
}

.historico-footer-actions {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.historico-page-select {
    width: 5.2rem;
}

.history-change-card {
    border: 1px solid color-mix(in srgb, var(--color-border) 88%, transparent);
    border-radius: 0.8rem;
    padding: 0.85rem;
    background: color-mix(in srgb, var(--color-bg-surface) 93%, #101722);
}

.history-change-card h4 {
    margin: 0 0 0.6rem;
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--color-text);
}

.history-change-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.65rem;
}

.history-change-col {
    border: 1px solid color-mix(in srgb, var(--color-border) 88%, transparent);
    border-radius: 0.66rem;
    padding: 0.6rem 0.7rem;
    background: color-mix(in srgb, var(--color-bg-surface) 95%, #141c29);
}

.history-change-col span {
    color: var(--color-text-muted);
    font-size: 0.82rem;
}

.history-change-col p {
    margin: 0.3rem 0 0;
    color: var(--color-text);
    font-size: 0.95rem;
    white-space: pre-wrap;
    word-break: break-word;
}

.audit-json {
    margin: 0;
    white-space: pre-wrap;
    max-width: 32rem;
    font-size: 0.73rem;
}

@media (max-width: 920px) {
    .product-editor-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .product-editor-title {
        font-size: 1.55rem;
    }

    .card-section-header h3 {
        font-size: 1.25rem;
    }

    .ean-group {
        grid-template-columns: 1fr;
    }

    .select-action-row {
        grid-template-columns: 1fr;
    }

    .note-banner {
        flex-direction: column;
        align-items: flex-start;
    }

    .optional-level-grid {
        grid-template-columns: 1fr;
    }

    .barcode-grid-controls {
        grid-template-columns: 1fr;
    }

    .barcode-pages-wrap {
        flex-wrap: wrap;
    }

    .barcode-modal-grid {
        grid-template-columns: 1fr;
    }

    .barcode-modal-primary,
    .barcode-modal-info-field,
    .barcode-modal-status-field,
    .barcode-modal-sku-field {
        grid-column: auto;
    }

    .counter-row {
        flex-direction: column;
        align-items: flex-start;
    }

    .attr-grid {
        grid-template-columns: 1fr;
    }

    .estoque-atributos-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .gerencial-layout {
        grid-template-columns: 1fr;
    }

    .gerencial-summary-card {
        position: static;
    }

    .gerencial-price-grid {
        grid-template-columns: 1fr;
    }

    .gerencial-kpi-grid {
        grid-template-columns: 1fr;
    }

    .gerencial-bottom-cards {
        grid-template-columns: 1fr;
    }

    .historico-filters {
        grid-template-columns: 1fr;
    }

    .historico-footer {
        flex-direction: column;
        align-items: flex-start;
    }

    .history-change-grid {
        grid-template-columns: 1fr;
    }

    .foto-grid {
        grid-template-columns: 1fr;
    }

    .composition-view-mode {
        width: 100%;
        justify-content: stretch;
    }

    .mode-btn {
        flex: 1;
    }

    .composition-cost-summary__grid {
        grid-template-columns: 1fr;
    }

    .composition-pricing-box__inputs,
    .composition-pricing-box__metrics {
        grid-template-columns: 1fr;
    }
}
</style>
