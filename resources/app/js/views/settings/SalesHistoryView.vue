<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import {
    ArrowLeft,
    Calendar,
    CircleX,
    ClipboardList,
    CreditCard,
    Eye,
    FileDown,
    FileSearch,
    FileText,
    Hash,
    History,
    Monitor,
    Package,
    Printer,
    ShieldAlert,
    ShoppingBag,
    User,
    Wallet,
} from 'lucide-vue-next';
import { useRoute, useRouter } from 'vue-router';
import api from '../../lib/api';
import { renderSaleReceiptEscPos } from '../../lib/escposReceipt';
import { downloadFiscalArtifact, openFiscalPdf, printFiscalPdf } from '../../lib/fiscalArtifacts';
import { useLocalPrinter } from '../../composables/useLocalPrinter';
import SettingsPageHeader from '../../components/settings/SettingsPageHeader.vue';
import SettingsEmptyState from '../../components/settings/SettingsEmptyState.vue';
import SettingsFilterBar from '../../components/settings/SettingsFilterBar.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppCard from '../../components/ui/AppCard.vue';
import AppSearchField from '../../components/ui/AppSearchField.vue';
import AppBadge from '../../components/ui/AppBadge.vue';
import AppIconButton from '../../components/ui/AppIconButton.vue';
import AppModal from '../../components/ui/AppModal.vue';
import AppTextarea from '../../components/ui/AppTextarea.vue';

const route = useRoute();
const router = useRouter();

const loadingSales = ref(false);
const loadingDetail = ref(false);
const sales = ref([]);
const selectedSale = ref(null);
const pageError = ref('');
const fiscalActionFeedback = ref('');
const fiscalActionError = ref('');
const fiscalActionLoading = reactive({
    downloadXml: false,
    print: false,
    printThermal: false,
    view: false,
});

const search = ref('');
const statusFilter = ref('todas');

const nowTick = ref(Date.now());
let timerIntervalId = null;

const {
    printer,
    connect: connectLocalPrinter,
    print: printLocalPayload,
} = useLocalPrinter();

const cancelModal = reactive({
    open: false,
    loading: false,
    error: '',
    reason: '',
});

const currentSaleId = computed(() => String(route.query.sale_id || '').trim());

const filteredSales = computed(() => {
    const needle = String(search.value || '').trim().toLowerCase();

    return sales.value.filter((item) => {
        if (statusFilter.value !== 'todas' && item.status !== statusFilter.value) return false;

        if (!needle) return true;

        const saleNumber = String(item.numero || '').toLowerCase();
        const customerName = String(item.cliente_nome || '').toLowerCase();
        const soldAtLabel = formatDateTime(item.sold_at).toLowerCase();

        return saleNumber.includes(needle) || customerName.includes(needle) || soldAtLabel.includes(needle);
    });
});

const summary = computed(() => {
    const source = sales.value;
    const todayKey = toDateKey(new Date());

    const todayFinalized = source.filter((item) => item.status === 'finalizada' && toDateKey(new Date(item.sold_at)) === todayKey);
    const finalized = source.filter((item) => item.status === 'finalizada');

    return {
        todayRevenue: todayFinalized.reduce((acc, item) => acc + Number(item.total_financeiro || 0), 0),
        todayCount: todayFinalized.length,
        totalRevenue: finalized.reduce((acc, item) => acc + Number(item.total_financeiro || 0), 0),
        totalCount: finalized.length,
    };
});

const cancellationMeta = computed(() => {
    if (!selectedSale.value) {
        return {
            remainingSeconds: 0,
            canCancel: false,
            message: '',
            deadlineAt: null,
        };
    }

    const sale = selectedSale.value;
    const policy = sale.cancel_policy || {};
    const windowSeconds = Number(policy.window_seconds || 0);
    const deadlineAt = policy.deadline_at || null;
    const deadlineMs = deadlineAt ? new Date(deadlineAt).getTime() : 0;
    const remainingSeconds = deadlineMs > 0 ? Math.max(0, Math.floor((deadlineMs - nowTick.value) / 1000)) : 0;

    if (sale.status === 'cancelada') {
        return {
            remainingSeconds,
            canCancel: false,
            message: 'Venda já cancelada.',
            deadlineAt,
        };
    }

    if (windowSeconds <= 0) {
        return {
            remainingSeconds,
            canCancel: false,
            message: 'Sem política de cancelamento para este tipo de documento.',
            deadlineAt,
        };
    }

    if (remainingSeconds <= 0) {
        return {
            remainingSeconds: 0,
            canCancel: false,
            message: `Prazo expirado para cancelamento de ${sale.document_label}.`,
            deadlineAt,
        };
    }

    return {
        remainingSeconds,
        canCancel: true,
        message: `Tempo para cancelar ${sale.document_label}: ${formatDuration(remainingSeconds)}`,
        deadlineAt,
    };
});

const saleItems = computed(() => (Array.isArray(selectedSale.value?.items) ? selectedSale.value.items : []));
const salePayments = computed(() => (Array.isArray(selectedSale.value?.payments) ? selectedSale.value.payments : []));

const itemsSoldCount = computed(() => saleItems.value.reduce((total, item) => total + Number(item?.quantidade || 0), 0));

const primaryPayment = computed(() => {
    if (!salePayments.value.length) return 'Não informado';

    const sorted = [...salePayments.value].sort((a, b) => Number(b?.valor || 0) - Number(a?.valor || 0));
    return fallbackText(sorted[0]?.metodo_nome, 'Não informado');
});

const discountTotal = computed(() => {
    if (!selectedSale.value) return 0;

    return pickFirstNumber([
        selectedSale.value.desconto_total,
        selectedSale.value.discount_total,
        selectedSale.value.total_desconto,
    ], 0);
});

const surchargeTotal = computed(() => {
    if (!selectedSale.value) return 0;

    const explicit = pickFirstNumber([
        selectedSale.value.acrescimo_total,
        selectedSale.value.surcharge_total,
        selectedSale.value.total_acrescimo,
    ], null);

    if (explicit != null) return explicit;

    return Number(selectedSale.value.juros_total || 0);
});

const fiscalSubtotal = computed(() => {
    if (!selectedSale.value) return 0;

    return pickFirstNumber([
        selectedSale.value.total_fiscal,
        selectedSale.value.subtotal_fiscal,
        selectedSale.value.total_bruto,
    ], 0);
});

const paidTotal = computed(() => {
    if (!selectedSale.value) return 0;

    const explicitPaid = pickFirstNumber([
        selectedSale.value.total_pago,
        selectedSale.value.valor_pago,
        selectedSale.value.paid_total,
    ], null);

    if (explicitPaid != null) return explicitPaid;

    return salePayments.value.reduce((total, payment) => total + Number(payment?.valor || 0), 0);
});

const changeTotal = computed(() => {
    if (!selectedSale.value) return 0;

    const explicitChange = pickFirstNumber([
        selectedSale.value.troco,
        selectedSale.value.change,
        selectedSale.value.change_total,
    ], null);

    if (explicitChange != null) return explicitChange;

    return Math.max(0, paidTotal.value - Number(selectedSale.value.total_financeiro || 0));
});

