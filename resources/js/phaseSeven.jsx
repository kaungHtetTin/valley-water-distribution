import { AlertCircle, ArrowDownLeft, ArrowLeft, ArrowUpRight, BadgeCheck, Banknote, BookOpen, CalendarDays, CircleDollarSign, CreditCard, Download, FileText, Plus, Printer, RefreshCw, RotateCcw, Save, Search, TrendingUp, WalletCards, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { DetailPage, DetailPanel } from './components/DetailPage';
import { ShellPageActions } from './components/ShellPageActions';
import { printConfiguredDocument } from './printDocuments';

const base = () => window.ValleyRuntime?.api?.finance || '/api/finance';
const mobileBase = () => window.ValleyRuntime?.api?.mobileFinance || '/api/mobile/finance';
const today = () => new Date().toISOString().slice(0, 10);
const money = (value) => `${new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(Number(value || 0))} MMK`;
const label = (value) => String(value || '-').replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());
const message = (error) => error.response?.data?.message || Object.values(error.response?.data?.errors || {})[0]?.[0] || 'Something went wrong.';
const statusClass = (status) => status === 'approved' ? 'success' : status === 'submitted' ? 'warning' : status === 'rejected' ? 'danger' : 'neutral';
const mergeFinanceRow = (current, item) => {
    const previous = current.items.find((row) => row.id === item.id);
    const exists = Boolean(previous);
    const amount = Number(item.amount || 0);
    const previousAmount = Number(previous?.amount || 0);
    return { ...current, items: exists ? current.items.map((row) => row.id === item.id ? item : row) : [item, ...current.items], summary: { ...current.summary, records_count: Number(current.summary.records_count || 0) + (exists ? 0 : 1), total_amount: Number(current.summary.total_amount || 0) - previousAmount + amount, submitted_amount: Number(current.summary.submitted_amount || 0) - (previous?.status === 'submitted' ? previousAmount : 0) + (item.status === 'submitted' ? amount : 0), approved_amount: Number(current.summary.approved_amount || 0) - (previous?.status === 'approved' ? previousAmount : 0) + (item.status === 'approved' ? amount : 0) }, meta: { ...current.meta, total: Number(current.meta?.total || 0) + (exists ? 0 : 1) } };
};
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
    return <div className="master-heading"><div><p className="eyebrow">{eyebrow}</p><h1>{tx(locale, title)}</h1><span className="muted">{hint}</span></div><ShellPageActions>{action}</ShellPageActions></div>;
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

function DigitFilter({ label: text, value, placeholder = '0', onChange }) {
    return <Field label={<span>{text}</span>}><input type="text" inputMode="numeric" pattern="[0-9]*" placeholder={placeholder} value={value} onChange={(event) => onChange(event.target.value.replace(/\D/g, ''))} /></Field>;
}

function csvCell(value) {
    const text = String(value ?? '');
    return /[",\r\n]/.test(text) ? `"${text.replaceAll('"', '""')}"` : text;
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
    const emptyFilters = () => ({ search: '', status: '', date_from: '', date_to: '', min_amount: '', max_amount: '' });
    const [state, setState] = useState({ loading: true, collections: [], summary: {}, error: '' });
    const [meta, setMeta] = useState({ customers: [] });
    const [form, setForm] = useState(emptyForm);
    const [filters, setFilters] = useState(emptyFilters);
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
            const collection = data.data.collection;
            setState((current) => ({ ...current, collections: [collection, ...(current.collections || [])], summary: { ...current.summary, collections_amount: Number(current.summary?.collections_amount || 0) + Number(collection.amount || 0), pending_count: Number(current.summary?.pending_count || 0) + 1 } }));
            setMeta((current) => ({ ...current, customers: current.customers.map((customer) => String(customer.id) === String(collection.customer_id) ? { ...customer, pending_collection: Number(customer.pending_collection || 0) + Number(collection.amount || 0), collectible: Math.max(0, Number(customer.collectible || 0) - Number(collection.amount || 0)) } : customer).filter((customer) => Number(customer.collectible || 0) > 0) }));
            setOpen(false);
            setForm(emptyForm());
            setSuccess(data.message);
        }).catch((error) => setState((current) => ({ ...current, error: message(error) }))).finally(() => setSaving(false));
    };

    if (state.loading) return <State loading text="Loading cash collections" />;
    const allItems = state.collections || [];
    const normalizedSearch = filters.search.trim().toLowerCase();
    const items = allItems.filter((item) => {
        const searchable = [item.code, item.customer_code, item.shop_name, item.reference_no].filter(Boolean).join(' ').toLowerCase();
        if (normalizedSearch && !searchable.includes(normalizedSearch)) return false;
        if (filters.status && item.status !== filters.status) return false;
        if (filters.date_from && item.collection_date < filters.date_from) return false;
        if (filters.date_to && item.collection_date > filters.date_to) return false;
        if (filters.min_amount !== '' && Number(item.amount) < Number(filters.min_amount)) return false;
        if (filters.max_amount !== '' && Number(item.amount) > Number(filters.max_amount)) return false;
        return true;
    });
    const printCollection = (item) => printConfiguredDocument('payment_receipt', {
        reference: item.code,
        facts: [
            ['Customer', `${item.customer_code || '-'} / ${item.shop_name || '-'}`],
            ['Collection date', item.collection_date],
            ['Collected by', item.employee_name || 'Driver'],
            ['Payment method', label(item.payment_method)],
            ['Customer reference', item.reference_no || '-'],
            ['Review status', item.status === 'submitted' ? 'Pending Office review' : label(item.status)],
        ],
        columns: ['Description', 'Amount'],
        rows: [['Cash received from customer', money(item.amount)]],
        total: ['Cash received', money(item.amount)],
        notes: item.notes,
        signatures: ['Collected by', 'Customer'],
    });

    return <div className="mobile-master-stack mobile-finance mobile-field-collections">
        <div className="mobile-master-heading">
            <div><p className="eyebrow">Field finance</p><h1>Cash collections</h1><span className="muted">Collect customer credit balances on your assigned route.</span></div>
            <button className="button primary" type="button" onClick={() => { setSuccess(''); setState((current) => ({ ...current, error: '' })); setOpen(true); }}><Plus size={15} />Collect</button>
        </div>
        {success && <p className="inline-success"><BadgeCheck size={14} />{success}</p>}
        {state.error && <p className="inline-error"><AlertCircle size={14} />{state.error}</p>}
        <div className="mobile-order-filters mobile-collection-filters">
            <label className="mobile-search"><Search size={16} /><input type="search" value={filters.search} placeholder="Search customer, receipt or reference" aria-label="Search cash collections" onChange={(event) => setFilters((current) => ({ ...current, search: event.target.value }))} /></label>
            <label className="mobile-order-filter-field"><span>Status</span><select value={filters.status} aria-label="Collection status" onChange={(event) => setFilters((current) => ({ ...current, status: event.target.value }))}><option value="">All statuses</option><option value="submitted">Submitted</option><option value="approved">Approved</option><option value="rejected">Rejected</option></select></label>
            <label className="mobile-order-filter-field"><span>Collected from</span><input type="date" value={filters.date_from} max={filters.date_to || undefined} aria-label="Collection date from" onChange={(event) => setFilters((current) => ({ ...current, date_from: event.target.value }))} /></label>
            <label className="mobile-order-filter-field"><span>Collected through</span><input type="date" value={filters.date_to} min={filters.date_from || undefined} aria-label="Collection date through" onChange={(event) => setFilters((current) => ({ ...current, date_to: event.target.value }))} /></label>
            <label className="mobile-order-filter-field"><span>Minimum amount (MMK)</span><input type="number" min="0" step="1" inputMode="numeric" value={filters.min_amount} aria-label="Minimum collection amount" onChange={(event) => setFilters((current) => ({ ...current, min_amount: event.target.value }))} /></label>
            <label className="mobile-order-filter-field"><span>Maximum amount (MMK)</span><input type="number" min={filters.min_amount || 0} step="1" inputMode="numeric" value={filters.max_amount} aria-label="Maximum collection amount" onChange={(event) => setFilters((current) => ({ ...current, max_amount: event.target.value }))} /></label>
            <button className="button" type="button" onClick={() => setFilters(emptyFilters())}>Clear filters</button>
        </div>
        <div className="mobile-finance-summary">
            <article><span>Cash submitted</span><strong>{money(state.summary?.collections_amount)}</strong></article>
            <article><span>Pending Office review</span><strong>{state.summary?.pending_count || 0}</strong></article>
        </div>
        <section className="mobile-master-section">
            <div className="mobile-section-heading"><div><h2>My collection history</h2><small>{items.length === allItems.length ? `${items.length} receipts` : `${items.length} of ${allItems.length} receipts`}</small></div></div>
            {!items.length ? <div className="workspace-state compact"><WalletCards size={20} /><strong>{allItems.length ? 'No collections match these filters.' : 'No cash collections yet.'}</strong></div> : <div className="mobile-finance-list">{items.map((item) => <article key={item.id}>
                <span className="finance-entry-icon collection"><Banknote size={15} /></span>
                <span><strong>{item.shop_name}</strong><small>{item.code} · {item.collection_date}</small></span>
                <span><strong>{money(item.amount)}</strong><small className={statusClass(item.status)}>{label(item.status)}</small></span>
                {appId === 'driver' && <button className="icon-button collection-print-button" type="button" title={`Print receipt ${item.code}`} aria-label={`Print receipt ${item.code}`} onClick={() => printCollection(item)}><Printer size={15} /></button>}
            </article>)}</div>}
        </section>
        {open && <Modal title={`${appId === 'driver' ? 'Driver' : 'Sales'} cash collection`} saving={saving} onClose={() => setOpen(false)} onSubmit={submit} submitLabel="Submit cash"><MobileCollectionFields form={form} setForm={setForm} customers={meta.customers || []} selectedCustomer={selectedCustomer} /></Modal>}
    </div>;
}

function PaymentReceiptPrintButton({ item }) {
    return <button className="icon-button" type="button" title="Print payment receipt" aria-label={`Print ${item.code}`} onClick={() => printConfiguredDocument('payment_receipt', { reference: item.code, facts: [['Customer', `${item.customer_code || '-'} · ${item.shop_name || '-'}`], ['Date', item.collection_date], ['Received by', item.employee_name || 'Office'], ['Payment method', label(item.payment_method)], ['Reference', item.reference_no || '-'], ['Status', label(item.status)]], columns: ['Description', 'Amount'], rows: [['Customer payment', money(item.amount)]], total: ['Amount received', money(item.amount)], notes: item.notes, signatures: ['Received by', 'Customer'] })}><Printer size={14} /></button>;
}

function ExpenseVoucherPrintButton({ item }) {
    return <button className="icon-button" type="button" title="Print expense voucher" aria-label={`Print ${item.code}`} onClick={() => printConfiguredDocument('expense_payment', { reference: item.code, facts: [['Date', item.expense_date], ['Category', label(item.category)], ['Employee / source', item.employee_name || 'Office'], ['Payment book', label(item.payment_method)], ['Reference', item.reference_no || '-'], ['Status', label(item.status)]], columns: ['Description', 'Amount'], rows: [[item.description, money(item.amount)]], total: ['Total paid', money(item.amount)], notes: item.notes, signatures: ['Prepared by', 'Approved by', 'Paid to'] })}><Printer size={14} /></button>;
}

function ReviewButtons({ item, canManage, endpoint, onUpdated, setError }) {
    if (!canManage || item.status !== 'submitted') return null;
    const requiresCashHandover = endpoint === 'collections' && item.source_app === 'driver' && item.payment_method === 'cash' && item.employee_id;
    const review = (status) => window.axios.post(`${base()}/${endpoint}/${item.id}/review`, { status }).then(({ data }) => onUpdated(data.data[endpoint === 'collections' ? 'collection' : 'expense'])).catch((error) => setError(message(error)));
    return <>{!requiresCashHandover && <button title="Approve" aria-label={`Approve ${item.code}`} onClick={() => review('approved')}><BadgeCheck size={15} /></button>}<button className="danger" title="Reject" aria-label={`Reject ${item.code}`} onClick={() => review('rejected')}><X size={15} /></button></>;
}

