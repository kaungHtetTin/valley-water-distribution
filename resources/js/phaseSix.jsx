import { ArrowLeft, Ban, CalendarDays, CheckCircle2, ChevronRight, MapPin, Navigation, Package, Pencil, Plus, RefreshCw, Save, Search, TriangleAlert, Truck, X } from 'lucide-react';
import { useEffect, useId, useMemo, useRef, useState } from 'react';
import { DetailPage, DetailPanel } from './components/DetailPage';

const statuses = ['planned', 'assigned', 'loading', 'on_route', 'delivered', 'partially_delivered', 'failed', 'cancelled'];
const blank = { invoice_ids: [], warehouse_id: '', route_id: '', driver_id: '', vehicle_id: '', planned_date: new Date().toISOString().slice(0, 10), delivery_address: '', notes: '' };
const api = () => window.ValleyRuntime?.api?.deliveries || '/api/deliveries';
const errorMessage = (error) => error.response?.data?.message || 'The delivery request could not be completed.';
const number = (value) => Number(value || 0).toLocaleString();
const money = (value) => `${number(value)} MMK`;
const date = (value) => value ? new Date(`${value}T00:00:00`).toLocaleDateString([], { year: 'numeric', month: 'short', day: '2-digit' }) : '-';
const statusLabel = (value) => String(value || '').replaceAll('_', ' ');
const statusFamily = (value) => ['delivered'].includes(value) ? 'success' : ['failed', 'cancelled'].includes(value) ? 'danger' : ['loading', 'on_route'].includes(value) ? 'info' : ['partially_delivered'].includes(value) ? 'warning' : 'neutral';

