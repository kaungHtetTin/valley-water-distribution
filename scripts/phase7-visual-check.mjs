import { spawn } from 'node:child_process';
import { mkdir, rm, writeFile } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';

const baseUrl = 'http://localhost/valley/public';
const edgePath = 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
const profilePath = path.join(os.tmpdir(), `valley-phase7-${Date.now()}`);
const artifactPath = path.resolve('artifacts');
const onlyPhase10 = process.argv.includes('--phase10');
const onlyPhase11 = process.argv.includes('--phase11');

async function login(email, app) {
    const cookies = new Map();
    const collect = (response) => response.headers.getSetCookie().forEach((value) => {
        const pair = value.split(';', 1)[0]; const separator = pair.indexOf('='); cookies.set(pair.slice(0, separator), pair.slice(separator + 1));
    });
    const header = () => [...cookies].map(([name, value]) => `${name}=${value}`).join('; ');
    const page = await fetch(`${baseUrl}/${app}`); collect(page);
    const csrf = (await page.text()).match(/meta name="csrf-token" content="([^"]+)"/)?.[1];
    const response = await fetch(`${baseUrl}/api/auth/login`, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Cookie: header() }, body: JSON.stringify({ email, password: 'password', app }) });
    collect(response); if (!response.ok) throw new Error(`Login failed for ${app}: ${response.status}`); return header();
}

async function waitForEdge() {
    for (let attempt = 0; attempt < 30; attempt += 1) { try { if ((await fetch('http://127.0.0.1:9222/json/version')).ok) return; } catch {} await new Promise((resolve) => setTimeout(resolve, 250)); }
    throw new Error('Edge debugging endpoint did not start.');
}

async function connect() {
    const target = await (await fetch('http://127.0.0.1:9222/json/new?about:blank', { method: 'PUT' })).json();
    const socket = new WebSocket(target.webSocketDebuggerUrl); await new Promise((resolve, reject) => { socket.onopen = resolve; socket.onerror = reject; });
    let id = 0; const pending = new Map();
    socket.onmessage = ({ data }) => { const response = JSON.parse(data); if (!response.id || !pending.has(response.id)) return; const call = pending.get(response.id); pending.delete(response.id); response.error ? call.reject(response.error) : call.resolve(response.result); };
    const send = (method, params = {}) => new Promise((resolve, reject) => { const callId = ++id; pending.set(callId, { resolve, reject }); socket.send(JSON.stringify({ id: callId, method, params })); });
    return { socket, send };
}

async function setCookies(send, header) {
    await send('Network.clearBrowserCookies');
    for (const part of header.split(';')) { const separator = part.indexOf('='); if (separator > 0) await send('Network.setCookie', { name: part.slice(0, separator).trim(), value: part.slice(separator + 1).trim(), url: baseUrl }); }
}

async function capture(send, route, width, height, name, cookies, locale = 'en', theme = 'light', density = 'compact') {
    await setCookies(send, cookies); await send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: width < 500 }); await send('Page.navigate', { url: `${baseUrl}${route}` }); await new Promise((resolve) => setTimeout(resolve, 2200));
    await send('Runtime.evaluate', { expression: `window.localStorage.setItem('valley-locale', '${locale}'); window.localStorage.setItem('valley-theme', '${theme}'); window.localStorage.setItem('valley-density', '${density}')` }); await send('Page.reload'); await new Promise((resolve) => setTimeout(resolve, 1400));
    const result = await send('Runtime.evaluate', { expression: `({ title: document.querySelector('h1')?.innerText, error: document.querySelector('.inline-error')?.innerText, body: document.body.innerText.slice(0, 500) })`, returnByValue: true });
    if (!result.result.value?.title || result.result.value?.error) throw new Error(`${route}: ${JSON.stringify(result.result.value)}`);
    const image = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false }); await writeFile(path.join(artifactPath, name), Buffer.from(image.data, 'base64'));
    console.log(`${route} -> ${result.result.value.title}`);
}

