import { AlertCircle, BadgeCheck, CalendarDays, CircleGauge, Clock3, RefreshCw, Save, Search, Send, Target, TrendingUp, Users, WalletCards, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { ShellPageActions } from './components/ShellPageActions';
import { MonthSelect } from './components/MonthSelect';

const currentMonth = () => {
    const date = new Date();
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
};
const apiBase = () => window.ValleyRuntime?.api?.kpiReviews || '/api/kpi-reviews';
const targetApi = () => window.ValleyRuntime?.api?.kpiTargets || '/api/kpi-targets';
const mobileApi = () => window.ValleyRuntime?.api?.mobileKpi || '/api/mobile/kpi';
const money = (value) => `${Number(value || 0).toLocaleString()} MMK`;
const number = (value) => Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 2 });
const titleCase = (value) => String(value || '').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());

function errorMessage(error, fallback) {
    if (error.response?.data?.errors) {
        const first = Object.values(error.response.data.errors).flat()[0];
        if (first) return first;
    }
    return error.response?.data?.message || fallback;
}

export function MobileKpiScreen() {
    const [month, setMonth] = useState(currentMonth());
    const [refreshKey, setRefreshKey] = useState(0);
    const [state, setState] = useState({ loading: true, result: null, items: [], previous: null, history: [], error: '' });

    useEffect(() => {
        let mounted = true;
        setState((current) => ({ ...current, loading: true, error: '' }));
        window.axios.get(mobileApi(), { params: { month } })
            .then(({ data }) => mounted && setState({ loading: false, ...data.data, error: '' }))
            .catch((error) => mounted && setState({
                loading: false,
                result: null,
                items: [],
                previous: null,
                history: [],
                error: errorMessage(error, 'Unable to load your KPI review.'),
            }));
        return () => { mounted = false; };
    }, [month, refreshKey]);

    const { result, items, previous, history, loading, error } = state;
    const scoreChange = result && previous ? Number(result.overall_score) - Number(previous.overall_score) : null;

    return (
        <div className="mobile-master-stack mobile-kpi-page">
            <div className="mobile-master-heading mobile-kpi-heading">
                <div>
                    <span>Performance</span>
                    <h1>My KPI</h1>
                    <p>Your monthly score and supporting figures.</p>
                </div>
                <ShellPageActions><button className="icon-button" type="button" aria-label="Refresh KPI" title="Refresh KPI" disabled={loading} onClick={() => setRefreshKey((key) => key + 1)}>
                    <RefreshCw className={loading ? 'spin' : ''} size={17} />
                </button></ShellPageActions>
            </div>

            <section className="mobile-master-section mobile-kpi-period">
                <label>
                    <CalendarDays size={16} />
                    <span>Review month</span>
                    <MonthSelect value={month} aria-label="KPI report month" onChange={(event) => setMonth(event.target.value)} />
                </label>
            </section>

            {error && <div className="inline-error"><AlertCircle size={16} /> {error}</div>}
            {loading && <KpiState icon={RefreshCw} title="Loading your KPI" loading />}
            {!loading && !error && !result && (
                <KpiState icon={Clock3} title="No review for this month" message="Office has not created your KPI review yet. Choose an earlier month below if available." />
            )}

            {!loading && result && (
                <>
                    <section className="mobile-kpi-hero">
                        <div className="mobile-kpi-score" style={{ '--kpi-score': `${Math.min(Math.max(Number(result.overall_score), 0), 100) * 3.6}deg` }}>
                            <div><strong>{number(result.overall_score)}</strong><span>Score</span></div>
                        </div>
                        <div className="mobile-kpi-hero-copy">
                            <div className="mobile-kpi-title-row"><strong>{result.template_name}</strong><KpiStatus status={result.status} /></div>
                            <span>{result.month} · {result.employee_name}</span>
                            <div className="mobile-kpi-bonus"><small>{result.payroll_adjustment_id ? 'Posted to payroll' : result.status === 'approved' ? 'Approved bonus' : 'Bonus preview'}</small><strong>{money(result.bonus_amount)}</strong></div>
                            {scoreChange !== null && <small className={scoreChange >= 0 ? 'is-positive' : 'is-negative'}><TrendingUp size={13} /> {scoreChange >= 0 ? '+' : ''}{number(scoreChange)} points from {previous.month}</small>}
                        </div>
                    </section>

                    <section className="mobile-master-section mobile-kpi-metrics">
                        <header><div><span>Score details</span><strong>{items.length} metrics</strong></div><small>Contribution to 100 points</small></header>
                        <div className="mobile-kpi-metric-list">
                            {items.map((item) => {
                                const manual = item.calculation_type === 'manual';
                                const contribution = item.weighted_score === null ? 0 : Math.min(Number(item.weighted_score) / Math.max(Number(item.weight), 1) * 100, 100);
                                const actualValue = manual ? item.manual_score : item.actual_value;
                                return (
                                    <article key={item.id}>
                                        <div className="mobile-kpi-metric-title">
                                            <div><strong>{item.name}</strong><span>{manual ? 'Manager assessment' : item.is_automatic ? 'Updated from operations' : item.unit}</span></div>
                                            <b>{item.weighted_score === null ? 'Pending' : `${number(item.weighted_score)} pts`}</b>
                                        </div>
                                        <div className="mobile-kpi-progress"><i style={{ width: `${contribution}%` }} /></div>
                                        <div className="mobile-kpi-values">
                                            {!manual && <span><small>Target</small><strong>{item.target_value === null ? '—' : number(item.target_value)}</strong></span>}
                                            <span><small>{manual ? 'Score' : 'Actual'}</small><strong>{actualValue === null ? '—' : number(actualValue)}</strong></span>
                                            <span><small>Achievement</small><strong>{item.achievement_percent === null ? '—' : `${number(item.achievement_percent)}%`}</strong></span>
                                            <span><small>Weight</small><strong>{number(item.weight)}%</strong></span>
                                        </div>
                                        {item.is_automatic && item.source_note && <p className="mobile-kpi-source"><BadgeCheck size={13} /> {item.source_note}</p>}
                                    </article>
                                );
                            })}
                        </div>
                    </section>

                    {result.notes && <section className="mobile-master-section mobile-kpi-note"><span>Review note</span><p>{result.notes}</p></section>}
                </>
            )}

            {!loading && history.length > 0 && (
                <section className="mobile-master-section mobile-kpi-history">
                    <header><div><span>Recent reviews</span><strong>Monthly history</strong></div></header>
                    <div>
                        {history.slice(0, 6).map((review) => (
                            <button className={review.month === month ? 'is-active' : ''} type="button" key={review.id} onClick={() => setMonth(review.month)}>
                                <span><strong>{review.month}</strong><KpiStatus status={review.status} /></span>
                                <span><strong>{number(review.overall_score)}%</strong><small>{money(review.bonus_amount)}</small></span>
                            </button>
                        ))}
                    </div>
                </section>
            )}
        </div>
    );
}

