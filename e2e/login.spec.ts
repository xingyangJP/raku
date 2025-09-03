import { test, expect } from '@playwright/test';

test('test', async ({ page }) => {
  await page.goto('http://localhost:8000/login');
  await page.getByLabel('ユーザー').selectOption('3');
  await page.getByRole('textbox', { name: 'パスワード' }).click();
  await page.getByRole('textbox', { name: 'パスワード' }).fill('00000000');
  await page.getByRole('checkbox', { name: 'ログイン状態を保持する' }).check();
  await page.getByRole('button', { name: 'ログイン' }).click();
  await page.getByRole('link', { name: '見積管理' }).click();
  await page.getByRole('button', { name: '新規見積' }).click();
  await page.getByText('顧客を選択').click();
  await page.getByRole('option', { name: 'ゆうしんグループ' }).click();
  await page.getByText('担当者を選択').click();
  await page.getByRole('option', { name: '古閑信広' }).click();
  await page.getByRole('textbox', { name: '件名' }).click();

  await page.getByRole('textbox', { name: '件名' }).fill('システムリニューアル');
  await page.getByRole('textbox', { name: '件名' }).press('Enter');
  await page.getByRole('textbox', { name: '件名' }).fill('システムリニューアル０１');
  await page.getByRole('textbox', { name: '件名' }).press('Enter');
  await page.getByRole('textbox', { name: '件名' }).fill('システムリニューアル０１');
  await page.getByRole('textbox', { name: '発行日' }).fill('2025-09-01');
  await page.getByRole('textbox', { name: '有効期間' }).fill('2025-10-04');
  await page.getByRole('textbox', { name: '納入場所' }).click();
 
  await page.getByRole('textbox', { name: '納入場所' }).fill('お客様指定');
  await page.getByRole('textbox', { name: '納入場所' }).press('Enter');
  await page.getByRole('textbox', { name: '納入場所' }).fill('お客様指定');
  await page.getByRole('textbox', { name: '納入場所' }).press('Tab');
  await page.getByRole('textbox', { name: '備考（対外）' }).fill('備考を');

  await page.getByRole('textbox', { name: '備考（対外）' }).fill('備考をAI');

  await page.getByRole('textbox', { name: '備考（対外）' }).fill('備考をAIでそのうち生成する');
  await page.getByRole('textbox', { name: '備考（社内メモ）' }).click();

  await page.getByRole('textbox', { name: '備考（社内メモ）' }).fill('値引きの背景などを書く');
  await page.goto('http://localhost:8000/estimates/create');
  await page.getByRole('button', { name: '行を追加' }).click();
  await page.getByRole('combobox').filter({ hasText: '品目を選択' }).click();
  await page.getByLabel('インフラ構築').getByText('インフラ構築').click();
  await page.getByRole('textbox', { name: '詳細（項目）' }).click();

  await page.getByRole('textbox', { name: '詳細（項目）' }).fill('インフラ構築の詳細');
  await page.goto('http://localhost:8000/estimates/create');
  await page.getByRole('button', { name: '行を追加' }).click();
  await page.getByRole('combobox').filter({ hasText: '品目を選択' }).click();
  await page.getByLabel('基本設計').getByText('基本設計').click();
  await page.getByRole('row', { name: '1 式 150000 150,000 80000 80,' }).getByPlaceholder('詳細（項目）').click();
  await page.getByRole('row', { name: '1 式 150000 150,000 80000 80,' }).getByPlaceholder('詳細（項目）').fill('基本設計の詳細');
  await page.goto('http://localhost:8000/estimates/create');
  await page.getByRole('button', { name: '承認申請' }).click();
  await page.getByRole('button', { name: '承認者を追加…' }).click();
  await page.getByRole('option', { name: '川口大希' }).click();
  await page.getByRole('button', { name: '承認者を追加…' }).click();
  await page.getByRole('option', { name: '守部幸洋' }).click();
  await page.getByRole('button', { name: '申請する' }).click();
  await page.getByRole('button', { name: 'Close' }).click();
  await page.getByRole('link', { name: 'ダッシュボード' }).click();
  await page.getByRole('radio', { name: '自分のみ' }).click();
  await page.goto('http://localhost:8000/dashboard');
  await page.getByRole('radio', { name: '全て' }).click();
  await page.getByRole('radio', { name: '自分のみ' }).click();
});