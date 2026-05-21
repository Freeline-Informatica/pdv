<script setup>
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue';
import api from '../../../lib/api';
import { formatCurrency, formatPercent } from '../../../lib/format';
import AppModal from '../../ui/AppModal.vue';
import AppButton from '../../ui/AppButton.vue';
import AppInput from '../../ui/AppInput.vue';
import AppSelect from '../../ui/AppSelect.vue';
import AppTextarea from '../../ui/AppTextarea.vue';
import AppSearchField from '../../ui/AppSearchField.vue';
import FinalizeSaleStepper from './FinalizeSaleStepper.vue';
import SaleSummaryCard from './SaleSummaryCard.vue';
import PaymentMethodsGrid from './PaymentMethodsGrid.vue';
import PaymentCompositionList from './PaymentCompositionList.vue';
import FinalizationSuccessState from './FinalizationSuccessState.vue';
import FiscalDocumentSelector from './FiscalDocumentSelector.vue';
import { mockCustomers } from '../../../mock/customers';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    cart: {
        type: Array,
        default: () => [],
    },
    saleContext: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close', 'completed', 'customer-selected']);

const steps = [
    { id: 1, label: 'Cliente' },
    { id: 2, label: 'Pagamento' },
    { id: 3, label: 'Finalizacao' },
];

const customerMode = ref('consumer');
const selectedCustomer = ref(null);
const customerSearch = ref('');
const customerResults = ref([]);
const customerSearchLoading = ref(false);
const customerSearchError = ref('');
const customerStepError = ref('');
const quickExpanded = ref(false);
const savingQuickCustomer = ref(false);
const fetchingCep = ref(false);
const actionFeedback = ref('');

const quickForm = reactive({
    personType: 'fisica',
    document: '',
    name: '',
    cep: '',
    number: '',
    phone: '',
    email: '',
    neighborhood: '',
    street: '',
    complement: '',
    state: '',
    city: '',
    stateRegistration: '',
    notes: '',
    fantasyName: '',
    country: 'Brasil',
});

const quickErrors = reactive({
    document: '',
    name: '',
    cep: '',
    number: '',
    phone: '',
    email: '',
});

const fiscalDefaults = reactive({
    documentModel: 'NFC-e',
    documentSeries: '1',
});

const paymentMethods = ref([]);
const paymentMethodsLoading = ref(false);
const paymentPlans = ref([]);
const paymentPlansLoading = ref(false);
const selectedInstallmentOptionId = ref('');
const selectedPaymentMethodId = ref('');
const paymentAmount = ref('');
const paymentError = ref('');
const payments = ref([]);
const processingEmission = ref(false);
const emissionError = ref('');
const currentStep = ref(1);
const successReceipt = ref(null);
const AUTO_EMIT_DURATION_MS = 3000;
const autoEmitCountdownActive = ref(false);
const autoEmitProgress = ref(0);
const autoEmitRemainingMs = ref(AUTO_EMIT_DURATION_MS);
const autoEmitSecondsLeft = computed(() => Math.max(0, Math.ceil(autoEmitRemainingMs.value / 1000)));

const consumerOptionRef = ref(null);
let searchDebounce = null;
let autoEmitInterval = null;

const fallbackPaymentMethods = [
    {
        id: 'cash',
        nome: 'Dinheiro',
        tipo: 'dinheiro',
        permite_troco: true,
        permite_multiplos_pagamentos: true,
        permite_parcelamento: false,
        parcelas_min: 1,
        parcelas_max: 1,
        parcela_minima: 0,
        sem_juros_ate: 0,
        taxa_credito_parcelado: 0,
    },
    {
        id: 'pix',
        nome: 'PIX',
        tipo: 'pix',
        permite_troco: false,
        permite_multiplos_pagamentos: true,
        permite_parcelamento: false,
        parcelas_min: 1,
        parcelas_max: 1,
        parcela_minima: 0,
        sem_juros_ate: 0,
        taxa_credito_parcelado: 0,
    },
    {
        id: 'credit-card',
        nome: 'Cartao de credito',
        tipo: 'credito',
        permite_troco: false,
        permite_multiplos_pagamentos: true,
        permite_parcelamento: true,
        parcelas_min: 1,
        parcelas_max: 12,
        parcela_minima: 5,
        sem_juros_ate: 3,
        taxa_credito_parcelado: 2.99,
    },
    {
        id: 'debit-card',
        nome: 'Cartao de debito',
        tipo: 'debito',
        permite_troco: false,
        permite_multiplos_pagamentos: true,
        permite_parcelamento: false,
        parcelas_min: 1,
        parcelas_max: 1,
        parcela_minima: 0,
        sem_juros_ate: 0,
        taxa_credito_parcelado: 0,
    },
    {
        id: 'check',
        nome: 'Cheque',
        tipo: 'cheque',
        permite_troco: false,
        permite_multiplos_pagamentos: true,
        permite_parcelamento: false,
        parcelas_min: 1,
        parcelas_max: 1,
        parcela_minima: 0,
        sem_juros_ate: 0,
        taxa_credito_parcelado: 0,
    },
    {
        id: 'store-credit',
        nome: 'Credito da loja',
        tipo: 'credito_loja',
        permite_troco: false,
        permite_multiplos_pagamentos: true,
        permite_parcelamento: false,
        parcelas_min: 1,
        parcelas_max: 1,
        parcela_minima: 0,
        sem_juros_ate: 0,
        taxa_credito_parcelado: 0,
    },
    {
        id: 'gift-card',
        nome: 'Vale presente',
        tipo: 'vale_presente',
        permite_troco: false,
        permite_multiplos_pagamentos: true,
        permite_parcelamento: false,
        parcelas_min: 1,
        parcelas_max: 1,
        parcela_minima: 0,
        sem_juros_ate: 0,
        taxa_credito_parcelado: 0,
    },
];

const itemsCount = computed(() => props.cart.reduce((acc, item) => acc + Number(item.qty || 0), 0));
const productsTotal = computed(() => props.cart.reduce((acc, item) => acc + Number(item.preco_venda || 0) * Number(item.qty || 0), 0));
const discountTotal = computed(() => 0);
const interestTotal = computed(() =>
    roundMoney(payments.value.reduce((acc, payment) => acc + Number(payment.interestAmount || 0), 0)),
);
const surchargeTotal = computed(() => interestTotal.value);
const netTotal = computed(() => Math.max(0, roundMoney(productsTotal.value)));
const payableTotal = computed(() => roundMoney(netTotal.value + interestTotal.value));
const paidTotal = computed(() => payments.value.reduce((acc, payment) => acc + payment.amount, 0));
const remainingTotal = computed(() => Math.max(0, roundMoney(payableTotal.value - paidTotal.value)));
const overpaidTotal = computed(() => Math.max(0, roundMoney(paidTotal.value - payableTotal.value)));
const hasChangeCapableMethod = computed(() => payments.value.some((payment) => payment.permite_troco));
const changeTotal = computed(() => (overpaidTotal.value > 0 && hasChangeCapableMethod.value ? overpaidTotal.value : 0));
const selectedPaymentMethod = computed(() => paymentMethods.value.find((item) => item.id === selectedPaymentMethodId.value) || null);
const isCreditMethod = computed(() => {
    const type = String(selectedPaymentMethod.value?.tipo || '').toLowerCase();
    return ['credito', 'credit', 'credito', 'cartao_credito', 'cartao credito', 'credit-card'].includes(type);
});

