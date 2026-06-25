export function base64ToBytes(value) {
    const normalized = String(value || '')
        .replace(/^data:application\/pdf;base64,/, '')
        .replace(/\s+/g, '');

    if (!normalized) {
        return new Uint8Array();
    }

    const binary = window.atob(normalized);
    const bytes = new Uint8Array(binary.length);

    for (let index = 0; index < binary.length; index += 1) {
        bytes[index] = binary.charCodeAt(index);
    }

    return bytes;
}
