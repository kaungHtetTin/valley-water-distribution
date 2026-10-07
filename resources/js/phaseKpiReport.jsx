import { AlertCircle, BadgeCheck, CalendarDays, ChevronDown, CircleGauge, Download, Printer, RefreshCw, Search, TrendingUp, Users, WalletCards } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { ShellPageActions } from './components/ShellPageActions';

const currentMonth = () => {
    const date = new Date();
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
};
const reportApi = () => window.ValleyRuntime?.api?.kpiReports || '/api/kpi-reports';
const money = (value) => `${Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 0 })} MMK`;
const number = (value) => Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 2 });
const titleCase = (value) => String(value || '').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
const initialFilters = () => ({ period: 'month', month: currentMonth(), year: currentMonth().slice(0, 4), employee_type: '', employee_id: '' });
const monthOptions = [
    ['01', 'January'], ['02', 'February'], ['03', 'March'], ['04', 'April'],
    ['05', 'May'], ['06', 'June'], ['07', 'July'], ['08', 'August'],
    ['09', 'September'], ['10', 'October'], ['11', 'November'], ['12', 'December'],
];
const yearOptions = Array.from({ length: new Date().getFullYear() - 2019 }, (_, index) => String(new Date().getFullYear() - index));

export function KpiReportsScreen() {
    const [draft, setDraft] = useState(initialFilters);
    const [filters, setFilters] = useState(initialFilters);
    const [refreshKey, setRefreshKey] = useState(0);
    const [state, setState] = useState({ loading: true, data: emptyReport(), error: '' });

    useEffect(() => {
        let mounted = true;
        setState((current) => ({ ...current, loading: true, error: '' }));
        window.axios.get(reportApi(), { params: {
            period: filters.period,
            month: filters.period === 'month' ? filters.month : undefined,
            year: filters.period === 'year' ? filters.year : filters.month.slice(0, 4),
            employee_type: filters.employee_type || undefined,
            employee_id: filters.employee_id || undefined,
        } }).then(({ data }) => {
            if (mounted) setState({ loading: false, data: data.data, error: '' });
        }).catch((error) => {
            if (mounted) setState({ loading: false, data: emptyReport(), error: error.response?.data?.message || 'Unable to load KPI report.' });
        });
        return () => { mounted = false; };
    }, [filters, refreshKey]);

    const visibleEmployees = useMemo(() => state.data.employees.filter((employee) => !draft.employee_type || employee.employee_type === draft.employee_type), [state.data.employees, draft.employee_type]);
    const periodLabel = filters.period === 'month' ? filters.month : filters.year;

    const applyFilters = (event) => {
        event.preventDefault();
        setFilters({ ...draft });
    };

    const exportExcel = () => {
        const rows = [
            ['KPI Performance Report'],
            ['Period', periodLabel],
            [],
            ['Summary'],
            ['Reviews', 'Average score %', 'Target bonus MMK', 'Bonus total MMK', 'Approved', 'Posted to payroll'],
            [state.data.summary.reviews, state.data.summary.average_score, state.data.summary.target_bonus_total, state.data.summary.bonus_total, state.data.summary.approved, state.data.summary.posted],
            [],
            ['Role summary'],
            ['Role', 'Reviews', 'Average score %', 'Approved', 'Bonus total MMK', 'Posted'],
            ...state.data.role_summary.map((role) => [role.template_name, role.reviews, role.average_score, role.approved, role.bonus_total, role.posted]),
            [],
            ['Approval status'],
            ['Status', 'Reviews', 'Bonus total MMK'],
            ...state.data.status_summary.map((status) => [titleCase(status.status), status.reviews, status.bonus_total]),
            [],
            ['Monthly score trend'],
            ['Month', 'Reviews', 'Average score %', 'Bonus total MMK'],
            ...state.data.monthly_trend.map((item) => [item.label, item.reviews, item.average_score, item.bonus_total]),
            [],
            ['Yearly score trend'],
            ['Year', 'Reviews', 'Average score %', 'Bonus total MMK'],
            ...state.data.yearly_trend.map((item) => [item.label, item.reviews, item.average_score, item.bonus_total]),
            [],
            ['Review details'],
            ['Review reference', 'Month', 'Employee code', 'Employee', 'Role', 'Score', 'Bonus MMK', 'Status', 'Payroll adjustment'],
            ...state.data.reviews.map((review) => [review.reference, review.month, review.employee_code, review.employee_name, review.template_name, review.overall_score, review.bonus_amount, titleCase(review.status), review.payroll_adjustment_reference || 'Not posted']),
            [],
            ['Metric code', 'Role', 'Metric', 'Unit', 'Average target', 'Average actual', 'Achievement %', 'Points', 'Weight %'],
            ...state.data.metric_breakdown.map((metric) => [metric.code, metric.template_name, metric.name, metric.unit, metric.target_average ?? '', metric.actual_average ?? '', metric.achievement_average ?? '', metric.points_average ?? '', metric.weight]),
        ];
        const csv = `\uFEFF${rows.map((row) => row.map(csvCell).join(',')).join('\r\n')}`;
        const link = document.createElement('a');
        link.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
        link.download = `kpi-report-${periodLabel}.csv`;
        link.click();
        URL.revokeObjectURL(link.href);
    };

    const summary = state.data.summary;

    return (
        <section className="master-workspace kpi-report-page">
            <div className="master-heading kpi-report-heading">
                <div><p className="eyebrow">Performance report</p><h1>KPI analysis</h1><span className="muted">Monthly and yearly scores, bonuses, approval progress, and metric results.</span></div>
            </div>

            <div className="master-panel kpi-report-filter-panel">
                <form className="kpi-report-filters" aria-label="KPI report filters" onSubmit={applyFilters}>
                    <div className="kpi-report-filter-scroll">
                        <div className="kpi-report-filter-fields">
                            <label><span>Period</span><select value={draft.period} onChange={(event) => setDraft((current) => ({ ...current, period: event.target.value }))}><option value="month">Monthly</option><option value="year">Yearly</option></select></label>
                            {draft.period === 'month' ? <>
                                <label><span>Year</span><select aria-label="KPI analysis year" value={draft.month.slice(0, 4)} onChange={(event) => setDraft((current) => ({ ...current, month: `${event.target.value}-${current.month.slice(5, 7)}` }))}>{yearOptions.map((year) => <option value={year} key={year}>{year}</option>)}</select></label>
                                <label><span>Month</span><select aria-label="KPI analysis month" value={draft.month.slice(5, 7)} onChange={(event) => setDraft((current) => ({ ...current, month: `${current.month.slice(0, 4)}-${event.target.value}` }))}>{monthOptions.map(([value, label]) => <option value={value} key={value}>{label}</option>)}</select></label>
                            </> : <label><span>Year</span><select required value={draft.year} onChange={(event) => setDraft((current) => ({ ...current, year: event.target.value }))}>{yearOptions.map((year) => <option value={year} key={year}>{year}</option>)}</select></label>}
                            <label className="kpi-report-staff-group"><span>Staff group</span><select value={draft.employee_type} onChange={(event) => setDraft((current) => ({ ...current, employee_type: event.target.value, employee_id: '' }))}><option value="">All KPI staff</option><option value="sales">Sales</option><option value="sales_supervisor">Sales Supervisor</option><option value="driver">Driver</option><option value="warehouse">Warehouse</option><option value="office">Office</option></select></label>
                            <label className="kpi-report-employee"><span>Employee</span><select value={draft.employee_id} onChange={(event) => setDraft((current) => ({ ...current, employee_id: event.target.value }))}><option value="">All employees</option>{visibleEmployees.map((employee) => <option value={employee.id} key={employee.id}>{employee.name} · {employee.code}</option>)}</select></label>
                        </div>
                    </div>
                    <div className="kpi-report-filter-actions">
                        <button className="button primary" type="submit" disabled={state.loading}><Search size={15} /> Apply</button>
                    </div>
                    <ShellPageActions>
                        <button className="button" type="button" disabled={state.loading || state.data.reviews.length === 0} onClick={exportExcel}><Download size={15} /> Excel</button>
                        <button className="button" type="button" disabled={state.loading || state.data.reviews.length === 0} onClick={() => window.print()}><Printer size={15} /> Print</button>
                        <button className="icon-button" type="button" aria-label="Refresh report" title="Refresh report" disabled={state.loading} onClick={() => setRefreshKey((key) => key + 1)}><RefreshCw className={state.loading ? 'spin' : ''} size={15} /></button>
                    </ShellPageActions>
                </form>
            </div>

            {state.error && <div className="inline-error"><AlertCircle size={15} /> {state.error}</div>}
            {state.loading ? <ReportState icon={RefreshCw} title="Preparing KPI report" loading /> : state.data.reviews.length === 0 ? <ReportState icon={CircleGauge} title="No KPI results for this period" message="Choose another month, year, role, or employee." /> : (
                <>
                    <div className="metrics kpi-report-metrics">
                        <div className="metric"><span>Reviews</span><strong>{summary.reviews}</strong><small>{periodLabel}</small></div>
                        <div className="metric"><span>Average score</span><strong>{number(summary.average_score)}%</strong><small>Across current view</small></div>
                        <div className="metric"><span>Bonus total</span><strong>{money(summary.bonus_total)}</strong><small>{summary.posted} posted to payroll</small></div>
                        <div className="metric"><span>Approval progress</span><strong>{summary.approved} / {summary.reviews}</strong><small>{number(summary.reviews ? summary.approved / summary.reviews * 100 : 0)}% approved</small></div>
                    </div>

                    <div className="kpi-report-chart-grid">
                        <TrendChart title="Monthly score trend" hint={`${filters.employee_id ? 'Employee' : 'Team'} average in ${state.data.period.year}`} items={state.data.monthly_trend} />
                        <TrendChart title="Yearly score trend" hint="Five-year average score" items={state.data.yearly_trend} />
                    </div>

                    <div className="kpi-report-chart-grid summary-grid">
                        <RoleSummary items={state.data.role_summary} />
                        <StatusSummary items={state.data.status_summary} total={summary.reviews} posted={summary.posted} />
                    </div>

                    <MetricBreakdown key={`${filters.period}-${filters.month}-${filters.year}-${filters.employee_type}-${filters.employee_id}`} items={state.data.metric_breakdown} roleSummary={state.data.role_summary} selectedType={filters.employee_type} employeeSelected={Boolean(filters.employee_id)} />
                    <ReviewTable items={state.data.reviews} />
                </>
            )}
        </section>
    );
}

