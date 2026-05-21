<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { ArrowLeft, Plus, Trash2 } from 'lucide-vue-next';
import { useRoute, useRouter } from 'vue-router';
import api from '../../lib/api';
import SettingsPageHeader from '../../components/settings/SettingsPageHeader.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppInput from '../../components/ui/AppInput.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppTextarea from '../../components/ui/AppTextarea.vue';

const route = useRoute();
const router = useRouter();

const loading = ref(false);
const saving = ref(false);
const pageError = ref('');
const formError = ref('');

const suppliers = ref([]);
const products = ref([]);
const items = ref([]);

const productDraft = reactive({
    product_id: '',
    quantidade: '',
    custo_unitario: '',
});

const form = reactive({
    supplier_id: '',
    filial: '',
    data_compra: '',
    observacoes: '',
});

const isEditing = computed(() => Boolean(route.params.purchaseOrderId));
const pageTitle = computed(() => (isEditing.value ? 'Editar Compra' : 'Nova Compra'));
const pageSubtitle = computed(() => 'Pedido de compra — não movimenta estoque');

const summary = computed(() => {
    const totalItems = items.value.length;
    const totalQuantity = items.value.reduce((acc, item) => acc + Number(item.quantidade || 0), 0);
    const totalValue = items.value.reduce((acc, item) => acc + Number(item.total || 0), 0);

    return {
        totalItems,
        totalQuantity,
        totalValue,
    };
});

const canSave = computed(() => {
    return !loading.value
        && !saving.value
        && !!String(form.supplier_id || '').trim()
        && !!String(form.data_compra || '').trim()
        && items.value.length > 0;
});

const supplierOptions = computed(() => {
    return suppliers.value
        .filter((item) => !!item?.id && !!item?.nome)
        .sort((left, right) => String(left.nome).localeCompare(String(right.nome), 'pt-BR'));
});

const productOptions = computed(() => {
    return products.value
        .filter((item) => !!item?.id && !!item?.nome)
        .sort((left, right) => String(left.nome).localeCompare(String(right.nome), 'pt-BR'));
});

function formatCurrency(value) {
    return Number(value || 0).toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    });
}

function formatQuantity(value) {
    return Number(value || 0).toLocaleString('pt-BR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 3,
    });
}

function parseNumberInput(value) {
    const normalized = String(value ?? '')
        .trim()
        .replace(/\s+/g, '')
        .replace(',', '.');

    if (!normalized) return null;

    const numeric = Number(normalized);
    if (!Number.isFinite(numeric)) return null;

    return numeric;
}

function buildItemRow(product, quantidade, custoUnitario) {
    const quantityValue = Math.round(quantidade * 1000) / 1000;
    const unitCostValue = Math.round(custoUnitario * 100) / 100;

    return {
        id: `${product.id}-${Date.now()}-${Math.random().toString(16).slice(2)}`,
        product_id: product.id,
        produto_nome: product.nome,
        produto_codigo: product.codigo || null,
        quantidade: quantityValue,
        custo_unitario: unitCostValue,
        total: Math.round(quantityValue * unitCostValue * 100) / 100,
    };
}

function resetDraft() {
    productDraft.product_id = '';
    productDraft.quantidade = '';
    productDraft.custo_unitario = '';
}

function removeItem(itemId) {
    items.value = items.value.filter((item) => item.id !== itemId);
}

function addItem() {
    formError.value = '';

    const product = productOptions.value.find((item) => item.id === productDraft.product_id);
    if (!product) {
        formError.value = 'Selecione um produto para adicionar.';
        return;
    }

    const quantity = parseNumberInput(productDraft.quantidade);
    if (quantity == null || quantity <= 0) {
        formError.value = 'Informe uma quantidade válida maior que zero.';
        return;
    }

    const unitCost = parseNumberInput(productDraft.custo_unitario);
    if (unitCost == null || unitCost < 0) {
        formError.value = 'Informe um custo unitário válido.';
        return;
    }

    items.value = [
        ...items.value,
        buildItemRow(product, quantity, unitCost),
    ];

    resetDraft();
}

function backToList() {
    router.push('/configuracoes/compras');
}

