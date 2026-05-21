const DEFAULT_ROOT_ID = 'root';

const DEFAULT_BRANCH_PALETTE = [
    '#8b5cf6',
    '#22c55e',
    '#ef4444',
    '#3b82f6',
    '#06b6d4',
    '#f59e0b',
    '#ec4899',
    '#14b8a6',
];

export const DEFAULT_COMPOSITION_ROOT_COLOR = '#f43f5e';

function normalizeId(value) {
    return String(value || '').trim();
}

function toNumber(value, fallback = 0) {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : fallback;
}

export function parseCompositionOrderValue(value) {
    const normalized = String(value || '').trim();
    if (!normalized) return null;
    const parsed = Number(normalized.replace(',', '.'));
    if (!Number.isFinite(parsed) || parsed <= 0) return null;
    return parsed;
}

function sortNodeIds(nodeIds, rowsById, indexById) {
    return [...nodeIds].sort((aId, bId) => {
        const aRow = rowsById[aId] || {};
        const bRow = rowsById[bId] || {};
        const aOrder = parseCompositionOrderValue(aRow.ordem);
        const bOrder = parseCompositionOrderValue(bRow.ordem);
        const aHasOrder = aOrder !== null;
        const bHasOrder = bOrder !== null;

        if (aHasOrder && bHasOrder) {
            if (aOrder !== bOrder) return aOrder - bOrder;
            return (indexById[aId] ?? 0) - (indexById[bId] ?? 0);
        }

        if (aHasOrder) return -1;
        if (bHasOrder) return 1;

        return (indexById[aId] ?? 0) - (indexById[bId] ?? 0);
    });
}

export function buildCompositionTree(rows = [], options = {}) {
    const rootId = normalizeId(options.rootId || DEFAULT_ROOT_ID) || DEFAULT_ROOT_ID;
    const entries = Array.isArray(rows) ? rows : [];
    const rowsById = {};
    const indexById = {};

    entries.forEach((row, index) => {
        const id = normalizeId(row?.id);
        if (!id || rowsById[id]) return;
        rowsById[id] = row;
        indexById[id] = index;
    });

    const parentById = {};
    const childrenByParent = { [rootId]: [] };

    Object.values(rowsById).forEach((row) => {
        const nodeId = normalizeId(row?.id);
        if (!nodeId) return;

        const requestedParentId = normalizeId(row?.parent_id) || rootId;
        const resolvedParentId = requestedParentId !== nodeId && rowsById[requestedParentId]
            ? requestedParentId
            : rootId;

        parentById[nodeId] = resolvedParentId;

        if (!childrenByParent[resolvedParentId]) {
            childrenByParent[resolvedParentId] = [];
        }

        childrenByParent[resolvedParentId].push(nodeId);
    });

    Object.keys(childrenByParent).forEach((parentId) => {
        childrenByParent[parentId] = sortNodeIds(childrenByParent[parentId], rowsById, indexById);
    });

    const orderedRows = [];
    const nodeMetaById = {};
    const visited = new Set();
    let sequence = 1;

    const walk = (nodeId, depth, ancestorLastFlags, branchRootId, branchIndex, isLastChild = true) => {
        if (!nodeId || visited.has(nodeId)) return;
        visited.add(nodeId);

        const children = childrenByParent[nodeId] || [];
        const row = rowsById[nodeId];

        nodeMetaById[nodeId] = {
            id: nodeId,
            parentId: parentById[nodeId] || rootId,
            depth,
            branchRootId,
            branchIndex,
            childrenCount: children.length,
        };

        orderedRows.push({
            source: row,
            originalIndex: indexById[nodeId] ?? 0,
            depth,
            ancestorLastFlags,
            isLastChild,
            sequencia: sequence,
            sequenciaLabel: `${sequence}º`,
        });
        sequence += 1;

        children.forEach((childId, childIndex) => {
            const childIsLast = childIndex === children.length - 1;
            walk(childId, depth + 1, [...ancestorLastFlags, childIsLast], branchRootId, branchIndex, childIsLast);
        });
    };

    const rootChildren = childrenByParent[rootId] || [];
    rootChildren.forEach((nodeId, index) => {
        const isLastChild = index === rootChildren.length - 1;
        walk(nodeId, 1, [], nodeId, index, isLastChild);
    });

    let orphanBranchIndex = rootChildren.length;
    Object.keys(rowsById).forEach((nodeId) => {
        if (visited.has(nodeId)) return;
        walk(nodeId, 1, [], nodeId, orphanBranchIndex, true);
        orphanBranchIndex += 1;
    });

    return {
        rootId,
        rowsById,
        parentById,
        childrenByParent,
        rootChildren,
        nodeMetaById,
        orderedRows,
    };
}

