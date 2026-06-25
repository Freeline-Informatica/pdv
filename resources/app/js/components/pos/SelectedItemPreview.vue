<script setup>
import { computed } from 'vue';

const props = defineProps({
    item: {
        type: Object,
        default: null,
    },
    itemTotal: {
        type: Number,
        default: 0,
    },
    formatCurrency: {
        type: Function,
        required: true,
    },
    formatDecimal: {
        type: Function,
        required: true,
    },
});

function pickFirst(...values) {
    return values.map((value) => String(value ?? '').trim()).find(Boolean) || '';
}

function pickTaxValue(...values) {
    return values.find((value) => {
        if (value && typeof value === 'object') return true;
        return String(value ?? '').trim() !== '';
    });
}

function normalizeObjectTaxValue(value, keys = []) {
    if (!value || typeof value !== 'object') return '';

    const candidates = [
        ...keys,
        'label',
        'descricao',
        'description',
        'codigo',
        'code',
        'cst',
        'csosn',
        'cclass_trib',
        'cClassTrib',
        'tax_classification_code',
        'aliquota',
        'rate',
        'percentual',
    ];

    return pickFirst(...candidates.map((key) => value[key]));
}

function normalizeTaxValue(value, keys = []) {
    if (typeof value === 'string') {
        const trimmed = value.trim();
        if (!trimmed) return '';

        if (trimmed.startsWith('{')) {
            try {
                return normalizeObjectTaxValue(JSON.parse(trimmed), keys) || trimmed;
            } catch {
                return trimmed;
            }
        }

        return trimmed;
    }

    if (value && typeof value === 'object') {
        return normalizeObjectTaxValue(value, keys);
    }

    return pickFirst(value);
}

function formatTaxRate(value) {
    const text = pickFirst(value);
    if (!text) return '';
    if (text.includes('%') || /[a-z]/i.test(text)) return text;

    const numericText = text.includes(',')
        ? text.replace(/\./g, '').replace(',', '.')
        : text;
    const normalizedNumber = Number(numericText);
    if (!Number.isFinite(normalizedNumber)) return text;

    return `${normalizedNumber.toLocaleString('pt-BR', {
        minimumFractionDigits: normalizedNumber % 1 === 0 ? 0 : 2,
        maximumFractionDigits: 4,
    })}%`;
}

function compactTaxValue(...values) {
    const value = pickFirst(...values.map((entry) => normalizeTaxValue(entry)));
    return value || 'N/I';
}

const taxInfo = computed(() => {
    const item = props.item || {};
    const tributacao = item.tributacao && typeof item.tributacao === 'object' ? item.tributacao : {};
    const attrs = item.restaurant_config && typeof item.restaurant_config === 'object' ? item.restaurant_config : {};

    return {
        documentModel: pickFirst(tributacao.document_model, item.document_model, 'NFC-e'),
        cfop: pickFirst(tributacao.cfop, item.cfop, attrs.fiscal_cfop),
        cst: pickFirst(tributacao.cst, item.cst, attrs.fiscal_cst),
        csosn: pickFirst(tributacao.csosn, item.csosn, attrs.fiscal_csosn),
        ncm: pickFirst(tributacao.ncm, item.ncm, attrs.fiscal_ncm),
        icms: normalizeTaxValue(
            pickTaxValue(tributacao.icms, tributacao.icms_label, item.icms, attrs.fiscal_icms, attrs.icms),
            ['cst', 'csosn', 'aliquota'],
        ),
        icmsAliquota: formatTaxRate(pickFirst(
            tributacao.icms_aliquota,
            tributacao.aliquota_icms,
            item.icms_aliquota,
            attrs.fiscal_icms_aliquota,
            attrs.aliquota_icms,
        )),
        impostoSeletivo: normalizeTaxValue(
            pickTaxValue(
                tributacao.is,
                tributacao.imposto_seletivo,
                item.is,
                item.imposto_seletivo,
                attrs.fiscal_is,
                attrs.fiscal_imposto_seletivo,
                attrs.imposto_seletivo,
            ),
            ['cst', 'aliquota'],
        ),
        impostoSeletivoAliquota: formatTaxRate(pickFirst(
            tributacao.is_aliquota,
            tributacao.aliquota_is,
            item.is_aliquota,
            attrs.fiscal_is_aliquota,
            attrs.aliquota_is,
        )),
        ibs: normalizeTaxValue(
            pickTaxValue(tributacao.ibs, item.ibs, attrs.fiscal_ibs, attrs.ibs),
            ['cst', 'cclass_trib', 'cClassTrib', 'aliquota'],
        ),
        ibsAliquota: formatTaxRate(pickFirst(
            tributacao.ibs_aliquota,
            tributacao.aliquota_ibs,
            item.ibs_aliquota,
            attrs.fiscal_ibs_aliquota,
            attrs.aliquota_ibs,
        )),
        cbs: normalizeTaxValue(
            pickTaxValue(tributacao.cbs, item.cbs, attrs.fiscal_cbs, attrs.cbs),
            ['cst', 'cclass_trib', 'cClassTrib', 'aliquota'],
        ),
        cbsAliquota: formatTaxRate(pickFirst(
            tributacao.cbs_aliquota,
            tributacao.aliquota_cbs,
            item.cbs_aliquota,
            attrs.fiscal_cbs_aliquota,
            attrs.aliquota_cbs,
        )),
        ibsCbs: normalizeTaxValue(
            pickTaxValue(tributacao.ibs_cbs, tributacao.ibsCBS, item.ibs_cbs, attrs.fiscal_ibs_cbs, attrs.ibs_cbs),
            ['cst', 'cclass_trib', 'cClassTrib', 'tax_classification_code'],
        ),
        taxClassificationCode: pickFirst(tributacao.tax_classification_code, item.tax_classification_code, attrs.fiscal_tax_classification_code),
    };
});

