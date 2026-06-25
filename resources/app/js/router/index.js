import { createRouter, createWebHistory } from 'vue-router';
import api from '../lib/api';
import {
    clearAuthData,
    clearSettingsAccessKey,
    getSettingsAccessKey,
    getTerminalSession,
    getToken,
    getUser,
    setTerminalSession,
    setUser,
} from '../lib/auth';
import { normalizeTerminalSessionWithProfile, resolveTerminalLandingPath } from '../lib/terminalRouting';
import PosLayout from '../layouts/PosLayout.vue';
import PosView from '../views/PosView.vue';
import LoginView from '../views/LoginView.vue';
import TerminalSelectionView from '../views/TerminalSelectionView.vue';
import NotFoundView from '../views/NotFoundView.vue';
import RestaurantAutoServiceView from '../views/restaurant/RestaurantAutoServiceView.vue';
import RestaurantTotemView from '../views/restaurant/RestaurantTotemView.vue';
import RestaurantWaiterView from '../views/restaurant/RestaurantWaiterView.vue';
import RestaurantProductionView from '../views/restaurant/RestaurantProductionView.vue';
import SettingsLayout from '../layouts/SettingsLayout.vue';
import CompanySettingsView from '../views/settings/CompanySettingsView.vue';
import PaymentMethodsView from '../views/settings/PaymentMethodsView.vue';
import PaymentPlansView from '../views/settings/PaymentPlansView.vue';
import AcquirersView from '../views/settings/AcquirersView.vue';
import ProductsView from '../views/settings/ProductsView.vue';
import ProductEditorView from '../views/settings/ProductEditorView.vue';
import CategoriesView from '../views/settings/CategoriesView.vue';
import ThemeSettingsView from '../views/settings/ThemeSettingsView.vue';
import TerminalSettingsView from '../views/settings/TerminalSettingsView.vue';
import TerminalVisualSettingsView from '../views/settings/TerminalVisualSettingsView.vue';
import BackofficeModuleView from '../views/settings/BackofficeModuleView.vue';
import OperatorsView from '../views/settings/OperatorsView.vue';
import AuditLogsView from '../views/settings/AuditLogsView.vue';
import StockMovementsView from '../views/settings/StockMovementsView.vue';
import StockConsultationView from '../views/settings/StockConsultationView.vue';
import StockAdjustmentsView from '../views/settings/StockAdjustmentsView.vue';
import StockInventoryView from '../views/settings/StockInventoryView.vue';
import SalesHistoryView from '../views/settings/SalesHistoryView.vue';
import CashRegistersView from '../views/settings/CashRegistersView.vue';
import CustomersView from '../views/settings/CustomersView.vue';
import SuppliersView from '../views/settings/SuppliersView.vue';
import PurchaseOrdersListView from '../views/settings/PurchaseOrdersListView.vue';
import PurchaseOrderFormView from '../views/settings/PurchaseOrderFormView.vue';
import PurchaseOrderDetailView from '../views/settings/PurchaseOrderDetailView.vue';
import CatalogParametersCentralView from '../views/settings/CatalogParametersCentralView.vue';
import CatalogFamiliesView from '../views/settings/CatalogFamiliesView.vue';
import CatalogUnitsView from '../views/settings/CatalogUnitsView.vue';
import CatalogClassificationsView from '../views/settings/CatalogClassificationsView.vue';
import RestaurantParametersView from '../views/settings/RestaurantParametersView.vue';

