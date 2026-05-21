<script setup>
import KdsStatusColumn from './KdsStatusColumn.vue';

const props = defineProps({
    groupedTickets: {
        type: Object,
        required: true,
    },
    mobileStatus: {
        type: String,
        default: 'novo',
    },
    mobileTickets: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['advance', 'reprint', 'update:mobileStatus']);

const columns = Object.freeze([
    { id: 'novo', label: 'Novo', next: 'em_preparo' },
    { id: 'em_preparo', label: 'Em preparo', next: 'pronto' },
    { id: 'pronto', label: 'Pronto', next: 'entregue' },
    { id: 'entregue', label: 'Entregue', next: null },
]);

function nextStatusFor(status) {
    const match = columns.find((column) => column.id === status);
    return match?.next || null;
}
</script>

<template>
    <div class="kds-board-desktop">
        <KdsStatusColumn
            v-for="column in columns"
            :key="column.id"
            :title="column.label"
            :tickets="groupedTickets[column.id] || []"
            :next-status="column.next"
            @advance="(ticketId, status) => emit('advance', ticketId, status)"
            @reprint="emit('reprint', $event)"
        />
    </div>

    <div class="kds-board-mobile ui-card">
        <nav class="kds-board-mobile__tabs">
            <button
                v-for="column in columns.filter((item) => item.id !== 'entregue')"
                :key="column.id"
                type="button"
                class="kds-board-mobile__tab"
                :class="{ 'is-active': mobileStatus === column.id }"
                @click="emit('update:mobileStatus', column.id)"
            >
                {{ column.label }}
                <small>{{ (groupedTickets[column.id] || []).length }}</small>
            </button>
        </nav>

        <section class="kds-board-mobile__list">
            <KdsStatusColumn
                :title="columns.find((column) => column.id === mobileStatus)?.label || 'Status'"
                :tickets="mobileTickets"
                :next-status="nextStatusFor(mobileStatus)"
                @advance="(ticketId, status) => emit('advance', ticketId, status)"
                @reprint="emit('reprint', $event)"
            />
        </section>
    </div>
</template>

<style scoped>
.kds-board-desktop {
    display: none;
}

.kds-board-mobile {
    padding: 0.65rem;
    display: grid;
    gap: 0.56rem;
}

.kds-board-mobile__tabs {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.35rem;
}

.kds-board-mobile__tab {
    border: 1px solid var(--color-border);
    border-radius: 0.65rem;
    padding: 0.42rem;
    background: color-mix(in srgb, var(--color-bg-elevated) 80%, var(--color-bg-surface));
    color: var(--color-text-muted);
    font-size: 0.74rem;
    font-weight: 700;
    display: grid;
    place-items: center;
    gap: 0.18rem;
    cursor: pointer;
}

.kds-board-mobile__tab.is-active {
    border-color: color-mix(in srgb, var(--color-primary) 50%, transparent);
    color: var(--color-text);
    background: color-mix(in srgb, var(--color-primary) 14%, var(--color-bg-surface));
}

.kds-board-mobile__tab small {
    color: var(--color-text-muted);
}

@media (min-width: 980px) {
    .kds-board-desktop {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.58rem;
    }

    .kds-board-mobile {
        display: none;
    }
}
</style>
