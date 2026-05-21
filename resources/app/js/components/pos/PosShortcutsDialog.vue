<script setup>
import {
    ClipboardList,
    Eraser,
    FileText,
    HelpCircle,
    Keyboard,
    Percent,
    Plus,
    ReceiptText,
    Search,
    Settings2,
    ShoppingCart,
    Trash2,
    User,
    Wallet,
    X,
} from 'lucide-vue-next';
import AppModal from '../ui/AppModal.vue';
import { getTerminalSession } from '../../lib/auth';

defineProps({
    open: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close']);

const columnA = [
    { key: 'F1', label: 'Finaliza a venda', icon: ReceiptText },
    { key: 'F2', label: 'Abrir cancelamento', icon: Trash2 },
    { key: 'F3', label: 'Cancelar venda', icon: X },
    { key: 'A', label: 'Ajuda', icon: HelpCircle },
    { key: 'M', label: 'Mesa/Conta', icon: ClipboardList },
    { key: 'R', label: 'Recebimento carnê', icon: Wallet },
    { key: 'O', label: 'Orçamento', icon: FileText },
];

const columnB = [
    { key: 'F6', label: 'Consulta produto', icon: Search },
    { key: 'F7', label: 'Abrir gaveta', icon: ShoppingCart },
    { key: 'F8', label: 'Operações TEF', icon: Settings2 },
    { key: 'F9', label: 'Limpa tela', icon: Eraser },
    { key: 'F10', label: 'Identificar cliente', icon: User },
    { key: 'F11', label: 'Menu de opções', icon: Settings2 },
    { key: 'V', label: 'Identificar vendedor', icon: User },
];

const adjustments = [
    { key: '+', label: 'Acréscimo no item (valor ou %)', icon: Plus },
    { key: 'D', label: 'Desconto no item (valor ou %)', icon: Percent },
    { key: 'Ctrl + *', label: 'Abrir multiplicador de quantidade', icon: X },
    { key: '*N', label: 'Aplicar multiplicador direto na busca (ex.: *4)', icon: X },
];

const navigation = [
    { key: '↑ ↓ ← →', label: 'Navega entre os componentes na tela', icon: Keyboard },
    { key: 'AltGr + WASD', label: 'Navegação auxiliar (equivale às setas)', icon: Keyboard },
    { key: 'Enter', label: 'Aciona clique no elemento focado', icon: Keyboard },
];

const terminalSession = getTerminalSession();
const isRestaurantTerminal = String(terminalSession?.layoutMode || '').toLowerCase() === 'restaurante';
const restaurantLinks = [
    { to: '/pdv/restaurante/auto-atendimento', label: 'Autoatendimento de mesa' },
    { to: '/pdv/restaurante/totem', label: 'Totem' },
    { to: '/pdv/restaurante/garcom', label: 'Comanda do garcom' },
    { to: '/pdv/restaurante/producao', label: 'Producao (KDS)' },
];
</script>

<template>
    <AppModal :open="open" title="Atalhos do PDV" width-class="max-w-5xl" @close="emit('close')">
        <div class="shortcuts-head">
            <span class="shortcuts-chip">
                <Keyboard class="h-4 w-4" aria-hidden="true" />
                Teclas rápidas
            </span>
            <p class="text-sm text-muted">
                Consulta rápida de atalhos do teclado para operação de caixa.
            </p>
        </div>

        <div class="shortcuts-grid">
            <section class="shortcuts-column">
                <ul class="shortcuts-list">
                    <li v-for="item in columnA" :key="item.key" class="shortcut-row">
                        <div class="shortcut-left">
                            <kbd class="shortcut-key">{{ item.key }}</kbd>
                            <component :is="item.icon" class="h-4 w-4 text-muted" aria-hidden="true" />
                            <span class="shortcut-label">{{ item.label }}</span>
                        </div>
                    </li>
                </ul>
            </section>

            <section class="shortcuts-column">
                <ul class="shortcuts-list">
                    <li v-for="item in columnB" :key="item.key" class="shortcut-row">
                        <div class="shortcut-left">
                            <kbd class="shortcut-key">{{ item.key }}</kbd>
                            <component :is="item.icon" class="h-4 w-4 text-muted" aria-hidden="true" />
                            <span class="shortcut-label">{{ item.label }}</span>
                        </div>
                    </li>
                </ul>
            </section>
        </div>

        <section class="shortcuts-adjustments">
            <h4 class="shortcuts-section-title">Ajustes Rápidos</h4>
            <ul class="shortcuts-list">
                <li v-for="item in adjustments" :key="item.key" class="shortcut-row">
                    <div class="shortcut-left">
                        <kbd class="shortcut-key">{{ item.key }}</kbd>
                        <component :is="item.icon" class="h-4 w-4 text-muted" aria-hidden="true" />
                        <span class="shortcut-label">{{ item.label }}</span>
                    </div>
                </li>
            </ul>
        </section>

        <section class="shortcuts-adjustments">
            <h4 class="shortcuts-section-title">Navegação</h4>
            <ul class="shortcuts-list">
                <li v-for="item in navigation" :key="item.key" class="shortcut-row">
                    <div class="shortcut-left">
                        <kbd class="shortcut-key">{{ item.key }}</kbd>
                        <component :is="item.icon" class="h-4 w-4 text-muted" aria-hidden="true" />
                        <span class="shortcut-label">{{ item.label }}</span>
                    </div>
                </li>
            </ul>
        </section>

        <section v-if="isRestaurantTerminal" class="shortcuts-adjustments">
            <h4 class="shortcuts-section-title">Modos Restaurante</h4>
            <div class="shortcuts-restaurant-links">
                <RouterLink
                    v-for="link in restaurantLinks"
                    :key="link.to"
                    :to="link.to"
                    class="shortcuts-restaurant-link"
                    @click="emit('close')"
                >
                    {{ link.label }}
                </RouterLink>
            </div>
        </section>
    </AppModal>
</template>

<style scoped>
.shortcuts-head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.shortcuts-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border-radius: 999px;
    border: 1px solid color-mix(in srgb, var(--color-primary) 38%, transparent);
    background: color-mix(in srgb, var(--color-primary) 15%, var(--color-bg-surface));
    color: var(--color-text);
    font-size: 0.78rem;
    font-weight: 700;
    padding: 0.3rem 0.7rem;
}

.shortcuts-grid {
    display: grid;
    gap: 0.9rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.shortcuts-column {
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    background: color-mix(in srgb, var(--color-bg-elevated) 78%, var(--color-bg-surface));
    padding: 0.8rem;
}

.shortcuts-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 0.45rem;
}

.shortcut-row {
    border: 1px solid transparent;
    border-radius: 0.68rem;
    padding: 0.42rem 0.45rem;
    transition: all var(--transition-fast);
}

.shortcut-row:hover {
    border-color: color-mix(in srgb, var(--color-primary) 34%, transparent);
    background: color-mix(in srgb, var(--color-primary) 10%, transparent);
}

.shortcut-left {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
}

.shortcut-key {
    min-width: 2rem;
    height: 1.7rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.45rem;
    border: 1px solid var(--color-border-strong);
    background: var(--color-bg-surface);
    color: var(--color-text);
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.02em;
}

.shortcut-label {
    color: var(--color-text);
    font-size: 0.86rem;
    font-weight: 600;
}

.shortcuts-adjustments {
    margin-top: 0.95rem;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    background: color-mix(in srgb, var(--color-bg-elevated) 78%, var(--color-bg-surface));
    padding: 0.8rem;
}

.shortcuts-section-title {
    margin: 0 0 0.6rem;
    font-size: 0.84rem;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    color: var(--color-text-muted);
}

.shortcuts-restaurant-links {
    display: grid;
    gap: 0.38rem;
}

.shortcuts-restaurant-link {
    border: 1px solid var(--color-border);
    border-radius: 0.62rem;
    padding: 0.45rem 0.55rem;
    color: var(--color-text);
    text-decoration: none;
    font-size: 0.84rem;
    font-weight: 700;
    background: color-mix(in srgb, var(--color-bg-elevated) 78%, var(--color-bg-surface));
    transition: all var(--transition-fast);
}

.shortcuts-restaurant-link:hover {
    border-color: color-mix(in srgb, var(--color-primary) 52%, transparent);
    background: color-mix(in srgb, var(--color-primary) 12%, var(--color-bg-surface));
}

@media (max-width: 900px) {
    .shortcuts-grid {
        grid-template-columns: 1fr;
    }
}
</style>
