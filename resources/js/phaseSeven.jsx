import { AlertCircle, ArrowDownLeft, ArrowLeft, ArrowUpRight, BadgeCheck, Banknote, BookOpen, CalendarDays, CircleDollarSign, CreditCard, FileText, Plus, RefreshCw, RotateCcw, Save, Search, TrendingUp, WalletCards, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { DetailPage, DetailPanel } from './components/DetailPage';

const base = () => window.ValleyRuntime?.api?.finance || '/api/finance';
const mobileBase = () => window.ValleyRuntime?.api?.mobileFinance || '/api/mobile/finance';
const today = () => new Date().toISOString().slice(0, 10);
const money = (value) => `${new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(Number(value || 0))} MMK`;
const label = (value) => String(value || '-').replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());
const message = (error) => error.response?.data?.message || Object.values(error.response?.data?.errors || {})[0]?.[0] || 'Something went wrong.';
const statusClass = (status) => status === 'approved' ? 'success' : status === 'submitted' ? 'warning' : status === 'rejected' ? 'danger' : 'neutral';
const my = {
    Collections: 'ငွေကောက်ခံမှုများ',
    'Outdoor employee collections': 'ပြင်ပဝန်ထမ်း ငွေကောက်ခံမှုများ',
    'Customer receivables': 'ဖောက်သည် ရရန်ငွေများ',
    'Supplier ledger': 'ပေးသွင်းသူ စာရင်း',
    'Cash book': 'ငွေသားစာရင်း',
    'Bank book': 'ဘဏ်စာရင်း',
    'Daily expenses': 'နေ့စဉ်အသုံးစရိတ်များ',
    'Outdoor employee expenses': 'ပြင်ပဝန်ထမ်း အသုံးစရိတ်များ',
    'Profit & loss': 'အမြတ် / အရှုံး',
    'Ledger & payments': 'စာရင်းနှင့် ငွေပေးချေမှုများ',
    'Outdoor expenses': 'ပြင်ပအသုံးစရိတ်များ',
    'Field finance': 'ကွင်းဆင်းဘဏ္ဍာရေး',
    'My account': 'ကျွန်ုပ်၏စာရင်း',
    New: 'အသစ်',
    'New collection': 'ငွေကောက်ခံမှုအသစ်',
    'New expense': 'အသုံးစရိတ်အသစ်',
    'Ledger entry': 'စာရင်းသွင်းရန်',
};
const tx = (locale, value) => locale === 'my' ? my[value] || value : value;

function Heading({ eyebrow = 'Phase 7 · Finance', title, hint, action }) {
    const locale = window.localStorage.getItem('valley-locale') || 'en';
    return <div className="master-heading"><div><p className="eyebrow">{eyebrow}</p><h1>{tx(locale, title)}</h1><span className="muted">{hint}</span></div>{action}</div>;
}

function Metric({ icon: Icon, label: text, value, hint }) {
    return <article className="metric finance-metric"><span><Icon size={16} />{text}</span><strong>{value}</strong><small>{hint}</small></article>;
}

function State({ loading, text }) {
    return <div className="workspace-state">{loading ? <RefreshCw className="spin" size={22} /> : <AlertCircle size={22} />}<strong>{text}</strong></div>;
}

function Pagination({ meta, page, setPage }) {
    if (!meta?.last_page) return null;
    return <div className="master-pagination"><span>{meta.total} records · Page {meta.current_page} of {meta.last_page}</span><div><button disabled={page <= 1} onClick={() => setPage(page - 1)}>‹</button><button disabled={page >= meta.last_page} onClick={() => setPage(page + 1)}>›</button></div></div>;
}

function Field({ children, label: text, wide = false }) {
    return <label className={`master-field ${wide ? 'wide' : ''}`}>{text}{children}</label>;
}

function Modal({ title, children, saving, onClose, onSubmit, submitLabel = 'Save record' }) {
    return <div className="modal-backdrop" onMouseDown={(e) => e.target === e.currentTarget && onClose()}><form className="master-dialog finance-dialog" onSubmit={onSubmit}><header><h2>{title}</h2><button className="icon-button" type="button" aria-label="Close" onClick={onClose}><X size={16} /></button></header><div className="master-form-body"><div className="master-form-grid">{children}</div></div><footer><button className="button" type="button" onClick={onClose}>Cancel</button><button className="button primary" disabled={saving}><Save size={15} />{submitLabel}</button></footer></form></div>;
}

function CustomerCombobox({ customers, value, onChange }) {
    const selected = customers.find((customer) => String(customer.id) === String(value));
    const [query, setQuery] = useState(selected ? `${selected.code} · ${selected.shop_name}` : '');
    const [open, setOpen] = useState(false);
    const [activeIndex, setActiveIndex] = useState(0);
    const rootRef = useRef(null);
    const inputRef = useRef(null);
    const normalizedQuery = query.trim().toLocaleLowerCase();
    const filtered = customers.filter((customer) => !normalizedQuery || `${customer.code} ${customer.shop_name}`.toLocaleLowerCase().includes(normalizedQuery)).slice(0, 20);

    useEffect(() => {
        const close = (event) => {
            if (!rootRef.current?.contains(event.target)) setOpen(false);
        };
        document.addEventListener('mousedown', close);
        return () => document.removeEventListener('mousedown', close);
    }, []);

    const choose = (customer) => {
        onChange(String(customer.id));
        setQuery(`${customer.code} · ${customer.shop_name}`);
        inputRef.current?.setCustomValidity('');
        setOpen(false);
    };

    const keyDown = (event) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setOpen(true);
            setActiveIndex((index) => Math.min(index + 1, Math.max(filtered.length - 1, 0)));
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActiveIndex((index) => Math.max(index - 1, 0));
        } else if (event.key === 'Enter' && open && filtered[activeIndex]) {
            event.preventDefault();
            choose(filtered[activeIndex]);
        } else if (event.key === 'Escape') {
            setOpen(false);
        }
    };

    return <div className="customer-combobox" ref={rootRef}>
        <div className="customer-combobox-input">
            <Search size={15} />
            <input
                ref={inputRef}
                role="combobox"
                aria-autocomplete="list"
                aria-controls="collection-customer-options"
                aria-expanded={open}
                required
                value={query}
                placeholder="Search customer code or shop name"
                onFocus={() => setOpen(true)}
                onKeyDown={keyDown}
                onInvalid={(event) => event.currentTarget.setCustomValidity(value ? '' : 'Select a customer from the results.')}
                onChange={(event) => {
                    event.currentTarget.setCustomValidity('');
                    setQuery(event.target.value);
                    onChange('');
                    setActiveIndex(0);
                    setOpen(true);
                }}
            />
        </div>
        {open && <div className="customer-combobox-options" id="collection-customer-options" role="listbox">
            {filtered.length ? filtered.map((customer, index) => <button className={index === activeIndex ? 'is-active' : ''} type="button" role="option" aria-selected={String(customer.id) === String(value)} key={customer.id} onMouseDown={(event) => event.preventDefault()} onClick={() => choose(customer)}><span><strong>{customer.shop_name}</strong><small>{customer.code}{customer.outstanding !== undefined ? ` · Due ${money(customer.outstanding)}` : ''}</small></span>{String(customer.id) === String(value) && <BadgeCheck size={16} />}</button>) : <p>No customers match “{query}”.</p>}
            {customers.length > 20 && !normalizedQuery && <small className="customer-combobox-hint">Type to search {customers.length} customers</small>}
        </div>}
    </div>;
}

