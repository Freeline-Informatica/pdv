import { normalizeTerminalDeviceAccess } from './deviceAccess.js';

export function resolveTerminalDeviceAccess(terminalSession) {
    const source = terminalSession && typeof terminalSession === 'object'
        ? terminalSession
        : {};

    return normalizeTerminalDeviceAccess(source.deviceAccess || source.device_access || source);
}

export function resolvePrinterDeviceConfig(terminalSession) {
    return resolveTerminalDeviceAccess(terminalSession).printer;
}

export function resolveScaleDeviceConfig(terminalSession) {
    return resolveTerminalDeviceAccess(terminalSession).scale;
}

export function resolvePrinterClientKey(terminalSession) {
    return resolvePrinterDeviceConfig(terminalSession).mode === 'network'
        ? 'network'
        : 'direct';
}

export function resolveScaleClientKey(terminalSession) {
    return resolveScaleDeviceConfig(terminalSession).mode === 'network'
        ? 'network'
        : 'direct';
}
