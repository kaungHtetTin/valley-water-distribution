import { useEffect, useRef, useState } from 'react';

const prefix = 'valley:page:';
export function usePageState(name, initialValue) {
    const key = `${prefix}${window.location.pathname}:${name}`;
    const read = () => {
        try {
            const stored = window.sessionStorage.getItem(key);
            if (stored !== null) return JSON.parse(stored);
        } catch { /* Storage can be disabled; in-memory state still works. */ }
        return typeof initialValue === 'function' ? initialValue() : initialValue;
    };
    const [record, setRecord] = useState(() => ({ key, value: read() }));
    const value = record.key === key ? record.value : read();
    if (record.key !== key) setRecord({ key, value });
    const setValue = (next) => setRecord((current) => ({ key, value: typeof next === 'function' ? next(current.key === key ? current.value : read()) : next }));
    useEffect(() => {
        try { window.sessionStorage.setItem(key, JSON.stringify(value)); } catch { /* Optional persistence. */ }
    }, [key, value]);
    return [value, setValue];
}

// Pagination resets on an actual filter edit, while returning to a list retains its page.
export function useUpdateEffect(callback, dependencies) {
    const scope = window.location.pathname;
    const previous = useRef({ scope, dependencies });
    useEffect(() => {
        const changed = previous.current.scope === scope && dependencies.some((value, index) => !Object.is(value, previous.current.dependencies[index]));
        previous.current = { scope, dependencies };
        if (changed) return callback();
    }, dependencies);
}

export function clearPageState() {
    try {
        Object.keys(window.sessionStorage).filter((key) => key.startsWith(prefix)).forEach((key) => window.sessionStorage.removeItem(key));
    } catch { /* Optional persistence. */ }
}
