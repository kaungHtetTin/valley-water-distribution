import { useEffect } from 'react';

const focusable = 'button:not(:disabled), a[href], input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex="-1"])';
const visible = (node) => node.getClientRects().length && !node.closest('[inert], [aria-hidden="true"]');

// Cover legacy module dialogs as well as the shared confirmation dialog.
export function useOverlayAccessibility() {
    useEffect(() => {
        let active = null;
        let lastFocused = document.activeElement;
        let sequence = 0;
        const returnTargets = new WeakMap();
        const topOverlay = () => {
            const dialogs = [...document.querySelectorAll('[role="dialog"][aria-modal="true"]')].filter(visible);
            return dialogs.sort((a, b) => {
                const layer = (node) => Number(getComputedStyle(node.closest('.modal-backdrop, .drawer-backdrop, .printing-preview-modal-backdrop') || node).zIndex) || 0;
                return layer(a) - layer(b);
            }).at(-1) || null;
        };
        const sync = () => {
            document.querySelectorAll('.modal-backdrop > .master-dialog, .drawer-backdrop > .record-drawer, .drawer-backdrop > .mobile-detail-sheet, .printing-preview-modal-backdrop > .printing-preview-modal').forEach((dialog) => {
                dialog.setAttribute('role', 'dialog');
                dialog.setAttribute('aria-modal', 'true');
                if (!dialog.hasAttribute('aria-label') && !dialog.hasAttribute('aria-labelledby')) {
                    const heading = dialog.querySelector('h1, h2, h3');
                    if (heading) {
                        if (!heading.id) heading.id = `overlay-title-${++sequence}`;
                        dialog.setAttribute('aria-labelledby', heading.id);
                    }
                }
            });
            const next = topOverlay();
            if (next === active) return;
            if (active && !document.contains(active)) {
                const previous = returnTargets.get(active);
                if (previous?.isConnected) previous.focus({ preventScroll: true });
            }
            active = next;
            if (next) {
                if (!returnTargets.has(next)) returnTargets.set(next, document.activeElement);
                if (!next.contains(document.activeElement)) {
                    const target = [...next.querySelectorAll('[autofocus], ' + focusable)].find(visible);
                    next.tabIndex = -1;
                    (target || next).focus({ preventScroll: true });
                }
            }
        };
        const keydown = (event) => {
            const overlay = topOverlay();
            if (!overlay) return;
            if (event.key === 'Escape') {
                const close = [...overlay.querySelectorAll('button[aria-label], button[title]')].find((button) => button.querySelector('.lucide-x') && !button.disabled);
                if (close) { event.preventDefault(); event.stopImmediatePropagation(); close.click(); }
            }
            if (event.key !== 'Tab') return;
            const items = [...overlay.querySelectorAll(focusable)].filter(visible);
            const first = items[0] || overlay;
            const last = items.at(-1) || overlay;
            if (!overlay.contains(document.activeElement) || (event.shiftKey && document.activeElement === first) || (!event.shiftKey && document.activeElement === last)) {
                event.preventDefault();
                (event.shiftKey ? last : first).focus();
            }
        };
        const containFocus = (event) => {
            const overlay = topOverlay();
            // React autofocus runs before the mutation observer sees a new dialog.
            if (overlay && !returnTargets.has(overlay)) returnTargets.set(overlay, lastFocused);
            lastFocused = event.target;
            if (overlay && !overlay.contains(event.target)) (Array.from(overlay.querySelectorAll(focusable)).find(visible) || overlay).focus();
        };
        const observer = new MutationObserver(sync);
        observer.observe(document.body, { childList: true, subtree: true });
        document.addEventListener('keydown', keydown, true);
        document.addEventListener('focusin', containFocus);
        sync();
        return () => {
            observer.disconnect();
            document.removeEventListener('keydown', keydown, true);
            document.removeEventListener('focusin', containFocus);
        };
    }, []);
}
