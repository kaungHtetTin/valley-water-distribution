import { AlertCircle, ArrowUpRight, CalendarDays, CheckCircle2, ChevronRight, CircleDollarSign, Clock3, Download, MapPinned, Package, Printer, RefreshCw, Target, Truck, Users, WalletCards } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

const dashboardApi = (kind) => window.ValleyRuntime?.api?.dashboards ? `${window.ValleyRuntime.api.dashboards}/${kind}` : `/api/dashboards/${kind}`;
const chartApi = (kind) => window.ValleyRuntime?.api?.dashboardCharts ? `${window.ValleyRuntime.api.dashboardCharts}/${kind}` : `/api/dashboard-charts/${kind}`;
const mobileApi = () => window.ValleyRuntime?.api?.mobileDashboard || '/api/mobile/dashboard';
const today = () => new Date().toISOString().slice(0, 10);
const currentMonthStart = () => `${today().slice(0, 7)}-01`;
const money = (value) => `${new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(Number(value || 0))} MMK`;
const number = (value) => new Intl.NumberFormat('en-US', { maximumFractionDigits: 1 }).format(Number(value || 0));
const titleCase = (value) => String(value || '—').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
const errorText = (error) => error.response?.data?.message || 'Dashboard data could not be loaded.';
const tr = (locale, en, my) => locale === 'my' ? my : en;

const definitions = {
    owner: { title: 'Owner dashboard', my: 'ပိုင်ရှင် ဒက်ရှ်ဘုတ်', hint: 'Business health, liquidity, cost, receivables, and sales performance at a glance.' },
    sales: { title: 'Sales KPI', my: 'အရောင်း KPI', hint: 'Monthly target achievement, territory contribution, representative ranking, and customers.' },
    stock: { title: 'Stock KPI', my: 'စတော့ KPI', hint: 'Warehouse value, available quantity, movement velocity, and replenishment alerts.' },
    delivery: { title: 'Delivery KPI', my: 'ပို့ဆောင်ရေး KPI', hint: 'Completion, delivered volume, fleet usage, cost, and driver performance.' },
    finance: { title: 'Finance KPI', my: 'ဘဏ္ဍာရေး KPI', hint: 'Collections, debt, expenses, liquidity, and monthly profit trend.' },
};

function State({ loading, text }) {
    return <div className="workspace-state phase10-state">{loading ? <RefreshCw className="spin" size={22} /> : <AlertCircle size={22} />}<strong>{text}</strong></div>;
}

