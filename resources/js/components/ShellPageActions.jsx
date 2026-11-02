import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';

function actionLabel(children) {
    return React.Children.toArray(children)
        .map((child) => {
            if (typeof child === 'string' || typeof child === 'number') return String(child);
            if (!React.isValidElement(child)) return '';
            return actionLabel(child.props.children);
        })
        .join(' ')
        .replace(/\s+/g, ' ')
        .trim();
}

export function ShellPageActions({ children, className = '' }) {
    const [host, setHost] = useState(null);

    useEffect(() => {
        setHost(document.getElementById('shell-page-actions'));
    }, []);

    if (!host || !children) return null;

    const actions = React.Children.map(children, (child) => {
        if (!React.isValidElement(child) || child.type !== 'button') return child;
        const label = child.props['aria-label'] || child.props.title || actionLabel(child.props.children);
        return label ? React.cloneElement(child, { 'aria-label': label, title: child.props.title || label }) : child;
    });

    return createPortal(
        <div className={`shell-page-action-group ${className}`.trim()}>{actions}</div>,
        host,
    );
}

export function ShellPageSearch({ children }) {
    const [host, setHost] = useState(null);

    useEffect(() => {
        setHost(document.getElementById('shell-page-search'));
    }, []);

    if (!host || !children) return null;

    return createPortal(children, host);
}
