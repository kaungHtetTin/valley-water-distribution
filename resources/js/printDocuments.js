const printingApi = () => window.ValleyRuntime?.api?.printSettings || '/api/settings/printing';

const escapeHtml = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

const paperSize = (setting) => {
    if (setting.paper_size === '80mm') return '80mm 297mm';
    if (setting.paper_size === '58mm') return '58mm 210mm';
    return `${setting.paper_size || 'A4'} ${setting.orientation || 'portrait'}`;
};

const renderFacts = (facts = []) => facts.filter((fact) => fact?.[1] !== undefined && fact?.[1] !== null && fact?.[1] !== '').map(([label, value]) => `
    <div><dt>${escapeHtml(label)}</dt><dd>${escapeHtml(value)}</dd></div>`).join('');

const renderTable = (document) => {
    if (!document.columns?.length || !document.rows?.length) return '';
    const head = document.columns.map((column) => `<th>${escapeHtml(column)}</th>`).join('');
    const rows = document.rows.map((row) => `<tr>${row.map((value) => `<td>${escapeHtml(value)}</td>`).join('')}</tr>`).join('');
    const total = document.total ? `<tfoot><tr><th colspan="${Math.max(1, document.columns.length - 1)}">${escapeHtml(document.total[0])}</th><th>${escapeHtml(document.total[1])}</th></tr></tfoot>` : '';
    return `<table><thead><tr>${head}</tr></thead><tbody>${rows}</tbody>${total}</table>`;
};

const renderCopy = ({ company, setting, document, copyIndex }) => {
    const address = [company?.address, company?.city, company?.state].filter(Boolean).join(', ');
    const contact = [company?.phone, company?.email].filter(Boolean).join(' · ');
    const logo = setting.show_logo ? (company?.logo_url
        ? `<img src="${escapeHtml(company.logo_url)}" alt="">`
        : `<span class="logo-fallback">${escapeHtml((company?.name || 'Company').split(/\s+/).map((word) => word[0]).join('').slice(0, 2).toUpperCase())}</span>`) : '';

    return `<article class="document design-${escapeHtml(setting.design || 'classic')}">
        <header class="company-header">${logo}<div><h1>${escapeHtml(company?.name || 'Valley Water Distribution')}</h1>
            ${setting.header_text ? `<p>${escapeHtml(setting.header_text)}</p>` : ''}
            ${setting.show_address && address ? `<p>${escapeHtml(address)}</p>` : ''}
            ${setting.show_contact && contact ? `<p>${escapeHtml(contact)}</p>` : ''}
            ${setting.show_tax_number && company?.tax_no ? `<p>Tax: ${escapeHtml(company.tax_no)}</p>` : ''}
        </div></header>
        <section class="document-title"><div><small>DOCUMENT</small><strong>${escapeHtml(setting.label || document.title)}</strong></div><div><small>REFERENCE</small><strong>${escapeHtml(document.reference || '-')}</strong></div></section>
        <dl>${renderFacts(document.facts)}</dl>
        ${renderTable(document)}
        ${setting.show_notes && document.notes ? `<section class="notes"><strong>Notes</strong><p>${escapeHtml(document.notes)}</p></section>` : ''}
        ${setting.show_signatures ? `<footer class="signatures">${(document.signatures || ['Prepared by', 'Approved by', 'Received by']).map((label) => `<span>${escapeHtml(label)}</span>`).join('')}</footer>` : ''}
        ${setting.footer_text ? `<p class="footer-note">${escapeHtml(setting.footer_text)}</p>` : ''}
        ${Number(setting.copies || 1) > 1 ? `<small class="copy-number">Copy ${copyIndex + 1} of ${setting.copies}</small>` : ''}
    </article>`;
};

