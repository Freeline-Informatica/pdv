<script setup>
import { computed, ref, watch } from 'vue';
import {
    ArrowLeftRight,
    BadgeDollarSign,
    Building2,
    ChartColumn,
    ChevronDown,
    ClipboardCheck,
    CreditCard,
    Landmark,
    Layers3,
    Monitor,
    Package,
    Palette,
    PencilLine,
    Search,
    ShieldCheck,
    SlidersHorizontal,
    SidebarClose,
    SidebarOpen,
    LogOut,
    ShoppingCart,
    Tags,
    Truck,
    UtensilsCrossed,
    UsersRound,
    WalletCards,
} from 'lucide-vue-next';
import AppSidebar from '../layout/AppSidebar.vue';
import AppButton from '../ui/AppButton.vue';
import AppUserMenu from '../layout/AppUserMenu.vue';

const props = defineProps({
    sections: {
        type: Array,
        default: () => [],
    },
    activePath: {
        type: String,
        default: '',
    },
    collapsed: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['navigate', 'toggle', 'go-pos', 'logout']);

const iconMap = {
    'arrow-left-right': ArrowLeftRight,
    'badge-dollar-sign': BadgeDollarSign,
    'building-2': Building2,
    'chart-column': ChartColumn,
    'clipboard-check': ClipboardCheck,
    'credit-card': CreditCard,
    landmark: Landmark,
    'layers-3': Layers3,
    palette: Palette,
    monitor: Monitor,
    package: Package,
    'pencil-line': PencilLine,
    search: Search,
    'shield-check': ShieldCheck,
    'sliders-horizontal': SlidersHorizontal,
    'shopping-cart': ShoppingCart,
    tags: Tags,
    truck: Truck,
    'utensils-crossed': UtensilsCrossed,
    'users-round': UsersRound,
    'wallet-cards': WalletCards,
};

const expandedBySection = ref({});

function initializeExpandedState() {
    const nextState = {};
    props.sections.forEach((section) => {
        nextState[section.id] = false;
    });
    expandedBySection.value = nextState;
}

initializeExpandedState();

watch(
    () => props.sections,
    () => {
        initializeExpandedState();
    },
    { deep: true },
);

watch(
    () => props.collapsed,
    (collapsed) => {
        if (!collapsed) {
            initializeExpandedState();
        }
    },
);

const flattenedItems = computed(() => props.sections.flatMap((section) => section.items || []));
const nestedActivePaths = new Set([
    '/configuracoes/catalogo/parametros',
    '/configuracoes/compras',
    '/configuracoes/vendas',
]);

function getNavIcon(iconName) {
    return iconMap[iconName] || Building2;
}

function isSectionExpanded(sectionId) {
    return expandedBySection.value[sectionId] !== false;
}

function toggleSection(sectionId) {
    if (props.collapsed) return;
    expandedBySection.value[sectionId] = !isSectionExpanded(sectionId);
}

function isItemActive(path) {
    if (props.activePath === path) return true;
    return nestedActivePaths.has(path) && props.activePath.startsWith(`${path}/`);
}
</script>

<template>
    <AppSidebar class="settings-sidebar-shell">
        <div class="settings-sidebar-inner" :class="{ 'is-collapsed': props.collapsed }">
            <div class="settings-sidebar-head" :class="{ 'is-collapsed': props.collapsed }">
                <div v-if="!props.collapsed" class="settings-sidebar-brand">
                    <img :src="'/logo.png'" alt="Simples PDV" class="settings-sidebar-logo" />
                    <div class="settings-sidebar-brand-copy">
                        <p class="settings-sidebar-title">Retaguarda</p>
                        <p class="settings-sidebar-subtitle">Configurações do sistema</p>
                    </div>
                </div>
                <button
                    type="button"
                    class="settings-collapse-btn u-focus"
                    :title="props.collapsed ? 'Expandir menu' : 'Recolher menu'"
                    @click="emit('toggle')"
                >
                    <SidebarOpen v-if="props.collapsed" class="h-4 w-4" aria-hidden="true" />
                    <SidebarClose v-else class="h-4 w-4" aria-hidden="true" />
                </button>
            </div>

            <nav v-if="props.collapsed" class="settings-sidebar-nav is-collapsed" aria-label="Navegação da retaguarda">
                <button
                    v-for="item in flattenedItems"
                    :key="item.path"
                    type="button"
                    class="settings-entry is-collapsed"
                    :class="{ 'is-active': isItemActive(item.path) }"
                    :title="item.label"
                    @click="emit('navigate', item.path)"
                >
                    <span class="settings-item-icon" aria-hidden="true">
                        <component :is="getNavIcon(item.icon)" class="settings-item-svg" />
                    </span>
                </button>
            </nav>

            <nav v-else class="settings-sidebar-nav" aria-label="Navegação da retaguarda">
                <section v-for="section in props.sections" :key="section.id" class="settings-nav-section">
                    <button
                        type="button"
                        class="settings-section-trigger"
                        :class="{ 'is-open': isSectionExpanded(section.id) }"
                        @click="toggleSection(section.id)"
                    >
                        <span class="settings-section-trigger__label">{{ section.label }}</span>
                        <ChevronDown class="h-4 w-4 settings-section-trigger__icon" :class="{ 'rotate-180': isSectionExpanded(section.id) }" aria-hidden="true" />
                    </button>

                    <div v-if="isSectionExpanded(section.id)" class="settings-section-items">
                        <button
                            v-for="item in section.items"
                            :key="item.path"
                            type="button"
                            class="settings-entry"
                            :class="{ 'is-active': isItemActive(item.path) }"
                            @click="emit('navigate', item.path)"
                        >
                            <span class="settings-item-icon" aria-hidden="true">
                                <component :is="getNavIcon(item.icon)" class="settings-item-svg" />
                            </span>
                            <span class="settings-entry-copy">
                                <span class="settings-entry-label">{{ item.label }}</span>
                                <span class="settings-entry-description">{{ item.description }}</span>
                            </span>
                        </button>
                    </div>
                </section>
            </nav>

            <footer class="settings-sidebar-footer" :class="{ 'is-collapsed': props.collapsed }">
                <div class="settings-sidebar-user">
                    <AppUserMenu class="settings-user-chip" :compact="props.collapsed">
                        <template v-if="!props.collapsed">Freeline Admin</template>
                    </AppUserMenu>
                </div>

                <div class="settings-sidebar-actions">
                    <AppButton
                        variant="ghost"
                        class="settings-footer-btn"
                        :title="props.collapsed ? 'Abrir PDV' : ''"
                        @click="emit('go-pos')"
                    >
                        <Monitor class="h-4 w-4" aria-hidden="true" />
                        <span v-if="!props.collapsed">Abrir PDV</span>
                    </AppButton>
                    <AppButton
                        variant="danger"
                        class="settings-footer-btn"
                        :title="props.collapsed ? 'Sair' : ''"
                        @click="emit('logout')"
                    >
                        <LogOut class="h-4 w-4" aria-hidden="true" />
                        <span v-if="!props.collapsed">Sair</span>
                    </AppButton>
                </div>
            </footer>
        </div>
    </AppSidebar>
</template>

<style scoped>
:global(.app-sidebar.settings-sidebar-shell) {
    height: 100%;
    max-height: 100%;
    min-height: 0;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.settings-sidebar-inner {
    flex: 1 1 auto;
    height: 100%;
    max-height: 100%;
    min-height: 0;
    display: flex;
    flex-direction: column;
    gap: var(--space-3);
    padding: var(--space-3);
    overflow: hidden;
}

.settings-sidebar-inner.is-collapsed {
    padding-left: var(--space-2);
    padding-right: var(--space-2);
    align-items: center;
    gap: var(--space-2);
}

.settings-sidebar-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-2);
}