export function DeliveryPlanningScreen({ canManage = false, historyOnly = false, locale = 'en', navigate, detailId = null }) {
    const localized = locale === 'my';
    const [meta, setMeta] = useState({ loading: true, invoices: [], warehouses: [], routes: [], drivers: [], vehicles: [], error: '' });
    const [filters, setFilters] = useState({ search: '', status: '', driver_id: '', date: '' });
    const [state, setState] = useState({ loading: true, items: [], summary: {}, meta: {}, error: '' });
    const [page, setPage] = useState(1);
    const [refresh, setRefresh] = useState(0);
    const [detail, setDetail] = useState({ loading: false, delivery: null, trip: null, stops: [], stock: [], items: [], error: '' });
    const [selectedStopId, setSelectedStopId] = useState(null);
    const [cancelOpen, setCancelOpen] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [actionError, setActionError] = useState('');

    const loadMeta = () => window.axios.get(`${api()}/meta`).then(({ data }) => setMeta({ loading: false, ...data.data, error: '' })).catch((error) => setMeta({ loading: false, invoices: [], warehouses: [], routes: [], drivers: [], vehicles: [], error: errorMessage(error) }));
    useEffect(() => { loadMeta(); }, [refresh]);
    useEffect(() => {
        let mounted = true;
        setState((current) => ({ ...current, loading: true, error: '' }));
        const timer = window.setTimeout(() => window.axios.get(api(), { params: { ...filters, history: historyOnly ? 1 : undefined, page, per_page: 20 } }).then(({ data }) => mounted && setState({ loading: false, items: data.data.items, summary: data.data.summary, meta: data.data.meta, error: '' })).catch((error) => mounted && setState({ loading: false, items: [], summary: {}, meta: {}, error: errorMessage(error) })), 220);
        return () => { mounted = false; window.clearTimeout(timer); };
    }, [filters, historyOnly, page, refresh]);
    useEffect(() => { setPage(1); }, [filters]);

    const openForm = () => navigate(`${window.ValleyRuntime?.routes?.office || '/office'}/deliveries/new`);
    const loadDetail = (id) => {
        setDetail({ loading: true, delivery: null, trip: null, stops: [], stock: [], items: [], error: '' });
        window.axios.get(`${api()}/${id}`).then(({ data }) => {
            const stops = data.data.stops || [];
            setDetail({ loading: false, delivery: data.data.delivery, trip: data.data.trip, stops, stock: data.data.stock || [], items: data.data.items, error: '' });
            setSelectedStopId(stops[0]?.id || null);
        }).catch((error) => setDetail({ loading: false, delivery: null, trip: null, stops: [], stock: [], items: [], error: errorMessage(error) }));
    };
    const show = (id) => navigate(`${window.ValleyRuntime?.routes?.office || '/office'}/deliveries${historyOnly ? '/history' : ''}/${id}`);

    useEffect(() => {
        setSelectedStopId(null);
        if (detailId) loadDetail(detailId);
        else setDetail({ loading: false, delivery: null, trip: null, stops: [], stock: [], items: [], error: '' });
    }, [detailId]);

    const listPath = `${window.ValleyRuntime?.routes?.office || '/office'}/deliveries${historyOnly ? '/history' : ''}`;
    const selectedStop = detail.stops.find((stop) => stop.id === selectedStopId) || detail.stops[0] || null;
    const tripStatus = detail.trip?.status || detail.delivery?.status;
    const canChangePlan = canManage && ['planned', 'assigned'].includes(tripStatus) && detail.stops.every((stop) => Number(stop.loaded_quantity || 0) === 0);
    const cancelTrip = () => {
        if (!detail.delivery || processing) return;
        setProcessing(true); setActionError('');
        window.axios.post(`${api()}/${detail.delivery.id}/cancel`).then(({ data }) => {
            const stops = data.data.stops || [];
            setDetail({ loading: false, delivery: data.data.delivery, trip: data.data.trip, stops, stock: data.data.stock || [], items: data.data.items || [], error: '' });
            setCancelOpen(false);
        }).catch((error) => setActionError(errorMessage(error))).finally(() => setProcessing(false));
    };

    if (detailId) return (<>
        <DetailPage
            eyebrow="Delivery operations"
            title={detail.trip?.code || detail.delivery?.trip_code || detail.delivery?.code || 'Loading'}
            subtitle={detail.delivery ? `${detail.trip?.orders_count || detail.stops.length} delivery stops · ${detail.delivery.route_name}` : 'Trip detail'}
            onBack={() => navigate(listPath)}
            aside={detail.delivery && <DetailPanel eyebrow="Trip summary"><div className="delivery-trip-summary"><span><small>Stops</small><strong>{number(detail.trip?.orders_count || detail.stops.length)}</strong></span><span><small>Required units</small><strong>{number(detail.trip?.total_quantity || 0)}</strong></span><span className="delivery-trip-summary-status"><small>Current status</small><strong><span className={`status ${statusFamily(detail.trip?.status || detail.delivery.status)}`}>{statusLabel(detail.trip?.status || detail.delivery.status)}</span></strong></span></div></DetailPanel>}
            actions={detail.delivery && canManage ? <>{actionError && <p className="form-alert">{actionError}</p>}{canChangePlan ? <><button className="button primary" type="button" onClick={() => navigate(`${window.ValleyRuntime?.routes?.office || '/office'}/deliveries/${detail.delivery.id}/edit`)}><Pencil size={15} />Edit trip plan</button><button className="button danger" type="button" onClick={() => setCancelOpen(true)}><Ban size={15} />Cancel trip</button></> : <p className="muted delivery-plan-locked">Planning is locked after loading starts.</p>}</> : null}
            wideContent={detail.delivery && <div className="delivery-trip-detail-tables">
                <div className="delivery-trip-stop-browser">
                    <DetailPanel eyebrow="Trip stops" title={`${detail.stops.length} customer stops`} className="delivery-trip-stop-list-panel">
                        <div className="delivery-stop-browser-intro"><span>Select a customer stop</span><strong>{number(detail.trip?.total_quantity)} units</strong></div>
                        <div className="master-table-wrap"><table className="master-table delivery-stop-browser-table"><colgroup><col /><col /><col /><col /></colgroup><thead><tr><th>#</th><th>Customer / destination</th><th className="numeric">Units</th><th>Status</th></tr></thead><tbody>{detail.stops.map((stop, index) => <tr className={selectedStop?.id === stop.id ? 'is-selected' : ''} key={stop.id} tabIndex="0" aria-selected={selectedStop?.id === stop.id} onClick={() => setSelectedStopId(stop.id)} onKeyDown={(event) => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); setSelectedStopId(stop.id); } }}><td><span className="delivery-stop-index">{index + 1}</span></td><td className="delivery-stop-customer-cell"><strong>{stop.shop_name}</strong><span className="delivery-stop-phone">{stop.recipient_phone || 'No phone number'}</span><span className="delivery-stop-address"><MapPin size={12} />{stop.delivery_address || 'No delivery address'}</span></td><td className="numeric"><strong>{number(stop.total_quantity)}</strong></td><td><span className={`status ${statusFamily(stop.status)}`}>{statusLabel(stop.status)}</span></td></tr>)}</tbody></table></div>
                    </DetailPanel>
                    <DetailPanel eyebrow="Stop products" title={selectedStop?.shop_name || 'Select a stop'} className="delivery-trip-stop-products-panel">
                        {selectedStop ? <>
                            <div className="delivery-stop-selected-summary"><span><MapPin size={14} />{selectedStop.delivery_address || 'No delivery address'}</span><div><span><small>Order</small><strong>{selectedStop.order_code || '-'}</strong></span><span><small>Invoice</small><strong>{selectedStop.invoice_code}</strong></span><span><small>Products</small><strong>{number(selectedStop.items?.length)}</strong></span><span><small>Units</small><strong>{number(selectedStop.total_quantity)}</strong></span></div></div>
                            <div className="master-table-wrap"><table className="master-table delivery-stop-product-table"><colgroup><col /><col /><col /><col /></colgroup><thead><tr><th>Product</th><th>SKU / unit</th><th className="numeric">Planned</th><th className="numeric">Loaded</th></tr></thead><tbody>{(selectedStop.items || []).map((item) => <tr key={item.id}><td><strong>{item.product_name}</strong></td><td><strong>{item.product_sku}</strong><span className="muted">{item.unit}</span></td><td className="numeric"><strong>{number(item.planned_quantity)}</strong></td><td className="numeric">{number(item.loaded_quantity)}</td></tr>)}</tbody></table></div>
                        </> : <State title="Select a customer stop" />}
                    </DetailPanel>
                </div>
                <DetailPanel eyebrow="Stock plan" title={`${detail.stock.length} products required`} className="delivery-trip-stock-panel">
                    <div className="master-table-wrap"><table className="master-table delivery-trip-stock-table"><colgroup><col /><col /><col /><col /><col /><col /><col /></colgroup><thead><tr><th>Product</th><th>SKU / unit</th><th className="numeric">Required</th><th className="numeric">Loaded</th><th className="numeric">Delivered</th><th className="numeric">Returned</th><th className="numeric">Damaged</th></tr></thead><tbody>{detail.stock.map((item) => <tr key={item.product_id}><td><strong>{item.product_name}</strong></td><td>{item.product_sku} · {item.unit}</td><td className="numeric"><strong>{number(item.planned_quantity)}</strong></td><td className="numeric">{number(item.loaded_quantity)}</td><td className="numeric">{number(item.delivered_quantity)}</td><td className="numeric">{number(item.returned_quantity)}</td><td className="numeric">{number(item.damaged_quantity)}</td></tr>)}</tbody><tfoot><tr><th colSpan="2">Total units</th><td className="numeric"><strong>{number(detail.stock.reduce((sum, item) => sum + Number(item.planned_quantity || 0), 0))}</strong></td><td className="numeric"><strong>{number(detail.stock.reduce((sum, item) => sum + Number(item.loaded_quantity || 0), 0))}</strong></td><td className="numeric"><strong>{number(detail.stock.reduce((sum, item) => sum + Number(item.delivered_quantity || 0), 0))}</strong></td><td className="numeric"><strong>{number(detail.stock.reduce((sum, item) => sum + Number(item.returned_quantity || 0), 0))}</strong></td><td className="numeric"><strong>{number(detail.stock.reduce((sum, item) => sum + Number(item.damaged_quantity || 0), 0))}</strong></td></tr></tfoot></table></div>
                </DetailPanel>
            </div>}
        >
            {detail.loading ? <State title="Loading delivery trip" loading /> : detail.error ? <State title={detail.error} /> : detail.delivery && <DetailPanel eyebrow="Details" title="Trip information"><dl className="record-page-facts"><Info label="Planned date" value={date(detail.trip?.planned_date || detail.delivery.planned_date)} /><Info label="Warehouse" value={`${detail.delivery.warehouse_code} · ${detail.delivery.warehouse_name}`} /><Info label="Route" value={`${detail.delivery.route_code} · ${detail.delivery.route_name}`} /><Info label="Driver" value={`${detail.delivery.driver_code} · ${detail.delivery.driver_name}`} /><Info label="Vehicle" value={`${detail.delivery.vehicle_code} · ${detail.delivery.plate_no}`} /><Info label="Status" value={statusLabel(detail.trip?.status || detail.delivery.status)} /></dl></DetailPanel>}
        </DetailPage>
        {cancelOpen && <TripCancelConfirmation code={detail.trip?.code || detail.delivery?.trip_code || detail.delivery?.code} processing={processing} onConfirm={cancelTrip} onDismiss={() => setCancelOpen(false)} />}
    </>);

    return <section className="page delivery-workspace">
        <div className="master-heading"><div><p className="eyebrow">{localized ? 'ပို့ဆောင်ရေးလုပ်ငန်း' : 'Delivery operations'}</p><h1>{historyOnly ? (localized ? 'ပို့ဆောင်မှုမှတ်တမ်း' : 'Delivery history') : (localized ? 'ပို့ဆောင်မှုစီစဉ်ခြင်း' : 'Trip planning')}</h1><span className="muted">{historyOnly ? (localized ? 'ပြီးဆုံး၊ တစ်စိတ်တစ်ပိုင်းနှင့် မအောင်မြင်သော ပို့ဆောင်မှုများကို ပြန်လည်ကြည့်ရှုပါ။' : 'Review completed, partial, failed, and cancelled deliveries.') : 'Select ready orders, assign one vehicle and driver, and review the combined stock requirement.'}</span></div>{canManage && !historyOnly && <button className="button primary" type="button" onClick={openForm} disabled={meta.loading || !meta.invoices.length}><Plus size={16} />New trip</button>}</div>
        <div className="metrics delivery-metrics"><Metric label="Deliveries" value={number(state.summary.deliveries_count)} hint="All statuses" icon={Truck} /><Metric label="Planned quantity" value={number(state.summary.total_quantity)} hint="Units" icon={Package} /><Metric label="Pending" value={number(state.summary.pending_count)} hint="Planned or assigned" icon={CalendarDays} /><Metric label="Delivered" value={number(state.summary.delivered_count)} hint="Completed" icon={Truck} /></div>
        <section className="master-panel"><div className="master-panel-heading"><div><p className="eyebrow">{historyOnly ? (localized ? 'မှတ်တမ်း' : 'History') : (localized ? 'အစီအစဉ်' : 'Schedule')}</p><h2>{historyOnly ? (localized ? 'ပြီးဆုံးသော ပို့ဆောင်မှုများ' : 'Completed delivery records') : (localized ? 'ပို့ဆောင်မှုတာဝန်များ' : 'Delivery assignments')}</h2></div></div><div className="master-toolbar delivery-toolbar"><label className="master-search"><Search size={14} /><input placeholder="Search delivery, invoice, shop, driver" value={filters.search} onChange={(e) => setFilters((v) => ({ ...v, search: e.target.value }))} /></label><select value={filters.status} onChange={(e) => setFilters((v) => ({ ...v, status: e.target.value }))}><option value="">All statuses</option>{statuses.map((item) => <option key={item} value={item}>{statusLabel(item)}</option>)}</select><select value={filters.driver_id} onChange={(e) => setFilters((v) => ({ ...v, driver_id: e.target.value }))}><option value="">All drivers</option>{meta.drivers.map((item) => <option key={item.id} value={item.id}>{item.label}</option>)}</select><label className="date-filter"><CalendarDays size={14} /><input type="date" value={filters.date} onChange={(e) => setFilters((v) => ({ ...v, date: e.target.value }))} /></label><button className="button" type="button" onClick={() => setRefresh((v) => v + 1)}><RefreshCw size={15} />Refresh</button></div>
            {state.loading ? <State title="Loading delivery assignments" loading /> : state.error || meta.error ? <State title={state.error || meta.error} /> : !state.items.length ? <State title="No deliveries match this view." /> : <><div className="master-table-wrap"><table className="master-table delivery-table"><thead><tr><th>Delivery</th><th>Customer / invoice</th><th>Plan</th><th>Driver / vehicle</th><th>Route / warehouse</th><th>Quantity</th><th>Status</th></tr></thead><tbody>{state.items.map((item) => <tr key={item.id} onClick={() => show(item.id)} tabIndex="0" onKeyDown={(e) => e.key === 'Enter' && show(item.id)}><td><strong>{item.code}</strong><span className="muted">{date(item.planned_date)}</span></td><td><strong>{item.shop_name}</strong><span className="muted">{item.invoice_code} · {money(item.invoice_total)}</span></td><td>{date(item.planned_date)}</td><td><strong>{item.driver_name}</strong><span className="muted">{item.vehicle_code} · {item.plate_no}</span></td><td><strong>{item.route_name}</strong><span className="muted">{item.warehouse_code}</span></td><td className="numeric">{number(item.total_quantity)}</td><td><span className={`status ${statusFamily(item.status)}`}>{statusLabel(item.status)}</span></td></tr>)}</tbody></table></div><Pagination meta={state.meta} page={page} setPage={setPage} /></>}
        </section>
    </section>;
}

