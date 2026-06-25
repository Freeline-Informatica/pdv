<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { ArrowLeft, CookingPot, HandPlatter, Settings2, ShoppingBag, Store } from 'lucide-vue-next';
import AppThemeToggle from '../../layout/AppThemeToggle.vue';
import AppButton from '../../ui/AppButton.vue';
import AppBadge from '../../ui/AppBadge.vue';
import { getUserRole } from '../../../lib/auth';
import { useRestaurantTerminal } from '../../../composables/useRestaurantTerminal';

const props = defineProps({
    title: {
        type: String,
        default: '',
    },
    subtitle: {
        type: String,
        default: '',
    },
    modeLabel: {
        type: String,
        default: '',
    },
});

const route = useRoute();
const { restaurantMode } = useRestaurantTerminal();
const isAdminUser = computed(() => getUserRole() === 'admin');

const navItemsByMode = Object.freeze({
    auto_atendimento: [
        { to: '/pdv/restaurante/auto-atendimento', label: 'Autoatendimento', icon: HandPlatter },
    ],
    totem: [
        { to: '/pdv/restaurante/totem', label: 'Totem', icon: Store },
    ],
    comanda_garcom: [
        { to: '/pdv/restaurante/garcom', label: 'Garcom', icon: ShoppingBag },
    ],
    comanda_cozinha: [
        { to: '/pdv/restaurante/producao/cozinha', label: 'Produção cozinha', icon: CookingPot },
    ],
    comanda_bar: [
        { to: '/pdv/restaurante/producao/bar', label: 'Produção bar', icon: CookingPot },
    ],
    caixa: [],
});

const navItems = computed(() => navItemsByMode[restaurantMode.value] || []);

const activePath = computed(() => route.path);

function isNavActive(targetPath) {
    if (targetPath.startsWith('/pdv/restaurante/producao')) {
        return activePath.value.startsWith('/pdv/restaurante/producao');
    }

    return activePath.value === targetPath;
}
</script>

<template>
    <div class="restaurant-shell">
        <header class="restaurant-shell__head ui-card">
            <div class="restaurant-shell__head-top">
                <div class="restaurant-shell__title-wrap">
                    <p class="restaurant-shell__eyebrow">Simples PDV Restaurante</p>
                    <h1 class="restaurant-shell__title">{{ title }}</h1>
                    <p v-if="subtitle" class="restaurant-shell__subtitle">{{ subtitle }}</p>
                </div>

                <div class="restaurant-shell__tools">
                    <AppBadge v-if="modeLabel" variant="success">{{ modeLabel }}</AppBadge>
                    <AppThemeToggle />
                    <RouterLink v-if="isAdminUser" to="/configuracoes">
                        <AppButton variant="ghost">
                            <Settings2 class="h-4 w-4" aria-hidden="true" />
                            Configurações
                        </AppButton>
                    </RouterLink>
                    <RouterLink to="/">
                        <AppButton variant="secondary">
                            <ArrowLeft class="h-4 w-4" aria-hidden="true" />
                            Voltar ao PDV
                        </AppButton>
                    </RouterLink>
                </div>
            </div>

            <nav v-if="navItems.length" class="restaurant-shell__nav">
                <RouterLink
                    v-for="item in navItems"
                    :key="item.to"
                    :to="item.to"
                    class="restaurant-shell__nav-link"
                    :class="{ 'is-active': isNavActive(item.to) }"
                >
                    <component :is="item.icon" class="h-4 w-4" aria-hidden="true" />
                    <span>{{ item.label }}</span>
                </RouterLink>
            </nav>
        </header>

        <main class="restaurant-shell__main">
            <slot />
        </main>
    </div>
</template>

<style scoped>
.restaurant-shell {
    min-height: 100dvh;
    padding: 0.95rem;
    display: grid;
    gap: 0.85rem;
}

.restaurant-shell__head {
    padding: 0.85rem;
    display: grid;
    gap: 0.8rem;
}

.restaurant-shell__head-top {
    display: flex;
    justify-content: space-between;
    gap: 0.8rem;
    flex-wrap: wrap;
}

.restaurant-shell__title-wrap {
    min-width: 14rem;
}

.restaurant-shell__eyebrow {
    margin: 0;
    font-size: 0.74rem;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--color-text-muted);
    font-weight: 700;
}

.restaurant-shell__title {
    margin: 0.12rem 0 0;
    font-size: clamp(1.18rem, 2vw, 1.52rem);
    font-weight: 800;
    color: var(--color-text);
}

.restaurant-shell__subtitle {
    margin: 0.22rem 0 0;
    color: var(--color-text-muted);
    font-size: 0.9rem;
}

.restaurant-shell__tools {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.restaurant-shell__nav {
    display: flex;
    gap: 0.5rem;
    overflow-x: auto;
    padding-bottom: 0.1rem;
}

.restaurant-shell__nav-link {
    border-radius: 0.75rem;
    border: 1px solid var(--color-border);
    background: color-mix(in srgb, var(--color-bg-surface) 85%, var(--color-bg-elevated));
    color: var(--color-text-muted);
    font-weight: 700;
    font-size: 0.82rem;
    padding: 0.48rem 0.72rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.42rem;
    white-space: nowrap;
    transition: all var(--transition-fast);
}

.restaurant-shell__nav-link:hover {
    border-color: color-mix(in srgb, var(--color-primary) 45%, transparent);
    color: var(--color-text);
}

.restaurant-shell__nav-link.is-active {
    border-color: color-mix(in srgb, var(--color-primary) 55%, transparent);
    background: color-mix(in srgb, var(--color-primary) 16%, var(--color-bg-surface));
    color: var(--color-text);
}

.restaurant-shell__main {
    min-height: 0;
}

@media (max-width: 768px) {
    .restaurant-shell {
        padding: 0.68rem;
    }

    .restaurant-shell__head {
        padding: 0.7rem;
    }
}
</style>