export function KpiTargetsScreen({ canManage = false, embedded = false }) {
    const [filters, setFilters] = useState({ employee_type: '', search: '' });
    const [state, setState] = useState({ loading: true, items: [], templates: [], summary: {}, error: '', success: '' });
    const [editing, setEditing] = useState(null);
    const [form, setForm] = useState({ target_bonus: 40000, targets: {} });
    const [saving, setSaving] = useState(false);
    const [refreshKey, setRefreshKey] = useState(0);

    useEffect(() => {
        let mounted = true;
        setState((current) => ({ ...current, loading: true, error: '' }));
        window.axios.get(targetApi(), { params: {
            employee_type: filters.employee_type || undefined,
            search: filters.search || undefined,
        } }).then(({ data }) => mounted && setState((current) => ({ ...current, loading: false, ...data.data, error: '' })))
            .catch((error) => mounted && setState((current) => ({ ...current, loading: false, items: [], error: errorMessage(error, 'Unable to load staff KPI targets.') })));
        return () => { mounted = false; };
    }, [filters, refreshKey]);

    const openTarget = (role) => {
        setEditing(role);
        setForm({
            target_bonus: role.target_bonus ?? 40000,
            targets: Object.fromEntries((role.metrics || []).map((metric) => [metric.id, metric.default_target ?? ''])),
        });
        setState((current) => ({ ...current, error: '', success: '' }));
    };

    const saveTarget = async (event) => {
        event.preventDefault();
        if (!canManage || saving) return;
        setSaving(true);
        setState((current) => ({ ...current, error: '', success: '' }));
        try {
            const { data } = await window.axios.put(`${targetApi()}/roles/${editing.id}`, {
                target_bonus: form.target_bonus,
                apply_to_staff: true,
                targets: (editing.metrics || []).filter((metric) => metric.calculation_type !== 'manual').map((metric) => ({
                    metric_id: metric.id,
                    target_value: form.targets[metric.id] === '' ? null : form.targets[metric.id],
                })),
            });
            const staff = Number(data.data?.staff_count || 0);
            const drafts = Number(data.data?.updated_drafts || 0);
            const targetValues = form.targets;
            const updatedRole = {
                ...editing,
                target_bonus: Number(form.target_bonus || 0),
                metrics: editing.metrics.map((metric) => metric.calculation_type === 'manual' ? metric : { ...metric, default_target: targetValues[metric.id] === '' ? null : Number(targetValues[metric.id]) }),
            };
            updatedRole.configured_targets = updatedRole.metrics.filter((metric) => metric.calculation_type !== 'manual' && metric.default_target !== null).length;
            setEditing(null);
            setState((current) => ({ ...current, items: current.items.map((role) => role.id === updatedRole.id ? updatedRole : role), success: `Role target saved for ${staff} staff member(s)${drafts ? ` and ${drafts} draft review(s)` : ''}.`, error: '' }));
        } catch (error) {
            setState((current) => ({ ...current, error: errorMessage(error, 'Unable to save staff KPI targets.') }));
        } finally {
            setSaving(false);
        }
    };

    return (
        <section className={`master-workspace kpi-target-workspace ${embedded ? 'is-embedded' : ''}`}>
            <div className="master-heading">
                <div>
                    <p className="eyebrow">Performance setup</p>
                    <h1>KPI role targets</h1>
                    <span className="muted">Set one shared target for each staff role, then apply it to everyone assigned to that role.</span>
                </div>
            </div>

            <div className="master-panel">
                <div className="master-toolbar kpi-target-toolbar">
                    <label className="master-search"><Search size={15} /><input value={filters.search} placeholder="Search KPI role or code" onChange={(event) => setFilters((current) => ({ ...current, search: event.target.value }))} /></label>
                    <select aria-label="Staff group" value={filters.employee_type} onChange={(event) => setFilters((current) => ({ ...current, employee_type: event.target.value }))}>
                        <option value="">All staff groups</option>
                        <option value="sales">Sales</option>
                        <option value="sales_supervisor">Sales Supervisor</option>
                        <option value="driver">Driver</option>
                        <option value="warehouse">Warehouse</option>
                        <option value="office">Office</option>
                    </select>
                    <button className="icon-button" type="button" aria-label="Refresh" title="Refresh" onClick={() => setRefreshKey((key) => key + 1)}><RefreshCw size={15} /></button>
                </div>

                {state.success && <div className="inline-success"><BadgeCheck size={15} /> {state.success}</div>}
                {state.error && !editing && <div className="inline-error"><AlertCircle size={15} /> {state.error}</div>}
                {state.loading ? <KpiState icon={RefreshCw} title="Loading role targets" loading /> : state.items.length === 0 ? <KpiState icon={Target} title="No KPI roles found" message="Change the filters to find a KPI role." /> : (
                    <div className="master-table-wrap kpi-target-table-wrap">
                        <table className="master-table kpi-target-table">
                            <thead><tr><th>KPI role</th><th>Staff group</th><th>Monthly bonus</th><th>Targets</th>{canManage && <th className="table-actions-header">Actions</th>}</tr></thead>
                            <tbody>{state.items.map((role) => (
                                <tr key={role.id} className={canManage ? 'clickable-row' : ''} onClick={() => canManage && openTarget(role)}>
                                    <td><strong>{role.name}</strong><span className="muted">{role.code}</span></td>
                                    <td>{titleCase(role.employee_type)}</td>
                                    <td>{money(role.target_bonus)}</td>
                                    <td><strong>{role.configured_targets} / {role.total_targets}</strong></td>
                                    {canManage && <td className="table-actions-cell" onClick={(event) => event.stopPropagation()}><div className="row-actions"><button type="button" aria-label={`Edit ${role.name} targets`} title="Edit role targets" onClick={() => openTarget(role)}><Target size={15} /></button></div></td>}
                                </tr>
                            ))}</tbody>
                        </table>
                    </div>
                )}
            </div>

            {editing && (
                <div className="modal-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && setEditing(null)}>
                    <form className="master-dialog kpi-target-dialog" role="dialog" aria-modal="true" aria-label={`${editing.name} role targets`} onSubmit={saveTarget}>
                        <header>
                            <div><p className="eyebrow">Role target</p><h2>{editing.name}</h2><span className="muted">{editing.code} · {titleCase(editing.employee_type)} · {editing.staff_count} assigned staff</span></div>
                            <button className="icon-button" type="button" aria-label="Close" onClick={() => setEditing(null)}><X size={17} /></button>
                        </header>
                        <div className="kpi-target-dialog-body">
                            {state.error && <div className="inline-error"><AlertCircle size={15} /> {state.error}</div>}
                            <div className="kpi-target-fields">
                                <div className="kpi-target-role-note"><Users size={17} /><span><strong>Apply to all assigned staff</strong><small>Saving replaces the targets for all {editing.staff_count} staff member(s) assigned to this KPI role.</small></span></div>
                                <label className="master-field"><span>Monthly target bonus</span><div className="kpi-target-money"><input type="number" min="0" step="1000" value={form.target_bonus} onChange={(event) => setForm((current) => ({ ...current, target_bonus: event.target.value }))} /><b>MMK</b></div></label>
                            </div>

                            <div className="master-table-wrap">
                                <table className="master-table kpi-target-metric-table">
                                    <thead><tr><th>Metric</th><th>Weight</th><th>Role target</th></tr></thead>
                                    <tbody>{editing.metrics.map((metric) => (
                                        <tr key={metric.id}>
                                            <td><strong>{metric.name}</strong><span className="muted">{metric.code} · {metric.unit}</span></td>
                                            <td>{number(metric.weight)}%</td>
                                            <td>{metric.calculation_type === 'manual' ? <span className="kpi-manager-score">Manager scores 0–100 monthly</span> : <input className="kpi-cell-input" type="number" min="0" step="0.01" value={form.targets[metric.id] ?? ''} placeholder="Set role target" aria-label={`${metric.name} role target`} onChange={(event) => setForm((current) => ({ ...current, targets: { ...current.targets, [metric.id]: event.target.value } }))} />}</td>
                                        </tr>
                                    ))}</tbody>
                                </table>
                            </div>
                        </div>
                        <footer><button className="button" type="button" onClick={() => setEditing(null)}>Cancel</button><button className="button primary" type="submit" disabled={saving}><Save size={15} /> {saving ? 'Saving…' : 'Save & apply to staff'}</button></footer>
                    </form>
                </div>
            )}
        </section>
    );
}

