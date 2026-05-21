import { computed, onMounted, ref, watch } from 'vue';
import {
    fetchRestaurantProductionTickets,
    updateRestaurantProductionTicketStatus,
} from '../lib/restaurantOperations';

const statusOrder = ['novo', 'em_preparo', 'pronto', 'entregue'];

function elapsedMinutes(value) {
    const createdAt = new Date(value).getTime();
    if (Number.isNaN(createdAt)) return 0;
    return Math.max(0, Math.floor((Date.now() - createdAt) / 60000));
}

export function useRestaurantProductionBoard(initialSector = 'todos') {
    const tickets = ref([]);
    const loading = ref(false);
    const error = ref('');

    const sectorFilter = ref(initialSector);
    const delayedOnly = ref(false);
    const mobileStatus = ref('novo');

    const normalizedTickets = computed(() =>
        tickets.value.map((ticket) => {
            const minutes = Number(ticket?.elapsedMinutes || elapsedMinutes(ticket?.criadoEm));
            return {
                ...ticket,
                elapsedMinutes: minutes,
                isDelayed: Boolean(ticket?.isDelayed || minutes >= 20),
            };
        }),
    );

    const filteredTickets = computed(() =>
        normalizedTickets.value.filter((ticket) => {
            if (sectorFilter.value !== 'todos' && ticket.setor !== sectorFilter.value) return false;
            if (delayedOnly.value && !ticket.isDelayed) return false;
            return true;
        }),
    );

    const groupedTickets = computed(() => {
        const grouped = {
            novo: [],
            em_preparo: [],
            pronto: [],
            entregue: [],
        };

        filteredTickets.value.forEach((ticket) => {
            if (!grouped[ticket.status]) grouped[ticket.status] = [];
            grouped[ticket.status].push(ticket);
        });

        return grouped;
    });

    const mobileTickets = computed(() => groupedTickets.value[mobileStatus.value] || []);

    async function loadTickets() {
        loading.value = true;
        error.value = '';

        try {
            const response = await fetchRestaurantProductionTickets({
                sector: sectorFilter.value,
                delayed_only: delayedOnly.value,
            });
            tickets.value = response.tickets;
        } catch (requestError) {
            error.value = String(requestError?.message || 'Nao foi possivel carregar a fila de producao.');
            tickets.value = [];
        } finally {
            loading.value = false;
        }
    }

    async function updateTicketStatus(ticketId, nextStatus) {
        if (!statusOrder.includes(nextStatus)) return;

        await updateRestaurantProductionTicketStatus(ticketId, nextStatus);
        await loadTickets();
    }

    function nextStatusAction(status) {
        if (status === 'novo') return 'em_preparo';
        if (status === 'em_preparo') return 'pronto';
        if (status === 'pronto') return 'entregue';
        return null;
    }

    function resetBoard() {
        loadTickets();
    }

    watch([sectorFilter, delayedOnly], () => {
        loadTickets();
    });

    onMounted(loadTickets);

    return {
        loading,
        error,
        tickets,
        sectorFilter,
        delayedOnly,
        mobileStatus,
        groupedTickets,
        mobileTickets,
        loadTickets,
        updateTicketStatus,
        nextStatusAction,
        resetBoard,
    };
}
