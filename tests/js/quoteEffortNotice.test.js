import test from 'node:test';
import assert from 'node:assert/strict';
import { resolveEstimateAssignmentStatus } from '../../resources/js/lib/quoteEffortNotice.js';

const baseOptions = {
    defaultCapacityPerPersonDays: 20,
    personHoursPerPersonDay: 8,
    resolveProduct: () => null,
};

test('トップレベル担当者だけある既存見積は按分未設定として判定できる', () => {
    const status = resolveEstimateAssignmentStatus({
        staff_id: 9,
        staff_name: '川口大希',
        items: [
            {
                qty: 2,
                unit: '人日',
                assignees: [],
            },
        ],
    }, baseOptions);

    assert.deepEqual(status, {
        key: 'legacy_top_level_only',
        label: '按分未設定',
    });
});

test('担当者も按分もない工数明細は担当未割当として判定できる', () => {
    const status = resolveEstimateAssignmentStatus({
        staff_id: null,
        staff_name: '',
        items: [
            {
                qty: 1,
                unit: '人月',
                assignees: [],
            },
        ],
    }, baseOptions);

    assert.deepEqual(status, {
        key: 'unassigned',
        label: '担当未割当',
    });
});

test('第1種以外の式表示明細も担当未割当として判定できる', () => {
    const status = resolveEstimateAssignmentStatus({
        staff_id: null,
        staff_name: '',
        items: [
            {
                qty: 1,
                unit: '式',
                business_division: 'fifth_business',
                assignees: [],
            },
        ],
    }, baseOptions);

    assert.deepEqual(status, {
        key: 'unassigned',
        label: '担当未割当',
    });
});

test('按分済みの工数明細は未割当扱いしない', () => {
    const status = resolveEstimateAssignmentStatus({
        staff_id: 9,
        staff_name: '川口大希',
        items: [
            {
                qty: 1,
                unit: '人日',
                assignees: [
                    {
                        user_id: '9',
                        user_name: '川口大希',
                        share_percent: '100',
                    },
                ],
            },
        ],
    }, baseOptions);

    assert.equal(status, null);
});
