<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { Eye, PencilLine } from 'lucide-vue-next';
import { useRouter } from 'vue-router';
import api from '../../lib/api';
import SettingsPageHeader from '../../components/settings/SettingsPageHeader.vue';
import SettingsFilterBar from '../../components/settings/SettingsFilterBar.vue';
import SettingsTableCard from '../../components/settings/SettingsTableCard.vue';
import SettingsEmptyState from '../../components/settings/SettingsEmptyState.vue';
import AppSearchField from '../../components/ui/AppSearchField.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppTable from '../../components/ui/AppTable.vue';
import AppBadge from '../../components/ui/AppBadge.vue';
import AppIconButton from '../../components/ui/AppIconButton.vue';
import AppModal from '../../components/ui/AppModal.vue';
import AppInput from '../../components/ui/AppInput.vue';
import AppTextarea from '../../components/ui/AppTextarea.vue';
import AppButton from '../../components/ui/AppButton.vue';

const router = useRouter();

const loading = ref(false);
const savingAdjustment = ref(false);
const error = ref('');
const submitError = ref('');
const products = ref([]);
const categories = ref([]);

const search = ref('');
const selectedCategory = ref('todos');
const selectedStatus = ref('todos');

const detailsModalOpen = ref(false);
const detailsProduct = ref(null);
const detailsPayload = ref(null);
const detailsMovements = ref([]);
const detailsLoading = ref(false);
const detailsError = ref('');

const adjustModalOpen = ref(false);
const adjustProduct = ref(null);
const formErrors = reactive({
    newQuantity: '',
});
const adjustForm = reactive({
    newQuantity: '',
    tipo: 'correcao',
    complemento: '',
});

const categoryNameById = computed(() => {
    const map = new Map();

    categories.value.forEach((item) => {
        map.set(String(item.id), String(item.nome || '').trim());
    });

    return map;
});

const categoryOptions = computed(() => [
    { id: 'todos', nome: 'Todas categorias' },
    ...categories.value.map((item) => ({
        id: String(item.id),
        nome: item.nome,
    })),
]);

const statusOptions = [
    { id: 'todos', nome: 'Todos' },
    { id: 'normal', nome: 'Normal' },
    { id: 'baixo', nome: 'Baixo' },
    { id: 'critico', nome: 'Crítico' },
    { id: 'sem_estoque', nome: 'Sem estoque' },
    { id: 'inativo', nome: 'Inativo' },
];

const filteredProducts = computed(() => {
    const needle = String(search.value || '').trim().toLowerCase();

    return products.value.filter((item) => {
        if (selectedCategory.value !== 'todos' && String(item.category_id || '') !== String(selectedCategory.value)) {
            return false;
        }

        const statusKey = resolveStockStatus(item).key;
        if (selectedStatus.value !== 'todos' && selectedStatus.value !== statusKey) {
            return false;
        }

        if (!needle) return true;

        const productName = String(item.nome || '').toLowerCase();
        const productCode = String(item.codigo || '').toLowerCase();

        return productName.includes(needle) || productCode.includes(needle);
    });
});

function resolveStockStatus(item) {
    if (!item?.ativo) {
        return { key: 'inativo', label: 'Inativo', variant: 'default' };
    }

    const current = Number(item?.estoque_atual || 0);
    const minimum = Number(item?.estoque_minimo || 0);

    if (current <= 0) {
        return { key: 'sem_estoque', label: 'Sem estoque', variant: 'danger' };
    }

    if (minimum > 0 && current < minimum) {
        if (current <= minimum * 0.5) {
            return { key: 'critico', label: 'Crítico', variant: 'danger' };
        }
        return { key: 'baixo', label: 'Baixo estoque', variant: 'warning' };
    }

    return { key: 'normal', label: 'Normal', variant: 'success' };
}

function formatQuantity(value, unit = 'UN') {
    const normalizedUnit = String(unit || 'UN').toUpperCase();
    const precision = normalizedUnit === 'KG' ? 3 : 0;

    return Number(value || 0).toLocaleString('pt-BR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: precision,
    });
}