export function resolveCompositionBranchColors(rows = [], options = {}) {
    const rootColor = options.rootColor || DEFAULT_COMPOSITION_ROOT_COLOR;
    const palette = Array.isArray(options.palette) && options.palette.length
        ? options.palette
        : DEFAULT_BRANCH_PALETTE;

    const tree = buildCompositionTree(rows, options);
    const branchByNodeId = {
        [tree.rootId]: {
            nodeId: tree.rootId,
            parentId: null,
            rootChildId: tree.rootId,
            branchIndex: -1,
            branchColor: rootColor,
            level: 1,
        },
    };

    const visited = new Set([tree.rootId]);

    const applyBranch = (nodeId, branchColor, branchIndex, rootChildId, level) => {
        if (!nodeId || visited.has(nodeId)) return;
        visited.add(nodeId);

        const parentId = tree.parentById[nodeId] || tree.rootId;
        branchByNodeId[nodeId] = {
            nodeId,
            parentId,
            rootChildId,
            branchIndex,
            branchColor,
            level,
        };

        const children = tree.childrenByParent[nodeId] || [];
        children.forEach((childId) => applyBranch(childId, branchColor, branchIndex, rootChildId, level + 1));
    };

    tree.rootChildren.forEach((childId, index) => {
        const branchColor = palette[index % palette.length];
        applyBranch(childId, branchColor, index, childId, 2);
    });

    let orphanBranchIndex = tree.rootChildren.length;
    Object.keys(tree.rowsById).forEach((nodeId) => {
        if (visited.has(nodeId)) return;
        const branchColor = palette[orphanBranchIndex % palette.length];
        applyBranch(nodeId, branchColor, orphanBranchIndex, nodeId, 2);
        orphanBranchIndex += 1;
    });

    return {
        ...tree,
        rootColor,
        palette,
        branchByNodeId,
    };
}

function sumAdditionalOperationalCost(additionalFields = []) {
    if (!Array.isArray(additionalFields)) return 0;
    return additionalFields.reduce((acc, field) => acc + Math.max(0, toNumber(field?.operational_cost, 0)), 0);
}

function resolveQuantity(value) {
    const quantity = toNumber(value, 1);
    return quantity > 0 ? quantity : 0;
}

