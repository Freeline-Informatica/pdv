<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { GitBranch, LayoutGrid, Plus, Trash2 } from 'lucide-vue-next';
import { useRouter } from 'vue-router';
import api from '../../lib/api';
import SettingsPageHeader from '../../components/settings/SettingsPageHeader.vue';
import AppCard from '../../components/ui/AppCard.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppBadge from '../../components/ui/AppBadge.vue';
import AppModal from '../../components/ui/AppModal.vue';
import AppInput from '../../components/ui/AppInput.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppCheckbox from '../../components/ui/AppCheckbox.vue';
import AppSearchField from '../../components/ui/AppSearchField.vue';
import AppTextarea from '../../components/ui/AppTextarea.vue';

const router = useRouter();

const loading = ref(false);
const saving = ref(false);
const deletingId = ref('');
const modalOpen = ref(false);
const editingId = ref('');
const selectedClassificationId = ref('');
const hoveredClassificationId = ref('');
const error = ref('');
const submitError = ref('');
const viewMode = ref('grid');

const search = ref('');
const onlyActive = ref(false);

const classifications = ref([]);
const parentOptions = ref([]);

const orgZoom = ref(0.82);
const orgPan = reactive({ x: 0, y: 0 });
const orgNodePositions = reactive({});
const orgPointer = reactive({
    mode: '',
    nodeId: '',
    startX: 0,
    startY: 0,
    startPanX: 0,
    startPanY: 0,
    startNodeX: 0,
    startNodeY: 0,
    hasDragged: false,
});

const form = reactive({
    codigo: '',
    descricao: '',
    descricao_reduzida: '',
    parent_id: '',
    ordem: '',
    ativo: true,
    parametros_observacoes: [],
});

const formErrors = reactive({
    codigo: '',
    descricao: '',
    descricao_reduzida: '',
    parent_id: '',
    ordem: '',
    ativo: '',
    parametros_observacoes: '',
});

const observationFieldNameTemplates = [
    { id: 'personalizado', label: 'Personalizado' },
    { id: 'observacao_padrao', label: 'Observação padrão' },
    { id: 'sem_item', label: 'Sem item' },
    { id: 'com_item', label: 'Com item' },
    { id: 'intensidade', label: 'Intensidade' },
    { id: 'temperatura', label: 'Temperatura' },
];

const observationFieldTypeOptions = [
    { id: 'texto_curto', label: 'Texto curto' },
    { id: 'texto_longo', label: 'Texto longo' },
    { id: 'numero_inteiro', label: 'Número inteiro' },
    { id: 'numero_decimal', label: 'Número decimal' },
    { id: 'data', label: 'Data' },
    { id: 'sim_nao', label: 'Sim/Não' },
    { id: 'checkbox_texto', label: 'Checkbox + texto' },
];

const selectedClassification = computed(() => classifications.value.find((item) => item.id === selectedClassificationId.value) || null);
const totalCount = computed(() => classifications.value.length);
const activeCount = computed(() => classifications.value.filter((item) => item.ativo && !item.deleted_at).length);

const mapById = computed(() => {
    const map = new Map();
    classifications.value.forEach((item) => {
        map.set(item.id, item);
    });
    return map;
});

const childrenByParent = computed(() => {
    const map = new Map();
    classifications.value.forEach((item) => {
        const key = String(item.parent_id || 'root');
        if (!map.has(key)) map.set(key, []);
        map.get(key).push(item);
    });

    map.forEach((list) => list.sort(sortClassificationRows));
    return map;
});

const roots = computed(() =>
    classifications.value
        .filter((item) => !item.parent_id || !mapById.value.has(item.parent_id))
        .sort(sortClassificationRows),
);

const childCountByNode = computed(() => {
    const map = new Map();
    classifications.value.forEach((item) => {
        const key = String(item.parent_id || '');
        map.set(key, (map.get(key) || 0) + 1);
    });
    return map;
});

const treeRows = computed(() => {
    const rows = [];
    const visited = new Set();

    function walk(node, depth, parentLabel = '') {
        if (visited.has(node.id)) return;
        visited.add(node.id);

        rows.push({
            ...node,
            depth,
            parent_label: parentLabel,
        });

        const children = childrenByParent.value.get(String(node.id)) || [];
        children.forEach((child) => walk(child, depth + 1, node.descricao || ''));
    }

    roots.value.forEach((root) => walk(root, 0, ''));

    return rows;
});

const parentSelectOptions = computed(() => {
    const activeOptions = parentOptions.value.filter((item) => !item.deleted_at);
    const filtered = editingId.value ? activeOptions.filter((item) => item.id !== editingId.value) : activeOptions;
    return filtered.sort(sortClassificationRows);
});

const orgAutoLayoutNodes = computed(() => {
    const nodes = [];
    const leafGap = 315;
    const levelGap = 210;
    const baseY = 80;
    let nextLeafX = 90;

    function placeNode(node, depth) {
        const children = (childrenByParent.value.get(String(node.id)) || []).filter((child) => !child.deleted_at);
        let x = nextLeafX;

        if (children.length > 0) {
            const childPositions = children.map((child) => placeNode(child, depth + 1));
            x = (childPositions[0] + childPositions[childPositions.length - 1]) / 2;
        } else {
            nextLeafX += leafGap;
        }

        nodes.push({
            ...node,
            depth,
            autoPosition: { x, y: baseY + depth * levelGap },
            childrenCount: childCountByNode.value.get(String(node.id)) || 0,
            color: getNodeColor(node.codigo || node.id),
        });

        return x;
    }

    const activeRoots = roots.value.filter((row) => !row.deleted_at);
    activeRoots.forEach((root, index) => {
        if (index > 0) nextLeafX += 140;
        placeNode(root, 0);
    });

    return nodes;
});

const orgNodes = computed(() =>
    orgAutoLayoutNodes.value.map((node) => ({
        ...node,
        position: orgNodePositions[node.id] || node.autoPosition,
    })),
);

const orgNodeMap = computed(() => {
    const map = new Map();
    orgNodes.value.forEach((node) => map.set(node.id, node));
    return map;
});

const orgEdges = computed(() =>
    orgNodes.value
        .filter((node) => node.parent_id && orgNodeMap.value.has(node.parent_id))
        .map((node) => ({
            id: `${node.parent_id}-${node.id}`,
            from: orgNodeMap.value.get(node.parent_id),
            to: node,
            color: node.color,
        })),
);

const selectedOrgNode = computed(() => orgNodeMap.value.get(selectedClassificationId.value) || null);
const hoveredOrgNode = computed(() => orgNodeMap.value.get(hoveredClassificationId.value) || null);

