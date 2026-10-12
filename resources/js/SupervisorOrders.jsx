import { AlertCircle, ChevronLeft, ChevronRight, RefreshCw, Search, ShoppingCart } from 'lucide-react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { StatusBadge } from './phaseFour';

const api = () => window.ValleyRuntime?.api?.mobileOrders || '/api/mobile/orders';
const emptyFilters = { search: '', representative_id: '', route_id: '', status: '', delivery_status: '', payment_type: '', date_from: '', date_to: '', delivery_from: '', delivery_to: '', min_total: '', max_total: '' };
const money = (value) => `${Number(value || 0).toLocaleString()} MMK`;

export function SupervisorOrdersScreen({ locale = 'en', active = true, onViewOrder }) {
    const my = locale === 'my';
    const label = (en, mm) => my ? mm : en;
    const [draft, setDraft] = useState(emptyFilters);
    const [filters, setFilters] = useState(emptyFilters);
    const [page, setPage] = useState(1);
    const [refresh, setRefresh] = useState(0);
    const [setup, setSetup] = useState({ representatives: [], routes: [] });
    const [setupError, setSetupError] = useState('');
    const [state, setState] = useState({ loading: true, error: '', items: [], summary: {}, meta: {} });
    const scroll = useRef(0);
    const openedOrder = useRef(null);
    const list = useRef(null);

    useEffect(() => {
        let mounted = true;
        setSetupError('');
        window.axios.get(`${api()}/meta`)
            .then(({ data }) => mounted && setSetup(data.data))
            .catch((error) => mounted && setSetupError(error.response?.data?.message || 'Unable to load team filters.'));
        return () => { mounted = false; };
    }, [refresh]);

    useEffect(() => {
        let mounted = true;
        setState((current) => ({ ...current, loading: true, error: '' }));
        const params = Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== ''));
        window.axios.get(api(), { params: { ...params, page, per_page: 12 } })
            .then(({ data }) => mounted && setState({ loading: false, error: '', ...data.data }))
            .catch((error) => mounted && setState((current) => ({ ...current, loading: false, error: error.response?.data?.message || 'Unable to load team orders.' })));
        return () => { mounted = false; };
    }, [filters, page, refresh]);

    // Keep the loaded list and its filters mounted while viewing a team order.
    useLayoutEffect(() => {
        if (!active || openedOrder.current === null) return;
        const frame = requestAnimationFrame(() => {
            window.scrollTo({ top: scroll.current, behavior: 'instant' });
            list.current?.querySelector(`[data-order-id="${openedOrder.current}"]`)?.focus({ preventScroll: true });
        });
        return () => cancelAnimationFrame(frame);
    }, [active]);

    const update = (name, value) => setDraft((current) => ({ ...current, [name]: value }));
    const apply = (event) => {
        event.preventDefault();
        setPage(1);
        setFilters({ ...draft });
    };
    const clear = () => { setDraft(emptyFilters); setFilters(emptyFilters); setPage(1); };
    const select = (name, title, all, options) => <label className="mobile-order-filter-field"><span>{title}</span><select aria-label={title} value={draft[name]} onChange={(event) => update(name, event.target.value)}><option value="">{all}</option>{options.map(([value, text]) => <option value={value} key={value}>{text}</option>)}</select></label>;
    const date = (name, title, min, max) => <label className="mobile-order-filter-field"><span>{title}</span><input aria-label={title} type="date" value={draft[name]} min={min || undefined} max={max || undefined} onChange={(event) => update(name, event.target.value)} /></label>;
    const statusNames = { draft: label('Draft', 'မူကြမ်း'), pending: label('Pending', 'စောင့်ဆိုင်း'), confirmed: label('Confirmed', 'အတည်ပြုပြီး'), invoiced: label('Invoiced', 'Invoice ပြီး'), assigned: label('Assigned', 'တာဝန်ပေးပြီး'), delivering: label('Delivering', 'ပို့ဆောင်နေသည်'), delivered: label('Delivered', 'ပို့ဆောင်ပြီး'), cancelled: label('Cancelled', 'ပယ်ဖျက်ပြီး') };
    const deliveryNames = { none: label('No delivery yet', 'ပို့ဆောင်မှုမရှိသေး'), planned: label('Planned', 'စီစဉ်ပြီး'), assigned: label('Assigned', 'တာဝန်ပေးပြီး'), loading: label('Loading', 'ပစ္စည်းတင်နေသည်'), on_route: label('On route', 'ပို့ဆောင်နေသည်'), delivered: label('Delivered', 'ပို့ဆောင်ပြီး'), partially_delivered: label('Partially delivered', 'တစ်စိတ်တစ်ပိုင်းပို့ပြီး'), failed: label('Failed', 'မအောင်မြင်') };

    return <div hidden={!active} ref={list}>
        <div className="mobile-master-stack supervisor-orders-page">
            <div className="mobile-master-heading"><div><p className="eyebrow">{label('Assigned team', 'သတ်မှတ်ထားသောအဖွဲ့')}</p><h1>{label('Team orders', 'အဖွဲ့အော်ဒါများ')}</h1><span className="muted">{label('Orders submitted by your sales representatives', 'သင့်အဖွဲ့မှ အရောင်းကိုယ်စားလှယ်များ တင်ထားသောအော်ဒါများ')}</span></div><button className="icon-button" type="button" aria-label={label('Refresh orders', 'အော်ဒါများ ပြန်ရယူရန်')} disabled={state.loading} onClick={() => setRefresh((value) => value + 1)}><RefreshCw size={17} /></button></div>
            {active && <form className="mobile-order-filters" onSubmit={apply}>
                <label className="mobile-search"><Search size={16} /><input aria-label={label('Search team orders', 'အဖွဲ့အော်ဒါ ရှာရန်')} type="search" placeholder={label('Order, customer or address', 'အော်ဒါ၊ ဖောက်သည် သို့မဟုတ် လိပ်စာ')} value={draft.search} onChange={(event) => update('search', event.target.value)} /></label>
                {select('representative_id', label('Representative', 'အရောင်းကိုယ်စားလှယ်'), label('All team members', 'အဖွဲ့ဝင်အားလုံး'), setup.representatives.map((member) => [member.id, `${member.code} · ${member.name}`]))}
                {select('route_id', label('Route', 'လမ်းကြောင်း'), label('All routes', 'လမ်းကြောင်းအားလုံး'), setup.routes.map((route) => [route.id, `${route.code} · ${route.name}`]))}
                {select('status', label('Order status', 'အော်ဒါအခြေအနေ'), label('All order statuses', 'အခြေအနေအားလုံး'), Object.entries(statusNames))}
                {select('delivery_status', label('Delivery status', 'ပို့ဆောင်မှုအခြေအနေ'), label('All delivery statuses', 'ပို့ဆောင်မှုအားလုံး'), Object.entries(deliveryNames))}
                {select('payment_type', label('Payment type', 'ငွေပေးချေမှု'), label('All payment types', 'ငွေပေးချေမှုအားလုံး'), [['unsettled', label('Set at delivery', 'ပို့ဆောင်ချိန်သတ်မှတ်ရန်')], ['cash', label('Cash', 'ငွေသား')], ['credit', label('Credit', 'အကြွေး')]])}
                {date('date_from', label('Order date from', 'အော်ဒါ စရက်'), '', draft.date_to)}
                {date('date_to', label('Order date through', 'အော်ဒါ ဆုံးရက်'), draft.date_from)}
                {date('delivery_from', label('Delivery date from', 'ပို့ဆောင်မှု စရက်'), '', draft.delivery_to)}
                {date('delivery_to', label('Delivery date through', 'ပို့ဆောင်မှု ဆုံးရက်'), draft.delivery_from)}
                {['min_total', 'max_total'].map((name) => <label className="mobile-order-filter-field" key={name}><span>{name === 'min_total' ? label('Minimum total (MMK)', 'အနည်းဆုံးတန်ဖိုး (ကျပ်)') : label('Maximum total (MMK)', 'အများဆုံးတန်ဖိုး (ကျပ်)')}</span><input aria-label={name === 'min_total' ? label('Minimum total (MMK)', 'အနည်းဆုံးတန်ဖိုး (ကျပ်)') : label('Maximum total (MMK)', 'အများဆုံးတန်ဖိုး (ကျပ်)')} type="number" min={name === 'max_total' ? draft.min_total || 0 : 0} step="1" value={draft[name]} onChange={(event) => update(name, event.target.value)} /></label>)}
                <button className="button primary" type="submit" disabled={state.loading}>{label('Apply filters', 'စစ်ထုတ်ရန်')}</button>
                <button className="button" type="button" onClick={clear}>{label('Clear filters', 'စစ်ထုတ်မှု ရှင်းရန်')}</button>
            </form>}
            {setupError && <div className="inline-error"><AlertCircle size={16} />{setupError}</div>}
            <section className="supervisor-kpi-summary" aria-label={label('Filtered order summary', 'အော်ဒါအကျဉ်းချုပ်')}>
                <article><span>{label('Orders', 'အော်ဒါများ')}</span><strong>{state.summary.orders_count || 0}</strong></article>
                <article><span>{label('Pending', 'စောင့်ဆိုင်း')}</span><strong>{state.summary.pending_count || 0}</strong></article>
                <article><span>{label('Delivering', 'ပို့ဆောင်နေသည်')}</span><strong>{state.summary.active_delivery_count || 0}</strong></article>
                <article><span>{label('Total value', 'စုစုပေါင်းတန်ဖိုး')}</span><strong>{money(state.summary.total_amount)}</strong></article>
            </section>
            <section className="mobile-master-section" aria-busy={state.loading}>
                {state.error ? <div className="supervisor-mobile-state"><AlertCircle size={22} /><strong>{state.error}</strong><button className="button" type="button" onClick={() => setRefresh((value) => value + 1)}>{label('Retry', 'ပြန်ကြိုးစားရန်')}</button></div> : state.loading ? <div className="supervisor-mobile-state"><RefreshCw size={22} className="spin" /><strong>{label('Loading team orders', 'အဖွဲ့အော်ဒါများ ရယူနေသည်')}</strong></div> : state.items.length === 0 ? <div className="supervisor-mobile-state"><ShoppingCart size={22} /><strong>{label('No team orders match these filters.', 'ကိုက်ညီသော အဖွဲ့အော်ဒါ မရှိပါ။')}</strong></div> : <div className="mobile-order-list">
                    {state.items.map((order) => <button type="button" key={order.id} data-order-id={order.id} onClick={() => { scroll.current = window.scrollY; openedOrder.current = order.id; onViewOrder(order.id); }}>
                        <span className="mobile-order-icon"><ShoppingCart size={17} /></span>
                        <span><strong>{order.code} · {money(order.total)}</strong><small>{order.shop_name}</small><small>{order.representative_name} · {order.route || label('No route', 'လမ်းကြောင်းမရှိ')}</small><small>{order.order_date}{order.delivery ? ` · ${deliveryNames[order.delivery.status] || order.delivery.status}` : ''}</small></span>
                        <StatusBadge status={order.status} locale={locale} />
                    </button>)}
                </div>}
            </section>
            <nav className="supervisor-orders-pagination" aria-label={label('Order pages', 'အော်ဒါစာမျက်နှာများ')}>
                <button className="button" type="button" disabled={state.loading || page <= 1} onClick={() => setPage((value) => value - 1)}><ChevronLeft size={16} />{label('Previous', 'ရှေ့စာမျက်နှာ')}</button>
                <span>{page} / {state.meta.last_page || 1} · {state.meta.total || 0} {label('orders', 'အော်ဒါများ')}</span>
                <button className="button" type="button" disabled={state.loading || page >= (state.meta.last_page || 1)} onClick={() => setPage((value) => value + 1)}>{label('Next', 'နောက်စာမျက်နှာ')}<ChevronRight size={16} /></button>
            </nav>
        </div>
    </div>;
}