const taxCompactRows = computed(() => [
    {
        label: 'ICMS',
        value: compactTaxValue(
            taxInfo.value.icms,
            taxInfo.value.icmsAliquota,
            taxInfo.value.cst ? `CST ${taxInfo.value.cst}` : '',
            taxInfo.value.csosn ? `CSOSN ${taxInfo.value.csosn}` : '',
            taxInfo.value.cfop ? `CFOP ${taxInfo.value.cfop}` : '',
        ),
    },
    {
        label: 'IS',
        value: compactTaxValue(
            taxInfo.value.impostoSeletivo,
            taxInfo.value.impostoSeletivoAliquota,
        ),
    },
    {
        label: 'IBS/CBS',
        value: compactTaxValue(
            taxInfo.value.ibsCbs,
            taxInfo.value.taxClassificationCode ? `cClass ${taxInfo.value.taxClassificationCode}` : '',
            [taxInfo.value.ibs, taxInfo.value.cbs].filter(Boolean).join('/'),
            [taxInfo.value.ibsAliquota ? `IBS ${taxInfo.value.ibsAliquota}` : '', taxInfo.value.cbsAliquota ? `CBS ${taxInfo.value.cbsAliquota}` : ''].filter(Boolean).join('/'),
        ),
    },
]);
</script>

<template>
    <article class="ui-card pos-last-item-review">
        <header class="flex items-center justify-between gap-2">
            <p class="pos-last-item-heading text-sm font-semibold uppercase tracking-wide text-muted">Último item selecionado</p>
            <span
                v-if="item"
                class="rounded-full border border-[var(--color-border)] bg-[var(--color-bg-elevated)] px-3 py-1 text-xs font-semibold text-main"
            >
                {{ String(item.codigo || 'Sem código') }}
            </span>
        </header>

        <div v-if="item" class="pos-last-item-content mt-4 grid gap-3 md:grid-cols-3">
            <div class="md:col-span-3">
                <p class="pos-last-item-name text-base font-black text-main">
                    {{ item.nome }}
                </p>
                <p class="pos-last-item-unit text-sm text-muted">
                    Unitário: {{ formatCurrency(item.preco_venda) }}
                </p>
            </div>
            <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-bg-elevated)] p-3">
                <p class="text-xs uppercase tracking-wide text-muted">Quantidade</p>
                <p class="pos-last-item-qty-value mt-1 text-2xl font-black text-main">
                    {{ formatDecimal(item.qty) }}
                </p>
            </div>
            <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-bg-elevated)] p-3 md:col-span-2">
                <p class="text-xs uppercase tracking-wide text-muted">Valor do item</p>
                <p class="pos-last-item-total-value mt-1 text-2xl font-black text-[var(--color-primary)]">
                    {{ formatCurrency(itemTotal) }}
                </p>
            </div>
            <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-bg-elevated)] p-3 md:col-span-3">
                <p class="text-xs uppercase tracking-wide text-muted">Tributação</p>
                <div class="mt-1 grid gap-1 text-main">
                    <p
                        v-for="row in taxCompactRows"
                        :key="row.label"
                        class="grid grid-cols-[3.4rem_minmax(0,1fr)] items-baseline gap-2 text-xs leading-tight"
                    >
                        <span class="font-black text-muted">{{ row.label }}</span>
                        <strong class="truncate font-black">{{ row.value }}</strong>
                    </p>
                </div>
            </div>
        </div>

        <div v-else class="mt-4 rounded-lg border border-dashed border-[var(--color-border)] p-4">
            <p class="text-sm font-semibold text-main">Nenhum item lançado ainda.</p>
            <p class="text-sm text-muted">Assim que um produto for confirmado, ele aparece aqui para conferência rápida.</p>
        </div>
    </article>
</template>
