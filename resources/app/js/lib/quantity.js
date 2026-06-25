const FRACTIONAL_UNITS = new Set(['KG', 'G', 'GR', 'L', 'LT', 'ML', 'M', 'MT', 'M2', 'M3']);
const BRAZILIAN_INTEGER_PATTERN = '(?:\\d+|\\d{1,3}(?:\\.\\d{3})+)';
const NORMALIZED_DECIMAL_PATTERN = /^(\d+)(?:\.(\d+))?$/;

export function normalizeUnit(value) {
    return String(value || 'UN').trim().toUpperCase();
}

export function unitAllowsFractionalQuantity(unit) {
    return FRACTIONAL_UNITS.has(normalizeUnit(unit));
}

function productUnitCandidates(product) {
    return [
        product?.unidade_tributavel,
        product?.tributacao?.unidade_tributavel,
        product?.restaurant_config?.tributacao?.unidade_tributavel,
        product?.unidade_medida?.codigo_fiscal,
        product?.unidadeMedida?.codigo_fiscal,
        product?.unidade,
        product?.unit,
        product?.unidade_medida?.unidade,
        product?.unidadeMedida?.unidade,
    ];
}

export function productAllowsFractionalQuantity(product) {
    if (productUnitCandidates(product).some((unit) => unitAllowsFractionalQuantity(unit))) return true;
    if (typeof product?.permite_fracionamento === 'boolean') return product.permite_fracionamento;
    if (typeof product?.fractional === 'boolean') return product.fractional;

    return false;
}

export function normalizeQuantityText(value) {
    return String(value ?? '').trim().replace(/\s+/g, '').replace(/[^\d,.-]/g, '');
}

function normalizeBrazilianDecimalString(value, { fractional = true, precision = 3 } = {}) {
    const cleaned = normalizeQuantityText(value);
    if (!cleaned || cleaned === '-' || cleaned.startsWith('-')) return '';

    const decimalPart = fractional ? `(?:,(\\d{1,${precision}}))?` : '';
    const pattern = new RegExp(`^${BRAZILIAN_INTEGER_PATTERN}${decimalPart}$`);
    if (!pattern.test(cleaned)) return '';

    return cleaned.replace(/\./g, '').replace(',', '.');
}

function normalizeApiDecimalString(value, { fractional = true, precision = 3 } = {}) {
    const cleaned = normalizeQuantityText(value);
    if (!cleaned || cleaned === '-' || cleaned.startsWith('-')) return '';

    const match = cleaned.match(NORMALIZED_DECIMAL_PATTERN);
    if (!match) return '';
    if (!fractional && match[2]) return '';
    if (match[2] && match[2].length > precision) return '';

    return cleaned;
}

export function normalizeQuantityForApi(value, options = {}) {
    if (typeof value === 'number') {
        const precision = Number.isInteger(options.precision) ? options.precision : 3;
        const fractional = options.fractional !== false;
        if (!Number.isFinite(value) || value < 0) return '';
        if (!fractional && !Number.isInteger(value)) return '';
        return String(Number(value.toFixed(fractional ? precision : 0)));
    }

    return normalizeBrazilianDecimalString(value, options);
}

export function parseQuantityInput(value, { fractional = true, precision = 3 } = {}) {
    if (typeof value === 'number') {
        if (!Number.isFinite(value) || value < 0) return null;
        if (!fractional && !Number.isInteger(value)) return null;
        return Number(value.toFixed(fractional ? precision : 0));
    }

    const normalized = normalizeBrazilianDecimalString(value, { fractional, precision });
    if (!normalized) return null;

    const numeric = Number(normalized);
    if (!Number.isFinite(numeric) || numeric < 0) return null;
    if (!fractional && normalized.includes('.')) return null;

    const decimals = normalized.includes('.') ? normalized.split('.')[1].length : 0;
    if (fractional && decimals > precision) return null;

    return Number(numeric.toFixed(fractional ? precision : 0));
}

export function sanitizeQuantityInput(value, { fractional = true, precision = 3 } = {}) {
    const cleaned = normalizeQuantityText(value);
    if (!cleaned || cleaned.startsWith('-')) return '';

    if (!fractional) return cleaned.replace(/[^\d.]/g, '');

    const hasComma = cleaned.includes(',');
    if (!hasComma) return cleaned.replace(/[^\d.]/g, '');

    const separatorIndex = cleaned.indexOf(',');
    const integerPart = cleaned.slice(0, separatorIndex).replace(/[^\d.]/g, '') || '0';
    const decimalPart = cleaned.slice(separatorIndex + 1).replace(/\D/g, '').slice(0, precision);

    return decimalPart ? `${integerPart},${decimalPart}` : `${integerPart},`;
}

export function formatQuantityInputValue(value, { fractional = true, precision = 3, source = 'ui' } = {}) {
    let numeric = parseQuantityInput(value, { fractional, precision });
    if (numeric == null && source === 'api') {
        numeric = Number(value || 0);
    }
    if (numeric == null) return '';
    if (!Number.isFinite(numeric)) return '';

    return numeric.toLocaleString('pt-BR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: fractional ? precision : 0,
    });
}

export function validateQuantityInput(value, { fractional = true, precision = 3 } = {}) {
    const text = String(value ?? '').trim();
    if (!text) return { valid: false, message: 'Informe uma quantidade válida.' };
    if (text.startsWith('-')) return { valid: false, message: 'A quantidade não pode ser negativa.' };

    const parsed = parseQuantityInput(value, { fractional, precision });
    if (parsed == null) {
        if (!fractional && /,/.test(text)) {
            return { valid: false, message: 'Esta unidade não permite quantidade decimal.' };
        }

        const decimals = String(text.split(',')[1] || '').replace(/\D/g, '').length;
        if (fractional && decimals > precision) {
            return { valid: false, message: 'Quantidade deve ter no máximo 3 casas decimais.' };
        }

        return { valid: false, message: 'Informe a quantidade no padrão brasileiro. Exemplo: 1.000,500.' };
    }

    return { valid: true, value: parsed, message: '' };
}
