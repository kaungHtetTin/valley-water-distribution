import { CalendarDays, Download, Package, Printer, RefreshCw, TrendingUp, Truck, Users, WalletCards } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { ShellPageActions } from './components/ShellPageActions';

const api = () => window.ValleyRuntime?.api?.operationsReports || '/api/reports/operations';
const money = (value) => `${Number(value || 0).toLocaleString()} MMK`;
const number = (value) => Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 2 });
const today = () => new Date().toISOString().slice(0, 10);
const monthStart = () => `${today().slice(0, 7)}-01`;

const sections = {
    route_sales: { label: 'Route sales', columns: [['code', 'Route'], ['name', 'Name'], ['invoices', 'Invoices'], ['amount', 'Sales', 'money']] },
    customer_sales: { label: 'Customer sales', columns: [['code', 'Customer'], ['name', 'Shop'], ['invoices', 'Invoices'], ['amount', 'Sales', 'money']] },
    product_sales: { label: 'Product sales', columns: [['code', 'Product'], ['name', 'Name'], ['quantity', 'Quantity'], ['amount', 'Sales', 'money']] },
    expenses: { label: 'Expenses', columns: [['code', 'Category'], ['name', 'Name'], ['records', 'Records'], ['amount', 'Amount', 'money']] },
    payroll: { label: 'Payroll', columns: [['code', 'Staff group'], ['gross', 'Gross', 'money'], ['deductions', 'Deductions', 'money'], ['amount', 'Net pay', 'money']] },
    drivers: { label: 'Driver performance', columns: [['code', 'Driver'], ['name', 'Name'], ['deliveries', 'Stops'], ['completion', 'Completion', 'percent'], ['quantity', 'Delivered'], ['returned', 'Returned'], ['damaged', 'Damaged']] },
    stock: { label: 'Stock movement', columns: [['code', 'Product'], ['name', 'Name'], ['warehouse', 'Warehouse'], ['in', 'In'], ['out', 'Out'], ['net', 'Net']] },
};

function formatValue(value, type) {
    if (type === 'money') return money(value);
    if (type === 'percent') return `${number(value)}%`;
    return typeof value === 'number' ? number(value) : (value ?? '—');
}

function Filter({ label, value, onChange, options }) {
    return <label><span>{label}</span><select value={value || ''} onChange={(event) => onChange(event.target.value)}><option value="">All</option>{(options || []).map((option) => <option value={option.id} key={option.id}>{option.label}</option>)}</select></label>;
}

function Metric({ icon: Icon, label, value, hint }) {
    return <article className="metric report-metric"><span>{label}<Icon size={16} /></span><strong>{value}</strong><small>{hint}</small></article>;
}