function DriverHomeDashboard({ summary, trips, locale, onViewTasks, onViewGps, onViewAttendance }) {
    const currentTrip = summary.current_trip;
    const activeTrip = currentTrip?.status === 'on_route' ? currentTrip : null;
    const stopsTotal = Number(activeTrip?.stops_count ?? summary.today_stops_count ?? 0);
    const stopsDone = Number(activeTrip?.completed_stops ?? summary.today_completed_stops ?? 0);
    const progress = stopsTotal > 0 ? Math.min(100, (stopsDone / stopsTotal) * 100) : 0;
    const additionalTrips = currentTrip ? trips.filter((trip) => Number(trip.id) !== Number(currentTrip.id)) : trips;
    const dateLabel = new Date().toLocaleDateString(locale === 'my' ? 'my-MM' : 'en-US', { weekday: 'long', month: 'short', day: 'numeric' });
    const greeting = new Date().getHours() < 12 ? 'Good morning' : new Date().getHours() < 17 ? 'Good afternoon' : 'Good evening';
    const tripState = (status) => ({ on_route: 'On route', loading: 'Ready to start', assigned: 'Load required', delivered: 'Completed', partially_delivered: 'Completed', failed: 'Completed' }[status] || titleCase(status));

    return <div className="mobile-kpi-home driver-home-dashboard">
        <header className="driver-home-heading"><div><p className="eyebrow">Driver workspace</p><h1>{greeting}</h1><span>{dateLabel} · Shift overview</span></div></header>
        <section className="driver-home-hero">
            <div className="driver-home-hero-heading"><span>{currentTrip ? 'Current trip' : 'Today’s assignment'}</span><span className={`driver-home-state ${currentTrip?.status === 'on_route' ? 'is-active' : ''}`}>{currentTrip ? tripState(currentTrip.status) : trips.length ? 'Scheduled' : 'No active trip'}</span></div>
            <h2>{currentTrip?.route_name || summary.assigned_route || 'No route assigned'}</h2>
            <p>{currentTrip ? `${currentTrip.code} · ${currentTrip.vehicle_code || ''} ${currentTrip.plate_no || ''}` : trips.length ? `${trips.length} trip${trips.length === 1 ? '' : 's'} on today’s schedule` : 'Your route and trip details will appear here when assigned.'}</p>
            <div className="driver-home-progress"><div><span>{activeTrip ? 'Trip progress' : 'Today’s stop progress'}</span><strong>{stopsDone} / {stopsTotal}</strong></div><i><b style={{ width: `${progress}%` }} /></i></div>
            <div className="driver-home-actions"><button className="button primary" type="button" onClick={onViewTasks}>{currentTrip ? 'Continue trip' : 'View tasks'}<ChevronRight size={15} /></button><button className="button" type="button" onClick={onViewGps}><MapPinned size={15} />Live GPS</button><button className="button" type="button" onClick={onViewAttendance}><CalendarDays size={15} />Attendance</button></div>
        </section>
        <section className="driver-home-metrics" aria-label="Cash and expense summary">
            <article className="is-cash"><span><WalletCards size={15} />Total cash hold</span><strong>{money(summary.cash_hold_amount)}</strong><small>Awaiting office handover</small></article>
            <article><span><CircleDollarSign size={15} />Expense reports</span><strong>{Number(summary.submitted_expenses || 0)}</strong><small>Filed this month</small></article>
        </section>
        {additionalTrips.length > 0 && <section className="driver-home-trips">
            <header><div><p className="eyebrow">Route plan</p><h2>{currentTrip ? 'Additional trips' : 'Today’s trips'}</h2></div></header>
            {additionalTrips.map((trip) => <article key={trip.id}><span className="driver-home-trip-icon"><Truck size={16} /></span><span className="driver-home-trip-info"><strong>{trip.code}</strong><small>{trip.route_name} · {trip.stops_count} stops · {tripState(trip.status)}</small></span><span className="driver-home-trip-progress">{trip.completed_stops}/{trip.stops_count}</span></article>)}
        </section>}
    </div>;
}

function Metric({ label, value, hint, tone = '', icon: Icon = ArrowUpRight }) {
    return <article className={`phase10-metric ${tone}`}><span className="phase10-metric-icon"><Icon size={16} /></span><div><small>{label}</small><strong>{value}</strong><span>{hint}</span></div></article>;
}

function BarChart({ title, hint, items = [], valueKey = 'value', secondaryKey, formatter = number, primaryLabel = 'Actual', secondaryLabel = 'Target' }) {
    const max = Math.max(1, ...items.flatMap((item) => [Number(item[valueKey] || 0), Number(secondaryKey ? item[secondaryKey] || 0 : 0)]));
    return <section className="phase10-panel phase10-chart"><header><div><h2>{title}</h2><span>{hint}</span></div><span className="phase10-live">Live KPI</span></header><div className="phase10-bars" role="img" aria-label={title}>{items.length ? items.map((item, index) => <div className="phase10-bar-column" key={`${item.label}-${index}`} title={`${item.label}: ${formatter(item[valueKey])}`}><div className="phase10-bar-track">{secondaryKey && <i className="secondary" style={{ height: `${Math.max(3, Number(item[secondaryKey] || 0) / max * 100)}%` }} />}<i style={{ height: `${Math.max(3, Number(item[valueKey] || 0) / max * 100)}%` }} /></div><small>{item.label}</small></div>) : <span className="muted">No chart data</span>}</div>{secondaryKey && <footer><span><i className="legend-primary" />{primaryLabel}</span><span><i className="legend-secondary" />{secondaryLabel}</span></footer>}</section>;
}

function CompactTable({ title, subtitle, columns, items = [], empty = 'No records for this period.' }) {
    const stockMovementTable = ['Fast moving', 'Slow moving & alerts'].includes(title);
    const deliveryTable = ['Driver performance', 'Fleet usage'].includes(title);
    return <section className={`phase10-panel phase10-table-panel ${stockMovementTable ? 'phase10-stock-movement-table' : ''} ${deliveryTable ? 'phase10-delivery-table' : ''}`}><header><div><h2>{title}</h2><span>{subtitle}</span></div><strong>{items.length}</strong></header>{items.length ? <div className="phase10-table-wrap"><table><thead><tr>{columns.map((column) => <th key={column.label}>{column.label}</th>)}</tr></thead><tbody>{items.map((item, index) => <tr key={item.id || item.employee_id || item.product_id || item.vehicle_id || `${title}-${index}`}>{columns.map((column) => <td key={column.label}>{column.render(item, index)}</td>)}</tr>)}</tbody></table></div> : <p className="phase10-empty">{empty}</p>}</section>;
}