function DriverCashHandoverModal({ handovers, loading, canManage, onClose, onReceived }) {
    const [search, setSearch] = useState('');
    const [sort, setSort] = useState('amount_desc');
    const [page, setPage] = useState(1);
    const [selected, setSelected] = useState(null);
    const [form, setForm] = useState({ received_amount: '', notes: '' });
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const normalizedSearch = search.trim().toLocaleLowerCase();
    const filtered = handovers.filter((item) => !normalizedSearch || `${item.employee_code} ${item.employee_name}`.toLocaleLowerCase().includes(normalizedSearch)).sort((left, right) => {
        if (sort === 'name') return String(left.employee_name).localeCompare(String(right.employee_name));
        if (sort === 'oldest') return String(left.oldest_collection_date).localeCompare(String(right.oldest_collection_date));
        return Number(right.amount || 0) - Number(left.amount || 0);
    });
    const perPage = 8;
    const lastPage = Math.max(1, Math.ceil(filtered.length / perPage));
    const activePage = Math.min(page, lastPage);
    const visible = filtered.slice((activePage - 1) * perPage, activePage * perPage);
    const totalAmount = handovers.reduce((sum, item) => sum + Number(item.amount || 0), 0);
    const receiptCount = handovers.reduce((sum, item) => sum + Number(item.collections_count || 0), 0);
    const receivedAmount = Number(form.received_amount || 0);
    const amountMatches = selected && Math.abs(receivedAmount - Number(selected.amount || 0)) < 0.001;

    const chooseDriver = (item) => {
        setSelected(item);
        setForm({ received_amount: String(item.amount), notes: '' });
        setError('');
    };
    const submit = (event) => {
        event.preventDefault();
        if (!selected || !amountMatches) return;
        setSaving(true);
        setError('');
        window.axios.post(`${base()}/cash-handovers/${selected.employee_id}/receive`, {
            collection_ids: selected.collection_ids,
            received_amount: form.received_amount,
            notes: form.notes || null,
        }).then(({ data }) => onReceived(data.message, { ...data.data.handover, collection_ids: selected.collection_ids })).catch((requestError) => setError(message(requestError))).finally(() => setSaving(false));
    };

    useEffect(() => {
        const closeOnEscape = (event) => {
            if (event.key === 'Escape' && !saving) onClose();
        };
        document.addEventListener('keydown', closeOnEscape);
        return () => document.removeEventListener('keydown', closeOnEscape);
    }, [onClose, saving]);

    return <div className="modal-backdrop" onMouseDown={(event) => event.target === event.currentTarget && !saving && onClose()}>
        <form className="master-dialog cash-handover-dialog" role="dialog" aria-modal="true" aria-labelledby="cash-handover-title" onSubmit={submit}>
            <header>
                <div className="cash-handover-dialog-title">{selected && <button className="icon-button" type="button" aria-label="Back to drivers" disabled={saving} onClick={() => setSelected(null)}><ArrowLeft size={16} /></button>}<span className="cash-handover-dialog-icon"><Banknote size={18} /></span><div><p className="eyebrow">Office cash receipt</p><h2 id="cash-handover-title">{selected ? 'Confirm cash received' : 'Driver cash handover'}</h2></div></div>
                <button className="icon-button" type="button" aria-label="Close" disabled={saving} onClick={onClose}><X size={16} /></button>
            </header>
            <div className="cash-handover-dialog-body">
                {error && <p className="inline-error cash-handover-error"><AlertCircle size={14} />{error}</p>}
                {!selected ? <>
                    <div className="cash-handover-summary" aria-label="Pending cash handover summary">
                        <span><small>Pending drivers</small><strong>{handovers.length}</strong></span>
                        <span><small>Receipts</small><strong>{receiptCount}</strong></span>
                        <span><small>Total cash hold</small><strong>{money(totalAmount)}</strong></span>
                    </div>
                    <div className="cash-handover-toolbar">
                        <label className="master-search"><Search size={14} /><input aria-label="Search driver cash handovers" placeholder="Search driver name or code" value={search} onChange={(event) => { setSearch(event.target.value); setPage(1); }} /></label>
                        <select aria-label="Sort driver cash handovers" value={sort} onChange={(event) => { setSort(event.target.value); setPage(1); }}><option value="amount_desc">Highest cash first</option><option value="oldest">Oldest pending first</option><option value="name">Driver name</option></select>
                    </div>
                    {loading ? <State loading text="Loading driver cash holds" /> : !handovers.length ? <div className="cash-handover-empty"><BadgeCheck size={18} /><span><strong>All driver cash received</strong><small>There are no pending handovers.</small></span></div> : !filtered.length ? <div className="cash-handover-empty neutral"><Search size={18} /><span><strong>No matching driver</strong><small>Try another name or employee code.</small></span></div> : <>
                        <div className="cash-handover-table-wrap"><table className="master-table cash-handover-table"><thead><tr><th>Driver</th><th>Pending since</th><th className="numeric">Receipts</th><th className="numeric">Cash hold</th>{canManage && <th className="table-actions-header">Actions</th>}</tr></thead><tbody>{visible.map((item) => <tr key={item.employee_id}>
                            <td><strong>{item.employee_name}</strong><span className="muted">{item.employee_code}</span></td>
                            <td>{item.oldest_collection_date}<span className="muted">Latest {item.latest_collection_date}</span></td>
                            <td className="numeric"><strong>{item.collections_count}</strong></td>
                            <td className="numeric"><strong>{money(item.amount)}</strong></td>
                            {canManage && <td className="cash-handover-action table-actions-cell"><button className="button primary" type="button" onClick={() => chooseDriver(item)}>Receive</button></td>}
                        </tr>)}</tbody></table></div>
                        <div className="cash-handover-pagination"><span>Showing {(activePage - 1) * perPage + 1}–{Math.min(activePage * perPage, filtered.length)} of {filtered.length} drivers</span><div><button type="button" disabled={activePage <= 1} onClick={() => setPage((value) => Math.max(1, value - 1))} aria-label="Previous driver page">‹</button><strong>{activePage} / {lastPage}</strong><button type="button" disabled={activePage >= lastPage} onClick={() => setPage((value) => Math.min(lastPage, value + 1))} aria-label="Next driver page">›</button></div></div>
                    </>}
                </> : <div className="cash-handover-form">
                    <section className="cash-handover-driver-summary"><span className="cash-handover-dialog-icon"><Banknote size={18} /></span><div><small>Driver</small><strong>{selected.employee_name}</strong><span>{selected.employee_code}</span></div><strong>{money(selected.amount)}</strong></section>
                    <div className="cash-handover-facts"><span><small>Pending receipts</small><strong>{selected.collections_count}</strong></span><span><small>Collection period</small><strong>{selected.oldest_collection_date} – {selected.latest_collection_date}</strong></span></div>
                    <label className="cash-handover-field"><span>Cash counted at Office <b>*</b></span><div className="cash-handover-money-input"><input required autoFocus type="number" inputMode="decimal" min="1" step="0.01" value={form.received_amount} onChange={(event) => setForm((current) => ({ ...current, received_amount: event.target.value }))} /><strong>MMK</strong></div><small className={amountMatches ? 'is-match' : 'is-mismatch'}>{amountMatches ? 'Amount matches the driver cash hold.' : `Expected ${money(selected.amount)}.`}</small></label>
                    <label className="cash-handover-field"><span>Handover notes <small>Optional</small></span><textarea rows="3" placeholder="Add a receipt reference or note" value={form.notes} onChange={(event) => setForm((current) => ({ ...current, notes: event.target.value }))} /></label>
                    <div className="cash-handover-posting-note"><BadgeCheck size={17} /><span><strong>What happens after confirmation</strong><small>{selected.collections_count} receipts will be approved, posted to the cash book, and removed from this driver's cash hold.</small></span></div>
                </div>}
            </div>
            <footer>{selected ? <button className="button primary" disabled={saving || !amountMatches}><Banknote size={15} />{saving ? 'Receiving…' : 'Receive & post cash'}</button> : <button className="button" type="button" onClick={onClose}>Close</button>}</footer>
        </form>
    </div>;
}

