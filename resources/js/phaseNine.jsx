import { FilterToolbar } from './ui/FilterToolbar';
import { currentLocale, businessToday, formatMoney as uiMoney } from './ui/format.js';
import { reportCopy } from './ui/reportCopy';
import { usePageState } from './ui/usePageState';
import { CalendarDays, Download, Package, Printer, RefreshCw, TrendingUp, Truck, Users, WalletCards } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { ShellPageActions } from './components/ShellPageActions';

const translate = (label, locale) => locale === 'my' ? (reportCopy[label] || label) : label;
const api = () => window.ValleyRuntime?.api?.operationsReports || '/api/reports/operations';
const money = uiMoney;
const number = (value) => Number(value || 0).toLocaleString(currentLocale(), { maximumFractionDigits: 2 });
const today = () => businessToday();
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

function Filter({ label, value, onChange, options, locale }) {
    return <label><span>{label}</span><select value={value || ''} onChange={(event) => onChange(event.target.value)}><option value="">{translate('All', locale)}</option>{(options || []).map((option) => <option value={option.id} key={option.id}>{option.label}</option>)}</select></label>;
}

function Metric({ icon: Icon, label, value, hint }) {
    return <article className="metric report-metric"><span>{label}<Icon size={16} /></span><strong>{value}</strong><small>{hint}</small></article>;
}