function OwnerView({ data, charts }) {
    const k = data.kpis || {};
    const metrics = [
        ['End-date sales', money(k.today_sales), 'End date activity', 'accent', CircleDollarSign], ['Period sales', money(k.monthly_sales), `${number(k.target_achievement)}% of target`, 'success', Target],
        ['Period revenue', money(k.annual_sales), 'Selected date range', '', ArrowUpRight], ['Cash', money(k.cash_balance), 'Balance as of end date', '', WalletCards],
        ['Bank', money(k.bank_balance), 'Balance as of end date', '', WalletCards], ['Outstanding', money(k.outstanding_credit), 'As of end date', 'warning', Clock3],
        ['Warehouse value', money(k.warehouse_value), `${k.customer_count || 0} active customers`, '', Package], ['Vehicle cost', money(k.vehicle_cost), 'Selected date range', '', Truck],
        ['Salary cost', money(k.salary_cost), 'Approved and paid payroll', '', Users], ['Outdoor expense', money(k.outdoor_expense), 'Approved field expense', '', ArrowUpRight],
        ['Net profit', money(k.net_profit), 'Revenue less operating cost', Number(k.net_profit) >= 0 ? 'success' : 'danger', CircleDollarSign], ['Field collection', money(k.field_collection), 'Selected date range', '', WalletCards],
    ];
    return <><div className="phase10-metrics owner">{metrics.map(([label, value, hint, tone, icon]) => <Metric key={label} label={label} value={value} hint={hint} tone={tone} icon={icon} />)}</div><div className="phase10-grid two"><BarChart title="Six-month sales" hint="Invoice revenue by month" items={charts.sales_trend} formatter={money} /><BarChart title="Seven-day cash flow" hint="Approved inflow by day" items={charts.cash_flow} valueKey="inflow" secondaryKey="outflow" formatter={money} primaryLabel="Inflow" secondaryLabel="Outflow" /></div><div className="phase10-grid wide"><CompactTable title="Recent orders" subtitle="Latest customer activity" items={data.recent_orders} columns={[{ label: 'Order', render: (x) => <><strong>{x.code}</strong><small>{x.date}</small></> }, { label: 'Customer', render: (x) => x.shop_name }, { label: 'Amount', render: (x) => money(x.amount) }, { label: 'Status', render: (x) => <span className={`status ${x.status === 'pending' ? 'warning' : 'neutral'}`}>{titleCase(x.status)}</span> }]} /><section className="phase10-panel phase10-attention"><header><div><h2>Needs attention</h2><span>Operational review queue</span></div><AlertCircle size={18} /></header>{Object.entries(data.attention || {}).map(([label, value]) => <div key={label}><span>{titleCase(label)}</span><strong>{value}</strong></div>)}</section></div></>;
}

function SalesView({ data, charts }) {
    const s = data.summary || {};
    return <><div className="phase10-metrics"><Metric label="Monthly sales" value={money(s.sales)} hint={`${s.orders || 0} invoiced orders`} tone="accent" icon={CircleDollarSign} /><Metric label="Target" value={money(s.target)} hint={`${number(s.achievement)}% achieved`} icon={Target} /><Metric label="New customers" value={s.new_customers || 0} hint="Added this month" icon={Users} /><Metric label="Territories" value={(data.area_sales || []).length} hint="Areas with sales" icon={ArrowUpRight} /></div><div className="phase10-grid two"><BarChart title="Area sales" hint="Territory contribution" items={charts.area_sales} formatter={money} /><BarChart title="Target vs actual" hint="Representative performance" items={charts.target} valueKey="actual" secondaryKey="target" formatter={money} /></div><div className="phase10-grid two"><CompactTable title="Sales ranking" subtitle="Actual against assigned target" items={data.ranking} columns={[{ label: '#', render: (x) => x.rank }, { label: 'Representative', render: (x) => <><strong>{x.name}</strong><small>{x.route_name}</small></> }, { label: 'Actual', render: (x) => money(x.actual) }, { label: 'Achievement', render: (x) => `${number(x.achievement)}%` }]} /><CompactTable title="Top customers" subtitle="Highest invoiced sales" items={data.top_customers} columns={[{ label: 'Customer', render: (x) => <><strong>{x.shop_name}</strong><small>{x.code}</small></> }, { label: 'Route', render: (x) => x.route_name }, { label: 'Sales', render: (x) => money(x.sales) }]} /></div></>;
}

