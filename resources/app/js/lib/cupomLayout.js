export const DEFAULT_SECTION_ORDER = [
    'logo',
    'header',
    'recipient',
    'items',
    'totals',
    'payments',
    'ibpt',
    'messages',
    'protocol_footer',
    'consultation',
    'qr_code',
];

export const REQUIRED_SECTIONS = ['header', 'items', 'totals', 'payments', 'consultation', 'qr_code', 'protocol_footer'];

export const SECTION_LABELS = {
    logo: 'Logo',
    header: 'Cabeçalho',
    recipient: 'Consumidor',
    items: 'Itens',
    totals: 'Totais',
    payments: 'Pagamentos',
    ibpt: 'IBPT',
    messages: 'Mensagens',
    consultation: 'Consulta',
    qr_code: 'QR Code',
    protocol_footer: 'Protocolo',
};

function sectionDefaults(type, order) {
    const required = REQUIRED_SECTIONS.includes(type);
    const overrides = {
        logo: { enabled: false, align: 'center', spacing_after_mm: 0 },
        header: { align: 'center', spacing_after_mm: 0, emphasis: 'strong' },
        recipient: { enabled: true, align: 'center', spacing_after_mm: 0 },
        items: { align: 'left', spacing_after_mm: 0 },
        totals: { align: 'right', spacing_after_mm: 0, emphasis: 'strong' },
        payments: { align: 'left', spacing_after_mm: 2 },
        ibpt: { enabled: true, align: 'center', spacing_after_mm: 2 },
        messages: { enabled: false, align: 'center', spacing_after_mm: 2 },
        protocol_footer: { align: 'center', spacing_after_mm: 0, emphasis: 'strong' },
        consultation: { align: 'center', spacing_after_mm: 0 },
        qr_code: { align: 'center', spacing_after_mm: 0 },
    }[type] || {};

    return {
        type,
        required,
        enabled: overrides.enabled ?? (required || ['recipient', 'ibpt'].includes(type)),
        order,
        align: overrides.align ?? (['logo', 'header', 'recipient', 'qr_code'].includes(type) ? 'center' : type === 'totals' ? 'right' : 'left'),
        spacing_before_mm: 0,
        spacing_after_mm: overrides.spacing_after_mm ?? (type === 'protocol_footer' ? 0 : 2),
        padding_left_mm: 0,
        padding_right_mm: 0,
        emphasis: overrides.emphasis ?? (['header', 'totals'].includes(type) ? 'strong' : 'normal'),
    };
}

export const DEFAULT_CUPOM_LAYOUT = {
    schema_version: 2,
    renderer: 'nfce_pdf_thermal',
    block_spacing_mm: 0,
    paper: {
        width_mm: 80,
        margin_top_mm: 2,
        margin_right_mm: 2,
        margin_bottom_mm: 4,
        margin_left_mm: 2,
        qr_size_mm: 26,
        feed_before_cut_mm: 8,
        cut_enabled: false,
    },
    typography: {
        base_font_pt: 7,
        mono_font_pt: 7,
        total_font_pt: 9,
    },
    item_layout: {
        max_description_lines: 2,
        description_wrap: 'wrap',
        break_long_words: true,
        line_spacing_mm: 3.2,
        item_spacing_mm: 0,
    },
    sections: DEFAULT_SECTION_ORDER.map((type, index) => sectionDefaults(type, index + 1)),
};

function objectValue(value) {
    if (value && typeof value === 'object' && !Array.isArray(value)) {
        return value;
    }

    if (typeof value === 'string') {
        try {
            const parsed = JSON.parse(value);
            return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
        } catch {
            return {};
        }
    }

    return {};
}

function numberValue(value, fallback) {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : fallback;
}

function booleanValue(value, fallback) {
    return typeof value === 'boolean' ? value : fallback;
}

function alignValue(value, fallback) {
    return ['left', 'center', 'right'].includes(value) ? value : fallback;
}

function emphasisValue(value, fallback) {
    return ['normal', 'strong'].includes(value) ? value : fallback;
}

function normalizeSection(raw, type, order) {
    const defaults = sectionDefaults(type, order);
    const source = raw && typeof raw === 'object' ? raw : {};
    const required = REQUIRED_SECTIONS.includes(type);

    return {
        type,
        required,
        enabled: required ? true : booleanValue(source.enabled, defaults.enabled),
        order,
        align: alignValue(source.align, defaults.align),
        spacing_before_mm: Math.max(0, numberValue(source.spacing_before_mm, defaults.spacing_before_mm)),
        spacing_after_mm: Math.max(0, numberValue(source.spacing_after_mm, defaults.spacing_after_mm)),
        padding_left_mm: Math.max(0, numberValue(source.padding_left_mm, defaults.padding_left_mm)),
        padding_right_mm: Math.max(0, numberValue(source.padding_right_mm, defaults.padding_right_mm)),
        emphasis: emphasisValue(source.emphasis, defaults.emphasis),
    };
}

