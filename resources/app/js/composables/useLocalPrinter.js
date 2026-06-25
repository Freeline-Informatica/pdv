import { onMounted, onUnmounted, ref } from 'vue';
import { getTerminalSession } from '../lib/auth';
import { bridgePrinterClient } from '../lib/bridgePrinterClient';
import { resolvePrinterDeviceConfig } from '../lib/deviceTransportResolver';
import { localPrinterClient } from '../lib/localPrinterClient';

const TERMINAL_SESSION_EVENT = 'pdv:terminal-session-updated';

function resolvePrinterRuntime() {
    const terminalSession = getTerminalSession();
    const config = resolvePrinterDeviceConfig(terminalSession);

    if (config.mode === 'network') {
        bridgePrinterClient.configure({
            bridgeBaseUrl: config.bridgeBaseUrl,
            bridgeDeviceId: config.bridgeDeviceId,
        });

        return {
            client: bridgePrinterClient,
            config,
        };
    }

    return {
        client: localPrinterClient,
        config,
    };
}

export function useLocalPrinter() {
    const printer = ref(localPrinterClient.getState());
    let activeClient = localPrinterClient;
    let activeConfig = { mode: 'direct' };
    let unsubscribe = null;
    let mounted = false;

    function syncClientState() {
        const runtime = resolvePrinterRuntime();
        activeClient = runtime.client;
        activeConfig = runtime.config;

        if (unsubscribe) {
            unsubscribe();
            unsubscribe = null;
        }

        unsubscribe = activeClient.subscribe((state) => {
            printer.value = {
                ...state,
                mode: activeConfig.mode,
            };
        });
    }

    async function hydrate() {
        syncClientState();
        await activeClient.hydrateGrantedDevice();
    }

    async function connect(preferred = 'serial') {
        syncClientState();

        if (activeConfig.mode === 'network') {
            return activeClient.requestConnection();
        }

        return activeClient.requestConnection(preferred);
    }

    async function disconnect() {
        syncClientState();
        return activeClient.disconnect();
    }

    async function print(payload) {
        syncClientState();
        return activeClient.print(payload);
    }

    async function printTestPage() {
        syncClientState();
        return activeClient.printTestPage();
    }

    function handleTerminalSessionUpdate() {
        if (!mounted) return;
        void hydrate();
    }

    onMounted(() => {
        mounted = true;
        void hydrate();
        window.addEventListener(TERMINAL_SESSION_EVENT, handleTerminalSessionUpdate);
    });

    onUnmounted(() => {
        mounted = false;
        window.removeEventListener(TERMINAL_SESSION_EVENT, handleTerminalSessionUpdate);

        if (unsubscribe) {
            unsubscribe();
            unsubscribe = null;
        }
    });

    return {
        printer,
        connect,
        disconnect,
        print,
        printTestPage,
    };
}
