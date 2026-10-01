import { AlertCircle, CalendarDays, CalendarRange, ChevronDown, ChevronRight, CircleGauge, RefreshCw, Route, Search, ShoppingCart, UserCheck, Users, WalletCards } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { ShellBackButton } from './components/ShellBackButton';

const api = () => window.ValleyRuntime?.api?.mobileSupervisor || '/api/mobile/supervisor';

const money = (value) => `${Number(value || 0).toLocaleString()} MMK`;
const number = (value) => value === null || value === undefined ? '—' : Number(value).toLocaleString(undefined, { maximumFractionDigits: 2 });

function State({ loading = false, message, onRetry }) {
    return <section className="mobile-master-section supervisor-mobile-state">
        {loading ? <RefreshCw className="spin" size={22} /> : <AlertCircle size={22} />}
        <strong>{message}</strong>
        {!loading && onRetry && <button className="button" type="button" onClick={onRetry}>Retry</button>}
    </section>;
}

function useSupervisorOverview() {
    const [refresh, setRefresh] = useState(0);
    const [state, setState] = useState({ loading: true, error: '', supervisor: null, team: [], summary: {} });

    useEffect(() => {
        let active = true;
        setState((current) => ({ ...current, loading: true, error: '' }));
        window.axios.get(api())
            .then(({ data }) => active && setState({ loading: false, error: '', ...data.data }))
            .catch((error) => active && setState((current) => ({ ...current, loading: false, error: error.response?.data?.message || 'Unable to load the supervisor workspace.' })));
        return () => { active = false; };
    }, [refresh]);

    return [state, () => setRefresh((value) => value + 1)];
}

export function SupervisorHomeScreen({ locale = 'en', onViewTeam, onViewAttendance, onViewKpi }) {
    const [state, reload] = useSupervisorOverview();
    const my = locale === 'my';

    if (state.loading) return <State loading message={my ? 'အဖွဲ့အချက်အလက် ရယူနေသည်' : 'Loading team workspace'} />;
    if (state.error) return <State message={state.error} onRetry={reload} />;

    return <div className="mobile-master-stack supervisor-mobile-home">
        <section className="supervisor-mobile-hero">
            <span>{my ? 'အရောင်းကြီးကြပ်သူ' : 'Sales supervisor'}</span>
            <h1>{state.supervisor?.name}</h1>
            <p>{my ? 'သင့်အဖွဲ့၏ လစဉ်လုပ်ဆောင်မှု အကျဉ်းချုပ်' : 'Your team overview for the current month'}</p>
        </section>

        <section className="supervisor-mobile-metrics" aria-label="Team summary">
            <article><span><Users size={17} />{my ? 'အဖွဲ့ဝင်' : 'Team members'}</span><strong>{state.summary.team_members || 0}</strong><small>{state.summary.active_members || 0} active</small></article>
            <article><span><ShoppingCart size={17} />{my ? 'အော်ဒါ' : 'Team orders'}</span><strong>{state.summary.orders || 0}</strong><small>{state.period}</small></article>
            <article><span><WalletCards size={17} />{my ? 'တန်ဖိုး' : 'Order value'}</span><strong>{money(state.summary.order_value)}</strong><small>{state.summary.customers_reached || 0} customers</small></article>
        </section>

        <section className="mobile-master-section supervisor-mobile-actions">
            <button type="button" onClick={onViewTeam}><span><Users size={18} /></span><strong>{my ? 'အဖွဲ့ကိုကြည့်ရန်' : 'View my team'}<small>{state.summary.team_members || 0} sales representatives</small></strong><ChevronRight size={17} /></button>
            <button type="button" onClick={onViewAttendance}><span><CalendarDays size={18} /></span><strong>{my ? 'တက်ရောက်မှု' : 'My attendance'}<small>Calendar and check-in history</small></strong><ChevronRight size={17} /></button>
            <button type="button" onClick={onViewKpi}><span><CircleGauge size={18} /></span><strong>{my ? 'KPI အစီရင်ခံစာ' : 'My KPI report'}<small>Targets, score and bonus</small></strong><ChevronRight size={17} /></button>
        </section>
    </div>;
}

export function SupervisorTeamScreen({ locale = 'en', onViewMember }) {
    const [state, reload] = useSupervisorOverview();
    const my = locale === 'my';

    return <div className="mobile-master-stack supervisor-team-page">
        <div className="mobile-master-heading"><div><p className="eyebrow">{my ? 'သတ်မှတ်ထားသောအဖွဲ့' : 'Assigned team'}</p><h1>{my ? 'အရောင်းကိုယ်စားလှယ်များ' : 'Sales representatives'}</h1><span className="muted">{my ? 'အဖွဲ့ဝင်နှင့် လမ်းကြောင်းအချက်အလက်' : 'People and route assignments in your team'}</span></div></div>
        {state.loading ? <State loading message="Loading team members" /> : state.error ? <State message={state.error} onRetry={reload} /> : state.team.length === 0 ? <State message="No sales representatives are assigned to your team." /> : <section className="mobile-master-section supervisor-team-list">
            {state.team.map((member) => <button type="button" onClick={() => onViewMember?.(member.id)} key={member.id}>
                <span className="supervisor-team-avatar">{member.name.split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase()}</span>
                <span><strong>{member.name}</strong><small>{member.code} · {member.phone || 'No phone'}</small><small><Route size={12} />{member.route_name || 'No route assigned'}</small></span>
                <span className="supervisor-team-row-end"><span className={`status ${member.is_active ? 'success' : 'neutral'}`}><UserCheck size={12} />{member.is_active ? 'Active' : 'Inactive'}</span><ChevronRight size={16} /></span>
            </button>)}
        </section>}
    </div>;
}