export function normalizeCupomLayout(raw) {
    const source = objectValue(raw);
    const paper = objectValue(source.paper);
    const typography = objectValue(source.typography);
    const itemLayout = objectValue(source.item_layout);
    const rawSections = Array.isArray(source.sections) ? source.sections : [];
    const byType = new Map();

    rawSections
        .filter((section) => section && typeof section === 'object' && DEFAULT_SECTION_ORDER.includes(section.type))
        .sort((left, right) => numberValue(left.order, 0) - numberValue(right.order, 0))
        .forEach((section) => byType.set(section.type, section));

    const orderedTypes = [
        ...rawSections
            .filter((section) => section && typeof section === 'object' && DEFAULT_SECTION_ORDER.includes(section.type))
            .sort((left, right) => numberValue(left.order, 0) - numberValue(right.order, 0))
            .map((section) => section.type),
        ...DEFAULT_SECTION_ORDER.filter((type) => !byType.has(type)),
    ];

    return {
        schema_version: 2,
        renderer: 'nfce_pdf_thermal',
        block_spacing_mm: Math.max(0, Math.min(8, numberValue(source.block_spacing_mm, DEFAULT_CUPOM_LAYOUT.block_spacing_mm))),
        paper: {
            width_mm: numberValue(paper.width_mm, DEFAULT_CUPOM_LAYOUT.paper.width_mm) <= 69 ? 58 : 80,
            margin_top_mm: Math.max(0, numberValue(paper.margin_top_mm, DEFAULT_CUPOM_LAYOUT.paper.margin_top_mm)),
            margin_right_mm: Math.max(0, numberValue(paper.margin_right_mm, DEFAULT_CUPOM_LAYOUT.paper.margin_right_mm)),
            margin_bottom_mm: Math.max(0, numberValue(paper.margin_bottom_mm, DEFAULT_CUPOM_LAYOUT.paper.margin_bottom_mm)),
            margin_left_mm: Math.max(0, numberValue(paper.margin_left_mm, DEFAULT_CUPOM_LAYOUT.paper.margin_left_mm)),
            qr_size_mm: Math.max(20, Math.min(40, numberValue(paper.qr_size_mm, DEFAULT_CUPOM_LAYOUT.paper.qr_size_mm))),
            feed_before_cut_mm: Math.max(0, Math.min(40, numberValue(paper.feed_before_cut_mm, DEFAULT_CUPOM_LAYOUT.paper.feed_before_cut_mm))),
            cut_enabled: booleanValue(paper.cut_enabled, DEFAULT_CUPOM_LAYOUT.paper.cut_enabled),
        },
        typography: {
            base_font_pt: Math.max(6, Math.min(12, numberValue(typography.base_font_pt, DEFAULT_CUPOM_LAYOUT.typography.base_font_pt))),
            mono_font_pt: Math.max(6, Math.min(12, numberValue(typography.mono_font_pt, DEFAULT_CUPOM_LAYOUT.typography.mono_font_pt))),
            total_font_pt: Math.max(7, Math.min(16, numberValue(typography.total_font_pt, DEFAULT_CUPOM_LAYOUT.typography.total_font_pt))),
        },
        item_layout: {
            max_description_lines: Math.max(1, Math.min(6, Math.round(numberValue(itemLayout.max_description_lines, DEFAULT_CUPOM_LAYOUT.item_layout.max_description_lines)))),
            description_wrap: itemLayout.description_wrap === 'truncate' ? 'truncate' : 'wrap',
            break_long_words: booleanValue(itemLayout.break_long_words, DEFAULT_CUPOM_LAYOUT.item_layout.break_long_words),
            line_spacing_mm: Math.max(2, Math.min(6, numberValue(itemLayout.line_spacing_mm, DEFAULT_CUPOM_LAYOUT.item_layout.line_spacing_mm))),
            item_spacing_mm: Math.max(0, Math.min(8, numberValue(itemLayout.item_spacing_mm, DEFAULT_CUPOM_LAYOUT.item_layout.item_spacing_mm))),
        },
        sections: orderedTypes.map((type, index) => normalizeSection(byType.get(type), type, index + 1)),
    };
}

export function orderedCupomSections(layout) {
    return normalizeCupomLayout(layout).sections
        .slice()
        .sort((left, right) => left.order - right.order)
        .filter((section) => section.enabled);
}
