<script setup>
import { Boxes, CirclePlus, Menu, Percent, Search, X } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import {
    clearAuthData,
    clearSettingsAccessKey,
    exitIntegratedPdv,
    getTerminalSession,
    getUserRole,
    resolvePdvExitLabel,
    setSettingsAccessKey,
} from '../lib/auth';
import api from '../lib/api';
import {
    fetchCommandCenterSnapshot,
    registerCommandConference,
    registerCommandMerge,
    registerCommandPrintAction,
    registerCommandTransfer,
    reintegrateCommandCenter,
} from '../lib/restaurantCommandCenter';
import { formatCurrency } from '../lib/format';
import { useRoute, useRouter } from 'vue-router';
import { useKeyboardShortcuts } from '../composables/useKeyboardShortcuts';
import PosShell from '../components/pos/PosShell.vue';
import PosCategoryRail from '../components/pos/PosCategoryRail.vue';
import PosHeader from '../components/pos/PosHeader.vue';
import PosSearchBar from '../components/pos/PosSearchBar.vue';
import PosProductGrid from '../components/pos/PosProductGrid.vue';
import PosProductCard from '../components/pos/PosProductCard.vue';
import PosCartPanel from '../components/pos/PosCartPanel.vue';
import PosPaymentSummary from '../components/pos/PosPaymentSummary.vue';
import PosBottomActions from '../components/pos/PosBottomActions.vue';
import SaleReceiptPreview from '../components/pos/SaleReceiptPreview.vue';
import SelectedItemPreview from '../components/pos/SelectedItemPreview.vue';
import PosShortcutsDialog from '../components/pos/PosShortcutsDialog.vue';
import PosCancelDialog from '../components/pos/PosCancelDialog.vue';
import FinalizeSaleModal from '../components/pos/finalize/FinalizeSaleModal.vue';
import CommandCenterModal from '../components/pos/command-center/CommandCenterModal.vue';
import PosCustomerBadge from '../components/pos/PosCustomerBadge.vue';
import PosNfceStatus from '../components/pos/PosNfceStatus.vue';
import AppThemeToggle from '../components/layout/AppThemeToggle.vue';
import AppToast from '../components/ui/AppToast.vue';
import AppModal from '../components/ui/AppModal.vue';
import AppButton from '../components/ui/AppButton.vue';
import AppInput from '../components/ui/AppInput.vue';
import AppSelect from '../components/ui/AppSelect.vue';
import AppTooltip from '../components/ui/AppTooltip.vue';
import { useRestaurantCommandCenter } from '../composables/useRestaurantCommandCenter';

const router = useRouter();
const route = useRoute();
const categories = ref([]);
const products = ref([]);
const search = ref('');
const category = ref('todos');
const cart = reactive([]);
const posShellRef = ref(null);
const searchBarRef = ref(null);
const consultSearchBarRef = ref(null);
const shortcutsOpen = ref(false);
const cancelDialogOpen = ref(false);
const finalizeModalOpen = ref(false);
const productConsultModalOpen = ref(false);
const restaurantComandaModalOpen = ref(false);
const productConsultSearch = ref('');
const productConsultDepartment = ref('todos');
const saleCustomerLabel = ref('Cliente balcão');
const activeSaleCommandContext = ref(null);
const posLayoutMode = ref('varejo');
const restaurantOperationalMode = ref('comanda_garcom');
const selfServiceCustomerName = ref('');
const selfServiceCustomerInput = ref('');
const selfServiceTicketCode = ref('');
const selfServiceStartedAt = ref('');
const selfServiceCurrentClock = ref('');
const selfServiceViewportMode = ref('web');
const selfServiceNameInputRef = ref(null);
const totemDrawerOpen = ref(false);
const totemEdgeTriggerVisible = ref(false);
const totemCartDialogOpen = ref(false);
const totemAdminPinInputRef = ref(null);
const totemTouchGesture = reactive({
    tracking: false,
    startX: 0,
    startY: 0,
});
const totemActionGuard = reactive({
    open: false,
    mode: 'credentials',
    adminEmail: '',
    adminPassword: '',
    adminPin: '',
    loading: false,
    error: '',
    pendingActionId: '',
});
const receiptEmitter = reactive({
    name: '',
    cnpj: '',
    ie: '',
    address: '',
    city: '',
    state: '',
    phone: '',
});
const toastVisible = ref(false);
const toastMessage = ref('');
const toastTone = ref('success');
const adjustmentModal = reactive({
    open: false,
    kind: 'surcharge',
});
const adjustmentForm = reactive({
    mode: 'value',
    amount: '',
    quantity: '1',
});
const surchargeAdjustment = reactive({
    active: false,
    mode: 'value',
    amount: 0,
});
const discountAdjustment = reactive({
    active: false,
    mode: 'value',
    amount: 0,
});
const productDraftQuantities = reactive({});
const multiplierAdjustment = reactive({
    active: false,
    quantity: 1,
});
const settingsUnlockModal = reactive({
    open: false,
    mode: 'credentials',
    adminEmail: '',
    adminPassword: '',
    adminPin: '',
    loading: false,
    error: '',
});
const adminPinInputRef = ref(null);
const cancelUnlockModal = reactive({
    open: false,
    mode: 'credentials',
    adminEmail: '',
    adminPassword: '',
    adminPin: '',
    loading: false,
    error: '',
});
const cancelAdminPinInputRef = ref(null);
const isOperatorUser = computed(() => getUserRole() === 'operador');
const adminPinLength = computed(() =>
    String(settingsUnlockModal.adminPin ?? '')
        .replace(/\D/g, '')
        .slice(0, 6)
        .length,
);
const cancelAdminPinLength = computed(() =>
    String(cancelUnlockModal.adminPin ?? '')
        .replace(/\D/g, '')
        .slice(0, 6)
        .length,
);
const canSubmitSettingsUnlock = computed(() => {
    if (settingsUnlockModal.loading) return false;

    if (settingsUnlockModal.mode === 'pin') {
        return adminPinLength.value === 6;
    }

    return Boolean(
        settingsUnlockModal.adminEmail.trim() &&
        settingsUnlockModal.adminPassword,
    );
});
const canSubmitCancelUnlock = computed(() => {
    if (cancelUnlockModal.loading) return false;

    if (cancelUnlockModal.mode === 'pin') {
        return cancelAdminPinLength.value === 6;
    }

    return Boolean(
        cancelUnlockModal.adminEmail.trim() &&
        cancelUnlockModal.adminPassword,
    );
});
const totemAdminPinLength = computed(() =>
    String(totemActionGuard.adminPin ?? '')
        .replace(/\D/g, '')
        .slice(0, 6)
        .length,
);
const canSubmitTotemGuard = computed(() => {
    if (totemActionGuard.loading) return false;

    if (totemActionGuard.mode === 'pin') {
        return totemAdminPinLength.value === 6;
    }

    return Boolean(
        totemActionGuard.adminEmail.trim() &&
        totemActionGuard.adminPassword,
    );
});
let toastTimeout = null;
let lastFocusedBeforeShortcuts = null;
let lastFocusedBeforeAdjustment = null;
let selfServiceClockInterval = null;
const fixedShortcuts = new Set(['f1', 'f2', 'f3', 'f6', 'f7', 'f8', 'f9', 'f10', 'f11']);
const editableAllowedShortcuts = new Set([...fixedShortcuts, '+', 'escape']);
const directionalKeyMap = Object.freeze({
    arrowup: 'up',
    arrowdown: 'down',
    arrowleft: 'left',
    arrowright: 'right',
});
const altGraphDirectionalKeyMap = Object.freeze({
    w: 'up',
    a: 'left',
    s: 'down',
    d: 'right',
});
const altGraphDirectionalCodeMap = Object.freeze({
    KeyW: 'up',
    KeyA: 'left',
    KeyS: 'down',
    KeyD: 'right',
});
const keyboardNavigableSelector = [
    'button:not([disabled]):not([aria-disabled="true"]):not([data-nav-skip="true"])',
    'a[href]',
    'input:not([type="hidden"]):not([disabled]):not([data-nav-skip="true"])',
    'select:not([disabled]):not([data-nav-skip="true"])',
    'textarea:not([disabled]):not([data-nav-skip="true"])',
    '[tabindex]:not([tabindex="-1"]):not([data-nav-skip="true"])',
].join(', ');
const productIdentifierFields = Object.freeze([
    'sku',
    'codigo',
    'codigo_interno',
    'codigoInterno',
    'codigo_barras',
    'codigoBarras',
    'barcode',
    'ean',
    'gtin',
    'upc',
    'plu',
]);
const validPosLayoutModes = new Set(['varejo', 'restaurante', 'servicos']);
const validRestaurantOperationModes = new Set(['auto_atendimento', 'totem', 'caixa', 'comanda_bar', 'comanda_cozinha', 'comanda_garcom']);
const isRestaurantMode = computed(() => posLayoutMode.value === 'restaurante');
const isSelfServiceMode = computed(
    () => isRestaurantMode.value && restaurantOperationalMode.value === 'totem',
);
const isTotemSessionStarted = computed(
    () => isSelfServiceMode.value && Boolean(String(selfServiceCustomerName.value || '').trim()),
);
const budgetShortcutLabel = computed(() => {
    if (isSelfServiceMode.value) return 'Conta';
    return isRestaurantMode.value ? 'Comandas' : 'Orçamento';
});
const searchPlaceholder = computed(() =>
    isSelfServiceMode.value
        ? 'Buscar item por nome ou código no auto atendimento'
        : isRestaurantMode.value
        ? 'Buscar item por nome ou código para lançar na comanda'
        : 'Buscar produto ou digitar código para lançar',
);
const searchConfirmLabel = computed(() => {
    if (isSelfServiceMode.value) return 'Adicionar item';
    return isRestaurantMode.value ? 'Lançar item' : 'Confirmar produto';
});
const posCanvasTitle = computed(() => {
    if (isSelfServiceMode.value) return 'Totem de Autoatendimento';
    return isRestaurantMode.value ? 'PDV Restaurante' : 'Ponto de Venda';
});
const posShellModeClass = computed(() => {
    const classes = [`pos-mode-${posLayoutMode.value}`];
    if (isSelfServiceMode.value) {
        classes.push('pos-mode-totem', `pos-mode-totem-${selfServiceViewportMode.value}`);
        if (!isTotemSessionStarted.value) {
            classes.push('pos-mode-totem-prestart');
        }
    }
    return classes.join(' ');
});
const selfServiceCategoryOptions = computed(() => [
    { id: 'todos', nome: 'Todos' },
    ...categories.value.map((item) => ({ id: item.id, nome: item.nome })),
]);
const selfServiceViewportLabel = computed(() => {
    if (selfServiceViewportMode.value === 'tablet-horizontal') return 'Tablet (horizontal)';
    if (selfServiceViewportMode.value === 'vertical') return 'Vertical';
    return 'Web';
});
const totemCartItemCountLabel = computed(() => `${cart.length} ${cart.length === 1 ? 'item' : 'itens'}`);
const exitLabel = computed(() => resolvePdvExitLabel());
const totemMenuActions = computed(() => [
    { id: 'cancel-item', key: 'F2', label: 'Cancelamento' },
    { id: 'identify-customer', key: 'F10', label: 'Cliente' },
    { id: 'open-budget', key: 'O', label: 'Conta' },
    { id: 'identify-seller', key: 'V', label: 'Vendedor' },
    { id: 'open-shortcuts', key: 'F11', label: 'Atalhos' },
    { id: 'open-settings', key: '', label: 'Configurações' },
    { id: 'logout', key: '', label: exitLabel.value },
]);
const restaurantCommandCenter = useRestaurantCommandCenter({
    getProducts: () => products.value,
    api: {
        fetchSnapshot: () => fetchCommandCenterSnapshot(),
        reintegrate: () => reintegrateCommandCenter(),
        registerTransfer: (payload) => registerCommandTransfer(payload),
        registerMerge: (payload) => registerCommandMerge(payload),
        registerPrintAction: (payload) => registerCommandPrintAction(payload),
        registerConference: (payload) => registerCommandConference(payload),
    },
});
const restaurantCommandState = restaurantCommandCenter.state;
const openedRestaurantTables = restaurantCommandCenter.filteredOpenedTables;
const closedRestaurantTables = restaurantCommandCenter.filteredClosedTables;
const selectedRestaurantTable = restaurantCommandCenter.selectedTable;
const selectedRestaurantCommand = restaurantCommandCenter.selectedCommand;
const canImportSelectedRestaurantCommand = restaurantCommandCenter.canImportSelectedCommand;
const restaurantCommandSummary = restaurantCommandCenter.modalSummary;
const allRestaurantTables = computed(() => [...closedRestaurantTables.value, ...openedRestaurantTables.value]);
const restaurantCommandCenterContext = computed(() => {
    const queryContext = String(route.query.pdvContext || '').trim().toLowerCase();
    if (queryContext === 'terminal') return 'terminal';
    if (queryContext === 'waiter') return 'waiter';
    if (isOperatorUser.value) return 'waiter';
    return 'cashier';
});

function roundMoney(value) {
    return Math.round((Number(value) || 0) * 100) / 100;
}

function normalizeQuantity(value) {
    return Math.round((Number(value) || 0) * 1000) / 1000;
}

function formatDecimal(value, decimals = 3) {
    return Number(value || 0).toLocaleString('pt-BR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: decimals,
    });
}

function normalizeDraftQuantity(value) {
    const normalized = Math.round(Number(value) || 0);
    return normalized > 0 ? normalized : 1;
}

function normalizeSearchToken(value) {
    return String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim()
        .toLowerCase();
}

function parseInlineMultiplierToken(value) {
    const match = String(value ?? '').trim().match(/^\*\s*(\d+(?:[.,]\d{1,3})?)$/);
    if (!match) return null;

    const parsedQuantity = Number(match[1].replace(',', '.'));
    const quantity = normalizeQuantity(parsedQuantity);
    if (!Number.isFinite(quantity) || quantity <= 0) return null;

    return quantity;
}

function normalizeRestaurantOperationMode(value) {
    const normalized = String(value || '').trim().toLowerCase();
    return validRestaurantOperationModes.has(normalized) ? normalized : 'comanda_garcom';
}

function resolveSelfServiceViewportMode() {
    const width = window.innerWidth || 0;
    const height = window.innerHeight || 0;

    if (width >= 1200) return 'web';
    if (width >= 900 && width > height) return 'tablet-horizontal';
    return 'vertical';
}

function updateSelfServiceViewportMode() {
    selfServiceViewportMode.value = resolveSelfServiceViewportMode();
}

function focusSelfServiceNameInput() {
    nextTick(() => {
        selfServiceNameInputRef.value?.focus?.();
    });
}

function updateSelfServiceClock() {
    selfServiceCurrentClock.value = new Date().toLocaleTimeString('pt-BR', {
        hour: '2-digit',
        minute: '2-digit',
    });
}

function startSelfServiceClock() {
    updateSelfServiceClock();
    if (selfServiceClockInterval) clearInterval(selfServiceClockInterval);
    selfServiceClockInterval = window.setInterval(updateSelfServiceClock, 30_000);
}

function stopSelfServiceClock() {
    if (!selfServiceClockInterval) return;
    clearInterval(selfServiceClockInterval);
    selfServiceClockInterval = null;
}

function resetSelfServiceSession(options = { keepInput: false }) {
    const { keepInput = false } = options || {};

    selfServiceCustomerName.value = '';
    selfServiceTicketCode.value = '';
    selfServiceStartedAt.value = '';
    if (!keepInput) {
        selfServiceCustomerInput.value = '';
    }
    saleCustomerLabel.value = 'Cliente balcão';
    activeSaleCommandContext.value = null;
}

