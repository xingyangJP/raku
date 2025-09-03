// e2e/auth.spec.ts
import { test, expect } from '@playwright/test';

test.describe('Authentication and Dashboard', () => {
  test.use({ storageState: 'auth.json' });

  test('should be able to access the dashboard after login', async ({ page }) => {
    // ログイン後の認証状態を使用しているため、直接ダッシュボードにアクセス
    await page.goto('/dashboard');
    
    // ダッシュボードに正しく遷移したことをURLで確認
    await expect(page).toHaveURL(/.*\/dashboard/);
    
    // ダッシュボードの主要な見出しが表示されていることを確認
    await expect(page.getByRole('heading', { name: 'ダッシュボード' })).toBeVisible();
  });
});