import { computed, onMounted, reactive, ref, watch } from 'vue';
import api from '../lib/api';
import {
    createRestaurantFicha,
    fetchRestaurantFichaConference,
    fetchRestaurantFichaSummary,
    fetchRestaurantOrderingContext,
    requestRestaurantFichaClose,
    saveRestaurantFichaObservation,
    submitRestaurantFichaOrder,
} from '../lib/restaurantOperations';

function normalizeToken(value) {
    return String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim()
        .toLowerCase();
}

function roundMoney(value) {
    return Math.round((Number(value) || 0) * 100) / 100;
}

function mapProduct(product) {
    return {
        id: String(product?.id || ''),
        categoryId: String(product?.category_id || ''),
        classificationId: String(product?.classificacao_mercadologica_id || ''),
        nome: String(product?.nome || 'Item sem nome'),
        descricao: String(product?.observacoes || ''),
        preco: Number(product?.preco_venda || 0),
        imagemUrl: String(product?.imagem_url || ''),
        estoque: Number(product?.estoque_atual || 0),
        classificationObservationParameters: Array.isArray(product?.classification_observation_parameters)
            ? product.classification_observation_parameters
            : [],
    };
}

const observationFieldNameTemplates = [
    { id: 'personalizado', label: 'Personalizado' },
    { id: 'observacao_padrao', label: 'Observação padrão' },
    { id: 'sem_item', label: 'Sem item' },
    { id: 'com_item', label: 'Com item' },
    { id: 'intensidade', label: 'Intensidade' },
    { id: 'temperatura', label: 'Temperatura' },
];

const BLOCKED_ORDER_STATUSES = new Set(['aguardando_pagamento', 'paga', 'cancelada', 'closed']);

function getObservationFieldLabel(field) {
    const templateId = String(field?.nome_template || 'personalizado');
    if (templateId === 'personalizado') {
        const custom = String(field?.nome_personalizado || '').trim();
        return custom || 'Campo adicional';
    }
    return observationFieldNameTemplates.find((option) => option.id === templateId)?.label || 'Campo adicional';
}

function normalizeObservationFieldType(value) {
    const type = String(value || 'texto_curto').trim();
    const allowed = ['texto_curto', 'texto_longo', 'numero_inteiro', 'numero_decimal', 'data', 'sim_nao', 'checkbox_texto'];
    return allowed.includes(type) ? type : 'texto_curto';
}

function normalizeClassificationObservationField(field, index = 0) {
    const type = normalizeObservationFieldType(field?.tipo_campo);
    return {
        id: String(field?.id || `classification-obs-${index}-${Date.now()}`),
        nome_template: String(field?.nome_template || 'personalizado'),
        nome_personalizado: String(field?.nome_personalizado || ''),
        tipo_campo: type,
        texto_checkbox: String(field?.texto_checkbox || ''),
        obrigatorio: Boolean(field?.obrigatorio),
        ordem: Number.isFinite(Number(field?.ordem)) ? Number(field.ordem) : index,
    };
}

function normalizeClassificationObservationFields(raw) {
    if (!Array.isArray(raw)) return [];
    return raw
        .map((field, index) => normalizeClassificationObservationField(field, index))
        .sort((a, b) => {
            const orderA = Number.isFinite(Number(a?.ordem)) ? Number(a.ordem) : Number.MAX_SAFE_INTEGER;
            const orderB = Number.isFinite(Number(b?.ordem)) ? Number(b.ordem) : Number.MAX_SAFE_INTEGER;
            if (orderA !== orderB) return orderA - orderB;
            return String(a?.id || '').localeCompare(String(b?.id || ''), 'pt-BR');
        });
}

function createObservationParameterValues(fields) {
    const values = {};
    fields.forEach((field) => {
        const key = String(field.id || '');
        if (!key) return;
        values[key] = field.tipo_campo === 'checkbox_texto' ? false : '';
    });
    return values;
}