function generateSelfServiceTicketCode(baseDate = new Date()) {
    const hh = String(baseDate.getHours()).padStart(2, '0');
    const mm = String(baseDate.getMinutes()).padStart(2, '0');
    const ss = String(baseDate.getSeconds()).padStart(2, '0');
    const randomPart = Math.floor(Math.random() * 90 + 10);
    return `F${hh}${mm}${ss}-${randomPart}`;
}

function startSelfServiceSession() {
    const customerName = String(selfServiceCustomerInput.value || '').trim();
    if (customerName.length < 2) {
        showShortcutFeedback('Informe seu nome para iniciar o auto atendimento.', 'danger');
        focusSelfServiceNameInput();
        return;
    }

    const now = new Date();
    selfServiceCustomerName.value = customerName;
    selfServiceTicketCode.value = generateSelfServiceTicketCode(now);
    selfServiceStartedAt.value = now.toLocaleTimeString('pt-BR', {
        hour: '2-digit',
        minute: '2-digit',
    });

    saleCustomerLabel.value = customerName.length > 20 ? `${customerName.slice(0, 20)}...` : customerName;
    showShortcutFeedback(`Olá, ${customerName}! Sua ficha foi aberta.`);
    focusSearchField();
}

function ensureSelfServiceSessionStarted() {
    if (!isSelfServiceMode.value) return true;
    if (isTotemSessionStarted.value) return true;

    showShortcutFeedback('Informe seu nome para abrir a ficha antes de lançar itens.', 'danger');
    focusSelfServiceNameInput();
    return false;
}

function resetTotemActionGuard() {
    totemActionGuard.mode = 'credentials';
    totemActionGuard.adminEmail = '';
    totemActionGuard.adminPassword = '';
    totemActionGuard.adminPin = '';
    totemActionGuard.loading = false;
    totemActionGuard.error = '';
    totemActionGuard.pendingActionId = '';
}

function closeTotemActionGuard() {
    totemActionGuard.open = false;
    totemActionGuard.loading = false;
    totemActionGuard.error = '';
    totemActionGuard.adminPassword = '';
    totemActionGuard.adminPin = '';
    totemActionGuard.pendingActionId = '';
}

function openTotemActionGuard(actionId) {
    resetTotemActionGuard();
    totemActionGuard.pendingActionId = String(actionId || '');
    totemActionGuard.open = true;
}

function focusTotemAdminPinInput() {
    nextTick(() => {
        totemAdminPinInputRef.value?.focus?.();
    });
}

function handleTotemAdminPinInput(event) {
    const rawValue = event?.target?.value ?? totemActionGuard.adminPin;
    totemActionGuard.adminPin = String(rawValue)
        .replace(/\D/g, '')
        .slice(0, 6);
}

function openTotemDrawer() {
    if (!isTotemSessionStarted.value) return;
    totemDrawerOpen.value = true;
    totemEdgeTriggerVisible.value = false;
}

function closeTotemDrawer() {
    totemDrawerOpen.value = false;
}

function openTotemCartDialog() {
    if (!isTotemSessionStarted.value) return;
    totemCartDialogOpen.value = true;
}

function closeTotemCartDialog() {
    totemCartDialogOpen.value = false;
}

function handleTotemMouseMove(event) {
    if (!isTotemSessionStarted.value || totemDrawerOpen.value) {
        totemEdgeTriggerVisible.value = false;
        return;
    }

    const clientX = Number(event?.clientX || 0);
    totemEdgeTriggerVisible.value = clientX <= 18;
}

function handleTotemTouchStart(event) {
    if (!isTotemSessionStarted.value || totemDrawerOpen.value) {
        totemTouchGesture.tracking = false;
        return;
    }

    const touch = event.touches?.[0];
    if (!touch) {
        totemTouchGesture.tracking = false;
        return;
    }

    totemTouchGesture.tracking = touch.clientX <= 22;
    totemTouchGesture.startX = touch.clientX;
    totemTouchGesture.startY = touch.clientY;
}

function handleTotemTouchMove(event) {
    if (!totemTouchGesture.tracking || !isTotemSessionStarted.value || totemDrawerOpen.value) return;

    const touch = event.touches?.[0];
    if (!touch) return;

    const deltaX = touch.clientX - totemTouchGesture.startX;
    const deltaY = Math.abs(touch.clientY - totemTouchGesture.startY);

    if (deltaY > 64 || deltaX < -12) {
        totemTouchGesture.tracking = false;
        return;
    }

    if (deltaX >= 72 && deltaY <= 42) {
        totemTouchGesture.tracking = false;
        openTotemDrawer();
    }
}

function handleTotemTouchEnd() {
    totemTouchGesture.tracking = false;
}

function runTotemAction(actionId) {
    const normalizedId = String(actionId || '');

    if (normalizedId === 'cancel-item') {
        cancelDialogOpen.value = true;
        return;
    }

    if (normalizedId === 'identify-customer') {
        runPlannedShortcut('Identificar cliente');
        return;
    }

    if (normalizedId === 'open-budget') {
        handleBudgetShortcut();
        return;
    }

    if (normalizedId === 'identify-seller') {
        runPlannedShortcut('Identificar vendedor');
        return;
    }

    if (normalizedId === 'open-shortcuts') {
        openShortcuts();
        return;
    }

    if (normalizedId === 'logout') {
        logout();
    }
}

function requestTotemAction(actionId) {
    if (!isSelfServiceMode.value) {
        runTotemAction(actionId);
        return;
    }

    openTotemActionGuard(actionId);
}

async function authorizeTotemAction() {
    if (!canSubmitTotemGuard.value) {
        totemActionGuard.error =
            totemActionGuard.mode === 'pin'
                ? 'Informe um PIN válido com 6 dígitos.'
                : 'Informe e-mail e senha do administrador.';
        return;
    }

    totemActionGuard.error = '';
    totemActionGuard.loading = true;

    try {
        const payload =
            totemActionGuard.mode === 'pin'
                ? { admin_pin: totemActionGuard.adminPin }
                : {
                    admin_email: totemActionGuard.adminEmail.trim(),
                    admin_password: totemActionGuard.adminPassword,
                };

        const actionId = String(totemActionGuard.pendingActionId || '');
        if (actionId === 'open-settings') {
            const { data } = await api.post('/auth/settings/authorize', payload);
            if (data?.settings_access_key) {
                setSettingsAccessKey(data.settings_access_key);
            }
            closeTotemActionGuard();
            closeTotemDrawer();
            await router.push('/configuracoes');
            showShortcutFeedback('Configurações liberadas pelo administrador.');
            return;
        }

        await api.post('/auth/cancel/authorize', payload);
        closeTotemActionGuard();
        closeTotemDrawer();
        runTotemAction(actionId);
    } catch (error) {
        totemActionGuard.error =
            error?.response?.data?.message ?? 'Não foi possível validar as credenciais do administrador.';
    } finally {
        totemActionGuard.loading = false;
    }
}

function assignReceiptEmitter(payload) {
    const nextLayoutMode = String(payload?.pdv_layout_mode || '').trim().toLowerCase();
    if (validPosLayoutModes.has(nextLayoutMode)) {
        posLayoutMode.value = nextLayoutMode;
    } else {
        posLayoutMode.value = 'varejo';
    }

    const payloadRestaurantMode = String(payload?.pdv_restaurant_mode || '').trim();
    const sessionRestaurantMode = getTerminalSession()?.restaurantMode;
    const restaurantModeSource = payloadRestaurantMode || sessionRestaurantMode || '';
    restaurantOperationalMode.value = normalizeRestaurantOperationMode(restaurantModeSource);

    receiptEmitter.name = String(payload?.name ?? '').trim();
    receiptEmitter.cnpj = String(payload?.cnpj ?? '').trim();
    receiptEmitter.ie = String(payload?.ie ?? '').trim();
    receiptEmitter.address = String(payload?.address ?? '').trim();
    receiptEmitter.city = String(payload?.city ?? '').trim();
    receiptEmitter.state = String(payload?.state ?? '').trim();
    receiptEmitter.phone = String(payload?.phone ?? '').trim();
}

function normalizeAdjustmentEntry(value) {
    if (!value || typeof value !== 'object') return null;

    const mode = value.mode === 'percent' ? 'percent' : 'value';
    const amount = roundMoney(value.amount);
    if (!Number.isFinite(amount) || amount <= 0) return null;

    return { mode, amount };
}

function parseAdjustmentSignature(value) {
    let parsed = value;

    if (typeof value === 'string') {
        try {
            parsed = JSON.parse(value);
        } catch {
            parsed = {};
        }
    }

    if (!parsed || typeof parsed !== 'object') parsed = {};

    return {
        surcharge: normalizeAdjustmentEntry(parsed.surcharge),
        discount: normalizeAdjustmentEntry(parsed.discount),
        multiplier: parsed.multiplier && typeof parsed.multiplier === 'object' ? parsed.multiplier : null,
    };
}

function computeAdjustmentMetricsFromSignature(adjustedUnitPrice, signature) {
    const normalizedSignature = parseAdjustmentSignature(signature);
    const currentPrice = roundMoney(adjustedUnitPrice);
    const surcharge = normalizedSignature.surcharge;
    const discount = normalizedSignature.discount;

    const surchargePercent = surcharge?.mode === 'percent' ? Number(surcharge.amount) / 100 : 0;
    const discountPercent = discount?.mode === 'percent' ? Number(discount.amount) / 100 : 0;
    const surchargeValue = surcharge?.mode === 'value' ? Number(surcharge.amount) : 0;
    const discountValue = discount?.mode === 'value' ? Number(discount.amount) : 0;

    const coefficient = 1 + surchargePercent - discountPercent;
    const constant = surchargeValue - discountValue;

    let baseUnitPrice = currentPrice;
    if (Math.abs(coefficient) > 0.000001) {
        baseUnitPrice = roundMoney((currentPrice - constant) / coefficient);
    }
    if (!Number.isFinite(baseUnitPrice) || baseUnitPrice < 0) {
        baseUnitPrice = roundMoney(Math.max(0, currentPrice - surchargeValue + discountValue));
    }

    const surchargeUnit = surcharge
        ? roundMoney(surcharge.mode === 'percent' ? (baseUnitPrice * surcharge.amount) / 100 : surcharge.amount)
        : 0;
    const discountUnit = discount
        ? roundMoney(discount.mode === 'percent' ? (baseUnitPrice * discount.amount) / 100 : discount.amount)
        : 0;

    return {
        baseUnitPrice,
        surchargeUnit,
        discountUnit,
        signature: normalizedSignature,
    };
}

function stringifyAdjustmentSignature(value) {
    const signature = parseAdjustmentSignature(value);

    return JSON.stringify({
        surcharge: signature.surcharge,
        discount: signature.discount,
        multiplier: signature.multiplier,
    });
}

function getScopedProducts() {
    return products.value.filter(
        (item) => category.value === 'todos' || String(item.category_id) === String(category.value),
    );
}

function getProductNameToken(item) {
    return normalizeSearchToken(item?.nome);
}

function getProductIdentifiers(item) {
    const identifiers = productIdentifierFields
        .map((field) => normalizeSearchToken(item?.[field]))
        .filter(Boolean);

    return [...new Set(identifiers)];
}

function productMatchesSearch(item, rawTerm) {
    const normalizedTerm = normalizeSearchToken(rawTerm);
    if (!normalizedTerm) return false;

    if (getProductNameToken(item).includes(normalizedTerm)) return true;
    return getProductIdentifiers(item).some((identifier) => identifier.includes(normalizedTerm));
}

function findProductByExactIdentifier(rawTerm, scopedProducts = getScopedProducts()) {
    const normalizedTerm = normalizeSearchToken(rawTerm);
    if (!normalizedTerm) return null;

    return (
        scopedProducts.find((item) =>
            getProductIdentifiers(item).some((identifier) => identifier === normalizedTerm),
        ) || null
    );
}

function findProductByExactName(rawTerm, scopedProducts = getScopedProducts()) {
    const normalizedTerm = normalizeSearchToken(rawTerm);
    if (!normalizedTerm) return null;

    return scopedProducts.find((item) => getProductNameToken(item) === normalizedTerm) || null;
}

const overlaysOpen = computed(
    () =>
        shortcutsOpen.value ||
        totemCartDialogOpen.value ||
        cancelUnlockModal.open ||
        cancelDialogOpen.value ||
        adjustmentModal.open ||
        finalizeModalOpen.value ||
        restaurantComandaModalOpen.value ||
        productConsultModalOpen.value ||
        settingsUnlockModal.open,
);

function resolvePosRootElement() {
    const root = posShellRef.value?.$el ?? posShellRef.value;
    return root instanceof HTMLElement ? root : null;
}

function resolveActiveModalElement() {
    const panels = Array.from(document.querySelectorAll('.ui-modal-backdrop .ui-modal-panel')).filter(
        (element) => element instanceof HTMLElement && isNavigableElementVisible(element),
    );

    const activePanel = panels.at(-1);
    return activePanel instanceof HTMLElement ? activePanel : null;
}

function resolveNavigationRootElement() {
    return resolveActiveModalElement() || resolvePosRootElement();
}

function getNavigationRegion(element) {
    if (!(element instanceof HTMLElement)) return '';

    return element.closest('[data-nav-region]')?.getAttribute('data-nav-region') || '';
}

function isEditableElement(target) {
    if (!(target instanceof HTMLElement)) return false;
    if (target.isContentEditable) return true;

    const field = target.closest('input, textarea, select');
    if (!(field instanceof HTMLElement)) return false;
    if (field.tagName.toLowerCase() === 'input') return !field.readOnly;

    return true;
}

function isNavigableElementVisible(element) {
    if (!element.isConnected) return false;
    if (element.hasAttribute('disabled') || element.getAttribute('aria-disabled') === 'true') return false;

    const style = window.getComputedStyle(element);
    if (style.display === 'none' || style.visibility === 'hidden') return false;

    return element.getClientRects().length > 0;
}

function getNavigableElements() {
    const root = resolveNavigationRootElement();
    if (!root) return [];

    return Array.from(root.querySelectorAll(keyboardNavigableSelector)).filter(
        (element) => element instanceof HTMLElement && isNavigableElementVisible(element),
    );
}

function sortElementsByPosition(elements) {
    return [...elements].sort((left, right) => {
        const leftRect = left.getBoundingClientRect();
        const rightRect = right.getBoundingClientRect();
        const verticalDelta = leftRect.top - rightRect.top;

        if (Math.abs(verticalDelta) > 8) return verticalDelta;
        return leftRect.left - rightRect.left;
    });
}

function isAltGraphPressed(event) {
    return Boolean(event.getModifierState?.('AltGraph')) || (event.ctrlKey && event.altKey && !event.metaKey);
}

function getNavigationDirection(event) {
    const key = event.key.toLowerCase();
    if (key in directionalKeyMap) {
        if (event.altKey || event.ctrlKey || event.metaKey) return null;
        return directionalKeyMap[key];
    }

    if (!isAltGraphPressed(event)) return null;
    return altGraphDirectionalKeyMap[key] ?? altGraphDirectionalCodeMap[event.code] ?? null;
}

function getElementCenter(element) {
    const rect = element.getBoundingClientRect();
    return {
        x: rect.left + rect.width / 2,
        y: rect.top + rect.height / 2,
    };
}