function LegacyFinanceCollectionsScreen({ outdoor = false, canCreate = false, canReview = false, canReceive = false, locale = 'en' }) {
    const emptyFilters = { search: '', customer_id: '', employee_id: '', source_app: '', payment_method: '', status: '', date_from: '', date_to: '', min_amount: '', max_amount: '', page: 1 };
    const [filters, setFilters] = useState(emptyFilters);
    const [state, setState] = useState({ loading: true, items: [], cash_handovers: [], summary: {}, meta: {}, error: '' });
    const [meta, setMeta] = useState({ customers: [], invoices: [], employees: [] });
    const [open, setOpen] = useState(false); const [saving, setSaving] = useState(false); const [refresh, setRefresh] = useState(0);
    const [handoverOpen, setHandoverOpen] = useState(false);
    const [success, setSuccess] = useState('');
    const [form, setForm] = useState({ customer_id: '', invoice_id: '', employee_id: '', collection_date: today(), amount: '', payment_method: 'cash', reference_no: '', notes: '' });
    const reload = () => setRefresh((v) => v + 1);
    useEffect(() => { window.axios.get(`${base()}/meta`).then(({ data }) => setMeta(data.data)).catch(() => {}); }, []);
    useEffect(() => { let live = true; setState((s) => ({ ...s, loading: true, error: '' })); window.axios.get(`${base()}/collections`, { params: { ...filters, outdoor: outdoor ? 1 : undefined } }).then(({ data }) => live && setState({ loading: false, ...data.data, error: '' })).catch((error) => live && setState((s) => ({ ...s, loading: false, error: message(error) }))); return () => { live = false; }; }, [filters, outdoor, refresh]);
    const invoices = meta.invoices.filter((item) => String(item.customer_id) === String(form.customer_id));
    const save = (e) => { e.preventDefault(); setSaving(true); const payload = { ...form, invoice_id: form.invoice_id || null, employee_id: form.employee_id || null }; window.axios.post(`${base()}/collections`, payload).then(({ data }) => { setState((current) => mergeFinanceRow(current, data.data.collection)); setOpen(false); }).catch((error) => setState((s) => ({ ...s, error: message(error) }))).finally(() => setSaving(false)); };
    const cashHandovers = state.cash_handovers || [];
    const pendingHandoverAmount = cashHandovers.reduce((sum, item) => sum + Number(item.amount || 0), 0);
    const pendingReceiptCount = cashHandovers.reduce((sum, item) => sum + Number(item.collections_count || 0), 0);
    const handoverReceived = (successMessage, handover) => {
        setHandoverOpen(false);
        setSuccess(successMessage);
        const ids = new Set(handover.collection_ids || []);
        setState((current) => ({ ...current, items: current.items.map((item) => ids.has(item.id) ? { ...item, status: 'approved' } : item), cash_handovers: current.cash_handovers.filter((item) => Number(item.employee_id) !== Number(handover.employee_id)), summary: { ...current.summary, submitted_amount: Math.max(0, Number(current.summary.submitted_amount || 0) - Number(handover.received_amount || 0)), approved_amount: Number(current.summary.approved_amount || 0) + Number(handover.received_amount || 0) } }));
    };
    const title = outdoor ? 'Outdoor employee collections' : 'Payments';
    const showCollectionActions = canReview && state.items.some((item) => item.status === 'submitted');
    return <section className="page finance-page">
        <Heading title={title} hint={outdoor ? 'Receive driver cash handovers and review field collections.' : 'Record customer payments and post approved receipts to the cash or bank book.'} action={canCreate && <button className="button primary" onClick={() => setOpen(true)}><Plus size={15} />New collection</button>} />
        {success && <p className="inline-success"><BadgeCheck size={14} />{success}</p>}
        {state.error && <p className="inline-error"><AlertCircle size={14} />{state.error}</p>}
        {outdoor && <section className={`cash-handover-launcher ${cashHandovers.length ? '' : 'is-clear'}`}><span className="cash-handover-launcher-icon">{cashHandovers.length ? <Banknote size={19} /> : <BadgeCheck size={19} />}</span><div><strong>{cashHandovers.length ? 'Driver cash waiting for Office' : 'All driver cash received'}</strong><small>{cashHandovers.length ? `${cashHandovers.length} drivers · ${pendingReceiptCount} receipts` : 'There are no pending driver cash handovers.'}</small></div>{cashHandovers.length ? <strong className="cash-handover-launcher-amount">{money(pendingHandoverAmount)}</strong> : null}{canReceive && <button className="button primary" type="button" onClick={() => { setSuccess(''); setState((current) => ({ ...current, error: '' })); setHandoverOpen(true); }}><Banknote size={15} />Cash handover</button>}</section>}
        <div className="metrics finance-metrics"><Metric icon={WalletCards} label="Collection value" value={money(state.summary.total_amount)} hint={`${state.summary.records_count || 0} records`} /><Metric icon={BadgeCheck} label="Approved" value={money(state.summary.approved_amount)} hint="Posted to books" /><Metric icon={CalendarDays} label="Submitted" value={money(state.summary.submitted_amount)} hint="Waiting for review" /><Metric icon={CircleDollarSign} label="Average receipt" value={money((state.summary.total_amount || 0) / Math.max(state.summary.records_count || 0, 1))} hint="Across current view" /></div>
        <section className="master-panel"><div className="master-toolbar outdoor-collection-filter-toolbar">
            <label className="master-search"><Search size={14} /><input placeholder="Search receipt, customer, invoice or employee" value={filters.search} onChange={(event) => setFilters((current) => ({ ...current, search: event.target.value, page: 1 }))} /></label>
            <Field label={<span>Date from</span>}><input type="date" value={filters.date_from} onChange={(event) => setFilters((current) => ({ ...current, date_from: event.target.value, page: 1 }))} /></Field>
            <Field label={<span>Date to</span>}><input type="date" value={filters.date_to} onChange={(event) => setFilters((current) => ({ ...current, date_to: event.target.value, page: 1 }))} /></Field>
            <Field label={<span>Customer</span>}><select value={filters.customer_id} onChange={(event) => setFilters((current) => ({ ...current, customer_id: event.target.value, page: 1 }))}><option value="">All customers</option>{meta.customers.map((customer) => <option value={customer.id} key={customer.id}>{customer.label}</option>)}</select></Field>
            <Field label={<span>Employee</span>}><select value={filters.employee_id} onChange={(event) => setFilters((current) => ({ ...current, employee_id: event.target.value, page: 1 }))}><option value="">All field employees</option>{meta.employees.map((employee) => <option value={employee.id} key={employee.id}>{employee.label}</option>)}</select></Field>
            <Field label={<span>Source</span>}><select value={filters.source_app} onChange={(event) => setFilters((current) => ({ ...current, source_app: event.target.value, page: 1 }))}><option value="">All sources</option><option value="driver">Driver app</option><option value="office">Office</option></select></Field>
            <Field label={<span>Method</span>}><select value={filters.payment_method} onChange={(event) => setFilters((current) => ({ ...current, payment_method: event.target.value, page: 1 }))}><option value="">All methods</option><option value="cash">Cash</option><option value="bank">Bank</option></select></Field>
            <Field label={<span>Status</span>}><select value={filters.status} onChange={(event) => setFilters((current) => ({ ...current, status: event.target.value, page: 1 }))}><option value="">All statuses</option><option value="submitted">Waiting review</option><option value="approved">Approved</option><option value="rejected">Rejected</option></select></Field>
            <Field label={<span>Minimum amount</span>}><input type="text" inputMode="numeric" pattern="[0-9]*" placeholder="0" value={filters.min_amount} onChange={(event) => setFilters((current) => ({ ...current, min_amount: event.target.value.replace(/\D/g, ''), page: 1 }))} /></Field>
            <Field label={<span>Maximum amount</span>}><input type="text" inputMode="numeric" pattern="[0-9]*" placeholder="Any amount" value={filters.max_amount} onChange={(event) => setFilters((current) => ({ ...current, max_amount: event.target.value.replace(/\D/g, ''), page: 1 }))} /></Field>
            <button className="button" type="button" onClick={() => setFilters(emptyFilters)}>Clear filters</button>
            <button className="button" type="button" onClick={reload}><RefreshCw size={14} />Refresh</button>
        </div>{state.loading ? <State loading text="Loading collections" /> : !state.items.length ? <State text="No collections match this view." /> : <><div className="master-table-wrap"><table className="master-table finance-table"><thead><tr><th>Receipt</th><th>Customer / invoice</th><th>Collector</th><th>Method</th><th className="numeric">Amount</th><th>Status</th><th className="table-actions-header">Actions</th></tr></thead><tbody>{state.items.map((item) => <tr key={item.id}><td><strong>{item.code}</strong><span className="muted">{item.collection_date}</span></td><td><strong>{item.shop_name}</strong><span className="muted">{item.invoice_code || 'Customer balance'}</span></td><td>{item.employee_name || 'Office'}<span className="muted">{label(item.source_app)}</span></td><td>{label(item.payment_method)}<span className="muted">{item.reference_no || 'No reference'}</span></td><td className="numeric"><strong>{money(item.amount)}</strong></td><td><span className={`status ${statusClass(item.status)}`}>{label(item.status)}</span></td><td className="table-actions-cell"><div className="row-actions"><PaymentReceiptPrintButton item={item} /><ReviewButtons item={item} canManage={canReview} endpoint="collections" onUpdated={(updated) => setState((current) => mergeFinanceRow(current, updated))} setError={(error) => setState((s) => ({ ...s, error }))} /></div></td></tr>)}</tbody></table></div><Pagination meta={state.meta} page={filters.page} setPage={(page) => setFilters((v) => ({ ...v, page }))} /></>}</section>
        {handoverOpen && <DriverCashHandoverModal handovers={cashHandovers} loading={state.loading} canManage={canReceive} onClose={() => setHandoverOpen(false)} onReceived={handoverReceived} />}
        {open && <Modal title="Record customer collection" saving={saving} onClose={() => setOpen(false)} onSubmit={save}><Field label="Customer"><select required value={form.customer_id} onChange={(e) => setForm((v) => ({ ...v, customer_id: e.target.value, invoice_id: '' }))}><option value="">Select customer</option>{meta.customers.map((i) => <option key={i.id} value={i.id}>{i.label} · {money(i.outstanding)}</option>)}</select></Field><Field label="Invoice (optional)"><select value={form.invoice_id} onChange={(e) => setForm((v) => ({ ...v, invoice_id: e.target.value }))}><option value="">Apply to customer balance</option>{invoices.map((i) => <option key={i.id} value={i.id}>{i.label} · {money(i.outstanding)}</option>)}</select></Field>{outdoor && <Field label="Field employee"><select required value={form.employee_id} onChange={(e) => setForm((v) => ({ ...v, employee_id: e.target.value }))}><option value="">Select employee</option>{meta.employees.map((i) => <option key={i.id} value={i.id}>{i.label}</option>)}</select></Field>}<Field label="Collection date"><input required type="date" value={form.collection_date} onChange={(e) => setForm((v) => ({ ...v, collection_date: e.target.value }))} /></Field><Field label="Amount (MMK)"><input required type="number" min="1" value={form.amount} onChange={(e) => setForm((v) => ({ ...v, amount: e.target.value }))} /></Field><Field label="Payment book"><select value={form.payment_method} onChange={(e) => setForm((v) => ({ ...v, payment_method: e.target.value }))}><option value="cash">Cash</option><option value="bank">Bank</option></select></Field><Field label="Reference"><input value={form.reference_no} onChange={(e) => setForm((v) => ({ ...v, reference_no: e.target.value }))} /></Field><Field label="Notes" wide><textarea rows="3" value={form.notes} onChange={(e) => setForm((v) => ({ ...v, notes: e.target.value }))} /></Field></Modal>}
    </section>;
}

export function FinanceCollectionsScreen({ outdoor = false, canCreate = false, canReview = false, canReceive = false, locale = 'en' }) {
    if (outdoor) return <LegacyFinanceCollectionsScreen outdoor canCreate={canCreate} canReview={canReview} canReceive={canReceive} locale={locale} />;
    return <OfficePaymentsScreen canCreate={canCreate} canReview={canReview} />;
}

function OfficePaymentsScreen({ canCreate, canReview }) {
    const emptyFilters = { search: '', customer_id: '', receiver: '', source_app: '', payment_method: '', status: '', date_from: '', date_to: '', min_amount: '', max_amount: '', page: 1 };
    const [filters, setFilters] = useState(emptyFilters);
    const [state, setState] = useState({ loading: true, items: [], summary: {}, meta: {}, error: '' });
    const [meta, setMeta] = useState({ customers: [], employees: [] });
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
            .then(({ data }) => {
                setState((current) => mergeFinanceRow(current, data.data.collection));
                setOpen(false);
                setForm({ customer_id: '', collection_date: today(), amount: '', payment_method: 'cash', reference_no: '', notes: '' });
            }).catch((error) => setState((current) => ({ ...current, error: message(error) })))
            .finally(() => setSaving(false));
    };

    return <section className="page finance-page">
        <Heading title="Payments" hint="Record customer payments and review the complete payment history." action={canCreate && <button className="button primary" type="button" onClick={() => setOpen(true)}><Plus size={15} />Record payment</button>} />
        <div className="metrics finance-metrics finance-metrics-three">
            <Metric icon={WalletCards} label="Payments" value={money(state.summary.total_amount)} hint={`${state.summary.records_count || 0} records`} />
            <Metric icon={BadgeCheck} label="Approved" value={money(state.summary.approved_amount)} hint="Posted to cash or bank" />
            <Metric icon={CalendarDays} label="Waiting review" value={money(state.summary.submitted_amount)} hint="Field payments" />
        </div>
        <section className="master-panel">
            <div className="master-toolbar payment-filter-toolbar">
                <label className="master-search"><Search size={14} /><input placeholder="Search payment or customer" value={filters.search} onChange={(event) => setFilters((current) => ({ ...current, search: event.target.value, page: 1 }))} /></label>
                <Field label={<span>Date from</span>}><input type="date" value={filters.date_from} onChange={(event) => setFilters((current) => ({ ...current, date_from: event.target.value, page: 1 }))} /></Field>
                <Field label={<span>Date to</span>}><input type="date" value={filters.date_to} onChange={(event) => setFilters((current) => ({ ...current, date_to: event.target.value, page: 1 }))} /></Field>
                <Field label={<span>Customer</span>}><select value={filters.customer_id} onChange={(event) => setFilters((current) => ({ ...current, customer_id: event.target.value, page: 1 }))}><option value="">All customers</option>{(meta.customers || []).map((customer) => <option value={customer.id} key={customer.id}>{customer.code} · {customer.shop_name}</option>)}</select></Field>
                <Field label={<span>Received by</span>}><select value={filters.receiver} onChange={(event) => setFilters((current) => ({ ...current, receiver: event.target.value, page: 1 }))}><option value="">All receivers</option><option value="office">Office</option>{(meta.employees || []).map((employee) => <option value={`employee:${employee.id}`} key={employee.id}>{employee.label}</option>)}</select></Field>
                <Field label={<span>Source</span>}><select value={filters.source_app} onChange={(event) => setFilters((current) => ({ ...current, source_app: event.target.value, page: 1 }))}><option value="">All sources</option><option value="office">Office</option><option value="driver">Driver app</option></select></Field>
                <Field label={<span>Method</span>}><select value={filters.payment_method} onChange={(event) => setFilters((current) => ({ ...current, payment_method: event.target.value, page: 1 }))}><option value="">All methods</option><option value="cash">Cash</option><option value="bank">Bank</option></select></Field>
                <Field label={<span>Status</span>}><select aria-label="Payment status" value={filters.status} onChange={(event) => setFilters((current) => ({ ...current, status: event.target.value, page: 1 }))}><option value="">All statuses</option><option value="submitted">Waiting review</option><option value="approved">Approved</option><option value="rejected">Rejected</option></select></Field>
                <Field label={<span>Minimum amount</span>}><input type="text" inputMode="numeric" pattern="[0-9]*" placeholder="0" value={filters.min_amount} onChange={(event) => setFilters((current) => ({ ...current, min_amount: event.target.value.replace(/\D/g, ''), page: 1 }))} /></Field>
                <Field label={<span>Maximum amount</span>}><input type="text" inputMode="numeric" pattern="[0-9]*" placeholder="Any amount" value={filters.max_amount} onChange={(event) => setFilters((current) => ({ ...current, max_amount: event.target.value.replace(/\D/g, ''), page: 1 }))} /></Field>
                <button className="button" type="button" onClick={() => setFilters(emptyFilters)}>Clear filters</button>
                <button className="button" type="button" onClick={() => setRefresh((value) => value + 1)}><RefreshCw size={14} />Refresh</button>
            </div>
            {state.error && <p className="inline-error"><AlertCircle size={14} />{state.error}</p>}
            {state.loading ? <State loading text="Loading payments" /> : !state.items.length ? <State text="No payments match this view." /> : <><div className="master-table-wrap"><table className="master-table finance-table"><thead><tr><th>Payment</th><th>Customer</th><th>Received by</th><th>Method</th><th className="numeric">Amount</th><th>Status</th><th className="table-actions-header">Actions</th></tr></thead><tbody>{state.items.map((item) => <tr key={item.id}><td><strong>{item.code}</strong><span className="muted">{item.collection_date}</span></td><td><strong>{item.shop_name}</strong><span className="muted">{item.customer_code}</span></td><td>{item.employee_name || 'Office'}<span className="muted">{label(item.source_app)}</span></td><td>{label(item.payment_method)}<span className="muted">{item.reference_no || 'No reference'}</span></td><td className="numeric"><strong>{money(item.amount)}</strong></td><td><span className={`status ${statusClass(item.status)}`}>{label(item.status)}</span></td><td className="table-actions-cell"><div className="row-actions"><PaymentReceiptPrintButton item={item} /><ReviewButtons item={item} canManage={canReview} endpoint="collections" onUpdated={(updated) => setState((current) => mergeFinanceRow(current, updated))} setError={(error) => setState((current) => ({ ...current, error }))} /></div></td></tr>)}</tbody></table></div><Pagination meta={state.meta} page={filters.page} setPage={(page) => setFilters((current) => ({ ...current, page }))} /></>}
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
                <div className="finance-ledger-list record-page-ledger">{ledger.entries.map((entry) => <article key={entry.key}><span className={`finance-entry-icon ${entry.type}`}>{entry.type === 'invoice' ? <ArrowUpRight size={15} /> : <ArrowDownLeft size={15} />}</span><span><strong>{entry.reference}</strong><small>{entry.date} · {entry.description}</small></span><span><strong className={entry.status === 'submitted' ? 'warning' : ''}>{entry.status === 'submitted' ? `Pending ${money(entry.pending_amount)}` : entry.credit ? `− ${money(entry.credit)}` : `+ ${money(entry.debit)}`}</strong><small>Balance {money(entry.balance)}</small></span></article>)}</div>
            </DetailPanel>}
        </DetailPage>
    );
    return <section className="page finance-page"><Heading title="Customer receivables" hint="Monitor invoiced, collected, and outstanding balances; open any customer for the full ledger." /><div className="metrics finance-metrics"><Metric icon={FileText} label="Invoiced" value={money(state.summary.invoiced_amount)} hint="Issued invoice value" /><Metric icon={ArrowDownLeft} label="Collected" value={money(state.summary.collected_amount)} hint="Approved receipts" /><Metric icon={WalletCards} label="Outstanding" value={money(state.summary.outstanding_amount)} hint="Current receivable" /><Metric icon={BookOpen} label="Customers" value={state.summary.customers_count || 0} hint="Active accounts" /></div><section className="master-panel"><div className="master-toolbar"><label className="master-search"><Search size={14} /><input placeholder="Search customer or code" value={search} onChange={(e) => setSearch(e.target.value)} /></label></div>{state.loading ? <State loading text="Loading customer balances" /> : state.error ? <State text={state.error} /> : <div className="master-table-wrap"><table className="master-table"><thead><tr><th>Customer</th><th>Route</th><th className="numeric">Credit limit</th><th className="numeric">Invoiced</th><th className="numeric">Collected</th><th className="numeric">Outstanding</th></tr></thead><tbody>{state.items.map((item) => <tr key={item.id} className="clickable-row" onClick={() => show(item.id)}><td><strong>{item.shop_name}</strong><span className="muted">{item.code}</span></td><td>{item.route_name || '-'}</td><td className="numeric">{money(item.credit_limit)}</td><td className="numeric">{money(item.invoiced_amount)}</td><td className="numeric">{money(item.collected_amount)}</td><td className="numeric"><strong>{money(item.outstanding_amount)}</strong></td></tr>)}</tbody></table></div>}</section></section>;
}

