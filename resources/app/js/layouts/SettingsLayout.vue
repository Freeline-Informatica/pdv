<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { ArrowLeft, LogOut, Monitor } from 'lucide-vue-next';
import { clearAuthData, exitIntegratedPdv, resolvePdvExitLabel } from '../lib/auth';
import { useRoute, useRouter } from 'vue-router';
import api from '../lib/api';
import AppShell from '../components/layout/AppShell.vue';
import AppTopbar from '../components/layout/AppTopbar.vue';
import AppContent from '../components/layout/AppContent.vue';
import SettingsSidebar from '../components/settings/SettingsSidebar.vue';
import AppButton from '../components/ui/AppButton.vue';

const route = useRoute();
const router = useRouter();
const sidebarCollapsed = ref(true);
const companyLayoutMode = ref('');
const companyLayoutLoaded = ref(false);
const isManagedByErp = ref(false);
const exitLabel = computed(() => resolvePdvExitLabel());

const navigationSectionsBase = [
    {
        id: 'configuracoes',
        label: 'CONFIGURAÇÕES',
        items: [
            {
                path: '/configuracoes/empresa',
                label: 'Dados da Empresa',
                description: 'Cadastro, NF-e e certificado',
                icon: 'building-2',
            },
            {
                path: '/configuracoes/operadores',
                label: 'Operadores',
                description: 'Usuários do PDV',
                icon: 'users-round',
            },
            {
                path: '/configuracoes/terminais',
                label: 'Terminais',
                description: 'Terminais de PDV',
                icon: 'monitor',
            },
            {
                path: '/configuracoes/auditoria',
                label: 'Auditoria',
                description: 'Logs e rastreabilidade',
                icon: 'shield-check',
            },
            {
                path: '/configuracoes/tema',
                label: 'Temas',
                description: 'Cores, modo claro/escuro e assinatura visual',
                icon: 'palette',
            },
        ],
    },
    {
        id: 'catalogo',
        label: 'CATÁLOGO',
        items: [
            {
                path: '/configuracoes/produtos',
                label: 'Produtos',
                description: 'Produtos e composição',
                icon: 'package',
            },
            {
                path: '/configuracoes/categorias',
                label: 'Categorias',
                description: 'Agrupamento de produtos',
                icon: 'tags',
            },
            {
                path: '/configuracoes/catalogo/parametros',
                label: 'Parâmetros',
                description: 'Famílias, unidades e classificações',
                icon: 'sliders-horizontal',
            },
        ],
    },
    {
        id: 'estoque',
        label: 'ESTOQUE',
        items: [
            {
                path: '/configuracoes/estoque',
                label: 'Dashboard',
                description: 'Visão geral do estoque',
                icon: 'chart-column',
            },
            {
                path: '/configuracoes/estoque/consulta',
                label: 'Consulta',
                description: 'Pesquisar e filtrar produtos',
                icon: 'search',
            },
            {
                path: '/configuracoes/estoque/movimentacoes',
                label: 'Movimentações',
                description: 'Kardex e histórico',
                icon: 'arrow-left-right',
            },
            {
                path: '/configuracoes/estoque/ajustes',
                label: 'Ajustes',
                description: 'Solicitações de ajuste',
                icon: 'pencil-line',
            },
            {
                path: '/configuracoes/estoque/inventario',
                label: 'Inventário',
                description: 'Contagem física',
                icon: 'clipboard-check',
            },
        ],
    },
    {
        id: 'vendas',
        label: 'VENDAS',
        items: [
            {
                path: '/configuracoes/vendas',
                label: 'Histórico de Vendas',
                description: 'Listagem e detalhes',
                icon: 'badge-dollar-sign',
            },
            {
                path: '/configuracoes/caixa',
                label: 'Caixa',
                description: 'Abertura, movimentações e fechamento',
                icon: 'wallet-cards',
            },
        ],
    },
    {
        id: 'compras',
        label: 'COMPRAS',
        items: [
            {
                path: '/configuracoes/compras',
                label: 'Pedidos de Compra',
                description: 'Listagem e recebimento',
                icon: 'shopping-cart',
            },
            {
                path: '/configuracoes/fornecedores',
                label: 'Fornecedores',
                description: 'Cadastro de fornecedores',
                icon: 'truck',
            },
        ],
    },
    {
        id: 'pagamentos',
        label: 'PAGAMENTOS',
        items: [
            {
                path: '/configuracoes/pagamentos',
                label: 'Meios de Pagamento',
                description: 'Cadastro e regras de uso',
                icon: 'credit-card',
            },
            {
                path: '/configuracoes/adquirentes',
                label: 'Adquirentes',
                description: 'Taxas, bancos e TEF',
                icon: 'landmark',
            },
            {
                path: '/configuracoes/planos',
                label: 'Planos de Pagamento',
                description: 'Parcelas, juros e condições',
                icon: 'layers-3',
            },
        ],
    },
];

