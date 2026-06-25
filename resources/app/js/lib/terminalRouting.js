import { normalizeTerminalDeviceAccess } from './deviceAccess.js';

function normalizeLayoutMode(value) {
    const normalized = String(value || '').trim().toLowerCase();
    return ['varejo', 'restaurante', 'servicos'].includes(normalized) ? normalized : 'varejo';
}

function normalizeRestaurantMode(value) {
    const normalized = String(value || '').trim().toLowerCase();
    const validModes = ['auto_atendimento', 'totem', 'caixa', 'comanda_bar', 'comanda_cozinha', 'comanda_garcom'];
    return validModes.includes(normalized) ? normalized : 'comanda_garcom';
}

export function resolveTerminalLandingPath(terminalSession) {
    const layoutMode = normalizeLayoutMode(terminalSession?.layoutMode);
    if (layoutMode !== 'restaurante') return '/';

    const restaurantMode = normalizeRestaurantMode(terminalSession?.restaurantMode);

    if (restaurantMode === 'auto_atendimento') return '/pdv/restaurante/auto-atendimento';
    if (restaurantMode === 'totem') return '/pdv/restaurante/totem';
    if (restaurantMode === 'comanda_garcom') return '/pdv/restaurante/garcom';
    if (restaurantMode === 'comanda_cozinha') return '/pdv/restaurante/producao/cozinha';
    if (restaurantMode === 'comanda_bar') return '/pdv/restaurante/producao/bar';

    // Modo "caixa" continua no PDV padrão atual.
    return '/';
}

export function normalizeTerminalSessionWithProfile(terminalSession, profile) {
    if (!terminalSession || typeof terminalSession !== 'object') {
        return terminalSession;
    }

    const nextLayoutMode = normalizeLayoutMode(profile?.pdv_layout_mode || terminalSession.layoutMode);
    const nextRestaurantMode = nextLayoutMode === 'restaurante'
        ? normalizeRestaurantMode(profile?.pdv_restaurant_mode || terminalSession.restaurantMode)
        : null;
    const deviceAccess = normalizeTerminalDeviceAccess(profile?.device_access || profile || terminalSession?.deviceAccess);

    return {
        ...terminalSession,
        layoutMode: nextLayoutMode,
        restaurantMode: nextRestaurantMode,
        deviceAccess,
    };
}
