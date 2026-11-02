import { mobileText as mt } from './ui/mobileCopy.js';
import { CustomerHome, SalesToday } from './MobileHomeSections';
import { currentLocale, businessToday, formatMoney as uiMoney, TIME_ZONE } from './ui/format.js';
import { usePageState } from './ui/usePageState';
import { AlertCircle, ArrowUpRight, CalendarDays, CheckCircle2, ChevronRight, CircleDollarSign, CircleGauge, Clock3, Download, Globe2, Mail, MapPinned, MessageCircle, Package, Phone, Printer, RefreshCw, Rows3, Send, Share2, ShoppingCart, Target, Truck, Users, WalletCards } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { ShellPageActions } from './components/ShellPageActions';

const dashboardApi = (kind) => window.ValleyRuntime?.api?.dashboards ? `${window.ValleyRuntime.api.dashboards}/${kind}` : `/api/dashboards/${kind}`;
const chartApi = (kind) => window.ValleyRuntime?.api?.dashboardCharts ? `${window.ValleyRuntime.api.dashboardCharts}/${kind}` : `/api/dashboard-charts/${kind}`;
const mobileApi = () => window.ValleyRuntime?.api?.mobileDashboard || '/api/mobile/dashboard';
const mobileKpiApi = () => window.ValleyRuntime?.api?.mobileKpi || '/api/mobile/kpi';
const today = () => businessToday();
const currentMonthStart = () => `${today().slice(0, 7)}-01`;
const money = uiMoney;
const number = (value) => new Intl.NumberFormat(currentLocale(), { maximumFractionDigits: 1 }).format(Number(value || 0));
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

function MobileHomeKpiSummary({ appId, state, onOpen }) {
    const result = state.result;
    const score = Math.min(100, Math.max(0, Number(result?.overall_score || 0)));
    const status = mt(titleCase(result?.status || 'draft'));

    return <button className={`mobile-home-kpi-summary ${appId}`} type="button" onClick={onOpen} aria-label={mt("Open monthly KPI report")}>
        <span className="mobile-home-kpi-icon"><CircleGauge size={19} /></span>
        <span className="mobile-home-kpi-body">{state.loading ? <span className="mobile-home-kpi-copy"><small>{mt("Monthly KPI")}</small><strong>{mt("Loading current review...")}</strong></span> : state.error ? <span className="mobile-home-kpi-copy"><small>{mt("Monthly KPI")}</small><strong>{mt("Review unavailable")}</strong><small>{mt("Open KPI report for details")}</small></span> : result ? <>
                <span className="mobile-home-kpi-score" style={{ '--home-kpi-score': `${score * 3.6}deg` }}><b>{number(score)}</b><small>{mt("Score")}</small></span>
                <span className="mobile-home-kpi-copy"><small>{result.month} monthly KPI</small><strong>{result.template_name}</strong><small>{status} · {result.payroll_adjustment_id ? mt("Posted bonus") : result.status === 'approved' ? mt("Approved bonus") : mt("Bonus preview")} {money(result.bonus_amount)}</small></span>
            </> : <span className="mobile-home-kpi-copy"><small>{mt("Monthly KPI")}</small><strong>{mt("No review for this month")}</strong><small>{mt("Open your KPI history")}</small></span>}
        </span>
        <ChevronRight size={17} />
    </button>;
}