const saleInfoRows = computed(() => {
    if (!selectedSale.value) return [];

    const sale = selectedSale.value;

    return [
        { label: 'Número da venda', value: `#${fallbackText(sale.numero, '-')}` },
        { label: 'Data e hora', value: fallbackText(formatDateTime(sale.sold_at), 'Não informado') },
        { label: 'Operador', value: fallbackText(sale?.creator?.name, 'Não informado') },
        {
            label: 'Terminal/Caixa',
            value: fallbackText(firstFilled([
                sale.terminal_nome,
                sale.caixa_nome,
                sale?.terminal?.nome,
                sale?.cash_register?.nome,
            ]), 'Não informado'),
        },
        {
            label: 'Cliente',
            value: fallbackText(firstFilled([
                sale.cliente_nome,
                sale?.cliente?.nome,
            ]), 'Não informado'),
        },
        {
            label: 'Origem da venda',
            value: fallbackText(firstFilled([
                sale.origem,
                sale.sale_origin,
                sale?.source?.label,
            ]), '-'),
        },
        { label: 'Status', value: statusLabel(sale.status) },
        {
            label: 'Observação',
            value: fallbackText(firstFilled([
                sale.observacao,
                sale.observacoes,
                sale.notes,
                sale.cancellation_reason,
            ]), '-'),
        },
    ];
});

const fiscalModel = computed(() => {
    if (!selectedSale.value) return '-';

    return fallbackText(firstFilled([
        selectedSale.value?.fiscal?.modelo,
        selectedSale.value?.fiscal?.model,
        selectedSale.value.document_label,
        selectedSale.value.document_type ? String(selectedSale.value.document_type).toUpperCase() : null,
    ]), '-');
});

const fiscalDocumentLabel = computed(() => {
    if (!selectedSale.value) return 'NFC-e';

    return fallbackText(firstFilled([
        selectedSale.value.document_label,
        selectedSale.value?.fiscal?.document_type ? String(selectedSale.value.fiscal.document_type).toUpperCase() : null,
        selectedSale.value.document_type ? String(selectedSale.value.document_type).toUpperCase() : null,
    ]), 'NFC-e');
});

const fiscalStatus = computed(() => {
    if (!selectedSale.value) return 'Não informado';

    return fallbackText(firstFilled([
        selectedSale.value?.fiscal?.status_label,
        selectedSale.value?.fiscal?.status,
        selectedSale.value.status_fiscal,
        selectedSale.value.nfce_status,
    ]), 'Não emitida');
});

const fiscalNumber = computed(() => firstFilled([
    selectedSale.value?.fiscal?.numero,
    selectedSale.value?.fiscal?.number,
    selectedSale.value.numero_fiscal,
    selectedSale.value.nfce_numero,
]));

const fiscalSeries = computed(() => firstFilled([
    selectedSale.value?.fiscal?.serie,
    selectedSale.value?.fiscal?.series,
    selectedSale.value.serie_fiscal,
]));

const fiscalAccessKey = computed(() => firstFilled([
    selectedSale.value?.fiscal?.chave_acesso,
    selectedSale.value?.fiscal?.access_key,
    selectedSale.value.chave_acesso,
]));

const fiscalProtocol = computed(() => firstFilled([
    selectedSale.value?.fiscal?.protocolo,
    selectedSale.value?.fiscal?.protocol,
    selectedSale.value.protocolo_autorizacao,
]));

const fiscalAuthorizedAt = computed(() => firstFilled([
    selectedSale.value?.fiscal?.autorizado_em,
    selectedSale.value?.fiscal?.authorized_at,
    selectedSale.value.data_autorizacao,
]));

const fiscalCancelStatus = computed(() => {
    if (!selectedSale.value) return '-';

    return fallbackText(firstFilled([
        selectedSale.value?.fiscal?.cancel_status,
        selectedSale.value?.fiscal?.status_cancelamento,
        selectedSale.value.status_cancelamento_fiscal,
    ]), cancellationMeta.value.canCancel ? 'Cancelamento disponível' : 'Cancelamento indisponível');
});

const fiscalCancelBlockMessage = computed(() => {
    if (!selectedSale.value) return '';
    if (selectedSale.value.status === 'cancelada') return '';
    if (cancellationMeta.value.canCancel) return '';

    if (String(cancellationMeta.value.message || '').toLowerCase().includes('prazo expirado')) {
        const documentLabel = fallbackText(selectedSale.value.document_label, 'NFC-e');
        return `Cancelamento fiscal indisponível. O prazo para cancelamento da ${documentLabel} já expirou.`;
    }

    return cancellationMeta.value.message;
});

const hasLinkedFiscalDocument = computed(() => [
    fiscalNumber.value,
    fiscalSeries.value,
    fiscalAccessKey.value,
    fiscalProtocol.value,
    fiscalAuthorizedAt.value,
].some((value) => hasValue(value)));

const detailSummaryCards = computed(() => {
    if (!selectedSale.value) return [];

    return [
        {
            id: 'total',
            label: 'Total financeiro',
            value: formatCurrency(selectedSale.value.total_financeiro),
            emphasis: true,
        },
        {
            id: 'subtotal',
            label: 'Subtotal fiscal',
            value: formatCurrency(fiscalSubtotal.value),
        },
        {
            id: 'items',
            label: 'Itens vendidos',
            value: formatQuantity(itemsSoldCount.value),
        },
        {
            id: 'payment',
            label: 'Forma principal',
            value: primaryPayment.value,
        },
        {
            id: 'status',
            label: 'Status da venda',
            value: statusLabel(selectedSale.value.status),
        },
        {
            id: 'fiscal',
            label: 'Status fiscal/NFC-e',
            value: fiscalStatus.value,
        },
    ];
});

const timelineEvents = computed(() => {
    if (!selectedSale.value) return [];

    const source = [
        selectedSale.value.eventos,
        selectedSale.value.events,
        selectedSale.value.timeline,
        selectedSale.value.audit_logs,
        selectedSale.value.audits,
        selectedSale.value.logs,
    ].find((value) => Array.isArray(value));

    if (!Array.isArray(source)) return [];

    return source
        .map((event, index) => {
            const timestamp = firstFilled([
                event.horario,
                event.occurred_at,
                event.happened_at,
                event.created_at,
                event.timestamp,
            ]);

            return {
                id: event.id || `event-${index}`,
                at: timestamp,
                type: prettifyText(firstFilled([
                    event.tipo,
                    event.type,
                    event.event,
                    event.action,
                ]) || 'Evento'),
                responsible: fallbackText(firstFilled([
                    event.responsavel,
                    event.usuario,
                    event.operator_name,
                    event?.user?.name,
                    event?.operator?.name,
                ]), 'Não informado'),
                note: firstFilled([
                    event.observacao,
                    event.description,
                    event.note,
                    event.message,
                ]) || null,
            };
        })
        .sort((a, b) => {
            const first = a.at ? new Date(a.at).getTime() : 0;
            const second = b.at ? new Date(b.at).getTime() : 0;
            return first - second;
        });
});

const printReceiptUrl = computed(() => firstFilled([
    selectedSale.value?.receipt_url,
    selectedSale.value?.print_url,
    selectedSale.value?.links?.print,
]));

const printFiscalUrl = computed(() => firstFilled([
    selectedSale.value?.fiscal?.pdf_url,
    selectedSale.value?.pdf_url,
]));

const viewFiscalUrl = computed(() => firstFilled([
    selectedSale.value?.fiscal?.pdf_url,
    selectedSale.value?.fiscal?.view_url,
    selectedSale.value?.pdf_url,
    selectedSale.value?.nfce_url,
    selectedSale.value?.links?.fiscal,
]));

const downloadXmlUrl = computed(() => firstFilled([
    selectedSale.value?.fiscal?.xml_url,
    selectedSale.value?.xml_url,
    selectedSale.value?.links?.xml,
]));

const canUseLocalPrinter = computed(() => printer.value.browserSupported && !['connecting', 'printing'].includes(printer.value.status));

function toDateKey(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function formatCurrency(value) {
    return Number(value || 0).toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    });
}

function formatDateTime(value) {
    if (!value) return '—';

    const normalizedDate = new Date(value);
    if (Number.isNaN(normalizedDate.getTime())) return '—';

    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(normalizedDate);
}

