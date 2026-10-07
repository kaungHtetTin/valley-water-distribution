import { AlertCircle, BadgeCheck, CalendarDays, CircleDollarSign, Download, Pencil, Plus, Printer, RefreshCw, Save, Search, Trash2, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useLiveKpiRefresh } from './useLiveKpiRefresh';
import { DetailPage, DetailPanel } from './components/DetailPage';
import { ShellPageActions } from './components/ShellPageActions';
import { printConfiguredDocument } from './printDocuments';

const copy = {
    en: {
        actions: 'Actions',
        allEmployeeTypes: 'All employee types',
        allStatuses: 'All statuses',
        amount: 'Amount',
        approve: 'Approve',
        approveConfirm: 'Approve this payroll draft?',
        approved: 'Approved',
        approvedAt: 'Approved at',
        attendance: 'Attendance',
        baseSalary: 'Base salary',
        cancel: 'Cancel',
        delete: 'Delete',
        deleteConfirm: 'Delete this payroll adjustment?',
        deletePayrollConfirm: 'Delete this payroll draft and all employee calculations?',
        deductions: 'Deductions',
        details: 'Details',
        draft: 'Draft',
        edit: 'Edit',
        employee: 'Employee',
        employeeType: 'Employee type',
        effectiveDate: 'Effective date',
        empty: 'No payroll drafts match this view.',
        emptyAdjustments: 'No payroll adjustments match this view.',
        generateDraft: 'Generate draft',
        grossPay: 'Gross pay',
        adjustment: 'Adjustment',
        adjustmentHint: 'Advances, allowances, incentives, and OT used by payroll drafts.',
        adjustmentType: 'Type',
        advance: 'Advance',
        allowance: 'Allowance',
        incentive: 'Incentive',
        latestPayment: 'Latest payment',
        loading: 'Loading payrolls',
        notes: 'Notes',
        month: 'Month',
        year: 'Year',
        netPay: 'Net pay',
        ot: 'OT',
        paid: 'Paid',
        paidAt: 'Paid at',
        paymentReference: 'Payment reference',
        paymentReferencePrompt: 'Optional payment reference',
        payConfirm: 'Mark this payroll as paid?',
        payroll: 'Payroll',
        payrollHint: 'Monthly salary draft from employees and attendance totals.',
        period: 'Period',
        salaryHistory: 'Salary History',
        salaryHistoryHint: 'Paid employee salary records and monthly payroll breakdown.',
        records: 'Records',
        refresh: 'Refresh',
        retry: 'Retry',
        save: 'Save changes',
        searchPayroll: 'Search payroll code',
        searchAdjustments: 'Search employee or title',
        searchSalary: 'Search employee, payroll, or payment reference',
        showAll: 'Show all',
        status: 'Status',
        title: 'Title',
        totalCost: 'Total cost',
        totalEmployees: 'Employees',
        totalPaid: 'Total paid',
        updated: 'Updated',
        active: 'Active',
        emptySalary: 'No paid salary records match this view.',
        void: 'Void',
    },
    my: {},
};

function t(locale, key) {
    return copy[locale]?.[key] || copy.en[key] || key;
}

const payrollMonthOptions = [
    ['01', 'January'], ['02', 'February'], ['03', 'March'], ['04', 'April'],
    ['05', 'May'], ['06', 'June'], ['07', 'July'], ['08', 'August'],
    ['09', 'September'], ['10', 'October'], ['11', 'November'], ['12', 'December'],
];
const payrollYearOptions = Array.from({ length: new Date().getFullYear() - 2019 }, (_, index) => String(new Date().getFullYear() - index));

function PayrollMonthSelectors({ value, onChange, locale, allowEmpty = false }) {
    const current = new Date().toISOString().slice(0, 7);
    const year = value?.slice(0, 4) || '';
    const month = value?.slice(5, 7) || '';
    const changeYear = (nextYear) => onChange(nextYear ? `${nextYear}-${month || current.slice(5, 7)}` : '');
    const changeMonth = (nextMonth) => onChange(nextMonth ? `${year || current.slice(0, 4)}-${nextMonth}` : '');

    return <>
        <label className="payroll-month-selector"><span>{t(locale, 'year')}</span><select aria-label={t(locale, 'year')} value={year} onChange={(event) => changeYear(event.target.value)}>{allowEmpty && <option value="">All years</option>}{payrollYearOptions.map((option) => <option value={option} key={option}>{option}</option>)}</select></label>
        <label className="payroll-month-selector"><span>{t(locale, 'month')}</span><select aria-label={t(locale, 'month')} value={month} onChange={(event) => changeMonth(event.target.value)}>{allowEmpty && <option value="">All months</option>}{payrollMonthOptions.map(([option, label]) => <option value={option} key={option}>{label}</option>)}</select></label>
    </>;
}

function apiBase(name) {
    const fallback = { masterData: '/api/master-data', mobilePayroll: '/api/mobile/payroll', payrollHistory: '/api/payroll-history', payrolls: '/api/payrolls', payrollAdjustments: '/api/payroll-adjustments' };
    return window.ValleyRuntime?.api?.[name] || fallback[name];
}

function requestMessage(error, locale, fallbackKey = 'loading') {
    return locale === 'my' ? t(locale, fallbackKey) : error.response?.data?.message || t(locale, fallbackKey);
}

