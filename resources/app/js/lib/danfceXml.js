export function stripAccents(value) {
    return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}

export function normalizeText(value) {
    return stripAccents(value).replace(/[^\x20-\x7E\n]/g, '');
}

export function parseText(node, tag) {
    return node?.getElementsByTagName(tag)?.[0]?.textContent?.trim() || '';
}

export function parseNumber(value) {
    const parsed = Number(String(value || '0').replace(',', '.'));
    return Number.isFinite(parsed) ? parsed : 0;
}

export function formatMoney(value) {
    return parseNumber(value).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

export function formatQuantity(value) {
    return parseNumber(value).toLocaleString('pt-BR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 3,
    });
}

export function formatDateTime(value) {
    if (!value) return '';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return value;

    return new Intl.DateTimeFormat('pt-BR', {
        timeZone: 'America/Sao_Paulo',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    }).format(parsed);
}

export function formatCnpj(value) {
    const digits = String(value || '').replace(/\D/g, '');
    if (digits.length !== 14) return value || '';
    return digits.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, '$1.$2.$3/$4-$5');
}

export function formatCpfCnpj(value) {
    const digits = String(value || '').replace(/\D/g, '');
    if (digits.length === 11) return digits.replace(/^(\d{3})(\d{3})(\d{3})(\d{2})$/, '$1.$2.$3-$4');
    if (digits.length === 14) return formatCnpj(digits);
    return value || '';
}

export function divider(width) {
    return '-'.repeat(width);
}

export function truncate(value, width) {
    const text = normalizeText(value).replace(/\s+/g, ' ').trim();
    if (text.length <= width) return text;
    return text.slice(0, Math.max(0, width - 1)).trimEnd();
}

export function wrapText(value, width) {
    const text = normalizeText(value).replace(/\s+/g, ' ').trim();
    if (!text) return [''];

    const lines = [];
    let current = '';

    for (const word of text.split(' ')) {
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

export function printableMessage(value) {
    const text = normalizeText(value).replace(/\s+/g, ' ').trim();

    if (!text || /^[A-Z]{1,3}$/.test(text)) return '';

    return text;
}

export function uniqueMessages(values) {
    const seen = new Set();

    return values
        .map(printableMessage)
        .filter(Boolean)
        .filter((message) => {
            const key = message.toLowerCase();
            if (seen.has(key)) return false;
            seen.add(key);
            return true;
        });
}

export function isTaxMessage(value) {
    return /tribut|ibpt|fonte/i.test(value);
}

export function twoColumns(left, right, width) {
    const rightText = normalizeText(right);
    const leftWidth = Math.max(1, width - rightText.length - 1);
    return `${truncate(left, leftWidth).padEnd(leftWidth, ' ')} ${rightText}`;
}

export function chunkAccessKey(value) {
    const digits = String(value || '').replace(/\D/g, '');
    if (digits.length !== 44) return value || '';
    return digits.replace(/(.{4})/g, '$1 ').trim();
}

export function paymentLabel(code) {
    return {
        '01': 'Dinheiro',
        '02': 'Cheque',
        '03': 'Cartao de Credito',
        '04': 'Cartao de Debito',
        '05': 'Credito Loja',
        '10': 'Vale Alimentacao',
        '11': 'Vale Refeicao',
        '12': 'Vale Presente',
        '13': 'Vale Combustivel',
        '15': 'Boleto',
        '16': 'Deposito Bancario',
        '17': 'PIX',
        '18': 'Transferencia',
        '19': 'Programa Fidelidade',
        '90': 'Sem Pagamento',
        '99': 'Outros',
    }[String(code || '').trim()] || 'Pagamento';
}

export function companyAddressLines(emit) {
    const ender = emit?.getElementsByTagName('enderEmit')?.[0] || null;
    const street = [
        parseText(ender, 'xLgr'),
        parseText(ender, 'nro'),
        parseText(ender, 'xCpl'),
    ].filter(Boolean).join(', ');
    const city = [
        parseText(ender, 'xBairro'),
        parseText(ender, 'xMun'),
        parseText(ender, 'UF'),
    ].filter(Boolean).join(' - ');

    return [street, city].filter(Boolean);
}

export function parseDanfceXml(xml, options = {}) {
    const parser = new DOMParser();
    const doc = parser.parseFromString(xml, 'application/xml');
    const parserError = doc.getElementsByTagName('parsererror')[0] || null;
    const infNFe = doc.getElementsByTagName('infNFe')[0] || null;

    if (parserError || !infNFe) {
        throw new Error('XML fiscal invalido para impressao do DANFC-e.');
    }

    const ide = infNFe.getElementsByTagName('ide')[0] || null;
    const emit = infNFe.getElementsByTagName('emit')[0] || null;
    const dest = infNFe.getElementsByTagName('dest')[0] || null;
    const total = infNFe.getElementsByTagName('ICMSTot')[0] || null;
    const dets = Array.from(infNFe.getElementsByTagName('det') || []);
    const paymentNodes = Array.from(doc.getElementsByTagName('detPag') || []);
    const messages = uniqueMessages([parseText(doc, 'infCpl'), parseText(doc, 'infAdFisco')]);

    return {
        doc,
        infNFe,
        ide,
        emit,
        dest,
        total,
        model: parseText(ide, 'mod'),
        number: parseText(ide, 'nNF'),
        series: parseText(ide, 'serie'),
        emittedAt: parseText(ide, 'dhEmi'),
        company: {
            name: parseText(emit, 'xFant') || parseText(emit, 'xNome') || options.companyName || 'EMITENTE',
            legalName: parseText(emit, 'xNome'),
            cnpj: parseText(emit, 'CNPJ'),
            ie: parseText(emit, 'IE'),
            addressLines: companyAddressLines(emit),
        },
        recipient: {
            name: parseText(dest, 'xNome'),
            document: parseText(dest, 'CPF') || parseText(dest, 'CNPJ'),
        },
        items: dets.map((det, index) => {
            const prod = det.getElementsByTagName('prod')[0] || null;

            return {
                index: index + 1,
                code: parseText(prod, 'cProd'),
                description: parseText(prod, 'xProd') || 'ITEM',
                quantity: parseText(prod, 'qCom') || '1',
                unit: parseText(prod, 'uCom') || 'UN',
                unitPrice: parseText(prod, 'vUnCom'),
                lineTotal: parseText(prod, 'vProd'),
            };
        }),
        totalValue: parseText(total, 'vNF'),
        payments: paymentNodes.map((payment) => ({
            code: parseText(payment, 'tPag'),
            label: paymentLabel(parseText(payment, 'tPag')),
            value: parseText(payment, 'vPag'),
        })),
        changeValue: parseText(doc, 'vTroco'),
        taxMessages: messages.filter(isTaxMessage),
        messages: messages.filter((message) => !isTaxMessage(message)),
        consultationUrl: parseText(doc, 'urlChave'),
        qrCode: parseText(doc, 'qrCode'),
        accessKey: (infNFe.getAttribute('Id') || '').replace(/^NFe/, '') || parseText(doc, 'chNFe'),
        protocol: options.protocol || parseText(doc, 'nProt'),
        authorizedAt: parseText(doc, 'dhRecbto'),
    };
}
