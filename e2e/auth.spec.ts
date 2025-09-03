// e2e/auth.spec.ts
import { test, expect } from '@playwright/test';

test('login successfully', async ({ page }) => {
  await page.goto('http://localhost:8000/login');
  await page.locator('#external_user_id').selectOption('3'); // Select '守部幸洋'
  await page.getByRole('textbox', { name: 'パスワード' }).click();
  await page.getByRole('textbox', { name: 'パスワード' }).fill('00000000');
  await page.getByRole('checkbox', { name: 'ログイン状態を保持する' }).check();
  await page.getByRole('button', { name: 'ログイン' }).click();
  await expect(page).toHaveURL(/\/dashboard\/?$/);
  await expect(page.getByRole('heading', { name: 'ダッシュボード' })).toBeVisible();
});