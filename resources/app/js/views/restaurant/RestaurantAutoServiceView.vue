<script setup>
import { ref } from 'vue';
import AppToast from '../../components/ui/AppToast.vue';
import RestaurantModeGuard from '../../components/pos/restaurant/RestaurantModeGuard.vue';
import RestaurantTerminalShell from '../../components/pos/restaurant/RestaurantTerminalShell.vue';
import RestaurantOrderingCore from '../../components/pos/restaurant/RestaurantOrderingCore.vue';
import { useRestaurantTerminal } from '../../composables/useRestaurantTerminal';

const { restaurantModeLabel } = useRestaurantTerminal();
const toastVisible = ref(false);
const toastMessage = ref('');

function showToast(message) {
    toastMessage.value = message;
    toastVisible.value = true;
    setTimeout(() => {
        toastVisible.value = false;
    }, 2400);
}

function handleCallWaiter(payload) {
    showToast(`Garcom chamado para mesa ${payload?.table?.code || '--'}.`);
}

function handleOrderConfirmed(payload) {
    showToast(`Pedido ${payload?.id || ''} confirmado com sucesso.`);
}
</script>

<template>
    <RestaurantModeGuard :allowed-modes="['auto_atendimento']" feature-label="Autoatendimento de mesa">
        <RestaurantTerminalShell
            title="Autoatendimento de Mesa"
            subtitle="Pedido digital para cliente na mesa, sem funcoes fiscais de caixa."
            :mode-label="restaurantModeLabel"
        >
            <RestaurantOrderingCore
                mode="auto_atendimento"
                title="Cardapio da mesa"
                subtitle="Selecione os itens, personalize e confirme o pedido para cozinha/bar."
                confirm-label="Confirmar pedido"
                show-call-waiter
                @call-waiter="handleCallWaiter"
                @order-confirmed="handleOrderConfirmed"
            />
        </RestaurantTerminalShell>

        <AppToast :show="toastVisible">{{ toastMessage }}</AppToast>
    </RestaurantModeGuard>
</template>
