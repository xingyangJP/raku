import test from 'node:test';
import assert from 'node:assert/strict';
import { buildLegacyDefaultAssigneesForItem, resolveInitialEstimateStaff } from '../../resources/js/lib/estimateAssignmentFallback.js';

test('既存見積のトップレベル担当者を初期スタッフとして解決できる', () => {
    assert.deepEqual(resolveInitialEstimateStaff({
        staff_id: 9,
        staff_name: '川口大希',
    }), {
        id: 9,
        name: '川口大希',
    });
});

test('第1種以外の明細は単位に関係なくトップレベル担当者を100%按分で補完できる', () => {
    assert.deepEqual(buildLegacyDefaultAssigneesForItem({
        qty: 2,
        unit: '式',
        business_division: 'system_development',
    }, {
        id: 9,
        name: '川口大希',
    }), [
        {
            user_id: '9',
            user_name: '川口大希',
            share_percent: '100',
        },
    ]);
});

test('販売系明細はトップレベル担当者を自動補完しない', () => {
    assert.deepEqual(buildLegacyDefaultAssigneesForItem({
        qty: 1,
        unit: '式',
        business_division: 'first_business',
    }, {
        id: 9,
        name: '川口大希',
    }), []);
});