function toPayload() {
    return {
        supplier_id: form.supplier_id,
        data_compra: form.data_compra,
        filial: String(form.filial || '').trim() || null,
        observacoes: String(form.observacoes || '').trim() || null,
        items: items.value.map((item) => ({
            product_id: item.product_id,
            quantidade: Number(item.quantidade),
            custo_unitario: Number(item.custo_unitario),
        })),
    };
}

function applyPurchaseOrder(data) {
    form.supplier_id = data?.supplier_id || '';
    form.data_compra = data?.data_compra || '';
    form.filial = data?.filial || '';
    form.observacoes = data?.observacoes || '';

    items.value = Array.isArray(data?.items)
        ? data.items.map((item) => ({
            id: String(item.id || `${item.product_id}-${Math.random().toString(16).slice(2)}`),
            product_id: item.product_id,
            produto_nome: item.produto_nome,
            produto_codigo: item.produto_codigo,
            quantidade: Number(item.quantidade || 0),
            custo_unitario: Number(item.custo_unitario || 0),
            total: Number(item.total || 0),
        }))
        : [];
}

async function loadSuppliers() {
    const { data } = await api.get('/suppliers');
    suppliers.value = Array.isArray(data) ? data : [];
}

async function loadProducts() {
    const { data } = await api.get('/products');
    products.value = Array.isArray(data) ? data.filter((item) => item.ativo !== false) : [];
}

async function loadExistingPurchaseOrder() {
    if (!isEditing.value) return;

    const purchaseOrderId = String(route.params.purchaseOrderId || '').trim();
    if (!purchaseOrderId) return;

    const { data } = await api.get(`/purchase-orders/${purchaseOrderId}`);

    if (data?.status !== 'aberto') {
        router.replace(`/configuracoes/compras/${purchaseOrderId}`);
        return;
    }

    applyPurchaseOrder(data);
}

async function savePurchaseOrder() {
    if (!canSave.value) {
        formError.value = 'Preencha fornecedor, data e ao menos um item.';
        return;
    }

    formError.value = '';
    saving.value = true;

    try {
        const payload = toPayload();
        const purchaseOrderId = String(route.params.purchaseOrderId || '').trim();

        let response;
        if (isEditing.value && purchaseOrderId) {
            response = await api.put(`/purchase-orders/${purchaseOrderId}`, payload);
        } else {
            response = await api.post('/purchase-orders', payload);
        }

        const orderId = response?.data?.id;
        if (orderId) {
            router.push(`/configuracoes/compras/${orderId}`);
            return;
        }

        backToList();
    } catch (requestError) {
        const validationErrors = requestError?.response?.data?.errors || {};

        if (Array.isArray(validationErrors.items) && validationErrors.items.length > 0) {
            formError.value = validationErrors.items[0];
        } else if (Array.isArray(validationErrors.supplier_id) && validationErrors.supplier_id.length > 0) {
            formError.value = validationErrors.supplier_id[0];
        } else if (Array.isArray(validationErrors.data_compra) && validationErrors.data_compra.length > 0) {
            formError.value = validationErrors.data_compra[0];
        } else if (Array.isArray(validationErrors.status) && validationErrors.status.length > 0) {
            formError.value = validationErrors.status[0];
        } else {
            formError.value = requestError?.response?.data?.message ?? 'Não foi possível salvar a compra.';
        }
    } finally {
        saving.value = false;
    }
}