function TrendChart({ title, hint, items }) {
    if (!items.length) return <ReportPanel title={title} hint={hint}><div className="kpi-report-empty">No trend data.</div></ReportPanel>;
    const width = 640;
    const height = 190;
    const left = 34;
    const right = 16;
    const top = 15;
    const bottom = 32;
    const plotWidth = width - left - right;
    const plotHeight = height - top - bottom;
    const points = items.map((item, index) => ({
        ...item,
        x: left + index * (plotWidth / Math.max(items.length - 1, 1)),
        y: top + plotHeight - Math.min(Math.max(Number(item.average_score), 0), 100) / 100 * plotHeight,
    }));
    return <ReportPanel title={title} hint={hint} aside={<strong>{number(items.at(-1)?.average_score)}%</strong>}>
        <div className="kpi-report-line-chart">
            <svg viewBox={`0 0 ${width} ${height}`} role="img" aria-label={title}>
                {[0, 25, 50, 75, 100].map((value) => {
                    const y = top + plotHeight - value / 100 * plotHeight;
                    return <g key={value}><line x1={left} x2={width - right} y1={y} y2={y} /><text x={left - 7} y={y + 3}>{value}</text></g>;
                })}
                <polyline points={points.map((point) => `${point.x},${point.y}`).join(' ')} />
                {points.map((point) => <g className="data-point" key={point.label}><circle cx={point.x} cy={point.y} r="4" /><text x={point.x} y={height - 8}>{shortPeriod(point.label)}</text><title>{point.label}: {number(point.average_score)}%</title></g>)}
            </svg>
        </div>
    </ReportPanel>;
}