function DriverHomeDashboard({ summary, trips, locale, kpiState, onViewTasks, onViewGps, onViewAttendance, onViewKpi, onViewTrip }) {
    const currentTrip = summary.current_trip;
    const activeTrip = currentTrip?.status === 'on_route' ? currentTrip : null;
    const stopsTotal = Number(activeTrip?.stops_count ?? summary.today_stops_count ?? 0);
    const stopsDone = Number(activeTrip?.completed_stops ?? summary.today_completed_stops ?? 0);
    const progress = stopsTotal > 0 ? Math.min(100, (stopsDone / stopsTotal) * 100) : 0;
    const additionalTrips = currentTrip ? trips.filter((trip) => Number(trip.id) !== Number(currentTrip.id)) : trips;
    const dateLabel = new Date().toLocaleDateString(currentLocale(), { timeZone: TIME_ZONE, weekday: 'long', month: 'short', day: 'numeric' });
    const greeting = new Date().getHours() < 12 ? mt("Good morning") : new Date().getHours() < 17 ? mt("Good afternoon") : mt("Good evening");
    const tripState = (status) => mt(({ on_route: 'On route', loading: 'Ready to start', assigned: 'Load required', delivered: 'Completed', partially_delivered: 'Completed', failed: 'Completed' }[status] || titleCase(status)));

    return <div className="mobile-kpi-home driver-home-dashboard">
        <header className="driver-home-heading"><div><p className="eyebrow">{mt("Driver workspace")}</p><h1>{greeting}</h1><span>{dateLabel} · Shift overview</span></div></header>
        <section className="driver-home-hero">
            <div className="driver-home-hero-heading"><span>{currentTrip ? mt("Current trip") : mt("Today’s assignment")}</span><span className={`driver-home-state ${currentTrip?.status === 'on_route' ? 'is-active' : ''}`}>{currentTrip ? tripState(currentTrip.status) : trips.length ? mt("Scheduled") : mt("No active trip")}</span></div>
            <h2>{currentTrip?.route_name || summary.assigned_route || mt("No route assigned")}</h2>
            <p>{currentTrip ? `${currentTrip.code} · ${currentTrip.vehicle_code || ''} ${currentTrip.plate_no || ''}` : trips.length ? `${trips.length} trip${trips.length === 1 ? '' : 's'} on today’s schedule` : mt("Your route and trip details will appear here when assigned.")}</p>
            <div className="driver-home-progress"><div><span>{activeTrip ? mt("Trip progress") : mt("Today’s stop progress")}</span><strong>{stopsDone} / {stopsTotal}</strong></div><i><b style={{ width: `${progress}%` }} /></i></div>
            <div className="driver-home-actions"><button className="button primary" type="button" onClick={() => currentTrip ? onViewTrip(currentTrip.id) : onViewTasks()}>{currentTrip ? mt("Continue trip") : mt("View deliveries")}<ChevronRight size={15} /></button></div>
        </section>
        <section className="driver-home-metrics" aria-label={mt("Cash and expense summary")}>
            <article className="is-cash"><span><WalletCards size={15} />{mt("Total cash hold")}</span><strong>{money(summary.cash_hold_amount)}</strong><small>{mt("Awaiting office handover")}</small></article>
            <article><span><CircleDollarSign size={15} />{mt("Expense reports")}</span><strong>{Number(summary.submitted_expenses || 0)}</strong><small>{mt("Filed this month")}</small></article>
        </section>
        
        {additionalTrips.length > 0 && <section className="driver-home-trips">
            <header><div><p className="eyebrow">{mt("Route plan")}</p><h2>{currentTrip ? mt("Additional trips") : mt("Today’s trips")}</h2></div></header>
            {additionalTrips.map((trip) => <button className="mobile-task-link" type="button" onClick={() => onViewTrip(trip.id)} key={trip.id}><span className="driver-home-trip-icon"><Truck size={16} /></span><span className="driver-home-trip-info"><strong>{trip.code}</strong><small>{trip.route_name} · {trip.stops_count} stops · {tripState(trip.status)}</small></span><span className="driver-home-trip-progress">{trip.completed_stops}/{trip.stops_count}</span></button>)}
        </section>}
    <MobileHomeKpiSummary appId="driver" state={kpiState} onOpen={onViewKpi} /></div>;
}

function Metric({ label, value, hint, tone = '', icon: Icon = ArrowUpRight }) {
    return <article className={`phase10-metric ${tone}`}><span className="phase10-metric-icon"><Icon size={16} /></span><div><small>{label}</small><strong>{value}</strong><span>{hint}</span></div></article>;
}

function BarChart({ title, hint, items = [], valueKey = 'value', secondaryKey, formatter = number, primaryLabel = 'Actual', secondaryLabel = 'Target' }) {
    const max = Math.max(1, ...items.flatMap((item) => [Number(item[valueKey] || 0), Number(secondaryKey ? item[secondaryKey] || 0 : 0)]));
    return <section className="phase10-panel phase10-chart"><header><div><h2>{title}</h2><span>{hint}</span></div><span className="phase10-live">Live KPI</span></header><div className="phase10-bars" role="img" aria-label={title}>{items.length ? items.map((item, index) => <div className="phase10-bar-column" key={`${item.label}-${index}`} title={`${item.label}: ${formatter(item[valueKey])}`}><div className="phase10-bar-track">{secondaryKey && <i className="secondary" style={{ height: `${Math.max(3, Number(item[secondaryKey] || 0) / max * 100)}%` }} />}<i style={{ height: `${Math.max(3, Number(item[valueKey] || 0) / max * 100)}%` }} /></div><small>{item.label}</small></div>) : <span className="muted">No chart data</span>}</div>{secondaryKey && <footer><span><i className="legend-primary" />{primaryLabel}</span><span><i className="legend-secondary" />{secondaryLabel}</span></footer>}</section>;
}

