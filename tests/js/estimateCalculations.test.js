import test from 'node:test';
import assert from 'node:assert/strict';
import {
    calculateEstimateSubtotal,
    calculateEstimateTax,
    calculateEstimateTotal,
    calculateEstimateTotals,
    calculateLineAmount,
    calculateLineCostAmount,
    calculateLineGrossMargin,
    calculateLineGrossProfit,
} from '../../resources/js/lib/estimateCalculations.js';

test('明細の金額・原価・粗利・粗利率を計算できる', () => {
    const item = {
        qty: '2',
        price: '50000',
        cost: '18000',
    };

    assert.equal(calculateLineAmount(item), 100000);
    assert.equal(calculateLineCostAmount(item), 36000);
    assert.equal(calculateLineGrossProfit(item), 64000);
    assert.equal(calculateLineGrossMargin(item), 64);
});

test('標準・軽減・非課税を混在させた税額と合計を計算できる', () => {
    const items = [
        { qty: 1, price: 100000, cost: 50000, tax_category: 'standard' },
        { qty: 2, price: 10000, cost: 3000, tax_category: 'reduced' },
        { qty: 1, price: 5000, cost: 1000, tax_category: 'exempt' },
    ];

    assert.equal(calculateEstimateSubtotal(items), 125000);
    assert.equal(calculateEstimateTax(items), 11600);
    assert.equal(calculateEstimateTotal(items), 136600);
});

test('見積全体の小計・原価・粗利・税・合計をまとめて返す', () => {
    const items = [
        { qty: 10, price: 40000, cost: 15000, tax_category: 'standard' },
        { qty: 3, price: 30000, cost: 12000, tax_category: 'standard' },
    ];

    assert.deepEqual(calculateEstimateTotals(items), {
        subtotal: 490000,
        totalCost: 186000,
        totalGrossProfit: 304000,
        totalGrossMargin: 62.04081632653061,
        tax: 49000,
        total: 539000,
    });
});