function MobileCollectionFields({ form, setForm, customers, selectedCustomer }) {
    const outstanding = Number(selectedCustomer?.outstanding || 0);
    const pending = Number(selectedCustomer?.pending_collection || 0);
    const collectible = Number(selectedCustomer?.collectible || 0);

    return <>
        <Field label="Customer" wide>
            <CustomerCombobox customers={customers} value={form.customer_id} onChange={(customerId) => {
                const customer = customers.find((item) => String(item.id) === String(customerId));
                setForm((current) => ({ ...current, customer_id: customerId, delivery_id: customer?.active_delivery_id || '', amount: '' }));
            }} />
        </Field>
        {selectedCustomer && <section className="mobile-collection-customer span-2" aria-label="Selected customer balance">
            <span><small>Available to collect</small><strong>{money(collectible)}</strong></span>
            <span><small>Customer</small><strong>{selectedCustomer.contact_name || selectedCustomer.shop_name}</strong></span>
            <span><small>Phone</small><strong>{selectedCustomer.phone || '-'}</strong></span>
            {pending > 0 && <span className="wide mobile-collection-pending"><small>Awaiting Office review</small><strong>{money(pending)} of {money(outstanding)}</strong></span>}
            <span className="wide"><small>Address</small><strong>{selectedCustomer.address || 'No address recorded'}</strong></span>
            {selectedCustomer.active_delivery_code && <span className="wide mobile-collection-delivery"><small>Linked trip</small><strong>{selectedCustomer.active_delivery_code}</strong></span>}
        </section>}
        <Field label="Collection date"><input required type="date" value={form.collection_date} onChange={(event) => setForm((current) => ({ ...current, collection_date: event.target.value }))} /></Field>
        <Field label="Cash received (MMK)">
            <div className="mobile-collection-amount">
                <input required type="number" inputMode="numeric" min="1" max={collectible || undefined} disabled={!selectedCustomer} value={form.amount} onChange={(event) => setForm((current) => ({ ...current, amount: event.target.value }))} />
                <button type="button" disabled={!collectible} onClick={() => setForm((current) => ({ ...current, amount: String(collectible) }))}>Full</button>
            </div>
        </Field>
        <div className="mobile-cash-method span-2"><Banknote size={17} /><span><strong>Cash collection</strong><small>Office approval will post this receipt to the cash book.</small></span></div>
        <Field label="Receipt reference"><input value={form.reference_no} placeholder="Optional receipt number" onChange={(event) => setForm((current) => ({ ...current, reference_no: event.target.value }))} /></Field>
        <Field label="Notes" wide><textarea rows="2" value={form.notes} onChange={(event) => setForm((current) => ({ ...current, notes: event.target.value }))} /></Field>
    </>;
}

function MobileFieldCollectionsScreen({ appId }) {
    const emptyForm = () => ({ customer_id: '', delivery_id: '', collection_date: today(), amount: '', payment_method: 'cash', reference_no: '', notes: '' });
    const [state, setState] = useState({ loading: true, collections: [], summary: {}, error: '' });
    const [meta, setMeta] = useState({ customers: [] });
    const [form, setForm] = useState(emptyForm);
    const [open, setOpen] = useState(false);
    const [saving, setSaving] = useState(false);
    const [success, setSuccess] = useState('');
    const [refresh, setRefresh] = useState(0);
    const selectedCustomer = meta.customers.find((customer) => String(customer.id) === String(form.customer_id));

    useEffect(() => {
        let active = true;
        setState((current) => ({ ...current, loading: true, error: '' }));
        Promise.all([window.axios.get(mobileBase()), window.axios.get(`${mobileBase()}/meta`)]).then(([financeResponse, metaResponse]) => {
            if (!active) return;
            setState({ loading: false, ...financeResponse.data.data, error: '' });
            setMeta(metaResponse.data.data);
        }).catch((error) => active && setState((current) => ({ ...current, loading: false, error: message(error) })));
        return () => { active = false; };
    }, [refresh]);

    const submit = (event) => {
        event.preventDefault();
        const amount = Number(form.amount || 0);
        const collectible = Number(selectedCustomer?.collectible || 0);
        if (!selectedCustomer || amount <= 0 || amount > collectible) {
            setState((current) => ({ ...current, error: amount > collectible ? 'Cash received cannot exceed the balance available to collect.' : 'Select a customer and enter the cash received.' }));
            return;
        }
        setSaving(true);
        setState((current) => ({ ...current, error: '' }));
        window.axios.post(`${mobileBase()}/collections`, { ...form, payment_method: 'cash', delivery_id: form.delivery_id || null }).then(({ data }) => {
            setOpen(false);
            setForm(emptyForm());
            setSuccess(data.message);
            setRefresh((current) => current + 1);
        }).catch((error) => setState((current) => ({ ...current, error: message(error) }))).finally(() => setSaving(false));
    };

    if (state.loading) return <State loading text="Loading cash collections" />;
    const items = state.collections || [];

    return <div className="mobile-master-stack mobile-finance mobile-field-collections">
        <div className="mobile-master-heading">
            <div><p className="eyebrow">Field finance</p><h1>Cash collections</h1><span className="muted">Collect customer credit balances on your assigned route.</span></div>
            <button className="button primary" type="button" onClick={() => { setSuccess(''); setState((current) => ({ ...current, error: '' })); setOpen(true); }}><Plus size={15} />Collect</button>
        </div>
        {success && <p className="inline-success"><BadgeCheck size={14} />{success}</p>}
        {state.error && <p className="inline-error"><AlertCircle size={14} />{state.error}</p>}
        <div className="mobile-finance-summary">
            <article><span>Cash submitted</span><strong>{money(state.summary?.collections_amount)}</strong></article>
            <article><span>Pending Office review</span><strong>{state.summary?.pending_count || 0}</strong></article>
        </div>
        <section className="mobile-master-section">
            <div className="mobile-section-heading"><div><h2>My collection history</h2><small>{items.length} receipts</small></div></div>
            {!items.length ? <div className="workspace-state compact"><WalletCards size={20} /><strong>No cash collections yet.</strong></div> : <div className="mobile-finance-list">{items.map((item) => <article key={item.id}>
                <span className="finance-entry-icon collection"><Banknote size={15} /></span>
                <span><strong>{item.shop_name}</strong><small>{item.code} · {item.collection_date}</small></span>
                <span><strong>{money(item.amount)}</strong><small className={statusClass(item.status)}>{label(item.status)}</small></span>
            </article>)}</div>}
        </section>
        {open && <Modal title={`${appId === 'driver' ? 'Driver' : 'Sales'} cash collection`} saving={saving} onClose={() => setOpen(false)} onSubmit={submit} submitLabel="Submit cash"><MobileCollectionFields form={form} setForm={setForm} customers={meta.customers || []} selectedCustomer={selectedCustomer} /></Modal>}
    </div>;
}

function ReviewButtons({ item, canManage, endpoint, refresh, setError }) {
    if (!canManage || item.status !== 'submitted') return null;
    const review = (status) => window.axios.post(`${base()}/${endpoint}/${item.id}/review`, { status }).then(refresh).catch((error) => setError(message(error)));
    return <div className="row-actions"><button title="Approve" aria-label={`Approve ${item.code}`} onClick={() => review('approved')}><BadgeCheck size={15} /></button><button className="danger" title="Reject" aria-label={`Reject ${item.code}`} onClick={() => review('rejected')}><X size={15} /></button></div>;
}

