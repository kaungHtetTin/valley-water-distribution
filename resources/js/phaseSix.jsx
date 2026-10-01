import { ArrowDown, ArrowUp, Ban, CalendarDays, Check, CheckCircle2, ChevronRight, MapPin, Navigation, Package, Pencil, Plus, Printer, RefreshCw, Save, Search, TriangleAlert, Truck, X } from 'lucide-react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { useEffect, useId, useMemo, useRef, useState } from 'react';
import { DetailPage, DetailPanel } from './components/DetailPage';
import { ShellBackButton } from './components/ShellBackButton';
import { ShellPageActions } from './components/ShellPageActions';

const statuses = ['planned', 'assigned', 'loading', 'on_route', 'delivered', 'partially_delivered', 'failed', 'cancelled'];
const blank = { invoice_ids: [], warehouse_id: '', route_id: '', driver_id: '', vehicle_id: '', planned_date: new Date().toISOString().slice(0, 10), delivery_address: '', notes: '' };
const api = () => window.ValleyRuntime?.api?.deliveries || '/api/deliveries';
const errorMessage = (error) => error.response?.data?.message || 'The delivery request could not be completed.';
const number = (value) => Number(value || 0).toLocaleString();
const money = (value) => `${number(value)} MMK`;
const hasGpsCoordinate = (value) => value !== null && value !== '' && Number.isFinite(Number(value));
const date = (value) => {
    if (!value) return '-';
    const source = String(value);
    const parsed = new Date(source.includes('T') ? source : (source.includes(' ') ? source.replace(' ', 'T') : `${source}T00:00:00`));
    return Number.isNaN(parsed.getTime()) ? source : parsed.toLocaleDateString([], { year: 'numeric', month: 'short', day: '2-digit' });
};
const statusLabel = (value) => String(value || '').replaceAll('_', ' ');
const statusFamily = (value) => ['delivered'].includes(value) ? 'success' : ['failed', 'cancelled'].includes(value) ? 'danger' : ['loading', 'on_route'].includes(value) ? 'info' : ['partially_delivered'].includes(value) ? 'warning' : 'neutral';
const driverTrace = (stage, details = {}) => {
    if (import.meta.env.DEV) window.console.debug(`[DriverTrace] ${stage}`, details);
};
const driverTraceError = (stage, error, details = {}) => {
    if (!import.meta.env.DEV) return;
    window.console.error(`[DriverTrace] ${stage}`, {
    ...details,
    message: error?.message || String(error),
    method: error?.config?.method || null,
    url: error?.config?.url || null,
    status: error?.response?.status || null,
    statusText: error?.response?.statusText || null,
    response: error?.response?.data || null,
    });
};
const buildFinalSaleRows = (stop, availableProducts = []) => {
    const rows = new Map();
    (stop.items || []).filter((item) => Number(item.planned_quantity) > 0 || Number(item.delivered_quantity) > 0).forEach((item) => {
        const productId = Number(item.product_id);
        const key = `product-${productId}`;
        const available = availableProducts.find((product) => Number(product.id) === productId);
        const current = rows.get(key) || {
            key,
            product_id: productId,
            product_name: item.product_name,
            product_sku: item.product_sku,
            unit: item.unit,
            sale_id: null,
            foc_id: null,
            sale_quantity: 0,
            foc_quantity: 0,
            unit_price: Number(available?.unit_price || 0),
            discount_amount: 0,
            damaged_quantity: 0,
            loaded_quantity: 0,
        };
        const itemType = item.item_type || (Number(item.unit_price) > 0 ? 'sale' : 'foc');
        const initialQuantity = Number(item.delivered_quantity || Math.min(Number(item.planned_quantity || 0), Number(item.loaded_quantity || 0)));
        current.loaded_quantity += Number(item.loaded_quantity || 0);
        current.damaged_quantity += Number(item.damaged_quantity || 0);
        if (itemType === 'foc') {
            current.foc_id = item.id;
            current.foc_quantity += initialQuantity;
        } else {
            current.sale_id = item.id;
            current.sale_quantity += initialQuantity;
            current.unit_price = Number(item.unit_price || available?.unit_price || 0);
            current.discount_amount += Number(item.discount_amount || 0);
        }
        if (available) current.loaded_quantity = Number(available.loaded_quantity || current.loaded_quantity);
        rows.set(key, current);
    });

    return Array.from(rows.values());
};

