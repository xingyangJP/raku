import { chromium, FullConfig } from '@playwright/test';
import fs from 'fs';
import path from 'path';

// Utility: simple wait/poll for a URL to respond
async function waitForUrl(url: string, timeout = 30000, interval = 1000) {
  const start = Date.now();
  // use global fetch available in Node 18+. Try until timeout.
  while (Date.now() - start < timeout) {
    try {
      const res = await fetch(url, { method: 'GET' });
      if (res.ok) return true;
    } catch (e) {
      // ignore and retry
    }
    await new Promise((r) => setTimeout(r, interval));
  }
  return false;
}

// This script runs before the test suite and saves an authenticated storageState.
// It uses a seeded test user. Adjust EMAIL and PASSWORD if necessary.

export default async function globalSetup(config: FullConfig) {
  // compute storage directory relative to repo root to avoid import.meta issues
  const storageDir = path.resolve(process.cwd(), 'e2e', '.auth');
  const storagePath = path.join(storageDir, 'storageState.json');

  if (!fs.existsSync(storageDir)) fs.mkdirSync(storageDir, { recursive: true });

  // If storage state already exists, skip login to save time.
  if (fs.existsSync(storagePath)) {
    console.log('Using existing storageState.json');
    return;
  }

  const browser = await chromium.launch();
  const page = await browser.newPage();

  const baseURL = (config.projects && config.projects[0] && config.projects[0].use && (config.projects[0].use as any).baseURL) || process.env.BASE_URL || 'http://localhost:8000';

  // Wait for server to respond before attempting to open login page
  const up = await waitForUrl(baseURL, 30000, 1000);
  if (!up) {
    console.error(`Global-setup: server not responding at ${baseURL} after timeout`);
  }

  // Use seeded test account from seeder (password likely '00000000')
  const EMAIL = process.env.PW_TEST_EMAIL || 'a-yuhara@k-cs.co.jp';
  const PASSWORD = process.env.PW_TEST_PASSWORD || '00000000';

  try {
    const loginUrl = `${baseURL}/login`;

    // Fetch the login page to obtain CSRF token
    await page.goto(loginUrl, { waitUntil: 'networkidle', timeout: 30000 });
    // Try to get CSRF token from hidden input or cookie
    let csrf = await page.locator('input[name="_token"]').getAttribute('value').catch(() => null);
    if (!csrf) {
      // try to read XSRF-TOKEN cookie and decode
      const cookies = await page.context().cookies();
      const xsrf = cookies.find(c => c.name === 'XSRF-TOKEN');
      if (xsrf && xsrf.value) {
        try {
          csrf = decodeURIComponent(xsrf.value);
        } catch (e) {
          csrf = xsrf.value;
        }
      }
    }

    // Perform login using in-page fetch so cookies (XSRF) are sent automatically
    const loginResult: any = await page.evaluate(async (args) => {
      const { loginUrl: L, email, password, csrfToken } = args as any;
      try {
        const form = new URLSearchParams();
        form.append('email', email);
        form.append('password', password);
        if (csrfToken) form.append('_token', csrfToken);

        const res = await fetch(L, {
          method: 'POST',
          body: form,
          headers: csrfToken ? { 'X-XSRF-TOKEN': csrfToken, 'Content-Type': 'application/x-www-form-urlencoded' } : { 'Content-Type': 'application/x-www-form-urlencoded' },
          credentials: 'same-origin'
        });
        const text = await res.text();
        return { status: res.status, redirected: res.redirected, url: res.url, snippet: text.slice(0, 200) };
      } catch (e) {
        return { error: String(e) };
      }
    }, { loginUrl, email: EMAIL, password: PASSWORD, csrfToken: csrf });

    if (loginResult && (loginResult as any).error) {
      throw new Error('global-setup: in-page login failed: ' + (loginResult as any).error);
    }

    // After in-page fetch, read cookies from the browser context and save storage state
    const contextCookies = await page.context().cookies();
    const hasSession = contextCookies.some(c => c.name === 'laravel-session');
    if (hasSession) {
      if (!fs.existsSync(storageDir)) fs.mkdirSync(storageDir, { recursive: true });
      await page.context().storageState({ path: storagePath });
      console.log('Saved storageState to', storagePath, 'cookies:', contextCookies.map(c=>c.name));
    } else {
      console.warn('global-setup: laravel-session not found after in-page login; cookies:', contextCookies);
      throw new Error('global-setup: laravel-session not present after login');
    }
  } catch (err) {
    console.error('global-setup failed to create storage state:', err);
    try { await page.screenshot({ path: path.join(storageDir, 'global-setup-error.png') }); } catch (e) { /* ignore */ }
    // As a fallback create an empty storageState file so tests don't fail with ENOENT.
    try {
      if (!fs.existsSync(storageDir)) fs.mkdirSync(storageDir, { recursive: true });
      fs.writeFileSync(storagePath, JSON.stringify({ cookies: [], origins: [] }));
      console.log('Wrote fallback empty storageState to', storagePath);
    } catch (writeErr) {
      console.error('Failed to write fallback storage state:', writeErr);
    }
    if (process.env.CI) throw err;
  } finally {
    await browser.close();
  }
}
