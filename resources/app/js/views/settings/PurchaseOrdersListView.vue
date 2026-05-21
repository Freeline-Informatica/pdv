<script setup>
import { computed, onMounted, ref } from 'vue';
import { Eye, PackageCheck, Pencil, Plus } from 'lucide-vue-next';
import { useRouter } from 'vue-router';
import api from '../../lib/api';
import SettingsPageHeader from '../../components/settings/SettingsPageHeader.vue';
import SettingsFilterBar from '../../components/settings/SettingsFilterBar.vue';
import AppBadge from '../../components/ui/AppBadge.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppIconButton from '../../components/ui/AppIconButton.vue';
import AppSearchField from '../../components/ui/AppSearchField.vue';
import AppSelect from '../../components/ui/AppSelect.vue';

const router = useRouter();

const loading = ref(false);
const pageError = ref('');
const items = ref([]);
const search = ref('');
const statusFilter = ref('todos');

const filteredItems = computed(() => {
    const normalizedSearch = normalizeToken(search.value);

    return items.value.filter((item) => {
        if (statusFilter.value !== 'todos' && item.status !== statusFilter.value) {
            return false;
        }

        if (!normalizedSearch) return true;

        const haystack = normalizeToken([
            `#${item.numero}`,
            item.supplier_name,
            item.filial,
            item.status_label,
            formatDate(item.data_compra),
        ].join(' '));

        return haystack.includes(normalizedSearch);
    });
});

function normalizeToken(value) {
    return String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim()
        .toLowerCase();
}

function formatCurrency(value) {
    return Number(value || 0).toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    });
}

function formatDate(value) {
    if (!value) return '—';

    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(new Date(`${value}T00:00:00`));
}

function statusVariant(status) {
    return status === 'recebido' ? 'success' : 'default';
}

function openCreate() {
    router.push('/configuracoes/compras/nova');
}

function openDetail(orderId, extraQuery = {}) {
    router.push({
        path: `/configuracoes/compras/${orderId}`,
        query: extraQuery,
    });
}

function openEdit(orderId) {
    router.push(`/configuracoes/compras/${orderId}/editar`);
}

async function loadPurchaseOrders() {
    loading.value = true;
    pageError.value = '';

    try {
        const { data } = await api.get('/purchase-orders');
        items.value = Array.isArray(data) ? data : [];
    } catch (requestError) {
        items.value = [];
        pageError.value = requestError?.response?.data?.message ?? 'Falha ao carregar pedidos de compra.';
    } finally {
        loading.value = false;
    }
}

onMounted(loadPurchaseOrders);
</script>

<template>
    <div class="space-y-4">
        <SettingsPageHeader title="Compras" subtitle="Gerencie pedidos de compra e recebimentos">
            <template #actions>
                <AppButton @click="openCreate">
                    <Plus class="h-4 w-4" aria-hidden="true" />
                    Nova Compra
                </AppButton>
            </template>
        </SettingsPageHeader>

        <p v-if="pageError" class="text-sm text-danger">{{ pageError }}</p>

        <SettingsFilterBar>
            <div class="purchase-search">
                <AppSearchField v-model="search" placeholder="Buscar por fornecedor ou número..." />
            </div>

            <div class="purchase-status-filter">
                <AppSelect v-model="statusFilter" aria-label="Filtrar status">
                    <option value="todos">Todos</option>
                    <option value="aberto">Em aberto</option>
                    <option value="recebido">Recebido</option>
                </AppSelect>
            </div>
        </SettingsFilterBar>

        <div class="ui-table-wrap purchases-table-shell">
            <table class="ui-table purchases-table">
                <thead>
                    <tr>
                        <th class="purchases-col-number">N°</th>
                        <th class="purchases-col-supplier">Fornecedor</th>
                        <th class="purchases-col-date">Data</th>
                        <th class="purchases-col-branch">Filial</th>
                        <th class="purchases-col-status">Status</th>
                        <th class="purchases-col-total">Valor Total</th>
                        <th class="purchases-col-actions">Ações</th>
                    </tr>
                </thead>

                <tbody>
                    <tr v-if="loading">
                        <td colspan="7" class="purchases-empty">Carregando compras...</td>
                    </tr>

                    <tr v-else-if="filteredItems.length === 0">
                        <td colspan="7" class="purchases-empty">
                            Nenhum pedido de compra encontrado.
                        </td>
                    </tr>

                    <tr v-for="item in filteredItems" :key="item.id">
                        <td class="purchases-number-cell">#{{ item.numero }}</td>
                        <td class="purchases-supplier-cell">{{ item.supplier_name }}</td>
                        <td>{{ formatDate(item.data_compra) }}</td>
                        <td>{{ item.filial || '—' }}</td>
                        <td>
                            <AppBadge :variant="statusVariant(item.status)">
                                {{ item.status_label || (item.status === 'recebido' ? 'Recebido' : 'Em aberto') }}
                            </AppBadge>
                        </td>
                        <td class="purchases-total-cell">{{ formatCurrency(item.total_value) }}</td>
                        <td>
                            <div class="purchases-actions-cell">
                                <AppIconButton title="Visualizar compra" @click="openDetail(item.id)">
                                    <Eye class="h-4 w-4" aria-hidden="true" />
                                </AppIconButton>

                                <AppIconButton
                                    v-if="item.can_edit"
                                    title="Editar compra"
                                    @click="openEdit(item.id)"
                                >
                                    <Pencil class="h-4 w-4" aria-hidden="true" />
                                </AppIconButton>

                                <AppIconButton
                                    v-if="item.can_receive"
                                    title="Receber compra"
                                    @click="openDetail(item.id, { receive: '1' })"
                                >
                                    <PackageCheck class="h-4 w-4" aria-hidden="true" />
                                </AppIconButton>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>
</template>

<style scoped>
.purchase-search {
    flex: 1 1 22rem;
    min-width: 16rem;
}

.purchase-status-filter {
    width: 12rem;
}

.purchases-table-shell {
    border-radius: var(--radius-xl);
}

.purchases-table {
    table-layout: fixed;
}

.purchases-col-number,
.purchases-col-supplier,
.purchases-col-date,
.purchases-col-branch,
.purchases-col-status,
.purchases-col-total,
.purchases-col-actions {
    text-transform: none;
    letter-spacing: 0;
    font-size: 0.92rem;
    font-weight: 700;
    text-align: left;
}

.purchases-col-number {
    width: 8%;
}

.purchases-col-supplier {
    width: 20%;
}

.purchases-col-date {
    width: 14%;
}

.purchases-col-branch {
    width: 10%;
}

.purchases-col-status {
    width: 14%;
}

.purchases-col-total {
    width: 14%;
}

.purchases-col-actions {
    width: 20%;
}

.purchases-empty {
    text-align: center;
    color: var(--color-text-muted);
    padding: 1.2rem 0.8rem;
}

.purchases-number-cell,
.purchases-supplier-cell,
.purchases-total-cell {
    font-weight: 700;
    color: var(--color-text);
}

.purchases-actions-cell {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

@media (max-width: 1100px) {
    .purchase-status-filter {
        width: min(100%, 14rem);
    }

    .purchases-col-branch,
    .purchases-col-status {
        width: 12%;
    }
}
</style>