export function DeliveryPlanningScreen({ canManage = false, locale = 'en', navigate, detailId = null }) {
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
        const timer = window.setTimeout(() => window.axios.get(api(), { params: { ...filters, page, per_page: 20 } }).then(({ data }) => mounted && setState({ loading: false, items: data.data.items, summary: data.data.summary, meta: data.data.meta, error: '' })).catch((error) => mounted && setState({ loading: false, items: [], summary: {}, meta: {}, error: errorMessage(error) })), 220);
        return () => { mounted = false; window.clearTimeout(timer); };
    }, [filters, page, refresh]);
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
    const show = (id) => navigate(`${window.ValleyRuntime?.routes?.office || '/office'}/deliveries/${id}`);

    useEffect(() => {
        setSelectedStopId(null);
        if (detailId) loadDetail(detailId);
        else setDetail({ loading: false, delivery: null, trip: null, stops: [], stock: [], items: [], error: '' });
    }, [detailId]);

    const listPath = `${window.ValleyRuntime?.routes?.office || '/office'}/deliveries`;
    const selectedStop = detail.stops.find((stop) => stop.id === selectedStopId) || detail.stops[0] || null;
    const tripStatus = detail.trip?.status || detail.delivery?.status;
    const canChangePlan = canManage && ['planned', 'assigned'].includes(tripStatus) && detail.stops.every((stop) => Number(stop.loaded_quantity || 0) === 0);
    const cancelTrip = () => {
        if (!detail.delivery || processing) return;
        setProcessing(true); setActionError('');
        window.axios.post(`${api()}/${detail.delivery.id}/cancel`).then(({ data }) => {
            const stops = data.data.stops || [];
            setDetail({ loading: false, delivery: data.data.delivery, trip: data.data.trip, stops, stock: data.data.stock || [], items: data.data.items || [], error: '' });
            setState((current) => {
                const matchesTrip = (item) => item.id === data.data.delivery.id || (data.data.delivery.trip_id && item.trip_id === data.data.delivery.trip_id);
                const previous = current.items.find(matchesTrip);
                const items = current.items.map((item) => matchesTrip(item)
                    ? { ...item, ...data.data.delivery, id: item.id, code: data.data.trip?.code || item.code, status: data.data.trip?.status || data.data.delivery.status }
                    : item);
                const wasPending = ['planned', 'assigned'].includes(previous?.status);
                return {
                    ...current,
                    items,
                    summary: {
                        ...current.summary,
                        pending_count: wasPending ? Math.max(0, Number(current.summary.pending_count || 0) - 1) : current.summary.pending_count,
                    },
                };
            });
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
                    <div className="master-table-wrap"><table className="master-table delivery-trip-stock-table"><colgroup><col /><col /><col /><col /><col /><col /><col /></colgroup><thead><tr><th>Product</th><th>SKU / unit</th><th className="numeric">Required</th><th className="numeric">Loaded</th><th className="numeric">Delivered</th><th className="numeric">Trip buffer / pending return</th><th className="numeric">Damaged</th></tr></thead><tbody>{detail.stock.map((item) => <tr key={item.product_id}><td><strong>{item.product_name}</strong></td><td>{item.product_sku} · {item.unit}</td><td className="numeric"><strong>{number(item.planned_quantity)}</strong></td><td className="numeric">{number(item.loaded_quantity)}</td><td className="numeric">{number(item.delivered_quantity)}</td><td className="numeric">{number(item.returned_quantity)}</td><td className="numeric">{number(item.damaged_quantity)}</td></tr>)}</tbody><tfoot><tr><th colSpan="2">Total units</th><td className="numeric"><strong>{number(detail.stock.reduce((sum, item) => sum + Number(item.planned_quantity || 0), 0))}</strong></td><td className="numeric"><strong>{number(detail.stock.reduce((sum, item) => sum + Number(item.loaded_quantity || 0), 0))}</strong></td><td className="numeric"><strong>{number(detail.stock.reduce((sum, item) => sum + Number(item.delivered_quantity || 0), 0))}</strong></td><td className="numeric"><strong>{number(detail.stock.reduce((sum, item) => sum + Number(item.returned_quantity || 0), 0))}</strong></td><td className="numeric"><strong>{number(detail.stock.reduce((sum, item) => sum + Number(item.damaged_quantity || 0), 0))}</strong></td></tr></tfoot></table></div>
                </DetailPanel>
            </div>}
        >
            {detail.loading ? <State title="Loading delivery trip" loading /> : detail.error ? <State title={detail.error} /> : detail.delivery && <DetailPanel eyebrow="Details" title="Trip information"><dl className="record-page-facts"><Info label="Planned date" value={date(detail.trip?.planned_date || detail.delivery.planned_date)} /><Info label="Warehouse" value={`${detail.delivery.warehouse_code} · ${detail.delivery.warehouse_name}`} /><Info label="Route" value={`${detail.delivery.route_code} · ${detail.delivery.route_name}`} /><Info label="Driver" value={`${detail.delivery.driver_code} · ${detail.delivery.driver_name}`} /><Info label="Vehicle" value={`${detail.delivery.vehicle_code} · ${detail.delivery.plate_no}`} /><Info label="Status" value={statusLabel(detail.trip?.status || detail.delivery.status)} /></dl></DetailPanel>}
        </DetailPage>
        {cancelOpen && <TripCancelConfirmation code={detail.trip?.code || detail.delivery?.trip_code || detail.delivery?.code} processing={processing} onConfirm={cancelTrip} onDismiss={() => setCancelOpen(false)} />}
    </>);

    return <section className="page delivery-workspace">
        <div className="master-heading"><div><p className="eyebrow">{localized ? 'ပို့ဆောင်ရေးလုပ်ငန်း' : 'Delivery operations'}</p><h1>{localized ? 'ပို့ဆောင်မှုစီစဉ်ခြင်း' : 'Trip planning'}</h1><span className="muted">Plan new trips and review active, completed, failed, or cancelled delivery records in one place.</span></div><ShellPageActions>{canManage && <button className="button primary" type="button" onClick={openForm} disabled={meta.loading || !meta.invoices.length}><Plus size={16} />New trip</button>}</ShellPageActions></div>
        <div className="metrics delivery-metrics"><Metric label="Trips" value={number(state.summary.deliveries_count)} hint="All statuses" icon={Truck} /><Metric label="Planned quantity" value={number(state.summary.total_quantity)} hint="Units" icon={Package} /><Metric label="Pending" value={number(state.summary.pending_count)} hint="Planned or assigned" icon={CalendarDays} /><Metric label="Delivered" value={number(state.summary.delivered_count)} hint="Completed trips" icon={Truck} /></div>
        <section className="master-panel"><div className="master-panel-heading"><div><p className="eyebrow">{localized ? 'ပို့ဆောင်မှုများ' : 'Trips'}</p><h2>{localized ? 'ပို့ဆောင်မှုမှတ်တမ်းအားလုံး' : 'All delivery records'}</h2></div></div><div className="master-toolbar delivery-toolbar"><label className="master-search"><Search size={14} /><input placeholder="Search delivery, invoice, shop, driver" value={filters.search} onChange={(e) => setFilters((v) => ({ ...v, search: e.target.value }))} /></label><select value={filters.status} onChange={(e) => setFilters((v) => ({ ...v, status: e.target.value }))}><option value="">All statuses</option>{statuses.map((item) => <option key={item} value={item}>{statusLabel(item)}</option>)}</select><select value={filters.driver_id} onChange={(e) => setFilters((v) => ({ ...v, driver_id: e.target.value }))}><option value="">All drivers</option>{meta.drivers.map((item) => <option key={item.id} value={item.id}>{item.label}</option>)}</select><label className="date-filter"><CalendarDays size={14} /><input type="date" value={filters.date} onChange={(e) => setFilters((v) => ({ ...v, date: e.target.value }))} /></label><button className="button" type="button" onClick={() => setRefresh((v) => v + 1)}><RefreshCw size={15} />Refresh</button></div>
            {state.loading ? <State title="Loading delivery trips" loading /> : state.error || meta.error ? <State title={state.error || meta.error} /> : !state.items.length ? <State title="No trips match this view." /> : <><div className="master-table-wrap"><table className="master-table delivery-table"><thead><tr><th>Trip</th><th>Stops / orders</th><th>Plan</th><th>Driver / vehicle</th><th>Route / warehouse</th><th>Quantity</th><th>Status</th></tr></thead><tbody>{state.items.map((item) => <tr key={item.id} onClick={() => show(item.id)} tabIndex="0" onKeyDown={(e) => e.key === 'Enter' && show(item.id)}><td><strong>{item.code}</strong><span className="muted">{date(item.planned_date)}</span></td><td><strong>{item.customer_summary}</strong><span className="muted">{number(item.stops_count)} stops · {number(item.orders_count)} orders</span></td><td>{date(item.planned_date)}</td><td><strong>{item.driver_name}</strong><span className="muted">{item.vehicle_code} · {item.plate_no}</span></td><td><strong>{item.route_name}</strong><span className="muted">{item.warehouse_code}</span></td><td className="numeric">{number(item.total_quantity)}</td><td><span className={`status ${statusFamily(item.status)}`}>{statusLabel(item.status)}</span></td></tr>)}</tbody></table></div><Pagination meta={state.meta} page={page} setPage={setPage} /></>}
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
        <ShellBackButton onClick={() => navigate(`${listPath}/${deliveryId}`)} label="Back to trip" />
        <div className="master-heading trip-wizard-heading"><div><p className="eyebrow">Delivery operations</p><h1>Edit trip plan</h1><span className="muted">{state.trip?.code || state.delivery.trip_code || state.delivery.code} · Change the schedule and assignment before loading starts.</span></div></div>
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
    const [showAllOrders, setShowAllOrders] = useState(false);
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

    const selectedOrders = useMemo(() => form.invoice_ids.map((id) => meta.invoices.find((invoice) => invoice.id === id)).filter(Boolean), [meta.invoices, form.invoice_ids]);
    const visibleOrders = useMemo(() => {
        const query = orderSearch.trim().toLowerCase();
        const availableOrders = showAllOrders ? meta.invoices : meta.invoices.filter((invoice) => Number(invoice.route_id) === Number(form.route_id));
        if (!query) return availableOrders;
        return availableOrders.filter((invoice) => [invoice.order_code, invoice.code, invoice.shop_name, invoice.delivery_address].some((value) => String(value || '').toLowerCase().includes(query)));
    }, [form.route_id, meta.invoices, orderSearch, showAllOrders]);
    const plannedStock = useMemo(() => Object.values(selectedOrders.flatMap((invoice) => invoice.items || []).reduce((totals, item) => {
        const current = totals[item.product_id] || { ...item, quantity: 0 };
        current.quantity += Number(item.quantity || 0);
        totals[item.product_id] = current;
        return totals;
    }, {})), [selectedOrders]);
    const selectedQuantity = plannedStock.reduce((sum, item) => sum + item.quantity, 0);
    const selectedValue = selectedOrders.reduce((sum, invoice) => sum + Number(invoice.total || 0), 0);
    const lookup = (items, id) => items.find((item) => Number(item.id) === Number(id));

    const toggleOrder = (id) => setForm((current) => {
        const invoice = meta.invoices.find((item) => item.id === id);
        if (invoice && Number(invoice.route_id) !== Number(current.route_id)) {
            return { ...current, route_id: invoice.route_id, invoice_ids: [id] };
        }
        return {
            ...current,
            invoice_ids: current.invoice_ids.includes(id) ? current.invoice_ids.filter((value) => value !== id) : [...current.invoice_ids, id],
        };
    });
    const toggleVisible = () => {
        const visibleIds = visibleOrders.filter((item) => Number(item.route_id) === Number(form.route_id)).map((item) => item.id);
        const allSelected = visibleIds.length > 0 && visibleIds.every((id) => form.invoice_ids.includes(id));
        setForm((current) => ({
            ...current,
            invoice_ids: allSelected
                ? current.invoice_ids.filter((id) => !visibleIds.includes(id))
                : [...new Set([...current.invoice_ids, ...visibleIds])],
        }));
    };
    const moveOrder = (index, offset) => setForm((current) => {
        const invoiceIds = [...current.invoice_ids];
        const target = index + offset;
        if (target < 0 || target >= invoiceIds.length) return current;
        [invoiceIds[index], invoiceIds[target]] = [invoiceIds[target], invoiceIds[index]];
        return { ...current, invoice_ids: invoiceIds };
    });
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
    const stepIndicator = <div className="trip-plan-stepper-wrap">
        <ol className="trip-wizard-steps stock-receive-steps trip-plan-steps" aria-label="Trip creation progress">
            {steps.map((item) => <li className={`${step === item.number ? 'is-current' : ''} ${step > item.number ? 'is-complete' : ''}`} key={item.number}><button type="button" disabled={item.number > step} onClick={() => item.number < step && setStep(item.number)} aria-current={step === item.number ? 'step' : undefined}><span>{step > item.number ? <Check size={13} /> : item.number}</span><strong>{item.label}</strong><small>{item.hint}</small></button></li>)}
        </ol>
    </div>;

    return <section className="page trip-wizard-page">
        <ShellBackButton onClick={step > 1 ? back : () => navigate(listPath)} label={step > 1 ? 'Previous step' : 'Back to trips'} />
        <div className="master-heading trip-wizard-heading"><div><p className="eyebrow">{localized ? 'ပို့ဆောင်ရေးလုပ်ငန်း' : 'Delivery operations'}</p><h1>Create delivery trip</h1><span className="muted">Build one clear load plan from ready customer orders.</span></div></div>

        {error && <p className="form-alert trip-wizard-error" role="alert">{error}</p>}

        <section className="master-panel trip-wizard-panel">
            {step === 1 && <><div className="master-panel-heading trip-wizard-panel-heading"><div><p className="eyebrow">Step 1 of 3</p><h2>Basic trip information</h2><span className="muted">Set when, where, and who will operate this trip.</span></div>{stepIndicator}</div><div className="trip-wizard-form master-form-grid"><Field label="Planned date"><input required type="date" value={form.planned_date} onChange={(event) => setForm((current) => ({ ...current, planned_date: event.target.value }))} /></Field><Select label="Warehouse" name="warehouse_id" items={meta.warehouses} form={form} setForm={setForm} /><Select label="Route" name="route_id" items={meta.routes} form={form} setForm={setForm} /><Select label="Driver" name="driver_id" items={meta.drivers} form={form} setForm={setForm} /><Select label="Vehicle" name="vehicle_id" items={meta.vehicles} form={form} setForm={setForm} /><Field label="Trip notes" wide><textarea rows="4" placeholder="Loading or route instructions" value={form.notes} onChange={(event) => setForm((current) => ({ ...current, notes: event.target.value }))} /></Field></div></>}

            {step === 2 && <><div className="master-panel-heading trip-wizard-panel-heading"><div><p className="eyebrow">Step 2 of 3</p><h2>Select ready orders</h2><span className="muted">Only issued orders that are not assigned to another trip are available.</span><strong className="trip-step-meta">{selectedOrders.length} selected</strong></div>{stepIndicator}</div><div className="trip-order-scope"><span className="muted">{showAllOrders ? 'All ready orders are shown. Selecting another route starts a new route selection.' : 'Only orders on the selected route are shown.'}</span><div className="trip-order-scope-toggle" role="group" aria-label="Order route scope"><button className={!showAllOrders ? 'is-active' : ''} type="button" aria-pressed={!showAllOrders} onClick={() => setShowAllOrders(false)}>On route</button><button className={showAllOrders ? 'is-active' : ''} type="button" aria-pressed={showAllOrders} onClick={() => setShowAllOrders(true)}>Show all</button></div></div><div className="trip-order-toolbar"><label className="master-search"><Search size={14} /><input placeholder="Search order, invoice, or customer" value={orderSearch} onChange={(event) => setOrderSearch(event.target.value)} /></label>{!showAllOrders && <button className="button" type="button" disabled={!visibleOrders.length} onClick={toggleVisible}>{visibleOrders.length > 0 && visibleOrders.every((item) => form.invoice_ids.includes(item.id)) ? 'Clear visible' : 'Select visible'}</button>}</div>{!visibleOrders.length ? <State title="No ready orders match this search." /> : <div className="master-table-wrap"><table className="master-table trip-order-table"><colgroup><col className="trip-select-col" /><col className="trip-order-col" /><col className="trip-customer-col" /><col className="trip-invoice-col" /><col className="trip-items-col" /><col className="trip-quantity-col" /><col className="trip-value-col" /></colgroup><thead><tr><th aria-label="Select order"></th><th>Order</th><th>Customer</th><th>Invoice</th><th className="numeric">Items</th><th className="numeric">Quantity</th><th className="numeric">Value</th></tr></thead><tbody>{visibleOrders.map((invoice) => { const quantity = (invoice.items || []).reduce((sum, item) => sum + Number(item.quantity || 0), 0); return <tr className={form.invoice_ids.includes(invoice.id) ? 'is-selected' : ''} key={invoice.id} onClick={() => toggleOrder(invoice.id)}><td><input aria-label={`Select ${invoice.order_code || invoice.code}`} type="checkbox" checked={form.invoice_ids.includes(invoice.id)} onClick={(event) => event.stopPropagation()} onChange={() => toggleOrder(invoice.id)} /></td><td><strong>{invoice.order_code || '-'}</strong></td><td title={invoice.shop_name}>{invoice.shop_name}</td><td>{invoice.code}</td><td className="numeric">{invoice.items?.length || 0}</td><td className="numeric">{number(quantity)}</td><td className="numeric"><strong>{money(invoice.total)}</strong></td></tr>; })}</tbody></table></div>}</>}

            {step === 3 && <>
                <div className="master-panel-heading trip-wizard-panel-heading"><div><p className="eyebrow">Step 3 of 3</p><h2>Review trip</h2><span className="muted">Confirm the assignment, delivery stops, and planned warehouse issue.</span><span className="status neutral trip-step-meta">Ready to submit</span></div>{stepIndicator}</div>
                <div className="trip-review-grid">
                    <section><h3>Trip information</h3><dl className="trip-review-facts"><Info label="Planned date" value={date(form.planned_date)} /><Info label="Warehouse" value={lookup(meta.warehouses, form.warehouse_id)?.label} /><Info label="Route" value={lookup(meta.routes, form.route_id)?.label} /><Info label="Driver" value={lookup(meta.drivers, form.driver_id)?.label} /><Info label="Vehicle" value={lookup(meta.vehicles, form.vehicle_id)?.label} /><Info label="Notes" value={form.notes || 'No trip notes'} /></dl></section>
                    <aside><h3>Trip totals</h3><div className="trip-review-totals"><span><small>Orders</small><strong>{selectedOrders.length}</strong></span><span><small>Products</small><strong>{plannedStock.length}</strong></span><span><small>Units</small><strong>{number(selectedQuantity)}</strong></span><span><small>Order value</small><strong>{money(selectedValue)}</strong></span></div></aside>
                </div>
                <div className="trip-review-section">
                    <div className="trip-section-heading"><span>Delivery stops</span><strong>{selectedOrders.length} orders</strong></div>
                    <div className="master-table-wrap">
                        <table className="master-table trip-stop-table">
                            <colgroup><col /><col /><col /><col /><col /><col /><col /></colgroup>
                            <thead><tr><th>Stop</th><th>Order</th><th>Customer</th><th>Delivery address</th><th>Invoice</th><th className="numeric">Quantity</th><th className="numeric">Value</th></tr></thead>
                            <tbody>{selectedOrders.map((invoice, index) => <tr key={invoice.id}><td><div className="trip-stop-order"><strong>{index + 1}</strong><button type="button" disabled={index === 0} aria-label="Move stop up" onClick={() => moveOrder(index, -1)}><ArrowUp size={13} /></button><button type="button" disabled={index === selectedOrders.length - 1} aria-label="Move stop down" onClick={() => moveOrder(index, 1)}><ArrowDown size={13} /></button></div></td><td><strong>{invoice.order_code || '-'}</strong></td><td><strong>{invoice.shop_name}</strong><span className="muted">{invoice.recipient_phone || 'No phone'}</span></td><td className="trip-stop-address">{invoice.delivery_address || <span className="muted">No delivery address</span>}</td><td>{invoice.code}</td><td className="numeric">{number((invoice.items || []).reduce((sum, item) => sum + Number(item.quantity || 0), 0))}</td><td className="numeric"><strong>{money(invoice.total)}</strong></td></tr>)}</tbody>
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

        <footer className="trip-wizard-actions"><span>Step {step} of 3</span><div>{step < 3 ? <button className="button primary" type="button" onClick={next}>Continue<ChevronRight size={15} /></button> : <button className="button primary" type="button" disabled={saving} onClick={submit}><Save size={15} />{saving ? 'Creating trip…' : 'Create trip'}</button>}</div></footer>
    </section>;
}

function DriverLiveMap({ items, selectedId, onSelect }) {
    const containerRef = useRef(null);
    const mapRef = useRef(null);
    const markerLayerRef = useRef(null);
    const hasFitRef = useRef(false);

    useEffect(() => {
        if (!containerRef.current || mapRef.current) return undefined;

        const map = L.map(containerRef.current, {
            center: [20.15, 96.5],
            zoom: 6,
            minZoom: 4,
            maxZoom: 19,
            zoomControl: true,
        });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19,
        }).addTo(map);
        markerLayerRef.current = L.layerGroup().addTo(map);
        mapRef.current = map;
        const resizeFrame = window.requestAnimationFrame(() => map.invalidateSize());

        return () => {
            window.cancelAnimationFrame(resizeFrame);
            map.remove();
            mapRef.current = null;
            markerLayerRef.current = null;
            hasFitRef.current = false;
        };
    }, []);

    useEffect(() => {
        const map = mapRef.current;
        const markerLayer = markerLayerRef.current;
        if (!map || !markerLayer) return;

        markerLayer.clearLayers();
        const positions = items.filter((item) => Number.isFinite(Number(item.location?.latitude)) && Number.isFinite(Number(item.location?.longitude)));
        const bounds = L.latLngBounds([]);

        positions.forEach((item) => {
            const latitude = Number(item.location.latitude);
            const longitude = Number(item.location.longitude);
            const isSelected = item.id === selectedId;
            const color = item.tracking_state === 'live' ? '#168255' : item.tracking_state === 'stale' ? '#b77700' : '#64748b';
            const marker = L.circleMarker([latitude, longitude], {
                radius: isSelected ? 10 : 8,
                color: isSelected ? '#0787a8' : '#ffffff',
                weight: isSelected ? 4 : 2,
                fillColor: color,
                fillOpacity: 1,
                className: `delivery-driver-map-marker ${item.tracking_state}${isSelected ? ' is-selected' : ''}`,
            });
            const label = document.createElement('span');
            label.textContent = item.driver_code || item.driver_name;
            marker.bindTooltip(label, {
                permanent: true,
                direction: 'top',
                offset: [0, -8],
                className: `delivery-driver-map-label ${item.tracking_state}${isSelected ? ' is-selected' : ''}`,
            });
            marker.on('click', () => onSelect(item.id));
            marker.addTo(markerLayer);

            if (isSelected && Number(item.location.accuracy_m) > 0) {
                L.circle([latitude, longitude], {
                    radius: Number(item.location.accuracy_m),
                    color: '#0787a8',
                    weight: 1,
                    fillColor: '#0787a8',
                    fillOpacity: 0.08,
                    interactive: false,
                }).addTo(markerLayer);
            }
            bounds.extend([latitude, longitude]);
        });

        if (!positions.length || !bounds.isValid()) return;
        const visibleBounds = map.getBounds();
        const everyDriverVisible = positions.every((item) => visibleBounds.contains([Number(item.location.latitude), Number(item.location.longitude)]));
        if (!hasFitRef.current || !everyDriverVisible) {
            map.fitBounds(bounds, { padding: [48, 48], maxZoom: 15 });
            hasFitRef.current = true;
        } else {
            const selected = positions.find((item) => item.id === selectedId);
            if (selected) map.panInside([Number(selected.location.latitude), Number(selected.location.longitude)], { padding: [60, 60] });
        }
    }, [items, selectedId, onSelect]);

    return (
        <div className="delivery-leaflet-map-wrap">
            <div ref={containerRef} className="delivery-leaflet-map" aria-label="OpenStreetMap showing current driver locations" />
            {!items.length && <div className="delivery-map-empty"><MapPin size={24} /><strong>Waiting for a GPS update</strong><span>Active drivers appear here after location permission is granted.</span></div>}
        </div>
    );
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
    const selected = state.items.find((item) => item.id === selectedId) || null;
    const stateClass = (value) => value === 'live' ? 'success' : value === 'stale' ? 'warning' : 'neutral';
    const time = (value) => value ? new Date(value.replace(' ', 'T')).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' }) : '-';

    return <section className="page delivery-map-workspace"><div className="master-heading"><div><p className="eyebrow">{localized ? 'တိုက်ရိုက်လုပ်ငန်း' : 'Live operations'}</p><h1>{localized ? 'ယာဉ်မောင်း တိုက်ရိုက်မြေပုံ' : 'Driver live map'}</h1><span className="muted">{localized ? 'လမ်းပေါ်ရှိ ပို့ဆောင်မှုတိုင်း၏ နောက်ဆုံး GPS တည်နေရာ။' : 'Latest GPS position for every delivery currently on route.'}</span></div><ShellPageActions><button className="button" type="button" onClick={() => setRefresh((value) => value + 1)}><RefreshCw size={15} />{localized ? 'ပြန်လည်စစ်ဆေး' : 'Refresh'}</button></ShellPageActions></div>
        <div className="metrics delivery-metrics"><Metric label="On route" value={number(state.summary.active_count)} hint="Active trips" icon={Truck} /><Metric label="Live signal" value={number(state.summary.live_count)} hint="Updated within 5 min" icon={Navigation} /><Metric label="Stale signal" value={number(state.summary.stale_count)} hint="Update overdue" icon={MapPin} /><Metric label="Not reported" value={number(state.summary.unreported_count)} hint="Waiting for GPS" icon={MapPin} /></div>
        {state.loading && !state.items.length ? <State title="Loading live trip map" loading /> : state.error ? <State title={state.error} /> : !state.items.length ? <State title="No trips are currently on route." /> : <div className="delivery-live-layout"><section className="delivery-map-panel" aria-label="Live driver positions"><DriverLiveMap items={tracked} selectedId={selectedId} onSelect={setSelectedId} /><footer><span>OpenStreetMap live view · {tracked.length} driver{tracked.length === 1 ? '' : 's'} reporting</span><span>Auto-refresh: 15 seconds · {time(state.refreshed_at)}</span></footer></section>
            <aside className="delivery-live-sidebar"><div className="master-panel-heading"><div><p className="eyebrow">Active fleet</p><h2>{state.items.length} on route</h2></div></div><div className="delivery-driver-list">{state.items.map((item) => <button className={selectedId === item.id ? 'is-selected' : ''} type="button" key={item.id} onClick={() => setSelectedId(item.id)}><span className="mobile-order-icon"><Navigation size={15} /></span><span><strong>{item.driver_name}</strong><small>{item.code} · {item.plate_no}</small><small>{item.shop_name}</small></span><span className={`status ${stateClass(item.tracking_state)}`}>{item.tracking_state}</span></button>)}</div></aside>
            {selected && <section className="delivery-location-detail"><div className="master-panel-heading"><div><p className="eyebrow">Selected trip</p><h2>{selected.driver_name} · {selected.code}</h2></div><span className={`status ${stateClass(selected.tracking_state)}`}>{selected.tracking_state}</span></div><dl><Info label="Stops" value={`${selected.stops_count} · ${selected.shop_name}`} /><Info label="Vehicle" value={`${selected.vehicle_code} · ${selected.plate_no}`} /><Info label="Route" value={selected.route_name} /><Info label="Destinations" value={selected.delivery_address} /><Info label="Latitude" value={selected.location?.latitude?.toFixed(7)} /><Info label="Longitude" value={selected.location?.longitude?.toFixed(7)} /><Info label="Accuracy" value={selected.location?.accuracy_m !== null && selected.location ? `${number(selected.location.accuracy_m)} m` : '-'} /><Info label="Last update" value={time(selected.location?.recorded_at)} /></dl><h3>Recent GPS fixes</h3>{history.loading ? <State title="Loading location history" loading /> : history.error ? <p className="form-alert">{history.error}</p> : !history.locations.length ? <p className="muted delivery-history-empty">No location history reported.</p> : <div className="delivery-location-history">{history.locations.slice(0, 8).map((location) => <article key={location.id}><MapPin size={14} /><span><strong>{location.latitude.toFixed(6)}, {location.longitude.toFixed(6)}</strong><small>{time(location.recorded_at)} · {location.accuracy_m ? `${number(location.accuracy_m)} m accuracy` : 'Accuracy unavailable'}</small></span></article>)}</div>}</section>}
        </div>}
    </section>;
}

