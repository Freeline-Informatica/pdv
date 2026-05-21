import { computed, ref } from 'vue';

export type Theme = 'light' | 'dark';
export type ThemeMode = 'system' | Theme;
export type ThemePalette =
    | 'salesforce-signature'
    | 'ocean-tech'
    | 'emerald-corporate'
    | 'graphite-clean'
    | 'sunset-luxe'
    | 'nordic-ice'
    | 'ruby-noir'
    | 'amber-industrial'
    | 'cyber-lime'
    | 'royal-indigo'
    | 'terracotta-modern'
    | 'mint-frost'
    | 'mono-cobalt';

export const THEME_MODE_OPTIONS = [
    { id: 'system', label: 'Sistema', description: 'Segue automaticamente o tema do sistema operacional.' },
    { id: 'light', label: 'Claro', description: 'Mantém o backoffice sempre no modo claro.' },
    { id: 'dark', label: 'Escuro', description: 'Mantém o backoffice sempre no modo escuro.' },
] as const;

export const THEME_PALETTE_OPTIONS = [
    {
        id: 'salesforce-signature',
        label: 'Salesforce Signature',
        description: 'Paleta padrão premium atual.',
        preview: '',
    },
    {
        id: 'ocean-tech',
        label: 'Ocean Tech',
        description: 'Azul tecnológico com contraste limpo.',
        preview: 'linear-gradient(135deg, #021837 0%, #0f3b68 48%, #07202f 100%)',
    },
    {
        id: 'emerald-corporate',
        label: 'Emerald Corporate',
        description: 'Tom corporativo com acento esmeralda.',
        preview: 'linear-gradient(140deg, #04231d 0%, #0d4e3f 50%, #081c18 100%)',
    },
    {
        id: 'graphite-clean',
        label: 'Graphite Clean',
        description: 'Visual sóbrio com acento magenta.',
        preview: 'linear-gradient(140deg, #171920 0%, #2a2034 52%, #171923 100%)',
    },
    {
        id: 'sunset-luxe',
        label: 'Sunset Luxe',
        description: 'Roxo quente com energia de pôr do sol.',
        preview: 'linear-gradient(140deg, #2f1618 0%, #4a2327 45%, #2d1c30 100%)',
    },
    {
        id: 'nordic-ice',
        label: 'Nordic Ice',
        description: 'Paleta fria e clean com azul polar.',
        preview: 'linear-gradient(140deg, #162235 0%, #1a3f6a 52%, #10212f 100%)',
    },
    {
        id: 'ruby-noir',
        label: 'Ruby Noir',
        description: 'Dark elegante com acentos rubi.',
        preview: 'linear-gradient(140deg, #2d0f1a 0%, #511a2d 52%, #230f17 100%)',
    },
    {
        id: 'amber-industrial',
        label: 'Amber Industrial',
        description: 'Âmbar e grafite com pegada industrial premium.',
        preview: 'linear-gradient(140deg, #2c2418 0%, #5a4625 48%, #272013 100%)',
    },
    {
        id: 'cyber-lime',
        label: 'Cyber Lime',
        description: 'Tecnológico vibrante com verde limão controlado.',
        preview: 'linear-gradient(140deg, #132618 0%, #2c5825 50%, #11241a 100%)',
    },
    {
        id: 'royal-indigo',
        label: 'Royal Indigo',
        description: 'Azul royal com contraste premium institucional.',
        preview: 'linear-gradient(140deg, #121b3a 0%, #272f66 50%, #101835 100%)',
    },
    {
        id: 'terracotta-modern',
        label: 'Terracotta Modern',
        description: 'Terracota sofisticado com neutros modernos.',
        preview: 'linear-gradient(140deg, #30221d 0%, #584035 48%, #2a1f19 100%)',
    },
    {
        id: 'mint-frost',
        label: 'Mint Frost',
        description: 'Verde menta claro com sensação clean e leve.',
        preview: 'linear-gradient(140deg, #18302d 0%, #2f6560 48%, #112625 100%)',
    },
    {
        id: 'mono-cobalt',
        label: 'Mono Cobalt',
        description: 'Escala monocromática azul para visual corporativo.',
        preview: 'linear-gradient(140deg, #132039 0%, #244279 48%, #111b31 100%)',
    },
] as const;

const LEGACY_THEME_STORAGE_KEY = 'simples-pdv-theme';
const THEME_MODE_STORAGE_KEY = 'simples-pdv-theme-mode';
const THEME_PALETTE_STORAGE_KEY = 'simples-pdv-theme-palette';
const DEFAULT_THEME_MODE: ThemeMode = 'system';
const DEFAULT_THEME_PALETTE: ThemePalette = 'salesforce-signature';
const VALID_PALETTES = new Set(THEME_PALETTE_OPTIONS.map((item) => item.id));

const themeRef = ref<Theme>('light');
const modeRef = ref<ThemeMode>('system');
const paletteRef = ref<ThemePalette>('salesforce-signature');
let initialized = false;
let systemThemeMedia: MediaQueryList | null = null;
let systemThemeBound = false;