function StockView({ data, charts }) {
    const s = data.summary || {};
    const columns = [{ label: 'Product', render: (x) => <><strong>{x.product_name}</strong><small>{x.sku}</small></> }, { label: 'On hand', render: (x) => number(x.quantity) }, { label: 'Demand', render: (x) => number(x.monthly_demand) }, { label: 'Value', render: (x) => money(x.stock_value) }];
    return <><div className="phase10-metrics"><Metric label="Stock value" value={money(s.stock_value)} hint="Across all warehouses" tone="accent" icon={WalletCards} /><Metric label="On-hand quantity" value={number(s.quantity)} hint={`${s.products || 0} products`} icon={Package} /><Metric label="Warehouses" value={s.warehouses || 0} hint="Active stock locations" icon={Package} /><Metric label="Stock alerts" value={s.alerts || 0} hint="At or below threshold" tone="warning" icon={AlertCircle} /></div><BarChart title="Fast-moving products" hint="Monthly invoiced quantity" items={charts.movement} formatter={number} /><div className="phase10-grid two"><CompactTable title="Fast moving" subtitle="Highest demand products" items={data.fast_moving} columns={columns} /><CompactTable title="Slow moving & alerts" subtitle="Review stock allocation" items={(data.alerts?.length ? data.alerts : data.slow_moving)} columns={columns} /></div></>;
}

function DeliveryView({ data, charts }) {
    const s = data.summary || {};
    return <><div className="phase10-metrics"><Metric label="Deliveries" value={s.deliveries || 0} hint={`${s.active || 0} active`} tone="accent" icon={Truck} /><Metric label="Completed" value={s.completed || 0} hint="Delivered or partial" tone="success" icon={CheckCircle2} /><Metric label="Delivered quantity" value={number(s.delivered_quantity)} hint={`${number(s.distance_km)} km travelled`} icon={Package} /><Metric label="Delivery cost" value={money(s.delivery_cost)} hint={`${s.vehicles_used || 0} vehicles used`} icon={CircleDollarSign} /></div><div className="phase10-grid two"><BarChart title="Driver output" hint="Delivered quantity" items={charts.driver_output} formatter={number} /><BarChart title="Vehicle usage" hint="Delivery assignments" items={charts.vehicle_usage} formatter={number} /></div><div className="phase10-grid two"><CompactTable title="Driver performance" subtitle="Monthly delivery execution" items={data.drivers} columns={[{ label: 'Driver', render: (x) => <><strong>{x.name}</strong><small>{x.route_name}</small></> }, { label: 'Completed', render: (x) => `${x.completed}/${x.deliveries}` }, { label: 'Quantity', render: (x) => number(x.delivered_quantity) }, { label: 'Rate', render: (x) => `${number(x.completion_rate)}%` }]} /><CompactTable title="Fleet usage" subtitle="Assignments and route distance" items={data.vehicle_usage} columns={[{ label: 'Vehicle', render: (x) => <><strong>{x.code}</strong><small>{x.plate_no}</small></> }, { label: 'Deliveries', render: (x) => x.deliveries }, { label: 'Quantity', render: (x) => number(x.delivered_quantity) }, { label: 'Distance', render: (x) => `${number(x.distance_km)} km` }]} /></div></>;
}

