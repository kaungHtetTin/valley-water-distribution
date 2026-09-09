import { AlertCircle, ArrowUpRight, CalendarDays, CheckCircle2, ChevronRight, CircleDollarSign, Clock3, Package, RefreshCw, Target, Truck, Users, WalletCards } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

const dashboardApi = (kind) => window.ValleyRuntime?.api?.dashboards ? `${window.ValleyRuntime.api.dashboards}/${kind}` : `/api/dashboards/${kind}`;
const chartApi = (kind) => window.ValleyRuntime?.api?.dashboardCharts ? `${window.ValleyRuntime.api.dashboardCharts}/${kind}` : `/api/dashboard-charts/${kind}`;
const mobileApi = () => window.ValleyRuntime?.api?.mobileDashboard || '/api/mobile/dashboard';
const today = () => new Date().toISOString().slice(0, 10);
const month = () => today().slice(0, 7);
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
        ['Today sales', money(k.today_sales), 'Current trading day', 'accent', CircleDollarSign], ['Month sales', money(k.monthly_sales), `${number(k.target_achievement)}% of target`, 'success', Target],
        ['Year sales', money(k.annual_sales), 'Year to date', '', ArrowUpRight], ['Cash', money(k.cash_balance), 'Available cash book balance', '', WalletCards],
        ['Bank', money(k.bank_balance), 'Available bank balance', '', WalletCards], ['Outstanding', money(k.outstanding_credit), 'Customer receivable', 'warning', Clock3],
        ['Warehouse value', money(k.warehouse_value), `${k.customer_count || 0} active customers`, '', Package], ['Vehicle cost', money(k.vehicle_cost), 'Year to date', '', Truck],
        ['Salary cost', money(k.salary_cost), 'Approved and paid payroll', '', Users], ['Outdoor expense', money(k.outdoor_expense), 'Approved field expense', '', ArrowUpRight],
        ['Net profit', money(k.net_profit), 'Revenue less operating cost', Number(k.net_profit) >= 0 ? 'success' : 'danger', CircleDollarSign], ['Field collection', money(k.field_collection), 'Current month', '', WalletCards],
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
    const [filter, setFilter] = useState(kind === 'owner' ? today() : month());
    const [refresh, setRefresh] = useState(0);
    const [state, setState] = useState({ loading: true, data: {}, charts: {}, error: '' });
    useEffect(() => { let active = true; setState((value) => ({ ...value, loading: true, error: '' })); const params = kind === 'owner' ? { date: filter } : { month: filter }; Promise.all([window.axios.get(dashboardApi(kind), { params }), window.axios.get(chartApi(kind), { params: { ...params, date: kind === 'owner' ? filter : `${filter}-01` } })]).then(([summary, chart]) => active && setState({ loading: false, data: summary.data.data, charts: chart.data.data, error: '' })).catch((error) => active && setState({ loading: false, data: {}, charts: {}, error: errorText(error) })); return () => { active = false; }; }, [kind, filter, refresh]);
    const definition = definitions[kind];
    return <section className="page phase10-page"><div className="master-heading phase10-heading"><div><p className="eyebrow">{tr(locale, 'Phase 10 · Executive intelligence', 'အဆင့် ၁၀ · စီမံခန့်ခွဲမှု အချက်အလက်')}</p><h1>{tr(locale, definition.title, definition.my)}</h1><span className="muted">{definition.hint}</span></div><div className="phase10-filter"><CalendarDays size={15} /><input aria-label={kind === 'owner' ? 'Dashboard date' : 'Dashboard month'} type={kind === 'owner' ? 'date' : 'month'} value={filter} onChange={(event) => setFilter(event.target.value)} /><button className="icon-button" aria-label="Refresh dashboard" onClick={() => setRefresh((value) => value + 1)}><RefreshCw size={15} /></button></div></div>{state.loading ? <State loading text="Calculating dashboard KPIs" /> : state.error ? <p className="inline-error"><AlertCircle size={15} />{state.error}</p> : <>{kind === 'owner' && <OwnerView data={state.data} charts={state.charts} />}{kind === 'sales' && <SalesView data={state.data} charts={state.charts} />}{kind === 'stock' && <StockView data={state.data} charts={state.charts} />}{kind === 'delivery' && <DeliveryView data={state.data} charts={state.charts} />}{kind === 'finance' && <FinanceView data={state.data} charts={state.charts} />}</>}</section>;
}