function collectDirectionalCandidates(currentElement, elements, direction) {
    const currentCenter = getElementCenter(currentElement);
    const candidates = [];

    for (const candidate of elements) {
        if (candidate === currentElement) continue;
        const candidateCenter = getElementCenter(candidate);
        const deltaX = candidateCenter.x - currentCenter.x;
        const deltaY = candidateCenter.y - currentCenter.y;

        let primaryDistance = 0;
        let secondaryDistance = 0;

        if (direction === 'left') {
            if (deltaX >= -2) continue;
            primaryDistance = Math.abs(deltaX);
            secondaryDistance = Math.abs(deltaY);
        } else if (direction === 'right') {
            if (deltaX <= 2) continue;
            primaryDistance = Math.abs(deltaX);
            secondaryDistance = Math.abs(deltaY);
        } else if (direction === 'up') {
            if (deltaY >= -2) continue;
            primaryDistance = Math.abs(deltaY);
            secondaryDistance = Math.abs(deltaX);
        } else {
            if (deltaY <= 2) continue;
            primaryDistance = Math.abs(deltaY);
            secondaryDistance = Math.abs(deltaX);
        }

        candidates.push({
            candidate,
            primaryDistance,
            secondaryDistance,
        });
    }

    return candidates;
}

function pickBestDirectionalCandidate(candidates) {
    let bestCandidate = null;
    let bestScore = Number.POSITIVE_INFINITY;

    for (const item of candidates) {
        const score = item.primaryDistance * 1000 + item.secondaryDistance;
        if (score < bestScore) {
            bestScore = score;
            bestCandidate = item.candidate;
        }
    }

    return bestCandidate;
}

function getFallbackRegionForDirection(currentRegion, direction) {
    if (currentRegion === 'sidebar' && direction === 'right') return 'catalog';
    if (currentRegion === 'catalog' && direction === 'left') return 'sidebar';
    if (currentRegion === 'catalog' && direction === 'right') return 'cart';
    if (currentRegion === 'cart' && direction === 'left') return 'catalog';
    return '';
}

function pickDirectionalCandidate(currentElement, elements, direction) {
    const directionalCandidates = collectDirectionalCandidates(currentElement, elements, direction);
    if (!directionalCandidates.length) return null;

    const currentRegion = getNavigationRegion(currentElement);
    if (!currentRegion) return pickBestDirectionalCandidate(directionalCandidates);

    const sameRegionCandidates = directionalCandidates.filter(
        (item) => getNavigationRegion(item.candidate) === currentRegion,
    );

    if (sameRegionCandidates.length) {
        return pickBestDirectionalCandidate(sameRegionCandidates);
    }

    const fallbackRegion = getFallbackRegionForDirection(currentRegion, direction);
    if (!fallbackRegion) return null;

    const fallbackCandidates = directionalCandidates.filter(
        (item) => getNavigationRegion(item.candidate) === fallbackRegion,
    );
    if (!fallbackCandidates.length) return null;

    return pickBestDirectionalCandidate(fallbackCandidates);
}

function getCurrentFocusableElement() {
    return document.activeElement instanceof HTMLElement ? document.activeElement : null;
}

function restoreFocusToElement(targetElement) {
    if (!(targetElement instanceof HTMLElement)) return;
    if (!targetElement.isConnected || !isNavigableElementVisible(targetElement)) return;

    targetElement.focus();
}

function focusElementByDirection(direction) {
    const elements = getNavigableElements();
    if (!elements.length) return false;

    const activeElement = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    const currentElement =
        activeElement instanceof HTMLElement
            ? activeElement.matches(keyboardNavigableSelector)
                ? activeElement
                : activeElement.closest(keyboardNavigableSelector)
            : null;

    const isCurrentWithinPos = currentElement instanceof HTMLElement && elements.includes(currentElement);

    if (!isCurrentWithinPos) {
        const ordered = sortElementsByPosition(elements);
        const fallback =
            direction === 'left' || direction === 'up' ? ordered[ordered.length - 1] : ordered[0];
        if (!fallback) return false;
        fallback.focus();
        return true;
    }

    const nextElement = pickDirectionalCandidate(currentElement, elements, direction);
    if (!nextElement) return false;

    nextElement.focus();
    nextElement.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    return true;
}

function handleDirectionalShortcuts(event) {
    if (event.repeat) return;

    const direction = getNavigationDirection(event);
    if (!direction) return;
    if (!resolveNavigationRootElement()) return;
    if (!canMoveFromEventTarget(event, direction)) return;

    const moved = focusElementByDirection(direction);
    if (!moved) return;

    event.preventDefault();
}

function canMoveFromEventTarget(event, direction) {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return true;
    if (!isEditableElement(target)) return true;

    if (isAltGraphPressed(event)) return true;

    const field = target.closest('input, textarea, select');
    if (!(field instanceof HTMLElement)) return false;
    if (field.tagName.toLowerCase() === 'select') return false;
    if (field.tagName.toLowerCase() === 'textarea') return false;
    if (!(field instanceof HTMLInputElement)) return false;

    const valueLength = field.value?.length ?? 0;
    const selectionStart = field.selectionStart ?? 0;
    const selectionEnd = field.selectionEnd ?? selectionStart;

    if (selectionStart !== selectionEnd) return false;

    if ((direction === 'left' || direction === 'up') && selectionStart === 0) return true;
    if ((direction === 'right' || direction === 'down') && selectionEnd === valueLength) return true;

    return false;
}

function handleEnterAsClick(event) {
    if (event.repeat) return;
    if (event.key !== 'Enter' && event.code !== 'NumpadEnter') return;
    if (event.altKey || event.ctrlKey || event.metaKey) return;

    const target = event.target;
    if (!(target instanceof HTMLElement)) return;
    if (isEditableElement(target)) return;

    const root = resolveNavigationRootElement();
    if (root && !root.contains(target)) return;

    const clickable = target.closest('button, [role="button"], a[href]');
    if (!(clickable instanceof HTMLElement)) return;
    if (clickable.getAttribute('aria-disabled') === 'true') return;
    if (clickable instanceof HTMLButtonElement && clickable.disabled) return;

    event.preventDefault();
    clickable.click();
}

const searchQuery = computed(() => normalizeSearchToken(search.value));

const filteredProducts = computed(() => {
    const q = searchQuery.value;
    if (!q) {
        return isRestaurantMode.value ? getScopedProducts() : [];
    }

    return getScopedProducts().filter((item) => productMatchesSearch(item, q));
});

const hasSearchTerm = computed(() => Boolean(searchQuery.value));
const shouldShowProductGrid = computed(() =>
    isRestaurantMode.value ? filteredProducts.value.length > 0 : hasSearchTerm.value && filteredProducts.value.length > 0,
);
const catalogEmptyTitle = computed(() => {
    if (isRestaurantMode.value) {
        return hasSearchTerm.value ? 'Nenhum produto encontrado' : 'Nenhum produto disponível';
    }

    return hasSearchTerm.value ? 'Nenhum produto encontrado' : 'Busque para listar produtos';
});
const catalogEmptySubtitle = computed(() => {
    if (isRestaurantMode.value) {
        return hasSearchTerm.value
            ? 'Ajuste o texto da busca para localizar o item.'
            : 'Cadastre produtos e selecione uma categoria para iniciar os lançamentos.';
    }

    return hasSearchTerm.value
        ? 'Ajuste o texto da busca para localizar o item.'
        : 'Digite nome, código ou código de barras no campo superior.';
});
const lastSelectedItem = computed(() => (cart.length ? cart[cart.length - 1] : null));
const lastSelectedItemTotal = computed(() => {
    if (!lastSelectedItem.value) return 0;
    return roundMoney(Number(lastSelectedItem.value.preco_venda || 0) * Number(lastSelectedItem.value.qty || 0));
});

const subtotal = computed(() => cart.reduce((acc, item) => acc + Number(item.preco_venda) * item.qty, 0));
const hasPendingSaleChanges = computed(
    () => Boolean(cart.length || search.value.trim() || category.value !== 'todos'),
);
const cancelDialogRows = computed(() =>
    cart.map((item, index) => {
        const quantity = normalizeQuantity(item.qty || 0);
        const adjustedUnitPrice = roundMoney(item.preco_venda);
        const metrics = computeAdjustmentMetricsFromSignature(adjustedUnitPrice, item.adjustment_signature);
        const subtotalValue = roundMoney(adjustedUnitPrice * quantity);
        const discountTotal = roundMoney(metrics.discountUnit * quantity);
        const surchargeTotal = roundMoney(metrics.surchargeUnit * quantity);

        return {
            sourceIndex: index,
            seq: index + 1,
            productCode: String(item.codigo || ''),
            description: String(item.nome || 'Item sem descrição'),
            quantityLabel: formatDecimal(quantity),
            quantity,
            subtotal: subtotalValue,
            discountTotal,
            surchargeTotal,
            adjustmentNet: roundMoney(surchargeTotal - discountTotal),
            hasAdjustments: discountTotal > 0 || surchargeTotal > 0,
            baseUnitPrice: metrics.baseUnitPrice,
        };
    }),
);
const productConsultSearchQuery = computed(() => normalizeSearchToken(productConsultSearch.value));
const productConsultDepartmentOptions = computed(() => [
    { id: 'todos', nome: 'Todos' },
    ...categories.value.map((item) => ({ id: item.id, nome: item.nome })),
]);
const productConsultCategoryNameById = computed(() => {
    const map = new Map();
    categories.value.forEach((item) => {
        map.set(String(item.id), String(item.nome || '').trim());
    });
    return map;
});
const productConsultResults = computed(() => {
    const scopedByDepartment = products.value.filter(
        (item) =>
            productConsultDepartment.value === 'todos' ||
            String(item.category_id) === String(productConsultDepartment.value),
    );
    const query = productConsultSearchQuery.value;
    const filtered = query ? scopedByDepartment.filter((item) => productMatchesSearch(item, query)) : scopedByDepartment;

    return [...filtered].sort((left, right) => String(left?.nome || '').localeCompare(String(right?.nome || ''), 'pt-BR'));
});

const adjustmentModalTitle = computed(() => {
    if (adjustmentModal.kind === 'surcharge') return 'Acréscimo no Item';
    if (adjustmentModal.kind === 'discount') return 'Desconto no Item';
    return 'Multiplicador de Quantidade';
});

const adjustmentModalDescription = computed(() => {
    if (adjustmentModal.kind === 'surcharge') {
        return 'Configure um acréscimo em valor ou percentual para os próximos itens lançados.';
    }

    if (adjustmentModal.kind === 'discount') {
        return 'Configure um desconto em valor ou percentual para os próximos itens lançados.';
    }

    return 'Configure quantas unidades (ou kg) serão adicionadas por lançamento de item.';
});

const isCurrentModalAdjustmentActive = computed(() => {
    if (adjustmentModal.kind === 'surcharge') return surchargeAdjustment.active;
    if (adjustmentModal.kind === 'discount') return discountAdjustment.active;
    return multiplierAdjustment.active;
});

const discountAdjustmentLabel = computed(() => {
    if (!discountAdjustment.active) return '';

    const amountLabel =
        discountAdjustment.mode === 'percent'
            ? `${formatDecimal(discountAdjustment.amount, 2)}%`
            : formatCurrency(discountAdjustment.amount);

    return `Desconto ${amountLabel}`;
});

const multiplierAdjustmentLabel = computed(() => {
    if (!multiplierAdjustment.active) return '';
    return `Quantidade x ${formatDecimal(multiplierAdjustment.quantity)}`;
});

function focusSearchField() {
    nextTick(() => {
        searchBarRef.value?.focus?.();
    });
}

function quickIncreaseUnits() {
    const currentQuantity = multiplierAdjustment.active ? Number(multiplierAdjustment.quantity || 1) : 1;
    const nextQuantity = normalizeQuantity(currentQuantity + 1);

    multiplierAdjustment.active = true;
    multiplierAdjustment.quantity = nextQuantity;
    showShortcutFeedback(`Unidades por lançamento: ${formatDecimal(nextQuantity)}x.`);
    focusSearchField();
}

function openProductConsultModal() {
    productConsultSearch.value = '';
    productConsultDepartment.value = 'todos';
    productConsultModalOpen.value = true;
    nextTick(() => {
        consultSearchBarRef.value?.focus?.();
    });
}

function closeProductConsultModal() {
    productConsultModalOpen.value = false;
    focusSearchField();
}

function handleCategorySelect(nextCategory) {
    category.value = nextCategory;
}

async function openCancelDialog() {
    if (isOperatorUser.value) {
        openCancelUnlockModal();
        return;
    }

    cancelDialogOpen.value = true;
}

function closeCancelDialog(options = { focusSearch: true }) {
    const { focusSearch = true } = options || {};
    cancelDialogOpen.value = false;

    if (!focusSearch) return;
    focusSearchField();
}

function openCancelUnlockModal() {
    cancelUnlockModal.open = true;
    cancelUnlockModal.mode = 'credentials';
    cancelUnlockModal.adminEmail = '';
    cancelUnlockModal.adminPassword = '';
    cancelUnlockModal.adminPin = '';
    cancelUnlockModal.loading = false;
    cancelUnlockModal.error = '';
}

function focusCancelAdminPinInput() {
    cancelAdminPinInputRef.value?.focus?.();
}

function closeCancelUnlockModal() {
    cancelUnlockModal.open = false;
    cancelUnlockModal.loading = false;
    cancelUnlockModal.error = '';
    cancelUnlockModal.adminEmail = '';
    cancelUnlockModal.adminPassword = '';
    cancelUnlockModal.adminPin = '';
}

function handleCancelAdminPinInput(event) {
    const rawValue = event?.target?.value ?? cancelUnlockModal.adminPin;
    cancelUnlockModal.adminPin = String(rawValue)
        .replace(/\D/g, '')
        .slice(0, 6);
}

async function authorizeCancelAndOpen() {
    if (!canSubmitCancelUnlock.value) {
        cancelUnlockModal.error =
            cancelUnlockModal.mode === 'pin'
                ? 'Informe um PIN válido com 6 dígitos.'
                : 'Informe e-mail e senha do administrador.';
        return;
    }

    cancelUnlockModal.error = '';
    cancelUnlockModal.loading = true;

    try {
        const payload =
            cancelUnlockModal.mode === 'pin'
                ? {
                    admin_pin: cancelUnlockModal.adminPin,
                }
                : {
                    admin_email: cancelUnlockModal.adminEmail.trim(),
                    admin_password: cancelUnlockModal.adminPassword,
                };

        await api.post('/auth/cancel/authorize', payload);

        closeCancelUnlockModal();
        cancelDialogOpen.value = true;
        showShortcutFeedback('Menu de cancelamento liberado pelo administrador.');
    } catch (error) {
        cancelUnlockModal.error =
            error?.response?.data?.message ?? 'Não foi possível validar o acesso do administrador.';
    } finally {
        cancelUnlockModal.loading = false;
    }
}

function openShortcuts() {
    lastFocusedBeforeShortcuts = getCurrentFocusableElement();
    shortcutsOpen.value = true;
}

function closeShortcuts() {
    shortcutsOpen.value = false;
    nextTick(() => {
        restoreFocusToElement(lastFocusedBeforeShortcuts);
        lastFocusedBeforeShortcuts = null;
    });
}

function showShortcutFeedback(message, tone = 'success') {
    toastMessage.value = message;
    toastTone.value = tone;
    toastVisible.value = true;

    if (toastTimeout) clearTimeout(toastTimeout);
    toastTimeout = setTimeout(() => {
        toastVisible.value = false;
    }, 1800);
}

function clearScreen() {
    search.value = '';
    resetProductDraftQuantities();
    category.value = 'todos';
    showShortcutFeedback('Tela limpa com sucesso.');
}

