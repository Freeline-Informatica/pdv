<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { ArrowLeft, Eye, PackageCheck, Pencil } from 'lucide-vue-next';
import { useRoute, useRouter } from 'vue-router';
import api from '../../lib/api';
import SettingsPageHeader from '../../components/settings/SettingsPageHeader.vue';
import AppBadge from '../../components/ui/AppBadge.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppModal from '../../components/ui/AppModal.vue';

const route = useRoute();
const router = useRouter();

const loading = ref(false);
const pageError = ref('');
const purchaseOrder = ref(null);

const receiveModal = reactive({
    open: false,
    loading: false,
    error: '',
});

const purchaseOrderId = computed(() => String(route.params.purchaseOrderId || '').trim());

const statusLabel = computed(() => {
    if (!purchaseOrder.value) return '';
    return purchaseOrder.value.status_label || (purchaseOrder.value.status === 'recebido' ? 'Recebido' : 'Em aberto');
});

const statusVariant = computed(() => {
    return purchaseOrder.value?.status === 'recebido' ? 'success' : 'default';
});

const summary = computed(() => {
    const source = purchaseOrder.value;

    return {
        totalItems: Number(source?.total_items || 0),
        totalQuantity: Number(source?.total_quantity || 0),
        totalValue: Number(source?.total_value || 0),
    };
});

const canEdit = computed(() => Boolean(purchaseOrder.value?.can_edit));
const canReceive = computed(() => Boolean(purchaseOrder.value?.can_receive));

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