export function TripEditPage({ deliveryId, locale = 'en', navigate }) {
    const listPath = `${window.ValleyRuntime?.routes?.office || '/office'}/deliveries`;
    const [state, setState] = useState({ loading: true, meta: null, trip: null, delivery: null, error: '' });
    const [form, setForm] = useState({ ...blank });
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        let mounted = true;
        Promise.all([window.axios.get(`${api()}/meta`), window.axios.get(`${api()}/${deliveryId}`)])
            .then(([metaResponse, detailResponse]) => {
                if (!mounted) return;
                const detailData = detailResponse.data.data;
                const delivery = detailData.delivery;
                const trip = detailData.trip;
                setState({ loading: false, meta: metaResponse.data.data, trip, delivery, error: '' });
                setForm({
                    warehouse_id: String(trip?.warehouse_id || delivery.warehouse_id || ''),
                    route_id: String(trip?.route_id || delivery.route_id || ''),
                    driver_id: String(trip?.driver_id || delivery.driver_id || ''),
                    vehicle_id: String(trip?.vehicle_id || delivery.vehicle_id || ''),
                    planned_date: trip?.planned_date || delivery.planned_date || '',
                    notes: trip?.notes || delivery.notes || '',
                });
            })
            .catch((error) => mounted && setState({ loading: false, meta: null, trip: null, delivery: null, error: errorMessage(error) }));
        return () => { mounted = false; };
    }, [deliveryId]);

    const save = (event) => {
        event.preventDefault();
        if (saving) return;
        setSaving(true); setState((current) => ({ ...current, error: '' }));
        window.axios.patch(`${api()}/${deliveryId}/trip`, form)
            .then(() => navigate(`${listPath}/${deliveryId}`))
            .catch((error) => { setSaving(false); setState((current) => ({ ...current, error: errorMessage(error) })); });
    };

    if (state.loading) return <section className="page trip-wizard-page"><State title="Loading trip plan" loading /></section>;
    if (!state.meta || !state.delivery) return <section className="page trip-wizard-page"><State title={state.error || 'Trip plan could not be loaded.'} /></section>;

    return <section className="page trip-wizard-page trip-edit-page">
        <div className="master-heading trip-wizard-heading"><div><p className="eyebrow">Delivery operations</p><h1>Edit trip plan</h1><span className="muted">{state.trip?.code || state.delivery.trip_code || state.delivery.code} · Change the schedule and assignment before loading starts.</span></div><button className="button" type="button" onClick={() => navigate(`${listPath}/${deliveryId}`)}><ArrowLeft size={15} />Back</button></div>
        {state.error && <p className="form-alert" role="alert">{state.error}</p>}
        <form className="master-panel trip-edit-form" onSubmit={save}>
            <div className="master-panel-heading"><div><p className="eyebrow">Trip plan</p><h2>Schedule and assignment</h2></div><span className={`status ${statusFamily(state.trip?.status || state.delivery.status)}`}>{statusLabel(state.trip?.status || state.delivery.status)}</span></div>
            <div className="trip-wizard-form master-form-grid">
                <Field label="Planned date"><input required type="date" value={form.planned_date} onChange={(event) => setForm((current) => ({ ...current, planned_date: event.target.value }))} /></Field>
                <Select label="Warehouse" name="warehouse_id" items={state.meta.warehouses} form={form} setForm={setForm} />
                <Select label="Route" name="route_id" items={state.meta.routes} form={form} setForm={setForm} />
                <Select label="Driver" name="driver_id" items={state.meta.drivers} form={form} setForm={setForm} />
                <Select label="Vehicle" name="vehicle_id" items={state.meta.vehicles} form={form} setForm={setForm} />
                <Field label="Trip notes" wide><textarea rows="4" maxLength="500" value={form.notes} onChange={(event) => setForm((current) => ({ ...current, notes: event.target.value }))} /></Field>
            </div>
            <footer className="trip-edit-actions"><button className="button" type="button" disabled={saving} onClick={() => navigate(`${listPath}/${deliveryId}`)}>Cancel</button><button className="button primary" disabled={saving}><Save size={15} />{saving ? 'Saving…' : 'Save trip plan'}</button></footer>
        </form>
    </section>;
}

function TripCancelConfirmation({ code, processing, onConfirm, onDismiss }) {
    const titleId = useId();
    useEffect(() => {
        const closeOnEscape = (event) => { if (event.key === 'Escape' && !processing) onDismiss(); };
        window.addEventListener('keydown', closeOnEscape);
        return () => window.removeEventListener('keydown', closeOnEscape);
    }, [onDismiss, processing]);

    return <div className="modal-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && !processing && onDismiss()}>
        <section className="master-dialog order-cancel-dialog" role="dialog" aria-modal="true" aria-labelledby={titleId}>
            <header><div><p className="eyebrow">Trip planning</p><h2 id={titleId}>Cancel trip</h2></div><button className="icon-button" type="button" disabled={processing} aria-label="Keep trip" onClick={onDismiss}><X size={17} /></button></header>
            <div className="order-cancel-dialog-body"><span className="order-cancel-dialog-icon"><TriangleAlert size={22} /></span><div><strong>Cancel {code}?</strong><p>The assigned orders will return to the ready-to-plan list. This cancelled trip remains in delivery history.</p></div></div>
            <footer><button className="button" type="button" disabled={processing} onClick={onDismiss}>Keep trip</button><button className="button danger" type="button" disabled={processing} onClick={onConfirm}><Ban size={15} />{processing ? 'Cancelling…' : 'Confirm cancellation'}</button></footer>
        </section>
    </div>;
}