function FinanceView({ data, charts }) {
    const s = data.summary || {};
    return <><div className="phase10-metrics finance"><Metric label="Revenue" value={money(s.revenue)} hint="Invoiced this month" tone="accent" icon={CircleDollarSign} /><Metric label="Collections" value={money(s.collections)} hint={`${money(s.field_collections)} field`} tone="success" icon={WalletCards} /><Metric label="Debt balance" value={money(s.debt_balance)} hint="Customer receivable" tone="warning" icon={Clock3} /><Metric label="Expenses" value={money(s.expenses)} hint="Approved operating cost" icon={ArrowUpRight} /><Metric label="Net profit" value={money(s.profit)} hint="Revenue less expenses" tone={Number(s.profit) >= 0 ? 'success' : 'danger'} icon={CircleDollarSign} /><Metric label="Liquidity" value={money(Number(s.cash_balance || 0) + Number(s.bank_balance || 0))} hint="Cash plus bank" icon={WalletCards} /></div><div className="phase10-grid two"><BarChart title="Profit trend" hint="Six-month revenue and expense" items={charts.profit_trend} valueKey="revenue" secondaryKey="expense" formatter={money} primaryLabel="Revenue" secondaryLabel="Expense" /><BarChart title="Expense analysis" hint="Current month by category" items={data.expense_analysis} formatter={money} /></div><section className="phase10-panel phase10-review"><header><div><h2>Pending finance review</h2><span>Submitted records awaiting action</span></div></header>{Object.entries(data.pending || {}).map(([label, value]) => <div key={label}><span>{titleCase(label)}</span><strong>{value}</strong></div>)}</section></>;
}

export function OfficeKpiDashboard({ kind = 'owner', locale = 'en' }) {
    const [filter, setFilter] = useState({ date_from: currentMonthStart(), date_to: today() });
    const [refresh, setRefresh] = useState(0);
    const [state, setState] = useState({ loading: true, data: {}, charts: {}, error: '' });
    useEffect(() => { let active = true; if (!filter.date_from || !filter.date_to || filter.date_from > filter.date_to) return undefined; setState((value) => ({ ...value, loading: true, error: '' })); Promise.all([window.axios.get(dashboardApi(kind), { params: filter }), window.axios.get(chartApi(kind), { params: filter })]).then(([summary, chart]) => active && setState({ loading: false, data: summary.data.data, charts: chart.data.data, error: '' })).catch((error) => active && setState({ loading: false, data: {}, charts: {}, error: errorText(error) })); return () => { active = false; }; }, [kind, filter.date_from, filter.date_to, refresh]);
    const definition = definitions[kind];
    const invalidRange = Boolean(filter.date_from && filter.date_to && filter.date_from > filter.date_to);
    return <section className="page phase10-page"><div className="master-heading phase10-heading"><div><p className="eyebrow">{tr(locale, 'Phase 10 · Executive intelligence', 'အဆင့် ၁၀ · စီမံခန့်ခွဲမှု အချက်အလက်')}</p><h1>{tr(locale, definition.title, definition.my)}</h1><span className="muted">{definition.hint}</span></div><div className="phase10-filter"><label className="phase10-range-field"><span>From</span><input aria-label="From date" type="date" value={filter.date_from} max={filter.date_to} onChange={(event) => setFilter((value) => ({ ...value, date_from: event.target.value }))} /></label><label className="phase10-range-field"><span>To</span><input aria-label="To date" type="date" value={filter.date_to} min={filter.date_from} onChange={(event) => setFilter((value) => ({ ...value, date_to: event.target.value }))} /></label>{invalidRange && <span className="phase10-range-error">To date must be on or after From date.</span>}<button className="button secondary phase10-filter-refresh" disabled={invalidRange || !filter.date_from || !filter.date_to} onClick={() => setRefresh((value) => value + 1)}><RefreshCw size={15} />Refresh</button></div></div>{state.loading ? <State loading text="Calculating dashboard KPIs" /> : state.error ? <p className="inline-error"><AlertCircle size={15} />{state.error}</p> : <>{kind === 'owner' && <OwnerView data={state.data} charts={state.charts} />}{kind === 'sales' && <SalesView data={state.data} charts={state.charts} />}{kind === 'stock' && <StockView data={state.data} charts={state.charts} />}{kind === 'delivery' && <DeliveryView data={state.data} charts={state.charts} />}{kind === 'finance' && <FinanceView data={state.data} charts={state.charts} />}</>}</section>;
}

function MobileStat({ label, value, icon: Icon }) {
    return <article><span><Icon size={15} />{label}</span><strong>{value}</strong></article>;
}