function cancelLastItem() {
    if (!cart.length) {
        showShortcutFeedback('Nenhum item para cancelar.', 'danger');
        return false;
    }

    const lastItem = cart[cart.length - 1];
    const removedQuantity = normalizeQuantity(lastItem?.qty || 0);
    cart.pop();
    if (removedQuantity > 1) {
        showShortcutFeedback(`${lastItem.nome} (${formatDecimal(removedQuantity)}x) removido do carrinho.`);
        return true;
    }

    showShortcutFeedback(`${lastItem.nome} removido do carrinho.`);
    return true;
}

function cancelSale() {
    if (!hasPendingSaleChanges.value) {
        showShortcutFeedback('Nada para limpar no momento.');
        return false;
    }

    cart.splice(0, cart.length);
    search.value = '';
    resetProductDraftQuantities();
    category.value = 'todos';
    activeSaleCommandContext.value = null;
    saleCustomerLabel.value = 'Cliente balcão';
    showShortcutFeedback('Venda cancelada e tela resetada.');
    return true;
}

function cancelSelectedItemsFromDialog(selectedIndexes = []) {
    const normalizedIndexes = [...new Set(selectedIndexes.map((value) => Number(value)))]
        .filter((value) => Number.isInteger(value) && value >= 0 && value < cart.length)
        .sort((left, right) => right - left);

    if (!normalizedIndexes.length) {
        return cancelLastItem();
    }

    const affectedLabels = [];

    normalizedIndexes.forEach((index) => {
        const item = cart[index];
        if (!item) return;

        const removedQuantity = normalizeQuantity(item?.qty || 0);
        const quantityLabel = removedQuantity > 1 ? `${formatDecimal(removedQuantity)}x ` : '';
        affectedLabels.push(`${quantityLabel}${String(item.nome || 'Item')}`);
        cart.splice(index, 1);
    });

    if (!affectedLabels.length) {
        showShortcutFeedback('Nenhum item elegível para cancelamento.', 'danger');
        return false;
    }

    if (affectedLabels.length === 1) {
        showShortcutFeedback(`${affectedLabels[0]} cancelado do carrinho.`);
        return true;
    }

    showShortcutFeedback(`${affectedLabels.length} item(ns) atualizados no carrinho.`);
    return true;
}

function cancelAdjustmentsFromDialog(selectedIndexes = []) {
    const hadActiveAdjustments = hasActiveAdjustments();
    const rowsByIndex = new Map(cancelDialogRows.value.map((row) => [row.sourceIndex, row]));
    const normalizedIndexes = [...new Set(selectedIndexes.map((value) => Number(value)))]
        .filter((value) => Number.isInteger(value) && value >= 0 && value < cart.length)
        .sort((left, right) => right - left);

    let adjustedItems = 0;
    normalizedIndexes.forEach((index) => {
        const row = rowsByIndex.get(index);
        if (!row?.hasAdjustments) return;

        const item = cart[index];
        if (!item) return;

        const signature = parseAdjustmentSignature(item.adjustment_signature);
        signature.surcharge = null;
        signature.discount = null;

        item.preco_venda = roundMoney(row.baseUnitPrice);
        item.adjustment_signature = stringifyAdjustmentSignature(signature);
        adjustedItems += 1;
    });

    if (hadActiveAdjustments) {
        clearAllAdjustments();
    }

    if (!adjustedItems && !hadActiveAdjustments) {
        showShortcutFeedback('Nenhum desconto/acréscimo para cancelar.', 'danger');
        return false;
    }

    if (adjustedItems && hadActiveAdjustments) {
        showShortcutFeedback(`${adjustedItems} item(ns) normalizados e ajustes ativos desativados.`);
        return true;
    }

    if (adjustedItems) {
        showShortcutFeedback(`${adjustedItems} item(ns) com desconto/acréscimo normalizados.`);
        return true;
    }

    showShortcutFeedback('Ajustes de desconto/acréscimo desativados.');
    return true;
}

function handleCancelDialogLastItem(payload = {}) {
    const success = cancelSelectedItemsFromDialog(payload.selectedIndexes || []);
    if (success) {
        closeCancelDialog();
    }
}

function handleCancelDialogSale() {
    const success = cancelSale();
    if (success) {
        closeCancelDialog();
    }
}

function handleCancelDialogAdjustments(payload = {}) {
    const success = cancelAdjustmentsFromDialog(payload.selectedIndexes || []);
    if (success) {
        closeCancelDialog();
    }
}

function finalizeSale() {
    if (!ensureSelfServiceSessionStarted()) return;

    if (!cart.length) {
        showShortcutFeedback('Adicione itens antes de finalizar.', 'danger');
        return;
    }

    totemCartDialogOpen.value = false;
    finalizeModalOpen.value = true;
}

function closeFinalizeModal() {
    finalizeModalOpen.value = false;
    focusSearchField();
}

function getDefaultCommandAdjustmentSignature() {
    return stringifyAdjustmentSignature({
        surcharge: null,
        discount: null,
        multiplier: null,
    });
}

function sanitizeCommandItemForCart(item) {
    const normalized = restaurantCommandCenter.sanitizeCommandItem(item);

    return {
        id: normalized.id,
        productId: normalized.productId,
        nome: normalized.nome,
        codigo: normalized.codigo,
        unidade: normalized.unidade,
        preco_venda: roundMoney(Number(normalized.preco_venda || 0)),
        qty: normalizeQuantity(normalized.qty || 1),
        observation: normalized.observation,
        sellerName: normalized.sellerName,
        history: normalized.history,
        adjustment_signature: getDefaultCommandAdjustmentSignature(),
    };
}

async function openRestaurantComandaModal() {
    if (!isRestaurantMode.value) return;

    await restaurantCommandCenter.ensureData();
    if (restaurantCommandState.error) {
        showShortcutFeedback(restaurantCommandState.error, 'danger');
    }
    restaurantCommandCenter.clearError();

    const hasClosedTables = closedRestaurantTables.value.length > 0;
    restaurantCommandCenter.setActiveTab(hasClosedTables ? 'closed' : 'opened');
    restaurantComandaModalOpen.value = true;
}

function closeRestaurantComandaModal() {
    restaurantComandaModalOpen.value = false;
    restaurantCommandCenter.clearError();
}

async function reintegrateRestaurantComandas() {
    const apiMessage = await restaurantCommandCenter.reintegrate();
    const feedback = apiMessage || 'Comandas integradas novamente.';
    showShortcutFeedback(feedback);
}

function handleCommandCenterSelectTable(tableId) {
    restaurantCommandCenter.selectTable(tableId);
}

function handleCommandCenterSelectCommand(commandId) {
    restaurantCommandCenter.selectCommand(commandId);
}

function importSelectedRestaurantCommandToCart() {
    if (restaurantCommandState.loading) return;

    const selected = restaurantCommandCenter.resolveImportSelection();
    if (!selected) {
        restaurantCommandCenter.setError('Selecione uma mesa e uma ficha para lançar no PDV.');
        return;
    }

    if (cart.length > 0) {
        const shouldReplace = window.confirm(
            'Já existem itens no carrinho. Deseja substituir pelos itens da ficha selecionada?',
        );
        if (!shouldReplace) return;
    }

    const normalizedItems = selected.items
        .map((item) => sanitizeCommandItemForCart(item))
        .filter((item) => Number(item.qty) > 0);

    if (!selected.command?.pendingFiscal) {
        restaurantCommandCenter.setError('A ficha selecionada já foi fiscalizada e não pode ser importada novamente.');
        return;
    }

    if (!normalizedItems.length) {
        restaurantCommandCenter.setError('A ficha selecionada não possui itens para faturamento.');
        return;
    }

    cart.splice(0, cart.length, ...normalizedItems);
    search.value = '';
    category.value = 'todos';
    resetProductDraftQuantities();
    clearAllAdjustments();
    restaurantCommandCenter.clearError();

    const customerLabel = String(selected.table.customerName || `Mesa ${selected.table.code || '--'}`);
    saleCustomerLabel.value = customerLabel.length > 20 ? `${customerLabel.slice(0, 20)}...` : customerLabel;
    activeSaleCommandContext.value = {
        tableId: String(selected.table.id || ''),
        tableCode: String(selected.table.code || '--'),
        commandId: String(selected.command.id || ''),
        commandCode: String(selected.command.code || '--'),
        waiterName: String(selected.command.waiterName || selected.table.waiterName || 'Equipe'),
    };

    restaurantCommandCenter.markCommandAsIntegrated(selected);
    closeRestaurantComandaModal();
    showShortcutFeedback(`Mesa ${selected.table.code} / ficha ${selected.command.code} carregada no PDV.`);
    focusSearchField();
}

function handleCommandCenterEdit(payload = {}) {
    const commandCode = payload?.command?.code || selectedRestaurantCommand.value?.code || '--';
    showShortcutFeedback(`Edição da ficha ${commandCode} preparada para a próxima etapa.`);
}

async function handleCommandCenterConference(payload = {}) {
    const tableId = String(payload?.table?.id || selectedRestaurantTable.value?.id || '');
    const commandId = String(payload?.command?.id || selectedRestaurantCommand.value?.id || '');
    const commandCode = payload?.command?.code || selectedRestaurantCommand.value?.code || '--';

    try {
        const response = await restaurantCommandCenter.registerConference({
            table_id: tableId,
            command_id: commandId,
        });
        showShortcutFeedback(String(response?.message || `Conferência operacional da ficha ${commandCode} acionada.`));
    } catch (error) {
        showShortcutFeedback(error?.message || `Não foi possível registrar conferência da ficha ${commandCode}.`, 'danger');
    }
}

async function handleCommandCenterPrintAction(payload = {}) {
    const actionLabels = {
        conference: 'Conferência da mesa',
        non_fiscal_receipt: 'Cupom não fiscal',
        kitchen_order: 'Pedido da cozinha',
        bar_order: 'Pedido do bar',
    };

    const actionLabel = actionLabels[payload?.action] || 'Impressão operacional';

    try {
        const response = await restaurantCommandCenter.registerPrintAction({
            action: String(payload?.action || ''),
            table_id: String(payload?.table?.id || selectedRestaurantTable.value?.id || ''),
            command_id: String(payload?.command?.id || selectedRestaurantCommand.value?.id || ''),
        });
        showShortcutFeedback(String(response?.message || `${actionLabel} enviado para impressão.`));
    } catch (error) {
        showShortcutFeedback(error?.message || `Não foi possível registrar ${actionLabel}.`, 'danger');
    }
}

function handleCommandCenterOpenTransfer() {
    restaurantCommandCenter.openTransferActionSheet();
}

function handleCommandCenterCloseTransfer() {
    restaurantCommandCenter.closeTransferActionSheet();
}

async function handleCommandCenterTransferConfigured(payload = {}) {
    restaurantCommandCenter.closeTransferActionSheet();
    const destinationCode = String(payload?.destinationCode || '--');

    try {
        const response = await restaurantCommandCenter.registerTransfer({
            origin_table_id: String(payload?.originTableId || ''),
            origin_command_id: String(payload?.originCommandId || ''),
            destination_type: String(payload?.destinationType || 'command'),
            destination_code: destinationCode,
            transfer_mode: String(payload?.transferMode || 'partial'),
            quantity: Number(payload?.quantity || 0) || null,
            reason: String(payload?.reason || ''),
        });
        showShortcutFeedback(String(response?.message || `Estrutura de transferência preparada para o destino ${destinationCode}.`));
    } catch (error) {
        showShortcutFeedback(error?.message || `Não foi possível registrar transferência para ${destinationCode}.`, 'danger');
    }
}

function handleCommandCenterOpenMerge() {
    restaurantCommandCenter.openMergeDialog();
}

function handleCommandCenterCloseMerge() {
    restaurantCommandCenter.closeMergeDialog();
}

async function handleCommandCenterMergeConfigured(payload = {}) {
    restaurantCommandCenter.closeMergeDialog();
    const sourceCode = String(payload?.sourceCommandId || '--');
    const destinationCode = String(payload?.destinationCommandId || '--');

    try {
        const response = await restaurantCommandCenter.registerMerge({
            source_command_id: sourceCode,
            destination_command_id: destinationCode,
            keep_original_open_date: Boolean(payload?.keepOriginalOpenDate),
        });
        showShortcutFeedback(String(response?.message || `Estrutura de junção preparada (${sourceCode} → ${destinationCode}).`));
    } catch (error) {
        showShortcutFeedback(error?.message || `Não foi possível registrar junção ${sourceCode} → ${destinationCode}.`, 'danger');
    }
}

function handleBudgetShortcut() {
    if (isSelfServiceMode.value) {
        runPlannedShortcut('Fechamento da ficha');
        return;
    }

    if (isRestaurantMode.value) {
        openRestaurantComandaModal();
        return;
    }

    runPlannedShortcut('Orçamento');
}

function runPlannedShortcut(featureLabel) {
    showShortcutFeedback(`${featureLabel} em desenvolvimento.`);
}

function openAdjustmentModal(kind) {
    lastFocusedBeforeAdjustment = getCurrentFocusableElement();
    adjustmentModal.kind = kind;

    if (kind === 'surcharge') {
        adjustmentForm.mode = surchargeAdjustment.mode;
        adjustmentForm.amount = surchargeAdjustment.active ? String(surchargeAdjustment.amount) : '';
    } else if (kind === 'discount') {
        adjustmentForm.mode = discountAdjustment.mode;
        adjustmentForm.amount = discountAdjustment.active ? String(discountAdjustment.amount) : '';
    } else {
        adjustmentForm.quantity = multiplierAdjustment.active ? String(multiplierAdjustment.quantity) : '1';
    }

    adjustmentModal.open = true;
}

function closeAdjustmentModal(options = { focusSearch: false, restoreFocus: true }) {
    const { focusSearch = false, restoreFocus = true } = options || {};
    const targetToRestore = restoreFocus ? lastFocusedBeforeAdjustment : null;
    lastFocusedBeforeAdjustment = null;
    adjustmentModal.open = false;

    nextTick(() => {
        if (focusSearch) {
            focusSearchField();
            return;
        }

        restoreFocusToElement(targetToRestore);
    });
}

function clearAdjustment(kind, options = { withFeedback: true }) {
    if (kind === 'surcharge') {
        surchargeAdjustment.active = false;
        surchargeAdjustment.mode = 'value';
        surchargeAdjustment.amount = 0;
    } else if (kind === 'discount') {
        discountAdjustment.active = false;
        discountAdjustment.mode = 'value';
        discountAdjustment.amount = 0;
    } else {
        multiplierAdjustment.active = false;
        multiplierAdjustment.quantity = 1;
    }

    if (options.withFeedback) {
        showShortcutFeedback('Ajuste desativado.');
    }
}

function hasActiveAdjustments() {
    return surchargeAdjustment.active || discountAdjustment.active || multiplierAdjustment.active;
}

function clearAllAdjustments() {
    clearAdjustment('surcharge', { withFeedback: false });
    clearAdjustment('discount', { withFeedback: false });
    clearAdjustment('multiplier', { withFeedback: false });
}

function activateMultiplierAdjustment(quantity, options = {}) {
    const { clearSearch = false } = options;
    const normalizedQuantity = normalizeQuantity(quantity);
    if (!Number.isFinite(normalizedQuantity) || normalizedQuantity <= 0) {
        showShortcutFeedback('Informe um multiplicador válido.', 'danger');
        return false;
    }

    multiplierAdjustment.active = true;
    multiplierAdjustment.quantity = normalizedQuantity;

    if (clearSearch) {
        search.value = '';
    }

    showShortcutFeedback(`Multiplicador ${formatDecimal(normalizedQuantity)}x ativado.`);
    return true;
}

