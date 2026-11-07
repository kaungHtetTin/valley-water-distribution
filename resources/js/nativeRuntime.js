import { Capacitor, registerPlugin } from '@capacitor/core';

export const isNativeAndroid = () => Capacitor.isNativePlatform() && Capacitor.getPlatform() === 'android';
export const nativeBridge = registerPlugin('ValleyNative');
// Resolve from an existing runtime URL so /public and other deployment prefixes are retained.
export const deviceApi = () => (window.ValleyRuntime?.auth?.user || '/api/auth/user').replace(/\/auth\/user$/, '/native/devices');
let registration = null;
let registeredUser = null;
let registeredAt = 0;
let registeredToken = null;

export function resetNativeRegistration() {
    registeredAt = 0;
    registeredUser = null;
    registeredToken = null;
}

export async function registerNativeDevice(user, app, status, force = false) {
    if (!isNativeAndroid() || !user || status.app !== app) return;
    const token = status.notifications ? status.fcmToken || null : null;
    if (!force && registeredUser === user.id && registeredToken === token && Date.now() - registeredAt < 60 * 60 * 1000) return;
    if (registration) return registration;
    registration = (async () => {
        const { data } = await window.axios.post(deviceApi(), {
            installation_id: status.installationId, app,
            fcm_token: token,
        });
        if (app === 'driver') await nativeBridge.configure({ trackingToken: data.data.tracking_token });
        registeredUser = user.id;
        registeredAt = Date.now();
        registeredToken = token;
    })();
    try { await registration; } finally { registration = null; }
}

export async function disconnectNativeDevice() {
    resetNativeRegistration();
    if (!isNativeAndroid()) return;
    await nativeBridge.disconnect();
    const status = await nativeBridge.status();
    await window.axios.delete(`${deviceApi()}/${status.installationId}`);
}

export async function nativePrint(html) {
    return nativeBridge.printHtml({ html });
}
