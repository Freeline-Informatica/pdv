import { computed, reactive } from 'vue';

export const COMMAND_CENTER_TAB = Object.freeze({
    CLOSED: 'closed',
    OPENED: 'opened',
});

export const COMMAND_STATUS = Object.freeze({
    OPENED: 'opened',
    CLOSED: 'closed',
    PENDING_FISCAL: 'pending_fiscal',
    INTEGRATED: 'integrated',
    PROBLEM: 'problem',
});

const COMMAND_CENTER_CONTEXT = Object.freeze({
    CASHIER: 'cashier',
    WAITER: 'waiter',
    TERMINAL: 'terminal',
});

function roundMoney(value) {
    return Math.round((Number(value) || 0) * 100) / 100;
}

function normalizeQuantity(value) {
    return Math.round((Number(value) || 0) * 1000) / 1000;
}

function normalizeSearchToken(value) {
    return String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim()
        .toLowerCase();
}

function createCommandItemFromProduct(product, quantity = 1, overrides = {}) {
    const normalizedQty = normalizeQuantity(quantity);
    const unitPrice = roundMoney(overrides.unitPrice ?? product?.preco_venda ?? 0);
    const sellerName = String(overrides.sellerName || 'Operador caixa').trim();

    const history = Array.isArray(overrides.history)
        ? overrides.history
        : [
            {
                id: `hist-${Math.random().toString(36).slice(2, 8)}`,
                atLabel: String(overrides.historyAtLabel || 'Agora'),
                action: String(overrides.historyAction || 'Lançamento inicial'),
                by: sellerName,
                quantity: normalizedQty > 0 ? normalizedQty : 1,
            },
        ];

    return {
        id: String(overrides.id ?? product?.id ?? product?.codigo ?? `cmd-item-${Math.random().toString(36).slice(2, 8)}`),
        productId: String(overrides.productId ?? product?.id ?? ''),
        nome: String(overrides.nome ?? product?.nome ?? 'Item sem descrição'),
        codigo: String(overrides.codigo ?? product?.codigo ?? ''),
        unidade: String(overrides.unidade ?? product?.unidade ?? 'UN'),
        qty: normalizedQty > 0 ? normalizedQty : 1,
        preco_venda: unitPrice,
        total: roundMoney((normalizedQty > 0 ? normalizedQty : 1) * unitPrice),
        observation: String(overrides.observation || '').trim(),
        sellerName,
        history,
    };
}

function sanitizeCommandItem(item) {
    return createCommandItemFromProduct(item, item?.qty ?? 1, {
        id: item?.id,
        productId: item?.productId,
        nome: item?.nome,
        codigo: item?.codigo,
        unidade: item?.unidade,
        unitPrice: item?.preco_venda,
        observation: item?.observation,
        sellerName: item?.sellerName,
        history: Array.isArray(item?.history) ? item.history : undefined,
    });
}

function computeCommandTotal(items = []) {
    return roundMoney(
        items.reduce((sum, item) => sum + Number(item?.preco_venda || 0) * Number(item?.qty || 0), 0),
    );
}

function normalizeCommand(command, context = {}) {
    const normalizedItems = (Array.isArray(command?.items) ? command.items : []).map((item) => sanitizeCommandItem(item));
    const total = computeCommandTotal(normalizedItems);

    return {
        id: String(command?.id || `cmd-${Math.random().toString(36).slice(2, 8)}`),
        code: String(command?.code || command?.id || '--'),
        status: String(command?.status || COMMAND_STATUS.OPENED),
        openedAtLabel: String(command?.openedAtLabel || context.openedAtLabel || '--'),
        closedAtLabel: String(command?.closedAtLabel || context.closedAtLabel || '--'),
        waiterName: String(command?.waiterName || context.defaultWaiterName || 'Equipe'),
        total,
        itemsCount: normalizedItems.length,
        pendingFiscal: Boolean(command?.pendingFiscal),
        items: normalizedItems,
        tags: Array.isArray(command?.tags) ? command.tags : [],
    };
}

