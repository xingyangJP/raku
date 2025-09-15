import { test, expect } from '@playwright/test';

test('PDF display test', async ({ page }) => {
  await page.goto('/login');
  await page.getByLabel('ユーザー').selectOption('3');
  await page.getByRole('textbox', { name: 'パスワード' }).fill('00000000');
  await page.getByRole('button', { name: 'ログイン' }).click();

  await page.getByRole('link', { name: '見積管理' }).click();
  await expect(page).toHaveURL('/quotes');

  // Promise to catch the PDF response
  const pdfResponsePromise = page.waitForResponse(response => 
    response.url().includes('.pdf') && response.status() === 200
  );

  // Find the row and click the PDF display button
  // The user's log used a name selector, but let's make it more robust
  await page.getByRole('row').filter({ hasText: /EST/ }).first().locator('td:last-child button').click();
  await page.getByRole('menuitem', { name: 'PDF表示' }).click();

  // Wait for the PDF response
  const pdfResponse = await pdfResponsePromise;

  // Assertions
  expect(pdfResponse.ok()).toBeTruthy();
  const contentType = await pdfResponse.headerValue('content-type');
  expect(contentType).toBe('application/pdf');
});
