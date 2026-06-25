import { onBeforeUnmount, onMounted } from 'vue';

type ShortcutHandler = (event: KeyboardEvent, key: string) => void;

interface KeyboardShortcutOptions {
    enabled?: () => boolean;
    preventDefault?: boolean;
    stopPropagation?: boolean;
    shouldHandleEvent?: (event: KeyboardEvent, key: string) => boolean;
}

function normalizeKey(event: KeyboardEvent | undefined): string {
    const code = typeof event?.code === 'string' ? event.code : '';
    if (code === 'NumpadAdd') return '+';
    if (code === 'NumpadMultiply') return '*';

    const key = typeof event?.key === 'string' ? event.key : '';
    return key ? key.toLowerCase() : '';
}

export function useKeyboardShortcuts(shortcuts: Record<string, ShortcutHandler>, options: KeyboardShortcutOptions = {}) {
    const handler = (event: KeyboardEvent) => {
        if (event.repeat) return;
        if (event.ctrlKey || event.metaKey || event.altKey) return;
        if (options.enabled && !options.enabled()) return;

        const key = normalizeKey(event);
        if (!key) return;

        const shortcut = shortcuts[key];
        if (!shortcut) return;

        if (options.shouldHandleEvent && !options.shouldHandleEvent(event, key)) return;

        if (options.preventDefault !== false) event.preventDefault();
        if (options.stopPropagation) event.stopPropagation();

        shortcut(event, key);
    };

    onMounted(() => window.addEventListener('keydown', handler));
    onBeforeUnmount(() => window.removeEventListener('keydown', handler));
}
