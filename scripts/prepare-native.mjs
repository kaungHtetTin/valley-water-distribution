import { readFile, mkdir, writeFile } from 'node:fs/promises';
const apps = ['sales', 'supervisor', 'driver'];
const config = JSON.parse(await readFile(new URL('../capacitor.config.json', import.meta.url), 'utf8'));
const error = await readFile(new URL('../native/web/error.html', import.meta.url), 'utf8');
for (const app of apps) {
    const assets = new URL(`../android/app/src/${app}/assets/`, import.meta.url);
    await mkdir(new URL('public/', assets), { recursive: true });
    await writeFile(new URL('capacitor.config.json', assets), JSON.stringify({
        ...config, appId: `com.valleymyanmar.${app}`, appName: `Valley ${app[0].toUpperCase()}${app.slice(1)}`,
        server: { ...config.server, url: `https://www.valleymyanmar.com/public/${app}` },
        android: { ...config.android, appendUserAgent: ` ValleyNative/1 ${app}` },
    }, null, 2) + '\n');
    await writeFile(new URL('public/error.html', assets), error.replace('location.reload()', `location.href='https://www.valleymyanmar.com/public/${app}'`));
}
console.log('Prepared Sales, Supervisor, and Driver Android flavors.');