async function capturePrimaryDialog(send, name) {
    const opened = await send('Runtime.evaluate', { expression: `(() => { const button = [...document.querySelectorAll('button')].find((item) => item.innerText.includes('New')); if (!button) return false; button.click(); return true; })()`, returnByValue: true });
    if (!opened.result.value) throw new Error('Primary creation action was not found.');
    await new Promise((resolve) => setTimeout(resolve, 500));
    const dialog = await send('Runtime.evaluate', { expression: `({ visible: !!document.querySelector('.master-dialog'), fields: document.querySelectorAll('.master-dialog input, .master-dialog select, .master-dialog textarea').length })`, returnByValue: true });
    if (!dialog.result.value.visible || dialog.result.value.fields < 3) throw new Error(`Creation dialog failed: ${JSON.stringify(dialog.result.value)}`);
    const image = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false }); await writeFile(path.join(artifactPath, name), Buffer.from(image.data, 'base64'));
}

async function captureAfterClick(send, text, name, expectedSelector) {
    const clicked = await send('Runtime.evaluate', { expression: `(() => { const target = [...document.querySelectorAll('button')].find((item) => item.innerText.includes(${JSON.stringify(text)}) || item.getAttribute('aria-label')?.includes(${JSON.stringify(text)})); if (!target) return false; target.click(); return true; })()`, returnByValue: true });
    if (!clicked.result.value) throw new Error(`Action not found: ${text}`);
    await new Promise((resolve) => setTimeout(resolve, 500));
    const visible = await send('Runtime.evaluate', { expression: `!!document.querySelector(${JSON.stringify(expectedSelector)})`, returnByValue: true });
    if (!visible.result.value) throw new Error(`Expected state not visible after ${text}: ${expectedSelector}`);
    const image = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false }); await writeFile(path.join(artifactPath, name), Buffer.from(image.data, 'base64'));
}