function formatDuration(totalSeconds) {
    const seconds = Math.max(0, Number(totalSeconds || 0));
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const remSeconds = seconds % 60;

    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(remSeconds).padStart(2, '0')}`;
}

function formatQuantity(value) {
    return Number(value || 0).toLocaleString('pt-BR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 3,
    });
}

function hasValue(value) {
    if (value == null) return false;
    if (typeof value === 'string') return value.trim() !== '';
    return true;
}

function firstFilled(values) {
    for (const value of values) {
        if (hasValue(value)) return value;
    }

    return null;
}

function fallbackText(value, fallback = 'Não informado') {
    return hasValue(value) ? String(value) : fallback;
}

function pickFirstNumber(values, fallback = 0) {
    for (const value of values) {
        const parsed = Number(value);
        if (Number.isFinite(parsed)) return parsed;
    }

    return fallback;
}

function prettifyText(value) {
    const safeValue = String(value || '').trim();
    if (!safeValue) return '';

    return safeValue
        .replace(/[_-]+/g, ' ')
        .replace(/\s+/g, ' ')
        .toLowerCase()
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

function statusVariant(status) {
    const normalized = String(status || '').toLowerCase();

    if (normalized === 'cancelada') return 'danger';
    if (['pendente', 'em_aberto', 'aguardando'].includes(normalized)) return 'warning';
    return 'success';
}

function statusLabel(status) {
    const normalized = String(status || '').toLowerCase();

    if (normalized === 'cancelada') return 'Cancelada';
    if (normalized === 'finalizada') return 'Finalizada';
    if (normalized === 'pendente') return 'Pendente';
    if (normalized === 'em_aberto') return 'Em aberto';

    return prettifyText(status || 'Não informado') || 'Não informado';
}

function rowClass(status) {
    return status === 'cancelada' ? 'sale-row is-canceled' : 'sale-row';
}

function itemIdentifier(item) {
    return fallbackText(firstFilled([
        item?.produto_codigo,
        item?.codigo,
        item?.sku,
        item?.ean,
    ]), '-');
}

function paymentField(payment, keys) {
    return firstFilled(keys.map((key) => payment?.[key]));
}

function openExternalLink(url) {
    if (!hasValue(url)) return;
    window.open(String(url), '_blank', 'noopener,noreferrer');
}

function clearFiscalActionStatus() {
    fiscalActionFeedback.value = '';
    fiscalActionError.value = '';
}

function buildFiscalFileBaseName() {
    const documentType = String(selectedSale.value?.document_type || '').trim().toLowerCase() === 'nfe' ? 'nfe' : 'nfce';
    const saleNumber = String(selectedSale.value?.numero || 'documento').trim() || 'documento';

    return `${documentType}-${saleNumber}`;
}

async function handlePrintFiscal() {
    if (!printFiscalUrl.value || fiscalActionLoading.print) return;

    clearFiscalActionStatus();
    fiscalActionLoading.print = true;

    try {
        const result = await printFiscalPdf(printFiscalUrl.value, {
            fallbackBaseName: buildFiscalFileBaseName(),
            successMessage: 'Impressão fiscal enviada ao navegador.',
        });

        if (result.success) {
            fiscalActionFeedback.value = result.message;
            return;
        }

        fiscalActionError.value = result.message || 'Não foi possível imprimir o documento fiscal.';
    } finally {
        fiscalActionLoading.print = false;
    }
}

async function handlePrintThermalReceipt() {
    if (!selectedSale.value || fiscalActionLoading.printThermal || !canUseLocalPrinter.value) return;

    clearFiscalActionStatus();
    fiscalActionLoading.printThermal = true;

    try {
        const state = printer.value.status === 'connected'
            ? printer.value
            : await connectLocalPrinter(printer.value.supportsSerial ? 'serial' : 'auto');

        if (state.status !== 'connected') {
            fiscalActionError.value = state.lastError || 'Não foi possível conectar a impressora térmica.';
            return;
        }

        const payload = renderSaleReceiptEscPos({
            receipt: {
                id: selectedSale.value.id,
                number: selectedSale.value.numero,
                series: fiscalSeries.value || selectedSale.value?.fiscal?.series || '1',
                total: selectedSale.value.total_financeiro,
                status: selectedSale.value.status_label,
                sold_at: selectedSale.value.sold_at,
                fiscal: selectedSale.value.fiscal || null,
            },
            sale: selectedSale.value,
            items: saleItems.value,
            payments: salePayments.value,
            customer: {
                nome: selectedSale.value.cliente_nome,
            },
            totals: {
                total_financeiro: selectedSale.value.total_financeiro,
                change_total: changeTotal.value,
            },
        });

        await printLocalPayload(payload);
        fiscalActionFeedback.value = 'Cupom térmico enviado para a impressora.';
    } catch (error) {
        fiscalActionError.value = error?.message || 'Não foi possível imprimir o cupom térmico.';
    } finally {
        fiscalActionLoading.printThermal = false;
    }
}

async function handleViewFiscal() {
    if (!viewFiscalUrl.value || fiscalActionLoading.view) return;

    clearFiscalActionStatus();
    fiscalActionLoading.view = true;

    try {
        const result = await openFiscalPdf(viewFiscalUrl.value, {
            fallbackBaseName: buildFiscalFileBaseName(),
        });

        if (!result.success) {
            fiscalActionError.value = result.message || 'Não foi possível abrir o documento fiscal.';
        }
    } finally {
        fiscalActionLoading.view = false;
    }
}

async function handleDownloadXml() {
    if (!downloadXmlUrl.value || fiscalActionLoading.downloadXml) return;

    clearFiscalActionStatus();
    fiscalActionLoading.downloadXml = true;

    try {
        const result = await downloadFiscalArtifact(downloadXmlUrl.value, {
            extension: 'xml',
            accept: 'application/xml,text/xml,*/*',
            fallbackBaseName: buildFiscalFileBaseName(),
            successMessage: 'XML fiscal preparado para download.',
        });

        if (result.success) {
            fiscalActionFeedback.value = result.message;
            return;
        }

        fiscalActionError.value = result.message || 'Não foi possível baixar o XML fiscal.';
    } finally {
        fiscalActionLoading.downloadXml = false;
    }
}

function openCancelModal() {
    cancelModal.error = '';
    cancelModal.reason = '';
    cancelModal.open = true;
}

function closeCancelModal() {
    cancelModal.open = false;
    cancelModal.loading = false;
    cancelModal.error = '';
    cancelModal.reason = '';
}

async function loadSales() {
    loadingSales.value = true;
    pageError.value = '';

    try {
        const { data } = await api.get('/sales');
        sales.value = Array.isArray(data) ? data : [];
    } catch (requestError) {
        sales.value = [];
        pageError.value = requestError?.response?.data?.message ?? 'Falha ao carregar histórico de vendas.';
    } finally {
        loadingSales.value = false;
    }
}

async function loadSaleDetail(saleId) {
    if (!saleId) {
        selectedSale.value = null;
        clearFiscalActionStatus();
        return;
    }

    loadingDetail.value = true;
    pageError.value = '';
    clearFiscalActionStatus();

    try {
        const { data } = await api.get(`/sales/${saleId}`);
        selectedSale.value = data;
    } catch (requestError) {
        selectedSale.value = null;
        pageError.value = requestError?.response?.data?.message ?? 'Falha ao carregar detalhes da venda.';
    } finally {
        loadingDetail.value = false;
    }
}

async function openSaleDetails(saleId) {
    await router.replace({
        path: '/configuracoes/vendas',
        query: {
            sale_id: saleId,
        },
    });
}

async function backToList() {
    await router.replace({
        path: '/configuracoes/vendas',
        query: {},
    });
}

async function submitCancelSale() {
    if (!selectedSale.value) return;

    const reason = String(cancelModal.reason || '').trim();
    if (!reason) {
        cancelModal.error = 'Informe o motivo do cancelamento.';
        return;
    }

    cancelModal.error = '';
    cancelModal.loading = true;

    try {
        await api.post(`/sales/${selectedSale.value.id}/cancel`, {
            motivo: reason,
        });

        closeCancelModal();
        await Promise.all([
            loadSales(),
            loadSaleDetail(selectedSale.value.id),
        ]);
    } catch (requestError) {
        const validationErrors = requestError?.response?.data?.errors || {};
        if (Array.isArray(validationErrors.motivo) && validationErrors.motivo.length) {
            cancelModal.error = validationErrors.motivo[0];
        } else if (Array.isArray(validationErrors.status) && validationErrors.status.length) {
            cancelModal.error = validationErrors.status[0];
        } else {
            cancelModal.error = requestError?.response?.data?.message ?? 'Não foi possível cancelar a venda.';
        }
    } finally {
        cancelModal.loading = false;
    }
}

watch(
    () => currentSaleId.value,
    async (nextSaleId) => {
        if (!nextSaleId) {
            selectedSale.value = null;
            return;
        }

        if (selectedSale.value?.id === nextSaleId) return;
        await loadSaleDetail(nextSaleId);
    },
);

onMounted(async () => {
    await loadSales();

    if (currentSaleId.value) {
        await loadSaleDetail(currentSaleId.value);
    }

    timerIntervalId = window.setInterval(() => {
        nowTick.value = Date.now();
    }, 1000);
});

onUnmounted(() => {
    if (timerIntervalId) {
        clearInterval(timerIntervalId);
        timerIntervalId = null;
    }
});
</script>

<template>
    <div class="space-y-4">
        <SettingsPageHeader
            :title="selectedSale ? `Venda #${selectedSale.numero}` : 'Vendas'"
            :subtitle="selectedSale ? formatDateTime(selectedSale.sold_at) : 'Histórico de vendas realizadas'"
        >
            <template #actions>
                <template v-if="selectedSale">
                    <AppButton variant="secondary" @click="backToList">
                        <ArrowLeft class="h-4 w-4" aria-hidden="true" />
                        Voltar
                    </AppButton>
                </template>
                <template v-else>
                    <AppButton @click="router.push('/')">
                        <ShoppingBag class="h-4 w-4" aria-hidden="true" />
                        Ir para PDV
                    </AppButton>
                </template>
            </template>
        </SettingsPageHeader>

        <p v-if="pageError" class="text-sm text-danger">{{ pageError }}</p>

        <div v-if="currentSaleId && loadingDetail" class="ui-card p-5 text-muted">Carregando venda...</div>

        <template v-else-if="selectedSale">
            <section class="sale-detail-shell">
                <div class="sale-detail-main">
                    <article class="ui-card sale-hero-card">
                        <div class="sale-hero-content">
                            <p class="sale-hero-overline">Ficha administrativa de conferência</p>
                            <h2 class="sale-hero-title">Venda #{{ selectedSale.numero }}</h2>

                            <p class="sale-hero-date">
                                <Calendar class="h-4 w-4" aria-hidden="true" />
                                {{ formatDateTime(selectedSale.sold_at) }}
                            </p>

                            <div class="sale-hero-badges">
                                <AppBadge :variant="statusVariant(selectedSale.status)">
                                    {{ statusLabel(selectedSale.status) }}
                                </AppBadge>
                                <AppBadge variant="warning">
                                    {{ fallbackText(selectedSale.document_label, 'Sem modelo fiscal') }}
                                </AppBadge>
                            </div>

                            <div class="sale-hero-meta-grid">
                                <div class="sale-hero-meta-item">
                                    <span>
                                        <User class="h-4 w-4" aria-hidden="true" />
                                        Operador
                                    </span>
                                    <strong>{{ fallbackText(selectedSale?.creator?.name, 'Não informado') }}</strong>
                                </div>

                                <div class="sale-hero-meta-item">
                                    <span>
                                        <Monitor class="h-4 w-4" aria-hidden="true" />
                                        Terminal/Caixa
                                    </span>
                                    <strong>
                                        {{ fallbackText(firstFilled([
                                            selectedSale.terminal_nome,
                                            selectedSale.caixa_nome,
                                            selectedSale?.terminal?.nome,
                                            selectedSale?.cash_register?.nome,
                                        ]), 'Não informado') }}
                                    </strong>
                                </div>

                                <div class="sale-hero-meta-item">
                                    <span>
                                        <Hash class="h-4 w-4" aria-hidden="true" />
                                        Cliente
                                    </span>
                                    <strong>{{ fallbackText(selectedSale.cliente_nome, 'Não informado') }}</strong>
                                </div>
                            </div>
                        </div>

                        <div class="sale-hero-total-card">
                            <p class="sale-hero-total-label">Total financeiro</p>
                            <strong class="sale-hero-total-value">{{ formatCurrency(selectedSale.total_financeiro) }}</strong>
                            <p class="sale-hero-total-meta">Subtotal fiscal: {{ formatCurrency(fiscalSubtotal) }}</p>
                            <p class="sale-hero-total-meta">Itens vendidos: {{ formatQuantity(itemsSoldCount) }}</p>
                        </div>
                    </article>

                    <div class="sale-status-timer" :class="{ 'is-expired': !cancellationMeta.canCancel }">
                        {{ cancellationMeta.message }}
                    </div>

                    <section class="sale-summary-cards" aria-label="Resumo rápido da venda">
                        <article v-for="card in detailSummaryCards" :key="card.id" class="ui-card sale-summary-card" :class="{ 'is-emphasis': card.emphasis }">
                            <p class="sale-summary-card-label">{{ card.label }}</p>
                            <p class="sale-summary-card-value">{{ card.value }}</p>
                        </article>
                    </section>

                    <article class="ui-card sale-section-card">
                        <header class="sale-section-header">
                            <h3>
                                <ClipboardList class="h-5 w-5" aria-hidden="true" />
                                Informações da venda
                            </h3>
                        </header>

                        <div class="sale-info-grid">
                            <div v-for="entry in saleInfoRows" :key="entry.label" class="sale-info-item">
                                <span>{{ entry.label }}</span>
                                <strong>{{ entry.value }}</strong>
                            </div>
                        </div>
                    </article>

                    <article class="ui-card sale-section-card">
                        <header class="sale-section-header">
                            <h3>
                                <Package class="h-5 w-5" aria-hidden="true" />
                                Itens vendidos
                            </h3>
                        </header>

                        <div v-if="saleItems.length" class="sale-items-shell">
                            <div class="sale-items-table-head">
                                <span>Produto</span>
                                <span>Código/SKU/EAN</span>
                                <span>Quantidade</span>
                                <span>Valor unitário</span>
                                <span class="text-right">Total</span>
                            </div>

                            <div v-for="item in saleItems" :key="item.id" class="sale-items-row">
                                <div class="sale-items-product">
                                    <p>{{ fallbackText(item.produto_nome, 'Item sem nome') }}</p>
                                    <div class="sale-item-badges">
                                        <AppBadge v-if="item.cancelado" variant="danger">Item cancelado</AppBadge>
                                        <AppBadge v-if="Number(item.desconto || 0) > 0" variant="warning">Desconto</AppBadge>
                                        <AppBadge v-if="Number(item.acrescimo || 0) > 0" variant="warning">Acréscimo</AppBadge>
                                    </div>
                                </div>
                                <p class="sale-items-muted">{{ itemIdentifier(item) }}</p>
                                <p>{{ formatQuantity(item.quantidade) }} {{ fallbackText(item.unidade, '') }}</p>
                                <p>{{ formatCurrency(item.valor_unitario) }}</p>
                                <strong class="sale-items-total">{{ formatCurrency(item.valor_total) }}</strong>
                            </div>
                        </div>

                        <SettingsEmptyState
                            v-else
                            title="Sem itens vinculados"
                            description="Nenhum item foi registrado para esta venda."
                        />
                    </article>

                    <article class="ui-card sale-section-card">
                        <header class="sale-section-header">
                            <h3>
                                <CreditCard class="h-5 w-5" aria-hidden="true" />
                                Pagamentos
                            </h3>
                        </header>

                        <div v-if="salePayments.length" class="sale-payments-list">
                            <article v-for="payment in salePayments" :key="payment.id" class="sale-payment-card">
                                <div class="sale-payment-card-main">
                                    <p class="sale-payment-title">{{ fallbackText(payment.metodo_nome, 'Pagamento') }}</p>
                                    <p v-if="payment.descricao" class="sale-payment-note">{{ payment.descricao }}</p>

                                    <div class="sale-payment-meta-grid">
                                        <p><span>Valor pago</span><strong>{{ formatCurrency(payment.valor) }}</strong></p>
                                        <p>
                                            <span>Valor recebido</span>
                                            <strong>
                                                {{ hasValue(paymentField(payment, ['valor_recebido', 'received_amount']))
                                                    ? formatCurrency(paymentField(payment, ['valor_recebido', 'received_amount']))
                                                    : '-' }}
                                            </strong>
                                        </p>
                                        <p>
                                            <span>Troco</span>
                                            <strong>
                                                {{ hasValue(paymentField(payment, ['troco', 'change']))
                                                    ? formatCurrency(paymentField(payment, ['troco', 'change']))
                                                    : '-' }}
                                            </strong>
                                        </p>
                                        <p>
                                            <span>NSU/Autorização</span>
                                            <strong>{{ fallbackText(paymentField(payment, ['nsu', 'autorizacao', 'authorization_code']), '-') }}</strong>
                                        </p>
                                    </div>
                                </div>

                                <strong class="sale-payment-value">{{ formatCurrency(payment.valor) }}</strong>
                            </article>
                        </div>

                        <SettingsEmptyState
                            v-else
                            title="Sem pagamentos registrados"
                            description="Nenhum pagamento foi encontrado para esta venda."
                        />
                    </article>

                    <article class="ui-card sale-section-card sale-financial-card">
                        <header class="sale-section-header">
                            <h3>
                                <Wallet class="h-5 w-5" aria-hidden="true" />
                                Resumo financeiro
                            </h3>
                        </header>

                        <div class="sale-financial-rows">
                            <div class="sale-financial-row">
                                <span>Subtotal dos itens</span>
                                <strong>{{ formatCurrency(selectedSale.total_bruto) }}</strong>
                            </div>
                            <div class="sale-financial-row">
                                <span>Desconto</span>
                                <strong>{{ formatCurrency(discountTotal) }}</strong>
                            </div>
                            <div class="sale-financial-row">
                                <span>Acréscimo/Juros</span>
                                <strong>{{ formatCurrency(surchargeTotal) }}</strong>
                            </div>
                            <div class="sale-financial-row">
                                <span>Total fiscal</span>
                                <strong>{{ formatCurrency(fiscalSubtotal) }}</strong>
                            </div>
                            <div class="sale-financial-row is-total">
                                <span>Total financeiro</span>
                                <strong>{{ formatCurrency(selectedSale.total_financeiro) }}</strong>
                            </div>
                            <div class="sale-financial-row">
                                <span>Valor pago</span>
                                <strong>{{ formatCurrency(paidTotal) }}</strong>
                            </div>
                            <div class="sale-financial-row">
                                <span>Troco</span>
                                <strong>{{ formatCurrency(changeTotal) }}</strong>
                            </div>
                        </div>
                    </article>

                    <article class="ui-card sale-section-card">
                        <header class="sale-section-header">
                            <h3>
                                <FileText class="h-5 w-5" aria-hidden="true" />
                                Informações fiscais
                            </h3>
                        </header>

                        <div v-if="hasLinkedFiscalDocument" class="sale-info-grid">
                            <div class="sale-info-item"><span>Modelo fiscal</span><strong>{{ fiscalModel }}</strong></div>
                            <div class="sale-info-item"><span>Status da NFC-e</span><strong>{{ fiscalStatus }}</strong></div>
                            <div class="sale-info-item"><span>Número fiscal</span><strong>{{ fallbackText(fiscalNumber, '-') }}</strong></div>
                            <div class="sale-info-item"><span>Série</span><strong>{{ fallbackText(fiscalSeries, '-') }}</strong></div>
                            <div class="sale-info-item"><span>Chave de acesso</span><strong>{{ fallbackText(fiscalAccessKey, '-') }}</strong></div>
                            <div class="sale-info-item"><span>Protocolo</span><strong>{{ fallbackText(fiscalProtocol, '-') }}</strong></div>
                            <div class="sale-info-item"><span>Data de autorização</span><strong>{{ fallbackText(formatDateTime(fiscalAuthorizedAt), '-') }}</strong></div>
                            <div class="sale-info-item"><span>Status de cancelamento</span><strong>{{ fiscalCancelStatus }}</strong></div>
                            <div class="sale-info-item sale-info-item--wide">
                                <span>Motivo de indisponibilidade de cancelamento</span>
                                <strong>{{ cancellationMeta.canCancel ? 'Cancelamento disponível no prazo.' : fallbackText(cancellationMeta.message, '-') }}</strong>
                            </div>
                        </div>

                        <div v-else class="sale-empty-inline">
                            Esta venda não possui documento fiscal vinculado.
                        </div>

                        <div v-if="fiscalCancelBlockMessage" class="sale-warning-block">
                            <ShieldAlert class="h-4 w-4" aria-hidden="true" />
                            <p>{{ fiscalCancelBlockMessage }}</p>
                        </div>
                    </article>

                    <article class="ui-card sale-section-card">
                        <header class="sale-section-header">
                            <h3>
                                <History class="h-5 w-5" aria-hidden="true" />
                                Linha do tempo
                            </h3>
                        </header>

                        <ol v-if="timelineEvents.length" class="sale-timeline-list">
                            <li v-for="event in timelineEvents" :key="event.id" class="sale-timeline-item">
                                <div class="sale-timeline-dot" aria-hidden="true"></div>
                                <div class="sale-timeline-content">
                                    <p class="sale-timeline-headline">{{ event.type }}</p>
                                    <p class="sale-timeline-meta">
                                        {{ formatDateTime(event.at) }} · Responsável: {{ event.responsible }}
                                    </p>
                                    <p v-if="event.note" class="sale-timeline-note">{{ event.note }}</p>
                                </div>
                            </li>
                        </ol>

                        <div v-else class="sale-empty-inline">
                            Nenhum evento registrado para esta venda.
                        </div>
                    </article>
                </div>

                <aside class="sale-detail-aside">
                    <article class="ui-card sale-sticky-card">
                        <p class="sale-sticky-overline">Resumo da venda</p>
                        <h3>Venda #{{ selectedSale.numero }}</h3>

                        <div class="sale-sticky-rows">
                            <div><span>Status</span><strong>{{ statusLabel(selectedSale.status) }}</strong></div>
                            <div><span>Total</span><strong class="text-success">{{ formatCurrency(selectedSale.total_financeiro) }}</strong></div>
                            <div><span>Pagamento</span><strong>{{ primaryPayment }}</strong></div>
                            <div><span>Operador</span><strong>{{ fallbackText(selectedSale?.creator?.name, 'Não informado') }}</strong></div>
                            <div>
                                <span>Terminal</span>
                                <strong>{{ fallbackText(firstFilled([
                                    selectedSale.terminal_nome,
                                    selectedSale.caixa_nome,
                                    selectedSale?.terminal?.nome,
                                    selectedSale?.cash_register?.nome,
                                ]), 'Não informado') }}</strong>
                            </div>
                            <div><span>Status fiscal</span><strong>{{ fiscalStatus }}</strong></div>
                        </div>

                        <div class="sale-sticky-actions">
                            <AppButton variant="secondary" block @click="backToList">
                                <ArrowLeft class="h-4 w-4" aria-hidden="true" />
                                Voltar
                            </AppButton>

                            <AppButton v-if="printReceiptUrl" variant="secondary" block @click="openExternalLink(printReceiptUrl)">
                                <Printer class="h-4 w-4" aria-hidden="true" />
                                Imprimir comprovante
                            </AppButton>

                            <AppButton
                                variant="secondary"
                                block
                                :disabled="!canUseLocalPrinter"
                                :loading="fiscalActionLoading.printThermal"
                                @click="handlePrintThermalReceipt"
                            >
                                <Printer class="h-4 w-4" aria-hidden="true" />
                                Imprimir cupom térmico
                            </AppButton>

                            <AppButton
                                v-if="printFiscalUrl"
                                variant="secondary"
                                block
                                :loading="fiscalActionLoading.print"
                                @click="handlePrintFiscal"
                            >
                                <Printer class="h-4 w-4" aria-hidden="true" />
                                Imprimir {{ fiscalDocumentLabel }}
                            </AppButton>

                            <AppButton
                                v-if="viewFiscalUrl"
                                variant="secondary"
                                block
                                :loading="fiscalActionLoading.view"
                                @click="handleViewFiscal"
                            >
                                <FileSearch class="h-4 w-4" aria-hidden="true" />
                                Ver {{ fiscalDocumentLabel }}
                            </AppButton>

                            <AppButton
                                v-if="downloadXmlUrl"
                                variant="secondary"
                                block
                                :loading="fiscalActionLoading.downloadXml"
                                @click="handleDownloadXml"
                            >
                                <FileDown class="h-4 w-4" aria-hidden="true" />
                                Baixar XML
                            </AppButton>

                            <AppButton
                                variant="danger"
                                block
                                :disabled="!cancellationMeta.canCancel"
                                @click="openCancelModal"
                            >
                                <CircleX class="h-4 w-4" aria-hidden="true" />
                                Cancelar venda
                            </AppButton>
                        </div>

                        <p v-if="fiscalActionError" class="text-sm text-danger">{{ fiscalActionError }}</p>
                        <p v-else-if="fiscalActionFeedback" class="text-sm text-success">{{ fiscalActionFeedback }}</p>

                        <p v-if="!cancellationMeta.canCancel" class="sale-sticky-hint">
                            {{ cancellationMeta.message }}
                        </p>
                    </article>
                </aside>
            </section>
        </template>

        <template v-else>
            <div class="sales-summary-grid">
                <AppCard>
                    <p class="sales-card-label">VENDAS HOJE</p>
                    <p class="sales-card-value text-success">{{ formatCurrency(summary.todayRevenue) }}</p>
                    <p class="sales-card-meta">{{ summary.todayCount }} vendas</p>
                </AppCard>

                <AppCard>
                    <p class="sales-card-label">TOTAL RECEBIDO</p>
                    <p class="sales-card-value">{{ formatCurrency(summary.totalRevenue) }}</p>
                    <p class="sales-card-meta">{{ summary.totalCount }} vendas</p>
                </AppCard>
            </div>

            <SettingsFilterBar>
                <div class="sales-search">
                    <AppSearchField v-model="search" placeholder="Buscar por número ou cliente..." />
                </div>

                <div class="sales-status-filters">
                    <button
                        type="button"
                        class="sales-filter-chip"
                        :class="{ 'is-active': statusFilter === 'todas' }"
                        @click="statusFilter = 'todas'"
                    >
                        Todas
                    </button>
                    <button
                        type="button"
                        class="sales-filter-chip"
                        :class="{ 'is-active': statusFilter === 'finalizada' }"
                        @click="statusFilter = 'finalizada'"
                    >
                        Finalizadas
                    </button>
                    <button
                        type="button"
                        class="sales-filter-chip"
                        :class="{ 'is-active': statusFilter === 'cancelada' }"
                        @click="statusFilter = 'cancelada'"
                    >
                        Canceladas
                    </button>
                </div>
            </SettingsFilterBar>

            <div class="sales-list">
                <div v-if="loadingSales" class="ui-card p-5 text-muted">Carregando vendas...</div>

                <SettingsEmptyState
                    v-else-if="filteredSales.length === 0"
                    title="Nenhuma venda encontrada"
                    description="Ajuste os filtros para localizar as vendas no período."
                />

                <template v-else>
                    <article
                        v-for="sale in filteredSales"
                        :key="sale.id"
                        :class="rowClass(sale.status)"
                    >
                        <div class="sale-row-main">
                            <p class="sale-row-title">
                                #{{ sale.numero }}
                                <AppBadge :variant="statusVariant(sale.status)">
                                    {{ statusLabel(sale.status) }}
                                </AppBadge>
                            </p>
                            <p class="sale-row-meta">{{ formatDateTime(sale.sold_at) }}</p>
                            <p v-if="Number(sale.juros_total) > 0" class="sale-row-interest">Juros: {{ formatCurrency(sale.juros_total) }}</p>
                        </div>

                        <div class="sale-row-right">
                            <strong>{{ formatCurrency(sale.total_financeiro) }}</strong>
                            <AppIconButton title="Ver venda" @click="openSaleDetails(sale.id)">
                                <Eye class="h-4 w-4" aria-hidden="true" />
                            </AppIconButton>
                        </div>
                    </article>
                </template>
            </div>
        </template>

        <AppModal
            :open="cancelModal.open"
            :title="selectedSale ? `Cancelar Venda #${selectedSale.numero}` : 'Cancelar Venda'"
            width-class="max-w-2xl"
            @close="closeCancelModal"
        >
            <div class="space-y-4">
                <p class="text-sm text-muted">O estoque será estornado para os itens desta venda.</p>

                <AppTextarea
                    v-model="cancelModal.reason"
                    label="Motivo do cancelamento *"
                    rows="4"
                    placeholder="Informe o motivo..."
                />

                <p v-if="cancelModal.error" class="text-sm text-danger">{{ cancelModal.error }}</p>

                <div class="sales-modal-actions">
                    <AppButton variant="secondary" @click="closeCancelModal">Voltar</AppButton>
                    <AppButton variant="danger" :loading="cancelModal.loading" @click="submitCancelSale">
                        Confirmar Cancelamento
                    </AppButton>
                </div>
            </div>
        </AppModal>
    </div>