function applyAdjustmentModal() {
    if (adjustmentModal.kind === 'multiplier') {
        const applied = activateMultiplierAdjustment(adjustmentForm.quantity);
        if (!applied) return;
        closeAdjustmentModal({ focusSearch: true, restoreFocus: false });
        return;
    }

    const amount = roundMoney(adjustmentForm.amount);
    if (!Number.isFinite(amount) || amount <= 0) {
        showShortcutFeedback('Informe um valor de ajuste válido.', 'danger');
        return;
    }

    if (adjustmentModal.kind === 'surcharge') {
        surchargeAdjustment.active = true;
        surchargeAdjustment.mode = adjustmentForm.mode;
        surchargeAdjustment.amount = amount;
        closeAdjustmentModal({ focusSearch: true, restoreFocus: false });
        showShortcutFeedback('Acréscimo configurado.');
        return;
    }

    discountAdjustment.active = true;
    discountAdjustment.mode = adjustmentForm.mode;
    discountAdjustment.amount = amount;
    closeAdjustmentModal({ focusSearch: true, restoreFocus: false });
    showShortcutFeedback('Desconto configurado.');
}

function clearAdjustmentFromModal() {
    clearAdjustment(adjustmentModal.kind, { withFeedback: false });
    closeAdjustmentModal({ focusSearch: true, restoreFocus: false });
    showShortcutFeedback('Ajuste desativado.');
}

function buildAdjustmentSignature() {
    return JSON.stringify({
        surcharge: surchargeAdjustment.active
            ? { mode: surchargeAdjustment.mode, amount: surchargeAdjustment.amount }
            : null,
        discount: discountAdjustment.active
            ? { mode: discountAdjustment.mode, amount: discountAdjustment.amount }
            : null,
        multiplier: multiplierAdjustment.active ? { quantity: multiplierAdjustment.quantity } : null,
    });
}

function applyActiveAdjustments(product, selectedQuantity = 1) {
    const basePrice = Number(product.preco_venda || 0);

    const surchargeValue = surchargeAdjustment.active
        ? surchargeAdjustment.mode === 'percent'
            ? (basePrice * surchargeAdjustment.amount) / 100
            : surchargeAdjustment.amount
        : 0;

    const discountValue = discountAdjustment.active
        ? discountAdjustment.mode === 'percent'
            ? (basePrice * discountAdjustment.amount) / 100
            : discountAdjustment.amount
        : 0;

    const adjustedPrice = roundMoney(Math.max(0, basePrice + surchargeValue - discountValue));
    const baseQuantity = normalizeDraftQuantity(selectedQuantity);
    const addedQuantity = multiplierAdjustment.active
        ? normalizeQuantity(baseQuantity * multiplierAdjustment.quantity)
        : baseQuantity;

    return {
        ...product,
        preco_venda: adjustedPrice,
        qty: normalizeQuantity(addedQuantity),
        adjustment_signature: buildAdjustmentSignature(),
    };
}

function addToCart(product, selectedQuantity = 1) {
    const hadActiveAdjustments = hasActiveAdjustments();
    const configuredProduct = applyActiveAdjustments(product, selectedQuantity);
    const existing = cart.find(
        (item) =>
            item.id === configuredProduct.id &&
            item.adjustment_signature === configuredProduct.adjustment_signature,
    );

    if (existing) {
        existing.qty = normalizeQuantity(existing.qty + configuredProduct.qty);
    } else {
        cart.push(configuredProduct);
    }

    if (hadActiveAdjustments) {
        clearAllAdjustments();
        showShortcutFeedback('Modificadores aplicados e limpos para o próximo item.');
        focusSearchField();
    }
}

function getProductDraftQuantity(productId) {
    return normalizeDraftQuantity(productDraftQuantities[productId]);
}

function setProductDraftQuantity(productId, nextQuantity) {
    productDraftQuantities[productId] = normalizeDraftQuantity(nextQuantity);
}

function increaseProductDraftQuantity(productId) {
    setProductDraftQuantity(productId, getProductDraftQuantity(productId) + 1);
}

function decreaseProductDraftQuantity(productId) {
    setProductDraftQuantity(productId, getProductDraftQuantity(productId) - 1);
}

function resetProductDraftQuantities() {
    Object.keys(productDraftQuantities).forEach((key) => {
        delete productDraftQuantities[key];
    });
}

function addProductFromSearch(product) {
    if (!ensureSelfServiceSessionStarted()) return;

    const selectedQuantity = getProductDraftQuantity(product.id);
    const hadActiveAdjustments = hasActiveAdjustments();

    addToCart(product, selectedQuantity);
    search.value = '';
    resetProductDraftQuantities();
    focusSearchField();

    if (!hadActiveAdjustments) {
        const qtyLabel = selectedQuantity > 1 ? `${selectedQuantity}x ` : '';
        showShortcutFeedback(`${qtyLabel}${product.nome} adicionado ao carrinho.`);
    }
}

function findProductBySearchTerm(rawTerm) {
    const normalizedTerm = normalizeSearchToken(rawTerm);
    if (!normalizedTerm) return null;

    const scopedProducts = getScopedProducts();

    const byExactIdentifier = findProductByExactIdentifier(normalizedTerm, scopedProducts);
    if (byExactIdentifier) return byExactIdentifier;

    const byExactName = findProductByExactName(normalizedTerm, scopedProducts);
    if (byExactName) return byExactName;

    const identifierPrefixMatches = scopedProducts.filter((item) =>
        getProductIdentifiers(item).some((identifier) => identifier.startsWith(normalizedTerm)),
    );
    if (identifierPrefixMatches.length === 1) return identifierPrefixMatches[0];

    const namePrefixMatches = scopedProducts.filter((item) =>
        getProductNameToken(item).startsWith(normalizedTerm),
    );
    if (namePrefixMatches.length === 1) return namePrefixMatches[0];

    const containsMatches = scopedProducts.filter((item) => productMatchesSearch(item, normalizedTerm));
    if (containsMatches.length === 1) return containsMatches[0];

    return null;
}

function confirmSearchProduct() {
    if (!ensureSelfServiceSessionStarted()) return;

    const typedTerm = search.value.trim();
    if (!typedTerm) {
        showShortcutFeedback('Digite um produto ou código para confirmar.', 'danger');
        focusSearchField();
        return;
    }

    const inlineMultiplierQuantity = parseInlineMultiplierToken(typedTerm);
    if (inlineMultiplierQuantity) {
        activateMultiplierAdjustment(inlineMultiplierQuantity, { clearSearch: true });
        focusSearchField();
        return;
    }

    const productToAdd = findProductBySearchTerm(typedTerm);
    if (!productToAdd) {
        if (filteredProducts.value.length > 1) {
            showShortcutFeedback('Mais de um item encontrado. Digite SKU/código de barras ou o nome completo.', 'danger');
            return;
        }

        showShortcutFeedback('Produto não encontrado para esse termo.', 'danger');
        return;
    }

    addProductFromSearch(productToAdd);
}

watch(searchQuery, (normalizedTerm) => {
    if (!normalizedTerm) return;

    const exactIdentifierProduct = findProductByExactIdentifier(normalizedTerm);
    if (exactIdentifierProduct) {
        const canonicalName = String(exactIdentifierProduct.nome || '').trim();
        if (canonicalName && normalizeSearchToken(canonicalName) !== normalizedTerm) {
            search.value = canonicalName;
            return;
        }
    }

    const exactNameProduct = findProductByExactName(normalizedTerm);
    if (!exactNameProduct) return;

    const canonicalName = String(exactNameProduct.nome || '').trim();
    if (!canonicalName) return;
    if (search.value.trim() === canonicalName) return;

    search.value = canonicalName;
});

function resetSettingsUnlockModal() {
    settingsUnlockModal.mode = 'credentials';
    settingsUnlockModal.adminEmail = '';
    settingsUnlockModal.adminPassword = '';
    settingsUnlockModal.adminPin = '';
    settingsUnlockModal.loading = false;
    settingsUnlockModal.error = '';
}

function closeSettingsUnlockModal() {
    settingsUnlockModal.open = false;
    settingsUnlockModal.loading = false;
    settingsUnlockModal.error = '';
    settingsUnlockModal.adminPassword = '';
    settingsUnlockModal.adminPin = '';

    if (route.query.unlockSettings) {
        const nextQuery = { ...route.query };
        delete nextQuery.unlockSettings;
        router.replace({ path: route.path, query: nextQuery });
    }
}

function openSettingsUnlockModal() {
    resetSettingsUnlockModal();
    settingsUnlockModal.open = true;
}

function focusAdminPinInput() {
    nextTick(() => {
        adminPinInputRef.value?.focus?.();
    });
}

function handleAdminPinInput(event) {
    const rawValue = event?.target?.value ?? settingsUnlockModal.adminPin;
    settingsUnlockModal.adminPin = String(rawValue)
        .replace(/\D/g, '')
        .slice(0, 6);
}

async function authorizeSettingsAndOpen() {
    if (!canSubmitSettingsUnlock.value) {
        settingsUnlockModal.error =
            settingsUnlockModal.mode === 'pin'
                ? 'Informe um PIN válido com 6 dígitos.'
                : 'Informe e-mail e senha do administrador.';
        return;
    }

    settingsUnlockModal.error = '';
    settingsUnlockModal.loading = true;

    try {
        const payload =
            settingsUnlockModal.mode === 'pin'
                ? {
                    admin_pin: settingsUnlockModal.adminPin,
                }
                : {
                    admin_email: settingsUnlockModal.adminEmail.trim(),
                    admin_password: settingsUnlockModal.adminPassword,
                };

        const { data } = await api.post('/auth/settings/authorize', payload);
        setSettingsAccessKey(data.settings_access_key);
        closeSettingsUnlockModal();

        const nextQuery = { ...route.query };
        delete nextQuery.unlockSettings;

        await router.push({
            path: '/configuracoes',
            query: nextQuery,
        });
        showShortcutFeedback('Acesso à retaguarda autorizado pelo administrador.');
    } catch (error) {
        settingsUnlockModal.error =
            error?.response?.data?.message ?? 'Não foi possível validar o acesso de administrador.';
    } finally {
        settingsUnlockModal.loading = false;
    }
}

async function openSettings() {
    if (!isOperatorUser.value) {
        clearSettingsAccessKey();
        await router.push('/configuracoes');
        return;
    }

    openSettingsUnlockModal();
}

watch(
    () => route.query.unlockSettings,
    (needsUnlock) => {
        if (needsUnlock === '1' && isOperatorUser.value) {
            openSettingsUnlockModal();
        }
    },
    { immediate: true },
);

watch(
    () => settingsUnlockModal.mode,
    (mode) => {
        settingsUnlockModal.error = '';

        if (mode === 'pin') {
            settingsUnlockModal.adminEmail = '';
            settingsUnlockModal.adminPassword = '';
            focusAdminPinInput();
            return;
        }

        settingsUnlockModal.adminPin = '';
    },
);

watch(
    () => cancelUnlockModal.mode,
    (mode) => {
        cancelUnlockModal.error = '';

        if (mode === 'pin') {
            cancelUnlockModal.adminEmail = '';
            cancelUnlockModal.adminPassword = '';
            focusCancelAdminPinInput();
            return;
        }

        cancelUnlockModal.adminPin = '';
    },
);

watch(
    () => totemActionGuard.mode,
    (mode) => {
        totemActionGuard.error = '';

        if (mode === 'pin') {
            totemActionGuard.adminEmail = '';
            totemActionGuard.adminPassword = '';
            focusTotemAdminPinInput();
            return;
        }

        totemActionGuard.adminPin = '';
    },
);

watch(
    restaurantCommandCenterContext,
    (contextMode) => {
        restaurantCommandCenter.setContextMode(contextMode);
    },
    { immediate: true },
);

watch(
    isRestaurantMode,
    (enabled) => {
        if (!enabled || isSelfServiceMode.value) return;
        restaurantCommandCenter.ensureData();
    },
);

watch(
    isSelfServiceMode,
    (enabled) => {
        if (enabled) {
            startSelfServiceClock();
            category.value = 'todos';
            nextTick(() => {
                if (selfServiceCustomerName.value) {
                    focusSearchField();
                    return;
                }
                focusSelfServiceNameInput();
            });
            return;
        }

        stopSelfServiceClock();
        totemEdgeTriggerVisible.value = false;
        totemDrawerOpen.value = false;
        totemCartDialogOpen.value = false;
        closeTotemActionGuard();
        if (isRestaurantMode.value) {
            restaurantCommandCenter.ensureData();
        }
    },
    { immediate: true },
);

function logout() {
    if (exitIntegratedPdv()) return;

    clearAuthData();
    router.push('/login');
}

function handleFinalizeCustomerSelected(customer) {
    if (!customer?.nome) {
        saleCustomerLabel.value = 'Cliente balcão';
        return;
    }

    const name = String(customer.nome);
    saleCustomerLabel.value = name.length > 20 ? `${name.slice(0, 20)}...` : name;
}

function handleFinalizeCompleted(result) {
    cart.splice(0, cart.length);
    search.value = '';
    resetProductDraftQuantities();
    category.value = 'todos';
    totemCartDialogOpen.value = false;
    if (isSelfServiceMode.value) {
        resetSelfServiceSession();
        focusSelfServiceNameInput();
    } else {
        saleCustomerLabel.value = 'Cliente balcão';
        activeSaleCommandContext.value = null;
    }
    finalizeModalOpen.value = false;

    if (isRestaurantMode.value) {
        restaurantCommandCenter.resetData(true);
        restaurantCommandCenter.ensureData();
    }

    const emittedNumber = result?.receipt?.number;
    if (emittedNumber) {
        showShortcutFeedback(`NFC-e ${emittedNumber} emitida com sucesso.`);
        focusSearchField();
        return;
    }

    showShortcutFeedback('Venda finalizada com sucesso.');
    focusSearchField();
}

async function loadData() {
    try {
        const [categoriesRes, productsRes] = await Promise.all([
            api.get('/pos/categories'),
            api.get('/pos/products'),
        ]);

        const apiCategories = Array.isArray(categoriesRes.data) ? categoriesRes.data : [];
        const apiProducts = Array.isArray(productsRes.data) ? productsRes.data : [];

        categories.value = apiCategories;
        products.value = apiProducts;
    } catch {
        categories.value = [];
        products.value = [];
    }
}

async function loadReceiptEmitter() {
    try {
        const terminalId = String(getTerminalSession()?.id || '').trim();
        const requestConfig = terminalId
            ? {
                params: {
                    terminal_id: terminalId,
                },
            }
            : undefined;

        const { data } = await api.get('/pos/company-profile', requestConfig);
        assignReceiptEmitter(data);
    } catch {
        assignReceiptEmitter({});
    }
}

const shortcutKeys = new Set([
    'd',
    '+',
    'escape',
    'f1',
    'f2',
    'f3',
    'f6',
    'f7',
    'f8',
    'f9',
    'f10',
    'f11',
]);

