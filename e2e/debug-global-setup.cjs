const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

(async () => {
  const baseURL = process.env.BASE_URL || 'http://localhost:8000';
  const EMAIL = process.env.PW_TEST_EMAIL || 'a-yuhara@k-cs.co.jp';
  const PASSWORD = process.env.PW_TEST_PASSWORD || '00000000';
  const storageDir = path.resolve(process.cwd(), 'e2e', '.auth');
  const storagePath = path.join(storageDir, 'storageState.json');
  if (!fs.existsSync(storageDir)) fs.mkdirSync(storageDir, { recursive: true });

  const browser = await chromium.launch();
  const page = await browser.newPage();
  try {
    console.log('goto', baseURL + '/login');
    await page.goto(baseURL + '/login', { waitUntil: 'networkidle', timeout: 30000 });
    let csrf = null;
    try { csrf = await page.locator('input[name="_token"]').getAttribute('value'); } catch (e) {}
    console.log('csrf from input:', csrf);
    const cookiesBefore = await page.context().cookies();
    console.log('cookies before:', cookiesBefore);

    const request = page.context().request;
    const res = await request.post(baseURL + '/login', {
      form: { email: EMAIL, password: PASSWORD, _token: csrf || '' },
      headers: csrf ? { 'X-CSRF-TOKEN': csrf } : {}
    });
    console.log('login status', res && res.status());
    const headers = res ? res.headers() : {};
    console.log('login headers:', headers);
    try {
      const body = await (res ? res.text() : '');
      fs.writeFileSync(path.join(storageDir, 'global-setup-response-body.html'), body);
    } catch (e) { console.warn('failed to write body', e); }

    // Try to parse set-cookie and add to context
    const setCookie = headers['set-cookie'];
    console.log('set-cookie header:', setCookie);
    if (setCookie) {
      const cookies = Array.isArray(setCookie) ? setCookie : [setCookie];
      const toAdd = [];
      for (const header of cookies) {
        const parts = header.split(';').map(s => s.trim());
        const [nameValue, ...attrs] = parts;
        const eq = nameValue.indexOf('=');
        if (eq === -1) continue;
        const name = nameValue.substring(0, eq);
        const value = nameValue.substring(eq + 1);
        const cookie = { name, value, path: '/', url: baseURL };
        for (const a of attrs) {
          const [k, v] = a.split('=');
          const key = k.toLowerCase();
          if (key === 'path') cookie.path = v || '/';
          else if (key === 'domain') cookie.domain = v;
          else if (key === 'expires') cookie.expires = Math.floor(new Date(v).getTime() / 1000);
          else if (key === 'httponly') cookie.httpOnly = true;
          else if (key === 'secure') cookie.secure = true;
        }
        toAdd.push(cookie);
      }
      console.log('parsed cookies to add:', toAdd);
      if (toAdd.length) await page.context().addCookies(toAdd);
    }

    const cookiesAfter = await page.context().cookies();
    console.log('cookies after:', cookiesAfter);
    await page.context().storageState({ path: storagePath });
    console.log('wrote storageState to', storagePath);
  } catch (e) {
    console.error('error in debug script', e);
  } finally {
    await browser.close();
  }
})();