function MobileSalesCharts({ locale, monthItems = [], yearItems = [], summary = {} }) {
    const monthMax = Math.max(1, ...monthItems.map((item) => Number(item.value || 0)));
    const yearMax = Math.max(1, ...yearItems.map((item) => Number(item.value || 0)));
    const yearPoints = yearItems.map((item, index) => {
        const x = 10 + (index * (300 / Math.max(yearItems.length - 1, 1)));
        const y = 12 + ((1 - (Number(item.value || 0) / yearMax)) * 82);
        return { ...item, x, y };
    });
    const polyline = yearPoints.map((item) => `${item.x},${item.y}`).join(' ');
    const area = yearPoints.length ? `10,98 ${polyline} ${yearPoints.at(-1).x},98` : '';

    return <div className="mobile-sales-charts">
        <section className="mobile-sales-chart-card">
            <header><div><h2>{tr(locale, 'Sales this month', 'ယခုလ အရောင်း')}</h2><span>{tr(locale, 'Weekly invoiced sales', 'အပတ်စဉ် invoice အရောင်း')}</span></div><strong>{money(summary.monthly_sales)}</strong></header>
            <div className="mobile-month-bars" role="img" aria-label={tr(locale, 'Weekly sales bar chart', 'အပတ်စဉ် အရောင်း ဘားဇယား')}>
                {monthItems.map((item) => <div key={item.label} title={`${item.label}: ${money(item.value)}`}><span><i style={{ height: `${Math.max(Number(item.value) > 0 ? 8 : 2, Number(item.value || 0) / monthMax * 100)}%` }} /></span><small>{item.label}</small></div>)}
            </div>
        </section>
        <section className="mobile-sales-chart-card">
            <header><div><h2>{tr(locale, 'Sales this year', 'ယခုနှစ် အရောင်း')}</h2><span>{tr(locale, 'Monthly invoiced sales', 'လစဉ် invoice အရောင်း')}</span></div><strong>{money(summary.yearly_sales)}</strong></header>
            <div className="mobile-year-line" role="img" aria-label={tr(locale, 'Yearly sales line graph', 'နှစ်စဉ် အရောင်းမျဉ်းဂရပ်')}>
                <svg viewBox="0 0 320 105" preserveAspectRatio="none" aria-hidden="true">
                    <defs><linearGradient id="mobile-sales-area" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stopColor="currentColor" stopOpacity=".24" /><stop offset="100%" stopColor="currentColor" stopOpacity=".02" /></linearGradient></defs>
                    <line className="grid-line" x1="10" y1="98" x2="310" y2="98" />
                    {area && <polygon points={area} fill="url(#mobile-sales-area)" />}
                    {polyline && <polyline points={polyline} />}
                    {yearPoints.map((item) => <circle key={item.label} cx={item.x} cy={item.y} r="2.8"><title>{item.label}: {money(item.value)}</title></circle>)}
                </svg>
                <div>{yearItems.map((item) => <small key={item.label}>{item.label}</small>)}</div>
            </div>
        </section>
    </div>;
}

function MobileSalesBreakdowns({ locale, customers = [], products = [], onViewCustomer }) {
    return <div className="mobile-sales-breakdowns">
        <section className="mobile-kpi-list">
            <header><div><h2>{tr(locale, 'Top customers', 'အရောင်းအများဆုံး ဖောက်သည်များ')}</h2><span>{tr(locale, 'Selected reporting period', 'ရွေးချယ်ထားသော ကာလ')}</span></div></header>
            {customers.length ? customers.map((customer, index) => <button type="button" key={customer.customer_id} onClick={() => onViewCustomer?.(customer.customer_id)}><span className="mobile-kpi-rank">{index + 1}</span><span><strong>{customer.name}</strong><small>{customer.code}</small></span><strong>{money(customer.sales)}</strong></button>) : <p className="phase10-empty">{tr(locale, 'No customer sales in this period.', 'ဤကာလအတွင်း ဖောက်သည်အရောင်းမရှိပါ။')}</p>}
        </section>
        <section className="mobile-kpi-list">
            <header><div><h2>{tr(locale, 'Top products', 'အရောင်းအများဆုံး ပစ္စည်းများ')}</h2><span>{tr(locale, 'By invoiced quantity', 'Invoice အရေအတွက်အလိုက်')}</span></div></header>
            {products.length ? products.map((product, index) => <article key={product.product_id}><span className="mobile-kpi-rank">{index + 1}</span><span><strong>{product.name}</strong><small>{product.sku} · {number(product.quantity)} units</small></span><strong>{money(product.sales)}</strong></article>) : <p className="phase10-empty">{tr(locale, 'No product sales in this period.', 'ဤကာလအတွင်း ပစ္စည်းအရောင်းမရှိပါ။')}</p>}
        </section>
    </div>;
}