function LegacyFinanceCollectionsScreen({ outdoor = false, canManage = false, locale = 'en' }) {
    const [filters, setFilters] = useState({ search: '', status: '', page: 1 });
    const [state, setState] = useState({ loading: true, items: [], summary: {}, meta: {}, error: '' });
    const [meta, setMeta] = useState({ customers: [], invoices: [], employees: [] });
    const [open, setOpen] = useState(false); const [saving, setSaving] = useState(false); const [refresh, setRefresh] = useState(0);
    const [form, setForm] = useState({ customer_id: '', invoice_id: '', employee_id: '', collection_date: today(), amount: '', payment_method: 'cash', reference_no: '', notes: '' });
    const reload = () => setRefresh((v) => v + 1);
    useEffect(() => { window.axios.get(`${base()}/meta`).then(({ data }) => setMeta(data.data)).catch(() => {}); }, []);
    useEffect(() => { let live = true; setState((s) => ({ ...s, loading: true, error: '' })); window.axios.get(`${base()}/collections`, { params: { ...filters, outdoor: outdoor ? 1 : undefined } }).then(({ data }) => live && setState({ loading: false, ...data.data, error: '' })).catch((error) => live && setState((s) => ({ ...s, loading: false, error: message(error) }))); return () => { live = false; }; }, [filters, outdoor, refresh]);
    const invoices = meta.invoices.filter((item) => String(item.customer_id) === String(form.customer_id));
    const save = (e) => { e.preventDefault(); setSaving(true); const payload = { ...form, invoice_id: form.invoice_id || null, employee_id: form.employee_id || null }; window.axios.post(`${base()}/collections`, payload).then(() => { setOpen(false); reload(); }).catch((error) => setState((s) => ({ ...s, error: message(error) }))).finally(() => setSaving(false)); };
    const title = outdoor ? 'Outdoor employee collections' : 'Payments';
    return <section className="page finance-page"><Heading title={title} hint={outdoor ? 'Review route and delivery collections submitted by field employees.' : 'Record customer payments and post approved receipts to the cash or bank book.'} action={canManage && <button className="button primary" onClick={() => setOpen(true)}><Plus size={15} />New collection</button>} /><div className="metrics finance-metrics"><Metric icon={WalletCards} label="Collection value" value={money(state.summary.total_amount)} hint={`${state.summary.records_count || 0} records`} /><Metric icon={BadgeCheck} label="Approved" value={money(state.summary.approved_amount)} hint="Posted to books" /><Metric icon={CalendarDays} label="Submitted" value={money(state.summary.submitted_amount)} hint="Waiting for review" /><Metric icon={CircleDollarSign} label="Average receipt" value={money((state.summary.total_amount || 0) / Math.max(state.summary.records_count || 0, 1))} hint="Across current view" /></div><section className="master-panel"><div className="master-toolbar"><label className="master-search"><Search size={14} /><input placeholder="Search code, customer or employee" value={filters.search} onChange={(e) => setFilters((v) => ({ ...v, search: e.target.value, page: 1 }))} /></label><select value={filters.status} onChange={(e) => setFilters((v) => ({ ...v, status: e.target.value, page: 1 }))}><option value="">All statuses</option><option value="submitted">Submitted</option><option value="approved">Approved</option><option value="rejected">Rejected</option></select><button className="button" onClick={reload}><RefreshCw size={14} />Refresh</button></div>{state.error && <p className="inline-error"><AlertCircle size={14} />{state.error}</p>}{state.loading ? <State loading text="Loading collections" /> : !state.items.length ? <State text="No collections match this view." /> : <><div className="master-table-wrap"><table className="master-table finance-table"><thead><tr><th>Receipt</th><th>Customer / invoice</th><th>Collector</th><th>Method</th><th className="numeric">Amount</th><th>Status</th><th></th></tr></thead><tbody>{state.items.map((item) => <tr key={item.id}><td><strong>{item.code}</strong><span className="muted">{item.collection_date}</span></td><td><strong>{item.shop_name}</strong><span className="muted">{item.invoice_code || 'Customer balance'}</span></td><td>{item.employee_name || 'Office'}<span className="muted">{label(item.source_app)}</span></td><td>{label(item.payment_method)}<span className="muted">{item.reference_no || 'No reference'}</span></td><td className="numeric"><strong>{money(item.amount)}</strong></td><td><span className={`status ${statusClass(item.status)}`}>{label(item.status)}</span></td><td><ReviewButtons item={item} canManage={canManage} endpoint="collections" refresh={reload} setError={(error) => setState((s) => ({ ...s, error }))} /></td></tr>)}</tbody></table></div><Pagination meta={state.meta} page={filters.page} setPage={(page) => setFilters((v) => ({ ...v, page }))} /></>}</section>{open && <Modal title="Record customer collection" saving={saving} onClose={() => setOpen(false)} onSubmit={save}><Field label="Customer"><select required value={form.customer_id} onChange={(e) => setForm((v) => ({ ...v, customer_id: e.target.value, invoice_id: '' }))}><option value="">Select customer</option>{meta.customers.map((i) => <option key={i.id} value={i.id}>{i.label} · {money(i.outstanding)}</option>)}</select></Field><Field label="Invoice (optional)"><select value={form.invoice_id} onChange={(e) => setForm((v) => ({ ...v, invoice_id: e.target.value }))}><option value="">Apply to customer balance</option>{invoices.map((i) => <option key={i.id} value={i.id}>{i.label} · {money(i.outstanding)}</option>)}</select></Field>{outdoor && <Field label="Field employee"><select required value={form.employee_id} onChange={(e) => setForm((v) => ({ ...v, employee_id: e.target.value }))}><option value="">Select employee</option>{meta.employees.map((i) => <option key={i.id} value={i.id}>{i.label}</option>)}</select></Field>}<Field label="Collection date"><input required type="date" value={form.collection_date} onChange={(e) => setForm((v) => ({ ...v, collection_date: e.target.value }))} /></Field><Field label="Amount (MMK)"><input required type="number" min="1" value={form.amount} onChange={(e) => setForm((v) => ({ ...v, amount: e.target.value }))} /></Field><Field label="Payment book"><select value={form.payment_method} onChange={(e) => setForm((v) => ({ ...v, payment_method: e.target.value }))}><option value="cash">Cash</option><option value="bank">Bank</option></select></Field><Field label="Reference"><input value={form.reference_no} onChange={(e) => setForm((v) => ({ ...v, reference_no: e.target.value }))} /></Field><Field label="Notes" wide><textarea rows="3" value={form.notes} onChange={(e) => setForm((v) => ({ ...v, notes: e.target.value }))} /></Field></Modal>}</section>;
}

export function FinanceCollectionsScreen({ outdoor = false, canManage = false, locale = 'en' }) {
    if (outdoor) return <LegacyFinanceCollectionsScreen outdoor canManage={canManage} locale={locale} />;
    return <OfficePaymentsScreen canManage={canManage} />;
}

