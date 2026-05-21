<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../../lib/api';
import SettingsPageHeader from '../../components/settings/SettingsPageHeader.vue';
import SettingsTableCard from '../../components/settings/SettingsTableCard.vue';
import SettingsEmptyState from '../../components/settings/SettingsEmptyState.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppTable from '../../components/ui/AppTable.vue';
import AppBadge from '../../components/ui/AppBadge.vue';

const route = useRoute();
const router = useRouter();

const loading = ref(false);
const error = ref('');
const rows = ref([]);

const parameterKey = computed(() => String(route.meta?.parameterKey || ''));
const pageTitle = computed(() => String(route.meta?.parameterTitle || 'Parâmetro de catálogo'));
const pageSubtitle = computed(() => String(route.meta?.parameterSubtitle || 'Cadastros-base utilizados no catálogo de produtos.'));
const singularLabel = computed(() => String(route.meta?.parameterSingular || 'item'));
const breadcrumb = computed(() => `Catálogo > Parâmetros > ${pageTitle.value}`);

const columns = computed(() => {
    if (parameterKey.value === 'familias') {
        return [
            { key: 'codigo', label: 'Código', empty: '—' },
            { key: 'nome', label: 'Nome', empty: '—' },
        ];
    }

    if (parameterKey.value === 'unidades_medida') {
        return [
            { key: 'unidade', label: 'Sigla', empty: '—' },
            { key: 'descricao', label: 'Descrição', empty: '—' },
        ];
    }

    if (parameterKey.value === 'classificacoes_mercadologicas') {
        return [
            { key: 'codigo', label: 'Código', empty: '—' },
            { key: 'descricao', label: 'Descrição', empty: '—' },
            { key: 'nivel', label: 'Nível', empty: '—' },
        ];
    }

    return [];
});

function normalizeRows(data) {
    if (parameterKey.value === 'familias') {
        const source = Array.isArray(data?.familias) ? data.familias : [];
        rows.value = source.map((item) => ({
            id: item?.id,
            codigo: String(item?.codigo || '').trim() || null,
            nome: String(item?.nome || '').trim() || null,
        }));
        return;
    }

    if (parameterKey.value === 'unidades_medida') {
        const source = Array.isArray(data?.unidades_medida) ? data.unidades_medida : [];
        rows.value = source.map((item) => ({
            id: item?.id,
            unidade: String(item?.unidade || '').trim() || null,
            descricao: String(item?.descricao || '').trim() || null,
        }));
        return;
    }

    if (parameterKey.value === 'classificacoes_mercadologicas') {
        const source = Array.isArray(data?.classificacoes_mercadologicas) ? data.classificacoes_mercadologicas : [];
        rows.value = source.map((item) => ({
            id: item?.id,
            codigo: String(item?.codigo || '').trim() || null,
            descricao: String(item?.descricao || '').trim() || null,
            nivel: item?.nivel == null ? null : Number(item.nivel),
        }));
        return;
    }

    rows.value = [];
}

function goBackToCentral() {
    router.push('/configuracoes/catalogo/parametros');
}

async function load() {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await api.get('/catalog/products/support-data');
        normalizeRows(data);
    } catch (requestError) {
        error.value = requestError?.response?.data?.message ?? 'Não foi possível carregar os parâmetros.';
        rows.value = [];
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="space-y-4">
        <p class="catalog-parameter-breadcrumb">{{ breadcrumb }}</p>

        <SettingsPageHeader :title="pageTitle" :subtitle="pageSubtitle">
            <template #actions>
                <AppButton variant="secondary" @click="goBackToCentral">Voltar para central</AppButton>
            </template>
        </SettingsPageHeader>

        <SettingsTableCard>
            <AppTable>
                <thead>
                    <tr>
                        <th v-for="column in columns" :key="column.key" class="text-left">{{ column.label }}</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td :colspan="columns.length + 1" class="text-center text-muted">Carregando...</td>
                    </tr>
                    <tr v-else-if="error">
                        <td :colspan="columns.length + 1" class="text-center text-danger">{{ error }}</td>
                    </tr>
                    <tr v-else-if="rows.length === 0">
                        <td :colspan="columns.length + 1" class="p-0">
                            <SettingsEmptyState
                                :title="`Nenhum(a) ${singularLabel} encontrado(a)`"
                                description="A lista ainda não possui registros ativos."
                            >
                                <template #actions>
                                    <AppButton variant="secondary" @click="goBackToCentral">Voltar para central</AppButton>
                                </template>
                            </SettingsEmptyState>
                        </td>
                    </tr>
                    <tr v-for="row in rows" :key="row.id">
                        <td
                            v-for="column in columns"
                            :key="`${row.id}-${column.key}`"
                            class="text-muted"
                        >
                            {{ row[column.key] ?? column.empty }}
                        </td>
                        <td class="text-center">
                            <AppBadge variant="success">Ativo</AppBadge>
                        </td>
                    </tr>
                </tbody>
            </AppTable>
        </SettingsTableCard>
    </div>
</template>

<style scoped>
.catalog-parameter-breadcrumb {
    margin: 0;
    font-size: 0.82rem;
    font-weight: 700;
    color: color-mix(in srgb, var(--color-text-muted) 86%, transparent);
}
</style>