export function KpiReviewsScreen({ canManage = false, canApprove = canManage }) {
    const [filters, setFilters] = useState({ month: currentMonth(), template_id: '', status: '', search: '' });
    const [state, setState] = useState({ loading: true, items: [], summary: {}, error: '' });
    const [templates, setTemplates] = useState([]);
    const [refreshKey, setRefreshKey] = useState(0);
    const [selected, setSelected] = useState(null);
    const [form, setForm] = useState({ notes: '', items: [] });
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        let mounted = true;
        window.axios.get(`${apiBase()}/meta`).then(({ data }) => {
            if (!mounted) return;
            const available = data.data.templates || [];
            setTemplates(available);
        }).catch(() => {});
        return () => { mounted = false; };
    }, []);

    useEffect(() => {
        let mounted = true;
        setState((current) => ({ ...current, loading: true, error: '' }));
        window.axios.get(apiBase(), { params: { ...filters, template_id: filters.template_id || undefined, search: filters.search || undefined, status: filters.status || undefined, per_page: 50 } })
            .then(({ data }) => mounted && setState({ loading: false, items: data.data.items, summary: data.data.summary, error: '' }))
            .catch((error) => mounted && setState({ loading: false, items: [], summary: {}, error: errorMessage(error, 'Unable to load KPI reviews.') }));
        return () => { mounted = false; };
    }, [filters, refreshKey]);

    const openReview = async (id) => {
        setProcessing(true);
        setState((current) => ({ ...current, error: '' }));
        try {
            const { data } = await window.axios.get(`${apiBase()}/${id}`);
            setSelected(data.data);
            setForm({ notes: data.data.result.notes || '', items: data.data.items.map((item) => ({ ...item })) });
        } catch (error) {
            setState((current) => ({ ...current, error: errorMessage(error, 'Unable to open KPI review.') }));
        } finally {
            setProcessing(false);
        }
    };

    const updateItem = (id, field, value) => {
        setForm((current) => ({
            ...current,
            items: current.items.map((item) => item.id === id ? { ...item, [field]: value } : item),
        }));
    };

    const saveDraft = async () => {
        const { data } = await window.axios.put(`${apiBase()}/${selected.result.id}`, {
            notes: form.notes || null,
            items: form.items.map((item) => ({
                id: item.id,
                target_value: item.target_value === '' ? null : item.target_value,
                actual_value: item.actual_value === '' ? null : item.actual_value,
                manual_score: item.manual_score === '' ? null : item.manual_score,
                notes: item.notes || null,
            })),
        });
        setSelected(data.data);
        setForm({ notes: data.data.result.notes || '', items: data.data.items.map((item) => ({ ...item })) });
        updateReviewRow(data.data.result);
        return data.data;
    };

    const updateReviewRow = (result) => {
        setState((current) => {
            const previous = current.items.find((item) => item.id === result.id);
            const items = current.items.map((item) => item.id === result.id ? { ...item, ...result } : item);
            const summary = { ...current.summary };
            if (previous && previous.status !== result.status) {
                summary[previous.status] = Math.max(0, Number(summary[previous.status] || 0) - 1);
                summary[result.status] = Number(summary[result.status] || 0) + 1;
            }
            return { ...current, items, summary };
        });
    };

    const perform = async (action) => {
        if (!(action === 'approve' || action === 'post-bonus' ? canApprove : canManage) || processing) return;
        setProcessing(true);
        setState((current) => ({ ...current, error: '' }));
        try {
            if (action === 'save') {
                await saveDraft();
            } else if (action === 'submit') {
                const saved = await saveDraft();
                const { data } = await window.axios.post(`${apiBase()}/${saved.result.id}/submit`);
                setSelected(data.data);
                setForm({ notes: data.data.result.notes || '', items: data.data.items.map((item) => ({ ...item })) });
                updateReviewRow(data.data.result);
            } else if (action === 'approve') {
                const { data } = await window.axios.post(`${apiBase()}/${selected.result.id}/approve`);
                setSelected(data.data);
                updateReviewRow(data.data.result);
            } else if (action === 'post-bonus') {
                const { data } = await window.axios.post(`${apiBase()}/${selected.result.id}/post-bonus`);
                setSelected(data.data);
                updateReviewRow(data.data.result);
            }
        } catch (error) {
            setState((current) => ({ ...current, error: errorMessage(error, 'Unable to update KPI review.') }));
        } finally {
            setProcessing(false);
        }
    };

    const summary = state.summary || {};
    return (
        <section className="master-workspace kpi-review-workspace">
            <div className="master-heading">
                <div>
                    <p className="eyebrow">Performance</p>
                    <h1>Monthly KPI reviews</h1>
                    <span className="muted">Monthly reviews are created automatically. Check manager-scored metrics, then submit and approve.</span>
                </div>
                <label className="kpi-review-month-selector">
                    <span><CalendarDays size={14} /> Review month</span>
                    <MonthSelect value={filters.month} aria-label="KPI review month" onChange={(event) => { setSelected(null); setFilters((current) => ({ ...current, month: event.target.value })); }} />
                </label>
            </div>

            <div className="metrics kpi-review-metrics">
                <div className="metric"><span>Employees</span><strong>{summary.total || 0}</strong><small>{filters.month}</small></div>
                <div className="metric"><span>Draft</span><strong>{summary.draft || 0}</strong><small>Being prepared</small></div>
                <div className="metric"><span>Waiting approval</span><strong>{summary.submitted || 0}</strong><small>Submitted</small></div>
                <div className="metric"><span>Projected bonus</span><strong>{money(summary.bonus_total)}</strong><small>{summary.posted || 0} posted to payroll</small></div>
            </div>

            <div className="master-panel">
                <div className="master-toolbar kpi-review-toolbar">
                    <select aria-label="KPI role" value={filters.template_id} onChange={(event) => setFilters((current) => ({ ...current, template_id: event.target.value }))}>
                        <option value="">All KPI roles</option>
                        {templates.map((template) => <option value={template.id} key={template.id}>{template.name}</option>)}
                    </select>
                    <select aria-label="Status" value={filters.status} onChange={(event) => setFilters((current) => ({ ...current, status: event.target.value }))}>
                        <option value="">All statuses</option>
                        <option value="draft">Draft</option>
                        <option value="submitted">Submitted</option>
                        <option value="approved">Approved</option>
                    </select>
                    <label className="master-search"><Search size={15} /><input value={filters.search} placeholder="Search employee" onChange={(event) => setFilters((current) => ({ ...current, search: event.target.value }))} /></label>
                    <button className="icon-button" type="button" aria-label="Refresh" title="Refresh" onClick={() => setRefreshKey((key) => key + 1)}><RefreshCw size={15} /></button>
                </div>

                {state.error && <div className="inline-error"><AlertCircle size={15} /> {state.error}</div>}
                {state.loading ? <KpiState icon={RefreshCw} title="Loading KPI reviews" loading /> : state.items.length === 0 ? <KpiState icon={CircleGauge} title="No reviews for this month" message="Assign an active KPI role and targets to staff to include them automatically." /> : (
                    <div className="master-table-wrap">
                        <table className="master-table kpi-review-table">
                            <thead><tr><th>Employee</th><th>Role</th><th>Score</th><th>Bonus</th><th>Status</th><th>Updated</th></tr></thead>
                            <tbody>{state.items.map((result) => (
                                <tr className="clickable-row" key={result.id} tabIndex={0} onClick={() => !processing && openReview(result.id)} onKeyDown={(event) => { if (!processing && event.target === event.currentTarget && ['Enter', ' '].includes(event.key)) { event.preventDefault(); openReview(result.id); } }}>
                                    <td><strong>{result.employee_name}</strong><span className="muted">{result.employee_code}</span></td>
                                    <td>{result.template_name}</td>
                                    <td><strong>{number(result.overall_score)}%</strong></td>
                                    <td className="kpi-bonus-cell"><strong>{money(result.bonus_amount)}</strong>{result.payroll_adjustment_id && <span><BadgeCheck size={11} /> Posted</span>}</td>
                                    <td><KpiStatus status={result.status} /></td>
                                    <td>{formatDate(result.updated_at)}</td>
                                </tr>
                            ))}</tbody>
                        </table>
                    </div>
                )}
            </div>

            {selected && <ReviewDialog detail={selected} form={form} canManage={canManage} canApprove={canApprove} processing={processing} error={state.error} onClose={() => setSelected(null)} onChangeNotes={(notes) => setForm((current) => ({ ...current, notes }))} onChangeItem={updateItem} onAction={perform} />}
        </section>
    );
}