function MobileStat({ label, value, icon: Icon }) {
    return <article><span><Icon size={15} />{label}</span><strong>{value}</strong></article>;
}

export function MobileHomeDashboard({ appId, locale = 'en', quickLinks = [], onNavigate }) {
    const [state, setState] = useState({ loading: true, data: {}, error: '' });
    useEffect(() => { window.axios.get(mobileApi()).then(({ data }) => setState({ loading: false, data: data.data, error: '' })).catch((error) => setState({ loading: false, data: {}, error: errorText(error) })); }, []);
    const summary = state.data.summary || {};
    const content = useMemo(() => ({
        client: { eyebrow: tr(locale, 'Account overview', 'အကောင့်အနှစ်ချုပ်'), title: summary.current_order_code || tr(locale, 'No active order', 'လက်ရှိ အော်ဒါမရှိပါ'), meta: `${titleCase(summary.current_order_status)} · ${money(summary.outstanding_balance)}`, stats: [['Recent orders', summary.recent_orders_count || 0, Package], ['Outstanding', money(summary.outstanding_balance), WalletCards]], items: state.data.recent_orders || [] },
        sales: { eyebrow: tr(locale, 'Monthly performance', 'လစဉ် အရောင်းစွမ်းဆောင်ရည်'), title: money(summary.monthly_sales), meta: `${number(summary.achievement)}% of ${money(summary.target)}`, stats: [['New customers', summary.new_customers || 0, Users], ['Collections', money(summary.collections), WalletCards], ['Assigned route', summary.assigned_route || '—', Truck]], items: state.data.trend || [] },
        driver: { eyebrow: tr(locale, 'Delivery shift', 'ပို့ဆောင်ရေး အလှည့်ကျ'), title: summary.assigned_route || 'No route assigned', meta: `${summary.today_deliveries || 0} today · ${summary.completed_deliveries || 0} completed`, stats: [['Delivered qty', number(summary.delivered_quantity), Package], ['Expenses', summary.submitted_expenses || 0, CircleDollarSign], ['Collections', summary.submitted_collections || 0, WalletCards]], items: state.data.deliveries || [] },
    })[appId], [appId, locale, state.data, summary]);
    if (state.loading) return <State loading text="Loading your dashboard" />;
    if (state.error) return <p className="inline-error"><AlertCircle size={15} />{state.error}</p>;
    return <div className="mobile-kpi-home"><section className={`mobile-kpi-hero ${appId}`}><span>{content.eyebrow}</span><h1>{content.title}</h1><p>{content.meta}</p>{appId === 'sales' && <div className="mobile-target"><i style={{ width: `${Math.min(100, Number(summary.achievement || 0))}%` }} /></div>}</section><div className="mobile-kpi-stats">{content.stats.map(([label, value, icon]) => <MobileStat key={label} label={label} value={value} icon={icon} />)}</div>{appId === 'sales' && quickLinks.length > 0 && <section className="mobile-home-links"><header><div><h2>{tr(locale, 'Important links', 'အရေးကြီး လုပ်ငန်းများ')}</h2><span>{tr(locale, 'Frequently used operations', 'မကြာခဏ အသုံးပြုသော လုပ်ငန်းများ')}</span></div></header><nav aria-label={tr(locale, 'Important links', 'အရေးကြီး လုပ်ငန်းများ')}>{quickLinks.map(({ view, label, href, icon: Icon }) => <a href={href} onClick={(event) => onNavigate?.(event, href)} key={view}><span><Icon size={17} /></span><strong>{label}</strong><ChevronRight size={15} /></a>)}</nav></section>}<section className="mobile-kpi-list"><header><div><h2>{appId === 'sales' ? 'Six-month trend' : appId === 'driver' ? 'Recent deliveries' : 'Recent orders'}</h2><span>Updated from office records</span></div><span className="phase10-live">Live</span></header>{content.items.length ? content.items.map((item, index) => <article key={item.id || `${item.label}-${index}`}><span className="mobile-kpi-row-icon">{appId === 'sales' ? <ArrowUpRight size={16} /> : appId === 'driver' ? <Truck size={16} /> : <Package size={16} />}</span><span><strong>{item.code || item.label}</strong><small>{item.route_name || item.date || titleCase(item.status)}</small></span><strong>{appId === 'sales' ? money(item.value) : appId === 'driver' ? number(item.quantity) : money(item.amount)}</strong></article>) : <p className="phase10-empty">No recent activity.</p>}</section></div>;
}
