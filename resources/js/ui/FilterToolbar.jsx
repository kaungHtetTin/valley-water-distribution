import { Children, Fragment, isValidElement, useLayoutEffect, useRef, useState } from 'react';

// Keep the original controls and handlers; only their layout is shared.
function splitControls(children, fields, actions) {
    Children.forEach(children, (child) => {
        if (child == null || typeof child === 'boolean' || (typeof child === 'string' && !child.trim())) return;
        const classes = isValidElement(child) ? child.props.className || '' : '';
        if (isValidElement(child) && child.type === Fragment) {
            splitControls(child.props.children, fields, actions);
        } else if (isValidElement(child) && /(?:^|\s)[\w-]*(?:filter-actions|filters-actions)(?:\s|$)/.test(classes)) {
            Children.forEach(child.props.children, (action) => actions.push(action));
        } else if (isValidElement(child) && /(?:^|\s)(?:[\w-]*filter-(?:scroll|fields)|mobile-kpi-range)(?:\s|$)/.test(classes)) {
            splitControls(child.props.children, fields, actions);
        } else if (isValidElement(child) && child.type === 'button') {
            actions.push(child);
        } else {
            fields.push(child);
        }
    });
}

export function FilterToolbar({ as: Root = 'div', className = '', children, ...props }) {
    const viewport = useRef(null);
    const row = useRef(null);
    const scrollbar = useRef(null);
    const [overflow, setOverflow] = useState(0);
    const [trackWidth, setTrackWidth] = useState(0);
    const fields = [];
    const actions = [];
    splitControls(children, fields, actions);

    useLayoutEffect(() => {
        const measure = () => {
            const distance = Math.max(0, viewport.current.scrollWidth - viewport.current.clientWidth);
            setOverflow(distance);
            setTrackWidth((scrollbar.current?.clientWidth || 0) + distance);
            if (scrollbar.current) scrollbar.current.scrollLeft = viewport.current.scrollLeft;
        };
        const observer = new ResizeObserver(measure);
        observer.observe(viewport.current);
        observer.observe(row.current);
        if (scrollbar.current) observer.observe(scrollbar.current);
        measure();
        return () => observer.disconnect();
    }, []);

    return <Root {...props} className={`${className} filter-toolbar${actions.length ? ' has-filter-actions' : ''}`}>
        <div className="filter-toolbar-viewport" ref={viewport} onScroll={() => {
            if (scrollbar.current) scrollbar.current.scrollLeft = viewport.current.scrollLeft;
        }}>
            <div className="filter-toolbar-fields" ref={row}>{Children.toArray(fields)}</div>
        </div>
        {actions.length > 0 && <div className="filter-toolbar-actions">{Children.toArray(actions)}</div>}
        <div className="filter-toolbar-scrollbar" ref={scrollbar} hidden={!overflow}
            tabIndex={overflow ? 0 : -1} role="region" aria-label="Scroll search and filter fields"
            onScroll={() => { if (viewport.current) viewport.current.scrollLeft = scrollbar.current.scrollLeft; }}>
            <div style={{ width: trackWidth, height: 1 }} />
        </div>
    </Root>;
}
