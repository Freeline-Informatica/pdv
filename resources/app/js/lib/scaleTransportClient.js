const DIRECT_UNSUPPORTED_MESSAGE = 'Leitura direta da balança não está disponível neste navegador.';

async function fetchWithTimeout(url, options = {}, timeoutMs = 2000) {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), timeoutMs);

    try {
        return await fetch(url, {
            ...options,
            signal: controller.signal,
        });
    } finally {
        clearTimeout(timeout);
    }
}

function normalizeBaseUrl(value) {
    const raw = String(value || '').trim();
    return raw ? raw.replace(/\/+$/, '') : null;
}

class DirectScaleClient {
    constructor() {
        this.listeners = new Set();
        this.state = {
            status: 'unsupported',
            transport: 'direct',
            label: 'Balanca local',
            browserSupported: false,
            lastError: DIRECT_UNSUPPORTED_MESSAGE,
            mode: 'direct',
        };
    }

    configure() {
        this.setState({
            status: 'unsupported',
            label: 'Balanca local',
            lastError: DIRECT_UNSUPPORTED_MESSAGE,
        });
    }

    subscribe(listener) {
        this.listeners.add(listener);
        listener(this.state);

        return () => {
            this.listeners.delete(listener);
        };
    }

    getState() {
        return this.state;
    }

    async hydrateGrantedDevice() {
        this.configure();
    }

    async requestConnection() {
        this.configure();
        return this.state;
    }

    async read() {
        throw new Error(DIRECT_UNSUPPORTED_MESSAGE);
    }

    async disconnect() {
        this.configure();
    }

    setState(next) {
        this.state = {
            ...this.state,
            ...next,
            transport: 'direct',
            mode: 'direct',
            browserSupported: false,
        };

        for (const listener of this.listeners) {
            listener(this.state);
        }
    }
}

class BridgeScaleClient {
    constructor() {
        this.listeners = new Set();
        this.bridgeBaseUrl = null;
        this.bridgeDeviceId = null;
        this.state = {
            status: 'disconnected',
            transport: 'network',
            label: null,
            browserSupported: true,
            lastError: null,
            mode: 'network',
        };
    }

    configure(config = {}) {
        this.bridgeBaseUrl = normalizeBaseUrl(config.bridgeBaseUrl || config.bridge_base_url);
        this.bridgeDeviceId = String(config.bridgeDeviceId || config.bridge_device_id || '').trim() || null;

        const hasBridgeConfig = Boolean(this.bridgeBaseUrl && this.bridgeDeviceId);

        this.setState({
            status: hasBridgeConfig ? 'disconnected' : 'error',
            label: hasBridgeConfig
                ? `Bridge ${this.bridgeBaseUrl} (${this.bridgeDeviceId})`
                : 'Bridge da balança não configurado',
            lastError: hasBridgeConfig
                ? null
                : 'Configure URL base e device ID da balança em rede neste terminal.',
        });
    }

    subscribe(listener) {
        this.listeners.add(listener);
        listener(this.state);

        return () => {
            this.listeners.delete(listener);
        };
    }

    getState() {
        return this.state;
    }

    async hydrateGrantedDevice() {
        if (!this.bridgeBaseUrl || !this.bridgeDeviceId) {
            this.setState({
                status: 'error',
                lastError: 'Configure URL base e device ID da balança em rede neste terminal.',
            });
        }
    }

    buildUrl(pathname) {
        if (!this.bridgeBaseUrl) return null;
        return `${this.bridgeBaseUrl}${pathname.startsWith('/') ? '' : '/'}${pathname}`;
    }

    async requestConnection() {
        if (!this.bridgeBaseUrl || !this.bridgeDeviceId) {
            this.setState({
                status: 'error',
                lastError: 'Bridge da balança em rede não configurado.',
            });
            return this.state;
        }

        this.setState({ status: 'connecting', lastError: null });

        try {
            const response = await fetchWithTimeout(this.buildUrl('/health'), {
                method: 'GET',
            }, 2500);

            if (!response.ok) {
                throw new Error(`Bridge indisponível (HTTP ${response.status}).`);
            }

            this.setState({ status: 'connected', lastError: null });
            return this.state;
        } catch (error) {
            this.setState({
                status: 'error',
                lastError: error instanceof Error ? error.message : 'Falha ao conectar no bridge da balança.',
            });
            return this.state;
        }
    }

    async read() {
        if (!this.bridgeBaseUrl || !this.bridgeDeviceId) {
            throw new Error('Bridge da balança em rede não configurado.');
        }

        if (this.state.status !== 'connected' && this.state.status !== 'reading') {
            const state = await this.requestConnection();
            if (state.status !== 'connected') {
                throw new Error(state.lastError || 'Bridge da balança indisponível.');
            }
        }

        this.setState({ status: 'reading', lastError: null });

        try {
            const response = await fetchWithTimeout(this.buildUrl(`/v1/scales/${encodeURIComponent(this.bridgeDeviceId)}/read`), {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                },
            }, 3500);

            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                const message = String(data?.message || `Bridge retornou erro HTTP ${response.status}.`);
                throw new Error(message);
            }

            const payload = await response.json().catch(() => ({}));
            this.setState({ status: 'connected', lastError: null });
            return payload;
        } catch (error) {
            this.setState({
                status: 'error',
                lastError: error instanceof Error ? error.message : 'Falha ao ler peso no bridge da balança.',
            });
            throw error;
        }
    }

    async disconnect() {
        const hasBridgeConfig = Boolean(this.bridgeBaseUrl && this.bridgeDeviceId);

        this.setState({
            status: hasBridgeConfig ? 'disconnected' : 'error',
            lastError: hasBridgeConfig
                ? null
                : 'Bridge da balança em rede não configurado.',
        });
    }

    setState(next) {
        this.state = {
            ...this.state,
            ...next,
            browserSupported: true,
            transport: 'network',
            mode: 'network',
        };

        for (const listener of this.listeners) {
            listener(this.state);
        }
    }
}

export const directScaleClient = new DirectScaleClient();
export const bridgeScaleClient = new BridgeScaleClient();

export function resolveScaleTransportClient(config = {}) {
    if (String(config?.mode || '').toLowerCase() === 'network') {
        bridgeScaleClient.configure(config);
        return bridgeScaleClient;
    }

    directScaleClient.configure(config);
    return directScaleClient;
}

export async function readScaleMeasurement(config = {}) {
    const client = resolveScaleTransportClient(config);
    return client.read();
}