onMounted(async () => {
    loading.value = true;
    pageError.value = '';

    try {
        await Promise.all([
            loadSuppliers(),
            loadProducts(),
        ]);

        if (!form.data_compra) {
            form.data_compra = new Date().toISOString().slice(0, 10);
        }

        await loadExistingPurchaseOrder();
    } catch (requestError) {
        pageError.value = requestError?.response?.data?.message ?? 'Falha ao carregar dados da compra.';
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="space-y-4">
        <SettingsPageHeader :title="pageTitle" :subtitle="pageSubtitle" />

        <p v-if="pageError" class="text-sm text-danger">{{ pageError }}</p>

        <div v-if="loading" class="ui-card purchase-loading">Carregando compra...</div>

        <template v-else>
            <div class="purchase-form-topbar">
                <AppButton variant="secondary" @click="backToList">
                    <ArrowLeft class="h-4 w-4" aria-hidden="true" />
                    Voltar
                </AppButton>
            </div>

            <section class="purchase-form-layout">
                <div class="purchase-form-main">
                    <article class="ui-card purchase-card">
                        <h2 class="purchase-card-title">Dados da Compra</h2>

                        <div class="purchase-grid">
                            <AppSelect v-model="form.supplier_id" label="Fornecedor *">
                                <option value="">Selecione...</option>
                                <option v-for="supplier in supplierOptions" :key="supplier.id" :value="supplier.id">
                                    {{ supplier.nome }}
                                </option>
                            </AppSelect>

                            <AppInput v-model="form.filial" label="Filial" placeholder="Opcional" />

                            <AppInput v-model="form.data_compra" type="date" label="Data da Compra" />

                            <div class="purchase-grid-span-2">
                                <AppTextarea
                                    v-model="form.observacoes"
                                    label="Observações"
                                    rows="3"
                                    placeholder="Notas adicionais..."
                                />
                            </div>
                        </div>
                    </article>

                    <article class="ui-card purchase-card">
                        <h2 class="purchase-card-title">Itens</h2>

                        <div class="purchase-item-entry">
                            <AppSelect v-model="productDraft.product_id" label="Produto">
                                <option value="">Selecione...</option>
                                <option v-for="product in productOptions" :key="product.id" :value="product.id">
                                    {{ product.nome }}{{ product.codigo ? ` (${product.codigo})` : '' }}
                                </option>
                            </AppSelect>

                            <AppInput
                                v-model="productDraft.quantidade"
                                type="number"
                                min="0"
                                step="0.001"
                                label="Quantidade"
                                placeholder="0"
                            />

                            <AppInput
                                v-model="productDraft.custo_unitario"
                                type="number"
                                min="0"
                                step="0.01"
                                label="Custo Unit."
                                placeholder="0,00"
                            />

                            <div class="purchase-item-action">
                                <span class="purchase-item-action-label">Adicionar</span>
                                <AppButton variant="secondary" @click="addItem">
                                    <Plus class="h-4 w-4" aria-hidden="true" />
                                </AppButton>
                            </div>
                        </div>

                        <div class="purchase-items-table-wrap">
                            <table class="ui-table purchase-items-table">
                                <thead>
                                    <tr>
                                        <th class="purchase-col-product">Produto</th>
                                        <th class="purchase-col-qty">Qtd.</th>
                                        <th class="purchase-col-cost">Custo Unit.</th>
                                        <th class="purchase-col-total">Total</th>
                                        <th class="purchase-col-remove">Ações</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <tr v-if="items.length === 0">
                                        <td colspan="5" class="purchase-items-empty">Nenhum item adicionado</td>
                                    </tr>

                                    <tr v-for="item in items" :key="item.id">
                                        <td class="purchase-product-cell">
                                            <span>{{ item.produto_nome }}</span>
                                            <small>{{ item.produto_codigo || '—' }}</small>
                                        </td>
                                        <td>{{ formatQuantity(item.quantidade) }}</td>
                                        <td>{{ formatCurrency(item.custo_unitario) }}</td>
                                        <td class="purchase-total-cell">{{ formatCurrency(item.total) }}</td>
                                        <td>
                                            <button
                                                type="button"
                                                class="purchase-remove-btn"
                                                title="Remover item"
                                                @click="removeItem(item.id)"
                                            >
                                                <Trash2 class="h-4 w-4" aria-hidden="true" />
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </article>
                </div>

                <aside class="purchase-form-summary">
                    <article class="ui-card purchase-summary-card">
                        <h2 class="purchase-card-title">Resumo</h2>

                        <div class="purchase-summary-row">
                            <span>Itens</span>
                            <strong>{{ summary.totalItems }}</strong>
                        </div>

                        <div class="purchase-summary-row">
                            <span>Qtd. Total</span>
                            <strong>{{ formatQuantity(summary.totalQuantity) }}</strong>
                        </div>

                        <div class="purchase-summary-total">
                            <span>Valor Total</span>
                            <strong>{{ formatCurrency(summary.totalValue) }}</strong>
                        </div>

                        <p v-if="formError" class="purchase-form-error">{{ formError }}</p>

                        <AppButton :loading="saving" :disabled="!canSave" block @click="savePurchaseOrder">
                            Salvar Compra
                        </AppButton>
                    </article>
                </aside>
            </section>
        </template>
    </div>
</template>

<style scoped>
.purchase-loading {
    padding: 1rem;
    color: var(--color-text-muted);
}

.purchase-form-topbar {
    display: flex;
    justify-content: flex-start;
}

.purchase-form-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 23rem;
    gap: 1rem;
    align-items: start;
}

.purchase-form-main {
    display: grid;
    gap: 1rem;
}

.purchase-card {
    padding: 1.1rem 1.2rem;
    display: grid;
    gap: 1rem;
}

.purchase-card-title {
    margin: 0;
    font-size: 1.03rem;
    font-weight: 800;
    color: var(--color-text);
}

.purchase-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.85rem;
}

.purchase-grid-span-2 {
    grid-column: span 2;
}

.purchase-item-entry {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 6.5rem 7.5rem auto;
    gap: 0.65rem;
    align-items: end;
}

.purchase-item-action {
    display: grid;
    gap: 0.35rem;
}

.purchase-item-action-label {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--color-text);
}

