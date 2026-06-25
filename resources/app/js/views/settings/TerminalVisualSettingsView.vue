<script setup>
import { computed, ref } from 'vue';
import SettingsPageHeader from '../../components/settings/SettingsPageHeader.vue';
import AppCard from '../../components/ui/AppCard.vue';
import AppBadge from '../../components/ui/AppBadge.vue';

const STORAGE_KEY = 'simples_pdv_terminal_type';

const terminalOptions = [
    {
        id: 'varejo',
        label: 'Varejo',
        description: 'Fluxo completo com catalogo em grade e carrinho lateral.',
        accent: '#22c983',
        status: 'Padrao atual',
    },
    {
        id: 'restaurante',
        label: 'Restaurante',
        description: 'Visual com foco em operacao rapida de atendimento e comandas.',
        accent: '#f59e0b',
        status: 'Preview simplificado',
    },
    {
        id: 'servicos',
        label: 'Servicos',
        description: 'Interface enxuta para lancamento direto e finalizacao objetiva.',
        accent: '#60a5fa',
        status: 'Preview simplificado',
    },
];

const selectedTerminalType = ref('varejo');

const selectedTerminalLabel = computed(() => {
    return terminalOptions.find((option) => option.id === selectedTerminalType.value)?.label || 'Varejo';
});

function loadTerminalType() {
    if (typeof window === 'undefined') return;
    const persisted = window.localStorage.getItem(STORAGE_KEY);
    const valid = terminalOptions.some((option) => option.id === persisted);
    selectedTerminalType.value = valid ? persisted : 'varejo';
}

function selectTerminalType(typeId) {
    selectedTerminalType.value = typeId;
    if (typeof window !== 'undefined') {
        window.localStorage.setItem(STORAGE_KEY, typeId);
    }
}

loadTerminalType();
</script>

<template>
    <div class="space-y-4">
        <SettingsPageHeader
            title="Configurações de Terminal"
            subtitle="Escolha o tipo de terminal para ajustar a experiencia visual do PDV."
        />

        <AppCard elevated>
            <div class="flex flex-wrap items-center gap-2">
                <AppBadge variant="default">Tipo selecionado: {{ selectedTerminalLabel }}</AppBadge>
                <AppBadge variant="success">Aplicacao imediata</AppBadge>
            </div>
            <p class="text-sm text-muted mt-3">
                O tipo <strong class="text-main">{{ selectedTerminalLabel }}</strong> e usado como perfil visual ativo do terminal.
            </p>
        </AppCard>

        <AppCard elevated>
            <div class="mb-3">
                <h2 class="ui-section-title">Tipo de terminal</h2>
                <p class="ui-section-subtitle">
                    Selecione um card como na tela de tema. Cada opcao mostra uma visualizacao simplificada do PDV.
                </p>
            </div>

            <div class="terminal-grid">
                <button
                    v-for="option in terminalOptions"
                    :key="option.id"
                    type="button"
                    class="terminal-card"
                    :class="{ 'is-active': option.id === selectedTerminalType }"
                    @click="selectTerminalType(option.id)"
                >
                    <div class="terminal-preview" :class="`is-${option.id}`">
                        <div class="preview-topbar">
                            <span class="preview-dot" />
                            <span class="preview-title">PDV {{ option.label }}</span>
                        </div>
                        <div v-if="option.id === 'varejo'" class="preview-varejo-layout">
                            <aside class="pv-sidebar">
                                <span class="pv-logo-block" />
                                <span class="pv-cat is-active" />
                                <span class="pv-cat" />
                                <span class="pv-cat" />
                                <span class="pv-cat" />
                                <div class="pv-sidebar-bottom">
                                    <span class="pv-side-btn" />
                                    <span class="pv-side-btn" />
                                    <span class="pv-side-btn" />
                                </div>
                            </aside>

                            <main class="pv-main">
                                <div class="pv-main-top">
                                    <div class="pv-chip-row">
                                        <span class="pv-chip" />
                                        <span class="pv-chip is-ok" />
                                        <span class="pv-chip is-square" />
                                    </div>
                                    <span class="pv-search" />
                                </div>
                                <span class="pv-divider" />
                                <div class="pv-grid">
                                    <span v-for="cardIndex in 16" :key="`pv-card-${cardIndex}`" class="pv-card" />
                                </div>
                            </main>

                            <aside class="pv-cart">
                                <span class="pv-cart-divider" />
                                <span class="pv-cart-box" />
                                <span class="pv-cart-box" />
                                <span class="pv-cart-box" />
                                <div class="pv-cart-actions">
                                    <span class="pv-action is-ok" />
                                    <span class="pv-action" />
                                </div>
                            </aside>
                        </div>

                        <div v-else class="preview-body">
                            <div class="preview-rail">
                                <span class="rail-pill is-strong" />
                                <span class="rail-pill" />
                                <span class="rail-pill" />
                            </div>
                            <div class="preview-products">
                                <span class="preview-search" />
                                <div class="preview-products-grid">
                                    <span class="preview-product is-strong" />
                                    <span class="preview-product" />
                                    <span class="preview-product" />
                                    <span class="preview-product" />
                                </div>
                            </div>
                            <div class="preview-cart">
                                <span class="cart-line is-strong" />
                                <span class="cart-line" />
                                <span class="cart-total" />
                            </div>
                        </div>
                    </div>

                    <div class="terminal-card-content">
                        <div class="flex items-center justify-between gap-2">
                            <p class="font-bold text-main">{{ option.label }}</p>
                            <AppBadge v-if="option.id === selectedTerminalType" variant="success">Ativo</AppBadge>
                        </div>
                        <p class="text-sm text-muted mt-1">{{ option.description }}</p>
                        <div class="terminal-pill mt-3">
                            <span class="terminal-pill-dot" :style="{ backgroundColor: option.accent }" />
                            <span>{{ option.status }}</span>
                        </div>
                    </div>
                </button>
            </div>
        </AppCard>
    </div>
