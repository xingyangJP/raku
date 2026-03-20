const TAX_RATES = {
    standard: 0.1,
    reduced: 0.08,
    exempt: 0,
};

export const normalizeEstimateNumber = (value, fallback = 0) => {
    if (typeof value === 'number') {
        return Number.isFinite(value) ? value : fallback;
    }
    if (typeof value === 'string' && value.trim() !== '') {
        const parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : fallback;
    }
    return fallback;
};

export const calculateLineAmount = (item = {}) => (
    normalizeEstimateNumber(item.qty, 0) * normalizeEstimateNumber(item.price, 0)
);

export const calculateLineCostAmount = (item = {}) => (
    normalizeEstimateNumber(item.qty, 0) * normalizeEstimateNumber(item.cost, 0)
);

export const calculateLineGrossProfit = (item = {}) => (
    calculateLineAmount(item) - calculateLineCostAmount(item)
);

export const calculateLineGrossMargin = (item = {}) => {
    const amount = calculateLineAmount(item);

    return amount !== 0 ? (calculateLineGrossProfit(item) / amount) * 100 : 0;
};

export const calculateEstimateSubtotal = (items = []) => (
    items.reduce((acc, item) => acc + calculateLineAmount(item), 0)
);

export const calculateEstimateTax = (items = []) => (
    items.reduce((acc, item) => {
        const amount = calculateLineAmount(item);
        const rate = TAX_RATES[item?.tax_category || 'standard'] || 0;

        return acc + (amount * rate);
    }, 0)
);

export const calculateEstimateTotal = (items = []) => (
    calculateEstimateSubtotal(items) + calculateEstimateTax(items)
);

export const calculateEstimateTotals = (items = []) => {
    const subtotal = calculateEstimateSubtotal(items);
    const totalCost = items.reduce((acc, item) => acc + calculateLineCostAmount(item), 0);
    const totalGrossProfit = subtotal - totalCost;
    const totalGrossMargin = subtotal !== 0 ? (totalGrossProfit / subtotal) * 100 : 0;
    const tax = calculateEstimateTax(items);
    const total = subtotal + tax;

    return {
        subtotal,
        totalCost,
        totalGrossProfit,
        totalGrossMargin,
        tax,
        total,
    };
};