export function OperationsReportScreen({ locale = 'en' }) {
    const t = (label) => translate(label, locale);
    const [filters, setFilters] = usePageState('phaseNine:filters:1', { date_from: monthStart(), date_to: today(), route_id: '', customer_id: '', warehouse_id: '', employee_id: '', driver_id: '', vehicle_id: '' });
    const [applied, setApplied] = usePageState('phaseNine:applied:2', filters);
    const [state, setState] = useState({ loading: true, error: '', data: null });
    const [section, setSection] = usePageState('phaseNine:section:3', 'route_sales');

    useEffect(() => {
        const controller = new AbortController();
        setState((current) => ({ ...current, loading: true, error: '' }));
        window.axios.get(api(), { params: applied, signal: controller.signal })
            .then(({ data }) => setState({ loading: false, error: '', data: data.data }))
            .catch((error) => {
                if (error.code === 'ERR_CANCELED') return;
                setState((current) => ({ ...current, loading: false, error: error.response?.data?.message || t('Report could not be loaded.') }));
            });
        return () => controller.abort();
    }, [applied, locale]);

    const report = state.data || {};
    const summary = report.summary || {};
    const meta = report.meta || {};
    const rows = report.breakdowns?.[section] || [];
    const trendMax = useMemo(() => Math.max(1, ...(report.trend || []).flatMap((item) => [Number(item.sales || 0), Number(item.cash_in || 0), Number(item.expenses || 0)])), [report.trend]);

    const applyPreset = (preset) => {
        const end = today();
        const start = preset === 'year' ? `${end.slice(0, 4)}-01-01` : preset === 'month' ? `${end.slice(0, 7)}-01` : end;
        const next = { ...filters, date_from: start, date_to: end };
        setFilters(next);
        setApplied(next);
    };
    const exportUrl = `${api()}/export?${new URLSearchParams({ ...Object.fromEntries(Object.entries(applied).filter(([, value]) => value !== '')), section }).toString()}`;

    return <section className="page operations-report-page">
        <header className="master-heading report-heading"><div><p className="eyebrow">{t('REPORTING')}</p><h1>{t('Operations report')}</h1><span className="muted">{t('Review sales, stock, cash, expenses, payroll, delivery, routes, customers, and staff from one report.')}</span></div><ShellPageActions className="report-actions"><button className="button" type="button" disabled={state.loading || Boolean(state.error)} title={section === 'customer_sales' ? t('Export one row per invoice item using the applied filters.') : t('CSV')} onClick={() => window.open(exportUrl, '_blank')}><Download size={15} />{t(section === 'customer_sales' ? 'Item CSV' : 'CSV')}</button><button className="button" type="button" disabled={state.loading || Boolean(state.error)} onClick={() => window.print()}><Printer size={15} />{t('Print')}</button></ShellPageActions></header>

        <section className="master-panel report-filter-panel">
            <FilterToolbar as="form" className="report-filters" aria-label={t('Report filters')} data-filter-mode="apply" onSubmit={(event) => { event.preventDefault(); setApplied({ ...filters }); }}>
                <div className="report-filter-scroll"><div className="report-filter-fields"><div className="report-presets"><button type="button" onClick={() => applyPreset('day')}>{t('Today')}</button><button type="button" onClick={() => applyPreset('month')}>{t('This month')}</button><button type="button" onClick={() => applyPreset('year')}>{t('This year')}</button></div>
                <label><span>{t('From')}</span><input type="date" value={filters.date_from} max={filters.date_to} onChange={(event) => setFilters((current) => ({ ...current, date_from: event.target.value }))} /></label>
                <label><span>{t('To')}</span><input type="date" value={filters.date_to} min={filters.date_from} onChange={(event) => setFilters((current) => ({ ...current, date_to: event.target.value }))} /></label>
                <Filter locale={locale} label={t('Route')} value={filters.route_id} options={meta.routes} onChange={(value) => setFilters((current) => ({ ...current, route_id: value }))} />
                <Filter locale={locale} label={t('Customer')} value={filters.customer_id} options={meta.customers} onChange={(value) => setFilters((current) => ({ ...current, customer_id: value }))} />
                <Filter locale={locale} label={t('Warehouse')} value={filters.warehouse_id} options={meta.warehouses} onChange={(value) => setFilters((current) => ({ ...current, warehouse_id: value }))} />
                <Filter locale={locale} label={t('Staff')} value={filters.employee_id} options={meta.employees} onChange={(value) => setFilters((current) => ({ ...current, employee_id: value }))} />
                <Filter locale={locale} label={t('Driver')} value={filters.driver_id} options={meta.drivers} onChange={(value) => setFilters((current) => ({ ...current, driver_id: value }))} />
                <Filter locale={locale} label={t('Vehicle')} value={filters.vehicle_id} options={meta.vehicles} onChange={(value) => setFilters((current) => ({ ...current, vehicle_id: value }))} />
                </div></div><div className="report-filter-actions"><button className="button primary" disabled={state.loading}><RefreshCw size={15} className={state.loading ? 'spin' : ''} />{t('Run report')}</button><button className="button" type="button" onClick={() => { const next = { date_from: monthStart(), date_to: today(), route_id: '', customer_id: '', warehouse_id: '', employee_id: '', driver_id: '', vehicle_id: '' }; setFilters(next); setApplied(next); }}>{t('Clear filters')}</button></div>
            </FilterToolbar><p className="filter-mode-hint" role="status">{t(JSON.stringify(filters) === JSON.stringify(applied) ? 'Filters applied' : 'Changes not applied')} · {t('Apply filters to update this report.')}</p>
        </section>

        {state.error && <p className="form-alert" role="alert">{state.error} <button className="button" onClick={() => setApplied({ ...applied })}>{t('Retry')}</button></p>}
        <div className="metrics report-metrics" aria-busy={state.loading}>
            <Metric icon={TrendingUp} label={t('Sales')} value={money(summary.sales)} hint={`${number(summary.orders)} ${t('orders')} · ${money(summary.average_order_value)} ${t('average')}`} />
            <Metric icon={Package} label={t('Stock movement')} value={`${number(summary.stock_in)} ${t('in')}`} hint={`${number(summary.stock_out)} ${t('out')} · ${money(summary.stock_value)} ${t('current value')}`} />
            <Metric icon={WalletCards} label={t('Cash net')} value={money(summary.cash_net)} hint={`${money(summary.cash_in)} ${t('in')} · ${money(summary.cash_out)} ${t('out')}`} />
            <Metric icon={CalendarDays} label={t('Operating result')} value={money(summary.net_result)} hint={`${money(summary.expenses)} ${t('expenses')} · ${money(summary.payroll)} ${t('payroll')}`} />
            <Metric icon={Truck} label={t('Delivery completion')} value={`${number(summary.delivery_completion)}%`} hint={`${number(summary.deliveries)} ${t('delivery stops')}`} />
            <Metric icon={Users} label={t('New customers')} value={number(summary.new_customers)} hint={`${applied.date_from} ${t('to')} ${applied.date_to}`} />
        </div>

        <section className="master-panel report-trend-panel">
            <header className="report-panel-heading"><div><p className="eyebrow">{t('REPORT TREND')}</p><h2>{t('Period trend')}</h2><span>{report.period?.grouping === 'month' ? t('Monthly totals') : t('Daily totals')} · {applied.date_from} {t('to')} {applied.date_to}</span></div></header>
            {state.loading ? <p className="report-empty">{t('Loading report…')}</p> : !(report.trend || []).length ? <p className="report-empty">{t('No trend data for this period.')}</p> : <div className="report-trend-scroll">{report.trend.map((item) => <article key={item.label}><div className="report-bars"><i className="sales" style={{ height: `${Math.max(2, Number(item.sales || 0) / trendMax * 100)}%` }} title={`${t('Sales')} ${money(item.sales)}`} /><i className="cash" style={{ height: `${Math.max(2, Number(item.cash_in || 0) / trendMax * 100)}%` }} title={`${t('Cash in')} ${money(item.cash_in)}`} /><i className="expense" style={{ height: `${Math.max(2, Number(item.expenses || 0) / trendMax * 100)}%` }} title={`${t('Expense')} ${money(item.expenses)}`} /></div><small title={item.label}>{report.period?.grouping === 'month' ? item.label : item.label.slice(5)}</small></article>)}</div>}
            <footer><span><i className="sales" />{t('Sales')}</span><span><i className="cash" />{t('Cash in')}</span><span><i className="expense" />{t('Expense')}</span></footer>
        </section>

        <section className="master-panel report-breakdown-panel">
            <header className="report-panel-heading"><div><p className="eyebrow">{t('DETAILS')}</p><h2>{t('Operational breakdown')}</h2><span>{t(sections[section].label)} {t('for the selected period')}</span></div><strong>{number(rows.length)} {t('records')}</strong></header>
            <div className="customer-history-tabs report-tabs" role="tablist">{Object.entries(sections).map(([key, config]) => <button className={section === key ? 'is-active' : ''} type="button" role="tab" aria-selected={section === key} onClick={() => setSection(key)} key={key}>{t(config.label)}</button>)}</div>
            <div className="master-table-wrap"><table className="master-table operations-report-table"><thead><tr>{sections[section].columns.map(([, label, type]) => <th className={type ? 'numeric' : ''} key={label}>{t(label)}</th>)}</tr></thead><tbody>{rows.length ? rows.map((row, index) => <tr key={`${row.code || row.name}-${index}`}>{sections[section].columns.map(([key, label, type]) => <td className={type ? 'numeric' : ''} key={label}>{formatValue(row[key], type)}</td>)}</tr>) : <tr><td className="report-empty" colSpan={sections[section].columns.length}>{t('No records for this report section.')}</td></tr>}</tbody></table></div>
        </section>
    </section>;
}