export function MobileHomeDashboard({ appId, locale = 'en', quickLinks = [], onNavigate, onViewCustomer, onViewOrders, onViewTasks, onViewGps, onViewAttendance }) {
    const [filter, setFilter] = useState({ date_from: currentMonthStart(), date_to: today() });
    const [refresh, setRefresh] = useState(0);
    const [state, setState] = useState({ loading: true, data: {}, error: '' });
    useEffect(() => {
        let active = true;
        const params = appId === 'sales' ? filter : undefined;
        setState((current) => ({ ...current, loading: true, error: '' }));
        window.axios.get(mobileApi(), { params }).then(({ data }) => active && setState({ loading: false, data: data.data, error: '' })).catch((error) => active && setState({ loading: false, data: {}, error: errorText(error) }));
        return () => { active = false; };
    }, [appId, filter.date_from, filter.date_to, refresh]);
    const summary = state.data.summary || {};
    const content = useMemo(() => ({
        client: { eyebrow: tr(locale, 'Account overview', 'အကောင့်အနှစ်ချုပ်'), title: summary.current_order_code || tr(locale, 'No active order', 'လက်ရှိ အော်ဒါမရှိပါ'), meta: `${titleCase(summary.current_order_status)} · ${money(summary.outstanding_balance)}`, stats: [['Recent orders', summary.recent_orders_count || 0, Package], ['Outstanding', money(summary.outstanding_balance), WalletCards]], items: state.data.recent_orders || [] },
        sales: { eyebrow: tr(locale, 'Monthly order performance', 'လစဉ် အော်ဒါစွမ်းဆောင်ရည်'), title: money(summary.monthly_sales), meta: `${number(summary.achievement)}% of ${money(summary.target)}`, stats: [['Today', money(summary.daily_sales), CircleDollarSign], ['Period sales', money(summary.period_sales), ArrowUpRight], ['Average order', money(summary.average_order_value), Package], ['Period orders', summary.period_orders || 0, Package], ['New customers', summary.new_customers || 0, Users], ['Year sales', money(summary.yearly_sales), CircleDollarSign]], items: state.data.trend || [] },
        driver: { eyebrow: tr(locale, 'Delivery shift', 'ပို့ဆောင်ရေး အလှည့်ကျ'), title: summary.assigned_route || 'No route assigned', meta: `${summary.today_deliveries || 0} trips today · ${summary.completed_deliveries || 0} completed`, stats: [['Total cash hold', money(summary.cash_hold_amount), WalletCards], ['Expenses', summary.submitted_expenses || 0, CircleDollarSign]], items: state.data.deliveries || [] },
    })[appId], [appId, locale, state.data, summary]);
    const invalidRange = filter.date_from > filter.date_to;
    const exportSalesCsv = () => {
        const rows = [
            ['Sales KPI Report', `${filter.date_from} to ${filter.date_to}`], ['Metric', 'Value'],
            ['Today sales', summary.daily_sales || 0], ['Period sales', summary.period_sales || 0],
            ['Average order', summary.average_order_value || 0], ['Period orders', summary.period_orders || 0],
            ['Monthly sales', summary.monthly_sales || 0], ['Year sales', summary.yearly_sales || 0], [],
            ['Top customers', 'Code', 'Sales'], ...(state.data.top_customers || []).map((item) => [item.name, item.code, item.sales]), [],
            ['Top products', 'SKU', 'Quantity', 'Sales'], ...(state.data.top_products || []).map((item) => [item.name, item.sku, item.quantity, item.sales]),
        ];
        const csv = rows.map((row) => row.map((value) => `"${String(value ?? '').replaceAll('"', '""')}"`).join(',')).join('\n');
        const href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
        const link = document.createElement('a');
        link.href = href;
        link.download = `sales-kpi-${filter.date_from}-${filter.date_to}.csv`;
        link.click();
        URL.revokeObjectURL(href);
    };
    if (state.loading) return <State loading text="Loading your dashboard" />;
    if (state.error) return <p className="inline-error"><AlertCircle size={15} />{state.error}</p>;
    if (appId === 'driver') return <DriverHomeDashboard summary={summary} trips={state.data.today_trips || []} locale={locale} onViewTasks={onViewTasks} onViewGps={onViewGps} onViewAttendance={onViewAttendance} />;
    return <div className="mobile-kpi-home">{appId === 'sales' && <section className="mobile-kpi-toolbar"><div className="mobile-kpi-range"><label><span>{tr(locale, 'From', 'မှ')}</span><input type="date" value={filter.date_from} max={filter.date_to} onChange={(event) => setFilter((current) => ({ ...current, date_from: event.target.value }))} /></label><label><span>{tr(locale, 'To', 'အထိ')}</span><input type="date" value={filter.date_to} min={filter.date_from} onChange={(event) => setFilter((current) => ({ ...current, date_to: event.target.value }))} /></label><button className="icon-button" type="button" disabled={invalidRange} aria-label={tr(locale, 'Refresh report', 'အစီရင်ခံစာ ပြန်ဖွင့်ရန်')} onClick={() => setRefresh((value) => value + 1)}><RefreshCw size={15} /></button></div><div className="mobile-kpi-export"><button className="button" type="button" onClick={exportSalesCsv}><Download size={14} />Excel CSV</button><button className="button" type="button" onClick={() => window.print()}><Printer size={14} />PDF</button><button className="button primary" type="button" onClick={onViewOrders}><ChevronRight size={14} />{tr(locale, 'View orders', 'အော်ဒါများကြည့်ရန်')}</button></div></section>}<section className={`mobile-kpi-hero ${appId}`}><span>{content.eyebrow}</span><h1>{content.title}</h1><p>{content.meta}</p>{appId === 'sales' && <div className="mobile-target"><i style={{ width: `${Math.min(100, Number(summary.achievement || 0))}%` }} /></div>}</section><div className="mobile-kpi-stats">{content.stats.map(([label, value, icon]) => <MobileStat key={label} label={label} value={value} icon={icon} />)}</div>{appId === 'sales' && <button className="mobile-home-attendance-link" type="button" onClick={onViewAttendance}><span><CalendarDays size={18} /></span><span><strong>{tr(locale, 'Record attendance', 'တက်ရောက်မှု မှတ်တမ်းတင်ရန်')}</strong><small>{tr(locale, 'Select a warehouse and verify your GPS location', 'ဂိုဒေါင်ရွေးပြီး GPS တည်နေရာ စစ်ဆေးပါ')}</small></span><ChevronRight size={17} /></button>}{appId === 'sales' && <MobileSalesCharts locale={locale} monthItems={state.data.month_trend} yearItems={state.data.year_trend} summary={summary} />}{appId === 'sales' && <MobileSalesBreakdowns locale={locale} customers={state.data.top_customers} products={state.data.top_products} onViewCustomer={onViewCustomer} />}{appId === 'sales' && quickLinks.length > 0 && <section className="mobile-home-links"><header><div><h2>{tr(locale, 'Important links', 'အရေးကြီး လုပ်ငန်းများ')}</h2><span>{tr(locale, 'Frequently used operations', 'မကြာခဏ အသုံးပြုသော လုပ်ငန်းများ')}</span></div></header><nav aria-label={tr(locale, 'Important links', 'အရေးကြီး လုပ်ငန်းများ')}>{quickLinks.map(({ view, label, href, icon: Icon }) => <a href={href} onClick={(event) => onNavigate?.(event, href)} key={view}><span><Icon size={17} /></span><strong>{label}</strong><ChevronRight size={15} /></a>)}</nav></section>}<section className="mobile-kpi-list"><header><div><h2>{appId === 'sales' ? 'Six-month trend' : appId === 'driver' ? 'Recent trips' : 'Recent orders'}</h2><span>Updated from office records</span></div><span className="phase10-live">Live</span></header>{content.items.length ? content.items.map((item, index) => <article key={item.id || `${item.label}-${index}`}><span className="mobile-kpi-row-icon">{appId === 'sales' ? <ArrowUpRight size={16} /> : appId === 'driver' ? <Truck size={16} /> : <Package size={16} />}</span><span><strong>{item.code || item.label}</strong><small>{item.route_name || item.date || titleCase(item.status)}</small></span><strong>{appId === 'sales' ? money(item.value) : appId === 'driver' ? number(item.quantity) : money(item.amount)}</strong></article>) : <p className="phase10-empty">No recent activity.</p>}</section></div>;
}