function sortClassificationRows(a, b) {
    const orderA = a?.ordem == null ? Number.MAX_SAFE_INTEGER : Number(a.ordem);
    const orderB = b?.ordem == null ? Number.MAX_SAFE_INTEGER : Number(b.ordem);
    if (orderA !== orderB) return orderA - orderB;
    return String(a?.descricao || '').localeCompare(String(b?.descricao || ''), 'pt-BR');
}

function goBack() {
    router.push('/configuracoes/catalogo/parametros');
}

function normalizeText(value) {
    const cleaned = String(value || '').trim();
    return cleaned === '' ? null : cleaned;
}

function normalizeInteger(value) {
    const cleaned = String(value ?? '').trim();
    if (cleaned === '') return null;
    const parsed = Number(cleaned);
    return Number.isFinite(parsed) ? Math.trunc(parsed) : null;
}

function resolveObservationTemplateLabel(templateId) {
    return observationFieldNameTemplates.find((option) => option.id === templateId)?.label || 'Campo adicional';
}

function createObservationParameter() {
    return {
        id: crypto?.randomUUID?.() || String(Date.now() + Math.random()),
        nome_template: 'personalizado',
        nome_personalizado: '',
        tipo_campo: 'texto_curto',
        texto_checkbox: '',
        obrigatorio: false,
        ordem: String(form.parametros_observacoes.length + 1),
    };
}

function normalizeObservationParameter(field, index = 0) {
    const type = String(field?.tipo_campo || 'texto_curto');
    const normalizedType = observationFieldTypeOptions.some((option) => option.id === type) ? type : 'texto_curto';
    return {
        id: String(field?.id || crypto?.randomUUID?.() || String(Date.now() + Math.random())),
        nome_template: String(field?.nome_template || 'personalizado'),
        nome_personalizado: String(field?.nome_personalizado || ''),
        tipo_campo: normalizedType,
        texto_checkbox: String(field?.texto_checkbox || ''),
        obrigatorio: Boolean(field?.obrigatorio),
        ordem: String(field?.ordem ?? index + 1),
    };
}

function normalizeObservationParametersList(rows) {
    if (!Array.isArray(rows)) return [];
    return rows
        .map((field, index) => normalizeObservationParameter(field, index))
        .sort((a, b) => {
            const orderA = Number(a?.ordem);
            const orderB = Number(b?.ordem);
            const parsedA = Number.isFinite(orderA) ? orderA : Number.MAX_SAFE_INTEGER;
            const parsedB = Number.isFinite(orderB) ? orderB : Number.MAX_SAFE_INTEGER;
            if (parsedA !== parsedB) return parsedA - parsedB;
            return String(a?.id || '').localeCompare(String(b?.id || ''), 'pt-BR');
        });
}

function onObservationTemplateChange(field) {
    if (!field || typeof field !== 'object') return;
    if (String(field.nome_template || '') !== 'personalizado') {
        field.nome_personalizado = '';
    }
}

function onObservationTypeChange(field) {
    if (!field || typeof field !== 'object') return;
    if (String(field.tipo_campo || '') !== 'checkbox_texto') {
        field.texto_checkbox = '';
    }
}

function addObservationParameter() {
    form.parametros_observacoes.push(createObservationParameter());
}

function removeObservationParameter(index) {
    form.parametros_observacoes.splice(index, 1);
}

function normalizeObservationParameterPayload(rows) {
    if (!Array.isArray(rows)) return [];

    return rows
        .map((field, index) => {
            const templateId = String(field?.nome_template || 'personalizado');
            const customName = String(field?.nome_personalizado || '').trim();
            const type = String(field?.tipo_campo || 'texto_curto');
            const checkboxText = String(field?.texto_checkbox || '').trim();
            const orderRaw = normalizeInteger(field?.ordem);

            if (templateId === 'personalizado' && customName === '') return null;

            return {
                id: String(field?.id || crypto?.randomUUID?.() || String(Date.now() + Math.random())),
                nome_template: templateId,
                nome_personalizado: templateId === 'personalizado' ? customName : '',
                tipo_campo: type,
                texto_checkbox: type === 'checkbox_texto' ? checkboxText : '',
                obrigatorio: Boolean(field?.obrigatorio),
                ordem: orderRaw == null ? index : Math.max(0, orderRaw),
            };
        })
        .filter(Boolean);
}

function resetForm() {
    form.codigo = '';
    form.descricao = '';
    form.descricao_reduzida = '';
    form.parent_id = '';
    form.ordem = '';
    form.ativo = true;
    form.parametros_observacoes = [];

    Object.keys(formErrors).forEach((key) => {
        formErrors[key] = '';
    });
    submitError.value = '';
}

function fillFormFromClassification(item) {
    form.codigo = item?.codigo || '';
    form.descricao = item?.descricao || '';
    form.descricao_reduzida = item?.descricao_reduzida || '';
    form.parent_id = item?.parent_id || '';
    form.ordem = item?.ordem == null ? '' : String(item.ordem);
    form.ativo = !!item?.ativo;
    form.parametros_observacoes = normalizeObservationParametersList(item?.parametros_observacoes);
}

function openCreate() {
    editingId.value = '';
    resetForm();
    modalOpen.value = true;
}

function openEdit(item) {
    if (item?.deleted_at) return;
    editingId.value = item.id;
    resetForm();
    fillFormFromClassification(item);
    modalOpen.value = true;
}

function closeOrgDrawer() {
    selectedClassificationId.value = '';
}

function makeCloneCode(baseCode) {
    const source = String(baseCode || 'CLASS').trim().toUpperCase() || 'CLASS';
    const base = `${source}_COP`;
    const existing = new Set(classifications.value.map((item) => String(item.codigo || '').toUpperCase()));

    if (!existing.has(base.toUpperCase()) && base.length <= 30) return base;

    let index = 2;
    while (index < 1000) {
        const candidate = `${source}_COP${index}`;
        if (candidate.length <= 30 && !existing.has(candidate.toUpperCase())) return candidate;
        index += 1;
    }

    return `CLASS_COPIA_${Date.now()}`.slice(0, 30);
}

function cloneSelected() {
    if (!selectedClassification.value) return;

    editingId.value = '';
    resetForm();
    fillFormFromClassification(selectedClassification.value);
    form.codigo = makeCloneCode(selectedClassification.value.codigo);
    form.descricao = `${selectedClassification.value.descricao || ''} (cópia)`.trim();
    modalOpen.value = true;
}