function normalizeTable(table, status = COMMAND_CENTER_TAB.CLOSED) {
    const normalizedCommands = (Array.isArray(table?.commands) ? table.commands : [])
        .map((command) => normalizeCommand(command, {
            openedAtLabel: table?.openedAtLabel,
            closedAtLabel: table?.closedAtLabel,
            defaultWaiterName: table?.waiterName,
        }));

    const tableItemsCount = normalizedCommands.reduce((sum, command) => sum + Number(command.itemsCount || 0), 0);
    const tableTotal = roundMoney(normalizedCommands.reduce((sum, command) => sum + Number(command.total || 0), 0));

    return {
        id: String(table?.id || `table-${Math.random().toString(36).slice(2, 8)}`),
        code: String(table?.code || '--'),
        customerName: String(table?.customerName || `Mesa ${table?.code || '--'}`),
        openedAtLabel: String(table?.openedAtLabel || '--'),
        closedAtLabel: String(table?.closedAtLabel || '--'),
        status: status === COMMAND_CENTER_TAB.OPENED ? COMMAND_STATUS.OPENED : COMMAND_STATUS.CLOSED,
        waiterName: String(table?.waiterName || 'Equipe'),
        pendingFiscal: Boolean(table?.pendingFiscal),
        commandsCount: normalizedCommands.length,
        itemsCount: tableItemsCount,
        total: tableTotal,
        commands: normalizedCommands,
    };
}

