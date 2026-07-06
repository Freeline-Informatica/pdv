<script setup>
import { FileText, Keyboard, LogOut, Menu, ReceiptText, ScrollText, Settings2, User, UserRoundCog, XCircle } from 'lucide-vue-next';
import { computed } from 'vue';
import AppBadge from '../ui/AppBadge.vue';

const props = defineProps({
    showSettings: {
        type: Boolean,
        default: true,
    },
    budgetLabel: {
        type: String,
        default: 'Orçamento',
    },
    enableCancelTicker: {
        type: Boolean,
        default: false,
    },
});

const quickActions = computed(() => [
    {
        id: 'cancel-item',
        key: 'F2',
        label: 'Cancelamento',
        icon: XCircle,
        tone: 'danger',
    },
    {
        id: 'cash-sales',
        key: 'F3',
        label: 'Vendas do caixa',
        icon: ReceiptText,
        tone: 'default',
    },
    {
        id: 'identify-customer',
        key: 'F10',
        label: 'Cliente',
        icon: User,
        tone: 'default',
    },
    {
        id: 'open-budget',
        key: 'O',
        label: String(props.budgetLabel || 'Orçamento'),
        icon: FileText,
        tone: 'default',
    },
    {
        id: 'identify-seller',
        key: 'V',
        label: 'Vendedor',
        icon: UserRoundCog,
        tone: 'default',
    },
    {
        id: 'open-menu',
        key: 'F11',
        label: 'Menu',
        icon: Menu,
        tone: 'default',
    },
]);

const utilityActions = [
    {
        id: 'open-shortcuts',
        label: 'Atalhos',
        icon: Keyboard,
        tone: 'default',
    },
    {
        id: 'open-menu-fiscal',
        label: 'Fiscal',
        icon: ScrollText,
        tone: 'default',
    },
    {
        id: 'open-settings',
        label: 'Config',
        icon: Settings2,
        tone: 'default',
    },
    {
        id: 'logout',
        label: 'Sair',
        icon: LogOut,
        tone: 'danger',
    },
];

const visibleUtilityActions = computed(() =>
    utilityActions.filter((action) => props.showSettings || action.id !== 'open-settings'),
);

const emit = defineEmits([
    'cancel-item',
    'cash-sales',
    'identify-customer',
    'open-budget',
    'identify-seller',
    'open-menu',
    'open-shortcuts',
    'open-menu-fiscal',
    'open-settings',
    'logout',
]);
</script>

<template>
    <aside class="pos-sidebar" data-nav-region="sidebar">
        <div class="pos-sidebar-status mb-1 rounded-[var(--radius-sm)] bg-[color:color-mix(in_srgb,var(--color-primary)_25%,var(--color-bg-sidebar))] p-2 text-center">
            <img :src="'/logo.png'" alt="Simples PDV" class="mx-auto h-12 w-auto object-contain" />
            <AppBadge variant="success" class="mt-1">Caixa Aberto</AppBadge>
        </div>

        <div class="pos-sidebar-actions">
            <button
                v-for="action in quickActions"
                :key="action.id"
                type="button"
                class="pos-sidebar-action"
                :class="`is-${action.tone}`"
                @click="emit(action.id)"
            >
                <span class="pos-sidebar-action-icon">
                    <component :is="action.icon" class="h-5 w-5" aria-hidden="true" />
                </span>
                <span class="pos-sidebar-action-copy">
                    <span class="pos-sidebar-action-key">{{ action.key }}</span>
                    <span
                        class="pos-sidebar-action-label"
                        :class="{ 'pos-sidebar-action-label--ticker': action.id === 'cancel-item' && props.enableCancelTicker }"
                    >
                        <span
                            v-if="action.id === 'cancel-item' && props.enableCancelTicker"
                            class="pos-sidebar-action-label-ticker-track"
                        >
                            {{ action.label }}
                        </span>
                        <template v-else>
                            {{ action.label }}
                        </template>
                    </span>
                </span>
            </button>
        </div>

        <div class="pos-sidebar-footer-actions">
            <button
                v-for="action in visibleUtilityActions"
                :key="action.id"
                type="button"
                class="pos-sidebar-action is-compact"
                :class="`is-${action.tone}`"
                @click="emit(action.id)"
            >
                <span class="pos-sidebar-action-icon">
                    <component :is="action.icon" class="h-4 w-4" aria-hidden="true" />
                </span>
                <span class="pos-sidebar-action-copy">
                    <span class="pos-sidebar-action-label">{{ action.label }}</span>
                </span>
            </button>
        </div>
    </aside>
</template>