function stringifyObservationParameterValue(field, rawValue) {
    const type = String(field?.tipo_campo || 'texto_curto');
    if (type === 'checkbox_texto') {
        return Boolean(rawValue) ? 'marcado' : '';
    }
    if (type === 'sim_nao') {
        const value = String(rawValue || '').trim().toLowerCase();
        if (value === 'sim' || value === 'nao') return value;
        return '';
    }
    return String(rawValue ?? '').trim();
}

function composeObservationWithClassification(baseObservation, fields, valuesMap) {
    const lines = [];
    const base = String(baseObservation || '').trim();

    if (base !== '') {
        lines.push(base);
    }

    fields.forEach((field) => {
        const key = String(field.id || '');
        if (!key) return;
        const normalizedValue = stringifyObservationParameterValue(field, valuesMap?.[key]);
        if (!normalizedValue) return;

        const fieldLabel = getObservationFieldLabel(field);
        if (String(field.tipo_campo || '') === 'checkbox_texto') {
            const checkboxLabel = String(field.texto_checkbox || '').trim() || fieldLabel;
            lines.push(checkboxLabel);
            return;
        }

        lines.push(`${fieldLabel}: ${normalizedValue}`);
    });

    return lines.join(' | ').slice(0, 1000);
}

function mapTable(table) {
    const fichas = (Array.isArray(table?.fichas) ? table.fichas : []).map((ficha) => ({
        id: String(ficha?.id || ''),
        tableId: String(table?.id || ''),
        code: String(ficha?.code || '--'),
        waiterName: String(ficha?.waiterName || 'Equipe'),
        customerName: String(ficha?.customerName || 'Cliente balcao'),
        isRandomCustomer: Boolean(ficha?.isRandomCustomer),
        status: String(ficha?.status || 'opened'),
        total: Number(ficha?.total || 0),
        openedAt: ficha?.openedAt || null,
        itemsCount: Number(ficha?.itemsCount || 0),
        ticketsCount: Number(ficha?.ticketsCount || 0),
        canAddItems: ficha?.canAddItems !== false,
        observation: String(ficha?.observation || ''),
        closingRequestedAt: ficha?.closingRequestedAt || null,
    }));

    return {
        id: String(table?.id || ''),
        code: String(table?.code || '--'),
        name: String(table?.name || `Mesa ${table?.code || '--'}`),
        status: String(table?.status || 'empty'),
        fichas,
        fichasCount: Number(table?.fichasCount || fichas.length || 0),
        totalOpen: Number(table?.totalOpen || 0),
    };
}

function getFichaStatusLabel(status) {
    const map = {
        opened: 'Aberta',
        em_atendimento: 'Em atendimento',
        aguardando_produção: 'Aguardando produção',
        parcialmente_entregue: 'Parcialmente entregue',
        em_conferencia: 'Em conferência',
        aguardando_pagamento: 'Aguardando pagamento',
        paga: 'Paga',
        cancelada: 'Cancelada',
        closed: 'Fechada',
    };

    return map[String(status || '').trim().toLowerCase()] || 'Ativa';
}

function getStatusVariant(status) {
    const normalized = String(status || '').trim().toLowerCase();
    if (normalized === 'aguardando_pagamento' || normalized === 'cancelada' || normalized === 'paga' || normalized === 'closed') {
        return 'danger';
    }
    if (normalized === 'aguardando_produção' || normalized === 'em_conferencia') {
        return 'warning';
    }
    if (normalized === 'em_atendimento' || normalized === 'opened' || normalized === 'parcialmente_entregue') {
        return 'success';
    }
    return 'default';
}

