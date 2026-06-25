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

function handleOrderConfirmed(payload) {
    showToast(`Pedido ${payload?.id || ''} enviado.`);
}
</script>

<template>
    <RestaurantModeGuard :allowed-modes="['comanda_garcom']" feature-label="Comanda do garcom">
        <RestaurantTerminalShell
            title="Comanda do Garcom"
            subtitle="Tela mobile-first para lancamento rapido de pedidos no salao."
            :mode-label="restaurantModeLabel"
        >
            <RestaurantOrderingCore
                mode="comanda_garcom"
                title="Lancamento de pedidos"
                subtitle="Troque mesa/comanda, adicione itens e envie para produção com poucos toques."
                confirm-label="Enviar para cozinha/bar"
                show-waiter-actions
                @order-confirmed="handleOrderConfirmed"
            />
        </RestaurantTerminalShell>

        <AppToast :show="toastVisible">{{ toastMessage }}</AppToast>
    </RestaurantModeGuard>
</template>