export function MobileDriverDeliveriesScreen({ locale = 'en' }) {
    const localized = locale === 'my';
    const mobileApi = () => window.ValleyRuntime?.api?.mobileDeliveries || '/api/mobile/deliveries';
    const [state, setState] = useState({ loading: true, items: [], summary: {}, error: '' });
    const [detail, setDetail] = useState({ loading: false, delivery: null, trip: null, stops: [], stock: [], error: '' });
    const [quantities, setQuantities] = useState({});
    const [notes, setNotes] = useState('');
    const [modificationNote, setModificationNote] = useState('');
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState('');
    const [refresh, setRefresh] = useState(0);

    useEffect(() => {
        let mounted = true;
        setState((current) => ({ ...current, loading: true, error: '' }));
        window.axios.get(mobileApi()).then(({ data }) => mounted && setState({ loading: false, items: data.data.items, summary: data.data.summary, error: '' })).catch((error) => mounted && setState({ loading: false, items: [], summary: {}, error: errorMessage(error) }));
        return () => { mounted = false; };
    }, [refresh]);

    const updateTripCard = (delivery, trip) => {
        const nextStatus = trip?.status || delivery?.status;
        setState((current) => {
            const matchesTrip = (item) => item.id === delivery?.id || (delivery?.trip_id && item.trip_id === delivery.trip_id);
            const previous = current.items.find(matchesTrip);
            if (!previous) return current;
            const previousStatus = previous.status;
            const items = current.items.map((item) => matchesTrip(item)
                ? { ...item, ...delivery, id: item.id, code: trip?.code || item.code, status: nextStatus, total_quantity: trip?.total_quantity ?? item.total_quantity }
                : item);
            const summary = { ...current.summary };
            if (previousStatus !== nextStatus) {
                if (previousStatus === 'assigned') summary.assigned_count = Math.max(0, Number(summary.assigned_count || 0) - 1);
                if (previousStatus === 'loading') summary.loading_count = Math.max(0, Number(summary.loading_count || 0) - 1);
                if (nextStatus === 'assigned') summary.assigned_count = Number(summary.assigned_count || 0) + 1;
                if (nextStatus === 'loading') summary.loading_count = Number(summary.loading_count || 0) + 1;
            }
            return { ...current, items, summary };
        });
    };

    const openDelivery = (id) => {
        setDetail({ loading: true, delivery: null, trip: null, stops: [], stock: [], error: '' }); setMessage('');
        window.axios.get(`${mobileApi()}/${id}`).then(({ data }) => {
            const items = (data.data.stops || []).flatMap((stop) => stop.items || []);
            setDetail({ loading: false, delivery: data.data.delivery, trip: data.data.trip, stops: data.data.stops || [], stock: data.data.stock || [], error: '' });
            setQuantities(Object.fromEntries(items.map((item) => [item.id, Number(item.loaded_quantity) || Number(item.planned_quantity)])));
            setNotes(data.data.delivery.notes || '');
        }).catch((error) => setDetail({ loading: false, delivery: null, trip: null, stops: [], stock: [], error: errorMessage(error) }));
    };

    const confirmLoad = () => {
        setSaving(true); setMessage('');
        const items = detail.stops.flatMap((stop) => (stop.items || []).map((item) => ({ id: item.id, loaded_quantity: quantities[item.id] ?? 0 })));
        window.axios.post(`${mobileApi()}/${detail.delivery.id}/confirm-loading`, { items, notes })
            .then(({ data }) => { setSaving(false); setMessage('Trip loading confirmed.'); setDetail((current) => ({ ...current, delivery: data.data.delivery, trip: data.data.trip, stops: data.data.stops, stock: data.data.stock })); updateTripCard(data.data.delivery, data.data.trip); })
            .catch((error) => { setSaving(false); setMessage(errorMessage(error)); });
    };

    if (detail.loading) return <MobileState title="Loading delivery" loading />;
    if (detail.error) return <MobileState title={detail.error} action={() => setDetail({ loading: false, delivery: null, trip: null, stops: [], stock: [], error: '' })} />;
    if (detail.delivery) {
        const tripStatus = detail.trip?.status || detail.delivery.status;
        const canLoad = ['assigned', 'loading'].includes(tripStatus);
        const loadedTotal = detail.stops.flatMap((stop) => stop.items || []).reduce((sum, item) => sum + Number(quantities[item.id] || 0), 0);
        return <div className="driver-delivery-detail"><ShellBackButton label="Back to trips" onClick={() => setDetail({ loading: false, delivery: null, trip: null, stops: [], stock: [], error: '' })} /><header className="mobile-section-heading"><div><p className="eyebrow">Assigned trip</p><h2>{detail.trip?.code || detail.delivery.trip_code || detail.delivery.code}</h2></div><span className={`status ${statusFamily(tripStatus)}`}>{statusLabel(tripStatus)}</span></header>
            <section className="driver-delivery-hero"><div><Truck size={22} /><span><strong>{detail.delivery.route_name}</strong><small>{detail.stops.length} stops · {number(detail.trip?.total_quantity)} units</small></span></div><dl><Info label="Planned" value={date(detail.trip?.planned_date)} /><Info label="Vehicle" value={`${detail.delivery.vehicle_code} · ${detail.delivery.plate_no}`} /><Info label="Driver" value={detail.delivery.driver_name} /><Info label="Warehouse" value={detail.delivery.warehouse_name} /></dl></section>
            <section className="driver-load-panel"><div className="mobile-section-heading"><div><p className="eyebrow">Combined stock</p><h2>{detail.stock.length} products</h2></div><strong>{number(loadedTotal)} / {number(detail.trip?.total_quantity)}</strong></div><div className="driver-load-summary">{detail.stock.map((item) => <span key={item.product_id}><strong>{item.product_name}</strong><small>{item.product_sku} · {number(item.planned_quantity)} {item.unit}</small></span>)}</div></section>
            <section className="driver-load-panel"><div className="mobile-section-heading"><div><p className="eyebrow">Stop allocation</p><h2>Confirm quantities by order</h2></div></div><div className="driver-trip-stops">{detail.stops.map((stop, index) => <article key={stop.id}><header><span className="delivery-stop-index">{index + 1}</span><span><strong>{stop.shop_name}</strong><small>{stop.order_code} · {stop.invoice_code}</small></span></header><div className="driver-load-items">{(stop.items || []).map((item) => <label key={item.id}><span><strong>{item.product_name}</strong><small>{item.product_sku} · Planned {number(item.planned_quantity)} {item.unit}</small></span><input aria-label={`Loaded quantity for ${stop.order_code} ${item.product_name}`} type="number" inputMode="numeric" min="0" max={item.planned_quantity} step="1" disabled={!canLoad} value={quantities[item.id] ?? 0} onChange={(event) => setQuantities((current) => ({ ...current, [item.id]: event.target.value }))} /></label>)}</div></article>)}</div><label className="driver-load-notes">Loading note<textarea disabled={!canLoad} value={notes} onChange={(event) => setNotes(event.target.value)} /></label>{message && <p className={message === 'Trip loading confirmed.' ? 'mobile-success-message' : 'form-alert'}>{message}</p>}{canLoad && <button className="button primary driver-load-action" type="button" disabled={saving} onClick={confirmLoad}><Package size={17} />{saving ? 'Saving…' : 'Confirm trip loading'}</button>}</section>
        </div>;
    }

    return <div className="driver-delivery-list"><header className="mobile-master-heading"><div><p className="eyebrow">{localized ? 'ယာဉ်မောင်း' : 'Driver'}</p><h1>{localized ? 'တာဝန်ပေးထားသော ခရီးစဉ်များ' : 'Assigned trips'}</h1><span className="muted">Review each route and confirm its complete warehouse load.</span></div><ShellPageActions><button className="icon-button" type="button" aria-label="Refresh trips" onClick={() => setRefresh((value) => value + 1)}><RefreshCw size={17} /></button></ShellPageActions></header><div className="mobile-credit-card"><span>Planned load</span><strong>{number(state.summary.total_quantity)}</strong><small>{number(state.summary.assigned_count)} awaiting load · {number(state.summary.loading_count)} loaded</small></div>{state.loading ? <MobileState title="Loading assigned trips" loading /> : state.error ? <MobileState title={state.error} action={() => setRefresh((value) => value + 1)} /> : !state.items.length ? <MobileState title="No trips are assigned." /> : <section className="mobile-master-section"><div className="mobile-section-heading"><div><p className="eyebrow">Schedule</p><h2>{number(state.summary.deliveries_count)} trips</h2></div></div><div className="mobile-delivery-cards">{state.items.map((item) => <button type="button" key={item.id} onClick={() => openDelivery(item.id)}><span className="mobile-order-icon"><Truck size={17} /></span><span><strong>{item.code}</strong><small>{item.route_name} · {date(item.planned_date)}</small><small>{item.stops_count} stops · {number(item.total_quantity)} units</small></span><span><span className={`status ${statusFamily(item.status)}`}>{statusLabel(item.status)}</span><ChevronRight size={16} /></span></button>)}</div></section>}</div>;
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

        return <div className="driver-delivery-detail delivery-status-detail"><ShellBackButton label="Back to deliveries" onClick={() => setDetail({ loading: false, delivery: null, items: [], error: '' })} /><header className="mobile-section-heading"><div><p className="eyebrow">Delivery tracking</p><h2>{detail.delivery.code}</h2></div><span className={`status ${statusFamily(detail.delivery.status)}`}>{statusLabel(detail.delivery.status)}</span></header>
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

    return <div className="driver-delivery-list"><header className="mobile-master-heading"><div><p className="eyebrow">{localized ? 'ပို့ဆောင်မှု အခြေအနေ' : 'Delivery status'}</p><h1>{pageTitle}</h1><span className="muted">{pageHint}</span></div><ShellPageActions><button className="icon-button" type="button" aria-label="Refresh delivery status" onClick={() => setRefresh((value) => value + 1)}><RefreshCw size={17} /></button></ShellPageActions></header><div className="mobile-delivery-stats" aria-label={localized ? 'ပို့ဆောင်မှု အနှစ်ချုပ်' : 'Delivery summary'}>
            <article className="is-active">
                <span className="mobile-delivery-stat-icon"><Truck size={16} /></span>
                <small>{localized ? 'လက်ရှိ' : 'Active'}</small>
                <strong>{number(state.summary.active_count)}</strong>
            </article>
            <article className="is-complete">
                <span className="mobile-delivery-stat-icon"><CheckCircle2 size={16} /></span>
                <small>{localized ? 'ပြီးဆုံး' : 'Completed'}</small>
                <strong>{number(state.summary.completed_count)}</strong>
            </article>
            <article className="is-units">
                <span className="mobile-delivery-stat-icon"><Package size={16} /></span>
                <small>{localized ? 'စုစုပေါင်းယူနစ်' : 'Total units'}</small>
                <strong>{number(state.summary.total_quantity)}</strong>
            </article>
        </div>{state.loading ? <MobileState title={localized ? 'ပို့ဆောင်မှုအခြေအနေ တင်နေသည်' : 'Loading delivery status'} loading /> : state.error ? <MobileState title={state.error} action={() => setRefresh((value) => value + 1)} /> : !state.items.length ? <MobileState title={localized ? 'ပို့ဆောင်မှု မရှိသေးပါ။' : 'No deliveries are available yet.'} /> : <section className="mobile-master-section"><div className="mobile-section-heading"><div><p className="eyebrow">{localized ? 'ပို့ဆောင်မှုမှတ်တမ်း' : 'Delivery history'}</p><h2>{number(state.summary.deliveries_count)} {localized ? 'ပို့ဆောင်မှု' : 'deliveries'}</h2></div></div><div className="mobile-delivery-cards">{state.items.map((item) => <button type="button" key={item.id} onClick={() => openDelivery(item.id)}><span className="mobile-order-icon"><Truck size={17} /></span><span><strong>{appId === 'client' ? item.code : item.shop_name}</strong><small>{appId === 'client' ? item.invoice_code : item.code} / {date(item.planned_date)}</small><small>{item.route_name} / {number(item.total_quantity)} {localized ? 'ယူနစ်' : 'units'}</small></span><span><span className={`status ${statusFamily(item.status)}`}>{statusLabel(item.status)}</span><ChevronRight size={16} /></span></button>)}</div></section>}</div>;
}

