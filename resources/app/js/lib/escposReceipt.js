const encoder = new TextEncoder();

function stripAccents(value) {
    return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}

function normalizeText(value) {
    return stripAccents(value).replace(/[^\x20-\x7E\n]/g, '');
}

function toMoney(value) {
    return Number(value || 0).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function parseNumber(value) {
    const parsed = Number(value || 0);
    return Number.isFinite(parsed) ? parsed : 0;
}

function line(text = '') {
    return encoder.encode(`${normalizeText(text)}\n`);
}

function command(...bytes) {
    return Uint8Array.from(bytes);
}

function align(value) {
    return command(0x1b, 0x61, value);
}

function bold(enabled) {
    return command(0x1b, 0x45, enabled ? 1 : 0);
}

function printAndFeed(lines = 1) {
    return command(0x1b, 0x64, Math.max(0, Math.min(255, Math.round(Number(lines || 0)))));
}

function concatBytes(parts) {
    const total = parts.reduce((sum, part) => sum + part.length, 0);
    const output = new Uint8Array(total);
    let offset = 0;

    for (const part of parts) {
        output.set(part, offset);
        offset += part.length;
    }

    return output;
}

function divider(width) {
    return '-'.repeat(width);
}

function truncate(value, width) {
    const text = normalizeText(value).replace(/\s+/g, ' ').trim();
    if (text.length <= width) return text;
    return text.slice(0, Math.max(0, width - 1)).trimEnd();
}

function wrapText(value, width) {
    const text = normalizeText(value).replace(/\s+/g, ' ').trim();
    if (!text) return [''];

    const words = text.split(' ');
    const lines = [];
    let current = '';

    for (const word of words) {
        const candidate = current ? `${current} ${word}` : word;
        if (candidate.length <= width) {
            current = candidate;
            continue;
        }

        if (current) lines.push(current);

        if (word.length <= width) {
            current = word;
            continue;
        }

        let remaining = word;
        while (remaining.length > width) {
            lines.push(remaining.slice(0, width));
            remaining = remaining.slice(width);
        }
        current = remaining;
    }

    if (current) lines.push(current);
    return lines;
}

function twoColumns(left, right, width) {
    const rightText = normalizeText(right);
    const leftWidth = Math.max(1, width - rightText.length - 1);
    return `${truncate(left, leftWidth).padEnd(leftWidth, ' ')} ${rightText}`;
}

function itemName(item) {
    return item?.nome || item?.produto_nome || item?.descricao || 'Produto';
}

function itemCode(item) {
    return item?.codigo || item?.produto_codigo || item?.cod_sku || item?.codigo_operacional || '';
}

function itemUnit(item) {
    return String(item?.unidade || item?.unit || 'UN').toUpperCase();
}

function itemQuantity(item) {
    return parseNumber(item?.qty ?? item?.quantidade ?? 0);
}

function itemUnitPrice(item) {
    return parseNumber(item?.preco_venda ?? item?.valor_unitario ?? item?.unit_price ?? 0);
}

function itemLineTotal(item) {
    const explicit = item?.valor_total ?? item?.line_total;
    if (explicit !== undefined && explicit !== null) return parseNumber(explicit);
    return Math.round(itemQuantity(item) * itemUnitPrice(item) * 100) / 100;
}

function paymentName(payment) {
    return payment?.methodName || payment?.metodo_nome || payment?.method_name || payment?.nome || 'Pagamento';
}

function paymentAmount(payment) {
    return parseNumber(payment?.amount ?? payment?.valor ?? 0);
}

function resolveTotal(context, items) {
    const explicit = context?.receipt?.total
        ?? context?.totals?.payable_total
        ?? context?.totals?.total_financeiro
        ?? context?.totals?.net
        ?? context?.sale?.total_financeiro;

    if (explicit !== undefined && explicit !== null) return parseNumber(explicit);
    return items.reduce((sum, item) => sum + itemLineTotal(item), 0);
}

function formatDateTime(value) {
    const date = value ? new Date(value) : new Date();
    const safeDate = Number.isNaN(date.getTime()) ? new Date() : date;

    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(safeDate);
}

function appendEmitter(parts, emitter, width) {
    parts.push(align(1), bold(true), line(emitter?.name || 'EMPRESA NAO CONFIGURADA'), bold(false));

    const cnpj = emitter?.cnpj ? `CNPJ: ${emitter.cnpj}` : '';
    const ie = emitter?.ie ? `IE: ${emitter.ie}` : '';
    if (cnpj || ie) parts.push(line([cnpj, ie].filter(Boolean).join('  ')));
    if (emitter?.address) parts.push(...wrapText(emitter.address, width).map((row) => line(row)));

    const cityLine = [emitter?.city, emitter?.state].filter(Boolean).join(' / ');
    const phoneLine = emitter?.phone ? `Tel: ${emitter.phone}` : '';
    if (cityLine || phoneLine) parts.push(line([cityLine, phoneLine].filter(Boolean).join('  ')));
}

function appendFiscalInfo(parts, receipt, width) {
    const fiscal = receipt?.fiscal || {};
    const accessKey = fiscal.access_key || fiscal.chave_acesso;
    const protocol = fiscal.protocol || fiscal.protocolo;
    const authorizedAt = fiscal.authorized_at || fiscal.autorizado_em;

    if (!accessKey && !protocol && !authorizedAt) return;

    parts.push(line(divider(width)));
    parts.push(bold(true), line('DADOS FISCAIS'), bold(false));
    if (accessKey) {
        parts.push(line('Chave de acesso:'));
        parts.push(...wrapText(String(accessKey), width).map((row) => line(row)));
    }
    if (protocol) parts.push(line(`Protocolo: ${protocol}`));
    if (authorizedAt) parts.push(line(`Autorizacao: ${formatDateTime(authorizedAt)}`));
}

export function renderSaleReceiptEscPos(context = {}) {
    const width = Number(context.width || 48);
    const receipt = context.receipt || {};
    const items = Array.isArray(context.items) ? context.items : [];
    const payments = Array.isArray(context.payments) ? context.payments : [];
    const emitter = context.emitter || {};
    const customer = context.customer || {};
    const saleContext = context.saleContext || {};
    const total = resolveTotal(context, items);
    const change = parseNumber(context?.totals?.change_total ?? context?.totals?.change ?? 0);

    const parts = [
        command(0x1b, 0x40),
        command(0x1b, 0x74, 0x10),
    ];

    appendEmitter(parts, emitter, width);

    parts.push(
        line(divider(width)),
        bold(true),
        line('COMPROVANTE TERMICO DE VENDA'),
        bold(false),
        align(0),
        line(`Venda: ${receipt.number || receipt.numero || '--'}  Serie: ${receipt.series || receipt.serie || '1'}`),
        line(`Emissao: ${formatDateTime(receipt.sold_at || receipt.created_at)}`),
    );

    const customerName = customer?.nome || customer?.name || customer?.razao_social || '';
    if (customerName) {
        parts.push(line(`Cliente: ${truncate(customerName, width - 9)}`));
    }

    const tableCode = saleContext?.tableCode || saleContext?.table_code;
    const commandCode = saleContext?.commandCode || saleContext?.command_code;
    if (tableCode || commandCode) {
        parts.push(line(`Mesa/Ficha: ${[tableCode, commandCode].filter(Boolean).join(' / ')}`));
    }

    parts.push(line(divider(width)));
    items.forEach((item, index) => {
        const quantity = itemQuantity(item);
        const unit = itemUnit(item);
        const unitPrice = itemUnitPrice(item);
        const lineTotal = itemLineTotal(item);
        const code = itemCode(item);
        const prefix = `${String(index + 1).padStart(3, '0')} `;
        const nameWidth = width - prefix.length;

        wrapText(itemName(item), nameWidth).forEach((row, rowIndex) => {
            parts.push(line(`${rowIndex === 0 ? prefix : ' '.repeat(prefix.length)}${row}`));
        });

        if (code) parts.push(line(`Cod: ${truncate(code, width - 5)}`));
        parts.push(line(twoColumns(`${quantity.toLocaleString('pt-BR')} ${unit} x ${toMoney(unitPrice)}`, toMoney(lineTotal), width)));
    });

    parts.push(line(divider(width)), bold(true), line(twoColumns('TOTAL', `R$ ${toMoney(total)}`, width)), bold(false));

    if (payments.length) {
        parts.push(line(divider(width)), line('PAGAMENTOS'));
        payments.forEach((payment) => {
            parts.push(line(twoColumns(paymentName(payment), `R$ ${toMoney(paymentAmount(payment))}`, width)));
            if (payment?.descricao) parts.push(...wrapText(payment.descricao, width).map((row) => line(row)));
            if (payment?.installments && Number(payment.installments) > 1) {
                parts.push(line(`${payment.installments}x de R$ ${toMoney(payment.installmentAmount || payment.installment_amount || paymentAmount(payment) / payment.installments)}`));
            }
        });
    }

    if (change > 0) {
        parts.push(line(twoColumns('Troco', `R$ ${toMoney(change)}`, width)));
    }

    appendFiscalInfo(parts, receipt, width);

    parts.push(
        line(divider(width)),
        align(1),
        line('Obrigado pela preferência'),
        printAndFeed(4),
        command(0x1d, 0x56, 0x00),
    );

    return concatBytes(parts);
}