useKeyboardShortcuts(
    {
        d: () => openAdjustmentModal('discount'),
        '+': () => openAdjustmentModal('surcharge'),
        f1: () => finalizeSale(),
        f2: () => openCancelDialog(),
        f3: () => cancelSale(),
        f6: () => openProductConsultModal(),
        f7: () => runPlannedShortcut('Abrir gaveta'),
        f8: () => runPlannedShortcut('Operações TEF'),
        f9: () => clearScreen(),
        f10: () => runPlannedShortcut('Identificar cliente'),
        f11: () => openShortcuts(),
        escape: () => {
            if (cancelUnlockModal.open) {
                closeCancelUnlockModal();
                return;
            }
            if (cancelDialogOpen.value) {
                closeCancelDialog();
                return;
            }
            if (settingsUnlockModal.open) {
                closeSettingsUnlockModal();
                return;
            }
            if (totemCartDialogOpen.value) {
                closeTotemCartDialog();
                return;
            }
            if (finalizeModalOpen.value) return;
            if (productConsultModalOpen.value) {
                closeProductConsultModal();
                return;
            }
            if (restaurantCommandState.transferActionOpen) {
                handleCommandCenterCloseTransfer();
                return;
            }
            if (restaurantCommandState.mergeDialogOpen) {
                handleCommandCenterCloseMerge();
                return;
            }
            if (restaurantComandaModalOpen.value) {
                closeRestaurantComandaModal();
                return;
            }
            if (adjustmentModal.open) {
                closeAdjustmentModal();
                return;
            }

            if (shortcutsOpen.value) {
                closeShortcuts();
            }
        },
    },
    {
        shouldHandleEvent: (event, key) => {
            if (!shortcutKeys.has(key)) return false;
            if (overlaysOpen.value && key !== 'escape') return false;

            const target = event.target;
            if (!isEditableElement(target)) return true;

            return editableAllowedShortcuts.has(key);
        },
    },
);

function handleAltShortcuts(event) {
    if (!event.altKey || event.ctrlKey || event.metaKey) return;
    if (overlaysOpen.value) return;

    const key = event.key.toLowerCase();

    if (key === 'a') {
        event.preventDefault();
        openShortcuts();
        return;
    }

    if (key === 'd') {
        event.preventDefault();
        openAdjustmentModal('discount');
        return;
    }

    if (key === 'm') {
        event.preventDefault();
        runPlannedShortcut('Mesa/Conta');
        return;
    }

    if (key === 'o') {
        event.preventDefault();
        handleBudgetShortcut();
        return;
    }

    if (key === 'r') {
        event.preventDefault();
        runPlannedShortcut('Recebimento de carnê');
        return;
    }

    if (key === 'v') {
        event.preventDefault();
        runPlannedShortcut('Identificar vendedor');
    }
}

function handleCtrlMultiplyShortcut(event) {
    if ((!event.ctrlKey && !event.metaKey) || event.altKey) return;
    if (overlaysOpen.value) return;

    const isMultiplyKey = event.code === 'NumpadMultiply' || event.key === '*';
    if (!isMultiplyKey) return;

    event.preventDefault();
    openAdjustmentModal('multiplier');
}

onMounted(() => {
    restaurantOperationalMode.value = normalizeRestaurantOperationMode(getTerminalSession()?.restaurantMode);
    updateSelfServiceViewportMode();
    loadData();
    loadReceiptEmitter();
    focusSearchField();
    window.addEventListener('keydown', handleAltShortcuts);
    window.addEventListener('keydown', handleCtrlMultiplyShortcut);
    window.addEventListener('keydown', handleDirectionalShortcuts);
    window.addEventListener('keydown', handleEnterAsClick);
    window.addEventListener('focus', loadReceiptEmitter);
    window.addEventListener('resize', updateSelfServiceViewportMode);
    window.addEventListener('mousemove', handleTotemMouseMove);
    window.addEventListener('touchstart', handleTotemTouchStart, { passive: true });
    window.addEventListener('touchmove', handleTotemTouchMove, { passive: true });
    window.addEventListener('touchend', handleTotemTouchEnd, { passive: true });
});

onBeforeUnmount(() => {
    if (toastTimeout) clearTimeout(toastTimeout);
    stopSelfServiceClock();
    window.removeEventListener('keydown', handleAltShortcuts);
    window.removeEventListener('keydown', handleCtrlMultiplyShortcut);
    window.removeEventListener('keydown', handleDirectionalShortcuts);
    window.removeEventListener('keydown', handleEnterAsClick);
    window.removeEventListener('focus', loadReceiptEmitter);
    window.removeEventListener('resize', updateSelfServiceViewportMode);
    window.removeEventListener('mousemove', handleTotemMouseMove);
    window.removeEventListener('touchstart', handleTotemTouchStart);
    window.removeEventListener('touchmove', handleTotemTouchMove);
    window.removeEventListener('touchend', handleTotemTouchEnd);
});
</script>