function validateForm() {
    Object.keys(formErrors).forEach((key) => {
        formErrors[key] = '';
    });
    submitError.value = '';

    if (!String(form.codigo || '').trim()) {
        formErrors.codigo = 'Informe o código.';
    }

    if (!String(form.descricao || '').trim()) {
        formErrors.descricao = 'Informe a descrição.';
    }

    const ordem = normalizeInteger(form.ordem);
    if (form.ordem !== '' && (ordem == null || ordem < 0)) {
        formErrors.ordem = 'Informe uma ordem válida.';
    }

    if (editingId.value && form.parent_id && form.parent_id === editingId.value) {
        formErrors.parent_id = 'A classificação não pode ser pai dela mesma.';
    }

    const hasInvalidParameter = form.parametros_observacoes.some((field) => {
        const templateId = String(field?.nome_template || 'personalizado');
        if (templateId !== 'personalizado') return false;
        return !String(field?.nome_personalizado || '').trim();
    });

    if (hasInvalidParameter) {
        formErrors.parametros_observacoes = 'Preencha o nome personalizado dos parâmetros adicionados.';
    }

    return Object.values(formErrors).every((value) => !value);
}

function countChildren(classificationId) {
    return childCountByNode.value.get(String(classificationId || '')) || 0;
}

function getEdgePath(edge) {
    const fromX = edge.from.position.x + 150;
    const fromY = edge.from.position.y + 154;
    const toX = edge.to.position.x + 150;
    const toY = edge.to.position.y;
    const midY = (fromY + toY) / 2;
    return `M ${fromX} ${fromY} C ${fromX} ${midY} ${toX} ${midY} ${toX} ${toY}`;
}

function arrangeOrg() {
    Object.keys(orgNodePositions).forEach((key) => {
        delete orgNodePositions[key];
    });

    orgAutoLayoutNodes.value.forEach((node) => {
        orgNodePositions[node.id] = { x: node.autoPosition.x, y: node.autoPosition.y };
    });
}

function setNodePosition(nodeId, x, y) {
    orgNodePositions[nodeId] = { x, y };
}

function startOrgPan(event) {
    if (event.button !== 0) return;
    if (event.target?.closest?.('.classifications-org-node')) return;

    orgPointer.mode = 'pan';
    orgPointer.startX = event.clientX;
    orgPointer.startY = event.clientY;
    orgPointer.startPanX = orgPan.x;
    orgPointer.startPanY = orgPan.y;
}

function startOrgNodeDrag(node, event) {
    if (event.button !== 0) return;

    selectedClassificationId.value = node.id;
    orgPointer.mode = 'node';
    orgPointer.nodeId = node.id;
    orgPointer.startX = event.clientX;
    orgPointer.startY = event.clientY;
    orgPointer.startNodeX = node.position.x;
    orgPointer.startNodeY = node.position.y;
    orgPointer.hasDragged = false;
}

function moveOrgPointer(event) {
    if (!orgPointer.mode) return;

    const deltaX = (event.clientX - orgPointer.startX) / orgZoom.value;
    const deltaY = (event.clientY - orgPointer.startY) / orgZoom.value;

    if (orgPointer.mode === 'pan') {
        orgPan.x = orgPointer.startPanX + (event.clientX - orgPointer.startX);
        orgPan.y = orgPointer.startPanY + (event.clientY - orgPointer.startY);
        return;
    }

    if (orgPointer.mode === 'node') {
        setNodePosition(orgPointer.nodeId, orgPointer.startNodeX + deltaX, orgPointer.startNodeY + deltaY);
        if (Math.hypot(event.clientX - orgPointer.startX, event.clientY - orgPointer.startY) > 6) {
            orgPointer.hasDragged = true;
        }
    }
}

function stopOrgPointer() {
    orgPointer.mode = '';
    orgPointer.nodeId = '';
    orgPointer.hasDragged = false;
}

function zoomOrg(delta) {
    const next = Math.min(1.45, Math.max(0.55, orgZoom.value + delta));
    orgZoom.value = Number(next.toFixed(2));
}

function onOrgWheel(event) {
    event.preventDefault();

    const oldZoom = orgZoom.value;
    const nextZoom = Math.min(1.45, Math.max(0.55, oldZoom + (event.deltaY < 0 ? 0.08 : -0.08)));
    if (nextZoom === oldZoom) return;

    const rect = event.currentTarget.getBoundingClientRect();
    const screenX = event.clientX - rect.left;
    const screenY = event.clientY - rect.top;
    const worldX = (screenX - orgPan.x) / oldZoom;
    const worldY = (screenY - orgPan.y) / oldZoom;

    orgZoom.value = Number(nextZoom.toFixed(2));
    orgPan.x = screenX - worldX * orgZoom.value;
    orgPan.y = screenY - worldY * orgZoom.value;
}

function centerOrg() {
    orgPan.x = 0;
    orgPan.y = 0;
    orgZoom.value = 0.82;
}

function centerOrgNode(node) {
    if (!node) return;
    orgPan.x = 620 - (node.position.x * orgZoom.value);
    orgPan.y = 180 - (node.position.y * orgZoom.value);
}

function getNodeColor(seed = '') {
    const palette = ['#85A3FF', '#6EC2FF', '#74E2C0', '#A6D37A', '#F2BE63', '#D99AF5'];
    const value = String(seed || 'node');
    let hash = 0;
    for (let i = 0; i < value.length; i += 1) {
        hash = (hash << 5) - hash + value.charCodeAt(i);
        hash |= 0;
    }
    return palette[Math.abs(hash) % palette.length];
}

async function loadParentOptions() {
    try {
        const { data } = await api.get('/catalog/classifications', {
            params: {
                include_deleted: true,
            },
        });

        parentOptions.value = Array.isArray(data) ? data : [];
    } catch {
        parentOptions.value = [];
    }
}

async function loadClassifications() {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await api.get('/catalog/classifications', {
            params: {
                search: search.value || undefined,
                only_active: onlyActive.value || undefined,
            },
        });

        classifications.value = Array.isArray(data) ? data : [];

        if (selectedClassificationId.value && !classifications.value.find((item) => item.id === selectedClassificationId.value)) {
            selectedClassificationId.value = '';
        }
    } catch (requestError) {
        error.value = requestError?.response?.data?.message ?? 'Falha ao carregar classificações.';
        classifications.value = [];
    } finally {
        loading.value = false;
    }
}

async function refreshAll() {
    await Promise.all([loadClassifications(), loadParentOptions()]);
    arrangeOrg();
}