function RoleSummary({ items }) {
    return <ReportPanel title="Role summary" hint="Selected period comparison" icon={Users}>
        <div className="kpi-role-summary">{items.map((item) => <article key={item.template_code}><div><strong>{item.template_name}</strong><span>{item.reviews} reviews · {item.approved} approved</span></div><b>{number(item.average_score)}%</b><div className="kpi-role-progress"><i style={{ width: `${Math.min(Number(item.average_score), 100)}%` }} /></div><small>{money(item.bonus_total)} bonus · {item.posted} posted</small></article>)}</div>
    </ReportPanel>;
}

function StatusSummary({ items, total, posted }) {
    const colors = { draft: 'var(--color-muted)', submitted: 'var(--color-warning)', approved: 'var(--color-success)' };
    return <ReportPanel title="Approval and bonus status" hint={`${posted} bonus adjustment${posted === 1 ? '' : 's'} posted`} icon={BadgeCheck}>
        <div className="kpi-status-summary">
            <div className="kpi-status-stack">{items.map((item) => <i key={item.status} style={{ width: `${total ? item.reviews / total * 100 : 0}%`, background: colors[item.status] }} title={`${titleCase(item.status)}: ${item.reviews}`} />)}</div>
            {items.map((item) => <article key={item.status}><span><i style={{ background: colors[item.status] }} />{titleCase(item.status)}</span><strong>{item.reviews}</strong><small>{money(item.bonus_total)}</small></article>)}
        </div>
    </ReportPanel>;
}

