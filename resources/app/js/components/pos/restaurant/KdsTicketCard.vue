<script setup>
import { Clock3, Printer } from 'lucide-vue-next';
import AppButton from '../../ui/AppButton.vue';
import RestaurantStatusBadge from './RestaurantStatusBadge.vue';

const props = defineProps({
    ticket: {
        type: Object,
        required: true,
    },
    nextStatus: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['advance', 'reprint']);

function nextStatusLabel(status) {
    if (status === 'em_preparo') return 'Iniciar preparo';
    if (status === 'pronto') return 'Marcar pronto';
    if (status === 'entregue') return 'Entregue';
    return 'Atualizar';
}
</script>

<template>
    <article class="kds-ticket ui-card" :class="{ 'is-delayed': ticket.isDelayed }">
        <header class="kds-ticket__head">
            <div>
                <strong>#{{ ticket.id }}</strong>
                <p>Mesa {{ ticket.mesa }} • Ficha {{ ticket.ficha || ticket.comanda }}</p>
            </div>
            <RestaurantStatusBadge :status="ticket.status" />
        </header>

        <div class="kds-ticket__meta">
            <span>Garcom: {{ ticket.garcom }}</span>
            <span class="kds-ticket__timer" :class="{ 'is-delayed': ticket.isDelayed }">
                <Clock3 class="h-4 w-4" aria-hidden="true" />
                {{ ticket.elapsedMinutes }} min
            </span>
        </div>

        <ul class="kds-ticket__items">
            <li v-for="item in ticket.itens" :key="item.id">
                <strong>{{ item.quantidade }}x</strong>
                <span>{{ item.nome }}</span>
                <small v-if="item.observacao">Obs.: {{ item.observacao }}</small>
            </li>
        </ul>

        <footer class="kds-ticket__actions">
            <AppButton v-if="nextStatus" @click="emit('advance', ticket.id, nextStatus)">
                {{ nextStatusLabel(nextStatus) }}
            </AppButton>
            <AppButton variant="ghost" @click="emit('reprint', ticket.id)">
                <Printer class="h-4 w-4" aria-hidden="true" />
                Reimprimir
            </AppButton>
        </footer>
    </article>
</template>

<style scoped>
.kds-ticket {
    padding: 0.72rem;
    display: grid;
    gap: 0.55rem;
}

.kds-ticket.is-delayed {
    border-color: color-mix(in srgb, var(--color-danger) 48%, var(--color-border));
    background: color-mix(in srgb, var(--color-danger) 7%, var(--color-bg-surface));
}

.kds-ticket__head {
    display: flex;
    justify-content: space-between;
    gap: 0.5rem;
    align-items: flex-start;
}

.kds-ticket__head strong {
    color: var(--color-text);
    font-size: 0.92rem;
}

.kds-ticket__head p {
    margin: 0.2rem 0 0;
    font-size: 0.74rem;
    color: var(--color-text-muted);
}

.kds-ticket__meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
    color: var(--color-text-muted);
    font-size: 0.78rem;
}

.kds-ticket__timer {
    display: inline-flex;
    align-items: center;
    gap: 0.24rem;
    font-weight: 700;
}

.kds-ticket__timer.is-delayed {
    color: var(--color-danger);
}

.kds-ticket__items {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 0.3rem;
}

.kds-ticket__items li {
    border: 1px solid var(--color-border);
    border-radius: 0.55rem;
    padding: 0.38rem 0.45rem;
    display: grid;
    gap: 0.16rem;
}

.kds-ticket__items strong {
    color: var(--color-text);
    font-size: 0.82rem;
}

.kds-ticket__items span {
    color: var(--color-text);
    font-size: 0.82rem;
}

.kds-ticket__items small {
    color: var(--color-text-muted);
    font-size: 0.72rem;
}

.kds-ticket__actions {
    display: flex;
    gap: 0.42rem;
    flex-wrap: wrap;
}
</style>