function DriverRouteStopsMap({ stops = [], position = null }) {
    const containerRef = useRef(null);
    const mapRef = useRef(null);
    const layerRef = useRef(null);
    const fittedStopsRef = useRef('');

    useEffect(() => {
        if (!containerRef.current || mapRef.current) return undefined;

        const map = L.map(containerRef.current, { center: [20.15, 96.5], zoom: 6, minZoom: 4, maxZoom: 19, zoomControl: true });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19,
        }).addTo(map);
        layerRef.current = L.layerGroup().addTo(map);
        mapRef.current = map;
        const resizeFrame = window.requestAnimationFrame(() => map.invalidateSize());

        return () => {
            window.cancelAnimationFrame(resizeFrame);
            map.remove();
            mapRef.current = null;
            layerRef.current = null;
            fittedStopsRef.current = '';
        };
    }, []);

    useEffect(() => {
        const map = mapRef.current;
        const layer = layerRef.current;
        if (!map || !layer) return;

        layer.clearLayers();
        const validStops = stops.filter((stop) => hasGpsCoordinate(stop.customer_latitude) && hasGpsCoordinate(stop.customer_longitude));
        const groupedStops = new Map();
        validStops.forEach((stop) => {
            const latitude = Number(stop.customer_latitude);
            const longitude = Number(stop.customer_longitude);
            const key = `${latitude.toFixed(7)},${longitude.toFixed(7)}`;
            if (!groupedStops.has(key)) groupedStops.set(key, { latitude, longitude, stops: [] });
            groupedStops.get(key).stops.push(stop);
        });
        const bounds = L.latLngBounds([]);

        groupedStops.forEach((group) => {
            const sequenceLabel = group.stops.map((stop) => Number(stop.stop_sequence || 1)).join(',');
            const marker = L.marker([group.latitude, group.longitude], {
                icon: L.divIcon({ className: 'driver-route-stop-marker', html: `<span><b>${sequenceLabel}</b></span>`, iconSize: [30, 36], iconAnchor: [15, 34], popupAnchor: [0, -32] }),
                title: group.stops.map((stop) => stop.shop_name).join(', '),
            });
            const popup = document.createElement('div');
            popup.className = 'driver-route-stop-popup';
            group.stops.forEach((stop) => {
                const row = document.createElement('div');
                const heading = document.createElement('strong');
                const detail = document.createElement('span');
                heading.textContent = `${stop.stop_sequence}. ${stop.shop_name}`;
                detail.textContent = `${stop.order_code || stop.code} / ${stop.delivery_address || 'No delivery address'}`;
                row.append(heading, detail);
                popup.append(row);
            });
            marker.bindPopup(popup, { maxWidth: 260 }).addTo(layer);
            bounds.extend([group.latitude, group.longitude]);
        });

        const hasDriverPosition = hasGpsCoordinate(position?.latitude) && hasGpsCoordinate(position?.longitude);
        if (hasDriverPosition) {
            const latitude = Number(position.latitude);
            const longitude = Number(position.longitude);
            L.circleMarker([latitude, longitude], { radius: 8, color: '#ffffff', weight: 3, fillColor: '#168255', fillOpacity: 1 })
                .bindTooltip('You', { permanent: true, direction: 'top', offset: [0, -7], className: 'driver-route-current-label' })
                .addTo(layer);
            if (Number(position.accuracy_m) > 0) L.circle([latitude, longitude], { radius: Number(position.accuracy_m), color: '#168255', weight: 1, fillColor: '#168255', fillOpacity: 0.08, interactive: false }).addTo(layer);
            bounds.extend([latitude, longitude]);
        }

        const stopsSignature = validStops.map((stop) => `${stop.id}:${stop.customer_latitude}:${stop.customer_longitude}`).join('|');
        const fitSignature = `${stopsSignature || 'no-stops'}|driver:${hasDriverPosition ? 'yes' : 'no'}`;
        if (bounds.isValid() && fittedStopsRef.current !== fitSignature) {
            map.fitBounds(bounds, { padding: [34, 34], maxZoom: 16 });
            fittedStopsRef.current = fitSignature;
        }
    }, [stops, position]);

    const hasLocation = stops.some((stop) => hasGpsCoordinate(stop.customer_latitude) && hasGpsCoordinate(stop.customer_longitude))
        || (hasGpsCoordinate(position?.latitude) && hasGpsCoordinate(position?.longitude));

    return <div className="driver-route-map-wrap"><div ref={containerRef} className="driver-route-leaflet-map" aria-label="OpenStreetMap showing the driver and GPS supported order stops" />{!hasLocation && <div className="driver-gps-map-empty"><MapPin size={28} /><strong>No GPS positions available</strong><span>Customer stops appear here after their GPS position is saved.</span></div>}</div>;
}

