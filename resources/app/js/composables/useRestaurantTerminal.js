import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { getTerminalSession } from '../lib/auth';

const validRestaurantModes = new Set([
    'auto_atendimento',
    'totem',
    'caixa',
    'comanda_bar',
    'comanda_cozinha',
    'comanda_garcom',
]);

const restaurantModeLabels = Object.freeze({
    auto_atendimento: 'Autoatendimento de mesa',
    totem: 'Totem',
    caixa: 'Caixa',
    comanda_bar: 'Comanda bar',
    comanda_cozinha: 'Comanda cozinha',
    comanda_garcom: 'Comanda do garcom',
});

function normalizeLayoutMode(value) {
    const normalized = String(value || '').trim().toLowerCase();
    return ['varejo', 'restaurante', 'servicos'].includes(normalized) ? normalized : 'varejo';
}

function normalizeRestaurantMode(value) {
    const normalized = String(value || '').trim().toLowerCase();
    return validRestaurantModes.has(normalized) ? normalized : 'comanda_garcom';
}

export function getRestaurantModeLabel(value) {
    const mode = normalizeRestaurantMode(value);
    return restaurantModeLabels[mode] || 'Comanda do garcom';
}

export function useRestaurantTerminal() {
    const terminal = ref(getTerminalSession());

    function refreshTerminal() {
        terminal.value = getTerminalSession();
    }

    function onWindowFocus() {
        refreshTerminal();
    }

    onMounted(() => {
        refreshTerminal();
        window.addEventListener('focus', onWindowFocus);
    });

    onBeforeUnmount(() => {
        window.removeEventListener('focus', onWindowFocus);
    });

    const layoutMode = computed(() => normalizeLayoutMode(terminal.value?.layoutMode));
    const restaurantMode = computed(() => normalizeRestaurantMode(terminal.value?.restaurantMode));
    const isRestaurantTerminal = computed(() => layoutMode.value === 'restaurante');

    return {
        terminal,
        layoutMode,
        restaurantMode,
        isRestaurantTerminal,
        restaurantModeLabel: computed(() => getRestaurantModeLabel(restaurantMode.value)),
        refreshTerminal,
    };
}
