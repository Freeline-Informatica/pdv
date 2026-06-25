export const GTIN_LENGTHS = {
    'GTIN-13': 13,
    'GTIN-14': 14,
    'EAN-8': 8,
};

export function normalizeGtinType(type) {
    const normalized = String(type || '').trim().toUpperCase();
    return normalized || 'GTIN-13';
}

export function getGtinLength(type) {
    return GTIN_LENGTHS[normalizeGtinType(type)] || null;
}

export function sanitizeGtin(value, maxLength = null) {
    const digits = String(value || '').replace(/\D+/g, '');
    return maxLength ? digits.slice(0, maxLength) : digits;
}

export function validateGtin(value, type, { required = true } = {}) {
    const normalizedType = normalizeGtinType(type);
    const digits = sanitizeGtin(value);
    const expectedLength = getGtinLength(normalizedType);

    if (!digits) {
        return {
            valid: !required,
            digits,
            message: required ? `${normalizedType} deve conter exatamente ${expectedLength || 0} dígitos.` : '',
        };
    }

    if (!expectedLength) {
        return { valid: false, digits, message: 'Tipo de código de barras inválido.' };
    }

    if (digits.length !== expectedLength) {
        return {
            valid: false,
            digits,
            message: `${normalizedType} deve conter exatamente ${expectedLength} dígitos.`,
        };
    }

    let sum = 0;
    let factor = 3;

    for (let index = digits.length - 2; index >= 0; index -= 1) {
        sum += Number(digits[index]) * factor;
        factor = factor === 3 ? 1 : 3;
    }

    const calculated = (10 - (sum % 10)) % 10;
    const valid = Number(digits[digits.length - 1]) === calculated;

    return {
        valid,
        digits,
        message: valid ? '' : 'Código de barras inválido. Verifique o dígito verificador.',
    };
}