function formatDateTime(value) {
    const source = String(value || '').trim();
    if (!source) return '—';
    const date = new Date(source);
    if (Number.isNaN(date.getTime())) return source;

    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

function movementTypeLabel(type) {
    const labels = {
        venda: 'Venda',
        entrada: 'Entrada',
        saida: 'Saída',
        ajuste: 'Ajuste',
        inventario: 'Inventário',
        correcao: 'Correção',
        avaria: 'Avaria',
        quebra: 'Quebra',
        outro: 'Outro',
    };
    return labels[String(type || '').toLowerCase()] || 'Movimentação';
}

function buildStockRowFromCatalogProduct(payload, fallback = null) {
    if (!payload || typeof payload !== 'object') {
        return fallback;
    }

    const stock = payload?.estoque || {};

    return {
        id: payload?.id || fallback?.id,
        nome: String(payload?.descricao || fallback?.nome || ''),
        codigo: String(payload?.cod_sku || payload?.codigo_operacional || fallback?.codigo || ''),
        sku: String(payload?.cod_sku || ''),
        codigo_operacional: String(payload?.codigo_operacional || ''),
        category_id: payload?.produto_familia_id || fallback?.category_id || null,
        category_name: String(payload?.familia?.nome || fallback?.category_name || ''),
        unidade: String(payload?.unidade_medida?.unidade || fallback?.unidade || 'UN'),
        estoque_atual: Number(stock?.quantidade ?? fallback?.estoque_atual ?? 0),
        estoque_minimo: stock?.quantidade_minima == null
            ? (fallback?.estoque_minimo == null ? null : Number(fallback.estoque_minimo))
            : Number(stock.quantidade_minima),
        ativo: String(payload?.situacao || fallback?.situacao || 'ativo').toLowerCase() !== 'inativo',
        situacao: String(payload?.situacao || fallback?.situacao || 'ativo'),
        updated_at: payload?.updated_at || fallback?.updated_at || '',
    };
}

async function openDetails(item) {
    detailsProduct.value = item;
    detailsPayload.value = null;
    detailsMovements.value = [];
    detailsError.value = '';
    detailsModalOpen.value = true;

    detailsLoading.value = true;
    try {
        const [productRes, movementsRes] = await Promise.all([
            api.get(`/catalog/products/${item.id}`),
            api.get('/stock-movements', {
                params: { product_id: item.id },
            }),
        ]);

        const payload = productRes?.data || null;
        detailsPayload.value = payload;
        detailsProduct.value = buildStockRowFromCatalogProduct(payload, item);
        detailsMovements.value = Array.isArray(movementsRes?.data) ? movementsRes.data : [];
    } catch (requestError) {
        detailsError.value = requestError?.response?.data?.message ?? 'Não foi possível carregar os detalhes do produto.';
    } finally {
        detailsLoading.value = false;
    }
}

function closeDetails() {
    detailsModalOpen.value = false;
    detailsProduct.value = null;
    detailsPayload.value = null;
    detailsMovements.value = [];
    detailsError.value = '';
    detailsLoading.value = false;
}

function openMovementHistoryFromDetails() {
    if (!detailsProduct.value?.id) return;
    const productId = detailsProduct.value.id;
    closeDetails();
    router.push({
        path: '/configuracoes/estoque/movimentacoes',
        query: {
            product_id: productId,
        },
    });
}

function openAdjustFromDetails() {
    if (!detailsProduct.value) return;
    const product = detailsProduct.value;
    closeDetails();
    openAdjustModal(product);
}

function openAdjustModal(item) {
    adjustProduct.value = item;
    adjustForm.newQuantity = String(item?.estoque_atual ?? 0);
    adjustForm.tipo = 'correcao';
    adjustForm.complemento = '';
    submitError.value = '';
    formErrors.newQuantity = '';
    adjustModalOpen.value = true;
}

function closeAdjustModal() {
    adjustModalOpen.value = false;
    adjustProduct.value = null;
    submitError.value = '';
    formErrors.newQuantity = '';
}

const detailsStockStatus = computed(() => {
    return detailsProduct.value ? resolveStockStatus(detailsProduct.value) : { key: 'normal', label: 'Normal', variant: 'success' };
});

const detailsCategoryLabel = computed(() => {
    if (!detailsProduct.value) return 'Sem categoria';
    return detailsProduct.value.category_name || categoryNameById.value.get(String(detailsProduct.value.category_id || '')) || 'Sem categoria';
});

const detailsStockDifference = computed(() => {
    if (!detailsProduct.value) return 0;
    const current = Number(detailsProduct.value.estoque_atual || 0);
    const minimum = Number(detailsProduct.value.estoque_minimo || 0);
    return current - minimum;
});

const detailsStockDifferenceLabel = computed(() => {
    if (!detailsProduct.value) return '—';
    if (detailsProduct.value.estoque_minimo == null) return 'Sem mínimo definido';
    const unit = detailsProduct.value.unidade || 'UN';
    const diff = detailsStockDifference.value;
    const abs = formatQuantity(Math.abs(diff), unit);
    if (diff >= 0) return `+${abs}`;
    return `-${abs}`;
});

const detailsStockDifferenceCaption = computed(() => {
    if (!detailsProduct.value) return 'Comparativo';
    if (detailsProduct.value.estoque_minimo == null) return 'Sem referência';
    return detailsStockDifference.value >= 0 ? 'Acima do mínimo' : 'Abaixo do mínimo';
});

const detailsSummaryText = computed(() => {
    if (!detailsProduct.value) return '—';

    const unit = String(detailsProduct.value.unidade || 'UN').toUpperCase();
    const diff = detailsStockDifference.value;
    const abs = formatQuantity(Math.abs(diff), unit);

    if (detailsStockStatus.value.key === 'inativo') {
        return 'O produto está inativo no cadastro e deve ser conferido antes de novas movimentações.';
    }

    if (detailsStockStatus.value.key === 'sem_estoque') {
        if (detailsProduct.value.estoque_minimo == null) {
            return 'O produto está sem estoque no momento e não possui estoque mínimo configurado.';
        }
        return `O produto está sem estoque no momento e ${abs} ${unit} abaixo do estoque mínimo.`;
    }

    if (detailsProduct.value.estoque_minimo == null) {
        return 'O produto não possui estoque mínimo configurado. Recomenda-se definir um valor de referência.';
    }

    if (detailsStockStatus.value.key === 'critico') {
        return `O produto está em nível crítico, ${abs} ${unit} abaixo do estoque mínimo.`;
    }

    if (detailsStockStatus.value.key === 'baixo') {
        return `O produto está com baixo estoque, ${abs} ${unit} abaixo do estoque mínimo.`;
    }

    return `O produto está com estoque normal, ${abs} ${unit} acima do estoque mínimo.`;
});

const detailsLastAuditUser = computed(() => {
    const firstAudit = Array.isArray(detailsPayload.value?.auditoria) ? detailsPayload.value.auditoria[0] : null;
    return String(firstAudit?.usuario || '').trim() || '—';
});

const detailsDataRows = computed(() => {
    if (!detailsProduct.value) return [];

    const item = detailsProduct.value;
    const skuValue = [String(item.sku || '').trim(), String(item.codigo_operacional || '').trim()].filter(Boolean).join(' / ');

    return [
        { label: 'Nome', value: item.nome || '—' },
        { label: 'Código/SKU', value: skuValue || item.codigo || '—' },
        { label: 'Categoria', value: detailsCategoryLabel.value || '—' },
        { label: 'Unidade', value: String(item.unidade || 'UN').toUpperCase() },
        { label: 'Estoque atual', value: `${formatQuantity(item.estoque_atual, item.unidade)} ${String(item.unidade || 'UN').toUpperCase()}` },
        { label: 'Estoque mínimo', value: item.estoque_minimo == null ? '—' : `${formatQuantity(item.estoque_minimo, item.unidade)} ${String(item.unidade || 'UN').toUpperCase()}` },
        { label: 'Status', value: detailsStockStatus.value.label },
        { label: 'Última atualização', value: formatDateTime(item.updated_at || detailsPayload.value?.updated_at) },
        { label: 'Responsável última alteração', value: detailsLastAuditUser.value },
    ];
});

const detailsRecentMovements = computed(() => {
    return (Array.isArray(detailsMovements.value) ? detailsMovements.value : [])
        .map((movement) => {
            const quantity = Number(movement?.quantidade_movimentada || 0);
            const unit = String(
                movement?.product?.unidade
                || detailsProduct.value?.unidade
                || 'UN',
            ).toUpperCase();
            const happenedAt = movement?.happened_at || movement?.created_at || null;
            const creatorName = String(movement?.creator?.name || '').trim() || 'Sistema';

            return {
                id: String(movement?.id || crypto?.randomUUID?.() || Math.random()),
                typeLabel: movementTypeLabel(movement?.tipo),
                quantity,
                quantityLabel: `${quantity >= 0 ? '+' : '-'}${formatQuantity(Math.abs(quantity), unit)} ${unit}`,
                tone: quantity > 0 ? 'positive' : quantity < 0 ? 'negative' : 'neutral',
                happenedAt,
                happenedLabel: formatDateTime(happenedAt),
                responsible: creatorName,
                note: String(movement?.descricao || movement?.observacao || '').trim() || null,
            };
        })
        .sort((left, right) => new Date(right.happenedAt || 0) - new Date(left.happenedAt || 0))
        .slice(0, 8);
});

function validateAdjustmentForm() {
    formErrors.newQuantity = '';

    const currentQuantity = Number(adjustProduct.value?.estoque_atual || 0);
    const newQuantity = Number(adjustForm.newQuantity);

    if (!Number.isFinite(newQuantity) || newQuantity < 0) {
        formErrors.newQuantity = 'Informe uma quantidade válida maior ou igual a zero.';
        return false;
    }

    if (Math.abs(newQuantity - currentQuantity) < 0.000001) {
        formErrors.newQuantity = 'A nova quantidade deve ser diferente da atual.';
        return false;
    }

    return true;
}

async function submitAdjustmentRequest() {
    if (!adjustProduct.value) return;
    submitError.value = '';

    if (!validateAdjustmentForm()) return;

    savingAdjustment.value = true;
    try {
        const payload = {
            product_id: adjustProduct.value.id,
            tipo: adjustForm.tipo,
            nova_quantidade: Number(adjustForm.newQuantity),
            complemento: adjustForm.complemento.trim() || null,
        };

        const { data } = await api.post('/stock-adjustments', payload);

        closeAdjustModal();
        await router.push({
            path: '/configuracoes/estoque/ajustes',
            query: {
                created: data.id,
            },
        });
    } catch (requestError) {
        const validationErrors = requestError?.response?.data?.errors || {};
        if (Array.isArray(validationErrors.nova_quantidade) && validationErrors.nova_quantidade.length) {
            formErrors.newQuantity = validationErrors.nova_quantidade[0];
        }

        submitError.value = requestError?.response?.data?.message ?? 'Não foi possível enviar a solicitação de ajuste.';
    } finally {
        savingAdjustment.value = false;
    }
}

async function load() {
    loading.value = true;
    error.value = '';

    try {
        const [productsRes, supportRes] = await Promise.all([
            api.get('/catalog/products', { params: { per_page: 300 } }),
            api.get('/catalog/products/support-data'),
        ]);

        const rawProducts = Array.isArray(productsRes?.data?.data)
            ? productsRes.data.data
            : Array.isArray(productsRes?.data)
            ? productsRes.data
            : [];

        const families = Array.isArray(supportRes?.data?.familias) ? supportRes.data.familias : [];

        products.value = rawProducts.map((item) => ({
            id: item?.id,
            nome: String(item?.descricao || ''),
            codigo: String(item?.cod_sku || item?.codigo_operacional || ''),
            sku: String(item?.cod_sku || ''),
            codigo_operacional: String(item?.codigo_operacional || ''),
            category_id: item?.familia?.id || null,
            category_name: String(item?.familia?.nome || ''),
            unidade: String(item?.unidade_medida?.unidade || 'UN'),
            estoque_atual: Number(item?.estoque_atual || 0),
            estoque_minimo: item?.estoque_minimo == null ? null : Number(item.estoque_minimo),
            ativo: String(item?.situacao || 'ativo').toLowerCase() !== 'inativo',
            situacao: String(item?.situacao || 'ativo'),
            updated_at: item?.updated_at || '',
        }));

        categories.value = families.map((item) => ({
            id: String(item?.id || ''),
            nome: String(item?.nome || ''),
        }));
    } catch (requestError) {
        error.value = requestError?.response?.data?.message ?? 'Falha ao carregar consulta de estoque.';
        products.value = [];
        categories.value = [];
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="space-y-4">
        <SettingsPageHeader title="Consulta de Estoque" subtitle="Pesquise e filtre os produtos cadastrados" />

        <SettingsFilterBar>
            <div class="consulta-search">
                <AppSearchField v-model="search" placeholder="Buscar por nome ou código..." />
            </div>

            <div class="consulta-select">
                <AppSelect v-model="selectedCategory">
                    <option
                        v-for="option in categoryOptions"
                        :key="option.id"
                        :value="option.id"
                    >
                        {{ option.nome }}
                    </option>
                </AppSelect>
            </div>

            <div class="consulta-select-sm">
                <AppSelect v-model="selectedStatus">
                    <option
                        v-for="option in statusOptions"
                        :key="option.id"
                        :value="option.id"
                    >
                        {{ option.nome }}
                    </option>
                </AppSelect>
            </div>
        </SettingsFilterBar>

        <SettingsTableCard>
            <AppTable>
                <thead>
                    <tr>
                        <th class="text-left">Produto</th>
                        <th class="text-left">Código</th>
                        <th class="text-left">Categoria</th>
                        <th class="text-right">Estoque Atual</th>
                        <th class="text-right">Estoque Mínimo</th>
                        <th class="text-center">Status</th>
                        <th class="text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="7" class="text-center text-muted py-6">Carregando produtos...</td>
                    </tr>
                    <tr v-else-if="error">
                        <td colspan="7" class="text-center text-danger py-6">{{ error }}</td>
                    </tr>
                    <tr v-else-if="filteredProducts.length === 0">
                        <td colspan="7" class="p-0">
                            <SettingsEmptyState
                                title="Nenhum produto encontrado"
                                description="Ajuste os filtros para localizar itens na consulta de estoque."
                            />
                        </td>
                    </tr>
                    <tr v-for="item in filteredProducts" :key="item.id">
                        <td class="font-semibold text-main">{{ item.nome }}</td>
                        <td class="text-muted">{{ item.codigo || '—' }}</td>
                        <td class="text-muted">{{ categoryNameById.get(String(item.category_id || '')) || 'Sem categoria' }}</td>
                        <td class="text-right font-semibold">{{ formatQuantity(item.estoque_atual, item.unidade) }}</td>
                        <td class="text-right">{{ item.estoque_minimo == null ? '—' : formatQuantity(item.estoque_minimo, item.unidade) }}</td>
                        <td class="text-center">
                            <AppBadge :variant="resolveStockStatus(item).variant">
                                {{ resolveStockStatus(item).label }}
                            </AppBadge>
                        </td>
                        <td class="text-right">
                            <div class="consulta-actions">
                                <AppIconButton title="Ver detalhes" @click="openDetails(item)">
                                    <Eye class="h-4 w-4" aria-hidden="true" />
                                </AppIconButton>
                                <AppIconButton title="Solicitar ajuste" @click="openAdjustModal(item)">
                                    <PencilLine class="h-4 w-4" aria-hidden="true" />
                                </AppIconButton>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </AppTable>
        </SettingsTableCard>

        <AppModal
            :open="detailsModalOpen"
            title="Ficha de Conferência do Produto"
            width-class="stock-view-modal-width"
            @close="closeDetails"
        >
            <div v-if="detailsProduct" class="consulta-view-shell">
                <header class="consulta-view-header">
                    <div>
                        <p class="consulta-view-eyebrow">Conferência rápida</p>
                        <h3 class="consulta-view-title">{{ detailsProduct.nome }}</h3>
                        <p class="consulta-view-subtitle">
                            {{ detailsProduct.codigo || 'Sem código' }}
                        </p>
                    </div>
                    <div class="consulta-view-header-badges">
                        <AppBadge variant="default">{{ detailsCategoryLabel }}</AppBadge>
                        <AppBadge :variant="detailsProduct.ativo ? 'success' : 'default'">
                            {{ detailsProduct.situacao || (detailsProduct.ativo ? 'Ativo' : 'Inativo') }}
                        </AppBadge>
                        <AppBadge :variant="detailsStockStatus.variant">{{ detailsStockStatus.label }}</AppBadge>
                    </div>
                </header>

                <div v-if="detailsLoading" class="consulta-view-loading">Carregando ficha do produto...</div>
                <div v-else-if="detailsError" class="consulta-view-error">{{ detailsError }}</div>

                <template v-else>
                    <section class="consulta-kpi-grid">
                        <article class="consulta-kpi-card">
                            <span>Estoque atual</span>
                            <strong>{{ formatQuantity(detailsProduct.estoque_atual, detailsProduct.unidade) }}</strong>
                            <small>{{ String(detailsProduct.unidade || 'UN').toUpperCase() }}</small>
                        </article>
                        <article class="consulta-kpi-card">
                            <span>Estoque mínimo</span>
                            <strong>{{ detailsProduct.estoque_minimo == null ? '—' : formatQuantity(detailsProduct.estoque_minimo, detailsProduct.unidade) }}</strong>
                            <small>{{ String(detailsProduct.unidade || 'UN').toUpperCase() }}</small>
                        </article>
                        <article class="consulta-kpi-card">
                            <span>{{ detailsStockDifferenceCaption }}</span>
                            <strong :class="{
                                'text-success': detailsStockDifference > 0,
                                'text-danger': detailsStockDifference < 0,
                            }">
                                {{ detailsStockDifferenceLabel }}
                            </strong>
                            <small>{{ String(detailsProduct.unidade || 'UN').toUpperCase() }}</small>
                        </article>
                        <article class="consulta-kpi-card">
                            <span>Status do estoque</span>
                            <strong>{{ detailsStockStatus.label }}</strong>
                            <small>{{ detailsProduct.ativo ? 'Produto ativo' : 'Produto inativo' }}</small>
                        </article>
                    </section>

                    <section class="consulta-summary-box">
                        <p>{{ detailsSummaryText }}</p>
                    </section>

                    <section class="consulta-data-card">
                        <div class="consulta-section-head">
                            <h4>Dados do Produto</h4>
                        </div>
                        <div class="consulta-data-grid">
                            <div v-for="row in detailsDataRows" :key="row.label" class="consulta-data-item">
                                <span>{{ row.label }}</span>
                                <strong>{{ row.value || '—' }}</strong>
                            </div>
                        </div>
                    </section>

                    <section class="consulta-movements-card">
                        <div class="consulta-section-head">
                            <h4>Últimas movimentações</h4>
                            <AppButton variant="secondary" @click="openMovementHistoryFromDetails">Ver histórico completo</AppButton>
                        </div>

                        <div v-if="detailsRecentMovements.length === 0" class="consulta-movements-empty">
                            <p>Nenhuma movimentação registrada para este produto.</p>
                        </div>
                        <div v-else class="consulta-movements-list">
                            <article
                                v-for="movement in detailsRecentMovements"
                                :key="movement.id"
                                class="consulta-movement-item"
                            >
                                <div class="consulta-movement-head">
                                    <span class="consulta-movement-type">{{ movement.typeLabel }}</span>
                                    <strong
                                        :class="{
                                            'text-success': movement.tone === 'positive',
                                            'text-danger': movement.tone === 'negative',
                                        }"
                                    >
                                        {{ movement.quantityLabel }}
                                    </strong>
                                </div>
                                <div class="consulta-movement-meta">
                                    <span>{{ movement.happenedLabel }}</span>
                                    <span>{{ movement.responsible }}</span>
                                </div>
                                <p v-if="movement.note" class="consulta-movement-note">{{ movement.note }}</p>
                            </article>
                        </div>
                    </section>
                </template>

                <footer class="consulta-view-actions">
                    <AppButton variant="secondary" @click="closeDetails">Fechar</AppButton>
                    <AppButton @click="openAdjustFromDetails">Solicitar ajuste</AppButton>
                </footer>
            </div>
        </AppModal>

        <AppModal
            :open="adjustModalOpen"
            title="Solicitar Ajuste de Estoque"
            width-class="max-w-2xl"
            @close="closeAdjustModal"
        >
            <div v-if="adjustProduct" class="space-y-4">
                <div>
                    <p class="consulta-detail-label">Produto</p>
                    <p class="consulta-detail-value">{{ adjustProduct.nome }}</p>
                </div>

                <AppInput
                    :model-value="formatQuantity(adjustProduct.estoque_atual, adjustProduct.unidade)"
                    label="Quantidade Atual"
                    disabled
                />

                <AppInput
                    v-model="adjustForm.newQuantity"
                    label="Nova Quantidade"
                    type="number"
                    min="0"
                    step="0.001"
                    :error="formErrors.newQuantity"
                />

                <AppSelect v-model="adjustForm.tipo" label="Tipo de Ajuste">
                    <option value="correcao">Correção</option>
                    <option value="entrada">Entrada</option>
                    <option value="saida">Saída</option>
                    <option value="inventario">Inventário</option>
                    <option value="avaria">Avaria</option>
                    <option value="quebra">Quebra</option>
                    <option value="outro">Outro</option>
                </AppSelect>

                <AppTextarea
                    v-model="adjustForm.complemento"
                    label="Complemento (opcional)"
                    rows="4"
                    placeholder="Descreva detalhes adicionais..."
                />

                <p v-if="submitError" class="text-sm text-danger">{{ submitError }}</p>

                <div class="consulta-modal-actions">
                    <AppButton variant="secondary" @click="closeAdjustModal">Cancelar</AppButton>
                    <AppButton :loading="savingAdjustment" @click="submitAdjustmentRequest">
                        Enviar Solicitação
                    </AppButton>
                </div>
            </div>
        </AppModal>
    </div>
</template>

<style scoped>
.consulta-search {
    flex: 1 1 20rem;
    min-width: 18rem;
}

.consulta-select {
    flex: 0 1 14rem;
    min-width: 12rem;
}

.consulta-select-sm {
    flex: 0 1 10rem;
    min-width: 9rem;
}

.consulta-actions {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.consulta-view-shell {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.consulta-view-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem;
    border: 1px solid color-mix(in srgb, var(--color-border) 88%, transparent);
    border-radius: 0.9rem;
    background: color-mix(in srgb, var(--color-bg-surface) 95%, #101827);
}

.consulta-view-eyebrow {
    margin: 0;
    font-size: 0.78rem;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--color-text-muted);
}

.consulta-view-title {
    margin: 0.2rem 0 0;
    font-size: 1.3rem;
    font-weight: 800;
    color: var(--color-text);
}

.consulta-view-subtitle {
    margin: 0.3rem 0 0;
    color: var(--color-text-muted);
    font-size: 0.92rem;
}

.consulta-view-header-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    justify-content: flex-end;
}

.consulta-view-loading,
.consulta-view-error {
    border: 1px solid color-mix(in srgb, var(--color-border) 88%, transparent);
    border-radius: 0.8rem;
    padding: 0.85rem 1rem;
}

.consulta-view-error {
    color: var(--color-danger);
}

.consulta-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.75rem;
}

