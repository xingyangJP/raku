// e2e/debug-global-setup.js
import { chromium } from '@playwright/test';
import fs from 'fs';
import path from 'path';

async function globalSetup(config) {
  const { baseURL, storageState } = config.projects[0].use;
  const browser = await chromium.launch();
  const page = await browser.newPage();
  try {
    await page.goto(baseURL);
    await page.getByLabel('Email').fill('admin@example.com');
    await page.getByLabel('Password').fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    // Save signed-in state to storageState path from config.
    await page.context().storageState({ path: storageState });
  } catch (error) {
    console.error('Global setup failed:', error);
    // Save screenshot and response body for debugging
    const screenshotPath = path.resolve(path.dirname(storageState), 'global-setup-error.png');
    const responseBodyPath = path.resolve(path.dirname(storageState), 'global-setup-response-body.html');
    await page.screenshot({ path: screenshotPath });
    const responseBody = await page.content();
    fs.writeFileSync(responseBodyPath, responseBody);
    throw error;
  } finally {
    await browser.close();
  }
}

export default globalSetup;