const routes = [
    { path: '/login', component: LoginView, meta: { public: true } },
    { path: '/selecionar-terminal', component: TerminalSelectionView },
    {
        path: '/',
        component: PosLayout,
        children: [
            { path: '', component: PosView, meta: { requiresTerminal: true } },
            { path: 'pdv/restaurante/auto-atendimento', component: RestaurantAutoServiceView, meta: { requiresTerminal: true } },
            { path: 'pdv/restaurante/totem', component: RestaurantTotemView, meta: { requiresTerminal: true } },
            { path: 'pdv/restaurante/garcom', component: RestaurantWaiterView, meta: { requiresTerminal: true } },
            { path: 'pdv/restaurante/producao', component: RestaurantProductionView, meta: { requiresTerminal: true } },
            {
                path: 'pdv/restaurante/producao/cozinha',
                component: RestaurantProductionView,
                meta: { requiresTerminal: true, productionSector: 'cozinha' },
            },
            {
                path: 'pdv/restaurante/producao/bar',
                component: RestaurantProductionView,
                meta: { requiresTerminal: true, productionSector: 'bar' },
            },
        ],
    },
    {
        path: '/configuracoes',
        component: SettingsLayout,
        children: [
            { path: '', redirect: '/configuracoes/empresa' },
            { path: 'empresa', component: CompanySettingsView },
            { path: 'operadores', component: OperatorsView },
            {
                path: 'terminais',
                component: TerminalSettingsView,
            },
            {
                path: 'auditoria',
                component: AuditLogsView,
            },
            { path: 'pagamentos', component: PaymentMethodsView },
            { path: 'planos', component: PaymentPlansView },
            { path: 'adquirentes', component: AcquirersView },
            { path: 'produtos', component: ProductsView, meta: { standaloneOnly: true } },
            { path: 'produtos/novo', component: ProductEditorView, meta: { standaloneOnly: true } },
            { path: 'produtos/:produtoId/editar', component: ProductEditorView, meta: { standaloneOnly: true } },
            { path: 'categorias', component: CategoriesView, meta: { standaloneOnly: true } },
            {
                path: 'restaurante/parametros',
                component: RestaurantParametersView,
                meta: { standaloneOnly: true },
            },
            {
                path: 'catalogo/parametros',
                component: CatalogParametersCentralView,
                meta: { standaloneOnly: true },
            },
            {
                path: 'catalogo/parametros/familias',
                component: CatalogFamiliesView,
                meta: {
                    standaloneOnly: true,
                    parameterKey: 'familias',
                    parameterTitle: 'Famílias',
                    parameterSingular: 'família',
                    parameterSubtitle: 'Agrupamentos comerciais usados no cadastro e nas regras do catálogo de produtos.',
                },
            },
            {
                path: 'catalogo/parametros/unidades-medida',
                component: CatalogUnitsView,
                meta: {
                    standaloneOnly: true,
                    parameterKey: 'unidades_medida',
                    parameterTitle: 'Unidades de Medida',
                    parameterSingular: 'unidade',
                    parameterSubtitle: 'Unidades comerciais e fiscais disponíveis para produto, estoque e conversões.',
                },
            },
            {
                path: 'catalogo/parametros/classificacoes-mercadologicas',
                component: CatalogClassificationsView,
                meta: {
                    standaloneOnly: true,
                    parameterKey: 'classificacoes_mercadologicas',
                    parameterTitle: 'Classificação Mercadológica',
                    parameterSingular: 'classificação',
                    parameterSubtitle: 'Hierarquia mercadológica utilizada na organização e governança do catálogo.',
                },
            },
            {
                path: 'estoque',
                component: BackofficeModuleView,
                meta: {
                    moduleTitle: 'Dashboard de Estoque',
                    moduleSubtitle: 'Visão geral do controle de estoque',
                    moduleHint: 'O painel de indicadores e atalhos do estoque está sendo atualizado conforme o novo layout.',
                },
            },
            {
                path: 'estoque/consulta',
                component: StockConsultationView,
            },
            {
                path: 'estoque/movimentacoes',
                component: StockMovementsView,
            },
            {
                path: 'estoque/ajustes',
                component: StockAdjustmentsView,
            },
            {
                path: 'estoque/inventario',
                component: StockInventoryView,
            },
            {
                path: 'vendas',
                component: SalesHistoryView,
            },
            {
                path: 'caixa',
                component: CashRegistersView,
            },
            {
                path: 'clientes',
                component: CustomersView,
            },
            {
                path: 'compras',
                component: PurchaseOrdersListView,
            },
            {
                path: 'compras/nova',
                component: PurchaseOrderFormView,
            },
            {
                path: 'compras/:purchaseOrderId/editar',
                component: PurchaseOrderFormView,
            },
            {
                path: 'compras/:purchaseOrderId',
                component: PurchaseOrderDetailView,
            },
            {
                path: 'fornecedores',
                component: SuppliersView,
            },
            { path: 'terminal', redirect: '/configuracoes/terminais' },
            { path: 'tema', component: ThemeSettingsView },
            {
                path: 'terminal-visual',
                component: TerminalVisualSettingsView,
            },
        ],
    },
    { path: '/:pathMatch(.*)*', component: NotFoundView, meta: { public: true } },
];

