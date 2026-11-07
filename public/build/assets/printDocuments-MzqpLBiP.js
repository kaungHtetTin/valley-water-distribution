import{d as e,l as t}from"./app-1VzMk0PD.js";var n=()=>window.ValleyRuntime?.api?.printSettings||`/api/settings/printing`,r=e=>String(e??``).replaceAll(`&`,`&amp;`).replaceAll(`<`,`&lt;`).replaceAll(`>`,`&gt;`).replaceAll(`"`,`&quot;`).replaceAll(`'`,`&#039;`),i=e=>e.paper_size===`80mm`?`80mm 297mm`:e.paper_size===`58mm`?`58mm 210mm`:`${e.paper_size||`A4`} ${e.orientation||`portrait`}`,a=(e=[])=>e.filter(e=>e?.[1]!==void 0&&e?.[1]!==null&&e?.[1]!==``).map(([e,t])=>`
    <div><dt>${r(e)}</dt><dd>${r(t)}</dd></div>`).join(``),o=e=>!e.columns?.length||!e.rows?.length?``:`<table><thead><tr>${e.columns.map(e=>`<th>${r(e)}</th>`).join(``)}</tr></thead><tbody>${e.rows.map(e=>`<tr>${e.map(e=>`<td>${r(e)}</td>`).join(``)}</tr>`).join(``)}</tbody>${e.total?`<tfoot><tr><th colspan="${Math.max(1,e.columns.length-1)}">${r(e.total[0])}</th><th>${r(e.total[1])}</th></tr></tfoot>`:``}</table>`,s=({company:e,setting:t,document:n,copyIndex:i})=>{let s=[e?.address,e?.city,e?.state].filter(Boolean).join(`, `),c=[e?.phone,e?.email].filter(Boolean).join(` · `),l=t.show_logo?e?.logo_url?`<img src="${r(e.logo_url)}" alt="">`:`<span class="logo-fallback">${r((e?.name||`Company`).split(/\s+/).map(e=>e[0]).join(``).slice(0,2).toUpperCase())}</span>`:``;return`<article class="document design-${r(t.design||`classic`)}">
        <header class="company-header">${l}<div><h1>${r(e?.name||`Valley Water Distribution`)}</h1>
            ${t.header_text?`<p>${r(t.header_text)}</p>`:``}
            ${t.show_address&&s?`<p>${r(s)}</p>`:``}
            ${t.show_contact&&c?`<p>${r(c)}</p>`:``}
            ${t.show_tax_number&&e?.tax_no?`<p>Tax: ${r(e.tax_no)}</p>`:``}
        </div></header>
        <section class="document-title"><div><small>DOCUMENT</small><strong>${r(t.label||n.title)}</strong></div><div><small>REFERENCE</small><strong>${r(n.reference||`-`)}</strong></div></section>
        <dl>${a(n.facts)}</dl>
        ${o(n)}
        ${t.show_notes&&n.notes?`<section class="notes"><strong>Notes</strong><p>${r(n.notes)}</p></section>`:``}
        ${t.show_signatures?`<footer class="signatures">${(n.signatures||[`Prepared by`,`Approved by`,`Received by`]).map(e=>`<span>${r(e)}</span>`).join(``)}</footer>`:``}
        ${t.footer_text?`<p class="footer-note">${r(t.footer_text)}</p>`:``}
        ${Number(t.copies||1)>1?`<small class="copy-number">Copy ${i+1} of ${t.copies}</small>`:``}
    </article>`},c=({company:e,setting:t,document:n})=>{let a=Array.from({length:Math.max(1,Number(t.copies||1))},(r,i)=>s({company:e,setting:t,document:n,copyIndex:i})).join(``),o=[`80mm`,`58mm`].includes(t.paper_size);return`<!doctype html><html><head><meta charset="utf-8"><title>${r(n.reference||t.label)}</title><style>
        @page { size: ${i(t)}; margin: ${Number(t.margin_mm??10)}mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #172033; background: #fff; font-family: "Segoe UI", Arial, sans-serif; font-size: ${o?`9px`:`11px`}; }
        .document { width: 100%; page-break-after: always; }
        .document:last-child { page-break-after: auto; }
        .company-header { display: flex; align-items: flex-start; gap: 12px; padding-bottom: 10px; border-bottom: 3px solid ${r(t.accent_color||`#0b84a5`)}; }
        .company-header img, .logo-fallback { width: ${o?`28px`:`44px`}; height: ${o?`28px`:`44px`}; object-fit: contain; border-radius: 7px; }
        .logo-fallback { display: grid; place-items: center; color: #fff; background: ${r(t.accent_color||`#0b84a5`)}; font-weight: 800; }
        h1 { margin: 0 0 2px; color: ${r(t.accent_color||`#0b84a5`)}; font-size: ${o?`14px`:`20px`}; }
        p { margin: 2px 0 0; }
        .company-header p { color: #667085; }
        .document-title { display: flex; justify-content: space-between; gap: 16px; margin: 12px 0; }
        .document-title > div:last-child { text-align: right; }
        small, dt { color: #667085; font-size: .78em; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
        .document-title strong { display: block; margin-top: 3px; font-size: 1.25em; }
        dl { display: grid; grid-template-columns: ${o?`1fr`:`repeat(2, minmax(0, 1fr))`}; column-gap: 24px; row-gap: ${t.design===`compact`?`5px`:`9px`}; margin: 0 0 14px; }
        dl > div { min-width: 0; padding: 2px 0; }
        dd { margin: 2px 0 0; font-weight: 700; overflow-wrap: anywhere; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { padding: ${t.design===`compact`?`5px`:`7px`} 5px; border-bottom: 1px solid #d8dee8; text-align: right; overflow-wrap: anywhere; }
        th:first-child, td:first-child { width: 44%; text-align: left; overflow-wrap: anywhere; }
        td:first-child { white-space: pre-line; }
        thead { color: ${t.design===`minimal`?`#172033`:`#fff`}; background: ${t.design===`minimal`?`#f2f4f7`:r(t.accent_color||`#0b84a5`)}; }
        tfoot th { border-top: 2px solid ${r(t.accent_color||`#0b84a5`)}; }
        .notes { margin-top: 14px; }
        .notes p { white-space: pre-wrap; }
        tr, .signatures { break-inside: avoid; }
        .signatures { display: grid; grid-template-columns: repeat(${o?`1`:n.signatures?.length||3}, 1fr); gap: ${o?`24px`:`18px`}; margin-top: 42px; }
        .signatures span { padding-top: 5px; border-top: 1px solid #667085; text-align: center; }
        .footer-note { margin-top: 18px; color: #667085; text-align: center; }
        .copy-number { display: block; margin-top: 8px; text-align: right; }
    </style></head><body>${a}<script>window.addEventListener('load',()=>setTimeout(()=>window.print(),120));<\/script></body></html>`};async function l(i,a,o={}){if(t())try{let[t,r]=await Promise.all([window.axios.get(n()),typeof a==`function`?a():Promise.resolve(a)]),{data:s}=t,l=s.data.documents?.[i];if(!l)throw Error(`Printing settings are unavailable for this document.`);let u={...l,...o};return await e(c({company:s.data.company,setting:u,document:r})),{company:s.data.company,setting:u}}catch(e){return window.alert(e.response?.data?.message||e.message||`Unable to print this document.`),null}let s=window.open(``,`_blank`);if(!s)return window.alert(`Allow pop-ups to print this document.`),null;s.opener=null,s.document.write(`<!doctype html><title>Preparing print</title><p style="font:14px Segoe UI;padding:24px">Preparing document…</p>`);try{let[e,t]=await Promise.all([window.axios.get(n()),typeof a==`function`?a():Promise.resolve(a)]),{data:r}=e,l=r.data.documents?.[i];if(!l)throw Error(`Printing settings are unavailable for this document.`);let u={...l,...o};return s.document.open(),s.document.write(c({company:r.data.company,setting:u,document:t})),s.document.close(),{company:r.data.company,setting:u}}catch(e){return s.document.open(),s.document.write(`<p style="font:14px Segoe UI;padding:24px">${r(e.response?.data?.message||e.message||`Unable to prepare this document.`)}</p>`),s.document.close(),null}}export{l as t};