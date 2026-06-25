<script setup>
import { computed, ref } from 'vue';
import { PlugZap, Printer, TestTube2, Unplug } from 'lucide-vue-next';
import { useLocalPrinter } from '../../composables/useLocalPrinter';
import AppButton from '../ui/AppButton.vue';

defineProps({
    compact: {
        type: Boolean,
        default: false,
    },
});

const {
    printer,
    connect: connectLocalPrinter,
    disconnect: disconnectLocalPrinter,
    printTestPage,
} = useLocalPrinter();

const feedback = ref('');
const error = ref('');
const testing = ref(false);

const isNetworkMode = computed(() => printer.value.mode === 'network');
const isConnected = computed(() => printer.value.status === 'connected');
const isBusy = computed(() => ['connecting', 'printing'].includes(printer.value.status) || testing.value);
const canUsePrinter = computed(() => (isNetworkMode.value || printer.value.browserSupported) && !isBusy.value);

const statusLabel = computed(() => {
    const labels = {
        unsupported: 'Sem suporte',
        disconnected: 'Desconectada',
        connecting: 'Conectando',
        connected: 'Conectada',
        printing: 'Imprimindo',
        error: 'Erro',
    };

    return labels[printer.value.status] || printer.value.status;
});

const hint = computed(() => {
    if (isNetworkMode.value) {
        if (isConnected.value) return 'Bridge conectado e pronto para receber ESC/POS.';
        if (printer.value.status === 'error') return printer.value.lastError || 'Falha ao acessar o bridge de impressão.';
        return 'Conecte o bridge configurado neste terminal antes de iniciar as vendas.';
    }

    if (!printer.value.browserSupported) return 'Use Chrome ou Edge para conectar a impressora térmica.';
    if (isConnected.value) return `Pronta em ${printer.value.transport || 'canal local'}.`;
    if (printer.value.status === 'error') return printer.value.lastError || 'Falha ao acessar a impressora.';
    return 'Conecte a impressora do caixa antes de iniciar as vendas.';
});

async function connectPrinter() {
    feedback.value = '';
    error.value = '';

    const state = isNetworkMode.value
        ? await connectLocalPrinter()
        : await connectLocalPrinter(printer.value.supportsSerial ? 'serial' : 'auto');
    if (state.status !== 'connected') {
        error.value = state.lastError || (
            isNetworkMode.value
                ? 'Não foi possível conectar ao bridge da impressora.'
                : 'Não foi possível conectar a impressora térmica.'
        );
        return;
    }

    feedback.value = isNetworkMode.value
        ? 'Bridge de impressão conectado.'
        : 'Impressora conectada ao caixa.';
}

async function disconnectPrinter() {
    feedback.value = '';
    error.value = '';
    await disconnectLocalPrinter();
    feedback.value = isNetworkMode.value
        ? 'Bridge de impressão desconectado.'
        : 'Impressora desconectada.';
}

async function testPrinter() {
    feedback.value = '';
    error.value = '';
    testing.value = true;

    try {
        if (!isConnected.value) {
            const state = isNetworkMode.value
                ? await connectLocalPrinter()
                : await connectLocalPrinter(printer.value.supportsSerial ? 'serial' : 'auto');
            if (state.status !== 'connected') {
                error.value = state.lastError || (
                    isNetworkMode.value
                        ? 'Não foi possível conectar ao bridge da impressora.'
                        : 'Não foi possível conectar a impressora térmica.'
                );
                return;
            }
        }

        await printTestPage();
        feedback.value = isNetworkMode.value
            ? 'Página de teste enviada ao bridge.'
            : 'Página de teste enviada.';
    } catch (exception) {
        error.value = exception?.message || 'Falha ao imprimir a página de teste.';
    } finally {
        testing.value = false;
    }
}
</script>