export function TripWizardPage({ locale = 'en', navigate }) {
    const localized = locale === 'my';
    const listPath = `${window.ValleyRuntime?.routes?.office || '/office'}/deliveries`;
    const [step, setStep] = useState(1);
    const [meta, setMeta] = useState({ loading: true, invoices: [], warehouses: [], routes: [], drivers: [], vehicles: [], error: '' });
    const [form, setForm] = useState(blank);
    const [orderSearch, setOrderSearch] = useState('');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        let mounted = true;
        window.axios.get(`${api()}/meta`).then(({ data }) => {
            if (!mounted) return;
            const nextMeta = { loading: false, ...data.data, error: '' };
            setMeta(nextMeta);
            setForm((current) => ({
                ...current,
                warehouse_id: current.warehouse_id || nextMeta.warehouses[0]?.id || '',
                route_id: current.route_id || nextMeta.invoices[0]?.route_id || nextMeta.routes[0]?.id || '',
                driver_id: current.driver_id || nextMeta.drivers[0]?.id || '',
                vehicle_id: current.vehicle_id || nextMeta.vehicles[0]?.id || '',
            }));
        }).catch((requestError) => mounted && setMeta({ loading: false, invoices: [], warehouses: [], routes: [], drivers: [], vehicles: [], error: errorMessage(requestError) }));
        return () => { mounted = false; };
    }, []);

    useEffect(() => {
        if (!form.route_id || !meta.invoices.length) return;
        setForm((current) => ({
            ...current,
            invoice_ids: current.invoice_ids.filter((id) => meta.invoices.some((invoice) => invoice.id === id && Number(invoice.route_id) === Number(current.route_id))),
        }));
    }, [form.route_id, meta.invoices]);

    const selectedOrders = useMemo(() => meta.invoices.filter((invoice) => form.invoice_ids.includes(invoice.id)), [meta.invoices, form.invoice_ids]);
    const visibleOrders = useMemo(() => {
        const query = orderSearch.trim().toLowerCase();
        const routeOrders = meta.invoices.filter((invoice) => Number(invoice.route_id) === Number(form.route_id));
        if (!query) return routeOrders;
        return routeOrders.filter((invoice) => [invoice.order_code, invoice.code, invoice.shop_name, invoice.delivery_address].some((value) => String(value || '').toLowerCase().includes(query)));
    }, [form.route_id, meta.invoices, orderSearch]);
    const plannedStock = useMemo(() => Object.values(selectedOrders.flatMap((invoice) => invoice.items || []).reduce((totals, item) => {
        const current = totals[item.product_id] || { ...item, quantity: 0 };
        current.quantity += Number(item.quantity || 0);
        totals[item.product_id] = current;
        return totals;
    }, {})), [selectedOrders]);
    const selectedQuantity = plannedStock.reduce((sum, item) => sum + item.quantity, 0);
    const selectedValue = selectedOrders.reduce((sum, invoice) => sum + Number(invoice.total || 0), 0);
    const lookup = (items, id) => items.find((item) => Number(item.id) === Number(id));

    const toggleOrder = (id) => setForm((current) => ({
        ...current,
        invoice_ids: current.invoice_ids.includes(id) ? current.invoice_ids.filter((value) => value !== id) : [...current.invoice_ids, id],
    }));
    const toggleVisible = () => {
        const visibleIds = visibleOrders.map((item) => item.id);
        const allSelected = visibleIds.length > 0 && visibleIds.every((id) => form.invoice_ids.includes(id));
        setForm((current) => ({
            ...current,
            invoice_ids: allSelected
                ? current.invoice_ids.filter((id) => !visibleIds.includes(id))
                : [...new Set([...current.invoice_ids, ...visibleIds])],
        }));
    };
    const next = () => {
        setError('');
        if (step === 1 && (!form.planned_date || !form.warehouse_id || !form.route_id || !form.driver_id || !form.vehicle_id)) {
            setError('Complete every required trip field before selecting orders.');
            return;
        }
        if (step === 2 && !form.invoice_ids.length) {
            setError('Select at least one ready order for this trip.');
            return;
        }
        setStep((current) => Math.min(current + 1, 3));
        window.scrollTo({ top: 0, behavior: 'auto' });
    };
    const back = () => { setError(''); setStep((current) => Math.max(current - 1, 1)); window.scrollTo({ top: 0, behavior: 'auto' }); };
    const submit = () => {
        setSaving(true); setError('');
        window.axios.post(api(), form)
            .then(() => navigate(listPath))
            .catch((requestError) => { setSaving(false); setError(errorMessage(requestError)); });
    };

    if (meta.loading) return <section className="page trip-wizard-page"><State title="Loading trip setup" loading /></section>;
    if (meta.error) return <section className="page trip-wizard-page"><State title={meta.error} /></section>;

    const steps = [
        { number: 1, label: 'Basic information', hint: 'Schedule and assignment' },
        { number: 2, label: 'Order selection', hint: 'Choose ready orders' },
        { number: 3, label: 'Trip review', hint: 'Verify and submit' },
    ];

    return <section className="page trip-wizard-page">
        <div className="master-heading trip-wizard-heading"><div><p className="eyebrow">{localized ? 'ပို့ဆောင်ရေးလုပ်ငန်း' : 'Delivery operations'}</p><h1>Create delivery trip</h1><span className="muted">Build one clear load plan from ready customer orders.</span></div><button className="button" type="button" onClick={() => navigate(listPath)}><X size={15} />Cancel</button></div>

        <ol className="trip-wizard-steps" aria-label="Trip creation progress">
            {steps.map((item) => <li className={`${step === item.number ? 'is-current' : ''} ${step > item.number ? 'is-complete' : ''}`} aria-current={step === item.number ? 'step' : undefined} key={item.number}><button type="button" disabled={item.number > step} onClick={() => item.number < step && setStep(item.number)}><span>{step > item.number ? <CheckCircle2 size={15} /> : item.number}</span><strong>{item.label}</strong><small>{item.hint}</small></button></li>)}
        </ol>

        {error && <p className="form-alert trip-wizard-error" role="alert">{error}</p>}

        <section className="master-panel trip-wizard-panel">
            {step === 1 && <><div className="master-panel-heading"><div><p className="eyebrow">Step 1 of 3</p><h2>Basic trip information</h2><span className="muted">Set when, where, and who will operate this trip.</span></div></div><div className="trip-wizard-form master-form-grid"><Field label="Planned date"><input required type="date" value={form.planned_date} onChange={(event) => setForm((current) => ({ ...current, planned_date: event.target.value }))} /></Field><Select label="Warehouse" name="warehouse_id" items={meta.warehouses} form={form} setForm={setForm} /><Select label="Route" name="route_id" items={meta.routes} form={form} setForm={setForm} /><Select label="Driver" name="driver_id" items={meta.drivers} form={form} setForm={setForm} /><Select label="Vehicle" name="vehicle_id" items={meta.vehicles} form={form} setForm={setForm} /><Field label="Trip notes" wide><textarea rows="4" placeholder="Loading or route instructions" value={form.notes} onChange={(event) => setForm((current) => ({ ...current, notes: event.target.value }))} /></Field></div></>}

            {step === 2 && <><div className="master-panel-heading"><div><p className="eyebrow">Step 2 of 3</p><h2>Select ready orders</h2><span className="muted">Only issued orders that are not assigned to another trip are available.</span></div><strong>{selectedOrders.length} selected</strong></div><div className="trip-order-toolbar"><label className="master-search"><Search size={14} /><input placeholder="Search order, invoice, or customer" value={orderSearch} onChange={(event) => setOrderSearch(event.target.value)} /></label><button className="button" type="button" disabled={!visibleOrders.length} onClick={toggleVisible}>{visibleOrders.length > 0 && visibleOrders.every((item) => form.invoice_ids.includes(item.id)) ? 'Clear visible' : 'Select visible'}</button></div>{!visibleOrders.length ? <State title="No ready orders match this search." /> : <div className="master-table-wrap"><table className="master-table trip-order-table"><thead><tr><th aria-label="Select order"></th><th>Order</th><th>Customer</th><th>Invoice</th><th className="numeric">Items</th><th className="numeric">Quantity</th><th className="numeric">Value</th></tr></thead><tbody>{visibleOrders.map((invoice) => { const quantity = (invoice.items || []).reduce((sum, item) => sum + Number(item.quantity || 0), 0); return <tr className={form.invoice_ids.includes(invoice.id) ? 'is-selected' : ''} key={invoice.id} onClick={() => toggleOrder(invoice.id)}><td><input aria-label={`Select ${invoice.order_code || invoice.code}`} type="checkbox" checked={form.invoice_ids.includes(invoice.id)} onClick={(event) => event.stopPropagation()} onChange={() => toggleOrder(invoice.id)} /></td><td><strong>{invoice.order_code || '-'}</strong></td><td>{invoice.shop_name}</td><td>{invoice.code}</td><td className="numeric">{invoice.items?.length || 0}</td><td className="numeric">{number(quantity)}</td><td className="numeric">{money(invoice.total)}</td></tr>; })}</tbody></table></div>}</>}

            {step === 3 && <>
                <div className="master-panel-heading">
                    <div><p className="eyebrow">Step 3 of 3</p><h2>Review trip</h2><span className="muted">Confirm the assignment, delivery stops, and planned warehouse issue.</span></div>
                    <span className="status neutral">Ready to submit</span>
                </div>
                <div className="trip-review-grid">
                    <section><h3>Trip information</h3><dl className="trip-review-facts"><Info label="Planned date" value={date(form.planned_date)} /><Info label="Warehouse" value={lookup(meta.warehouses, form.warehouse_id)?.label} /><Info label="Route" value={lookup(meta.routes, form.route_id)?.label} /><Info label="Driver" value={lookup(meta.drivers, form.driver_id)?.label} /><Info label="Vehicle" value={lookup(meta.vehicles, form.vehicle_id)?.label} /><Info label="Notes" value={form.notes || 'No trip notes'} /></dl></section>
                    <aside><h3>Trip totals</h3><div className="trip-review-totals"><span><small>Orders</small><strong>{selectedOrders.length}</strong></span><span><small>Products</small><strong>{plannedStock.length}</strong></span><span><small>Units</small><strong>{number(selectedQuantity)}</strong></span><span><small>Order value</small><strong>{money(selectedValue)}</strong></span></div></aside>
                </div>
                <div className="trip-review-section">
                    <div className="trip-section-heading"><span>Delivery stops</span><strong>{selectedOrders.length} orders</strong></div>
                    <div className="master-table-wrap">
                        <table className="master-table trip-stop-table">
                            <colgroup><col /><col /><col /><col /><col /><col /></colgroup>
                            <thead><tr><th>Order</th><th>Customer</th><th>Delivery address</th><th>Invoice</th><th className="numeric">Quantity</th><th className="numeric">Value</th></tr></thead>
                            <tbody>{selectedOrders.map((invoice) => <tr key={invoice.id}><td><strong>{invoice.order_code || '-'}</strong></td><td><strong>{invoice.shop_name}</strong><span className="muted">{invoice.recipient_phone || 'No phone'}</span></td><td className="trip-stop-address">{invoice.delivery_address || <span className="muted">No delivery address</span>}</td><td>{invoice.code}</td><td className="numeric">{number((invoice.items || []).reduce((sum, item) => sum + Number(item.quantity || 0), 0))}</td><td className="numeric"><strong>{money(invoice.total)}</strong></td></tr>)}</tbody>
                        </table>
                    </div>
                </div>
                <div className="trip-review-section">
                    <div className="trip-section-heading"><span>Planned stock issue</span><strong>{number(selectedQuantity)} units</strong></div>
                    <div className="master-table-wrap"><table className="master-table"><thead><tr><th>Product</th><th>SKU</th><th>Unit</th><th className="numeric">Quantity</th></tr></thead><tbody>{plannedStock.map((item) => <tr key={item.product_id}><td><strong>{item.product_name}</strong></td><td>{item.product_sku}</td><td>{item.unit}</td><td className="numeric"><strong>{number(item.quantity)}</strong></td></tr>)}</tbody></table></div>
                    <p className="trip-stock-note"><Package size={15} />Stock is not deducted during planning. The automatic issue is posted when the driver confirms loading.</p>
                </div>
            </>}
        </section>

        <footer className="trip-wizard-actions"><span>Step {step} of 3</span><div><button className="button" type="button" onClick={step === 1 ? () => navigate(listPath) : back}><ArrowLeft size={15} />{step === 1 ? 'Cancel' : 'Back'}</button>{step < 3 ? <button className="button primary" type="button" onClick={next}>Continue<ChevronRight size={15} /></button> : <button className="button primary" type="button" disabled={saving} onClick={submit}><Save size={15} />{saving ? 'Creating trip…' : 'Create trip'}</button>}</div></footer>
    </section>;
}

