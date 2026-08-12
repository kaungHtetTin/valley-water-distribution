import { useState } from 'react';
import { Icon, type IconName } from '../../packages/design-system/Icon';
import { usePreferences } from '../../packages/design-system/usePreferences';
import { useI18n } from '../../packages/i18n/I18nProvider';
import type { MessageKey } from '../../packages/i18n/messages';

interface MobileNavItem {
    label: MessageKey;
    icon: IconName;
}

interface MobileFoundationProps {
    applicationLabel: MessageKey;
    nav: MobileNavItem[];
    accentIcon: IconName;
}

export function MobileFoundation({ applicationLabel, nav, accentIcon }: MobileFoundationProps) {
    const { locale, setLocale, t } = useI18n();
    const { theme, toggleTheme } = usePreferences();
    const [active, setActive] = useState(nav[0].label);

    return (
        <div className="mobile-root" data-theme={theme} data-density="comfortable">
            <header className="mobile-appbar">
                <div className="mobile-brand">
                    <span className="brand-mark">V</span>
                    <div><strong>{t('appName')}</strong><small>{t(applicationLabel)}</small></div>
                </div>
                <div className="mobile-header-actions">
                    <button className="icon-button" type="button" onClick={toggleTheme} aria-label={t('toggleTheme')}>
                        <Icon name={theme === 'light' ? 'moon' : 'sun'} />
                    </button>
                    <button className="language-button" type="button" onClick={() => setLocale(locale === 'en' ? 'my-MM' : 'en')} aria-label={t('switchLanguage')}>
                        {locale === 'en' ? 'မြန်မာ' : 'EN'}
                    </button>
                </div>
            </header>

            <main className="mobile-content">
                <section className="mobile-hero">
                    <span className="mobile-hero-icon"><Icon name={accentIcon} size={24} /></span>
                    <p className="eyebrow">{t('phaseZero')}</p>
                    <h1>{t('mobileFoundation')}</h1>
                    <p>{t('mobileDescription')}</p>
                </section>

                <section className="mobile-status-strip" aria-label={t('platformReadiness')}>
                    <div><span className="status-dot status-dot--success" /><strong>{t('online')}</strong></div>
                    <div><span className="status-dot status-dot--neutral" /><strong>{t('pendingSync')}</strong></div>
                </section>

                <section className="mobile-section">
                    <div className="mobile-section-heading"><h2>{t('platformReadiness')}</h2><span className="status-badge status-badge--info"><span />{t('phaseZero')}</span></div>
                    <article className="mobile-task-card">
                        <span className="task-icon"><Icon name="shield" /></span>
                        <div><strong>{t('secureSession')}</strong><p>{t('notActivated')}</p></div>
                        <Icon name="chevron" size={16} />
                    </article>
                    <article className="mobile-task-card">
                        <span className="task-icon"><Icon name="activity" /></span>
                        <div><strong>{t('offlineQueue')}</strong><p>{t('pendingSync')}</p></div>
                        <Icon name="chevron" size={16} />
                    </article>
                </section>

                <section className="mobile-empty panel">
                    <Icon name={accentIcon} size={24} />
                    <strong>{t(active)}</strong>
                    <p>{t('awaitingDomainData')}</p>
                </section>
            </main>

            <nav className="bottom-nav" aria-label={t(applicationLabel)}>
                {nav.map((item) => (
                    <button key={item.label} type="button" className={active === item.label ? 'is-active' : ''} onClick={() => setActive(item.label)} aria-current={active === item.label ? 'page' : undefined}>
                        <Icon name={item.icon} size={19} />
                        <span>{t(item.label)}</span>
                    </button>
                ))}
            </nav>
        </div>
    );
}