function SignalPanel({ title, hint, items = [], empty = 'Everything is on track.' }) {
    return <section className="phase10-panel phase10-attention"><header><div><h2>{title}</h2><span>{hint}</span></div><AlertCircle size={18} /></header>{items.length ? items.map((item) => <div key={item.label}><span>{item.label}</span><strong className={item.tone || ''}>{item.value}</strong></div>) : <p className="phase10-empty">{empty}</p>}</section>;
}

function OwnerView({ data, charts }) {
    const k = data.kpis || {};
    const liquidity = Number(k.cash_balance || 0) + Number(k.bank_balance || 0);
    const operatingCost = Number(k.vehicle_cost || 0) + Number(k.salary_cost || 0) + Number(k.outdoor_expense || 0);
    const metrics = [
        ['Month sales', money(k.monthly_sales), `${number(k.target_achievement)}% of target`, 'accent', CircleDollarSign],
        ['Net profit', money(k.net_profit), 'Revenue less operating cost', Number(k.net_profit) >= 0 ? 'success' : 'danger', CircleDollarSign],
        ['Liquidity', money(liquidity), 'Cash plus bank balance', '', WalletCards],
        [mt("Outstanding"), money(k.outstanding_credit), 'Customer receivables', 'warning', Clock3],
        ['Warehouse value', money(k.warehouse_value), 'Current stock value', '', Package],
        ['Operating cost', money(operatingCost), 'Payroll, vehicle and field cost', '', ArrowUpRight],
    ];
    const attention = Object.entries(data.attention || {}).filter(([, value]) => Number(value) > 0).map(([label, value]) => ({ label: titleCase(label), value, tone: 'warning' }));
    return <><div className="phase10-metrics owner">{metrics.map(([label, value, hint, tone, icon]) => <Metric key={label} label={label} value={value} hint={hint} tone={tone} icon={icon} />)}</div><div className="phase10-grid two"><BarChart title="Six-month sales" hint="Monthly invoice revenue" items={charts.sales_trend} formatter={money} /><SignalPanel title="Needs attention" hint="Current operational exceptions" items={attention} /></div></>;
}

function SalesView({ data, charts }) {
    const s = data.summary || {};
    const targetGap = Math.max(0, Number(s.target || 0) - Number(s.sales || 0));
    const belowTarget = (data.ranking || []).filter((item) => Number(item.achievement || 0) < 100).length;
    const topArea = [...(data.area_sales || [])].sort((a, b) => Number(b.sales || b.value || 0) - Number(a.sales || a.value || 0))[0];
    const signals = [{ label: 'Remaining target', value: money(targetGap), tone: targetGap > 0 ? 'warning' : 'success' }, { label: 'Representatives below target', value: belowTarget, tone: belowTarget ? 'warning' : 'success' }, { label: 'Leading territory', value: topArea?.area_name || topArea?.label || 'No sales yet' }];
    return <><div className="phase10-metrics"><Metric label="Monthly sales" value={money(s.sales)} hint={`${s.orders || 0} invoiced orders`} tone="accent" icon={CircleDollarSign} /><Metric label="Target" value={money(s.target)} hint={`${number(s.achievement)}% achieved`} icon={Target} /><Metric label={mt("New customers")} value={s.new_customers || 0} hint="Added this month" icon={Users} /><Metric label="Territories" value={(data.area_sales || []).length} hint="Areas with sales" icon={ArrowUpRight} /></div><div className="phase10-grid two"><BarChart title="Target vs actual" hint="Representative performance this month" items={charts.target} valueKey="actual" secondaryKey="target" formatter={money} /><SignalPanel title="Sales focus" hint="Items that need a quick review" items={signals} /></div></>;
}

function StockView({ data, charts }) {
    const s = data.summary || {};
    const alertItems = (data.alerts || []).slice(0, 5).map((item) => ({ label: item.product_name || item.name || item.sku, value: `${number(item.quantity)} on hand`, tone: 'warning' }));
    return <><div className="phase10-metrics"><Metric label="Stock value" value={money(s.stock_value)} hint="Across all warehouses" tone="accent" icon={WalletCards} /><Metric label="On-hand quantity" value={number(s.quantity)} hint={`${s.products || 0} products`} icon={Package} /><Metric label="Warehouses" value={s.warehouses || 0} hint="Active stock locations" icon={Package} /><Metric label="Stock alerts" value={s.alerts || 0} hint="At or below threshold" tone="warning" icon={AlertCircle} /></div><div className="phase10-grid two"><BarChart title="Fast-moving products" hint="Monthly invoiced quantity" items={charts.movement} formatter={number} /><SignalPanel title="Replenishment attention" hint="Lowest available products" items={alertItems} empty="No stock alerts for the current month." /></div></>;
}

