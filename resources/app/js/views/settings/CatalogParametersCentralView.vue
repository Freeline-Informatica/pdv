<script setup>
import { computed, onMounted, ref } from 'vue';
import { Boxes, LayoutList, Ruler } from 'lucide-vue-next';
import { useRouter } from 'vue-router';
import api from '../../lib/api';
import SettingsPageHeader from '../../components/settings/SettingsPageHeader.vue';
import AppCard from '../../components/ui/AppCard.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppStatCard from '../../components/ui/AppStatCard.vue';

const router = useRouter();

const loading = ref(false);
const error = ref('');
const familiesCount = ref(0);
const unitsCount = ref(0);
const classificationsCount = ref(0);

const parameterLinks = [
    {
        key: 'familias',
        title: 'Famílias',
        description: 'Agrupamento comercial e regra de geração de código operacional.',
        buttonLabel: 'Gerenciar famílias',
        path: '/configuracoes/catalogo/parametros/familias',
        icon: Boxes,
    },
    {
        key: 'unidades-medida',
        title: 'Unidades de Medida',
        description: 'Catálogo de unidades comerciais e fiscais usado no produto e nas conversões.',
        buttonLabel: 'Gerenciar unidades',
        path: '/configuracoes/catalogo/parametros/unidades-medida',
        icon: Ruler,
    },
    {
        key: 'classificacoes-mercadologicas',
        title: 'Classificação Mercadológica',
        description: 'Hierarquia mercadológica usada no cadastro principal e em regras de produto.',
        buttonLabel: 'Gerenciar classificações',
        path: '/configuracoes/catalogo/parametros/classificacoes-mercadologicas',
        icon: LayoutList,
    },
];

const totalParameters = computed(() => familiesCount.value + unitsCount.value + classificationsCount.value);

function goTo(path) {
    router.push(path);
}

async function loadDashboard() {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await api.get('/catalog/products/support-data');
        familiesCount.value = Array.isArray(data?.familias) ? data.familias.length : 0;
        unitsCount.value = Array.isArray(data?.unidades_medida) ? data.unidades_medida.length : 0;
        classificationsCount.value = Array.isArray(data?.classificacoes_mercadologicas) ? data.classificacoes_mercadologicas.length : 0;
    } catch (requestError) {
        error.value = requestError?.response?.data?.message ?? 'Não foi possível carregar o resumo da central de parâmetros.';
    } finally {
        loading.value = false;
    }
}

onMounted(loadDashboard);
</script>

<template>
    <div class="space-y-4 catalog-parameters-central">
        <p class="catalog-central-breadcrumb">Catálogo &gt; Parâmetros</p>

        <AppCard elevated padding="p-0" class="catalog-central-hero">
            <section class="catalog-central-hero-main">
                <p class="catalog-central-kicker">CATÁLOGO</p>
                <h2 class="catalog-central-title">Central de Parâmetros de Produto</h2>
                <p class="catalog-central-copy">
                    Selecione uma central abaixo para acessar famílias, unidades de medida e classificação mercadológica.
                </p>
            </section>

            <section class="catalog-central-shortcuts">
                <p class="catalog-central-kicker">ATALHOS</p>
                <div class="catalog-central-shortcuts-grid">
                    <button
                        v-for="item in parameterLinks"
                        :key="`shortcut-${item.key}`"
                        type="button"
                        class="catalog-shortcut-btn"
                        @click="goTo(item.path)"
                    >
                        <span class="catalog-shortcut-icon" aria-hidden="true">
                            <component :is="item.icon" class="h-5 w-5" />
                        </span>
                        <span>{{ item.title }}</span>
                    </button>
                </div>
            </section>
        </AppCard>

        <AppCard class="catalog-central-notice">
            Esta central é exclusiva para parâmetros de produto. Famílias, unidades e classificação mercadológica já estão operacionais.
        </AppCard>

        <div class="catalog-central-stats">
            <AppStatCard label="Famílias ativas" :value="loading ? '...' : familiesCount" />
            <AppStatCard label="Unidades ativas" :value="loading ? '...' : unitsCount" />
            <AppStatCard label="Classificações ativas" :value="loading ? '...' : classificationsCount" />
            <AppStatCard label="Total de parâmetros" :value="loading ? '...' : totalParameters" />
        </div>

        <p v-if="error" class="text-danger text-sm">{{ error }}</p>

        <div class="catalog-central-grid">
            <AppCard
                v-for="item in parameterLinks"
                :key="`panel-${item.key}`"
                class="catalog-central-panel"
            >
                <h3 class="catalog-panel-title">{{ item.title }}</h3>
                <p class="catalog-panel-copy">{{ item.description }}</p>
                <AppButton @click="goTo(item.path)">{{ item.buttonLabel }}</AppButton>
            </AppCard>
        </div>
    </div>
</template>

<style scoped>
.catalog-central-breadcrumb {
    margin: 0;
    font-size: 0.82rem;
    font-weight: 700;
    color: color-mix(in srgb, var(--color-text-muted) 86%, transparent);
}

.catalog-central-hero {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
}

.catalog-central-hero-main,
.catalog-central-shortcuts {
    padding: 1.5rem;
}

.catalog-central-shortcuts {
    border-top: 1px solid color-mix(in srgb, var(--color-border-strong) 48%, transparent);
}

.catalog-central-kicker {
    margin: 0;
    font-size: 0.78rem;
    letter-spacing: 0.12em;
    font-weight: 800;
    color: color-mix(in srgb, var(--color-primary) 56%, var(--color-text));
}

.catalog-central-title {
    margin: 0.65rem 0 0;
    font-size: 1.9rem;
    line-height: 1.2;
    font-weight: 900;
    color: var(--color-text);
}

.catalog-central-copy {
    margin: 0.8rem 0 0;
    color: var(--color-text-muted);
}

.catalog-central-shortcuts-grid {
    margin-top: 1rem;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr));
    gap: 0.75rem;
}

.catalog-shortcut-btn {
    width: 100%;
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 50%, transparent);
    background: color-mix(in srgb, var(--color-bg-surface) 85%, transparent);
    color: var(--color-text);
    border-radius: var(--radius-md);
    min-height: 3.25rem;
    padding: 0.7rem 0.8rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.55rem;
    font-weight: 700;
    cursor: pointer;
    transition: all var(--transition-fast);
}

.catalog-shortcut-btn:hover {
    border-color: color-mix(in srgb, var(--color-primary) 44%, transparent);
    background: color-mix(in srgb, var(--color-primary) 15%, var(--color-bg-surface));
}

.catalog-shortcut-icon {
    width: 1.9rem;
    height: 1.9rem;
    border-radius: var(--radius-sm);
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 46%, transparent);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: color-mix(in srgb, var(--color-primary) 12%, var(--color-bg-surface));
}

.catalog-central-notice {
    color: var(--color-text);
}

.catalog-central-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr));
    gap: 0.75rem;
}

.catalog-central-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(16.5rem, 1fr));
    gap: 0.95rem;
}

.catalog-central-panel {
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
}

.catalog-panel-title {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--color-text);
}

.catalog-panel-copy {
    margin: 0;
    color: var(--color-text-muted);
    line-height: 1.55;
    min-height: 3.5rem;
}

@media (min-width: 980px) {
    .catalog-central-hero {
        grid-template-columns: minmax(0, 1fr) minmax(0, 0.85fr);
    }

    .catalog-central-shortcuts {
        border-top: 0;
        border-left: 1px solid color-mix(in srgb, var(--color-border-strong) 48%, transparent);
    }
}
</style>