const availableInstallmentOptions = computed(() => {
    const method = selectedPaymentMethod.value;
    if (!method || !method.permite_parcelamento || !isCreditMethod.value) return [];

    const planOptions = paymentPlans.value
        .filter((plan) => plan.ativo && plan.exibir_pdv)
        .sort((left, right) => Number(left.quantidade_parcelas || 0) - Number(right.quantidade_parcelas || 0))
        .map((plan) => {
            const quantity = Math.max(1, Number(plan.quantidade_parcelas || 1));
            const interestRate = plan.possui_juros ? Number(plan.percentual_juros || 0) : 0;

            return {
                id: `plan:${plan.id}`,
                quantity,
                interestRate,
                minInstallmentValue: Number(plan.valor_minimo_parcela || 0),
                planId: plan.id,
                label: `${quantity}x ${interestRate > 0 ? `com ${formatPercent(interestRate)} de juros` : 'sem juros'}`,
            };
        });

    if (planOptions.length) return planOptions;

    const minInstallments = Math.max(1, Number(method.parcelas_min || 1));
    const maxInstallments = Math.max(minInstallments, Number(method.parcelas_max || minInstallments));
    const noInterestUntil = Math.max(0, Number(method.sem_juros_ate || 0));
    const defaultInterestRate = Math.max(0, Number(method.taxa_credito_parcelado || 0));
    const minInstallmentValue = Math.max(0, Number(method.parcela_minima || 0));

    return Array.from({ length: maxInstallments - minInstallments + 1 }, (_, index) => {
        const quantity = minInstallments + index;
        const interestRate = quantity > noInterestUntil && quantity > 1 ? defaultInterestRate : 0;

        return {
            id: `manual:${quantity}`,
            quantity,
            interestRate,
            minInstallmentValue,
            planId: null,
            label: `${quantity}x ${interestRate > 0 ? `com ${formatPercent(interestRate)} de juros` : 'sem juros'}`,
        };
    });
});

const selectedInstallmentOption = computed(
    () => availableInstallmentOptions.value.find((option) => option.id === selectedInstallmentOptionId.value) || null,
);
const selectedInstallments = computed(() => selectedInstallmentOption.value?.quantity || 1);
const selectedInterestRate = computed(() => selectedInstallmentOption.value?.interestRate || 0);
const enteredPaymentAmount = computed(() => roundMoney(parseMoney(paymentAmount.value)));
const enteredPaymentInterest = computed(() =>
    selectedInterestRate.value > 0 ? roundMoney((enteredPaymentAmount.value * selectedInterestRate.value) / 100) : 0,
);
const enteredPaymentTotal = computed(() => roundMoney(enteredPaymentAmount.value + enteredPaymentInterest.value));
const enteredInstallmentAmount = computed(() =>
    selectedInstallments.value > 0 ? roundMoney(enteredPaymentTotal.value / selectedInstallments.value) : 0,
);
const hasEnteredPaymentPreview = computed(() => enteredPaymentAmount.value > 0 && !!selectedPaymentMethod.value);
const projectedInterestTotal = computed(() =>
    roundMoney(interestTotal.value + (hasEnteredPaymentPreview.value ? enteredPaymentInterest.value : 0)),
);
const projectedPayableTotal = computed(() => roundMoney(netTotal.value + projectedInterestTotal.value));
const projectedPaidTotal = computed(() =>
    roundMoney(paidTotal.value + (hasEnteredPaymentPreview.value ? enteredPaymentTotal.value : 0)),
);
const projectedOverpaidTotal = computed(() =>
    Math.max(0, roundMoney(projectedPaidTotal.value - projectedPayableTotal.value)),
);
const projectedRemainingTotal = computed(() =>
    Math.max(0, roundMoney(projectedPayableTotal.value - projectedPaidTotal.value)),
);
const projectedHasChangeCapableMethod = computed(() =>
    hasChangeCapableMethod.value ||
    (hasEnteredPaymentPreview.value && !!selectedPaymentMethod.value?.permite_troco),
);
const projectedChangeTotal = computed(() =>
    projectedHasChangeCapableMethod.value ? projectedOverpaidTotal.value : 0,
);
const displayedSurchargeTotal = computed(() =>
    currentStep.value === 2 ? projectedInterestTotal.value : interestTotal.value,
);
const displayedNetTotal = computed(() =>
    currentStep.value === 2 ? projectedPayableTotal.value : payableTotal.value,
);
const displayedPaidTotal = computed(() =>
    currentStep.value === 2 ? projectedPaidTotal.value : paidTotal.value,
);
const displayedRemainingTotal = computed(() =>
    currentStep.value === 2 ? projectedRemainingTotal.value : remainingTotal.value,
);
const displayedChangeTotal = computed(() =>
    currentStep.value === 2 ? projectedChangeTotal.value : changeTotal.value,
);

const paymentValidationMessage = computed(() => {
    if (payableTotal.value <= 0) return 'Total da venda deve ser maior que zero.';
    if (!payments.value.length) return 'Adicione ao menos um pagamento.';
    if (remainingTotal.value > 0) return `Falta pagar ${formatCurrency(remainingTotal.value)}.`;
    if (overpaidTotal.value > 0 && !hasChangeCapableMethod.value) {
        return 'Composicao excede o total e nao ha forma que permita troco.';
    }
    return '';
});

const selectedDocumentLabel = computed(() => (fiscalDefaults.documentModel === 'NF-e' ? 'NF-e' : 'NFC-e'));

const statusLabel = computed(() => {
    if (successReceipt.value) return `${selectedDocumentLabel.value} emitida`;
    if (processingEmission.value) return `Emitindo ${selectedDocumentLabel.value}`;
    if (currentStep.value === 1) return 'Cliente em selecao';
    if (currentStep.value === 2) return 'Pagamento em preenchimento';
    if (!paymentValidationMessage.value) return 'Pronta para emitir';
    return 'Aguardando pagamento';
});

const noteSummary = computed(() => '');

const customerLabel = computed(() => selectedCustomer.value?.nome || 'Consumidor final');

const canGoBack = computed(() => currentStep.value > 1 && !processingEmission.value);
const canContinue = computed(() => {
    if (processingEmission.value || currentStep.value >= 3) return false;
    if (currentStep.value === 2) return !paymentValidationMessage.value;
    return true;
});
const canEmit = computed(
    () =>
        currentStep.value === 3 &&
        !processingEmission.value &&
        !paymentValidationMessage.value &&
        validateStep1(false),
);

const hasPendingChanges = computed(() => {
    if (currentStep.value > 1) return true;
    if (customerMode.value !== 'consumer') return true;
    if (customerSearch.value.trim()) return true;
    if (payments.value.length) return true;
    if (quickForm.document || quickForm.name || quickForm.cep || quickForm.number || quickForm.phone || quickForm.email) return true;
    return false;
});

function roundMoney(value) {
    return Math.round(Number(value || 0) * 100) / 100;
}

function digitsOnly(value) {
    return String(value || '').replace(/\D/g, '');
}

function maskCpf(value) {
    const digits = digitsOnly(value).slice(0, 11);
    return digits
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
}

function maskCnpj(value) {
    const digits = digitsOnly(value).slice(0, 14);
    return digits
        .replace(/(\d{2})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d)/, '$1/$2')
        .replace(/(\d{4})(\d{1,2})$/, '$1-$2');
}

function maskDocumentByType(value, personType) {
    if (personType === 'juridica') return maskCnpj(value);
    return maskCpf(value);
}

function maskPhone(value) {
    const digits = digitsOnly(value).slice(0, 11);
    if (digits.length <= 10) {
        return digits.replace(/(\d{2})(\d)/, '($1) $2').replace(/(\d{4})(\d)/, '$1-$2');
    }
    return digits.replace(/(\d{2})(\d)/, '($1) $2').replace(/(\d{5})(\d)/, '$1-$2');
}

function maskCep(value) {
    const digits = digitsOnly(value).slice(0, 8);
    return digits.replace(/(\d{5})(\d)/, '$1-$2');
}

function maskMoney(value) {
    const digits = digitsOnly(value);
    if (!digits) return '';
    const cents = digits.padStart(3, '0');
    const integerPart = cents.slice(0, -2).replace(/^0+(?=\d)/, '');
    const decimalPart = cents.slice(-2);
    const formattedInt = (integerPart || '0').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return `${formattedInt},${decimalPart}`;
}

function parseMoney(value) {
    if (!value) return 0;
    const normalized = String(value).replace(/\./g, '').replace(',', '.').replace(/[^\d.-]/g, '');
    return Number(normalized || 0);
}

function formatDocument(value) {
    const digits = digitsOnly(value);
    if (digits.length > 11) return maskCnpj(digits);
    if (digits.length === 11) return maskCpf(digits);
    return value || '';
}

