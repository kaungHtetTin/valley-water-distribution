import { useEffect, useState } from 'react';
import type { Density, Theme } from '../domain-types/application';

function readTheme(): Theme {
    const stored = window.localStorage.getItem('vw-theme');
    if (stored === 'light' || stored === 'dark') return stored;
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function readDensity(): Density {
    return window.localStorage.getItem('vw-density') === 'comfortable' ? 'comfortable' : 'compact';
}

export function usePreferences() {
    const [theme, setTheme] = useState<Theme>(readTheme);
    const [density, setDensity] = useState<Density>(readDensity);

    useEffect(() => {
        window.localStorage.setItem('vw-theme', theme);
    }, [theme]);

    useEffect(() => {
        window.localStorage.setItem('vw-density', density);
    }, [density]);

    return {
        theme,
        density,
        toggleTheme: () => setTheme((current) => (current === 'light' ? 'dark' : 'light')),
        toggleDensity: () => setDensity((current) => (current === 'compact' ? 'comfortable' : 'compact')),
    };
}
