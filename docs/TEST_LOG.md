# テストログ（サンプル）

> ステータス: 参考スニペット。正式なE2Eシナリオは `tests/` 配下を優先。

```ts
import { test, expect } from '@playwright/test';

test('test', async ({ page }) => {
  await page.goto('http://localhost:8000/login');
  await page.getByLabel('ユーザー').selectOption('3');
  await page.getByRole('textbox', { name: 'パスワード' }).click();
  await page.getByRole('textbox', { name: 'パスワード' }).press('Eisu');
  await page.getByRole('textbox', { name: 'パスワード' }).fill('00000000');
  await page.locator('form').click();
  await page.getByRole('button', { name: 'ログイン' }).click();
  await page.getByRole('link', { name: '見積管理' }).click();
  await page.getByRole('row', { name: 'EST-4-CRM-111-251409-001' }).getByRole('button').click();
  await page.getByRole('menuitem', { name: 'PDF表示' }).click();
});
```
