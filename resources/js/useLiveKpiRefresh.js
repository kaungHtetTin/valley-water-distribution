import { useEffect, useRef } from 'react';

export function useLiveKpiRefresh(refresh, scope, enabled = true) {
    const callback = useRef(refresh);
    callback.current = refresh;

    useEffect(() => {
        if (!enabled) return undefined;
        let active = true;
        let pending = false;
        const update = async () => {
            if (!active || pending || document.hidden) return;
            pending = true;
            try {
                await callback.current(() => active);
            } catch {
                // Keep the last loaded figures if a background refresh fails.
            } finally {
                pending = false;
            }
        };
        const timer = window.setInterval(update, 20000);
        window.addEventListener('focus', update);
        document.addEventListener('visibilitychange', update);
        return () => {
            active = false;
            window.clearInterval(timer);
            window.removeEventListener('focus', update);
            document.removeEventListener('visibilitychange', update);
        };
    }, [scope, enabled]);
}
