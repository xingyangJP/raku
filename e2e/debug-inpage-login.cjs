const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

(async () => {
  const baseURL = process.env.BASE_URL || 'http://localhost:8000';
  const EMAIL = process.env.PW_TEST_EMAIL || 'yuki@xerographix.co.jp';
  const PASSWORD = process.env.PW_TEST_PASSWORD || '00000000';
  const storageDir = path.resolve(process.cwd(), 'e2e', '.auth');
  const storagePath = path.join(storageDir, 'storageState.json');
  if (!fs.existsSync(storageDir)) fs.mkdirSync(storageDir, { recursive: true });

  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  try {
    console.log('goto', baseURL + '/login');
    await page.goto(baseURL + '/login', { waitUntil: 'networkidle', timeout: 30000 });

    let csrf = null;
    try { csrf = await page.locator('input[name="_token"]').getAttribute('value'); } catch (e) {}
    console.log('csrf from input:', csrf);

    const cookiesBefore = await page.context().cookies();
    console.log('cookies before:', cookiesBefore.map(c=>({name:c.name, value:c.value, httpOnly:c.httpOnly})));

    // perform in-page fetch to POST credentials
    const result = await page.evaluate(async (args) => {
      const { loginUrl, email, password, csrfToken } = args;
      try {
        const form = new URLSearchParams();
        form.append('email', email);
        form.append('password', password);
        if (csrfToken) form.append('_token', csrfToken);
        const res = await fetch(loginUrl, {
          method: 'POST',
          body: form,
          headers: csrfToken ? { 'X-XSRF-TOKEN': csrfToken, 'Content-Type': 'application/x-www-form-urlencoded' } : { 'Content-Type': 'application/x-www-form-urlencoded' },
          credentials: 'same-origin'
        });
        const text = await res.text();
        return { status: res.status, redirected: res.redirected, url: res.url, snippet: text.slice(0,200) };
      } catch (e) {
        return { error: String(e) };
      }
    }, { loginUrl: baseURL + '/login', email: EMAIL, password: PASSWORD, csrfToken: csrf });

    console.log('in-page login result:', result);

    const cookiesAfter = await page.context().cookies();
    console.log('cookies after:', cookiesAfter.map(c=>({name:c.name, value:c.value, httpOnly:c.httpOnly})));

    await page.context().storageState({ path: storagePath });
    console.log('wrote storageState to', storagePath);
  } catch (e) {
    console.error('error in debug-inpage script', e);
  } finally {
    await browser.close();
  }
})();