function OfficePaymentsScreen({ canManage }) {
    const [filters, setFilters] = useState({ search: '', status: '', page: 1 });
    const [state, setState] = useState({ loading: true, items: [], summary: {}, meta: {}, error: '' });
    const [meta, setMeta] = useState({ customers: [] });
    const [open, setOpen] = useState(false);
    const [saving, setSaving] = useState(false);
    const [refresh, setRefresh] = useState(0);
    const [form, setForm] = useState({ customer_id: '', collection_date: today(), amount: '', payment_method: 'cash', reference_no: '', notes: '' });

    useEffect(() => {
        window.axios.get(`${base()}/meta`).then(({ data }) => setMeta(data.data)).catch(() => {});
    }, []);

    useEffect(() => {
        let live = true;
        setState((current) => ({ ...current, loading: true, error: '' }));
        window.axios.get(`${base()}/collections`, { params: filters })
            .then(({ data }) => live && setState({ loading: false, ...data.data, error: '' }))
            .catch((error) => live && setState((current) => ({ ...current, loading: false, error: message(error) })));
        return () => { live = false; };
    }, [filters, refresh]);

    const save = (event) => {
        event.preventDefault();
        setSaving(true);
        window.axios.post(`${base()}/collections`, { ...form, invoice_id: null, employee_id: null })
            .then(() => {
                setOpen(false);
                setForm({ customer_id: '', collection_date: today(), amount: '', payment_method: 'cash', reference_no: '', notes: '' });
                setRefresh((value) => value + 1);
            }).catch((error) => setState((current) => ({ ...current, error: message(error) })))
            .finally(() => setSaving(false));
    };

    return <section className="page finance-page">
        <Heading title="Payments" hint="Record customer payments and review the complete payment history." action={canManage && <button className="button primary" type="button" onClick={() => setOpen(true)}><Plus size={15} />Record payment</button>} />
        <div className="metrics finance-metrics finance-metrics-three">
            <Metric icon={WalletCards} label="Payments" value={money(state.summary.total_amount)} hint={`${state.summary.records_count || 0} records`} />
            <Metric icon={BadgeCheck} label="Approved" value={money(state.summary.approved_amount)} hint="Posted to cash or bank" />
            <Metric icon={CalendarDays} label="Waiting review" value={money(state.summary.submitted_amount)} hint="Field payments" />
        </div>
        <section className="master-panel">
            <div className="master-toolbar">
                <label className="master-search"><Search size={14} /><input placeholder="Search payment or customer" value={filters.search} onChange={(event) => setFilters((current) => ({ ...current, search: event.target.value, page: 1 }))} /></label>
                <select aria-label="Payment status" value={filters.status} onChange={(event) => setFilters((current) => ({ ...current, status: event.target.value, page: 1 }))}><option value="">All statuses</option><option value="submitted">Waiting review</option><option value="approved">Approved</option><option value="rejected">Rejected</option></select>
                <button className="button" type="button" onClick={() => setRefresh((value) => value + 1)}><RefreshCw size={14} />Refresh</button>
            </div>
            {state.error && <p className="inline-error"><AlertCircle size={14} />{state.error}</p>}
            {state.loading ? <State loading text="Loading payments" /> : !state.items.length ? <State text="No payments match this view." /> : <><div className="master-table-wrap"><table className="master-table finance-table"><thead><tr><th>Payment</th><th>Customer</th><th>Received by</th><th>Method</th><th className="numeric">Amount</th><th>Status</th></tr></thead><tbody>{state.items.map((item) => <tr key={item.id}><td><strong>{item.code}</strong><span className="muted">{item.collection_date}</span></td><td><strong>{item.shop_name}</strong><span className="muted">{item.customer_code}</span></td><td>{item.employee_name || 'Office'}<span className="muted">{label(item.source_app)}</span></td><td>{label(item.payment_method)}<span className="muted">{item.reference_no || 'No reference'}</span></td><td className="numeric"><strong>{money(item.amount)}</strong></td><td><span className={`status ${statusClass(item.status)}`}>{label(item.status)}</span></td></tr>)}</tbody></table></div><Pagination meta={state.meta} page={filters.page} setPage={(page) => setFilters((current) => ({ ...current, page }))} /></>}
        </section>
        {open && <Modal title="Record customer payment" saving={saving} onClose={() => setOpen(false)} onSubmit={save} submitLabel="Record payment">
            <Field label="Customer"><CustomerCombobox customers={meta.customers || []} value={form.customer_id} onChange={(customerId) => setForm((current) => ({ ...current, customer_id: customerId }))} /></Field>
            <Field label="Payment date"><input required type="date" value={form.collection_date} onChange={(event) => setForm((current) => ({ ...current, collection_date: event.target.value }))} /></Field>
            <Field label="Amount (MMK)"><input required type="number" min="1" value={form.amount} onChange={(event) => setForm((current) => ({ ...current, amount: event.target.value }))} /></Field>
            <Field label="Paid into"><select value={form.payment_method} onChange={(event) => setForm((current) => ({ ...current, payment_method: event.target.value }))}><option value="cash">Cash</option><option value="bank">Bank</option></select></Field>
            <Field label="Reference (optional)"><input value={form.reference_no} onChange={(event) => setForm((current) => ({ ...current, reference_no: event.target.value }))} /></Field>
            <Field label="Notes (optional)" wide><textarea rows="3" value={form.notes} onChange={(event) => setForm((current) => ({ ...current, notes: event.target.value }))} /></Field>
        </Modal>}
    </section>;
}

function LegacyFinanceReceivablesScreen({ detailId = null, onNavigate }) {
    const [search, setSearch] = useState(''); const [state, setState] = useState({ loading: true, items: [], summary: {}, error: '' }); const [ledger, setLedger] = useState(null);
    useEffect(() => { const timer = setTimeout(() => window.axios.get(`${base()}/receivables`, { params: { search } }).then(({ data }) => setState({ loading: false, ...data.data, error: '' })).catch((error) => setState((s) => ({ ...s, loading: false, error: message(error) }))), 200); return () => clearTimeout(timer); }, [search]);
    const listPath = `${window.ValleyRuntime?.routes?.office || '/office'}/finance/receivables`;
    const loadDetail = (id) => { setLedger({ loading: true }); window.axios.get(`${base()}/customers/${id}/ledger`).then(({ data }) => setLedger(data.data)).catch((error) => setLedger({ error: message(error) })); };
    const show = (id) => onNavigate?.(`${listPath}/${id}`);
    useEffect(() => { if (detailId) loadDetail(detailId); else setLedger(null); }, [detailId]);
    if (detailId) return (
        <DetailPage eyebrow="Finance" title={ledger?.customer?.shop_name || 'Loading'} subtitle={ledger?.customer?.code || 'Customer ledger'} onBack={() => onNavigate?.(listPath)}
            aside={ledger?.customer && <DetailPanel eyebrow="Balance"><div className="record-page-summary"><span>Outstanding balance</span><strong>{money(ledger.summary.outstanding_amount)}</strong><small className="muted">Credit limit {money(ledger.customer.credit_limit)}</small></div></DetailPanel>}
        >
            {ledger?.loading ? <State loading text="Loading ledger" /> : ledger?.error ? <State text={ledger.error} /> : ledger && <DetailPanel eyebrow="Ledger" title="Account activity">
                <div className="finance-ledger-list record-page-ledger">{ledger.entries.map((entry) => <article key={entry.key}><span className={`finance-entry-icon ${entry.type}`}>{entry.type === 'invoice' ? <ArrowUpRight size={15} /> : <ArrowDownLeft size={15} />}</span><span><strong>{entry.reference}</strong><small>{entry.date} · {entry.description}</small></span><span><strong>{entry.credit ? `− ${money(entry.credit)}` : `+ ${money(entry.debit)}`}</strong><small>Balance {money(entry.balance)}</small></span></article>)}</div>
            </DetailPanel>}
        </DetailPage>
    );
    return <section className="page finance-page"><Heading title="Customer receivables" hint="Monitor invoiced, collected, and outstanding balances; open any customer for the full ledger." /><div className="metrics finance-metrics"><Metric icon={FileText} label="Invoiced" value={money(state.summary.invoiced_amount)} hint="Issued invoice value" /><Metric icon={ArrowDownLeft} label="Collected" value={money(state.summary.collected_amount)} hint="Approved receipts" /><Metric icon={WalletCards} label="Outstanding" value={money(state.summary.outstanding_amount)} hint="Current receivable" /><Metric icon={BookOpen} label="Customers" value={state.summary.customers_count || 0} hint="Active accounts" /></div><section className="master-panel"><div className="master-toolbar"><label className="master-search"><Search size={14} /><input placeholder="Search customer or code" value={search} onChange={(e) => setSearch(e.target.value)} /></label></div>{state.loading ? <State loading text="Loading customer balances" /> : state.error ? <State text={state.error} /> : <div className="master-table-wrap"><table className="master-table"><thead><tr><th>Customer</th><th>Route</th><th className="numeric">Credit limit</th><th className="numeric">Invoiced</th><th className="numeric">Collected</th><th className="numeric">Outstanding</th></tr></thead><tbody>{state.items.map((item) => <tr key={item.id} className="clickable-row" onClick={() => show(item.id)}><td><strong>{item.shop_name}</strong><span className="muted">{item.code}</span></td><td>{item.route_name || '-'}</td><td className="numeric">{money(item.credit_limit)}</td><td className="numeric">{money(item.invoiced_amount)}</td><td className="numeric">{money(item.collected_amount)}</td><td className="numeric"><strong>{money(item.outstanding_amount)}</strong></td></tr>)}</tbody></table></div>}</section></section>;
}

