import QRCode from 'qrcode';
import { normalizeCupomLayout, orderedCupomSections } from './cupomLayout.js';
import { parseDanfceXml } from './danfceXml.js';

const encoder = new TextEncoder();
const styleStates = new WeakMap();

function stripAccents(value) {
    return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}

function normalizeText(value) {
    return stripAccents(value).replace(/[^\x20-\x7E\n]/g, '');
}

function command(...bytes) {
    return Uint8Array.from(bytes);
}

function line(text = '') {
    return encoder.encode(`${normalizeText(text)}\n`);
}

function align(value) {
    return command(0x1b, 0x61, value);
}

function bold(enabled) {
    return command(0x1b, 0x45, enabled ? 1 : 0);
}

function printMode(value = 0) {
    return command(0x1b, 0x21, value & 0xff);
}

function condensed(enabled) {
    return command(enabled ? 0x0f : 0x12);
}

function lineSpacing(mm = 3.2) {
    const dots = Math.max(16, Math.min(64, Math.round(Number(mm || 3.2) * 8)));
    return command(0x1b, 0x33, dots);
}

function resetLineSpacing() {
    return command(0x1b, 0x32);
}

function feed(lines = 1) {
    return Uint8Array.from(Array.from({ length: lines }, () => 0x0a));
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

function parseText(node, tag) {
    return node?.getElementsByTagName(tag)?.[0]?.textContent?.trim() || '';
}

function parseNumber(value) {
    const parsed = Number(String(value || '0').replace(',', '.'));
    return Number.isFinite(parsed) ? parsed : 0;
}

function formatMoney(value) {
    return parseNumber(value).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function formatQuantity(value) {
    return parseNumber(value).toLocaleString('pt-BR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 3,
    });
}

function formatDateTime(value) {
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

function formatDateTimeCompact(value) {
    if (!value) return '';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return value;

    return new Intl.DateTimeFormat('pt-BR', {
        timeZone: 'America/Sao_Paulo',
        day: '2-digit',
        month: '2-digit',
        year: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
    }).format(parsed).replace(',', '');
}

function formatCnpj(value) {
    const digits = String(value || '').replace(/\D/g, '');
    if (digits.length !== 14) return value || '';
    return digits.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, '$1.$2.$3/$4-$5');
}

function formatCpfCnpj(value) {
    const digits = String(value || '').replace(/\D/g, '');
    if (digits.length === 11) return digits.replace(/^(\d{3})(\d{3})(\d{3})(\d{2})$/, '$1.$2.$3-$4');
    if (digits.length === 14) return formatCnpj(digits);
    return value || '';
}

function divider(width) {
    return '-'.repeat(width);
}

function fiscalText(value) {
    return normalizeText(value).replace(/\s+/g, ' ').trim().toUpperCase();
}

function truncate(value, width) {
    const text = normalizeText(value).replace(/\s+/g, ' ').trim();
    if (text.length <= width) return text;
    return text.slice(0, Math.max(0, width)).trimEnd();
}

function truncateLine(value, width) {
    const text = normalizeText(value).replace(/[\r\n]+/g, ' ');
    if (text.length <= width) return text;
    return text.slice(0, Math.max(0, width)).trimEnd();
}

function wrapText(value, width) {
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

function printableMessage(value) {
    const text = normalizeText(value).replace(/\s+/g, ' ').trim();

    if (!text || /^[A-Z]{1,3}$/.test(text)) return '';

    return text;
}

function uniqueMessages(values) {
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

function isTaxMessage(value) {
    return /tribut|ibpt|fonte/i.test(value);
}

function twoColumns(left, right, width) {
    const effectiveWidth = Math.max(8, Math.floor(Number(width || 0)));
    const rightText = normalizeText(right);
    const leftWidth = Math.max(1, effectiveWidth - rightText.length - 1);

    if (rightText.length + 1 >= effectiveWidth) {
        return truncate(rightText, effectiveWidth).padStart(effectiveWidth, ' ');
    }

    return `${truncate(left, leftWidth).padEnd(leftWidth, ' ')} ${rightText}`;
}

function chunkAccessKey(value) {
    const digits = String(value || '').replace(/\D/g, '');
    if (digits.length !== 44) return value || '';
    return digits.replace(/(.{4})/g, '$1 ').trim();
}

function paymentLabel(code) {
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
        '16': 'Depósito Bancário',
        '17': 'PIX',
        '18': 'Transferencia',
        '19': 'Programa Fidelidade',
        '90': 'Sem Pagamento',
        '99': 'Outros',
    }[String(code || '').trim()] || 'Pagamento';
}

function companyAddressLines(emit) {
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

function typographyPointSize(layout, variant = 'base') {
    if (variant === 'total') return Number(layout.typography.total_font_pt || 10);
    if (variant === 'mono') return Number(layout.typography.mono_font_pt || layout.typography.base_font_pt || 8);
    return Number(layout.typography.base_font_pt || 8);
}

function textStyle(layout, variant = 'base') {
    const pointSize = typographyPointSize(layout, variant);

    return {
        condensed: pointSize <= 7,
        pointSize,
    };
}

function styleStateFor(parts) {
    if (!styleStates.has(parts)) {
        styleStates.set(parts, {
            bold: false,
            condensed: false,
            mode: 0,
        });
    }

    return styleStates.get(parts);
}

function printModeFor(layout, variant = 'base', strong = false, section = null) {
    const pointSize = typographyPointSize(layout, variant);
    const nextBold = Boolean(strong || section?.emphasis === 'strong');
    let mode = 0;

    if (pointSize <= 7) {
        mode |= 0x01;
    }

    if (pointSize >= 10) {
        mode |= 0x10;
    }

    if (pointSize >= 11 || (variant === 'total' && pointSize >= 15)) {
        mode |= 0x20;
    }

    if (nextBold) {
        mode |= 0x08;
    }

    return mode;
}

function applyTextStyle(parts, layout, section = null, variant = 'base', strong = false) {
    const state = styleStateFor(parts);
    const nextBold = Boolean(strong || section?.emphasis === 'strong');
    const nextMode = printModeFor(layout, variant, strong, section);
    const nextCondensed = typographyPointSize(layout, variant) <= 7;

    if (state.condensed !== nextCondensed) {
        parts.push(condensed(nextCondensed));
        state.condensed = nextCondensed;
    }

    if (state.mode !== nextMode) {
        parts.push(printMode(nextMode));
        state.mode = nextMode;
    }

    if (state.bold !== nextBold) {
        parts.push(bold(nextBold));
        state.bold = nextBold;
    }
}

function resetTextStyle(parts) {
    const state = styleStateFor(parts);

    if (state.bold) {
        parts.push(bold(false));
        state.bold = false;
    }

    if (state.condensed) {
        parts.push(condensed(false));
        state.condensed = false;
    }

    if (state.mode) {
        parts.push(printMode(0));
        state.mode = 0;
    }
}

function charsPerLine(paperWidthMm, fontPt = 8) {
    const base = paperWidthMm >= 80 ? 48 : 32;
    if (fontPt <= 7) return paperWidthMm >= 80 ? 64 : 42;
    if (fontPt >= 11) return paperWidthMm >= 80 ? 24 : 16;

    const penalty = fontPt > 9 ? Math.min(paperWidthMm >= 80 ? 6 : 4, Math.ceil((fontPt - 9) * 2)) : 0;

    return Math.max(paperWidthMm >= 80 ? 30 : 20, base - penalty);
}

function charsForLayout(layout, section = null, variant = 'base') {
    const paperWidth = Number(layout.paper.width_mm || 80);
    const leftMargin = Number(layout.paper.margin_left_mm || 0);
    const rightMargin = Number(layout.paper.margin_right_mm || 0);
    const leftPadding = Number(section?.padding_left_mm || 0);
    const rightPadding = Number(section?.padding_right_mm || 0);
    const printableWidthMm = Math.max(20, paperWidth - leftMargin - rightMargin - leftPadding - rightPadding);
    const style = textStyle(layout, variant);
    const fullWidth = charsPerLine(paperWidth, style.pointSize);

    return Math.max(8, Math.floor((printableWidthMm / Math.max(1, paperWidth)) * fullWidth));
}

function charsForMm(mm, paperWidthMm, fullWidthChars) {
    return Math.max(0, Math.round((Number(mm || 0) / Math.max(1, Number(paperWidthMm || 80))) * fullWidthChars));
}

function sectionTextMetrics(layout, section, variant = 'base') {
    const fullWidth = charsForLayout(layout, null, variant);
    const width = charsForLayout(layout, section, variant);
    const leftInsetMm = Number(layout.paper.margin_left_mm || 0) + Number(section?.padding_left_mm || 0);
    const leftIndent = section?.align === 'left'
        ? Math.min(8, charsForMm(leftInsetMm, layout.paper.width_mm, fullWidth))
        : 0;

    return {
        indent: ' '.repeat(leftIndent),
        width,
    };
}

function sectionLine(value, metrics) {
    const indent = metrics.indent || '';
    const width = Math.max(1, Number(metrics.width || 0));

    return line(`${indent}${truncateLine(value, width)}`);
}

function pushStyledLine(parts, value, metrics, layout, section, variant = 'base', strong = false) {
    applyTextStyle(parts, layout, section, variant, strong);
    parts.push(sectionLine(value, metrics));
}

function pushWrappedLines(parts, value, metrics, layout, section, variant = 'base', strong = false) {
    wrapText(value, metrics.width).forEach((row) => {
        pushStyledLine(parts, row, metrics, layout, section, variant, strong);
    });
}

function paperWidthPx(widthMm = 80) {
    return widthMm >= 80 ? 576 : 420;
}

function mmToFeedLines(mm) {
    return Math.max(0, Math.round(Number(mm || 0) / 2));
}

function escposAlign(value) {
    if (value === 'center') return 1;
    if (value === 'right') return 2;
    return 0;
}

function sectionMap(layout) {
    return new Map(layout.sections.map((section) => [section.type, section]));
}

function isEnabled(sections, type) {
    return sections.get(type)?.enabled !== false;
}

function openSection(parts, layout, section, variant = 'base') {
    if (!section) return;
    parts.push(
        feed(mmToFeedLines(section.spacing_before_mm)),
        align(escposAlign(section.align)),
    );
    applyTextStyle(parts, layout, section, variant);
}

function closeSection(parts, section, layout = null) {
    if (!section) return;
    const blockSpacing = Number(layout?.block_spacing_mm || 0);
    parts.push(align(0));
    parts.push(feed(mmToFeedLines(Number(section.spacing_after_mm || 0) + blockSpacing)));
}

function rasterImage(data, bytesPerRow, height) {
    const output = new Uint8Array(8 + data.length);
    output.set([
        0x1d, 0x76, 0x30, 0x00,
        bytesPerRow % 256,
        Math.floor(bytesPerRow / 256),
        height % 256,
        Math.floor(height / 256),
    ], 0);
    output.set(data, 8);
    return output;
}

function canvasToRasterBytes(canvas) {
    const widthPx = canvas.width;
    const heightPx = canvas.height;
    const bytesPerRow = Math.ceil(widthPx / 8);
    const context = canvas.getContext('2d', { willReadFrequently: true });

    if (!context) return null;

    const imageData = context.getImageData(0, 0, widthPx, heightPx).data;
    const raster = new Uint8Array(bytesPerRow * heightPx);

    for (let y = 0; y < heightPx; y += 1) {
        for (let x = 0; x < widthPx; x += 1) {
            const offset = (y * widthPx + x) * 4;
            const r = imageData[offset] ?? 255;
            const g = imageData[offset + 1] ?? 255;
            const b = imageData[offset + 2] ?? 255;
            const a = imageData[offset + 3] ?? 255;
            const luminance = 0.299 * r + 0.587 * g + 0.114 * b;

            if (a > 0 && luminance < 180) {
                const byteIndex = y * bytesPerRow + Math.floor(x / 8);
                raster[byteIndex] |= 0x80 >> (x % 8);
            }
        }
    }

    return rasterImage(raster, bytesPerRow, heightPx);
}

async function appendLogo(parts, logoUrl, layout, section) {
    if (!logoUrl || typeof window === 'undefined') return;

    try {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        const loaded = new Promise((resolve, reject) => {
            img.onload = () => resolve();
            img.onerror = () => reject(new Error('Falha ao carregar a logo para impressão.'));
        });
        img.src = logoUrl;
        await loaded;

        const widthMm = Number(layout.paper.width_mm || 80);
        const paperWidth = paperWidthPx(widthMm);
        const leftInsetMm = Number(layout.paper.margin_left_mm || 0) + Number(section?.padding_left_mm || 0);
        const rightInsetMm = Number(layout.paper.margin_right_mm || 0) + Number(section?.padding_right_mm || 0);
        const printableWidthPx = Math.max(8, Math.floor(((widthMm - leftInsetMm - rightInsetMm) / widthMm) * paperWidth));
        const leftInsetPx = Math.max(0, Math.floor((leftInsetMm / widthMm) * paperWidth));
        const maxLogoWidth = Math.max(8, Math.min(printableWidthPx, Math.floor((26 / widthMm) * paperWidth)));
        const maxLogoHeight = Math.max(8, Math.floor((16 / widthMm) * paperWidth));
        const ratio = Math.min(1, maxLogoWidth / Math.max(1, img.width), maxLogoHeight / Math.max(1, img.height));
        const widthPx = Math.max(8, Math.floor(img.width * ratio));
        const heightPx = Math.max(8, Math.floor(img.height * ratio));
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d', { willReadFrequently: true });

        if (!context) return;

        canvas.width = paperWidth;
        canvas.height = heightPx;
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, canvas.width, canvas.height);

        let x = leftInsetPx;
        if (section?.align === 'right') {
            x = leftInsetPx + Math.max(0, printableWidthPx - widthPx);
        } else if (section?.align === 'center') {
            x = leftInsetPx + Math.max(0, Math.floor((printableWidthPx - widthPx) / 2));
        }

        context.drawImage(img, x, 0, widthPx, heightPx);

        const raster = canvasToRasterBytes(canvas);
        if (raster) parts.push(raster);
    } catch (error) {
        console.error('pdv.logo_render_failed', error);
    }
}

async function appendQrCode(parts, content, layout, section) {
    if (!content) return;

    if (typeof document !== 'undefined') {
        try {
            const widthMm = Number(layout.paper.width_mm || 80);
            const sizeMm = Math.max(20, Math.min(40, Number(layout.paper.qr_size_mm || 28)));
            const paperWidth = paperWidthPx(widthMm);
            const qrSizePx = Math.max(120, Math.min(paperWidth, Math.round((sizeMm / widthMm) * paperWidth)));
            const leftInsetMm = Number(layout.paper.margin_left_mm || 0) + Number(section?.padding_left_mm || 0);
            const rightInsetMm = Number(layout.paper.margin_right_mm || 0) + Number(section?.padding_right_mm || 0);
            const printableWidthPx = Math.max(8, Math.floor(((widthMm - leftInsetMm - rightInsetMm) / widthMm) * paperWidth));
            const leftInsetPx = Math.max(0, Math.floor((leftInsetMm / widthMm) * paperWidth));
            const qrCanvas = document.createElement('canvas');
            const rowCanvas = document.createElement('canvas');
            const context = rowCanvas.getContext('2d', { willReadFrequently: true });

            if (!context) return;

            await QRCode.toCanvas(qrCanvas, content, {
                errorCorrectionLevel: 'M',
                margin: 1,
                width: qrSizePx,
                color: {
                    dark: '#000000',
                    light: '#ffffff',
                },
            });

            rowCanvas.width = paperWidth;
            rowCanvas.height = qrSizePx;
            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, rowCanvas.width, rowCanvas.height);

            let x = leftInsetPx;
            if (section?.align === 'right') {
                x = leftInsetPx + Math.max(0, printableWidthPx - qrSizePx);
            } else if (section?.align === 'center') {
                x = leftInsetPx + Math.max(0, Math.floor((printableWidthPx - qrSizePx) / 2));
            }

            context.drawImage(qrCanvas, x, 0, qrSizePx, qrSizePx);

            const raster = canvasToRasterBytes(rowCanvas);
            if (raster) {
                parts.push(raster);
                return;
            }
        } catch (error) {
            console.error('pdv.qr_render_failed', error);
        }
    }

    const metrics = sectionTextMetrics(layout, section, 'mono');
    parts.push(align(escposAlign(section?.align)));
    pushWrappedLines(parts, content, metrics, layout, section, 'mono');
    parts.push(align(0));
}

function shouldPrintSection(sections, type) {
    return isEnabled(sections, type);
}

function appendHeader(parts, context, section) {
    const { emit, layout, options } = context;
    const metrics = sectionTextMetrics(layout, section);
    const { width } = metrics;
    const fantasyName = fiscalText(parseText(emit, 'xFant') || options.companyName || parseText(emit, 'xNome') || 'EMITENTE');
    const legalName = fiscalText(parseText(emit, 'xNome'));
    const addressLines = companyAddressLines(emit).map(fiscalText).filter(Boolean);
    const documents = [
        formatCnpj(parseText(emit, 'CNPJ')) ? `CNPJ:${formatCnpj(parseText(emit, 'CNPJ'))}` : '',
        parseText(emit, 'IE') ? `IE:${parseText(emit, 'IE')}` : '',
    ].filter(Boolean).join(' ');

    openSection(parts, layout, section);
    pushWrappedLines(parts, fantasyName, metrics, layout, section, 'base', true);
    if (legalName && legalName !== fantasyName) {
        pushWrappedLines(parts, legalName, metrics, layout, section);
    }
    addressLines.forEach((row) => {
        wrapText(row, width).forEach((wrapped) => pushStyledLine(parts, wrapped, metrics, layout, section));
    });
    if (documents) pushStyledLine(parts, documents, metrics, layout, section);
    pushStyledLine(parts, divider(width), metrics, layout, section);
    pushWrappedLines(parts, 'DOC. AUXILIAR DA NOTA FISCAL DE CONSUMIDOR ELETRONICA', metrics, layout, section, 'base', true);
    pushStyledLine(parts, divider(width), metrics, layout, section);
    closeSection(parts, section, layout);
}

function appendRecipient(parts, context, section) {
    const { dest, layout } = context;
    const metrics = sectionTextMetrics(layout, section);
    const { width } = metrics;
    const destName = fiscalText(parseText(dest, 'xNome'));
    const destDoc = parseText(dest, 'CPF') || parseText(dest, 'CNPJ');
    const recipientLabel = destName || destDoc
        ? ['CONSUMIDOR', destName, destDoc ? formatCpfCnpj(destDoc) : ''].filter(Boolean).join(' ')
        : 'CONSUMIDOR NAO IDENTIFICADO';

    openSection(parts, layout, section);
    pushWrappedLines(parts, recipientLabel, metrics, layout, section, 'base', true);
    pushStyledLine(parts, divider(width), metrics, layout, section);
    closeSection(parts, section, layout);
}

function itemColumns(width) {
    const effectiveWidth = Math.max(8, Math.floor(Number(width || 0)));
    const compactQuantityUnit = effectiveWidth < 48;
    const fullCodeWidth = 14;
    const tableColumns = compactQuantityUnit
        ? {
            table: true,
            compactQuantityUnit,
            code: fullCodeWidth,
            quantityUnit: 6,
            unitPrice: 5,
            total: 5,
        }
        : {
            table: true,
            compactQuantityUnit,
            code: fullCodeWidth,
            quantity: 5,
            unit: 2,
            unitPrice: 5,
            total: 6,
        };
    tableColumns.description = effectiveWidth - (
        tableColumns.code
        + (compactQuantityUnit ? tableColumns.quantityUnit : tableColumns.quantity + tableColumns.unit)
        + tableColumns.unitPrice
        + tableColumns.total
        + (compactQuantityUnit ? 4 : 5)
    );

    if (tableColumns.description >= 8) {
        return {
            ...tableColumns,
            description: Math.max(8, tableColumns.description),
        };
    }

    return {
        table: false,
        code: Math.max(6, Math.min(fullCodeWidth, effectiveWidth - 8)),
    };
}

function tableRow(columns, values) {
    if (columns.compactQuantityUnit) {
        return [
            truncate(values.code, columns.code).padEnd(columns.code, ' '),
            truncate(values.description, columns.description).padEnd(columns.description, ' '),
            truncate(values.quantityUnit, columns.quantityUnit).padStart(columns.quantityUnit, ' '),
            truncate(values.unitPrice, columns.unitPrice).padStart(columns.unitPrice, ' '),
            truncate(values.total, columns.total).padStart(columns.total, ' '),
        ].join(' ');
    }

    return [
        truncate(values.code, columns.code).padEnd(columns.code, ' '),
        truncate(values.description, columns.description).padEnd(columns.description, ' '),
        truncate(values.quantity, columns.quantity).padStart(columns.quantity, ' '),
        truncate(values.unit, columns.unit).padEnd(columns.unit, ' '),
        truncate(values.unitPrice, columns.unitPrice).padStart(columns.unitPrice, ' '),
        truncate(values.total, columns.total).padStart(columns.total, ' '),
    ].join(' ');
}

function appendItems(parts, context, section) {
    const { dets, layout } = context;
    const metrics = sectionTextMetrics(layout, section, 'mono');
    const { width } = metrics;
    const columns = itemColumns(width);

    openSection(parts, layout, section, 'mono');
    if (columns.table) {
        pushStyledLine(parts, tableRow(columns, {
            code: 'CODIGO',
            description: 'DESCRICAO',
            quantity: 'QTD',
            unit: 'UN',
            quantityUnit: 'QTD/UN',
            unitPrice: 'V.UN',
            total: 'TOTAL',
        }), metrics, layout, section, 'mono', true);
    } else {
        pushStyledLine(parts, 'ITEM CODIGO DESCRICAO', metrics, layout, section, 'mono', true);
    }

    dets.forEach((det, index) => {
        const prod = det.getElementsByTagName('prod')[0] || null;
        const code = parseText(prod, 'cProd');
        const description = fiscalText(parseText(prod, 'xProd') || 'ITEM');
        const quantity = formatQuantity(parseText(prod, 'qCom') || '1');
        const unit = parseText(prod, 'uCom') || 'UN';
        const unitPrice = formatMoney(parseText(prod, 'vUnCom'));
        const lineTotal = formatMoney(parseText(prod, 'vProd'));

        if (columns.table) {
            const descriptionLines = layout.item_layout.description_wrap === 'truncate'
                ? [truncate(description, columns.description)]
                : wrapText(description, columns.description).slice(0, layout.item_layout.max_description_lines);
            const [firstDescription = 'ITEM', ...extraDescriptions] = descriptionLines;

            pushStyledLine(parts, tableRow(columns, {
                code,
                description: firstDescription,
                quantity,
                unit,
                quantityUnit: `${quantity} ${unit}`,
                unitPrice,
                total: lineTotal,
            }), metrics, layout, section, 'mono');
            extraDescriptions.forEach((row) => {
                pushStyledLine(parts, tableRow(columns, {
                    code: '',
                    description: row,
                    quantity: '',
                    unit: '',
                    quantityUnit: '',
                    unitPrice: '',
                    total: '',
                }), metrics, layout, section, 'mono');
            });
        } else {
            const codeWidth = columns.code;
            const prefix = `${String(index + 1).padStart(3, '0')} ${truncate(code, codeWidth).padEnd(codeWidth, ' ')} `;
            const descriptionWidth = Math.max(6, width - prefix.length);
            const descriptionLines = layout.item_layout.description_wrap === 'truncate'
                ? [truncate(description, descriptionWidth)]
                : wrapText(description, descriptionWidth).slice(0, layout.item_layout.max_description_lines);
            const [firstDescription = 'ITEM', ...extraDescriptions] = descriptionLines;
            const descriptionIndent = ' '.repeat(Math.min(prefix.length, Math.max(0, width - 1)));

            pushStyledLine(parts, `${prefix}${firstDescription}`, metrics, layout, section, 'mono');
            extraDescriptions.forEach((row) => pushStyledLine(parts, `${descriptionIndent}${row}`, metrics, layout, section, 'mono'));
            pushStyledLine(parts, twoColumns(`${quantity} ${unit} x ${unitPrice}`, lineTotal, width), metrics, layout, section, 'mono');
        }

        if (layout.item_layout.item_spacing_mm > 0) {
            parts.push(feed(mmToFeedLines(layout.item_layout.item_spacing_mm)));
        }
    });
    pushStyledLine(parts, divider(width), metrics, layout, section, 'mono');
    closeSection(parts, section, layout);
}

function appendTotals(parts, context, section) {
    const { layout, total } = context;
    const columnSection = { ...section, align: 'left' };
    const totalMetrics = sectionTextMetrics(layout, columnSection, 'mono');
    const totalValue = formatMoney(parseText(total, 'vNF'));

    openSection(parts, layout, columnSection, 'mono');
    pushStyledLine(parts, twoColumns('Subtotal', totalValue, totalMetrics.width), totalMetrics, layout, section, 'total');
    pushStyledLine(parts, twoColumns('TOTAL R$', totalValue, totalMetrics.width), totalMetrics, layout, section, 'total', true);
    closeSection(parts, columnSection, layout);
}

function appendPayments(parts, context, section) {
    const { layout, paymentNodes, troco } = context;
    const metrics = sectionTextMetrics(layout, section, 'mono');
    const { width } = metrics;
    if (paymentNodes.length === 0) return;

    openSection(parts, layout, section, 'mono');
    paymentNodes.forEach((payment) => {
        pushStyledLine(parts, twoColumns(fiscalText(paymentLabel(parseText(payment, 'tPag'))), formatMoney(parseText(payment, 'vPag')), width), metrics, layout, section, 'mono');
    });
    if (troco > 0) pushStyledLine(parts, twoColumns('TROCO', formatMoney(troco), width), metrics, layout, section, 'mono');
    closeSection(parts, section, layout);
}

function appendIbpt(parts, context, section) {
    const { doc, layout } = context;
    const metrics = sectionTextMetrics(layout, section, 'mono');
    const { width } = metrics;
    const messages = uniqueMessages([parseText(doc, 'infCpl'), parseText(doc, 'infAdFisco')]).filter(isTaxMessage);
    if (messages.length === 0) return;

    openSection(parts, layout, section, 'mono');
    messages.forEach((message) => {
        wrapText(fiscalText(message), width).forEach((row) => pushStyledLine(parts, row, metrics, layout, section, 'mono'));
    });
    closeSection(parts, section, layout);
}

function appendMessages(parts, context, section) {
    const { doc, layout } = context;
    const metrics = sectionTextMetrics(layout, section, 'mono');
    const { width } = metrics;
    const messages = uniqueMessages([parseText(doc, 'infCpl'), parseText(doc, 'infAdFisco')]).filter((message) => !isTaxMessage(message));
    if (messages.length === 0) return;

    openSection(parts, layout, section, 'mono');
    pushStyledLine(parts, divider(width), metrics, layout, section, 'mono');
    messages.forEach((message) => {
        wrapText(message, width).forEach((row) => pushStyledLine(parts, row, metrics, layout, section, 'mono'));
    });
    closeSection(parts, section, layout);
}

function appendConsultation(parts, context, section) {
    const { accessKey, doc, layout } = context;
    const metrics = sectionTextMetrics(layout, section, 'mono');
    const { width } = metrics;
    const consultationUrl = parseText(doc, 'urlChave');
    if (!accessKey && !consultationUrl) return;

    openSection(parts, layout, section, 'mono');
    pushStyledLine(parts, 'Consulte pela Chave de Acesso em', metrics, layout, section, 'mono');
    if (consultationUrl) {
        wrapText(consultationUrl, width).forEach((row) => pushStyledLine(parts, row, metrics, layout, section, 'mono'));
    }
    if (accessKey) {
        wrapText(chunkAccessKey(accessKey).replace(/\s+/g, ' '), width).forEach((row) => pushStyledLine(parts, row, metrics, layout, section, 'mono'));
    }
    closeSection(parts, section, layout);
}

async function appendProtocolFooter(parts, context, section) {
    const { ide, layout } = context;
    const metrics = sectionTextMetrics(layout, section, 'mono');
    const { width } = metrics;
    const issueDate = formatDateTimeCompact(parseText(ide, 'dhEmi'));

    openSection(parts, layout, section, 'mono');
    pushStyledLine(parts, divider(width), metrics, layout, section, 'mono');
    pushWrappedLines(parts, `N:${parseText(ide, 'nNF') || '--'} Serie:${parseText(ide, 'serie') || '--'} Data:${issueDate}-Via Consumidor`, metrics, layout, section, 'mono', true);
    closeSection(parts, section, layout);
}

function appendTrailer(parts, layout) {
    const bottomFeed = mmToFeedLines(layout.paper.margin_bottom_mm);
    const cutFeed = mmToFeedLines(layout.paper.feed_before_cut_mm);
    parts.push(align(0));
    resetTextStyle(parts);
    parts.push(resetLineSpacing());

    if (layout.paper.cut_enabled) {
        parts.push(printAndFeed(Math.max(1, bottomFeed + cutFeed)));
        parts.push(command(0x1d, 0x56, 0x00));
        return;
    }

    parts.push(printAndFeed(Math.max(1, bottomFeed + cutFeed)));
}

export async function renderDanfceXmlEscPos(xml, options = {}) {
    const parsed = parseDanfceXml(xml, options);

    const layout = normalizeCupomLayout(options.layout);
    const sections = sectionMap(layout);
    const width = Number(options.width || charsForLayout(layout));
    const { doc, infNFe, ide, emit, dest, total } = parsed;
    const dets = Array.from(infNFe.getElementsByTagName('det') || []);
    const paymentNodes = Array.from(doc.getElementsByTagName('detPag') || []);
    const qrCode = parsed.qrCode;
    const accessKey = parsed.accessKey;
    const protocol = parsed.protocol;
    const troco = parseNumber(parsed.changeValue);
    const parts = [
        command(0x1b, 0x40),
        command(0x1b, 0x74, 0x10),
        lineSpacing(layout.item_layout.line_spacing_mm),
    ];

    const context = {
        accessKey,
        dest,
        dets,
        doc,
        emit,
        ide,
        layout,
        options,
        paymentNodes,
        protocol,
        qrCode,
        sections,
        total,
        troco,
        width,
    };

    parts.push(feed(mmToFeedLines(layout.paper.margin_top_mm)));

    for (const section of orderedCupomSections(layout)) {
        if (!shouldPrintSection(sections, section.type)) continue;

        switch (section.type) {
            case 'logo':
                if (!options.logoUrl) break;
                openSection(parts, layout, section);
                await appendLogo(parts, options.logoUrl, layout, section);
                closeSection(parts, section, layout);
                break;
            case 'header':
                appendHeader(parts, context, section);
                break;
            case 'recipient':
                appendRecipient(parts, context, section);
                break;
            case 'items':
                appendItems(parts, context, section);
                break;
            case 'totals':
                appendTotals(parts, context, section);
                break;
            case 'payments':
                appendPayments(parts, context, section);
                break;
            case 'ibpt':
                appendIbpt(parts, context, section);
                break;
            case 'messages':
                appendMessages(parts, context, section);
                break;
            case 'consultation':
                appendConsultation(parts, context, section);
                break;
            case 'qr_code':
                openSection(parts, layout, section, 'mono');
                await appendQrCode(parts, qrCode, layout, section);
                closeSection(parts, section, layout);
                break;
            case 'protocol_footer':
                await appendProtocolFooter(parts, context, section);
                break;
            default:
                break;
        }
    }

    appendTrailer(parts, layout);

    return concatBytes(parts);
}