async function save() {
    if (!validateForm()) return;

    saving.value = true;
    submitError.value = '';

    try {
        const payload = {
            codigo: String(form.codigo || '').trim(),
            descricao: String(form.descricao || '').trim(),
            descricao_reduzida: normalizeText(form.descricao_reduzida),
            parent_id: normalizeText(form.parent_id),
            ordem: normalizeInteger(form.ordem),
            ativo: !!form.ativo,
            parametros_observacoes: normalizeObservationParameterPayload(form.parametros_observacoes),
        };

        if (editingId.value) {
            await api.put(`/catalog/classifications/${editingId.value}`, payload);
        } else {
            await api.post('/catalog/classifications', payload);
        }

        modalOpen.value = false;
        await refreshAll();
    } catch (requestError) {
        const responseErrors = requestError?.response?.data?.errors || {};
        Object.entries(responseErrors).forEach(([field, messages]) => {
            if (!Object.hasOwn(formErrors, field)) return;
            formErrors[field] = Array.isArray(messages) && messages.length ? String(messages[0]) : 'Campo inválido.';
        });

        if (Array.isArray(responseErrors.classification) && responseErrors.classification.length) {
            submitError.value = responseErrors.classification[0];
            return;
        }

        submitError.value = requestError?.response?.data?.message ?? 'Não foi possível salvar a classificação.';
    } finally {
        saving.value = false;
    }
}

async function removeClassification(item) {
    if (!item || item.deleted_at) return;
    if (!window.confirm(`Excluir classificação "${item.descricao}"?`)) return;

    deletingId.value = item.id;
    try {
        await api.delete(`/catalog/classifications/${item.id}`);
        await refreshAll();
    } catch (requestError) {
        const responseErrors = requestError?.response?.data?.errors || {};
        if (Array.isArray(responseErrors.classification) && responseErrors.classification.length) {
            error.value = responseErrors.classification[0];
        } else {
            error.value = requestError?.response?.data?.message ?? 'Não foi possível excluir a classificação.';
        }
    } finally {
        deletingId.value = '';
    }
}

onMounted(() => {
    refreshAll();
    window.addEventListener('pointermove', moveOrgPointer);
    window.addEventListener('pointerup', stopOrgPointer);
});

onBeforeUnmount(() => {
    window.removeEventListener('pointermove', moveOrgPointer);
    window.removeEventListener('pointerup', stopOrgPointer);
});
</script>