function isValidCpf(value) {
    const cpf = digitsOnly(value);
    if (cpf.length !== 11 || /^(\d)\1+$/.test(cpf)) return false;
    let sum = 0;
    for (let i = 0; i < 9; i += 1) sum += Number(cpf[i]) * (10 - i);
    let remainder = (sum * 10) % 11;
    if (remainder === 10) remainder = 0;
    if (remainder !== Number(cpf[9])) return false;
    sum = 0;
    for (let i = 0; i < 10; i += 1) sum += Number(cpf[i]) * (11 - i);
    remainder = (sum * 10) % 11;
    if (remainder === 10) remainder = 0;
    return remainder === Number(cpf[10]);
}

function isValidCnpj(value) {
    const cnpj = digitsOnly(value);
    if (cnpj.length !== 14 || /^(\d)\1+$/.test(cnpj)) return false;
    const calc = (base, factors) => {
        let total = 0;
        for (let i = 0; i < factors.length; i += 1) total += Number(base[i]) * factors[i];
        const remainder = total % 11;
        return remainder < 2 ? 0 : 11 - remainder;
    };
    const d1 = calc(cnpj, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
    const d2 = calc(cnpj, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
    return d1 === Number(cnpj[12]) && d2 === Number(cnpj[13]);
}

function createConsumerCustomer() {
    return {
        id: 'consumer-final',
        nome: 'Consumidor final',
        cpf_cnpj: '',
        telefone: '',
        cidade: '',
        uf: '',
    };
}

function normalizeCustomer(record) {
    return {
        id: record.id || `customer-${Date.now()}`,
        nome: record.nome || record.razao_social || record.name || 'Cliente sem nome',
        cpf_cnpj: record.cpf_cnpj || record.cpfCnpj || record.documento || '',
        telefone: record.telefone || record.phone || '',
        cidade: record.cidade || record.city || '',
        uf: record.uf || record.state || '',
        email: record.email || '',
        tipo_pessoa: record.tipo_pessoa || record.personType || '',
    };
}

function resetQuickErrors() {
    quickErrors.document = '';
    quickErrors.name = '';
    quickErrors.cep = '';
    quickErrors.number = '';
    quickErrors.phone = '';
    quickErrors.email = '';
}

function clearMessages() {
    customerStepError.value = '';
    paymentError.value = '';
    emissionError.value = '';
    actionFeedback.value = '';
}

function focusCurrentStep() {
    if (!props.open) return;
    nextTick(() => {
        if (successReceipt.value) return;

        if (currentStep.value === 1) {
            if (customerMode.value === 'search') {
                document.getElementById('finalize-customer-search')?.focus();
                return;
            }
            if (customerMode.value === 'quick') {
                document.getElementById('finalize-quick-document')?.focus();
                return;
            }
            consumerOptionRef.value?.focus?.();
            return;
        }

        if (currentStep.value === 2) {
            document.getElementById('finalize-payment-amount')?.focus();
            return;
        }

        document.getElementById('finalize-emit-button')?.focus();
    });
}

function setCustomerMode(mode) {
    customerMode.value = mode;
    customerStepError.value = '';
    actionFeedback.value = '';
    if (mode === 'consumer') {
        selectedCustomer.value = createConsumerCustomer();
        emit('customer-selected', selectedCustomer.value);
    } else {
        selectedCustomer.value = null;
    }
    focusCurrentStep();
}

async function loadPaymentMethods() {
    paymentMethodsLoading.value = true;
    try {
        const { data } = await api.get('/payment-methods?active_only=1');
        const methods = Array.isArray(data)
            ? data.map((method) => ({
                  id: method.id,
                  nome: method.nome,
                  tipo: method.tipo,
                  permite_troco: !!method.permite_troco,
                  permite_multiplos_pagamentos: !!method.permite_multiplos_pagamentos,
                  permite_parcelamento: !!method.permite_parcelamento,
                  parcelas_min: Number(method.parcelas_min || 1),
                  parcelas_max: Number(method.parcelas_max || 1),
                  parcela_minima: Number(method.parcela_minima || 0),
                  sem_juros_ate: Number(method.sem_juros_ate || 0),
                  taxa_credito_parcelado: Number(method.taxa_credito_parcelado || 0),
              }))
            : [];

        paymentMethods.value = methods.length ? methods : fallbackPaymentMethods;
    } catch {
        paymentMethods.value = fallbackPaymentMethods;
    } finally {
        paymentMethodsLoading.value = false;
        if (!selectedPaymentMethodId.value) {
            selectedPaymentMethodId.value = paymentMethods.value[0]?.id || '';
        }
    }
}

async function loadPaymentPlansForMethod(methodId) {
    const method = paymentMethods.value.find((item) => item.id === methodId);
    const type = String(method?.tipo || '').toLowerCase();
    const isCreditType = ['credito', 'credit', 'cartao_credito', 'cartao credito', 'credit-card'].includes(type);

    if (!method || !method.permite_parcelamento || !isCreditType) {
        paymentPlans.value = [];
        selectedInstallmentOptionId.value = '';
        return;
    }

    paymentPlansLoading.value = true;
    try {
        const { data } = await api.get(`/payment-plans?payment_method_id=${methodId}`);
        paymentPlans.value = Array.isArray(data) ? data : [];
    } catch {
        paymentPlans.value = [];
    } finally {
        paymentPlansLoading.value = false;
    }
}

async function loadFiscalDefaults() {
    try {
        const { data } = await api.get('/settings/fiscal');
        fiscalDefaults.documentSeries = String(data?.serie_nfce || '1');
    } catch {
        fiscalDefaults.documentSeries = '1';
    }
}

function resetQuickForm() {
    quickForm.personType = 'fisica';
    quickForm.document = '';
    quickForm.name = '';
    quickForm.cep = '';
    quickForm.number = '';
    quickForm.phone = '';
    quickForm.email = '';
    quickForm.neighborhood = '';
    quickForm.street = '';
    quickForm.complement = '';
    quickForm.state = '';
    quickForm.city = '';
    quickForm.stateRegistration = '';
    quickForm.notes = '';
    quickForm.fantasyName = '';
    quickForm.country = 'Brasil';
}

function resetFiscalDefaults() {
    fiscalDefaults.documentModel = 'NFC-e';
    fiscalDefaults.documentSeries = '1';
}

function clearAutoEmitCountdown(resetProgress = true) {
    if (autoEmitInterval) {
        clearInterval(autoEmitInterval);
        autoEmitInterval = null;
    }

    autoEmitCountdownActive.value = false;
    autoEmitRemainingMs.value = AUTO_EMIT_DURATION_MS;

    if (resetProgress) {
        autoEmitProgress.value = 0;
    }
}

function startAutoEmitCountdown() {
    if (autoEmitCountdownActive.value) return;
    if (!props.open || currentStep.value !== 3) return;
    if (successReceipt.value || processingEmission.value || !canEmit.value) return;

    autoEmitCountdownActive.value = true;
    autoEmitProgress.value = 0;
    autoEmitRemainingMs.value = AUTO_EMIT_DURATION_MS;

    const startedAt = Date.now();
    autoEmitInterval = setInterval(() => {
        const elapsed = Date.now() - startedAt;
        const clamped = Math.min(AUTO_EMIT_DURATION_MS, elapsed);

        autoEmitProgress.value = (clamped / AUTO_EMIT_DURATION_MS) * 100;
        autoEmitRemainingMs.value = Math.max(0, AUTO_EMIT_DURATION_MS - clamped);

        if (clamped >= AUTO_EMIT_DURATION_MS) {
            clearAutoEmitCountdown(false);
            autoEmitProgress.value = 100;
            emitNfce();
        }
    }, 50);
}

function resetFlow() {
    clearAutoEmitCountdown();
    currentStep.value = 1;
    successReceipt.value = null;
    customerMode.value = 'consumer';
    selectedCustomer.value = createConsumerCustomer();
    customerSearch.value = '';
    customerResults.value = [];
    customerSearchError.value = '';
    quickExpanded.value = false;
    resetQuickForm();
    resetQuickErrors();
    resetFiscalDefaults();
    payments.value = [];
    paymentAmount.value = '';
    paymentPlans.value = [];
    selectedInstallmentOptionId.value = '';
    selectedPaymentMethodId.value = '';
    clearMessages();
}

async function initializeFlow() {
    resetFlow();
    await Promise.all([loadPaymentMethods(), loadFiscalDefaults()]);
    await loadPaymentPlansForMethod(selectedPaymentMethodId.value);
    focusCurrentStep();
}

async function performCustomerSearch(term) {
    customerSearchLoading.value = true;
    customerSearchError.value = '';
    try {
        const { data } = await api.get(`/customers?search=${encodeURIComponent(term)}`);
        if (Array.isArray(data)) {
            customerResults.value = data.map(normalizeCustomer);
        } else {
            customerResults.value = [];
        }
    } catch {
        const needle = term.toLowerCase();
        customerResults.value = mockCustomers
            .map(normalizeCustomer)
            .filter((item) => {
                return (
                    item.nome.toLowerCase().includes(needle) ||
                    digitsOnly(item.cpf_cnpj).includes(digitsOnly(needle)) ||
                    digitsOnly(item.telefone).includes(digitsOnly(needle)) ||
                    String(item.id).toLowerCase().includes(needle)
                );
            });
    } finally {
        customerSearchLoading.value = false;
    }
}

function selectSearchCustomer(customer) {
    selectedCustomer.value = customer;
    customerStepError.value = '';
    emit('customer-selected', customer);
}

function updateQuickField(field, value) {
    if (field === 'document') {
        quickForm.document = maskDocumentByType(value, quickForm.personType);
        return;
    }

    if (field === 'phone') {
        quickForm.phone = maskPhone(value);
        return;
    }

    if (field === 'cep') {
        quickForm.cep = maskCep(value);
        return;
    }

    quickForm[field] = value;
}

function validateQuickForm() {
    resetQuickErrors();

    if (!quickForm.document.trim()) quickErrors.document = quickForm.personType === 'juridica' ? 'Informe o CNPJ' : 'Informe o CPF';
    if (!quickForm.name.trim()) quickErrors.name = 'Nome e obrigatorio';
    if (!quickForm.cep.trim()) quickErrors.cep = 'Informe o CEP';
    if (!quickForm.number.trim()) quickErrors.number = 'Informe o numero';
    if (!quickForm.phone.trim()) quickErrors.phone = 'Informe o telefone';

    const docDigits = digitsOnly(quickForm.document);
    if (!quickErrors.document) {
        if (quickForm.personType === 'juridica' && !isValidCnpj(docDigits)) {
            quickErrors.document = 'CNPJ invalido';
        }
        if (quickForm.personType === 'fisica' && !isValidCpf(docDigits)) {
            quickErrors.document = 'CPF invalido';
        }
    }

    if (!quickErrors.cep && digitsOnly(quickForm.cep).length !== 8) {
        quickErrors.cep = 'CEP invalido';
    }

    if (!quickErrors.phone && digitsOnly(quickForm.phone).length < 10) {
        quickErrors.phone = 'Telefone invalido';
    }

    if (quickForm.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(quickForm.email)) {
        quickErrors.email = 'E-mail invalido';
    }

    return !Object.values(quickErrors).some(Boolean);
}

async function searchCep() {
    const cep = digitsOnly(quickForm.cep);
    if (cep.length !== 8) {
        quickErrors.cep = 'CEP invalido';
        return;
    }

    fetchingCep.value = true;
    quickErrors.cep = '';
    try {
        const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
        const data = await response.json();
        if (data?.erro) {
            quickErrors.cep = 'CEP nao encontrado';
            return;
        }

        quickForm.street = data.logradouro || quickForm.street;
        quickForm.neighborhood = data.bairro || quickForm.neighborhood;
        quickForm.city = data.localidade || quickForm.city;
        quickForm.state = data.uf || quickForm.state;
    } catch {
        quickErrors.cep = 'Falha ao buscar CEP';
    } finally {
        fetchingCep.value = false;
    }
}

async function saveQuickCustomer() {
    customerStepError.value = '';
    if (!validateQuickForm()) {
        customerStepError.value = 'Confira os campos obrigatorios para salvar o cliente.';
        return;
    }

    savingQuickCustomer.value = true;
    try {
        const payload = {
            tipo_pessoa: quickForm.personType,
            cpf_cnpj: digitsOnly(quickForm.document),
            nome: quickForm.name.trim(),
            cep: digitsOnly(quickForm.cep),
            numero: quickForm.number.trim(),
            telefone: digitsOnly(quickForm.phone),
            email: quickForm.email || null,
            bairro: quickForm.neighborhood || null,
            logradouro: quickForm.street || null,
            complemento: quickForm.complement || null,
            uf: quickForm.state || null,
            cidade: quickForm.city || null,
            inscricao_estadual: quickForm.stateRegistration || null,
            observacoes: quickForm.notes || null,
            nome_fantasia: quickForm.fantasyName || null,
            pais: quickForm.country || 'Brasil',
        };

        let customer = null;
        try {
            const { data } = await api.post('/customers', payload);
            customer = normalizeCustomer(data || payload);
        } catch {
            customer = normalizeCustomer({
                ...payload,
                id: `quick-${Date.now()}`,
            });
        }

        selectedCustomer.value = customer;
        emit('customer-selected', customer);
        customerStepError.value = '';
        actionFeedback.value = 'Cliente salvo e selecionado.';
    } finally {
        savingQuickCustomer.value = false;
    }
}

function validateStep1(showErrors = true) {
    if (customerMode.value === 'consumer') return true;

    if (customerMode.value === 'search') {
        const ok = !!selectedCustomer.value;
        if (!ok && showErrors) customerStepError.value = 'Selecione um cliente para continuar.';
        return ok;
    }

    const ok = !!selectedCustomer.value;
    if (!ok && showErrors) customerStepError.value = 'Salve o cliente rapido para continuar.';
    return ok;
}

function goNext() {
    clearMessages();
    if (currentStep.value === 1 && !validateStep1(true)) return;
    if (currentStep.value === 2 && paymentValidationMessage.value) {
        paymentError.value = paymentValidationMessage.value;
        return;
    }
    if (currentStep.value < 3) currentStep.value += 1;
}

function goBack() {
    clearAutoEmitCountdown();
    clearMessages();
    if (currentStep.value > 1) currentStep.value -= 1;
}

function selectPaymentMethod(methodId) {
    selectedPaymentMethodId.value = methodId;
    paymentError.value = '';
}

function handleConsumerConfirmEnter() {
    if (currentStep.value !== 1 || customerMode.value !== 'consumer') return;
    goNext();
}

function addPayment() {
    paymentError.value = '';
    const method = paymentMethods.value.find((item) => item.id === selectedPaymentMethodId.value);
    if (!method) {
        paymentError.value = 'Selecione uma forma de pagamento.';
        return;
    }

    const baseAmount = enteredPaymentAmount.value;
    if (baseAmount <= 0) {
        paymentError.value = 'Informe um valor de pagamento.';
        return;
    }

    const installmentOption = selectedInstallmentOption.value;
    const installmentCount = selectedInstallments.value;
    const interestRate = selectedInterestRate.value;
    const interestAmount = enteredPaymentInterest.value;
    const amount = enteredPaymentTotal.value;

    if (isCreditMethod.value && method.permite_parcelamento && !installmentOption) {
        paymentError.value = 'Selecione o parcelamento para o cartao de credito.';
        return;
    }

    const minInstallmentValue = Number(installmentOption?.minInstallmentValue || 0);
    if (minInstallmentValue > 0 && enteredInstallmentAmount.value < minInstallmentValue) {
        paymentError.value = `Parcela minima: ${formatCurrency(minInstallmentValue)}.`;
        return;
    }

    if (!method.permite_multiplos_pagamentos && payments.value.some((item) => item.methodId === method.id)) {
        paymentError.value = 'Esse meio nao permite multiplos lancamentos.';
        return;
    }

    const nextPaid = roundMoney(paidTotal.value + amount);
    const nextInterestTotal = roundMoney(interestTotal.value + interestAmount);
    const nextPayableTotal = roundMoney(netTotal.value + nextInterestTotal);
    const willOverpay = nextPaid > nextPayableTotal;
    if (willOverpay && !method.permite_troco) {
        paymentError.value = 'Esse meio nao permite troco para valor acima do total.';
        return;
    }

    payments.value.push({
        id: `${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
        methodId: method.id,
        methodName: method.nome,
        amount,
        baseAmount,
        installments: installmentCount,
        interestRate,
        interestAmount,
        installmentAmount: installmentCount > 0 ? roundMoney(amount / installmentCount) : amount,
        paymentPlanId: installmentOption?.planId || null,
        permite_troco: !!method.permite_troco,
    });

    const remainingAfterPayment = Math.max(0, roundMoney(nextPayableTotal - nextPaid));
    paymentAmount.value = remainingAfterPayment > 0 ? maskMoney(String(Math.round(remainingAfterPayment * 100))) : '';

    nextTick(() => {
        if (!paymentValidationMessage.value) {
            currentStep.value = 3;
            return;
        }

        document.getElementById('finalize-payment-amount')?.focus();
    });
}

function removePayment(paymentId) {
    payments.value = payments.value.filter((item) => item.id !== paymentId);
}

function buildPayload() {
    const context = props.saleContext && typeof props.saleContext === 'object'
        ? props.saleContext
        : null;

    return {
        customer: selectedCustomer.value,
        items: props.cart.map((item) => ({
            id: item.productId || item.id,
            nome: item.nome,
            codigo: item.codigo || null,
            quantidade: Number(item.qty || 0),
            valor_unitario: Number(item.preco_venda || 0),
            valor_total: roundMoney(Number(item.preco_venda || 0) * Number(item.qty || 0)),
        })),
        complementary: {
            representative: null,
            sale_observation: null,
            document_model: fiscalDefaults.documentModel || 'NFC-e',
            document_series: fiscalDefaults.documentSeries || '1',
            fiscal_observation: null,
            discount_value: 0,
            discount_percent: 0,
            surcharge_value: 0,
            surcharge_percent: 0,
            restaurant_ficha_id: context ? String(context.commandId || '') : null,
            restaurant_table_id: context ? String(context.tableId || '') : null,
            restaurant_ficha_code: context ? String(context.commandCode || '') : null,
            restaurant_table_code: context ? String(context.tableCode || '') : null,
        },
        payments: payments.value.map((item) => ({
            method_id: item.methodId,
            method_name: item.methodName,
            amount: item.amount,
            base_amount: item.baseAmount || item.amount,
            installments: item.installments || 1,
            interest_rate: item.interestRate || 0,
            interest_amount: item.interestAmount || 0,
            installment_amount: item.installmentAmount || item.amount,
            payment_plan_id: item.paymentPlanId || null,
        })),
        totals: {
            products_total: productsTotal.value,
            discount_total: discountTotal.value,
            surcharge_total: surchargeTotal.value,
            interest_total: interestTotal.value,
            net_total: netTotal.value,
            payable_total: payableTotal.value,
            paid_total: paidTotal.value,
            remaining_total: remainingTotal.value,
            change_total: changeTotal.value,
        },
    };
}

async function emitNfce() {
    emissionError.value = '';
    if (!validateStep1(true)) {
        currentStep.value = 1;
        return;
    }
    if (paymentValidationMessage.value) {
        currentStep.value = 2;
        emissionError.value = paymentValidationMessage.value;
        return;
    }

    clearAutoEmitCountdown(false);
    processingEmission.value = true;
    const payload = buildPayload();

    try {
        const { data } = await api.post('/pos/sales/finalize', payload);
        const receipt = {
            id: data?.id || null,
            number: data?.numero || '',
            series: data?.serie || fiscalDefaults.documentSeries || '1',
            total: payableTotal.value,
            status: data?.status || 'Autorizada local',
            fiscal: data?.fiscal || null,
        };
        successReceipt.value = receipt;
    } catch (error) {
        const errors = error?.response?.data?.errors;
        emissionError.value = error?.response?.data?.message
            || (errors ? Object.values(errors).flat().join(' ') : '')
            || `Nao foi possivel emitir a ${selectedDocumentLabel.value}.`;
    } finally {
        processingEmission.value = false;
    }
}

function finishSale(startNew = false) {
    clearAutoEmitCountdown();
    if (!successReceipt.value) {
        emit('close');
        return;
    }

    emit('completed', {
        startNew,
        receipt: successReceipt.value,
        customer: selectedCustomer.value,
        totals: {
            net: payableTotal.value,
            change: changeTotal.value,
        },
    });
    emit('close');
}

function printDanfe() {
    window.print();
}

function resendReceipt() {
    actionFeedback.value = 'Reenvio de comprovante em desenvolvimento.';
}

function requestClose() {
    if (processingEmission.value) return;
    if (!successReceipt.value && hasPendingChanges.value) {
        const confirmClose = window.confirm('Existem dados preenchidos. Deseja sair da finalizacao?');
        if (!confirmClose) return;
    }

    clearAutoEmitCountdown();
    emit('close');
}

function handleWindowKeydown(event) {
    if (!props.open) return;
    if (event.defaultPrevented) return;
    if (event.key === 'Escape') {
        event.preventDefault();
        requestClose();
        return;
    }

    if (successReceipt.value) return;
    const isEnter = event.key === 'Enter' || event.code === 'NumpadEnter';
    if (!isEnter || event.shiftKey || event.ctrlKey || event.metaKey || event.altKey) return;

    const target = event.target;
    const isTargetElement = target instanceof HTMLElement;
    const tag = isTargetElement ? target.tagName.toLowerCase() : '';
    if (tag === 'textarea') return;

    if (currentStep.value === 1 && customerMode.value === 'consumer') {
        event.preventDefault();
        goNext();
        return;
    }

    const isPaymentAmountInput = isTargetElement && target.id === 'finalize-payment-amount';
    if (currentStep.value === 2 && isPaymentAmountInput) {
        event.preventDefault();
        addPayment();
        return;
    }

    if (currentStep.value < 3) {
        event.preventDefault();
        goNext();
        return;
    }

    if (canEmit.value) {
        event.preventDefault();
        emitNfce();
    }
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            initializeFlow();
            window.addEventListener('keydown', handleWindowKeydown);
            return;
        }

        clearAutoEmitCountdown();
        window.removeEventListener('keydown', handleWindowKeydown);
    },
);

watch(
    () => selectedPaymentMethodId.value,
    (methodId) => {
        loadPaymentPlansForMethod(methodId);
    },
);

watch(
    () => availableInstallmentOptions.value,
    (options) => {
        if (!options.length) {
            selectedInstallmentOptionId.value = '';
            return;
        }

        const exists = options.some((option) => option.id === selectedInstallmentOptionId.value);
        if (!exists) {
            selectedInstallmentOptionId.value = options[0].id;
        }
    },
    { immediate: true },
);

watch(
    () => [customerMode.value, customerSearch.value],
    ([mode, search]) => {
        if (mode !== 'search') return;

        const query = search.trim();
        if (searchDebounce) clearTimeout(searchDebounce);

        if (query.length < 2) {
            customerResults.value = [];
            customerSearchError.value = '';
            return;
        }

        searchDebounce = setTimeout(() => {
            performCustomerSearch(query);
        }, 280);
    },
);

watch(
    () => currentStep.value,
    (step) => {
        focusCurrentStep();

        if (step === 3) {
            startAutoEmitCountdown();
            return;
        }

        clearAutoEmitCountdown();
    },
);

watch(
    () => processingEmission.value,
    (isProcessing) => {
        if (isProcessing) {
            clearAutoEmitCountdown(false);
        }
    },
);

watch(
    () => successReceipt.value,
    (receipt) => {
        if (receipt) {
            clearAutoEmitCountdown(false);
        }
    },
);

watch(
    () => customerMode.value,
    () => {
        focusCurrentStep();
    },
);

watch(
    () => quickForm.personType,
    () => {
        quickForm.document = maskDocumentByType(quickForm.document, quickForm.personType);
    },
);

watch(
    () => paymentAmount.value,
    (value) => {
        paymentAmount.value = maskMoney(value);
    },
);

onBeforeUnmount(() => {
    if (searchDebounce) clearTimeout(searchDebounce);
    clearAutoEmitCountdown();
    window.removeEventListener('keydown', handleWindowKeydown);
});
</script>

<template>
    <AppModal :open="open" :title="`Finalizar ${selectedDocumentLabel}`" width-class="finalize-modal-panel" @close="requestClose">
        <div class="finalize-wrap">
            <header class="finalize-head">
                <p class="finalize-subtitle">Confira cliente, pagamentos e finalize a emissão da {{ selectedDocumentLabel }}.</p>
                <FinalizeSaleStepper :steps="steps" :current-step="currentStep" />
            </header>

            <div class="finalize-grid">
                <section class="finalize-content">
                    <FinalizationSuccessState
                        v-if="successReceipt"
                        :receipt="successReceipt"
                        :format-currency="formatCurrency"
                        @print="printDanfe"
                        @resend="resendReceipt"
                        @new-sale="finishSale(true)"
                        @close="finishSale(false)"
                    />

                    <template v-else>
                        <div v-if="currentStep === 1" class="space-y-4">
                            <div class="option-grid">
                                <button
                                    ref="consumerOptionRef"
                                    type="button"
                                    class="option-card"
                                    :class="{ 'is-active': customerMode === 'consumer' }"
                                    @click="setCustomerMode('consumer')"
                                    @keydown.enter.prevent.stop="handleConsumerConfirmEnter"
                                >
                                    <p>Consumidor final</p>
                                    <small>Venda rapida sem identificacao</small>
                                </button>
                                <button
                                    type="button"
                                    class="option-card"
                                    :class="{ 'is-active': customerMode === 'search' }"
                                    @click="setCustomerMode('search')"
                                >
                                    <p>Buscar cliente</p>
                                    <small>CPF/CNPJ, nome, telefone ou codigo</small>
                                </button>
                                <button
                                    type="button"
                                    class="option-card"
                                    :class="{ 'is-active': customerMode === 'quick' }"
                                    @click="setCustomerMode('quick')"
                                >
                                    <p>Cadastrar cliente rapido</p>
                                    <small>Cadastro simplificado para emissao</small>
                                </button>
                            </div>

                            <div v-if="customerMode === 'consumer'" class="ui-card p-4">
                                <p class="text-sm font-bold text-main">Consumidor final selecionado.</p>
                                <p class="text-sm text-muted mt-1">A venda sera emitida sem identificacao nominal do cliente.</p>
                                <AppButton class="mt-3" variant="secondary" @click="setCustomerMode('search')">Trocar cliente</AppButton>
                            </div>

                            <div v-if="customerMode === 'search'" class="ui-card p-4 space-y-3">
                                <AppSearchField
                                    id="finalize-customer-search"
                                    v-model="customerSearch"
                                    placeholder="Digite CPF/CNPJ, nome, telefone ou codigo"
                                />

                                <p v-if="customerSearchLoading" class="text-sm text-muted">Buscando clientes...</p>
                                <p v-else-if="customerSearch.trim().length >= 2 && customerResults.length === 0" class="text-sm text-muted">
                                    Nenhum cliente encontrado.
                                </p>
                                <p v-if="customerSearchError" class="text-sm text-danger">{{ customerSearchError }}</p>

                                <div v-if="customerResults.length" class="search-results">
                                    <article v-for="customer in customerResults" :key="customer.id" class="search-card">
                                        <div>
                                            <p class="font-bold text-main">{{ customer.nome }}</p>
                                            <p class="text-xs text-muted">
                                                {{ customer.cpf_cnpj ? formatDocument(customer.cpf_cnpj) : 'Sem documento' }}
                                            </p>
                                            <p class="text-xs text-muted">
                                                {{ customer.telefone ? maskPhone(customer.telefone) : 'Sem telefone' }}
                                                <span v-if="customer.cidade || customer.uf">
                                                    • {{ customer.cidade || '—' }}/{{ customer.uf || '—' }}
                                                </span>
                                            </p>
                                        </div>
                                        <AppButton variant="secondary" @click="selectSearchCustomer(customer)">Selecionar</AppButton>
                                    </article>
                                </div>

                                <div v-if="selectedCustomer" class="selected-customer">
                                    <p class="font-bold text-main">{{ selectedCustomer.nome }}</p>
                                    <p class="text-xs text-muted">Cliente selecionado para esta venda.</p>
                                </div>
                            </div>

                            <div v-if="customerMode === 'quick'" class="ui-card p-4 space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <AppSelect
                                        :model-value="quickForm.personType"
                                        label="Tipo de pessoa"
                                        @update:model-value="updateQuickField('personType', $event)"
                                    >
                                        <option value="fisica">Pessoa fisica</option>
                                        <option value="juridica">Pessoa juridica</option>
                                    </AppSelect>
                                    <AppInput
                                        id="finalize-quick-document"
                                        :model-value="quickForm.document"
                                        :label="quickForm.personType === 'juridica' ? 'CNPJ' : 'CPF'"
                                        :error="quickErrors.document"
                                        placeholder="Somente numeros"
                                        @update:model-value="updateQuickField('document', $event)"
                                    />
                                    <AppInput
                                        :model-value="quickForm.name"
                                        label="Nome / Razao social"
                                        :error="quickErrors.name"
                                        class="md:col-span-2"
                                        @update:model-value="updateQuickField('name', $event)"
                                    />
                                    <AppInput
                                        :model-value="quickForm.cep"
                                        label="CEP"
                                        :error="quickErrors.cep"
                                        @update:model-value="updateQuickField('cep', $event)"
                                    />
                                    <AppInput
                                        :model-value="quickForm.number"
                                        label="Numero"
                                        :error="quickErrors.number"
                                        @update:model-value="updateQuickField('number', $event)"
                                    />
                                    <AppInput
                                        :model-value="quickForm.phone"
                                        label="Telefone"
                                        :error="quickErrors.phone"
                                        @update:model-value="updateQuickField('phone', $event)"
                                    />
                                    <AppInput
                                        :model-value="quickForm.email"
                                        label="E-mail"
                                        :error="quickErrors.email"
                                        @update:model-value="updateQuickField('email', $event)"
                                    />
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <AppButton variant="secondary" :loading="fetchingCep" @click="searchCep">Buscar CEP</AppButton>
                                    <AppButton variant="ghost" @click="quickExpanded = !quickExpanded">
                                        {{ quickExpanded ? 'Ocultar dados extras' : 'Adicionar mais dados' }}
                                    </AppButton>
                                </div>

                                <div v-if="quickExpanded" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <AppInput
                                        :model-value="quickForm.neighborhood"
                                        label="Bairro"
                                        @update:model-value="updateQuickField('neighborhood', $event)"
                                    />
                                    <AppInput
                                        :model-value="quickForm.street"
                                        label="Logradouro"
                                        @update:model-value="updateQuickField('street', $event)"
                                    />
                                    <AppInput
                                        :model-value="quickForm.complement"
                                        label="Complemento"
                                        @update:model-value="updateQuickField('complement', $event)"
                                    />
                                    <AppInput
                                        :model-value="quickForm.state"
                                        label="UF"
                                        @update:model-value="updateQuickField('state', $event)"
                                    />
                                    <AppInput
                                        :model-value="quickForm.city"
                                        label="Cidade"
                                        @update:model-value="updateQuickField('city', $event)"
                                    />
                                    <AppInput
                                        :model-value="quickForm.stateRegistration"
                                        label="Inscricao estadual"
                                        @update:model-value="updateQuickField('stateRegistration', $event)"
                                    />
                                    <AppInput
                                        :model-value="quickForm.fantasyName"
                                        label="Nome fantasia"
                                        @update:model-value="updateQuickField('fantasyName', $event)"
                                    />
                                    <AppInput
                                        :model-value="quickForm.country"
                                        label="Pais"
                                        @update:model-value="updateQuickField('country', $event)"
                                    />
                                    <AppTextarea
                                        :model-value="quickForm.notes"
                                        label="Observacao"
                                        rows="3"
                                        class="md:col-span-2"
                                        @update:model-value="updateQuickField('notes', $event)"
                                    />
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <AppButton :loading="savingQuickCustomer" @click="saveQuickCustomer">Salvar e selecionar cliente</AppButton>
                                    <AppButton variant="secondary" @click="resetQuickForm">Limpar formulario</AppButton>
                                </div>

                                <div v-if="selectedCustomer" class="selected-customer">
                                    <p class="font-bold text-main">{{ selectedCustomer.nome }}</p>
                                    <p class="text-xs text-muted">Cliente rapido selecionado para esta venda.</p>
                                </div>
                            </div>

                            <p v-if="customerStepError" class="text-sm text-danger">{{ customerStepError }}</p>
                        </div>

                        <div v-if="currentStep === 2" class="payment-step-layout">
                            <section class="ui-card p-4 space-y-3 payment-methods-card">
                                <h3 class="text-base font-bold text-main">Formas de pagamento</h3>
                                <p v-if="paymentMethodsLoading" class="text-sm text-muted">Carregando formas de pagamento...</p>
                                <div v-else class="payment-methods-scroll">
                                    <PaymentMethodsGrid
                                        :methods="paymentMethods"
                                        :selected-method-id="selectedPaymentMethodId"
                                        @select="selectPaymentMethod"
                                    />
                                </div>
                            </section>

                            <section class="ui-card p-4 space-y-3 payment-composition-card">
                                <h3 class="text-base font-bold text-main">Composicao de pagamentos</h3>

                                <div v-if="isCreditMethod && selectedPaymentMethod?.permite_parcelamento" class="credit-config-wrap">
                                    <div class="credit-config-grid">
                                        <AppSelect v-model="selectedInstallmentOptionId" label="Parcelamento">
                                            <option
                                                v-for="option in availableInstallmentOptions"
                                                :key="option.id"
                                                :value="option.id"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </AppSelect>
                                        <div class="credit-config-card">
                                            <p>Acrecimo aplicado: <strong>{{ formatPercent(selectedInterestRate) }}</strong></p>
                                            <p v-if="selectedInstallments > 1">
                                                Parcela estimada:
                                                <strong>{{ formatCurrency(enteredInstallmentAmount) }}</strong>
                                            </p>
                                            <p>
                                                Total com Acrecimos:
                                                <strong>{{ formatCurrency(enteredPaymentTotal) }}</strong>
                                            </p>
                                            <p v-if="paymentPlansLoading" class="text-muted">Carregando planos...</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="payment-add-row">
                                    <AppInput
                                        id="finalize-payment-amount"
                                        class="payment-amount-input"
                                        :model-value="paymentAmount"
                                        placeholder="Valor (ex.: 32,00)"
                                        @update:model-value="paymentAmount = $event"
                                        @keydown.enter.prevent.stop="addPayment"
                                    />
                                    <AppButton class="payment-add-btn" @click="addPayment">Adicionar</AppButton>
                                </div>
                                <p v-if="paymentError" class="text-sm text-danger">{{ paymentError }}</p>

                                <PaymentCompositionList :payments="payments" :format-currency="formatCurrency" @remove="removePayment" />
                            </section>

                            <section class="payment-totals payment-step-totals">
                                <div>
                                    <span>Total de produtos</span>
                                    <strong>{{ formatCurrency(netTotal) }}</strong>
                                </div>
                                <div>
                                    <span>Acrecimo no pagamento</span>
                                    <strong>{{ formatCurrency(displayedSurchargeTotal) }}</strong>
                                </div>
                                <div>
                                    <span>Total com Acrecimos</span>
                                    <strong>{{ formatCurrency(displayedNetTotal) }}</strong>
                                </div>
                                <div>
                                    <span>Total alocado</span>
                                    <strong>{{ formatCurrency(displayedPaidTotal) }}</strong>
                                </div>
                                <div>
                                    <span>Falta pagar</span>
                                    <strong>{{ formatCurrency(displayedRemainingTotal) }}</strong>
                                </div>
                                <div>
                                    <span>Troco</span>
                                    <strong>{{ formatCurrency(displayedChangeTotal) }}</strong>
                                </div>
                            </section>

                            <p v-if="paymentValidationMessage" class="text-sm text-warning payment-step-warning">
                                {{ paymentValidationMessage }}
                            </p>
                        </div>

                        <div v-if="currentStep === 3" class="space-y-4">
                            <section class="finalization-panel">
                                <p class="finalization-eyebrow">Pronto para emitir</p>
                                <h3 class="finalization-title">Finalizacao da venda</h3>
                                <p class="finalization-subtitle">
                                    Confira os totais e confirme a emissão da {{ selectedDocumentLabel }} para concluir esta venda.
                                </p>

                                <FiscalDocumentSelector
                                    :document-model="fiscalDefaults.documentModel"
                                    :document-series="fiscalDefaults.documentSeries"
                                    :disabled="processingEmission"
                                    @update:document-model="fiscalDefaults.documentModel = $event"
                                    @update:document-series="fiscalDefaults.documentSeries = String($event || '').trim() || '1'"
                                />

                                <div class="finalization-grid">
                                    <div>
                                        <span>Total de produtos</span>
                                        <strong>{{ formatCurrency(netTotal) }}</strong>
                                    </div>
                                    <div>
                                        <span>Acrecimo no pagamento</span>
                                        <strong>{{ formatCurrency(interestTotal) }}</strong>
                                    </div>
                                    <div>
                                        <span>Total com Acrecimos</span>
                                        <strong>{{ formatCurrency(payableTotal) }}</strong>
                                    </div>
                                    <div>
                                        <span>Total alocado</span>
                                        <strong>{{ formatCurrency(paidTotal) }}</strong>
                                    </div>
                                </div>

                                <p v-if="paymentValidationMessage" class="text-sm text-warning">{{ paymentValidationMessage }}</p>

                                <div class="finalization-actions">
                                    <AppButton variant="secondary" :disabled="processingEmission" @click="goBack">
                                        Revisar pagamentos
                                    </AppButton>
                                    <AppButton
                                        id="finalize-emit-button"
                                        class="emit-progress-btn"
                                        :loading="processingEmission"
                                        :disabled="!canEmit"
                                        @click="emitNfce"
                                    >
                                        <span
                                            class="emit-progress-fill"
                                            :style="{ width: `${autoEmitProgress}%` }"
                                            aria-hidden="true"
                                        />
                                        <span class="emit-progress-label">
                                            {{
                                                processingEmission
                                                    ? `Enviando ${selectedDocumentLabel}...`
                                                    : autoEmitCountdownActive
                                                        ? `Emitir ${selectedDocumentLabel} (${autoEmitSecondsLeft}s)`
                                                        : `Emitir ${selectedDocumentLabel}`
                                            }}
                                        </span>
                                    </AppButton>
                                </div>
                            </section>
                        </div>
                    </template>

                    <p v-if="emissionError" class="text-sm text-danger">{{ emissionError }}</p>
                    <p v-if="actionFeedback" class="text-sm text-success">{{ actionFeedback }}</p>
                </section>

                <SaleSummaryCard
                    :customer-label="customerLabel"
                    :items-count="itemsCount"
                    :products-total="productsTotal"
                    :discount-total="discountTotal"
                    :surcharge-total="displayedSurchargeTotal"
                    surcharge-label="Acrecimo"
                    :net-total="displayedNetTotal"
                    :paid-total="displayedPaidTotal"
                    :remaining-total="displayedRemainingTotal"
                    :change-total="displayedChangeTotal"
                    :status-label="statusLabel"
                    :note-summary="noteSummary"
                    :format-currency="formatCurrency"
                />
            </div>

            <footer v-if="!successReceipt && currentStep < 3" class="finalize-footer">
                <AppButton variant="ghost" :disabled="!canGoBack" @click="goBack">Voltar</AppButton>
                <div class="ml-auto flex items-center gap-2">
                    <AppButton
                        v-if="canContinue"
                        id="finalize-continue-button"
                        variant="secondary"
                        @click="goNext"
                    >
                        Continuar
                    </AppButton>
                </div>
            </footer>
        </div>
    </AppModal>
</template>

<style scoped>
.finalize-wrap {
    display: grid;
    grid-template-rows: auto minmax(0, 1fr) auto;
    gap: 1rem;
    height: min(86vh, 860px);
    min-height: 0;
}

.finalize-head {
    display: grid;
    gap: 0.72rem;
}

.finalize-subtitle {
    margin: 0;
    color: var(--color-text-muted);
    font-size: 0.86rem;
}

.finalize-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(260px, 320px);
    gap: 1rem;
    min-height: 0;
    overflow: hidden;
}

.finalize-content {
    min-width: 0;
    display: grid;
    align-content: start;
    gap: 0.7rem;
    min-height: 0;
    overflow: auto;
    padding-right: 0.2rem;
}

.option-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.7rem;
}

.option-card {
    border-radius: var(--radius-md);
    border: 1px solid var(--color-border);
    background: color-mix(in srgb, var(--color-bg-elevated) 78%, var(--color-bg-surface));
    text-align: left;
    padding: 0.72rem 0.8rem;
    transition: border-color var(--transition-fast), transform var(--transition-fast), background var(--transition-fast);
}

.option-card:hover {
    border-color: var(--color-border-strong);
    transform: translateY(-1px);
}

.option-card:focus-visible {
    border-color: color-mix(in srgb, var(--color-primary) 60%, var(--color-border));
    transform: translateY(-1px);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary) 24%, transparent);
}

.option-card.is-active {
    border-color: color-mix(in srgb, var(--color-primary) 56%, var(--color-border));
    background: color-mix(in srgb, var(--color-primary) 14%, var(--color-bg-surface));
}

.option-card p {
    margin: 0;
    font-size: 0.9rem;
    font-weight: 800;
    color: var(--color-text);
}

.option-card small {
    display: block;
    margin-top: 0.2rem;
    font-size: 0.75rem;
    color: var(--color-text-muted);
}

.search-results {
    display: grid;
    gap: 0.48rem;
}

.search-card {
    border-radius: var(--radius-sm);
    border: 1px solid color-mix(in srgb, var(--color-border) 74%, transparent);
    background: var(--color-bg-surface);
    padding: 0.6rem 0.7rem;
    display: grid;
    gap: 0.5rem;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
}

.search-card:focus-within {
    border-color: color-mix(in srgb, var(--color-primary) 52%, var(--color-border));
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary) 20%, transparent);
}

.selected-customer {
    border-radius: var(--radius-sm);
    border: 1px solid color-mix(in srgb, var(--color-success) 36%, transparent);
    background: color-mix(in srgb, var(--color-success) 10%, var(--color-bg-surface));
    padding: 0.56rem 0.65rem;
}

.payment-step-layout {
    display: grid;
    gap: 0.75rem;
    grid-template-columns: minmax(0, 1.05fr) minmax(0, 1fr);
    align-content: start;
}

.payment-methods-card,
.payment-composition-card {
    min-height: 0;
}

.payment-methods-scroll {
    max-height: clamp(170px, 30vh, 260px);
    overflow: auto;
    padding-right: 0.2rem;
}

.credit-config-wrap {
    border-radius: var(--radius-md);
    border: 1px solid color-mix(in srgb, var(--color-primary) 30%, var(--color-border));
    background: color-mix(in srgb, var(--color-primary) 8%, var(--color-bg-surface));
    padding: 0.6rem 0.7rem;
}

.credit-config-grid {
    display: grid;
    gap: 0.55rem;
    grid-template-columns: minmax(0, 1fr) minmax(220px, 0.9fr);
    align-items: start;
}

.credit-config-card {
    border-radius: var(--radius-sm);
    border: 1px solid color-mix(in srgb, var(--color-border) 76%, transparent);
    background: color-mix(in srgb, var(--color-bg-surface) 88%, var(--color-bg-elevated));
    padding: 0.55rem 0.6rem;
    font-size: 0.79rem;
    color: var(--color-text-muted);
    display: grid;
    gap: 0.22rem;
}

.credit-config-card p {
    margin: 0;
}

.credit-config-card strong {
    color: var(--color-text);
}

.payment-add-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: end;
    gap: 0.45rem;
}

.payment-amount-input {
    min-width: 0;
}

.payment-amount-input :deep(.ui-field) {
    padding-top: 0.58rem;
    padding-bottom: 0.58rem;
}

.payment-add-btn {
    white-space: nowrap;
}

.payment-totals {
    border-radius: var(--radius-md);
    border: 1px solid var(--color-border);
    background: color-mix(in srgb, var(--color-bg-elevated) 84%, var(--color-bg-surface));
    padding: 0.8rem;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.55rem;
}

.payment-totals span {
    display: block;
    font-size: 0.73rem;
    color: var(--color-text-muted);
}

.payment-totals strong {
    display: block;
    margin-top: 0.15rem;
    color: var(--color-text);
    font-size: 0.9rem;
}

.payment-step-totals {
    grid-column: 1 / -1;
}

.payment-step-warning {
    grid-column: 1 / -1;
    margin-top: -0.1rem;
}

.finalization-panel {
    border: 1px solid color-mix(in srgb, var(--color-primary) 40%, var(--color-border));
    border-radius: var(--radius-lg);
    background: linear-gradient(
        140deg,
        color-mix(in srgb, var(--color-primary) 14%, var(--color-bg-surface)),
        color-mix(in srgb, var(--color-bg-elevated) 84%, var(--color-bg-surface))
    );
    padding: 1rem;
    display: grid;
    gap: 0.85rem;
}

.finalization-eyebrow {
    margin: 0;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--color-primary);
}

.finalization-title {
    margin: 0;
    font-size: 1.2rem;
    line-height: 1.2;
    font-weight: 900;
    color: var(--color-text);
}

.finalization-subtitle {
    margin: 0;
    color: var(--color-text-muted);
    font-size: 0.86rem;
}

.finalization-grid {
    border-radius: var(--radius-md);
    border: 1px solid color-mix(in srgb, var(--color-border) 76%, transparent);
    background: color-mix(in srgb, var(--color-bg-surface) 86%, var(--color-bg-elevated));
    padding: 0.75rem;
    display: grid;
    gap: 0.6rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.finalization-grid span {
    display: block;
    font-size: 0.72rem;
    color: var(--color-text-muted);
}

.finalization-grid strong {
    display: block;
    margin-top: 0.16rem;
    color: var(--color-text);
    font-size: 0.96rem;
    font-weight: 800;
}

.finalization-actions {
    display: grid;
    gap: 0.55rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.emit-progress-btn {
    position: relative;
    overflow: hidden;
    isolation: isolate;
}

.emit-progress-fill {
    position: absolute;
    inset: 0 auto 0 0;
    width: 0%;
    background: color-mix(in srgb, var(--color-text-inverse) 18%, transparent);
    transition: width 80ms linear;
    z-index: 0;
}

.emit-progress-label {
    position: relative;
    z-index: 1;
}

.finalize-footer {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    border-top: 1px solid color-mix(in srgb, var(--color-border) 82%, transparent);
    padding-top: 0.8rem;
    min-height: 0;
}

:deep(.finalize-modal-panel) {
    width: min(96vw, 1300px);
    max-height: calc(100vh - 1.2rem);
    overflow: hidden;
    padding: 1rem 1.2rem;
}

@media (max-width: 1024px) {
    .finalize-wrap {
        height: min(90vh, 920px);
    }

    .finalize-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 840px) {
    .option-grid {
        grid-template-columns: 1fr;
    }

    .search-card {
        grid-template-columns: 1fr;
    }

    .payment-step-layout {
        grid-template-columns: 1fr;
    }

    .payment-methods-scroll {
        max-height: 240px;
    }

    .payment-add-row {
        grid-template-columns: 1fr;
    }

    .credit-config-grid {
        grid-template-columns: 1fr;
    }

    .finalization-grid,
    .finalization-actions {
        grid-template-columns: 1fr;
    }
}
</style>