export function FinanceReceivablesScreen({ detailId = null, onNavigate, canManage = false }) {
    const emptyFilters = { search: '', route_id: '', credit_scope: '', min_credit_limit: '', max_credit_limit: '', min_credit_sales: '', max_credit_sales: '', min_payments: '', max_payments: '', min_returns: '', max_returns: '', min_outstanding: '', max_outstanding: '', min_customer_credit: '', max_customer_credit: '', sort: '' };
    const [filters, setFilters] = useState(emptyFilters);
    const [state, setState] = useState({ loading: true, items: [], filter_options: { routes: [] }, summary: {}, error: '' });
    const [ledger, setLedger] = useState(null);
    const [refresh, setRefresh] = useState(0);
    const [paymentOpen, setPaymentOpen] = useState(false);
    const [saving, setSaving] = useState(false);
    const [paymentForm, setPaymentForm] = useState({ collection_date: today(), amount: '', payment_method: 'cash', reference_no: '', notes: '' });
    const listPath = `${window.ValleyRuntime?.routes?.office || '/office'}/finance/receivables`;

    useEffect(() => {
        const timer = window.setTimeout(() => {
            setState((current) => ({ ...current, loading: true, error: '' }));
            window.axios.get(`${base()}/receivables`, { params: filters })
                .then(({ data }) => setState({ loading: false, ...data.data, error: '' }))
                .catch((error) => setState((current) => ({ ...current, loading: false, error: message(error) })));
        }, 200);
        return () => window.clearTimeout(timer);
    }, [filters, refresh]);

    const updateFilter = (key, value) => setFilters((current) => ({ ...current, [key]: value }));
    const exportCurrentView = () => {
        const rows = [
            ['Customer Credit Report'],
            ['Customers', state.summary.customers_count || 0],
            ['Credit sales MMK', state.summary.credit_sales_amount || 0],
            ['Payments MMK', state.summary.collected_amount || 0],
            ['Return credit MMK', state.summary.return_credits_amount || 0],
            ['Outstanding MMK', state.summary.outstanding_amount || 0],
            ['Customer credit MMK', state.summary.customer_credit_amount || 0],
            [],
            ['Customer code', 'Customer', 'Route', 'Credit limit MMK', 'Credit sales MMK', 'Payments MMK', 'Return credit MMK', 'Outstanding MMK', 'Customer credit MMK', 'Available credit MMK', 'Overdue MMK', 'Next due date'],
            ...state.items.map((item) => [item.code, item.shop_name, item.route_name || '', item.credit_limit, item.invoiced_amount, item.collected_amount, item.return_credits_amount, item.outstanding_amount, item.customer_credit_amount, item.available_credit ?? '', item.overdue_amount, item.next_due_date || '']),
        ];
        const url = URL.createObjectURL(new Blob([`\uFEFF${rows.map((row) => row.map(csvCell).join(',')).join('\r\n')}`], { type: 'text/csv;charset=utf-8' }));
        const link = document.createElement('a');
        link.href = url;
        link.download = `customer-credit-${today()}.csv`;
        link.click();
        URL.revokeObjectURL(url);
    };

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
                {ledger.entries.length ? <div className="finance-ledger-list record-page-ledger">{ledger.entries.map((entry) => <article key={entry.key}><span className={`finance-entry-icon ${entry.type}`}>{entry.type === 'invoice' ? <ArrowUpRight size={15} /> : <ArrowDownLeft size={15} />}</span><span><strong>{entry.reference}</strong><small>{entry.date} / {entry.description}{entry.due_date ? ` / Due ${entry.due_date}` : ''}</small></span><span><strong className={entry.status === 'submitted' ? 'warning' : ''}>{entry.status === 'submitted' ? `Pending ${money(entry.pending_amount)}` : entry.credit ? `- ${money(entry.credit)}` : `+ ${money(entry.debit)}`}</strong><small>Balance {money(entry.balance)}</small></span></article>)}</div> : <State text="No credit sales or payments yet." />}
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
        <Heading title="Customer credit" hint="See each customer's credit sales, payments, outstanding balance, and due status." action={<button className="button" type="button" disabled={state.loading || !state.items.length} onClick={exportCurrentView}><Download size={15} />Export CSV</button>} />
        <div className="metrics finance-metrics">
            <Metric icon={FileText} label="Credit sales" value={money(state.summary.credit_sales_amount)} hint="Confirmed credit orders" />
            <Metric icon={ArrowDownLeft} label="Payments" value={money(state.summary.collected_amount)} hint="Approved payments" />
            <Metric icon={RotateCcw} label="Return credit" value={money(state.summary.return_credits_amount)} hint="Approved sales returns" />
            <Metric icon={WalletCards} label="Outstanding" value={money(state.summary.outstanding_amount)} hint={`Customer credit ${money(state.summary.customer_credit_amount)}`} />
        </div>
        <section className="master-panel">
            <div className="master-toolbar customer-credit-filter-toolbar">
                <label className="master-search"><Search size={14} /><input placeholder="Search customer or code" value={filters.search} onChange={(event) => updateFilter('search', event.target.value)} /></label>
                <Field label={<span>Route</span>}><select value={filters.route_id} onChange={(event) => updateFilter('route_id', event.target.value)}><option value="">All routes</option>{(state.filter_options?.routes || []).map((route) => <option value={route.id} key={route.id}>{route.code} · {route.name}</option>)}</select></Field>
                <Field label={<span>Account status</span>}><select value={filters.credit_scope} onChange={(event) => updateFilter('credit_scope', event.target.value)}><option value="">All accounts</option><option value="activity">Accounts with activity</option><option value="outstanding">Outstanding balance</option><option value="overdue">Overdue balance</option><option value="customer_credit">Customer credit balance</option><option value="settled">Settled accounts</option></select></Field>
                <Field label={<span>Sort by</span>}><select value={filters.sort} onChange={(event) => updateFilter('sort', event.target.value)}><option value="">Customer name</option><option value="outstanding_desc">Highest outstanding</option><option value="credit_sales_desc">Highest credit sales</option><option value="payments_desc">Highest payments</option><option value="customer_credit_desc">Highest customer credit</option><option value="credit_limit_desc">Highest credit limit</option></select></Field>
                <DigitFilter label="Minimum credit limit" value={filters.min_credit_limit} onChange={(value) => updateFilter('min_credit_limit', value)} />
                <DigitFilter label="Maximum credit limit" value={filters.max_credit_limit} placeholder="Any amount" onChange={(value) => updateFilter('max_credit_limit', value)} />
                <DigitFilter label="Minimum credit sales" value={filters.min_credit_sales} onChange={(value) => updateFilter('min_credit_sales', value)} />
                <DigitFilter label="Maximum credit sales" value={filters.max_credit_sales} placeholder="Any amount" onChange={(value) => updateFilter('max_credit_sales', value)} />
                <DigitFilter label="Minimum payments" value={filters.min_payments} onChange={(value) => updateFilter('min_payments', value)} />
                <DigitFilter label="Maximum payments" value={filters.max_payments} placeholder="Any amount" onChange={(value) => updateFilter('max_payments', value)} />
                <DigitFilter label="Minimum returns" value={filters.min_returns} onChange={(value) => updateFilter('min_returns', value)} />
                <DigitFilter label="Maximum returns" value={filters.max_returns} placeholder="Any amount" onChange={(value) => updateFilter('max_returns', value)} />
                <DigitFilter label="Minimum outstanding" value={filters.min_outstanding} onChange={(value) => updateFilter('min_outstanding', value)} />
                <DigitFilter label="Maximum outstanding" value={filters.max_outstanding} placeholder="Any amount" onChange={(value) => updateFilter('max_outstanding', value)} />
                <DigitFilter label="Minimum customer credit" value={filters.min_customer_credit} onChange={(value) => updateFilter('min_customer_credit', value)} />
                <DigitFilter label="Maximum customer credit" value={filters.max_customer_credit} placeholder="Any amount" onChange={(value) => updateFilter('max_customer_credit', value)} />
                <button className="button" type="button" onClick={() => setFilters(emptyFilters)}>Clear filters</button>
                <button className="button" type="button" onClick={() => setRefresh((value) => value + 1)}><RefreshCw size={14} />Refresh</button>
            </div>
            {state.loading ? <State loading text="Loading customer credit" /> : state.error ? <State text={state.error} /> : !state.items.length ? <State text="No customers match this view." /> : <div className="master-table-wrap"><table className="master-table customer-credit-table"><thead><tr><th>Customer</th><th>Route</th><th className="numeric">Credit limit</th><th className="numeric">Credit sales</th><th className="numeric">Payments</th><th className="numeric">Returns</th><th className="numeric">Outstanding</th><th className="numeric">Customer credit</th></tr></thead><tbody>{state.items.map((item) => <tr key={item.id} className="clickable-row" onClick={() => onNavigate?.(`${listPath}/${item.id}`)}><td><strong>{item.shop_name}</strong><span className="muted">{item.code}</span></td><td>{item.route_name || '-'}</td><td className="numeric">{money(item.credit_limit)}</td><td className="numeric">{money(item.invoiced_amount)}</td><td className="numeric">{money(item.collected_amount)}</td><td className="numeric">{money(item.return_credits_amount)}</td><td className="numeric"><strong>{money(item.outstanding_amount)}</strong></td><td className="numeric"><strong>{money(item.customer_credit_amount)}</strong></td></tr>)}</tbody></table></div>}
        </section>
    </section>;
}

