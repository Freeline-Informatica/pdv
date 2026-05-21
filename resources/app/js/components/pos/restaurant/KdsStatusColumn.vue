<script setup>
import KdsTicketCard from './KdsTicketCard.vue';
import RestaurantEmptyState from './RestaurantEmptyState.vue';

const props = defineProps({
    title: {
        type: String,
        default: '',
    },
    tickets: {
        type: Array,
        default: () => [],
    },
    nextStatus: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['advance', 'reprint']);
</script>

<template>
    <section class="kds-column ui-card">
        <header class="kds-column__head">
            <h3>{{ title }}</h3>
            <strong>{{ tickets.length }}</strong>
        </header>

        <div v-if="tickets.length" class="kds-column__list">
            <KdsTicketCard
                v-for="ticket in tickets"
                :key="ticket.id"
                :ticket="ticket"
                :next-status="nextStatus"
                @advance="(ticketId, status) => emit('advance', ticketId, status)"
                @reprint="emit('reprint', $event)"
            />
        </div>

        <RestaurantEmptyState
            v-else
            title="Sem tickets"
            description="Nenhum pedido neste status no momento."
        />
    </section>
</template>

<style scoped>
.kds-column {
    padding: 0.68rem;
    display: grid;
    gap: 0.56rem;
    min-height: 16rem;
}

.kds-column__head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
}

.kds-column__head h3 {
    margin: 0;
    font-size: 0.92rem;
    color: var(--color-text);
}

.kds-column__head strong {
    color: var(--color-primary);
    font-size: 1rem;
}

.kds-column__list {
    display: grid;
    gap: 0.45rem;
}
</style>
