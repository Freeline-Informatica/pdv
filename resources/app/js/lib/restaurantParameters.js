export const restaurantOperationModes = Object.freeze([
    {
        id: 'automatic',
        title: 'Automático / Dinâmico',
        description: 'O sistema cria comandas e mesas conforme a operação acontece. Ideal para balcão, bares e atendimento rápido.',
    },
    {
        id: 'manual',
        title: 'Manual / Fixo',
        description: 'Você define uma quantidade fixa de mesas e fichas. Elas ficam ocupadas durante o atendimento e são liberadas após o fechamento.',
    },
    {
        id: 'hybrid',
        title: 'Híbrido',
        description: 'Use mesas fixas e comandas automáticas. Ideal para restaurantes com salão numerado e controle flexível de contas.',
    },
]);

const tableStatuses = Object.freeze(['livre', 'ocupada', 'reservada', 'bloqueada']);
const ticketStatuses = Object.freeze(['livre', 'em_uso', 'fechada', 'cancelada', 'bloqueada']);

function normalizeText(value, fallback, max = 30) {
    const text = String(value ?? '').trim();
    if (!text) return fallback;
    return text.slice(0, max);
}

function normalizeInt(value, fallback, min = 0, max = null) {
    const parsed = Number.parseInt(String(value ?? ''), 10);
    if (Number.isNaN(parsed)) return fallback;

    let output = parsed;
    if (output < min) output = min;
    if (Number.isFinite(max) && output > max) output = max;
    return output;
}

function normalizeMode(mode) {
    const normalized = String(mode || '').trim().toLowerCase();
    return ['automatic', 'manual', 'hybrid'].includes(normalized) ? normalized : 'automatic';
}

function resolveTableAndTicketModes(operationMode, tablesMode, ticketsMode) {
    if (operationMode === 'automatic') {
        return { tablesMode: 'automatic', ticketsMode: 'automatic' };
    }

    if (operationMode === 'manual') {
        return { tablesMode: 'manual', ticketsMode: 'manual' };
    }

    if (operationMode === 'hybrid') {
        return { tablesMode: 'manual', ticketsMode: 'automatic' };
    }

    return {
        tablesMode: ['automatic', 'manual', 'disabled'].includes(tablesMode) ? tablesMode : 'automatic',
        ticketsMode: ['automatic', 'manual', 'disabled'].includes(ticketsMode) ? ticketsMode : 'automatic',
    };
}

export function createDefaultRestaurantParameters() {
    return {
        operation_mode: 'automatic',
        tables: {
            mode: 'automatic',
            quantity: 20,
            prefix: 'Mesa',
            start_number: 1,
            padding: 2,
            allow_manual_rename: false,
            allow_blocking: true,
            use_capacity: false,
            default_capacity: 4,
            allow_create_during_service: true,
            allow_temporary_table: true,
            future_statuses: [...tableStatuses],
        },
        tabs_or_tickets: {
            mode: 'automatic',
            allow_without_table: false,
            require_table: true,
            allow_multiple_per_table: true,
            code_generation_type: 'continuous',
            prefix: 'CMD',
            start_number: 1,
            padding: 4,
            random_code_length: 4,
            quantity: 100,
            reuse_after_closing: true,
            allow_blocking: true,
            future_statuses: [...ticketStatuses],
        },
    };
}