export function FinanceReceivablesScreen({ detailId = null, onNavigate, canManage = false }) {
    const [search, setSearch] = useState('');
    const [state, setState] = useState({ loading: true, items: [], summary: {}, error: '' });
    const [ledger, setLedger] = useState(null);
    const [refresh, setRefresh] = useState(0);
    const [paymentOpen, setPaymentOpen] = useState(false);
    const [saving, setSaving] = useState(false);
    const [paymentForm, setPaymentForm] = useState({ collection_date: today(), amount: '', payment_method: 'cash', reference_no: '', notes: '' });
    const listPath = `${window.ValleyRuntime?.routes?.office || '/office'}/finance/receivables`;

    useEffect(() => {
        const timer = window.setTimeout(() => {
            setState((current) => ({ ...current, loading: true, error: '' }));
            window.axios.get(`${base()}/receivables`, { params: { search } })
                .then(({ data }) => setState({ loading: false, ...data.data, error: '' }))
                .catch((error) => setState((current) => ({ ...current, loading: false, error: message(error) })));
        }, 200);
        return () => window.clearTimeout(timer);
    }, [search, refresh]);

    const loadDetail = (id) => {
        setLedger({ loading: true });
        window.axios.get(`${base()}/customers/${id}/ledger`)
            .then(({ data }) => setLedger(data.data))
            .catch((error) => setLedger({ error: message(error) }));
    };

    useEffect(() => {
        if (detailId) loadDetail(detailId);
        else setLedger(null);
    }, [detailId]);

    const savePayment = (event) => {
        event.preventDefault();
        if (!ledger?.customer) return;
        setSaving(true);
        window.axios.post(`${base()}/collections`, {
            ...paymentForm,
            customer_id: ledger.customer.id,
            invoice_id: null,
            employee_id: null,
        }).then(() => {
            setPaymentOpen(false);
            setPaymentForm({ collection_date: today(), amount: '', payment_method: 'cash', reference_no: '', notes: '' });
            loadDetail(ledger.customer.id);
            setRefresh((value) => value + 1);
        }).catch((error) => setLedger((current) => ({ ...current, error: message(error) })))
            .finally(() => setSaving(false));
    };

    if (detailId) return <>
        <DetailPage
            eyebrow="Customer credit"
            title={ledger?.customer?.shop_name || 'Loading'}
            subtitle={ledger?.customer?.code || 'Credit and payment history'}
            onBack={() => onNavigate?.(listPath)}
            actions={canManage && ledger?.customer && Number(ledger.summary.outstanding_amount || 0) > 0 ? <button className="button primary" type="button" onClick={() => setPaymentOpen(true)}><Plus size={15} />Record payment</button> : null}
            aside={ledger?.customer && <DetailPanel eyebrow="Balance"><div className="record-page-summary customer-credit-summary"><span>Outstanding</span><strong>{money(ledger.summary.outstanding_amount)}</strong><small>Customer credit {money(ledger.summary.customer_credit_amount)}</small><small>Returns {money(ledger.summary.return_credits_amount)}</small><small>Overdue {money(ledger.summary.overdue_amount)}</small><small>Next due {ledger.summary.next_due_date || 'No open due date'}</small><small>Credit limit {money(ledger.customer.credit_limit)}</small></div></DetailPanel>}
        >
            {ledger?.loading ? <State loading text="Loading credit history" /> : ledger?.error ? <State text={ledger.error} /> : ledger && <DetailPanel eyebrow="History" title="Sales, returns and payments">
                {ledger.entries.length ? <div className="finance-ledger-list record-page-ledger">{ledger.entries.map((entry) => <article key={entry.key}><span className={`finance-entry-icon ${entry.type}`}>{entry.type === 'invoice' ? <ArrowUpRight size={15} /> : <ArrowDownLeft size={15} />}</span><span><strong>{entry.reference}</strong><small>{entry.date} / {entry.description}{entry.due_date ? ` / Due ${entry.due_date}` : ''}</small></span><span><strong>{entry.credit ? `- ${money(entry.credit)}` : `+ ${money(entry.debit)}`}</strong><small>Balance {money(entry.balance)}</small></span></article>)}</div> : <State text="No credit sales or payments yet." />}
            </DetailPanel>}
        </DetailPage>
        {paymentOpen && ledger?.customer && <Modal title={`Record payment / ${ledger.customer.shop_name}`} saving={saving} onClose={() => setPaymentOpen(false)} onSubmit={savePayment} submitLabel="Record payment">
            <Field label="Payment date"><input required type="date" value={paymentForm.collection_date} onChange={(event) => setPaymentForm((current) => ({ ...current, collection_date: event.target.value }))} /></Field>
            <Field label="Amount (MMK)"><input required autoFocus type="number" min="1" max={Math.max(Number(ledger.summary.outstanding_amount || 0), 1)} value={paymentForm.amount} onChange={(event) => setPaymentForm((current) => ({ ...current, amount: event.target.value }))} /></Field>
            <Field label="Paid into"><select value={paymentForm.payment_method} onChange={(event) => setPaymentForm((current) => ({ ...current, payment_method: event.target.value }))}><option value="cash">Cash</option><option value="bank">Bank</option></select></Field>
            <Field label="Reference (optional)"><input value={paymentForm.reference_no} onChange={(event) => setPaymentForm((current) => ({ ...current, reference_no: event.target.value }))} /></Field>
            <Field label="Notes (optional)" wide><textarea rows="3" value={paymentForm.notes} onChange={(event) => setPaymentForm((current) => ({ ...current, notes: event.target.value }))} /></Field>
        </Modal>}
    </>;

    return <section className="page finance-page">
        <Heading title="Customer credit" hint="See each customer's credit sales, payments, outstanding balance, and due status." />
        <div className="metrics finance-metrics">
            <Metric icon={FileText} label="Credit sales" value={money(state.summary.credit_sales_amount)} hint="Confirmed credit orders" />
            <Metric icon={ArrowDownLeft} label="Payments" value={money(state.summary.collected_amount)} hint="Approved payments" />
            <Metric icon={RotateCcw} label="Return credit" value={money(state.summary.return_credits_amount)} hint="Approved sales returns" />
            <Metric icon={WalletCards} label="Outstanding" value={money(state.summary.outstanding_amount)} hint={`Customer credit ${money(state.summary.customer_credit_amount)}`} />
        </div>
        <section className="master-panel">
            <div className="master-toolbar"><label className="master-search"><Search size={14} /><input placeholder="Search customer or code" value={search} onChange={(event) => setSearch(event.target.value)} /></label></div>
            {state.loading ? <State loading text="Loading customer credit" /> : state.error ? <State text={state.error} /> : !state.items.length ? <State text="No customers match this view." /> : <div className="master-table-wrap"><table className="master-table customer-credit-table"><thead><tr><th>Customer</th><th>Route</th><th className="numeric">Credit limit</th><th className="numeric">Credit sales</th><th className="numeric">Payments</th><th className="numeric">Returns</th><th className="numeric">Outstanding</th><th className="numeric">Customer credit</th></tr></thead><tbody>{state.items.map((item) => <tr key={item.id} className="clickable-row" onClick={() => onNavigate?.(`${listPath}/${item.id}`)}><td><strong>{item.shop_name}</strong><span className="muted">{item.code}</span></td><td>{item.route_name || '-'}</td><td className="numeric">{money(item.credit_limit)}</td><td className="numeric">{money(item.invoiced_amount)}</td><td className="numeric">{money(item.collected_amount)}</td><td className="numeric">{money(item.return_credits_amount)}</td><td className="numeric"><strong>{money(item.outstanding_amount)}</strong></td><td className="numeric"><strong>{money(item.customer_credit_amount)}</strong></td></tr>)}</tbody></table></div>}
        </section>
    </section>;
}

