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

function officeIconAction(node) {
    if (!React.isValidElement(node)) return node;

    if (node.type === 'button') {
        const label = node.props['aria-label'] || node.props.title || actionLabel(node.props.children) || 'Action';
        const icon = React.Children.toArray(node.props.children).find((child) => React.isValidElement(child));
        if (!icon) return React.cloneElement(node, { 'aria-label': label, title: label });

        const currentClasses = String(node.props.className || '').split(/\s+/).filter(Boolean);
        const isPrimary = currentClasses.includes('primary') || currentClasses.includes('primary-icon');
        const classes = currentClasses.filter((name) => !['button', 'primary', 'primary-icon', 'icon-button'].includes(name));
        classes.push('icon-button');
        if (isPrimary) classes.push('primary-icon');

        return React.cloneElement(node, {
            'aria-label': label,
            title: label,
            className: [...new Set(classes)].join(' '),
        }, icon);
    }

    if (!node.props.children) return node;

    return React.cloneElement(node, undefined, React.Children.map(node.props.children, officeIconAction));
}

export function ShellPageActions({ children, className = '' }) {
    const [host, setHost] = useState(null);

    useEffect(() => {
        setHost(document.getElementById('shell-page-actions'));
    }, []);

    if (!host || !children) return null;

    const isOfficeApp = host.closest('.app-root')?.dataset.app === 'office';
    const actions = isOfficeApp ? React.Children.map(children, officeIconAction) : children;

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