function buildDefaultCommandSeed(catalogProducts = []) {
    const source = catalogProducts.length
        ? catalogProducts
        : [
            { id: 'fallback-1', nome: 'Produto não configurado', codigo: '0000', preco_venda: 0, unidade: 'UN' },
        ];
    const pick = (index) => source[index % source.length];

    const closedTables = [
        {
            id: 'table-12',
            code: '12',
            customerName: 'Mesa 12',
            closedAtLabel: 'Fechada às 21:10',
            openedAtLabel: 'Aberta às 19:45',
            waiterName: 'Carlos',
            pendingFiscal: true,
            commands: [
                {
                    id: 'cmd-1201',
                    code: '1201',
                    status: COMMAND_STATUS.PENDING_FISCAL,
                    pendingFiscal: true,
                    closedAtLabel: 'Fechada às 21:10',
                    waiterName: 'Carlos',
                    items: [
                        createCommandItemFromProduct(pick(0), 2, { sellerName: 'Carlos', observation: 'Sem cebola' }),
                        createCommandItemFromProduct(pick(1), 1, { sellerName: 'Carlos' }),
                    ],
                },
                {
                    id: 'cmd-1202',
                    code: '1202',
                    status: COMMAND_STATUS.PENDING_FISCAL,
                    pendingFiscal: true,
                    closedAtLabel: 'Fechada às 21:10',
                    waiterName: 'Ana',
                    items: [
                        createCommandItemFromProduct(pick(2), 1, { sellerName: 'Ana', observation: 'Ponto bem passado' }),
                    ],
                },
            ],
        },
        {
            id: 'table-07',
            code: '07',
            customerName: 'Mesa 07',
            closedAtLabel: 'Fechada às 20:46',
            openedAtLabel: 'Aberta às 18:58',
            waiterName: 'Juliana',
            pendingFiscal: true,
            commands: [
                {
                    id: 'cmd-0701',
                    code: '0701',
                    status: COMMAND_STATUS.PENDING_FISCAL,
                    pendingFiscal: true,
                    closedAtLabel: 'Fechada às 20:46',
                    waiterName: 'Juliana',
                    items: [
                        createCommandItemFromProduct(pick(3), 1, { sellerName: 'Juliana' }),
                        createCommandItemFromProduct(pick(4), 2, { sellerName: 'Juliana', observation: 'Sem gelo' }),
                    ],
                },
            ],
        },
        {
            id: 'table-18',
            code: '18',
            customerName: 'Mesa 18',
            closedAtLabel: 'Fechada às 20:30',
            openedAtLabel: 'Aberta às 19:12',
            waiterName: 'Gustavo',
            pendingFiscal: true,
            commands: [
                {
                    id: 'cmd-1801',
                    code: '1801',
                    status: COMMAND_STATUS.PENDING_FISCAL,
                    pendingFiscal: true,
                    closedAtLabel: 'Fechada às 20:30',
                    waiterName: 'Gustavo',
                    items: [
                        createCommandItemFromProduct(pick(5), 2, { sellerName: 'Gustavo' }),
                        createCommandItemFromProduct(pick(6), 1, { sellerName: 'Gustavo' }),
                    ],
                },
            ],
        },
    ];

    const openedTables = [
        {
            id: 'table-03',
            code: '03',
            customerName: 'Mesa 03',
            openedAtLabel: 'Aberta às 19:48',
            waiterName: 'Fernanda',
            commands: [
                {
                    id: 'cmd-0301',
                    code: '0301',
                    status: COMMAND_STATUS.OPENED,
                    openedAtLabel: 'Aberta às 19:48',
                    waiterName: 'Fernanda',
                    items: [
                        createCommandItemFromProduct(pick(2), 1, { sellerName: 'Fernanda' }),
                        createCommandItemFromProduct(pick(0), 1, { sellerName: 'Fernanda', observation: 'Sem alho' }),
                    ],
                },
            ],
        },
        {
            id: 'table-09',
            code: '09',
            customerName: 'Mesa 09',
            openedAtLabel: 'Aberta às 20:05',
            waiterName: 'Bruno',
            commands: [
                {
                    id: 'cmd-0901',
                    code: '0901',
                    status: COMMAND_STATUS.OPENED,
                    openedAtLabel: 'Aberta às 20:05',
                    waiterName: 'Bruno',
                    items: [
                        createCommandItemFromProduct(pick(1), 2, { sellerName: 'Bruno' }),
                    ],
                },
                {
                    id: 'cmd-0902',
                    code: '0902',
                    status: COMMAND_STATUS.OPENED,
                    openedAtLabel: 'Aberta às 20:20',
                    waiterName: 'Bruno',
                    items: [
                        createCommandItemFromProduct(pick(4), 1, { sellerName: 'Bruno', observation: 'Sem açúcar' }),
                    ],
                },
            ],
        },
        {
            id: 'table-14',
            code: '14',
            customerName: 'Mesa 14',
            openedAtLabel: 'Aberta às 20:22',
            waiterName: 'Equipe',
            commands: [
                {
                    id: 'cmd-1401',
                    code: '1401',
                    status: COMMAND_STATUS.PROBLEM,
                    openedAtLabel: 'Aberta às 20:22',
                    waiterName: 'Equipe',
                    tags: ['Atenção'],
                    items: [
                        createCommandItemFromProduct(pick(6), 3, { sellerName: 'Equipe' }),
                        createCommandItemFromProduct(pick(5), 1, { sellerName: 'Equipe', observation: 'Pedido duplicado em conferência' }),
                    ],
                },
            ],
        },
    ];

    return {
        openedTables,
        closedTables,
    };
}

function tableMatchesSearch(table, token) {
    if (!token) return true;

    const normalizedCode = normalizeSearchToken(table?.code);
    const normalizedCustomer = normalizeSearchToken(table?.customerName);
    const normalizedWaiter = normalizeSearchToken(table?.waiterName);

    if (normalizedCode.includes(token) || normalizedCustomer.includes(token) || normalizedWaiter.includes(token)) {
        return true;
    }

    return (Array.isArray(table?.commands) ? table.commands : []).some((command) => {
        const commandCode = normalizeSearchToken(command?.code);
        const commandWaiter = normalizeSearchToken(command?.waiterName);

        if (commandCode.includes(token) || commandWaiter.includes(token)) return true;

        return (Array.isArray(command?.items) ? command.items : []).some((item) => {
            const itemName = normalizeSearchToken(item?.nome);
            const itemCode = normalizeSearchToken(item?.codigo);
            return itemName.includes(token) || itemCode.includes(token);
        });
    });
}