export function PayrollDraftsScreen({ locale, canPrepare = false, canApprove = false, canPay = false, detailId = null, onNavigate }) {
    const currentMonth = new Date().toISOString().slice(0, 7);
    const [filters, setFilters] = useState({ month: currentMonth, status: '', employee_type: '' });
    const [state, setState] = useState({ loading: true, items: [], meta: {}, error: '' });
    const [viewing, setViewing] = useState(null);
    const [generating, setGenerating] = useState(false);
    const [processingId, setProcessingId] = useState(null);
    const [refreshKey, setRefreshKey] = useState(0);
    const listPath = `${window.ValleyRuntime?.routes?.office || '/office'}/payroll/drafts`;

    useEffect(() => {
        setViewing(detailId ? { payroll: { id: detailId }, items: null } : null);
    }, [detailId]);

    useEffect(() => {
        let mounted = true;
        setState((current) => ({ ...current, loading: true, error: '' }));
        window.axios.get(apiBase('payrolls'), {
            params: {
                month: filters.month || undefined,
                status: filters.status || undefined,
                employee_type: filters.employee_type || undefined,
                per_page: 20,
            },
        }).then(({ data }) => {
            if (!mounted) return;
            setState({ loading: false, items: data.data.items, meta: data.data.meta, error: '' });
        }).catch((error) => mounted && setState({ loading: false, items: [], meta: {}, error: requestMessage(error, locale) }));

        return () => { mounted = false; };
    }, [filters, locale, refreshKey]);

    const generate = async () => {
        if (!canPrepare) return;
        setGenerating(true);
        setState((current) => ({ ...current, error: '' }));
        try {
            const { data } = await window.axios.post(`${apiBase('payrolls')}/generate`, {
                month: filters.month,
                employee_type: filters.employee_type || null,
            });
            setViewing(data.data);
            setState((current) => { const item = data.data.payroll; const exists = current.items.some((row) => row.id === item.id); return { ...current, items: exists ? current.items.map((row) => row.id === item.id ? { ...row, ...item } : row) : [{ ...item, items_count: data.data.items?.length || 0 }, ...current.items] }; });
            onNavigate?.(`${listPath}/${data.data.payroll.id}`);
        } catch (error) {
            setState((current) => ({ ...current, error: requestMessage(error, locale) }));
        } finally {
            setGenerating(false);
        }
    };

    const transitionPayroll = async (payroll, action) => {
        if (!(action === 'approve' ? canApprove : canPay) || processingId) return;

        if (action === 'approve' && !window.confirm(t(locale, 'approveConfirm'))) return;
        if (action === 'mark-paid' && !window.confirm(t(locale, 'payConfirm'))) return;

        const paymentReference = action === 'mark-paid'
            ? window.prompt(t(locale, 'paymentReferencePrompt'), payroll.payment_reference || '')
            : undefined;

        if (paymentReference === null) return;

        setProcessingId(payroll.id);
        setState((current) => ({ ...current, error: '' }));
        try {
            const { data } = await window.axios.post(`${apiBase('payrolls')}/${payroll.id}/${action}`, action === 'mark-paid' ? { payment_reference: paymentReference || null } : {});
            const nextPayroll = data.data.payroll;
            setViewing((current) => current?.payroll?.id === payroll.id ? { payroll: nextPayroll, items: null } : current);
            setState((current) => ({ ...current, items: current.items.map((item) => item.id === payroll.id ? { ...item, ...nextPayroll } : item) }));
        } catch (error) {
            setState((current) => ({ ...current, error: requestMessage(error, locale) }));
        } finally {
            setProcessingId(null);
        }
    };

    const deletePayroll = async (payroll) => {
        if (!canPrepare || processingId || payroll.status !== 'draft') return;
        if (!window.confirm(t(locale, 'deletePayrollConfirm'))) return;

        setProcessingId(payroll.id);
        setState((current) => ({ ...current, error: '' }));
        try {
            await window.axios.delete(`${apiBase('payrolls')}/${payroll.id}`);
            setViewing(null);
            setState((current) => ({ ...current, items: current.items.filter((item) => item.id !== payroll.id) }));
            if (detailId) onNavigate?.(listPath);
        } catch (error) {
            setState((current) => ({ ...current, error: requestMessage(error, locale) }));
        } finally {
            setProcessingId(null);
        }
    };

    const totals = state.items.reduce((carry, item) => ({
        employees: carry.employees + Number(item.items_count || 0),
        gross: carry.gross + Number(item.total_gross || 0),
        deductions: carry.deductions + Number(item.total_deductions || 0),
        net: carry.net + Number(item.total_net || 0),
    }), { employees: 0, gross: 0, deductions: 0, net: 0 });
    const showPayrollActions = state.items.some((payroll) => (payroll.status === 'draft' && (canPrepare || canApprove)) || (payroll.status === 'approved' && canPay));

    if (detailId && viewing) return <PayrollDetailPage viewing={viewing} locale={locale} canPrepare={canPrepare} canApprove={canApprove} canPay={canPay} processingId={processingId} operationError={state.error} onApprove={(payroll) => transitionPayroll(payroll, 'approve')} onMarkPaid={(payroll) => transitionPayroll(payroll, 'mark-paid')} onDelete={deletePayroll} onClose={() => onNavigate?.(listPath)} />;

    return (
        <section className="master-workspace payroll-workspace">
            <div className="master-heading">
                <div>
                    <p className="eyebrow">{t(locale, 'payroll')}</p>
                    <h1>{t(locale, 'payroll')}</h1>
                    <span className="muted">{t(locale, 'payrollHint')}</span>
                </div>
                <ShellPageActions>{canPrepare && <button className="button primary" type="button" disabled={generating} onClick={generate}><Plus size={16} /> {t(locale, 'generateDraft')}</button>}</ShellPageActions>
            </div>

            <div className="metrics payroll-metrics">
                <div className="metric"><span>{t(locale, 'totalEmployees')}</span><strong>{totals.employees}</strong><small>{filters.month}</small></div>
                <div className="metric"><span>{t(locale, 'grossPay')}</span><strong>{money(totals.gross)}</strong><small>{t(locale, 'totalCost')}</small></div>
                <div className="metric"><span>{t(locale, 'deductions')}</span><strong>{money(totals.deductions)}</strong><small>{t(locale, 'payroll')}</small></div>
                <div className="metric"><span>{t(locale, 'netPay')}</span><strong>{money(totals.net)}</strong><small>{t(locale, 'totalCost')}</small></div>
            </div>

            <div className="master-panel">
                <div className="master-toolbar payroll-toolbar">
                    <PayrollMonthSelectors value={filters.month} locale={locale} onChange={(month) => setFilters((current) => ({ ...current, month }))} />
                    <select aria-label={t(locale, 'employeeType')} value={filters.employee_type} onChange={(event) => setFilters((current) => ({ ...current, employee_type: event.target.value }))}>
                        <option value="">{t(locale, 'allEmployeeTypes')}</option>
                        {['office', 'sales', 'sales_supervisor', 'driver', 'warehouse'].map((type) => <option value={type} key={type}>{titleCase(type)}</option>)}
                    </select>
                    <select aria-label={t(locale, 'status')} value={filters.status} onChange={(event) => setFilters((current) => ({ ...current, status: event.target.value }))}>
                        <option value="">{t(locale, 'allStatuses')}</option>
                        <option value="draft">{t(locale, 'draft')}</option>
                        <option value="approved">{t(locale, 'approved')}</option>
                        <option value="paid">{t(locale, 'paid')}</option>
                    </select>
                    <button className="icon-button" type="button" aria-label={t(locale, 'refresh')} title={t(locale, 'refresh')} onClick={() => setRefreshKey((key) => key + 1)}><RefreshCw size={15} /></button>
                </div>

                {state.error && <div className="inline-error"><AlertCircle size={15} /> {state.error}</div>}
                {state.loading ? <WorkspaceState icon={RefreshCw} title={t(locale, 'loading')} loading compact /> : state.items.length === 0 ? <WorkspaceState icon={Search} title={t(locale, 'empty')} compact /> : (
                    <div className="master-table-wrap">
                        <table className="master-table payroll-table">
                            <thead><tr><th>{t(locale, 'payroll')}</th><th>{t(locale, 'month')}</th><th>{t(locale, 'employeeType')}</th><th>{t(locale, 'totalEmployees')}</th><th>{t(locale, 'grossPay')}</th><th>{t(locale, 'netPay')}</th><th>{t(locale, 'status')}</th>{showPayrollActions && <th className="table-actions-header">{t(locale, 'actions')}</th>}</tr></thead>
                            <tbody>
                                {state.items.map((payroll) => (
                                    <tr className="clickable-row" key={payroll.id} tabIndex={0} onClick={() => onNavigate?.(`${listPath}/${payroll.id}`)} onKeyDown={(event) => { if (event.target === event.currentTarget && ['Enter', ' '].includes(event.key)) { event.preventDefault(); onNavigate?.(`${listPath}/${payroll.id}`); } }}>
                                        <td><strong>{payroll.code}</strong><span className="muted">{t(locale, 'updated')}: {formatDateTime(payroll.updated_at)}</span></td>
                                        <td>{payroll.month}</td>
                                        <td>{payroll.employee_type ? titleCase(payroll.employee_type) : t(locale, 'allEmployeeTypes')}</td>
                                        <td>{payroll.items_count}</td>
                                        <td>{money(payroll.total_gross)}</td>
                                        <td>{money(payroll.total_net)}</td>
                                        <td><StatusBadge status={payroll.status} locale={locale} /></td>
                                        {showPayrollActions && <td className="table-actions-cell" onClick={(event) => event.stopPropagation()}><div className="row-actions">
                                            {canApprove && payroll.status === 'draft' && <button type="button" disabled={processingId === payroll.id} aria-label={t(locale, 'approve')} title={t(locale, 'approve')} onClick={() => transitionPayroll(payroll, 'approve')}><BadgeCheck size={15} /></button>}
                                            {canPrepare && payroll.status === 'draft' && <button className="danger" type="button" disabled={processingId === payroll.id} aria-label={t(locale, 'delete')} title={t(locale, 'delete')} onClick={() => deletePayroll(payroll)}><Trash2 size={15} /></button>}
                                            {canPay && payroll.status === 'approved' && <button type="button" disabled={processingId === payroll.id} aria-label={t(locale, 'paid')} title={t(locale, 'paid')} onClick={() => transitionPayroll(payroll, 'mark-paid')}><CircleDollarSign size={15} /></button>}
                                        </div></td>}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

        </section>
    );
}

const adjustmentTypes = ['advance', 'allowance', 'incentive', 'ot'];
const blankAdjustmentForm = {
    employee_id: '',
    adjustment_type: 'allowance',
    title: '',
    amount: '',
    effective_date: new Date().toISOString().slice(0, 10),
    status: 'active',
    notes: '',
};

export function PayrollAdjustmentsScreen({ locale, canManage = false }) {
    const currentMonth = new Date().toISOString().slice(0, 7);
    const [filters, setFilters] = useState({ search: '', adjustment_type: '', employee_id: '', month: currentMonth, status: 'active' });
    const [state, setState] = useState({ loading: true, items: [], meta: {}, error: '' });
    const [employees, setEmployees] = useState([]);
    const [editing, setEditing] = useState(null);
    const [formOpen, setFormOpen] = useState(false);
    const [form, setForm] = useState(blankAdjustmentForm);
    const [saving, setSaving] = useState(false);
    const [refreshKey, setRefreshKey] = useState(0);

    useEffect(() => {
        let mounted = true;
        window.axios.get(`${apiBase('masterData')}/meta`)
            .then(({ data }) => mounted && setEmployees(data.data.options?.employees || []))
            .catch(() => mounted && setEmployees([]));
        return () => { mounted = false; };
    }, []);

    useEffect(() => {
        let mounted = true;
        setState((current) => ({ ...current, loading: true, error: '' }));
        window.axios.get(apiBase('payrollAdjustments'), {
            params: {
                search: filters.search || undefined,
                adjustment_type: filters.adjustment_type || undefined,
                employee_id: filters.employee_id || undefined,
                month: filters.month || undefined,
                status: filters.status || undefined,
                per_page: 50,
            },
        }).then(({ data }) => {
            if (!mounted) return;
            setState({ loading: false, items: data.data.items, meta: data.data.meta, error: '' });
        }).catch((error) => mounted && setState({ loading: false, items: [], meta: {}, error: requestMessage(error, locale) }));

        return () => { mounted = false; };
    }, [filters, locale, refreshKey]);

    const openForm = (adjustment = null) => {
        setEditing(adjustment);
        setFormOpen(true);
        setForm(adjustment ? {
            employee_id: adjustment.employee_id || '',
            adjustment_type: adjustment.adjustment_type || 'allowance',
            title: adjustment.title || '',
            amount: adjustment.amount || '',
            effective_date: adjustment.effective_date || blankAdjustmentForm.effective_date,
            status: adjustment.status || 'active',
            notes: adjustment.notes || '',
        } : { ...blankAdjustmentForm, effective_date: filters.month ? `${filters.month}-01` : blankAdjustmentForm.effective_date });
    };

    const closeForm = () => {
        setEditing(null);
        setFormOpen(false);
        setForm(blankAdjustmentForm);
        setSaving(false);
    };

    const saveAdjustment = async (event) => {
        event.preventDefault();
        if (!canManage) return;
        setSaving(true);
        setState((current) => ({ ...current, error: '' }));
        try {
            const payload = {
                employee_id: Number(form.employee_id),
                adjustment_type: form.adjustment_type,
                title: form.title,
                amount: Number(form.amount || 0),
                effective_date: form.effective_date,
                status: form.status,
                notes: form.notes || null,
            };
            const { data } = editing?.id
                ? await window.axios.put(`${apiBase('payrollAdjustments')}/${editing.id}`, payload)
                : await window.axios.post(apiBase('payrollAdjustments'), payload);
            const adjustment = data.data.adjustment;
            setState((current) => {
                const exists = current.items.some((item) => item.id === adjustment.id);
                return { ...current, items: exists ? current.items.map((item) => item.id === adjustment.id ? adjustment : item) : [adjustment, ...current.items], meta: { ...current.meta, total: Number(current.meta?.total || 0) + (exists ? 0 : 1) } };
            });
            closeForm();
        } catch (error) {
            setState((current) => ({ ...current, error: requestMessage(error, locale) }));
            setSaving(false);
        }
    };

    const deleteAdjustment = async (adjustment) => {
        if (!canManage || !window.confirm(t(locale, 'deleteConfirm'))) return;
        setState((current) => ({ ...current, error: '' }));
        try {
            await window.axios.delete(`${apiBase('payrollAdjustments')}/${adjustment.id}`);
            setState((current) => ({ ...current, items: current.items.filter((item) => item.id !== adjustment.id), meta: { ...current.meta, total: Math.max(0, Number(current.meta?.total || 0) - 1) } }));
        } catch (error) {
            setState((current) => ({ ...current, error: requestMessage(error, locale) }));
        }
    };

    const totals = state.items.reduce((carry, item) => {
        const amount = Number(item.amount || 0);
        return item.adjustment_type === 'advance'
            ? { ...carry, deductions: carry.deductions + amount, count: carry.count + 1 }
            : { ...carry, additions: carry.additions + amount, count: carry.count + 1 };
    }, { additions: 0, deductions: 0, count: 0 });
    const showAdjustmentActions = canManage && state.items.some((adjustment) => !adjustment.source);

    return (
        <section className="master-workspace payroll-workspace">
            <div className="master-heading">
                <div>
                    <p className="eyebrow">{t(locale, 'adjustment')}</p>
                    <h1>{t(locale, 'adjustment')}</h1>
                    <span className="muted">{t(locale, 'adjustmentHint')}</span>
                </div>
                <ShellPageActions>{canManage && <button className="button primary" type="button" onClick={() => openForm()}><Plus size={16} /> {t(locale, 'adjustment')}</button>}</ShellPageActions>
            </div>

            <div className="metrics payroll-metrics">
                <div className="metric"><span>{t(locale, 'records')}</span><strong>{totals.count}</strong><small>{filters.month || t(locale, 'allStatuses')}</small></div>
                <div className="metric"><span>{t(locale, 'grossPay')}</span><strong>{money(totals.additions)}</strong><small>{t(locale, 'allowance')} / {t(locale, 'incentive')} / {t(locale, 'ot')}</small></div>
                <div className="metric"><span>{t(locale, 'deductions')}</span><strong>{money(totals.deductions)}</strong><small>{t(locale, 'advance')}</small></div>
                <div className="metric"><span>{t(locale, 'netPay')}</span><strong>{money(totals.additions - totals.deductions)}</strong><small>{t(locale, 'payroll')}</small></div>
            </div>

            <div className="master-panel">
                <div className="master-toolbar payroll-toolbar payroll-adjustment-toolbar">
                    <label className="master-search"><Search size={14} /><input value={filters.search} placeholder={t(locale, 'searchAdjustments')} onChange={(event) => setFilters((current) => ({ ...current, search: event.target.value }))} /></label>
                    <PayrollMonthSelectors value={filters.month} locale={locale} onChange={(month) => setFilters((current) => ({ ...current, month }))} />
                    <select aria-label={t(locale, 'adjustmentType')} value={filters.adjustment_type} onChange={(event) => setFilters((current) => ({ ...current, adjustment_type: event.target.value }))}>
                        <option value="">{t(locale, 'adjustmentType')}</option>
                        {adjustmentTypes.map((type) => <option value={type} key={type}>{t(locale, type)}</option>)}
                    </select>
                    <select aria-label={t(locale, 'employee')} value={filters.employee_id} onChange={(event) => setFilters((current) => ({ ...current, employee_id: event.target.value }))}>
                        <option value="">{t(locale, 'employee')}</option>
                        {employees.map((employee) => <option value={employee.id} key={employee.id}>{employee.label}</option>)}
                    </select>
                    <select aria-label={t(locale, 'status')} value={filters.status} onChange={(event) => setFilters((current) => ({ ...current, status: event.target.value }))}>
                        <option value="">{t(locale, 'allStatuses')}</option>
                        <option value="active">{t(locale, 'active')}</option>
                        <option value="void">{t(locale, 'void')}</option>
                    </select>
                    <button className="icon-button" type="button" aria-label={t(locale, 'refresh')} title={t(locale, 'refresh')} onClick={() => setRefreshKey((key) => key + 1)}><RefreshCw size={15} /></button>
                </div>

                {state.error && <div className="inline-error"><AlertCircle size={15} /> {state.error}</div>}
                {state.loading ? <WorkspaceState icon={RefreshCw} title={t(locale, 'loading')} loading compact /> : state.items.length === 0 ? <WorkspaceState icon={Search} title={t(locale, 'emptyAdjustments')} compact /> : (
                    <div className="master-table-wrap">
                        <table className="master-table payroll-adjustment-table">
                            <thead><tr><th>{t(locale, 'employee')}</th><th>{t(locale, 'title')}</th><th>{t(locale, 'adjustmentType')}</th><th>{t(locale, 'effectiveDate')}</th><th>{t(locale, 'amount')}</th><th>{t(locale, 'status')}</th>{showAdjustmentActions && <th className="table-actions-header">{t(locale, 'actions')}</th>}</tr></thead>
                            <tbody>
                                {state.items.map((adjustment) => (
                                    <tr key={adjustment.id}>
                                        <td><strong>{adjustment.employee_name}</strong><span className="muted">{adjustment.employee_code} · {titleCase(adjustment.employee_type)}</span></td>
                                        <td>
                                            <strong>{adjustment.title}</strong>
                                            {adjustment.source?.type === 'kpi' && <span className="kpi-adjustment-source"><BadgeCheck size={11} /> {adjustment.source.reference} · {adjustment.source.month} · {Number(adjustment.source.score || 0).toLocaleString(undefined, { maximumFractionDigits: 2 })}%</span>}
                                            {adjustment.notes && <span className="muted">{adjustment.notes}</span>}
                                        </td>
                                        <td><span className={`adjustment-chip ${adjustment.adjustment_type}`}>{t(locale, adjustment.adjustment_type)}</span></td>
                                        <td>{adjustment.effective_date}</td>
                                        <td>{money(adjustment.amount)}</td>
                                        <td><StatusBadge status={adjustment.status} locale={locale} /></td>
                                        {showAdjustmentActions && <td className="table-actions-cell"><div className="row-actions">
                                            {!adjustment.source && <button type="button" aria-label={t(locale, 'edit')} title={t(locale, 'edit')} onClick={() => openForm(adjustment)}><Pencil size={15} /></button>}
                                            {!adjustment.source && <button className="danger" type="button" aria-label={t(locale, 'delete')} title={t(locale, 'delete')} onClick={() => deleteAdjustment(adjustment)}><Trash2 size={15} /></button>}
                                        </div></td>}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {formOpen && (
                <div className="modal-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && closeForm()}>
                    <form className="master-dialog" role="dialog" aria-modal="true" aria-label={t(locale, 'adjustment')} onSubmit={saveAdjustment}>
                        <header><div><p className="eyebrow">{editing?.id ? t(locale, 'edit') : t(locale, 'adjustment')}</p><h2>{t(locale, 'adjustment')}</h2></div><button className="icon-button" type="button" aria-label={t(locale, 'cancel')} onClick={closeForm}><X size={17} /></button></header>
                        <div className="master-form-body">
                            <div className="master-form-grid">
                                <label className="master-field"><span>{t(locale, 'employee')} <b>*</b></span><select required value={form.employee_id} onChange={(event) => setForm((current) => ({ ...current, employee_id: event.target.value }))}><option value="">{t(locale, 'employee')}</option>{employees.map((employee) => <option value={employee.id} key={employee.id}>{employee.label}</option>)}</select></label>
                                <label className="master-field"><span>{t(locale, 'adjustmentType')} <b>*</b></span><select required value={form.adjustment_type} onChange={(event) => setForm((current) => ({ ...current, adjustment_type: event.target.value }))}>{adjustmentTypes.map((type) => <option value={type} key={type}>{t(locale, type)}</option>)}</select></label>
                                <label className="master-field wide"><span>{t(locale, 'title')} <b>*</b></span><input required value={form.title} maxLength={150} onChange={(event) => setForm((current) => ({ ...current, title: event.target.value }))} /></label>
                                <label className="master-field"><span>{t(locale, 'amount')} <b>*</b></span><input required type="number" min="0" step="100" value={form.amount} onChange={(event) => setForm((current) => ({ ...current, amount: event.target.value }))} /></label>
                                <label className="master-field"><span>{t(locale, 'effectiveDate')} <b>*</b></span><input required type="date" value={form.effective_date} onChange={(event) => setForm((current) => ({ ...current, effective_date: event.target.value }))} /></label>
                                <label className="master-field"><span>{t(locale, 'status')} <b>*</b></span><select required value={form.status} onChange={(event) => setForm((current) => ({ ...current, status: event.target.value }))}><option value="active">{t(locale, 'active')}</option><option value="void">{t(locale, 'void')}</option></select></label>
                                <label className="master-field wide"><span>{t(locale, 'notes')}</span><textarea rows="3" value={form.notes} maxLength={500} onChange={(event) => setForm((current) => ({ ...current, notes: event.target.value }))} /></label>
                            </div>
                        </div>
                        <footer><button className="button" type="button" onClick={closeForm}>{t(locale, 'cancel')}</button><button className="button primary" type="submit" disabled={saving}><Save size={15} /> {t(locale, 'save')}</button></footer>
                    </form>
                </div>
            )}
        </section>
    );
}

export function SalaryHistoryScreen({ locale, detailId = null, onNavigate }) {
    const [filters, setFilters] = useState({ search: '', month: '', employee_type: '', employee_id: '' });
    const [state, setState] = useState({ loading: true, items: [], summary: { payments_count: 0, employees_count: 0, total_gross: 0, total_deductions: 0, total_net: 0, latest_paid_at: null }, meta: {}, error: '' });
    const [employees, setEmployees] = useState([]);
    const [selected, setSelected] = useState(null);
    const [refreshKey, setRefreshKey] = useState(0);
    const listPath = `${window.ValleyRuntime?.routes?.office || '/office'}/payroll/salary-history`;

    useEffect(() => {
        if (!detailId) {
            setSelected(null);
            return undefined;
        }
        let mounted = true;
        window.axios.get(`${apiBase('payrollHistory')}/${detailId}`)
            .then(({ data }) => mounted && setSelected(data.data.item))
            .catch((error) => mounted && setState((current) => ({ ...current, error: requestMessage(error, locale) })));
        return () => { mounted = false; };
    }, [detailId, locale]);

    useEffect(() => {
        let mounted = true;
        window.axios.get(`${apiBase('masterData')}/meta`)
            .then(({ data }) => mounted && setEmployees(data.data.options?.employees || []))
            .catch(() => mounted && setEmployees([]));
        return () => { mounted = false; };
    }, []);

    useEffect(() => {
        let mounted = true;
        setState((current) => ({ ...current, loading: true, error: '' }));
        window.axios.get(apiBase('payrollHistory'), {
            params: {
                search: filters.search || undefined,
                month: filters.month || undefined,
                employee_type: filters.employee_type || undefined,
                employee_id: filters.employee_id || undefined,
                per_page: 50,
            },
        }).then(({ data }) => {
            if (!mounted) return;
            setState({ loading: false, items: data.data.items, summary: data.data.summary, meta: data.data.meta, error: '' });
        }).catch((error) => mounted && setState({ loading: false, items: [], summary: { payments_count: 0, employees_count: 0, total_gross: 0, total_deductions: 0, total_net: 0, latest_paid_at: null }, meta: {}, error: requestMessage(error, locale) }));

        return () => { mounted = false; };
    }, [filters, locale, refreshKey]);

    if (detailId) return selected ? <SalaryHistoryDetailPage item={selected} locale={locale} onClose={() => onNavigate?.(listPath)} /> : <WorkspaceState icon={state.error ? AlertCircle : RefreshCw} title={state.error || t(locale, 'loading')} loading={!state.error} />;

    return (
        <section className="master-workspace payroll-workspace">
            <div className="master-heading">
                <div>
                    <p className="eyebrow">{t(locale, 'payroll')}</p>
                    <h1>{t(locale, 'salaryHistory')}</h1>
                    <span className="muted">{t(locale, 'salaryHistoryHint')}</span>
                </div>
                <ShellPageActions><button className="icon-button" type="button" aria-label={t(locale, 'refresh')} title={t(locale, 'refresh')} onClick={() => setRefreshKey((key) => key + 1)}><RefreshCw size={15} /></button></ShellPageActions>
            </div>

            <div className="metrics payroll-metrics">
                <div className="metric"><span>{t(locale, 'records')}</span><strong>{state.summary.payments_count}</strong><small>{state.summary.employees_count} {t(locale, 'totalEmployees')}</small></div>
                <div className="metric"><span>{t(locale, 'grossPay')}</span><strong>{money(state.summary.total_gross)}</strong><small>{t(locale, 'totalCost')}</small></div>
                <div className="metric"><span>{t(locale, 'deductions')}</span><strong>{money(state.summary.total_deductions)}</strong><small>{t(locale, 'payroll')}</small></div>
                <div className="metric"><span>{t(locale, 'totalPaid')}</span><strong>{money(state.summary.total_net)}</strong><small>{t(locale, 'latestPayment')}: {formatShortDate(state.summary.latest_paid_at)}</small></div>
            </div>

            <div className="master-panel">
                <div className="master-toolbar payroll-toolbar salary-history-toolbar">
                    <label className="master-search"><Search size={14} /><input value={filters.search} placeholder={t(locale, 'searchSalary')} onChange={(event) => setFilters((current) => ({ ...current, search: event.target.value }))} /></label>
                    <PayrollMonthSelectors value={filters.month} locale={locale} allowEmpty onChange={(month) => setFilters((current) => ({ ...current, month }))} />
                    <select aria-label={t(locale, 'employeeType')} value={filters.employee_type} onChange={(event) => setFilters((current) => ({ ...current, employee_type: event.target.value }))}>
                        <option value="">{t(locale, 'allEmployeeTypes')}</option>
                        {['office', 'sales', 'sales_supervisor', 'driver', 'warehouse'].map((type) => <option value={type} key={type}>{titleCase(type)}</option>)}
                    </select>
                    <select aria-label={t(locale, 'employee')} value={filters.employee_id} onChange={(event) => setFilters((current) => ({ ...current, employee_id: event.target.value }))}>
                        <option value="">{t(locale, 'employee')}</option>
                        {employees.map((employee) => <option value={employee.id} key={employee.id}>{employee.label}</option>)}
                    </select>
                    {(filters.search || filters.month || filters.employee_type || filters.employee_id) && <button className="button" type="button" onClick={() => setFilters({ search: '', month: '', employee_type: '', employee_id: '' })}>{t(locale, 'showAll')}</button>}
                </div>

                {state.error && <div className="inline-error"><AlertCircle size={15} /> {state.error}</div>}
                {state.loading ? <WorkspaceState icon={RefreshCw} title={t(locale, 'loading')} loading compact /> : state.items.length === 0 ? <WorkspaceState icon={CircleDollarSign} title={t(locale, 'emptySalary')} compact /> : (
                    <div className="master-table-wrap">
                        <table className="master-table salary-history-table">
                            <thead><tr><th>{t(locale, 'employee')}</th><th>{t(locale, 'payroll')}</th><th>{t(locale, 'month')}</th><th>{t(locale, 'grossPay')}</th><th>{t(locale, 'deductions')}</th><th>{t(locale, 'netPay')}</th><th>{t(locale, 'paidAt')}</th></tr></thead>
                            <tbody>
                                {state.items.map((item) => (
                                    <tr className="clickable-row" key={item.id} tabIndex={0} onClick={() => onNavigate?.(`${listPath}/${item.id}`)} onKeyDown={(event) => { if (event.target === event.currentTarget && ['Enter', ' '].includes(event.key)) { event.preventDefault(); onNavigate?.(`${listPath}/${item.id}`); } }}>
                                        <td><strong>{item.employee_name}</strong><span className="muted">{item.employee_code} - {titleCase(item.employee_type)}</span></td>
                                        <td><strong>{item.payroll_code}</strong><span className="muted">{item.payment_reference || '-'}</span></td>
                                        <td>{item.month}</td>
                                        <td>{money(item.gross_pay)}</td>
                                        <td>{money(Number(item.advance_deduction || 0) + Number(item.other_deduction || 0))}</td>
                                        <td>{money(item.net_pay)}</td>
                                        <td>{formatDateTime(item.paid_at)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

        </section>
    );
}

export function MobilePayrollHistoryScreen({ locale }) {
    const [filters, setFilters] = useState({ month: '' });
    const [state, setState] = useState({ loading: true, items: [], summary: { payments_count: 0, total_net: 0, latest_paid_at: null }, error: '' });
    const [selected, setSelected] = useState(null);
    const [refreshKey, setRefreshKey] = useState(0);

    useEffect(() => {
        let mounted = true;
        setState((current) => ({ ...current, loading: true, error: '' }));
        window.axios.get(`${apiBase('mobilePayroll')}/history`, {
            params: {
                month: filters.month || undefined,
                per_page: 12,
            },
        }).then(({ data }) => {
            if (!mounted) return;
            setState({ loading: false, items: data.data.items, summary: data.data.summary, error: '' });
        }).catch((error) => mounted && setState({ loading: false, items: [], summary: { payments_count: 0, total_net: 0, latest_paid_at: null }, error: requestMessage(error, locale) }));

        return () => { mounted = false; };
    }, [filters, locale, refreshKey]);

    return (
        <div className="mobile-master-stack mobile-payroll-history">
            <div className="mobile-master-heading">
                <div>
                    <p className="eyebrow">{t(locale, 'payroll')}</p>
                    <h1>{t(locale, 'salaryHistory')}</h1>
                    <span className="muted">{t(locale, 'salaryHistoryHint')}</span>
                </div>
                <ShellPageActions><button className="icon-button" type="button" aria-label={t(locale, 'refresh')} title={t(locale, 'refresh')} onClick={() => setRefreshKey((key) => key + 1)}><RefreshCw size={18} /></button></ShellPageActions>
            </div>

            <section className="mobile-payroll-summary" aria-label={t(locale, 'salaryHistory')}>
                <div><small>{t(locale, 'totalPaid')}</small><strong>{money(state.summary.total_net)}</strong></div>
                <div><small>{t(locale, 'records')}</small><strong>{state.summary.payments_count}</strong></div>
                <div><small>{t(locale, 'latestPayment')}</small><strong>{formatShortDate(state.summary.latest_paid_at)}</strong></div>
            </section>

            <section className="mobile-master-section">
                <div className="mobile-payroll-filters">
                    <label><CalendarDays size={14} /><input aria-label={t(locale, 'month')} type="month" value={filters.month} onChange={(event) => setFilters({ month: event.target.value })} /></label>
                    {filters.month && <button className="button" type="button" onClick={() => setFilters({ month: '' })}>{t(locale, 'showAll')}</button>}
                </div>

                {state.error ? <WorkspaceState icon={AlertCircle} title={state.error} action={() => setRefreshKey((key) => key + 1)} actionLabel={t(locale, 'retry')} compact /> : state.loading ? <WorkspaceState icon={RefreshCw} title={t(locale, 'loading')} loading compact /> : state.items.length === 0 ? <WorkspaceState icon={CircleDollarSign} title={t(locale, 'emptySalary')} compact /> : (
                    <div className="mobile-payroll-list">
                        {state.items.map((item) => (
                            <button type="button" key={item.id} onClick={() => setSelected(item)}>
                                <span className="salary-icon"><CircleDollarSign size={17} /></span>
                                <span>
                                    <strong>{item.month} · {money(item.net_pay)}</strong>
                                    <small>{item.payroll_code}</small>
                                    <small>{t(locale, 'paidAt')}: {formatDateTime(item.paid_at)}</small>
                                </span>
                                <StatusBadge status={item.status} locale={locale} />
                            </button>
                        ))}
                    </div>
                )}
            </section>

            {selected && <MobileSalaryDetailSheet item={selected} locale={locale} onClose={() => setSelected(null)} />}
        </div>
    );
}

function PayrollDetailPage({ viewing, locale, canPrepare = false, canApprove = false, canPay = false, processingId = null, operationError = '', onApprove, onMarkPaid, onDelete, onClose }) {
    const [state, setState] = useState(() => viewing.items ? { loading: false, payroll: viewing.payroll, items: viewing.items, error: '' } : { loading: true, payroll: viewing.payroll, items: [], error: '' });

    useLiveKpiRefresh(async (isCurrent) => {
        const { data } = await window.axios.get(`${apiBase('payrolls')}/${viewing.payroll.id}`);
        if (isCurrent()) setState({ loading: false, payroll: data.data.payroll, items: data.data.items, error: '' });
    }, String(viewing.payroll.id), !state.loading && state.payroll.status === 'draft' && !processingId);

    useEffect(() => {
        if (viewing.items) return undefined;
        let mounted = true;
        window.axios.get(`${apiBase('payrolls')}/${viewing.payroll.id}`)
            .then(({ data }) => mounted && setState({ loading: false, payroll: data.data.payroll, items: data.data.items, error: '' }))
            .catch((error) => mounted && setState((current) => ({ ...current, loading: false, error: requestMessage(error, locale) })));
        return () => { mounted = false; };
    }, [locale, viewing]);

    const exportPayrollCsv = () => {
        const escapeCell = (value) => typeof value === 'number' && Number.isFinite(value) ? String(value) : `"${String(value ?? '').replaceAll('"', '""')}"`;
        const headers = ['Employee', 'Employee code', 'Employee type', 'Attendance accepted', 'Attendance rejected', 'Base salary (MMK)', 'Allowance (MMK)', 'Bonus / incentive (MMK)', 'OT (MMK)', 'Advance (MMK)', 'Other deduction (MMK)', 'Gross pay (MMK)', 'Net pay (MMK)'];
        const rows = state.items.map((item) => [item.employee_name, item.employee_code, titleCase(item.employee_type), item.accepted_count, item.rejected_count, item.base_salary, item.allowance_amount, item.incentive_amount, item.ot_amount, item.advance_deduction, item.other_deduction, item.gross_pay, item.net_pay]);
        const totals = state.items.reduce((result, item) => ({
            base: result.base + Number(item.base_salary || 0),
            allowance: result.allowance + Number(item.allowance_amount || 0),
            incentive: result.incentive + Number(item.incentive_amount || 0),
            ot: result.ot + Number(item.ot_amount || 0),
            advance: result.advance + Number(item.advance_deduction || 0),
            other: result.other + Number(item.other_deduction || 0),
            gross: result.gross + Number(item.gross_pay || 0),
            net: result.net + Number(item.net_pay || 0),
        }), { base: 0, allowance: 0, incentive: 0, ot: 0, advance: 0, other: 0, gross: 0, net: 0 });
        rows.push(['TOTAL', state.payroll.code, state.payroll.status, '', '', totals.base, totals.allowance, totals.incentive, totals.ot, totals.advance, totals.other, totals.gross, totals.net]);
        const csv = `\uFEFF${[headers, ...rows].map((row) => row.map(escapeCell).join(',')).join('\r\n')}`;
        const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
        const link = document.createElement('a');
        link.href = url;
        link.download = `${state.payroll.code || 'payroll'}-${state.payroll.month}.csv`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    };

    const actions = <>
        <button className="icon-button" type="button" disabled={state.loading || state.items.length === 0} aria-label="Export payroll table to Excel CSV" title="Export Excel CSV" onClick={exportPayrollCsv}><Download size={17} /></button>
        {canApprove && state.payroll.status === 'draft' && <button className="button primary" type="button" disabled={processingId === state.payroll.id} onClick={() => onApprove?.(state.payroll)}><BadgeCheck size={15} /> {t(locale, 'approve')}</button>}
        {canPrepare && state.payroll.status === 'draft' && <button className="button danger" type="button" disabled={processingId === state.payroll.id} onClick={() => onDelete?.(state.payroll)}><Trash2 size={15} /> {t(locale, 'delete')}</button>}
        {canPay && state.payroll.status === 'approved' && <button className="button primary" type="button" disabled={processingId === state.payroll.id} onClick={() => onMarkPaid?.(state.payroll)}><CircleDollarSign size={15} /> {t(locale, 'paid')}</button>}
    </>;

    return (
        <DetailPage eyebrow={t(locale, 'payroll')} title={state.payroll.code || t(locale, 'loading')} subtitle={state.payroll.month} onBack={onClose} actions={actions}>
            {(state.error || operationError) && <div className="inline-error"><AlertCircle size={15} /> {state.error || operationError}</div>}
            {state.loading ? <WorkspaceState icon={RefreshCw} title={t(locale, 'loading')} loading compact /> : <>
                <section className="master-panel record-page-panel payroll-items-panel">
                    <div className="record-page-panel-heading payroll-items-heading">
                        <div className="payroll-items-heading-copy">
                            <p className="eyebrow">{t(locale, 'employees')}</p>
                            <div><h2>{state.payroll.items_count} {t(locale, 'totalEmployees')}</h2><StatusBadge status={state.payroll.status} locale={locale} /></div>
                            {(state.payroll.approved_at || state.payroll.paid_at || state.payroll.payment_reference) && <span>{[
                                state.payroll.paid_at ? `${t(locale, 'paidAt')}: ${formatDateTime(state.payroll.paid_at)}` : state.payroll.approved_at ? `${t(locale, 'approvedAt')}: ${formatDateTime(state.payroll.approved_at)}` : null,
                                state.payroll.payment_reference ? `${t(locale, 'paymentReference')}: ${state.payroll.payment_reference}` : null,
                            ].filter(Boolean).join(' · ')}</span>}
                        </div>
                        <div><span>{t(locale, 'netPay')}</span><strong>{money(state.payroll.total_net)}</strong></div>
                    </div>
                    <div className="master-table-wrap">
                        <table className="master-table payroll-item-table">
                            <colgroup><col className="payroll-col-employee" /><col className="payroll-col-role" /><col className="payroll-col-attendance" /><col className="payroll-col-base" /><col className="payroll-col-allowance" /><col className="payroll-col-bonus" /><col className="payroll-col-ot" /><col className="payroll-col-advance" /><col className="payroll-col-other" /><col className="payroll-col-gross" /><col className="payroll-col-net" /></colgroup>
                            <thead><tr><th>{t(locale, 'employee')}</th><th>{t(locale, 'employeeType')}</th><th className="center">{t(locale, 'attendance')}</th><th className="numeric">{t(locale, 'baseSalary')}</th><th className="numeric">{t(locale, 'allowance')}</th><th className="numeric">Bonus / {t(locale, 'incentive')}</th><th className="numeric">{t(locale, 'ot')}</th><th className="numeric">{t(locale, 'advance')}</th><th className="numeric">Other deduction</th><th className="numeric">{t(locale, 'grossPay')}</th><th className="numeric">{t(locale, 'netPay')}</th></tr></thead>
                            <tbody>{state.items.map((item) => <tr key={item.id}><td><strong>{item.employee_name}</strong><span className="muted">{item.employee_code}</span></td><td>{titleCase(item.employee_type)}</td><td className="center"><span className="payroll-attendance-pair"><strong>{item.accepted_count}</strong><small>/ {item.rejected_count}</small></span></td><td className="numeric">{money(item.base_salary)}</td><td className="numeric">{money(item.allowance_amount)}</td><td className="numeric payroll-bonus-cell">{money(item.incentive_amount)}</td><td className="numeric">{money(item.ot_amount)}</td><td className="numeric">{money(item.advance_deduction)}</td><td className="numeric">{money(item.other_deduction)}</td><td className="numeric"><strong>{money(item.gross_pay)}</strong></td><td className="numeric payroll-net-cell"><strong>{money(item.net_pay)}</strong></td></tr>)}</tbody>
                        </table>
                    </div>
                </section>
            </>}
        </DetailPage>
    );
}

function SalaryHistoryDetailPage({ item, locale, onClose }) {
    const deductions = Number(item.advance_deduction || 0) + Number(item.other_deduction || 0);
    const printPayslip = () => printConfiguredDocument('employee_payslip', {
        reference: item.payroll_code,
        facts: [
            ['Employee', `${item.employee_code} · ${item.employee_name}`],
            ['Role', titleCase(item.employee_type)],
            ['Month', item.month],
            ['Period', `${item.period_start} - ${item.period_end}`],
            ['Attendance', `${item.accepted_count} accepted / ${item.rejected_count} rejected`],
            ['Payment reference', item.payment_reference || '-'],
        ],
        columns: ['Earning', 'Amount', 'Deduction', 'Amount'],
        rows: [
            ['Base salary', money(item.base_salary), 'Advance', money(item.advance_deduction)],
            ['Allowance', money(item.allowance_amount), 'Other', money(item.other_deduction)],
            ['Incentive', money(item.incentive_amount), 'Total deductions', money(deductions)],
            ['Overtime', money(item.ot_amount), '', ''],
        ],
        total: ['Net pay', money(item.net_pay)],
        signatures: ['Prepared by', 'Employee'],
    });
    return (
        <DetailPage eyebrow={t(locale, 'salaryHistory')} title={item.employee_name} subtitle={`${item.employee_code} · ${item.month}`} onBack={onClose} actions={<button className="icon-button" type="button" title="Print payslip" aria-label="Print payslip" onClick={printPayslip}><Printer size={16} /></button>}
            aside={<DetailPanel eyebrow={t(locale, 'netPay')}><section className="payroll-total-card record-page-summary"><CircleDollarSign size={18} /><div><small>{item.payroll_code}</small><strong>{money(item.net_pay)}</strong><span>{t(locale, 'paidAt')}: {formatDateTime(item.paid_at)}</span></div></section></DetailPanel>}
        >
            <DetailPanel eyebrow={t(locale, 'details')} title={t(locale, 'salaryHistory')}>
                    <dl className="record-page-facts salary-history-detail">
                        <InfoLine label={t(locale, 'employee')} value={`${item.employee_code} - ${item.employee_name}`} />
                        <InfoLine label={t(locale, 'employeeType')} value={titleCase(item.employee_type)} />
                        <InfoLine label={t(locale, 'period')} value={`${item.period_start} - ${item.period_end}`} />
                        <InfoLine label={t(locale, 'paymentReference')} value={item.payment_reference || '-'} />
                        <InfoLine label={t(locale, 'attendance')} value={`${item.accepted_count} / ${item.rejected_count}`} />
                        <InfoLine label={t(locale, 'baseSalary')} value={money(item.base_salary)} />
                        <InfoLine label={t(locale, 'allowance')} value={money(item.allowance_amount)} />
                        <InfoLine label={t(locale, 'incentive')} value={money(item.incentive_amount)} />
                        <InfoLine label={t(locale, 'ot')} value={money(item.ot_amount)} />
                        <InfoLine label={t(locale, 'deductions')} value={money(deductions)} />
                        <InfoLine label={t(locale, 'grossPay')} value={money(item.gross_pay)} />
                        <InfoLine label={t(locale, 'netPay')} value={money(item.net_pay)} />
                    </dl>
            </DetailPanel>
        </DetailPage>
    );
}

function MobileSalaryDetailSheet({ item, locale, onClose }) {
    return (
        <div className="drawer-backdrop mobile" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
            <aside className="mobile-detail-sheet" role="dialog" aria-modal="true" aria-label={t(locale, 'details')}>
                <header><div><p className="eyebrow">{t(locale, 'salaryHistory')}</p><h2>{item.month}</h2></div><button className="icon-button" type="button" onClick={onClose} aria-label={t(locale, 'cancel')}><X size={17} /></button></header>
                <dl className="mobile-info-list">
                    <InfoLine label={t(locale, 'payroll')} value={item.payroll_code} />
                    <InfoLine label={t(locale, 'status')} value={<StatusBadge status={item.status} locale={locale} />} />
                    <InfoLine label={t(locale, 'paidAt')} value={formatDateTime(item.paid_at)} />
                    <InfoLine label={t(locale, 'paymentReference')} value={item.payment_reference || '-'} />
                    <InfoLine label={t(locale, 'attendance')} value={`${item.accepted_count} / ${item.rejected_count}`} />
                    <InfoLine label={t(locale, 'baseSalary')} value={money(item.base_salary)} />
                    <InfoLine label={t(locale, 'allowance')} value={money(item.allowance_amount)} />
                    <InfoLine label={t(locale, 'incentive')} value={money(item.incentive_amount)} />
                    <InfoLine label={t(locale, 'ot')} value={money(item.ot_amount)} />
                    <InfoLine label={t(locale, 'deductions')} value={money(Number(item.advance_deduction || 0) + Number(item.other_deduction || 0))} />
                    <InfoLine label={t(locale, 'grossPay')} value={money(item.gross_pay)} />
                    <InfoLine label={t(locale, 'netPay')} value={money(item.net_pay)} />
                </dl>
            </aside>
        </div>
    );
}

function InfoLine({ label, value }) {
    return <div><dt>{label}</dt><dd>{value}</dd></div>;
}

function WorkspaceState({ icon: Icon, title, action, actionLabel, loading = false, compact = false }) {
    return <div className={`workspace-state ${compact ? 'compact' : ''}`}><Icon className={loading ? 'spin' : ''} size={22} /><strong>{title}</strong>{action && <button className="button" type="button" onClick={action}>{actionLabel}</button>}</div>;
}

function StatusBadge({ status, locale }) {
    const family = ['paid', 'active'].includes(status) ? 'success' : status === 'approved' ? 'info' : status === 'draft' ? 'warning' : 'neutral';
    return <span className={`status ${family}`}>{t(locale, status)}</span>;
}

function money(value) {
    return `${Number(value || 0).toLocaleString()} MMK`;
}

function titleCase(value) {
    return String(value || '').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function formatDateTime(value) {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return date.toLocaleString([], { year: 'numeric', month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit' });
}

function formatShortDate(value) {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return date.toLocaleDateString([], { month: 'short', day: '2-digit' });
}