export function MobileDriverGpsScreen() {
    const mobileApi = () => window.ValleyRuntime?.api?.mobileDeliveries || '/api/mobile/deliveries';
    const [tripState, setTripState] = useState({ loading: true, trip: null, stops: [], error: '' });
    const [gps, setGps] = useState({ state: 'waiting', position: null, uploadedAt: null, error: '' });
    const lastUpload = useRef(0);

    useEffect(() => {
        let mounted = true;
        window.axios.get(mobileApi(), { params: { scope: 'tasks' } })
            .then(async ({ data }) => {
                if (!mounted) return;
                const trip = (data.data.items || []).find((item) => item.status === 'on_route') || null;
                if (!trip) {
                    setTripState({ loading: false, trip: null, stops: [], error: '' });
                    return;
                }
                const detailResponse = await window.axios.get(`${mobileApi()}/${trip.id}`);
                if (!mounted) return;
                setTripState({ loading: false, trip: { ...trip, ...(detailResponse.data.data.trip || {}) }, stops: detailResponse.data.data.stops || [], error: '' });
            })
            .catch((error) => mounted && setTripState({ loading: false, trip: null, stops: [], error: errorMessage(error) }));
        return () => { mounted = false; };
    }, []);

    useEffect(() => {
        if (!navigator.geolocation) {
            setGps((current) => ({ ...current, state: 'error', error: 'GPS is not available in this browser.' }));
            return undefined;
        }

        let active = true;
        let uploadInFlight = false;
        setGps((current) => ({ ...current, state: 'waiting', error: '' }));
        const watchId = navigator.geolocation.watchPosition((position) => {
            if (!active) return;
            const point = {
                latitude: position.coords.latitude,
                longitude: position.coords.longitude,
                accuracy_m: position.coords.accuracy,
                heading: position.coords.heading,
                speed_kmh: position.coords.speed === null ? null : position.coords.speed * 3.6,
                recorded_at: new Date(position.timestamp).toISOString(),
            };
            setGps((current) => ({ ...current, state: tripState.trip ? 'sharing' : 'ready', position: point, error: '' }));
            const now = Date.now();
            if (!tripState.trip || uploadInFlight || now - lastUpload.current < 15000) return;
            uploadInFlight = true;
            lastUpload.current = now;
            window.axios.post(`${mobileApi()}/${tripState.trip.id}/location`, point)
                .then(() => active && setGps((current) => ({ ...current, state: 'live', uploadedAt: new Date().toISOString(), error: '' })))
                .catch((error) => active && setGps((current) => ({ ...current, state: 'error', error: errorMessage(error) })))
                .finally(() => { uploadInFlight = false; });
        }, (error) => {
            if (active) setGps((current) => ({ ...current, state: 'error', error: error.code === 1 ? 'Location permission is required to show your live position.' : 'Current GPS position is unavailable.' }));
        }, { enableHighAccuracy: true, maximumAge: 5000, timeout: 20000 });

        return () => {
            active = false;
            navigator.geolocation.clearWatch(watchId);
        };
    }, [tripState.trip?.id]);

    const point = gps.position;
    const remainingStops = tripState.stops.filter((stop) => !['delivered', 'partially_delivered', 'failed'].includes(stop.status));
    const mappedStops = remainingStops.filter((stop) => hasGpsCoordinate(stop.customer_latitude) && hasGpsCoordinate(stop.customer_longitude));
    const statusLabel = gps.state === 'live' ? 'Live · sharing' : gps.state === 'sharing' ? 'GPS ready · sharing' : gps.state === 'ready' ? 'GPS ready' : gps.state === 'error' ? 'GPS unavailable' : 'Finding GPS…';
    const timeLabel = (value) => value ? new Date(value).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' }) : 'Waiting for first fix';

    return <div className="driver-gps-page">
        <header className="mobile-master-heading"><div><p className="eyebrow">Driver location</p><h1>Live GPS</h1><span className="muted">Your current position and route sharing status.</span></div><span className={`status ${gps.state === 'live' || gps.state === 'ready' ? 'success' : gps.state === 'error' ? 'danger' : 'info'}`}>{statusLabel}</span></header>
        {tripState.trip ? <section className="driver-gps-trip"><Navigation size={18} /><span><strong>{tripState.trip.code}</strong><small>{tripState.trip.route_name} · {tripState.trip.vehicle_code} {tripState.trip.plate_no}</small></span><span className="status info">On route</span></section> : <section className="driver-gps-trip is-idle"><MapPin size={18} /><span><strong>{tripState.loading ? 'Checking active trip…' : 'No active trip'}</strong><small>{tripState.error || 'Your current GPS position is still shown below.'}</small></span></section>}
        {gps.error && <p className="form-alert" role="status">{gps.error}</p>}
        <section className="driver-gps-map-panel" aria-label="Current route map">
            <header className="driver-gps-map-heading"><span><strong>Route map</strong><small>Tap a numbered marker to view a remaining order stop.</small></span><strong>{mappedStops.length} / {remainingStops.length} stops mapped</strong></header>
            <DriverRouteStopsMap stops={remainingStops} position={point} />
        </section>
        <section className="driver-gps-facts">
            <article><span>Latitude</span><strong>{point ? point.latitude.toFixed(6) : '—'}</strong></article>
            <article><span>Longitude</span><strong>{point ? point.longitude.toFixed(6) : '—'}</strong></article>
            <article><span>Accuracy</span><strong>{point ? `${Math.round(point.accuracy_m)} m` : '—'}</strong></article>
            <article><span>Last GPS fix</span><strong>{timeLabel(point?.recorded_at)}</strong></article>
        </section>
        {tripState.trip && <p className="driver-gps-share-note">Location is sent to the office every 15 seconds while this GPS view is open. Last sent: {timeLabel(gps.uploadedAt)}.</p>}
        {point && <a className="button driver-gps-open-map" href={`https://www.openstreetmap.org/?mlat=${point.latitude}&mlon=${point.longitude}#map=16/${point.latitude}/${point.longitude}`} target="_blank" rel="noreferrer"><MapPin size={16} />Open current position in OpenStreetMap</a>}
    </div>;
}

