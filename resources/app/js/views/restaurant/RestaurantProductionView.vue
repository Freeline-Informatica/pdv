<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import AppCheckbox from '../../components/ui/AppCheckbox.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppToast from '../../components/ui/AppToast.vue';
import RestaurantModeGuard from '../../components/pos/restaurant/RestaurantModeGuard.vue';
import RestaurantTerminalShell from '../../components/pos/restaurant/RestaurantTerminalShell.vue';
import RestaurantHeader from '../../components/pos/restaurant/RestaurantHeader.vue';
import KdsBoard from '../../components/pos/restaurant/KdsBoard.vue';
import { useRestaurantTerminal } from '../../composables/useRestaurantTerminal';
import { useRestaurantProductionBoard } from '../../composables/useRestaurantProductionBoard';

const route = useRoute();
const { restaurantModeLabel, restaurantMode } = useRestaurantTerminal();
const board = useRestaurantProductionBoard(route.meta?.productionSector || 'todos');

const toastVisible = ref(false);
const toastMessage = ref('');

const sectorTitle = computed(() => {
    if (board.sectorFilter.value === 'cozinha') return 'Producao - Cozinha';
    if (board.sectorFilter.value === 'bar') return 'Producao - Bar';
    return 'Producao - Cozinha e Bar';
});

const allowedModes = computed(() => {
    const routeSector = String(route.meta?.productionSector || '').trim().toLowerCase();
    if (routeSector === 'cozinha') return ['comanda_cozinha'];
    if (routeSector === 'bar') return ['comanda_bar'];
    return ['comanda_cozinha', 'comanda_bar'];
});

const lockedSectorMode = computed(() => {
    if (restaurantMode.value === 'comanda_cozinha') return 'cozinha';
    if (restaurantMode.value === 'comanda_bar') return 'bar';
    return null;
});

const canSelectAllSectors = computed(() => !lockedSectorMode.value);
const canSeeKitchenSector = computed(() => !lockedSectorMode.value || lockedSectorMode.value === 'cozinha');
const canSeeBarSector = computed(() => !lockedSectorMode.value || lockedSectorMode.value === 'bar');

watch(
    () => route.meta?.productionSector,
    (nextSector) => {
        if (lockedSectorMode.value) {
            board.sectorFilter.value = lockedSectorMode.value;
            return;
        }

        if (!nextSector) return;
        board.sectorFilter.value = nextSector;
    },
);

watch(
    () => restaurantMode.value,
    () => {
        if (!lockedSectorMode.value) return;
        board.sectorFilter.value = lockedSectorMode.value;
    },
    { immediate: true },
);

function showToast(message) {
    toastMessage.value = message;
    toastVisible.value = true;
    setTimeout(() => {
        toastVisible.value = false;
    }, 2200);
}

async function handleAdvance(ticketId, nextStatus) {
    try {
        await board.updateTicketStatus(ticketId, nextStatus);
        showToast(`Ticket ${ticketId} movido para ${nextStatus.replace('_', ' ')}.`);
    } catch (requestError) {
        showToast(String(requestError?.message || 'Nao foi possivel atualizar o ticket.'));
    }
}

function handleReprint(ticketId) {
    showToast(`Reimpressao solicitada para ${ticketId}.`);
}
</script>

<template>
    <RestaurantModeGuard :allowed-modes="allowedModes" feature-label="Tela de producao (Cozinha/Bar)">
        <RestaurantTerminalShell
            :title="sectorTitle"
            subtitle="KDS com acompanhamento de tickets por status e destaque de atraso."
            :mode-label="restaurantModeLabel"
        >
            <RestaurantHeader
                title="Fila de producao"
                subtitle="Acompanhe pedidos novos, em preparo, prontos e entregues."
                status-label="KDS"
            />

            <section class="restaurant-production-filters ui-card">
                <AppSelect
                    label="Setor"
                    :model-value="board.sectorFilter.value"
                    :disabled="!canSelectAllSectors"
                    @update:model-value="board.sectorFilter.value = $event"
                >
                    <option v-if="canSelectAllSectors" value="todos">Todos</option>
                    <option v-if="canSeeKitchenSector" value="cozinha">Cozinha</option>
                    <option v-if="canSeeBarSector" value="bar">Bar</option>
                </AppSelect>

                <AppCheckbox
                    :model-value="board.delayedOnly.value"
                    label="Apenas atrasados"
                    @update:model-value="board.delayedOnly.value = $event"
                />
            </section>

            <KdsBoard
                :grouped-tickets="board.groupedTickets.value"
                :mobile-status="board.mobileStatus.value"
                :mobile-tickets="board.mobileTickets.value"
                @advance="handleAdvance"
                @reprint="handleReprint"
                @update:mobile-status="board.mobileStatus.value = $event"
            />
            <p v-if="board.error.value" class="text-sm text-danger">{{ board.error.value }}</p>
        </RestaurantTerminalShell>

        <AppToast :show="toastVisible">{{ toastMessage }}</AppToast>
    </RestaurantModeGuard>
</template>

<style scoped>
.restaurant-production-filters {
    padding: 0.74rem;
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 0.8rem;
    flex-wrap: wrap;
}

@media (max-width: 900px) {
    .restaurant-production-filters {
        align-items: stretch;
    }
}
</style>