</template>

<style scoped>
.terminal-grid {
    display: grid;
    gap: var(--space-4);
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
}

.terminal-card {
    width: 100%;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    background: var(--color-bg-surface);
    text-align: left;
    overflow: hidden;
    transition: border-color var(--transition-fast), background var(--transition-fast), transform var(--transition-fast);
}

.terminal-card:hover {
    border-color: var(--color-border-strong);
    transform: translateY(-1px);
}

.terminal-card.is-active {
    border-color: color-mix(in srgb, var(--color-primary) 64%, var(--color-border));
    background: color-mix(in srgb, var(--color-primary) 11%, var(--color-bg-surface));
}

.terminal-card-content {
    padding: var(--space-4);
}

.terminal-preview {
    padding: 0.62rem;
    border-bottom: 1px solid color-mix(in srgb, var(--color-border) 65%, transparent);
    background: linear-gradient(160deg, #0a1324 0%, #12213a 54%, #0b172b 100%);
}

.terminal-preview.is-restaurante {
    background: linear-gradient(160deg, #21110a 0%, #3f2211 54%, #241207 100%);
}

.terminal-preview.is-servicos {
    background: linear-gradient(160deg, #091627 0%, #15355d 54%, #0a1e35 100%);
}

.preview-topbar {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    margin-bottom: 0.45rem;
}

.preview-dot {
    width: 0.35rem;
    height: 0.35rem;
    border-radius: 999px;
    background: #32d583;
}

.preview-title {
    font-size: 0.64rem;
    color: #e2e8f0;
    font-weight: 700;
    letter-spacing: 0.02em;
}

.preview-varejo-layout {
    display: grid;
    grid-template-columns: 0.5fr 2.2fr 1fr;
    gap: 0.34rem;
}

.pv-sidebar,
.pv-main,
.pv-cart {
    border-radius: 0.5rem;
    border: 1px solid rgb(255 255 255 / 0.16);
    background: rgb(13 33 54 / 0.66);
    min-height: 4.1rem;
}

.pv-sidebar {
    display: grid;
    align-content: start;
    gap: 0.16rem;
    padding: 0.34rem;
}

.pv-logo-block {
    display: block;
    height: 0.86rem;
    border-radius: 0.28rem;
    background: rgb(44 180 64 / 0.92);
}

.pv-chip,
.pv-cat,
.pv-side-btn,
.pv-search,
.pv-card,
.pv-cart-divider,
.pv-cart-box,
.pv-action {
    display: block;
    border-radius: 0.28rem;
    background: rgb(92 123 157 / 0.42);
}

.pv-chip {
    width: 0.58rem;
    height: 0.28rem;
}

.pv-chip.is-ok {
    background: rgb(44 180 64 / 0.92);
}

.pv-chip.is-square {
    width: 0.28rem;
    height: 0.28rem;
}

.pv-cat {
    height: 0.42rem;
}

.pv-cat.is-active {
    background: rgb(44 180 64 / 0.92);
}

.pv-sidebar-bottom {
    margin-top: auto;
    display: grid;
    gap: 0.12rem;
}

.pv-side-btn {
    height: 0.34rem;
}

.pv-main {
    padding: 0.34rem;
    display: grid;
    gap: 0.18rem;
    align-content: start;
}

.pv-main-top {
    display: grid;
    gap: 0.16rem;
}

.pv-chip-row {
    display: inline-flex;
    justify-self: end;
    align-items: center;
    gap: 0.1rem;
}

.pv-search {
    height: 0.42rem;
}

.pv-divider {
    display: block;
    height: 0.03rem;
    border-radius: 999px;
    background: rgb(27 161 255 / 0.95);
}

.pv-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.12rem;
}

.pv-card {
    height: 0.9rem;
}

.pv-cart {
    padding: 0.34rem;
    display: grid;
    align-content: start;
    gap: 0.14rem;
}

.pv-cart-divider {
    height: 0.03rem;
    border-radius: 999px;
}

.pv-cart-box {
    height: 0.95rem;
}

.pv-cart-actions {
    margin-top: auto;
    display: grid;
    gap: 0.12rem;
}

.pv-action {
    height: 0.4rem;
}

.pv-action.is-ok {
    background: rgb(44 180 64 / 0.92);
}

.preview-body {
    display: grid;
    grid-template-columns: 0.55fr 1fr 0.75fr;
    gap: 0.35rem;
}

.preview-rail,
.preview-products,
.preview-cart {
    border-radius: 0.45rem;
    border: 1px solid rgb(255 255 255 / 0.16);
    background: rgb(8 18 33 / 0.66);
    min-height: 3.15rem;
    padding: 0.28rem;
}

.terminal-preview.is-restaurante .preview-rail,
.terminal-preview.is-restaurante .preview-products,
.terminal-preview.is-restaurante .preview-cart {
    background: rgb(44 22 10 / 0.62);
}

.terminal-preview.is-servicos .preview-rail,
.terminal-preview.is-servicos .preview-products,
.terminal-preview.is-servicos .preview-cart {
    background: rgb(10 28 50 / 0.62);
}

.preview-rail {
    display: grid;
    gap: 0.24rem;
    align-content: start;
}

.rail-pill,
.preview-search,
.preview-product,
.cart-line,
.cart-total {
    display: block;
    border-radius: 0.3rem;
    background: rgb(226 232 240 / 0.25);
}

.rail-pill {
    height: 0.34rem;
}

.rail-pill.is-strong,
.preview-product.is-strong,
.cart-line.is-strong {
    background: rgb(34 197 131 / 0.72);
}

.terminal-preview.is-restaurante .rail-pill.is-strong,
.terminal-preview.is-restaurante .preview-product.is-strong,
.terminal-preview.is-restaurante .cart-line.is-strong {
    background: rgb(245 158 11 / 0.75);
}

.terminal-preview.is-servicos .rail-pill.is-strong,
.terminal-preview.is-servicos .preview-product.is-strong,
.terminal-preview.is-servicos .cart-line.is-strong {
    background: rgb(96 165 250 / 0.75);
}

.preview-search {
    height: 0.35rem;
    margin-bottom: 0.25rem;
}

.preview-products-grid {
    display: grid;
    gap: 0.2rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.preview-product {
    height: 0.7rem;
}

.preview-cart {
    display: grid;
    gap: 0.24rem;
    align-content: start;
}

.cart-line {
    height: 0.34rem;
}

.cart-total {
    height: 0.5rem;
    margin-top: 0.18rem;
}

.terminal-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    border-radius: 999px;
    border: 1px solid var(--color-border);
    background: color-mix(in srgb, var(--color-bg-elevated) 82%, var(--color-bg-surface));
    color: var(--color-text-muted);
    font-size: 0.74rem;
    font-weight: 700;
    padding: 0.22rem 0.6rem;
}

.terminal-pill-dot {
    width: 0.75rem;
    height: 0.75rem;
    border-radius: 999px;
    border: 1px solid rgb(255 255 255 / 0.45);
    box-shadow: 0 0 0 1px rgb(15 23 42 / 0.24);
}
</style>