export function MobileDriverExecutionScreen({ mode, locale = 'en' }) {
    const historyMode = mode === 'history';
    const mobileApi = () => window.ValleyRuntime?.api?.mobileDeliveries || '/api/mobile/deliveries';
    const emptyHistoryFilters = () => ({ search: '', status: '', date_from: '', date_to: '', route_id: '', vehicle_id: '' });
    const [state, setState] = useState({ loading: true, items: [], error: '' });
    const [historyFilters, setHistoryFilters] = useState(emptyHistoryFilters);
    const [detail, setDetail] = useState({ loading: false, delivery: null, trip: null, stops: [], stock: [], availableProducts: [], retryId: null, error: '' });
    const [selectedStopId, setSelectedStopId] = useState(null);
    const [tripCloseoutPage, setTripCloseoutPage] = useState(false);
    const [resultStatus, setResultStatus] = useState('delivered');
    const [results, setResults] = useState({});
    const [finalLines, setFinalLines] = useState([]);
    const [notes, setNotes] = useState('');
    const [modificationNote, setModificationNote] = useState('');
    const [settlement, setSettlement] = useState({ method: 'credit', reference: '' });
    const [orderDiscount, setOrderDiscount] = useState(0);
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState('');
    const [sharing, setSharing] = useState({ state: 'idle', message: '' });
    const [refresh, setRefresh] = useState(0);
    const lastLocationShare = useRef(0);
    const historyRoutes = Array.from(new Map(state.items.filter((item) => item.route_id).map((item) => [String(item.route_id), { id: item.route_id, label: `${item.route_code || ''}${item.route_code ? ' / ' : ''}${item.route_name || '-'}` }])).values()).sort((a, b) => a.label.localeCompare(b.label));
    const historyVehicles = Array.from(new Map(state.items.filter((item) => item.vehicle_id).map((item) => [String(item.vehicle_id), { id: item.vehicle_id, label: `${item.vehicle_code || ''}${item.vehicle_code ? ' / ' : ''}${item.plate_no || '-'}` }])).values()).sort((a, b) => a.label.localeCompare(b.label));
    const normalizedHistorySearch = historyFilters.search.trim().toLowerCase();
    const visibleItems = historyMode ? state.items.filter((item) => {
        const searchable = [item.code, item.trip_code, item.customer_summary, item.route_code, item.route_name, item.vehicle_code, item.plate_no].filter(Boolean).join(' ').toLowerCase();
        if (normalizedHistorySearch && !searchable.includes(normalizedHistorySearch)) return false;
        if (historyFilters.status && item.status !== historyFilters.status) return false;
        if (historyFilters.date_from && item.planned_date < historyFilters.date_from) return false;
        if (historyFilters.date_to && item.planned_date > historyFilters.date_to) return false;
        if (historyFilters.route_id && String(item.route_id) !== historyFilters.route_id) return false;
        if (historyFilters.vehicle_id && String(item.vehicle_id) !== historyFilters.vehicle_id) return false;
        return true;
    }) : state.items;

    useEffect(() => {
        driverTrace('screen:mounted', { traceVersion: '2026-09-15.1', mode, path: window.location.pathname });
    }, [mode]);

    useEffect(() => {
        let mounted = true;
        const scope = historyMode ? 'history' : 'tasks';
        const url = mobileApi();
        setState((current) => ({ ...current, loading: true, error: '' }));
        driverTrace('tasks:list:request', { scope, url });
        window.axios.get(url, { params: { scope } })
            .then((response) => {
                const items = response.data.data.items;
                driverTrace('tasks:list:success', {
                    scope,
                    status: response.status,
                    count: items.length,
                    tasks: items.map((item) => ({ id: item.id, code: item.code, status: item.status })),
                });
                if (mounted) setState({ loading: false, items, error: '' });
            })
            .catch((error) => {
                driverTraceError('tasks:list:error', error, { scope });
                if (mounted) setState({ loading: false, items: [], error: errorMessage(error) });
            });
        return () => { mounted = false; };
    }, [refresh, historyMode]);

    const updateTaskCard = (delivery, trip) => {
        if (!delivery) return;
        setState((current) => ({
            ...current,
            items: current.items.map((item) => item.id === delivery.id || (delivery.trip_id && item.trip_id === delivery.trip_id)
                ? { ...item, ...delivery, id: item.id, code: trip?.code || item.code, status: trip?.status || delivery.status, total_quantity: trip?.total_quantity ?? item.total_quantity }
                : item),
        }));
    };

    const removeTaskCard = (delivery) => {
        setState((current) => ({ ...current, items: current.items.filter((item) => item.id !== delivery.id && (!delivery.trip_id || item.trip_id !== delivery.trip_id)) }));
    };

    const updateStopCustomerLocation = (customerId, location) => {
        setDetail((current) => ({
            ...current,
            stops: current.stops.map((stop) => Number(stop.customer_id) === Number(customerId) ? {
                ...stop,
                customer_latitude: location.latitude,
                customer_longitude: location.longitude,
                customer_gps_accuracy_m: location.accuracy_m,
                customer_gps_captured_at: location.captured_at,
            } : stop),
        }));
    };

    useEffect(() => {
        if (historyMode || (detail.trip?.status || detail.delivery?.status) !== 'on_route') {
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
    }, [historyMode, detail.delivery?.id, detail.delivery?.status, detail.trip?.status]);

    const open = (id) => {
        const url = `${mobileApi()}/${id}`;
        setDetail({ loading: true, delivery: null, trip: null, stops: [], stock: [], availableProducts: [], retryId: id, error: '' }); setMessage('');
        driverTrace('trip-detail:request', { deliveryId: id, url });
        window.axios.get(url).then((response) => {
            const { data } = response;
            const stops = data.data.stops || [];
            driverTrace('trip-detail:success', {
                status: response.status,
                deliveryId: data.data.delivery?.id || id,
                tripId: data.data.trip?.id || null,
                tripCode: data.data.trip?.code || data.data.delivery?.trip_code || data.data.delivery?.code || null,
                tripStatus: data.data.trip?.status || data.data.delivery?.status || null,
                stopCount: stops.length,
                stops: stops.map((stop) => ({ id: stop.id, code: stop.order_code, status: stop.status, itemCount: (stop.items || []).length })),
            });
            setDetail({ loading: false, delivery: data.data.delivery, trip: data.data.trip, stops, stock: data.data.stock || [], availableProducts: data.data.available_products || [], retryId: null, error: '' });
            setSelectedStopId(null);
            setTripCloseoutPage(false);
            setResults({});
            setFinalLines([]);
            setOrderDiscount(0);
            setResultStatus('delivered');
            setSettlement({ method: 'credit', reference: '' });
            setModificationNote('');
            setNotes(data.data.delivery.notes || '');
        }).catch((error) => {
            const failureStage = error?.response ? 'trip-detail:http-error' : error?.config ? 'trip-detail:network-error' : 'trip-detail:processing-error';
            const displayError = failureStage === 'trip-detail:processing-error'
                ? `Trip detail could not be displayed: ${error?.message || 'Unknown client error.'}`
                : errorMessage(error);
            driverTraceError(failureStage, error, { deliveryId: id, requestUrl: url });
            setDetail({ loading: false, delivery: null, trip: null, stops: [], stock: [], availableProducts: [], retryId: id, error: displayError });
        });
    };

    const selectStop = (stop) => {
        driverTrace('stop-item:selected', {
            deliveryId: detail.delivery?.id || null,
            tripId: detail.trip?.id || null,
            stopId: stop.id,
            orderCode: stop.order_code,
            status: stop.status,
            itemCount: (stop.items || []).length,
            items: (stop.items || []).map((item) => ({ id: item.id, productId: item.product_id, sku: item.product_sku, type: item.item_type, planned: item.planned_quantity, loaded: item.loaded_quantity })),
        });
        setSelectedStopId(stop.id); setMessage(''); setNotes(stop.notes || '');
        setModificationNote(stop.order_modification_note || '');
        setResultStatus(stop.status === 'on_route' ? 'delivered' : stop.status);
        setSettlement({ method: stop.settlement_method || (stop.order_payment_type === 'cash' ? 'cash_driver' : 'credit'), reference: '' });
        setResults(Object.fromEntries((stop.items || []).map((item) => [item.id, { delivered_quantity: item.delivered_quantity || item.loaded_quantity, returned_quantity: item.returned_quantity || 0, damaged_quantity: item.damaged_quantity || 0 }])));
        setFinalLines(buildFinalSaleRows(stop, detail.availableProducts));
        setOrderDiscount((stop.items || []).filter((item) => (item.item_type || (Number(item.unit_price) > 0 ? 'sale' : 'foc')) === 'sale').reduce((sum, item) => sum + Number(item.discount_amount || 0), 0));
    };

    const startRoute = () => {
        setSaving(true); setMessage('');
        window.axios.post(`${mobileApi()}/${detail.delivery.id}/start-route`).then(({ data }) => { setSaving(false); setMessage('Trip started.'); setDetail((current) => ({ ...current, delivery: data.data.delivery, trip: data.data.trip, stops: data.data.stops })); updateTaskCard(data.data.delivery, data.data.trip); }).catch((error) => { setSaving(false); setMessage(errorMessage(error)); });
    };

    const confirmLoad = () => {
        setSaving(true); setMessage('');
        window.axios.post(`${mobileApi()}/${detail.delivery.id}/confirm-loading`, { notes }).then(({ data }) => {
            setSaving(false); setMessage('Load received.'); setDetail((current) => ({ ...current, delivery: data.data.delivery, trip: data.data.trip, stops: data.data.stops })); updateTaskCard(data.data.delivery, data.data.trip);
        }).catch((error) => { setSaving(false); setMessage(errorMessage(error)); });
    };

    const complete = () => {
        const stop = detail.stops.find((item) => item.id === selectedStopId);
        if (!stop) return;
        setSaving(true); setMessage('');
        const items = resultStatus === 'failed' ? [] : finalLines.flatMap((item) => {
            const saleQuantity = Number(item.sale_quantity || 0);
            const focQuantity = Number(item.foc_quantity || 0);
            const damagedQuantity = Number(item.damaged_quantity || 0);
            const saleId = item.sale_id || (focQuantity <= 0 ? item.foc_id : null);
            const focId = item.foc_id || (saleQuantity <= 0 ? item.sale_id : null);
            const lines = [];
            if (saleQuantity > 0 || (damagedQuantity > 0 && focQuantity <= 0)) lines.push({ id: saleId || null, product_id: item.product_id, item_type: 'sale', unit_price: item.unit_price, discount_amount: 0, delivered_quantity: saleQuantity, damaged_quantity: damagedQuantity });
            if (focQuantity > 0) lines.push({ id: focId || null, product_id: item.product_id, item_type: 'foc', unit_price: 0, discount_amount: 0, delivered_quantity: focQuantity, damaged_quantity: saleQuantity > 0 ? 0 : damagedQuantity });
            return lines;
        });
        const saleSubtotal = finalLines.reduce((sum, item) => sum + Number(item.sale_quantity || 0) * Number(item.unit_price || 0), 0);
        const appliedOrderDiscount = resultStatus === 'failed' ? 0 : Math.min(Math.max(Number(orderDiscount || 0), 0), saleSubtotal);
        window.axios.post(`${mobileApi()}/${stop.id}/complete`, { final_sale: true, status: resultStatus, items, order_discount: appliedOrderDiscount, notes, modification_note: modificationNote || null, settlement_method: resultStatus === 'failed' ? null : settlement.method, payment_reference: settlement.reference || null }).then(({ data }) => {
            const stops = data.data.stops || [];
            if (!data.data.delivery.trip_id && ['delivered', 'partially_delivered', 'failed'].includes(data.data.delivery.status)) {
                setSaving(false); removeTaskCard(data.data.delivery); setDetail({ loading: false, delivery: null, trip: null, stops: [], stock: [], availableProducts: [], error: '' });
                return;
            }
            setSaving(false); setMessage('Stop result saved.'); setDetail((current) => ({ ...current, delivery: data.data.delivery, trip: data.data.trip, stops, stock: data.data.stock || current.stock || [], availableProducts: data.data.available_products || current.availableProducts }));
            setSelectedStopId(null);
            updateTaskCard(data.data.delivery, data.data.trip);
        }).catch((error) => { setSaving(false); setMessage(errorMessage(error)); });
    };

    const completeTrip = () => {
        setSaving(true); setMessage('');
        window.axios.post(`${mobileApi()}/${detail.delivery.id}/complete-trip`).then(({ data }) => {
            setSaving(false); removeTaskCard(data.data.delivery); setDetail({ loading: false, delivery: null, trip: null, stops: [], stock: [], availableProducts: [], error: '' });
        }).catch((error) => { setSaving(false); setMessage(errorMessage(error)); });
    };

    if (detail.loading) return <MobileState title="Loading delivery" loading />;
    if (detail.error) return <MobileState title={detail.error} action={() => detail.retryId && open(detail.retryId)} />;
    if (detail.delivery) {
        const tripStatus = detail.trip?.status || detail.delivery.status;
        const selectedStop = selectedStopId === null ? null : detail.stops.find((item) => item.id === selectedStopId) || null;
        const active = selectedStop?.status === 'on_route';
        const allStopsComplete = detail.stops.length > 0 && detail.stops.every((stop) => ['delivered', 'partially_delivered', 'failed'].includes(stop.status));
        const plannedTotal = detail.stops.reduce((sum, stop) => sum + Number(stop.total_quantity || 0), 0);
        const loadedTotal = detail.stops.reduce((sum, stop) => sum + Number(stop.loaded_quantity || 0), 0);
        const tripReturnStock = (detail.stock || []).filter((item) => Number(item.returned_quantity || 0) > 0.001);
        const productsToLoad = Array.from(detail.stops.reduce((products, stop) => {
            (stop.items || []).forEach((item) => {
                const key = String(item.product_id || item.product_sku || item.id);
                const current = products.get(key) || { product_id: key, product_name: item.product_name, product_sku: item.product_sku, unit: item.unit, planned_quantity: 0 };
                current.planned_quantity += Number(item.planned_quantity || 0);
                products.set(key, current);
            });
            return products;
        }, new Map()).values());
        if (selectedStop) {
            return <DriverStopPage stop={selectedStop} delivery={detail.delivery} trip={detail.trip} availableProducts={detail.availableProducts} historyMode={historyMode} active={active} resultStatus={resultStatus} setResultStatus={setResultStatus} results={results} setResults={setResults} finalLines={finalLines} setFinalLines={setFinalLines} orderDiscount={orderDiscount} setOrderDiscount={setOrderDiscount} notes={notes} setNotes={setNotes} modificationNote={modificationNote} setModificationNote={setModificationNote} settlement={settlement} setSettlement={setSettlement} message={message} saving={saving} onSave={complete} onCustomerLocationUpdated={(location) => updateStopCustomerLocation(selectedStop.customer_id, location)} onBack={() => !saving && setSelectedStopId(null)} />;
        }
        if (tripCloseoutPage && !historyMode) {
            const cashCollections = detail.trip?.cash_hold_collections || [];
            return <div className="driver-delivery-detail driver-trip-closeout-page">
                <ShellBackButton label="Back to trip" disabled={saving} onClick={() => !saving && setTripCloseoutPage(false)} />
                <header className="mobile-section-heading"><div><p className="eyebrow">Trip closeout</p><h2>{detail.trip?.code || detail.delivery.trip_code || detail.delivery.code}</h2></div><span className={`status ${statusFamily(tripStatus)}`}>{statusLabel(tripStatus)}</span></header>
                <section className="driver-delivery-hero driver-closeout-summary"><div><CheckCircle2 size={22} /><span><strong>All route stops are complete</strong><small>Review the cash and stock held for this trip before returning to the office.</small></span></div><dl><Info label="Stops completed" value={`${detail.stops.length} / ${detail.stops.length}`} /><Info label="Planned date" value={date(detail.trip?.planned_date || detail.delivery.planned_date)} /></dl></section>
                <section className="driver-load-panel driver-trip-cash-hold">
                    <div className="mobile-section-heading"><div><p className="eyebrow">Cash handover</p><h2>Cash held for this trip</h2><small>Submitted cash collections awaiting office review.</small></div><strong>{money(detail.trip?.cash_hold_amount || 0)}</strong></div>
                    {cashCollections.length ? <div className="driver-load-summary">{cashCollections.map((item) => <span key={item.id}><span><strong>{item.customer_name}</strong><small>{item.code} · {item.delivery_code}</small></span><strong>{money(item.amount)}</strong></span>)}</div> : <p className="driver-trip-no-return">No cash is currently held for this trip.</p>}
                </section>
                <section className="driver-load-panel driver-trip-return-confirmation">
                    <div className="mobile-section-heading"><div><p className="eyebrow">Trip buffer stock</p><h2>Return stock to office</h2><small>Confirming adds these remaining quantities back to warehouse stock.</small></div><strong>{number(tripReturnStock.reduce((sum, item) => sum + Number(item.returned_quantity || 0), 0))} units</strong></div>
                    {tripReturnStock.length ? <div className="driver-load-summary">{tripReturnStock.map((item) => <span key={item.product_id}><span><strong>{item.product_name}</strong><small>{item.product_sku} · {item.unit}</small></span><strong>{number(item.returned_quantity)}</strong></span>)}</div> : <p className="driver-trip-no-return">No stock remains to return.</p>}
                </section>
                {message && <p className={message === 'Trip completed.' ? 'mobile-success-message' : 'form-alert'}>{message}</p>}
                <div className="driver-trip-closeout-actions"><button className="button primary driver-load-action" type="button" disabled={saving} onClick={completeTrip}><CheckCircle2 size={17} />{saving ? 'Confirming return…' : 'Confirm return stock & end trip'}</button></div>
            </div>;
        }
        return <div className="driver-delivery-detail"><ShellBackButton label="Back" onClick={() => setDetail({ loading: false, delivery: null, trip: null, stops: [], error: '' })} /><header className="mobile-section-heading"><div><p className="eyebrow">{historyMode ? 'Task history' : 'Driver task'}</p><h2>{detail.trip?.code || detail.delivery.trip_code || detail.delivery.code}</h2></div><span className={`status ${statusFamily(tripStatus)}`}>{statusLabel(tripStatus)}</span></header>{tripStatus !== 'assigned' && <section className="driver-delivery-hero"><div><Navigation size={22} /><span><strong>{detail.delivery.route_name}</strong><small>{detail.stops.length} stops · {detail.delivery.plate_no}</small></span></div><dl><Info label="Planned" value={date(detail.trip?.planned_date || detail.delivery.planned_date)} /><Info label="Warehouse" value={detail.delivery.warehouse_name} /><Info label="Vehicle" value={`${detail.delivery.vehicle_code} · ${detail.delivery.plate_no}`} /><Info label="Quantity" value={number(historyMode ? loadedTotal : plannedTotal)} /></dl></section>}
            {tripStatus === 'assigned' && <><section className="driver-load-panel"><div className="mobile-section-heading"><div><p className="eyebrow">Load list</p><h2>Products to load</h2></div><strong>{number(plannedTotal)} units</strong></div><div className="driver-load-summary driver-assigned-product-list">{productsToLoad.map((item) => <span key={item.product_id}><span><strong>{item.product_name}</strong><small>{item.product_sku} · {item.unit}</small></span><strong>{number(item.planned_quantity)}</strong></span>)}</div></section><section className="driver-load-panel"><div className="mobile-section-heading"><div><p className="eyebrow">Route</p><h2>Stop list</h2></div><strong>{detail.stops.length} stops</strong></div><div className="driver-trip-stops driver-assigned-stop-list">{detail.stops.map((stop, index) => <article key={stop.id}><header><span className="delivery-stop-index">{index + 1}</span><span><strong>{stop.shop_name}</strong><small>{stop.order_code} · {stop.delivery_address || 'No address'}</small></span><strong>{number(stop.total_quantity)}</strong></header></article>)}</div>{message && <p className={message === 'Load received.' ? 'mobile-success-message' : 'form-alert'}>{message}</p>}<button className="button primary driver-load-action" type="button" disabled={saving} onClick={confirmLoad}><Package size={17} />{saving ? 'Confirming…' : 'Confirm load received'}</button></section></>}
            {tripStatus === 'loading' && <section className="driver-load-panel"><div className="mobile-section-heading"><div><p className="eyebrow">Ready</p><h2>Load received</h2><small>Your next action is to start this trip.</small></div><strong>{number(loadedTotal)} units</strong></div><div className="driver-route-summary"><Info label="Warehouse" value={detail.delivery.warehouse_name} /><Info label="Vehicle" value={`${detail.delivery.vehicle_code} · ${detail.delivery.plate_no}`} /><Info label="Route" value={detail.delivery.route_name} /><Info label="Stops" value={detail.stops.length} /></div>{message && <p className={['Load received.', 'Trip started.'].includes(message) ? 'mobile-success-message' : 'form-alert'}>{message}</p>}<button className="button primary driver-load-action" type="button" disabled={saving} onClick={startRoute}><Navigation size={17} />{saving ? 'Starting…' : 'Start trip'}</button></section>}
            {(tripStatus === 'on_route' || historyMode) && <>
                <section className="driver-load-panel">
                    <div className="mobile-section-heading"><div><p className="eyebrow">{historyMode ? 'Completed task' : 'In progress'}</p><h2>{historyMode ? 'Trip result' : 'Current route'}</h2></div><strong>{number(detail.stops.filter((stop) => ['delivered', 'partially_delivered', 'failed'].includes(stop.status)).length)} / {detail.stops.length} stops</strong></div>
                    {!historyMode && <div className={`driver-location-status ${sharing.state}`}><Navigation size={17} /><span><strong>Live location</strong><small>{sharing.message || 'GPS sharing starts automatically during the trip.'}</small></span></div>}
                </section>
                {!historyMode && allStopsComplete && tripStatus === 'on_route' && <button className="button primary driver-load-action driver-trip-closeout-link" type="button" onClick={() => { setMessage(''); setTripCloseoutPage(true); }}>Review return stock & cash <ChevronRight size={17} /></button>}
                <TripStopList stops={detail.stops} selectedId={selectedStopId} onSelect={selectStop} />
                {message && <p className={message === 'Stop result saved.' ? 'mobile-success-message' : 'form-alert'}>{message}</p>}
            </>}
        </div>;
    }

    return <div className="driver-delivery-list">
        <header className="mobile-master-heading">
            <div><p className="eyebrow">Driver</p><h1>{historyMode ? 'Task history' : 'Tasks'}</h1><span className="muted">{historyMode ? 'Review your completed delivery routes and results.' : 'Receive, start, and complete your assigned route in one place.'}</span></div>
            <ShellPageActions><button className="icon-button" type="button" aria-label="Refresh" onClick={() => setRefresh((value) => value + 1)}><RefreshCw size={17} /></button></ShellPageActions>
        </header>
        {historyMode && <div className="mobile-order-filters driver-history-filters">
            <label className="mobile-search"><Search size={16} /><input type="search" value={historyFilters.search} placeholder="Search trip, customer, route or vehicle" aria-label="Search driver history" onChange={(event) => setHistoryFilters((current) => ({ ...current, search: event.target.value }))} /></label>
            <label className="mobile-order-filter-field"><span>Result status</span><select value={historyFilters.status} aria-label="Trip result status" onChange={(event) => setHistoryFilters((current) => ({ ...current, status: event.target.value }))}><option value="">All results</option><option value="delivered">Delivered</option><option value="partially_delivered">Partially delivered</option><option value="failed">Failed</option></select></label>
            <label className="mobile-order-filter-field"><span>Planned from</span><input type="date" value={historyFilters.date_from} max={historyFilters.date_to || undefined} aria-label="Planned date from" onChange={(event) => setHistoryFilters((current) => ({ ...current, date_from: event.target.value }))} /></label>
            <label className="mobile-order-filter-field"><span>Planned through</span><input type="date" value={historyFilters.date_to} min={historyFilters.date_from || undefined} aria-label="Planned date through" onChange={(event) => setHistoryFilters((current) => ({ ...current, date_to: event.target.value }))} /></label>
            <label className="mobile-order-filter-field"><span>Route</span><select value={historyFilters.route_id} aria-label="Route" onChange={(event) => setHistoryFilters((current) => ({ ...current, route_id: event.target.value }))}><option value="">All routes</option>{historyRoutes.map((item) => <option value={item.id} key={item.id}>{item.label}</option>)}</select></label>
            <label className="mobile-order-filter-field"><span>Vehicle</span><select value={historyFilters.vehicle_id} aria-label="Vehicle" onChange={(event) => setHistoryFilters((current) => ({ ...current, vehicle_id: event.target.value }))}><option value="">All vehicles</option>{historyVehicles.map((item) => <option value={item.id} key={item.id}>{item.label}</option>)}</select></label>
            <button className="button" type="button" onClick={() => setHistoryFilters(emptyHistoryFilters())}>Clear filters</button>
        </div>}
        {state.loading ? <MobileState title={historyMode ? 'Loading task history' : 'Loading tasks'} loading /> : state.error ? <MobileState title={state.error} action={() => setRefresh((value) => value + 1)} /> : !visibleItems.length ? <MobileState title={historyMode && state.items.length ? 'No trips match these filters.' : historyMode ? 'No completed tasks yet.' : 'No active or assigned tasks.'} /> : <section className="mobile-master-section">
            {historyMode && <div className="mobile-section-heading"><div><h2>Completed trips</h2><small>{visibleItems.length === state.items.length ? `${visibleItems.length} trips` : `${visibleItems.length} of ${state.items.length} trips`}</small></div></div>}
            <div className="mobile-delivery-cards">{visibleItems.map((item) => <button type="button" key={item.id} onClick={() => open(item.id)}><span className="mobile-order-icon">{historyMode ? <CheckCircle2 size={17} /> : <Navigation size={17} />}</span><span><strong>{item.code}</strong><small>{item.route_name} / {item.stops_count} stops</small><small>{historyMode ? `${number(item.completed_stops)} completed / ${number(item.delivered_quantity)} delivered` : item.status === 'assigned' ? `${number(item.total_quantity)} units to receive` : `${number(item.completed_stops)} completed / ${number(item.loaded_quantity)} loaded`}</small></span><span><span className={`status ${statusFamily(item.status)}`}>{statusLabel(item.status)}</span><ChevronRight size={16} /></span></button>)}</div>
        </section>}
    </div>;
}

function DriverStopPage({ stop, delivery, trip, availableProducts = [], historyMode, active, resultStatus, setResultStatus, results, setResults, finalLines, setFinalLines, orderDiscount, setOrderDiscount, notes, setNotes, modificationNote, setModificationNote, settlement, setSettlement, message, saving, onSave, onCustomerLocationUpdated, onBack }) {
    const [printProfile, setPrintProfile] = useState({ company: null, setting: null });
    const [customerLocation, setCustomerLocation] = useState(null);
    const [customerLocationDraft, setCustomerLocationDraft] = useState(null);
    const [customerLocationState, setCustomerLocationState] = useState({ locating: false, saving: false, message: '', error: '' });
    useEffect(() => {
        let mounted = true;
        window.axios.get(window.ValleyRuntime?.api?.printSettings || '/api/settings/printing')
            .then(({ data }) => mounted && setPrintProfile({ company: data.data.company, setting: data.data.documents?.delivery_voucher || null }))
            .catch(() => {});
        return () => { mounted = false; };
    }, []);
    useEffect(() => {
        setCustomerLocation(hasGpsCoordinate(stop.customer_latitude) && hasGpsCoordinate(stop.customer_longitude) ? {
            latitude: Number(stop.customer_latitude),
            longitude: Number(stop.customer_longitude),
            accuracy_m: [null, undefined, ''].includes(stop.customer_gps_accuracy_m) ? null : Number(stop.customer_gps_accuracy_m),
            captured_at: stop.customer_gps_captured_at || null,
        } : null);
        setCustomerLocationDraft(null);
        setCustomerLocationState({ locating: false, saving: false, message: '', error: '' });
    }, [stop.id]);
    const [addProductId, setAddProductId] = useState('');
    const finalSaleMode = active && !historyMode;
    const originalLines = useMemo(() => {
        const grouped = {};
        (stop.items || []).forEach((item) => {
            const key = `${item.product_id}:${item.item_type || (Number(item.unit_price) > 0 ? 'sale' : 'foc')}`;
            const current = grouped[key] || { quantity: 0, unit_price: 0, discount_amount: 0 };
            current.quantity = Number(current.quantity) + Number(item.planned_quantity || 0);
            current.unit_price = Number(item.unit_price || 0);
            current.discount_amount = Number(current.discount_amount) + Number(item.discount_amount || 0);
            grouped[key] = current;
        });
        return grouped;
    }, [stop.items]);
    const currentLines = useMemo(() => {
        const grouped = {};
        finalLines.forEach((item) => {
            grouped[`${item.product_id}:sale`] = { quantity: Number(item.sale_quantity || 0), unit_price: Number(item.unit_price || 0), discount_amount: Number(item.discount_amount || 0) };
            grouped[`${item.product_id}:foc`] = { quantity: Number(item.foc_quantity || 0), unit_price: 0, discount_amount: 0 };
        });
        return grouped;
    }, [finalLines]);
    const normalizedOriginal = Object.entries(originalLines).filter(([, item]) => Number(item.quantity) > 0).sort();
    const normalizedCurrent = Object.entries(currentLines).filter(([, item]) => Number(item.quantity) > 0).sort();
    const originalOrderDiscount = (stop.items || []).filter((item) => (item.item_type || (Number(item.unit_price) > 0 ? 'sale' : 'foc')) === 'sale').reduce((sum, item) => sum + Number(item.discount_amount || 0), 0);
    const saleSubtotal = finalLines.reduce((sum, item) => sum + Number(item.sale_quantity || 0) * Number(item.unit_price || 0), 0);
    const appliedOrderDiscount = Math.min(Math.max(Number(orderDiscount || 0), 0), saleSubtotal);
    const estimatedPayable = Math.max(saleSubtotal - appliedOrderDiscount, 0);
    const isModified = finalSaleMode && (JSON.stringify(normalizedOriginal) !== JSON.stringify(normalizedCurrent) || Math.abs(originalOrderDiscount - Number(orderDiscount || 0)) > 0.001);
    const focQuantity = finalLines.reduce((sum, item) => sum + Number(item.foc_quantity || 0), 0);

    useEffect(() => {
        driverTrace('stop-detail:render', {
            deliveryId: delivery.id,
            tripId: trip?.id || null,
            stopId: stop.id,
            orderCode: stop.order_code,
            status: stop.status,
            active,
            historyMode,
            itemCount: (stop.items || []).length,
            finalLineCount: finalLines.length,
        });
    }, [active, delivery.id, finalLines.length, historyMode, stop.id, stop.items, stop.order_code, stop.status, trip?.id]);

    const addLine = () => {
        const product = availableProducts.find((item) => String(item.id) === String(addProductId));
        if (!product) return;
        if (finalLines.some((item) => Number(item.product_id) === Number(product.id))) return;
        setFinalLines((current) => [...current, { key: `new-${product.id}-${Date.now()}`, product_id: product.id, product_name: product.name, product_sku: product.sku, unit: product.unit, sale_id: null, foc_id: null, sale_quantity: 1, foc_quantity: 0, unit_price: Number(product.unit_price || 0), discount_amount: 0, damaged_quantity: 0, loaded_quantity: Number(product.loaded_quantity || 0) }]);
        setAddProductId('');
    };
    const updateLine = (key, field, value) => setFinalLines((current) => current.map((item) => item.key === key ? { ...item, [field]: value } : item));
    const submit = (event) => {
        event.preventDefault();
        if (active && !historyMode) onSave();
    };
    const captureCustomerLocation = () => {
        if (!navigator.geolocation) {
            setCustomerLocationState({ locating: false, saving: false, message: '', error: 'GPS is not available on this device.' });
            return;
        }
        setCustomerLocationState({ locating: true, saving: false, message: '', error: '' });
        navigator.geolocation.getCurrentPosition((position) => {
            setCustomerLocationDraft({ latitude: position.coords.latitude, longitude: position.coords.longitude, accuracy_m: position.coords.accuracy, captured_at: new Date(position.timestamp).toISOString() });
            setCustomerLocationState({ locating: false, saving: false, message: 'Position captured as a draft. Submit it to update the customer.', error: '' });
        }, (error) => {
            setCustomerLocationState({ locating: false, saving: false, message: '', error: error.code === 1 ? 'Location permission was denied.' : 'Unable to capture the current position.' });
        }, { enableHighAccuracy: true, maximumAge: 0, timeout: 20000 });
    };
    const submitCustomerLocation = () => {
        if (!customerLocationDraft) return;
        setCustomerLocationState((current) => ({ ...current, saving: true, message: '', error: '' }));
        const mobileDeliveryApi = window.ValleyRuntime?.api?.mobileDeliveries || '/api/mobile/deliveries';
        window.axios.post(`${mobileDeliveryApi}/${stop.id}/customer-location`, {
            latitude: customerLocationDraft.latitude,
            longitude: customerLocationDraft.longitude,
            accuracy_m: customerLocationDraft.accuracy_m,
        }).then(({ data }) => {
            setCustomerLocation(data.data.customer_location);
            onCustomerLocationUpdated?.(data.data.customer_location);
            setCustomerLocationDraft(null);
            setCustomerLocationState({ locating: false, saving: false, message: 'Customer GPS position updated.', error: '' });
        }).catch((error) => setCustomerLocationState({ locating: false, saving: false, message: '', error: errorMessage(error) }));
    };
    const voucherItems = historyMode || !active ? stop.items.filter((item) => Number(item.delivered_quantity) > 0) : stop.items;
    const printSetting = printProfile.setting || { paper_size: 'A5', orientation: 'portrait', margin_mm: 10, design: 'compact', accent_color: '#0b84a5', show_signatures: true, show_notes: true, header_text: '', footer_text: '' };
    const pageSize = printSetting.paper_size === '80mm' ? '80mm 297mm' : printSetting.paper_size === '58mm' ? '58mm 210mm' : `${printSetting.paper_size} ${printSetting.orientation}`;
    const displayedCustomerLocation = customerLocationDraft || customerLocation;
    const customerMapBounds = displayedCustomerLocation ? `${displayedCustomerLocation.longitude - 0.004},${displayedCustomerLocation.latitude - 0.003},${displayedCustomerLocation.longitude + 0.004},${displayedCustomerLocation.latitude + 0.003}` : null;
    const customerMapUrl = displayedCustomerLocation ? `https://www.openstreetmap.org/export/embed.html?bbox=${encodeURIComponent(customerMapBounds)}&layer=mapnik&marker=${displayedCustomerLocation.latitude},${displayedCustomerLocation.longitude}` : null;

    return <form className="driver-stop-page" onSubmit={submit}>
        <style media="print">{`@page { size: ${pageSize}; margin: ${Number(printSetting.margin_mm) || 0}mm; }`}</style>
        <ShellBackButton label="Back to route stops" disabled={saving} onClick={onBack} />
        <ShellPageActions><button className="icon-button" type="button" aria-label="Print voucher" title="Print voucher" onClick={() => window.print()}><Printer size={17} /></button></ShellPageActions>
        <header className="mobile-section-heading driver-stop-page-heading"><div><p className="eyebrow">Stop {stop.stop_sequence} · Final sale</p><h2>{stop.shop_name}</h2><small>{stop.order_code} · {stop.invoice_code}</small></div><span className={`status ${statusFamily(stop.status)}`}>{statusLabel(stop.status)}</span></header>
        <section className="driver-stop-customer-location">
            <header><span><MapPin size={16} /><span><strong>Customer GPS position</strong><small>{customerLocationDraft ? 'Draft position - not submitted' : customerLocation ? 'Saved customer position' : 'No customer position saved'}</small></span></span>{customerLocationDraft && <span className="status warning">Draft</span>}</header>
            {customerMapUrl ? <iframe key={`${displayedCustomerLocation.latitude}-${displayedCustomerLocation.longitude}`} src={customerMapUrl} title={`${stop.shop_name} GPS position`} loading="lazy" referrerPolicy="no-referrer" /> : <div className="driver-stop-location-empty"><MapPin size={22} /><span>No saved GPS position for this customer.</span></div>}
            {displayedCustomerLocation && <dl><Info label="Latitude" value={Number(displayedCustomerLocation.latitude).toFixed(6)} /><Info label="Longitude" value={Number(displayedCustomerLocation.longitude).toFixed(6)} /><Info label="Accuracy" value={displayedCustomerLocation.accuracy_m !== null ? `${Math.round(displayedCustomerLocation.accuracy_m)} m` : '-'} /><Info label={customerLocationDraft ? 'Captured' : 'Last updated'} value={displayedCustomerLocation.captured_at ? new Date(displayedCustomerLocation.captured_at).toLocaleString() : '-'} /></dl>}
            {customerLocationState.message && <p className={`driver-stop-location-message ${customerLocationDraft ? 'draft' : 'success'}`}><CheckCircle2 size={14} />{customerLocationState.message}</p>}
            {customerLocationState.error && <p className="driver-stop-location-message error"><TriangleAlert size={14} />{customerLocationState.error}</p>}
            {active && !historyMode && stop.customer_id && <footer><button className="button" type="button" disabled={customerLocationState.locating || customerLocationState.saving} onClick={captureCustomerLocation}><Navigation size={15} />{customerLocationState.locating ? 'Capturing GPS...' : customerLocation ? 'Capture new position' : 'Capture current position'}</button><button className="button primary" type="button" disabled={!customerLocationDraft || customerLocationState.saving} onClick={submitCustomerLocation}><Save size={15} />{customerLocationState.saving ? 'Submitting...' : 'Submit GPS update'}</button></footer>}
        </section>
        <section className={`driver-stop-voucher print-design-${printSetting.design} print-paper-${printSetting.paper_size.toLowerCase()}`} style={{ '--voucher-accent': printSetting.accent_color }}>
            <header><div><p className="eyebrow">{printProfile.company?.name || 'Valley Water Distribution'}</p><h1>Delivery voucher</h1>{printSetting.header_text && <small>{printSetting.header_text}</small>}</div><strong>{trip?.code || delivery.trip_code || delivery.code}</strong></header>
            <dl className="driver-stop-voucher-facts"><Info label="Customer" value={stop.shop_name} /><Info label="Order" value={stop.order_code} /><Info label="Invoice" value={stop.invoice_code} /><Info label="Planned date" value={date(trip?.planned_date || delivery.planned_date)} /><Info label="Route" value={delivery.route_name} /><Info label="Vehicle" value={`${delivery.vehicle_code} · ${delivery.plate_no}`} /><Info label="Address" value={stop.delivery_address} /><Info label="Loaded quantity" value={number(stop.loaded_quantity)} /></dl>
            {active && !historyMode && <label className="driver-result-status driver-stop-edit-control">Result<select value={resultStatus} onChange={(event) => setResultStatus(event.target.value)}><option value="delivered">Delivered</option><option value="partially_delivered">Partially delivered</option><option value="failed">Failed</option></select></label>}

            {finalSaleMode && resultStatus !== 'failed' ? <section className="driver-final-sale-editor">
                <header><div><p className="eyebrow">Final order</p><h3>Sale and FOC items</h3></div><span><strong>{money(estimatedPayable)}</strong><small>{number(focQuantity)} FOC</small></span></header>
                <div className="driver-final-sale-lines">{finalLines.map((item, index) => <article key={item.key}>
                    <header><span className="driver-voucher-line">{index + 1}</span><span><strong>{item.product_name}</strong><small>{item.product_sku} · {number(item.loaded_quantity)} available · {money(Number(item.sale_quantity || 0) * Number(item.unit_price || 0))}</small></span><button className="icon-button danger-text" type="button" aria-label={`Remove ${item.product_name}`} onClick={() => setFinalLines((current) => current.filter((line) => line.key !== item.key))}><X size={15} /></button></header>
                    <div className="driver-final-sale-line-fields"><label>Sale quantity<input required type="number" inputMode="numeric" min="0" step="1" value={item.sale_quantity} onChange={(event) => updateLine(item.key, 'sale_quantity', event.target.value)} /></label><label>FOC quantity<input required type="number" inputMode="numeric" min="0" step="1" value={item.foc_quantity} onChange={(event) => updateLine(item.key, 'foc_quantity', event.target.value)} /></label><label>Price MMK<input required type="number" min="0" step="1" value={item.unit_price} onChange={(event) => updateLine(item.key, 'unit_price', event.target.value)} /></label><label>Damaged<input type="text" inputMode="numeric" pattern="[0-9]*" value={item.damaged_quantity ?? ''} onChange={(event) => updateLine(item.key, 'damaged_quantity', event.target.value.replace(/\D/g, ''))} /></label></div>
                </article>)}</div>
                <div className="driver-final-sale-add"><select aria-label="Product to add" value={addProductId} onChange={(event) => setAddProductId(event.target.value)}><option value="">Add product from vehicle</option>{availableProducts.filter((product) => !finalLines.some((item) => Number(item.product_id) === Number(product.id))).map((product) => <option key={product.id} value={product.id}>{product.name} · {number(product.loaded_quantity)} loaded</option>)}</select><button className="button" type="button" disabled={!addProductId} onClick={addLine}><Plus size={15} />Add product</button></div>
                <label className="driver-order-discount">Order discount (MMK)<input type="text" inputMode="numeric" pattern="[0-9]*" value={orderDiscount ?? ''} onChange={(event) => setOrderDiscount(event.target.value.replace(/\D/g, ''))} /><small>{money(estimatedPayable)} payable after discount</small></label>
                {isModified && <label className="driver-load-notes driver-modification-note">Modification note (optional)<textarea rows="3" value={modificationNote} onChange={(event) => setModificationNote(event.target.value)} placeholder="Why did the final Sale or FOC quantities change?" /></label>}
            </section> : <div className="driver-result-items driver-stop-voucher-items">{voucherItems.map((item, index) => <article key={item.id}><header><span className="driver-voucher-line">{index + 1}</span><span><strong>{item.product_name}</strong><small>{item.product_sku} · {item.unit} · {(item.item_type || 'sale').toUpperCase()}</small></span><strong>{number(active ? item.loaded_quantity : item.delivered_quantity)} {active ? 'loaded' : 'final'}</strong></header>{active && !historyMode && <div>{['delivered_quantity', 'returned_quantity', 'damaged_quantity'].map((field) => <label key={field}>{field.replace('_quantity', '')}<input type="number" inputMode="numeric" min="0" max={item.loaded_quantity} step="1" value={results[item.id]?.[field] ?? 0} onChange={(event) => setResults((current) => ({ ...current, [item.id]: { ...current[item.id], [field]: event.target.value } }))} /></label>)}</div>}</article>)}</div>}

            {active && !historyMode && resultStatus !== 'failed' && <section className="driver-settlement-card">
                <div><p className="eyebrow">Sale settlement</p><h3>How is this customer paying?</h3><small>{finalSaleMode ? `${money(estimatedPayable)} estimated payable. FOC has zero financial value.` : 'The final amount is calculated from delivered quantities when you save.'}</small></div>
                {!stop.customer_id && <p className="driver-customer-notice">A new customer will be created automatically with the company default credit limit.</p>}
                <label>Payment choice<select value={settlement.method} onChange={(event) => setSettlement((current) => ({ ...current, method: event.target.value }))}><option value="credit">Credit</option><option value="cash_driver">Cash handed to me</option><option value="cash_office">Cash paid directly to office</option><option value="bank_office">Bank payment to office</option></select></label>
                {settlement.method === 'credit' && <small className="driver-settlement-hint">The due date is calculated from stock issue. Credit is accepted automatically when it remains within the customer limit.</small>}
                {settlement.method === 'cash_driver' && <small className="driver-settlement-hint">This amount will be added to your cash holding for Office handover.</small>}
                {['cash_office', 'bank_office'].includes(settlement.method) && <small className="driver-settlement-hint">This payment goes directly to the Office book and is not added to your holding.</small>}
                {settlement.method === 'bank_office' && <label>Bank reference<input required type="text" maxLength="100" value={settlement.reference} onChange={(event) => setSettlement((current) => ({ ...current, reference: event.target.value }))} placeholder="Transfer or transaction reference" /></label>}
            </section>}
            {(historyMode || !active) && stop.settlement_method && <section className="driver-settlement-summary"><Info label="Settlement" value={{ credit: 'Credit', cash_driver: 'Cash held by driver', cash_office: 'Cash paid to office', bank_office: 'Bank paid to office' }[stop.settlement_method]} /><Info label="Final amount" value={money(stop.settlement_amount)} />{stop.credit_due_date && <Info label="Credit due" value={date(stop.credit_due_date)} />}</section>}
            {(historyMode || !active) && stop.order_modified && <div className="driver-modified-banner"><Pencil size={16} /><span><strong>Final sale modified by driver</strong><small>{stop.order_modification_note || 'No modification note was added.'}</small></span></div>}
            {printSetting.show_notes && ((active && !historyMode) ? <label className="driver-load-notes driver-stop-note">Completion note<textarea rows="3" value={notes} onChange={(event) => setNotes(event.target.value)} /></label> : notes && <div className="driver-stop-note-readonly"><strong>Completion note</strong><p>{notes}</p></div>)}
            {message && <p className="form-alert">{message}</p>}
            {printSetting.show_signatures && <footer className="driver-voucher-signatures"><span>Driver signature</span><span>Customer signature</span></footer>}
            {printSetting.footer_text && <p className="voucher-footer-note">{printSetting.footer_text}</p>}
        </section>
        {active && !historyMode && <footer className="driver-stop-page-actions"><button className="button primary" type="submit" disabled={saving}><CheckCircle2 size={17} />{saving ? 'Saving…' : 'Save final sale'}</button></footer>}
    </form>;
}

function TripStopList({ stops, selectedId, onSelect }) {
    return <section className="driver-load-panel"><div className="mobile-section-heading"><div><p className="eyebrow">Route stops</p><h2>{stops.length} customers</h2></div><strong>{stops.filter((stop) => ['delivered', 'partially_delivered', 'failed'].includes(stop.status)).length} done</strong></div><div className="driver-trip-stop-list">{stops.map((stop, index) => <button className={selectedId === stop.id ? 'is-selected' : ''} type="button" key={stop.id} onClick={() => onSelect(stop)}><span className="delivery-stop-index">{index + 1}</span><span><strong>{stop.shop_name}</strong><small>{stop.order_code} · {stop.delivery_address || 'No address'}</small></span><span className={`status ${statusFamily(stop.status)}`}>{statusLabel(stop.status)}</span></button>)}</div></section>;
}

function Metric({ label, value, hint, icon: Icon }) { return <article className="metric"><span>{label}</span><strong>{value}</strong><small>{hint}</small><Icon size={18} /></article>; }
function State({ title, loading = false }) { return <div className="workspace-state"><RefreshCw className={loading ? 'spin' : ''} size={22} /><strong>{title}</strong></div>; }
function MobileState({ title, loading = false, action }) { return <div className="workspace-state compact"><RefreshCw className={loading ? 'spin' : ''} size={22} /><strong>{title}</strong>{action && <button className="button" type="button" onClick={action}>Retry</button>}</div>; }
function Field({ label, wide, children }) { return <label className={wide ? 'span-2' : ''}>{label}{children}</label>; }
function Select({ label, name, items, form, setForm }) { return <Field label={label}><select required value={form[name]} onChange={(e) => setForm((v) => ({ ...v, [name]: e.target.value }))}>{items.map((item) => <option key={item.id} value={item.id}>{item.label}</option>)}</select></Field>; }
function Info({ label, value }) { return <div><dt>{label}</dt><dd>{value || '-'}</dd></div>; }
function Pagination({ meta = {}, page, setPage }) { return meta.last_page > 1 ? <div className="master-pagination"><span>Page {meta.current_page} of {meta.last_page} · {meta.total}</span><div><button disabled={page <= 1} onClick={() => setPage((v) => v - 1)}>Previous</button><button disabled={page >= meta.last_page} onClick={() => setPage((v) => v + 1)}>Next</button></div></div> : null; }