export function DeliveryLiveMapScreen({ locale = 'en' }) {
    const localized = locale === 'my';
    const liveApi = () => window.ValleyRuntime?.api?.deliveryLiveMap || '/api/deliveries/live-map';
    const [state, setState] = useState({ loading: true, items: [], summary: {}, refreshed_at: null, error: '' });
    const [selectedId, setSelectedId] = useState(null);
    const [history, setHistory] = useState({ loading: false, locations: [], error: '' });
    const [refresh, setRefresh] = useState(0);

    useEffect(() => {
        let mounted = true;
        const load = () => window.axios.get(liveApi()).then(({ data }) => {
            if (!mounted) return;
            setState({ loading: false, ...data.data, error: '' });
            setSelectedId((current) => data.data.items.some((item) => item.id === current) ? current : data.data.items[0]?.id || null);
        }).catch((error) => mounted && setState((current) => ({ ...current, loading: false, error: errorMessage(error) })));
        setState((current) => ({ ...current, loading: true, error: '' }));
        load();
        const timer = window.setInterval(load, 15000);
        return () => { mounted = false; window.clearInterval(timer); };
    }, [refresh]);

    useEffect(() => {
        if (!selectedId) { setHistory({ loading: false, locations: [], error: '' }); return undefined; }
        let mounted = true;
        setHistory((current) => ({ ...current, loading: true, error: '' }));
        window.axios.get(`${api()}/${selectedId}/locations`)
            .then(({ data }) => mounted && setHistory({ loading: false, locations: data.data.locations, error: '' }))
            .catch((error) => mounted && setHistory({ loading: false, locations: [], error: errorMessage(error) }));
        return () => { mounted = false; };
    }, [selectedId, state.refreshed_at]);

    const tracked = state.items.filter((item) => item.location);
    const latitudes = tracked.map((item) => item.location.latitude);
    const longitudes = tracked.map((item) => item.location.longitude);
    const minLat = Math.min(...latitudes); const maxLat = Math.max(...latitudes);
    const minLng = Math.min(...longitudes); const maxLng = Math.max(...longitudes);
    const position = (location) => ({
        left: `${tracked.length < 2 || maxLng === minLng ? 50 : 10 + ((location.longitude - minLng) / (maxLng - minLng)) * 80}%`,
        top: `${tracked.length < 2 || maxLat === minLat ? 50 : 90 - ((location.latitude - minLat) / (maxLat - minLat)) * 80}%`,
    });
    const selected = state.items.find((item) => item.id === selectedId) || null;
    const stateClass = (value) => value === 'live' ? 'success' : value === 'stale' ? 'warning' : 'neutral';
    const time = (value) => value ? new Date(value.replace(' ', 'T')).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' }) : '-';

    return <section className="page delivery-map-workspace"><div className="master-heading"><div><p className="eyebrow">{localized ? 'တိုက်ရိုက်လုပ်ငန်း' : 'Live operations'}</p><h1>{localized ? 'ယာဉ်မောင်း တိုက်ရိုက်မြေပုံ' : 'Driver live map'}</h1><span className="muted">{localized ? 'လမ်းပေါ်ရှိ ပို့ဆောင်မှုတိုင်း၏ နောက်ဆုံး GPS တည်နေရာ။' : 'Latest GPS position for every delivery currently on route.'}</span></div><button className="button" type="button" onClick={() => setRefresh((value) => value + 1)}><RefreshCw size={15} />{localized ? 'ပြန်လည်စစ်ဆေး' : 'Refresh'}</button></div>
        <div className="metrics delivery-metrics"><Metric label="On route" value={number(state.summary.active_count)} hint="Active deliveries" icon={Truck} /><Metric label="Live signal" value={number(state.summary.live_count)} hint="Updated within 5 min" icon={Navigation} /><Metric label="Stale signal" value={number(state.summary.stale_count)} hint="Update overdue" icon={MapPin} /><Metric label="Not reported" value={number(state.summary.unreported_count)} hint="Waiting for GPS" icon={MapPin} /></div>
        {state.loading && !state.items.length ? <State title="Loading live delivery map" loading /> : state.error ? <State title={state.error} /> : !state.items.length ? <State title="No deliveries are currently on route." /> : <div className="delivery-live-layout"><section className="delivery-map-panel" aria-label="Live driver positions"><div className="delivery-map-grid"><span className="map-axis north">N</span>{tracked.length ? tracked.map((item) => <button className={`delivery-map-marker ${selectedId === item.id ? 'is-selected' : ''} ${item.tracking_state}`} style={position(item.location)} type="button" key={item.id} onClick={() => setSelectedId(item.id)} aria-label={`Select ${item.driver_name}`}><Truck size={15} /><span>{item.driver_code}</span></button>) : <div className="delivery-map-empty"><MapPin size={24} /><strong>Waiting for a GPS update</strong><span>Active drivers appear here after location permission is granted.</span></div>}</div><footer><span>Operational coordinate view</span><span>Auto-refresh: 15 seconds · {time(state.refreshed_at)}</span></footer></section>
            <aside className="delivery-live-sidebar"><div className="master-panel-heading"><div><p className="eyebrow">Active fleet</p><h2>{state.items.length} on route</h2></div></div><div className="delivery-driver-list">{state.items.map((item) => <button className={selectedId === item.id ? 'is-selected' : ''} type="button" key={item.id} onClick={() => setSelectedId(item.id)}><span className="mobile-order-icon"><Navigation size={15} /></span><span><strong>{item.driver_name}</strong><small>{item.code} · {item.plate_no}</small><small>{item.shop_name}</small></span><span className={`status ${stateClass(item.tracking_state)}`}>{item.tracking_state}</span></button>)}</div></aside>
            {selected && <section className="delivery-location-detail"><div className="master-panel-heading"><div><p className="eyebrow">Selected delivery</p><h2>{selected.driver_name} · {selected.code}</h2></div><span className={`status ${stateClass(selected.tracking_state)}`}>{selected.tracking_state}</span></div><dl><Info label="Customer" value={selected.shop_name} /><Info label="Vehicle" value={`${selected.vehicle_code} · ${selected.plate_no}`} /><Info label="Route" value={selected.route_name} /><Info label="Destination" value={selected.delivery_address} /><Info label="Latitude" value={selected.location?.latitude?.toFixed(7)} /><Info label="Longitude" value={selected.location?.longitude?.toFixed(7)} /><Info label="Accuracy" value={selected.location?.accuracy_m !== null && selected.location ? `${number(selected.location.accuracy_m)} m` : '-'} /><Info label="Last update" value={time(selected.location?.recorded_at)} /></dl><h3>Recent GPS fixes</h3>{history.loading ? <State title="Loading location history" loading /> : history.error ? <p className="form-alert">{history.error}</p> : !history.locations.length ? <p className="muted delivery-history-empty">No location history reported.</p> : <div className="delivery-location-history">{history.locations.slice(0, 8).map((location) => <article key={location.id}><MapPin size={14} /><span><strong>{location.latitude.toFixed(6)}, {location.longitude.toFixed(6)}</strong><small>{time(location.recorded_at)} · {location.accuracy_m ? `${number(location.accuracy_m)} m accuracy` : 'Accuracy unavailable'}</small></span></article>)}</div>}</section>}
        </div>}
    </section>;
}

