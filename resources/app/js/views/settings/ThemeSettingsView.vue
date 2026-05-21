<script setup>
import { computed } from 'vue';
import SettingsPageHeader from '../../components/settings/SettingsPageHeader.vue';
import AppCard from '../../components/ui/AppCard.vue';
import AppBadge from '../../components/ui/AppBadge.vue';
import { useThemeStore } from '../../stores/theme';

const {
    theme,
    mode,
    palette,
    setThemeMode,
    setPalette,
    availableModes,
    availablePalettes,
} = useThemeStore();

const activeModeLabel = computed(() => availableModes.value.find((item) => item.id === mode.value)?.label ?? 'Sistema');
const activePaletteLabel = computed(() => availablePalettes.value.find((item) => item.id === palette.value)?.label ?? 'Salesforce Signature');

const DARK_BACKGROUND_BY_PALETTE = {
    'salesforce-signature': '/salesforcebackground/salesforcesignature_dark.png',
    'ocean-tech': '/salesforcebackground/oceantech_dark.png',
    'emerald-corporate': '/salesforcebackground/emeraldcorporate_dark.png',
    'graphite-clean': '/salesforcebackground/graphiteclean_dark.png',
    'sunset-luxe': '/salesforcebackground/sunsetluxe_dark.png',
    'nordic-ice': '/salesforcebackground/nordicice_dark.png',
    'ruby-noir': '/salesforcebackground/rubynoir_dark.png',
    'amber-industrial': '/salesforcebackground/amberindustrial_dark.png',
    'cyber-lime': '/salesforcebackground/cyberlime_dark.png',
    'royal-indigo': '/salesforcebackground/royalindigo_dark.png',
    'terracotta-modern': '/salesforcebackground/terracotamodern_dark.png',
    'mint-frost': '/salesforcebackground/mintfrost_dark.png',
    'mono-cobalt': '/salesforcebackground/cobaltmono_dark.png',
};

const LIGHT_BACKGROUND_BY_PALETTE = {
    'salesforce-signature': '/salesforcebackground/salesforcesignature_light.png',
    'ocean-tech': '/salesforcebackground/oceantech_light.png',
    'emerald-corporate': '/salesforcebackground/emeraldcorporate_light.png',
    'graphite-clean': '/salesforcebackground/graphiteclean_light.png',
    'sunset-luxe': '/salesforcebackground/sunsetluxe_light.png',
    'nordic-ice': '/salesforcebackground/nordicice_light.png',
    'ruby-noir': '/salesforcebackground/rubynoir_light.png',
    'amber-industrial': '/salesforcebackground/amberindustrial_light.png',
    'cyber-lime': '/salesforcebackground/cyberlime_light.png',
    'royal-indigo': '/salesforcebackground/royalindigo_light.png',
    'terracotta-modern': '/salesforcebackground/terracotamodern_light.png',
    'mint-frost': '/salesforcebackground/mintfrost_light.png',
    'mono-cobalt': '/salesforcebackground/cobaltmono_light.png',
};

const PRIMARY_COLOR_BY_PALETTE = {
    'salesforce-signature': '#22c983',
    'ocean-tech': '#1f86f1',
    'emerald-corporate': '#1daa72',
    'graphite-clean': '#9d73ff',
    'sunset-luxe': '#d87756',
    'nordic-ice': '#3d96eb',
    'ruby-noir': '#cc5170',
    'amber-industrial': '#d49b40',
    'cyber-lime': '#7eb234',
    'royal-indigo': '#5e6af2',
    'terracotta-modern': '#c18057',
    'mint-frost': '#2aa995',
    'mono-cobalt': '#3f76d8',
};

function resolvePaletteBackground(paletteId) {
    const activeThemeBackgrounds = theme.value === 'light' ? LIGHT_BACKGROUND_BY_PALETTE : DARK_BACKGROUND_BY_PALETTE;
    return activeThemeBackgrounds[paletteId];
}

function paletteHasBackground(paletteId) {
    return Boolean(resolvePaletteBackground(paletteId));
}

function paletteBackgroundStatusLabel(paletteId) {
    const themeLabel = theme.value === 'light' ? 'BG claro' : 'BG escuro';
    return paletteHasBackground(paletteId) ? `${themeLabel} disponível` : `${themeLabel} pendente`;
}

function palettePrimaryColor(paletteId) {
    return PRIMARY_COLOR_BY_PALETTE[paletteId] || '#22c983';
}

function palettePreviewStyle(option) {
    const image = resolvePaletteBackground(option.id);
    const fallback = option.preview || 'linear-gradient(140deg, #141d2d 0%, #233552 48%, #141d2d 100%)';

    if (!image) {
        return { background: fallback };
    }

    const overlay =
        theme.value === 'light'
            ? 'linear-gradient(130deg, rgb(243 246 251 / 0.22), rgb(243 246 251 / 0.46))'
            : 'linear-gradient(130deg, rgb(4 8 16 / 0.38), rgb(4 8 16 / 0.58))';

    return {
        backgroundImage: `${overlay}, url("${image}")`,
        backgroundSize: 'cover',
        backgroundPosition: 'center',
    };
}
</script>

