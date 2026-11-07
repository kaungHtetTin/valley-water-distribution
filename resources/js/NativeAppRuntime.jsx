import { useEffect, useState } from 'react';
import { Bell, MapPin, Settings } from 'lucide-react';
import { isNativeAndroid, nativeBridge, registerNativeDevice, resetNativeRegistration } from './nativeRuntime';

export function NativeAppRuntime({ user, app }) {
    useEffect(() => {
        if (!isNativeAndroid()) return undefined;
        if (!user) {
            resetNativeRegistration();
            nativeBridge.disconnect().catch(() => {});
            return undefined;
        }
        let active = true;
        let busy = false;
        const sync = async () => {
            if (busy || document.visibilityState !== 'visible') return;
            busy = true;
            try {
                const status = await nativeBridge.status();
                if (!active || status.app !== app) return;
                await registerNativeDevice(user, app, status);
                if (app !== 'driver' || !status.trackingEnabled || status.trackingRunning) return;
                const { data } = await window.axios.get(window.ValleyRuntime?.api?.mobileDeliveries || '/api/mobile/deliveries');
                if (active && data.data.items.some((trip) => trip.status === 'on_route')) await nativeBridge.startTracking();
            } catch (error) {
                window.dispatchEvent(new CustomEvent('valley-native-error', { detail: error.response?.data?.message || error.message || 'Unable to connect native features.' }));
            } finally { busy = false; }
        };
        sync();
        const timer = window.setInterval(sync, 15000);
        window.addEventListener('valley-native-sync', sync);
        document.addEventListener('visibilitychange', sync);
        return () => { active = false; window.clearInterval(timer); window.removeEventListener('valley-native-sync', sync); document.removeEventListener('visibilitychange', sync); };
    }, [user?.id, app]);

    useEffect(() => {
        if (!user || !window.valleyDesktop || app !== 'office') return undefined;
        let active = true;
        let previous = {};
        const poll = async () => {
            try {
                const status = await window.valleyDesktop.status();
                if (!status.notifications) return;
                const { data } = await window.axios.get(window.ValleyRuntime?.api?.actionAlerts || '/api/action-alerts', { params: { app } });
                if (!active) return;
                const next = {};
                for (const alert of data.data.items) {
                    next[alert.id] = alert.count;
                    if (previous[alert.id] !== alert.count) window.valleyDesktop.notify(`${alert.count} ${alert.label}. Open Valley to review.`, alert.path);
                }
                previous = next;
            } catch {}
        };
        poll();
        const timer = window.setInterval(poll, 60000);
        return () => { active = false; window.clearInterval(timer); };
    }, [user?.id, app]);
    return null;
}

export function NativeDeviceSettings({ user }) {
    const [status, setStatus] = useState(null);
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);
    const android = isNativeAndroid();
    const desktop = window.valleyDesktop;
    const refresh = async () => setStatus(android ? await nativeBridge.status() : await desktop.status());
    useEffect(() => {
        if (!android && !desktop) return undefined;
        refresh().catch(() => {});
        const showError = (event) => setError(event.detail);
        window.addEventListener('valley-native-error', showError);
        const timer = window.setInterval(() => refresh().catch(() => {}), 5000);
        return () => { window.clearInterval(timer); window.removeEventListener('valley-native-error', showError); };
    }, []);
    if ((!android && !desktop) || !status) return null;
    const action = async (work) => {
        setBusy(true); setError('');
        try { await work(); await refresh(); window.dispatchEvent(new Event('valley-native-sync')); }
        catch (exception) { setError(exception.response?.data?.message || exception.message || 'Unable to update device permissions.'); }
        finally { setBusy(false); }
    };
    return <section className="master-panel native-device-settings">
        <h2><Settings size={18} /> Device permissions</h2>
        <div className="native-permission-row"><div><strong><Bell size={16} /> Notifications</strong><p>{android
            ? status.pushConfigured ? status.notifications ? 'Android notifications are allowed. Enable notifications to connect this account.' : 'Allow alerts for tasks and approvals on this device.' : 'Push notifications will become available after Firebase is configured.'
            : 'Show Windows notifications while Valley Office is running.'}</p></div>
            <button className="button" type="button" disabled={busy || (android && !status.pushConfigured)} onClick={() => action(async () => {
                if (android) {
                    await nativeBridge.enableNotifications();
                    const next = await nativeBridge.status();
                    await registerNativeDevice(user, next.app, next, true);
                } else await desktop.setNotifications(!status.notifications);
            })}>{desktop && status.notifications ? 'Disable notifications' : 'Enable notifications'}</button>
        </div>
        {android && status.app === 'driver' && <div className="native-permission-row"><div><strong><MapPin size={16} /> Trip location sharing</strong>
            <p>Share precise location during active delivery trips, including while the screen is locked. Android shows a persistent tracking notification. Sharing stops when the trip finishes or you sign out.</p>
            <p role="status">{status.trackingRunning ? 'Trip tracking is running.' : status.trackingEnabled ? 'Ready. Tracking starts when you have an active trip.' : 'Trip tracking is disabled.'} {status.trackingError}</p>
            {status.lastUpload > 0 && <small>Last shared: {new Date(status.lastUpload).toLocaleString()}</small>}
        </div><button className="button" type="button" disabled={busy} onClick={() => action(() => status.trackingEnabled ? nativeBridge.stopTracking() : nativeBridge.enableTracking())}>{status.trackingEnabled ? 'Stop tracking' : 'Enable trip tracking'}</button></div>}
        {android && <button className="button" type="button" onClick={() => action(() => nativeBridge.openSettings())}>Open Android Settings</button>}
        {error && <p className="form-alert" role="alert">{error}</p>}
    </section>;
}
