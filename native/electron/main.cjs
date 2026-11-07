const { app, BrowserWindow, session, shell, ipcMain, Notification, dialog } = require('electron');
const path = require('node:path');
const fs = require('node:fs');
const { BASE, serverUrl, appUrl, externalUrl } = require('./policy.cjs');
let mainWindow;
let notificationsEnabled = false;
const smokeTest = process.argv.includes('--smoke-test');

// A portable executable still stores sessions in the current user's protected profile.
// It never needs elevation or writes credentials beside the EXE.
app.setAppUserModelId('com.valleymyanmar.office');
if (!app.requestSingleInstanceLock()) app.quit();
app.on('second-instance', () => { mainWindow?.restore(); mainWindow?.focus(); });
app.on('web-contents-created', (_, contents) => {
    contents.on('will-attach-webview', (event) => event.preventDefault());
    contents.on('will-navigate', (event, url) => {
        if (appUrl(url)) return;
        event.preventDefault();
        if (externalUrl(url) && !serverUrl(url)) shell.openExternal(url);
    });
    contents.on('will-redirect', (event, url) => { if (!appUrl(url)) event.preventDefault(); });
    contents.setWindowOpenHandler(({ url }) => {
        // Existing invoice printing writes into a blank child window, then calls window.print().
        if (url === 'about:blank' && appUrl(contents.getURL())) return {
            action: 'allow', overrideBrowserWindowOptions: { autoHideMenuBar: true, webPreferences: { nodeIntegration: false, contextIsolation: true, sandbox: true } },
        };
        if (externalUrl(url) && !serverUrl(url)) shell.openExternal(url);
        return { action: 'deny' };
    });
});

app.whenReady().then(() => {
    const preferences = path.join(app.getPath('userData'), 'native-preferences.json');
    try { notificationsEnabled = JSON.parse(fs.readFileSync(preferences, 'utf8')).notifications === true; } catch {}
    const trusted = (event) => event.sender === mainWindow?.webContents && event.senderFrame === event.sender.mainFrame && appUrl(event.senderFrame.url);
    ipcMain.handle('valley:notification-permission', (event, enabled) => {
        if (!trusted(event) || typeof enabled !== 'boolean') return false;
        notificationsEnabled = enabled;
        fs.writeFileSync(preferences, JSON.stringify({ notifications: enabled }));
        return notificationsEnabled;
    });
    ipcMain.handle('valley:status', (event) => trusted(event) ? { notifications: notificationsEnabled } : {});
    ipcMain.on('valley:notify', (event, data) => {
        if (!trusted(event) || !notificationsEnabled || !Notification.isSupported()) return;
        if (typeof data?.body !== 'string' || typeof data?.path !== 'string') return;
        const destination = `${BASE}/office${data.path}`;
        if (!data.path.startsWith('/') || !appUrl(destination)) return;
        const notification = new Notification({ title: 'Valley Office', body: data.body.slice(0, 180), icon: path.join(__dirname, 'valley.ico'), silent: false });
        notification.on('click', () => { mainWindow.show(); mainWindow.focus(); mainWindow.loadURL(destination); });
        notification.show();
    });
    const browserSession = session.defaultSession;
    browserSession.setPermissionCheckHandler((contents, permission, origin, details) =>
        contents === mainWindow?.webContents && appUrl(contents.getURL()) && origin === new URL(BASE).origin
        && details?.isMainFrame === true && permission === 'geolocation');
    browserSession.setPermissionRequestHandler(async (contents, permission, callback, details) => {
        if (contents !== mainWindow?.webContents || !appUrl(contents.getURL()) || !appUrl(details.requestingUrl) || !details.isMainFrame || permission !== 'geolocation') return callback(false);
        const { response } = await dialog.showMessageBox(mainWindow, { type: 'question', title: 'Location access', message: 'Allow Valley Office to use your location for this request?', buttons: ['Deny', 'Allow'], defaultId: 0, cancelId: 0 });
        callback(response === 1);
    });
    browserSession.setDevicePermissionHandler(() => false);
    browserSession.on('will-download', (event, item, contents) => {
        const url = item.getURL();
        if (!appUrl(mainWindow?.webContents.getURL()) || !(serverUrl(url) || url.startsWith(`blob:${new URL(BASE).origin}/`))) return event.preventDefault();
        item.setSaveDialogOptions({ title: 'Save Valley document', defaultPath: path.join(app.getPath('downloads'), path.basename(item.getFilename())) });
    });
    mainWindow = new BrowserWindow({
        icon: path.join(__dirname, 'valley.ico'),
        title: 'Valley Office', width: 1440, height: 940, minWidth: 1024, minHeight: 700, autoHideMenuBar: true,
        webPreferences: { preload: path.join(__dirname, 'preload.cjs'), nodeIntegration: false, contextIsolation: true, sandbox: true, webSecurity: true, allowRunningInsecureContent: false, spellcheck: false },
    });
    mainWindow.webContents.on('did-fail-load', (_, code, description, url, isMainFrame) => {
        if (!isMainFrame || code === -3) return;
        dialog.showMessageBox(mainWindow, { type: 'warning', title: 'Connection unavailable', message: 'Valley needs an internet connection. Check your connection and try again.', buttons: ['Retry', 'Close'] })
            .then(({ response }) => response === 0 ? mainWindow.loadURL(`${BASE}/office`) : mainWindow.close());
    });
    if (smokeTest) {
        mainWindow.webContents.once('did-finish-load', async () => {
            const result = await mainWindow.webContents.executeJavaScript('JSON.stringify({url:location.href,title:document.title,node:typeof require,bridge:typeof window.valleyDesktop})');
            console.log('VALLEY_SMOKE_TEST '+result);
            app.quit();
        });
    }
    mainWindow.loadURL(`${BASE}/office`);
});
app.on('window-all-closed', () => app.quit());