const renderHtml = ({ company, setting, document }) => {
    const copies = Array.from({ length: Math.max(1, Number(setting.copies || 1)) }, (_, index) => renderCopy({ company, setting, document, copyIndex: index })).join('');
    const compact = ['80mm', '58mm'].includes(setting.paper_size);
    return `<!doctype html><html><head><meta charset="utf-8"><title>${escapeHtml(document.reference || setting.label)}</title><style>
        @page { size: ${paperSize(setting)}; margin: ${Number(setting.margin_mm ?? 10)}mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #172033; background: #fff; font-family: "Segoe UI", Arial, sans-serif; font-size: ${compact ? '9px' : '11px'}; }
        .document { width: 100%; page-break-after: always; }
        .document:last-child { page-break-after: auto; }
        .company-header { display: flex; align-items: flex-start; gap: 12px; padding-bottom: 10px; border-bottom: 3px solid ${escapeHtml(setting.accent_color || '#0b84a5')}; }
        .company-header img, .logo-fallback { width: ${compact ? '28px' : '44px'}; height: ${compact ? '28px' : '44px'}; object-fit: contain; border-radius: 7px; }
        .logo-fallback { display: grid; place-items: center; color: #fff; background: ${escapeHtml(setting.accent_color || '#0b84a5')}; font-weight: 800; }
        h1 { margin: 0 0 2px; color: ${escapeHtml(setting.accent_color || '#0b84a5')}; font-size: ${compact ? '14px' : '20px'}; }
        p { margin: 2px 0 0; }
        .company-header p { color: #667085; }
        .document-title { display: flex; justify-content: space-between; gap: 16px; margin: 12px 0; }
        .document-title > div:last-child { text-align: right; }
        small, dt { color: #667085; font-size: .78em; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
        .document-title strong { display: block; margin-top: 3px; font-size: 1.25em; }
        dl { display: grid; grid-template-columns: ${compact ? '1fr' : 'repeat(2, minmax(0, 1fr))'}; column-gap: 24px; row-gap: ${setting.design === 'compact' ? '5px' : '9px'}; margin: 0 0 14px; }
        dl > div { min-width: 0; padding: 2px 0; }
        dd { margin: 2px 0 0; font-weight: 700; overflow-wrap: anywhere; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { padding: ${setting.design === 'compact' ? '5px' : '7px'} 5px; border-bottom: 1px solid #d8dee8; text-align: right; }
        th:first-child, td:first-child { width: 44%; text-align: left; overflow-wrap: anywhere; }
        thead { color: ${setting.design === 'minimal' ? '#172033' : '#fff'}; background: ${setting.design === 'minimal' ? '#f2f4f7' : escapeHtml(setting.accent_color || '#0b84a5')}; }
        tfoot th { border-top: 2px solid ${escapeHtml(setting.accent_color || '#0b84a5')}; }
        .notes { margin-top: 14px; }
        .notes p { white-space: pre-wrap; }
        .signatures { display: grid; grid-template-columns: repeat(${compact ? '1' : '3'}, 1fr); gap: ${compact ? '24px' : '18px'}; margin-top: 42px; }
        .signatures span { padding-top: 5px; border-top: 1px solid #667085; text-align: center; }
        .footer-note { margin-top: 18px; color: #667085; text-align: center; }
        .copy-number { display: block; margin-top: 8px; text-align: right; }
    </style></head><body>${copies}<script>window.addEventListener('load',()=>setTimeout(()=>window.print(),120));<\/script></body></html>`;
};

export async function printConfiguredDocument(type, document) {
    const printWindow = window.open('', '_blank');
    if (!printWindow) {
        window.alert('Allow pop-ups to print this document.');
        return;
    }
    printWindow.opener = null;
    printWindow.document.write('<!doctype html><title>Preparing print</title><p style="font:14px Segoe UI;padding:24px">Preparing document…</p>');
    try {
        const [settingsResponse, resolvedDocument] = await Promise.all([
            window.axios.get(printingApi()),
            typeof document === 'function' ? document() : Promise.resolve(document),
        ]);
        const { data } = settingsResponse;
        const setting = data.data.documents?.[type];
        if (!setting) throw new Error('Printing settings are unavailable for this document.');
        printWindow.document.open();
        printWindow.document.write(renderHtml({ company: data.data.company, setting, document: resolvedDocument }));
        printWindow.document.close();
    } catch (error) {
        printWindow.document.open();
        printWindow.document.write(`<p style="font:14px Segoe UI;padding:24px">${escapeHtml(error.response?.data?.message || error.message || 'Unable to prepare this document.')}</p>`);
        printWindow.document.close();
    }
}
