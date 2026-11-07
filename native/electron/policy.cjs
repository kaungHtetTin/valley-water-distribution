const BASE = 'https://www.valleymyanmar.com/public';
const ORIGIN = new URL(BASE).origin;
function serverUrl(value) {
    try { const url = new URL(value); return url.origin === ORIGIN && !url.username && !url.password && (url.pathname === '/public' || url.pathname.startsWith('/public/')); } catch { return false; }
}
function appUrl(value) {
    if (!serverUrl(value)) return false;
    const path = new URL(value).pathname;
    return ['/public/office', '/public/reset-password', '/public/attendance'].some((prefix) => path === prefix || path.startsWith(`${prefix}/`));
}
function externalUrl(value) {
    try { const url = new URL(value); return ['https:', 'mailto:', 'tel:'].includes(url.protocol) && !url.username && !url.password; } catch { return false; }
}
module.exports = { BASE, ORIGIN, serverUrl, appUrl, externalUrl };