function DeliveryView({ data, charts }) {
    const s = data.summary || {};
    const outstanding = Math.max(0, Number(s.deliveries || 0) - Number(s.completed || 0));
    const signals = [{ label: 'Active deliveries', value: s.active || 0, tone: Number(s.active || 0) ? 'warning' : 'success' }, { label: 'Not completed', value: outstanding, tone: outstanding ? 'warning' : 'success' }, { label: 'Vehicles used', value: s.vehicles_used || 0 }, { label: 'Distance travelled', value: `${number(s.distance_km)} km` }];
    return <><div className="phase10-metrics"><Metric label={mt("Deliveries")} value={s.deliveries || 0} hint={`${s.active || 0} active`} tone="accent" icon={Truck} /><Metric label={mt("Completed")} value={s.completed || 0} hint="Delivered or partial" tone="success" icon={CheckCircle2} /><Metric label={mt("Delivered quantity")} value={number(s.delivered_quantity)} hint={`${number(s.distance_km)} km travelled`} icon={Package} /><Metric label="Delivery cost" value={money(s.delivery_cost)} hint={`${s.vehicles_used || 0} vehicles used`} icon={CircleDollarSign} /></div><div className="phase10-grid two"><BarChart title="Driver output" hint="Delivered quantity this month" items={charts.driver_output} formatter={number} /><SignalPanel title="Delivery focus" hint="Current execution status" items={signals} /></div></>;
}

function FinanceView({ data, charts }) {
    const s = data.summary || {};
    const pending = Object.entries(data.pending || {}).filter(([, value]) => Number(value) > 0).map(([label, value]) => ({ label: titleCase(label), value, tone: 'warning' }));
    return <><div className="phase10-metrics finance"><Metric label="Revenue" value={money(s.revenue)} hint="Invoiced this month" tone="accent" icon={CircleDollarSign} /><Metric label={mt("Collections")} value={money(s.collections)} hint={`${money(s.field_collections)} field`} tone="success" icon={WalletCards} /><Metric label="Debt balance" value={money(s.debt_balance)} hint="Customer receivable" tone="warning" icon={Clock3} /><Metric label={mt("Expenses")} value={money(s.expenses)} hint="Approved operating cost" icon={ArrowUpRight} /><Metric label="Net profit" value={money(s.profit)} hint="Revenue less expenses" tone={Number(s.profit) >= 0 ? 'success' : 'danger'} icon={CircleDollarSign} /><Metric label="Liquidity" value={money(Number(s.cash_balance || 0) + Number(s.bank_balance || 0))} hint="Cash plus bank" icon={WalletCards} /></div><div className="phase10-grid two"><BarChart title="Profit trend" hint="Six-month revenue and expense" items={charts.profit_trend} valueKey="revenue" secondaryKey="expense" formatter={money} primaryLabel="Revenue" secondaryLabel="Expense" /><SignalPanel title="Pending finance review" hint="Submitted records awaiting action" items={pending} empty="No pending finance submissions." /></div></>;
}