.consulta-kpi-card {
    border: 1px solid color-mix(in srgb, var(--color-border) 88%, transparent);
    border-radius: 0.82rem;
    padding: 0.85rem;
    background: color-mix(in srgb, var(--color-bg-surface) 96%, #111b2d);
}

.consulta-kpi-card span {
    display: block;
    font-size: 0.78rem;
    color: var(--color-text-muted);
}

.consulta-kpi-card strong {
    display: block;
    margin-top: 0.38rem;
    font-size: 1.2rem;
    font-weight: 800;
    color: var(--color-text);
}

.consulta-kpi-card small {
    display: block;
    margin-top: 0.28rem;
    font-size: 0.76rem;
    color: var(--color-text-muted);
}

.consulta-summary-box {
    border: 1px solid color-mix(in srgb, var(--color-info) 35%, var(--color-border));
    border-radius: 0.85rem;
    background: color-mix(in srgb, var(--color-info) 12%, var(--color-bg-surface));
    padding: 0.9rem 1rem;
}

.consulta-summary-box p {
    margin: 0;
    color: var(--color-text);
    font-weight: 600;
}

.consulta-data-card,
.consulta-movements-card {
    border: 1px solid color-mix(in srgb, var(--color-border) 88%, transparent);
    border-radius: 0.9rem;
    padding: 0.95rem;
    background: color-mix(in srgb, var(--color-bg-surface) 95%, #111a2a);
}

.consulta-section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.6rem;
    margin-bottom: 0.8rem;
}

.consulta-section-head h4 {
    margin: 0;
    font-size: 1rem;
    font-weight: 800;
    color: var(--color-text);
}

.consulta-data-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.65rem;
}

