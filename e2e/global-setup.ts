import { chromium, FullConfig } from '@playwright/test';

const authFile = 'auth.json';

async function globalSetup(config: FullConfig) {
  const { baseURL } = config.projects[0].use;
  const browser = await chromium.launch();
  const page = await browser.newPage();

  try {
    await page.goto(`${baseURL}/login`);
    await page.locator('#external_user_id').selectOption('3'); // Select '守部幸洋'
    await page.getByRole('textbox', { name: 'パスワード' }).fill('00000000');
    await page.getByRole('checkbox', { name: 'ログイン状態を保持する' }).check();
    await page.getByRole('button', { name: 'ログイン' }).click();
    await page.waitForURL(/\/dashboard\/?$/);
    await page.context().storageState({ path: authFile });
  } finally {
    await browser.close();
  }
}

export default globalSetup;