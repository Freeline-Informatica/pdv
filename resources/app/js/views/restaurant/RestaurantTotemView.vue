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
    showToast(`Pedido ${payload?.id || ''} enviado para producao.`);
}
</script>

<template>
    <RestaurantModeGuard :allowed-modes="['totem']" feature-label="Totem de pedidos">
        <RestaurantTerminalShell
            title="Totem de Autoatendimento"
            subtitle="Fluxo touch com botoes grandes e experiencia simplificada para pedido rapido."
            :mode-label="restaurantModeLabel"
        >
            <RestaurantOrderingCore
                mode="totem"
                title="Monte seu pedido"
                subtitle="Escolha os produtos, personalize e confirme para gerar sua senha."
                confirm-label="Confirmar pedido"
                @order-confirmed="handleOrderConfirmed"
            />
        </RestaurantTerminalShell>

        <AppToast :show="toastVisible">{{ toastMessage }}</AppToast>
    </RestaurantModeGuard>
</template>