<template>
    <div v-if="compact" class="printer-compact">
        <button
            type="button"
            class="printer-compact-btn"
            :class="{ 'is-connected': isConnected, 'is-error': printer.status === 'error' }"
            :disabled="!canUsePrinter"
            :title="hint"
            @click="connectPrinter"
        >
            <Printer class="h-4 w-4" aria-hidden="true" />
            <span>{{ statusLabel }}</span>
        </button>
    </div>

    <section v-else class="printer-panel">
        <div class="printer-panel__main">
            <div class="printer-panel__icon" :class="{ 'is-connected': isConnected, 'is-error': printer.status === 'error' }">
                <Printer class="h-5 w-5" aria-hidden="true" />
            </div>
            <div>
                <p class="printer-panel__eyebrow">Impressora do caixa</p>
                <h4 class="printer-panel__title">Conexão térmica ESC/POS</h4>
                <p class="printer-panel__hint">{{ hint }}</p>
                <p v-if="printer.label" class="printer-panel__device">{{ printer.label }}</p>
                <p v-if="feedback" class="printer-panel__feedback">{{ feedback }}</p>
                <p v-if="error" class="printer-panel__error">{{ error }}</p>
            </div>
        </div>

        <div class="printer-panel__actions">
            <AppButton variant="secondary" :disabled="!canUsePrinter" @click="connectPrinter">
                <PlugZap class="h-4 w-4" aria-hidden="true" />
                {{ isConnected ? 'Reconectar' : 'Conectar' }}
            </AppButton>
            <AppButton variant="secondary" :disabled="!canUsePrinter" :loading="testing" @click="testPrinter">
                <TestTube2 class="h-4 w-4" aria-hidden="true" />
                Testar
            </AppButton>
            <AppButton v-if="isConnected" variant="ghost" :disabled="isBusy" @click="disconnectPrinter">
                <Unplug class="h-4 w-4" aria-hidden="true" />
                Desconectar
            </AppButton>
        </div>
    </section>
</template>

<style scoped>
.printer-compact-btn {
    min-height: 2.2rem;
    border-radius: var(--radius-sm);
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 48%, transparent);
    background: color-mix(in srgb, var(--color-bg-elevated) 75%, var(--color-bg-surface));
    color: var(--color-text-muted);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    padding: 0 0.6rem;
    font-size: 0.73rem;
    font-weight: 800;
    transition: all var(--transition-fast);
}

.printer-compact-btn:hover {
    border-color: color-mix(in srgb, var(--color-primary) 55%, transparent);
    color: var(--color-text);
}

.printer-compact-btn.is-connected {
    border-color: color-mix(in srgb, var(--color-success) 48%, var(--color-border));
    background: color-mix(in srgb, var(--color-success) 12%, var(--color-bg-surface));
    color: var(--color-success);
}

.printer-compact-btn.is-error {
    border-color: color-mix(in srgb, var(--color-danger) 48%, var(--color-border));
    background: color-mix(in srgb, var(--color-danger) 12%, var(--color-bg-surface));
    color: var(--color-danger);
}

.printer-compact-btn:disabled {
    cursor: not-allowed;
    opacity: 0.72;
}

.printer-panel {
    border: 1px solid color-mix(in srgb, var(--color-primary) 32%, var(--color-border));
    border-radius: var(--radius-md);
    background: color-mix(in srgb, var(--color-primary) 7%, var(--color-bg-surface));
    padding: 0.85rem;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 0.9rem;
    align-items: center;
    margin-bottom: 0.95rem;
}

.printer-panel__main {
    min-width: 0;
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    gap: 0.75rem;
    align-items: start;
}

.printer-panel__icon {
    width: 2.35rem;
    height: 2.35rem;
    border-radius: var(--radius-sm);
    border: 1px solid var(--color-border);
    background: var(--color-bg-elevated);
    color: var(--color-text-muted);
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.printer-panel__icon.is-connected {
    border-color: color-mix(in srgb, var(--color-success) 44%, var(--color-border));
    color: var(--color-success);
}

.printer-panel__icon.is-error {
    border-color: color-mix(in srgb, var(--color-danger) 44%, var(--color-border));
    color: var(--color-danger);
}

.printer-panel__eyebrow,
.printer-panel__hint,
.printer-panel__device,
.printer-panel__feedback,
.printer-panel__error {
    margin: 0;
}

.printer-panel__eyebrow {
    font-size: 0.72rem;
    text-transform: uppercase;
    font-weight: 800;
    color: var(--color-text-muted);
}

.printer-panel__title {
    margin: 0.16rem 0 0.16rem;
    font-size: 0.98rem;
    line-height: 1.2;
    font-weight: 900;
    color: var(--color-text);
}

.printer-panel__hint,
.printer-panel__device {
    font-size: 0.78rem;
    color: var(--color-text-muted);
}

.printer-panel__feedback {
    margin-top: 0.35rem;
    font-size: 0.78rem;
    color: var(--color-success);
}

.printer-panel__error {
    margin-top: 0.35rem;
    font-size: 0.78rem;
    color: var(--color-danger);
}

.printer-panel__actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 0.45rem;
}

@media (max-width: 760px) {
    .printer-panel {
        grid-template-columns: 1fr;
    }

    .printer-panel__actions {
        justify-content: stretch;
    }
}
</style>