<template>
    <div class="space-y-4">
        <SettingsPageHeader
            title="Tema"
            subtitle="Personalize o visual da retaguarda com modo de exibição e assinatura de cores."
        />

        <AppCard elevated>
            <div class="flex flex-wrap items-center gap-2">
                <AppBadge variant="default">Modo: {{ activeModeLabel }}</AppBadge>
                <AppBadge variant="default">Assinatura: {{ activePaletteLabel }}</AppBadge>
                <AppBadge variant="success">Aplicação imediata</AppBadge>
            </div>
            <p class="text-sm text-muted mt-3">
                O modo atual renderizado é <strong class="text-main">{{ theme === 'dark' ? 'escuro' : 'claro' }}</strong>.
            </p>
        </AppCard>

        <AppCard elevated>
            <div class="mb-3">
                <h2 class="ui-section-title">Modo de exibição</h2>
                <p class="ui-section-subtitle">Escolha entre seguir o sistema, forçar claro ou forçar escuro.</p>
            </div>

            <div class="theme-mode-grid">
                <button
                    v-for="option in availableModes"
                    :key="option.id"
                    type="button"
                    class="theme-option-card"
                    :class="{ 'is-active': option.id === mode }"
                    @click="setThemeMode(option.id)"
                >
                    <div class="flex items-center justify-between gap-2">
                        <p class="font-bold text-main">{{ option.label }}</p>
                        <AppBadge v-if="option.id === mode" variant="success">Ativo</AppBadge>
                    </div>
                    <p class="text-sm text-muted mt-1">{{ option.description }}</p>
                </button>
            </div>
        </AppCard>

        <AppCard elevated>
            <div class="mb-3">
                <h2 class="ui-section-title">Assinatura visual</h2>
                <p class="ui-section-subtitle">Selecione a paleta principal para botões, destaques e elementos ativos.</p>
            </div>

            <div class="theme-palette-grid">
                <button
                    v-for="option in availablePalettes"
                    :key="option.id"
                    type="button"
                    class="theme-option-card theme-palette-card"
                    :class="{ 'is-active': option.id === palette }"
                    @click="setPalette(option.id)"
                >
                    <div class="theme-preview" :style="palettePreviewStyle(option)">
                        <span
                            class="theme-bg-status"
                            :class="paletteHasBackground(option.id) ? 'is-ready' : 'is-pending'"
                        >
                            {{ paletteBackgroundStatusLabel(option.id) }}
                        </span>
                    </div>

                    <div class="p-4 pt-3">
                        <div class="flex items-center justify-between gap-2">
                            <p class="font-bold text-main">{{ option.label }}</p>
                            <AppBadge v-if="option.id === palette" variant="success">Ativo</AppBadge>
                        </div>
                        <p class="text-sm text-muted mt-1">{{ option.description }}</p>

                        <div class="theme-color-pill mt-3">
                            <span class="theme-color-dot" :style="{ backgroundColor: palettePrimaryColor(option.id) }" />
                            <span>Cor principal: {{ palettePrimaryColor(option.id).toUpperCase() }}</span>
                        </div>
                    </div>
                </button>
            </div>
        </AppCard>
    </div>
</template>

<style scoped>
.theme-mode-grid {
    display: grid;
    gap: var(--space-3);
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}

.theme-palette-grid {
    display: grid;
    gap: var(--space-4);
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
}

.theme-option-card {
    width: 100%;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    background: var(--color-bg-surface);
    text-align: left;
    overflow: hidden;
    transition: border-color var(--transition-fast), background var(--transition-fast), transform var(--transition-fast);
}

.theme-option-card:hover {
    border-color: var(--color-border-strong);
    transform: translateY(-1px);
}

.theme-option-card.is-active {
    border-color: color-mix(in srgb, var(--color-primary) 64%, var(--color-border));
    background: color-mix(in srgb, var(--color-primary) 11%, var(--color-bg-surface));
}

.theme-option-card:not(.theme-palette-card) {
    padding: var(--space-4);
}

.theme-preview {
    position: relative;
    height: 120px;
    display: flex;
    align-items: flex-end;
    padding: 0.65rem;
    border-bottom: 1px solid color-mix(in srgb, var(--color-border) 65%, transparent);
}

.theme-bg-status {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    border: 1px solid transparent;
    padding: 0.15rem 0.55rem;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.01em;
    backdrop-filter: blur(4px);
}

.theme-bg-status.is-ready {
    background: rgb(5 46 22 / 0.55);
    border-color: rgb(34 197 94 / 0.6);
    color: #dcfce7;
}

.theme-bg-status.is-pending {
    background: rgb(71 85 105 / 0.52);
    border-color: rgb(148 163 184 / 0.48);
    color: #e2e8f0;
}

.theme-color-pill {
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

.theme-color-dot {
    width: 0.75rem;
    height: 0.75rem;
    border-radius: 999px;
    border: 1px solid rgb(255 255 255 / 0.45);
    box-shadow: 0 0 0 1px rgb(15 23 42 / 0.24);
}
</style>
