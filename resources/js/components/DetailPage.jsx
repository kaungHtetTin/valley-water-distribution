import React from 'react';
import { ShellBackButton } from './ShellBackButton';
import { ShellPageActions } from './ShellPageActions';

export function DetailPage({ eyebrow, title, subtitle, onBack, actions = null, aside = null, wideContent = null, children }) {
    return (
        <section className="master-workspace record-page">
            <ShellBackButton onClick={onBack} />
            {actions && <ShellPageActions>{actions}</ShellPageActions>}
            <div className="master-heading record-page-heading">
                <div>
                    {eyebrow && <p className="eyebrow">{eyebrow}</p>}
                    <h1>{title}</h1>
                    {subtitle && <span className="muted">{subtitle}</span>}
                </div>
            </div>
            <div className={`record-page-layout ${aside ? '' : 'single-column'}`}>
                <main className="record-page-main">{children}</main>
                {aside && (
                    <aside className="record-page-side" aria-label="Record summary">
                        {aside}
                    </aside>
                )}
            </div>
            {wideContent && <div className="record-page-wide">{wideContent}</div>}
        </section>
    );
}

export function DetailPanel({ eyebrow, title, children, className = '' }) {
    return (
        <section className={`master-panel record-page-panel ${className}`.trim()}>
            {(eyebrow || title) && <div className="record-page-panel-heading">{eyebrow && <p className="eyebrow">{eyebrow}</p>}{title && <h2>{title}</h2>}</div>}
            {children}
        </section>
    );
}