export function FinanceExpensesScreen({ expenseType = 'daily', canCreate = false, canReview = false, onNavigate }) {
    const outdoor = expenseType === 'outdoor';
    const emptyFilters = { search: '', status: '', category: '', employee_scope: '', employee_id: '', payment_method: '', source_app: '', submitted_by: '', reviewed_by: '', date_from: '', date_to: '', reviewed_from: '', reviewed_to: '', amount_min: '', amount_max: '', sort: 'newest' };
    const emptyForm = (type = expenseType) => ({
        employee_id: '',
        expense_date: today(),
        expense_type: type,
        category: type === 'outdoor' ? 'travel' : 'utilities',
        description: '',
        amount: '',
        payment_method: 'cash',
        reference_no: '',
        notes: '',
    });
    const [state, setState] = useState({ loading: true, items: [], summary: {}, meta: {}, filter_options: { categories: [], source_apps: [], employees: [], submitters: [], reviewers: [] }, error: '' });
    const [meta, setMeta] = useState({ employees: [], expense_categories: [] });
    const [open, setOpen] = useState(false);
    const [saving, setSaving] = useState(false);
    const [refresh, setRefresh] = useState(0);
    const [draft, setDraft] = useState(emptyFilters);
    const [filters, setFilters] = useState(emptyFilters);
    const [page, setPage] = useState(1);
    const [form, setForm] = useState(() => emptyForm(expenseType));
    const reload = () => setRefresh((value) => value + 1);

    useEffect(() => {
        window.axios.get(`${base()}/meta`).then(({ data }) => setMeta(data.data));
    }, []);
    useEffect(() => {
        setPage(1);
        setOpen(false);
        setDraft(emptyFilters);
        setFilters(emptyFilters);
    }, [expenseType]);
    useEffect(() => {
        setState((current) => ({ ...current, loading: true }));
        window.axios.get(`${base()}/expenses`, { params: { ...filters, expense_type: expenseType, page } })
            .then(({ data }) => setState({ loading: false, ...data.data, error: '' }))
            .catch((error) => setState((current) => ({ ...current, loading: false, error: message(error) })));
    }, [expenseType, filters, page, refresh]);

    const updateDraft = (key, value) => setDraft((current) => ({ ...current, [key]: value }));
    const applyFilters = (event) => {
        event.preventDefault();
        setPage(1);
        setFilters({ ...draft });
    };
    const clearFilters = () => {
        setDraft(emptyFilters);
        setFilters(emptyFilters);
        setPage(1);
    };

    const openExpenseType = (nextType) => {
        const officePath = window.location.pathname.split('/finance/')[0];
        const path = `${officePath}/finance/expenses/${nextType}`;
        if (onNavigate) onNavigate(path);
        else window.location.assign(path);
    };
    const openCreate = () => {
        setForm(emptyForm(expenseType));
        setOpen(true);
    };
    const save = (event) => {
        event.preventDefault();
        setSaving(true);
        window.axios.post(`${base()}/expenses`, { ...form, employee_id: form.employee_id || null })
            .then(() => {
                setOpen(false);
                setPage(1);
                reload();
            })
            .catch((error) => setState((current) => ({ ...current, error: message(error) })))
            .finally(() => setSaving(false));
    };

    return <section className="page finance-page">
        <Heading
            title="Expenses"
            hint="Record and review routine office costs and employee field expenses in one workspace."
            action={canCreate && <button className="button primary" type="button" onClick={openCreate}><Plus size={15} />New expense</button>}
        />
        <div className="metrics finance-metrics">
            <Metric icon={CreditCard} label="Expense value" value={money(state.summary.total_amount)} hint={`${state.summary.records_count || 0} records`} />
            <Metric icon={BadgeCheck} label="Approved" value={money(state.summary.approved_amount)} hint="Posted to books" />
            <Metric icon={CalendarDays} label="Submitted" value={money(state.summary.submitted_amount)} hint="Waiting for review" />
            <Metric icon={CircleDollarSign} label="Average cost" value={money((state.summary.total_amount || 0) / Math.max(state.summary.records_count || 0, 1))} hint="Across current view" />
        </div>
        <section className="master-panel finance-expenses-workspace">
            <nav className="customer-history-tabs finance-expense-tabs" aria-label="Expense type">
                <button className={!outdoor ? 'is-active' : ''} type="button" onClick={() => openExpenseType('daily')} aria-current={!outdoor ? 'page' : undefined}><CreditCard size={15} />Daily expenses</button>
                <button className={outdoor ? 'is-active' : ''} type="button" onClick={() => openExpenseType('outdoor')} aria-current={outdoor ? 'page' : undefined}><ArrowUpRight size={15} />Outdoor expenses</button>
            </nav>
            <form className="master-toolbar expense-filter-toolbar" aria-label={`${label(expenseType)} expense filters`} onSubmit={applyFilters}>
                <label className="master-search"><Search size={14} /><input type="search" aria-label="Search expense records" placeholder="Search expense, reference, employee or notes" value={draft.search} onChange={(event) => updateDraft('search', event.target.value)} /></label>
                <Field label={<span>Status</span>}><select value={draft.status} onChange={(event) => updateDraft('status', event.target.value)}><option value="">All statuses</option><option value="submitted">Submitted</option><option value="approved">Approved</option><option value="rejected">Rejected</option></select></Field>
                <Field label={<span>Category</span>}><select value={draft.category} onChange={(event) => updateDraft('category', event.target.value)}><option value="">All categories</option>{(state.filter_options?.categories || []).map((category) => <option value={category} key={category}>{label(category)}</option>)}</select></Field>
                <Field label={<span>Expense owner</span>}><select value={draft.employee_scope} onChange={(event) => updateDraft('employee_scope', event.target.value)}><option value="">Office and employees</option><option value="office">Office only</option><option value="employee">Employees only</option></select></Field>
                <Field label={<span>Employee</span>}><select value={draft.employee_id} onChange={(event) => updateDraft('employee_id', event.target.value)}><option value="">All employees</option>{(state.filter_options?.employees || []).map((employee) => <option value={employee.id} key={employee.id}>{employee.code} · {employee.name}</option>)}</select></Field>
                <Field label={<span>Payment book</span>}><select value={draft.payment_method} onChange={(event) => updateDraft('payment_method', event.target.value)}><option value="">Cash and bank</option><option value="cash">Cash</option><option value="bank">Bank</option></select></Field>
                <Field label={<span>Source app</span>}><select value={draft.source_app} onChange={(event) => updateDraft('source_app', event.target.value)}><option value="">All source apps</option>{(state.filter_options?.source_apps || []).map((source) => <option value={source} key={source}>{label(source)}</option>)}</select></Field>
                <Field label={<span>Submitted by</span>}><select value={draft.submitted_by} onChange={(event) => updateDraft('submitted_by', event.target.value)}><option value="">All submitters</option>{(state.filter_options?.submitters || []).map((user) => <option value={user.id} key={user.id}>{user.name}</option>)}</select></Field>
                <Field label={<span>Reviewed by</span>}><select value={draft.reviewed_by} onChange={(event) => updateDraft('reviewed_by', event.target.value)}><option value="">All reviewers</option>{(state.filter_options?.reviewers || []).map((user) => <option value={user.id} key={user.id}>{user.name}</option>)}</select></Field>
                <Field label={<span>Expense date from</span>}><input type="date" max={draft.date_to || undefined} value={draft.date_from} onChange={(event) => updateDraft('date_from', event.target.value)} /></Field>
                <Field label={<span>Expense date to</span>}><input type="date" min={draft.date_from || undefined} value={draft.date_to} onChange={(event) => updateDraft('date_to', event.target.value)} /></Field>
                <Field label={<span>Reviewed from</span>}><input type="date" max={draft.reviewed_to || undefined} value={draft.reviewed_from} onChange={(event) => updateDraft('reviewed_from', event.target.value)} /></Field>
                <Field label={<span>Reviewed to</span>}><input type="date" min={draft.reviewed_from || undefined} value={draft.reviewed_to} onChange={(event) => updateDraft('reviewed_to', event.target.value)} /></Field>
                <Field label={<span>Minimum amount</span>}><input type="number" min="0" step="1" placeholder="Any amount" value={draft.amount_min} onChange={(event) => updateDraft('amount_min', event.target.value)} /></Field>
                <Field label={<span>Maximum amount</span>}><input type="number" min={draft.amount_min || '0'} step="1" placeholder="Any amount" value={draft.amount_max} onChange={(event) => updateDraft('amount_max', event.target.value)} /></Field>
                <Field label={<span>Order</span>}><select value={draft.sort} onChange={(event) => updateDraft('sort', event.target.value)}><option value="newest">Newest first</option><option value="oldest">Oldest first</option><option value="amount_desc">Highest amount</option><option value="amount_asc">Lowest amount</option></select></Field>
                <div className="expense-filter-actions"><button className="button" type="button" onClick={clearFilters}>Clear</button><button className="button" type="button" onClick={reload}><RefreshCw size={14} />Refresh</button><button className="button primary" type="submit" disabled={state.loading}><Search size={14} />Apply</button></div>
            </form>
            {state.error && <p className="inline-error">{state.error}</p>}
            {state.loading ? <State loading text="Loading expenses" /> : !state.items.length ? <State text={`No ${outdoor ? 'outdoor' : 'daily'} expenses match this view.`} /> : <>
                <div className="master-table-wrap"><table className="master-table"><thead><tr><th>Expense</th><th>Description</th><th>Employee / source</th><th>Book</th><th className="numeric">Amount</th><th>Status</th><th className="table-actions-header">Actions</th></tr></thead><tbody>{state.items.map((item) => <tr key={item.id}><td><strong>{item.code}</strong><span className="muted">{item.expense_date}{' \u00b7 '}{label(item.category)}</span></td><td>{item.description}</td><td>{item.employee_name || 'Office'}<span className="muted">{label(item.source_app)}</span></td><td>{label(item.payment_method)}<span className="muted">{item.reference_no || '-'}</span></td><td className="numeric"><strong>{money(item.amount)}</strong></td><td><span className={`status ${statusClass(item.status)}`}>{label(item.status)}</span></td><td className="table-actions-cell"><div className="row-actions"><ExpenseVoucherPrintButton item={item} /><ReviewButtons item={item} canManage={canReview} endpoint="expenses" onUpdated={(updated) => setState((current) => mergeFinanceRow(current, updated))} setError={(error) => setState((current) => ({ ...current, error }))} /></div></td></tr>)}</tbody></table></div>
                <Pagination meta={state.meta} page={page} setPage={setPage} />
            </>}
        </section>
        {open && <Modal title={`Record ${outdoor ? 'outdoor' : 'daily'} expense`} saving={saving} onClose={() => setOpen(false)} onSubmit={save}>
            {outdoor && <Field label="Employee"><select required value={form.employee_id} onChange={(event) => setForm((current) => ({ ...current, employee_id: event.target.value }))}><option value="">Select employee</option>{meta.employees.map((item) => <option key={item.id} value={item.id}>{item.label}</option>)}</select></Field>}
            <Field label="Expense date"><input required type="date" value={form.expense_date} onChange={(event) => setForm((current) => ({ ...current, expense_date: event.target.value }))} /></Field>
            <Field label="Category"><select value={form.category} onChange={(event) => setForm((current) => ({ ...current, category: event.target.value }))}>{meta.expense_categories.map((item) => <option key={item} value={item}>{label(item)}</option>)}</select></Field>
            <Field label="Amount (MMK)"><input required min="1" type="number" value={form.amount} onChange={(event) => setForm((current) => ({ ...current, amount: event.target.value }))} /></Field>
            <Field label="Payment book"><select value={form.payment_method} onChange={(event) => setForm((current) => ({ ...current, payment_method: event.target.value }))}><option value="cash">Cash</option><option value="bank">Bank</option></select></Field>
            <Field label="Reference"><input value={form.reference_no} onChange={(event) => setForm((current) => ({ ...current, reference_no: event.target.value }))} /></Field>
            <Field label="Description" wide><input required value={form.description} onChange={(event) => setForm((current) => ({ ...current, description: event.target.value }))} /></Field>
            <Field label="Notes" wide><textarea rows="3" value={form.notes} onChange={(event) => setForm((current) => ({ ...current, notes: event.target.value }))} /></Field>
        </Modal>}
    </section>;
}