function normalizeTab(value) {
    return value === COMMAND_CENTER_TAB.OPENED ? COMMAND_CENTER_TAB.OPENED : COMMAND_CENTER_TAB.CLOSED;
}

export function useRestaurantCommandCenter(options = {}) {
    const state = reactive({
        loaded: false,
        loading: false,
        dataSource: 'seed',
        activeTab: COMMAND_CENTER_TAB.CLOSED,
        searchQuery: '',
        error: '',
        contextMode: COMMAND_CENTER_CONTEXT.CASHIER,
        showUnitPrice: true,
        onlyPendingFiscal: false,
        openedTables: [],
        closedTables: [],
        selectedTableId: '',
        selectedCommandId: '',
        transferActionOpen: false,
        mergeDialogOpen: false,
    });

    function resolveCatalogProducts() {
        const fromOptions = typeof options.getProducts === 'function' ? options.getProducts() : [];
        return Array.isArray(fromOptions) ? fromOptions.filter(Boolean) : [];
    }

    function applySeed(seed) {
        const openedTables = (Array.isArray(seed?.openedTables) ? seed.openedTables : [])
            .map((table) => normalizeTable(table, COMMAND_CENTER_TAB.OPENED));
        const closedTables = (Array.isArray(seed?.closedTables) ? seed.closedTables : [])
            .map((table) => normalizeTable(table, COMMAND_CENTER_TAB.CLOSED));

        state.openedTables = openedTables;
        state.closedTables = closedTables;

        ensureSelectionForActiveTab();
        state.loaded = true;
    }

    function buildSeed() {
        if (typeof options.seedBuilder === 'function') {
            return options.seedBuilder(resolveCatalogProducts());
        }

        return buildDefaultCommandSeed(resolveCatalogProducts());
    }

    async function fetchRemoteSeed() {
        const apiClient = options.api;
        if (!apiClient || typeof apiClient.fetchSnapshot !== 'function') return null;

        const response = await apiClient.fetchSnapshot();
        if (!response || typeof response !== 'object') return null;

        if (response.source) {
            state.dataSource = String(response.source);
        }

        if (response.message && response.source === 'fallback') {
            setError(String(response.message));
        }

        return response.snapshot || null;
    }

    async function ensureData() {
        if (state.loaded || state.loading) return;
        state.loading = true;

        try {
            clearError();

            const remoteSeed = await fetchRemoteSeed();
            applySeed(remoteSeed || buildSeed());

            if (!remoteSeed) {
                state.dataSource = state.dataSource || 'seed';
            }
        } catch (error) {
            applySeed(buildSeed());
            state.dataSource = 'seed';
            setError(error?.message || 'Não foi possível carregar a central de comandas.');
        } finally {
            state.loading = false;
        }
    }

    function resetData(keepActiveTab = true) {
        const nextTab = keepActiveTab ? state.activeTab : COMMAND_CENTER_TAB.CLOSED;

        state.loaded = false;
        state.loading = false;
        state.openedTables = [];
        state.closedTables = [];
        state.selectedTableId = '';
        state.selectedCommandId = '';
        state.error = '';
        state.activeTab = normalizeTab(nextTab);
    }

    async function reintegrate() {
        if (state.loading) return '';
        const activeTab = state.activeTab;

        const apiClient = options.api;
        state.loading = true;

        try {
            clearError();

            let remoteSeed = null;
            let message = '';

            if (apiClient && typeof apiClient.reintegrate === 'function') {
                const response = await apiClient.reintegrate();
                remoteSeed = response?.snapshot || null;
                message = String(response?.message || '');
                state.dataSource = String(response?.source || state.dataSource || 'seed');
            }

            resetData(true);
            state.activeTab = activeTab;
            applySeed(remoteSeed || buildSeed());
            return message;
        } catch (error) {
            resetData(true);
            state.activeTab = activeTab;
            applySeed(buildSeed());
            state.dataSource = 'seed';
            setError(error?.message || 'Não foi possível reintegrar as comandas.');
            return '';
        } finally {
            state.loading = false;
        }
    }

    function getActiveTables() {
        return state.activeTab === COMMAND_CENTER_TAB.OPENED ? state.openedTables : state.closedTables;
    }

    function ensureSelectionForActiveTab() {
        const activeTables = getActiveTables();
        const selectedTable = activeTables.find((table) => table.id === state.selectedTableId) || activeTables[0] || null;

        state.selectedTableId = selectedTable?.id || '';

        if (!selectedTable) {
            state.selectedCommandId = '';
            return;
        }

        const selectedCommand = selectedTable.commands.find((command) => command.id === state.selectedCommandId)
            || selectedTable.commands[0]
            || null;

        state.selectedCommandId = selectedCommand?.id || '';
    }

    function setActiveTab(tab) {
        state.activeTab = normalizeTab(tab);
        ensureSelectionForActiveTab();
    }

    function setSearchQuery(value) {
        state.searchQuery = String(value || '');
        ensureSelectionForActiveTab();
    }

    function setOnlyPendingFiscal(value) {
        state.onlyPendingFiscal = Boolean(value);
        ensureSelectionForActiveTab();
    }

    function setShowUnitPrice(value) {
        state.showUnitPrice = Boolean(value);
    }

    function setContextMode(mode) {
        if (Object.values(COMMAND_CENTER_CONTEXT).includes(mode)) {
            state.contextMode = mode;
            return;
        }

        state.contextMode = COMMAND_CENTER_CONTEXT.CASHIER;
    }

    function setError(message) {
        state.error = String(message || '');
    }

    function clearError() {
        state.error = '';
    }

    function selectTable(tableId) {
        const normalizedId = String(tableId || '');
        if (!normalizedId) return;

        const table = getActiveTables().find((item) => item.id === normalizedId);
        if (!table) return;

        state.selectedTableId = table.id;
        state.selectedCommandId = table.commands[0]?.id || '';
        clearError();
    }

    function selectCommand(commandId) {
        const normalizedId = String(commandId || '');
        if (!normalizedId) return;

        const table = getActiveTables().find((item) => item.id === state.selectedTableId);
        if (!table) return;

        const command = table.commands.find((item) => item.id === normalizedId);
        if (!command) return;

        state.selectedCommandId = command.id;
        clearError();
    }

    const searchToken = computed(() => normalizeSearchToken(state.searchQuery));

    const filteredClosedTables = computed(() => {
        const token = searchToken.value;
        const onlyPending = state.onlyPendingFiscal;

        return state.closedTables
            .filter((table) => tableMatchesSearch(table, token))
            .filter((table) => !onlyPending || table.pendingFiscal || table.commands.some((command) => command.pendingFiscal));
    });

    const filteredOpenedTables = computed(() => {
        const token = searchToken.value;
        return state.openedTables.filter((table) => tableMatchesSearch(table, token));
    });

    const activeTables = computed(() => (
        state.activeTab === COMMAND_CENTER_TAB.OPENED ? filteredOpenedTables.value : filteredClosedTables.value
    ));

    const selectedTable = computed(() => (
        activeTables.value.find((table) => table.id === state.selectedTableId)
        || activeTables.value[0]
        || null
    ));

    const selectedCommand = computed(() => {
        const table = selectedTable.value;
        if (!table) return null;

        return table.commands.find((command) => command.id === state.selectedCommandId) || table.commands[0] || null;
    });

    const selectedCommandItems = computed(() => selectedCommand.value?.items || []);

    const canImportSelectedCommand = computed(() => (
        state.activeTab === COMMAND_CENTER_TAB.CLOSED
        && Boolean(selectedCommand.value)
        && Boolean(selectedCommand.value?.pendingFiscal)
        && selectedCommandItems.value.length > 0
    ));

    const closedCommandsCount = computed(() => (
        filteredClosedTables.value.reduce((sum, table) => sum + Number(table.commandsCount || 0), 0)
    ));

    const openedCommandsCount = computed(() => (
        filteredOpenedTables.value.reduce((sum, table) => sum + Number(table.commandsCount || 0), 0)
    ));

    const modalSummary = computed(() => ({
        tablesClosed: filteredClosedTables.value.length,
        tablesOpened: filteredOpenedTables.value.length,
        commandsClosed: closedCommandsCount.value,
        commandsOpened: openedCommandsCount.value,
    }));

    function resolveImportSelection() {
        const table = selectedTable.value;
        const command = selectedCommand.value;

        if (!table || !command) return null;

        const items = (Array.isArray(command.items) ? command.items : []).map((item) => sanitizeCommandItem(item));

        return {
            table,
            command,
            items,
        };
    }

    function markCommandAsIntegrated(importSelection) {
        const selection = importSelection || resolveImportSelection();
        if (!selection) return;

        const closedTableIndex = state.closedTables.findIndex((table) => table.id === selection.table.id);
        if (closedTableIndex < 0) return;

        const targetTable = state.closedTables[closedTableIndex];
        const nextCommands = (Array.isArray(targetTable.commands) ? targetTable.commands : [])
            .filter((command) => command.id !== selection.command.id);

        if (!nextCommands.length) {
            state.closedTables.splice(closedTableIndex, 1);
        } else {
            const nextTable = normalizeTable(
                {
                    ...targetTable,
                    commands: nextCommands,
                },
                COMMAND_CENTER_TAB.CLOSED,
            );
            state.closedTables.splice(closedTableIndex, 1, nextTable);
        }

        ensureSelectionForActiveTab();
    }

    function openTransferActionSheet() {
        state.transferActionOpen = true;
    }

    function closeTransferActionSheet() {
        state.transferActionOpen = false;
    }

    function openMergeDialog() {
        state.mergeDialogOpen = true;
    }

    function closeMergeDialog() {
        state.mergeDialogOpen = false;
    }

    async function registerTransfer(payload = {}) {
        const apiClient = options.api;
        if (!apiClient || typeof apiClient.registerTransfer !== 'function') {
            return { source: 'local', message: 'Transferência preparada localmente.' };
        }

        return apiClient.registerTransfer(payload);
    }

    async function registerMerge(payload = {}) {
        const apiClient = options.api;
        if (!apiClient || typeof apiClient.registerMerge !== 'function') {
            return { source: 'local', message: 'Junção preparada localmente.' };
        }

        return apiClient.registerMerge(payload);
    }

    async function registerPrintAction(payload = {}) {
        const apiClient = options.api;
        if (!apiClient || typeof apiClient.registerPrintAction !== 'function') {
            return { source: 'local', message: 'Impressão operacional preparada localmente.' };
        }

        return apiClient.registerPrintAction(payload);
    }

    async function registerConference(payload = {}) {
        const apiClient = options.api;
        if (!apiClient || typeof apiClient.registerConference !== 'function') {
            return { source: 'local', message: 'Conferência preparada localmente.' };
        }

        return apiClient.registerConference(payload);
    }

    return {
        state,
        COMMAND_CENTER_TAB,
        COMMAND_STATUS,
        COMMAND_CENTER_CONTEXT,
        filteredClosedTables,
        filteredOpenedTables,
        activeTables,
        selectedTable,
        selectedCommand,
        selectedCommandItems,
        canImportSelectedCommand,
        modalSummary,
        ensureData,
        resetData,
        reintegrate,
        setActiveTab,
        setSearchQuery,
        setOnlyPendingFiscal,
        setShowUnitPrice,
        setContextMode,
        setError,
        clearError,
        selectTable,
        selectCommand,
        resolveImportSelection,
        markCommandAsIntegrated,
        openTransferActionSheet,
        closeTransferActionSheet,
        openMergeDialog,
        closeMergeDialog,
        registerTransfer,
        registerMerge,
        registerPrintAction,
        registerConference,
        sanitizeCommandItem,
        roundMoney,
    };
}
