import api from './api';

function isEndpointMissing(error) {
    const status = Number(error?.response?.status || 0);
    return status === 404 || status === 405 || status === 501;
}

function resolveApiMessage(error, fallbackMessage) {
    return String(error?.response?.data?.message || fallbackMessage || 'Operação não concluída.');
}

function normalizeSnapshot(data) {
    if (!data || typeof data !== 'object') return null;

    if (data.snapshot && typeof data.snapshot === 'object') {
        return data.snapshot;
    }

    return data;
}

export async function fetchCommandCenterSnapshot() {
    try {
        const { data } = await api.get('/pos/restaurant/command-center');
        return {
            source: 'api',
            snapshot: normalizeSnapshot(data),
            message: String(data?.message || ''),
        };
    } catch (error) {
        if (isEndpointMissing(error)) {
            return {
                source: 'fallback',
                snapshot: null,
                message: 'Endpoint de comandas não disponível. Usando base local temporária.',
            };
        }

        throw new Error(resolveApiMessage(error, 'Não foi possível carregar a central de comandas.'));
    }
}

export async function reintegrateCommandCenter() {
    try {
        const { data } = await api.post('/pos/restaurant/command-center/reintegrate');

        return {
            source: 'api',
            snapshot: normalizeSnapshot(data),
            message: String(data?.message || 'Comandas reintegradas.'),
        };
    } catch (error) {
        if (isEndpointMissing(error)) {
            return {
                source: 'fallback',
                snapshot: null,
                message: 'Reintegração local aplicada (endpoint não disponível).',
            };
        }

        throw new Error(resolveApiMessage(error, 'Não foi possível reintegrar as comandas.'));
    }
}

export async function registerCommandTransfer(payload) {
    try {
        const { data } = await api.post('/pos/restaurant/command-center/transfer', payload);
        return {
            source: 'api',
            message: String(data?.message || 'Transferência registrada.'),
            audit: data?.audit || null,
        };
    } catch (error) {
        if (isEndpointMissing(error)) {
            return {
                source: 'fallback',
                message: 'Transferência registrada localmente para continuidade operacional.',
                audit: null,
            };
        }

        throw new Error(resolveApiMessage(error, 'Não foi possível registrar a transferência.'));
    }
}

export async function registerCommandMerge(payload) {
    try {
        const { data } = await api.post('/pos/restaurant/command-center/merge', payload);
        return {
            source: 'api',
            message: String(data?.message || 'Junção registrada.'),
            audit: data?.audit || null,
        };
    } catch (error) {
        if (isEndpointMissing(error)) {
            return {
                source: 'fallback',
                message: 'Junção registrada localmente para continuidade operacional.',
                audit: null,
            };
        }

        throw new Error(resolveApiMessage(error, 'Não foi possível registrar a junção de fichas.'));
    }
}

export async function registerCommandPrintAction(payload) {
    try {
        const { data } = await api.post('/pos/restaurant/command-center/print', payload);
        return {
            source: 'api',
            message: String(data?.message || 'Impressão operacional registrada.'),
            audit: data?.audit || null,
        };
    } catch (error) {
        if (isEndpointMissing(error)) {
            return {
                source: 'fallback',
                message: 'Impressão operacional registrada localmente. Não é documento fiscal.',
                audit: null,
            };
        }

        throw new Error(resolveApiMessage(error, 'Não foi possível registrar a impressão operacional.'));
    }
}

export async function registerCommandConference(payload) {
    try {
        const { data } = await api.post('/pos/restaurant/command-center/conference', payload);
        return {
            source: 'api',
            message: String(data?.message || 'Conferência operacional registrada.'),
            audit: data?.audit || null,
        };
    } catch (error) {
        if (isEndpointMissing(error)) {
            return {
                source: 'fallback',
                message: 'Conferência registrada localmente para continuidade operacional.',
                audit: null,
            };
        }

        throw new Error(resolveApiMessage(error, 'Não foi possível registrar a conferência.'));
    }
}