</template>

<style scoped>
.sales-summary-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
}

.sales-card-label {
    margin: 0;
    color: var(--color-text-muted);
    font-size: 0.8rem;
    letter-spacing: 0.03em;
    font-weight: 700;
}

.sales-card-value {
    margin: 0.2rem 0 0;
    color: var(--color-text);
    font-size: 1.75rem;
    font-weight: 900;
    line-height: 1;
}

.sales-card-meta {
    margin: 0.4rem 0 0;
    color: var(--color-text-muted);
    font-size: 0.88rem;
}

.sales-search {
    flex: 1 1 20rem;
    min-width: 16rem;
}

.sales-status-filters {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.sales-filter-chip {
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 70%, transparent);
    border-radius: 999px;
    padding: 0.3rem 0.72rem;
    background: var(--color-bg-surface);
    color: var(--color-text-muted);
    font-size: 0.84rem;
    font-weight: 700;
    transition: all var(--transition-fast);
}

.sales-filter-chip.is-active {
    border-color: color-mix(in srgb, var(--color-primary) 60%, transparent);
    background: var(--color-primary);
    color: var(--color-text-inverse);
}

.sales-list {
    display: grid;
    gap: 0.65rem;
}

.sale-row {
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 55%, transparent);
    background: var(--color-bg-surface);
    border-radius: var(--radius-lg);
    padding: 0.72rem 0.92rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.82rem;
}

