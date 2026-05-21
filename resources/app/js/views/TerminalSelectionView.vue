<script setup>
import { AlertTriangle, LogOut, Monitor, RefreshCcw } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '../lib/api';
import { clearAuthData, exitIntegratedPdv, getTerminalSession, getUser, resolvePdvExitLabel, setTerminalSession } from '../lib/auth';
import { resolveTerminalLandingPath } from '../lib/terminalRouting';
import AppCard from '../components/ui/AppCard.vue';
import AppButton from '../components/ui/AppButton.vue';
import AppThemeToggle from '../components/layout/AppThemeToggle.vue';

const router = useRouter();
const currentUser = getUser();
const refreshing = ref(false);
const saving = ref(false);
const loadingTerminals = ref(false);
const pageError = ref('');
const terminals = ref([]);

const persistedTerminal = getTerminalSession();
const validLayoutModes = new Set(['varejo', 'restaurante', 'servicos']);
const validRestaurantModes = new Set(['auto_atendimento', 'totem', 'caixa', 'comanda_bar', 'comanda_cozinha', 'comanda_garcom']);
const defaultRestaurantMode = 'comanda_garcom';

function normalizeLayoutMode(value) {
    const normalized = String(value || '').trim().toLowerCase();
    return validLayoutModes.has(normalized) ? normalized : 'varejo';
}

function normalizeRestaurantMode(layoutMode, value) {
    if (layoutMode !== 'restaurante') return null;

    const normalized = String(value || '').trim().toLowerCase();
    return validRestaurantModes.has(normalized) ? normalized : defaultRestaurantMode;
}

function resolveOwnTerminalIndex(user, terminalsCount) {
    if (!terminalsCount) return 0;

    const basis = Number(user?.id ?? 0);
    if (!Number.isInteger(basis) || basis <= 0) return 0 % terminalsCount;
    return Math.abs(basis - 1) % terminalsCount;
}

function mapTerminals(items, user) {
    const ownIndex = resolveOwnTerminalIndex(user, items.length);

    return items.map((item, index) => {
        const isOwn = index === ownIndex;
        const isPersisted = persistedTerminal?.id === item.id;
        const layoutMode = normalizeLayoutMode(item.pdv_layout_mode);

        return {
            id: String(item.id),
            label: String(item.nome),
            code: String(item.identificador),
            layoutMode,
            restaurantMode: normalizeRestaurantMode(layoutMode, item.pdv_restaurant_mode),
            isOwn,
            status: isOwn ? 'mine' : 'free',
            hasOpenCash: isOwn && isPersisted,
        };
    });
}

const ownTerminal = computed(() => terminals.value.find((item) => item.isOwn) || null);

const selectedTerminalId = ref(
    persistedTerminal?.id && terminals.value.some((item) => item.id === persistedTerminal.id)
        ? persistedTerminal.id
        : null,
);

const selectedTerminal = computed(() => terminals.value.find((item) => item.id === selectedTerminalId.value) || null);
const openCashTerminal = computed(() => terminals.value.find((item) => item.isOwn && item.hasOpenCash) || null);
const exitLabel = computed(() => resolvePdvExitLabel());

const canContinueSelected = computed(() => {
    if (!selectedTerminal.value) return false;
    return !(selectedTerminal.value.status === 'occupied' && !selectedTerminal.value.isOwn);
});

const selectedActionLabel = computed(() => {
    if (!selectedTerminal.value) return 'Selecione um terminal';
    if (selectedTerminal.value.isOwn) return 'Entrar no seu caixa';
    return 'Entrar no caixa selecionado';
});

function statusLabel(terminal) {
    if (terminal.isOwn) return 'Seu caixa';
    if (terminal.status === 'occupied') return 'Ocupado';
    return 'Livre';
}

function isTerminalBlocked(terminal) {
    return terminal.status === 'occupied' && !terminal.isOwn;
}

function selectTerminal(terminal) {
    if (isTerminalBlocked(terminal)) return;
    selectedTerminalId.value = terminal.id;
}

async function loadTerminals() {
    loadingTerminals.value = true;
    pageError.value = '';

    try {
        const { data } = await api.get('/pos-terminals');
        const activeItems = Array.isArray(data)
            ? data.filter((item) => Boolean(item?.ativo))
            : [];

        terminals.value = mapTerminals(activeItems, currentUser);

        const fallbackId = ownTerminal.value?.id || terminals.value[0]?.id || null;
        if (!selectedTerminalId.value || !terminals.value.some((item) => item.id === selectedTerminalId.value)) {
            selectedTerminalId.value = fallbackId;
        }
    } catch (requestError) {
        terminals.value = [];
        selectedTerminalId.value = null;
        pageError.value = requestError?.response?.data?.message ?? 'Não foi possível carregar os terminais.';
    } finally {
        loadingTerminals.value = false;
    }
}

