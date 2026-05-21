<script setup>
import { computed } from 'vue';
import AppBadge from '../../ui/AppBadge.vue';
import AppButton from '../../ui/AppButton.vue';
import AppDrawer from '../../ui/AppDrawer.vue';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    summary: {
        type: Object,
        default: null,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    requestingClose: {
        type: Boolean,
        default: false,
    },
    formatCurrency: {
        type: Function,
        required: true,
    },
    statusLabel: {
        type: String,
        default: 'Ativa',
    },
    statusVariant: {
        type: String,
        default: 'default',
    },
});

const emit = defineEmits(['close', 'refresh', 'conference', 'close-request', 'add-more']);

const consolidatedItems = computed(() => Array.isArray(props.summary?.itensDaFicha) ? props.summary.itensDaFicha : []);
const sentOrders = computed(() => Array.isArray(props.summary?.pedidosEnviados) ? props.summary.pedidosEnviados : []);
</script>

<template>
    <AppDrawer :open="open" title="Resumo da ficha" @close="emit('close')">
        <div class="command-summary-drawer">
            <div class="command-summary-drawer__meta ui-card">
                <p><strong>Mesa:</strong> {{ summary?.mesa?.code || '--' }}</p>
                <p><strong>Ficha:</strong> {{ summary?.ficha?.code || '--' }}</p>
                <p><strong>Garcom:</strong> {{ summary?.ficha?.waiterName || 'Equipe' }}</p>
                <p><strong>Total:</strong> {{ formatCurrency(summary?.totals?.total || 0) }}</p>
                <AppBadge :variant="statusVariant">{{ statusLabel }}</AppBadge>
                <p v-if="summary?.ficha?.observation"><strong>Obs. ficha:</strong> {{ summary.ficha.observation }}</p>
            </div>

            <div v-if="loading" class="command-summary-drawer__loading ui-card">
                Carregando ficha...
            </div>

            <section v-else class="command-summary-drawer__block ui-card">
                <h4>Itens consolidados</h4>
                <article v-for="item in consolidatedItems" :key="`${item.productName}-${item.productCode}`" class="command-summary-drawer__row">
                    <div>
                        <strong>{{ item.productName }}</strong>
                        <small>{{ item.productCode || 'Sem código' }}</small>
                    </div>
                    <div class="text-right">
                        <small>{{ item.quantity }} x {{ formatCurrency(item.unitPrice) }}</small>
                        <strong>{{ formatCurrency(item.totalPrice) }}</strong>
                    </div>
                </article>

                <p v-if="consolidatedItems.length === 0" class="command-summary-drawer__empty">
                    Nenhum item enviado para esta ficha.
                </p>
            </section>

            <section v-if="!loading" class="command-summary-drawer__block ui-card">
                <h4>Pedidos enviados</h4>
                <article v-for="order in sentOrders" :key="order.id" class="command-summary-drawer__order">
                    <div class="command-summary-drawer__order-head">
                        <strong>#{{ order.id.slice(0, 8) }}</strong>
                        <small>{{ order.sector }} • {{ formatCurrency(order.total || 0) }}</small>
                    </div>
                    <small>{{ order.createdAt }}</small>
                    <small v-if="order.orderObservation">Obs.: {{ order.orderObservation }}</small>
                </article>
                <p v-if="sentOrders.length === 0" class="command-summary-drawer__empty">
                    Sem pedidos enviados ainda.
                </p>
            </section>

            <div class="command-summary-drawer__actions">
                <AppButton variant="secondary" @click="emit('add-more')">Adicionar mais itens</AppButton>
                <AppButton variant="secondary" @click="emit('conference')">Gerar conferência</AppButton>
                <AppButton variant="secondary" @click="emit('refresh')">Atualizar</AppButton>
                <AppButton :loading="requestingClose" @click="emit('close-request')">Solicitar fechamento</AppButton>
            </div>
        </div>
    </AppDrawer>
</template>

<style scoped>
.command-summary-drawer {
    display: grid;
    gap: 0.58rem;
}

.command-summary-drawer__meta,
.command-summary-drawer__block,
.command-summary-drawer__loading {
    padding: 0.65rem;
}

.command-summary-drawer__meta {
    display: grid;
    gap: 0.25rem;
}

.command-summary-drawer__meta p {
    margin: 0;
    font-size: 0.84rem;
    color: var(--color-text-muted);
}

.command-summary-drawer__block {
    display: grid;
    gap: 0.45rem;
}

.command-summary-drawer__block h4 {
    margin: 0;
    font-size: 0.88rem;
    font-weight: 800;
    color: var(--color-text);
}

.command-summary-drawer__row {
    border: 1px solid var(--color-border);
    border-radius: 0.66rem;
    padding: 0.5rem;
    display: flex;
    justify-content: space-between;
    gap: 0.45rem;
}

.command-summary-drawer__row strong,
.command-summary-drawer__order strong {
    color: var(--color-text);
    display: block;
}

.command-summary-drawer__row small,
.command-summary-drawer__order small {
    color: var(--color-text-muted);
    display: block;
}

.command-summary-drawer__order {
    border: 1px solid var(--color-border);
    border-radius: 0.66rem;
    padding: 0.5rem;
}

.command-summary-drawer__order-head {
    display: flex;
    justify-content: space-between;
    gap: 0.3rem;
}

.command-summary-drawer__empty {
    margin: 0;
    color: var(--color-text-muted);
    font-size: 0.82rem;
}

.command-summary-drawer__actions {
    display: grid;
    gap: 0.45rem;
}
</style>
