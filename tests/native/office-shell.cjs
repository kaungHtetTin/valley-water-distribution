// Run with scripts/test-office-shell.mjs after npm run build.
// Uses Electron's Chromium and a local fixture server; no database or live account is touched.
const { app, BrowserWindow, session } = require('electron');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const root = path.resolve(__dirname, '../..');
const build = path.join(root, 'public/build');
const manifest = JSON.parse(fs.readFileSync(path.join(build, 'manifest.json'), 'utf8'));
const source = fs.readFileSync(path.join(root, 'resources/js/App.jsx'), 'utf8');
const permissions = [...new Set([...source.matchAll(/['"](office\.[a-z.-]+)['"]/g)].map((match) => match[1]))];
const user = { id: 1, name: 'Test Owner', email: 'owner@example.test', phone: '', role: 'Owner', locale: 'en', permissions, allowed_apps: ['office'], default_app: 'office', profile_photo_url: null };
const emptySalary = { items: [], meta: { total: 0, current_page: 1, last_page: 1 }, summary: { payments_count: 0, employees_count: 0, total_gross: 0, total_deductions: 0, total_net: 0, latest_paid_at: null } };
const pendingChunks = [];
const pendingPayroll = [];
const unexpectedRequests = [];
let releaseChunks = false;
let releasePayroll = false;
let failPayroll = true;
let win;
let server;
const output = path.join(root, 'artifacts/verification/office-shell');
fs.mkdirSync(output, { recursive: true });
// Isolate the test browser from any installed Office user's cookies and settings.
app.setPath('userData', fs.mkdtempSync(path.join(app.getPath('temp'), 'valley-office-shell-')));
const json = (res, data, status = 200) => { res.writeHead(status, { 'Content-Type': 'application/json' }); res.end(JSON.stringify({ ok: status === 200, data, message: status === 200 ? 'Fixture loaded.' : 'Test connection interruption' })); };

async function serve(req, res) {
    const url = new URL(req.url, 'http://localhost');
    if (url.pathname.startsWith('/build/')) {
        const file = path.resolve(build, url.pathname.slice('/build/'.length));
        if (!file.startsWith(build + path.sep) || !fs.existsSync(file)) { res.writeHead(404); res.end(); return; }
        if (url.pathname === '/build/' + manifest['resources/js/phaseThree.jsx'].file && !releaseChunks) await new Promise((resolve) => pendingChunks.push(resolve));
        if (res.destroyed) return;
        const mime = file.endsWith('.js') ? 'text/javascript' : file.endsWith('.css') ? 'text/css' : 'image/png';
        res.writeHead(200, { 'Content-Type': mime }); fs.createReadStream(file).pipe(res); return;
    }
    if (url.pathname === '/api/auth/user') return json(res, { user });
    if (url.pathname === '/api/action-alerts') return json(res, { app: 'office', total: 0, items: [] });
    if (url.pathname === '/api/settings/company') return json(res, { company: { name: 'Valley Water', logo_url: null, primary_color: '#0b84a5', default_theme: 'light' } });
    if (url.pathname === '/api/master-data/meta') return json(res, { resources: [], options: { employees: [] } });
    if (url.pathname === '/api/payroll-history') {
        if (!releasePayroll) await new Promise((resolve) => pendingPayroll.push(resolve));
        return json(res, emptySalary, failPayroll ? 503 : 200);
    }
    if (url.pathname.startsWith('/api/')) { unexpectedRequests.push(url.pathname); return json(res, {}, 404); }
    if (url.pathname === '/favicon.ico') { res.writeHead(204); res.end(); return; }
    const css = [manifest['resources/css/app.css'].file, ...(manifest['resources/js/app.js'].css || [])];
    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
    res.end(`<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">${css.map((file) => `<link rel="stylesheet" href="/build/${file}">`).join('')}</head><body><div id="root"></div><script>window.ValleyRuntime={routes:{office:'/office',sales:'/sales',supervisor:'/supervisor',driver:'/driver',client:'/client'}};</script><script type="module" src="/build/${manifest['resources/js/app.js'].file}"></script></body></html>`);
}

async function waitFor(label, condition) {
    const deadline = Date.now() + 12000;
    while (Date.now() < deadline) {
        if (typeof condition === 'function' ? condition() : await win.webContents.executeJavaScript(condition)) return;
        await new Promise((resolve) => setTimeout(resolve, 30));
    }
    throw new Error('Timed out: ' + label);
}
async function screenshot(name) {
    await win.webContents.executeJavaScript(`Promise.allSettled(document.getAnimations().filter((animation) => Number.isFinite(animation.effect?.getComputedTiming().endTime)).map((animation) => animation.finished)).then(() => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve))))`);
    fs.writeFileSync(path.join(output, name + '.png'), (await win.webContents.capturePage()).toPNG());
}
async function shellState() {
    return win.webContents.executeJavaScript(`(() => {
        const before = window.__shellTest;
        const sidebar = document.querySelector('.sidebar');
        const topbar = document.querySelector('.topbar');
        return {
            sameSidebar: sidebar === before.sidebar, sameTopbar: topbar === before.topbar,
            sidebarVisible: sidebar.getBoundingClientRect().width > 0,
            topbarVisible: topbar.getBoundingClientRect().height > 0,
            scrollTop: before.scroller.scrollTop, expectedScrollTop: before.scrollTop,
            scrollbar: getComputedStyle(sidebar, '::-webkit-scrollbar').width,
            rootScrollbar: getComputedStyle(document.documentElement, '::-webkit-scrollbar').width,
            scrollbarButton: getComputedStyle(sidebar, '::-webkit-scrollbar-button').display,
            standardWidth: getComputedStyle(sidebar).scrollbarWidth,
            theme: document.querySelector('.app-root').dataset.theme
        };
    })()`);
}
function assertShell(state, checkScroll = true) {
    assert.equal(state.sameSidebar, true, 'Sidebar must remain mounted');
    assert.equal(state.sameTopbar, true, 'App bar must remain mounted');
    assert.equal(state.sidebarVisible, true, 'Sidebar must remain visible on desktop');
    assert.equal(state.topbarVisible, true, 'App bar must remain visible');
    if (checkScroll) assert.equal(state.scrollTop, state.expectedScrollTop, 'Sidebar scroll position must survive page loading');
    assert.equal(state.scrollbar, '6px');
    assert.equal(state.rootScrollbar, '6px');
    assert.equal(state.scrollbarButton, 'none');
    assert.equal(state.standardWidth, 'auto', 'Chromium must use the custom scrollbar styling');
}

app.whenReady().then(async () => {
    try {
        server = http.createServer((req, res) => serve(req, res).catch((error) => { console.error(error); res.destroy(); }));
        await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
        const origin = 'http://127.0.0.1:' + server.address().port;
        session.defaultSession.webRequest.onBeforeRequest((details, callback) => callback({ cancel: !details.url.startsWith(origin + '/') && !details.url.startsWith('data:') && !details.url.startsWith('blob:') }));
        win = new BrowserWindow({ show: false, width: 1440, height: 900, useContentSize: true, webPreferences: { nodeIntegration: false, contextIsolation: true, sandbox: true, backgroundThrottling: false } });
        win.webContents.setWindowOpenHandler(() => ({ action: 'deny' }));
        await win.loadURL(origin + '/office/profile');
        await waitFor('profile page', `document.querySelector('.profile-settings-page') !== null`);
        await win.webContents.executeJavaScript(`(() => {
            const sidebar = document.querySelector('.sidebar');
            const nav = document.querySelector('.sidebar-nav');
            const scroller = [sidebar, nav].find((node) => node.scrollHeight > node.clientHeight && /auto|scroll/.test(getComputedStyle(node).overflowY));
            if (!scroller) throw new Error('Expected a scrollable sidebar');
            scroller.scrollTo({ top: 300, behavior: 'instant' });
            window.__shellTest = { sidebar, scroller, topbar: document.querySelector('.topbar'), scrollTop: scroller.scrollTop };
            document.querySelector('.nav-item[href="/office/payroll/salary-history"]').click();
        })()`, true);
        await waitFor('local page loading indicator', `document.querySelector('.office-page-loading') !== null`);
        await waitFor('page module request reaches fixture server', () => pendingChunks.length > 0);
        assert.ok(pendingChunks.length, 'The page module must still be pending');
        assertShell(await shellState());
        await screenshot('desktop-loading-light');
        await win.webContents.executeJavaScript(`document.querySelector('.topbar button[aria-label="Theme"]').click()`);
        await waitFor('theme toggle during loading', `document.querySelector('.app-root').dataset.theme === 'dark'`);
        assertShell(await shellState());
        win.setContentSize(1280, 720);
        await waitFor('dark desktop size', `innerWidth === 1280`);
        await screenshot('desktop-loading-dark');
        win.setContentSize(390, 844);
        await waitFor('mobile size', `innerWidth === 390`);
        await win.webContents.executeJavaScript(`document.querySelector('button[aria-label="Open navigation"]').click()`);
        await waitFor('drawer opens during loading', `document.querySelector('.sidebar.is-open') && document.querySelector('.mobile-scrim')`);
        await screenshot('mobile-drawer-loading');
        await win.webContents.executeJavaScript(`document.querySelector('button[aria-label="Close navigation"]').click()`);
        win.setContentSize(1440, 900);
        win.webContents.navigationHistory.goBack();
        await waitFor('Back works while module is pending', `location.pathname === '/office/profile' && document.querySelector('.profile-settings-page')?.getBoundingClientRect().height > 0`);
        assert.ok(!releaseChunks, 'Back must work before the pending module resolves');
        win.webContents.navigationHistory.goForward();
        await waitFor('Forward restores local loading', `document.querySelector('.office-page-loading') !== null`);
        releaseChunks = true; pendingChunks.splice(0).forEach((resolve) => resolve());
        await waitFor('data request after module loads', `document.querySelector('.payroll-workspace') !== null`);
        assertShell(await shellState(), false);
        await screenshot('desktop-data-loading');
        releasePayroll = true; pendingPayroll.splice(0).forEach((resolve) => resolve());
        await waitFor('data error stays in page', `document.querySelector('.payroll-workspace .inline-error')?.textContent.includes('Test connection interruption')`);
        assertShell(await shellState(), false);
        await screenshot('desktop-data-error');
        failPayroll = false;
        await win.webContents.executeJavaScript(`document.querySelector('#shell-page-actions button[aria-label="Refresh"]').click()`);
        await waitFor('refresh recovers after data error', `!document.querySelector('.payroll-workspace .inline-error') && document.querySelector('.payroll-workspace')?.textContent.includes('No paid salary records')`);
        win.setContentSize(1024, 768);
        await waitFor('tablet size', `innerWidth === 1024`);
        assertShell(await shellState(), false);
        await screenshot('tablet-loaded');
        assert.deepEqual(unexpectedRequests, []);
        const report = { passed: true, scenarios: ['Pending module keeps sidebar/app bar mounted and sidebar scroll unchanged', 'Theme control remains interactive while loading', 'Mobile drawer opens and closes while loading', 'Back and Forward work before the page module resolves', 'Data loading and errors keep navigation visible', 'Refresh recovers after a data error', '6px root/sidebar scrollbars without arrow buttons in Chromium', 'Light 1440x900, dark 1280x720, tablet 1024x768, mobile 390x844'] };
        fs.writeFileSync(path.join(output, 'report.json'), JSON.stringify(report, null, 2));
        console.log(JSON.stringify(report, null, 2));
        app.exit(0);
    } catch (error) {
        console.error(error);
        if (win && !win.isDestroyed()) console.error(await win.webContents.executeJavaScript(`JSON.stringify({url:location.href,history:history.length,profile:document.querySelector('.profile-settings-page')?.getBoundingClientRect().height,loading:!!document.querySelector('.office-page-loading')})`).catch(() => 'Renderer unavailable'));
        if (win && !win.isDestroyed()) await screenshot('failure').catch(() => {});
        app.exit(1);
    }
});