.settings-sidebar-head.is-collapsed {
    justify-content: center;
}

.settings-collapse-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.2rem;
    height: 2.2rem;
    border-radius: var(--radius-sm);
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 40%, transparent);
    background: color-mix(in srgb, var(--color-bg-sidebar-item) 60%, transparent);
    color: var(--color-text-sidebar);
    cursor: pointer;
    transition: all var(--transition-fast);
}

.settings-collapse-btn:hover {
    background: color-mix(in srgb, var(--color-primary) 24%, var(--color-bg-sidebar-item));
}

.settings-sidebar-brand {
    flex: 1 1 auto;
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 0.58rem;
}

.settings-sidebar-logo {
    height: 1.95rem;
    width: auto;
    object-fit: contain;
    flex: none;
}

.settings-sidebar-brand-copy {
    min-width: 0;
}

.settings-sidebar-title {
    margin: 0;
    font-weight: 900;
    letter-spacing: 0.01em;
    color: var(--color-text-sidebar);
}

.settings-sidebar-subtitle {
    margin: 0.1rem 0 0;
    font-size: 0.75rem;
    color: color-mix(in srgb, var(--color-text-sidebar) 70%, transparent);
}

.settings-sidebar-nav {
    flex: 1 1 auto;
    height: 100%;
    max-height: 100%;
    min-height: 0;
    overflow-y: auto;
    overflow-x: hidden;
    display: grid;
    align-content: start;
    gap: 0.9rem;
    padding-right: 0.35rem;
    padding-bottom: var(--space-2);
    overscroll-behavior: contain;
    scrollbar-gutter: stable;
    scrollbar-width: thin;
    scrollbar-color: color-mix(in srgb, var(--color-text-sidebar) 38%, transparent) transparent;
}

.settings-sidebar-nav.is-collapsed {
    width: 100%;
    display: flex;
    flex-direction: column;
    justify-items: center;
    align-items: center;
    padding-inline: 0;
    padding-right: 0;
    gap: 0.5rem;
    scrollbar-gutter: stable both-edges;
}