<template>
    <div class="space-y-4 classifications-catalog-view">
        <p class="classifications-breadcrumb">Catálogo &gt; Parâmetros</p>

        <SettingsPageHeader
            title="Classificação Mercadológica"
            subtitle="Hierarquia mercadológica usada no produto e em regras de classificação operacional."
        >
            <template #actions>
                <div class="classifications-header-actions">
                    <AppButton variant="secondary" @click="goBack">Voltar aos parâmetros</AppButton>
                    <AppButton variant="secondary" :disabled="!selectedClassification || !!selectedClassification?.deleted_at" @click="cloneSelected">
                        Clonar classificação cadastrada
                    </AppButton>
                    <AppButton @click="openCreate">Nova classificação</AppButton>
                </div>
            </template>
        </SettingsPageHeader>

        <AppCard class="classifications-notice">
            Esta tela centraliza a hierarquia mercadológica e evita manutenção exclusivamente pelo modal rápido do cadastro principal.
        </AppCard>

        <AppCard elevated class="classifications-grid-shell">
            <div class="classifications-grid-header">
                <div>
                    <h3 class="classifications-grid-title">Classificações cadastradas</h3>
                    <p class="classifications-grid-subtitle">{{ totalCount }} registro(s) • {{ activeCount }} ativo(s)</p>
                </div>

                <div class="classifications-view-mode">
                    <button
                        type="button"
                        class="classifications-view-btn"
                        :class="{ 'is-active': viewMode === 'grid' }"
                        @click="viewMode = 'grid'"
                    >
                        <LayoutGrid class="h-4 w-4" aria-hidden="true" />
                        Grid
                    </button>
                    <button
                        type="button"
                        class="classifications-view-btn"
                        :class="{ 'is-active': viewMode === 'organograma' }"
                        @click="viewMode = 'organograma'"
                    >
                        <GitBranch class="h-4 w-4" aria-hidden="true" />
                        Organograma
                    </button>
                </div>
            </div>

            <section class="classifications-filters">
                <p class="classifications-filters-label">Filtros</p>
                <div class="classifications-filters-row">
                    <div class="classifications-search">
                        <AppSearchField v-model="search" placeholder="Buscar por código, descrição ou caminho" />
                    </div>
                    <div class="classifications-filter-checks">
                        <AppCheckbox v-model="onlyActive" label="Somente ativas" />
                    </div>
                    <AppButton variant="secondary" :loading="loading" @click="loadClassifications">Atualizar</AppButton>
                </div>
            </section>

            <p v-if="error" class="text-danger text-sm">{{ error }}</p>

            <div v-if="loading" class="classifications-loading">Carregando classificações...</div>

            <div v-else-if="classifications.length === 0" class="classifications-empty">
                Nenhuma classificação encontrada para os filtros selecionados.
            </div>

            <div v-else-if="viewMode === 'grid'" class="classifications-list">
                <article
                    v-for="item in treeRows"
                    :key="item.id"
                    class="classifications-item-card"
                    :class="{
                        'is-selected': selectedClassificationId === item.id,
                        'is-deleted': !!item.deleted_at,
                        'is-child-level': item.depth > 0,
                    }"
                    :style="{ '--level-depth': item.depth }"
                    @click="selectedClassificationId = item.id"
                >
                    <div class="classifications-item-main">
                        <div class="classifications-item-title-row">
                            <h4 class="classifications-item-title">{{ item.descricao }}</h4>
                            <div class="classifications-item-badges">
                                <AppBadge variant="default">Nível {{ item.nivel || 1 }}</AppBadge>
                                <AppBadge variant="default">{{ item.codigo || '—' }}</AppBadge>
                                <AppBadge variant="default">{{ item.products_count || 0 }} produto(s)</AppBadge>
                                <AppBadge variant="default">{{ (item.parametros_observacoes || []).length }} parâmetro(s)</AppBadge>
                                <AppBadge :variant="item.deleted_at ? 'warning' : item.ativo ? 'success' : 'default'">
                                    {{ item.deleted_at ? 'Excluída' : item.ativo ? 'Ativa' : 'Inativa' }}
                                </AppBadge>
                            </div>
                        </div>
                        <p class="classifications-item-description">{{ item.descricao_reduzida || 'Sem descrição reduzida.' }}</p>
                        <p v-if="item.parent_label" class="classifications-item-parent">Vinculada a {{ item.parent_label }}</p>
                    </div>

                    <div class="classifications-item-actions">
                        <AppButton
                            variant="secondary"
                            :disabled="!!item.deleted_at"
                            @click.stop="openEdit(item)"
                        >
                            Editar
                        </AppButton>
                        <AppButton
                            variant="danger"
                            :disabled="!!item.deleted_at || deletingId === item.id"
                            @click.stop="removeClassification(item)"
                        >
                            Excluir
                        </AppButton>
                    </div>
                </article>
            </div>

            <div v-else class="classifications-org-shell" :class="{ 'has-open-drawer': selectedOrgNode }">
                <div class="classifications-org-blur-layer">
                    <div class="classifications-org-header">
                        <div>
                            <h3>Organograma da classificação</h3>
                            <p>Nível raiz representa classificações sem pai. Arraste nós para reorganizar a leitura visual.</p>
                        </div>
                        <div class="classifications-org-toolbar">
                            <button type="button" class="classifications-org-tool" @click="arrangeOrg">Expandir tudo</button>
                            <button type="button" class="classifications-org-tool" @click="centerOrg">Centralizar</button>
                            <button type="button" class="classifications-org-tool" @click="zoomOrg(-0.1)">Zoom -</button>
                            <button type="button" class="classifications-org-tool" @click="zoomOrg(0.1)">Zoom +</button>
                            <span class="classifications-org-zoom">{{ Math.round(orgZoom * 100) }}%</span>
                        </div>
                    </div>

                    <div class="classifications-org-path">
                        <strong>Caminho:</strong>
                        <span>{{ selectedOrgNode?.path || 'Selecione um nó para visualizar o caminho completo.' }}</span>
                    </div>

                    <div
                        class="classifications-org-canvas"
                        @pointerdown="startOrgPan"
                        @wheel="onOrgWheel"
                    >
                        <div
                            class="classifications-org-stage"
                            :style="{ transform: `translate(${orgPan.x}px, ${orgPan.y}px) scale(${orgZoom})` }"
                        >
                            <svg class="classifications-org-edges" viewBox="0 0 2000 1200" aria-hidden="true">
                                <path
                                    v-for="edge in orgEdges"
                                    :key="`halo-${edge.id}`"
                                    :d="getEdgePath(edge)"
                                    class="classifications-org-edge-halo"
                                />
                                <path
                                    v-for="edge in orgEdges"
                                    :key="edge.id"
                                    :d="getEdgePath(edge)"
                                    class="classifications-org-edge"
                                    :style="{ '--edge-color': edge.color }"
                                />
                            </svg>

                            <div
                                v-for="node in orgNodes"
                                :key="`node-${node.id}`"
                                class="classifications-org-node"
                                :class="{
                                    'is-selected': selectedClassificationId === node.id,
                                    'is-inactive': !node.ativo,
                                }"
                                :style="{ left: `${node.position.x}px`, top: `${node.position.y}px`, '--node-color': node.color }"
                                @pointerdown.stop="startOrgNodeDrag(node, $event)"
                                @mouseenter="hoveredClassificationId = node.id"
                                @mouseleave="hoveredClassificationId = ''"
                                @click.stop="selectedClassificationId = node.id"
                            >
                                <h4>{{ node.descricao }}</h4>
                                <div class="classifications-org-tags">
                                    <span>Nível {{ node.nivel || 1 }}</span>
                                    <span>{{ node.codigo || '—' }}</span>
                                    <span>{{ node.childrenCount }} filho(s)</span>
                                </div>
                                <p><strong>Produtos:</strong> {{ node.products_count || 0 }}</p>
                                <p v-if="node.descricao_reduzida"><strong>Resumo:</strong> {{ node.descricao_reduzida }}</p>
                            </div>

                            <div
                                v-if="hoveredOrgNode"
                                class="classifications-org-hover"
                                :style="{ left: `${hoveredOrgNode.position.x - 6}px`, top: `${hoveredOrgNode.position.y - 150}px` }"
                            >
                                <h4>{{ hoveredOrgNode.descricao }}</h4>
                                <div class="classifications-org-hover-grid">
                                    <span><strong>Código:</strong> {{ hoveredOrgNode.codigo }}</span>
                                    <span><strong>Nível:</strong> {{ hoveredOrgNode.nivel || 1 }}</span>
                                    <span><strong>Filhos:</strong> {{ hoveredOrgNode.childrenCount }}</span>
                                    <span><strong>Produtos:</strong> {{ hoveredOrgNode.products_count || 0 }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <button
                    v-if="selectedOrgNode"
                    type="button"
                    class="classifications-drawer-backdrop"
                    aria-label="Fechar detalhes do nó"
                    @click="closeOrgDrawer"
                ></button>

                <div
                    v-if="selectedOrgNode"
                    class="classifications-node-spotlight"
                    :style="{ '--node-color': selectedOrgNode.color }"
                >
                    <span class="classifications-node-spotlight__eyebrow">Nó selecionado</span>
                    <h4>{{ selectedOrgNode.descricao }}</h4>
                    <div class="classifications-org-tags">
                        <span>Nível {{ selectedOrgNode.nivel || 1 }}</span>
                        <span>{{ selectedOrgNode.codigo }}</span>
                        <span>{{ selectedOrgNode.childrenCount }} filho(s)</span>
                    </div>
                    <p><strong>Produtos:</strong> {{ selectedOrgNode.products_count || 0 }}</p>
                </div>

                <aside v-if="selectedOrgNode" class="classifications-node-drawer">
                    <button type="button" class="classifications-node-drawer__close" @click="closeOrgDrawer">x</button>
                    <h3>Detalhes do Nó</h3>
                    <p>Análise rápida da classificação selecionada no organograma.</p>

                    <div class="classifications-node-detail-card">
                        <div class="classifications-node-detail-title">
                            <h4>{{ selectedOrgNode.descricao }}</h4>
                            <span>Nível {{ selectedOrgNode.nivel || 1 }}</span>
                        </div>
                        <p><strong>Código:</strong> {{ selectedOrgNode.codigo }}</p>
                        <p><strong>Path:</strong> {{ selectedOrgNode.path }}</p>
                        <p><strong>Ordem:</strong> {{ selectedOrgNode.ordem ?? '—' }}</p>
                        <p><strong>Filhos:</strong> {{ selectedOrgNode.childrenCount }}</p>
                        <p><strong>Produtos:</strong> {{ selectedOrgNode.products_count || 0 }}</p>
                        <p><strong>Parâmetros:</strong> {{ (selectedOrgNode.parametros_observacoes || []).length }}</p>
                        <p v-if="selectedOrgNode.descricao_reduzida"><strong>Descrição reduzida:</strong> {{ selectedOrgNode.descricao_reduzida }}</p>
                    </div>

                    <div class="classifications-node-detail-card">
                        <h4>Caminho hierárquico</h4>
                        <p>{{ selectedOrgNode.path }}</p>
                    </div>

                    <div class="classifications-node-detail-card">
                        <h4>Parâmetros de observação</h4>
                        <p v-if="!(selectedOrgNode.parametros_observacoes || []).length">Nenhum parâmetro configurado.</p>
                        <p
                            v-for="field in selectedOrgNode.parametros_observacoes || []"
                            :key="`selected-node-observation-${field.id}`"
                        >
                            <strong>{{ field.nome_template === 'personalizado' ? field.nome_personalizado : resolveObservationTemplateLabel(field.nome_template) }}:</strong>
                            {{ observationFieldTypeOptions.find((option) => option.id === field.tipo_campo)?.label || 'Texto curto' }}
                        </p>
                    </div>

                    <div class="classifications-node-drawer-actions">
                        <AppButton variant="secondary" @click="centerOrgNode(selectedOrgNode)">Centralizar nó</AppButton>
                        <AppButton @click="openEdit(selectedOrgNode)">Editar classificação</AppButton>
                    </div>
                </aside>
            </div>
        </AppCard>

        <AppModal
            :open="modalOpen"
            :title="editingId ? 'Editar classificação' : 'Nova classificação'"
            width-class="classifications-modal-width"
            @close="modalOpen = false"
        >
            <div class="classifications-form-grid">
                <AppInput v-model="form.codigo" label="Código" :error="formErrors.codigo" maxlength="30" />
                <AppInput v-model="form.descricao" label="Descrição" :error="formErrors.descricao" maxlength="120" />

                <AppInput
                    v-model="form.descricao_reduzida"
                    label="Descrição reduzida"
                    :error="formErrors.descricao_reduzida"
                    maxlength="40"
                />
                <AppSelect v-model="form.parent_id" label="Classificação pai" :error="formErrors.parent_id">
                    <option value="">Sem pai</option>
                    <option
                        v-for="option in parentSelectOptions"
                        :key="option.id"
                        :value="option.id"
                    >
                        N{{ option.nivel }} · {{ option.descricao }} ({{ option.codigo }})
                    </option>
                </AppSelect>

                <AppInput v-model="form.ordem" label="Ordem" :error="formErrors.ordem" inputmode="numeric" />
                <div class="classifications-checkbox-wrap">
                    <AppCheckbox v-model="form.ativo" label="Registro ativo" />
                </div>

                <section class="classifications-observation-parameters">
                    <div class="classifications-observation-parameters__header">
                        <div>
                            <h4>Parâmetros e observações da classificação</h4>
                            <p>Esses campos aparecerão no modal da comanda quando o produto usar esta classificação.</p>
                        </div>
                        <AppButton type="button" variant="secondary" @click="addObservationParameter">
                            <Plus class="h-4 w-4" aria-hidden="true" />
                            Adicionar parâmetro
                        </AppButton>
                    </div>

                    <div v-if="form.parametros_observacoes.length === 0" class="classifications-observation-parameters__empty">
                        Nenhum parâmetro configurado para esta classificação.
                    </div>

                    <div v-else class="classifications-observation-parameters__list">
                        <article
                            v-for="(field, fieldIndex) in form.parametros_observacoes"
                            :key="field.id || `classification-parameter-${fieldIndex}`"
                            class="classifications-observation-parameter-card"
                        >
                            <div class="classifications-observation-parameter-card__grid">
                                <AppSelect
                                    v-model="field.nome_template"
                                    label="Nome do parâmetro"
                                    @update:model-value="onObservationTemplateChange(field)"
                                >
                                    <option v-for="template in observationFieldNameTemplates" :key="template.id" :value="template.id">
                                        {{ template.label }}
                                    </option>
                                </AppSelect>

                                <AppSelect
                                    v-model="field.tipo_campo"
                                    label="Tipo do campo"
                                    @update:model-value="onObservationTypeChange(field)"
                                >
                                    <option v-for="typeOption in observationFieldTypeOptions" :key="typeOption.id" :value="typeOption.id">
                                        {{ typeOption.label }}
                                    </option>
                                </AppSelect>

                                <AppInput
                                    v-model="field.ordem"
                                    label="Ordem"
                                    type="number"
                                    min="0"
                                    inputmode="numeric"
                                />
                            </div>

                            <AppInput
                                v-if="field.nome_template === 'personalizado'"
                                v-model="field.nome_personalizado"
                                label="Nome personalizado"
                                placeholder="Ex: Limão e gelo"
                            />

                            <AppInput
                                v-if="field.tipo_campo === 'checkbox_texto'"
                                v-model="field.texto_checkbox"
                                label="Texto ao lado do checkbox"
                                placeholder="Ex: Com limão e gelo"
                            />

                            <div class="classifications-observation-parameter-card__footer">
                                <AppCheckbox v-model="field.obrigatorio" label="Resposta obrigatória" />
                                <AppButton type="button" variant="danger" @click="removeObservationParameter(fieldIndex)">
                                    <Trash2 class="h-4 w-4" aria-hidden="true" />
                                    Remover
                                </AppButton>
                            </div>
                        </article>
                    </div>
                </section>
            </div>

            <p v-if="submitError" class="text-danger text-sm mt-3">{{ submitError }}</p>
            <p v-else-if="formErrors.parametros_observacoes" class="text-danger text-sm mt-3">{{ formErrors.parametros_observacoes }}</p>

            <div class="classifications-form-actions">
                <AppButton variant="secondary" @click="modalOpen = false">Cancelar</AppButton>
                <AppButton :loading="saving" @click="save">Salvar</AppButton>
            </div>
        </AppModal>
    </div>
</template>

<style scoped>
.classifications-breadcrumb {
    margin: 0;
    font-size: 0.82rem;
    font-weight: 700;
    color: color-mix(in srgb, var(--color-text-muted) 86%, transparent);
}

.classifications-header-actions {
    display: inline-flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 0.6rem;
}

.classifications-notice {
    color: var(--color-text);
}

.classifications-grid-shell {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.classifications-grid-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}

.classifications-grid-title {
    margin: 0;
    font-size: 2rem;
    line-height: 1.1;
    font-weight: 900;
    color: var(--color-text);
}

.classifications-grid-subtitle {
    margin: 0.35rem 0 0;
    color: var(--color-text-muted);
}

.classifications-view-mode {
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 48%, transparent);
    border-radius: var(--radius-sm);
    background: color-mix(in srgb, var(--color-bg-surface) 85%, transparent);
    padding: 0.22rem;
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
}