export function MobileDriverDeliveriesScreen({ locale = 'en' }) {
    const localized = locale === 'my';
    const mobileApi = () => window.ValleyRuntime?.api?.mobileDeliveries || '/api/mobile/deliveries';
    const [state, setState] = useState({ loading: true, items: [], summary: {}, error: '' });
    const [detail, setDetail] = useState({ loading: false, delivery: null, items: [], error: '' });
    const [quantities, setQuantities] = useState({});
    const [notes, setNotes] = useState('');
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState('');
    const [refresh, setRefresh] = useState(0);

    useEffect(() => {
        let mounted = true;
        setState((current) => ({ ...current, loading: true, error: '' }));
        window.axios.get(mobileApi()).then(({ data }) => mounted && setState({ loading: false, items: data.data.items, summary: data.data.summary, error: '' })).catch((error) => mounted && setState({ loading: false, items: [], summary: {}, error: errorMessage(error) }));
        return () => { mounted = false; };
    }, [refresh]);

    const openDelivery = (id) => {
        setDetail({ loading: true, delivery: null, items: [], error: '' }); setMessage('');
        window.axios.get(`${mobileApi()}/${id}`).then(({ data }) => {
            setDetail({ loading: false, delivery: data.data.delivery, items: data.data.items, error: '' });
            setQuantities(Object.fromEntries(data.data.items.map((item) => [item.id, item.loaded_quantity || item.planned_quantity])));
            setNotes(data.data.delivery.notes || '');
        }).catch((error) => setDetail({ loading: false, delivery: null, items: [], error: errorMessage(error) }));
    };

    const confirmLoad = () => {
        setSaving(true); setMessage('');
        window.axios.post(`${mobileApi()}/${detail.delivery.id}/confirm-loading`, { items: detail.items.map((item) => ({ id: item.id, loaded_quantity: quantities[item.id] ?? 0 })), notes })
            .then(({ data }) => { setSaving(false); setMessage('Loading confirmed.'); setDetail((current) => ({ ...current, delivery: data.data.delivery, items: data.data.items })); setRefresh((value) => value + 1); })
            .catch((error) => { setSaving(false); setMessage(errorMessage(error)); });
    };

    if (detail.loading) return <MobileState title="Loading delivery" loading />;
    if (detail.error) return <MobileState title={detail.error} action={() => setDetail({ loading: false, delivery: null, items: [], error: '' })} />;
    if (detail.delivery) {
        const canLoad = ['assigned', 'loading'].includes(detail.delivery.status);
        const loadedTotal = detail.items.reduce((sum, item) => sum + Number(quantities[item.id] || 0), 0);
        return <div className="driver-delivery-detail"><header className="mobile-section-heading"><button className="icon-button" type="button" aria-label="Back to deliveries" onClick={() => setDetail({ loading: false, delivery: null, items: [], error: '' })}><ArrowLeft size={18} /></button><div><p className="eyebrow">Assigned delivery</p><h2>{detail.delivery.code}</h2></div><span className={`status ${statusFamily(detail.delivery.status)}`}>{statusLabel(detail.delivery.status)}</span></header>
            <section className="driver-delivery-hero"><div><Truck size={22} /><span><strong>{detail.delivery.shop_name}</strong><small>{detail.delivery.invoice_code}</small></span></div><dl><Info label="Planned" value={date(detail.delivery.planned_date)} /><Info label="Vehicle" value={`${detail.delivery.vehicle_code} · ${detail.delivery.plate_no}`} /><Info label="Route" value={detail.delivery.route_name} /><Info label="Warehouse" value={detail.delivery.warehouse_name} /></dl>{detail.delivery.delivery_address && <p><MapPin size={14} />{detail.delivery.delivery_address}</p>}</section>
            <section className="driver-load-panel"><div className="mobile-section-heading"><div><p className="eyebrow">Warehouse load</p><h2>Confirm quantities</h2></div><strong>{number(loadedTotal)} / {number(detail.delivery.total_quantity)}</strong></div><div className="driver-load-items">{detail.items.map((item) => <label key={item.id}><span><strong>{item.product_name}</strong><small>{item.product_sku} · Planned {number(item.planned_quantity)} {item.unit}</small></span><input aria-label={`Loaded quantity for ${item.product_name}`} type="number" min="0" max={item.planned_quantity} step="0.01" disabled={!canLoad} value={quantities[item.id] ?? 0} onChange={(event) => setQuantities((current) => ({ ...current, [item.id]: event.target.value }))} /></label>)}</div><label className="driver-load-notes">Loading note<textarea disabled={!canLoad} value={notes} onChange={(event) => setNotes(event.target.value)} /></label>{message && <p className={message === 'Loading confirmed.' ? 'mobile-success-message' : 'form-alert'}>{message}</p>}{canLoad && <button className="button primary driver-load-action" type="button" disabled={saving} onClick={confirmLoad}><Package size={17} />{saving ? 'Saving…' : 'Confirm loading'}</button>}</section>
        </div>;
    }

    return <div className="driver-delivery-list"><header className="mobile-master-heading"><div><p className="eyebrow">{localized ? 'ယာဉ်မောင်း' : 'Driver'}</p><h1>{localized ? 'တာဝန်ပေးထားသော ပို့ဆောင်မှုများ' : 'Assigned deliveries'}</h1><span className="muted">{localized ? 'ယနေ့လမ်းကြောင်းကို စစ်ဆေးပြီး ဂိုဒေါင်တင်ဆောင်မှုကို အတည်ပြုပါ။' : 'Review today’s route and confirm warehouse loading.'}</span></div><button className="icon-button" type="button" aria-label="Refresh deliveries" onClick={() => setRefresh((value) => value + 1)}><RefreshCw size={17} /></button></header><div className="mobile-credit-card"><span>{localized ? 'စီစဉ်ထားသော ဝန်' : 'Planned load'}</span><strong>{number(state.summary.total_quantity)}</strong><small>{number(state.summary.assigned_count)} {localized ? 'တင်ဆောင်ရန်စောင့်ဆိုင်း ·' : 'awaiting load ·'} {number(state.summary.loading_count)} {localized ? 'တင်ဆောင်နေသည်' : 'loading'}</small></div>{state.loading ? <MobileState title={localized ? 'ပို့ဆောင်မှုများ တင်နေသည်' : 'Loading assigned deliveries'} loading /> : state.error ? <MobileState title={state.error} action={() => setRefresh((value) => value + 1)} /> : !state.items.length ? <MobileState title={localized ? 'ပို့ဆောင်မှုတာဝန် မရှိသေးပါ။' : 'No deliveries are assigned.'} /> : <section className="mobile-master-section"><div className="mobile-section-heading"><div><p className="eyebrow">{localized ? 'အစီအစဉ်' : 'Schedule'}</p><h2>{number(state.summary.deliveries_count)} {localized ? 'ပို့ဆောင်မှု' : 'deliveries'}</h2></div></div><div className="mobile-delivery-cards">{state.items.map((item) => <button type="button" key={item.id} onClick={() => openDelivery(item.id)}><span className="mobile-order-icon"><Truck size={17} /></span><span><strong>{item.shop_name}</strong><small>{item.code} · {date(item.planned_date)}</small><small>{item.route_name} · {number(item.total_quantity)} {localized ? 'ယူနစ်' : 'units'}</small></span><span><span className={`status ${statusFamily(item.status)}`}>{statusLabel(item.status)}</span><ChevronRight size={16} /></span></button>)}</div></section>}</div>;
}

