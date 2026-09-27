import React from 'react';
import { ShellBackButton } from './ShellBackButton';

export function DetailPage({ eyebrow, title, subtitle, onBack, actions = null, aside = null, wideContent = null, children }) {
    return (
        <section className="master-workspace record-page">
            <ShellBackButton onClick={onBack} />
            <div className="master-heading record-page-heading">
                <div>
                    {eyebrow && <p className="eyebrow">{eyebrow}</p>}
                    <h1>{title}</h1>
                    {subtitle && <span className="muted">{subtitle}</span>}
                </div>
            </div>
            <div className={`record-page-layout ${aside || actions ? '' : 'single-column'}`}>
                <main className="record-page-main">{children}</main>
                {(aside || actions) && (
                    <aside className="record-page-side" aria-label="Record summary and actions">
                        {aside}
                        {actions && <section className="master-panel record-page-actions"><p className="eyebrow">Actions</p>{actions}</section>}
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