export function useRestaurantOrderingCore(mode = 'comanda_garcom') {
    const loading = ref(false);
    const creatingFicha = ref(false);
    const confirmingOrder = ref(false);
    const loadingSummary = ref(false);
    const loadingConference = ref(false);
    const requestingClose = ref(false);
    const savingObservation = ref(false);
    const error = ref('');

    const waiter = ref({ id: '', name: 'Equipe', email: '' });
    const categories = ref([]);
    const products = ref([]);
    const tables = ref([]);

    const searchQuery = ref('');
    const activeCategory = ref('todos');

    const selectedTableId = ref(null);
    const selectedCommandId = ref(null);

    const cart = ref([]);
    const cartDrawerOpen = ref(false);
    const successMessage = ref('');

    const currentOrderObservation = ref('');
    const fichaSummary = ref(null);
    const conferenceSummary = ref(null);

    const modifierModal = reactive({
        open: false,
        product: null,
        quantity: 1,
        observation: '',
        selectedOptions: [],
        removedIngredients: [],
        classificationParameters: [],
        classificationParameterValues: {},
    });

    const activeTable = computed(() => tables.value.find((item) => item.id === selectedTableId.value) || null);
    const availableCommands = computed(() => activeTable.value?.fichas || []);
    const activeCommand = computed(() => availableCommands.value.find((item) => item.id === selectedCommandId.value) || null);

    const normalizedSearch = computed(() => normalizeToken(searchQuery.value));

    const filteredProducts = computed(() => {
        return products.value.filter((product) => {
            if (activeCategory.value !== 'todos' && product.categoryId !== activeCategory.value) {
                return false;
            }

            if (!normalizedSearch.value) return true;

            const haystack = normalizeToken(`${product.nome} ${product.descricao || ''} ${product.id}`);
            return haystack.includes(normalizedSearch.value);
        });
    });

    const totalItems = computed(() =>
        cart.value.reduce((acc, item) => acc + (Number(item.quantity) || 0), 0),
    );

    const subtotal = computed(() =>
        roundMoney(
            cart.value.reduce((acc, item) => acc + (Number(item.lineTotal) || 0), 0),
        ),
    );

    const statusFicha = computed(() => String(activeCommand.value?.status || fichaSummary.value?.ficha?.status || 'opened'));
    const statusFichaLabel = computed(() => getFichaStatusLabel(statusFicha.value));
    const statusFichaVariant = computed(() => getStatusVariant(statusFicha.value));
    const canAddItemsToFicha = computed(() => !BLOCKED_ORDER_STATUSES.has(String(statusFicha.value || '').toLowerCase()));

    const pedidosEnviados = computed(() => Array.isArray(fichaSummary.value?.pedidosEnviados) ? fichaSummary.value.pedidosEnviados : []);
    const itensDaFicha = computed(() => Array.isArray(fichaSummary.value?.itensDaFicha) ? fichaSummary.value.itensDaFicha : []);
    const totalFicha = computed(() => {
        const fromSummary = Number(fichaSummary.value?.totals?.total || 0);
        if (fromSummary > 0) return fromSummary;
        return Number(activeCommand.value?.total || 0);
    });

    const totalPedidoAtual = computed(() => subtotal.value);

    watch(selectedTableId, (nextTableId) => {
        const hasCurrentFicha = availableCommands.value.some((item) => item.id === selectedCommandId.value);
        if (hasCurrentFicha) return;

        selectedCommandId.value = (tables.value.find((item) => item.id === nextTableId)?.fichas || [])[0]?.id || null;
    });

    watch(selectedCommandId, () => {
        loadFichaSummary().catch(() => {});
    });

    function getProductModifiers(productId) {
        return { adicionais: [], removerIngredientes: [] };
    }

    function computeSelectedOptions(productId, selectedOptionIds) {
        const meta = getProductModifiers(productId);
        const selected = [];

        meta.adicionais.forEach((group) => {
            group.opcoes.forEach((option) => {
                if (selectedOptionIds.includes(option.id)) {
                    selected.push({
                        groupId: group.id,
                        groupName: group.nome,
                        id: option.id,
                        nome: option.nome,
                        preco: Number(option.preco || 0),
                    });
                }
            });
        });

        return selected;
    }

    function openModifierModal(product) {
        const classificationParameters = normalizeClassificationObservationFields(product?.classificationObservationParameters);
        modifierModal.product = product;
        modifierModal.open = true;
        modifierModal.quantity = 1;
        modifierModal.observation = '';
        modifierModal.selectedOptions = [];
        modifierModal.removedIngredients = [];
        modifierModal.classificationParameters = classificationParameters;
        modifierModal.classificationParameterValues = createObservationParameterValues(classificationParameters);
    }

    function closeModifierModal() {
        modifierModal.open = false;
        modifierModal.product = null;
    }

    function addProductToCart(product, payload = {}) {
        const quantity = Math.max(1, Number(payload.quantity || 1));
        const selectedOptions = Array.isArray(payload.selectedOptions) ? payload.selectedOptions : [];
        const removedIngredients = Array.isArray(payload.removedIngredients) ? payload.removedIngredients : [];
        const optionRows = computeSelectedOptions(product.id, selectedOptions);
        const optionsTotal = optionRows.reduce((acc, item) => acc + Number(item.preco || 0), 0);
        const unitPrice = roundMoney(Number(product.preco || 0) + optionsTotal);
        const lineTotal = roundMoney(unitPrice * quantity);

        cart.value.push({
            id: `line-${Date.now()}-${Math.floor(Math.random() * 9999)}`,
            productId: product.id,
            nome: product.nome,
            quantity,
            basePrice: Number(product.preco || 0),
            unitPrice,
            lineTotal,
            observation: String(payload.observation || '').trim(),
            selectedOptions: optionRows,
            removedIngredients,
            classificationObservationAnswers: payload.classificationObservationAnswers || [],
        });
    }

    function submitModifierProduct() {
        if (!modifierModal.product) return;

        if (!canAddItemsToFicha.value) {
            error.value = 'Esta ficha está aguardando pagamento. Reabra a ficha para adicionar novos itens.';
            return;
        }

        addProductToCart(modifierModal.product, {
            quantity: modifierModal.quantity,
            observation: composeObservationWithClassification(
                modifierModal.observation,
                modifierModal.classificationParameters,
                modifierModal.classificationParameterValues,
            ),
            selectedOptions: modifierModal.selectedOptions,
            removedIngredients: modifierModal.removedIngredients,
            classificationObservationAnswers: modifierModal.classificationParameters
                .map((field) => {
                    const key = String(field.id || '');
                    if (!key) return null;
                    const normalizedValue = stringifyObservationParameterValue(field, modifierModal.classificationParameterValues[key]);
                    if (!normalizedValue) return null;
                    return {
                        id: key,
                        label: getObservationFieldLabel(field),
                        type: field.tipo_campo,
                        value: normalizedValue,
                    };
                })
                .filter(Boolean),
        });

        closeModifierModal();
        successMessage.value = 'Item adicionado ao pedido atual.';
        error.value = '';
    }

    function increaseItem(itemId) {
        const item = cart.value.find((entry) => entry.id === itemId);
        if (!item) return;

        item.quantity += 1;
        item.lineTotal = roundMoney(item.unitPrice * item.quantity);
    }

    function decreaseItem(itemId) {
        const item = cart.value.find((entry) => entry.id === itemId);
        if (!item) return;

        item.quantity = Math.max(1, item.quantity - 1);
        item.lineTotal = roundMoney(item.unitPrice * item.quantity);
    }

    function removeItem(itemId) {
        cart.value = cart.value.filter((entry) => entry.id !== itemId);
    }

    function clearCart() {
        cart.value = [];
    }

    async function refreshContext(preferredFichaId = null) {
        const response = await fetchRestaurantOrderingContext();
        waiter.value = response.waiter || waiter.value;
        tables.value = response.tables.map((table) => mapTable(table));

        if (!tables.value.length) {
            selectedTableId.value = null;
            selectedCommandId.value = null;
            fichaSummary.value = null;
            return;
        }

        const hasSelectedTable = tables.value.some((table) => table.id === selectedTableId.value);
        if (!hasSelectedTable) {
            selectedTableId.value = tables.value[0].id;
        }

        const currentTable = tables.value.find((table) => table.id === selectedTableId.value) || tables.value[0];
        const fichas = currentTable?.fichas || [];

        if (preferredFichaId && fichas.some((ficha) => ficha.id === preferredFichaId)) {
            selectedCommandId.value = preferredFichaId;
            return;
        }

        if (!fichas.some((ficha) => ficha.id === selectedCommandId.value)) {
            selectedCommandId.value = fichas[0]?.id || null;
        }
    }

    async function loadFichaSummary(fichaId = selectedCommandId.value) {
        if (!fichaId) {
            fichaSummary.value = null;
            return null;
        }

        loadingSummary.value = true;

        try {
            const response = await fetchRestaurantFichaSummary(fichaId);
            fichaSummary.value = response.summary || null;
            return fichaSummary.value;
        } finally {
            loadingSummary.value = false;
        }
    }

    async function loadConferenceSummary() {
        if (!selectedCommandId.value) {
            throw new Error('Selecione uma ficha para abrir a conferência.');
        }

        loadingConference.value = true;

        try {
            const response = await fetchRestaurantFichaConference(selectedCommandId.value);
            conferenceSummary.value = response.summary || null;
            return response;
        } finally {
            loadingConference.value = false;
        }
    }

    async function ensureActiveFicha() {
        if (selectedCommandId.value) return selectedCommandId.value;
        if (!selectedTableId.value) return null;

        const created = await createRestaurantFicha({
            table_id: selectedTableId.value,
            random_customer: true,
        });

        const nextFichaId = String(created?.ficha?.id || '');
        await refreshContext(nextFichaId || null);
        await loadFichaSummary(nextFichaId || selectedCommandId.value || null);
        return selectedCommandId.value;
    }

    async function createFichaForSelectedTable(payload = {}, options = {}) {
        if (!selectedTableId.value) {
            throw new Error('Selecione uma mesa antes de criar a ficha.');
        }

        if (cart.value.length && !options?.force) {
            throw new Error('Existe um pedido atual não enviado.');
        }

        creatingFicha.value = true;
        try {
            const response = await createRestaurantFicha({
                table_id: selectedTableId.value,
                random_customer: Boolean(payload.randomCustomer),
                customer_name: payload.customerName || null,
                ficha_code: payload.fichaCode || null,
            });

            const nextFichaId = String(response?.ficha?.id || '');
            await refreshContext(nextFichaId || null);
            await loadFichaSummary(nextFichaId || null);
            if (options?.clearCartOnSuccess) {
                clearCart();
            }
            successMessage.value = response.message || 'Ficha criada com sucesso.';
            error.value = '';
            return response;
        } finally {
            creatingFicha.value = false;
        }
    }

    async function saveFichaObservation(observation) {
        if (!selectedCommandId.value) {
            throw new Error('Selecione uma ficha antes de salvar observação.');
        }

        savingObservation.value = true;

        try {
            const response = await saveRestaurantFichaObservation(selectedCommandId.value, {
                observation: String(observation || '').trim(),
            });

            fichaSummary.value = response.summary || fichaSummary.value;
            await refreshContext(selectedCommandId.value);
            successMessage.value = response.message || 'Observação salva com sucesso.';
            error.value = '';
            return response;
        } finally {
            savingObservation.value = false;
        }
    }

    async function requestFichaClosing() {
        if (!selectedCommandId.value) {
            throw new Error('Selecione uma ficha antes de solicitar fechamento.');
        }

        requestingClose.value = true;

        try {
            const response = await requestRestaurantFichaClose(selectedCommandId.value);
            fichaSummary.value = response.summary || fichaSummary.value;
            await refreshContext(selectedCommandId.value);
            successMessage.value = response.message || 'Ficha enviada para o caixa.';
            error.value = '';
            return response;
        } finally {
            requestingClose.value = false;
        }
    }

    async function confirmOrder() {
        if (!selectedTableId.value) {
            error.value = 'Selecione uma mesa antes de enviar.';
            return null;
        }

        if (!selectedCommandId.value) {
            error.value = 'Selecione uma ficha antes de enviar.';
            return null;
        }

        if (!cart.value.length) {
            error.value = 'Adicione pelo menos um item ao pedido.';
            return null;
        }

        if (!canAddItemsToFicha.value) {
            error.value = 'Esta ficha está aguardando pagamento. Reabra a ficha para adicionar novos itens.';
            return null;
        }

        confirmingOrder.value = true;
        error.value = '';

        try {
            const fichaId = await ensureActiveFicha();
            if (!fichaId) {
                throw new Error('Não foi possível identificar a ficha para envio do pedido.');
            }

            const payload = {
                table_id: selectedTableId.value,
                order_observation: String(currentOrderObservation.value || '').trim() || null,
                items: cart.value.map((item) => ({
                    product_id: item.productId || null,
                    nome: item.nome,
                    quantity: item.quantity,
                    observation: item.observation || null,
                    selected_options: Array.isArray(item.selectedOptions)
                        ? item.selectedOptions.map((option) => option.nome)
                        : [],
                    removed_ingredients: Array.isArray(item.removedIngredients)
                        ? item.removedIngredients
                        : [],
                })),
            };

            const response = await submitRestaurantFichaOrder(fichaId, payload);
            const result = {
                id: response.tickets[0]?.id || `pedido-${Date.now()}`,
                tableCode: activeTable.value?.code || '--',
                commandCode: activeCommand.value?.code || '--',
                createdAt: new Date().toISOString(),
                total: subtotal.value,
                items: cart.value.length,
            };

            clearCart();
            currentOrderObservation.value = '';
            await refreshContext(fichaId);
            if (response.summary) {
                fichaSummary.value = response.summary;
            } else {
                await loadFichaSummary(fichaId);
            }
            successMessage.value = response.message || 'Pedido enviado para produção com sucesso.';
            return result;
        } catch (requestError) {
            error.value = String(requestError?.message || 'Não foi possível confirmar o pedido.');
            throw requestError;
        } finally {
            confirmingOrder.value = false;
        }
    }

    function dismissSuccess() {
        successMessage.value = '';
    }

    function clearError() {
        error.value = '';
    }

    async function bootstrap() {
        loading.value = true;
        error.value = '';

        try {
            const [categoriesResponse, productsResponse] = await Promise.all([
                api.get('/pos/categories'),
                api.get('/pos/products'),
            ]);

            categories.value = Array.isArray(categoriesResponse?.data)
                ? categoriesResponse.data.map((category) => ({
                    id: String(category?.id || ''),
                    nome: String(category?.nome || ''),
                }))
                : [];

            products.value = Array.isArray(productsResponse?.data)
                ? productsResponse.data.map((product) => mapProduct(product))
                : [];

            await refreshContext();
            await loadFichaSummary(selectedCommandId.value || null);
        } catch (requestError) {
            error.value = String(requestError?.message || 'Não foi possível carregar o modulo de fichas.');
        } finally {
            loading.value = false;
        }
    }

    onMounted(bootstrap);

    return {
        mode,
        loading,
        creatingFicha,
        confirmingOrder,
        loadingSummary,
        loadingConference,
        requestingClose,
        savingObservation,
        error,
        waiter,
        categories,
        filteredProducts,
        searchQuery,
        activeCategory,
        tables,
        commands: availableCommands,
        selectedTableId,
        selectedCommandId,
        activeTable,
        activeCommand,
        availableCommands,
        cart,
        cartDrawerOpen,
        totalItems,
        subtotal,
        totalPedidoAtual,
        currentOrderObservation,
        successMessage,
        modifierModal,
        fichaSummary,
        conferenceSummary,
        statusFicha,
        statusFichaLabel,
        statusFichaVariant,
        canAddItemsToFicha,
        pedidosEnviados,
        itensDaFicha,
        totalFicha,
        getProductModifiers,
        openModifierModal,
        closeModifierModal,
        submitModifierProduct,
        increaseItem,
        decreaseItem,
        removeItem,
        clearCart,
        createFichaForSelectedTable,
        refreshContext,
        loadFichaSummary,
        loadConferenceSummary,
        saveFichaObservation,
        requestFichaClosing,
        confirmOrder,
        dismissSuccess,
        clearError,
        getFichaStatusLabel,
        getStatusVariant,
    };
}
