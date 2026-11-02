import { ArrowRight, ClipboardList, Droplets, MapPinned, Package, Plus, WalletCards } from 'lucide-react';
import { formatMoney, formatNumber } from './ui/format.js';
import { publicText } from './PublicApp';

export function CustomerHome({ data, locale, onCreateOrder, onViewProducts, onViewOrder, onViewBalance, children }) {
    const t = (en, my) => publicText(locale, en, my);
    const orders = data.recent_orders || [];
    const active = orders.find((order) => !['delivered', 'cancelled', 'failed'].includes(order.status));
    return <div className="customer-home mobile-master-stack">
        <section className="public-welcome-hero"><Droplets size={32} /><p className="eyebrow">VALLEY WATER</p><h1>{t('Ready for a refill?', 'ရေသန့်ထပ်မှာမလား။')}</h1><p>{t('Order water for your home or business.', 'အိမ်နှင့် လုပ်ငန်းအတွက် ရေသန့်မှာယူပါ။')}</p><button className="button primary" onClick={onCreateOrder}>{t('Order water', 'ရေသန့်မှာယူရန်')}<ArrowRight size={17} /></button><button className="button" onClick={onViewProducts}>{t('Browse water', 'ရေသန့်များ ကြည့်ရန်')}</button></section>
        {active && <section className="mobile-master-section customer-active-order"><h2>{t('Your current order', 'သင့်လက်ရှိအော်ဒါ')}</h2><button className="mobile-task-link" onClick={() => onViewOrder(active.id)}><Package size={22} /><span><strong>{active.code}</strong><small>{t('View order and delivery progress', 'အော်ဒါနှင့် ပို့ဆောင်မှုကို ကြည့်ရန်')}</small></span><ArrowRight size={18} /></button></section>}
        <section className="mobile-master-section customer-recent-orders"><h2>{t('My orders', 'ကျွန်ုပ်၏အော်ဒါများ')}</h2>{orders.length ? orders.map((order) => <button className="mobile-task-link" key={order.id} onClick={() => onViewOrder(order.id)}><Package size={20} /><span><strong>{order.code}</strong><small>{order.date}</small></span><strong>{formatMoney(order.amount)}</strong></button>) : <p>{t('Your first water order starts here.', 'သင့်ပထမဆုံး ရေသန့်အော်ဒါကို ဤနေရာမှ မှာယူပါ။')}</p>}</section>
        {children}
        <button className="mobile-master-section mobile-task-link customer-balance-link" onClick={onViewBalance}><WalletCards size={23} /><span><strong>{t('Payments & balance', 'ငွေပေးချေမှုနှင့် လက်ကျန်')}</strong><small>{t('View payments and account balance', 'ငွေပေးချေမှုနှင့် အကောင့်လက်ကျန် ကြည့်ရန်')}</small></span><strong>{formatMoney(data.summary?.outstanding_balance)}</strong></button>
    </div>;
}

export function SalesToday({ summary, locale, onCreateOrder, onViewVisits, onViewOrders }) {
    const t = (en, my) => publicText(locale, en, my);
    return <section className="sales-today mobile-master-section"><p className="eyebrow">{t('Today’s work', 'ယနေ့လုပ်ငန်းများ')}</p><h1>{summary.assigned_route || t('Your sales day', 'သင့်အရောင်းလုပ်ငန်းနေ့')}</h1><div className="mobile-priority-actions"><button className="button primary" onClick={onCreateOrder}><Plus size={18} />{t('Create order', 'အော်ဒါအသစ်တင်ရန်')}</button><button className="button" onClick={onViewVisits}><MapPinned size={18} />{t('Today’s visits', 'ယနေ့လည်ပတ်မှုများ')}</button></div><div className="sales-today-metrics"><button onClick={onViewVisits}><MapPinned size={17} /><span>{t('Visits completed', 'လည်ပတ်ပြီး')}</span><strong>{formatNumber(summary.today_visits_completed)} / {formatNumber(summary.route_customers)}</strong></button><button onClick={onViewOrders}><ClipboardList size={17} /><span>{t('Pending orders', 'စောင့်ဆိုင်းအော်ဒါများ')}</span><strong>{formatNumber(summary.pending_orders)}</strong></button><article><span>{t('Today’s sales', 'ယနေ့အရောင်း')}</span><strong>{formatMoney(summary.daily_sales)}</strong></article></div></section>;
}
