// playwright.config.ts
import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './e2e',
  retries: 0,
  use: {
    baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:8000',
    // storageState: 'e2e/.auth/storageState.json', // ここが重要
    // 他：viewport, trace など
  },
  globalSetup: './e2e/global-setup.ts',
});