const textEncoder = new TextEncoder();
const DEFAULT_PRINT_JOB_TIMEOUT_MS = 60000;
const PRINT_JOB_POLL_INTERVAL_MS = 500;

function delay(ms) {
    return new Promise((resolve) => {
        globalThis.setTimeout(resolve, ms);
    });
}

function encodeBase64(bytes) {
    if (!bytes) return '';

    if (typeof bytes.toBase64 === 'function') {
        return bytes.toBase64();
    }

    let binary = '';
    const chunkSize = 0x8000;

    for (let offset = 0; offset < bytes.length; offset += chunkSize) {
        const chunk = bytes.subarray(offset, offset + chunkSize);
        binary += String.fromCharCode(...chunk);
    }

    return btoa(binary);
}

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

async function responseErrorMessage(response, fallbackMessage) {
    const data = await response.json().catch(() => ({}));
    const detail = data?.detail;

    if (Array.isArray(detail)) {
        return detail.map((item) => item?.msg || item?.message || JSON.stringify(item)).join(' ');
    }

    return String(data?.message || detail || fallbackMessage);
}

function normalizeBaseUrl(value) {
    const raw = String(value || '').trim();
    return raw ? raw.replace(/\/+$/, '') : null;
}

class BridgePrinterClient {
    constructor() {
        this.listeners = new Set();
        this.bridgeBaseUrl = null;
        this.bridgeDeviceId = null;
        this.state = {
            status: 'disconnected',
            transport: 'network',
            label: null,
            browserSupported: true,
            supportsUsb: false,
            supportsSerial: false,
            lastError: null,
            mode: 'network',
        };
    }

    configure(config = {}) {
        this.bridgeBaseUrl = normalizeBaseUrl(config.bridgeBaseUrl || config.bridge_base_url);
        this.bridgeDeviceId = String(config.bridgeDeviceId || config.bridge_device_id || '').trim() || null;

        const hasBridgeConfig = Boolean(this.bridgeBaseUrl && this.bridgeDeviceId);
        const label = hasBridgeConfig
            ? `Bridge ${this.bridgeBaseUrl} (${this.bridgeDeviceId})`
            : 'Bridge de impressão não configurado';

        this.setState({
            status: hasBridgeConfig ? 'disconnected' : 'error',
            label,
            lastError: hasBridgeConfig
                ? null
                : 'Configure URL base e device ID da impressora em rede neste terminal.',
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
                lastError: 'Configure URL base e device ID da impressora em rede neste terminal.',
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
                lastError: 'Bridge da impressora em rede não configurado.',
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
                lastError: error instanceof Error ? error.message : 'Falha ao conectar no bridge da impressora.',
            });
            return this.state;
        }
    }

    async print(payload) {
        if (!this.bridgeBaseUrl || !this.bridgeDeviceId) {
            throw new Error('Bridge da impressora em rede não configurado.');
        }

        if (this.state.status !== 'connected' && this.state.status !== 'printing') {
            const state = await this.requestConnection();
            if (state.status !== 'connected') {
                throw new Error(state.lastError || 'Bridge da impressora indisponível.');
            }
        }

        this.setState({ status: 'printing', lastError: null });

        try {
            const response = await fetchWithTimeout(this.buildUrl(`/v1/printers/${encodeURIComponent(this.bridgeDeviceId)}/jobs`), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    payload_base64: encodeBase64(payload),
                    content_type: 'escpos_raw',
                }),
            }, 15000);

            if (!response.ok) {
                throw new Error(await responseErrorMessage(response, `Bridge retornou erro HTTP ${response.status}.`));
            }

            const data = await response.json().catch(() => ({}));
            await this.waitForPrintJob(data?.job_id, DEFAULT_PRINT_JOB_TIMEOUT_MS);
            this.setState({ status: 'connected', lastError: null });
        } catch (error) {
            this.setState({
                status: 'error',
                lastError: error instanceof Error ? error.message : 'Falha ao enviar ESC/POS para o bridge.',
            });
            throw error;
        }
    }

    async waitForPrintJob(jobId, timeoutMs = DEFAULT_PRINT_JOB_TIMEOUT_MS) {
        const normalizedJobId = String(jobId || '').trim();
        if (!normalizedJobId) {
            throw new Error('Bridge não retornou o identificador do job de impressão.');
        }

        const deadline = Date.now() + Math.max(1, Number(timeoutMs || DEFAULT_PRINT_JOB_TIMEOUT_MS));
        let lastMessage = 'Aguardando confirmação do bridge.';

        while (Date.now() <= deadline) {
            const response = await fetchWithTimeout(
                this.buildUrl(`/v1/printers/${encodeURIComponent(this.bridgeDeviceId)}/jobs/${encodeURIComponent(normalizedJobId)}`),
                { method: 'GET' },
                2500,
            );

            if (!response.ok) {
                throw new Error(await responseErrorMessage(response, `Bridge retornou erro HTTP ${response.status} ao consultar o job.`));
            }

            const data = await response.json().catch(() => ({}));
            const status = String(data?.status || '').trim().toLowerCase();
            lastMessage = String(data?.message || lastMessage);

            if (status === 'printed') {
                return data;
            }

            if (status === 'failed') {
                throw new Error(lastMessage || 'Bridge falhou ao imprimir.');
            }

            const remainingMs = deadline - Date.now();
            if (remainingMs <= 0) {
                break;
            }

            await delay(Math.min(PRINT_JOB_POLL_INTERVAL_MS, remainingMs));
        }

        throw new Error(`Tempo limite aguardando confirmação do bridge: ${lastMessage}`);
    }

    async printTestPage() {
        const payload = textEncoder.encode('\x1B@\x1Ba\x01FREELINE PDV\n\x1Ba\x00TESTE DE INTEGRACAO BRIDGE\nSABBA SISTEMAS LTDA\n\n\x1Bd\x04\x1DV\x00');
        await this.print(payload);
    }

    async disconnect() {
        const hasBridgeConfig = Boolean(this.bridgeBaseUrl && this.bridgeDeviceId);

        this.setState({
            status: hasBridgeConfig ? 'disconnected' : 'error',
            lastError: hasBridgeConfig
                ? null
                : 'Bridge da impressora em rede não configurado.',
        });
    }

    setState(next) {
        this.state = {
            ...this.state,
            ...next,
            browserSupported: true,
            supportsUsb: false,
            supportsSerial: false,
            transport: 'network',
            mode: 'network',
        };

        for (const listener of this.listeners) {
            listener(this.state);
        }
    }
}

export const bridgePrinterClient = new BridgePrinterClient();
