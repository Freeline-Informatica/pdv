<script setup>
import { ClipboardList, Send } from 'lucide-vue-next';
import AppButton from '../../ui/AppButton.vue';

defineProps({
    hasSelection: {
        type: Boolean,
        default: false,
    },
    hasItems: {
        type: Boolean,
        default: false,
    },
    itemsCount: {
        type: Number,
        default: 0,
    },
    subtotalLabel: {
        type: String,
        default: 'R$ 0,00',
    },
    commandLabel: {
        type: String,
        default: '--',
    },
    totalFichaLabel: {
        type: String,
        default: 'R$ 0,00',
    },
    sending: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['open-order', 'open-summary', 'send', 'switch']);
</script>

<template>
    <footer class="sticky-order-footer">
        <template v-if="!hasSelection">
            <span>Selecione uma mesa e uma ficha para começar.</span>
            <AppButton variant="secondary" @click="emit('switch')">Selecionar</AppButton>
        </template>

        <template v-else-if="hasItems">
            <div class="sticky-order-footer__meta">
                <strong>Pedido atual: {{ itemsCount }} item(ns)</strong>
                <small>{{ subtotalLabel }}</small>
            </div>
            <div class="sticky-order-footer__actions">
                <AppButton variant="secondary" @click="emit('open-order')">
                    <ClipboardList class="h-4 w-4" aria-hidden="true" />
                    Ver pedido
                </AppButton>
                <AppButton :loading="sending" @click="emit('send')">
                    <Send class="h-4 w-4" aria-hidden="true" />
                    Enviar
                </AppButton>
            </div>
        </template>

        <template v-else>
            <div class="sticky-order-footer__meta">
                <strong>Ficha {{ commandLabel }}</strong>
                <small>Total acumulado: {{ totalFichaLabel }}</small>
            </div>
            <div class="sticky-order-footer__actions">
                <AppButton variant="secondary" @click="emit('open-summary')">Ver ficha</AppButton>
                <AppButton variant="secondary" @click="emit('switch')">Trocar</AppButton>
            </div>
        </template>
    </footer>
</template>

<style scoped>
.sticky-order-footer {
    position: fixed;
    left: 0.7rem;
    right: 0.7rem;
    bottom: 0.7rem;
    z-index: 45;
    border: 1px solid color-mix(in srgb, var(--color-primary) 45%, var(--color-border));
    border-radius: 0.88rem;
    background: color-mix(in srgb, var(--color-primary) 26%, var(--color-bg-surface));
    backdrop-filter: blur(8px);
    padding: 0.55rem 0.6rem;
    display: grid;
    gap: 0.52rem;
}

.sticky-order-footer span {
    color: var(--color-text);
    font-size: 0.82rem;
    font-weight: 700;
}

.sticky-order-footer__meta {
    display: grid;
    gap: 0.12rem;
}

.sticky-order-footer__meta strong {
    color: var(--color-text);
    font-size: 0.86rem;
}

.sticky-order-footer__meta small {
    color: color-mix(in srgb, var(--color-text) 80%, transparent);
    font-size: 0.8rem;
}

.sticky-order-footer__actions {
    display: grid;
    gap: 0.4rem;
    grid-template-columns: 1fr 1fr;
}

@media (min-width: 960px) {
    .sticky-order-footer {
        display: none;
    }
}
</style>