function MetricBreakdown({ items, roleSummary, selectedType, employeeSelected }) {
    const typeOrder = ['sales', 'sales_supervisor', 'driver', 'warehouse', 'office'];
    const typeLabels = { sales: 'Sales representatives', sales_supervisor: 'Sales supervisors', driver: 'Drivers', warehouse: 'Warehouse staff', office: 'Office staff' };
    const groupedItems = typeOrder
        .map((employeeType) => ({ employeeType, items: items.filter((item) => item.employee_type === employeeType) }))
        .filter((group) => group.items.length);
    const summaryFor = (employeeType) => roleSummary.find((summary) => summary.employee_type === employeeType) || {};

    return <ReportPanel title="Target versus actual" hint="Average result grouped by employee type" icon={TrendingUp} className="kpi-metric-report">
        <div className="kpi-metric-type-list">{groupedItems.map((group, index) => {
            const groupSummary = summaryFor(group.employeeType);
            const defaultOpen = selectedType === group.employeeType || employeeSelected || (!selectedType && !employeeSelected && index === 0);
            return <details className="kpi-metric-type-section" key={group.employeeType} open={defaultOpen ? true : undefined}>
                <summary>
                    <span className="kpi-metric-type-icon"><Users size={17} /></span>
                    <span><strong>{typeLabels[group.employeeType] || titleCase(group.employeeType)}</strong><small>{Number(groupSummary.employees || 0).toLocaleString()} employee{Number(groupSummary.employees || 0) === 1 ? '' : 's'} · {Number(groupSummary.reviews || 0).toLocaleString()} review{Number(groupSummary.reviews || 0) === 1 ? '' : 's'}</small></span>
                    <span className="kpi-metric-type-score"><small>Average score</small><strong>{number(groupSummary.average_score)}%</strong></span>
                    <ChevronDown size={17} />
                </summary>
                <div className="kpi-metric-report-list">{group.items.map((item) => {
                    const achievement = Math.min(Math.max(Number(item.achievement_average || 0), 0), 100);
                    return <article key={`${item.template_code}-${item.code}-${item.unit}`}><div className="kpi-metric-report-title"><span><strong>{item.name}</strong><small>{item.code} · {item.reviews} result{item.reviews === 1 ? '' : 's'}</small></span><b>{item.achievement_average === null ? 'Pending' : `${number(item.achievement_average)}%`}</b></div><div className="kpi-metric-report-bar"><i style={{ width: `${achievement}%` }} /></div><div className="kpi-metric-report-values"><span>Target <strong>{metricValue(item.target_average, item.unit)}</strong></span><span>Actual <strong>{metricValue(item.actual_average, item.unit)}</strong></span><span>Points <strong>{item.points_average === null ? '—' : `${number(item.points_average)} / ${number(item.weight)}`}</strong></span></div></article>;
                })}</div>
            </details>;
        })}</div>
    </ReportPanel>;
}

function ReviewTable({ items }) {
    return <section className="master-panel kpi-report-table-panel"><header><div><span>Review details</span><strong>{items.length} KPI results</strong></div><WalletCards size={17} /></header><div className="master-table-wrap"><table className="master-table kpi-report-table"><thead><tr><th>Month</th><th>Reference / employee</th><th>Role</th><th>Score</th><th>Bonus</th><th>Status</th><th>Payroll</th></tr></thead><tbody>{items.map((item) => <tr key={item.id}><td>{item.month}</td><td><strong>{item.employee_name}</strong><span>{item.reference} · {item.employee_code}</span></td><td>{item.template_name}</td><td><strong>{number(item.overall_score)}%</strong></td><td>{money(item.bonus_amount)}</td><td><span className={`kpi-status ${item.status}`}><i />{titleCase(item.status)}</span></td><td>{item.payroll_adjustment_reference ? <span className="kpi-report-posted"><BadgeCheck size={12} />{item.payroll_adjustment_reference}</span> : <span className="muted">Not posted</span>}</td></tr>)}</tbody></table></div></section>;
}

function ReportPanel({ title, hint, icon: Icon, aside, className = '', children }) {
    return <section className={`master-panel kpi-report-panel ${className}`}><header><div><span>{hint}</span><strong>{title}</strong></div>{aside || (Icon && <Icon size={17} />)}</header>{children}</section>;
}

function ReportState({ icon: Icon, title, message, loading = false }) {
    return <div className="workspace-state compact"><Icon className={loading ? 'spin' : ''} size={24} /><strong>{title}</strong>{message && <span>{message}</span>}</div>;
}

function emptyReport() {
    return { summary: { reviews: 0, average_score: 0, bonus_total: 0, approved: 0, posted: 0 }, role_summary: [], status_summary: [], monthly_trend: [], yearly_trend: [], metric_breakdown: [], reviews: [], employees: [], period: {} };
}

function metricValue(value, unit) {
    if (value === null || value === undefined) return '—';
    return String(unit).toUpperCase() === 'MMK' ? money(value) : `${number(value)} ${unit || ''}`.trim();
}

function shortPeriod(value) {
    const text = String(value || '');
    return text.length === 7 ? text.slice(5) : text;
}

function csvCell(value) {
    const text = String(value ?? '');
    return /[",\r\n]/.test(text) ? `"${text.replaceAll('"', '""')}"` : text;
}