function ReviewDialog({ detail, form, canManage, canApprove, processing, error, onClose, onChangeNotes, onChangeItem, onAction }) {
    const { result } = detail;
    const editable = canManage && result.status === 'draft';
    return (
        <div className="modal-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
            <section className="master-dialog kpi-review-dialog" role="dialog" aria-modal="true" aria-label={`${result.employee_name} KPI review`}>
                <header>
                    <div><p className="eyebrow">{result.month} · {result.template_name}</p><h2>{result.employee_name}</h2><span className="muted">{result.employee_code} · {result.reference}</span></div>
                    <button className="icon-button" type="button" aria-label="Close" onClick={onClose}><X size={17} /></button>
                </header>

                <div className="kpi-review-dialog-body">
                    <div className="kpi-review-score-strip">
                        <div><span>Overall score</span><strong>{number(result.overall_score)}%</strong></div>
                        <div><span>{result.status === 'approved' ? 'Approved bonus' : 'Bonus preview'}</span><strong>{money(result.bonus_amount)}</strong></div>
                        <div><span>Status</span><KpiStatus status={result.status} /></div>
                    </div>
                    {result.status === 'approved' && (
                        <div className={`kpi-payroll-state ${result.payroll_adjustment_id ? 'is-posted' : ''}`}>
                            <span><WalletCards size={17} /></span>
                            <div>
                                <strong>{result.payroll_adjustment_id ? 'Bonus posted to payroll' : 'Bonus ready for payroll'}</strong>
                                <small>{result.payroll_adjustment_id
                                    ? `${result.payroll_adjustment_reference} · ${result.reference} · Posted ${formatDate(result.bonus_posted_at)}`
                                    : `${result.reference} will create one active incentive for ${result.month}. Regenerate that month's draft payroll if it already exists.`}</small>
                            </div>
                        </div>
                    )}
                    {error && <div className="inline-error"><AlertCircle size={15} /> {error}</div>}
                    <div className="master-table-wrap">
                        <table className="master-table kpi-score-table">
                            <thead><tr><th>Metric</th><th>Weight</th><th>Target</th><th>Actual / score</th><th>Result</th><th>Points</th></tr></thead>
                            <tbody>{form.items.map((item) => {
                                const manual = item.calculation_type === 'manual';
                                const actualLocked = item.is_automatic || !editable;
                                return (
                                    <tr key={item.id}>
                                        <td><strong>{item.name}</strong><span className={item.is_automatic ? 'kpi-source-note' : 'muted'}>{item.is_automatic ? item.source_note || 'Ready to refresh' : manual ? 'Manager score' : item.unit}</span></td>
                                        <td>{number(item.weight)}%</td>
                                        <td>{manual ? <span className="muted">100</span> : <input className="kpi-cell-input" type="number" min="0" step="0.01" disabled={!editable} value={item.target_value ?? ''} aria-label={`${item.name} target`} onChange={(event) => onChangeItem(item.id, 'target_value', event.target.value)} />}</td>
                                        <td><input className={`kpi-cell-input ${item.is_automatic ? 'is-synced' : ''}`} type="number" min="0" max={manual ? 100 : undefined} step={manual ? 1 : 0.01} inputMode={manual ? 'numeric' : 'decimal'} disabled={manual ? !editable : actualLocked} value={(manual ? item.manual_score : item.actual_value) ?? ''} aria-label={`${item.name} ${manual ? 'score' : 'actual'}`} onChange={(event) => onChangeItem(item.id, manual ? 'manual_score' : 'actual_value', event.target.value)} /></td>
                                        <td>{item.achievement_percent === null ? <span className="kpi-pending-value">Pending</span> : `${number(item.achievement_percent)}%`}</td>
                                        <td><strong>{item.weighted_score === null ? '—' : number(item.weighted_score)}</strong></td>
                                    </tr>
                                );
                            })}</tbody>
                        </table>
                    </div>
                    <label className="master-field kpi-review-notes"><span>Review note</span><textarea rows="2" disabled={!editable} value={form.notes} maxLength={1000} placeholder="Optional month summary" onChange={(event) => onChangeNotes(event.target.value)} /></label>
                </div>

                <footer>
                    <button className="button" type="button" onClick={onClose}>Close</button>
                    {editable && <button className="button" type="button" disabled={processing} onClick={() => onAction('save')}><Save size={15} /> Save draft</button>}
                    {editable && <button className="button primary" type="button" disabled={processing} onClick={() => onAction('submit')}><Send size={15} /> Submit</button>}
                    {canApprove && result.status === 'submitted' && <button className="button primary" type="button" disabled={processing} onClick={() => onAction('approve')}><BadgeCheck size={15} /> Approve</button>}
                    {canApprove && result.status === 'approved' && !result.payroll_adjustment_id && <button className="button primary" type="button" disabled={processing} onClick={() => onAction('post-bonus')}><WalletCards size={15} /> Post {money(result.bonus_amount)}</button>}
                </footer>
            </section>
        </div>
    );
}

function KpiStatus({ status }) {
    return <span className={`kpi-status ${status}`}><i />{titleCase(status)}</span>;
}

function KpiState({ icon: Icon, title, message, loading = false }) {
    return <div className="workspace-state compact"><Icon className={loading ? 'spin' : ''} size={24} /><strong>{title}</strong>{message && <span>{message}</span>}</div>;
}

function formatDate(value) {
    if (!value) return '—';
    return new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }).format(new Date(value));
}
