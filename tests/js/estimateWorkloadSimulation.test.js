import test from 'node:test';
import assert from 'node:assert/strict';
import { formatMonthLabelFromKey, toMonthStartKey } from '../../resources/js/lib/estimateWorkloadSimulation.js';

test('対象日付を月初キーへ正規化できる', () => {
    assert.equal(toMonthStartKey('2026-04-20'), '2026-04-01');
    assert.equal(toMonthStartKey('2026-04-01'), '2026-04-01');
    assert.equal(toMonthStartKey('2026-04'), null);
});

test('月初キーから表示ラベルを作れる', () => {
    assert.equal(formatMonthLabelFromKey('2026-04-01'), '2026年4月');
    assert.equal(formatMonthLabelFromKey(null), '未確定');
});
