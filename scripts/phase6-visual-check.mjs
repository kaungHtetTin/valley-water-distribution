import { spawn } from 'node:child_process';
import { mkdir, rm, writeFile } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';

const baseUrl = 'http://localhost/valley/public';
const edgePath = 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
const profilePath = path.join(os.tmpdir(), `valley-phase6-${Date.now()}`);
const artifactPath = path.resolve('artifacts');

async function login(email, app) {
    const cookies = new Map();
    const collect = (response) => {
        for (const value of response.headers.getSetCookie()) {
            const pair = value.split(';', 1)[0];
            const separator = pair.indexOf('=');
            cookies.set(pair.slice(0, separator), pair.slice(separator + 1));
        }
    };
    const cookieHeader = () => [...cookies].map(([name, value]) => `${name}=${value}`).join('; ');
    const page = await fetch(`${baseUrl}/${app}`);
    collect(page);
    const html = await page.text();
    const csrf = html.match(/meta name="csrf-token" content="([^"]+)"/)?.[1];
    const response = await fetch(`${baseUrl}/api/auth/login`, {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Cookie: cookieHeader() },
        body: JSON.stringify({ email, password: 'password', app }),
    });
    collect(response);
    if (!response.ok) throw new Error(`Login failed for ${app}: ${response.status}`);
    return { header: cookieHeader(), csrf, xsrf: decodeURIComponent(cookies.get('XSRF-TOKEN') || '') };
}

async function setLocale(session, locale) {
    const response = await fetch(`${baseUrl}/api/auth/preferences`, {
        method: 'PUT',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': session.xsrf, Cookie: session.header },
        body: JSON.stringify({ locale }),
    });
    if (!response.ok) throw new Error(`Locale update failed: ${response.status}`);
}

async function waitForEdge() {
    for (let attempt = 0; attempt < 30; attempt += 1) {
        try {
            const response = await fetch('http://127.0.0.1:9222/json/version');
            if (response.ok) return;
        } catch {}
        await new Promise((resolve) => setTimeout(resolve, 250));
    }
    throw new Error('Edge debugging endpoint did not start.');
}

async function connect() {
    const target = await (await fetch('http://127.0.0.1:9222/json/new?about:blank', { method: 'PUT' })).json();
    const socket = new WebSocket(target.webSocketDebuggerUrl);
    await new Promise((resolve, reject) => { socket.onopen = resolve; socket.onerror = reject; });
    let id = 0;
    const pending = new Map();
    socket.onmessage = ({ data }) => {
        const message = JSON.parse(data);
        if (!message.id || !pending.has(message.id)) return;
        const call = pending.get(message.id);
        pending.delete(message.id);
        message.error ? call.reject(message.error) : call.resolve(message.result);
    };
    const send = (method, params = {}) => new Promise((resolve, reject) => {
        const callId = ++id;
        pending.set(callId, { resolve, reject });
        socket.send(JSON.stringify({ id: callId, method, params }));
    });
    return { socket, send };
}

async function setCookies(send, header) {
    await send('Network.clearBrowserCookies');
    for (const part of header.split(';')) {
        const separator = part.indexOf('=');
        if (separator < 1) continue;
        await send('Network.setCookie', { name: part.slice(0, separator).trim(), value: part.slice(separator + 1).trim(), url: baseUrl });
    }
}

async function screenshot(send, url, width, height, destination, cookies, locale = 'en') {
    await setCookies(send, cookies);
    await send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: width < 500 });
    await send('Page.navigate', { url });
    await new Promise((resolve) => setTimeout(resolve, 3000));
    await send('Runtime.evaluate', { expression: `window.localStorage.setItem('valley-locale', '${locale}')` });
    await send('Page.reload');
    let state = null;
    for (let attempt = 0; attempt < 30; attempt += 1) {
        const result = await send('Runtime.evaluate', {
            expression: `({ title: document.querySelector('h1')?.innerText, error: document.querySelector('.inline-error')?.innerText, loading: !!document.querySelector('.workspace-state .spin') || /Checking sign in|Loading|Calculating|Preparing/.test(document.body.innerText), body: document.body.innerText.slice(0, 500) })`,
            returnByValue: true,
        });
        state = result.result.value;
        if (state?.title && !state.error && !state.loading) break;
        await new Promise((resolve) => setTimeout(resolve, 500));
    }
    if (!state?.title || state.error || state.loading) throw new Error(`${url}: ${JSON.stringify(state)}`);
    const result = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
    await writeFile(destination, Buffer.from(result.data, 'base64'));
}

await mkdir(profilePath, { recursive: false });
await mkdir(artifactPath, { recursive: true });
const officeCookies = await login('office@valley.test', 'office');
const clientCookies = await login('client@valley.test', 'client');
const salesCookies = await login('sales@valley.test', 'sales');
const driverCookies = await login('driver@valley.test', 'driver');
const edge = spawn(edgePath, ['--headless=new', '--disable-gpu', '--remote-debugging-port=9222', `--user-data-dir=${profilePath}`, 'about:blank'], { stdio: 'ignore' });

try {
    await waitForEdge();
    const { socket, send } = await connect();
    await send('Page.enable');
    await send('Network.enable');
    await screenshot(send, `${baseUrl}/office/deliveries/live-map`, 1440, 900, path.join(artifactPath, 'phase6-office-live-map.png'), officeCookies.header);
    await screenshot(send, `${baseUrl}/office/deliveries/history`, 1280, 720, path.join(artifactPath, 'phase6-office-history.png'), officeCookies.header);
    await setLocale(officeCookies, 'my');
    await screenshot(send, `${baseUrl}/office/deliveries/history`, 1280, 720, path.join(artifactPath, 'phase6-office-history-my.png'), officeCookies.header, 'my');
    await setLocale(officeCookies, 'en');
    await screenshot(send, `${baseUrl}/client/deliveries`, 390, 844, path.join(artifactPath, 'phase6-client-deliveries.png'), clientCookies.header);
    await screenshot(send, `${baseUrl}/sales/deliveries`, 430, 932, path.join(artifactPath, 'phase6-sales-deliveries.png'), salesCookies.header);
    await screenshot(send, `${baseUrl}/driver/tasks`, 390, 844, path.join(artifactPath, 'phase6-driver-tasks.png'), driverCookies.header);
    await send('Browser.close').catch(() => {});
    socket.close();
} finally {
    if (edge.exitCode === null) edge.kill();
    await Promise.race([
        new Promise((resolve) => edge.once('exit', resolve)),
        new Promise((resolve) => setTimeout(resolve, 3000)),
    ]);
    const resolvedProfile = path.resolve(profilePath);
    const resolvedTemp = path.resolve(os.tmpdir());
    if (!resolvedProfile.startsWith(`${resolvedTemp}${path.sep}`)) throw new Error('Refusing to remove a profile outside the temporary directory.');
    for (let attempt = 0; attempt < 8; attempt += 1) {
        try {
            await rm(resolvedProfile, { recursive: true, force: true });
            break;
        } catch (error) {
            if (attempt === 7 && error.code !== 'EBUSY') throw error;
            if (attempt === 7) break;
            await new Promise((resolve) => setTimeout(resolve, 500));
        }
    }
}
