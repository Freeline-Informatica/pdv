<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { ArrowLeft, Eye, ReceiptText, RotateCcw } from 'lucide-vue-next';
import defaultApi from '../../lib/api';
import { formatCurrency } from '../../lib/format';
import AppButton from '../ui/AppButton.vue';
import AppModal from '../ui/AppModal.vue';
import AppTextarea from '../ui/AppTextarea.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    terminalId: { type: String, default: '' },
    apiClient: { type: Object, default: () => defaultApi },
});

const emit = defineEmits(['close', 'canceled']);
const sales = ref([]);
const cashSession = ref(null);
const selectedSale = ref(null);
const loading = ref(false);
const loadingDetail = ref(false);
const error = ref('');
const nowTick = ref(Date.now());
let timerId = null;

const cancellation = reactive({
    open: false,
    reason: '',
    error: '',
    loading: false,
});

const requestConfig = computed(() => ({ params: { terminal_id: props.terminalId } }));

function formatDateTime(value) {
    if (!value) return '-';
    return new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
}

function formatDuration(seconds) {
    const safeSeconds = Math.max(0, Math.floor(Number(seconds) || 0));
    const hours = Math.floor(safeSeconds / 3600);
    const minutes = Math.floor((safeSeconds % 3600) / 60);
    const remainder = safeSeconds % 60;
    if (hours > 0) return `${hours}h ${String(minutes).padStart(2, '0')}min`;
    return `${String(minutes).padStart(2, '0')}:${String(remainder).padStart(2, '0')}`;
}

function remainingSeconds(sale) {
    const deadline = sale?.cancel_policy?.deadline_at;
    if (!deadline) return 0;
    return Math.max(0, Math.floor((new Date(deadline).getTime() - nowTick.value) / 1000));
}

function canCancel(sale) {
    return sale?.status === 'finalizada'
        && Boolean(sale?.cancel_policy?.can_cancel)
        && remainingSeconds(sale) > 0;
}

function requestErrorMessage(requestError, fallback) {
    const validationErrors = requestError?.response?.data?.errors || {};
    const firstError = Object.values(validationErrors).find((messages) => Array.isArray(messages) && messages.length);
    return firstError?.[0] || requestError?.response?.data?.message || fallback;
}

async function loadSales() {
    if (!props.terminalId) {
        error.value = 'Terminal do PDV não identificado.';
        return;
    }

    loading.value = true;
    error.value = '';
    try {
        const { data } = await props.apiClient.get('/pos/sales', requestConfig.value);
        sales.value = Array.isArray(data?.sales) ? data.sales : [];
        cashSession.value = data?.cash_session || null;
    } catch (requestError) {
        error.value = requestErrorMessage(requestError, 'Não foi possível carregar as vendas deste caixa.');
    } finally {
        loading.value = false;
    }
}

async function openSale(sale) {
    loadingDetail.value = true;
    error.value = '';
    try {
        const { data } = await props.apiClient.get(`/pos/sales/${sale.id}`, requestConfig.value);
        selectedSale.value = data;
    } catch (requestError) {
        error.value = requestErrorMessage(requestError, 'Não foi possível abrir a nota.');
    } finally {
        loadingDetail.value = false;
    }
}

function openCancellation() {
    cancellation.reason = '';
    cancellation.error = '';
    cancellation.open = true;
}

function closeCancellation() {
    if (cancellation.loading) return;
    cancellation.open = false;
    cancellation.error = '';
}

async function confirmCancellation() {
    const reason = cancellation.reason.trim();
    if (reason.length < 3) {
        cancellation.error = 'Informe um motivo para o cancelamento.';
        return;
    }

    cancellation.loading = true;
    cancellation.error = '';
    try {
        const { data } = await props.apiClient.post(
            `/pos/sales/${selectedSale.value.id}/cancel`,
            { motivo: reason },
            requestConfig.value,
        );
        selectedSale.value = data;
        cancellation.open = false;
        emit('canceled', data);
        await loadSales();
    } catch (requestError) {
        cancellation.error = requestErrorMessage(requestError, 'Não foi possível cancelar a venda.');
    } finally {
        cancellation.loading = false;
    }
}

function closeDialog() {
    if (cancellation.loading) return;
    cancellation.open = false;
    selectedSale.value = null;
    emit('close');
}