export function OfficeKpiDashboard({ kind = 'owner', canViewOperationsReport = false, locale = 'en', onNavigate }) {
    const [refresh, setRefresh] = useState(0);
    const [state, setState] = useState({ loading: true, data: {}, charts: {}, error: '' });
    const period = useMemo(() => ({ date_from: currentMonthStart(), date_to: today() }), [kind]);

    useEffect(() => {
        let active = true;
        setState((value) => ({ ...value, loading: true, error: '' }));
        Promise.all([
            window.axios.get(dashboardApi(kind), { params: period }),
            window.axios.get(chartApi(kind), { params: period }),
        ]).then(([summary, chart]) => active && setState({ loading: false, data: summary.data.data, charts: chart.data.data, error: '' }))
            .catch((error) => active && setState({ loading: false, data: {}, charts: {}, error: errorText(error) }));
        return () => { active = false; };
    }, [kind, period, refresh]);

    const definition = definitions[kind];
    const officePath = window.ValleyRuntime?.routes?.office || '/office';
    const drilldowns = {
        owner: { label: 'Operational report', path: '/reports/operations', icon: Rows3 },
        sales: { label: 'View orders', path: '/orders', icon: ShoppingCart },
        stock: { label: 'Review stock', path: '/stock/overview/balance', icon: Package },
        delivery: { label: 'View trips', path: '/deliveries', icon: Truck },
        finance: { label: 'Open profit & loss', path: '/finance/profit-loss', icon: WalletCards },
    };
    const drilldown = drilldowns[kind];
    const DrilldownIcon = drilldown.icon;
    const navigateTo = (suffix) => onNavigate?.(`${officePath}${suffix}`);
    const combinedKpi = kind !== 'owner';
    const kpiTabs = [
        { id: 'sales', label: 'Sales', icon: CircleGauge },
        { id: 'stock', label: 'Stock', icon: Package },
        { id: 'delivery', label: 'Delivery', icon: Truck },
        { id: 'finance', label: 'Finance', icon: WalletCards },
    ];
    const openKpi = (nextKind) => navigateTo(`/dashboards/kpis/${nextKind}`);

    return <section className="page phase10-page">
        <div className="master-heading phase10-heading">
            <div>
                <p className="eyebrow">{tr(locale, 'Executive overview', 'စီမံခန့်ခွဲမှု အနှစ်ချုပ်')}</p>
                <h1>{combinedKpi ? 'KPI dashboards' : tr(locale, definition.title, definition.my)}</h1>
                <span className="muted">{combinedKpi ? 'Review sales, stock, delivery, and finance performance in one workspace.' : definition.hint}</span>
                <span className="phase10-period"><CalendarDays size={13} />Current month · {period.date_from} to {period.date_to}</span>
            </div>
            <ShellPageActions>
                <button className="button primary" type="button" onClick={() => navigateTo(drilldown.path)}><DrilldownIcon size={15} />{drilldown.label}</button>
                {canViewOperationsReport && kind !== 'owner' && <button className="button" type="button" onClick={() => navigateTo('/reports/operations')}><Rows3 size={15} />Detailed report</button>}
                <button className="icon-button" type="button" aria-label="Refresh KPI overview" title="Refresh KPI overview" disabled={state.loading} onClick={() => setRefresh((value) => value + 1)}><RefreshCw className={state.loading ? 'spin' : ''} size={15} /></button>
            </ShellPageActions>
        </div>
        {combinedKpi && <nav className="customer-history-tabs phase10-kpi-tabs" aria-label="KPI dashboard">
            {kpiTabs.map(({ id, label, icon: Icon }) => <button className={kind === id ? 'is-active' : ''} type="button" onClick={() => openKpi(id)} aria-current={kind === id ? 'page' : undefined} key={id}><Icon size={15} />{label} KPI</button>)}
        </nav>}
        {state.loading ? <State loading text="Calculating dashboard KPIs" /> : state.error ? <p className="inline-error"><AlertCircle size={15} />{state.error}</p> : <>{kind === 'owner' && <OwnerView data={state.data} charts={state.charts} />}{kind === 'sales' && <SalesView data={state.data} charts={state.charts} />}{kind === 'stock' && <StockView data={state.data} charts={state.charts} />}{kind === 'delivery' && <DeliveryView data={state.data} charts={state.charts} />}{kind === 'finance' && <FinanceView data={state.data} charts={state.charts} />}</>}
    </section>;
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
            {products.length ? products.map((product, index) => <article key={product.product_id}><span className="mobile-kpi-rank">{index + 1}</span><span><strong>{product.name}</strong><small>{product.sku} · {number(product.quantity)} {mt("units")}</small></span><strong>{money(product.sales)}</strong></article>) : <p className="phase10-empty">{tr(locale, 'No product sales in this period.', 'ဤကာလအတွင်း ပစ္စည်းအရောင်းမရှိပါ။')}</p>}
        </section>
    </div>;
}

const clientContactDefinitions = [
    ['phone', 'Phone', Phone],
    ['email', 'Email', Mail],
    ['website', 'Website', Globe2],
    ['facebook', 'Facebook', Share2],
    ['viber', 'Viber', MessageCircle],
    ['telegram', 'Telegram', Send],
    ['whatsapp', 'WhatsApp', MessageCircle],
];