<template>
    <PosShell ref="posShellRef" :class="posShellModeClass">
        <template v-if="!isSelfServiceMode" #sidebar>
            <PosCategoryRail
                :show-settings="!isOperatorUser"
                :budget-label="budgetShortcutLabel"
                :enable-cancel-ticker="isRestaurantMode && !isSelfServiceMode"
                :logout-label="exitLabel"
                @cancel-item="openCancelDialog"
                @identify-customer="runPlannedShortcut('Identificar cliente')"
                @open-budget="handleBudgetShortcut"
                @identify-seller="runPlannedShortcut('Identificar vendedor')"
                @open-menu="openShortcuts"
                @open-shortcuts="openShortcuts"
                @open-settings="openSettings"
                @logout="logout"
            />
        </template>

        <section class="pos-canvas" :class="`is-${posLayoutMode}`" data-nav-region="catalog">
            <PosHeader>
                <template v-if="isSelfServiceMode">
                    <div class="self-service-header">
                        <div class="self-service-header-main">
                            <h1 class="self-service-title">{{ posCanvasTitle }}</h1>
                            <p class="self-service-subtitle">Monte seu pedido e finalize no seu ritmo. Perfil: {{ selfServiceViewportLabel }}</p>
                        </div>
                        <div v-if="isTotemSessionStarted" class="self-service-meta">
                            <article class="self-service-meta-card">
                                <p class="self-service-meta-label">Cliente</p>
                                <p class="self-service-meta-value">
                                    {{ selfServiceCustomerName || 'Aguardando nome' }}
                                </p>
                            </article>
                            <article class="self-service-meta-card">
                                <p class="self-service-meta-label">Ficha</p>
                                <p class="self-service-meta-value">{{ selfServiceTicketCode || '--' }}</p>
                            </article>
                            <article class="self-service-meta-card">
                                <p class="self-service-meta-label">Início / Hora</p>
                                <p class="self-service-meta-value">
                                    {{ selfServiceStartedAt || '--:--' }} / {{ selfServiceCurrentClock || '--:--' }}
                                </p>
                            </article>
                        </div>
                    </div>

                    <div v-if="isTotemSessionStarted" class="pos-search-toolbar">
                        <PosSearchBar
                            ref="searchBarRef"
                            v-model="search"
                            :placeholder="searchPlaceholder"
                            @confirm="confirmSearchProduct"
                        />
                        <AppButton
                            class="pos-search-confirm-action"
                            @click="confirmSearchProduct"
                        >
                            {{ searchConfirmLabel }}
                        </AppButton>
                    </div>
                </template>
                <template v-else>
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h1 class="text-xl font-black text-main">{{ posCanvasTitle }}</h1>
                        </div>
                        <div class="pos-header-tools">
                            <AppTooltip text="Desconto por item">
                                <button
                                    type="button"
                                    class="pos-header-adjust-icon"
                                    :class="{ 'is-active': discountAdjustment.active, 'is-expanded': discountAdjustment.active }"
                                    @click="openAdjustmentModal('discount')"
                                >
                                    <span class="pos-header-adjust-symbol">
                                        <Percent class="h-4 w-4" aria-hidden="true" />
                                    </span>
                                    <span v-if="discountAdjustmentLabel" class="pos-header-adjust-text">{{ discountAdjustmentLabel }}</span>
                                </button>
                            </AppTooltip>
                            <AppTooltip text="Quantidade por item">
                                <button
                                    type="button"
                                    class="pos-header-adjust-icon"
                                    :class="{ 'is-active': multiplierAdjustment.active, 'is-expanded': multiplierAdjustment.active }"
                                    @click="openAdjustmentModal('multiplier')"
                                >
                                    <span class="pos-header-adjust-symbol">
                                        <Boxes class="h-4 w-4" aria-hidden="true" />
                                    </span>
                                    <span v-if="multiplierAdjustmentLabel" class="pos-header-adjust-text">{{ multiplierAdjustmentLabel }}</span>
                                </button>
                            </AppTooltip>
                            <AppTooltip text="Adicionar unidade na quantidade">
                                <button type="button" class="pos-header-adjust-icon" @click="quickIncreaseUnits">
                                    <span class="pos-header-adjust-symbol">
                                        <CirclePlus class="h-4 w-4" aria-hidden="true" />
                                    </span>
                                </button>
                            </AppTooltip>
                            <AppTooltip text="Consultar produtos">
                                <button type="button" class="pos-header-adjust-icon" @click="openProductConsultModal">
                                    <span class="pos-header-adjust-symbol">
                                        <Search class="h-4 w-4" aria-hidden="true" />
                                    </span>
                                </button>
                            </AppTooltip>
                            <PosCustomerBadge :label="saleCustomerLabel" />
                            <PosNfceStatus />
                            <AppThemeToggle />
                        </div>
                    </div>

                    <div class="pos-search-toolbar">
                        <PosSearchBar
                            ref="searchBarRef"
                            v-model="search"
                            :placeholder="searchPlaceholder"
                            @confirm="confirmSearchProduct"
                        />
                        <AppButton class="pos-search-confirm-action" @click="confirmSearchProduct">
                            {{ searchConfirmLabel }}
                        </AppButton>
                    </div>
                </template>
            </PosHeader>

            <template v-if="isSelfServiceMode">
                <section v-if="!selfServiceCustomerName" class="self-service-welcome">
                    <form class="self-service-welcome-card" @submit.prevent="startSelfServiceSession">
                        <h2 class="self-service-welcome-title">Bem-vindo ao Totem</h2>
                        <p class="self-service-welcome-copy">
                            Informe seu nome para abrir sua ficha e começar seu pedido.
                        </p>
                        <label class="ui-field-wrap">
                            <span class="ui-label">Seu nome</span>
                            <input
                                ref="selfServiceNameInputRef"
                                v-model="selfServiceCustomerInput"
                                class="ui-field"
                                type="text"
                                maxlength="60"
                                placeholder="Ex.: João da Mesa 8"
                                autocomplete="name"
                            >
                        </label>
                        <p class="self-service-welcome-copy is-soft">
                            A ficha será identificada automaticamente com horário e código.
                        </p>
                        <AppButton type="submit">Iniciar atendimento</AppButton>
                    </form>
                </section>

                <div v-else class="self-service-order-layout">
                    <aside class="self-service-category-sidebar">
                        <p class="self-service-category-title">Categorias</p>
                        <button
                            v-for="option in selfServiceCategoryOptions"
                            :key="option.id"
                            type="button"
                            class="self-service-category-btn"
                            :class="{ 'is-active': String(category) === String(option.id) }"
                            @click="handleCategorySelect(option.id)"
                        >
                            {{ option.nome }}
                        </button>
                    </aside>

                    <section class="self-service-products-stage">
                        <PosProductGrid v-if="shouldShowProductGrid" class="pos-product-grid-window">
                            <PosProductCard
                                v-for="product in filteredProducts"
                                :key="product.id"
                                :product="product"
                                :format-currency="formatCurrency"
                                :selected-qty="getProductDraftQuantity(product.id)"
                                @decrease-qty="decreaseProductDraftQuantity(product.id)"
                                @increase-qty="increaseProductDraftQuantity(product.id)"
                                @add="addProductFromSearch(product)"
                            />
                        </PosProductGrid>

                        <div v-else class="p-4">
                            <div class="ui-empty pos-catalog-empty-state">
                                <p class="ui-section-title">{{ catalogEmptyTitle }}</p>
                                <p class="ui-page-subtitle">{{ catalogEmptySubtitle }}</p>
                            </div>
                        </div>
                    </section>

                    <footer class="totem-order-footer">
                        <button type="button" class="totem-order-footer-btn is-cart" @click="openTotemCartDialog">
                            <span class="totem-order-footer-btn-title">Carrinho</span>
                            <small class="totem-order-footer-btn-sub">{{ totemCartItemCountLabel }} · {{ formatCurrency(subtotal) }}</small>
                        </button>
                        <button type="button" class="totem-order-footer-btn is-finalize" @click="finalizeSale">
                            <span class="totem-order-footer-btn-title">Finalizar pedido</span>
                            <small class="totem-order-footer-btn-sub">Ir para pagamento</small>
                        </button>
                    </footer>
                </div>
            </template>
            <div v-else class="pos-catalog-main">
                <PosProductGrid v-if="shouldShowProductGrid" class="pos-product-grid-window">
                    <PosProductCard
                        v-for="product in filteredProducts"
                        :key="product.id"
                        :product="product"
                        :format-currency="formatCurrency"
                        :selected-qty="getProductDraftQuantity(product.id)"
                        @decrease-qty="decreaseProductDraftQuantity(product.id)"
                        @increase-qty="increaseProductDraftQuantity(product.id)"
                        @add="addProductFromSearch(product)"
                    />
                </PosProductGrid>

                <div v-else class="p-4">
                    <div class="ui-empty pos-catalog-empty-state">
                        <p class="ui-section-title">{{ catalogEmptyTitle }}</p>
                        <p class="ui-page-subtitle">{{ catalogEmptySubtitle }}</p>
                    </div>
                </div>

                <section class="p-4 pt-0" :class="{ 'pos-last-item-section--restaurant': isRestaurantMode }">
                    <SelectedItemPreview
                        :item="lastSelectedItem"
                        :item-total="lastSelectedItemTotal"
                        :format-currency="formatCurrency"
                        :format-decimal="formatDecimal"
                    />
                </section>
            </div>
        </section>

        <template v-if="!isSelfServiceMode" #panel>
            <PosCartPanel>
                <div class="min-h-0 flex-1 p-4">
                    <SaleReceiptPreview
                        :items="cart"
                        :format-currency="formatCurrency"
                        :emitter="receiptEmitter"
                        :sale-context="activeSaleCommandContext"
                    />
                </div>

                <div class="p-5 border-t border-[var(--color-border)] space-y-4">
                    <PosPaymentSummary :subtotal="formatCurrency(subtotal)" :sale-context="activeSaleCommandContext" />
                    <PosBottomActions @finalize="finalizeSale" />
                </div>
            </PosCartPanel>
        </template>
    </PosShell>

    <button
        v-if="isTotemSessionStarted && !totemDrawerOpen && totemEdgeTriggerVisible"
        type="button"
        class="totem-edge-trigger"
        title="Abrir menu do totem"
        @click="openTotemDrawer"
    >
        <Menu class="h-4 w-4" aria-hidden="true" />
        <span>Menu</span>
    </button>

    <div
        v-if="isTotemSessionStarted && totemDrawerOpen"
        class="totem-drawer-backdrop"
        @click.self="closeTotemDrawer"
    >
        <aside class="totem-drawer-panel">
            <header class="totem-drawer-header">
                <h3 class="totem-drawer-title">Acesso administrativo</h3>
                <button type="button" class="totem-drawer-close" @click="closeTotemDrawer">
                    <X class="h-4 w-4" aria-hidden="true" />
                </button>
            </header>

            <div class="totem-drawer-actions">
                <button
                    v-for="action in totemMenuActions"
                    :key="action.id"
                    type="button"
                    class="totem-drawer-action-btn"
                    @click="requestTotemAction(action.id)"
                >
                    <span class="totem-drawer-action-label">{{ action.label }}</span>
                    <small v-if="action.key" class="totem-drawer-action-key">{{ action.key }}</small>
                </button>
            </div>
        </aside>
    </div>

    <AppModal
        :open="totemCartDialogOpen"
        title="Carrinho do pedido"
        width-class="max-w-4xl"
        @close="closeTotemCartDialog"
    >
        <div class="totem-cart-dialog">
            <div class="totem-cart-dialog-receipt">
                <SaleReceiptPreview
                    :items="cart"
                    :format-currency="formatCurrency"
                    :emitter="receiptEmitter"
                    :sale-context="activeSaleCommandContext"
                />
            </div>
            <div class="totem-cart-dialog-footer">
                <PosPaymentSummary :subtotal="formatCurrency(subtotal)" />
                <div class="totem-cart-dialog-actions">
                    <AppButton variant="secondary" @click="closeTotemCartDialog">Continuar escolhendo</AppButton>
                    <AppButton @click="finalizeSale">Finalizar pedido</AppButton>
                </div>
            </div>
        </div>
    </AppModal>

    <PosShortcutsDialog :open="shortcutsOpen" @close="closeShortcuts" />
    <AppModal
        :open="totemActionGuard.open"
        title="Autorização para ação do totem"
        width-class="max-w-md"
        @close="closeTotemActionGuard"
    >
        <div class="space-y-4">
            <p class="text-sm text-muted">
                Para executar esta ação, informe credenciais do administrador ou PIN.
            </p>

            <div class="unlock-mode-tabs-wrap">
                <div class="unlock-mode-tabs-grid" role="tablist" aria-label="Modo de autorização para ações do totem">
                    <button
                        type="button"
                        class="unlock-mode-tab-btn"
                        :class="{ 'is-active': totemActionGuard.mode === 'credentials' }"
                        :disabled="totemActionGuard.loading"
                        :aria-selected="totemActionGuard.mode === 'credentials'"
                        @click="totemActionGuard.mode = 'credentials'"
                    >
                        Login + Senha
                    </button>
                    <button
                        type="button"
                        class="unlock-mode-tab-btn"
                        :class="{ 'is-active': totemActionGuard.mode === 'pin' }"
                        :disabled="totemActionGuard.loading"
                        :aria-selected="totemActionGuard.mode === 'pin'"
                        @click="totemActionGuard.mode = 'pin'"
                    >
                        PIN do Admin
                    </button>
                </div>
            </div>

            <div v-if="totemActionGuard.mode === 'credentials'" class="space-y-3">
                <AppInput
                    v-model="totemActionGuard.adminEmail"
                    label="E-mail do administrador"
                    type="email"
                    placeholder="admin@simplespdv.local"
                />
                <AppInput
                    v-model="totemActionGuard.adminPassword"
                    label="Senha do administrador"
                    type="password"
                    placeholder="••••••••"
                />
            </div>

            <div v-else class="space-y-3">
                <div class="settings-pin-field">
                    <label class="ui-label">PIN do administrador</label>
                    <div
                        class="settings-pin-shell"
                        style="--pin-slots: 6"
                        role="button"
                        tabindex="0"
                        @click="focusTotemAdminPinInput"
                        @keydown.enter.prevent="focusTotemAdminPinInput"
                        @keydown.space.prevent="focusTotemAdminPinInput"
                    >
                        <input
                            ref="totemAdminPinInputRef"
                            :value="totemActionGuard.adminPin"
                            type="password"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            maxlength="6"
                            autocomplete="one-time-code"
                            class="settings-pin-native-input"
                            @input="handleTotemAdminPinInput"
                        />
                        <span
                            v-for="slot in 6"
                            :key="`totem-pin-${slot}`"
                            class="settings-pin-dot"
                            :class="{ 'is-filled': slot <= totemAdminPinLength }"
                        />
                    </div>
                    <p class="ui-field-hint">Digite os 6 dígitos do PIN do administrador.</p>
                </div>
            </div>

            <p v-if="totemActionGuard.error" class="text-sm text-danger">{{ totemActionGuard.error }}</p>

            <div class="flex items-center justify-end gap-2">
                <AppButton variant="secondary" :disabled="totemActionGuard.loading" @click="closeTotemActionGuard">
                    Cancelar
                </AppButton>
                <AppButton :loading="totemActionGuard.loading" :disabled="!canSubmitTotemGuard" @click="authorizeTotemAction">
                    Liberar ação
                </AppButton>
            </div>
        </div>
    </AppModal>
    <AppModal
        :open="cancelUnlockModal.open"
        title="Autorização para cancelamento"
        width-class="max-w-md"
        @close="closeCancelUnlockModal"
    >
        <div class="space-y-4">
            <p class="text-sm text-muted">
                Para abrir o menu de cancelamento, informe credenciais do administrador ou o PIN.
            </p>

            <div class="unlock-mode-tabs-wrap">
                <div class="unlock-mode-tabs-grid" role="tablist" aria-label="Modo de autorização para cancelamento">
                    <button
                        type="button"
                        class="unlock-mode-tab-btn"
                        :class="{ 'is-active': cancelUnlockModal.mode === 'credentials' }"
                        :disabled="cancelUnlockModal.loading"
                        :aria-selected="cancelUnlockModal.mode === 'credentials'"
                        @click="cancelUnlockModal.mode = 'credentials'"
                    >
                        Login + Senha
                    </button>
                    <button
                        type="button"
                        class="unlock-mode-tab-btn"
                        :class="{ 'is-active': cancelUnlockModal.mode === 'pin' }"
                        :disabled="cancelUnlockModal.loading"
                        :aria-selected="cancelUnlockModal.mode === 'pin'"
                        @click="cancelUnlockModal.mode = 'pin'"
                    >
                        PIN do Admin
                    </button>
                </div>
            </div>

            <div v-if="cancelUnlockModal.mode === 'credentials'" class="space-y-3">
                <AppInput
                    v-model="cancelUnlockModal.adminEmail"
                    label="E-mail do administrador"
                    type="email"
                    placeholder="admin@simplespdv.local"
                />
                <AppInput
                    v-model="cancelUnlockModal.adminPassword"
                    label="Senha do administrador"
                    type="password"
                    placeholder="••••••••"
                />
            </div>

            <div v-else class="space-y-3">
                <div class="settings-pin-field">
                    <label class="ui-label">PIN do administrador</label>
                    <div
                        class="settings-pin-shell"
                        style="--pin-slots: 6"
                        role="button"
                        tabindex="0"
                        @click="focusCancelAdminPinInput"
                        @keydown.enter.prevent="focusCancelAdminPinInput"
                        @keydown.space.prevent="focusCancelAdminPinInput"
                    >
                        <input
                            ref="cancelAdminPinInputRef"
                            :value="cancelUnlockModal.adminPin"
                            type="password"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            maxlength="6"
                            autocomplete="one-time-code"
                            class="settings-pin-native-input"
                            @input="handleCancelAdminPinInput"
                        />
                        <span
                            v-for="slot in 6"
                            :key="`cancel-pin-${slot}`"
                            class="settings-pin-dot"
                            :class="{ 'is-filled': slot <= cancelAdminPinLength }"
                        />
                    </div>
                    <p class="ui-field-hint">Digite os 6 dígitos do PIN do administrador.</p>
                </div>
            </div>

            <p v-if="cancelUnlockModal.error" class="text-sm text-danger">{{ cancelUnlockModal.error }}</p>

            <div class="flex items-center justify-end gap-2">
                <AppButton variant="secondary" :disabled="cancelUnlockModal.loading" @click="closeCancelUnlockModal">
                    Cancelar
                </AppButton>
                <AppButton :loading="cancelUnlockModal.loading" :disabled="!canSubmitCancelUnlock" @click="authorizeCancelAndOpen">
                    Liberar cancelamento
                </AppButton>
            </div>
        </div>
    </AppModal>
    <PosCancelDialog
        :open="cancelDialogOpen"
        :rows="cancelDialogRows"
        :has-active-adjustments="hasActiveAdjustments()"
        :has-pending-sale-changes="hasPendingSaleChanges"
        :format-currency="formatCurrency"
        @close="closeCancelDialog"
        @confirm-last-item="handleCancelDialogLastItem"
        @confirm-cancel-sale="handleCancelDialogSale"
        @confirm-cancel-adjustments="handleCancelDialogAdjustments"
    />
        <FinalizeSaleModal
            :open="finalizeModalOpen"
            :cart="cart"
            :sale-context="activeSaleCommandContext"
            @close="closeFinalizeModal"
            @completed="handleFinalizeCompleted"
            @customer-selected="handleFinalizeCustomerSelected"
        />
    <CommandCenterModal
        :open="restaurantComandaModalOpen"
        :active-tab="restaurantCommandState.activeTab"
        :summary="restaurantCommandSummary"
        :search-query="restaurantCommandState.searchQuery"
        :only-pending-fiscal="restaurantCommandState.onlyPendingFiscal"
        :show-unit-price="restaurantCommandState.showUnitPrice"
        :context-mode="restaurantCommandState.contextMode"
        :error="restaurantCommandState.error"
        :closed-tables="closedRestaurantTables"
        :opened-tables="openedRestaurantTables"
        :all-tables="allRestaurantTables"
        :selected-table-id="restaurantCommandState.selectedTableId"
        :selected-command-id="restaurantCommandState.selectedCommandId"
        :selected-table="selectedRestaurantTable"
        :selected-command="selectedRestaurantCommand"
        :can-import-to-pdv="canImportSelectedRestaurantCommand"
        :transfer-action-open="restaurantCommandState.transferActionOpen"
        :merge-dialog-open="restaurantCommandState.mergeDialogOpen"
        :loading="restaurantCommandState.loading"
        :format-currency="formatCurrency"
        @close="closeRestaurantComandaModal"
        @reintegrate="reintegrateRestaurantComandas"
        @update:active-tab="restaurantCommandCenter.setActiveTab($event)"
        @update:search-query="restaurantCommandCenter.setSearchQuery($event)"
        @update:only-pending-fiscal="restaurantCommandCenter.setOnlyPendingFiscal($event)"
        @update:show-unit-price="restaurantCommandCenter.setShowUnitPrice($event)"
        @select-table="handleCommandCenterSelectTable"
        @select-command="handleCommandCenterSelectCommand"
        @import-to-pdv="importSelectedRestaurantCommandToCart"
        @edit-command="handleCommandCenterEdit"
        @conference-command="handleCommandCenterConference"
        @print-action="handleCommandCenterPrintAction"
        @open-transfer="handleCommandCenterOpenTransfer"
        @close-transfer="handleCommandCenterCloseTransfer"
        @transfer-configured="handleCommandCenterTransferConfigured"
        @open-merge="handleCommandCenterOpenMerge"
        @close-merge="handleCommandCenterCloseMerge"
        @merge-configured="handleCommandCenterMergeConfigured"
    />
    <AppModal
        :open="productConsultModalOpen"
        title="Consulta de Produtos"
        width-class="max-w-5xl"
        @close="closeProductConsultModal"
    >
        <div class="space-y-4">
            <PosSearchBar
                ref="consultSearchBarRef"
                v-model="productConsultSearch"
                placeholder="Buscar por nome, código ou código de barras"
            />

            <div class="pos-consult-departments">
                <button
                    v-for="department in productConsultDepartmentOptions"
                    :key="department.id"
                    type="button"
                    class="pos-consult-department-btn"
                    :class="{ 'is-active': String(productConsultDepartment) === String(department.id) }"
                    @click="productConsultDepartment = department.id"
                >
                    {{ department.nome }}
                </button>
            </div>

            <div class="flex items-center justify-between gap-2 text-sm text-muted">
                <span>{{ productConsultResults.length }} produto(s) encontrado(s)</span>
                <span v-if="productConsultDepartment !== 'todos'">Departamento filtrado</span>
            </div>

            <div v-if="productConsultResults.length" class="pos-consult-products-grid">
                <article
                    v-for="product in productConsultResults"
                    :key="product.id"
                    class="ui-card pos-consult-product-card"
                >
                    <p class="text-xs text-muted font-mono">{{ product.codigo || '—' }}</p>
                    <p class="mt-2 text-sm font-bold text-main">{{ product.nome }}</p>
                    <p class="mt-1 text-xs text-muted">
                        {{ productConsultCategoryNameById.get(String(product.category_id)) || 'Sem departamento' }}
                    </p>
                    <p class="mt-2 text-base font-black text-[var(--color-primary)]">{{ formatCurrency(product.preco_venda) }}</p>
                    <p class="mt-1 text-xs text-muted">Estoque: {{ product.estoque_atual ?? '—' }}</p>
                </article>
            </div>
            <div v-else class="ui-empty">
                <p class="ui-section-title">Nenhum produto encontrado</p>
                <p class="ui-page-subtitle">Ajuste o departamento ou o texto da pesquisa.</p>
            </div>
        </div>
    </AppModal>
    <AppModal
        :open="adjustmentModal.open"
        :title="adjustmentModalTitle"
        width-class="max-w-md"
        @close="closeAdjustmentModal"
    >
        <div class="space-y-4">
            <p class="text-sm text-muted">{{ adjustmentModalDescription }}</p>

            <div v-if="adjustmentModal.kind === 'multiplier'">
                <AppInput
                    v-model="adjustmentForm.quantity"
                    label="Multiplicador de quantidade"
                    type="number"
                    min="0.001"
                    step="0.001"
                    placeholder="Ex.: 2 ou 0.5"
                    hint="Use 2 para duas unidades, ou 0.5 para meio kg por lançamento."
                />
            </div>

            <div v-else class="grid grid-cols-1 gap-4">
                <AppSelect v-model="adjustmentForm.mode" label="Tipo de ajuste">
                    <option value="value">Valor (R$)</option>
                    <option value="percent">Percentual (%)</option>
                </AppSelect>

                <AppInput
                    v-model="adjustmentForm.amount"
                    :label="adjustmentForm.mode === 'value' ? 'Valor do ajuste' : 'Percentual do ajuste'"
                    type="number"
                    min="0.01"
                    :step="adjustmentForm.mode === 'value' ? '0.01' : '0.1'"
                    :placeholder="adjustmentForm.mode === 'value' ? 'Ex.: 2.50' : 'Ex.: 10'"
                />
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                <AppButton
                    variant="ghost"
                    :disabled="!isCurrentModalAdjustmentActive"
                    @click="clearAdjustmentFromModal"
                >
                    Desativar
                </AppButton>
                <div class="flex items-center gap-2">
                    <AppButton variant="secondary" @click="closeAdjustmentModal">Cancelar</AppButton>
                    <AppButton @click="applyAdjustmentModal">Aplicar</AppButton>
                </div>
            </div>
        </div>
    </AppModal>
    <AppModal
        :open="settingsUnlockModal.open"
        title="Autorização de administrador"
        width-class="max-w-md"
        @close="closeSettingsUnlockModal"
    >
        <div class="space-y-4">
            <p class="text-sm text-muted">
                Para abrir a retaguarda com usuário operador, informe credenciais do administrador ou o PIN.
            </p>

            <div class="unlock-mode-tabs-wrap">
                <div class="unlock-mode-tabs-grid" role="tablist" aria-label="Modo de autorização de administrador">
                    <button
                        type="button"
                        class="unlock-mode-tab-btn"
                        :class="{ 'is-active': settingsUnlockModal.mode === 'credentials' }"
                        :disabled="settingsUnlockModal.loading"
                        :aria-selected="settingsUnlockModal.mode === 'credentials'"
                        @click="settingsUnlockModal.mode = 'credentials'"
                    >
                        Login + Senha
                    </button>
                    <button
                        type="button"
                        class="unlock-mode-tab-btn"
                        :class="{ 'is-active': settingsUnlockModal.mode === 'pin' }"
                        :disabled="settingsUnlockModal.loading"
                        :aria-selected="settingsUnlockModal.mode === 'pin'"
                        @click="settingsUnlockModal.mode = 'pin'"
                    >
                        PIN do Admin
                    </button>
                </div>
            </div>

            <div v-if="settingsUnlockModal.mode === 'credentials'" class="space-y-3">
                <AppInput
                    v-model="settingsUnlockModal.adminEmail"
                    label="E-mail do administrador"
                    type="email"
                    placeholder="admin@simplespdv.local"
                />
                <AppInput
                    v-model="settingsUnlockModal.adminPassword"
                    label="Senha do administrador"
                    type="password"
                    placeholder="••••••••"
                />
            </div>

            <div v-else class="space-y-3">
                <div class="settings-pin-field">
                    <label class="ui-label">PIN do administrador</label>
                    <div
                        class="settings-pin-shell"
                        style="--pin-slots: 6"
                        role="button"
                        tabindex="0"
                        @click="focusAdminPinInput"
                        @keydown.enter.prevent="focusAdminPinInput"
                        @keydown.space.prevent="focusAdminPinInput"
                    >
                        <input
                            ref="adminPinInputRef"
                            :value="settingsUnlockModal.adminPin"
                            type="password"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            maxlength="6"
                            autocomplete="one-time-code"
                            class="settings-pin-native-input"
                            @input="handleAdminPinInput"
                        />
                        <span
                            v-for="slot in 6"
                            :key="slot"
                            class="settings-pin-dot"
                            :class="{ 'is-filled': slot <= adminPinLength }"
                        />
                    </div>
                    <p class="ui-field-hint">Digite os 6 dígitos do PIN do administrador.</p>
                </div>
            </div>

            <p v-if="settingsUnlockModal.error" class="text-sm text-danger">{{ settingsUnlockModal.error }}</p>

            <div class="flex items-center justify-end gap-2">
                <AppButton variant="secondary" :disabled="settingsUnlockModal.loading" @click="closeSettingsUnlockModal">
                    Cancelar
                </AppButton>
                <AppButton :loading="settingsUnlockModal.loading" :disabled="!canSubmitSettingsUnlock" @click="authorizeSettingsAndOpen">
                    Autorizar e abrir
                </AppButton>
            </div>
        </div>
    </AppModal>
    <AppToast :show="toastVisible" :tone="toastTone">{{ toastMessage }}</AppToast>
