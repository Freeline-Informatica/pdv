<script setup>
import { MoreHorizontal } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import api from '../../lib/api';
import { formatCurrency } from '../../lib/format';
import SettingsPageHeader from '../../components/settings/SettingsPageHeader.vue';
import SettingsFilterBar from '../../components/settings/SettingsFilterBar.vue';
import SettingsTableCard from '../../components/settings/SettingsTableCard.vue';
import SettingsEmptyState from '../../components/settings/SettingsEmptyState.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppSearchField from '../../components/ui/AppSearchField.vue';
import AppTable from '../../components/ui/AppTable.vue';
import AppBadge from '../../components/ui/AppBadge.vue';

const router = useRouter();

const loading = ref(false);
const deleting = ref(false);
const error = ref('');
const search = ref('');
const items = ref([]);
const openActionMenuId = ref(null);
const pagination = reactive({
    current_page: 1,
    last_page: 1,
    total: 0,
    per_page: 15,
});

watch(search, () => {
    debounceLoad();
});

let searchTimeout = null;
function debounceLoad() {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    searchTimeout = setTimeout(() => {
        load(1);
    }, 250);
}

function openCreate() {
    router.push('/configuracoes/produtos/novo');
}

function openEdit(item) {
    router.push(`/configuracoes/produtos/${item.id}/editar`);
}

function toggleActionMenu(productId) {
    openActionMenuId.value = openActionMenuId.value === productId ? null : productId;
}

function closeActionMenu() {
    openActionMenuId.value = null;
}

function handleEditFromMenu(item) {
    closeActionMenu();
    openEdit(item);
}

async function handleDeleteFromMenu(item) {
    closeActionMenu();
    await remove(item);
}

function handleWindowClick(event) {
    const target = event.target;
    if (!(target instanceof Element)) return;
    if (!target.closest('[data-product-actions-menu]')) {
        closeActionMenu();
    }
}

function handleWindowKeydown(event) {
    if (event.key === 'Escape') {
        closeActionMenu();
    }
}

function formatStock(value) {
    if (value == null || value === '') {
        return '—';
    }

    const numeric = Number(value);
    if (!Number.isFinite(numeric)) {
        return '—';
    }

    return numeric.toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 6,
    });
}

async function remove(item) {
    if (!window.confirm(`Excluir produto "${item.descricao}"?`)) {
        return;
    }

    deleting.value = true;
    try {
        await api.delete(`/catalog/products/${item.id}`);
        await load(1);
    } finally {
        deleting.value = false;
    }
}

async function load(page = 1) {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await api.get('/catalog/products', {
            params: {
                page,
                per_page: pagination.per_page,
                search: search.value || undefined,
            },
        });

        items.value = Array.isArray(data?.data) ? data.data : [];
        pagination.current_page = Number(data?.current_page || 1);
        pagination.last_page = Number(data?.last_page || 1);
        pagination.total = Number(data?.total || 0);
    } catch (requestError) {
        error.value = requestError?.response?.data?.message ?? 'Falha ao carregar produtos.';
        items.value = [];
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    load(1);
    window.addEventListener('click', handleWindowClick);
    window.addEventListener('keydown', handleWindowKeydown);
});

onBeforeUnmount(() => {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }
    window.removeEventListener('click', handleWindowClick);
    window.removeEventListener('keydown', handleWindowKeydown);
});
</script>