.sale-row.is-canceled {
    opacity: 0.85;
    background: color-mix(in srgb, var(--color-danger) 5%, var(--color-bg-surface));
}

.sale-row-main {
    min-width: 0;
}

.sale-row-title {
    margin: 0;
    color: var(--color-text);
    font-size: 1.1rem;
    line-height: 1.15;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
}

.sale-row-meta {
    margin: 0.34rem 0 0;
    color: var(--color-text-muted);
    font-size: 0.9rem;
}

.sale-row-interest {
    margin: 0.34rem 0 0;
    display: inline-flex;
    border: 1px solid color-mix(in srgb, #f59e0b 65%, transparent);
    color: #b45309;
    background: color-mix(in srgb, #f59e0b 9%, var(--color-bg-surface));
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.16rem 0.46rem;
}

.sale-row-right {
    display: inline-flex;
    align-items: center;
    gap: 0.68rem;
}

.sale-row-right strong {
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--color-text);
}

.sale-detail-shell {
    display: grid;
    gap: 1rem;
    align-items: start;
    grid-template-columns: minmax(0, 1fr);
}

.sale-detail-main {
    display: grid;
    gap: 1rem;
}

.sale-hero-card {
    padding: 1.1rem 1.2rem;
    display: grid;
    gap: 1rem;
    border: 1px solid color-mix(in srgb, var(--color-primary) 25%, var(--color-border));
    background: linear-gradient(150deg, color-mix(in srgb, var(--color-primary) 8%, var(--color-bg-surface)) 0%, var(--color-bg-surface) 56%);
}

.sale-hero-content {
    display: grid;
    gap: 0.55rem;
}

.sale-hero-overline {
    margin: 0;
    color: var(--color-text-muted);
    font-size: 0.74rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-weight: 800;
}

.sale-hero-title {
    margin: 0;
    color: var(--color-text);
    font-size: clamp(1.35rem, 2.8vw, 1.9rem);
    font-weight: 900;
    line-height: 1.1;
}

.sale-hero-date {
    margin: 0;
    color: var(--color-text-muted);
    font-size: 0.91rem;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.sale-hero-badges {
    display: inline-flex;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.sale-hero-meta-grid {
    display: grid;
    gap: 0.65rem;
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.sale-hero-meta-item {
    border: 1px solid color-mix(in srgb, var(--color-border) 70%, transparent);
    border-radius: 0.8rem;
    background: color-mix(in srgb, var(--color-bg-soft) 72%, transparent);
    padding: 0.58rem 0.65rem;
    display: grid;
    gap: 0.32rem;
}

.sale-hero-meta-item span {
    color: var(--color-text-muted);
    font-size: 0.76rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.sale-hero-meta-item strong {
    color: var(--color-text);
    font-size: 0.92rem;
    font-weight: 800;
    line-height: 1.2;
}

.sale-hero-total-card {
    border: 1px solid color-mix(in srgb, var(--color-success) 40%, transparent);
    border-radius: 0.95rem;
    background: color-mix(in srgb, var(--color-success) 10%, var(--color-bg-surface));
    padding: 0.8rem;
    display: grid;
    align-content: start;
    gap: 0.3rem;
    min-width: min(100%, 15rem);
}

.sale-hero-total-label {
    margin: 0;
    color: color-mix(in srgb, var(--color-success) 80%, var(--color-text));
    font-size: 0.76rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.07em;
}

.sale-hero-total-value {
    color: var(--color-success);
    font-size: 1.5rem;
    font-weight: 900;
    line-height: 1.05;
}

.sale-hero-total-meta {
    margin: 0;
    color: var(--color-text-muted);
    font-size: 0.82rem;
}

.sale-status-timer {
    font-size: 0.88rem;
    font-weight: 700;
    color: #b45309;
    background: color-mix(in srgb, #f59e0b 10%, var(--color-bg-surface));
    border: 1px solid color-mix(in srgb, #f59e0b 50%, transparent);
    border-radius: 0.75rem;
    padding: 0.48rem 0.7rem;
}

.sale-status-timer.is-expired {
    color: var(--color-danger);
    background: color-mix(in srgb, var(--color-danger) 9%, var(--color-bg-surface));
    border-color: color-mix(in srgb, var(--color-danger) 50%, transparent);
}

.sale-summary-cards {
    display: grid;
    gap: 0.72rem;
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.sale-summary-card {
    padding: 0.78rem 0.86rem;
    border-radius: 0.9rem;
    display: grid;
    gap: 0.24rem;
}

.sale-summary-card.is-emphasis {
    border-color: color-mix(in srgb, var(--color-success) 45%, transparent);
    background: color-mix(in srgb, var(--color-success) 8%, var(--color-bg-surface));
}

.sale-summary-card-label {
    margin: 0;
    color: var(--color-text-muted);
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 800;
}

.sale-summary-card-value {
    margin: 0;
    color: var(--color-text);
    font-size: 1rem;
    font-weight: 800;
    line-height: 1.2;
}

.sale-section-card {
    padding: 1rem;
    display: grid;
    gap: 0.85rem;
    border-radius: 1rem;
}

.sale-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.65rem;
    border-bottom: 1px solid color-mix(in srgb, var(--color-border) 60%, transparent);
    padding-bottom: 0.68rem;
}

.sale-section-header h3 {
    margin: 0;
    color: var(--color-text);
    font-size: 1rem;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.sale-info-grid {
    display: grid;
    gap: 0.72rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.sale-info-item {
    border: 1px solid color-mix(in srgb, var(--color-border) 70%, transparent);
    border-radius: 0.82rem;
    padding: 0.6rem 0.68rem;
    display: grid;
    gap: 0.28rem;
    background: color-mix(in srgb, var(--color-bg-soft) 74%, transparent);
}

.sale-info-item--wide {
    grid-column: 1 / -1;
}

.sale-info-item span {
    color: var(--color-text-muted);
    font-size: 0.77rem;
    font-weight: 700;
}

.sale-info-item strong {
    color: var(--color-text);
    font-size: 0.9rem;
    font-weight: 800;
    line-height: 1.25;
}

.sale-items-shell {
    display: grid;
}

.sale-items-table-head,
.sale-items-row {
    display: grid;
    gap: 0.7rem;
    grid-template-columns: minmax(13rem, 2fr) minmax(8rem, 1.1fr) minmax(7rem, 0.8fr) minmax(7rem, 0.9fr) minmax(7rem, 0.9fr);
    align-items: center;
}

.sale-items-table-head {
    color: var(--color-text-muted);
    font-size: 0.74rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    font-weight: 800;
    padding: 0 0 0.62rem;
}

.sale-items-row {
    border-top: 1px solid color-mix(in srgb, var(--color-border) 65%, transparent);
    padding: 0.72rem 0;
}

.sale-items-product p {
    margin: 0;
    color: var(--color-text);
    font-weight: 800;
    font-size: 0.92rem;
}

.sale-items-muted {
    margin: 0;
    color: var(--color-text-muted);
    font-size: 0.84rem;
}

.sale-item-badges {
    display: inline-flex;
    gap: 0.3rem;
    margin-top: 0.28rem;
    flex-wrap: wrap;
}

.sale-items-row p {
    margin: 0;
    color: var(--color-text);
    font-size: 0.88rem;
}

.sale-items-total {
    justify-self: end;
    color: var(--color-text);
    font-size: 1rem;
    font-weight: 900;
}

.sale-payments-list {
    display: grid;
    gap: 0.72rem;
}

.sale-payment-card {
    border: 1px solid color-mix(in srgb, var(--color-border) 65%, transparent);
    border-radius: 0.86rem;
    padding: 0.72rem 0.78rem;
    display: flex;
    justify-content: space-between;
    gap: 0.72rem;
    align-items: flex-start;
    background: color-mix(in srgb, var(--color-bg-soft) 70%, transparent);
}

.sale-payment-card-main {
    min-width: 0;
    flex: 1;
}

.sale-payment-title {
    margin: 0;
    color: var(--color-text);
    font-size: 0.95rem;
    font-weight: 800;
}

.sale-payment-note {
    margin: 0.22rem 0 0;
    color: var(--color-text-muted);
    font-size: 0.82rem;
}

.sale-payment-meta-grid {
    margin-top: 0.55rem;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.48rem;
}

.sale-payment-meta-grid p {
    margin: 0;
    border: 1px solid color-mix(in srgb, var(--color-border) 60%, transparent);
    border-radius: 0.72rem;
    padding: 0.42rem 0.48rem;
    display: grid;
    gap: 0.22rem;
    background: color-mix(in srgb, var(--color-bg-surface) 80%, transparent);
}

.sale-payment-meta-grid span {
    color: var(--color-text-muted);
    font-size: 0.72rem;
    font-weight: 700;
}

.sale-payment-meta-grid strong {
    color: var(--color-text);
    font-size: 0.85rem;
    font-weight: 800;
}

.sale-payment-value {
    color: var(--color-text);
    font-size: 1rem;
    font-weight: 900;
    white-space: nowrap;
}

.sale-financial-card {
    border-color: color-mix(in srgb, var(--color-success) 35%, transparent);
}

.sale-financial-rows {
    display: grid;
}

.sale-financial-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid color-mix(in srgb, var(--color-border) 65%, transparent);
    padding: 0.56rem 0;
    gap: 0.65rem;
}

.sale-financial-row:first-child {
    border-top: 0;
    padding-top: 0;
}

.sale-financial-row span {
    color: var(--color-text-muted);
    font-size: 0.87rem;
    font-weight: 700;
}

.sale-financial-row strong {
    color: var(--color-text);
    font-size: 0.95rem;
    font-weight: 800;
}

.sale-financial-row.is-total span,
.sale-financial-row.is-total strong {
    color: var(--color-success);
}

.sale-empty-inline {
    border: 1px dashed color-mix(in srgb, var(--color-border-strong) 75%, transparent);
    border-radius: 0.8rem;
    padding: 0.8rem;
    color: var(--color-text-muted);
    font-size: 0.88rem;
    background: color-mix(in srgb, var(--color-bg-soft) 72%, transparent);
}

.sale-warning-block {
    margin-top: 0.35rem;
    border: 1px solid color-mix(in srgb, var(--color-danger) 45%, transparent);
    border-radius: 0.8rem;
    background: color-mix(in srgb, var(--color-danger) 8%, var(--color-bg-surface));
    color: var(--color-danger);
    padding: 0.62rem 0.68rem;
    display: inline-flex;
    align-items: flex-start;
    gap: 0.4rem;
}

.sale-warning-block p {
    margin: 0;
    font-size: 0.84rem;
    font-weight: 700;
    line-height: 1.35;
}

.sale-timeline-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 0.7rem;
}

.sale-timeline-item {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    gap: 0.6rem;
    align-items: flex-start;
}

.sale-timeline-dot {
    width: 0.66rem;
    height: 0.66rem;
    margin-top: 0.35rem;
    border-radius: 999px;
    background: color-mix(in srgb, var(--color-primary) 70%, transparent);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--color-primary) 15%, transparent);
}

.sale-timeline-content {
    border: 1px solid color-mix(in srgb, var(--color-border) 65%, transparent);
    border-radius: 0.8rem;
    padding: 0.6rem 0.68rem;
    background: color-mix(in srgb, var(--color-bg-soft) 75%, transparent);
}

.sale-timeline-headline {
    margin: 0;
    color: var(--color-text);
    font-size: 0.9rem;
    font-weight: 800;
}

.sale-timeline-meta {
    margin: 0.2rem 0 0;
    color: var(--color-text-muted);
    font-size: 0.8rem;
}

.sale-timeline-note {
    margin: 0.28rem 0 0;
    color: var(--color-text);
    font-size: 0.82rem;
}

.sale-detail-aside {
    display: block;
}

.sale-sticky-card {
    padding: 0.95rem;
    display: grid;
    gap: 0.75rem;
}

.sale-sticky-overline {
    margin: 0;
    color: var(--color-text-muted);
    font-size: 0.73rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    font-weight: 800;
}

.sale-sticky-card h3 {
    margin: 0;
    color: var(--color-text);
    font-size: 1.2rem;
    font-weight: 900;
}

.sale-sticky-rows {
    display: grid;
    gap: 0.45rem;
}

.sale-sticky-rows div {
    border: 1px solid color-mix(in srgb, var(--color-border) 65%, transparent);
    border-radius: 0.7rem;
    padding: 0.44rem 0.5rem;
    display: flex;
    justify-content: space-between;
    gap: 0.5rem;
    align-items: center;
    background: color-mix(in srgb, var(--color-bg-soft) 72%, transparent);
}

.sale-sticky-rows span {
    color: var(--color-text-muted);
    font-size: 0.78rem;
    font-weight: 700;
}

.sale-sticky-rows strong {
    color: var(--color-text);
    font-size: 0.82rem;
    font-weight: 800;
    text-align: right;
}

.sale-sticky-actions {
    display: grid;
    gap: 0.45rem;
}

.sale-sticky-hint {
    margin: 0;
    color: var(--color-text-muted);
    font-size: 0.78rem;
}

.sales-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
}

@media (min-width: 1100px) {
    .sale-detail-shell {
        grid-template-columns: minmax(0, 1fr) 19.2rem;
    }

    .sale-detail-aside {
        position: sticky;
        top: 1rem;
    }

    .sale-hero-card {
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: stretch;
    }
}

@media (max-width: 1000px) {
    .sales-summary-grid {
        grid-template-columns: 1fr;
    }

    .sale-row {
        flex-direction: column;
        align-items: flex-start;
    }

    .sale-row-right {
        width: 100%;
        justify-content: space-between;
    }

    .sale-row-title {
        font-size: 1rem;
    }

    .sale-row-right strong {
        font-size: 1rem;
    }

    .sale-hero-meta-grid,
    .sale-summary-cards,
    .sale-info-grid,
    .sale-payment-meta-grid {
        grid-template-columns: 1fr;
    }

    .sale-items-table-head {
        display: none;
    }

    .sale-items-row {
        grid-template-columns: 1fr;
        border: 1px solid color-mix(in srgb, var(--color-border) 65%, transparent);
        border-radius: 0.8rem;
        margin-top: 0.6rem;
        padding: 0.62rem;
        gap: 0.34rem;
    }

    .sale-items-row p,
    .sale-items-total {
        justify-self: flex-start;
    }

    .sale-payment-card {
        flex-direction: column;
    }

    .sale-payment-value {
        font-size: 0.94rem;
    }
}
</style>