export function SupervisorRepresentativeDetailScreen({ memberId, locale = 'en', onBack }) {
    const my = locale === 'my';
    const [draft, setDraft] = useState({ period: 'month', year: '', month: '' });
    const [applied, setApplied] = useState({});
    const [refresh, setRefresh] = useState(0);
    const [state, setState] = useState({ loading: true, error: '', representative: null, filters: {}, available_months: [], summary: {}, reviews: [], metrics: [] });

    useEffect(() => {
        let active = true;
        setState((current) => ({ ...current, loading: true, error: '' }));
        window.axios.get(`${api()}/team/${memberId}`, { params: applied })
            .then(({ data }) => {
                if (!active) return;
                setState({ loading: false, error: '', ...data.data });
                setDraft({
                    period: data.data.filters.period,
                    year: String(data.data.filters.year),
                    month: String(data.data.filters.month).slice(5, 7),
                });
            })
            .catch((error) => active && setState((current) => ({ ...current, loading: false, error: error.response?.data?.message || 'Unable to load this representative.' })));
        return () => { active = false; };
    }, [memberId, applied, refresh]);

    const years = useMemo(() => {
        const values = new Set((state.available_months || []).map((month) => String(month).slice(0, 4)));
        values.add(String(new Date().getFullYear()));
        return [...values].sort((left, right) => Number(right) - Number(left));
    }, [state.available_months]);
    const months = useMemo(() => Array.from({ length: 12 }, (_, index) => ({
        value: String(index + 1).padStart(2, '0'),
        label: new Intl.DateTimeFormat(my ? 'my-MM' : 'en-US', { month: 'long' }).format(new Date(2026, index, 1)),
    })), [my]);
    const apply = (event) => {
        event.preventDefault();
        const year = draft.year || String(new Date().getFullYear());
        setApplied(draft.period === 'month'
            ? { period: 'month', year, month: `${year}-${draft.month || '01'}` }
            : { period: 'year', year });
    };
    const review = state.reviews?.[0];
    const displayedPeriod = state.filters?.period || 'month';

    if (state.loading && !state.representative) return <><ShellBackButton onClick={onBack} label={my ? 'အဖွဲ့စာရင်းသို့ ပြန်ရန်' : 'Back to team'} /><State loading message={my ? 'ဝန်ထမ်းအချက်အလက် ရယူနေသည်' : 'Loading representative details'} /></>;
    if (state.error && !state.representative) return <><ShellBackButton onClick={onBack} label={my ? 'အဖွဲ့စာရင်းသို့ ပြန်ရန်' : 'Back to team'} /><State message={state.error} onRetry={() => setRefresh((value) => value + 1)} /></>;

    return <div className="mobile-master-stack supervisor-member-detail">
        <ShellBackButton onClick={onBack} label={my ? 'အဖွဲ့စာရင်းသို့ ပြန်ရန်' : 'Back to team'} />
        <div className="mobile-master-heading supervisor-member-heading">
            <div><p className="eyebrow">{my ? 'အဖွဲ့ဝင် အသေးစိတ်' : 'Team member detail'}</p><h1>{state.representative?.name}</h1><span className="muted">{state.representative?.code} · {state.representative?.route_name || (my ? 'လမ်းကြောင်းမသတ်မှတ်ရသေးပါ' : 'No route assigned')}</span></div>
        </div>

        <section className="mobile-master-section supervisor-member-profile">
            <span className="supervisor-team-avatar">{state.representative?.name?.split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase()}</span>
            <span><strong>{state.representative?.phone || (my ? 'ဖုန်းမရှိပါ' : 'No phone')}</strong><small>{state.representative?.email || (my ? 'အီးမေးလ်မရှိပါ' : 'No email')}</small></span>
            <span className={`status ${state.representative?.is_active ? 'success' : 'neutral'}`}>{state.representative?.is_active ? (my ? 'အသုံးပြုနေသည်' : 'Active') : (my ? 'ပိတ်ထားသည်' : 'Inactive')}</span>
        </section>

        <form className="mobile-master-section supervisor-kpi-filter" onSubmit={apply}>
            <div className="supervisor-kpi-filter-heading"><span><CalendarDays size={16} /></span><div><strong>{my ? 'KPI အစီရင်ခံစာကာလ' : 'KPI report duration'}</strong><small>{my ? 'လစဉ် သို့မဟုတ် နှစ်စဉ်ရလဒ်ကို ရွေးပါ' : 'Choose a monthly or yearly result'}</small></div></div>
            <div className="supervisor-period-toggle" aria-label="KPI duration">
                <button className={draft.period === 'month' ? 'is-active' : ''} type="button" onClick={() => setDraft((current) => ({ ...current, period: 'month' }))}>{my ? 'လစဉ်' : 'Monthly'}</button>
                <button className={draft.period === 'year' ? 'is-active' : ''} type="button" onClick={() => setDraft((current) => ({ ...current, period: 'year' }))}>{my ? 'နှစ်စဉ်' : 'Yearly'}</button>
            </div>
            <div className="supervisor-duration-fields">
                <label><span>{my ? 'နှစ်' : 'Year'}</span><div className="supervisor-select-control"><CalendarRange size={16} /><select value={draft.year} aria-label={my ? 'KPI နှစ်' : 'KPI year'} onChange={(event) => setDraft((current) => ({ ...current, year: event.target.value }))}>{years.map((year) => <option value={year} key={year}>{year}</option>)}</select><ChevronDown size={16} /></div></label>
                {draft.period === 'month' && <label><span>{my ? 'လ' : 'Month'}</span><div className="supervisor-select-control"><CalendarDays size={16} /><select value={draft.month} aria-label={my ? 'KPI လ' : 'KPI month'} onChange={(event) => setDraft((current) => ({ ...current, month: event.target.value }))}>{months.map((month) => <option value={month.value} key={month.value}>{month.label}</option>)}</select><ChevronDown size={16} /></div></label>}
            </div>
            <button className="button primary" type="submit" disabled={state.loading}><Search size={16} />{my ? 'ကြည့်ရန်' : 'Apply'}</button>
        </form>

        {state.error && <div className="inline-error"><AlertCircle size={16} />{state.error}</div>}
        <section className="supervisor-kpi-summary">
            <article><span>{my ? 'သုံးသပ်ချက်' : 'Reviews'}</span><strong>{state.summary.reviews || 0}</strong></article>
            <article><span>{my ? 'ပျမ်းမျှရမှတ်' : 'Average score'}</span><strong>{number(state.summary.average_score)}%</strong></article>
            <article><span>{my ? 'အတည်ပြုပြီး' : 'Approved'}</span><strong>{state.summary.approved || 0}</strong></article>
            <article><span>{my ? 'ဆုကြေးစုစုပေါင်း' : 'Bonus total'}</span><strong>{money(state.summary.bonus_total)}</strong></article>
        </section>

        {!state.loading && state.reviews.length === 0 ? <State message={my ? 'ရွေးချယ်ထားသောကာလအတွက် KPI မရှိပါ' : 'No KPI review exists for the selected period.'} /> : <>
            {displayedPeriod === 'month' && review && <section className="mobile-master-section supervisor-kpi-result">
                <div><span>{review.month}</span><strong>{number(review.overall_score)}%</strong><small>{review.template_name}</small></div>
                <div><span className={`status ${review.status === 'approved' ? 'success' : 'neutral'}`}>{review.status}</span><strong>{money(review.bonus_amount)}</strong><small>{my ? 'ဆုကြေး' : 'KPI bonus'}</small></div>
            </section>}
            {displayedPeriod === 'year' && <section className="mobile-master-section supervisor-kpi-history">
                {state.reviews.map((item) => <article key={item.id}><span><strong>{item.month}</strong><small>{item.template_name}</small></span><span><strong>{number(item.overall_score)}%</strong><small>{money(item.bonus_amount)}</small></span></article>)}
            </section>}
            <section className="mobile-master-section supervisor-kpi-metrics-list">
                <header><div><p className="eyebrow">{my ? 'စွမ်းဆောင်ရည်' : 'Performance'}</p><h2>{displayedPeriod === 'month' ? (my ? 'KPI အချက်များ' : 'KPI metrics') : (my ? 'နှစ်စဉ် ပျမ်းမျှ' : 'Yearly metric averages')}</h2></div><strong>{state.metrics.length}</strong></header>
                {state.metrics.map((metric) => <article key={metric.id}>
                    <div><span><strong>{metric.name}</strong><small>{metric.code} · {number(metric.weight)}%</small></span><b>{metric.weighted_score === null ? 'Pending' : `${number(metric.weighted_score)} pts`}</b></div>
                    <div className="supervisor-kpi-progress"><i style={{ width: `${Math.min(Number(metric.achievement_percent || 0), 100)}%` }} /></div>
                    <footer><span><small>{my ? 'ရည်မှန်းချက်' : 'Target'}</small><strong>{number(metric.target_value)}</strong></span><span><small>{my ? 'အမှန်တကယ်' : 'Actual'}</small><strong>{number(metric.actual_value)}</strong></span><span><small>{my ? 'ပြီးမြောက်မှု' : 'Achievement'}</small><strong>{metric.achievement_percent === null ? '—' : `${number(metric.achievement_percent)}%`}</strong></span><span><small>{my ? 'ရမှတ်' : 'Points'}</small><strong>{number(metric.weighted_score)}</strong></span></footer>
                </article>)}
            </section>
        </>}
    </div>;
}
