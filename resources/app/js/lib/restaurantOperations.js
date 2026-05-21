import api from './api';

function resolveApiError(error, fallbackMessage) {
    return String(error?.response?.data?.message || fallbackMessage || 'Operacao nao concluida.');
}

export async function fetchRestaurantOrderingContext() {
    try {
        const { data } = await api.get('/pos/restaurant/ordering/context');
        return {
            waiter: data?.waiter || null,
            tables: Array.isArray(data?.tables) ? data.tables : [],
            meta: data?.meta || {},
        };
    } catch (error) {
        throw new Error(resolveApiError(error, 'Nao foi possivel carregar mesas e fichas.'));
    }
}

export async function createRestaurantFicha(payload) {
    try {
        const { data } = await api.post('/pos/restaurant/fichas', payload);
        return {
            message: String(data?.message || 'Ficha criada com sucesso.'),
            ficha: data?.ficha || null,
        };
    } catch (error) {
        throw new Error(resolveApiError(error, 'Nao foi possivel criar a ficha.'));
    }
}

export async function submitRestaurantFichaOrder(fichaId, payload) {
    try {
        const { data } = await api.post(`/pos/restaurant/fichas/${fichaId}/orders`, payload);
        return {
            message: String(data?.message || 'Pedido enviado para producao.'),
            tickets: Array.isArray(data?.tickets) ? data.tickets : [],
            summary: data?.summary || null,
        };
    } catch (error) {
        throw new Error(resolveApiError(error, 'Nao foi possivel enviar o pedido para producao.'));
    }
}

export async function fetchRestaurantFichaSummary(fichaId) {
    try {
        const { data } = await api.get(`/pos/restaurant/fichas/${fichaId}/summary`);
        return {
            summary: data?.summary || null,
        };
    } catch (error) {
        throw new Error(resolveApiError(error, 'Nao foi possivel carregar o resumo da ficha.'));
    }
}

export async function saveRestaurantFichaObservation(fichaId, payload) {
    try {
        const { data } = await api.post(`/pos/restaurant/fichas/${fichaId}/observation`, payload || {});
        return {
            message: String(data?.message || 'Observacao salva.'),
            summary: data?.summary || null,
        };
    } catch (error) {
        throw new Error(resolveApiError(error, 'Nao foi possivel salvar a observacao da ficha.'));
    }
}

export async function requestRestaurantFichaClose(fichaId) {
    try {
        const { data } = await api.post(`/pos/restaurant/fichas/${fichaId}/close-request`);
        return {
            message: String(data?.message || 'Ficha enviada para o caixa.'),
            summary: data?.summary || null,
        };
    } catch (error) {
        throw new Error(resolveApiError(error, 'Nao foi possivel solicitar fechamento da ficha.'));
    }
}

export async function fetchRestaurantFichaConference(fichaId) {
    try {
        const { data } = await api.post(`/pos/restaurant/fichas/${fichaId}/conference`);
        return {
            message: String(data?.message || 'Conferencia carregada.'),
            summary: data?.summary || null,
        };
    } catch (error) {
        throw new Error(resolveApiError(error, 'Nao foi possivel carregar a conferencia da ficha.'));
    }
}

export async function fetchRestaurantProductionTickets(params = {}) {
    try {
        const normalizedParams = {
            sector: params?.sector || 'todos',
            delayed_only: params?.delayed_only ? '1' : '0',
        };

        const { data } = await api.get('/pos/restaurant/production/tickets', {
            params: normalizedParams,
        });

        return {
            tickets: Array.isArray(data?.tickets) ? data.tickets : [],
            meta: data?.meta || {},
        };
    } catch (error) {
        throw new Error(resolveApiError(error, 'Nao foi possivel carregar tickets de producao.'));
    }
}

export async function updateRestaurantProductionTicketStatus(ticketId, status) {
    try {
        const { data } = await api.post(`/pos/restaurant/production/tickets/${ticketId}/status`, {
            status,
        });

        return {
            message: String(data?.message || 'Status atualizado com sucesso.'),
            ticket: data?.ticket || null,
        };
    } catch (error) {
        throw new Error(resolveApiError(error, 'Nao foi possivel atualizar o status do ticket.'));
    }
}