function clientContactHref(channel, value) {
    const cleanValue = String(value || '').trim();
    if (channel === 'phone') return `tel:${cleanValue}`;
    if (channel === 'email') return `mailto:${cleanValue}`;
    if (channel === 'telegram') return /^https?:\/\//i.test(cleanValue) ? cleanValue : `https://t.me/${cleanValue.replace(/^@/, '')}`;
    if (channel === 'whatsapp') return /^https?:\/\//i.test(cleanValue) ? cleanValue : `https://wa.me/${cleanValue.replace(/\D/g, '')}`;
    if (channel === 'viber') return /^https?:\/\//i.test(cleanValue) ? cleanValue : `viber://chat?number=${encodeURIComponent(cleanValue.replace(/\s/g, ''))}`;
    return cleanValue;
}

function ClientContactChannels({ channels = {}, locale }) {
    const items = clientContactDefinitions
        .map(([key, label, Icon]) => ({ key, label, Icon, value: channels?.[key] }))
        .filter((item) => String(item.value || '').trim());

    if (!items.length) return null;

    return <section className="client-contact-channels">
        <header><div><h2>{tr(locale, 'Contact us', 'ဆက်သွယ်ရန်')}</h2><span>{tr(locale, 'Choose your preferred channel', 'နှစ်သက်ရာ ဆက်သွယ်ရေးလမ်းကြောင်းကို ရွေးပါ')}</span></div></header>
        <nav aria-label={tr(locale, 'Customer contact channels', 'ဖောက်သည်ဆက်သွယ်ရေးလမ်းကြောင်းများ')}>
            {items.map(({ key, label, Icon, value }) => <a href={clientContactHref(key, value)} target={['website', 'facebook', 'telegram', 'whatsapp'].includes(key) ? '_blank' : undefined} rel={['website', 'facebook', 'telegram', 'whatsapp'].includes(key) ? 'noreferrer' : undefined} key={key}>
                <span><Icon size={17} /></span><strong>{label}</strong><ChevronRight size={15} />
            </a>)}
        </nav>
    </section>;
}