export function OperationsReportScreen({ locale = 'en' }) {
    const [filters, setFilters] = useState({ date_from: monthStart(), date_to: today(), route_id: '', customer_id: '', warehouse_id: '', employee_id: '', driver_id: '', vehicle_id: '' });
    const [applied, setApplied] = useState(filters);
    const [state, setState] = useState({ loading: true, error: '', data: null });
    const [section, setSection] = useState('route_sales');

    useEffect(() => {
        const controller = new AbortController();
        setState((current) => ({ ...current, loading: true, error: '' }));
        window.axios.get(api(), { params: applied, signal: controller.signal })
            .then(({ data }) => setState({ loading: false, error: '', data: data.data }))
            .catch((error) => {
                if (error.code === 'ERR_CANCELED') return;
                setState((current) => ({ ...current, loading: false, error: error.response?.data?.message || 'Report could not be loaded.' }));
            });
        return () => controller.abort();
    }, [applied]);

    const report = state.data || {};
    const summary = report.summary || {};
    const meta = report.meta || {};
    const rows = report.breakdowns?.[section] || [];
    const trendMax = useMemo(() => Math.max(1, ...(report.trend || []).flatMap((item) => [Number(item.sales || 0), Number(item.cash_in || 0), Number(item.expenses || 0)])), [report.trend]);

    const applyPreset = (preset) => {
        const end = new Date();
        const start = new Date(end);
        if (preset === 'day') start.setTime(end.getTime());
        if (preset === 'month') start.setDate(1);
        if (preset === 'year') { start.setMonth(0); start.setDate(1); }
        const next = { ...filters, date_from: start.toISOString().slice(0, 10), date_to: end.toISOString().slice(0, 10) };
        setFilters(next);
        setApplied(next);
    };
    const exportUrl = `${api()}/export?${new URLSearchParams({ ...Object.fromEntries(Object.entries(applied).filter(([, value]) => value !== '')), section }).toString()}`;

    return <section className="page operations-report-page">
        <header className="master-heading report-heading"><div><p className="eyebrow">REPORTING</p><h1>{locale === 'my' ? 'လုပ်ငန်းဆိုင်ရာ အစီရင်ခံစာ' : 'Operations report'}</h1><span className="muted">Review sales, stock, cash, expenses, payroll, delivery, routes, customers, and staff from one report.</span></div><ShellPageActions className="report-actions"><button className="button" type="button" onClick={() => window.open(exportUrl, '_blank')}><Download size={15} />CSV</button><button className="button" type="button" onClick={() => window.print()}><Printer size={15} />Print</button></ShellPageActions></header>

        <section className="master-panel report-filter-panel">
            <form onSubmit={(event) => { event.preventDefault(); setApplied({ ...filters }); }}>
                <div className="report-presets"><button type="button" onClick={() => applyPreset('day')}>Today</button><button type="button" onClick={() => applyPreset('month')}>This month</button><button type="button" onClick={() => applyPreset('year')}>This year</button></div>
                <label><span>From</span><input type="date" value={filters.date_from} max={filters.date_to} onChange={(event) => setFilters((current) => ({ ...current, date_from: event.target.value }))} /></label>
                <label><span>To</span><input type="date" value={filters.date_to} min={filters.date_from} onChange={(event) => setFilters((current) => ({ ...current, date_to: event.target.value }))} /></label>
                <Filter label="Route" value={filters.route_id} options={meta.routes} onChange={(value) => setFilters((current) => ({ ...current, route_id: value }))} />
                <Filter label="Customer" value={filters.customer_id} options={meta.customers} onChange={(value) => setFilters((current) => ({ ...current, customer_id: value }))} />
                <Filter label="Warehouse" value={filters.warehouse_id} options={meta.warehouses} onChange={(value) => setFilters((current) => ({ ...current, warehouse_id: value }))} />
                <Filter label="Staff" value={filters.employee_id} options={meta.employees} onChange={(value) => setFilters((current) => ({ ...current, employee_id: value }))} />
                <Filter label="Driver" value={filters.driver_id} options={meta.drivers} onChange={(value) => setFilters((current) => ({ ...current, driver_id: value }))} />
                <Filter label="Vehicle" value={filters.vehicle_id} options={meta.vehicles} onChange={(value) => setFilters((current) => ({ ...current, vehicle_id: value }))} />
                <button className="button primary" disabled={state.loading}><RefreshCw size={15} className={state.loading ? 'spin' : ''} />Run report</button>
            </form>
        </section>

        {state.error && <p className="form-alert">{state.error}</p>}
        <div className="metrics report-metrics">
            <Metric icon={TrendingUp} label="Sales" value={money(summary.sales)} hint={`${number(summary.orders)} orders · ${money(summary.average_order_value)} average`} />
            <Metric icon={Package} label="Stock movement" value={`${number(summary.stock_in)} in`} hint={`${number(summary.stock_out)} out · ${money(summary.stock_value)} current value`} />
            <Metric icon={WalletCards} label="Cash net" value={money(summary.cash_net)} hint={`${money(summary.cash_in)} in · ${money(summary.cash_out)} out`} />
            <Metric icon={CalendarDays} label="Operating result" value={money(summary.net_result)} hint={`${money(summary.expenses)} expenses · ${money(summary.payroll)} payroll`} />
            <Metric icon={Truck} label="Delivery completion" value={`${number(summary.delivery_completion)}%`} hint={`${number(summary.deliveries)} delivery stops`} />
            <Metric icon={Users} label="New customers" value={number(summary.new_customers)} hint={`${applied.date_from} to ${applied.date_to}`} />
        </div>

        <section className="master-panel report-trend-panel">
            <header className="report-panel-heading"><div><p className="eyebrow">REPORT TREND</p><h2>Period trend</h2><span>{report.period?.grouping === 'month' ? 'Monthly totals' : 'Daily totals'} · {applied.date_from} to {applied.date_to}</span></div></header>
            {state.loading ? <p className="report-empty">Loading report…</p> : !(report.trend || []).length ? <p className="report-empty">No trend data for this period.</p> : <div className="report-trend-scroll">{report.trend.map((item) => <article key={item.label}><div className="report-bars"><i className="sales" style={{ height: `${Math.max(2, Number(item.sales || 0) / trendMax * 100)}%` }} title={`Sales ${money(item.sales)}`} /><i className="cash" style={{ height: `${Math.max(2, Number(item.cash_in || 0) / trendMax * 100)}%` }} title={`Cash in ${money(item.cash_in)}`} /><i className="expense" style={{ height: `${Math.max(2, Number(item.expenses || 0) / trendMax * 100)}%` }} title={`Expense ${money(item.expenses)}`} /></div><small title={item.label}>{report.period?.grouping === 'month' ? item.label : item.label.slice(5)}</small></article>)}</div>}
            <footer><span><i className="sales" />Sales</span><span><i className="cash" />Cash in</span><span><i className="expense" />Expense</span></footer>
        </section>

        <section className="master-panel report-breakdown-panel">
            <header className="report-panel-heading"><div><p className="eyebrow">DETAILS</p><h2>Operational breakdown</h2><span>{sections[section].label} for the selected period</span></div><strong>{rows.length} records</strong></header>
            <div className="customer-history-tabs report-tabs" role="tablist">{Object.entries(sections).map(([key, config]) => <button className={section === key ? 'is-active' : ''} type="button" role="tab" aria-selected={section === key} onClick={() => setSection(key)} key={key}>{config.label}</button>)}</div>
            <div className="master-table-wrap"><table className="master-table operations-report-table"><thead><tr>{sections[section].columns.map(([, label, type]) => <th className={type ? 'numeric' : ''} key={label}>{label}</th>)}</tr></thead><tbody>{rows.length ? rows.map((row, index) => <tr key={`${row.code || row.name}-${index}`}>{sections[section].columns.map(([key, label, type]) => <td className={type ? 'numeric' : ''} key={label}>{formatValue(row[key], type)}</td>)}</tr>) : <tr><td className="report-empty" colSpan={sections[section].columns.length}>No records for this report section.</td></tr>}</tbody></table></div>
        </section>
    </section>;
}
