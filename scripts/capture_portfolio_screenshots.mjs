import { chromium } from 'playwright';
import { mkdir } from 'node:fs/promises';

const baseUrl = process.env.ONBOARDING_PREVIEW_URL || 'http://127.0.0.1:8081';
await mkdir('screenshots', { recursive: true });

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({
  viewport: { width: 1728, height: 900 },
  deviceScaleFactor: 1,
  colorScheme: 'dark',
});
const page = await context.newPage();

await page.goto(`${baseUrl}/preview/access.html`, { waitUntil: 'networkidle' });
await page.fill('#pw', 'demo123');
await page.screenshot({ path: 'screenshots/onboarding-access.png', fullPage: true });

await page.goto(`${baseUrl}/preview/dark.html`, { waitUntil: 'networkidle' });
await page.screenshot({ path: 'screenshots/onboarding-device-selection.png', fullPage: true });

await page.evaluate(() => {
  const style = document.createElement('style');
  style.textContent = `
    .portfolio-tour-backdrop{position:fixed;inset:0;background:rgba(3,9,22,.72);z-index:800}
    .portfolio-tour-target{position:fixed;left:31.5%;top:238px;width:53%;height:112px;border:3px solid #67a2ff;border-radius:18px;box-shadow:0 0 0 5px rgba(49,118,214,.24);z-index:802}
    .portfolio-tour-card{position:fixed;left:9%;top:200px;width:385px;padding:22px;background:#202838;border:1px solid #647086;border-radius:14px;box-shadow:0 24px 70px rgba(0,0,0,.38);z-index:803;color:#f7f8fb}
    .portfolio-tour-card h2{margin:0 0 10px;font-size:22px}.portfolio-tour-card p{margin:0;color:#cbd2df;line-height:1.55}
    .portfolio-tour-actions{display:flex;align-items:center;justify-content:space-between;margin-top:24px}.portfolio-tour-actions button{border:0;border-radius:999px;padding:10px 18px;background:#3977d6;color:white}.portfolio-tour-actions .skip{padding:0;background:transparent;color:#5d9cff}.portfolio-tour-count{font-weight:700;color:#d9deea}
  `;
  document.head.appendChild(style);
  document.body.insertAdjacentHTML('beforeend', `
    <div class="portfolio-tour-backdrop"></div>
    <div class="portfolio-tour-target"></div>
    <section class="portfolio-tour-card" aria-label="Guided tutorial preview">
      <h2>Choose a flow</h2>
      <p>Device and guest choices create different onboarding paths.</p>
      <div class="portfolio-tour-actions"><button class="skip">Skip</button><span class="portfolio-tour-count">1 / 4</span><button>Next</button></div>
    </section>
  `);
});
await page.screenshot({ path: 'screenshots/onboarding-tutorial.png', fullPage: true });

await page.goto(`${baseUrl}/preview/dark.html`, { waitUntil: 'networkidle' });
await page.evaluate(() => {
  const sidebar = document.querySelector('.ieod-sidebar__list');
  sidebar.innerHTML = `
    <li><a class="is-current" href="#">Password</a></li>
    <li><a href="#">Two-factor authentication</a></li>
    <li><a href="#">VPN</a></li>
    <li><a href="#">Email signature</a></li>
    <li><a href="#">Company Portal</a></li>
    <li><a href="#">Toolbox</a></li>
    <li><a href="#">IT policy</a></li>
    <li><a href="#">Phishing awareness</a></li>
    <li><a href="#">IT contact</a></li>`;
  const content = document.querySelector('.ieod-content');
  content.innerHTML = `
    <div class="ieod-page-heading"><div><h1>Change your temporary password</h1><p>Create a strong personal password before using company services.</p></div><button class="ieod-icon-btn">Demo scope</button></div>
    <section class="ieod-scope-panel"><strong>Demo note:</strong> Screens, links and account actions are fictionalized. The original production workflow connected employees to approved internal systems.</section>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin:16px 0"><button class="ieod-btn ieod-btn--primary">Open the secure password-change portal</button><button class="ieod-btn">Create a compliant password</button><button class="ieod-btn">Reconnect your apps</button></div>
    <section style="border:1px solid #39465a;border-left:4px solid #3977d6;border-radius:14px;padding:18px;background:#202735">
      <h2 style="margin-top:0">Open the secure password-change portal</h2>
      <p><strong>Goal:</strong> Navigate to the fictional account portal from a trusted browser.</p>
      <ol style="line-height:1.9"><li>Open the Demo Identity Portal.</li><li>Sign in with the temporary credentials from your welcome letter.</li><li>Confirm that the displayed account belongs to you.</li></ol>
      <aside style="margin:16px 0;padding:14px;border:1px solid #88680e;border-radius:12px;background:#403821"><strong>Tip</strong><br>Never reuse a private password for a work account.</aside>
      <div style="height:260px;border:1px solid #39465a;border-radius:14px;background:#151b27;display:grid;place-items:center;overflow:hidden">
        <div style="width:68%;border:1px solid #506079;border-radius:18px;background:#202735;box-shadow:0 20px 50px rgba(0,0,0,.3)"><div style="padding:18px;background:#1b2b49;border-radius:18px 18px 0 0"><span style="color:#ffb400;font-size:22px">● ● ●</span></div><div style="padding:30px"><div style="width:34%;height:18px;border-radius:999px;background:#3977d6"></div><div style="height:14px;margin-top:22px;border-radius:999px;background:#58606d"></div><div style="width:72%;height:14px;margin-top:16px;border-radius:999px;background:#58606d"></div></div></div>
      </div>
    </section>`;
});
await page.screenshot({ path: 'screenshots/onboarding-password-step.png', fullPage: true });

await browser.close();