export function MobileHomeDashboard({ appId, locale = 'en', quickLinks = [], onNavigate, onViewCustomer, onViewOrders, onViewTasks, onViewGps, onViewAttendance, onViewKpi, onCreateOrder, onViewVisits, onViewProducts, onViewBalance, onViewOrder, onViewTrip }) {
    const [filter, setFilter] = usePageState('phaseTen:filter:view1', { date_from: currentMonthStart(), date_to: today() });
    const [refresh, setRefresh] = useState(0);
    const [state, setState] = useState({ loading: true, data: {}, error: '' });
    const [kpiState, setKpiState] = useState({ loading: ['sales', 'driver'].includes(appId), result: null, error: '' });
    useEffect(() => {
        if (appId === 'sales' && (!filter.date_from || !filter.date_to || filter.date_from > filter.date_to)) {
            setState((current) => ({ ...current, loading: false, data: { ...current.data, summary: current.data.summary || {} } }));
            return;
        }
        let active = true;
        const params = appId === 'sales' ? filter : undefined;
        setState((current) => ({ ...current, loading: true, error: '' }));
        window.axios.get(mobileApi(), { params }).then(({ data }) => active && setState({ loading: false, data: data.data, error: '' })).catch((error) => active && setState({ loading: false, data: {}, error: errorText(error) }));
        return () => { active = false; };
    }, [appId, filter.date_from, filter.date_to, refresh]);
    useEffect(() => {
        if (!['sales', 'driver'].includes(appId)) return undefined;
        let active = true;
        setKpiState((current) => ({ ...current, loading: true, error: '' }));
        window.axios.get(mobileKpiApi(), { params: { month: today().slice(0, 7) } })
            .then(({ data }) => active && setKpiState({ loading: false, result: data.data.result || null, error: '' }))
            .catch((error) => active && setKpiState({ loading: false, result: null, error: errorText(error) }));
        return () => { active = false; };
    }, [appId, refresh]);
    const summary = state.data.summary || {};
    const content = useMemo(() => ({
        client: { eyebrow: tr(locale, 'Account overview', 'အကောင့်အနှစ်ချုပ်'), title: summary.current_order_code || tr(locale, 'No active order', 'လက်ရှိ အော်ဒါမရှိပါ'), meta: `${titleCase(summary.current_order_status)} · ${money(summary.outstanding_balance)}`, stats: [[mt("Recent orders"), summary.recent_orders_count || 0, Package], [mt("Outstanding"), money(summary.outstanding_balance), WalletCards], ['Payment pending', money(summary.pending_collection_amount), Clock3]], items: state.data.recent_orders || [] },
        sales: { eyebrow: tr(locale, 'Monthly order performance', 'လစဉ် အော်ဒါစွမ်းဆောင်ရည်'), title: money(summary.monthly_sales), meta: `${number(summary.achievement)}% of ${money(summary.target)}`, stats: [[mt("Today"), money(summary.daily_sales), CircleDollarSign], [mt("Period sales"), money(summary.period_sales), ArrowUpRight], [mt("Average order"), money(summary.average_order_value), Package], [mt("Period orders"), summary.period_orders || 0, Package], [mt("New customers"), summary.new_customers || 0, Users], [mt("Year sales"), money(summary.yearly_sales), CircleDollarSign]], items: state.data.trend || [] },
        driver: { eyebrow: tr(locale, 'Delivery shift', 'ပို့ဆောင်ရေး အလှည့်ကျ'), title: 'Delivery tasks', meta: `${summary.today_deliveries || 0} trips today · ${summary.completed_deliveries || 0} completed`, stats: [[mt("Total cash hold"), money(summary.cash_hold_amount), WalletCards], [mt("Expenses"), summary.submitted_expenses || 0, CircleDollarSign]], items: state.data.deliveries || [] },
    })[appId], [appId, locale, state.data, summary]);
    const invalidRange = !filter.date_from || !filter.date_to || filter.date_from > filter.date_to;
    const exportSalesCsv = () => {
        const rows = [
            ['Sales KPI Report', `${filter.date_from} to ${filter.date_to}`], ['Metric', 'Value'],
            ['Today sales', summary.daily_sales || 0], [mt("Period sales"), summary.period_sales || 0],
            [mt("Average order"), summary.average_order_value || 0], [mt("Period orders"), summary.period_orders || 0],
            ['Monthly sales', summary.monthly_sales || 0], [mt("Year sales"), summary.yearly_sales || 0], [],
            ['Top customers', 'Code', 'Sales'], ...(state.data.top_customers || []).map((item) => [item.name, item.code, item.sales]), [],
            ['Top products', 'SKU', mt("Quantity"), 'Sales'], ...(state.data.top_products || []).map((item) => [item.name, item.sku, item.quantity, item.sales]),
        ];
        const csv = rows.map((row) => row.map((value) => `"${String(value ?? '').replaceAll('"', '""')}"`).join(',')).join('\n');
        const href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
        const link = document.createElement('a');
        link.href = href;
        link.download = `sales-kpi-${filter.date_from}-${filter.date_to}.csv`;
        link.click();
        URL.revokeObjectURL(href);
    };
    if (state.loading && !state.data.summary) return <State loading text={mt("Loading your dashboard")} />;
    if (state.error && !state.data.summary) return <div role="alert"><p className="inline-error"><AlertCircle size={15} />{state.error}</p><button className="button" onClick={() => setRefresh((value) => value + 1)}>{mt("Retry")}</button></div>;
    if (appId === 'client') return <CustomerHome data={state.data} locale={locale} onCreateOrder={onCreateOrder} onViewProducts={onViewProducts} onViewOrder={onViewOrder} onViewBalance={onViewBalance}><ClientContactChannels channels={state.data.contact_channels} locale={locale} /></CustomerHome>;
    if (appId === 'driver') return <DriverHomeDashboard summary={summary} trips={state.data.today_trips || []} locale={locale} kpiState={kpiState} onViewTasks={onViewTasks} onViewGps={onViewGps} onViewAttendance={onViewAttendance} onViewKpi={onViewKpi} onViewTrip={onViewTrip} />;
    return <div className="mobile-kpi-home">{appId === 'sales' && <SalesToday summary={summary} locale={locale} onCreateOrder={onCreateOrder} onViewVisits={onViewVisits} onViewOrders={onViewOrders} />}{state.error && <p className="form-alert" role="alert">{state.error}</p>}{state.loading && <p role="status">Updating performance?</p>}{appId === 'sales' && <section className="mobile-kpi-toolbar"><div className="mobile-kpi-range"><label><span>{tr(locale, mt("From"), 'မှ')}</span><input type="date" value={filter.date_from} max={filter.date_to} onChange={(event) => setFilter((current) => ({ ...current, date_from: event.target.value }))} /></label><label><span>{tr(locale, mt("To"), 'အထိ')}</span><input type="date" value={filter.date_to} min={filter.date_from} onChange={(event) => setFilter((current) => ({ ...current, date_to: event.target.value }))} /></label><button className="icon-button" type="button" disabled={invalidRange} aria-label={tr(locale, 'Refresh report', 'အစီရင်ခံစာ ပြန်ဖွင့်ရန်')} onClick={() => setRefresh((value) => value + 1)}><RefreshCw size={15} /></button></div></section>}{appId === 'sales' && invalidRange && <p className="form-alert" role="alert">{tr(locale, 'Choose a start and end date, with the end date on or after the start date.', '\u1005\u101b\u1000\u103a\u1014\u103e\u1004\u1037\u103a \u1006\u102f\u1036\u1038\u101b\u1000\u103a\u1000\u102d\u102f \u1019\u103e\u1014\u103a\u1000\u1014\u103a\u1005\u103d\u102c \u101b\u103d\u1031\u1038\u1015\u102b\u104b')}</p>}{appId === 'sales' && <div className="mobile-performance-actions"><button className="button" type="button" disabled={state.loading || Boolean(state.error) || invalidRange} onClick={exportSalesCsv}><Download size={14} />{mt("Excel CSV")}</button><button className="button" type="button" disabled={state.loading || Boolean(state.error) || invalidRange} onClick={() => window.print()}><Printer size={14} />{mt("Print / PDF")}</button></div>}<section className={`mobile-kpi-hero ${appId}`}><span>{content.eyebrow}</span><h1>{content.title}</h1><p>{content.meta}</p>{appId === 'sales' && <div className="mobile-target"><i style={{ width: `${Math.min(100, Number(summary.achievement || 0))}%` }} /></div>}</section>{appId === 'sales' && <MobileHomeKpiSummary appId="sales" state={kpiState} onOpen={onViewKpi} />}<div className="mobile-kpi-stats">{content.stats.map(([label, value, icon]) => <MobileStat key={label} label={label} value={value} icon={icon} />)}</div>{appId === 'client' && <ClientContactChannels channels={state.data.contact_channels} locale={locale} />}{appId === 'sales' && <button className="mobile-home-attendance-link" type="button" onClick={onViewAttendance}><span><CalendarDays size={18} /></span><span><strong>{tr(locale, 'Record attendance', 'တက်ရောက်မှု မှတ်တမ်းတင်ရန်')}</strong><small>{tr(locale, 'Select a warehouse and verify your GPS location', 'ဂိုဒေါင်ရွေးပြီး GPS တည်နေရာ စစ်ဆေးပါ')}</small></span><ChevronRight size={17} /></button>}{appId === 'sales' && <MobileSalesCharts locale={locale} monthItems={state.data.month_trend} yearItems={state.data.year_trend} summary={summary} />}{appId === 'sales' && <MobileSalesBreakdowns locale={locale} customers={state.data.top_customers} products={state.data.top_products} onViewCustomer={onViewCustomer} />}{appId === 'sales' && quickLinks.length > 0 && <section className="mobile-home-links"><header><div><h2>{tr(locale, 'Important links', 'အရေးကြီး လုပ်ငန်းများ')}</h2><span>{tr(locale, 'Frequently used operations', 'မကြာခဏ အသုံးပြုသော လုပ်ငန်းများ')}</span></div></header><nav aria-label={tr(locale, 'Important links', 'အရေးကြီး လုပ်ငန်းများ')}>{quickLinks.map(({ view, label, href, icon: Icon }) => <a href={href} onClick={(event) => onNavigate?.(event, href)} key={view}><span><Icon size={17} /></span><strong>{label}</strong><ChevronRight size={15} /></a>)}</nav></section>}<section className="mobile-kpi-list"><header><div><h2>{appId === 'sales' ? mt("Six-month trend") : appId === 'driver' ? 'Recent trips' : mt("Recent orders")}</h2><span>{mt("Updated from office records")}</span></div><span className="phase10-live">{mt("Live")}</span></header>{content.items.length ? content.items.map((item, index) => <article key={item.id || `${item.label}-${index}`}><span className="mobile-kpi-row-icon">{appId === 'sales' ? <ArrowUpRight size={16} /> : appId === 'driver' ? <Truck size={16} /> : <Package size={16} />}</span><span><strong>{item.code || item.label}</strong><small>{item.route_name || item.date || titleCase(item.status)}</small></span><strong>{appId === 'sales' ? money(item.value) : appId === 'driver' ? number(item.quantity) : money(item.amount)}</strong></article>) : <p className="phase10-empty">{mt("No recent activity.")}</p>}</section></div>;
}