.purchase-items-table-wrap {
    overflow-x: auto;
    border: 1px solid color-mix(in srgb, var(--color-border) 85%, transparent);
    border-radius: var(--radius-md);
}

.purchase-items-table {
    min-width: 100%;
}

.purchase-col-product,
.purchase-col-qty,
.purchase-col-cost,
.purchase-col-total,
.purchase-col-remove {
    text-transform: none;
    letter-spacing: 0;
    font-size: 0.86rem;
    font-weight: 700;
}

.purchase-col-product {
    width: 42%;
}

.purchase-col-qty,
.purchase-col-cost,
.purchase-col-total {
    width: 16%;
}

.purchase-col-remove {
    width: 10%;
    text-align: right;
}

.purchase-items-empty {
    text-align: center;
    color: var(--color-text-muted);
    padding: 1rem;
}

.purchase-product-cell {
    display: grid;
    gap: 0.08rem;
}

.purchase-product-cell span {
    font-weight: 700;
    color: var(--color-text);
}

.purchase-product-cell small {
    color: var(--color-text-muted);
    font-size: 0.8rem;
}

.purchase-total-cell {
    font-weight: 700;
    color: var(--color-text);
}

.purchase-remove-btn {
    width: 2rem;
    height: 2rem;
    border-radius: var(--radius-sm);
    border: 1px solid color-mix(in srgb, var(--color-danger) 35%, transparent);
    background: color-mix(in srgb, var(--color-danger) 10%, var(--color-bg-surface));
    color: var(--color-danger);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.purchase-remove-btn:hover {
    background: color-mix(in srgb, var(--color-danger) 18%, var(--color-bg-surface));
}

.purchase-form-summary {
    position: sticky;
    top: 0.75rem;
}

.purchase-summary-card {
    padding: 1.1rem 1.2rem;
    display: grid;
    gap: 0.8rem;
}

.purchase-summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
    color: var(--color-text-muted);
    font-size: 0.95rem;
    font-weight: 700;
}

.purchase-summary-row strong {
    color: var(--color-text);
}

.purchase-summary-total {
    margin-top: 0.1rem;
    padding-top: 0.75rem;
    border-top: 1px solid color-mix(in srgb, var(--color-border) 70%, transparent);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
    color: var(--color-text);
    font-size: 1.05rem;
    font-weight: 800;
}

.purchase-summary-total strong {
    color: var(--color-success);
}

.purchase-form-error {
    margin: 0;
    color: var(--color-danger);
    font-size: 0.85rem;
    font-weight: 700;
}

@media (max-width: 1180px) {
    .purchase-form-layout {
        grid-template-columns: 1fr;
    }

    .purchase-form-summary {
        position: static;
    }
}

@media (max-width: 860px) {
    .purchase-grid {
        grid-template-columns: 1fr;
    }

    .purchase-grid-span-2 {
        grid-column: span 1;
    }

    .purchase-item-entry {
        grid-template-columns: 1fr;
    }

    .purchase-item-action {
        justify-items: start;
    }
}
</style>