function formatDate(value) {
    if (!value) return '—';

    const date = value.includes('T') ? new Date(value) : new Date(`${value}T00:00:00`);

    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(date);
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

function backToList() {
    router.push('/configuracoes/compras');
}

function openEdit() {
    if (!purchaseOrder.value?.id) return;
    router.push(`/configuracoes/compras/${purchaseOrder.value.id}/editar`);
}

function closeReceiveModal() {
    receiveModal.open = false;
    receiveModal.loading = false;
    receiveModal.error = '';
}

function openReceiveModal() {
    if (!canReceive.value) return;
    receiveModal.open = true;
    receiveModal.error = '';
}

async function loadPurchaseOrder() {
    if (!purchaseOrderId.value) {
        purchaseOrder.value = null;
        return;
    }

    loading.value = true;
    pageError.value = '';

    try {
        const { data } = await api.get(`/purchase-orders/${purchaseOrderId.value}`);
        purchaseOrder.value = data;
    } catch (requestError) {
        purchaseOrder.value = null;
        pageError.value = requestError?.response?.data?.message ?? 'Falha ao carregar a compra.';
    } finally {
        loading.value = false;
    }
}

async function receivePurchaseOrder() {
    if (!purchaseOrder.value?.id || !canReceive.value) return;

    receiveModal.error = '';
    receiveModal.loading = true;

    try {
        const { data } = await api.post(`/purchase-orders/${purchaseOrder.value.id}/receive`);
        purchaseOrder.value = data;
        closeReceiveModal();

        if (route.query.receive) {
            router.replace({
                path: route.path,
                query: {},
            });
        }
    } catch (requestError) {
        const validationErrors = requestError?.response?.data?.errors || {};
        if (Array.isArray(validationErrors.status) && validationErrors.status.length > 0) {
            receiveModal.error = validationErrors.status[0];
        } else {
            receiveModal.error = requestError?.response?.data?.message ?? 'Não foi possível receber a compra.';
        }
    } finally {
        receiveModal.loading = false;
    }
}

watch(
    () => route.query.receive,
    (receiveQuery) => {
        if (receiveQuery === '1' && canReceive.value) {
            openReceiveModal();
        }
    },
);

watch(
    () => purchaseOrderId.value,
    async (nextId, previousId) => {
        if (!nextId || nextId === previousId) return;

        await loadPurchaseOrder();

        if (route.query.receive === '1' && canReceive.value) {
            openReceiveModal();
        }
    },
);

onMounted(async () => {
    await loadPurchaseOrder();

    if (route.query.receive === '1' && canReceive.value) {
        openReceiveModal();
    }
});
</script>

<template>
    <div class="space-y-4">
        <SettingsPageHeader
            :title="purchaseOrder ? `Compra #${purchaseOrder.numero}` : 'Compra'"
            subtitle="Detalhes do pedido de compra"
        >
            <template #actions>
                <AppBadge v-if="purchaseOrder" :variant="statusVariant">{{ statusLabel }}</AppBadge>
            </template>
        </SettingsPageHeader>

        <p v-if="pageError" class="text-sm text-danger">{{ pageError }}</p>

        <div v-if="loading" class="ui-card purchase-detail-loading">Carregando detalhes da compra...</div>

        <template v-else-if="purchaseOrder">
            <div class="purchase-detail-toolbar">
                <AppButton variant="secondary" @click="backToList">
                    <ArrowLeft class="h-4 w-4" aria-hidden="true" />
                    Voltar
                </AppButton>
            </div>

            <section class="purchase-detail-layout">
                <div class="purchase-detail-main">
                    <article class="ui-card purchase-detail-card">
                        <h2 class="purchase-detail-title">Informações</h2>

                        <dl class="purchase-info-grid">
                            <div>
                                <dt>Fornecedor</dt>
                                <dd>{{ purchaseOrder.supplier?.nome || purchaseOrder.supplier_name }}</dd>
                            </div>
                            <div>
                                <dt>Filial</dt>
                                <dd>{{ purchaseOrder.filial || '—' }}</dd>
                            </div>
                            <div>
                                <dt>Data da compra</dt>
                                <dd>{{ formatDate(purchaseOrder.data_compra) }}</dd>
                            </div>
                            <div>
                                <dt>Criado em</dt>
                                <dd>{{ formatDateTime(purchaseOrder.created_at) }}</dd>
                            </div>
                            <div>
                                <dt>Recebido em</dt>
                                <dd>{{ formatDateTime(purchaseOrder.received_at) }}</dd>
                            </div>
                            <div>
                                <dt>Responsável</dt>
                                <dd>{{ purchaseOrder.creator?.name || '—' }}</dd>
                            </div>
                        </dl>
                    </article>

                    <article class="ui-card purchase-detail-card">
                        <h2 class="purchase-detail-title">Itens</h2>

                        <div class="ui-table-wrap purchase-detail-table-wrap">
                            <table class="ui-table purchase-detail-table">
                                <thead>
                                    <tr>
                                        <th class="purchase-item-col-product">Produto</th>
                                        <th class="purchase-item-col-code">Código</th>
                                        <th class="purchase-item-col-qty">Qtd.</th>
                                        <th class="purchase-item-col-cost">Custo Unit.</th>
                                        <th class="purchase-item-col-total">Total</th>
                                        <th class="purchase-item-col-received">Recebido</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <tr v-if="!purchaseOrder.items || purchaseOrder.items.length === 0">
                                        <td colspan="6" class="purchase-detail-empty">Nenhum item registrado na compra.</td>
                                    </tr>

                                    <tr v-for="item in purchaseOrder.items || []" :key="item.id">
                                        <td class="purchase-item-product-cell">{{ item.produto_nome }}</td>
                                        <td>{{ item.produto_codigo || '—' }}</td>
                                        <td>{{ formatQuantity(item.quantidade) }}</td>
                                        <td>{{ formatCurrency(item.custo_unitario) }}</td>
                                        <td class="purchase-item-total-cell">{{ formatCurrency(item.total) }}</td>
                                        <td>{{ formatQuantity(item.quantidade_recebida) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </article>
                </div>

                <aside class="purchase-detail-summary">
                    <article class="ui-card purchase-summary-card">
                        <h2 class="purchase-detail-title">Resumo</h2>

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

                        <div class="purchase-summary-actions">
                            <AppButton v-if="canEdit" variant="secondary" block @click="openEdit">
                                <Pencil class="h-4 w-4" aria-hidden="true" />
                                Editar
                            </AppButton>

                            <AppButton
                                v-if="canReceive"
                                block
                                @click="openReceiveModal"
                            >
                                <PackageCheck class="h-4 w-4" aria-hidden="true" />
                                Receber Compra
                            </AppButton>

                            <AppButton v-if="!canEdit && !canReceive" variant="secondary" block @click="backToList">
                                <Eye class="h-4 w-4" aria-hidden="true" />
                                Voltar para lista
                            </AppButton>
                        </div>
                    </article>
                </aside>
            </section>
        </template>

        <AppModal
            :open="receiveModal.open"
            :title="purchaseOrder ? `Confirmar recebimento da Compra #${purchaseOrder.numero}` : 'Confirmar recebimento'"
            width-class="max-w-2xl"
            @close="closeReceiveModal"
        >
            <div class="space-y-4">
                <p class="text-sm text-muted">
                    O recebimento vai lançar entrada de estoque para todos os itens pendentes desta compra e criar movimentações no kardex.
                </p>

                <div class="purchase-receive-preview">
                    <span>Itens</span>
                    <strong>{{ summary.totalItems }}</strong>
                </div>

                <div class="purchase-receive-preview">
                    <span>Valor total</span>
                    <strong>{{ formatCurrency(summary.totalValue) }}</strong>
                </div>

                <p v-if="receiveModal.error" class="text-sm text-danger">{{ receiveModal.error }}</p>

                <div class="purchase-receive-actions">
                    <AppButton variant="secondary" @click="closeReceiveModal">Cancelar</AppButton>
                    <AppButton :loading="receiveModal.loading" @click="receivePurchaseOrder">Confirmar Recebimento</AppButton>
                </div>
            </div>
        </AppModal>
    </div>
</template>

<style scoped>
.purchase-detail-loading {
    padding: 1rem;
    color: var(--color-text-muted);
}

.purchase-detail-toolbar {
    display: flex;
    justify-content: flex-start;
}

.purchase-detail-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 25rem;
    gap: 1rem;
    align-items: start;
}

.purchase-detail-main {
    display: grid;
    gap: 1rem;
}

.purchase-detail-card {
    padding: 1.1rem 1.2rem;
    display: grid;
    gap: 0.95rem;
}

.purchase-detail-title {
    margin: 0;
    font-size: 1.03rem;
    font-weight: 800;
    color: var(--color-text);
}

.purchase-info-grid {
    margin: 0;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.85rem;
}

.purchase-info-grid dt {
    color: var(--color-text-muted);
    font-size: 0.86rem;
    font-weight: 700;
    margin: 0;
}

.purchase-info-grid dd {
    margin: 0.2rem 0 0;
    color: var(--color-text);
    font-weight: 700;
}

.purchase-detail-table-wrap {
    border-radius: var(--radius-md);
}

.purchase-detail-table {
    table-layout: fixed;
}

.purchase-item-col-product,
.purchase-item-col-code,
.purchase-item-col-qty,
.purchase-item-col-cost,
.purchase-item-col-total,
.purchase-item-col-received {
    text-transform: none;
    letter-spacing: 0;
    font-size: 0.88rem;
    font-weight: 700;
}

.purchase-item-col-product {
    width: 24%;
}

.purchase-item-col-code {
    width: 16%;
}

.purchase-item-col-qty,
.purchase-item-col-cost,
.purchase-item-col-total,
.purchase-item-col-received {
    width: 15%;
}

.purchase-item-product-cell,
.purchase-item-total-cell {
    font-weight: 700;
    color: var(--color-text);
}

.purchase-detail-empty {
    text-align: center;
    color: var(--color-text-muted);
    padding: 1rem;
}

.purchase-detail-summary {
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
    align-items: center;
    justify-content: space-between;
    gap: 0.7rem;
    color: var(--color-text-muted);
    font-size: 0.95rem;
    font-weight: 700;
}

.purchase-summary-row strong {
    color: var(--color-text);
}

.purchase-summary-total {
    margin-top: 0.15rem;
    padding-top: 0.75rem;
    border-top: 1px solid color-mix(in srgb, var(--color-border) 70%, transparent);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.7rem;
    color: var(--color-text);
    font-size: 1.05rem;
    font-weight: 800;
}

.purchase-summary-total strong {
    color: var(--color-success);
}

.purchase-summary-actions {
    display: grid;
    gap: 0.45rem;
    margin-top: 0.2rem;
}

.purchase-receive-preview {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
    border: 1px solid color-mix(in srgb, var(--color-border) 70%, transparent);
    border-radius: var(--radius-sm);
    padding: 0.6rem 0.75rem;
    color: var(--color-text-muted);
    font-size: 0.9rem;
    font-weight: 700;
}

.purchase-receive-preview strong {
    color: var(--color-text);
}

.purchase-receive-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
}

@media (max-width: 1180px) {
    .purchase-detail-layout {
        grid-template-columns: 1fr;
    }

    .purchase-detail-summary {
        position: static;
    }
}

@media (max-width: 860px) {
    .purchase-info-grid {
        grid-template-columns: 1fr;
    }
}
</style>
