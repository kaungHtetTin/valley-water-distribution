import { AlertCircle, ArrowLeft, BadgeCheck, Ban, CalendarDays, Eye, FileText, Plus, RefreshCw, RotateCcw, Save, Search, ShoppingCart, Trash2, TriangleAlert, X } from 'lucide-react';
import { useEffect, useId, useMemo, useRef, useState } from 'react';

const copy = {
    en: {
        actions: 'Actions',
        addItem: 'Add item',
        allCustomers: 'All customers',
        allStatuses: 'All statuses',
        adjustmentRecord: 'Adjustment record',
        cancel: 'Cancel',
        cash: 'Cash',
        confirmed: 'Confirmed',
        confirm: 'Confirm',
        confirmOrder: 'Confirm order',
        confirmPrompt: 'Confirm this order for delivery planning?',
        cancelOrder: 'Cancel order',
        cancelOrderPrompt: 'Cancel this order? It will be removed from the active sales workflow.',
        createInvoice: 'Create invoice',
        createInvoicePrompt: 'Create a draft invoice from this confirmed order?',
        credit: 'Credit',
        availableCredit: 'Available credit',
        customer: 'Customer',
        customers: 'Assigned customers',
        date: 'Date',
        details: 'Details',
        discount: 'Discount',
        empty: 'No orders match this view.',
        foc: 'FOC',
        invoiceDate: 'Invoice date',
        invoices: 'Invoices',
        invoicesHint: 'Draft invoices created from confirmed customer orders.',
        invoiceCreated: 'Invoice created',
        issueInvoice: 'Issue invoice',
        issueInvoicePrompt: 'Issue this invoice? It will become ready for delivery and collection follow-up.',
        cancelInvoice: 'Cancel invoice',
        cancelInvoicePrompt: 'Cancel this invoice and return the linked order to confirmed status?',
        itemType: 'Type',
        items: 'Items',
        loading: 'Loading orders',
        loadMore: 'Load more',
        notes: 'Notes',
        newOrder: 'New order',
        orderDate: 'Order date',
        order: 'Order',
        orders: 'Orders',
        ordersHint: 'Office intake for customer orders before confirmation, invoicing, and delivery assignment.',
        mobileOrdersHint: 'Choose products and quantities. Delivery details are optional.',
        paymentType: 'Payment',
        pending: 'Pending',
        price: 'Price',
        priceType: 'Price type',
        product: 'Product',
        quantity: 'Qty',
        refresh: 'Refresh',
        requestedDelivery: 'Requested delivery',
        retry: 'Retry',
        route: 'Route',
        saveMobileOrder: 'Place order',
        save: 'Save order',
        searchInvoices: 'Search invoice, order, shop, route',
        searchOrders: 'Search order or customer',
        status: 'Status',
        subtotal: 'Subtotal',
        total: 'Total',
        totalAmount: 'Order value',
        unit: 'Unit',
        updated: 'Updated',
        created: 'Created',
        damage: 'Damage',
        damageEntries: 'Damage entries',
        damageHint: 'Record damaged goods from office operations as non-sale adjustment entries.',
        newDamage: 'New damage',
        newReturn: 'New return',
        records: 'Records',
        reason: 'Reason',
        salesReturns: 'Sales returns',
        returnsHint: 'Record returned goods by customer, product, quantity, and value for follow-up reconciliation.',
        searchAdjustments: 'Search code, shop, route',
        entryDate: 'Entry date',
        value: 'Value',
        totalQuantity: 'Total qty',
        sales_return: 'Sales return',
        sale: 'Sale',
        draft: 'Draft',
        cancelled: 'Cancelled',
        invoiced: 'Invoiced',
        assigned: 'Assigned',
        delivering: 'Delivering',
        delivered: 'Delivered',
        dueDate: 'Due date',
        issued: 'Issued',
        orderHistory: 'Order history',
        orderReady: 'Order submitted',
        submitted: 'Submitted',
        statusTimeline: 'Status timeline',
        waiting: 'Waiting',
        current: 'Current',
        invoice: 'Invoice',
        delivery: 'Delivery',
        outstandingBalance: 'Outstanding',
        afterThisOrder: 'After this order',
        overLimit: 'Over limit',
        creditOk: 'Credit OK',
        creditLimit: 'Credit limit',
        remove: 'Remove',
    },
    my: {
        actions: 'လုပ်ဆောင်ချက်များ', addItem: 'ပစ္စည်းထပ်ထည့်ရန်', allCustomers: 'ဖောက်သည်အားလုံး', allStatuses: 'အခြေအနေအားလုံး', cancel: 'မလုပ်တော့ပါ', cash: 'ငွေသား', confirmed: 'အတည်ပြုပြီး', credit: 'အကြွေး', availableCredit: 'အသုံးပြုနိုင်သော အကြွေး', customer: 'ဖောက်သည်', customers: 'တာဝန်ပေးထားသော ဖောက်သည်များ', date: 'ရက်စွဲ', details: 'အသေးစိတ်', empty: 'ကိုက်ညီသော အော်ဒါမရှိပါ။', foc: 'အခမဲ့', itemType: 'အမျိုးအစား', items: 'ပစ္စည်းများ', loading: 'အော်ဒါများ ရယူနေသည်', notes: 'မှတ်ချက်', newOrder: 'အော်ဒါအသစ်', orderDate: 'အော်ဒါရက်စွဲ', order: 'အော်ဒါ', orders: 'အော်ဒါများ', ordersHint: 'ဖောက်သည်အော်ဒါများကို အတည်ပြုခြင်း၊ invoice နှင့် ပို့ဆောင်ရေးအတွက် စီမံပါ။', mobileOrdersHint: 'ပစ္စည်းနှင့် အရေအတွက်ကို ရွေးပါ။ ပို့ဆောင်ရေးအသေးစိတ်သည် မဖြစ်မနေမဟုတ်ပါ။', paymentType: 'ငွေပေးချေမှု', pending: 'စောင့်ဆိုင်း', price: 'ဈေးနှုန်း', product: 'ပစ္စည်း', quantity: 'အရေအတွက်', requestedDelivery: 'လိုချင်သော ပို့ဆောင်ရက်', retry: 'ပြန်ကြိုးစားရန်', route: 'လမ်းကြောင်း', saveMobileOrder: 'အော်ဒါတင်ရန်', searchOrders: 'အော်ဒါ သို့မဟုတ် ဖောက်သည်ရှာရန်', status: 'အခြေအနေ', subtotal: 'ပေါင်းလဒ်', total: 'စုစုပေါင်း', totalAmount: 'အော်ဒါတန်ဖိုး', unit: 'ယူနစ်', orderHistory: 'အော်ဒါမှတ်တမ်း', outstandingBalance: 'ပေးရန်ကျန်ငွေ', afterThisOrder: 'ဤအော်ဒါပြီးနောက်', overLimit: 'အကြွေးကန့်သတ်ချက်ကျော်', creditOk: 'အကြွေးရရှိနိုင်', creditLimit: 'အကြွေးကန့်သတ်ချက်', remove: 'ဖယ်ရှားရန်', sale: 'အရောင်း', draft: 'မူကြမ်း', cancelled: 'ပယ်ဖျက်ပြီး', invoiced: 'Invoice ပြီး', assigned: 'တာဝန်ပေးပြီး', delivering: 'ပို့ဆောင်နေသည်', delivered: 'ပို့ဆောင်ပြီး', submitted: 'တင်ပြပြီး', statusTimeline: 'အခြေအနေမှတ်တမ်း', waiting: 'စောင့်ဆိုင်း', current: 'လက်ရှိ', invoice: 'Invoice', delivery: 'ပို့ဆောင်ရေး',
    },
};

const blankItem = { product_id: '', quantity: 1, unit_price: '', discount_amount: 0, item_type: 'sale', remarks: '' };
const orderStatusTabs = ['', 'pending', 'confirmed', 'invoiced', 'assigned', 'delivering', 'delivered', 'cancelled'];
const invoiceStatusTabs = ['', 'draft', 'issued', 'cancelled'];

function t(locale, key) {
    return copy[locale]?.[key] || copy.en[key] || key;
}

function apiBase(name) {
    const fallback = { invoices: '/api/invoices', mobileOrders: '/api/mobile/orders', orderAdjustments: '/api/order-adjustments', orders: '/api/orders' };
    return window.ValleyRuntime?.api?.[name] || fallback[name];
}

function requestMessage(error, locale) {
    return locale === 'my' ? t(locale, 'loading') : error.response?.data?.message || 'The order request could not be completed.';
}

function defaultPriceTypeId(customer, priceTypes) {
    return customer?.price_type_id || priceTypes.find((item) => item.code === 'WSL')?.id || priceTypes[0]?.id || '';
}

function repriceItems(items, products, priceTypeId) {
    return items.map((item) => {
        const product = products.find((product) => Number(product.id) === Number(item.product_id));
        if (!product) return item;

        return {
            ...item,
            unit_price: item.item_type === 'foc' ? 0 : priceForProduct(product, priceTypeId),
        };
    });
}