export function calculateCompositionCosts(rows = [], options = {}) {
    const tree = resolveCompositionBranchColors(rows, options);
    const nodeCostById = {};

    const calculateNode = (nodeId, ancestry = new Set()) => {
        if (nodeCostById[nodeId]) return nodeCostById[nodeId];

        if (!nodeId || ancestry.has(nodeId)) {
            return {
                own_cost: 0,
                accumulated_cost: 0,
                component_cost: 0,
                operational_cost_total: 0,
                cost_enabled_count: 0,
                cost_ignored_count: 0,
                total_nodes: 0,
            };
        }

        const row = tree.rowsById[nodeId] || {};
        const baseCost = Math.max(0, toNumber(row?.preco_custo, 0));
        const operationalCost = Math.max(0, toNumber(row?.operational_cost, 0));
        const additionalOperationalCost = sumAdditionalOperationalCost(row?.campos_adicionais);
        const ownCost = baseCost + operationalCost + additionalOperationalCost;

        const nextAncestry = new Set(ancestry);
        nextAncestry.add(nodeId);

        const children = tree.childrenByParent[nodeId] || [];
        let childrenIncludedCost = 0;
        let childrenOperationalCost = 0;
        let childrenComponentCost = 0;
        let costEnabledCount = row?.calculate_cost === false ? 0 : 1;
        let costIgnoredCount = row?.calculate_cost === false ? 1 : 0;
        let totalNodes = 1;

        children.forEach((childId) => {
            const childMetrics = calculateNode(childId, nextAncestry);
            const childRow = tree.rowsById[childId] || {};
            const quantity = resolveQuantity(childRow?.quantidade);
            const shouldIncludeCost = childRow?.calculate_cost !== false;

            totalNodes += childMetrics.total_nodes;
            costEnabledCount += childMetrics.cost_enabled_count;
            costIgnoredCount += childMetrics.cost_ignored_count;
            childrenOperationalCost += childMetrics.operational_cost_total;
            childrenComponentCost += childMetrics.component_cost;

            if (shouldIncludeCost) {
                childrenIncludedCost += childMetrics.accumulated_cost * quantity;
            }
        });

        const metrics = {
            own_cost: ownCost,
            accumulated_cost: ownCost + childrenIncludedCost,
            component_cost: baseCost + childrenComponentCost,
            operational_cost_total: operationalCost + additionalOperationalCost + childrenOperationalCost,
            cost_enabled_count: costEnabledCount,
            cost_ignored_count: costIgnoredCount,
            total_nodes: totalNodes,
        };

        nodeCostById[nodeId] = metrics;
        return metrics;
    };

    tree.rootChildren.forEach((nodeId) => calculateNode(nodeId));

    const rootBaseCost = Math.max(0, toNumber(options.rootBaseCost, 0));
    const rootOperationalCost = Math.max(0, toNumber(options.rootOperationalCost, 0));
    const rootAdditionalOperationalCost = sumAdditionalOperationalCost(options.rootAdditionalFields);
    const rootOwnCost = rootBaseCost + rootOperationalCost + rootAdditionalOperationalCost;

    let rootChildrenCost = 0;
    let componentCost = rootBaseCost;
    let operationalCostTotal = rootOperationalCost + rootAdditionalOperationalCost;
    let costEnabledCount = options.rootCalculateCost === false ? 0 : 1;
    let costIgnoredCount = options.rootCalculateCost === false ? 1 : 0;
    let totalNodes = 1;

    tree.rootChildren.forEach((childId) => {
        const childMetrics = nodeCostById[childId] || calculateNode(childId);
        const childRow = tree.rowsById[childId] || {};
        const quantity = resolveQuantity(childRow?.quantidade);
        const shouldIncludeCost = childRow?.calculate_cost !== false;

        componentCost += childMetrics.component_cost;
        operationalCostTotal += childMetrics.operational_cost_total;
        costEnabledCount += childMetrics.cost_enabled_count;
        costIgnoredCount += childMetrics.cost_ignored_count;
        totalNodes += childMetrics.total_nodes;

        if (shouldIncludeCost) {
            rootChildrenCost += childMetrics.accumulated_cost * quantity;
        }
    });

    const rootMetrics = {
        own_cost: rootOwnCost,
        accumulated_cost: rootOwnCost + rootChildrenCost,
        component_cost: componentCost,
        operational_cost_total: operationalCostTotal,
        cost_enabled_count: costEnabledCount,
        cost_ignored_count: costIgnoredCount,
        total_nodes: totalNodes,
    };

    nodeCostById[tree.rootId] = rootMetrics;

    return {
        tree,
        nodeCostById,
        summary: {
            ...rootMetrics,
            total_items: totalNodes,
            cost_participants: costEnabledCount,
            cost_ignored: costIgnoredCount,
        },
    };
}

export function calculateSuggestedPricing(summary = {}, options = {}) {
    const costTotal = Math.max(0, toNumber(summary?.accumulated_cost, 0));
    const taxesRate = Math.max(0, toNumber(options.taxesRate, 0));
    const desiredMargin = Math.max(0, toNumber(options.desiredMargin, 0));
    const salePrice = Math.max(0, toNumber(options.salePrice, 0));

    const minimumDenominator = 1 - taxesRate;
    const suggestedDenominator = 1 - taxesRate - desiredMargin;

    const minimumPrice = minimumDenominator > 0
        ? costTotal / minimumDenominator
        : null;

    const suggestedPrice = suggestedDenominator > 0
        ? costTotal / suggestedDenominator
        : null;

    const unitProfit = salePrice > 0
        ? salePrice - costTotal - (salePrice * taxesRate)
        : null;

    const realMargin = salePrice > 0 && unitProfit !== null
        ? unitProfit / salePrice
        : null;

    return {
        cost_total: costTotal,
        minimum_price: minimumPrice,
        suggested_price: suggestedPrice,
        unit_profit: unitProfit,
        real_margin: realMargin,
        invalid_minimum_denominator: minimumDenominator <= 0,
        invalid_suggested_denominator: suggestedDenominator <= 0,
    };
}