.classifications-view-btn {
    border: 0;
    border-radius: calc(var(--radius-sm) - 0.2rem);
    background: transparent;
    color: var(--color-text-muted);
    min-height: 2.15rem;
    padding: 0 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-weight: 700;
    cursor: pointer;
    transition: all var(--transition-fast);
}

.classifications-view-btn.is-active {
    background: color-mix(in srgb, var(--color-primary) 75%, white 25%);
    color: var(--color-text-inverse);
}

.classifications-filters {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
}

.classifications-filters-label {
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    font-size: 0.76rem;
    color: color-mix(in srgb, var(--color-text-muted) 80%, transparent);
    font-weight: 800;
}

.classifications-filters-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 0.75rem;
}

.classifications-search {
    min-width: 0;
}

.classifications-filter-checks {
    display: inline-flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.85rem;
}

.classifications-loading,
.classifications-empty {
    border: 1px dashed color-mix(in srgb, var(--color-border-strong) 45%, transparent);
    border-radius: var(--radius-md);
    padding: 1rem;
    color: var(--color-text-muted);
    text-align: center;
}

.classifications-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.classifications-item-card {
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 42%, transparent);
    border-radius: var(--radius-md);
    padding: 1rem;
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 1rem;
    cursor: pointer;
    transition: all var(--transition-fast);
    margin-left: calc(var(--level-depth, 0) * 1.4rem);
}