const restaurantSection = Object.freeze({
    id: 'restaurante',
    label: 'RESTAURANTE',
    items: [
        {
            path: '/configuracoes/restaurante/parametros',
            label: 'Parâmetros',
            description: 'Mesas, fichas e operação',
            icon: 'utensils-crossed',
        },
    ],
});

function normalizeLayoutMode(value) {
    const normalized = String(value || '').trim().toLowerCase();
    return ['varejo', 'restaurante', 'servicos'].includes(normalized) ? normalized : 'varejo';
}

const shouldShowRestaurantSection = computed(() => {
    if (!companyLayoutLoaded.value) return false;
    if (isManagedByErp.value) return false;
    return companyLayoutMode.value === 'restaurante';
});

const navigationSections = computed(() => {
    const baseSections = isManagedByErp.value
        ? navigationSectionsBase
            .map((section) => ['catalogo', 'estoque', 'compras'].includes(section.id) ? null : section)
            .filter(Boolean)
        : navigationSectionsBase;

    if (!shouldShowRestaurantSection.value) {
        return baseSections;
    }

    return [
        baseSections[0],
        restaurantSection,
        ...baseSections.slice(1),
    ];
});

function handleCompanyLayoutUpdated(event) {
    const mode = normalizeLayoutMode(event?.detail?.pdv_layout_mode);
    companyLayoutMode.value = mode;
    companyLayoutLoaded.value = true;
}

async function loadCompanyLayoutMode() {
    companyLayoutLoaded.value = false;

    try {
        const { data } = await api.get('/settings/company');
        companyLayoutMode.value = normalizeLayoutMode(data?.pdv_layout_mode);
        isManagedByErp.value = Boolean(data?.managed_by_erp);
    } catch (error) {
        companyLayoutMode.value = 'varejo';
        isManagedByErp.value = false;
    } finally {
        companyLayoutLoaded.value = true;
    }
}

function logout() {
    if (exitIntegratedPdv()) return;

    clearAuthData();
    router.push('/login');
}

function goToPos() {
    router.push('/');
}

function goBack() {
    if (window.history.length > 1) {
        router.back();
        return;
    }

    router.push('/configuracoes/empresa');
}

onMounted(() => {
    loadCompanyLayoutMode();
    window.addEventListener('company-layout-mode-updated', handleCompanyLayoutUpdated);
});

onBeforeUnmount(() => {
    window.removeEventListener('company-layout-mode-updated', handleCompanyLayoutUpdated);
});
</script>

<template>
    <AppShell :style="{ '--app-sidebar-width': sidebarCollapsed ? '5.25rem' : '19.5rem' }">
        <template #topbar>
            <AppTopbar>
                <div class="flex items-center gap-3 min-w-0">
                    <button type="button" class="retaguarda-back-btn" title="Voltar" @click="goBack">
                        <ArrowLeft class="h-4 w-4" aria-hidden="true" />
                    </button>
                    <div class="min-w-0">
                        <p class="retaguarda-title">Retaguarda</p>
                        <p class="retaguarda-subtitle">Configurações do sistema</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <AppButton variant="ghost" @click="sidebarCollapsed = !sidebarCollapsed">
                        {{ sidebarCollapsed ? 'Abrir Menu' : 'Fechar Menu' }}
                    </AppButton>
                    <AppButton variant="secondary" @click="goToPos">
                        <Monitor class="h-4 w-4" aria-hidden="true" />
                        Abrir PDV
                    </AppButton>
                    <AppButton variant="ghost" @click="logout">
                        <LogOut class="h-4 w-4" aria-hidden="true" />
                        {{ exitLabel }}
                    </AppButton>
                </div>
            </AppTopbar>
        </template>

        <template #sidebar>
            <SettingsSidebar
                :sections="navigationSections"
                :active-path="route.path"
                :collapsed="sidebarCollapsed"
                :exit-label="exitLabel"
                @navigate="router.push($event)"
                @toggle="sidebarCollapsed = !sidebarCollapsed"
                @go-pos="goToPos"
                @logout="logout"
            />
        </template>

        <AppContent>
            <router-view />
        </AppContent>
    </AppShell>
</template>

<style scoped>
.retaguarda-back-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.2rem;
    height: 2.2rem;
    border-radius: var(--radius-sm);
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 44%, transparent);
    background: color-mix(in srgb, var(--color-bg-surface) 75%, transparent);
    color: var(--color-text);
    transition: all var(--transition-fast);
}

.retaguarda-back-btn:hover {
    background: color-mix(in srgb, var(--color-primary) 15%, var(--color-bg-surface));
    border-color: color-mix(in srgb, var(--color-primary) 35%, transparent);
}

.retaguarda-title {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 900;
    color: var(--color-text);
}

.retaguarda-subtitle {
    margin: 0.05rem 0 0;
    color: var(--color-text-muted);
    font-size: 0.84rem;
}

</style>
