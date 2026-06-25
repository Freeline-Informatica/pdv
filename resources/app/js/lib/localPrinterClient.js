const STORAGE_KEY = 'freeline.pdv.local_printer.preference';
const textEncoder = new TextEncoder();
const SERIAL_CHUNK_SIZE = 512;
const USB_CHUNK_SIZE = 512;
const SERIAL_CHUNK_DELAY_MS = 15;
const PRINT_SETTLE_MS = 1000;

function delay(ms) {
    return new Promise((resolve) => {
        globalThis.setTimeout(resolve, ms);
    });
}

function hasUsbSupport() {
    return typeof navigator !== 'undefined' && 'usb' in navigator;
}

function hasSerialSupport() {
    return typeof navigator !== 'undefined' && 'serial' in navigator;
}

function supportsBrowserPrinting() {
    return hasUsbSupport() || hasSerialSupport();
}

function readStoredPreference() {
    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

function storePreference(transport, label) {
    try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify({ transport, label }));
    } catch {
        // Local storage can be unavailable in private windows.
    }
}

function buildDefaultState() {
    return {
        status: supportsBrowserPrinting() ? 'disconnected' : 'unsupported',
        transport: null,
        label: readStoredPreference()?.label ?? null,
        browserSupported: supportsBrowserPrinting(),
        supportsUsb: hasUsbSupport(),
        supportsSerial: hasSerialSupport(),
        lastError: null,
    };
}

class LocalPrinterClient {
    constructor() {
        this.state = buildDefaultState();
        this.listeners = new Set();
        this.hydratePromise = null;
        this.usbDevice = null;
        this.usbEndpointNumber = null;
        this.serialPort = null;
        this.serialWriter = null;
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
        if (this.hydratePromise) return this.hydratePromise;

        this.hydratePromise = (async () => {
            if (!supportsBrowserPrinting()) {
                this.setState({ status: 'unsupported' });
                return;
            }

            if (this.state.status === 'connected' || this.state.status === 'printing') {
                return;
            }

            const stored = readStoredPreference();
            const preferred = stored?.transport;

            if (preferred === 'serial' && hasSerialSupport()) {
                const ports = await navigator.serial.getPorts();
                const port = ports[0] ?? null;
                if (port) {
                    await this.attachSerialPort(port);
                    return;
                }
            }

            if (preferred === 'usb' && hasUsbSupport()) {
                const devices = await navigator.usb.getDevices();
                const device = devices[0] ?? null;
                if (device) {
                    await this.attachUsbDevice(device);
                }
            }
        })().finally(() => {
            this.hydratePromise = null;
        });

        return this.hydratePromise;
    }

    async requestConnection(preferred = 'serial') {
        if (!supportsBrowserPrinting()) {
            this.setState({
                status: 'unsupported',
                lastError: 'Este navegador nao suporta WebSerial/WebUSB.',
            });
            return this.state;
        }

        this.setState({ status: 'connecting', lastError: null });

        try {
            if ((preferred === 'serial' || preferred === 'auto') && hasSerialSupport()) {
                try {
                    const port = await navigator.serial.requestPort();
                    await this.attachSerialPort(port);
                    return this.state;
                } catch (error) {
                    if (preferred === 'serial') throw error;
                }
            }

            if ((preferred === 'usb' || preferred === 'auto') && hasUsbSupport()) {
                const device = await navigator.usb.requestDevice({ acceptAllDevices: true });
                await this.attachUsbDevice(device);
                return this.state;
            }

            throw new Error('Nenhum canal de impressão local disponível neste navegador.');
        } catch (error) {
            this.setState({
                status: 'error',
                lastError: error instanceof Error ? error.message : 'Falha ao conectar a impressora local.',
            });
            return this.state;
        }
    }

    async print(payload) {
        if (this.state.status !== 'connected' && this.state.status !== 'printing') {
            throw new Error('Impressora local nao conectada.');
        }

        this.setState({ status: 'printing', lastError: null });

        try {
            if (this.state.transport === 'serial' && this.serialWriter) {
                for (let offset = 0; offset < payload.length; offset += SERIAL_CHUNK_SIZE) {
                    await this.serialWriter.write(payload.slice(offset, offset + SERIAL_CHUNK_SIZE));
                    await this.serialWriter.ready;
                    await delay(SERIAL_CHUNK_DELAY_MS);
                }
            } else if (this.state.transport === 'usb' && this.usbDevice && this.usbEndpointNumber !== null) {
                for (let offset = 0; offset < payload.length; offset += USB_CHUNK_SIZE) {
                    await this.usbDevice.bulkTransferOut(this.usbEndpointNumber, payload.slice(offset, offset + USB_CHUNK_SIZE));
                }
            } else {
                throw new Error('Canal de impressão indisponível.');
            }

            await delay(PRINT_SETTLE_MS);
            this.setState({ status: 'connected', lastError: null });
        } catch (error) {
            this.setState({
                status: 'error',
                lastError: error instanceof Error ? error.message : 'Falha ao enviar ESC/POS para a impressora.',
            });
            throw error;
        }
    }

