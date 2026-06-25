const VALID_CONNECTION_MODES = new Set(['direct', 'network']);

export function normalizeConnectionMode(value) {
    const normalized = String(value || '').trim().toLowerCase();
    return VALID_CONNECTION_MODES.has(normalized) ? normalized : 'direct';
}

export function normalizeBridgeBaseUrl(value) {
    const raw = String(value || '').trim();
    if (!raw) return null;

    try {
        const parsed = new URL(raw);
        if (!['http:', 'https:'].includes(parsed.protocol)) {
            return null;
        }

        const pathname = parsed.pathname.replace(/\/+$/, '');
        const suffix = pathname && pathname !== '/' ? pathname : '';

        return `${parsed.protocol}//${parsed.host}${suffix}`;
    } catch {
        return null;
    }
}

export function normalizeBridgeDeviceId(value) {
    const normalized = String(value || '').trim();
    return normalized || null;
}

export function normalizeDeviceConfig(config) {
    const mode = normalizeConnectionMode(config?.mode ?? config?.connection_mode);

    if (mode === 'direct') {
        return {
            mode: 'direct',
            bridgeBaseUrl: null,
            bridgeDeviceId: null,
        };
    }

    return {
        mode: 'network',
        bridgeBaseUrl: normalizeBridgeBaseUrl(config?.bridgeBaseUrl ?? config?.bridge_base_url),
        bridgeDeviceId: normalizeBridgeDeviceId(config?.bridgeDeviceId ?? config?.bridge_device_id),
    };
}

export function normalizeTerminalDeviceAccess(raw) {
    const source = raw && typeof raw === 'object' ? raw : {};

    const printerSource = source.printer ?? {
        mode: source.printer_connection_mode,
        bridge_base_url: source.printer_bridge_base_url,
        bridge_device_id: source.printer_bridge_device_id,
    };

    const scaleSource = source.scale ?? {
        mode: source.scale_connection_mode,
        bridge_base_url: source.scale_bridge_base_url,
        bridge_device_id: source.scale_bridge_device_id,
    };

    return {
        printer: normalizeDeviceConfig(printerSource),
        scale: normalizeDeviceConfig(scaleSource),
    };
}