</template>

<style scoped>
.unlock-mode-tabs-wrap {
    border-radius: var(--radius-md);
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 36%, transparent);
    background: color-mix(in srgb, var(--color-bg-surface) 92%, var(--color-bg-muted));
    padding: 0.35rem;
}

.unlock-mode-tabs-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.4rem;
}

.unlock-mode-tab-btn {
    min-height: 2.55rem;
    border-radius: var(--radius-sm);
    border: 1px solid transparent;
    background: transparent;
    color: var(--color-text-muted);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    font-size: 1rem;
    font-weight: 600;
    transition: all var(--transition-fast);
}

.unlock-mode-tab-btn:hover:not(:disabled) {
    color: var(--color-text);
    background: color-mix(in srgb, var(--color-bg-surface) 88%, transparent);
}

.unlock-mode-tab-btn.is-active {
    color: var(--color-text-inverse);
    background: linear-gradient(135deg, var(--color-primary), var(--color-primary-hover));
    border-color: color-mix(in srgb, var(--color-primary) 70%, transparent);
    box-shadow: 0 0 0 1px color-mix(in srgb, var(--color-primary) 25%, transparent), var(--shadow-xs);
}

.unlock-mode-tab-btn:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

.unlock-mode-tab-btn:focus-visible {
    outline: 0;
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary) 26%, transparent);
}

.settings-pin-field {
    display: grid;
    gap: 0.55rem;
}

.settings-pin-shell {
    position: relative;
    display: grid;
    grid-template-columns: repeat(var(--pin-slots, 4), 1fr);
    gap: 0.65rem;
    padding: 0.7rem;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    background: color-mix(in srgb, var(--color-bg-elevated) 88%, transparent);
    cursor: text;
}

.settings-pin-shell:focus-within {
    border-color: color-mix(in srgb, var(--color-primary) 60%, var(--color-border));
    box-shadow: 0 0 0 2px color-mix(in srgb, var(--color-primary) 22%, transparent);
}

.settings-pin-native-input {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    border: 0;
    outline: 0;
}

.settings-pin-dot {
    width: 1rem;
    height: 1rem;
    border-radius: 9999px;
    border: 2px solid color-mix(in srgb, var(--color-border-strong) 55%, transparent);
    background: transparent;
    justify-self: center;
    align-self: center;
    transition: all var(--transition-fast);
}

.settings-pin-dot.is-filled {
    border-color: color-mix(in srgb, var(--color-primary) 75%, transparent);
    background: var(--color-primary);
}

.self-service-header {
    display: grid;
    gap: 0.85rem;
}

.self-service-header-main {
    display: grid;
    gap: 0.25rem;
}

.self-service-title {
    margin: 0;
    font-size: 1.4rem;
    line-height: 1.1;
    font-weight: 900;
    color: var(--color-text);
}

.self-service-subtitle {
    margin: 0;
    color: var(--color-text-muted);
    font-size: 0.92rem;
}

.self-service-meta {
    display: grid;
    gap: 0.5rem;
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.self-service-meta-card {
    border-radius: var(--radius-sm);
    border: 1px solid color-mix(in srgb, var(--color-primary) 30%, var(--color-border));
    background: color-mix(in srgb, var(--color-primary) 8%, var(--color-bg-surface));
    padding: 0.55rem 0.65rem;
}

.self-service-meta-label {
    margin: 0;
    font-size: 0.7rem;
    line-height: 1.1;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--color-text-muted);
}

.self-service-meta-value {
    margin: 0.3rem 0 0;
    font-size: 0.95rem;
    line-height: 1.2;
    font-weight: 800;
    color: var(--color-text);
}

.self-service-welcome {
    flex: 1 1 auto;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.2rem;
}

.self-service-welcome-card {
    width: min(34rem, 100%);
    display: grid;
    gap: 0.9rem;
    border-radius: var(--radius-lg);
    border: 1px solid color-mix(in srgb, var(--color-primary) 32%, var(--color-border));
    background: linear-gradient(
        145deg,
        color-mix(in srgb, var(--color-bg-surface) 92%, #f59e0b 6%),
        color-mix(in srgb, var(--color-bg-surface) 96%, transparent)
    );
    padding: 1.1rem;
}

.self-service-welcome-title {
    margin: 0;
    font-size: 1.3rem;
    line-height: 1.15;
    font-weight: 900;
    color: var(--color-text);
}

.self-service-welcome-copy {
    margin: 0;
    color: var(--color-text-muted);
    font-size: 0.95rem;
}

.self-service-welcome-copy.is-soft {
    font-size: 0.85rem;
}

.self-service-order-layout {
    flex: 1 1 auto;
    min-height: 0;
    display: grid;
    grid-template-columns: minmax(13.5rem, 16rem) minmax(0, 1fr);
    gap: 0.9rem;
    padding: 0.9rem;
}

.self-service-category-sidebar {
    min-height: 0;
    overflow: auto;
    border-radius: var(--radius-md);
    border: 1px solid var(--color-border);
    background: color-mix(in srgb, var(--color-bg-elevated) 75%, var(--color-bg-surface));
    padding: 0.7rem;
    display: grid;
    align-content: start;
    gap: 0.45rem;
}

.self-service-category-title {
    margin: 0 0 0.25rem;
    font-size: 0.82rem;
    font-weight: 800;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--color-text-muted);
}

.self-service-category-btn {
    border-radius: var(--radius-sm);
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 45%, transparent);
    background: var(--color-bg-surface);
    color: var(--color-text);
    font-size: 1.03rem;
    font-weight: 700;
    text-align: left;
    padding: 0.82rem 0.8rem;
    min-height: 3rem;
    transition: all var(--transition-fast);
}

.self-service-category-btn:hover {
    border-color: color-mix(in srgb, var(--color-primary) 56%, transparent);
    background: color-mix(in srgb, var(--color-primary) 11%, var(--color-bg-surface));
}

.self-service-category-btn.is-active {
    border-color: color-mix(in srgb, var(--color-primary) 64%, transparent);
    background: color-mix(in srgb, var(--color-primary) 16%, var(--color-bg-surface));
    color: var(--color-primary);
}

.self-service-products-stage {
    min-height: 0;
    border-radius: var(--radius-md);
    border: 1px solid var(--color-border);
    background: color-mix(in srgb, var(--color-bg-surface) 92%, var(--color-bg-elevated));
}

:global(.pos-shell.pos-mode-totem) {
    grid-template-columns: minmax(0, 1fr);
    gap: var(--space-3);
}

:global(.pos-shell.pos-mode-totem-prestart) {
    grid-template-columns: minmax(0, 1fr);
}

.totem-order-footer {
    grid-column: 1 / -1;
    position: sticky;
    bottom: 0;
    z-index: 2;
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1.25fr);
    gap: 0.75rem;
    border-radius: var(--radius-md);
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 45%, transparent);
    background: color-mix(in srgb, var(--color-bg-surface) 88%, var(--color-bg-elevated));
    backdrop-filter: blur(5px);
    padding: 0.62rem;
}

.totem-order-footer-btn {
    border-radius: var(--radius-sm);
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 48%, transparent);
    background: color-mix(in srgb, var(--color-bg-elevated) 88%, var(--color-bg-surface));
    color: var(--color-text);
    min-height: 3.2rem;
    padding: 0.58rem 0.75rem;
    text-align: left;
    display: grid;
    align-content: center;
    gap: 0.14rem;
    transition: all var(--transition-fast);
}

.totem-order-footer-btn:hover {
    border-color: color-mix(in srgb, var(--color-primary) 58%, transparent);
    background: color-mix(in srgb, var(--color-primary) 12%, var(--color-bg-surface));
}

.totem-order-footer-btn.is-finalize {
    border-color: color-mix(in srgb, var(--color-primary) 62%, transparent);
    background: linear-gradient(
        140deg,
        color-mix(in srgb, var(--color-primary) 22%, var(--color-bg-surface)),
        color-mix(in srgb, var(--color-primary-hover) 30%, var(--color-bg-surface))
    );
}

.totem-order-footer-btn-title {
    font-size: 1.03rem;
    line-height: 1.2;
    font-weight: 800;
}

.totem-order-footer-btn-sub {
    font-size: 0.8rem;
    line-height: 1.2;
    color: var(--color-text-muted);
}

.totem-cart-dialog {
    display: grid;
    gap: 0.85rem;
}

.totem-cart-dialog-receipt {
    border-radius: var(--radius-md);
    border: 1px solid var(--color-border);
    overflow: hidden;
    max-height: min(58vh, 38rem);
}

.totem-cart-dialog-footer {
    display: grid;
    gap: 0.7rem;
}

.totem-cart-dialog-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.6rem;
    flex-wrap: wrap;
}

.totem-edge-trigger {
    position: fixed;
    left: 0.35rem;
    top: 50%;
    transform: translateY(-50%);
    z-index: calc(var(--z-modal) - 1);
    border-radius: 999px;
    border: 1px solid color-mix(in srgb, var(--color-primary) 55%, transparent);
    background: color-mix(in srgb, var(--color-bg-sidebar) 86%, var(--color-bg-surface));
    color: var(--color-text);
    padding: 0.42rem 0.62rem;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.76rem;
    font-weight: 800;
    box-shadow: var(--shadow-sm);
}

.totem-drawer-backdrop {
    position: fixed;
    inset: 0;
    z-index: calc(var(--z-modal) - 1);
    background: rgb(2 6 23 / 0.38);
    backdrop-filter: blur(1px);
}

.totem-drawer-panel {
    position: absolute;
    left: 0;
    top: 0;
    height: 100%;
    width: min(18.5rem, 84vw);
    border-right: 1px solid var(--color-border);
    background: linear-gradient(
        180deg,
        color-mix(in srgb, var(--color-bg-sidebar) 94%, var(--color-bg-surface)),
        color-mix(in srgb, var(--color-bg-sidebar) 84%, var(--color-bg-elevated))
    );
    color: var(--color-text-sidebar);
    box-shadow: var(--shadow-md);
    padding: 0.85rem;
    display: grid;
    align-content: start;
    gap: 0.7rem;
}

.totem-drawer-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.6rem;
}

.totem-drawer-title {
    margin: 0;
    font-size: 0.94rem;
    line-height: 1.2;
    font-weight: 800;
    color: var(--color-text-sidebar);
}

.totem-drawer-close {
    width: 2rem;
    height: 2rem;
    border-radius: 999px;
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 40%, transparent);
    background: color-mix(in srgb, var(--color-bg-sidebar-item) 80%, transparent);
    color: inherit;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.totem-drawer-actions {
    display: grid;
    gap: 0.45rem;
}

.totem-drawer-action-btn {
    border-radius: var(--radius-sm);
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 42%, transparent);
    background: color-mix(in srgb, var(--color-bg-sidebar-item) 80%, transparent);
    color: inherit;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.55rem;
    padding: 0.58rem 0.65rem;
    font-size: 0.9rem;
    font-weight: 700;
    text-align: left;
    transition: all var(--transition-fast);
}

.totem-drawer-action-btn:hover {
    border-color: color-mix(in srgb, var(--color-primary) 52%, transparent);
    background: color-mix(in srgb, var(--color-primary) 14%, var(--color-bg-sidebar-item));
}

.totem-drawer-action-label {
    line-height: 1.15;
}

.totem-drawer-action-key {
    font-size: 0.7rem;
    line-height: 1;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: color-mix(in srgb, var(--color-text-sidebar) 72%, transparent);
}

:global(.pos-shell.pos-mode-totem .pos-product-grid) {
    grid-template-columns: repeat(auto-fill, minmax(12rem, 1fr));
    gap: 0.7rem;
}

:global(.pos-shell.pos-mode-totem-web .self-service-order-layout) {
    grid-template-columns: minmax(14rem, 17rem) minmax(0, 1fr);
}

:global(.pos-shell.pos-mode-totem-tablet-horizontal .self-service-order-layout) {
    grid-template-columns: minmax(12.5rem, 15rem) minmax(0, 1fr);
}

:global(.pos-shell.pos-mode-totem-vertical .self-service-meta) {
    grid-template-columns: 1fr;
}

:global(.pos-shell.pos-mode-totem-vertical .self-service-order-layout) {
    grid-template-columns: 1fr;
}

:global(.pos-shell.pos-mode-totem-vertical .self-service-category-sidebar) {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    align-content: start;
}

:global(.pos-shell.pos-mode-totem-vertical .self-service-category-title) {
    grid-column: 1 / -1;
}

:global(.pos-shell.pos-mode-totem-vertical .totem-order-footer) {
    grid-template-columns: 1fr;
}

@media (max-width: 960px) {
    :global(.pos-shell.pos-mode-totem) {
        grid-template-columns: 1fr;
    }

    :global(.pos-shell.pos-mode-totem .pos-panel) {
        grid-column: 1 / -1;
        min-height: 20rem;
    }

    .self-service-meta {
        grid-template-columns: 1fr;
    }

    .self-service-order-layout {
        grid-template-columns: 1fr;
    }

    .totem-order-footer {
        grid-template-columns: 1fr;
    }
}
</style>
