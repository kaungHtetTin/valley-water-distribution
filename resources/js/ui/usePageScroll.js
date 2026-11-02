import { useEffect } from 'react';

export function usePageScroll(pathname) {
    useEffect(() => {
        const key = `valley:page:${pathname}:scroll`;
        let saved = 0;
        try { saved = Number(sessionStorage.getItem(key) || 0); } catch { /* Optional storage. */ }
        let restoring = true;
        let frame;
        const restore = () => {
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(() => { if (restoring) window.scrollTo({ top: saved, behavior: 'instant' }); });
        };
        const stopRestoring = () => { restoring = false; };
        const save = () => {
            if (restoring) return;
            try { sessionStorage.setItem(key, String(window.scrollY)); } catch { /* Optional storage. */ }
        };
        const observer = new MutationObserver(restore);
        observer.observe(document.querySelector('.app-root') || document.body, { childList: true, subtree: true });
        restore();
        const timeout = setTimeout(() => { restoring = false; observer.disconnect(); }, 2500);
        window.addEventListener('scroll', save, { passive: true });
        window.addEventListener('wheel', stopRestoring, { passive: true });
        window.addEventListener('touchstart', stopRestoring, { passive: true });
        window.addEventListener('keydown', stopRestoring);
        return () => {
            clearTimeout(timeout); cancelAnimationFrame(frame); observer.disconnect();
            window.removeEventListener('scroll', save);
            window.removeEventListener('wheel', stopRestoring);
            window.removeEventListener('touchstart', stopRestoring);
            window.removeEventListener('keydown', stopRestoring);
        };
    }, [pathname]);
}
