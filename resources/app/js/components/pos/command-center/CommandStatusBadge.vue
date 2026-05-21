<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: {
        type: String,
        default: 'opened',
    },
    small: {
        type: Boolean,
        default: false,
    },
});

const statusMeta = computed(() => {
    const normalized = String(props.status || '').toLowerCase();

    if (normalized === 'pending_fiscal') {
        return {
            label: 'Fiscal pendente',
            className: 'is-warning',
        };
    }

    if (normalized === 'closed') {
        return {
            label: 'Fechada',
            className: 'is-closed',
        };
    }

    if (normalized === 'integrated') {
        return {
            label: 'Integrada',
            className: 'is-integrated',
        };
    }

    if (normalized === 'problem') {
        return {
            label: 'Atenção',
            className: 'is-danger',
        };
    }

    return {
        label: 'Aberta',
        className: 'is-opened',
    };
});
</script>

<template>
    <span class="command-status-badge" :class="[statusMeta.className, { 'is-small': small }]">
        {{ statusMeta.label }}
    </span>
</template>

<style scoped>
.command-status-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    border: 1px solid transparent;
    padding: 0.2rem 0.54rem;
    font-size: 0.68rem;
    font-weight: 700;
    white-space: nowrap;
}

.command-status-badge.is-small {
    padding: 0.14rem 0.44rem;
    font-size: 0.62rem;
}

.command-status-badge.is-opened {
    border-color: color-mix(in srgb, var(--color-success) 48%, transparent);
    background: color-mix(in srgb, var(--color-success) 12%, var(--color-bg-surface));
    color: var(--color-success);
}

.command-status-badge.is-closed {
    border-color: color-mix(in srgb, var(--color-text-muted) 42%, transparent);
    background: color-mix(in srgb, var(--color-bg-elevated) 84%, var(--color-bg-surface));
    color: var(--color-text-muted);
}

.command-status-badge.is-warning {
    border-color: color-mix(in srgb, var(--color-warning) 50%, transparent);
    background: color-mix(in srgb, var(--color-warning) 16%, var(--color-bg-surface));
    color: var(--color-warning);
}

.command-status-badge.is-danger {
    border-color: color-mix(in srgb, var(--color-danger) 56%, transparent);
    background: color-mix(in srgb, var(--color-danger) 14%, var(--color-bg-surface));
    color: var(--color-danger);
}

.command-status-badge.is-integrated {
    border-color: color-mix(in srgb, var(--color-primary) 55%, transparent);
    background: color-mix(in srgb, var(--color-primary) 16%, var(--color-bg-surface));
    color: var(--color-primary);
}
</style>
