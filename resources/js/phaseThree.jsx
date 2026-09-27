import { AlertCircle, BadgeCheck, CalendarDays, CircleDollarSign, FileClock, Pencil, Plus, RefreshCw, Save, Search, Trash2, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { DetailPage, DetailPanel } from './components/DetailPage';

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

function apiBase(name) {
    const fallback = { masterData: '/api/master-data', mobilePayroll: '/api/mobile/payroll', payrollHistory: '/api/payroll-history', payrolls: '/api/payrolls', payrollAdjustments: '/api/payroll-adjustments' };
    return window.ValleyRuntime?.api?.[name] || fallback[name];
}

function requestMessage(error, locale, fallbackKey = 'loading') {
    return locale === 'my' ? t(locale, fallbackKey) : error.response?.data?.message || t(locale, fallbackKey);
}

export function PayrollDraftsScreen({ locale, canManage = false, detailId = null, onNavigate }) {
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
        if (!canManage) return;
        setGenerating(true);
        setState((current) => ({ ...current, error: '' }));
        try {
            const { data } = await window.axios.post(`${apiBase('payrolls')}/generate`, {
                month: filters.month,
                employee_type: filters.employee_type || null,
            });
            setViewing(data.data);
            setRefreshKey((key) => key + 1);
            onNavigate?.(`${listPath}/${data.data.payroll.id}`);
        } catch (error) {
            setState((current) => ({ ...current, error: requestMessage(error, locale) }));
        } finally {
            setGenerating(false);
        }
    };

    const transitionPayroll = async (payroll, action) => {
        if (!canManage || processingId) return;

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
            setRefreshKey((key) => key + 1);
        } catch (error) {
            setState((current) => ({ ...current, error: requestMessage(error, locale) }));
        } finally {
            setProcessingId(null);
        }
    };

    const deletePayroll = async (payroll) => {
        if (!canManage || processingId || payroll.status !== 'draft') return;
        if (!window.confirm(t(locale, 'deletePayrollConfirm'))) return;

        setProcessingId(payroll.id);
        setState((current) => ({ ...current, error: '' }));
        try {
            await window.axios.delete(`${apiBase('payrolls')}/${payroll.id}`);
            setViewing(null);
            setRefreshKey((key) => key + 1);
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
    const showPayrollActions = canManage && state.items.some((payroll) => ['draft', 'approved'].includes(payroll.status));

    if (detailId && viewing) return <PayrollDetailPage viewing={viewing} locale={locale} canManage={canManage} processingId={processingId} operationError={state.error} onApprove={(payroll) => transitionPayroll(payroll, 'approve')} onMarkPaid={(payroll) => transitionPayroll(payroll, 'mark-paid')} onDelete={deletePayroll} onClose={() => onNavigate?.(listPath)} />;

    return (
        <section className="master-workspace payroll-workspace">
            <div className="master-heading">
                <div>
                    <p className="eyebrow">{t(locale, 'payroll')}</p>
                    <h1>{t(locale, 'payroll')}</h1>
                    <span className="muted">{t(locale, 'payrollHint')}</span>
                </div>
                {canManage && <button className="button primary" type="button" disabled={generating} onClick={generate}><Plus size={16} /> {t(locale, 'generateDraft')}</button>}
            </div>

            <div className="metrics payroll-metrics">
                <div className="metric"><span>{t(locale, 'totalEmployees')}</span><strong>{totals.employees}</strong><small>{filters.month}</small></div>
                <div className="metric"><span>{t(locale, 'grossPay')}</span><strong>{money(totals.gross)}</strong><small>{t(locale, 'totalCost')}</small></div>
                <div className="metric"><span>{t(locale, 'deductions')}</span><strong>{money(totals.deductions)}</strong><small>{t(locale, 'payroll')}</small></div>
                <div className="metric"><span>{t(locale, 'netPay')}</span><strong>{money(totals.net)}</strong><small>{t(locale, 'totalCost')}</small></div>
            </div>

            <div className="master-panel">
                <div className="master-toolbar payroll-toolbar">
                    <label className="date-filter" title={t(locale, 'month')}><CalendarDays size={14} /><input type="month" value={filters.month} onChange={(event) => setFilters((current) => ({ ...current, month: event.target.value }))} /></label>
                    <select aria-label={t(locale, 'employeeType')} value={filters.employee_type} onChange={(event) => setFilters((current) => ({ ...current, employee_type: event.target.value }))}>
                        <option value="">{t(locale, 'allEmployeeTypes')}</option>
                        {['office', 'sales', 'driver', 'warehouse'].map((type) => <option value={type} key={type}>{titleCase(type)}</option>)}
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
                                            {payroll.status === 'draft' && <button type="button" disabled={processingId === payroll.id} aria-label={t(locale, 'approve')} title={t(locale, 'approve')} onClick={() => transitionPayroll(payroll, 'approve')}><BadgeCheck size={15} /></button>}
                                            {payroll.status === 'draft' && <button className="danger" type="button" disabled={processingId === payroll.id} aria-label={t(locale, 'delete')} title={t(locale, 'delete')} onClick={() => deletePayroll(payroll)}><Trash2 size={15} /></button>}
                                            {payroll.status === 'approved' && <button type="button" disabled={processingId === payroll.id} aria-label={t(locale, 'paid')} title={t(locale, 'paid')} onClick={() => transitionPayroll(payroll, 'mark-paid')}><CircleDollarSign size={15} /></button>}
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
            if (editing?.id) {
                await window.axios.put(`${apiBase('payrollAdjustments')}/${editing.id}`, payload);
            } else {
                await window.axios.post(apiBase('payrollAdjustments'), payload);
            }
            closeForm();
            setRefreshKey((key) => key + 1);
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
            setRefreshKey((key) => key + 1);
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
                {canManage && <button className="button primary" type="button" onClick={() => openForm()}><Plus size={16} /> {t(locale, 'adjustment')}</button>}
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
                    <label className="date-filter" title={t(locale, 'month')}><CalendarDays size={14} /><input type="month" value={filters.month} onChange={(event) => setFilters((current) => ({ ...current, month: event.target.value }))} /></label>
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
                <button className="icon-button" type="button" aria-label={t(locale, 'refresh')} title={t(locale, 'refresh')} onClick={() => setRefreshKey((key) => key + 1)}><RefreshCw size={15} /></button>
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
                    <label className="date-filter" title={t(locale, 'month')}><CalendarDays size={14} /><input type="month" value={filters.month} onChange={(event) => setFilters((current) => ({ ...current, month: event.target.value }))} /></label>
                    <select aria-label={t(locale, 'employeeType')} value={filters.employee_type} onChange={(event) => setFilters((current) => ({ ...current, employee_type: event.target.value }))}>
                        <option value="">{t(locale, 'allEmployeeTypes')}</option>
                        {['office', 'sales', 'driver', 'warehouse'].map((type) => <option value={type} key={type}>{titleCase(type)}</option>)}
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
                <button className="icon-button" type="button" aria-label={t(locale, 'refresh')} title={t(locale, 'refresh')} onClick={() => setRefreshKey((key) => key + 1)}><RefreshCw size={18} /></button>
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

function PayrollDetailPage({ viewing, locale, canManage = false, processingId = null, operationError = '', onApprove, onMarkPaid, onDelete, onClose }) {
    const [state, setState] = useState(() => viewing.items ? { loading: false, payroll: viewing.payroll, items: viewing.items, error: '' } : { loading: true, payroll: viewing.payroll, items: [], error: '' });

    useEffect(() => {
        if (viewing.items) return undefined;
        let mounted = true;
        window.axios.get(`${apiBase('payrolls')}/${viewing.payroll.id}`)
            .then(({ data }) => mounted && setState({ loading: false, payroll: data.data.payroll, items: data.data.items, error: '' }))
            .catch((error) => mounted && setState((current) => ({ ...current, loading: false, error: requestMessage(error, locale) })));
        return () => { mounted = false; };
    }, [locale, viewing]);

    const actions = canManage && ['draft', 'approved'].includes(state.payroll.status) ? <>
        {state.payroll.status === 'draft' && <button className="button primary" type="button" disabled={processingId === state.payroll.id} onClick={() => onApprove?.(state.payroll)}><BadgeCheck size={15} /> {t(locale, 'approve')}</button>}
        {state.payroll.status === 'draft' && <button className="button danger" type="button" disabled={processingId === state.payroll.id} onClick={() => onDelete?.(state.payroll)}><Trash2 size={15} /> {t(locale, 'delete')}</button>}
        {state.payroll.status === 'approved' && <button className="button primary" type="button" disabled={processingId === state.payroll.id} onClick={() => onMarkPaid?.(state.payroll)}><CircleDollarSign size={15} /> {t(locale, 'paid')}</button>}
    </> : null;

    return (
        <DetailPage eyebrow={t(locale, 'payroll')} title={state.payroll.code || t(locale, 'loading')} subtitle={state.payroll.month} onBack={onClose} actions={actions}
            aside={!state.loading && <DetailPanel eyebrow={t(locale, 'summary')}><section className="payroll-total-card record-page-summary"><FileClock size={18} /><div><small>{state.payroll.month}</small><strong>{money(state.payroll.total_net)}</strong><span>{state.payroll.items_count} {t(locale, 'totalEmployees')} · {t(locale, state.payroll.status)}</span></div></section></DetailPanel>}
        >
            {(state.error || operationError) && <div className="inline-error"><AlertCircle size={15} /> {state.error || operationError}</div>}
            {state.loading ? <WorkspaceState icon={RefreshCw} title={t(locale, 'loading')} loading compact /> : <>
                {(state.payroll.approved_at || state.payroll.paid_at || state.payroll.payment_reference) && (
                    <DetailPanel eyebrow={t(locale, 'status')} title={t(locale, 'details')}><section className="payroll-workflow-card">
                        {state.payroll.approved_at && <span><strong>{t(locale, 'approvedAt')}</strong>{formatDateTime(state.payroll.approved_at)}</span>}
                        {state.payroll.paid_at && <span><strong>{t(locale, 'paidAt')}</strong>{formatDateTime(state.payroll.paid_at)}</span>}
                        {state.payroll.payment_reference && <span><strong>{t(locale, 'paymentReference')}</strong>{state.payroll.payment_reference}</span>}
                    </section></DetailPanel>
                )}
                <DetailPanel eyebrow={t(locale, 'employees')} title={`${state.payroll.items_count} ${t(locale, 'totalEmployees')}`}>
                    <div className="master-table-wrap">
                            <table className="master-table payroll-item-table">
                                <colgroup><col className="payroll-item-employee" /><col className="payroll-item-attendance" /><col className="payroll-item-money" /><col className="payroll-item-money" /></colgroup>
                                <thead><tr><th>{t(locale, 'employee')}</th><th>{t(locale, 'attendance')}</th><th>{t(locale, 'baseSalary')}</th><th>{t(locale, 'netPay')}</th></tr></thead>
                                <tbody>{state.items.map((item) => <tr key={item.id}><td><strong>{item.employee_name}</strong><span className="muted">{item.employee_code}</span></td><td>{item.accepted_count} / {item.rejected_count}</td><td>{money(item.base_salary)}</td><td>{money(item.net_pay)}</td></tr>)}</tbody>
                            </table>
                    </div>
                </DetailPanel>
            </>}
        </DetailPage>
    );
}

function SalaryHistoryDetailPage({ item, locale, onClose }) {
    return (
        <DetailPage eyebrow={t(locale, 'salaryHistory')} title={item.employee_name} subtitle={`${item.employee_code} · ${item.month}`} onBack={onClose}
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
                        <InfoLine label={t(locale, 'deductions')} value={money(Number(item.advance_deduction || 0) + Number(item.other_deduction || 0))} />
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
