import { createContext, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import type { Locale } from '../domain-types/application';
import { messages, type MessageKey } from './messages';

interface I18nValue {
    locale: Locale;
    setLocale: (locale: Locale) => void;
    t: (key: MessageKey) => string;
}

const I18nContext = createContext<I18nValue | null>(null);

function initialLocale(): Locale {
    const stored = window.localStorage.getItem('vw-locale');
    return stored === 'my-MM' ? 'my-MM' : 'en';
}

export function I18nProvider({ children }: { children: ReactNode }) {
    const [locale, setLocale] = useState<Locale>(initialLocale);

    useEffect(() => {
        window.localStorage.setItem('vw-locale', locale);
        document.documentElement.lang = locale;
    }, [locale]);

    const value = useMemo<I18nValue>(
        () => ({
            locale,
            setLocale,
            t: (key) => messages[locale][key],
        }),
        [locale],
    );

    return <I18nContext.Provider value={value}>{children}</I18nContext.Provider>;
}

export function useI18n(): I18nValue {
    const context = useContext(I18nContext);
    if (!context) {
        throw new Error('useI18n must be used within I18nProvider.');
    }

    return context;
}