async function continueWithTerminal(terminal) {
    if (!terminal || isTerminalBlocked(terminal) || saving.value) return;

    saving.value = true;
    try {
        setTerminalSession({
            id: terminal.id,
            label: terminal.label,
            code: terminal.code,
            layoutMode: terminal.layoutMode,
            restaurantMode: terminal.restaurantMode,
            isOwn: terminal.isOwn,
        });

        await router.push(resolveTerminalLandingPath(terminal));
    } finally {
        saving.value = false;
    }
}

async function continueOpenCash() {
    if (!openCashTerminal.value) return;
    await continueWithTerminal(openCashTerminal.value);
}

async function refreshTerminals() {
    if (refreshing.value) return;

    refreshing.value = true;
    try {
        await loadTerminals();
    } finally {
        refreshing.value = false;
    }
}

async function exitSession() {
    if (exitIntegratedPdv()) return;

    clearAuthData();
    await router.push('/login');
}

onMounted(loadTerminals);
</script>

<template>
    <div class="terminal-screen min-h-screen flex items-center justify-center px-4 py-4">
        <div class="fixed top-4 right-4">
            <AppThemeToggle />
        </div>

        <AppCard class="terminal-card p-5 md:p-6" elevated>
            <header class="terminal-head">
                <span class="terminal-head__icon" aria-hidden="true">
                    <Monitor class="h-10 w-10" />
                </span>
                <h1 class="terminal-head__title">Selecione o Terminal</h1>
                <p class="terminal-head__subtitle">Escolha o terminal de PDV para iniciar as operações.</p>
            </header>

            <section v-if="openCashTerminal" class="terminal-open-box">
                <div class="terminal-open-box__headline">
                    <AlertTriangle class="h-5 w-5" />
                    <strong>Você possui um caixa aberto</strong>
                </div>
                <p class="terminal-open-box__line">
                    Terminal:
                    <strong>{{ openCashTerminal.label }} ({{ openCashTerminal.code }})</strong>
                </p>
                <AppButton block @click="continueOpenCash">Continuar no caixa aberto</AppButton>
            </section>

            <p v-if="pageError" class="terminal-message terminal-message--error">{{ pageError }}</p>
            <p v-else-if="loadingTerminals" class="terminal-message">Carregando terminais...</p>
            <p v-else-if="terminals.length === 0" class="terminal-message">Nenhum terminal ativo cadastrado.</p>

            <div class="terminal-tools">
                <button type="button" class="terminal-refresh-btn" :disabled="refreshing" @click="refreshTerminals">
                    <RefreshCcw class="h-4 w-4" :class="{ 'animate-spin': refreshing }" />
                    <span>Atualizar</span>
                </button>
            </div>

            <div class="terminal-list">
                <button
                    v-for="terminal in terminals"
                    :key="terminal.id"
                    type="button"
                    class="terminal-row"
                    :class="{
                        'is-selected': selectedTerminalId === terminal.id,
                        'is-mine': terminal.isOwn,
                        'is-occupied': terminal.status === 'occupied' && !terminal.isOwn,
                    }"
                    :disabled="isTerminalBlocked(terminal)"
                    @click="selectTerminal(terminal)"
                >
                    <span class="terminal-row__main">
                        <Monitor class="h-4 w-4" />
                        <strong>{{ terminal.label }} ({{ terminal.code }})</strong>
                    </span>
                    <span class="terminal-row__badge">{{ statusLabel(terminal) }}</span>
                </button>
            </div>

            <div class="terminal-actions">
                <AppButton block :loading="saving" :disabled="!canContinueSelected" @click="continueWithTerminal(selectedTerminal)">
                    {{ selectedActionLabel }}
                </AppButton>

                <AppButton variant="ghost" block @click="exitSession">
                    <LogOut class="h-4 w-4" />
                    {{ exitLabel }}
                </AppButton>
            </div>
        </AppCard>
    </div>
</template>

<style scoped>
.terminal-card {
    display: grid;
    gap: 0.78rem;
    width: min(38rem, 100%);
}

