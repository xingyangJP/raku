const toNumber = (value, fallback = 0) => {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : fallback;
};

export const resolveInitialEstimateStaff = (estimate) => {
    const staffName = String(estimate?.staff_name ?? '').trim();
    const staffId = estimate?.staff_id ?? null;

    if ((staffId === null || staffId === undefined || staffId === '') && staffName === '') {
        return null;
    }

    return {
        id: staffId ?? null,
        name: staffName,
    };
};

export const buildLegacyDefaultAssigneesForItem = (item, fallbackStaff) => {
    const userName = String(fallbackStaff?.name ?? fallbackStaff?.user_name ?? '').trim();
    const userId = fallbackStaff?.id ?? fallbackStaff?.user_id ?? null;

    if ((userId === null || userId === undefined || userId === '') && userName === '') {
        return [];
    }

    const division = item?.business_division ?? null;
    if (division === 'first_business') {
        return [];
    }

    const qty = toNumber(item?.qty ?? item?.quantity, 0);
    if (qty <= 0) {
        return [];
    }

    return [{
        user_id: userId != null && userId !== '' ? String(userId) : null,
        user_name: userName,
        share_percent: '100',
    }];
};