.consulta-data-item {
    border: 1px solid color-mix(in srgb, var(--color-border) 86%, transparent);
    border-radius: 0.72rem;
    padding: 0.62rem 0.72rem;
}

.consulta-data-item span {
    display: block;
    font-size: 0.76rem;
    color: var(--color-text-muted);
}

.consulta-data-item strong {
    display: block;
    margin-top: 0.3rem;
    font-size: 0.95rem;
    color: var(--color-text);
    word-break: break-word;
}

.consulta-movements-empty {
    border: 1px dashed color-mix(in srgb, var(--color-border) 80%, transparent);
    border-radius: 0.8rem;
    padding: 1.1rem;
    text-align: center;
    color: var(--color-text-muted);
}

.consulta-movements-empty p {
    margin: 0;
}

.consulta-movements-list {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.7rem;
}

.consulta-movement-item {
    border: 1px solid color-mix(in srgb, var(--color-border) 86%, transparent);
    border-radius: 0.72rem;
    padding: 0.65rem 0.75rem;
    background: color-mix(in srgb, var(--color-bg-surface) 96%, #0f1a2b);
}

.consulta-movement-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.consulta-movement-type {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--color-text);
}

.consulta-movement-head strong {
    font-size: 0.9rem;
}

.consulta-movement-meta {
    margin-top: 0.35rem;
    display: flex;
    justify-content: space-between;
    gap: 0.5rem;
    color: var(--color-text-muted);
    font-size: 0.78rem;
}

.consulta-movement-note {
    margin: 0.45rem 0 0;
    color: var(--color-text);
    font-size: 0.83rem;
}

.consulta-view-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.55rem;
    padding-top: 0.25rem;
}

.consulta-detail-label {
    margin: 0;
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--color-text-muted);
}

.consulta-detail-value {
    margin: 0.15rem 0 0;
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--color-text);
}

.consulta-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
}

:deep(.stock-view-modal-width) {
    width: min(1040px, 94vw);
    max-width: min(1040px, 94vw);
}

@media (max-width: 860px) {
    .consulta-view-header {
        flex-direction: column;
    }

    .consulta-view-header-badges {
        justify-content: flex-start;
    }

    .consulta-kpi-grid,
    .consulta-data-grid,
    .consulta-movements-list {
        grid-template-columns: 1fr;
    }

    .consulta-section-head {
        flex-direction: column;
        align-items: flex-start;
    }

    .consulta-view-actions {
        flex-direction: column-reverse;
    }

    .consulta-view-actions :deep(.ui-btn) {
        width: 100%;
    }

    .consulta-search,
    .consulta-select,
    .consulta-select-sm {
        min-width: 100%;
    }
}
</style>
