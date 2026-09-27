import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { ArrowLeft } from 'lucide-react';

export function ShellBackButton({ onClick, label = 'Back', disabled = false }) {
    const [host, setHost] = useState(null);

    useEffect(() => {
        setHost(document.getElementById('shell-back-slot'));
    }, []);

    if (!host) return null;

    return createPortal(
        <button className="icon-button shell-back-button" type="button" onClick={onClick} disabled={disabled} aria-label={label} title={label}>
            <ArrowLeft size={18} />
        </button>,
        host,
    );
}
