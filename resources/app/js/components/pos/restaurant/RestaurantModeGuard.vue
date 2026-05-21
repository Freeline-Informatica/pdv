<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import AppCard from '../../ui/AppCard.vue';
import AppButton from '../../ui/AppButton.vue';
import { useRestaurantTerminal, getRestaurantModeLabel } from '../../../composables/useRestaurantTerminal';
import { getUserRole } from '../../../lib/auth';

const props = defineProps({
    allowedModes: {
        type: Array,
        default: () => [],
    },
    featureLabel: {
        type: String,
        default: 'este recurso',
    },
});

const router = useRouter();
const { terminal, isRestaurantTerminal, restaurantMode } = useRestaurantTerminal();
const isAdminUser = computed(() => getUserRole() === 'admin');

const hasModeAccess = computed(() => {
    if (!props.allowedModes.length) return true;
    return props.allowedModes.includes(restaurantMode.value);
});

const canAccess = computed(() => isRestaurantTerminal.value && hasModeAccess.value);

const blockedMessage = computed(() => {
    if (!isRestaurantTerminal.value) {
        return 'Este recurso esta disponivel somente para terminais configurados como PDV Restaurante.';
    }

    const currentMode = getRestaurantModeLabel(restaurantMode.value);
    return `Este recurso nao esta habilitado para o modo atual (${currentMode}).`; 
});

function goToTerminalSelection() {
    router.push('/selecionar-terminal');
}

function goToMainPdv() {
    router.push('/');
}

function goToSettings() {
    router.push('/configuracoes');
}
</script>

<template>
    <div v-if="canAccess">
        <slot />
    </div>

    <div v-else class="restaurant-guard">
        <AppCard class="restaurant-guard__card" elevated>
            <p class="restaurant-guard__eyebrow">Acesso restrito</p>
            <h1 class="restaurant-guard__title">Modo Restaurante necessario</h1>
            <p class="restaurant-guard__text">{{ blockedMessage }}</p>
            <p class="restaurant-guard__meta">
                Terminal atual: <strong>{{ terminal?.label || 'Nao identificado' }}</strong>
                <span v-if="terminal?.code">({{ terminal.code }})</span>
            </p>
            <p class="restaurant-guard__meta">
                Recurso solicitado: <strong>{{ featureLabel }}</strong>
            </p>
            <div class="restaurant-guard__actions">
                <AppButton @click="goToTerminalSelection">Trocar terminal</AppButton>
                <AppButton v-if="isAdminUser" variant="ghost" @click="goToSettings">Voltar para configuracoes</AppButton>
                <AppButton variant="secondary" @click="goToMainPdv">Voltar ao PDV</AppButton>
            </div>
        </AppCard>
    </div>
</template>

<style scoped>
.restaurant-guard {
    min-height: 100dvh;
    display: grid;
    place-items: center;
    padding: 1.25rem;
}

.restaurant-guard__card {
    width: min(42rem, 100%);
    display: grid;
    gap: 0.7rem;
}

.restaurant-guard__eyebrow {
    margin: 0;
    font-size: 0.74rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--color-text-muted);
    font-weight: 700;
}

.restaurant-guard__title {
    margin: 0;
    font-size: 1.45rem;
    font-weight: 800;
    color: var(--color-text);
}

.restaurant-guard__text,
.restaurant-guard__meta {
    margin: 0;
    color: var(--color-text-muted);
    font-size: 0.94rem;
}

.restaurant-guard__meta strong {
    color: var(--color-text);
}

.restaurant-guard__actions {
    margin-top: 0.35rem;
    display: flex;
    gap: 0.55rem;
    flex-wrap: wrap;
}
</style>