export function FinanceBookScreen({ book = 'cash', onNavigate }) {
    const emptyFilters = { search: '', direction: '', category: '', reference_type: '', created_by: '', date_from: '', date_to: '', amount_min: '', amount_max: '', sort: 'newest' };
    const [draft, setDraft] = useState(emptyFilters);
    const [filters, setFilters] = useState(emptyFilters);
    const [state, setState] = useState({ loading: true, items: [], summary: {}, filter_options: { categories: [], reference_types: [], creators: [] }, error: '' });
    const [refresh, setRefresh] = useState(0);

    useEffect(() => {
        setState((current) => ({ ...current, loading: true, error: '' }));
        window.axios.get(`${base()}/books/${book}`, { params: filters })
            .then(({ data }) => setState({ loading: false, ...data.data, error: '' }))
            .catch((error) => setState((current) => ({ ...current, loading: false, error: message(error) })));
    }, [book, filters, refresh]);

    const updateDraft = (key, value) => setDraft((current) => ({ ...current, [key]: value }));
    const applyFilters = (event) => {
        event.preventDefault();
        setFilters({ ...draft });
    };
    const clearFilters = () => {
        setDraft(emptyFilters);
        setFilters(emptyFilters);
    };
    const openBook = (nextBook) => {
        const officePath = window.location.pathname.split('/finance/')[0];
        const path = `${officePath}/finance/books/${nextBook}`;
        if (onNavigate) onNavigate(path);
        else window.location.assign(path);
    };

    return <section className="page finance-page">
        <Heading title="Finance books" hint="Review approved cash and bank inflows, outflows, and running balances in one workspace." action={<button className="icon-button" type="button" aria-label="Refresh finance book" title="Refresh finance book" disabled={state.loading} onClick={() => setRefresh((value) => value + 1)}><RefreshCw className={state.loading ? 'spin' : ''} size={15} /></button>} />
        <div className="metrics finance-metrics finance-metrics-three"><Metric icon={ArrowDownLeft} label="Inflow" value={money(state.summary.inflow)} hint={`${label(book)} receipts`} /><Metric icon={ArrowUpRight} label="Outflow" value={money(state.summary.outflow)} hint={`${label(book)} payments`} /><Metric icon={book === 'cash' ? Banknote : CreditCard} label={`${label(book)} balance`} value={money(state.summary.balance)} hint="Current filtered balance" /></div>
        <section className="master-panel finance-books-workspace">
            <nav className="customer-history-tabs finance-book-tabs" aria-label="Finance books"><button className={book === 'cash' ? 'is-active' : ''} type="button" onClick={() => openBook('cash')} aria-current={book === 'cash' ? 'page' : undefined}><Banknote size={15} />Cash book</button><button className={book === 'bank' ? 'is-active' : ''} type="button" onClick={() => openBook('bank')} aria-current={book === 'bank' ? 'page' : undefined}><CreditCard size={15} />Bank book</button></nav>
            <form className="master-toolbar finance-book-filters" aria-label={`${label(book)} book filters`} onSubmit={applyFilters}>
                <label className="master-search"><Search size={14} /><input type="search" aria-label="Search finance book" placeholder="Search transaction, reference, description or category" value={draft.search} onChange={(event) => updateDraft('search', event.target.value)} /></label>
                <Field label={<span>Direction</span>}><select value={draft.direction} onChange={(event) => updateDraft('direction', event.target.value)}><option value="">All directions</option><option value="in">Money in</option><option value="out">Money out</option></select></Field>
                <Field label={<span>Category</span>}><select value={draft.category} onChange={(event) => updateDraft('category', event.target.value)}><option value="">All categories</option>{(state.filter_options?.categories || []).map((category) => <option value={category} key={category}>{label(category)}</option>)}</select></Field>
                <Field label={<span>Source</span>}><select value={draft.reference_type} onChange={(event) => updateDraft('reference_type', event.target.value)}><option value="">All sources</option>{(state.filter_options?.reference_types || []).map((type) => <option value={type} key={type}>{label(type)}</option>)}</select></Field>
                <Field label={<span>Created by</span>}><select value={draft.created_by} onChange={(event) => updateDraft('created_by', event.target.value)}><option value="">All users</option>{(state.filter_options?.creators || []).map((user) => <option value={user.id} key={user.id}>{user.name}</option>)}</select></Field>
                <Field label={<span>From</span>}><input type="date" max={draft.date_to || undefined} value={draft.date_from} onChange={(event) => updateDraft('date_from', event.target.value)} /></Field>
                <Field label={<span>To</span>}><input type="date" min={draft.date_from || undefined} value={draft.date_to} onChange={(event) => updateDraft('date_to', event.target.value)} /></Field>
                <Field label={<span>Minimum amount</span>}><input type="number" min="0" step="1" placeholder="Any amount" value={draft.amount_min} onChange={(event) => updateDraft('amount_min', event.target.value)} /></Field>
                <Field label={<span>Maximum amount</span>}><input type="number" min={draft.amount_min || '0'} step="1" placeholder="Any amount" value={draft.amount_max} onChange={(event) => updateDraft('amount_max', event.target.value)} /></Field>
                <Field label={<span>Order</span>}><select value={draft.sort} onChange={(event) => updateDraft('sort', event.target.value)}><option value="newest">Newest first</option><option value="oldest">Oldest first</option></select></Field>
                <div className="finance-book-filter-actions"><button className="button" type="button" onClick={clearFilters}>Clear</button><button className="button primary" type="submit" disabled={state.loading}><Search size={14} />Apply</button></div>
            </form>
            {state.error && <p className="inline-error">{state.error}</p>}
            {state.loading ? <State loading text={`Loading ${book} book`} /> : !state.items.length ? <State text={`No ${book} transactions match the current filters.`} /> : <div className="master-table-wrap"><table className="master-table"><thead><tr><th>Transaction</th><th>Description</th><th>Category</th><th className="numeric">In</th><th className="numeric">Out</th><th className="numeric">Balance</th></tr></thead><tbody>{state.items.map((item) => <tr key={item.id}><td><strong>{item.code}</strong><span className="muted">{item.transaction_date}{' \u00b7 '}{item.reference_code || '-'}</span></td><td>{item.description}</td><td>{label(item.category)}</td><td className="numeric positive">{item.direction === 'in' ? money(item.amount) : '-'}</td><td className="numeric negative">{item.direction === 'out' ? money(item.amount) : '-'}</td><td className="numeric"><strong>{money(item.balance)}</strong></td></tr>)}</tbody></table></div>}
        </section>
    </section>;
}