export function MobileDeliveryStatusScreen({ appId, locale = 'en' }) {
    const localized = locale === 'my';
    const mobileApi = () => window.ValleyRuntime?.api?.mobileDeliveryStatus || '/api/mobile/delivery-status';
    const [state, setState] = useState({ loading: true, items: [], summary: {}, error: '' });
    const [detail, setDetail] = useState({ loading: false, delivery: null, items: [], error: '' });
    const [refresh, setRefresh] = useState(0);

    useEffect(() => {
        let mounted = true;
        setState((current) => ({ ...current, loading: true, error: '' }));
        window.axios.get(mobileApi())
            .then(({ data }) => mounted && setState({ loading: false, items: data.data.items, summary: data.data.summary, error: '' }))
            .catch((error) => mounted && setState({ loading: false, items: [], summary: {}, error: errorMessage(error) }));
        return () => { mounted = false; };
    }, [refresh]);

    const openDelivery = (id) => {
        setDetail({ loading: true, delivery: null, items: [], error: '' });
        window.axios.get(`${mobileApi()}/${id}`)
            .then(({ data }) => setDetail({ loading: false, delivery: data.data.delivery, items: data.data.items, error: '' }))
            .catch((error) => setDetail({ loading: false, delivery: null, items: [], error: errorMessage(error) }));
    };

    if (detail.loading) return <MobileState title="Loading delivery status" loading />;
    if (detail.error) return <MobileState title={detail.error} action={() => setDetail({ loading: false, delivery: null, items: [], error: '' })} />;
    if (detail.delivery) {
        const terminal = ['delivered', 'partially_delivered', 'failed'].includes(detail.delivery.status);
        const stepIndex = detail.delivery.status === 'planned' ? 0 : detail.delivery.status === 'assigned' ? 0 : detail.delivery.status === 'loading' ? 1 : detail.delivery.status === 'on_route' ? 2 : terminal ? 3 : -1;
        const steps = ['Assigned', 'Loading', 'On route', terminal ? statusLabel(detail.delivery.status) : 'Delivered'];

        return <div className="driver-delivery-detail delivery-status-detail"><header className="mobile-section-heading"><button className="icon-button" type="button" aria-label="Back to deliveries" onClick={() => setDetail({ loading: false, delivery: null, items: [], error: '' })}><ArrowLeft size={18} /></button><div><p className="eyebrow">Delivery tracking</p><h2>{detail.delivery.code}</h2></div><span className={`status ${statusFamily(detail.delivery.status)}`}>{statusLabel(detail.delivery.status)}</span></header>
            <section className="driver-delivery-hero"><div><Truck size={22} /><span><strong>{detail.delivery.shop_name}</strong><small>{detail.delivery.invoice_code}</small></span></div><dl><Info label="Planned" value={date(detail.delivery.planned_date)} /><Info label="Route" value={detail.delivery.route_name} /><Info label="Driver" value={detail.delivery.driver_name} /><Info label="Vehicle" value={`${detail.delivery.vehicle_code} / ${detail.delivery.plate_no}`} /></dl>{detail.delivery.delivery_address && <p><MapPin size={14} />{detail.delivery.delivery_address}</p>}</section>
            <section className="driver-load-panel"><div className="mobile-section-heading"><div><p className="eyebrow">Progress</p><h2>Delivery journey</h2></div><strong>{number(detail.delivery.delivered_quantity)} / {number(detail.delivery.total_quantity)}</strong></div><ol className="mobile-delivery-timeline">{steps.map((label, index) => <li className={`${index <= stepIndex ? 'is-done' : ''} ${index === stepIndex ? 'is-current' : ''}`} key={label}><span>{index < stepIndex ? <CheckCircle2 size={14} /> : index + 1}</span><strong>{label}</strong></li>)}</ol></section>
            <section className="driver-load-panel"><div className="mobile-section-heading"><div><p className="eyebrow">Shipment</p><h2>{detail.items.length} items</h2></div><strong>{number(detail.delivery.total_quantity)} units</strong></div><div className="delivery-status-items">{detail.items.map((item) => <article key={item.id}><span><strong>{item.product_name}</strong><small>{item.product_sku} / {item.unit}</small></span><dl><Info label="Planned" value={number(item.planned_quantity)} /><Info label="Loaded" value={number(item.loaded_quantity)} /><Info label="Delivered" value={number(item.delivered_quantity)} /></dl></article>)}</div>{detail.delivery.notes && <p className="delivery-status-note">{detail.delivery.notes}</p>}</section>
        </div>;
    }

    const isSales = appId === 'sales';
    const pageTitle = appId === 'client'
        ? (localized ? 'ကျွန်ုပ်၏ ပို့ဆောင်မှုများ' : 'My deliveries')
        : (localized ? 'ဖောက်သည် ပို့ဆောင်မှုများ' : 'Customer deliveries');
    const pageHint = isSales
        ? (localized ? 'သတ်မှတ်ထားသော ယာဉ်မောင်းများက သီးခြားကိုင်တွယ်သည့် ဖောက်သည်ပို့ဆောင်မှုများကို စောင့်ကြည့်ပါ။' : 'Monitor customer deliveries handled independently by assigned drivers.')
        : (localized ? 'တင်ဆောင်မှု၊ လမ်းကြောင်းအခြေအနေနှင့် ပြီးဆုံးပမာဏကို ကြည့်ရှုပါ။' : 'Track loading, route progress, and completed quantities.');

    return <div className="driver-delivery-list"><header className="mobile-master-heading"><div><p className="eyebrow">{localized ? 'ပို့ဆောင်မှု အခြေအနေ' : 'Delivery status'}</p><h1>{pageTitle}</h1><span className="muted">{pageHint}</span></div><button className="icon-button" type="button" aria-label="Refresh delivery status" onClick={() => setRefresh((value) => value + 1)}><RefreshCw size={17} /></button></header><div className="mobile-credit-card"><span>{localized ? 'လက်ရှိ ပို့ဆောင်မှုများ' : 'Active deliveries'}</span><strong>{number(state.summary.active_count)}</strong><small>{number(state.summary.completed_count)} {localized ? 'ပြီးဆုံး ·' : 'completed ·'} {number(state.summary.total_quantity)} {localized ? 'စုစုပေါင်းယူနစ်' : 'total units'}</small></div>{state.loading ? <MobileState title={localized ? 'ပို့ဆောင်မှုအခြေအနေ တင်နေသည်' : 'Loading delivery status'} loading /> : state.error ? <MobileState title={state.error} action={() => setRefresh((value) => value + 1)} /> : !state.items.length ? <MobileState title={localized ? 'ပို့ဆောင်မှု မရှိသေးပါ။' : 'No deliveries are available yet.'} /> : <section className="mobile-master-section"><div className="mobile-section-heading"><div><p className="eyebrow">{localized ? 'ပို့ဆောင်မှုမှတ်တမ်း' : 'Delivery history'}</p><h2>{number(state.summary.deliveries_count)} {localized ? 'ပို့ဆောင်မှု' : 'deliveries'}</h2></div></div><div className="mobile-delivery-cards">{state.items.map((item) => <button type="button" key={item.id} onClick={() => openDelivery(item.id)}><span className="mobile-order-icon"><Truck size={17} /></span><span><strong>{appId === 'client' ? item.code : item.shop_name}</strong><small>{appId === 'client' ? item.invoice_code : item.code} / {date(item.planned_date)}</small><small>{item.route_name} / {number(item.total_quantity)} {localized ? 'ယူနစ်' : 'units'}</small></span><span><span className={`status ${statusFamily(item.status)}`}>{statusLabel(item.status)}</span><ChevronRight size={16} /></span></button>)}</div></section>}</div>;
}