<template>
    <div class="space-y-4">
        <SettingsPageHeader title="Produtos" subtitle="Painel de catálogo: pesquise, acompanhe e acesse o cadastro completo em tela dedicada.">
            <template #actions>
                <AppButton @click="openCreate">Criar produto</AppButton>
            </template>
        </SettingsPageHeader>

        <SettingsFilterBar>
            <div class="w-full flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="w-full max-w-sm">
                    <AppSearchField v-model="search" placeholder="Buscar por descrição, SKU ou código operacional" />
                </div>
                <div class="text-xs text-muted">{{ pagination.total }} registro(s)</div>
            </div>
        </SettingsFilterBar>

        <SettingsTableCard class="products-table-card">
            <AppTable>
                <thead>
                    <tr>
                        <th class="text-left">Descrição</th>
                        <th class="text-left">SKU</th>
                        <th class="text-left">Código operacional</th>
                        <th class="text-left">Unidade</th>
                        <th class="text-right">Preço</th>
                        <th class="text-right">Estoque</th>
                        <th class="text-center">Status</th>
                        <th class="text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="8" class="text-center text-muted">Carregando...</td>
                    </tr>
                    <tr v-else-if="error">
                        <td colspan="8" class="text-center text-danger">{{ error }}</td>
                    </tr>
                    <tr v-else-if="items.length === 0">
                        <td colspan="8" class="p-0">
                            <SettingsEmptyState
                                title="Nenhum produto encontrado"
                                description="Ajuste os filtros ou inicie um novo cadastro para popular o catálogo."
                            >
                                <template #actions>
                                    <AppButton @click="openCreate">Cadastrar produto</AppButton>
                                </template>
                            </SettingsEmptyState>
                        </td>
                    </tr>
                    <tr v-for="(item, rowIndex) in items" :key="item.id">
                        <td class="font-semibold text-main">{{ item.descricao }}</td>
                        <td class="text-xs font-mono text-muted">{{ item.cod_sku || '—' }}</td>
                        <td class="text-xs font-mono text-muted">{{ item.codigo_operacional || '—' }}</td>
                        <td>{{ item.unidade_medida?.unidade || '—' }}</td>
                        <td class="text-right font-semibold">{{ item.preco_venda != null ? formatCurrency(item.preco_venda) : '—' }}</td>
                        <td class="text-right">{{ formatStock(item.estoque_atual) }}</td>
                        <td class="text-center">
                            <AppBadge :variant="item.situacao === 'ativo' ? 'success' : 'default'">
                                {{ item.situacao || 'indefinido' }}
                            </AppBadge>
                        </td>
                        <td class="text-right">
                            <div
                                class="product-actions-menu"
                                :class="{ 'is-upward': rowIndex >= items.length - 2 }"
                                data-product-actions-menu
                                @click.stop
                            >
                                <button
                                    type="button"
                                    class="product-actions-trigger"
                                    title="Ações do produto"
                                    aria-label="Ações do produto"
                                    :aria-expanded="openActionMenuId === item.id"
                                    :disabled="deleting"
                                    @click="toggleActionMenu(item.id)"
                                >
                                    <MoreHorizontal class="h-4 w-4" aria-hidden="true" />
                                </button>

                                <div v-if="openActionMenuId === item.id" class="product-actions-popover">
                                    <button type="button" class="product-actions-item" @click="handleEditFromMenu(item)">
                                        Editar
                                    </button>
                                    <button
                                        type="button"
                                        class="product-actions-item product-actions-item--danger"
                                        :disabled="deleting"
                                        @click="handleDeleteFromMenu(item)"
                                    >
                                        Excluir
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </AppTable>

            <div class="mt-3 flex items-center justify-between text-sm text-muted m-5">
                <span>Página {{ pagination.current_page }} de {{ pagination.last_page }}</span>
                <div class="inline-flex items-center gap-2 ">
                    <AppButton variant="secondary" :disabled="pagination.current_page <= 1" @click="load(pagination.current_page - 1)">
                        Anterior
                    </AppButton>
                    <AppButton variant="secondary" :disabled="pagination.current_page >= pagination.last_page" @click="load(pagination.current_page + 1)">
                        Próxima
                    </AppButton>
                </div>
            </div>
        </SettingsTableCard>
    </div>
</template>

<style scoped>
.products-table-card,
.products-table-card :deep(.ui-table-wrap) {
    overflow: visible;
}

.product-actions-menu {
    position: relative;
    display: inline-flex;
    justify-content: flex-end;
}

.product-actions-trigger {
    width: 2.15rem;
    height: 2.15rem;
    border-radius: 999px;
    border: 1px solid var(--color-border);
    background: transparent;
    color: var(--color-text-muted);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all var(--transition-fast);
}

.product-actions-trigger:hover:not(:disabled) {
    border-color: color-mix(in srgb, var(--color-primary) 42%, transparent);
    color: var(--color-text);
    background: color-mix(in srgb, var(--color-primary) 12%, var(--color-bg-surface));
}

.product-actions-trigger:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.product-actions-popover {
    position: absolute;
    top: calc(100% + 0.35rem);
    right: 0;
    z-index: var(--z-dropdown);
    min-width: 8.5rem;
    border-radius: var(--radius-sm);
    border: 1px solid var(--color-border);
    background: var(--color-bg-surface);
    box-shadow: var(--shadow-md);
    padding: 0.28rem;
    display: grid;
    gap: 0.18rem;
}

.product-actions-menu.is-upward .product-actions-popover {
    top: auto;
    bottom: calc(100% + 0.35rem);
}

.product-actions-item {
    width: 100%;
    border: 1px solid transparent;
    border-radius: var(--radius-sm);
    background: transparent;
    color: var(--color-text);
    text-align: left;
    font-size: 0.84rem;
    font-weight: 600;
    padding: 0.46rem 0.55rem;
    transition: all var(--transition-fast);
}

.product-actions-item:hover:not(:disabled) {
    border-color: color-mix(in srgb, var(--color-primary) 34%, transparent);
    background: color-mix(in srgb, var(--color-primary) 11%, var(--color-bg-surface));
}

.product-actions-item:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.product-actions-item--danger {
    color: var(--color-danger);
}
</style>