    async printTestPage() {
        const payload = textEncoder.encode('\x1B@\x1Ba\x01FREELINE PDV\n\x1Ba\x00TESTE DE INTEGRACAO ESC/POS\nSABBA SISTEMAS LTDA\n\n\x1Bd\x04\x1DV\x00');
        await this.print(payload);
    }

    async disconnect() {
        try {
            if (this.serialWriter) await this.serialWriter.close();
        } catch {
            // ignore close failures
        }

        try {
            this.serialWriter?.releaseLock();
        } catch {
            // ignore stale lock
        }

        this.serialWriter = null;

        try {
            if (this.serialPort) await this.serialPort.close();
        } catch {
            // ignore close failures
        }

        this.serialPort = null;

        try {
            if (this.usbDevice?.opened) await this.usbDevice.close();
        } catch {
            // ignore close failures
        }

        this.usbDevice = null;
        this.usbEndpointNumber = null;

        this.setState({
            status: supportsBrowserPrinting() ? 'disconnected' : 'unsupported',
            transport: null,
            label: readStoredPreference()?.label ?? null,
            lastError: null,
        });
    }

    async attachSerialPort(port) {
        if (this.serialPort === port && this.serialWriter) {
            this.setState({ status: 'connected', transport: 'serial', lastError: null });
            return;
        }

        try {
            await port.open({ baudRate: 115200 });
        } catch (error) {
            const isAlreadyOpen = error instanceof DOMException && error.name === 'InvalidStateError';
            if (!isAlreadyOpen) throw error;
        }

        if (!port.writable) {
            throw new Error('Porta serial sem canal de escrita disponível.');
        }

        if (port.writable.locked && this.serialPort === port && this.serialWriter) {
            this.setState({ status: 'connected', transport: 'serial', lastError: null });
            return;
        }

        if (port.writable.locked) {
            throw new Error('A porta serial ja esta em uso. Desconecte a impressora e conecte novamente.');
        }

        try {
            this.serialWriter?.releaseLock();
        } catch {
            // ignore stale lock
        }

        this.serialPort = port;
        this.serialWriter = port.writable.getWriter();
        this.usbDevice = null;
        this.usbEndpointNumber = null;

        const info = port.getInfo?.() ?? {};
        const label = info.usbVendorId || info.usbProductId
            ? `Serial ${info.usbVendorId ?? ''}:${info.usbProductId ?? ''}`.trim()
            : 'Impressora Serial';

        storePreference('serial', label);
        this.setState({ status: 'connected', transport: 'serial', label, lastError: null });
    }

    async attachUsbDevice(device) {
        await device.open();

        if (!device.configuration) {
            await device.selectConfiguration(1);
        }

        const outInterface = device.configuration?.interfaces?.find((iface) =>
            iface.alternates?.some((alternate) =>
                alternate.endpoints?.some((endpoint) => endpoint.direction === 'out'),
            ),
        );

        if (!outInterface) {
            throw new Error('Interface USB de saida nao encontrada para a impressora.');
        }

        const alternate = outInterface.alternates?.find((candidate) =>
            candidate.endpoints?.some((endpoint) => endpoint.direction === 'out'),
        );

        if (!alternate) {
            throw new Error('Endpoint USB de saida nao encontrado para a impressora.');
        }

        await device.claimInterface(outInterface.interfaceNumber);
        await device.selectAlternateInterface(outInterface.interfaceNumber, alternate.alternateSetting);

        const outEndpoint = alternate.endpoints?.find((endpoint) => endpoint.direction === 'out');
        if (!outEndpoint) {
            throw new Error('Endpoint USB OUT ausente na impressora.');
        }

        this.usbDevice = device;
        this.usbEndpointNumber = outEndpoint.endpointNumber;
        this.serialPort = null;
        this.serialWriter = null;

        const label = [device.manufacturerName, device.productName].filter(Boolean).join(' ').trim() || 'Impressora USB';
        storePreference('usb', label);
        this.setState({ status: 'connected', transport: 'usb', label, lastError: null });
    }

    setState(next) {
        this.state = {
            ...this.state,
            ...next,
            browserSupported: supportsBrowserPrinting(),
            supportsUsb: hasUsbSupport(),
            supportsSerial: hasSerialSupport(),
        };

        for (const listener of this.listeners) {
            listener(this.state);
        }
    }
}

export const localPrinterClient = new LocalPrinterClient();