export function SupplierLedgerScreen({ canPay = false, canAdjust = false, canViewSuppliers = false, onNavigate }) {
    const [state, setState] = useState({ loading: true, items: [], summary: {}, error: '' });
    const [selected, setSelected] = useState(null);
    const [ledger, setLedger] = useState(null);
    const [activeTab, setActiveTab] = useState('overview');
    const [invoicePage, setInvoicePage] = useState(1);
    const [ledgerPage, setLedgerPage] = useState(1);
    const [adjustmentOpen, setAdjustmentOpen] = useState(false);
    const [paymentInvoice, setPaymentInvoice] = useState(null);
    const [saving, setSaving] = useState(false);
    const [refresh, setRefresh] = useState(0);
    const [adjustmentForm, setAdjustmentForm] = useState({ entry_date: today(), entry_type: 'adjustment', amount: '', reference_no: '', description: '' });
    const [paymentForm, setPaymentForm] = useState({ payment_date: today(), amount: '', payment_method: 'cash', reference_no: '', notes: '' });

    useEffect(() => {
        window.axios.get(`${base()}/suppliers`)
            .then(({ data }) => {
                setState({ loading: false, ...data.data, error: '' });
                setSelected((value) => value || data.data.items[0]?.id);
            })
            .catch((error) => setState({ loading: false, items: [], summary: {}, error: message(error) }));
    }, [refresh]);

    useEffect(() => {
        if (!selected) return;
        setLedger({ loading: true });
        window.axios.get(`${base()}/suppliers/${selected}/ledger`, { params: { invoice_page: invoicePage, ledger_page: ledgerPage } })
            .then(({ data }) => setLedger(data.data))
            .catch((error) => setLedger({ error: message(error) }));
    }, [selected, invoicePage, ledgerPage, refresh]);

    const saveAdjustment = (event) => {
        event.preventDefault();
        setSaving(true);
        window.axios.post(`${base()}/suppliers/${selected}/ledger`, adjustmentForm)
            .then(() => {
                setAdjustmentOpen(false);
                setAdjustmentForm({ entry_date: today(), entry_type: 'adjustment', amount: '', reference_no: '', description: '' });
                setLedgerPage(1);
                setRefresh((value) => value + 1);
            })
            .finally(() => setSaving(false));
    };

    const openPayment = (invoice) => {
        setPaymentInvoice(invoice);
        setPaymentForm({ payment_date: today(), amount: String(invoice.outstanding), payment_method: 'cash', reference_no: '', notes: '' });
    };

    const savePayment = (event) => {
        event.preventDefault();
        setSaving(true);
        window.axios.post(`${base()}/suppliers/${selected}/payments`, { ...paymentForm, supplier_invoice_id: paymentInvoice.id })
            .then(() => {
                setPaymentInvoice(null);
                setRefresh((value) => value + 1);
            })
            .finally(() => setSaving(false));
    };

    const suppliersPath = `${window.ValleyRuntime?.routes?.office || '/office'}/master/suppliers`;
    const invoiceStatusClass = (status) => status === 'paid' ? 'success' : status === 'overdue' ? 'danger' : status === 'partial' ? 'warning' : 'neutral';

    return <section className="page finance-page supplier-payables-page">
        <Heading title="Supplier payables" hint="Review supplier invoices, due dates, payments, and complete account history." action={<>
            {canViewSuppliers && <button className="button" type="button" onClick={() => onNavigate?.(suppliersPath)}><BookOpen size={15} />Suppliers</button>}
            {canAdjust && selected && <button className="button" type="button" onClick={() => setAdjustmentOpen(true)}><Plus size={15} />Adjustment</button>}
        </>} />

        <div className="finance-supplier-layout">
            <section className="master-panel finance-supplier-list">
                <div className="master-panel-heading"><div><p className="eyebrow">Suppliers</p><h2>{state.items.length} accounts · {money(state.summary.payable_amount)}</h2></div></div>
                {state.loading ? <State loading text="Loading suppliers" /> : state.error ? <State text={state.error} /> : state.items.map((item) => <button className={selected === item.id ? 'is-selected' : ''} key={item.id} onClick={() => { setSelected(item.id); setActiveTab('overview'); setInvoicePage(1); setLedgerPage(1); }}><span><strong>{item.name}</strong><small>{item.code} · {item.phone || 'No phone'}</small></span><strong>{money(item.balance)}</strong></button>)}
            </section>

            <section className="master-panel finance-supplier-ledger">
                {ledger?.loading ? <State loading text="Loading supplier payables" /> : ledger?.error ? <State text={ledger.error} /> : ledger ? <>
                    <div className="master-panel-heading"><div><p className="eyebrow">Supplier account</p><h2>{ledger.supplier.name}</h2></div><strong>{money(ledger.summary.balance)}</strong></div>
                    <div className="customer-history-tabs employee-history-tabs supplier-payable-tabs" role="tablist" aria-label="Supplier payable information">
                        {[['overview', 'Overview'], ['invoices', 'Purchase invoices'], ['ledger', 'Account ledger']].map(([key, tabLabel]) => <button type="button" role="tab" aria-selected={activeTab === key} className={activeTab === key ? 'is-active' : ''} onClick={() => setActiveTab(key)} key={key}>{tabLabel}{key === 'invoices' && <span>{ledger.summary.invoice_count}</span>}{key === 'ledger' && <span>{ledger.summary.ledger_count}</span>}</button>)}
                    </div>

                    {activeTab === 'overview' && <div className="supplier-payable-tab-content" role="tabpanel">
                        <div className="supplier-payable-summary">
                            <article><span>Invoice total</span><strong>{money(ledger.summary.invoice_total)}</strong></article>
                            <article><span>Paid</span><strong>{money(ledger.summary.paid_amount)}</strong></article>
                            <article><span>Outstanding</span><strong>{money(ledger.summary.outstanding_amount)}</strong></article>
                            <article className={Number(ledger.summary.overdue_amount) > 0 ? 'is-overdue' : ''}><span>Overdue</span><strong>{money(ledger.summary.overdue_amount)}</strong></article>
                        </div>
                        <div className="supplier-payable-overview-counts">
                            <article><span>Total invoices</span><strong>{ledger.summary.invoice_count}</strong></article>
                            <article><span>Open invoices</span><strong>{ledger.summary.open_invoice_count}</strong></article>
                            <article><span>Paid invoices</span><strong>{ledger.summary.paid_invoice_count}</strong></article>
                            <article><span>Ledger entries</span><strong>{ledger.summary.ledger_count}</strong></article>
                        </div>
                    </div>}

                    {activeTab === 'invoices' && <div className="supplier-payable-tab-content" role="tabpanel">
                        <div className="supplier-subsection-heading"><div><p className="eyebrow">Accounts payable</p><h3>Purchase invoices</h3></div><span>{ledger.invoice_meta.total} invoices</span></div>
                        {ledger.invoices.length ? <><div className="master-table-wrap supplier-invoice-wrap"><table className="master-table supplier-invoice-table"><thead><tr><th>Invoice</th><th>Invoice / due date</th><th className="numeric">Total</th><th className="numeric">Paid</th><th className="numeric">Outstanding</th><th>Status</th>{canPay && <th className="table-actions-header">Actions</th>}</tr></thead><tbody>{ledger.invoices.map((invoice) => <tr key={invoice.id}><td><strong>{invoice.code}</strong><span className="muted">{invoice.invoice_no || invoice.stock_document_code || '-'}</span></td><td><strong>{invoice.invoice_date}</strong><span className="muted">Due {invoice.due_date || '-'}</span></td><td className="numeric">{money(invoice.total)}</td><td className="numeric">{money(invoice.paid_amount)}</td><td className="numeric"><strong>{money(invoice.outstanding)}</strong></td><td><span className={`status ${invoiceStatusClass(invoice.status)}`}>{label(invoice.status)}</span></td>{canPay && <td className="table-actions-cell"><div className="row-actions">{Number(invoice.outstanding) > 0 && <button className="button small" type="button" onClick={() => openPayment(invoice)}>Pay</button>}</div></td>}</tr>)}</tbody></table></div><Pagination meta={ledger.invoice_meta} page={invoicePage} setPage={setInvoicePage} /></> : <State text="No supplier purchase invoices have been recorded." />}
                    </div>}

                    {activeTab === 'ledger' && <div className="supplier-payable-tab-content" role="tabpanel">
                        <div className="supplier-subsection-heading"><div><p className="eyebrow">Audit trail</p><h3>Account ledger</h3></div><span>{ledger.ledger_meta.total} entries</span></div>
                        {ledger.items.length ? <><div className="master-table-wrap"><table className="master-table supplier-ledger-table"><thead><tr><th>Date / reference</th><th>Description</th><th className="numeric">Payment</th><th className="numeric">Purchase</th><th className="numeric">Balance</th></tr></thead><tbody>{ledger.items.map((item) => <tr key={item.id}><td><strong>{item.entry_date}</strong><span className="muted">{item.reference_no || label(item.entry_type)}</span></td><td>{item.description}</td><td className="numeric">{item.debit ? money(item.debit) : '-'}</td><td className="numeric">{item.credit ? money(item.credit) : '-'}</td><td className="numeric"><strong>{money(item.balance)}</strong></td></tr>)}</tbody></table></div><Pagination meta={ledger.ledger_meta} page={ledgerPage} setPage={setLedgerPage} /></> : <State text="No supplier ledger entries have been recorded." />}
                    </div>}
                </> : <State text="Select a supplier." />}
            </section>
        </div>

        {adjustmentOpen && <Modal title="Supplier balance adjustment" saving={saving} onClose={() => setAdjustmentOpen(false)} onSubmit={saveAdjustment} submitLabel="Save adjustment">
            <Field label="Adjustment date"><input required type="date" value={adjustmentForm.entry_date} onChange={(event) => setAdjustmentForm((value) => ({ ...value, entry_date: event.target.value }))} /></Field>
            <Field label="Amount (MMK)"><input required step="0.01" type="number" value={adjustmentForm.amount} placeholder="Positive increases, negative reduces" onChange={(event) => setAdjustmentForm((value) => ({ ...value, amount: event.target.value }))} /></Field>
            <Field label="Reference"><input value={adjustmentForm.reference_no} onChange={(event) => setAdjustmentForm((value) => ({ ...value, reference_no: event.target.value }))} /></Field>
            <Field label="Description" wide><input required value={adjustmentForm.description} onChange={(event) => setAdjustmentForm((value) => ({ ...value, description: event.target.value }))} /></Field>
        </Modal>}

        {paymentInvoice && <Modal title="Record supplier payment" saving={saving} onClose={() => setPaymentInvoice(null)} onSubmit={savePayment} submitLabel="Record payment">
            <Field label="Invoice"><input readOnly value={`${paymentInvoice.code} · Outstanding ${money(paymentInvoice.outstanding)}`} /></Field>
            <Field label="Payment date"><input required type="date" value={paymentForm.payment_date} onChange={(event) => setPaymentForm((value) => ({ ...value, payment_date: event.target.value }))} /></Field>
            <Field label="Amount (MMK)"><input required min="0.01" max={paymentInvoice.outstanding} step="0.01" type="number" value={paymentForm.amount} onChange={(event) => setPaymentForm((value) => ({ ...value, amount: event.target.value }))} /></Field>
            <Field label="Payment book"><select value={paymentForm.payment_method} onChange={(event) => setPaymentForm((value) => ({ ...value, payment_method: event.target.value }))}><option value="cash">Cash</option><option value="bank">Bank</option></select></Field>
            <Field label="Payment reference"><input value={paymentForm.reference_no} onChange={(event) => setPaymentForm((value) => ({ ...value, reference_no: event.target.value }))} /></Field>
            <Field label="Notes" wide><textarea rows="3" value={paymentForm.notes} onChange={(event) => setPaymentForm((value) => ({ ...value, notes: event.target.value }))} /></Field>
        </Modal>}
    </section>;
}
function ProfitTrendChart({ items = [], granularity = 'month' }) {
    const max = Math.max(1, ...items.flatMap((item) => [Number(item.revenue || 0), Number(item.cost || 0)]));

    return <section className="master-panel profit-chart-panel">
        <header><div><p className="eyebrow">Performance trend</p><h2>Revenue and operating costs</h2><span>{granularity === 'day' ? 'Daily movement for the selected month' : 'Monthly movement for the selected year'}</span></div><div className="profit-chart-legend"><span><i className="revenue" />Revenue</span><span><i className="cost" />Costs</span></div></header>
        <div className={`profit-trend-scroll ${granularity}`}>
            <div className="profit-trend-chart" style={{ '--profit-period-count': items.length }}>
                {items.map((item) => <article key={item.key} title={`${item.label}: revenue ${money(item.revenue)}, costs ${money(item.cost)}, net ${money(item.net_profit)}`}>
                    <div className="profit-chart-bars"><i className="revenue" style={{ height: `${Math.max(item.revenue ? 4 : 0, Number(item.revenue || 0) / max * 100)}%` }} /><i className="cost" style={{ height: `${Math.max(item.cost ? 4 : 0, Number(item.cost || 0) / max * 100)}%` }} /></div>
                    <strong>{item.label}</strong>
                    <small className={Number(item.net_profit) >= 0 ? 'positive' : 'negative'}>{Number(item.net_profit) >= 0 ? '+' : ''}{new Intl.NumberFormat('en-US', { notation: 'compact', maximumFractionDigits: 1 }).format(Number(item.net_profit || 0))}</small>
                </article>)}
            </div>
        </div>
    </section>;
}

function ProfitCostChart({ items = [], total = 0 }) {
    const max = Math.max(1, ...items.map((item) => Number(item.amount || 0)));

    return <section className="master-panel profit-cost-panel">
        <header><div><p className="eyebrow">Cost structure</p><h2>Operating cost breakdown</h2><span>Share of approved operating costs</span></div></header>
        <div className="profit-cost-bars">
            {items.length ? items.map((item) => <article key={item.key}><div><strong>{item.label}</strong><span>{total > 0 ? `${(Number(item.amount || 0) / total * 100).toFixed(1)}%` : '0%'}</span></div><i><b style={{ width: `${Number(item.amount || 0) / max * 100}%` }} /></i><small>{money(item.amount)}</small></article>) : <p className="phase10-empty">No approved costs for this period.</p>}
        </div>
    </section>;
}