.classifications-item-card.is-child-level {
    border-left-width: 3px;
    border-left-color: color-mix(in srgb, var(--color-primary) 55%, transparent);
    background: color-mix(in srgb, var(--color-primary) 7%, var(--color-bg-surface));
}

.classifications-item-card:hover {
    border-color: color-mix(in srgb, var(--color-primary) 40%, transparent);
}

.classifications-item-card.is-selected {
    border-color: color-mix(in srgb, var(--color-primary) 58%, transparent);
    background: color-mix(in srgb, var(--color-primary) 10%, var(--color-bg-surface));
}

.classifications-item-card.is-deleted {
    opacity: 0.75;
}

.classifications-item-title-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.6rem;
}

.classifications-item-title {
    margin: 0;
    font-size: 1.1rem;
    color: var(--color-text);
}

.classifications-item-badges {
    display: inline-flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.45rem;
}

.classifications-item-description {
    margin: 0.75rem 0 0;
    color: var(--color-text-muted);
}

.classifications-item-parent {
    margin: 0.6rem 0 0;
    font-size: 0.85rem;
    color: color-mix(in srgb, var(--color-primary) 58%, white 42%);
    text-transform: uppercase;
    letter-spacing: 0.03em;
    font-weight: 700;
}

.classifications-item-actions {
    display: inline-flex;
    justify-content: flex-end;
    align-items: flex-start;
    gap: 0.5rem;
}

