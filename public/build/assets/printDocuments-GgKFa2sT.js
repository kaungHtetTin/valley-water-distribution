var e=()=>window.ValleyRuntime?.api?.printSettings||`/api/settings/printing`,t=e=>String(e??``).replaceAll(`&`,`&amp;`).replaceAll(`<`,`&lt;`).replaceAll(`>`,`&gt;`).replaceAll(`"`,`&quot;`).replaceAll(`'`,`&#039;`),n=e=>e.paper_size===`80mm`?`80mm 297mm`:e.paper_size===`58mm`?`58mm 210mm`:`${e.paper_size||`A4`} ${e.orientation||`portrait`}`,r=(e=[])=>e.filter(e=>e?.[1]!==void 0&&e?.[1]!==null&&e?.[1]!==``).map(([e,n])=>`
    <div><dt>${t(e)}</dt><dd>${t(n)}</dd></div>`).join(``),i=e=>!e.columns?.length||!e.rows?.length?``:`<table><thead><tr>${e.columns.map(e=>`<th>${t(e)}</th>`).join(``)}</tr></thead><tbody>${e.rows.map(e=>`<tr>${e.map(e=>`<td>${t(e)}</td>`).join(``)}</tr>`).join(``)}</tbody>${e.total?`<tfoot><tr><th colspan="${Math.max(1,e.columns.length-1)}">${t(e.total[0])}</th><th>${t(e.total[1])}</th></tr></tfoot>`:``}</table>`,a=({company:e,setting:n,document:a,copyIndex:o})=>{let s=[e?.address,e?.city,e?.state].filter(Boolean).join(`, `),c=[e?.phone,e?.email].filter(Boolean).join(` · `),l=n.show_logo?e?.logo_url?`<img src="${t(e.logo_url)}" alt="">`:`<span class="logo-fallback">${t((e?.name||`Company`).split(/\s+/).map(e=>e[0]).join(``).slice(0,2).toUpperCase())}</span>`:``;return`<article class="document design-${t(n.design||`classic`)}">
        <header class="company-header">${l}<div><h1>${t(e?.name||`Valley Water Distribution`)}</h1>
            ${n.header_text?`<p>${t(n.header_text)}</p>`:``}
            ${n.show_address&&s?`<p>${t(s)}</p>`:``}
            ${n.show_contact&&c?`<p>${t(c)}</p>`:``}
            ${n.show_tax_number&&e?.tax_no?`<p>Tax: ${t(e.tax_no)}</p>`:``}
        </div></header>
        <section class="document-title"><div><small>DOCUMENT</small><strong>${t(n.label||a.title)}</strong></div><div><small>REFERENCE</small><strong>${t(a.reference||`-`)}</strong></div></section>
        <dl>${r(a.facts)}</dl>
        ${i(a)}
        ${n.show_notes&&a.notes?`<section class="notes"><strong>Notes</strong><p>${t(a.notes)}</p></section>`:``}
        ${n.show_signatures?`<footer class="signatures">${(a.signatures||[`Prepared by`,`Approved by`,`Received by`]).map(e=>`<span>${t(e)}</span>`).join(``)}</footer>`:``}
        ${n.footer_text?`<p class="footer-note">${t(n.footer_text)}</p>`:``}
        ${Number(n.copies||1)>1?`<small class="copy-number">Copy ${o+1} of ${n.copies}</small>`:``}
    </article>`},o=({company:e,setting:r,document:i})=>{let o=Array.from({length:Math.max(1,Number(r.copies||1))},(t,n)=>a({company:e,setting:r,document:i,copyIndex:n})).join(``),s=[`80mm`,`58mm`].includes(r.paper_size);return`<!doctype html><html><head><meta charset="utf-8"><title>${t(i.reference||r.label)}</title><style>
        @page { size: ${n(r)}; margin: ${Number(r.margin_mm??10)}mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #172033; background: #fff; font-family: "Segoe UI", Arial, sans-serif; font-size: ${s?`9px`:`11px`}; }
        .document { width: 100%; page-break-after: always; }
        .document:last-child { page-break-after: auto; }
        .company-header { display: flex; align-items: flex-start; gap: 12px; padding-bottom: 10px; border-bottom: 3px solid ${t(r.accent_color||`#0b84a5`)}; }
        .company-header img, .logo-fallback { width: ${s?`28px`:`44px`}; height: ${s?`28px`:`44px`}; object-fit: contain; border-radius: 7px; }
        .logo-fallback { display: grid; place-items: center; color: #fff; background: ${t(r.accent_color||`#0b84a5`)}; font-weight: 800; }
        h1 { margin: 0 0 2px; color: ${t(r.accent_color||`#0b84a5`)}; font-size: ${s?`14px`:`20px`}; }
        p { margin: 2px 0 0; }
        .company-header p { color: #667085; }
        .document-title { display: flex; justify-content: space-between; gap: 16px; margin: 12px 0; }
        .document-title > div:last-child { text-align: right; }
        small, dt { color: #667085; font-size: .78em; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
        .document-title strong { display: block; margin-top: 3px; font-size: 1.25em; }
        dl { display: grid; grid-template-columns: ${s?`1fr`:`repeat(2, minmax(0, 1fr))`}; column-gap: 24px; row-gap: ${r.design===`compact`?`5px`:`9px`}; margin: 0 0 14px; }
        dl > div { min-width: 0; padding: 2px 0; }
        dd { margin: 2px 0 0; font-weight: 700; overflow-wrap: anywhere; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { padding: ${r.design===`compact`?`5px`:`7px`} 5px; border-bottom: 1px solid #d8dee8; text-align: right; }
        th:first-child, td:first-child { width: 44%; text-align: left; overflow-wrap: anywhere; }
        thead { color: ${r.design===`minimal`?`#172033`:`#fff`}; background: ${r.design===`minimal`?`#f2f4f7`:t(r.accent_color||`#0b84a5`)}; }
        tfoot th { border-top: 2px solid ${t(r.accent_color||`#0b84a5`)}; }
        .notes { margin-top: 14px; }
        .notes p { white-space: pre-wrap; }
        .signatures { display: grid; grid-template-columns: repeat(${s?`1`:`3`}, 1fr); gap: ${s?`24px`:`18px`}; margin-top: 42px; }
        .signatures span { padding-top: 5px; border-top: 1px solid #667085; text-align: center; }
        .footer-note { margin-top: 18px; color: #667085; text-align: center; }
        .copy-number { display: block; margin-top: 8px; text-align: right; }
    </style></head><body>${o}<script>window.addEventListener('load',()=>setTimeout(()=>window.print(),120));<\/script></body></html>`};async function s(n,r){let i=window.open(``,`_blank`);if(!i){window.alert(`Allow pop-ups to print this document.`);return}i.opener=null,i.document.write(`<!doctype html><title>Preparing print</title><p style="font:14px Segoe UI;padding:24px">Preparing document…</p>`);try{let[t,a]=await Promise.all([window.axios.get(e()),typeof r==`function`?r():Promise.resolve(r)]),{data:s}=t,c=s.data.documents?.[n];if(!c)throw Error(`Printing settings are unavailable for this document.`);i.document.open(),i.document.write(o({company:s.data.company,setting:c,document:a})),i.document.close()}catch(e){i.document.open(),i.document.write(`<p style="font:14px Segoe UI;padding:24px">${t(e.response?.data?.message||e.message||`Unable to prepare this document.`)}</p>`),i.document.close()}}export{s as t};