export function ProfitLossScreen() {
    const now = new Date();
    const currentYear = String(now.getFullYear());
    const currentMonth = String(now.getMonth() + 1).padStart(2, '0');
    const [filters, setFilters] = useState({ view: 'yearly', year: currentYear, month: currentMonth });
    const [state, setState] = useState({ loading: true, trend: [], expense_breakdown: [], cost_categories: [], analysis: {}, error: '' });
    const yearOptions = Array.from({ length: Math.max(1, Number(currentYear) - 2019) }, (_, index) => String(Number(currentYear) - index));
    const monthOptions = [
        ['01', 'January'], ['02', 'February'], ['03', 'March'], ['04', 'April'], ['05', 'May'], ['06', 'June'],
        ['07', 'July'], ['08', 'August'], ['09', 'September'], ['10', 'October'], ['11', 'November'], ['12', 'December'],
    ].filter(([value]) => filters.year !== currentYear || Number(value) <= Number(currentMonth));

    const reportParams = () => {
        if (filters.view === 'monthly') {
            const selectedMonth = `${filters.year}-${filters.month}`;
            const year = Number(filters.year);
            const month = Number(filters.month);
            const lastDay = new Date(year, month, 0).getDate();
            const isCurrentMonth = selectedMonth === `${currentYear}-${currentMonth}`;
            return { date_from: `${selectedMonth}-01`, date_to: isCurrentMonth ? today() : `${selectedMonth}-${String(lastDay).padStart(2, '0')}`, granularity: 'day' };
        }
        return { date_from: `${filters.year}-01-01`, date_to: filters.year === currentYear ? today() : `${filters.year}-12-31`, granularity: 'month' };
    };

    const load = () => {
        setState((current) => ({ ...current, loading: true, error: '' }));
        window.axios.get(`${base()}/profit-loss`, { params: reportParams() })
            .then(({ data }) => setState({ loading: false, ...data.data, error: '' }))
            .catch((error) => setState({ loading: false, trend: [], expense_breakdown: [], cost_categories: [], analysis: {}, error: message(error) }));
    };

    useEffect(() => { load(); }, []);

    const costs = state.costs || {};
    const analysis = state.analysis || {};
    const trend = state.trend || [];

    return <section className="page finance-page profit-loss-report">
        <Heading title="Profit & loss" hint="Monthly and yearly operating performance with revenue, cost, margin, and detailed trends." />
        <form className="master-toolbar profit-period-filter" aria-label="Profit and loss filters" onSubmit={(event) => { event.preventDefault(); load(); }}>
            <Field label={<span>Period</span>}><select value={filters.view} onChange={(event) => setFilters((value) => ({ ...value, view: event.target.value }))}><option value="monthly">Monthly</option><option value="yearly">Yearly</option></select></Field>
            <Field label={<span>Year</span>}><select value={filters.year} onChange={(event) => setFilters((value) => ({ ...value, year: event.target.value, month: event.target.value === currentYear && Number(value.month) > Number(currentMonth) ? currentMonth : value.month }))}>{yearOptions.map((year) => <option value={year} key={year}>{year}</option>)}</select></Field>
            {filters.view === 'monthly' && <Field label={<span>Month</span>}><select value={filters.month} onChange={(event) => setFilters((value) => ({ ...value, month: event.target.value }))}>{monthOptions.map(([value, name]) => <option value={value} key={value}>{name}</option>)}</select></Field>}
            <button className="button primary" type="submit" disabled={state.loading}><RefreshCw size={14} />Run report</button>
        </form>

        {state.loading ? <State loading text="Calculating profit and loss" /> : state.error ? <State text={state.error} /> : <>
            <div className="metrics finance-metrics profit-kpis">
                <Metric icon={TrendingUp} label="Revenue" value={money(state.revenue)} hint="Issued invoice value" />
                <Metric icon={ArrowUpRight} label="Operating costs" value={money(costs.total)} hint="Approved costs" />
                <Metric icon={CircleDollarSign} label="Net profit" value={money(state.net_profit)} hint={state.net_profit >= 0 ? 'Profitable period' : 'Loss for period'} />
                <Metric icon={BadgeCheck} label="Operating margin" value={`${Number(state.margin_percent || 0).toFixed(1)}%`} hint={`${analysis.profitable_periods || 0} profitable · ${analysis.loss_periods || 0} loss periods`} />
            </div>

            <ProfitTrendChart items={trend} granularity={state.period?.granularity} />

            <div className="profit-report-grid">
                <ProfitCostChart items={state.expense_breakdown || []} total={costs.total} />
                <section className="master-panel profit-loss-statement"><header><div><p className="eyebrow">Operating statement</p><h2>{state.period.date_from} to {state.period.date_to}</h2></div><span className={`status ${state.net_profit >= 0 ? 'success' : 'danger'}`}>{state.net_profit >= 0 ? 'Profit' : 'Loss'}</span></header><div className="profit-row revenue"><span>Invoice revenue</span><strong>{money(state.revenue)}</strong></div><p className="profit-section-label">Operating costs</p><div className="profit-row"><span>Payroll</span><strong>{money(costs.payroll)}</strong></div><div className="profit-row"><span>Vehicle costs</span><strong>{money(costs.vehicle)}</strong></div><div className="profit-row"><span>Outdoor employee expenses</span><strong>{money(costs.outdoor_employee)}</strong></div><div className="profit-row"><span>Daily expenses</span><strong>{money(costs.daily_expense)}</strong></div><div className="profit-row total"><span>Total operating costs</span><strong>{money(costs.total)}</strong></div><div className="profit-row net"><span>Net profit / loss</span><strong>{money(state.net_profit)}</strong></div></section>
            </div>

            <section className="master-panel profit-period-detail"><header><div><p className="eyebrow">Detailed report</p><h2>{filters.view === 'monthly' ? 'Daily profit and loss' : 'Monthly profit and loss'}</h2><span>Revenue and each operating cost source by period</span></div><strong>{trend.length} periods</strong></header><div className="master-table-wrap"><table className="master-table profit-detail-table"><thead><tr><th>Period</th><th className="numeric">Revenue</th><th className="numeric">Payroll</th><th className="numeric">Vehicle</th><th className="numeric">Outdoor</th><th className="numeric">Daily</th><th className="numeric">Total cost</th><th className="numeric">Net profit</th></tr></thead><tbody>{trend.map((item) => <tr key={item.key}><td><strong>{item.label}</strong><span className="muted">{item.key}</span></td><td className="numeric">{money(item.revenue)}</td><td className="numeric">{money(item.payroll)}</td><td className="numeric">{money(item.vehicle)}</td><td className="numeric">{money(item.outdoor_employee)}</td><td className="numeric">{money(item.daily_expense)}</td><td className="numeric">{money(item.cost)}</td><td className={`numeric ${Number(item.net_profit) >= 0 ? 'positive' : 'negative'}`}><strong>{money(item.net_profit)}</strong></td></tr>)}</tbody><tfoot><tr><th>Total</th><th className="numeric">{money(state.revenue)}</th><th className="numeric">{money(costs.payroll)}</th><th className="numeric">{money(costs.vehicle)}</th><th className="numeric">{money(costs.outdoor_employee)}</th><th className="numeric">{money(costs.daily_expense)}</th><th className="numeric">{money(costs.total)}</th><th className={`numeric ${Number(state.net_profit) >= 0 ? 'positive' : 'negative'}`}>{money(state.net_profit)}</th></tr></tfoot></table></div></section>

            <section className="master-panel profit-category-detail"><header><div><p className="eyebrow">Cost detail</p><h2>Expense categories</h2><span>Approved costs grouped by source and category</span></div></header><div>{(state.cost_categories || []).map((item) => <article key={item.label}><span>{item.label}</span><strong>{money(item.amount)}</strong></article>)}</div></section>
        </>}
    </section>;
}

function LegacyMobileFinanceScreen({ appId, mode = 'ledger', locale = 'en' }) {
    const [state, setState] = useState({ loading: true, error: '' }); const [meta, setMeta] = useState({ customers: [], deliveries: [], expense_categories: [] }); const [open, setOpen] = useState(false); const [saving, setSaving] = useState(false); const [success, setSuccess] = useState(''); const [refresh, setRefresh] = useState(0); const [ledgerSearch, setLedgerSearch] = useState('');
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
    const allItems = appId === 'client' ? state.entries || [] : collectionMode ? state.collections || [] : state.expenses || [];
    const normalizedLedgerSearch = ledgerSearch.trim().toLowerCase();
    const items = appId === 'client' && normalizedLedgerSearch ? allItems.filter((item) => [item.reference, item.description, item.date, item.status, item.type, item.debit, item.credit, item.pending_amount, item.balance].filter((value) => value !== null && value !== undefined).join(' ').toLowerCase().includes(normalizedLedgerSearch)) : allItems;
    const save = (e) => { e.preventDefault(); setSaving(true); window.axios.post(`${mobileBase()}/${collectionMode ? 'collections' : 'expenses'}`, form).then(({ data }) => { const item = data.data[collectionMode ? 'collection' : 'expense']; const key = collectionMode ? 'collections' : 'expenses'; const amountKey = collectionMode ? 'collections_amount' : 'expenses_amount'; setState((current) => ({ ...current, [key]: [item, ...(current[key] || [])], summary: { ...current.summary, [amountKey]: Number(current.summary?.[amountKey] || 0) + Number(item.amount || 0), pending_count: Number(current.summary?.pending_count || 0) + 1 } })); setOpen(false); setSuccess(data.message); }).catch((error) => setState((s) => ({ ...s, error: message(error) }))).finally(() => setSaving(false)); };
    const deliveryChanged = (id) => { const delivery = meta.deliveries.find((i) => String(i.id) === id); setForm((v) => ({ ...v, delivery_id: id, customer_id: delivery?.customer_id || '' })); };
    if (state.loading) return <State loading text="Loading account records" />;
    if (appId === 'client') return <div className="mobile-master-stack mobile-finance">
        <div className="mobile-master-heading"><div><p className="eyebrow">My account</p><h1>Ledger & payments</h1><span className="muted">Invoices, approved payments, and payments awaiting Office review.</span></div></div>
        <div className="mobile-order-filters mobile-ledger-search">
            <label className="mobile-search"><Search size={16} /><input type="search" value={ledgerSearch} placeholder="Search reference, date or payment" aria-label="Search ledger activity" onChange={(event) => setLedgerSearch(event.target.value)} /></label>
        </div>
        <div className="mobile-finance-balance"><span>Outstanding balance</span><strong>{money(state.summary?.outstanding_amount)}</strong><small>Paid {money(state.summary?.collected_amount)} of {money(state.summary?.invoiced_amount)}</small>{Number(state.summary?.pending_collection_count || 0) > 0 && <small className="pending-payment-summary">Pending review / {money(state.summary?.pending_collection_amount)}</small>}</div>
        <section className="mobile-master-section">
            <div className="mobile-section-heading"><div><h2>Account activity</h2><small>{items.length === allItems.length ? `${items.length} records` : `${items.length} of ${allItems.length} records`}</small></div></div>
            {items.length ? <div className="mobile-finance-list">{items.map((item) => <article key={item.key} className={item.status === 'submitted' ? 'pending-payment-entry' : ''}><span className={`finance-entry-icon ${item.type}`}>{item.type === 'invoice' ? <ArrowUpRight size={15} /> : <ArrowDownLeft size={15} />}</span><span><strong>{item.reference}</strong><small>{item.date} / {item.description}</small></span><span><strong className={item.status === 'submitted' ? 'warning' : ''}>{item.status === 'submitted' ? money(item.pending_amount) : item.credit ? `- ${money(item.credit)}` : `+ ${money(item.debit)}`}</strong><small>{item.status === 'submitted' ? `Pending / Balance ${money(item.balance)}` : `Balance ${money(item.balance)}`}</small></span></article>)}</div> : <div className="workspace-state compact"><Search size={20} /><strong>{allItems.length ? 'No ledger records match this search.' : 'No ledger activity yet.'}</strong></div>}
        </section>
    </div>;
    return <div className="mobile-master-stack mobile-finance"><div className="mobile-master-heading"><div><p className="eyebrow">Field finance</p><h1>{collectionMode ? 'Collections' : 'Outdoor expenses'}</h1><span className="muted">Submit records for Office review and track their status.</span></div>{collectionMode && <ShellPageActions><button className="button primary" onClick={() => setOpen(true)}><Plus size={15} />New</button></ShellPageActions>}</div>{success && <p className="inline-success"><BadgeCheck size={14} />{success}</p>}{state.error && <p className="inline-error">{state.error}</p>}<div className="mobile-finance-summary"><article><span>{collectionMode ? 'Collected' : 'Expenses'}</span><strong>{money(collectionMode ? state.summary?.collections_amount : state.summary?.expenses_amount)}</strong></article><article><span>Pending review</span><strong>{state.summary?.pending_count || 0}</strong></article></div><section className="mobile-master-section"><div className="mobile-section-heading"><div><h2>Submitted records</h2><small>{items.length} records</small></div></div><div className="mobile-finance-list">{items.map((item) => <article key={item.id}><span className={`finance-entry-icon ${collectionMode ? 'collection' : 'expense'}`}>{collectionMode ? <WalletCards size={15} /> : <CreditCard size={15} />}</span><span><strong>{item.code}</strong><small>{collectionMode ? item.shop_name : label(item.category)} · {item.collection_date || item.expense_date}</small></span><span><strong>{money(item.amount)}</strong><small className={statusClass(item.status)}>{label(item.status)}</small></span></article>)}</div></section>{!collectionMode && <button className="mobile-order-fab" type="button" title="Add expense" aria-label="Add expense" onClick={() => setOpen(true)}><Plus size={24} aria-hidden="true" /></button>}{open && <Modal title={collectionMode ? 'Submit field collection' : 'Submit outdoor expense'} saving={saving} onClose={() => setOpen(false)} onSubmit={save} submitLabel="Submit for review">{collectionMode && appId === 'driver' && <Field label="Delivery"><select required value={form.delivery_id} onChange={(e) => deliveryChanged(e.target.value)}><option value="">Select delivery</option>{meta.deliveries.map((i) => <option key={i.id} value={i.id}>{i.code} · {i.shop_name}</option>)}</select></Field>}{collectionMode && appId === 'sales' && <Field label="Customer"><CustomerCombobox customers={meta.customers} value={form.customer_id} onChange={(customerId) => setForm((v) => ({ ...v, customer_id: customerId }))} /></Field>}<Field label={collectionMode ? 'Collection date' : 'Expense date'}><input required type="date" value={form.collection_date || form.expense_date} onChange={(e) => setForm((v) => ({ ...v, [collectionMode ? 'collection_date' : 'expense_date']: e.target.value }))} /></Field>{!collectionMode && <Field label="Category"><select value={form.category} onChange={(e) => setForm((v) => ({ ...v, category: e.target.value }))}>{meta.expense_categories.map((i) => <option value={i} key={i}>{label(i)}</option>)}</select></Field>}<Field label="Amount (MMK)"><input required min="1" type="number" value={form.amount} onChange={(e) => setForm((v) => ({ ...v, amount: e.target.value }))} /></Field><Field label="Payment book"><select value={form.payment_method} onChange={(e) => setForm((v) => ({ ...v, payment_method: e.target.value }))}><option value="cash">Cash</option><option value="bank">Bank</option></select></Field><Field label="Reference"><input value={form.reference_no} onChange={(e) => setForm((v) => ({ ...v, reference_no: e.target.value }))} /></Field>{!collectionMode && <Field label="Description" wide><input required value={form.description} onChange={(e) => setForm((v) => ({ ...v, description: e.target.value }))} /></Field>}<Field label="Notes" wide><textarea rows="3" value={form.notes} onChange={(e) => setForm((v) => ({ ...v, notes: e.target.value }))} /></Field></Modal>}</div>;
}

export function MobileFinanceScreen(props) {
    if (props.mode === 'collections' && props.appId !== 'client') {
        return <MobileFieldCollectionsScreen appId={props.appId} />;
    }

    return <LegacyMobileFinanceScreen {...props} />;
}