const router = createRouter({
    history: createWebHistory('/pdv/'),
    routes,
});

let meRequest = null;
let companySettingsRequest = null;

async function ensureCurrentUser() {
    const cachedUser = getUser();
    if (cachedUser) return cachedUser;

    if (!meRequest) {
        meRequest = api
            .get('/auth/me')
            .then(({ data }) => {
                setUser(data);
                return data;
            })
            .catch(() => {
                clearAuthData();
                return null;
            })
            .finally(() => {
                meRequest = null;
            });
    }

    return meRequest;
}

async function ensureCompanySettings() {
    if (!companySettingsRequest) {
        companySettingsRequest = api
            .get('/settings/company')
            .then(({ data }) => data || {})
            .catch(() => null)
            .finally(() => {
                companySettingsRequest = null;
            });
    }

    return companySettingsRequest;
}

router.beforeEach(async (to, from) => {
    if (to.meta.public) {
        if (to.path === '/login' && getToken()) {
            const user = await ensureCurrentUser();
            if (user) {
                return getTerminalSession() ? '/' : '/selecionar-terminal';
            }
        }

        return true;
    }

    if (!getToken()) {
        clearAuthData();
        return '/login';
    }

    const user = await ensureCurrentUser();
    if (!user) {
        return '/login';
    }

    if (to.meta.requiresTerminal && !getTerminalSession()) {
        return '/selecionar-terminal';
    }

    if (to.path === '/') {
        const terminalSession = getTerminalSession();
        if (terminalSession) {
            try {
                const terminalId = String(terminalSession?.id || '').trim();
                let nextSession = terminalSession;

                if (terminalId !== '') {
                    const { data } = await api.get('/pos/company-profile', {
                        params: {
                            terminal_id: terminalId,
                        },
                    });
                    nextSession = normalizeTerminalSessionWithProfile(terminalSession, data);
                    setTerminalSession(nextSession);
                }

                const landingPath = resolveTerminalLandingPath(nextSession);
                if (landingPath !== '/') {
                    return landingPath;
                }
            } catch {
                const fallbackPath = resolveTerminalLandingPath(terminalSession);
                if (fallbackPath !== '/') {
                    return fallbackPath;
                }
            }
        }
    }

    const toSettings = to.path.startsWith('/configuracoes');
    const fromSettings = from.path.startsWith('/configuracoes');

    if (fromSettings && !toSettings) {
        clearSettingsAccessKey();
    }

    if (!toSettings) {
        return true;
    }

    if (user.role === 'admin') {
        if (to.meta.standaloneOnly) {
            const companySettings = await ensureCompanySettings();
            if (companySettings?.managed_by_erp) {
                return '/configuracoes/empresa';
            }
        }

        return true;
    }

    if (user.role === 'operador') {
        if (getSettingsAccessKey()) {
            if (to.meta.standaloneOnly) {
                const companySettings = await ensureCompanySettings();
                if (companySettings?.managed_by_erp) {
                    return '/configuracoes/empresa';
                }
            }

            return true;
        }

        return {
            path: '/',
            query: {
                ...to.query,
                unlockSettings: '1',
            },
        };
    }

    return true;
});

export default router;
