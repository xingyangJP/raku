export function toMonthStartKey(dateString) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(String(dateString || ''))) {
        return null;
    }

    return `${String(dateString).slice(0, 7)}-01`;
}

export function formatMonthLabelFromKey(monthKey) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(String(monthKey || ''))) {
        return '未確定';
    }

    return `${String(monthKey).slice(0, 4)}年${Number(String(monthKey).slice(5, 7))}月`;
}