.classifications-org-shell {
    position: relative;
    border: 1px solid color-mix(in srgb, var(--color-border) 90%, transparent);
    border-radius: 0.8rem;
    padding: 1rem;
    background: color-mix(in srgb, var(--color-bg-surface) 96%, #060a11);
}

.classifications-org-blur-layer {
    transition: filter 160ms ease, opacity 160ms ease;
}

.classifications-org-shell.has-open-drawer .classifications-org-blur-layer {
    filter: blur(3px);
    opacity: 0.42;
    pointer-events: none;
}

.classifications-drawer-backdrop {
    position: fixed;
    inset: 0;
    z-index: 55;
    border: 0;
    background: color-mix(in srgb, #000 62%, transparent);
    backdrop-filter: blur(5px);
    cursor: default;
}

.classifications-org-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 0.85rem;
}

.classifications-org-header h3 {
    margin: 0;
    color: var(--color-text);
    font-size: 1.08rem;
    font-weight: 800;
}

.classifications-org-header p {
    margin: 0.18rem 0 0;
    color: var(--color-text-muted);
    font-size: 0.9rem;
}

.classifications-org-toolbar {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.classifications-org-tool {
    border: 1px solid color-mix(in srgb, var(--color-border) 86%, transparent);
    border-radius: 0.5rem;
    min-height: 2.25rem;
    padding: 0 0.78rem;
    background: color-mix(in srgb, var(--color-bg-surface) 88%, #101622);
    color: var(--color-text);
    font-weight: 700;
    cursor: pointer;
}

.classifications-org-tool:hover {
    border-color: color-mix(in srgb, var(--color-primary) 44%, var(--color-border));
    background: color-mix(in srgb, var(--color-primary) 9%, var(--color-bg-surface));
}

.classifications-org-zoom {
    color: var(--color-text-muted);
    font-size: 0.85rem;
    font-weight: 700;
    padding-left: 0.25rem;
}

.classifications-org-path {
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

.classifications-org-path strong {
    color: var(--color-text);
}

.classifications-org-canvas {
    position: relative;
    height: 32rem;
    overflow: hidden;
    border: 1px solid color-mix(in srgb, var(--color-border) 84%, transparent);
    border-radius: 0.78rem;
    background:
        radial-gradient(circle, color-mix(in srgb, var(--color-text-muted) 30%, transparent) 1px, transparent 1px) 0 0 / 24px 24px,
        color-mix(in srgb, var(--color-bg-app) 82%, #05070c);
    cursor: grab;
    touch-action: none;
}

.classifications-org-canvas:active {
    cursor: grabbing;
}

.classifications-org-stage {
    position: absolute;
    inset: 0;
    width: 2000px;
    height: 1200px;
    transform-origin: 0 0;
}

.classifications-org-edges {
    position: absolute;
    inset: 0;
    width: 2000px;
    height: 1200px;
    overflow: visible;
    pointer-events: none;
}

.classifications-org-edge-halo,
.classifications-org-edge {
    fill: none;
    stroke-linecap: round;
}

.classifications-org-edge-halo {
    stroke: color-mix(in srgb, var(--color-bg-surface) 70%, #ffffff);
    stroke-width: 7;
    opacity: 0.9;
}

.classifications-org-edge {
    stroke: var(--edge-color, var(--color-primary));
    stroke-width: 3.5;
    opacity: 0.94;
    filter: drop-shadow(0 1px 1px color-mix(in srgb, #000 22%, transparent))
        drop-shadow(0 0 5px color-mix(in srgb, var(--edge-color, var(--color-primary)) 22%, transparent));
}

.classifications-org-node {
    position: absolute;
    width: 300px;
    min-height: 154px;
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

.classifications-org-node:hover,
.classifications-org-node.is-selected {
    border-color: var(--node-color, var(--color-primary));
    box-shadow:
        0 0 0 4px color-mix(in srgb, var(--node-color, var(--color-primary)) 18%, transparent),
        0 18px 34px color-mix(in srgb, #000 34%, transparent);
}

.classifications-org-node.is-inactive {
    opacity: 0.72;
}

.classifications-org-node h4 {
    margin: 0;
    font-size: 1.01rem;
    font-weight: 800;
}

.classifications-org-tags {
    margin-top: 0.55rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.classifications-org-tags span {
    border: 1px solid color-mix(in srgb, var(--node-color, var(--color-primary)) 44%, transparent);
    border-radius: 999px;
    padding: 0.14rem 0.48rem;
    font-size: 0.72rem;
    font-weight: 700;
    color: color-mix(in srgb, var(--node-color, var(--color-primary)) 72%, white 28%);
}

.classifications-org-node p {
    margin: 0.42rem 0 0;
    color: var(--color-text-muted);
    font-size: 0.78rem;
}

.classifications-org-hover {
    position: absolute;
    z-index: 38;
    width: 260px;
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 54%, transparent);
    background: color-mix(in srgb, var(--color-bg-surface) 92%, #091020);
    border-radius: 0.7rem;
    padding: 0.68rem 0.78rem;
    box-shadow: 0 20px 34px color-mix(in srgb, #000 40%, transparent);
    pointer-events: none;
}

.classifications-org-hover h4 {
    margin: 0;
    font-size: 0.92rem;
}

.classifications-org-hover-grid {
    margin-top: 0.5rem;
    display: grid;
    gap: 0.3rem;
    color: var(--color-text-muted);
    font-size: 0.76rem;
}

.classifications-node-spotlight {
    position: fixed;
    left: clamp(1rem, 8vw, 7rem);
    top: 50%;
    transform: translateY(-50%);
    z-index: 65;
    width: min(23rem, calc(100vw - 32rem));
    min-width: 18rem;
    border: 2px solid var(--node-color, var(--color-primary));
    border-radius: 1rem;
    background: color-mix(in srgb, var(--color-bg-surface) 94%, #050913);
    box-shadow: 0 26px 48px color-mix(in srgb, #000 50%, transparent);
    padding: 1rem 1.1rem;
}

.classifications-node-spotlight__eyebrow {
    display: inline-flex;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: color-mix(in srgb, var(--node-color, var(--color-primary)) 72%, white 28%);
}

.classifications-node-spotlight h4 {
    margin: 0.52rem 0 0;
    font-size: 1.1rem;
}

.classifications-node-spotlight p {
    margin: 0.62rem 0 0;
    color: var(--color-text-muted);
}

.classifications-node-drawer {
    position: fixed;
    top: 5.3rem;
    right: 1.2rem;
    width: min(28rem, calc(100vw - 2.4rem));
    max-height: calc(100vh - 6.6rem);
    overflow: auto;
    z-index: 68;
    background: color-mix(in srgb, var(--color-bg-surface) 97%, #050913);
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 56%, transparent);
    border-radius: 1.05rem;
    box-shadow: 0 28px 50px color-mix(in srgb, #000 58%, transparent);
    padding: 1rem 1.05rem 1.2rem;
}

.classifications-node-drawer__close {
    float: right;
    border: 0;
    width: 1.9rem;
    height: 1.9rem;
    border-radius: 0.5rem;
    background: color-mix(in srgb, var(--color-bg-surface) 84%, #0f1624);
    color: var(--color-text-muted);
    font-weight: 900;
    cursor: pointer;
}

.classifications-node-drawer h3 {
    margin: 0;
    font-size: 1.02rem;
    font-weight: 800;
}

.classifications-node-drawer p {
    margin: 0.5rem 0 0;
    color: var(--color-text-muted);
}

.classifications-node-detail-card {
    margin-top: 0.84rem;
    border: 1px solid color-mix(in srgb, var(--color-border) 90%, transparent);
    border-radius: 0.78rem;
    padding: 0.72rem 0.8rem;
    background: color-mix(in srgb, var(--color-bg-app) 84%, transparent);
}

.classifications-node-detail-card p {
    margin: 0.42rem 0 0;
    font-size: 0.83rem;
}

.classifications-node-detail-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.52rem;
}

.classifications-node-detail-title h4 {
    margin: 0;
    font-size: 0.96rem;
}

.classifications-node-detail-title span {
    font-size: 0.74rem;
    font-weight: 700;
    color: var(--color-text-muted);
}

.classifications-node-drawer-actions {
    margin-top: 0.85rem;
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.5rem;
}

:deep(.classifications-modal-width) {
    width: min(960px, calc(100vw - 2.5rem));
}

.classifications-form-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 0.9rem;
}

.classifications-observation-parameters {
    grid-column: 1 / -1;
    border: 1px solid color-mix(in srgb, var(--color-border) 86%, transparent);
    border-radius: 0.8rem;
    padding: 0.85rem;
    display: grid;
    gap: 0.8rem;
    background: color-mix(in srgb, var(--color-bg-app) 84%, transparent);
}

.classifications-observation-parameters__header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.6rem;
    flex-wrap: wrap;
}

.classifications-observation-parameters__header h4 {
    margin: 0;
    color: var(--color-text);
    font-size: 0.96rem;
}

.classifications-observation-parameters__header p {
    margin: 0.3rem 0 0;
    color: var(--color-text-muted);
    font-size: 0.82rem;
}

.classifications-observation-parameters__empty {
    border: 1px dashed color-mix(in srgb, var(--color-border-strong) 50%, transparent);
    border-radius: 0.65rem;
    padding: 0.85rem;
    color: var(--color-text-muted);
    font-size: 0.84rem;
}

.classifications-observation-parameters__list {
    display: grid;
    gap: 0.7rem;
}

.classifications-observation-parameter-card {
    border: 1px solid color-mix(in srgb, var(--color-border) 82%, transparent);
    border-radius: 0.72rem;
    padding: 0.72rem;
    display: grid;
    gap: 0.62rem;
}

.classifications-observation-parameter-card__grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 0.62rem;
}

.classifications-observation-parameter-card__footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
}

.classifications-checkbox-wrap {
    display: flex;
    align-items: flex-end;
    min-height: 100%;
}

.classifications-form-actions {
    margin-top: 1rem;
    display: inline-flex;
    width: 100%;
    justify-content: flex-end;
    gap: 0.6rem;
}

@media (min-width: 900px) {
    .classifications-filters-row {
        grid-template-columns: minmax(0, 1fr) auto auto;
        align-items: end;
    }

    .classifications-item-card {
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: flex-start;
    }

    .classifications-form-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .classifications-observation-parameter-card__grid {
        grid-template-columns: 1.2fr 1fr 0.55fr;
    }
}
</style>
