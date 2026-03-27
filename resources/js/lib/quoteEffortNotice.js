const FIRST_BUSINESS_KEY = 'first_business';

const toNumber = (value, fallback = 0) => {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : fallback;
};

const getQuantity = (item) => {
    const qty = item?.qty ?? item?.quantity ?? 0;
    return toNumber(qty, 0);
};

export const normalizeQuoteAssignees = (assignees = []) => {
    if (!Array.isArray(assignees)) {
        return [];
    }

    return assignees
        .map((assignee) => ({
            user_id: assignee?.user_id != null && assignee?.user_id !== '' ? String(assignee.user_id) : null,
            user_name: String(assignee?.user_name ?? assignee?.name ?? '').trim(),
            share_percent: assignee?.share_percent === null || assignee?.share_percent === undefined || assignee?.share_percent === ''
                ? null
                : toNumber(assignee.share_percent, 0),
        }))
        .filter((assignee) => assignee.user_id || assignee.user_name);
};

export const calculateQuoteItemEffortPersonDays = (
    item,
    {
        defaultCapacityPerPersonDays = 20,
        personHoursPerPersonDay = 8,
        resolveProduct = () => null,
    } = {},
) => {
    const product = resolveProduct(item);
    const division = item?.business_division ?? product?.business_division ?? null;
    if (division === FIRST_BUSINESS_KEY) {
        return 0;
    }

    const qty = getQuantity(item);
    if (qty <= 0) {
        return 0;
    }

    const unit = String(item?.unit ?? product?.unit ?? '').trim().toLowerCase();
    if (unit === '' || unit.includes('人日')) {
        return qty;
    }
    if (unit.includes('人月')) {
        return qty * defaultCapacityPerPersonDays;
    }
    if (unit.includes('人時') || unit.includes('時間') || unit === 'h' || unit === 'hr') {
        return personHoursPerPersonDay > 0 ? qty / personHoursPerPersonDay : 0;
    }

    return 0;
};

export const requiresQuoteItemAssignment = (
    item,
    {
        resolveProduct = () => null,
    } = {},
) => {
    const product = resolveProduct(item);
    const division = item?.business_division ?? product?.business_division ?? null;

    return division !== FIRST_BUSINESS_KEY;
};

export const resolveEstimateAssignmentStatus = (
    estimate,
    {
        defaultCapacityPerPersonDays = 20,
        personHoursPerPersonDay = 8,
        resolveProduct = () => null,
    } = {},
) => {
    const items = Array.isArray(estimate?.items) ? estimate.items : [];
    const hasTopLevelStaff = Boolean(estimate?.staff_id || String(estimate?.staff_name ?? '').trim());

    for (const item of items) {
        if (!requiresQuoteItemAssignment(item, {
            resolveProduct,
        })) {
            continue;
        }

        const assignees = normalizeQuoteAssignees(item.assignees);
        if (assignees.length === 0) {
            return hasTopLevelStaff
                ? { key: 'legacy_top_level_only', label: '按分未設定' }
                : { key: 'unassigned', label: '担当未割当' };
        }

        const totalShare = assignees.reduce((sum, assignee) => sum + toNumber(assignee.share_percent, 0), 0);
        if (totalShare <= 0) {
            return { key: 'share_missing', label: '按分未設定' };
        }
    }

    return null;
};