export function FinanceExpensesScreen({ outdoor = false, canManage = false }) {
    const [state, setState] = useState({ loading: true, items: [], summary: {}, meta: {}, error: '' }); const [meta, setMeta] = useState({ employees: [], expense_categories: [] }); const [open, setOpen] = useState(false); const [saving, setSaving] = useState(false); const [refresh, setRefresh] = useState(0); const [status, setStatus] = useState('');
    const [search, setSearch] = useState('');
    const [form, setForm] = useState({ employee_id: '', expense_date: today(), expense_type: outdoor ? 'outdoor' : 'daily', category: outdoor ? 'travel' : 'utilities', description: '', amount: '', payment_method: 'cash', reference_no: '', notes: '' }); const reload = () => setRefresh((v) => v + 1);
    useEffect(() => { window.axios.get(`${base()}/meta`).then(({ data }) => setMeta(data.data)); }, []);
    useEffect(() => { const timer = window.setTimeout(() => { setState((s) => ({ ...s, loading: true })); window.axios.get(`${base()}/expenses`, { params: { expense_type: outdoor ? 'outdoor' : 'daily', status, search } }).then(({ data }) => setState({ loading: false, ...data.data, error: '' })).catch((error) => setState((s) => ({ ...s, loading: false, error: message(error) }))); }, 220); return () => window.clearTimeout(timer); }, [outdoor, status, search, refresh]);
    const save = (e) => { e.preventDefault(); setSaving(true); window.axios.post(`${base()}/expenses`, { ...form, employee_id: form.employee_id || null }).then(() => { setOpen(false); reload(); }).catch((error) => setState((s) => ({ ...s, error: message(error) }))).finally(() => setSaving(false)); };
    return <section className="page finance-page"><Heading title={outdoor ? 'Outdoor employee expenses' : 'Daily expenses'} hint={outdoor ? 'Review field costs from sales and delivery teams before posting them.' : 'Record routine office costs against the cash or bank book.'} action={canManage && <button className="button primary" onClick={() => setOpen(true)}><Plus size={15} />New expense</button>} /><div className="metrics finance-metrics"><Metric icon={CreditCard} label="Expense value" value={money(state.summary.total_amount)} hint={`${state.summary.records_count || 0} records`} /><Metric icon={BadgeCheck} label="Approved" value={money(state.summary.approved_amount)} hint="Posted to books" /><Metric icon={CalendarDays} label="Submitted" value={money(state.summary.submitted_amount)} hint="Waiting for review" /><Metric icon={CircleDollarSign} label="Average cost" value={money((state.summary.total_amount || 0) / Math.max(state.summary.records_count || 0, 1))} hint="Across current view" /></div><section className="master-panel"><div className="master-toolbar"><label className="master-search"><Search size={14} /><input aria-label="Search expense records" placeholder="Search expense records" value={search} onChange={(e) => setSearch(e.target.value)} /></label><select aria-label="Filter expense status" value={status} onChange={(e) => setStatus(e.target.value)}><option value="">All statuses</option><option value="submitted">Submitted</option><option value="approved">Approved</option><option value="rejected">Rejected</option></select><button className="button" onClick={reload}><RefreshCw size={14} />Refresh</button></div>{state.error && <p className="inline-error">{state.error}</p>}{state.loading ? <State loading text="Loading expenses" /> : !state.items.length ? <State text="No expenses match this view." /> : <><div className="master-table-wrap"><table className="master-table"><thead><tr><th>Expense</th><th>Description</th><th>Employee / source</th><th>Book</th><th className="numeric">Amount</th><th>Status</th><th></th></tr></thead><tbody>{state.items.map((item) => <tr key={item.id}><td><strong>{item.code}</strong><span className="muted">{item.expense_date} · {label(item.category)}</span></td><td>{item.description}</td><td>{item.employee_name || 'Office'}<span className="muted">{label(item.source_app)}</span></td><td>{label(item.payment_method)}<span className="muted">{item.reference_no || '-'}</span></td><td className="numeric"><strong>{money(item.amount)}</strong></td><td><span className={`status ${statusClass(item.status)}`}>{label(item.status)}</span></td><td><ReviewButtons item={item} canManage={canManage} endpoint="expenses" refresh={reload} setError={(error) => setState((s) => ({ ...s, error }))} /></td></tr>)}</tbody></table></div><Pagination meta={state.meta} page={1} setPage={() => {}} /></>}</section>{open && <Modal title={`Record ${outdoor ? 'outdoor' : 'daily'} expense`} saving={saving} onClose={() => setOpen(false)} onSubmit={save}>{outdoor && <Field label="Employee"><select required value={form.employee_id} onChange={(e) => setForm((v) => ({ ...v, employee_id: e.target.value }))}><option value="">Select employee</option>{meta.employees.map((i) => <option key={i.id} value={i.id}>{i.label}</option>)}</select></Field>}<Field label="Expense date"><input required type="date" value={form.expense_date} onChange={(e) => setForm((v) => ({ ...v, expense_date: e.target.value }))} /></Field><Field label="Category"><select value={form.category} onChange={(e) => setForm((v) => ({ ...v, category: e.target.value }))}>{meta.expense_categories.map((i) => <option key={i} value={i}>{label(i)}</option>)}</select></Field><Field label="Amount (MMK)"><input required min="1" type="number" value={form.amount} onChange={(e) => setForm((v) => ({ ...v, amount: e.target.value }))} /></Field><Field label="Payment book"><select value={form.payment_method} onChange={(e) => setForm((v) => ({ ...v, payment_method: e.target.value }))}><option value="cash">Cash</option><option value="bank">Bank</option></select></Field><Field label="Reference"><input value={form.reference_no} onChange={(e) => setForm((v) => ({ ...v, reference_no: e.target.value }))} /></Field><Field label="Description" wide><input required value={form.description} onChange={(e) => setForm((v) => ({ ...v, description: e.target.value }))} /></Field><Field label="Notes" wide><textarea rows="3" value={form.notes} onChange={(e) => setForm((v) => ({ ...v, notes: e.target.value }))} /></Field></Modal>}</section>;
}

export function FinanceBookScreen({ book }) {
    const [filters, setFilters] = useState({ date_from: '', date_to: '' }); const [state, setState] = useState({ loading: true, items: [], summary: {}, error: '' });
    const load = () => { setState((s) => ({ ...s, loading: true })); window.axios.get(`${base()}/books/${book}`, { params: filters }).then(({ data }) => setState({ loading: false, ...data.data, error: '' })).catch((error) => setState((s) => ({ ...s, loading: false, error: message(error) }))); }; useEffect(load, [book]);
    return <section className="page finance-page"><Heading title={`${label(book)} book`} hint={`Approved ${book} inflows and outflows with a running balance.`} /><div className="metrics finance-metrics finance-metrics-three"><Metric icon={ArrowDownLeft} label="Inflow" value={money(state.summary.inflow)} hint="Approved receipts" /><Metric icon={ArrowUpRight} label="Outflow" value={money(state.summary.outflow)} hint="Approved payments" /><Metric icon={book === 'cash' ? Banknote : CreditCard} label="Book balance" value={money(state.summary.balance)} hint="Current filtered balance" /></div><section className="master-panel"><div className="master-toolbar finance-date-toolbar"><Field label="From"><input type="date" value={filters.date_from} onChange={(e) => setFilters((v) => ({ ...v, date_from: e.target.value }))} /></Field><Field label="To"><input type="date" value={filters.date_to} onChange={(e) => setFilters((v) => ({ ...v, date_to: e.target.value }))} /></Field><button className="button" onClick={load}><RefreshCw size={14} />Apply</button></div>{state.loading ? <State loading text={`Loading ${book} book`} /> : !state.items.length ? <State text={`No ${book} transactions in this period.`} /> : <div className="master-table-wrap"><table className="master-table"><thead><tr><th>Transaction</th><th>Description</th><th>Category</th><th className="numeric">In</th><th className="numeric">Out</th><th className="numeric">Balance</th></tr></thead><tbody>{state.items.map((item) => <tr key={item.id}><td><strong>{item.code}</strong><span className="muted">{item.transaction_date} · {item.reference_code}</span></td><td>{item.description}</td><td>{label(item.category)}</td><td className="numeric positive">{item.direction === 'in' ? money(item.amount) : '-'}</td><td className="numeric negative">{item.direction === 'out' ? money(item.amount) : '-'}</td><td className="numeric"><strong>{money(item.balance)}</strong></td></tr>)}</tbody></table></div>}</section></section>;
}