export function MobileDriverExecutionScreen({ mode, locale = 'en' }) {
    const localized = locale === 'my';
    const mobileApi = () => window.ValleyRuntime?.api?.mobileDeliveries || '/api/mobile/deliveries';
    const [state, setState] = useState({ loading: true, items: [], error: '' });
    const [detail, setDetail] = useState({ loading: false, delivery: null, items: [], error: '' });
    const [resultStatus, setResultStatus] = useState('delivered');
    const [results, setResults] = useState({});
    const [notes, setNotes] = useState('');
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState('');
    const [sharing, setSharing] = useState({ state: 'idle', message: '' });
    const [refresh, setRefresh] = useState(0);
    const lastLocationShare = useRef(0);
    const routeMode = mode === 'route';

    useEffect(() => {
        let mounted = true;
        window.axios.get(mobileApi()).then(({ data }) => {
            if (!mounted) return;
            const allowed = routeMode ? ['loading', 'on_route'] : ['on_route', 'delivered', 'partially_delivered', 'failed'];
            setState({ loading: false, items: data.data.items.filter((item) => allowed.includes(item.status)), error: '' });
        }).catch((error) => mounted && setState({ loading: false, items: [], error: errorMessage(error) }));
        return () => { mounted = false; };
    }, [refresh, routeMode]);

    useEffect(() => {
        if (!routeMode || detail.delivery?.status !== 'on_route') {
            setSharing({ state: 'idle', message: '' });
            return undefined;
        }
        if (!navigator.geolocation) {
            setSharing({ state: 'error', message: 'GPS is not available on this device.' });
            return undefined;
        }

        setSharing({ state: 'waiting', message: 'Waiting for GPS permission…' });
        lastLocationShare.current = 0;
        const controller = new AbortController();
        let active = true;
        let uploadInFlight = false;
        const watchId = navigator.geolocation.watchPosition((position) => {
            const now = Date.now();
            if (!active || uploadInFlight || now - lastLocationShare.current < 15000) return;
            lastLocationShare.current = now;
            uploadInFlight = true;
            setSharing({ state: 'sharing', message: 'Sharing current location…' });
            window.axios.post(`${mobileApi()}/${detail.delivery.id}/location`, {
                latitude: position.coords.latitude,
                longitude: position.coords.longitude,
                accuracy_m: position.coords.accuracy,
                heading: position.coords.heading,
                speed_kmh: position.coords.speed === null ? null : position.coords.speed * 3.6,
                recorded_at: new Date(position.timestamp).toISOString(),
            }, { signal: controller.signal })
                .then(() => active && setSharing({ state: 'live', message: `Location shared at ${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}` }))
                .catch((error) => {
                    if (active && error.code !== 'ERR_CANCELED') setSharing({ state: 'error', message: errorMessage(error) });
                })
                .finally(() => { uploadInFlight = false; });
        }, (error) => {
            if (active) setSharing({ state: 'error', message: error.code === 1 ? 'Location permission is required for the active route.' : 'Current GPS position is unavailable.' });
        }, { enableHighAccuracy: true, maximumAge: 10000, timeout: 20000 });

        return () => {
            active = false;
            navigator.geolocation.clearWatch(watchId);
            controller.abort();
        };
    }, [routeMode, detail.delivery?.id, detail.delivery?.status]);

    const open = (id) => {
        setDetail({ loading: true, delivery: null, items: [], error: '' }); setMessage('');
        window.axios.get(`${mobileApi()}/${id}`).then(({ data }) => {
            setDetail({ loading: false, delivery: data.data.delivery, items: data.data.items, error: '' });
            setResults(Object.fromEntries(data.data.items.map((item) => [item.id, { delivered_quantity: item.delivered_quantity || item.loaded_quantity, returned_quantity: item.returned_quantity || 0, damaged_quantity: item.damaged_quantity || 0 }])));
            setResultStatus(data.data.delivery.status === 'on_route' ? 'delivered' : data.data.delivery.status);
            setNotes(data.data.delivery.notes || '');
        }).catch((error) => setDetail({ loading: false, delivery: null, items: [], error: errorMessage(error) }));
    };

    const startRoute = () => {
        setSaving(true); setMessage('');
        window.axios.post(`${mobileApi()}/${detail.delivery.id}/start-route`).then(({ data }) => { setSaving(false); setMessage('Route started.'); setDetail((current) => ({ ...current, delivery: data.data.delivery })); setRefresh((value) => value + 1); }).catch((error) => { setSaving(false); setMessage(errorMessage(error)); });
    };

    const complete = () => {
        setSaving(true); setMessage('');
        window.axios.post(`${mobileApi()}/${detail.delivery.id}/complete`, { status: resultStatus, items: detail.items.map((item) => ({ id: item.id, ...results[item.id] })), notes }).then(({ data }) => { setSaving(false); setMessage('Delivery result saved.'); setDetail((current) => ({ ...current, delivery: data.data.delivery, items: data.data.items })); setRefresh((value) => value + 1); }).catch((error) => { setSaving(false); setMessage(errorMessage(error)); });
    };

    if (detail.loading) return <MobileState title="Loading delivery" loading />;
    if (detail.delivery) {
        const active = detail.delivery.status === 'on_route';
        return <div className="driver-delivery-detail"><header className="mobile-section-heading"><button className="icon-button" type="button" aria-label="Back" onClick={() => setDetail({ loading: false, delivery: null, items: [], error: '' })}><ArrowLeft size={18} /></button><div><p className="eyebrow">{routeMode ? 'Route execution' : 'Delivery result'}</p><h2>{detail.delivery.code}</h2></div><span className={`status ${statusFamily(detail.delivery.status)}`}>{statusLabel(detail.delivery.status)}</span></header><section className="driver-delivery-hero"><div><Navigation size={22} /><span><strong>{detail.delivery.shop_name}</strong><small>{detail.delivery.route_name} · {detail.delivery.plate_no}</small></span></div>{detail.delivery.delivery_address && <p><MapPin size={14} />{detail.delivery.delivery_address}</p>}</section>
            {routeMode ? <section className="driver-load-panel"><div className="mobile-section-heading"><div><p className="eyebrow">Departure</p><h2>{detail.delivery.status === 'loading' ? 'Ready to start route' : 'Route in progress'}</h2></div><strong>{number(detail.delivery.loaded_quantity)} units</strong></div><div className="driver-route-summary"><Info label="Warehouse" value={detail.delivery.warehouse_name} /><Info label="Vehicle" value={`${detail.delivery.vehicle_code} · ${detail.delivery.plate_no}`} /><Info label="Customer" value={detail.delivery.shop_name} /><Info label="Invoice" value={detail.delivery.invoice_code} /></div>{detail.delivery.status === 'on_route' && <div className={`driver-location-status ${sharing.state}`}><Navigation size={17} /><span><strong>Live location</strong><small>{sharing.message || 'GPS sharing starts automatically during the route.'}</small></span></div>}{message && <p className={message === 'Route started.' ? 'mobile-success-message' : 'form-alert'}>{message}</p>}{detail.delivery.status === 'loading' && <button className="button primary driver-load-action" type="button" disabled={saving} onClick={startRoute}><Navigation size={17} />{saving ? 'Starting…' : 'Start delivery route'}</button>}</section>
                : <section className="driver-load-panel"><div className="mobile-section-heading"><div><p className="eyebrow">Reconciliation</p><h2>Delivery quantities</h2></div><strong>{number(detail.delivery.loaded_quantity)} loaded</strong></div>{active && <label className="driver-result-status">Result<select value={resultStatus} onChange={(event) => setResultStatus(event.target.value)}><option value="delivered">Delivered</option><option value="partially_delivered">Partially delivered</option><option value="failed">Failed</option></select></label>}<div className="driver-result-items">{detail.items.map((item) => <article key={item.id}><header><span><strong>{item.product_name}</strong><small>{item.product_sku} · {number(item.loaded_quantity)} loaded</small></span><strong>{number(Number(results[item.id]?.delivered_quantity || 0) + Number(results[item.id]?.returned_quantity || 0) + Number(results[item.id]?.damaged_quantity || 0))} / {number(item.loaded_quantity)}</strong></header><div>{['delivered_quantity', 'returned_quantity', 'damaged_quantity'].map((field) => <label key={field}>{field.replace('_quantity', '')}<input type="number" min="0" max={item.loaded_quantity} step="0.01" disabled={!active} value={results[item.id]?.[field] ?? 0} onChange={(event) => setResults((current) => ({ ...current, [item.id]: { ...current[item.id], [field]: event.target.value } }))} /></label>)}</div></article>)}</div>{active && <label className="driver-load-notes">Completion note<textarea value={notes} onChange={(event) => setNotes(event.target.value)} /></label>}{message && <p className={message === 'Delivery result saved.' ? 'mobile-success-message' : 'form-alert'}>{message}</p>}{active && <button className="button primary driver-load-action" type="button" disabled={saving} onClick={complete}><CheckCircle2 size={17} />{saving ? 'Saving…' : 'Save delivery result'}</button>}</section>}
        </div>;
    }

    return <div className="driver-delivery-list"><header className="mobile-master-heading"><div><p className="eyebrow">{localized ? 'ယာဉ်မောင်း' : 'Driver'}</p><h1>{routeMode ? (localized ? 'ပို့ဆောင်ရေးလမ်းကြောင်း' : 'Delivery route') : (localized ? 'ပို့ဆောင်မှု အတည်ပြုခြင်း' : 'Confirm delivery')}</h1><span className="muted">{routeMode ? (localized ? 'တင်ဆောင်ပြီးသောတာဝန်ကို စတင်၍ လက်ရှိလမ်းကြောင်းကို လိုက်နာပါ။' : 'Start loaded assignments and follow the active route.') : (localized ? 'ပို့ဆောင်၊ ပြန်အပ်နှင့် ပျက်စီးပမာဏများကို မှတ်တမ်းတင်ပါ။' : 'Record delivered, returned, and damaged quantities.')}</span></div><button className="icon-button" type="button" aria-label="Refresh" onClick={() => setRefresh((value) => value + 1)}><RefreshCw size={17} /></button></header>{state.loading ? <MobileState title={localized ? 'ပို့ဆောင်မှုများ တင်နေသည်' : 'Loading deliveries'} loading /> : state.error ? <MobileState title={state.error} action={() => setRefresh((value) => value + 1)} /> : !state.items.length ? <MobileState title={routeMode ? (localized ? 'အဆင်သင့် လမ်းကြောင်း မရှိပါ။' : 'No loaded routes are ready.') : (localized ? 'ပို့ဆောင်မှုရလဒ် မရှိပါ။' : 'No delivery results are available.')} /> : <section className="mobile-master-section"><div className="mobile-delivery-cards">{state.items.map((item) => <button type="button" key={item.id} onClick={() => open(item.id)}><span className="mobile-order-icon">{routeMode ? <Navigation size={17} /> : <CheckCircle2 size={17} />}</span><span><strong>{item.shop_name}</strong><small>{item.code} · {item.route_name}</small><small>{number(item.loaded_quantity)} {localized ? 'တင်ဆောင်ပြီး' : 'loaded'}</small></span><span><span className={`status ${statusFamily(item.status)}`}>{statusLabel(item.status)}</span><ChevronRight size={16} /></span></button>)}</div></section>}</div>;
}

function Metric({ label, value, hint, icon: Icon }) { return <article className="metric"><span>{label}</span><strong>{value}</strong><small>{hint}</small><Icon size={18} /></article>; }
function State({ title, loading = false }) { return <div className="workspace-state"><RefreshCw className={loading ? 'spin' : ''} size={22} /><strong>{title}</strong></div>; }
function MobileState({ title, loading = false, action }) { return <div className="workspace-state compact"><RefreshCw className={loading ? 'spin' : ''} size={22} /><strong>{title}</strong>{action && <button className="button" type="button" onClick={action}>Retry</button>}</div>; }
function Field({ label, wide, children }) { return <label className={wide ? 'span-2' : ''}>{label}{children}</label>; }
function Select({ label, name, items, form, setForm }) { return <Field label={label}><select required value={form[name]} onChange={(e) => setForm((v) => ({ ...v, [name]: e.target.value }))}>{items.map((item) => <option key={item.id} value={item.id}>{item.label}</option>)}</select></Field>; }
function Info({ label, value }) { return <div><dt>{label}</dt><dd>{value || '-'}</dd></div>; }
function Pagination({ meta = {}, page, setPage }) { return meta.last_page > 1 ? <div className="master-pagination"><span>Page {meta.current_page} of {meta.last_page} · {meta.total}</span><div><button disabled={page <= 1} onClick={() => setPage((v) => v - 1)}>Previous</button><button disabled={page >= meta.last_page} onClick={() => setPage((v) => v + 1)}>Next</button></div></div> : null; }