export function normalizeRestaurantParameters(payload) {
    const defaults = createDefaultRestaurantParameters();
    const source = payload && typeof payload === 'object' ? payload : {};
    const operationMode = normalizeMode(source.operation_mode);

    const sourceTables = source.tables && typeof source.tables === 'object' ? source.tables : {};
    const sourceTickets = source.tabs_or_tickets && typeof source.tabs_or_tickets === 'object' ? source.tabs_or_tickets : {};

    const modePair = resolveTableAndTicketModes(operationMode, sourceTables.mode, sourceTickets.mode);

    const codeGenerationType = ['continuous', 'daily', 'random'].includes(String(sourceTickets.code_generation_type || '').trim())
        ? String(sourceTickets.code_generation_type || '').trim()
        : defaults.tabs_or_tickets.code_generation_type;

    const allowWithoutTable = Boolean(sourceTickets.allow_without_table ?? defaults.tabs_or_tickets.allow_without_table);
    const requireTable = allowWithoutTable
        ? false
        : Boolean(sourceTickets.require_table ?? defaults.tabs_or_tickets.require_table);

    return {
        operation_mode: operationMode,
        tables: {
            mode: modePair.tablesMode,
            quantity: normalizeInt(sourceTables.quantity, defaults.tables.quantity, 0),
            prefix: normalizeText(sourceTables.prefix, defaults.tables.prefix, 30),
            start_number: normalizeInt(sourceTables.start_number, defaults.tables.start_number, 0),
            padding: normalizeInt(sourceTables.padding, defaults.tables.padding, 1, 6),
            allow_manual_rename: Boolean(sourceTables.allow_manual_rename ?? defaults.tables.allow_manual_rename),
            allow_blocking: Boolean(sourceTables.allow_blocking ?? defaults.tables.allow_blocking),
            use_capacity: Boolean(sourceTables.use_capacity ?? defaults.tables.use_capacity),
            default_capacity: normalizeInt(sourceTables.default_capacity, defaults.tables.default_capacity, 1, 999),
            allow_create_during_service: Boolean(sourceTables.allow_create_during_service ?? defaults.tables.allow_create_during_service),
            allow_temporary_table: Boolean(sourceTables.allow_temporary_table ?? defaults.tables.allow_temporary_table),
            future_statuses: [...tableStatuses],
        },
        tabs_or_tickets: {
            mode: modePair.ticketsMode,
            allow_without_table: allowWithoutTable,
            require_table: requireTable,
            allow_multiple_per_table: Boolean(sourceTickets.allow_multiple_per_table ?? defaults.tabs_or_tickets.allow_multiple_per_table),
            code_generation_type: codeGenerationType,
            prefix: normalizeText(
                sourceTickets.prefix,
                modePair.ticketsMode === 'manual' ? 'Ficha' : defaults.tabs_or_tickets.prefix,
                30,
            ),
            start_number: normalizeInt(sourceTickets.start_number, defaults.tabs_or_tickets.start_number, 0),
            padding: normalizeInt(
                sourceTickets.padding,
                modePair.ticketsMode === 'manual' ? 3 : defaults.tabs_or_tickets.padding,
                1,
                6,
            ),
            random_code_length: normalizeInt(sourceTickets.random_code_length, defaults.tabs_or_tickets.random_code_length, 3, 10),
            quantity: normalizeInt(sourceTickets.quantity, defaults.tabs_or_tickets.quantity, 0),
            reuse_after_closing: Boolean(sourceTickets.reuse_after_closing ?? defaults.tabs_or_tickets.reuse_after_closing),
            allow_blocking: Boolean(sourceTickets.allow_blocking ?? defaults.tabs_or_tickets.allow_blocking),
            future_statuses: [...ticketStatuses],
        },
    };
}

export function applyOperationMode(parameters, mode) {
    const normalized = normalizeRestaurantParameters({
        ...parameters,
        operation_mode: mode,
    });

    if (normalized.operation_mode === 'manual' && normalized.tabs_or_tickets.prefix === 'CMD') {
        normalized.tabs_or_tickets.prefix = 'Ficha';
        normalized.tabs_or_tickets.padding = 3;
    }

    if (normalized.operation_mode !== 'manual' && normalized.tabs_or_tickets.prefix === 'Ficha') {
        normalized.tabs_or_tickets.prefix = 'CMD';
        normalized.tabs_or_tickets.padding = 4;
    }

    return normalized;
}

function padNumber(value, padding) {
    const safePadding = normalizeInt(padding, 2, 1, 6);
    const numeric = normalizeInt(value, 1, 0);
    return String(numeric).padStart(safePadding, '0');
}

export function buildTablePreview(tablesConfig, count = 4) {
    const prefix = normalizeText(tablesConfig?.prefix, 'Mesa', 30);
    const start = normalizeInt(tablesConfig?.start_number, 1, 0);
    const padding = normalizeInt(tablesConfig?.padding, 2, 1, 6);

    return Array.from({ length: count }, (_, index) => {
        const number = start + index;
        return `${prefix} ${padNumber(number, padding)}`;
    });
}

export function buildManualTicketPreview(ticketConfig, count = 4) {
    const prefix = normalizeText(ticketConfig?.prefix, 'Ficha', 30);
    const start = normalizeInt(ticketConfig?.start_number, 1, 0);
    const padding = normalizeInt(ticketConfig?.padding, 3, 1, 6);

    return Array.from({ length: count }, (_, index) => {
        const number = start + index;
        return `${prefix} ${padNumber(number, padding)}`;
    });
}

export function buildAutomaticTicketCodePreview(ticketConfig) {
    const generationType = String(ticketConfig?.code_generation_type || 'continuous').trim();

    if (generationType === 'random') {
        return buildRandomCodePreview(ticketConfig?.random_code_length);
    }

    const prefix = normalizeText(ticketConfig?.prefix, 'CMD', 30);
    const start = normalizeInt(ticketConfig?.start_number, 1, 0);
    const padding = normalizeInt(ticketConfig?.padding, 4, 1, 6);
    const formatted = padNumber(start, padding);

    if (generationType === 'daily') {
        const dayPart = new Date().toISOString().slice(0, 10).replace(/-/g, '');
        return `${prefix}-${dayPart}-${formatted}`;
    }

    return `${prefix}-${formatted}`;
}

export function buildRandomCodePreview(length) {
    const charset = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    const safeLength = normalizeInt(length, 4, 3, 10);

    let output = '';
    for (let index = 0; index < safeLength; index += 1) {
        const charIndex = (index * 11 + safeLength * 5) % charset.length;
        output += charset[charIndex];
    }

    return output;
}