export function SupplierLedgerScreen({ canManage = false }) {
    const [state, setState] = useState({ loading: true, items: [], summary: {} }); const [selected, setSelected] = useState(null); const [ledger, setLedger] = useState(null); const [open, setOpen] = useState(false); const [saving, setSaving] = useState(false); const [refresh, setRefresh] = useState(0); const [form, setForm] = useState({ entry_date: today(), entry_type: 'purchase', amount: '', reference_no: '', description: '' });
    useEffect(() => { window.axios.get(`${base()}/suppliers`).then(({ data }) => { setState({ loading: false, ...data.data }); setSelected((v) => v || data.data.items[0]?.id); }); }, [refresh]);
    useEffect(() => { if (!selected) return; setLedger({ loading: true }); window.axios.get(`${base()}/suppliers/${selected}/ledger`).then(({ data }) => setLedger(data.data)).catch((error) => setLedger({ error: message(error) })); }, [selected, refresh]);
    const save = (e) => { e.preventDefault(); setSaving(true); window.axios.post(`${base()}/suppliers/${selected}/ledger`, form).then(() => { setOpen(false); setRefresh((v) => v + 1); }).finally(() => setSaving(false)); };
    return <section className="page finance-page"><Heading title="Supplier ledger" hint="Track supplier purchases, payments, adjustments, and payable balances." action={canManage && selected && <button className="button primary" onClick={() => setOpen(true)}><Plus size={15} />Ledger entry</button>} /><div className="finance-supplier-layout"><section className="master-panel finance-supplier-list"><div className="master-panel-heading"><div><p className="eyebrow">Suppliers</p><h2>{state.items.length} accounts · {money(state.summary.payable_amount)}</h2></div></div>{state.loading ? <State loading text="Loading suppliers" /> : state.items.map((item) => <button className={selected === item.id ? 'is-selected' : ''} key={item.id} onClick={() => setSelected(item.id)}><span><strong>{item.name}</strong><small>{item.code} · {item.phone || 'No phone'}</small></span><strong>{money(item.balance)}</strong></button>)}</section><section className="master-panel finance-supplier-ledger">{ledger?.loading ? <State loading text="Loading supplier ledger" /> : ledger?.error ? <State text={ledger.error} /> : ledger ? <><div className="master-panel-heading"><div><p className="eyebrow">Account ledger</p><h2>{ledger.supplier.name}</h2></div><strong>{money(ledger.summary.balance)}</strong></div><div className="master-table-wrap"><table className="master-table supplier-ledger-table"><thead><tr><th>Date / reference</th><th>Description</th><th className="numeric">Payment</th><th className="numeric">Purchase</th><th className="numeric">Balance</th></tr></thead><tbody>{ledger.items.map((item) => <tr key={item.id}><td><strong>{item.entry_date}</strong><span className="muted">{item.reference_no || label(item.entry_type)}</span></td><td>{item.description}</td><td className="numeric">{item.debit ? money(item.debit) : '-'}</td><td className="numeric">{item.credit ? money(item.credit) : '-'}</td><td className="numeric"><strong>{money(item.balance)}</strong></td></tr>)}</tbody></table></div></> : <State text="Select a supplier." />}</section></div>{open && <Modal title="New supplier ledger entry" saving={saving} onClose={() => setOpen(false)} onSubmit={save}><Field label="Entry date"><input required type="date" value={form.entry_date} onChange={(e) => setForm((v) => ({ ...v, entry_date: e.target.value }))} /></Field><Field label="Entry type"><select value={form.entry_type} onChange={(e) => setForm((v) => ({ ...v, entry_type: e.target.value }))}><option value="purchase">Purchase</option><option value="payment">Payment</option><option value="adjustment">Adjustment</option></select></Field><Field label="Amount (MMK)"><input required min="1" type="number" value={form.amount} onChange={(e) => setForm((v) => ({ ...v, amount: e.target.value }))} /></Field><Field label="Reference"><input value={form.reference_no} onChange={(e) => setForm((v) => ({ ...v, reference_no: e.target.value }))} /></Field><Field label="Description" wide><input required value={form.description} onChange={(e) => setForm((v) => ({ ...v, description: e.target.value }))} /></Field></Modal>}</section>;
}

export function ProfitLossScreen() {
    const [filters, setFilters] = useState({ date_from: `${new Date().getFullYear()}-01-01`, date_to: today() }); const [state, setState] = useState({ loading: true, error: '' }); const load = () => window.axios.get(`${base()}/profit-loss`, { params: filters }).then(({ data }) => setState({ loading: false, ...data.data, error: '' })).catch((error) => setState({ loading: false, error: message(error) })); useEffect(() => { load(); }, []);
    const costs = state.costs || {};
    return <section className="page finance-page"><Heading title="Profit & loss" hint="Operating result including payroll, vehicles, outdoor employee costs, and daily expenses." /><section className="master-panel finance-report-filter"><div className="master-toolbar finance-date-toolbar"><Field label="From"><input type="date" value={filters.date_from} onChange={(e) => setFilters((v) => ({ ...v, date_from: e.target.value }))} /></Field><Field label="To"><input type="date" value={filters.date_to} onChange={(e) => setFilters((v) => ({ ...v, date_to: e.target.value }))} /></Field><button className="button primary" onClick={load}><RefreshCw size={14} />Run report</button></div></section>{state.loading ? <State loading text="Calculating profit and loss" /> : state.error ? <State text={state.error} /> : <><div className="metrics finance-metrics finance-metrics-three"><Metric icon={TrendingUp} label="Revenue" value={money(state.revenue)} hint="Issued invoice value" /><Metric icon={ArrowUpRight} label="Operating costs" value={money(costs.total)} hint="Approved costs" /><Metric icon={CircleDollarSign} label="Net profit" value={money(state.net_profit)} hint={state.net_profit >= 0 ? 'Profitable period' : 'Loss for period'} /></div><section className="master-panel profit-loss-statement"><header><div><p className="eyebrow">Operating statement</p><h2>{state.period.date_from} to {state.period.date_to}</h2></div><span className={`status ${state.net_profit >= 0 ? 'success' : 'danger'}`}>{state.net_profit >= 0 ? 'Profit' : 'Loss'}</span></header><div className="profit-row revenue"><span>Invoice revenue</span><strong>{money(state.revenue)}</strong></div><p className="profit-section-label">Operating costs</p><div className="profit-row"><span>Payroll</span><strong>{money(costs.payroll)}</strong></div><div className="profit-row"><span>Vehicle costs</span><strong>{money(costs.vehicle)}</strong></div><div className="profit-row"><span>Outdoor employee expenses</span><strong>{money(costs.outdoor_employee)}</strong></div><div className="profit-row"><span>Daily expenses</span><strong>{money(costs.daily_expense)}</strong></div><div className="profit-row total"><span>Total operating costs</span><strong>{money(costs.total)}</strong></div><div className="profit-row net"><span>Net profit / loss</span><strong>{money(state.net_profit)}</strong></div></section></>}</section>;
}