.terminal-head {
    text-align: center;
    display: grid;
    justify-items: center;
    gap: 0.35rem;
}

.terminal-head__icon {
    color: color-mix(in srgb, var(--color-text-muted) 68%, transparent);
}

.terminal-head__title {
    margin: 0;
    font-size: 1.75rem;
    line-height: 1.1;
    font-weight: 900;
    color: var(--color-text);
}

.terminal-head__subtitle {
    margin: 0;
    color: var(--color-text-muted);
    font-size: 0.98rem;
}

.terminal-open-box {
    border-radius: var(--radius-md);
    border: 1px solid color-mix(in srgb, var(--color-success) 40%, var(--color-border));
    background: color-mix(in srgb, var(--color-success) 12%, var(--color-bg-elevated));
    padding: 0.82rem;
    display: grid;
    gap: 0.5rem;
}

.terminal-open-box__headline {
    display: flex;
    align-items: center;
    gap: 0.38rem;
    color: color-mix(in srgb, var(--color-success) 90%, var(--color-text));
}

.terminal-open-box__line {
    margin: 0;
    font-size: 0.92rem;
    color: var(--color-text-muted);
}

.terminal-open-box__line strong {
    color: var(--color-text);
}

.terminal-message {
    margin: 0;
    text-align: center;
    color: var(--color-text-muted);
    font-size: 0.9rem;
}

.terminal-message--error {
    color: var(--color-danger);
}

.terminal-tools {
    display: flex;
    justify-content: center;
}

.terminal-refresh-btn {
    border: 0;
    background: transparent;
    color: var(--color-text);
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.92rem;
    font-weight: 700;
    cursor: pointer;
    padding: 0.2rem 0.5rem;
}

.terminal-refresh-btn:disabled {
    opacity: 0.62;
    cursor: default;
}

.terminal-list {
    display: grid;
    gap: 0.56rem;
}

.terminal-row {
    width: 100%;
    border-radius: var(--radius-md);
    border: 1px solid var(--color-border);
    background: var(--color-bg-surface);
    color: var(--color-text);
    min-height: 2.8rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.52rem;
    padding: 0.58rem 0.8rem;
    transition: all var(--transition-fast);
}

.terminal-row:hover:not(:disabled) {
    border-color: color-mix(in srgb, var(--color-primary) 45%, transparent);
}

.terminal-row.is-selected {
    border-color: color-mix(in srgb, var(--color-primary) 70%, transparent);
    background: color-mix(in srgb, var(--color-primary) 10%, var(--color-bg-surface));
}

.terminal-row.is-mine {
    border-color: color-mix(in srgb, var(--color-success) 54%, var(--color-border));
}

.terminal-row.is-occupied {
    border-color: color-mix(in srgb, var(--color-danger) 50%, var(--color-border));
    color: color-mix(in srgb, var(--color-text-muted) 84%, transparent);
}

.terminal-row:disabled {
    opacity: 0.72;
    cursor: not-allowed;
}

.terminal-row__main {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    min-width: 0;
    font-size: 1.02rem;
}

.terminal-row__badge {
    border-radius: 999px;
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 45%, transparent);
    background: color-mix(in srgb, var(--color-bg-elevated) 80%, var(--color-bg-surface));
    font-size: 0.85rem;
    font-weight: 700;
    line-height: 1;
    padding: 0.26rem 0.58rem;
}

.terminal-row.is-mine .terminal-row__badge {
    border-color: color-mix(in srgb, var(--color-success) 45%, transparent);
    background: color-mix(in srgb, var(--color-success) 18%, var(--color-bg-surface));
    color: color-mix(in srgb, var(--color-success) 88%, var(--color-text));
}

.terminal-row.is-occupied .terminal-row__badge {
    border-color: color-mix(in srgb, var(--color-danger) 35%, transparent);
    background: color-mix(in srgb, var(--color-danger) 12%, var(--color-bg-surface));
    color: color-mix(in srgb, var(--color-danger) 82%, var(--color-text));
}

.terminal-actions {
    display: grid;
    gap: 0.45rem;
    margin-top: 0.1rem;
}

@media (max-width: 768px) {
    .terminal-head__title {
        font-size: 1.5rem;
    }

    .terminal-head__subtitle {
        font-size: 0.9rem;
    }

    .terminal-row__main {
        font-size: 0.95rem;
    }

    .terminal-row__badge {
        font-size: 0.78rem;
    }
}
</style>