watch(
    () => props.open,
    (open) => {
        if (open) {
            selectedSale.value = null;
            nowTick.value = Date.now();
            loadSales();
            timerId = window.setInterval(() => { nowTick.value = Date.now(); }, 1000);
            return;
        }

        if (timerId) window.clearInterval(timerId);
        timerId = null;
    },
);
</script>

<template>
    <AppModal :open="open" title="Vendas deste caixa" width-class="max-w-5xl" @close="closeDialog">
        <p class="cash-sales__context">
            {{ cashSession?.terminal_name || 'Caixa atual' }}
            <span v-if="cashSession?.opened_at">aberto em {{ formatDateTime(cashSession.opened_at) }}</span>
        </p>

        <p v-if="error" class="cash-sales__error">{{ error }}</p>
        <div v-if="loading || loadingDetail" class="cash-sales__loading">Carregando vendas...</div>

        <section v-else-if="selectedSale" class="cash-sales__detail">
            <div class="cash-sales__detail-head">
                <AppButton variant="secondary" @click="selectedSale = null">
                    <ArrowLeft class="h-4 w-4" /> Voltar
                </AppButton>
                <div>
                    <strong>Nota #{{ selectedSale.numero }}</strong>
                    <p>{{ formatDateTime(selectedSale.sold_at) }} · {{ selectedSale.document_label }}</p>
                </div>
                <span class="cash-sales__status" :class="{ 'is-canceled': selectedSale.status === 'cancelada' }">
                    {{ selectedSale.status_label }}
                </span>
            </div>

            <div class="cash-sales__summary">
                <div><span>Cliente</span><strong>{{ selectedSale.cliente_nome || 'Cliente balcão' }}</strong></div>
                <div><span>Operador</span><strong>{{ selectedSale.creator?.name || '-' }}</strong></div>
                <div><span>Total</span><strong>{{ formatCurrency(selectedSale.total_financeiro) }}</strong></div>
                <div><span>Documento</span><strong>{{ selectedSale.numero_nota_formatado || `#${selectedSale.numero}` }}</strong></div>
            </div>

            <div class="cash-sales__table-wrap">
                <table class="cash-sales__table">
                    <thead><tr><th>Item</th><th>Código</th><th>Descrição</th><th>Qtd.</th><th>Unitário</th><th>Total</th></tr></thead>
                    <tbody>
                        <tr v-for="(item, index) in selectedSale.items" :key="item.id">
                            <td>{{ index + 1 }}</td>
                            <td>{{ item.produto_codigo || '-' }}</td>
                            <td>{{ item.produto_nome }}</td>
                            <td>{{ item.quantidade }} {{ item.unidade }}</td>
                            <td>{{ formatCurrency(item.valor_unitario) }}</td>
                            <td>{{ formatCurrency(item.valor_total) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="cash-sales__payments">
                <span>Pagamentos:</span>
                <strong v-for="payment in selectedSale.payments" :key="payment.id">
                    {{ payment.metodo_nome }}: {{ formatCurrency(payment.valor) }}
                </strong>
            </div>

            <div class="cash-sales__actions">
                <p v-if="canCancel(selectedSale)">Prazo restante: {{ formatDuration(remainingSeconds(selectedSale)) }}</p>
                <p v-else-if="selectedSale.status === 'cancelada'">Cancelada em {{ formatDateTime(selectedSale.canceled_at) }}.</p>
                <p v-else>Prazo de cancelamento encerrado.</p>
                <AppButton variant="danger" :disabled="!canCancel(selectedSale)" @click="openCancellation">
                    <RotateCcw class="h-4 w-4" /> Cancelar venda fechada
                </AppButton>
            </div>
        </section>

        <div v-else-if="sales.length" class="cash-sales__list">
            <article v-for="sale in sales" :key="sale.id" class="cash-sales__row">
                <ReceiptText class="h-5 w-5" />
                <div><strong>Nota #{{ sale.numero }}</strong><p>{{ formatDateTime(sale.sold_at) }} · {{ sale.cliente_nome || 'Cliente balcão' }}</p></div>
                <div class="cash-sales__row-total"><strong>{{ formatCurrency(sale.total_financeiro) }}</strong><small>{{ sale.status_label }}</small></div>
                <span v-if="canCancel(sale)" class="cash-sales__time">{{ formatDuration(remainingSeconds(sale)) }}</span>
                <AppButton variant="secondary" @click="openSale(sale)"><Eye class="h-4 w-4" /> Ver nota</AppButton>
            </article>
        </div>
        <div v-else-if="!loading" class="cash-sales__empty">Nenhuma venda fechada neste caixa.</div>

        <AppModal :open="cancellation.open" :title="`Cancelar venda #${selectedSale?.numero || ''}`" width-class="max-w-xl" @close="closeCancellation">
            <div class="space-y-4">
                <p class="text-sm text-muted">Esta ação cancela uma venda já finalizada e estorna o estoque. O carrinho atual não será alterado.</p>
                <AppTextarea v-model="cancellation.reason" label="Motivo do cancelamento *" rows="4" placeholder="Descreva o motivo..." />
                <p v-if="cancellation.error" class="cash-sales__error">{{ cancellation.error }}</p>
                <div class="cash-sales__confirm-actions">
                    <AppButton variant="secondary" :disabled="cancellation.loading" @click="closeCancellation">Voltar</AppButton>
                    <AppButton variant="danger" :loading="cancellation.loading" @click="confirmCancellation">Confirmar cancelamento</AppButton>
                </div>
            </div>
        </AppModal>
    </AppModal>
</template>

<style scoped>
.cash-sales__context { margin: -0.5rem 0 1rem; color: var(--color-text-muted); font-size: 0.85rem; }
.cash-sales__context span { margin-left: 0.4rem; }
.cash-sales__error { color: var(--color-danger); font-size: 0.875rem; }
.cash-sales__loading, .cash-sales__empty { padding: 2.5rem; text-align: center; color: var(--color-text-muted); }
.cash-sales__list { display: grid; gap: 0.65rem; max-height: 65vh; overflow: auto; }
.cash-sales__row { display: grid; grid-template-columns: auto minmax(0, 1fr) auto auto auto; align-items: center; gap: 0.85rem; padding: 0.85rem; border: 1px solid var(--color-border); border-radius: 0.75rem; background: var(--color-surface); }
.cash-sales__row p, .cash-sales__detail-head p { margin: 0.15rem 0 0; color: var(--color-text-muted); font-size: 0.8rem; }
.cash-sales__row-total { display: grid; text-align: right; }
.cash-sales__row-total small { color: var(--color-text-muted); }
.cash-sales__time { color: var(--color-success); font-family: var(--font-mono); font-weight: 700; }
.cash-sales__detail { display: grid; gap: 1rem; }
.cash-sales__detail-head { display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: 1rem; }
.cash-sales__status { padding: 0.35rem 0.65rem; border-radius: 999px; background: color-mix(in srgb, var(--color-success) 15%, transparent); color: var(--color-success); font-size: 0.75rem; font-weight: 800; }
.cash-sales__status.is-canceled { background: color-mix(in srgb, var(--color-danger) 15%, transparent); color: var(--color-danger); }
.cash-sales__summary { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0.75rem; }
.cash-sales__summary div { display: grid; gap: 0.2rem; padding: 0.75rem; border-radius: 0.65rem; background: var(--color-surface-muted); }
.cash-sales__summary span { color: var(--color-text-muted); font-size: 0.75rem; }
.cash-sales__table-wrap { max-height: 40vh; overflow: auto; border: 1px solid var(--color-border); border-radius: 0.65rem; }
.cash-sales__table { width: 100%; border-collapse: collapse; }
.cash-sales__table th, .cash-sales__table td { padding: 0.65rem; border-bottom: 1px solid var(--color-border); text-align: left; font-size: 0.8rem; }
.cash-sales__table th { position: sticky; top: 0; background: var(--color-surface-muted); }
.cash-sales__payments { display: flex; flex-wrap: wrap; gap: 0.7rem; font-size: 0.85rem; }
.cash-sales__payments span { color: var(--color-text-muted); }
.cash-sales__actions { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
.cash-sales__actions p { margin: 0; color: var(--color-text-muted); font-size: 0.85rem; }
.cash-sales__confirm-actions { display: flex; justify-content: flex-end; gap: 0.5rem; }
@media (max-width: 760px) {
    .cash-sales__row { grid-template-columns: auto 1fr auto; }
    .cash-sales__row-total, .cash-sales__time { display: none; }
    .cash-sales__summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
</style>