function LegacyMobileFinanceScreen({ appId, mode = 'ledger', locale = 'en' }) {
    const [state, setState] = useState({ loading: true, error: '' }); const [meta, setMeta] = useState({ customers: [], deliveries: [], expense_categories: [] }); const [open, setOpen] = useState(false); const [saving, setSaving] = useState(false); const [success, setSuccess] = useState(''); const [refresh, setRefresh] = useState(0);
    useEffect(() => {
        if (locale !== 'my' || state.loading) return undefined;
        const frame = window.requestAnimationFrame(() => {
            const heading = document.querySelector('.mobile-finance h1');
            const eyebrow = document.querySelector('.mobile-finance .eyebrow');
            if (heading) heading.textContent = tx(locale, appId === 'client' ? 'Ledger & payments' : mode === 'collections' ? 'Collections' : 'Outdoor expenses');
            if (eyebrow) eyebrow.textContent = tx(locale, appId === 'client' ? 'My account' : 'Field finance');
        });
        return () => window.cancelAnimationFrame(frame);
    }, [appId, locale, mode, state.loading]);
    const collectionMode = mode === 'collections'; const [form, setForm] = useState(collectionMode ? { customer_id: '', delivery_id: '', collection_date: today(), amount: '', payment_method: 'cash', reference_no: '', notes: '' } : { expense_date: today(), category: 'travel', description: '', amount: '', payment_method: 'cash', reference_no: '', notes: '' });
    useEffect(() => { window.axios.get(mobileBase()).then(({ data }) => setState({ loading: false, ...data.data, error: '' })).catch((error) => setState({ loading: false, error: message(error) })); if (appId !== 'client') window.axios.get(`${mobileBase()}/meta`).then(({ data }) => setMeta(data.data)); }, [refresh]);
    const items = appId === 'client' ? state.entries || [] : collectionMode ? state.collections || [] : state.expenses || [];
    const save = (e) => { e.preventDefault(); setSaving(true); window.axios.post(`${mobileBase()}/${collectionMode ? 'collections' : 'expenses'}`, form).then(({ data }) => { setOpen(false); setSuccess(data.message); setRefresh((v) => v + 1); }).catch((error) => setState((s) => ({ ...s, error: message(error) }))).finally(() => setSaving(false)); };
    const deliveryChanged = (id) => { const delivery = meta.deliveries.find((i) => String(i.id) === id); setForm((v) => ({ ...v, delivery_id: id, customer_id: delivery?.customer_id || '' })); };
    if (state.loading) return <State loading text="Loading account records" />;
    if (appId === 'client') return <div className="mobile-master-stack mobile-finance"><div className="mobile-master-heading"><div><p className="eyebrow">My account</p><h1>Ledger & payments</h1><span className="muted">Invoices, approved payments, and current outstanding.</span></div></div><div className="mobile-finance-balance"><span>Outstanding balance</span><strong>{money(state.summary?.outstanding_amount)}</strong><small>Paid {money(state.summary?.collected_amount)} of {money(state.summary?.invoiced_amount)}</small></div><section className="mobile-master-section"><div className="mobile-section-heading"><div><h2>Account activity</h2><small>{items.length} records</small></div></div><div className="mobile-finance-list">{items.map((item) => <article key={item.key}><span className={`finance-entry-icon ${item.type}`}>{item.type === 'invoice' ? <ArrowUpRight size={15} /> : <ArrowDownLeft size={15} />}</span><span><strong>{item.reference}</strong><small>{item.date} · {item.description}</small></span><span><strong>{item.credit ? `− ${money(item.credit)}` : `+ ${money(item.debit)}`}</strong><small>{money(item.balance)}</small></span></article>)}</div></section></div>;
    return <div className="mobile-master-stack mobile-finance"><div className="mobile-master-heading"><div><p className="eyebrow">Field finance</p><h1>{collectionMode ? 'Collections' : 'Outdoor expenses'}</h1><span className="muted">Submit records for Office review and track their status.</span></div><button className="button primary" onClick={() => setOpen(true)}><Plus size={15} />New</button></div>{success && <p className="inline-success"><BadgeCheck size={14} />{success}</p>}{state.error && <p className="inline-error">{state.error}</p>}<div className="mobile-finance-summary"><article><span>{collectionMode ? 'Collected' : 'Expenses'}</span><strong>{money(collectionMode ? state.summary?.collections_amount : state.summary?.expenses_amount)}</strong></article><article><span>Pending review</span><strong>{state.summary?.pending_count || 0}</strong></article></div><section className="mobile-master-section"><div className="mobile-section-heading"><div><h2>Submitted records</h2><small>{items.length} records</small></div></div><div className="mobile-finance-list">{items.map((item) => <article key={item.id}><span className={`finance-entry-icon ${collectionMode ? 'collection' : 'expense'}`}>{collectionMode ? <WalletCards size={15} /> : <CreditCard size={15} />}</span><span><strong>{item.code}</strong><small>{collectionMode ? item.shop_name : label(item.category)} · {item.collection_date || item.expense_date}</small></span><span><strong>{money(item.amount)}</strong><small className={statusClass(item.status)}>{label(item.status)}</small></span></article>)}</div></section>{open && <Modal title={collectionMode ? 'Submit field collection' : 'Submit outdoor expense'} saving={saving} onClose={() => setOpen(false)} onSubmit={save} submitLabel="Submit for review">{collectionMode && appId === 'driver' && <Field label="Delivery"><select required value={form.delivery_id} onChange={(e) => deliveryChanged(e.target.value)}><option value="">Select delivery</option>{meta.deliveries.map((i) => <option key={i.id} value={i.id}>{i.code} · {i.shop_name}</option>)}</select></Field>}{collectionMode && appId === 'sales' && <Field label="Customer"><CustomerCombobox customers={meta.customers} value={form.customer_id} onChange={(customerId) => setForm((v) => ({ ...v, customer_id: customerId }))} /></Field>}<Field label={collectionMode ? 'Collection date' : 'Expense date'}><input required type="date" value={form.collection_date || form.expense_date} onChange={(e) => setForm((v) => ({ ...v, [collectionMode ? 'collection_date' : 'expense_date']: e.target.value }))} /></Field>{!collectionMode && <Field label="Category"><select value={form.category} onChange={(e) => setForm((v) => ({ ...v, category: e.target.value }))}>{meta.expense_categories.map((i) => <option value={i} key={i}>{label(i)}</option>)}</select></Field>}<Field label="Amount (MMK)"><input required min="1" type="number" value={form.amount} onChange={(e) => setForm((v) => ({ ...v, amount: e.target.value }))} /></Field><Field label="Payment book"><select value={form.payment_method} onChange={(e) => setForm((v) => ({ ...v, payment_method: e.target.value }))}><option value="cash">Cash</option><option value="bank">Bank</option></select></Field><Field label="Reference"><input value={form.reference_no} onChange={(e) => setForm((v) => ({ ...v, reference_no: e.target.value }))} /></Field>{!collectionMode && <Field label="Description" wide><input required value={form.description} onChange={(e) => setForm((v) => ({ ...v, description: e.target.value }))} /></Field>}<Field label="Notes" wide><textarea rows="3" value={form.notes} onChange={(e) => setForm((v) => ({ ...v, notes: e.target.value }))} /></Field></Modal>}</div>;
}

export function MobileFinanceScreen(props) {
    if (props.mode === 'collections' && props.appId !== 'client') {
        return <MobileFieldCollectionsScreen appId={props.appId} />;
    }

    return <LegacyMobileFinanceScreen {...props} />;
}