function isValidMode(mode: string | null): mode is ThemeMode {
    return mode === 'system' || mode === 'light' || mode === 'dark';
}

function isValidTheme(theme: string | null): theme is Theme {
    return theme === 'light' || theme === 'dark';
}

function isValidPalette(palette: string | null): palette is ThemePalette {
    return !!palette && VALID_PALETTES.has(palette as ThemePalette);
}

function resolveSystemTheme(): Theme {
    if (typeof window === 'undefined') return 'light';
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function resolveThemeFromMode(mode: ThemeMode): Theme {
    return mode === 'system' ? resolveSystemTheme() : mode;
}

function applyTheme(theme: Theme, mode: ThemeMode, palette: ThemePalette) {
    if (typeof document === 'undefined') return;
    document.documentElement.setAttribute('data-theme', theme);
    document.documentElement.setAttribute('data-theme-mode', mode);
    document.documentElement.setAttribute('data-theme-palette', palette);
}

function resolveMode(): ThemeMode {
    if (typeof window === 'undefined') return DEFAULT_THEME_MODE;

    const persistedMode = window.localStorage.getItem(THEME_MODE_STORAGE_KEY);
    if (isValidMode(persistedMode)) {
        return persistedMode;
    }

    const legacyPersisted = window.localStorage.getItem(LEGACY_THEME_STORAGE_KEY);
    if (isValidTheme(legacyPersisted)) {
        return legacyPersisted;
    }

    return DEFAULT_THEME_MODE;
}

function resolvePalette(): ThemePalette {
    if (typeof window === 'undefined') return DEFAULT_THEME_PALETTE;

    const persisted = window.localStorage.getItem(THEME_PALETTE_STORAGE_KEY);
    if (isValidPalette(persisted)) {
        return persisted;
    }

    return DEFAULT_THEME_PALETTE;
}

function persistMode(mode: ThemeMode) {
    if (typeof window === 'undefined') return;

    window.localStorage.setItem(THEME_MODE_STORAGE_KEY, mode);
    if (mode === 'system') {
        window.localStorage.removeItem(LEGACY_THEME_STORAGE_KEY);
        return;
    }

    window.localStorage.setItem(LEGACY_THEME_STORAGE_KEY, mode);
}

function persistPalette(palette: ThemePalette) {
    if (typeof window === 'undefined') return;
    window.localStorage.setItem(THEME_PALETTE_STORAGE_KEY, palette);
}

function syncThemeFromSystem() {
    if (modeRef.value !== 'system') return;
    themeRef.value = resolveSystemTheme();
    applyTheme(themeRef.value, modeRef.value, paletteRef.value);
}

function bindSystemThemeListener() {
    if (typeof window === 'undefined' || systemThemeBound) return;
    systemThemeMedia = window.matchMedia('(prefers-color-scheme: dark)');

    if (typeof systemThemeMedia.addEventListener === 'function') {
        systemThemeMedia.addEventListener('change', syncThemeFromSystem);
    } else if (typeof systemThemeMedia.addListener === 'function') {
        systemThemeMedia.addListener(syncThemeFromSystem);
    }

    systemThemeBound = true;
}

export function initTheme() {
    if (initialized) return;
    modeRef.value = resolveMode();
    paletteRef.value = resolvePalette();
    themeRef.value = resolveThemeFromMode(modeRef.value);
    applyTheme(themeRef.value, modeRef.value, paletteRef.value);
    bindSystemThemeListener();
    initialized = true;
}

export function useTheme() {
    if (!initialized) {
        initTheme();
    }

    const setThemeMode = (nextMode: ThemeMode) => {
        if (!isValidMode(nextMode)) return;
        modeRef.value = nextMode;
        themeRef.value = resolveThemeFromMode(nextMode);
        applyTheme(themeRef.value, modeRef.value, paletteRef.value);
        persistMode(nextMode);
    };

    const setTheme = (nextTheme: Theme) => {
        setThemeMode(nextTheme);
    };

    const setPalette = (nextPalette: ThemePalette) => {
        if (!isValidPalette(nextPalette)) return;
        paletteRef.value = nextPalette;
        applyTheme(themeRef.value, modeRef.value, paletteRef.value);
        persistPalette(nextPalette);
    };

    const toggleTheme = () => {
        setThemeMode(themeRef.value === 'light' ? 'dark' : 'light');
    };

    return {
        theme: computed(() => themeRef.value),
        mode: computed(() => modeRef.value),
        palette: computed(() => paletteRef.value),
        availableModes: computed(() => THEME_MODE_OPTIONS),
        availablePalettes: computed(() => THEME_PALETTE_OPTIONS),
        setTheme,
        setThemeMode,
        setPalette,
        toggleTheme,
        isDark: computed(() => themeRef.value === 'dark'),
        isSystem: computed(() => modeRef.value === 'system'),
    };
}
