export function parseOperacaoRapida(value) {
    const raw = String(value ?? '').trim();
    if (!raw) return { type: 'empty', raw };

    if (/^\*\s*\d+(?:[.,]\d{1,3})?$/.test(raw)) {
        return {
            type: 'invalid_multiplier',
            raw,
            message: 'Formato inválido. Use 100* para multiplicar a quantidade.',
        };
    }

    const multiplierMatch = raw.match(/^(\d+(?:[.,]\d{1,3})?)\*\s*(.*)$/);
    if (!multiplierMatch) {
        return { type: 'search', raw, term: raw };
    }

    const quantity = Number(multiplierMatch[1].replace(',', '.'));
    if (!Number.isFinite(quantity) || quantity <= 0) {
        return {
            type: 'invalid_multiplier',
            raw,
            message: 'Informe um multiplicador válido.',
        };
    }

    const term = String(multiplierMatch[2] || '').trim();

    return term
        ? { type: 'multiplier_search', raw, quantity, term }
        : { type: 'pending_multiplier', raw, quantity };
}

export function calcularDesconto({ mode = 'value', amount = 0, subtotal = 0 } = {}) {
    const normalizedMode = mode === 'percent' ? 'percent' : 'value';
    const normalizedSubtotal = Math.round((Number(subtotal) || 0) * 100) / 100;
    const normalizedAmount = Number(amount);

    if (!Number.isFinite(normalizedAmount)) {
        return { valid: false, message: 'Informe um valor de desconto válido.' };
    }

    if (normalizedMode === 'percent') {
        if (normalizedAmount < 0) return { valid: false, message: 'Percentual não pode ser menor que 0.' };
        if (normalizedAmount > 100) return { valid: false, message: 'Percentual não pode ser maior que 100.' };

        const discount = Math.round(normalizedSubtotal * normalizedAmount) / 100;

        return {
            valid: true,
            mode: normalizedMode,
            amount: normalizedAmount,
            subtotal: normalizedSubtotal,
            discount,
            total: Math.max(0, Math.round((normalizedSubtotal - discount) * 100) / 100),
        };
    }

    const discount = Math.round(normalizedAmount * 100) / 100;
    if (discount < 0) return { valid: false, message: 'Desconto em valor não pode ser negativo.' };
    if (discount > normalizedSubtotal) return { valid: false, message: 'Desconto em valor não pode ser maior que o subtotal.' };

    return {
        valid: true,
        mode: normalizedMode,
        amount: discount,
        subtotal: normalizedSubtotal,
        discount,
        total: Math.max(0, Math.round((normalizedSubtotal - discount) * 100) / 100),
    };
}