.settings-sidebar-nav.is-collapsed .settings-entry.is-collapsed {
    margin-inline: auto;
}

.settings-sidebar-nav::-webkit-scrollbar {
    width: 0.46rem;
}

.settings-sidebar-nav::-webkit-scrollbar-track {
    background: transparent;
}

.settings-sidebar-nav::-webkit-scrollbar-thumb {
    background: color-mix(in srgb, var(--color-text-sidebar) 40%, transparent);
    border-radius: 999px;
    border: 2px solid transparent;
    background-clip: content-box;
}

.settings-sidebar-nav::-webkit-scrollbar-thumb:hover {
    background: color-mix(in srgb, var(--color-text-sidebar) 56%, transparent);
    background-clip: content-box;
}

.settings-sidebar-footer {
    margin-top: auto;
    padding-top: var(--space-2);
    border-top: 1px solid color-mix(in srgb, var(--color-border-strong) 35%, transparent);
    display: grid;
    gap: 0.45rem;
    background: transparent;
}

.settings-sidebar-user {
    min-width: 0;
}

.settings-sidebar-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.35rem;
}

.settings-footer-btn {
    width: 100%;
}

.settings-sidebar-footer.is-collapsed {
    width: 100%;
    display: grid;
    justify-items: center;
}

.settings-sidebar-footer.is-collapsed .settings-sidebar-actions {
    grid-template-columns: 1fr;
    width: min-content;
    justify-items: center;
}

.settings-sidebar-footer.is-collapsed .settings-footer-btn {
    width: 2.7rem;
    min-height: 2.7rem;
    padding: 0;
    border-radius: 0.9rem;
}

.settings-sidebar-footer.is-collapsed :deep(.settings-user-chip) {
    width: 2.7rem;
    min-height: 2.7rem;
    padding: 0;
    border-radius: 999px;
    border-color: color-mix(in srgb, var(--color-border-strong) 42%, transparent);
    background: color-mix(in srgb, var(--color-bg-sidebar-item) 78%, transparent);
    color: var(--color-text-sidebar);
}

.settings-nav-section {
    display: grid;
    gap: 0.28rem;
}

.settings-section-trigger {
    width: 100%;
    border: 0;
    background: transparent;
    color: color-mix(in srgb, var(--color-text-sidebar) 72%, transparent);
    font-size: 0.73rem;
    letter-spacing: 0.08em;
    font-weight: 900;
    text-transform: uppercase;
    padding: 0.2rem 0.34rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: color var(--transition-fast), background var(--transition-fast);
    border-radius: var(--radius-xs);
}

.settings-section-trigger:hover {
    color: var(--color-text-sidebar);
    background: color-mix(in srgb, var(--color-bg-sidebar-item) 55%, transparent);
}

.settings-section-trigger.is-open {
    color: var(--color-text-sidebar);
}

.settings-section-trigger__label {
    min-width: 0;
}

.settings-section-trigger__icon {
    flex: none;
}

.settings-section-items {
    display: grid;
    gap: 0.2rem;
}

.settings-entry {
    width: 100%;
    border: 1px solid transparent;
    background: transparent;
    color: color-mix(in srgb, var(--color-text-sidebar) 76%, transparent);
    border-radius: var(--radius-sm);
    text-align: left;
    min-height: 3.1rem;
    padding: 0.55rem 0.66rem;
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    align-items: center;
    column-gap: 0.62rem;
    transition: all var(--transition-fast);
}

.settings-entry:hover {
    background: color-mix(in srgb, var(--color-bg-sidebar-item) 76%, transparent);
    color: var(--color-text-sidebar);
}

.settings-entry.is-active {
    background: color-mix(in srgb, var(--color-primary) 24%, var(--color-bg-sidebar));
    border-color: color-mix(in srgb, var(--color-primary) 42%, transparent);
    color: var(--color-text-sidebar);
}

.settings-entry.is-collapsed {
    width: 2.7rem;
    min-height: 2.7rem;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.settings-item-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.45rem;
    height: 1.45rem;
    border-radius: 0.5rem;
    background: color-mix(in srgb, var(--color-bg-sidebar-item) 56%, transparent);
    flex: none;
}

.settings-entry.is-active .settings-item-icon {
    background: color-mix(in srgb, var(--color-primary) 26%, var(--color-bg-sidebar-item));
}

.settings-item-svg {
    width: 0.95rem;
    height: 0.95rem;
    display: block;
}

.settings-entry-copy {
    min-width: 0;
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.08rem;
    text-align: left;
}

.settings-entry-label {
    display: block;
    width: 100%;
    font-size: 1.03rem;
    line-height: 1.2;
    font-weight: 700;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.settings-entry-description {
    display: block;
    width: 100%;
    font-size: 0.78rem;
    line-height: 1.2;
    color: color-mix(in srgb, currentColor 75%, transparent);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>