await mkdir(profilePath, { recursive: false }); await mkdir(artifactPath, { recursive: true });
const office = await login('office@valley.test', 'office'); const client = await login('client@valley.test', 'client'); const sales = await login('sales@valley.test', 'sales'); const driver = await login('driver@valley.test', 'driver');
const edge = spawn(edgePath, ['--headless=new', '--disable-gpu', '--remote-debugging-port=9222', `--user-data-dir=${profilePath}`, 'about:blank'], { stdio: 'ignore' });
try {
    await waitForEdge(); const { socket, send } = await connect(); await send('Page.enable'); await send('Network.enable');
    if (!onlyPhase10 && !onlyPhase11) {
    await capture(send, '/office/finance/receivables', 1440, 900, 'phase7-office-receivables.png', office);
    await capture(send, '/office/finance/outdoor-expenses', 1280, 760, 'phase7-office-outdoor-expenses.png', office);
    await capture(send, '/office/finance/profit-loss', 1440, 900, 'phase7-office-profit-loss.png', office);
    await capture(send, '/office/finance/profit-loss', 1280, 720, 'phase7-office-profit-loss-my.png', office, 'my');
    await capture(send, '/client/ledger', 390, 844, 'phase7-client-ledger.png', client);
    await capture(send, '/sales/collections', 430, 932, 'phase7-sales-collections.png', sales);
    await capture(send, '/driver/expenses', 390, 844, 'phase7-driver-expenses.png', driver);
    await capture(send, '/office/vehicle-costs/fuel', 1440, 900, 'phase8-office-fuel.png', office);
    await capturePrimaryDialog(send, 'phase8-office-fuel-form.png');
    await capture(send, '/office/vehicle-reports/route-history', 1280, 720, 'phase8-office-route-history.png', office);
    await capture(send, '/office/vehicle-reports/route-history', 1280, 720, 'phase8-office-route-history-dark.png', office, 'en', 'dark');
    await capture(send, '/office/vehicle-reports/performance', 1440, 900, 'phase8-office-performance.png', office);
    await capture(send, '/office/vehicle-reports/performance', 1024, 768, 'phase8-office-performance-tablet.png', office);
    await capture(send, '/office/vehicle-costs/maintenance', 1440, 900, 'phase8-office-maintenance-comfortable.png', office, 'en', 'light', 'comfortable');
    await capture(send, '/office/vehicle-reports/performance', 1280, 720, 'phase8-office-performance-my.png', office, 'my');
    await capture(send, '/driver/vehicle', 390, 844, 'phase8-driver-vehicle.png', driver);
    await capturePrimaryDialog(send, 'phase8-driver-vehicle-form.png');
    }
    if (!onlyPhase11) {
    await capture(send, '/office', 1440, 900, 'phase10-owner-dashboard.png', office);
    await capture(send, '/office/dashboards/sales', 1280, 760, 'phase10-sales-dashboard-dark.png', office, 'en', 'dark');
    await capture(send, '/office/dashboards/stock', 1024, 768, 'phase10-stock-dashboard-tablet.png', office);
    await capture(send, '/office/dashboards/delivery', 1440, 900, 'phase10-delivery-dashboard.png', office);
    await capture(send, '/office/dashboards/finance', 1440, 900, 'phase10-finance-dashboard-comfortable.png', office, 'en', 'light', 'comfortable');
    await capture(send, '/office', 1280, 760, 'phase10-owner-dashboard-my.png', office, 'my');
    await capture(send, '/client/home', 390, 844, 'phase10-client-home.png', client);
    await capture(send, '/sales/home', 430, 932, 'phase10-sales-home.png', sales);
    await capture(send, '/driver/home', 390, 844, 'phase10-driver-home.png', driver);
    }
    await capture(send, '/office/uat', 1440, 900, 'phase11-uat-readiness.png', office);
    await captureAfterClick(send, 'Record finding', 'phase11-uat-finding-dialog.png', '.uat-dialog');
    await send('Runtime.evaluate', { expression: `document.querySelector('.uat-dialog .icon-button')?.click()` });
    await capture(send, '/office/uat', 1280, 720, 'phase11-uat-dark.png', office, 'en', 'dark');
    await captureAfterClick(send, 'Audit log', 'phase11-uat-audit.png', '.uat-audit-table');
    await capture(send, '/office/uat', 1024, 768, 'phase11-uat-tablet.png', office, 'en', 'light', 'comfortable');
    await capture(send, '/office/uat', 1280, 760, 'phase11-uat-myanmar.png', office, 'my');
    await capture(send, '/client/orders', 390, 844, 'phase11-client-orders.png', client);
    await captureAfterClick(send, 'New order', 'phase11-client-order-simple.png', '.mobile-order-form');
    await send('Network.emulateNetworkConditions', { offline: true, latency: 0, downloadThroughput: 0, uploadThroughput: 0 });
    await send('Runtime.evaluate', { expression: `window.dispatchEvent(new Event('offline'))` });
    await new Promise((resolve) => setTimeout(resolve, 200));
    const offline = await send('Runtime.evaluate', { expression: `({ visible: !!document.querySelector('.offline-banner'), text: document.querySelector('.offline-banner')?.innerText })`, returnByValue: true });
    if (!offline.result.value.visible) throw new Error('Offline status banner did not appear.');
    const offlineImage = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false }); await writeFile(path.join(artifactPath, 'phase11-client-order-offline.png'), Buffer.from(offlineImage.data, 'base64'));
    await send('Network.emulateNetworkConditions', { offline: false, latency: 0, downloadThroughput: -1, uploadThroughput: -1 });
    await capture(send, '/client/orders', 390, 844, 'phase11-client-orders-myanmar.png', client, 'my');
    await captureAfterClick(send, 'အော်ဒါအသစ်', 'phase11-client-order-simple-myanmar.png', '.mobile-order-form');
    await capture(send, '/sales/customers', 430, 932, 'phase11-sales-customers.png', sales);
    await capture(send, '/sales/orders', 430, 932, 'phase11-sales-orders.png', sales);
    await capture(send, '/sales/collections', 390, 844, 'phase11-sales-collections.png', sales);
    await capture(send, '/sales/expenses', 390, 844, 'phase11-sales-expenses.png', sales);
    await capture(send, '/driver/attendance', 390, 844, 'phase11-driver-attendance.png', driver);
    await capture(send, '/driver/route', 390, 844, 'phase11-driver-route.png', driver);
    await capture(send, '/driver/expenses', 390, 844, 'phase11-driver-expenses.png', driver);
    await capture(send, '/attendance/demo-taunggyi-office-attendance-token-2026', 390, 844, 'phase11-public-attendance.png', office, 'my');
    await send('Browser.close').catch(() => {}); socket.close();
} finally {
    if (edge.exitCode === null) edge.kill(); await Promise.race([new Promise((resolve) => edge.once('exit', resolve)), new Promise((resolve) => setTimeout(resolve, 3000))]);
    const resolvedProfile = path.resolve(profilePath); const resolvedTemp = path.resolve(os.tmpdir()); if (!resolvedProfile.startsWith(`${resolvedTemp}${path.sep}`)) throw new Error('Unsafe temporary profile path.');
    await rm(resolvedProfile, { recursive: true, force: true, maxRetries: 5, retryDelay: 250 }).catch((error) => {
        if (error.code !== 'EBUSY') throw error;
    });
}