export function OrdersScreen({ locale, canManage = false, canManageInvoices = false }) {
    const today = new Date().toISOString().slice(0, 10);
    const [filters, setFilters] = useState({ search: '', status: '', date: '', customer_id: '' });
    const [meta, setMeta] = useState({ loading: true, customers: [], products: [], price_types: [], error: '' });
    const [state, setState] = useState({ loading: true, items: [], summary: {}, pageMeta: {}, error: '' });
    const [page, setPage] = useState(1);
    const [refreshKey, setRefreshKey] = useState(0);
    const [formOpen, setFormOpen] = useState(false);
    const [saving, setSaving] = useState(false);
    const [processingId, setProcessingId] = useState(null);
    const [viewing, setViewing] = useState(null);
    const [form, setForm] = useState(() => ({
        customer_id: '',
        price_type_id: '',
        order_date: today,
        requested_delivery_date: '',
        payment_type: 'credit',
        notes: '',
        items: [{ ...blankItem }],
    }));

    useEffect(() => {
        let mounted = true;
        window.axios.get(`${apiBase('orders')}/meta`)
            .then(({ data }) => mounted && setMeta({ loading: false, customers: data.data.customers, products: data.data.products, price_types: data.data.price_types, error: '' }))
            .catch((error) => mounted && setMeta({ loading: false, customers: [], products: [], price_types: [], error: requestMessage(error, locale) }));
        return () => { mounted = false; };
    }, [locale]);

    useEffect(() => {
        let mounted = true;
        setState((current) => ({ ...current, loading: true, error: '' }));
        const timer = window.setTimeout(() => {
            window.axios.get(apiBase('orders'), {
                params: {
                    search: filters.search || undefined,
                    status: filters.status || undefined,
                    date: filters.date || undefined,
                    customer_id: filters.customer_id || undefined,
                    page,
                    per_page: 20,
                },
            }).then(({ data }) => {
                if (!mounted) return;
                setState({ loading: false, items: data.data.items, summary: data.data.summary, pageMeta: data.data.meta, error: '' });
            }).catch((error) => mounted && setState({ loading: false, items: [], summary: {}, pageMeta: {}, error: requestMessage(error, locale) }));
        }, 220);

        return () => { mounted = false; window.clearTimeout(timer); };
    }, [filters, locale, page, refreshKey]);

    useEffect(() => {
        setPage(1);
    }, [filters.search, filters.status, filters.date, filters.customer_id]);

    const selectedCustomer = useMemo(() => meta.customers.find((customer) => Number(customer.id) === Number(form.customer_id)), [form.customer_id, meta.customers]);
    const preview = useMemo(() => calculatePreview(form.items), [form.items]);

    const openForm = () => {
        const firstCustomer = meta.customers[0];
        const priceTypeId = defaultPriceTypeId(firstCustomer, meta.price_types);
        setForm({
            customer_id: firstCustomer?.id || '',
            price_type_id: priceTypeId,
            order_date: today,
            requested_delivery_date: '',
            payment_type: 'credit',
            notes: '',
            items: [{ ...blankItem }],
        });
        setFormOpen(true);
    };

    const closeForm = () => {
        setFormOpen(false);
        setSaving(false);
    };

    const updateItem = (index, patch) => {
        setForm((current) => {
            const nextItems = current.items.map((item, itemIndex) => itemIndex === index ? { ...item, ...patch } : item);
            return { ...current, items: nextItems };
        });
    };

    const handleProductChange = (index, productId) => {
        const product = meta.products.find((item) => Number(item.id) === Number(productId));
        const priceTypeId = form.price_type_id || defaultPriceTypeId(selectedCustomer, meta.price_types);
        const nextPrice = product ? priceForProduct(product, priceTypeId) : '';
        updateItem(index, { product_id: productId, unit_price: nextPrice });
    };

    const handleCustomerChange = (customerId) => {
        const customer = meta.customers.find((item) => Number(item.id) === Number(customerId));
        const priceTypeId = defaultPriceTypeId(customer, meta.price_types);
        setForm((current) => ({
            ...current,
            customer_id: customerId,
            price_type_id: priceTypeId,
            items: repriceItems(current.items, meta.products, priceTypeId),
        }));
    };

    const handlePriceTypeChange = (priceTypeId) => {
        setForm((current) => ({
            ...current,
            price_type_id: priceTypeId,
            items: repriceItems(current.items, meta.products, priceTypeId),
        }));
    };

    const submit = async (event) => {
        event.preventDefault();
        if (!canManage) return;
        setSaving(true);
        setState((current) => ({ ...current, error: '' }));

        try {
            const payload = {
                customer_id: Number(form.customer_id),
                price_type_id: form.price_type_id ? Number(form.price_type_id) : undefined,
                order_date: form.order_date,
                requested_delivery_date: form.requested_delivery_date || null,
                payment_type: form.payment_type,
                notes: form.notes || null,
                items: form.items
                    .filter((item) => item.product_id)
                    .map((item) => ({
                        product_id: Number(item.product_id),
                        quantity: Number(item.quantity || 0),
                        unit_price: Number(item.unit_price || 0),
                        discount_amount: Number(item.discount_amount || 0),
                        item_type: item.item_type,
                        remarks: item.remarks || null,
                    })),
            };
            const { data } = await window.axios.post(apiBase('orders'), payload);
            setViewing({ order: data.data.order, items: data.data.items });
            closeForm();
            setRefreshKey((key) => key + 1);
        } catch (error) {
            setState((current) => ({ ...current, error: requestMessage(error, locale) }));
            setSaving(false);
        }
    };

    const confirmOrder = async (order) => {
        if (!canManage || processingId || !window.confirm(t(locale, 'confirmPrompt'))) return;
        setProcessingId(order.id);
        setState((current) => ({ ...current, error: '' }));
        try {
            const { data } = await window.axios.post(`${apiBase('orders')}/${order.id}/confirm`);
            setViewing((current) => current?.order?.id === order.id ? { ...current, order: data.data.order } : current);
            setRefreshKey((key) => key + 1);
        } catch (error) {
            setState((current) => ({ ...current, error: requestMessage(error, locale) }));
        } finally {
            setProcessingId(null);
        }
    };

    const cancelOrder = async (order) => {
        if (!canManage || processingId || !window.confirm(t(locale, 'cancelOrderPrompt'))) return;
        setProcessingId(`cancel-${order.id}`);
        setState((current) => ({ ...current, error: '' }));
        try {
            const { data } = await window.axios.post(`${apiBase('orders')}/${order.id}/cancel`);
            setViewing((current) => current?.order?.id === order.id ? { ...current, order: data.data.order } : current);
            setRefreshKey((key) => key + 1);
        } catch (error) {
            setState((current) => ({ ...current, error: requestMessage(error, locale) }));
        } finally {
            setProcessingId(null);
        }
    };

    const createInvoice = async (order) => {
        if (!canManageInvoices || processingId || !window.confirm(t(locale, 'createInvoicePrompt'))) return;
        setProcessingId(`invoice-${order.id}`);
        setState((current) => ({ ...current, error: '' }));
        const dueDate = new Date();
        dueDate.setDate(dueDate.getDate() + 7);

        try {
            const { data } = await window.axios.post(`${apiBase('invoices')}/from-order`, {
                order_id: order.id,
                invoice_date: today,
                due_date: dueDate.toISOString().slice(0, 10),
            });
            window.alert(`${t(locale, 'invoiceCreated')}: ${data.data.invoice.code}`);
            setViewing((current) => current?.order?.id === order.id ? { ...current, order: { ...current.order, status: 'invoiced' } } : current);
            setRefreshKey((key) => key + 1);
        } catch (error) {
            setState((current) => ({ ...current, error: requestMessage(error, locale) }));
        } finally {
            setProcessingId(null);
        }
    };

    return (
        <section className="master-workspace order-workspace">
            <div className="master-heading">
                <div>
                    <p className="eyebrow">{t(locale, 'orders')}</p>
                    <h1>{t(locale, 'orders')}</h1>
                    <span className="muted">{t(locale, 'ordersHint')}</span>
                </div>
                {canManage && <button className="button primary" type="button" disabled={meta.loading} onClick={openForm}><Plus size={16} /> {t(locale, 'newOrder')}</button>}
            </div>

            <div className="metrics order-metrics">
                <div className="metric"><span>{t(locale, 'orders')}</span><strong>{Number(state.summary.orders_count || 0)}</strong><small>{t(locale, 'allStatuses')}</small></div>
                <div className="metric"><span>{t(locale, 'totalAmount')}</span><strong>{money(state.summary.total_amount)}</strong><small>{t(locale, 'subtotal')}</small></div>
                <div className="metric"><span>{t(locale, 'pending')}</span><strong>{Number(state.summary.pending_count || 0)}</strong><small>{t(locale, 'confirmOrder')}</small></div>
                <div className="metric"><span>{t(locale, 'confirmed')}</span><strong>{Number(state.summary.confirmed_count || 0)}</strong><small>{t(locale, 'route')}</small></div>
            </div>

            <div className="master-panel">
                <StatusTabs
                    label={t(locale, 'status')}
                    locale={locale}
                    statuses={orderStatusTabs}
                    value={filters.status}
                    onChange={(status) => setFilters((current) => ({ ...current, status }))}
                />
                <div className="master-toolbar order-toolbar">
                    <label className="master-search"><Search size={14} /><input value={filters.search} placeholder={t(locale, 'searchOrders')} onChange={(event) => setFilters((current) => ({ ...current, search: event.target.value }))} /></label>
                    <select aria-label={t(locale, 'customer')} value={filters.customer_id} onChange={(event) => setFilters((current) => ({ ...current, customer_id: event.target.value }))}>
                        <option value="">{t(locale, 'allCustomers')}</option>
                        {meta.customers.map((customer) => <option value={customer.id} key={customer.id}>{customer.label}</option>)}
                    </select>
                    <label className="date-filter" title={t(locale, 'date')}><CalendarDays size={14} /><input type="date" value={filters.date} onChange={(event) => setFilters((current) => ({ ...current, date: event.target.value }))} /></label>
                    <button className="icon-button" type="button" aria-label={t(locale, 'refresh')} title={t(locale, 'refresh')} onClick={() => setRefreshKey((key) => key + 1)}><RefreshCw size={15} /></button>
                </div>

                {(state.error || meta.error) && <div className="inline-error"><AlertCircle size={15} /> {state.error || meta.error}</div>}
                {state.loading ? <WorkspaceState icon={RefreshCw} title={t(locale, 'loading')} loading compact /> : state.items.length === 0 ? <WorkspaceState icon={ShoppingCart} title={t(locale, 'empty')} compact /> : (
                    <div className="master-table-wrap">
                        <table className="master-table order-table">
                            <thead><tr><th>{t(locale, 'orders')}</th><th>{t(locale, 'customer')}</th><th>{t(locale, 'date')}</th><th>{t(locale, 'paymentType')}</th><th>{t(locale, 'total')}</th><th>{t(locale, 'status')}</th><th>{t(locale, 'actions')}</th></tr></thead>
                            <tbody>
                                {state.items.map((order) => (
                                    <tr key={order.id}>
                                        <td><strong>{order.code}</strong><span className="muted">{t(locale, 'updated')}: {formatDateTime(order.updated_at)}</span></td>
                                        <td><strong>{order.shop_name}</strong><span className="muted">{order.customer_code} · {order.route || '-'}</span></td>
                                        <td>{order.order_date}<span className="muted">{order.requested_delivery_date || '-'}</span></td>
                                        <td>{t(locale, order.payment_type)}</td>
                                        <td className="numeric">{money(order.total)}</td>
                                        <td><StatusBadge status={order.status} locale={locale} /></td>
                                        <td className="row-actions">
                                            <button type="button" aria-label={t(locale, 'details')} title={t(locale, 'details')} onClick={() => setViewing({ order, items: null })}><Eye size={15} /></button>
                                            {canManage && order.status === 'pending' && <button type="button" disabled={processingId === order.id} aria-label={t(locale, 'confirm')} title={t(locale, 'confirm')} onClick={() => confirmOrder(order)}><BadgeCheck size={15} /></button>}
                                            {canManage && ['pending', 'confirmed'].includes(order.status) && <button className="danger" type="button" disabled={processingId === `cancel-${order.id}`} aria-label={t(locale, 'cancelOrder')} title={t(locale, 'cancelOrder')} onClick={() => cancelOrder(order)}><Ban size={15} /></button>}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination meta={state.pageMeta} page={page} setPage={setPage} />
            </div>

            {formOpen && (
                <div className="modal-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && closeForm()}>
                    <form className="master-dialog order-dialog" role="dialog" aria-modal="true" aria-label={t(locale, 'orders')} onSubmit={submit}>
                        <header><div><p className="eyebrow">{t(locale, 'newOrder')}</p><h2>{t(locale, 'orders')}</h2></div><button className="icon-button" type="button" aria-label={t(locale, 'cancel')} onClick={closeForm}><X size={17} /></button></header>
                        <div className="master-form-body">
                            <div className="master-form-grid">
                                <label className="master-field wide"><span>{t(locale, 'customer')} <b>*</b></span><select required value={form.customer_id} onChange={(event) => handleCustomerChange(event.target.value)}><option value="">{t(locale, 'customer')}</option>{meta.customers.map((customer) => <option value={customer.id} key={customer.id}>{customer.label}</option>)}</select></label>
                                <label className="master-field"><span>{t(locale, 'priceType')}</span><select value={form.price_type_id} onChange={(event) => handlePriceTypeChange(event.target.value)}><option value="">{t(locale, 'priceType')}</option>{meta.price_types.map((priceType) => <option value={priceType.id} key={priceType.id}>{priceType.name}</option>)}</select></label>
                                <label className="master-field"><span>{t(locale, 'orderDate')} <b>*</b></span><input required type="date" value={form.order_date} onChange={(event) => setForm((current) => ({ ...current, order_date: event.target.value }))} /></label>
                                <label className="master-field"><span>{t(locale, 'requestedDelivery')}</span><input type="date" value={form.requested_delivery_date} onChange={(event) => setForm((current) => ({ ...current, requested_delivery_date: event.target.value }))} /></label>
                                <label className="master-field"><span>{t(locale, 'paymentType')} <b>*</b></span><select required value={form.payment_type} onChange={(event) => setForm((current) => ({ ...current, payment_type: event.target.value }))}><option value="credit">{t(locale, 'credit')}</option><option value="cash">{t(locale, 'cash')}</option></select></label>
                                <label className="master-field wide"><span>{t(locale, 'notes')}</span><textarea rows="2" maxLength="500" value={form.notes} onChange={(event) => setForm((current) => ({ ...current, notes: event.target.value }))} /></label>
                            </div>

                            <section className="order-form-items">
                                <div className="order-form-items-heading"><strong>{t(locale, 'items')}</strong><button className="button" type="button" onClick={() => setForm((current) => ({ ...current, items: [...current.items, { ...blankItem }] }))}><Plus size={14} /> {t(locale, 'addItem')}</button></div>
                                {form.items.map((item, index) => {
                                    const product = meta.products.find((product) => Number(product.id) === Number(item.product_id));
                                    return (
                                        <div className="order-item-grid" key={index}>
                                            <label className="master-field product-field"><span>{t(locale, 'product')} <b>*</b></span><select required value={item.product_id} onChange={(event) => handleProductChange(index, event.target.value)}><option value="">{t(locale, 'product')}</option>{meta.products.map((productOption) => <option value={productOption.id} key={productOption.id}>{productOption.label}</option>)}</select></label>
                                            <label className="master-field"><span>{t(locale, 'quantity')}</span><input required type="number" min="0.01" step="0.01" value={item.quantity} onChange={(event) => updateItem(index, { quantity: event.target.value })} /></label>
                                            <label className="master-field"><span>{t(locale, 'price')}</span><input required type="number" min="0" step="1" value={item.unit_price} onChange={(event) => updateItem(index, { unit_price: event.target.value })} /></label>
                                            <label className="master-field"><span>{t(locale, 'itemType')}</span><select value={item.item_type} onChange={(event) => updateItem(index, { item_type: event.target.value, unit_price: event.target.value === 'foc' ? 0 : (product ? priceForProduct(product, form.price_type_id || defaultPriceTypeId(selectedCustomer, meta.price_types)) : item.unit_price) })}>{['sale', 'foc'].map((type) => <option value={type} key={type}>{t(locale, type)}</option>)}</select></label>
                                            <label className="master-field"><span>{t(locale, 'discount')}</span><input type="number" min="0" step="1" value={item.discount_amount} onChange={(event) => updateItem(index, { discount_amount: event.target.value })} /></label>
                                            <button className="icon-button danger order-line-remove" type="button" aria-label={t(locale, 'cancel')} title={t(locale, 'cancel')} disabled={form.items.length === 1} onClick={() => setForm((current) => ({ ...current, items: current.items.filter((_, itemIndex) => itemIndex !== index) }))}><Trash2 size={15} /></button>
                                        </div>
                                    );
                                })}
                                <div className="order-form-summary"><span>{t(locale, 'subtotal')}: <strong>{money(preview.subtotal)}</strong></span><span>{t(locale, 'discount')}: <strong>{money(preview.discount)}</strong></span><span>{t(locale, 'total')}: <strong>{money(preview.total)}</strong></span></div>
                            </section>
                        </div>
                        <footer><button className="button" type="button" onClick={closeForm}>{t(locale, 'cancel')}</button><button className="button primary" type="submit" disabled={saving || meta.loading}><Save size={15} /> {t(locale, 'save')}</button></footer>
                    </form>
                </div>
            )}

            {viewing && <OrderDrawer key={`${viewing.order.id}-${viewing.order.status}`} viewing={viewing} locale={locale} canManage={canManage} canManageInvoices={canManageInvoices} processingId={processingId} onConfirm={confirmOrder} onCancelOrder={cancelOrder} onCreateInvoice={createInvoice} onClose={() => setViewing(null)} />}
        </section>
    );
}

function OrderDrawer({ viewing, locale, canManage, canManageInvoices, processingId, onConfirm, onCancelOrder, onCreateInvoice, onClose }) {
    const [state, setState] = useState(() => viewing.items ? { loading: false, order: viewing.order, items: viewing.items, error: '' } : { loading: true, order: viewing.order, items: [], error: '' });

    useEffect(() => {
        if (viewing.items) return undefined;
        let mounted = true;
        window.axios.get(`${apiBase('orders')}/${viewing.order.id}`)
            .then(({ data }) => mounted && setState({ loading: false, order: data.data.order, items: data.data.items, error: '' }))
            .catch((error) => mounted && setState((current) => ({ ...current, loading: false, error: requestMessage(error, locale) })));
        return () => { mounted = false; };
    }, [locale, viewing]);

    return (
        <div className="drawer-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
            <aside className="record-drawer order-drawer" role="dialog" aria-modal="true" aria-label={t(locale, 'details')}>
                <header><div><p className="eyebrow">{t(locale, 'details')}</p><h2>{state.order.code}</h2></div><button className="icon-button" type="button" aria-label={t(locale, 'cancel')} onClick={onClose}><X size={17} /></button></header>
                {state.error && <div className="inline-error"><AlertCircle size={15} /> {state.error}</div>}
                {state.loading ? <WorkspaceState icon={RefreshCw} title={t(locale, 'loading')} loading compact /> : (
                    <div className="order-drawer-body">
                        <section className="order-total-card">
                            <ShoppingCart size={18} />
                            <div><small>{state.order.shop_name}</small><strong>{money(state.order.total)}</strong><span>{state.order.order_date} · {t(locale, state.order.payment_type)} · <StatusBadge status={state.order.status} locale={locale} /></span></div>
                        </section>
                        <dl>
                            <div><dt>{t(locale, 'customer')}</dt><dd>{state.order.customer_code} · {state.order.shop_name}</dd></div>
                            <div><dt>{t(locale, 'route')}</dt><dd>{state.order.route || '-'}</dd></div>
                            <div><dt>{t(locale, 'priceType')}</dt><dd>{state.order.price_type || '-'}</dd></div>
                            <div><dt>{t(locale, 'requestedDelivery')}</dt><dd>{state.order.requested_delivery_date || '-'}</dd></div>
                            <div><dt>{t(locale, 'notes')}</dt><dd>{state.order.notes || '-'}</dd></div>
                        </dl>
                        <div className="master-table-wrap">
                            <table className="master-table order-item-table">
                                <thead><tr><th>{t(locale, 'product')}</th><th>{t(locale, 'itemType')}</th><th>{t(locale, 'quantity')}</th><th>{t(locale, 'price')}</th><th>{t(locale, 'total')}</th></tr></thead>
                                <tbody>{state.items.map((item) => <tr key={item.id}><td><strong>{item.product_name}</strong><span className="muted">{item.product_sku} · {item.unit}</span></td><td>{t(locale, item.item_type)}</td><td className="numeric">{Number(item.quantity).toLocaleString()}</td><td className="numeric">{money(item.unit_price)}</td><td className="numeric">{money(item.line_total)}</td></tr>)}</tbody>
                            </table>
                        </div>
                    </div>
                )}
                {((canManage && ['pending', 'confirmed'].includes(state.order.status)) || (canManageInvoices && state.order.status === 'confirmed')) && (
                    <footer>
                        {canManage && state.order.status === 'pending' && <button className="button primary" type="button" disabled={processingId === state.order.id} onClick={() => onConfirm(state.order)}><BadgeCheck size={15} /> {t(locale, 'confirmOrder')}</button>}
                        {canManageInvoices && state.order.status === 'confirmed' && <button className="button primary" type="button" disabled={processingId === `invoice-${state.order.id}`} onClick={() => onCreateInvoice(state.order)}><FileText size={15} /> {t(locale, 'createInvoice')}</button>}
                        {canManage && ['pending', 'confirmed'].includes(state.order.status) && <button className="button danger" type="button" disabled={processingId === `cancel-${state.order.id}`} onClick={() => onCancelOrder(state.order)}><Ban size={15} /> {t(locale, 'cancelOrder')}</button>}
                    </footer>
                )}
            </aside>
        </div>
    );
}

export function InvoicesScreen({ locale, canManage = false }) {
    const [filters, setFilters] = useState({ search: '', status: '', date: '', customer_id: '' });
    const [meta, setMeta] = useState({ loading: true, customers: [], error: '' });
    const [state, setState] = useState({ loading: true, items: [], summary: {}, pageMeta: {}, error: '' });
    const [page, setPage] = useState(1);
    const [refreshKey, setRefreshKey] = useState(0);
    const [viewing, setViewing] = useState(null);
    const [processingId, setProcessingId] = useState(null);

    useEffect(() => {
        let mounted = true;
        window.axios.get(`${apiBase('invoices')}/meta`)
            .then(({ data }) => mounted && setMeta({ loading: false, customers: data.data.customers, error: '' }))
            .catch((error) => mounted && setMeta({ loading: false, customers: [], error: requestMessage(error, locale) }));
        return () => { mounted = false; };
    }, [locale]);

    useEffect(() => {
        let mounted = true;
        setState((current) => ({ ...current, loading: true, error: '' }));
        const timer = window.setTimeout(() => {
            window.axios.get(apiBase('invoices'), {
                params: {
                    search: filters.search || undefined,
                    status: filters.status || undefined,
                    date: filters.date || undefined,
                    customer_id: filters.customer_id || undefined,
                    page,
                    per_page: 20,
                },
            }).then(({ data }) => {
                if (!mounted) return;
                setState({ loading: false, items: data.data.items, summary: data.data.summary, pageMeta: data.data.meta, error: '' });
            }).catch((error) => mounted && setState({ loading: false, items: [], summary: {}, pageMeta: {}, error: requestMessage(error, locale) }));
        }, 220);

        return () => { mounted = false; window.clearTimeout(timer); };
    }, [filters, locale, page, refreshKey]);

    useEffect(() => {
        setPage(1);
    }, [filters.search, filters.status, filters.date, filters.customer_id]);

    const issueInvoice = async (invoice) => {
        if (!canManage || processingId || !window.confirm(t(locale, 'issueInvoicePrompt'))) return;
        setProcessingId(`issue-${invoice.id}`);
        setState((current) => ({ ...current, error: '' }));
        try {
            const { data } = await window.axios.post(`${apiBase('invoices')}/${invoice.id}/issue`);
            setViewing((current) => current?.invoice?.id === invoice.id ? { ...current, invoice: data.data.invoice } : current);
            setRefreshKey((key) => key + 1);
        } catch (error) {
            setState((current) => ({ ...current, error: requestMessage(error, locale) }));
        } finally {
            setProcessingId(null);
        }
    };

    const cancelInvoice = async (invoice) => {
        if (!canManage || processingId || !window.confirm(t(locale, 'cancelInvoicePrompt'))) return;
        setProcessingId(`cancel-${invoice.id}`);
        setState((current) => ({ ...current, error: '' }));
        try {
            const { data } = await window.axios.post(`${apiBase('invoices')}/${invoice.id}/cancel`);
            setViewing((current) => current?.invoice?.id === invoice.id ? { ...current, invoice: data.data.invoice } : current);
            setRefreshKey((key) => key + 1);
        } catch (error) {
            setState((current) => ({ ...current, error: requestMessage(error, locale) }));
        } finally {
            setProcessingId(null);
        }
    };

    return (
        <section className="master-workspace invoice-workspace">
            <div className="master-heading">
                <div>
                    <p className="eyebrow">{t(locale, 'invoices')}</p>
                    <h1>{t(locale, 'invoices')}</h1>
                    <span className="muted">{t(locale, 'invoicesHint')}</span>
                </div>
                <button className="icon-button" type="button" aria-label={t(locale, 'refresh')} title={t(locale, 'refresh')} onClick={() => setRefreshKey((key) => key + 1)}><RefreshCw size={15} /></button>
            </div>

            <div className="metrics invoice-metrics">
                <div className="metric"><span>{t(locale, 'invoices')}</span><strong>{Number(state.summary.invoices_count || 0)}</strong><small>{t(locale, 'allStatuses')}</small></div>
                <div className="metric"><span>{t(locale, 'totalAmount')}</span><strong>{money(state.summary.total_amount)}</strong><small>{t(locale, 'subtotal')}</small></div>
                <div className="metric"><span>{t(locale, 'draft')}</span><strong>{Number(state.summary.draft_count || 0)}</strong><small>{t(locale, 'createInvoice')}</small></div>
                <div className="metric"><span>{t(locale, 'issued')}</span><strong>{Number(state.summary.issued_count || 0)}</strong><small>{t(locale, 'status')}</small></div>
            </div>

            <div className="master-panel">
                <StatusTabs
                    label={t(locale, 'status')}
                    locale={locale}
                    statuses={invoiceStatusTabs}
                    value={filters.status}
                    onChange={(status) => setFilters((current) => ({ ...current, status }))}
                />
                <div className="master-toolbar invoice-toolbar">
                    <label className="master-search"><Search size={14} /><input value={filters.search} placeholder={t(locale, 'searchInvoices')} onChange={(event) => setFilters((current) => ({ ...current, search: event.target.value }))} /></label>
                    <select aria-label={t(locale, 'customer')} value={filters.customer_id} onChange={(event) => setFilters((current) => ({ ...current, customer_id: event.target.value }))}>
                        <option value="">{t(locale, 'allCustomers')}</option>
                        {meta.customers.map((customer) => <option value={customer.id} key={customer.id}>{customer.label}</option>)}
                    </select>
                    <label className="date-filter" title={t(locale, 'date')}><CalendarDays size={14} /><input type="date" value={filters.date} onChange={(event) => setFilters((current) => ({ ...current, date: event.target.value }))} /></label>
                    <button className="icon-button" type="button" aria-label={t(locale, 'refresh')} title={t(locale, 'refresh')} onClick={() => setRefreshKey((key) => key + 1)}><RefreshCw size={15} /></button>
                </div>

                {(state.error || meta.error) && <div className="inline-error"><AlertCircle size={15} /> {state.error || meta.error}</div>}
                {state.loading ? <WorkspaceState icon={RefreshCw} title={t(locale, 'loading')} loading compact /> : state.items.length === 0 ? <WorkspaceState icon={FileText} title={t(locale, 'empty')} compact /> : (
                    <div className="master-table-wrap">
                        <table className="master-table invoice-table">
                            <thead><tr><th>{t(locale, 'invoices')}</th><th>{t(locale, 'order')}</th><th>{t(locale, 'customer')}</th><th>{t(locale, 'invoiceDate')}</th><th>{t(locale, 'dueDate')}</th><th>{t(locale, 'total')}</th><th>{t(locale, 'status')}</th><th>{t(locale, 'actions')}</th></tr></thead>
                            <tbody>
                                {state.items.map((invoice) => (
                                    <tr key={invoice.id}>
                                        <td><strong>{invoice.code}</strong><span className="muted">{t(locale, 'updated')}: {formatDateTime(invoice.updated_at)}</span></td>
                                        <td><strong>{invoice.order_code || '-'}</strong><span className="muted">{t(locale, invoice.payment_type || '')}</span></td>
                                        <td><strong>{invoice.shop_name}</strong><span className="muted">{invoice.customer_code} · {invoice.route || '-'}</span></td>
                                        <td>{invoice.invoice_date}</td>
                                        <td>{invoice.due_date || '-'}</td>
                                        <td className="numeric">{money(invoice.total)}</td>
                                        <td><StatusBadge status={invoice.status} locale={locale} /></td>
                                        <td className="row-actions">
                                            <button type="button" aria-label={t(locale, 'details')} title={t(locale, 'details')} onClick={() => setViewing({ invoice, items: null })}><Eye size={15} /></button>
                                            {canManage && invoice.status === 'draft' && <button type="button" disabled={processingId === `issue-${invoice.id}`} aria-label={t(locale, 'issueInvoice')} title={t(locale, 'issueInvoice')} onClick={() => issueInvoice(invoice)}><BadgeCheck size={15} /></button>}
                                            {canManage && invoice.status !== 'cancelled' && <button className="danger" type="button" disabled={processingId === `cancel-${invoice.id}`} aria-label={t(locale, 'cancelInvoice')} title={t(locale, 'cancelInvoice')} onClick={() => cancelInvoice(invoice)}><Ban size={15} /></button>}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination meta={state.pageMeta} page={page} setPage={setPage} />
            </div>

            {viewing && <InvoiceDrawer viewing={viewing} locale={locale} canManage={canManage} processingId={processingId} onIssue={issueInvoice} onCancelInvoice={cancelInvoice} onClose={() => setViewing(null)} />}
        </section>
    );
}

function InvoiceDrawer({ viewing, locale, canManage, processingId, onIssue, onCancelInvoice, onClose }) {
    const [state, setState] = useState(() => viewing.items ? { loading: false, invoice: viewing.invoice, items: viewing.items, error: '' } : { loading: true, invoice: viewing.invoice, items: [], error: '' });

    useEffect(() => {
        if (viewing.items) return undefined;
        let mounted = true;
        window.axios.get(`${apiBase('invoices')}/${viewing.invoice.id}`)
            .then(({ data }) => mounted && setState({ loading: false, invoice: data.data.invoice, items: data.data.items, error: '' }))
            .catch((error) => mounted && setState((current) => ({ ...current, loading: false, error: requestMessage(error, locale) })));
        return () => { mounted = false; };
    }, [locale, viewing]);

    return (
        <div className="drawer-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
            <aside className="record-drawer invoice-drawer" role="dialog" aria-modal="true" aria-label={t(locale, 'details')}>
                <header><div><p className="eyebrow">{t(locale, 'details')}</p><h2>{state.invoice.code}</h2></div><button className="icon-button" type="button" aria-label={t(locale, 'cancel')} onClick={onClose}><X size={17} /></button></header>
                {state.error && <div className="inline-error"><AlertCircle size={15} /> {state.error}</div>}
                {state.loading ? <WorkspaceState icon={RefreshCw} title={t(locale, 'loading')} loading compact /> : (
                    <div className="invoice-drawer-body">
                        <section className="invoice-total-card">
                            <FileText size={18} />
                            <div><small>{state.invoice.shop_name}</small><strong>{money(state.invoice.total)}</strong><span>{state.invoice.invoice_date} · {state.invoice.order_code || '-'} · <StatusBadge status={state.invoice.status} locale={locale} /></span></div>
                        </section>
                        <dl>
                            <div><dt>{t(locale, 'customer')}</dt><dd>{state.invoice.customer_code} · {state.invoice.shop_name}</dd></div>
                            <div><dt>{t(locale, 'route')}</dt><dd>{state.invoice.route || '-'}</dd></div>
                            <div><dt>{t(locale, 'dueDate')}</dt><dd>{state.invoice.due_date || '-'}</dd></div>
                            <div><dt>{t(locale, 'notes')}</dt><dd>{state.invoice.notes || '-'}</dd></div>
                        </dl>
                        <div className="master-table-wrap">
                            <table className="master-table invoice-item-table">
                                <thead><tr><th>{t(locale, 'product')}</th><th>{t(locale, 'quantity')}</th><th>{t(locale, 'price')}</th><th>{t(locale, 'total')}</th></tr></thead>
                                <tbody>{state.items.map((item) => <tr key={item.id}><td><strong>{item.product_name}</strong><span className="muted">{item.product_sku} · {item.unit}</span></td><td className="numeric">{Number(item.quantity).toLocaleString()}</td><td className="numeric">{money(item.unit_price)}</td><td className="numeric">{money(item.line_total)}</td></tr>)}</tbody>
                            </table>
                        </div>
                    </div>
                )}
                {canManage && state.invoice.status !== 'cancelled' && (
                    <footer>
                        {state.invoice.status === 'draft' && <button className="button primary" type="button" disabled={processingId === `issue-${state.invoice.id}`} onClick={() => onIssue(state.invoice)}><BadgeCheck size={15} /> {t(locale, 'issueInvoice')}</button>}
                        <button className="button danger" type="button" disabled={processingId === `cancel-${state.invoice.id}`} onClick={() => onCancelInvoice(state.invoice)}><Ban size={15} /> {t(locale, 'cancelInvoice')}</button>
                    </footer>
                )}
            </aside>
        </div>
    );
}

function OrderCustomerCombobox({ customers, value, onChange, locale = 'en' }) {
    const selected = customers.find((customer) => String(customer.id) === String(value));
    const displayValue = selected ? `${selected.code} · ${selected.shop_name}` : '';
    const [query, setQuery] = useState(displayValue);
    const [open, setOpen] = useState(false);
    const [activeIndex, setActiveIndex] = useState(0);
    const rootRef = useRef(null);
    const inputRef = useRef(null);
    const optionsId = useId();
    const normalizedQuery = query.trim().toLocaleLowerCase();
    const filtered = customers
        .filter((customer) => !normalizedQuery || `${customer.code} ${customer.shop_name}`.toLocaleLowerCase().includes(normalizedQuery))
        .slice(0, 20);

    useEffect(() => {
        if (!open) setQuery(displayValue);
    }, [displayValue, open]);

    useEffect(() => {
        const close = (event) => {
            if (!rootRef.current?.contains(event.target)) {
                setOpen(false);
                setQuery(displayValue);
            }
        };
        document.addEventListener('mousedown', close);
        return () => document.removeEventListener('mousedown', close);
    }, [displayValue]);

    const choose = (customer) => {
        onChange(String(customer.id));
        setQuery(`${customer.code} · ${customer.shop_name}`);
        inputRef.current?.setCustomValidity('');
        setOpen(false);
    };

    const keyDown = (event) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setOpen(true);
            setActiveIndex((index) => Math.min(index + 1, Math.max(filtered.length - 1, 0)));
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActiveIndex((index) => Math.max(index - 1, 0));
        } else if (event.key === 'Enter' && open && filtered[activeIndex]) {
            event.preventDefault();
            choose(filtered[activeIndex]);
        } else if (event.key === 'Escape') {
            setQuery(displayValue);
            setOpen(false);
        }
    };

    return <div className="customer-combobox order-customer-combobox" ref={rootRef}>
        <div className="customer-combobox-input">
            <Search size={16} />
            <input
                ref={inputRef}
                role="combobox"
                aria-autocomplete="list"
                aria-controls={optionsId}
                aria-expanded={open}
                required
                value={query}
                placeholder={locale === 'my' ? 'ဖောက်သည်ကုဒ် သို့မဟုတ် ဆိုင်အမည်ရှာရန်' : 'Search customer code or shop name'}
                onFocus={() => {
                    setQuery('');
                    setActiveIndex(0);
                    setOpen(true);
                }}
                onKeyDown={keyDown}
                onInvalid={(event) => event.currentTarget.setCustomValidity(value ? '' : (locale === 'my' ? 'ရှာဖွေမှုရလဒ်မှ ဖောက်သည်ကို ရွေးပါ။' : 'Select a customer from the search results.'))}
                onChange={(event) => {
                    event.currentTarget.setCustomValidity('');
                    setQuery(event.target.value);
                    onChange('');
                    setActiveIndex(0);
                    setOpen(true);
                }}
            />
        </div>
        {open && <div className="customer-combobox-options" id={optionsId} role="listbox">
            {filtered.length ? filtered.map((customer, index) => <button className={index === activeIndex ? 'is-active' : ''} type="button" role="option" aria-selected={String(customer.id) === String(value)} key={customer.id} onMouseDown={(event) => event.preventDefault()} onClick={() => choose(customer)}><span><strong>{customer.shop_name}</strong><small>{customer.code}</small></span>{String(customer.id) === String(value) && <BadgeCheck size={16} />}</button>) : <p>{locale === 'my' ? 'ကိုက်ညီသော ဖောက်သည်မရှိပါ။' : `No customers match “${query}”.`}</p>}
            {customers.length > 20 && !normalizedQuery && <small className="customer-combobox-hint">{locale === 'my' ? `ဖောက်သည် ${customers.length} ဦးကို ရှာရန် စာရိုက်ပါ` : `Type to search ${customers.length} customers`}</small>}
        </div>}
    </div>;
}

export function MobileOrdersScreen({ appId, locale, onViewOrder }) {
    const today = new Date().toISOString().slice(0, 10);
    const [mode, setMode] = useState('list');
    const [filters, setFilters] = useState({ search: '', status: '' });
    const [meta, setMeta] = useState({ loading: true, app: appId, customers: [], products: [], error: '' });
    const [state, setState] = useState({ loading: true, items: [], summary: {}, pageMeta: {}, error: '' });
    const [saving, setSaving] = useState(false);
    const [showDetails, setShowDetails] = useState(false);
    const [refreshKey, setRefreshKey] = useState(0);
    const [form, setForm] = useState(() => ({
        customer_id: '',
        requested_delivery_date: '',
        payment_type: 'credit',
        notes: '',
        items: [{ product_id: '', quantity: 1, item_type: 'sale' }],
    }));

    useEffect(() => {
        let mounted = true;
        window.axios.get(`${apiBase('mobileOrders')}/meta`)
            .then(({ data }) => {
                if (!mounted) return;
                const customers = data.data.customers || [];
                setMeta({ loading: false, app: data.data.app, customers, products: data.data.products || [], error: '' });
                setForm((current) => ({ ...current, customer_id: current.customer_id || customers[0]?.id || '' }));
            })
            .catch((error) => mounted && setMeta({ loading: false, app: appId, customers: [], products: [], error: requestMessage(error, locale) }));
        return () => { mounted = false; };
    }, [appId, locale]);

    useEffect(() => {
        let mounted = true;
        setState((current) => ({ ...current, loading: true, error: '' }));
        const timer = window.setTimeout(() => {
            window.axios.get(apiBase('mobileOrders'), {
                params: {
                    search: filters.search || undefined,
                    status: filters.status || undefined,
                    per_page: 12,
                },
            }).then(({ data }) => {
                if (!mounted) return;
                setState({ loading: false, items: data.data.items, summary: data.data.summary, pageMeta: data.data.meta, error: '' });
            }).catch((error) => mounted && setState({ loading: false, items: [], summary: {}, pageMeta: {}, error: requestMessage(error, locale) }));
        }, 220);

        return () => { mounted = false; window.clearTimeout(timer); };
    }, [filters, locale, refreshKey]);

    const selectedCustomer = meta.customers.find((customer) => Number(customer.id) === Number(form.customer_id));
    const preview = calculateMobilePreview(form.items, meta.products, selectedCustomer?.price_type_id);

    const resetForm = () => {
        setForm({
            customer_id: meta.customers[0]?.id || '',
            requested_delivery_date: '',
            payment_type: 'credit',
            notes: '',
            items: [{ product_id: '', quantity: 1, item_type: 'sale' }],
        });
    };

    const updateItem = (index, patch) => {
        setForm((current) => ({ ...current, items: current.items.map((item, itemIndex) => itemIndex === index ? { ...item, ...patch } : item) }));
    };

    const submit = async (event) => {
        event.preventDefault();
        setSaving(true);
        setState((current) => ({ ...current, error: '' }));

        try {
            const payload = {
                customer_id: meta.app === 'sales' ? Number(form.customer_id) : undefined,
                order_date: today,
                requested_delivery_date: form.requested_delivery_date || null,
                payment_type: form.payment_type,
                notes: form.notes || null,
                items: form.items.filter((item) => item.product_id).map((item) => ({
                    product_id: Number(item.product_id),
                    quantity: Number(item.quantity || 0),
                    item_type: item.item_type || 'sale',
                })),
            };
            const { data } = await window.axios.post(apiBase('mobileOrders'), payload);
            resetForm();
            setMode('list');
            setRefreshKey((key) => key + 1);
            onViewOrder?.(data.data.order.id);
        } catch (error) {
            setState((current) => ({ ...current, error: requestMessage(error, locale) }));
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="mobile-master-stack mobile-order-workspace">
            <div className="mobile-master-heading">
                <div>
                    <p className="eyebrow">{t(locale, 'orders')}</p>
                    <h1>{mode === 'form' ? t(locale, 'newOrder') : t(locale, 'orderHistory')}</h1>
                    <span className="muted">{meta.app === 'sales' ? t(locale, 'customers') : t(locale, 'mobileOrdersHint')}</span>
                </div>
                <button className="icon-button primary-icon" type="button" aria-label={mode === 'form' ? t(locale, 'cancel') : t(locale, 'newOrder')} onClick={() => setMode((current) => current === 'form' ? 'list' : 'form')}>{mode === 'form' ? <X size={18} /> : <Plus size={18} />}</button>
            </div>

            <section className="mobile-order-summary">
                <div><small>{t(locale, 'orders')}</small><strong>{Number(state.summary.orders_count || 0)}</strong></div>
                <div><small>{t(locale, 'pending')}</small><strong>{Number(state.summary.pending_count || 0)}</strong></div>
                <div><small>{t(locale, 'totalAmount')}</small><strong>{money(state.summary.total_amount)}</strong></div>
            </section>

            {(state.error || meta.error) && <div className="inline-error"><AlertCircle size={15} /> {state.error || meta.error}</div>}

            {mode === 'form' ? (
                <form className="mobile-order-form" onSubmit={submit}>
                    {meta.app === 'sales' && (
                        <div className="mobile-order-customer-field"><span>{t(locale, 'customer')}</span><OrderCustomerCombobox customers={meta.customers} value={form.customer_id} locale={locale} onChange={(customerId) => setForm((current) => ({ ...current, customer_id: customerId }))} /></div>
                    )}
                    {selectedCustomer && <MobileCreditCard customer={selectedCustomer} previewTotal={preview.total} locale={locale} />}
                    <div className="mobile-order-lines">
                        {form.items.map((item, index) => (
                            <div className="mobile-order-line" key={index}>
                                <label>{t(locale, 'product')}<select required value={item.product_id} onChange={(event) => updateItem(index, { product_id: event.target.value })}><option value="">{t(locale, 'product')}</option>{meta.products.map((product) => <option value={product.id} key={product.id}>{product.label}</option>)}</select></label>
                                <div className="mobile-order-line-grid">
                                    <label>{t(locale, 'quantity')}<input required type="number" min="0.01" step="0.01" value={item.quantity} onChange={(event) => updateItem(index, { quantity: event.target.value })} /></label>
                                    {meta.app === 'sales' && <label>{t(locale, 'itemType')}<select value={item.item_type} onChange={(event) => updateItem(index, { item_type: event.target.value })}><option value="sale">{t(locale, 'sale')}</option><option value="foc">{t(locale, 'foc')}</option></select></label>}
                                </div>
                                {form.items.length > 1 && <button className="button danger" type="button" onClick={() => setForm((current) => ({ ...current, items: current.items.filter((_, itemIndex) => itemIndex !== index) }))}><Trash2 size={15} /> {t(locale, 'remove')}</button>}
                            </div>
                        ))}
                    </div>
                    <button className="button" type="button" onClick={() => setForm((current) => ({ ...current, items: [...current.items, { product_id: '', quantity: 1, item_type: 'sale' }] }))}><Plus size={15} /> {t(locale, 'addItem')}</button>
                    <button className="button mobile-order-more" type="button" aria-expanded={showDetails} onClick={() => setShowDetails((value) => !value)}>{showDetails ? '−' : '+'} {locale === 'my' ? 'ပို့ဆောင်ရက်၊ ငွေပေးချေမှုနှင့် မှတ်ချက်' : 'Delivery date, payment & notes'}</button>
                    {showDetails && <div className="mobile-order-optional"><label>{t(locale, 'requestedDelivery')}<input type="date" value={form.requested_delivery_date} onChange={(event) => setForm((current) => ({ ...current, requested_delivery_date: event.target.value }))} /></label><label>{t(locale, 'paymentType')}<select required value={form.payment_type} onChange={(event) => setForm((current) => ({ ...current, payment_type: event.target.value }))}><option value="credit">{t(locale, 'credit')}</option><option value="cash">{t(locale, 'cash')}</option></select></label><label>{t(locale, 'notes')}<textarea rows="3" value={form.notes} onChange={(event) => setForm((current) => ({ ...current, notes: event.target.value }))} /></label></div>}
                    <div className="mobile-order-preview"><span>{t(locale, 'total')}</span><strong>{money(preview.total)}</strong></div>
                    <button className="button primary" type="submit" disabled={saving || meta.loading}><Save size={16} /> {t(locale, 'saveMobileOrder')}</button>
                </form>
            ) : (
                <section className="mobile-master-section">
                    <div className="mobile-order-filters">
                        <label className="mobile-search"><Search size={16} /><input value={filters.search} placeholder={t(locale, 'searchOrders')} onChange={(event) => setFilters((current) => ({ ...current, search: event.target.value }))} /></label>
                        <select aria-label={t(locale, 'status')} value={filters.status} onChange={(event) => setFilters((current) => ({ ...current, status: event.target.value }))}><option value="">{t(locale, 'allStatuses')}</option>{['pending', 'confirmed', 'invoiced', 'assigned', 'delivering', 'delivered', 'cancelled'].map((status) => <option value={status} key={status}>{t(locale, status)}</option>)}</select>
                    </div>
                    {state.loading ? <WorkspaceState icon={RefreshCw} title={t(locale, 'loading')} loading compact /> : state.items.length === 0 ? <WorkspaceState icon={ShoppingCart} title={t(locale, 'empty')} compact /> : (
                        <div className="mobile-order-list">
                            {state.items.map((order) => (
                                <button type="button" key={order.id} onClick={() => onViewOrder?.(order.id)}>
                                    <span className="mobile-order-icon"><ShoppingCart size={17} /></span>
                                    <span><strong>{order.code} · {money(order.total)}</strong><small>{order.shop_name}</small><small>{order.order_date} · {t(locale, order.payment_type)}</small></span>
                                    <StatusBadge status={order.status} locale={locale} />
                                </button>
                            ))}
                        </div>
                    )}
                </section>
            )}

        </div>
    );
}

function MobileCreditCard({ customer, previewTotal = 0, locale }) {
    if (!customer) return null;
    const availableCredit = customer.available_credit === null || customer.available_credit === undefined ? null : Number(customer.available_credit);
    const afterOrderCredit = availableCredit === null ? null : availableCredit - Number(previewTotal || 0);
    const status = afterOrderCredit !== null && afterOrderCredit < 0 ? 'overLimit' : 'creditOk';

    return (
        <section className={`mobile-credit-card ${status === 'overLimit' ? 'is-danger' : ''}`}>
            <div>
                <small>{t(locale, 'outstandingBalance')}</small>
                <strong>{money(customer.outstanding_balance)}</strong>
            </div>
            <div>
                <small>{t(locale, 'availableCredit')}</small>
                <strong>{availableCredit === null ? '-' : money(availableCredit)}</strong>
            </div>
            {previewTotal > 0 && (
                <div>
                    <small>{t(locale, 'afterThisOrder')}</small>
                    <strong>{afterOrderCredit === null ? '-' : money(afterOrderCredit)}</strong>
                </div>
            )}
            <span className={`status ${status === 'overLimit' ? 'danger' : 'success'}`}>{t(locale, status)}</span>
        </section>
    );
}

function MobileOrderDetailSheet({ viewing, locale, onClose }) {
    const [state, setState] = useState(() => viewing.items ? { loading: false, order: viewing.order, items: viewing.items, error: '' } : { loading: true, order: viewing.order, items: [], error: '' });

    useEffect(() => {
        if (viewing.items) return undefined;
        let mounted = true;
        window.axios.get(`${apiBase('mobileOrders')}/${viewing.order.id}`)
            .then(({ data }) => mounted && setState({ loading: false, order: data.data.order, items: data.data.items, error: '' }))
            .catch((error) => mounted && setState((current) => ({ ...current, loading: false, error: requestMessage(error, locale) })));
        return () => { mounted = false; };
    }, [locale, viewing]);

    return (
        <div className="drawer-backdrop mobile" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
            <aside className="mobile-detail-sheet mobile-order-sheet" role="dialog" aria-modal="true">
                <header><div><p className="eyebrow">{t(locale, 'details')}</p><h2>{state.order.code}</h2></div><button className="icon-button" type="button" onClick={onClose} aria-label={t(locale, 'cancel')}><X size={17} /></button></header>
                {state.error ? <WorkspaceState icon={AlertCircle} title={state.error} compact /> : state.loading ? <WorkspaceState icon={RefreshCw} title={t(locale, 'loading')} loading compact /> : (
                    <>
                        <section className="mobile-order-detail-total"><small>{state.order.shop_name}</small><strong>{money(state.order.total)}</strong><span>{state.order.order_date} · <StatusBadge status={state.order.status} locale={locale} /></span></section>
                        <MobileOrderTimeline order={state.order} locale={locale} />
                        <div className="mobile-order-detail-items">{state.items.map((item) => <div key={item.id}><span><strong>{item.product_name}</strong><small>{item.product_sku} · {item.unit}</small></span><span>{Number(item.quantity).toLocaleString()} × {money(item.unit_price)}</span></div>)}</div>
                        <dl className="mobile-info-list">
                            <div><dt>{t(locale, 'requestedDelivery')}</dt><dd>{state.order.requested_delivery_date || '-'}</dd></div>
                            <div><dt>{t(locale, 'paymentType')}</dt><dd>{t(locale, state.order.payment_type)}</dd></div>
                            <div><dt>{t(locale, 'invoice')}</dt><dd>{state.order.invoice_code || '-'}</dd></div>
                            <div><dt>{t(locale, 'notes')}</dt><dd>{state.order.notes || '-'}</dd></div>
                        </dl>
                    </>
                )}
            </aside>
        </div>
    );
}

export function MobileOrderDetailPage({ orderId, locale, onBack }) {
    const [state, setState] = useState({ loading: true, order: null, items: [], error: '' });

    useEffect(() => {
        let mounted = true;
        setState({ loading: true, order: null, items: [], error: '' });
        window.axios.get(`${apiBase('mobileOrders')}/${orderId}`)
            .then(({ data }) => mounted && setState({ loading: false, order: data.data.order, items: data.data.items, error: '' }))
            .catch((error) => mounted && setState({ loading: false, order: null, items: [], error: requestMessage(error, locale) }));
        return () => { mounted = false; };
    }, [locale, orderId]);

    return (
        <div className="mobile-master-stack mobile-order-detail-page">
            <div className="mobile-master-heading">
                <button className="icon-button" type="button" onClick={onBack} aria-label={t(locale, 'cancel')}><ArrowLeft size={18} /></button>
                <div><p className="eyebrow">{t(locale, 'details')}</p><h1>{state.order?.code || t(locale, 'order')}</h1></div>
            </div>
            <section className="mobile-master-section mobile-order-detail-content">
                {state.error ? <WorkspaceState icon={AlertCircle} title={state.error} action={onBack} actionLabel={t(locale, 'cancel')} compact /> : state.loading ? <WorkspaceState icon={RefreshCw} title={t(locale, 'loading')} loading compact /> : (
                    <div>
                        <section className="mobile-order-detail-total"><small>{state.order.shop_name}</small><strong>{money(state.order.total)}</strong><span>{state.order.order_date} · <StatusBadge status={state.order.status} locale={locale} /></span></section>
                        <MobileOrderTimeline order={state.order} locale={locale} />
                        <div className="mobile-order-detail-items">{state.items.map((item) => <div key={item.id}><span><strong>{item.product_name}</strong><small>{item.product_sku} · {item.unit}</small></span><span>{Number(item.quantity).toLocaleString()} × {money(item.unit_price)}</span></div>)}</div>
                        <dl className="mobile-info-list">
                            <div><dt>{t(locale, 'requestedDelivery')}</dt><dd>{state.order.requested_delivery_date || '-'}</dd></div>
                            <div><dt>{t(locale, 'paymentType')}</dt><dd>{t(locale, state.order.payment_type)}</dd></div>
                            <div><dt>{t(locale, 'invoice')}</dt><dd>{state.order.invoice_code || '-'}</dd></div>
                            <div><dt>{t(locale, 'notes')}</dt><dd>{state.order.notes || '-'}</dd></div>
                        </dl>
                    </div>
                )}
            </section>
        </div>
    );
}

function MobileOrderTimeline({ order, locale }) {
    const steps = [
        { key: 'pending', label: t(locale, 'submitted'), meta: order.order_date },
        { key: 'confirmed', label: t(locale, 'confirmed'), meta: order.confirmed_at ? formatDateTime(order.confirmed_at) : null },
        { key: 'invoiced', label: t(locale, 'invoiced'), meta: order.invoice_code ? `${order.invoice_code} · ${order.invoice_date || '-'}` : null },
        { key: 'assigned', label: t(locale, 'assigned'), meta: t(locale, 'delivery') },
        { key: 'delivering', label: t(locale, 'delivering'), meta: t(locale, 'delivery') },
        { key: 'delivered', label: t(locale, 'delivered'), meta: t(locale, 'delivery') },
    ];
    const currentIndex = steps.findIndex((step) => step.key === order.status);
    const safeCurrentIndex = currentIndex >= 0 ? currentIndex : 0;

    return (
        <section className="mobile-order-timeline" aria-label={t(locale, 'statusTimeline')}>
            <div><strong>{t(locale, 'statusTimeline')}</strong><StatusBadge status={order.status} locale={locale} /></div>
            <ol>
                {steps.map((step, index) => {
                    const state = order.status === 'cancelled'
                        ? (index === 0 ? 'done' : 'pending')
                        : index < safeCurrentIndex ? 'done' : index === safeCurrentIndex ? 'current' : 'pending';
                    return (
                        <li className={state} key={step.key}>
                            <span />
                            <div>
                                <strong>{step.label}</strong>
                                <small>{step.meta || (state === 'current' ? t(locale, 'current') : t(locale, 'waiting'))}</small>
                            </div>
                        </li>
                    );
                })}
            </ol>
        </section>
    );
}

export function SalesReturnScreen({ locale, canManage = false }) {
    return (
        <AdjustmentScreen
            type="sales_return"
            title={t(locale, 'salesReturns')}
            hint={t(locale, 'returnsHint')}
            icon={RotateCcw}
            actionLabel={t(locale, 'newReturn')}
            locale={locale}
            canManage={canManage}
        />
    );
}

export function DamageEntryScreen({ locale, canManage = false }) {
    return (
        <AdjustmentScreen
            type="damage"
            title={t(locale, 'damageEntries')}
            hint={t(locale, 'damageHint')}
            icon={TriangleAlert}
            actionLabel={t(locale, 'newDamage')}
            locale={locale}
            canManage={canManage}
        />
    );
}

function AdjustmentScreen({ type, title, hint, icon: Icon, actionLabel, locale, canManage }) {
    const today = new Date().toISOString().slice(0, 10);
    const blankAdjustmentItem = { product_id: '', quantity: 1, unit_price: '', remarks: '' };
    const [filters, setFilters] = useState({ search: '', date: '', customer_id: '' });
    const [meta, setMeta] = useState({ loading: true, customers: [], products: [], error: '' });
    const [state, setState] = useState({ loading: true, items: [], summary: {}, pageMeta: {}, error: '' });
    const [page, setPage] = useState(1);
    const [refreshKey, setRefreshKey] = useState(0);
    const [formOpen, setFormOpen] = useState(false);
    const [saving, setSaving] = useState(false);
    const [viewing, setViewing] = useState(null);
    const [form, setForm] = useState(() => ({
        customer_id: '',
        entry_date: today,
        payment_type: 'credit',
        notes: '',
        items: [{ ...blankAdjustmentItem }],
    }));

    useEffect(() => {
        let mounted = true;
        window.axios.get(`${apiBase('orderAdjustments')}/meta`)
            .then(({ data }) => mounted && setMeta({ loading: false, customers: data.data.customers, products: data.data.products, error: '' }))
            .catch((error) => mounted && setMeta({ loading: false, customers: [], products: [], error: requestMessage(error, locale) }));
        return () => { mounted = false; };
    }, [locale]);

    useEffect(() => {
        let mounted = true;
        setState((current) => ({ ...current, loading: true, error: '' }));
        const timer = window.setTimeout(() => {
            window.axios.get(apiBase('orderAdjustments'), {
                params: {
                    type,
                    search: filters.search || undefined,
                    date: filters.date || undefined,
                    customer_id: filters.customer_id || undefined,
                    page,
                    per_page: 20,
                },
            }).then(({ data }) => {
                if (!mounted) return;
                setState({ loading: false, items: data.data.items, summary: data.data.summary, pageMeta: data.data.meta, error: '' });
            }).catch((error) => mounted && setState({ loading: false, items: [], summary: {}, pageMeta: {}, error: requestMessage(error, locale) }));
        }, 220);

        return () => { mounted = false; window.clearTimeout(timer); };
    }, [filters, locale, page, refreshKey, type]);

    useEffect(() => {
        setPage(1);
    }, [filters.search, filters.date, filters.customer_id]);

    const selectedCustomer = useMemo(() => meta.customers.find((customer) => Number(customer.id) === Number(form.customer_id)), [form.customer_id, meta.customers]);
    const preview = useMemo(() => adjustmentPreview(form.items), [form.items]);

    const openForm = () => {
        setForm({
            customer_id: meta.customers[0]?.id || '',
            entry_date: today,
            payment_type: 'credit',
            notes: '',
            items: [{ ...blankAdjustmentItem }],
        });
        setFormOpen(true);
    };

    const closeForm = () => {
        setFormOpen(false);
        setSaving(false);
    };

    const updateItem = (index, patch) => {
        setForm((current) => ({ ...current, items: current.items.map((item, itemIndex) => itemIndex === index ? { ...item, ...patch } : item) }));
    };

    const handleProductChange = (index, productId) => {
        const product = meta.products.find((item) => Number(item.id) === Number(productId));
        updateItem(index, { product_id: productId, unit_price: product ? priceForProduct(product, selectedCustomer?.price_type_id) : '' });
    };

    const submit = async (event) => {
        event.preventDefault();
        if (!canManage) return;
        setSaving(true);
        setState((current) => ({ ...current, error: '' }));

        try {
            const payload = {
                type,
                customer_id: Number(form.customer_id),
                entry_date: form.entry_date,
                payment_type: form.payment_type,
                notes: form.notes || null,
                items: form.items
                    .filter((item) => item.product_id)
                    .map((item) => ({
                        product_id: Number(item.product_id),
                        quantity: Number(item.quantity || 0),
                        unit_price: Number(item.unit_price || 0),
                        remarks: item.remarks || null,
                    })),
            };
            const { data } = await window.axios.post(apiBase('orderAdjustments'), payload);
            setViewing({ record: data.data.record, items: data.data.items });
            closeForm();
            setRefreshKey((key) => key + 1);
        } catch (error) {
            setState((current) => ({ ...current, error: requestMessage(error, locale) }));
            setSaving(false);
        }
    };

    return (
        <section className="master-workspace adjustment-workspace">
            <div className="master-heading">
                <div>
                    <p className="eyebrow">{t(locale, type)}</p>
                    <h1>{title}</h1>
                    <span className="muted">{hint}</span>
                </div>
                {canManage && <button className="button primary" type="button" disabled={meta.loading} onClick={openForm}><Plus size={16} /> {actionLabel}</button>}
            </div>

            <div className="metrics adjustment-metrics">
                <div className="metric"><span>{t(locale, 'records')}</span><strong>{Number(state.summary.records_count || 0)}</strong><small>{title}</small></div>
                <div className="metric"><span>{t(locale, 'totalQuantity')}</span><strong>{Number(state.summary.total_quantity || 0).toLocaleString()}</strong><small>{t(locale, 'items')}</small></div>
                <div className="metric"><span>{t(locale, 'value')}</span><strong>{money(state.summary.total_amount)}</strong><small>{t(locale, type)}</small></div>
            </div>

            <div className="master-panel">
                <div className="master-toolbar adjustment-toolbar">
                    <label className="master-search"><Search size={14} /><input value={filters.search} placeholder={t(locale, 'searchAdjustments')} onChange={(event) => setFilters((current) => ({ ...current, search: event.target.value }))} /></label>
                    <select aria-label={t(locale, 'customer')} value={filters.customer_id} onChange={(event) => setFilters((current) => ({ ...current, customer_id: event.target.value }))}>
                        <option value="">{t(locale, 'allCustomers')}</option>
                        {meta.customers.map((customer) => <option value={customer.id} key={customer.id}>{customer.label}</option>)}
                    </select>
                    <label className="date-filter" title={t(locale, 'entryDate')}><CalendarDays size={14} /><input type="date" value={filters.date} onChange={(event) => setFilters((current) => ({ ...current, date: event.target.value }))} /></label>
                    <button className="icon-button" type="button" aria-label={t(locale, 'refresh')} title={t(locale, 'refresh')} onClick={() => setRefreshKey((key) => key + 1)}><RefreshCw size={15} /></button>
                </div>

                {(state.error || meta.error) && <div className="inline-error"><AlertCircle size={15} /> {state.error || meta.error}</div>}
                {state.loading ? <WorkspaceState icon={RefreshCw} title={t(locale, 'loading')} loading compact /> : state.items.length === 0 ? <WorkspaceState icon={Icon} title={t(locale, 'empty')} compact /> : (
                    <div className="master-table-wrap">
                        <table className="master-table adjustment-table">
                            <thead><tr><th>{t(locale, 'adjustmentRecord')}</th><th>{t(locale, 'customer')}</th><th>{t(locale, 'entryDate')}</th><th>{t(locale, 'quantity')}</th><th>{t(locale, 'value')}</th><th>{t(locale, 'status')}</th><th>{t(locale, 'actions')}</th></tr></thead>
                            <tbody>
                                {state.items.map((record) => (
                                    <tr key={record.id}>
                                        <td><strong>{record.code}</strong><span className="muted">{t(locale, 'updated')}: {formatDateTime(record.updated_at)}</span></td>
                                        <td><strong>{record.shop_name}</strong><span className="muted">{record.customer_code} · {record.route || '-'}</span></td>
                                        <td>{record.entry_date}</td>
                                        <td className="numeric">{Number(record.quantity_total || 0).toLocaleString()}</td>
                                        <td className="numeric">{money(record.total)}</td>
                                        <td><StatusBadge status={record.status} locale={locale} /></td>
                                        <td className="row-actions"><button type="button" aria-label={t(locale, 'details')} title={t(locale, 'details')} onClick={() => setViewing({ record, items: null })}><Eye size={15} /></button></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination meta={state.pageMeta} page={page} setPage={setPage} />
            </div>

            {formOpen && (
                <div className="modal-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && closeForm()}>
                    <form className="master-dialog adjustment-dialog" role="dialog" aria-modal="true" aria-label={title} onSubmit={submit}>
                        <header><div><p className="eyebrow">{actionLabel}</p><h2>{title}</h2></div><button className="icon-button" type="button" aria-label={t(locale, 'cancel')} onClick={closeForm}><X size={17} /></button></header>
                        <div className="master-form-body">
                            <div className="master-form-grid">
                                <label className="master-field wide"><span>{t(locale, 'customer')} <b>*</b></span><select required value={form.customer_id} onChange={(event) => setForm((current) => ({ ...current, customer_id: event.target.value, items: current.items.map((item) => ({ ...item, unit_price: item.product_id ? priceForProduct(meta.products.find((product) => Number(product.id) === Number(item.product_id)), meta.customers.find((customer) => Number(customer.id) === Number(event.target.value))?.price_type_id) : item.unit_price })) }))}><option value="">{t(locale, 'customer')}</option>{meta.customers.map((customer) => <option value={customer.id} key={customer.id}>{customer.label}</option>)}</select></label>
                                <label className="master-field"><span>{t(locale, 'entryDate')} <b>*</b></span><input required type="date" value={form.entry_date} onChange={(event) => setForm((current) => ({ ...current, entry_date: event.target.value }))} /></label>
                                <label className="master-field"><span>{t(locale, 'paymentType')}</span><select value={form.payment_type} onChange={(event) => setForm((current) => ({ ...current, payment_type: event.target.value }))}><option value="credit">{t(locale, 'credit')}</option><option value="cash">{t(locale, 'cash')}</option></select></label>
                                <label className="master-field wide"><span>{t(locale, 'notes')}</span><textarea rows="2" maxLength="500" value={form.notes} onChange={(event) => setForm((current) => ({ ...current, notes: event.target.value }))} /></label>
                            </div>

                            <section className="order-form-items adjustment-form-items">
                                <div className="order-form-items-heading"><strong>{t(locale, 'items')}</strong><button className="button" type="button" onClick={() => setForm((current) => ({ ...current, items: [...current.items, { ...blankAdjustmentItem }] }))}><Plus size={14} /> {t(locale, 'addItem')}</button></div>
                                {form.items.map((item, index) => (
                                    <div className="adjustment-item-grid" key={index}>
                                        <label className="master-field product-field"><span>{t(locale, 'product')} <b>*</b></span><select required value={item.product_id} onChange={(event) => handleProductChange(index, event.target.value)}><option value="">{t(locale, 'product')}</option>{meta.products.map((productOption) => <option value={productOption.id} key={productOption.id}>{productOption.label}</option>)}</select></label>
                                        <label className="master-field"><span>{t(locale, 'quantity')}</span><input required type="number" min="0.01" step="0.01" value={item.quantity} onChange={(event) => updateItem(index, { quantity: event.target.value })} /></label>
                                        <label className="master-field"><span>{t(locale, 'price')}</span><input required type="number" min="0" step="1" value={item.unit_price} onChange={(event) => updateItem(index, { unit_price: event.target.value })} /></label>
                                        <label className="master-field"><span>{t(locale, 'reason')}</span><input maxLength="150" value={item.remarks} onChange={(event) => updateItem(index, { remarks: event.target.value })} /></label>
                                        <button className="icon-button danger adjustment-line-remove" type="button" aria-label={t(locale, 'cancel')} title={t(locale, 'cancel')} disabled={form.items.length === 1} onClick={() => setForm((current) => ({ ...current, items: current.items.filter((_, itemIndex) => itemIndex !== index) }))}><Trash2 size={15} /></button>
                                    </div>
                                ))}
                                <div className="order-form-summary"><span>{t(locale, 'totalQuantity')}: <strong>{Number(preview.quantity).toLocaleString()}</strong></span><span>{t(locale, 'value')}: <strong>{money(preview.total)}</strong></span></div>
                            </section>
                        </div>
                        <footer><button className="button" type="button" onClick={closeForm}>{t(locale, 'cancel')}</button><button className="button primary" type="submit" disabled={saving || meta.loading}><Save size={15} /> {t(locale, 'save')}</button></footer>
                    </form>
                </div>
            )}

            {viewing && <AdjustmentDrawer viewing={viewing} type={type} title={title} icon={Icon} locale={locale} onClose={() => setViewing(null)} />}
        </section>
    );
}

function AdjustmentDrawer({ viewing, type, title, icon: Icon, locale, onClose }) {
    const [state, setState] = useState(() => viewing.items ? { loading: false, record: viewing.record, items: viewing.items, error: '' } : { loading: true, record: viewing.record, items: [], error: '' });

    useEffect(() => {
        if (viewing.items) return undefined;
        let mounted = true;
        window.axios.get(`${apiBase('orderAdjustments')}/${viewing.record.id}`, { params: { type } })
            .then(({ data }) => mounted && setState({ loading: false, record: data.data.record, items: data.data.items, error: '' }))
            .catch((error) => mounted && setState((current) => ({ ...current, loading: false, error: requestMessage(error, locale) })));
        return () => { mounted = false; };
    }, [locale, type, viewing]);

    return (
        <div className="drawer-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
            <aside className="record-drawer adjustment-drawer" role="dialog" aria-modal="true" aria-label={t(locale, 'details')}>
                <header><div><p className="eyebrow">{title}</p><h2>{state.record.code}</h2></div><button className="icon-button" type="button" aria-label={t(locale, 'cancel')} onClick={onClose}><X size={17} /></button></header>
                {state.error && <div className="inline-error"><AlertCircle size={15} /> {state.error}</div>}
                {state.loading ? <WorkspaceState icon={RefreshCw} title={t(locale, 'loading')} loading compact /> : (
                    <div className="adjustment-drawer-body">
                        <section className="order-total-card adjustment-total-card">
                            <Icon size={18} />
                            <div><small>{state.record.shop_name}</small><strong>{money(state.record.total)}</strong><span>{state.record.entry_date} · {t(locale, state.record.payment_type)} · <StatusBadge status={state.record.status} locale={locale} /></span></div>
                        </section>
                        <dl>
                            <div><dt>{t(locale, 'customer')}</dt><dd>{state.record.customer_code} · {state.record.shop_name}</dd></div>
                            <div><dt>{t(locale, 'route')}</dt><dd>{state.record.route || '-'}</dd></div>
                            <div><dt>{t(locale, 'totalQuantity')}</dt><dd>{Number(state.record.quantity_total || 0).toLocaleString()}</dd></div>
                            <div><dt>{t(locale, 'notes')}</dt><dd>{state.record.notes || '-'}</dd></div>
                        </dl>
                        <div className="master-table-wrap">
                            <table className="master-table order-item-table">
                                <thead><tr><th>{t(locale, 'product')}</th><th>{t(locale, 'quantity')}</th><th>{t(locale, 'price')}</th><th>{t(locale, 'total')}</th><th>{t(locale, 'reason')}</th></tr></thead>
                                <tbody>{state.items.map((item) => <tr key={item.id}><td><strong>{item.product_name}</strong><span className="muted">{item.product_sku} · {item.unit}</span></td><td className="numeric">{Number(item.quantity).toLocaleString()}</td><td className="numeric">{money(item.unit_price)}</td><td className="numeric">{money(item.line_total)}</td><td>{item.remarks || '-'}</td></tr>)}</tbody>
                            </table>
                        </div>
                    </div>
                )}
            </aside>
        </div>
    );
}

function WorkspaceState({ icon: Icon, title, action, actionLabel, loading = false, compact = false }) {
    return <div className={`workspace-state ${compact ? 'compact' : ''}`}><Icon className={loading ? 'spin' : ''} size={22} /><strong>{title}</strong>{action && <button className="button" type="button" onClick={action}>{actionLabel}</button>}</div>;
}

function StatusBadge({ status, locale }) {
    const family = ['confirmed', 'delivered', 'issued'].includes(status) ? 'success' : ['pending', 'draft'].includes(status) ? 'warning' : ['cancelled'].includes(status) ? 'danger' : ['invoiced', 'assigned', 'delivering'].includes(status) ? 'info' : 'neutral';
    return <span className={`status ${family}`}>{t(locale, status)}</span>;
}

function StatusTabs({ label, locale, statuses, value, onChange }) {
    return (
        <div className="status-tabs segmented" role="tablist" aria-label={label}>
            {statuses.map((status) => {
                const selected = value === status;
                return (
                    <button
                        type="button"
                        role="tab"
                        aria-selected={selected}
                        className={selected ? 'is-selected' : ''}
                        key={status || 'all'}
                        onClick={() => onChange(status)}
                    >
                        {status ? t(locale, status) : t(locale, 'allStatuses')}
                    </button>
                );
            })}
        </div>
    );
}

function Pagination({ meta = {}, page, setPage }) {
    if (!meta.last_page || meta.last_page <= 1) return null;
    return <div className="master-pagination"><span>Page {meta.current_page} of {meta.last_page} · {meta.total}</span><div><button type="button" disabled={page <= 1} onClick={() => setPage((value) => value - 1)}>Previous</button><button type="button" disabled={page >= meta.last_page} onClick={() => setPage((value) => value + 1)}>Next</button></div></div>;
}

function priceForProduct(product, priceTypeId) {
    if (!product) return '';
    const price = product.prices?.find((item) => Number(item.price_type_id) === Number(priceTypeId)) || product.prices?.[0];
    return price ? Number(price.amount) : '';
}

function calculatePreview(items) {
    return items.reduce((carry, item) => {
        const quantity = Number(item.quantity || 0);
        const price = item.item_type === 'foc' ? 0 : Number(item.unit_price || 0);
        const discount = Number(item.discount_amount || 0);
        const subtotal = quantity * price;

        return {
            subtotal: carry.subtotal + subtotal,
            discount: carry.discount + discount,
            total: carry.total + Math.max(subtotal - discount, 0),
        };
    }, { subtotal: 0, discount: 0, total: 0 });
}

function calculateMobilePreview(items, products, priceTypeId) {
    return items.reduce((carry, item) => {
        const product = products.find((product) => Number(product.id) === Number(item.product_id));
        const price = item.item_type === 'foc' ? 0 : Number(priceForProduct(product, priceTypeId) || 0);
        const subtotal = Number(item.quantity || 0) * price;

        return { total: carry.total + subtotal };
    }, { total: 0 });
}

function adjustmentPreview(items) {
    return items.reduce((carry, item) => {
        const quantity = Number(item.quantity || 0);
        const lineTotal = quantity * Number(item.unit_price || 0);

        return {
            quantity: carry.quantity + quantity,
            total: carry.total + lineTotal,
        };
    }, { quantity: 0, total: 0 });
}

function money(value) {
    return `${Number(value || 0).toLocaleString()} MMK`;
}

function formatDateTime(value) {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return date.toLocaleString([], { year: 'numeric', month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit' });
}
