import { AlertCircle, BadgeCheck, Ban, CalendarDays, Check, Copy, FileText, MapPinned, Package, Pencil, Plus, Printer, RefreshCw, RotateCcw, Save, Search, ShoppingCart, Trash2, TriangleAlert, X } from 'lucide-react';
import { useEffect, useId, useMemo, useRef, useState } from 'react';
import { DetailPage, DetailPanel } from './components/DetailPage';
import { ShellBackButton } from './components/ShellBackButton';
import { ShellPageActions } from './components/ShellPageActions';
import { printConfiguredDocument } from './printDocuments';

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
        confirmCancellation: 'Yes, cancel order',
        keepOrder: 'Keep order',
        orderCancelled: 'Order cancelled successfully.',
        createInvoice: 'Create invoice',
        createInvoicePrompt: 'Create a draft invoice from this confirmed order?',
        credit: 'Credit',
        availableCredit: 'Available credit',
        customer: 'Customer',
        optionalCustomer: 'Registered customer (optional)',
        guestCustomer: 'Non-registered / walk-in customer',
        recipientName: 'Recipient or shop name',
        recipientPhone: 'Phone (optional)',
        area: 'Area',
        address: 'Delivery address',
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
        editOrder: 'Edit order',
        repeatOrder: 'Repeat order',
        printOrder: 'Print / PDF',
        saveDraft: 'Save draft',
        updateOrder: 'Update order',
        availableStock: 'Available stock',
        unitPrice: 'Unit price',
        orderDate: 'Order date',
        order: 'Order',
        orders: 'Orders',
        ordersHint: 'Create, confirm, and prepare customer orders for delivery.',
        mobileOrdersHint: 'Choose products and quantities. Delivery details are optional.',
        paymentType: 'Payment',
        pending: 'Pending',
        unsettled: 'Set at delivery',
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
        searchOrders: 'Search order, recipient, area, route or address',
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
        invoiced: 'Ready',
        assigned: 'Assigned',
        delivering: 'Delivering',
        delivered: 'Delivered',
        loadingDelivery: 'Loading',
        on_route: 'On route',
        partially_delivered: 'Partially delivered',
        failed: 'Delivery failed',
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

const blankItem = { product_id: '', quantity: 1, foc_quantity: 0, unit_price: '', discount_amount: 0, item_type: 'sale', remarks: '' };
const editableOrderStatuses = ['pending', 'confirmed', 'invoiced'];

function orderItemsForEditing(items = []) {
    return Object.values(items.reduce((rows, item) => {
        const key = String(item.product_id);
        if (!rows[key]) rows[key] = {
            ...blankItem,
            product_id: item.product_id,
            quantity: 0,
            foc_quantity: 0,
            unit_price: item.unit_price,
            discount_amount: 0,
            remarks: item.remarks || '',
        };
        if (item.item_type === 'foc') {
            rows[key].foc_quantity += Number(item.quantity || 0);
        } else {
            rows[key].quantity += Number(item.quantity || 0);
            rows[key].unit_price = Number(item.unit_price || 0);
            rows[key].discount_amount += Number(item.discount_amount || 0);
        }
        return rows;
    }, {}));
}
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
    return customer?.price_type_id || priceTypes.find((item) => item.is_default)?.id || priceTypes[0]?.id || '';
}

function repriceItems(items, products, priceTypeId) {
    return items.map((item) => {
        const product = products.find((product) => Number(product.id) === Number(item.product_id));
        if (!product) return item;

        return {
            ...item,
            unit_price: priceForProduct(product, priceTypeId),
        };
    });
}

function datePlusDays(date, days) {
    const [year, month, day] = String(date).split('-').map(Number);
    const value = new Date(Date.UTC(year, month - 1, day));
    value.setUTCDate(value.getUTCDate() + days);
    return value.toISOString().slice(0, 10);
}

export function OrdersScreen({ locale, canManage = false, creating = false, editId = null, detailId = null, onNavigate }) {
    const today = new Date().toISOString().slice(0, 10);
    const defaultCreditDueDate = datePlusDays(today, 7);
    const emptyFilters = { search: '', status: '', customer_scope: '', customer_id: '', area_id: '', route_id: '', price_type_id: '', payment_type: '', date_from: '', date_to: '', delivery_from: '', delivery_to: '', min_total: '', max_total: '', driver_modified: '' };
    const [filters, setFilters] = useState(emptyFilters);
    const [meta, setMeta] = useState({ loading: true, customers: [], products: [], price_types: [], areas: [], routes: [], error: '' });
    const [state, setState] = useState({ loading: true, items: [], summary: {}, pageMeta: {}, error: '' });
    const [page, setPage] = useState(1);
    const [refreshKey, setRefreshKey] = useState(0);
    const [formOpen, setFormOpen] = useState(false);
    const [wizardStep, setWizardStep] = useState(0);
    const [productSearch, setProductSearch] = useState('');
    const [saving, setSaving] = useState(false);
    const [processingId, setProcessingId] = useState(null);
    const [cancelCandidate, setCancelCandidate] = useState(null);
    const [actionMessage, setActionMessage] = useState('');
    const [viewing, setViewing] = useState(null);
    const orderListPath = `${window.ValleyRuntime?.routes?.office || '/office'}/orders`;
    const editing = Boolean(editId);
    const formMode = creating || editing;
    const [form, setForm] = useState(() => ({
        customer_id: '',
        area_id: '',
        route_id: '',
        recipient_name: '',
        recipient_phone: '',
        delivery_address: '',
        price_type_id: '',
        order_date: today,
        requested_delivery_date: '',
        payment_type: 'credit',
        credit_due_date: defaultCreditDueDate,
        notes: '',
        items: [{ ...blankItem }],
    }));

    useEffect(() => {
        let mounted = true;
        window.axios.get(`${apiBase('orders')}/meta`)
            .then(({ data }) => mounted && setMeta({ loading: false, customers: data.data.customers, products: data.data.products, price_types: data.data.price_types, areas: data.data.areas || [], routes: data.data.routes || [], error: '' }))
            .catch((error) => mounted && setMeta({ loading: false, customers: [], products: [], price_types: [], areas: [], routes: [], error: requestMessage(error, locale) }));
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
                    customer_scope: filters.customer_scope || undefined,
                    customer_id: filters.customer_id || undefined,
                    area_id: filters.area_id || undefined,
                    route_id: filters.route_id || undefined,
                    price_type_id: filters.price_type_id || undefined,
                    payment_type: filters.payment_type || undefined,
                    date_from: filters.date_from || undefined,
                    date_to: filters.date_to || undefined,
                    delivery_from: filters.delivery_from || undefined,
                    delivery_to: filters.delivery_to || undefined,
                    min_total: filters.min_total || undefined,
                    max_total: filters.max_total || undefined,
                    driver_modified: filters.driver_modified || undefined,
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
    }, [filters.search, filters.status, filters.customer_scope, filters.customer_id, filters.area_id, filters.route_id, filters.price_type_id, filters.payment_type, filters.date_from, filters.date_to, filters.delivery_from, filters.delivery_to, filters.min_total, filters.max_total, filters.driver_modified]);

    useEffect(() => {
        setViewing(detailId ? { order: { id: detailId }, items: null } : null);
    }, [detailId]);

    useEffect(() => {
        if (!editId || meta.loading) return undefined;
        let mounted = true;
        setFormOpen(false);
        setState((current) => ({ ...current, error: '' }));
        window.axios.get(`${apiBase('orders')}/${editId}`)
            .then(({ data }) => {
                if (!mounted) return;
                const order = data.data.order;
                const customer = meta.customers.find((item) => Number(item.id) === Number(order.customer_id));
                const routeId = order.route_id || customer?.route_id || '';
                const route = meta.routes.find((item) => Number(item.id) === Number(routeId));
                setForm({
                    customer_id: order.customer_id || '',
                    area_id: order.area_id || customer?.area_id || route?.area_id || '',
                    route_id: routeId,
                    recipient_name: order.recipient_name || order.shop_name || customer?.shop_name || '',
                    recipient_phone: order.recipient_phone || customer?.phone || '',
                    delivery_address: order.delivery_address || customer?.address || '',
                    price_type_id: order.price_type_id || defaultPriceTypeId(customer, meta.price_types),
                    order_date: order.order_date || today,
                    requested_delivery_date: order.requested_delivery_date || '',
                    payment_type: 'unsettled',
                    credit_due_date: '',
                    notes: order.notes || '',
                    items: orderItemsForEditing(data.data.items),
                });
                setWizardStep(0);
                setProductSearch('');
                setFormOpen(true);
            })
            .catch((error) => mounted && setState((current) => ({ ...current, error: requestMessage(error, locale) })));
        return () => { mounted = false; };
    }, [editId, locale, meta.loading]);

    const selectedCustomer = useMemo(() => meta.customers.find((customer) => Number(customer.id) === Number(form.customer_id)), [form.customer_id, meta.customers]);
    const preview = useMemo(() => calculatePreview(form.items), [form.items]);

    const initializeForm = (navigateToPage = true) => {
        const firstArea = meta.areas[0];
        const firstRoute = meta.routes.find((route) => Number(route.area_id) === Number(firstArea?.id));
        const priceTypeId = defaultPriceTypeId(null, meta.price_types);
        setForm({
            customer_id: '',
            area_id: firstArea?.id || '',
            route_id: firstRoute?.id || '',
            recipient_name: '',
            recipient_phone: '',
            delivery_address: '',
            price_type_id: priceTypeId,
            order_date: today,
            requested_delivery_date: '',
            payment_type: 'cash',
            credit_due_date: '',
            notes: '',
            items: [{ ...blankItem }],
        });
        setWizardStep(0);
        setProductSearch('');
        setFormOpen(true);
        if (navigateToPage) onNavigate?.(`${orderListPath}/new`);
    };

    const openForm = () => initializeForm(true);

    const closeForm = () => {
        setFormOpen(false);
        setSaving(false);
        setWizardStep(0);
        onNavigate?.(orderListPath);
    };

    useEffect(() => {
        if (creating && !meta.loading && !formOpen) initializeForm(false);
        if (!formMode && formOpen) setFormOpen(false);
    }, [creating, formMode, meta.loading]);

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
            area_id: customer?.area_id || current.area_id,
            route_id: customer?.route_id || current.route_id,
            recipient_name: customer?.shop_name || '',
            recipient_phone: customer?.phone || '',
            delivery_address: customer?.address || '',
            price_type_id: priceTypeId,
            payment_type: customer ? current.payment_type : 'cash',
            items: repriceItems(current.items, meta.products, priceTypeId),
        }));
    };

    const handleAreaChange = (areaId) => {
        const firstRoute = meta.routes.find((route) => Number(route.area_id) === Number(areaId));
        setForm((current) => ({ ...current, area_id: areaId, route_id: firstRoute?.id || '' }));
    };

    const handlePriceTypeChange = (priceTypeId) => {
        setForm((current) => ({
            ...current,
            price_type_id: priceTypeId,
            items: repriceItems(current.items, meta.products, priceTypeId),
        }));
    };

    const selectedOrderItems = form.items.filter((item) => item.product_id).map((item) => ({
        ...item,
        product: meta.products.find((product) => Number(product.id) === Number(item.product_id)),
    }));
    const filteredOrderProducts = meta.products.filter((product) => `${product.name} ${product.sku} ${product.unit}`.toLowerCase().includes(productSearch.trim().toLowerCase()));
    const orderBasicComplete = Boolean(form.area_id && form.route_id && form.recipient_name.trim() && form.delivery_address.trim() && form.order_date);
    const orderProductsComplete = orderBasicComplete && selectedOrderItems.length > 0;
    const orderPricingComplete = orderProductsComplete && selectedOrderItems.every((item) => {
        const saleQuantity = Number(item.quantity || 0);
        const focQuantity = Number(item.foc_quantity || 0);
        return saleQuantity >= 0
            && focQuantity >= 0
            && saleQuantity + focQuantity > 0
            && (saleQuantity === 0 || (item.unit_price !== '' && Number(item.unit_price) >= 0))
            && Number(item.discount_amount || 0) >= 0;
    });
    const canOpenOrderStep = (index) => index === 0 || (index === 1 && orderBasicComplete) || (index === 2 && orderProductsComplete) || (index === 3 && orderPricingComplete);

    const toggleOrderProduct = (product) => {
        const selected = form.items.some((item) => Number(item.product_id) === Number(product.id));
        setForm((current) => {
            if (selected) return { ...current, items: current.items.filter((item) => Number(item.product_id) !== Number(product.id)) };
            const item = { ...blankItem, product_id: product.id, unit_price: priceForProduct(product, current.price_type_id || defaultPriceTypeId(selectedCustomer, meta.price_types)) };
            const items = current.items.length === 1 && !current.items[0].product_id ? [item] : [...current.items, item];
            return { ...current, items };
        });
    };

    const submit = async (event) => {
        event.preventDefault();
        if (event.nativeEvent?.submitter?.dataset?.action !== 'save-order') return;
        if (!canManage || wizardStep !== 3 || !orderPricingComplete) return;
        setSaving(true);
        setState((current) => ({ ...current, error: '' }));

        try {
            const payload = {
                customer_id: form.customer_id ? Number(form.customer_id) : null,
                area_id: Number(form.area_id),
                route_id: Number(form.route_id),
                recipient_name: form.recipient_name,
                recipient_phone: form.recipient_phone || null,
                delivery_address: form.delivery_address,
                price_type_id: form.price_type_id ? Number(form.price_type_id) : undefined,
                order_date: form.order_date,
                requested_delivery_date: form.requested_delivery_date || null,
                notes: form.notes || null,
                items: form.items
                    .filter((item) => item.product_id)
                    .flatMap((item) => {
                        const lines = [];
                        if (Number(item.quantity || 0) > 0) lines.push({
                            product_id: Number(item.product_id),
                            quantity: Number(item.quantity),
                            unit_price: Number(item.unit_price || 0),
                            discount_amount: Number(item.discount_amount || 0),
                            item_type: 'sale',
                            remarks: item.remarks || null,
                        });
                        if (Number(item.foc_quantity || 0) > 0) lines.push({
                            product_id: Number(item.product_id),
                            quantity: Number(item.foc_quantity),
                            unit_price: 0,
                            discount_amount: 0,
                            item_type: 'foc',
                            remarks: item.remarks || null,
                        });
                        return lines;
                    }),
            };
            const { data } = editing
                ? await window.axios.put(`${apiBase('orders')}/${editId}`, payload)
                : await window.axios.post(apiBase('orders'), payload);
            setViewing({ order: data.data.order, items: data.data.items });
            closeForm();
            setState((current) => {
                const order = data.data.order;
                const exists = current.items.some((item) => item.id === order.id);
                return { ...current, items: exists ? current.items.map((item) => item.id === order.id ? { ...item, ...order } : item) : [{ ...order, items_count: data.data.items?.length || 0 }, ...current.items] };
            });
            onNavigate?.(`${orderListPath}/${data.data.order.id}`);
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
            setState((current) => ({ ...current, items: current.items.map((item) => item.id === order.id ? { ...item, ...data.data.order } : item), summary: { ...current.summary, pending_count: Math.max(0, Number(current.summary.pending_count || 0) - (order.status === 'pending' ? 1 : 0)), ready_count: Number(current.summary.ready_count || 0) + (order.status === 'pending' ? 1 : 0) } }));
        } catch (error) {
            setState((current) => ({ ...current, error: requestMessage(error, locale) }));
        } finally {
            setProcessingId(null);
        }
    };

    const cancelOrder = async (order) => {
        if (!canManage || processingId) return;
        setProcessingId(`cancel-${order.id}`);
        setActionMessage('');
        setState((current) => ({ ...current, error: '' }));
        try {
            const { data } = await window.axios.post(`${apiBase('orders')}/${order.id}/cancel`);
            setViewing((current) => current?.order?.id === order.id ? { ...current, order: data.data.order } : current);
            setCancelCandidate(null);
            setActionMessage(t(locale, 'orderCancelled'));
            setState((current) => ({ ...current, items: current.items.map((item) => item.id === order.id ? { ...item, ...data.data.order } : item), summary: { ...current.summary, pending_count: Math.max(0, Number(current.summary.pending_count || 0) - (order.status === 'pending' ? 1 : 0)), ready_count: Math.max(0, Number(current.summary.ready_count || 0) - (['confirmed', 'ready'].includes(order.status) ? 1 : 0)) } }));
        } catch (error) {
            setState((current) => ({ ...current, error: requestMessage(error, locale) }));
        } finally {
            setProcessingId(null);
        }
    };

    const requestCancelOrder = (order) => {
        if (!canManage || processingId) return;
        setActionMessage('');
        setState((current) => ({ ...current, error: '' }));
        setCancelCandidate(order);
    };

    const openEdit = (order) => {
        if (!canManage || !editableOrderStatuses.includes(order.status)) return;
        onNavigate?.(`${orderListPath}/${order.id}/edit`);
    };
    const showOrderActions = canManage && state.items.some((order) => editableOrderStatuses.includes(order.status) || order.status === 'pending');

    if (formMode) return formOpen ? <OrderCreationWizard editing={editing} locale={locale} form={form} setForm={setForm} meta={meta} saving={saving} error={state.error || meta.error} step={wizardStep} setStep={setWizardStep} productSearch={productSearch} setProductSearch={setProductSearch} products={filteredOrderProducts} selectedItems={selectedOrderItems} preview={preview} basicComplete={orderBasicComplete} productsComplete={orderProductsComplete} pricingComplete={orderPricingComplete} canOpenStep={canOpenOrderStep} onClose={closeForm} onSubmit={submit} onCustomerChange={handleCustomerChange} onAreaChange={handleAreaChange} onPriceTypeChange={handlePriceTypeChange} onToggleProduct={toggleOrderProduct} onUpdateItem={updateItem} /> : <WorkspaceState icon={(state.error || meta.error) ? AlertCircle : RefreshCw} title={state.error || meta.error || t(locale, 'loading')} loading={!state.error && !meta.error} />;

    if (detailId && viewing) return <OrderDetailPage key={`${detailId}-${viewing.order.status || ''}`} viewing={viewing} locale={locale} canManage={canManage} processingId={processingId} cancelCandidate={cancelCandidate} actionMessage={actionMessage} actionError={state.error} onEdit={openEdit} onConfirm={confirmOrder} onRequestCancel={requestCancelOrder} onConfirmCancel={cancelOrder} onDismissCancel={() => setCancelCandidate(null)} onClose={() => onNavigate?.(orderListPath)} />;

    return (
        <section className="master-workspace order-workspace">
            <div className="master-heading">
                <div>
                    <p className="eyebrow">{t(locale, 'orders')}</p>
                    <h1>{t(locale, 'orders')}</h1>
                    <span className="muted">{t(locale, 'ordersHint')}</span>
                </div>
                <ShellPageActions>{canManage && <button className="button primary" type="button" disabled={meta.loading} onClick={openForm}><Plus size={16} /> {t(locale, 'newOrder')}</button>}</ShellPageActions>
            </div>

            <div className="metrics order-metrics">
                <div className="metric"><span>{t(locale, 'orders')}</span><strong>{Number(state.summary.orders_count || 0)}</strong><small>{t(locale, 'allStatuses')}</small></div>
                <div className="metric"><span>{t(locale, 'totalAmount')}</span><strong>{money(state.summary.total_amount)}</strong><small>{t(locale, 'subtotal')}</small></div>
                <div className="metric"><span>{t(locale, 'pending')}</span><strong>{Number(state.summary.pending_count || 0)}</strong><small>{t(locale, 'confirmOrder')}</small></div>
                <div className="metric"><span>{t(locale, 'invoiced')}</span><strong>{Number(state.summary.ready_count || 0)}</strong><small>{t(locale, 'route')}</small></div>
            </div>

            <div className="master-panel">
                <div className="master-toolbar order-toolbar">
                    <label className="master-search"><Search size={14} /><input value={filters.search} placeholder={t(locale, 'searchOrders')} onChange={(event) => setFilters((current) => ({ ...current, search: event.target.value }))} /></label>
                    <label className="office-order-filter-field"><span>Status</span><select value={filters.status} onChange={(event) => setFilters((current) => ({ ...current, status: event.target.value }))}><option value="">{t(locale, 'allStatuses')}</option>{orderStatusTabs.filter(Boolean).map((status) => <option value={status} key={status}>{t(locale, status)}</option>)}</select></label>
                    <label className="office-order-filter-field"><span>Customer type</span><select value={filters.customer_scope} onChange={(event) => setFilters((current) => ({ ...current, customer_scope: event.target.value, customer_id: event.target.value === 'walk_in' ? '' : current.customer_id }))}><option value="">All customer types</option><option value="registered">Registered customers</option><option value="walk_in">Walk-in customers</option></select></label>
                    <label className="office-order-filter-field"><span>Customer</span><select disabled={filters.customer_scope === 'walk_in'} value={filters.customer_id} onChange={(event) => setFilters((current) => ({ ...current, customer_id: event.target.value, customer_scope: event.target.value ? 'registered' : current.customer_scope }))}><option value="">{t(locale, 'allCustomers')}</option>{meta.customers.map((customer) => <option value={customer.id} key={customer.id}>{customer.label}</option>)}</select></label>
                    <label className="office-order-filter-field"><span>Area</span><select value={filters.area_id} onChange={(event) => setFilters((current) => ({ ...current, area_id: event.target.value, route_id: '' }))}><option value="">All areas</option>{meta.areas.map((area) => <option value={area.id} key={area.id}>{area.code} · {area.name}</option>)}</select></label>
                    <label className="office-order-filter-field"><span>Route</span><select value={filters.route_id} onChange={(event) => setFilters((current) => ({ ...current, route_id: event.target.value }))}><option value="">All routes</option>{meta.routes.filter((route) => !filters.area_id || Number(route.area_id) === Number(filters.area_id)).map((route) => <option value={route.id} key={route.id}>{route.code} · {route.name}</option>)}</select></label>
                    <label className="office-order-filter-field"><span>Price type</span><select value={filters.price_type_id} onChange={(event) => setFilters((current) => ({ ...current, price_type_id: event.target.value }))}><option value="">All price types</option>{meta.price_types.map((priceType) => <option value={priceType.id} key={priceType.id}>{priceType.code} · {priceType.name}</option>)}</select></label>
                    <label className="office-order-filter-field"><span>Payment type</span><select value={filters.payment_type} onChange={(event) => setFilters((current) => ({ ...current, payment_type: event.target.value }))}><option value="">All payment types</option><option value="unsettled">Set at delivery</option><option value="cash">Cash</option><option value="credit">Credit</option></select></label>
                    <label className="office-order-filter-field"><span>Order date from</span><input type="date" value={filters.date_from} max={filters.date_to} onChange={(event) => setFilters((current) => ({ ...current, date_from: event.target.value }))} /></label>
                    <label className="office-order-filter-field"><span>Order date through</span><input type="date" value={filters.date_to} min={filters.date_from} onChange={(event) => setFilters((current) => ({ ...current, date_to: event.target.value }))} /></label>
                    <label className="office-order-filter-field"><span>Delivery date from</span><input type="date" value={filters.delivery_from} max={filters.delivery_to} onChange={(event) => setFilters((current) => ({ ...current, delivery_from: event.target.value }))} /></label>
                    <label className="office-order-filter-field"><span>Delivery date through</span><input type="date" value={filters.delivery_to} min={filters.delivery_from} onChange={(event) => setFilters((current) => ({ ...current, delivery_to: event.target.value }))} /></label>
                    <label className="office-order-filter-field"><span>Minimum total (MMK)</span><input type="number" min="0" step="1" value={filters.min_total} onChange={(event) => setFilters((current) => ({ ...current, min_total: event.target.value }))} /></label>
                    <label className="office-order-filter-field"><span>Maximum total (MMK)</span><input type="number" min={filters.min_total || 0} step="1" value={filters.max_total} onChange={(event) => setFilters((current) => ({ ...current, max_total: event.target.value }))} /></label>
                    <label className="office-order-filter-field"><span>Driver changes</span><select value={filters.driver_modified} onChange={(event) => setFilters((current) => ({ ...current, driver_modified: event.target.value }))}><option value="">All orders</option><option value="1">Modified by driver</option><option value="0">Not modified by driver</option></select></label>
                    <button className="button" type="button" onClick={() => setFilters(emptyFilters)}>Clear filters</button>
                    <button className="icon-button" type="button" aria-label={t(locale, 'refresh')} title={t(locale, 'refresh')} onClick={() => setRefreshKey((key) => key + 1)}><RefreshCw size={15} /></button>
                </div>

                {cancelCandidate && <OrderCancelConfirmation order={cancelCandidate} locale={locale} processingId={processingId} onConfirm={cancelOrder} onDismiss={() => setCancelCandidate(null)} />}
                {actionMessage && <div className="inline-success"><BadgeCheck size={15} /> {actionMessage}</div>}
                {(state.error || meta.error) && <div className="inline-error"><AlertCircle size={15} /> {state.error || meta.error}</div>}
                {state.loading ? <WorkspaceState icon={RefreshCw} title={t(locale, 'loading')} loading compact /> : state.items.length === 0 ? <WorkspaceState icon={ShoppingCart} title={t(locale, 'empty')} compact /> : (
                    <div className="master-table-wrap">
                        <table className="master-table order-table">
                            <thead><tr><th>{t(locale, 'orders')}</th><th>{t(locale, 'recipientName')}</th><th>{t(locale, 'customer')}</th><th>{t(locale, 'area')} / {t(locale, 'route')}</th><th>{t(locale, 'address')}</th><th>{t(locale, 'date')}</th><th>{t(locale, 'paymentType')}</th><th>{t(locale, 'total')}</th><th>{t(locale, 'status')}</th>{showOrderActions && <th className="order-actions-header">{t(locale, 'actions')}</th>}</tr></thead>
                            <tbody>
                                {state.items.map((order) => (
                                    <tr className="clickable-row" key={order.id} tabIndex={0} onClick={() => onNavigate?.(`${orderListPath}/${order.id}`)} onKeyDown={(event) => { if (event.target === event.currentTarget && ['Enter', ' '].includes(event.key)) { event.preventDefault(); onNavigate?.(`${orderListPath}/${order.id}`); } }}>
                                        <td><strong>{order.code}</strong><span className="muted">{t(locale, 'updated')}: {formatDateTime(order.updated_at)}</span></td>
                                        <td><strong>{order.shop_name}</strong><span className="muted">{order.recipient_phone || '-'}</span></td>
                                        <td><strong>{order.customer_code || '-'}</strong><span className="muted">{order.customer_id ? t(locale, 'customer') : t(locale, 'guestCustomer')}</span></td>
                                        <td><strong>{order.area || '-'}</strong><span className="muted">{order.route || '-'}</span></td>
                                        <td className="order-address-cell">{order.delivery_address || '-'}</td>
                                        <td>{order.order_date}<span className="muted">{order.requested_delivery_date || '-'}</span></td>
                                        <td>{t(locale, order.payment_type)}</td>
                                        <td className="numeric">{money(order.total)}</td>
                                        <td><StatusBadge status={order.status} locale={locale} />{order.driver_modified && <span className="driver-modified-status">Driver modified</span>}</td>
                                        {showOrderActions && <td className="order-actions-cell" onClick={(event) => event.stopPropagation()}><div className="row-actions">
                                            {editableOrderStatuses.includes(order.status) && <button type="button" aria-label={t(locale, 'editOrder')} title={t(locale, 'editOrder')} onClick={() => openEdit(order)}><Pencil size={15} /></button>}
                                            {order.status === 'pending' && <button type="button" disabled={processingId === order.id} aria-label={t(locale, 'confirm')} title={t(locale, 'confirm')} onClick={() => confirmOrder(order)}><BadgeCheck size={15} /></button>}
                                            {editableOrderStatuses.includes(order.status) && <button className="danger" type="button" disabled={processingId === `cancel-${order.id}`} aria-label={t(locale, 'cancelOrder')} title={t(locale, 'cancelOrder')} onClick={() => requestCancelOrder(order)}><Ban size={15} /></button>}
                                        </div></td>}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination meta={state.pageMeta} page={page} setPage={setPage} />
            </div>


        </section>
    );
}

function OrderCreationWizard({ editing = false, locale, form, setForm, meta, saving, error, step, setStep, productSearch, setProductSearch, products, selectedItems, preview, basicComplete, productsComplete, pricingComplete, canOpenStep, onClose, onSubmit, onCustomerChange, onAreaChange, onPriceTypeChange, onToggleProduct, onUpdateItem }) {
    const steps = [
        ['Customer & delivery', 'Recipient, route and order details'],
        ['Select products', 'Choose products for this order'],
        ['Quantity & price', 'Enter quantities, prices and discounts'],
        ['Review', 'Confirm before saving the order'],
    ];
    const customer = meta.customers.find((item) => Number(item.id) === Number(form.customer_id));
    const area = meta.areas.find((item) => Number(item.id) === Number(form.area_id));
    const route = meta.routes.find((item) => Number(item.id) === Number(form.route_id));
    const priceType = meta.price_types.find((item) => Number(item.id) === Number(form.price_type_id));
    const totalQuantity = selectedItems.reduce((sum, item) => sum + Number(item.quantity || 0) + Number(item.foc_quantity || 0), 0);
    const canContinue = step === 0 ? basicComplete : step === 1 ? productsComplete : pricingComplete;

    return (
        <section className="master-workspace stock-receive-form-page order-wizard-workspace">
            <ShellBackButton onClick={() => step > 0 ? setStep((current) => current - 1) : onClose()} label={step > 0 ? "Previous step" : "Back to orders"} />
            <form className="master-dialog stock-dialog stock-wizard-dialog stock-wizard-page" onSubmit={onSubmit}>
                <header className="stock-wizard-page-heading">
                    <div><h1>{editing ? t(locale, 'editOrder') : t(locale, 'newOrder')}</h1><p>{editing ? 'Update the order while keeping it available for cancellation.' : 'Create and review a customer order before saving it.'}</p></div>
                </header>

                <div className="stock-wizard-stepper-wrap">
                    <ol className="trip-wizard-steps stock-receive-steps" aria-label={editing ? t(locale, 'editOrder') : t(locale, 'newOrder')}>
                        {steps.map(([label, hint], index) => <li key={label} className={step === index ? 'is-current' : index < step ? 'is-complete' : ''}>
                            <button type="button" disabled={!canOpenStep(index)} onClick={() => canOpenStep(index) && setStep(index)} aria-current={step === index ? 'step' : undefined}>
                                <span>{index < step ? <Check size={13} /> : index + 1}</span><strong>{label}</strong><small>{hint}</small>
                            </button>
                        </li>)}
                    </ol>
                </div>

                <div className="master-form-body stock-wizard-body">
                    {error && <p className="form-alert">{error}</p>}

                    {step === 0 && <section className="stock-wizard-stage">
                        <div className="stock-wizard-stage-heading"><p className="eyebrow">1 / 4</p><h3>Customer and delivery</h3><span>Set the recipient, route, pricing, and requested delivery information.</span></div>
                        <div className="master-form-grid stock-wizard-basic order-wizard-basic">
                            <label className="span-2"><span className="stock-field-label">{t(locale, 'optionalCustomer')}</span><select value={form.customer_id} onChange={(event) => onCustomerChange(event.target.value)}><option value="">{t(locale, 'guestCustomer')}</option>{meta.customers.map((item) => <option value={item.id} key={item.id}>{item.label}</option>)}</select></label>
                            <label><span className="stock-field-label">{t(locale, 'area')} *</span><select required value={form.area_id} onChange={(event) => onAreaChange(event.target.value)}><option value="">{t(locale, 'area')}</option>{meta.areas.map((item) => <option value={item.id} key={item.id}>{item.code} - {item.name}</option>)}</select></label>
                            <label><span className="stock-field-label">{t(locale, 'route')} *</span><select required value={form.route_id} onChange={(event) => setForm((current) => ({ ...current, route_id: event.target.value }))}><option value="">{t(locale, 'route')}</option>{meta.routes.filter((item) => Number(item.area_id) === Number(form.area_id)).map((item) => <option value={item.id} key={item.id}>{item.code} - {item.name}</option>)}</select></label>
                            <label><span className="stock-field-label">{t(locale, 'recipientName')} *</span><input required maxLength="150" value={form.recipient_name} onChange={(event) => setForm((current) => ({ ...current, recipient_name: event.target.value }))} /></label>
                            <label><span className="stock-field-label">{t(locale, 'recipientPhone')} <small>{t(locale, 'optional')}</small></span><input type="tel" maxLength="50" value={form.recipient_phone} onChange={(event) => setForm((current) => ({ ...current, recipient_phone: event.target.value }))} /></label>
                            <label className="span-2"><span className="stock-field-label">{t(locale, 'address')} *</span><textarea required rows="2" maxLength="500" value={form.delivery_address} onChange={(event) => setForm((current) => ({ ...current, delivery_address: event.target.value }))} /></label>
                            <label><span className="stock-field-label">{t(locale, 'priceType')}</span><select value={form.price_type_id} onChange={(event) => onPriceTypeChange(event.target.value)}><option value="">{t(locale, 'priceType')}</option>{meta.price_types.map((item) => <option value={item.id} key={item.id}>{item.name}</option>)}</select></label>
                            <label><span className="stock-field-label">{t(locale, 'orderDate')} *</span><input required type="date" value={form.order_date} onChange={(event) => setForm((current) => ({ ...current, order_date: event.target.value, credit_due_date: current.payment_type === 'credit' && (!current.credit_due_date || current.credit_due_date < event.target.value) ? datePlusDays(event.target.value, 7) : current.credit_due_date }))} /></label>
                            <label><span className="stock-field-label">{t(locale, 'requestedDelivery')} <small>{t(locale, 'optional')}</small></span><input type="date" min={form.order_date} value={form.requested_delivery_date} onChange={(event) => setForm((current) => ({ ...current, requested_delivery_date: event.target.value }))} /></label>
                            <label className="span-2"><span className="stock-field-label">{t(locale, 'notes')} <small>{t(locale, 'optional')}</small></span><textarea rows="2" maxLength="500" value={form.notes} onChange={(event) => setForm((current) => ({ ...current, notes: event.target.value }))} /></label>
                        </div>
                    </section>}

                    {step === 1 && <section className="stock-wizard-stage">
                        <div className="stock-wizard-stage-heading"><p className="eyebrow">2 / 4</p><h3>Select products</h3><span>Search and choose every product included in this order.</span></div>
                        <label className="master-search stock-product-search"><Search size={14} /><input value={productSearch} onChange={(event) => setProductSearch(event.target.value)} placeholder="Search products" autoFocus /></label>
                        <div className="stock-product-picker">{products.length === 0 ? <p className="stock-wizard-empty">No products found.</p> : products.map((product) => {
                            const selected = selectedItems.some((item) => Number(item.product_id) === Number(product.id));
                            return <button type="button" key={product.id} className={selected ? 'is-selected' : ''} onClick={() => onToggleProduct(product)}><span className="stock-product-icon"><Package size={16} /></span><span><strong>{product.name}</strong><small>{product.sku} · {product.unit}</small></span><span className="stock-product-cost"><small>{t(locale, 'price')}</small><strong>{money(priceForProduct(product, form.price_type_id))}</strong></span><span className="stock-product-check">{selected && <Check size={14} />}</span></button>;
                        })}</div>
                    </section>}

                    {step === 2 && <section className="stock-wizard-stage">
                        <div className="stock-wizard-stage-heading"><p className="eyebrow">3 / 4</p><h3>Quantity and price</h3><span>Edit order values directly in the table.</span></div>
                        <div className="stock-line-summary"><span>{selectedItems.length} products</span><span>{t(locale, 'quantity')}: <strong>{totalQuantity.toLocaleString()}</strong></span><span>{t(locale, 'total')}: <strong>{money(preview.total)}</strong></span><button className="button" type="button" onClick={() => setStep(1)}>Select products</button></div>
                        <div className="master-table-wrap stock-receive-line-wrap order-wizard-line-wrap"><table className="master-table price-matrix-table stock-receive-entry-table order-wizard-entry-table"><thead><tr><th>{t(locale, 'product')}</th><th>{t(locale, 'quantity')}</th><th>{t(locale, 'foc')}</th><th>{t(locale, 'price')}<small>MMK</small></th><th>{t(locale, 'discount')}<small>MMK</small></th><th>{t(locale, 'total')}<small>MMK</small></th><th className="table-actions-header">{t(locale, 'actions')}</th></tr></thead><tbody>{selectedItems.map((item) => {
                            const index = form.items.findIndex((line) => Number(line.product_id) === Number(item.product_id));
                            const lineTotal = Math.max(0, Number(item.quantity || 0) * Number(item.unit_price || 0) - Number(item.discount_amount || 0));
                            return <tr key={item.product_id}><td><strong>{item.product?.name}</strong><span className="muted">{item.product?.sku} · {item.product?.unit}</span></td><td className="price-matrix-cell"><input aria-label={`${item.product?.name} quantity`} min="0" step="1" type="number" inputMode="numeric" value={item.quantity} onFocus={(event) => event.currentTarget.select()} onChange={(event) => onUpdateItem(index, { quantity: event.target.value })} /></td><td className="price-matrix-cell"><input aria-label={`${item.product?.name} FOC quantity`} min="0" step="1" type="number" inputMode="numeric" value={item.foc_quantity} onFocus={(event) => event.currentTarget.select()} onChange={(event) => onUpdateItem(index, { foc_quantity: event.target.value })} /></td><td className="price-matrix-cell"><input aria-label={`${item.product?.name} price`} required={Number(item.quantity || 0) > 0} min="0" step="1" type="number" value={item.unit_price} onFocus={(event) => event.currentTarget.select()} onChange={(event) => onUpdateItem(index, { unit_price: event.target.value })} /></td><td className="price-matrix-cell"><input aria-label={`${item.product?.name} discount`} min="0" step="1" type="number" value={item.discount_amount} disabled={Number(item.quantity || 0) <= 0} onFocus={(event) => event.currentTarget.select()} onChange={(event) => onUpdateItem(index, { discount_amount: event.target.value })} /></td><td className="numeric stock-calculated-cell">{money(lineTotal)}</td><td className="table-actions-cell stock-entry-actions"><button className="icon-button danger" type="button" aria-label={`Remove ${item.product?.name}`} onClick={() => onToggleProduct(item.product)}><Trash2 size={14} /></button></td></tr>;
                        })}</tbody></table></div>
                    </section>}

                    {step === 3 && <section className="stock-wizard-stage">
                        <div className="stock-wizard-stage-heading"><p className="eyebrow">4 / 4</p><h3>Order review</h3><span>Confirm the customer, delivery, products, and totals before saving.</span></div>
                        <dl className="stock-receive-review order-wizard-review"><div><dt>{t(locale, 'customer')}</dt><dd>{customer?.label || t(locale, 'guestCustomer')}</dd></div><div><dt>{t(locale, 'recipientName')}</dt><dd>{form.recipient_name}</dd></div><div><dt>{t(locale, 'area')} / {t(locale, 'route')}</dt><dd>{area?.name || '-'} / {route?.name || '-'}</dd></div><div><dt>{t(locale, 'priceType')}</dt><dd>{priceType?.name || '-'}</dd></div><div><dt>{t(locale, 'orderDate')}</dt><dd>{form.order_date}</dd></div><div><dt>{t(locale, 'requestedDelivery')}</dt><dd>{form.requested_delivery_date || '-'}</dd></div><div><dt>Settlement</dt><dd>Set by driver at delivery</dd></div><div><dt>{t(locale, 'total')}</dt><dd>{money(preview.total)}</dd></div><div className="stock-review-notes"><dt>{t(locale, 'address')}</dt><dd>{form.delivery_address}</dd></div>{form.notes && <div className="stock-review-notes"><dt>{t(locale, 'notes')}</dt><dd>{form.notes}</dd></div>}</dl>
                        <div className="master-table-wrap stock-receive-review-lines"><table className="master-table"><thead><tr><th>{t(locale, 'product')}</th><th>{t(locale, 'quantity')}</th><th>{t(locale, 'foc')}</th><th>{t(locale, 'price')}</th><th>{t(locale, 'discount')}</th><th>{t(locale, 'total')}</th></tr></thead><tbody>{selectedItems.map((item) => <tr key={item.product_id}><td><strong>{item.product?.name}</strong><span className="muted">{item.product?.sku} · {item.product?.unit}</span></td><td className="numeric">{Number(item.quantity || 0).toLocaleString()}</td><td className="numeric">{Number(item.foc_quantity || 0).toLocaleString()}</td><td className="numeric">{money(item.unit_price)}</td><td className="numeric">{money(item.discount_amount)}</td><td className="numeric"><strong>{money(Math.max(0, Number(item.quantity) * Number(item.unit_price) - Number(item.discount_amount || 0)))}</strong></td></tr>)}</tbody></table></div>
                    </section>}
                </div>

                <footer className="stock-wizard-footer"><span className="muted">Step {step + 1} of 4</span><div><button className="button" type="button" onClick={onClose}>{t(locale, 'cancel')}</button>{step < 3 ? <button key="order-wizard-next" className="button primary" type="button" disabled={!canContinue} onClick={(event) => { event.preventDefault(); setStep((current) => current + 1); }}>Next</button> : <button key="order-wizard-save" className="button primary" type="submit" data-action="save-order" disabled={saving || !pricingComplete}><Save size={15} />{saving ? 'Saving…' : editing ? 'Save changes' : 'Save order'}</button>}</div></footer>
            </form>
        </section>
    );
}

function OrderCancelConfirmation({ order, locale, processingId, onConfirm, onDismiss }) {
    const titleId = useId();
    const isCancelling = processingId === `cancel-${order.id}`;

    useEffect(() => {
        const closeOnEscape = (event) => {
            if (event.key === 'Escape' && !processingId) onDismiss();
        };
        window.addEventListener('keydown', closeOnEscape);
        return () => window.removeEventListener('keydown', closeOnEscape);
    }, [onDismiss, processingId]);

    return <div className="modal-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && !processingId && onDismiss()}>
        <section className="master-dialog order-cancel-dialog" role="dialog" aria-modal="true" aria-labelledby={titleId}>
            <header><div><p className="eyebrow">{t(locale, 'orders')}</p><h2 id={titleId}>{t(locale, 'cancelOrder')}</h2></div><button className="icon-button" type="button" disabled={Boolean(processingId)} aria-label={t(locale, 'keepOrder')} title={t(locale, 'keepOrder')} onClick={onDismiss}><X size={17} /></button></header>
            <div className="order-cancel-dialog-body"><span className="order-cancel-dialog-icon"><TriangleAlert size={22} /></span><div><strong>{t(locale, 'cancelOrder')} {order.code}?</strong><p>{t(locale, 'cancelOrderPrompt')}</p></div></div>
            <footer><button className="button" type="button" disabled={Boolean(processingId)} onClick={onDismiss}>{t(locale, 'keepOrder')}</button><button className="button danger" type="button" disabled={Boolean(processingId)} onClick={() => onConfirm(order)}><Ban size={15} />{isCancelling ? 'Cancelling…' : t(locale, 'confirmCancellation')}</button></footer>
        </section>
    </div>;
}

function OrderStatusRoadmap({ order, locale }) {
    const delivery = order.delivery;
    const steps = [
        { key: 'submitted', label: t(locale, 'submitted') },
        { key: 'confirmed', label: t(locale, 'confirmed') },
        { key: 'loading', label: t(locale, 'loadingDelivery') },
        { key: 'on_route', label: t(locale, 'on_route') },
        { key: 'delivered', label: t(locale, 'delivered') },
    ];
    const deliveryStage = { planned: 1, assigned: 1, loading: 2, on_route: 3, delivered: 4, partially_delivered: 4, failed: 3 };
    const orderStage = { draft: 0, pending: 0, confirmed: 1, invoiced: 1, assigned: 1, delivering: 3, delivered: 4 };
    const currentIndex = delivery ? (deliveryStage[delivery.status] ?? orderStage[order.status] ?? 0) : (orderStage[order.status] ?? 0);
    const displayStatus = delivery?.status || order.status;

    return <section className="order-status-roadmap" aria-label={t(locale, 'statusTimeline')}>
        <header><span>{t(locale, 'statusTimeline')}</span><StatusBadge status={displayStatus} locale={locale} /></header>
        <ol>{steps.map((step, index) => <li className={index < currentIndex ? 'complete' : index === currentIndex ? 'current' : 'future'} key={step.key}><span>{index < currentIndex ? <Check size={13} /> : index + 1}</span><strong>{step.label}</strong></li>)}</ol>
    </section>;
}

function OrderDetailPage({ viewing, locale, canManage, processingId, cancelCandidate, actionMessage, actionError, onEdit, onConfirm, onRequestCancel, onConfirmCancel, onDismissCancel, onClose }) {
    const [state, setState] = useState(() => viewing.items ? { loading: false, order: viewing.order, items: viewing.items, error: '' } : { loading: true, order: viewing.order, items: [], error: '' });
    const [printProfile, setPrintProfile] = useState({ company: null, setting: null });
    const [mapOpen, setMapOpen] = useState(false);

    useEffect(() => {
        if (viewing.items) return undefined;
        let mounted = true;
        window.axios.get(`${apiBase('orders')}/${viewing.order.id}`)
            .then(({ data }) => mounted && setState({ loading: false, order: data.data.order, items: data.data.items, error: '' }))
            .catch((error) => mounted && setState((current) => ({ ...current, loading: false, error: requestMessage(error, locale) })));
        return () => { mounted = false; };
    }, [locale, viewing]);

    useEffect(() => {
        let mounted = true;
        window.axios.get(window.ValleyRuntime?.api?.printSettings || '/api/settings/printing')
            .then(({ data }) => mounted && setPrintProfile({ company: data.data.company, setting: data.data.documents?.sales_order || null }))
            .catch(() => {});
        return () => { mounted = false; };
    }, []);

    const customerLatitude = Number(state.order.customer_latitude);
    const customerLongitude = Number(state.order.customer_longitude);
    const hasCustomerGps = state.order.customer_latitude !== null && state.order.customer_latitude !== undefined
        && state.order.customer_longitude !== null && state.order.customer_longitude !== undefined
        && Number.isFinite(customerLatitude) && Number.isFinite(customerLongitude);
    const customerMapBounds = hasCustomerGps ? `${customerLongitude - 0.005},${customerLatitude - 0.0035},${customerLongitude + 0.005},${customerLatitude + 0.0035}` : '';
    const customerMapEmbedUrl = hasCustomerGps ? `https://www.openstreetmap.org/export/embed.html?bbox=${encodeURIComponent(customerMapBounds)}&layer=mapnik&marker=${encodeURIComponent(`${customerLatitude},${customerLongitude}`)}` : null;
    const customerMapPageUrl = hasCustomerGps ? `https://www.openstreetmap.org/?mlat=${customerLatitude}&mlon=${customerLongitude}#map=17/${customerLatitude}/${customerLongitude}` : null;
    const actions = <>
        {customerMapEmbedUrl && <button className="icon-button" type="button" title="View customer GPS position" aria-label="View customer GPS position" onClick={() => setMapOpen(true)}><MapPinned size={16} /></button>}
        <button className="button" type="button" disabled={state.loading} onClick={() => window.print()}><Printer size={15} /> Print voucher</button>
        {canManage && editableOrderStatuses.includes(state.order.status) && <>
            <button className="button" type="button" onClick={() => onEdit(state.order)}><Pencil size={15} /> {t(locale, 'editOrder')}</button>
            {state.order.status === 'pending' && <button className="button primary" type="button" disabled={processingId === state.order.id} onClick={() => onConfirm(state.order)}><BadgeCheck size={15} /> {t(locale, 'confirmOrder')}</button>}
            <button className="button danger" type="button" disabled={processingId === `cancel-${state.order.id}`} onClick={() => onRequestCancel(state.order)}><Ban size={15} /> {t(locale, 'cancelOrder')}</button>
        </>}
    </>;
    const itemRows = useMemo(() => Object.values(state.items.reduce((rows, item) => {
        const key = String(item.product_id);
        if (!rows[key]) rows[key] = {
            product_id: item.product_id,
            product_name: item.product_name,
            product_sku: item.product_sku,
            unit: item.unit,
            quantity: 0,
            foc_quantity: 0,
            unit_price: 0,
            discount_amount: 0,
            line_total: 0,
        };
        if (item.item_type === 'foc') {
            rows[key].foc_quantity += Number(item.quantity || 0);
        } else {
            rows[key].quantity += Number(item.quantity || 0);
            rows[key].unit_price = Number(item.unit_price || 0);
            rows[key].discount_amount += Number(item.discount_amount || 0);
            rows[key].line_total += Number(item.line_total || 0);
        }
        return rows;
    }, {})), [state.items]);
    const itemsTable = !state.loading && <div className="order-detail-items-section">
        <div className="record-page-panel-heading"><p className="eyebrow">{t(locale, 'items')}</p><h2>{itemRows.length} {t(locale, 'items')}</h2></div>
        <div className="master-table-wrap order-detail-items-wrap">
            <table className="master-table order-item-table order-detail-item-table">
                <colgroup><col className="order-item-product-column" /><col className="order-item-quantity-column" /><col className="order-item-foc-column" /><col className="order-item-price-column" /><col className="order-item-discount-column" /><col className="order-item-total-column" /></colgroup>
                <thead><tr><th scope="col">{t(locale, 'product')}</th><th scope="col" className="numeric">{t(locale, 'quantity')}</th><th scope="col" className="numeric">{t(locale, 'foc')}</th><th scope="col" className="numeric">{t(locale, 'price')}</th><th scope="col" className="numeric">{t(locale, 'discount')}</th><th scope="col" className="numeric">{t(locale, 'total')}</th></tr></thead>
                <tbody>{itemRows.map((item) => <tr key={item.product_id}><td><strong>{item.product_name}</strong><span className="muted">{item.product_sku} · {item.unit}</span></td><td className="numeric">{Number(item.quantity).toLocaleString()}</td><td className="numeric">{Number(item.foc_quantity).toLocaleString()}</td><td className="numeric">{money(item.unit_price)}</td><td className="numeric">{money(item.discount_amount)}</td><td className="numeric"><strong>{money(item.line_total)}</strong></td></tr>)}</tbody>
            </table>
        </div>
    </div>;
    const businessName = printProfile.company?.name || document.querySelector('.brand strong')?.textContent?.trim() || 'Valley Water Distribution';
    const printSetting = printProfile.setting || { paper_size: 'A4', orientation: 'portrait', margin_mm: 10, design: 'classic', accent_color: '#0b84a5', show_logo: true, show_address: true, show_contact: true, show_tax_number: false, show_notes: true, show_signatures: true, header_text: '', footer_text: 'Thank you.', copies: 1 };
    const thermalPrint = ['80mm', '58mm'].includes(printSetting.paper_size);
    const pageSize = printSetting.paper_size === '80mm' ? '80mm 297mm' : printSetting.paper_size === '58mm' ? '58mm 210mm' : `${printSetting.paper_size} ${printSetting.orientation}`;
    const companyAddress = [printProfile.company?.address, printProfile.company?.city, printProfile.company?.state].filter(Boolean).join(', ');
    const companyContact = [printProfile.company?.phone, printProfile.company?.email].filter(Boolean).join(' · ');
    const voucherBody = (copyIndex) => <article className={`office-order-voucher print-design-${printSetting.design} print-paper-${printSetting.paper_size.toLowerCase()}`} key={copyIndex} style={{ '--voucher-accent': printSetting.accent_color }}>
        <header>
            <div className="voucher-company-heading">{printSetting.show_logo && printProfile.company?.logo_url && <img src={printProfile.company.logo_url} alt="" />}<span><p>{businessName}</p><h1>Order voucher</h1>{printSetting.header_text && <small>{printSetting.header_text}</small>}{printSetting.show_address && companyAddress && <small>{companyAddress}</small>}{printSetting.show_contact && companyContact && <small>{companyContact}</small>}{printSetting.show_tax_number && printProfile.company?.tax_no && <small>Tax: {printProfile.company.tax_no}</small>}</span></div>
            <div><strong>{state.order.code}</strong><span>{state.order.order_date}</span></div>
        </header>
        <dl>
            <div><dt>Customer</dt><dd>{state.order.customer_code ? `${state.order.customer_code} · ${state.order.shop_name}` : state.order.shop_name || t(locale, 'guestCustomer')}</dd></div>
            <div><dt>Phone</dt><dd>{state.order.recipient_phone || '-'}</dd></div>
            <div><dt>Area / Route</dt><dd>{state.order.area || '-'} / {state.order.route || '-'}</dd></div>
            <div><dt>Delivery address</dt><dd>{state.order.delivery_address || '-'}</dd></div>
            <div><dt>Payment</dt><dd>{t(locale, state.order.payment_type)}{state.order.credit_due_date ? ` · Due ${state.order.credit_due_date}` : ''}</dd></div>
            <div><dt>Requested delivery</dt><dd>{state.order.requested_delivery_date || '-'}</dd></div>
        </dl>
        {!thermalPrint ? <table>
            <thead><tr><th>#</th><th>Product</th><th className="numeric">Qty</th><th className="numeric">FOC</th><th className="numeric">Price</th><th className="numeric">Discount</th><th className="numeric">Total</th></tr></thead>
            <tbody>{itemRows.map((item, index) => <tr key={item.product_id}><td>{index + 1}</td><td><strong>{item.product_name}</strong><small>{item.product_sku} · {item.unit}</small></td><td className="numeric">{Number(item.quantity).toLocaleString()}</td><td className="numeric">{Number(item.foc_quantity).toLocaleString()}</td><td className="numeric">{money(item.unit_price)}</td><td className="numeric">{money(item.discount_amount)}</td><td className="numeric"><strong>{money(item.line_total)}</strong></td></tr>)}</tbody>
            <tfoot><tr><th colSpan="6">Subtotal</th><td className="numeric">{money(state.order.subtotal)}</td></tr><tr><th colSpan="6">Discount</th><td className="numeric">{money(state.order.discount_total)}</td></tr><tr className="voucher-grand-total"><th colSpan="6">Total</th><td className="numeric">{money(state.order.total)}</td></tr></tfoot>
        </table> : <section className="voucher-thermal-items">
            {itemRows.map((item) => <article key={item.product_id}><header><strong>{item.product_name}</strong><b>{money(item.line_total)}</b></header><small>{item.product_sku} · {item.unit}</small><p><span>{Number(item.quantity).toLocaleString()} × {money(item.unit_price)}</span>{Number(item.foc_quantity) > 0 && <span>FOC {Number(item.foc_quantity).toLocaleString()}</span>}{Number(item.discount_amount) > 0 && <span>Discount {money(item.discount_amount)}</span>}</p></article>)}
            <dl><div><dt>Subtotal</dt><dd>{money(state.order.subtotal)}</dd></div><div><dt>Discount</dt><dd>{money(state.order.discount_total)}</dd></div><div><dt>Total</dt><dd>{money(state.order.total)}</dd></div></dl>
        </section>}
        {printSetting.show_notes && state.order.notes && <section><strong>Notes</strong><p>{state.order.notes}</p></section>}
        {printSetting.show_signatures && <footer><span>Prepared by</span><span>Approved by</span><span>Customer signature</span></footer>}
        {printSetting.footer_text && <p className="voucher-footer-note">{printSetting.footer_text}</p>}
    </article>;
    const voucher = !state.loading && <><style media="print">{`@page { size: ${pageSize}; margin: ${Number(printSetting.margin_mm) || 0}mm; }`}</style>{Array.from({ length: Math.max(1, Number(printSetting.copies) || 1) }, (_, index) => voucherBody(index))}</>;

    return (
        <>
            {actions && <ShellPageActions>{actions}</ShellPageActions>}
            {voucher}
            {mapOpen && customerMapEmbedUrl && <div className="modal-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && setMapOpen(false)}>
                <section className="master-dialog order-customer-map-dialog" role="dialog" aria-modal="true" aria-labelledby="order-customer-map-title">
                    <header><div><p className="eyebrow">Customer location</p><h2 id="order-customer-map-title">{state.order.shop_name}</h2></div><button className="icon-button" type="button" title="Close map" aria-label="Close map" onClick={() => setMapOpen(false)}><X size={17} /></button></header>
                    <div className="order-customer-map-body"><iframe title={`${state.order.shop_name} GPS position`} src={customerMapEmbedUrl} loading="lazy" referrerPolicy="no-referrer" /></div>
                    <footer className="order-customer-map-footer"><span><MapPinned size={14} />{customerLatitude.toFixed(6)}, {customerLongitude.toFixed(6)}</span><a className="button" href={customerMapPageUrl} target="_blank" rel="noreferrer">Open in OpenStreetMap</a></footer>
                </section>
            </div>}
            <DetailPage eyebrow={t(locale, 'orders')} title={state.order.code || t(locale, 'loading')} subtitle={state.order.shop_name} onBack={onClose}>
                {cancelCandidate?.id === state.order.id && <OrderCancelConfirmation order={cancelCandidate} locale={locale} processingId={processingId} onConfirm={onConfirmCancel} onDismiss={onDismissCancel} />}
                {actionMessage && <div className="inline-success"><BadgeCheck size={15} /> {actionMessage}</div>}
                {actionError && <div className="inline-error"><AlertCircle size={15} /> {actionError}</div>}
                {state.error && <div className="inline-error"><AlertCircle size={15} /> {state.error}</div>}
                {!state.loading && state.order.driver_modified && <div className="driver-modified-banner"><Pencil size={16} /><span><strong>Driver modified the final sale</strong><small>{state.order.driver_modification_note || 'No modification note was added.'}</small></span></div>}
                {state.loading ? <WorkspaceState icon={RefreshCw} title={t(locale, 'loading')} loading compact /> : (
                    <DetailPanel className="order-detail-unified-panel">
                        <section className="order-total-card order-detail-overview"><ShoppingCart size={18} /><div><small>{state.order.shop_name}</small><strong>{money(state.order.total)}</strong><span>{state.order.order_date} · {t(locale, state.order.payment_type)}</span></div><OrderStatusRoadmap order={state.order} locale={locale} /></section>
                        <dl className="order-detail-statement">
                            <div><dt>{t(locale, 'customer')}</dt><dd>{state.order.customer_code ? `${state.order.customer_code} · ${state.order.shop_name}` : state.order.shop_name || t(locale, 'guestCustomer')}</dd></div>
                            <div><dt>{t(locale, 'area')} / {t(locale, 'route')}</dt><dd>{state.order.area || '-'} / {state.order.route || '-'}</dd></div>
                            <div><dt>{t(locale, 'address')}</dt><dd>{state.order.delivery_address || '-'}</dd></div>
                            <div><dt>{t(locale, 'priceType')}</dt><dd>{state.order.price_type || '-'}</dd></div>
                            <div><dt>{t(locale, 'paymentType')}</dt><dd>{t(locale, state.order.payment_type)} · {state.order.payment_type === 'credit' ? `${t(locale, 'dueDate')} ${state.order.credit_due_date || '-'}` : state.order.payment_type === 'unsettled' ? 'Calculated at delivery' : 'Paid by cash'}</dd></div>
                            <div><dt>{t(locale, 'requestedDelivery')}</dt><dd>{state.order.requested_delivery_date || '-'}</dd></div>
                            <div><dt>{t(locale, 'notes')}</dt><dd>{state.order.notes || '-'}</dd></div>
                        </dl>
                        {itemsTable}
                    </DetailPanel>
                )}
            </DetailPage>
        </>
    );
}

export function InvoicesScreen({ locale, canManage = false, detailId = null, onNavigate }) {
    const [filters, setFilters] = useState({ search: '', status: '', date: '', customer_id: '' });
    const [meta, setMeta] = useState({ loading: true, customers: [], error: '' });
    const [state, setState] = useState({ loading: true, items: [], summary: {}, pageMeta: {}, error: '' });
    const [page, setPage] = useState(1);
    const [refreshKey, setRefreshKey] = useState(0);
    const [viewing, setViewing] = useState(null);
    const invoiceListPath = `${window.ValleyRuntime?.routes?.office || '/office'}/invoices`;
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

    useEffect(() => {
        setViewing(detailId ? { invoice: { id: detailId }, items: null } : null);
    }, [detailId]);

    const issueInvoice = async (invoice) => {
        if (!canManage || processingId || !window.confirm(t(locale, 'issueInvoicePrompt'))) return;
        setProcessingId(`issue-${invoice.id}`);
        setState((current) => ({ ...current, error: '' }));
        try {
            const { data } = await window.axios.post(`${apiBase('invoices')}/${invoice.id}/issue`);
            setViewing((current) => current?.invoice?.id === invoice.id ? { ...current, invoice: data.data.invoice } : current);
            setState((current) => ({ ...current, items: current.items.map((item) => item.id === invoice.id ? { ...item, ...data.data.invoice } : item), summary: { ...current.summary, draft_count: Math.max(0, Number(current.summary.draft_count || 0) - (invoice.status === 'draft' ? 1 : 0)), issued_count: Number(current.summary.issued_count || 0) + (invoice.status === 'draft' ? 1 : 0) } }));
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
            setState((current) => ({ ...current, items: current.items.map((item) => item.id === invoice.id ? { ...item, ...data.data.invoice } : item), summary: { ...current.summary, draft_count: Math.max(0, Number(current.summary.draft_count || 0) - (invoice.status === 'draft' ? 1 : 0)), issued_count: Math.max(0, Number(current.summary.issued_count || 0) - (invoice.status === 'issued' ? 1 : 0)) } }));
        } catch (error) {
            setState((current) => ({ ...current, error: requestMessage(error, locale) }));
        } finally {
            setProcessingId(null);
        }
    };
    const showInvoiceActions = canManage && state.items.some((invoice) => invoice.status !== 'cancelled');

    if (detailId && viewing) return <InvoiceDetailPage viewing={viewing} locale={locale} canManage={canManage} processingId={processingId} onIssue={issueInvoice} onCancelInvoice={cancelInvoice} onClose={() => onNavigate?.(invoiceListPath)} />;

    return (
        <section className="master-workspace invoice-workspace">
            <div className="master-heading">
                <div>
                    <p className="eyebrow">{t(locale, 'invoices')}</p>
                    <h1>{t(locale, 'invoices')}</h1>
                    <span className="muted">{t(locale, 'invoicesHint')}</span>
                </div>
                <ShellPageActions><button className="icon-button" type="button" aria-label={t(locale, 'refresh')} title={t(locale, 'refresh')} onClick={() => setRefreshKey((key) => key + 1)}><RefreshCw size={15} /></button></ShellPageActions>
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
                            <thead><tr><th>{t(locale, 'invoices')}</th><th>{t(locale, 'order')}</th><th>{t(locale, 'customer')}</th><th>{t(locale, 'invoiceDate')}</th><th>{t(locale, 'dueDate')}</th><th>{t(locale, 'total')}</th><th>{t(locale, 'status')}</th>{showInvoiceActions && <th className="invoice-actions-header">{t(locale, 'actions')}</th>}</tr></thead>
                            <tbody>
                                {state.items.map((invoice) => (
                                    <tr className="clickable-row" key={invoice.id} tabIndex={0} onClick={() => onNavigate?.(`${invoiceListPath}/${invoice.id}`)} onKeyDown={(event) => { if (event.target === event.currentTarget && ['Enter', ' '].includes(event.key)) { event.preventDefault(); onNavigate?.(`${invoiceListPath}/${invoice.id}`); } }}>
                                        <td><strong>{invoice.code}</strong><span className="muted">{t(locale, 'updated')}: {formatDateTime(invoice.updated_at)}</span></td>
                                        <td><strong>{invoice.order_code || '-'}</strong><span className="muted">{t(locale, invoice.payment_type || '')}</span></td>
                                        <td><strong>{invoice.shop_name}</strong><span className="muted">{invoice.customer_code || t(locale, 'guestCustomer')} · {invoice.area || '-'} / {invoice.route || '-'}</span></td>
                                        <td>{invoice.invoice_date}</td>
                                        <td>{invoice.due_date || '-'}</td>
                                        <td className="numeric">{money(invoice.total)}</td>
                                        <td><StatusBadge status={invoice.status} locale={locale} /></td>
                                        {showInvoiceActions && <td className="invoice-actions-cell" onClick={(event) => event.stopPropagation()}><div className="row-actions">
                                            {invoice.status === 'draft' && <button type="button" disabled={processingId === `issue-${invoice.id}`} aria-label={t(locale, 'issueInvoice')} title={t(locale, 'issueInvoice')} onClick={() => issueInvoice(invoice)}><BadgeCheck size={15} /></button>}
                                            {invoice.status !== 'cancelled' && <button className="danger" type="button" disabled={processingId === `cancel-${invoice.id}`} aria-label={t(locale, 'cancelInvoice')} title={t(locale, 'cancelInvoice')} onClick={() => cancelInvoice(invoice)}><Ban size={15} /></button>}
                                        </div></td>}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination meta={state.pageMeta} page={page} setPage={setPage} />
            </div>

        </section>
    );
}

function InvoiceDetailPage({ viewing, locale, canManage, processingId, onIssue, onCancelInvoice, onClose }) {
    const [state, setState] = useState(() => viewing.items ? { loading: false, invoice: viewing.invoice, items: viewing.items, error: '' } : { loading: true, invoice: viewing.invoice, items: [], error: '' });

    useEffect(() => {
        if (viewing.items) return undefined;
        let mounted = true;
        window.axios.get(`${apiBase('invoices')}/${viewing.invoice.id}`)
            .then(({ data }) => mounted && setState({ loading: false, invoice: data.data.invoice, items: data.data.items, error: '' }))
            .catch((error) => mounted && setState((current) => ({ ...current, loading: false, error: requestMessage(error, locale) })));
        return () => { mounted = false; };
    }, [locale, viewing]);

    const printInvoice = () => printConfiguredDocument('sales_invoice', {
        reference: state.invoice.code,
        facts: [
            ['Customer', `${state.invoice.customer_code || 'Guest'} · ${state.invoice.shop_name || '-'}`],
            ['Order', state.invoice.order_code || '-'],
            ['Invoice date', state.invoice.invoice_date || '-'],
            ['Due date', state.invoice.due_date || '-'],
            ['Area / route', `${state.invoice.area || '-'} / ${state.invoice.route || '-'}`],
            ['Status', state.invoice.status || '-'],
        ],
        columns: ['Item', 'Qty', 'Price', 'Total'],
        rows: state.items.map((item) => [`${item.product_name} · ${item.product_sku}`, Number(item.quantity).toLocaleString(), money(item.unit_price), money(item.line_total)]),
        total: ['Total MMK', money(state.invoice.total)],
        notes: state.invoice.notes,
    });
    const actions = <>
        <button className="icon-button" type="button" disabled={state.loading} title="Print invoice" aria-label="Print invoice" onClick={printInvoice}><Printer size={16} /></button>
        {canManage && state.invoice.status !== 'cancelled' && <>
            {state.invoice.status === 'draft' && <button className="button primary" type="button" disabled={processingId === `issue-${state.invoice.id}`} onClick={() => onIssue(state.invoice)}><BadgeCheck size={15} /> {t(locale, 'issueInvoice')}</button>}
            <button className="button danger" type="button" disabled={processingId === `cancel-${state.invoice.id}`} onClick={() => onCancelInvoice(state.invoice)}><Ban size={15} /> {t(locale, 'cancelInvoice')}</button>
        </>}
    </>;

    return (
        <DetailPage eyebrow={t(locale, 'invoices')} title={state.invoice.code || t(locale, 'loading')} subtitle={state.invoice.shop_name} onBack={onClose} actions={actions}
            aside={!state.loading && <DetailPanel eyebrow={t(locale, 'summary')}><section className="invoice-total-card record-page-summary"><FileText size={18} /><div><small>{state.invoice.shop_name}</small><strong>{money(state.invoice.total)}</strong><span>{state.invoice.invoice_date} · {state.invoice.order_code || '-'} · <StatusBadge status={state.invoice.status} locale={locale} /></span></div></section></DetailPanel>}
        >
            {state.error && <div className="inline-error"><AlertCircle size={15} /> {state.error}</div>}
            {state.loading ? <WorkspaceState icon={RefreshCw} title={t(locale, 'loading')} loading compact /> : <>
                    <DetailPanel eyebrow={t(locale, 'details')} title={t(locale, 'invoice')}>
                        <dl className="record-page-facts">
                            <div><dt>{t(locale, 'customer')}</dt><dd>{state.invoice.customer_code || t(locale, 'guestCustomer')}</dd></div>
                            <div><dt>{t(locale, 'recipientName')}</dt><dd>{state.invoice.shop_name}</dd></div>
                            <div><dt>{t(locale, 'area')} / {t(locale, 'route')}</dt><dd>{state.invoice.area || '-'} / {state.invoice.route || '-'}</dd></div>
                            <div><dt>{t(locale, 'address')}</dt><dd>{state.invoice.delivery_address || '-'}</dd></div>
                            <div><dt>{t(locale, 'dueDate')}</dt><dd>{state.invoice.due_date || '-'}</dd></div>
                            <div><dt>{t(locale, 'notes')}</dt><dd>{state.invoice.notes || '-'}</dd></div>
                        </dl>
                    </DetailPanel>
                    <DetailPanel eyebrow={t(locale, 'items')} title={`${state.items.length} ${t(locale, 'items')}`}>
                        <div className="master-table-wrap">
                            <table className="master-table invoice-item-table">
                                <thead><tr><th>{t(locale, 'product')}</th><th>{t(locale, 'quantity')}</th><th>{t(locale, 'price')}</th><th>{t(locale, 'total')}</th></tr></thead>
                                <tbody>{state.items.map((item) => <tr key={item.id}><td><strong>{item.product_name}</strong><span className="muted">{item.product_sku} · {item.unit}</span></td><td className="numeric">{Number(item.quantity).toLocaleString()}</td><td className="numeric">{money(item.unit_price)}</td><td className="numeric">{money(item.line_total)}</td></tr>)}</tbody>
                            </table>
                        </div>
                    </DetailPanel>
                </>}
        </DetailPage>
    );
}

function OrderCustomerCombobox({ customers, value, onChange, locale = 'en', required = true }) {
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
                required={required}
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

export function MobileOrdersScreen({ appId, locale, initialMode = 'list', editOrderId = null, repeatOrderId = null, onShowForm, onShowList, onViewOrder }) {
    const today = new Date().toISOString().slice(0, 10);
    const [mode, setMode] = useState(initialMode);
    const emptyFilters = { search: '', status: '', payment_type: '', date_from: '', date_to: '', delivery_from: '', delivery_to: '', min_total: '', max_total: '' };
    const [filters, setFilters] = useState(emptyFilters);
    const [meta, setMeta] = useState({ loading: true, app: appId, customers: [], products: [], price_types: [], areas: [], routes: [], error: '' });
    const [state, setState] = useState({ loading: true, items: [], summary: {}, pageMeta: {}, error: '' });
    const [saving, setSaving] = useState(false);
    const [showDetails, setShowDetails] = useState(false);
    const [wizardStep, setWizardStep] = useState(1);
    const [productSearch, setProductSearch] = useState('');
    const [refreshKey, setRefreshKey] = useState(0);
    const [form, setForm] = useState(() => ({
        customer_id: '',
        area_id: '',
        route_id: '',
        recipient_name: '',
        recipient_phone: '',
        delivery_address: '',
        requested_delivery_date: '',
        payment_type: 'credit',
        notes: '',
        promotion_title: '',
        price_type_id: '',
        order_discount: '',
        items: [],
    }));

    useEffect(() => setMode(initialMode), [initialMode]);

    useEffect(() => {
        let mounted = true;
        window.axios.get(`${apiBase('mobileOrders')}/meta`)
            .then(({ data }) => {
                if (!mounted) return;
                const customers = data.data.customers || [];
                const areas = data.data.areas || [];
                const routes = data.data.routes || [];
                const customer = data.data.app === 'client' ? customers[0] : null;
                const areaId = customer?.area_id || areas[0]?.id || '';
                const routeId = customer?.route_id || routes.find((route) => Number(route.area_id) === Number(areaId))?.id || '';
                setMeta({ loading: false, app: data.data.app, customers, products: data.data.products || [], price_types: data.data.price_types || [], areas, routes, error: '' });
                setForm((current) => ({ ...current, customer_id: customer?.id || '', area_id: areaId, route_id: routeId, recipient_name: customer?.shop_name || '', recipient_phone: customer?.phone || '', delivery_address: customer?.address || '', payment_type: customer ? 'credit' : 'cash' }));
            })
            .catch((error) => mounted && setMeta({ loading: false, app: appId, customers: [], products: [], price_types: [], areas: [], routes: [], error: requestMessage(error, locale) }));
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
                    payment_type: filters.payment_type || undefined,
                    date_from: filters.date_from || undefined,
                    date_to: filters.date_to || undefined,
                    delivery_from: filters.delivery_from || undefined,
                    delivery_to: filters.delivery_to || undefined,
                    min_total: filters.min_total || undefined,
                    max_total: filters.max_total || undefined,
                    per_page: 12,
                },
            }).then(({ data }) => {
                if (!mounted) return;
                setState({ loading: false, items: data.data.items, summary: data.data.summary, pageMeta: data.data.meta, error: '' });
            }).catch((error) => mounted && setState({ loading: false, items: [], summary: {}, pageMeta: {}, error: requestMessage(error, locale) }));
        }, 220);

        return () => { mounted = false; window.clearTimeout(timer); };
    }, [filters, locale, refreshKey]);

    useEffect(() => {
        const sourceId = editOrderId || repeatOrderId;
        if (!sourceId || meta.loading) return undefined;
        let active = true;
        window.axios.get(`${apiBase('mobileOrders')}/${sourceId}`).then(({ data }) => {
            if (!active) return;
            const order = data.data.order;
            const noteLines = (order.notes || '').split('\n');
            const promotionLine = noteLines.find((line) => line.startsWith('Promotion: '));
            setForm({
                customer_id: order.customer_id || '', area_id: order.area_id || '', route_id: order.route_id || '',
                recipient_name: order.recipient_name || '', recipient_phone: order.recipient_phone || '', delivery_address: order.delivery_address || '',
                requested_delivery_date: repeatOrderId ? '' : (order.requested_delivery_date || ''), payment_type: order.payment_type || 'cash',
                price_type_id: order.price_type_id || '',
                notes: repeatOrderId ? `Repeated from ${order.code}` : noteLines.filter((line) => line !== promotionLine).join('\n'),
                promotion_title: repeatOrderId ? (promotionLine?.slice('Promotion: '.length) || '') : (promotionLine?.slice('Promotion: '.length) || ''),
                order_discount: order.discount_total || '',
                items: meta.app === 'sales'
                    ? orderItemsForEditing(data.data.items)
                    : data.data.items.map((item) => ({ product_id: item.product_id, quantity: item.quantity, item_type: item.item_type, discount_amount: item.discount_amount || 0 })),
            });
            setShowDetails(true);
            setWizardStep(1);
            setMode('form');
        }).catch((error) => active && setState((current) => ({ ...current, error: requestMessage(error, locale) })));
        return () => { active = false; };
    }, [editOrderId, locale, meta.loading, repeatOrderId]);

    const selectedCustomer = meta.customers.find((customer) => Number(customer.id) === Number(form.customer_id));
    const priceTypeId = form.price_type_id || defaultPriceTypeId(selectedCustomer, meta.price_types);
    const preview = meta.app === 'sales'
        ? calculateSalesWizardPreview(form.items, meta.products, priceTypeId, form.order_discount)
        : calculateMobilePreview(form.items, meta.products, priceTypeId);
    const selectedProductRows = form.items.filter((item) => Number(item.quantity || 0) > 0 || Number(item.foc_quantity || 0) > 0);
    const visibleProducts = meta.products.filter((product) => !productSearch.trim() || `${product.sku} ${product.name}`.toLocaleLowerCase().includes(productSearch.trim().toLocaleLowerCase()));
    const basicStepComplete = Boolean(
        form.recipient_name.trim()
        && form.delivery_address.trim()
        && (meta.app === 'client' || (form.area_id && form.route_id))
    );
    const productStepComplete = selectedProductRows.length > 0;

    const resetForm = () => {
        setForm({
            customer_id: meta.app === 'client' ? meta.customers[0]?.id || '' : '',
            area_id: meta.app === 'client' ? meta.customers[0]?.area_id || '' : meta.areas[0]?.id || '',
            route_id: meta.app === 'client' ? meta.customers[0]?.route_id || '' : meta.routes[0]?.id || '',
            recipient_name: meta.app === 'client' ? meta.customers[0]?.shop_name || '' : '',
            recipient_phone: meta.app === 'client' ? meta.customers[0]?.phone || '' : '',
            delivery_address: meta.app === 'client' ? meta.customers[0]?.address || '' : '',
            requested_delivery_date: '',
            payment_type: meta.app === 'client' ? 'credit' : 'cash',
            notes: '',
            promotion_title: '',
            price_type_id: '',
            order_discount: '',
            items: [],
        });
        setWizardStep(1);
        setProductSearch('');
    };

    const toggleMode = () => {
        if (mode === 'form') {
            if (onShowList) onShowList();
            else setMode('list');
            return;
        }

        if (onShowForm) onShowForm();
        else setMode('form');
    };

    const updateItem = (index, patch) => {
        setForm((current) => ({ ...current, items: current.items.map((item, itemIndex) => itemIndex === index ? { ...item, ...patch } : item) }));
    };

    const updateProductRow = (productId, patch) => {
        setForm((current) => {
            const rowIndex = current.items.findIndex((item) => Number(item.product_id) === Number(productId));
            if (rowIndex < 0) {
                return { ...current, items: [...current.items, { ...blankItem, product_id: productId, quantity: 0, foc_quantity: 0, remarks: '', ...patch }] };
            }
            return { ...current, items: current.items.map((item, index) => index === rowIndex ? { ...item, ...patch } : item) };
        });
    };

    const selectMobileCustomer = (customerId) => {
        const customer = meta.customers.find((item) => Number(item.id) === Number(customerId));
        setForm((current) => ({
            ...current,
            customer_id: customerId,
            price_type_id: defaultPriceTypeId(customer, meta.price_types),
            area_id: customer?.area_id || current.area_id,
            route_id: customer?.route_id || current.route_id,
            recipient_name: customer?.shop_name || '',
            recipient_phone: customer?.phone || '',
            delivery_address: customer?.address || '',
            payment_type: customer ? current.payment_type : 'cash',
        }));
    };

    const selectMobileArea = (areaId) => {
        const route = meta.routes.find((item) => Number(item.area_id) === Number(areaId));
        setForm((current) => ({ ...current, area_id: areaId, route_id: route?.id || '' }));
    };

    const submit = async (event) => {
        event.preventDefault();
        if (wizardStep < 3) {
            if (wizardStep === 1 && basicStepComplete) setWizardStep(2);
            if (wizardStep === 2 && productStepComplete) setWizardStep(3);
            return;
        }
        const saveAs = event.nativeEvent.submitter?.value === 'draft' ? 'draft' : 'pending';
        setSaving(true);
        setState((current) => ({ ...current, error: '' }));

        try {
            const payload = {
                customer_id: form.customer_id ? Number(form.customer_id) : null,
                area_id: form.area_id ? Number(form.area_id) : null,
                route_id: form.route_id ? Number(form.route_id) : null,
                price_type_id: priceTypeId ? Number(priceTypeId) : undefined,
                recipient_name: form.recipient_name,
                recipient_phone: form.recipient_phone || null,
                delivery_address: form.delivery_address,
                order_date: today,
                requested_delivery_date: form.requested_delivery_date || null,
                notes: [form.notes?.trim(), form.promotion_title?.trim() ? `Promotion: ${form.promotion_title.trim()}` : ''].filter(Boolean).join('\n') || null,
                save_as: saveAs,
                items: meta.app === 'sales'
                    ? distributeOrderDiscount(form.items, meta.products, priceTypeId, form.order_discount).flatMap((item) => {
                        const rows = [];
                        if (Number(item.quantity || 0) > 0) rows.push({ product_id: Number(item.product_id), quantity: Number(item.quantity), item_type: 'sale', discount_amount: Number(item.allocated_discount || 0) });
                        if (Number(item.foc_quantity || 0) > 0) rows.push({ product_id: Number(item.product_id), quantity: Number(item.foc_quantity), item_type: 'foc', discount_amount: 0 });
                        return rows;
                    })
                    : form.items.filter((item) => item.product_id).map((item) => ({
                        product_id: Number(item.product_id),
                        quantity: Number(item.quantity || 0),
                        item_type: item.item_type || 'sale',
                        discount_amount: Number(item.discount_amount || 0),
                    })),
            };
            const { data } = editOrderId
                ? await window.axios.put(`${apiBase('mobileOrders')}/${editOrderId}`, payload)
                : await window.axios.post(apiBase('mobileOrders'), payload);
            const savedOrder = data.data.order;
            setState((current) => {
                const previous = current.items.find((item) => item.id === savedOrder.id);
                const exists = Boolean(previous);
                const items = exists ? current.items.map((item) => item.id === savedOrder.id ? { ...item, ...savedOrder } : item) : [savedOrder, ...current.items];
                const summary = { ...current.summary, orders_count: Number(current.summary.orders_count || 0) + (exists ? 0 : 1), total_value: Number(current.summary.total_value || 0) - Number(previous?.total || 0) + Number(savedOrder.total || 0) };
                return { ...current, items, summary, pageMeta: { ...current.pageMeta, total: Number(current.pageMeta.total || 0) + (exists ? 0 : 1) } };
            });
            resetForm();
            setMode('list');
            onViewOrder?.(data.data.order.id);
        } catch (error) {
            setState((current) => ({ ...current, error: requestMessage(error, locale) }));
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="mobile-master-stack mobile-order-workspace">
            {mode === 'form' && <ShellBackButton label={wizardStep > 1 ? 'Previous step' : t(locale, 'cancel')} onClick={() => wizardStep > 1 ? setWizardStep((current) => current - 1) : toggleMode()} />}
            <div className="mobile-master-heading">
                <div>
                    <p className="eyebrow">{t(locale, 'orders')}</p>
                    <h1>{mode === 'form' ? (editOrderId ? t(locale, 'editOrder') : t(locale, 'newOrder')) : t(locale, 'orderHistory')}</h1>
                    <span className="muted">{meta.app === 'sales' ? t(locale, 'customers') : t(locale, 'mobileOrdersHint')}</span>
                </div>
            </div>

            <section className="mobile-order-summary">
                <div><small>{t(locale, 'orders')}</small><strong>{Number(state.summary.orders_count || 0)}</strong></div>
                <div><small>{meta.app === 'client' ? 'In delivery' : t(locale, 'pending')}</small><strong>{Number(meta.app === 'client' ? state.summary.active_delivery_count || 0 : state.summary.pending_count || 0)}</strong></div>
                <div><small>{meta.app === 'client' ? t(locale, 'delivered') : t(locale, 'totalAmount')}</small><strong>{meta.app === 'client' ? Number(state.summary.delivered_count || 0) : money(state.summary.total_amount)}</strong></div>
            </section>

            {(state.error || meta.error) && <div className="inline-error"><AlertCircle size={15} /> {state.error || meta.error}</div>}

            {mode === 'form' ? (
                <form className="mobile-order-form" onSubmit={submit}>
                    <>
                        <nav className="sales-order-wizard-steps" aria-label="Order creation progress">
                            {(meta.app === 'sales' ? [
                                [1, 'Basic information'],
                                [2, 'Products & FOC'],
                                [3, 'Promotion & review'],
                            ] : [
                                [1, 'Delivery information'],
                                [2, 'Products'],
                                [3, 'Review & submit'],
                            ]).map(([step, label]) => <button className={wizardStep === step ? 'is-current' : wizardStep > step ? 'is-complete' : ''} type="button" key={step} disabled={(step === 2 && !basicStepComplete) || (step === 3 && (!basicStepComplete || !productStepComplete))} onClick={() => setWizardStep(step)}><span>{wizardStep > step ? <Check size={13} /> : step}</span><small>{label}</small></button>)}
                        </nav>

                        {wizardStep === 1 && <section className="sales-order-wizard-panel">
                            <header><span>Step 1 of 3</span><h2>{meta.app === 'sales' ? 'Basic information' : 'Delivery information'}</h2><p>{meta.app === 'sales' ? 'Select the customer and confirm the delivery details.' : 'Enter the delivery address. Office staff will assign the service area and route after submission.'}</p></header>
                            {meta.app === 'sales' && <div className="mobile-order-customer-field"><span>{t(locale, 'optionalCustomer')}</span><OrderCustomerCombobox customers={meta.customers} value={form.customer_id} locale={locale} required={false} onChange={selectMobileCustomer} /></div>}
                            <div className="mobile-order-destination">
                                {meta.app === 'sales' && <label>{t(locale, 'area')}<select required value={form.area_id} onChange={(event) => selectMobileArea(event.target.value)}><option value="">{t(locale, 'area')}</option>{meta.areas.map((area) => <option key={area.id} value={area.id}>{area.code} - {area.name}</option>)}</select></label>}
                                {meta.app === 'sales' && <label>{t(locale, 'route')}<select required value={form.route_id} onChange={(event) => setForm((current) => ({ ...current, route_id: event.target.value }))}><option value="">{t(locale, 'route')}</option>{meta.routes.filter((route) => Number(route.area_id) === Number(form.area_id)).map((route) => <option key={route.id} value={route.id}>{route.code} - {route.name}</option>)}</select></label>}
                                <label>{t(locale, 'recipientName')}<input required maxLength="150" value={form.recipient_name} onChange={(event) => setForm((current) => ({ ...current, recipient_name: event.target.value }))} /></label>
                                <label>{t(locale, 'recipientPhone')}<input type="tel" maxLength="50" value={form.recipient_phone} onChange={(event) => setForm((current) => ({ ...current, recipient_phone: event.target.value }))} /></label>
                                {meta.app === 'sales' && <label className="wide">{t(locale, 'priceType')}<select value={priceTypeId} onChange={(event) => setForm((current) => ({ ...current, price_type_id: event.target.value }))}>{meta.price_types.map((priceType) => <option key={priceType.id} value={priceType.id}>{priceType.code} · {priceType.name}</option>)}</select></label>}
                                <label className="wide">{t(locale, 'address')}<textarea required rows="3" maxLength="500" value={form.delivery_address} onChange={(event) => setForm((current) => ({ ...current, delivery_address: event.target.value }))} /></label>
                                <label className="wide">{t(locale, 'requestedDelivery')}<input type="date" value={form.requested_delivery_date} onChange={(event) => setForm((current) => ({ ...current, requested_delivery_date: event.target.value }))} /></label>
                                <label className="wide">{t(locale, 'notes')}<textarea rows="3" maxLength="500" value={form.notes} onChange={(event) => setForm((current) => ({ ...current, notes: event.target.value }))} /></label>
                            </div>
                            <div className="sales-order-wizard-actions"><button className="button primary" type="button" disabled={!basicStepComplete} onClick={() => setWizardStep(2)}>Continue to products</button></div>
                        </section>}

                        {wizardStep === 2 && <section className="sales-order-wizard-panel">
                            <header><span>Step 2 of 3</span><h2>{meta.app === 'sales' ? 'Product quantities & FOC' : 'Product quantities'}</h2><p>Enter quantities directly in the grid. Leave unused products blank.</p></header>
                            <label className="sales-product-search"><Search size={15} /><input type="search" value={productSearch} placeholder="Search SKU or product" onChange={(event) => setProductSearch(event.target.value)} /></label>
                            <div className="sales-product-grid-wrap">
                                <table className="sales-product-grid">
                                    <thead><tr><th>Product</th><th>Qty</th>{meta.app === 'sales' && <th>FOC</th>}</tr></thead>
                                    <tbody>{visibleProducts.map((product) => {
                                        const row = form.items.find((item) => Number(item.product_id) === Number(product.id));
                                        return <tr className={Number(row?.quantity || 0) > 0 || Number(row?.foc_quantity || 0) > 0 ? 'has-value' : ''} key={product.id}>
                                            <td><strong>{product.name}</strong><small><span>{product.sku} · {product.unit}</span><b>{money(priceForProduct(product, priceTypeId))}</b></small></td>
                                            <td className="editable"><input aria-label={`${product.name} quantity`} type="number" min="0" step="1" inputMode="numeric" value={row?.quantity || ''} onChange={(event) => updateProductRow(product.id, { quantity: event.target.value })} /></td>
                                            {meta.app === 'sales' && <td className="editable foc"><input aria-label={`${product.name} FOC quantity`} type="number" min="0" step="1" inputMode="numeric" value={row?.foc_quantity || ''} onChange={(event) => updateProductRow(product.id, { foc_quantity: event.target.value })} /></td>}
                                        </tr>;
                                    })}</tbody>
                                </table>
                            </div>
                            <div className="sales-product-grid-summary"><span>{selectedProductRows.length} products selected</span><strong>{meta.app === 'sales' ? `${Number(preview.saleQuantity || 0).toLocaleString()} sale · ${Number(preview.focQuantity || 0).toLocaleString()} FOC` : `${selectedProductRows.reduce((sum, item) => sum + Number(item.quantity || 0), 0).toLocaleString()} total quantity`}</strong></div>
                            <div className="sales-order-wizard-actions"><button className="button primary" type="button" disabled={!productStepComplete} onClick={() => setWizardStep(3)}>Continue to review</button></div>
                        </section>}

                        {wizardStep === 3 && <section className="sales-order-wizard-panel">
                            <header><span>Step 3 of 3</span><h2>{meta.app === 'sales' ? 'Promotion, discount & review' : 'Review & submit'}</h2><p>{meta.app === 'sales' ? 'Add an order promotion title and discount, then review the final order.' : 'Confirm the delivery details and product quantities before submitting the order.'}</p></header>
                            {meta.app === 'sales' && <div className="sales-order-promotion-fields">
                                <label>Promotion title<input maxLength="150" value={form.promotion_title} placeholder="Optional campaign or promotion" onChange={(event) => setForm((current) => ({ ...current, promotion_title: event.target.value }))} /></label>
                                <label>Order discount (MMK)<input type="number" min="0" max={preview.subtotal} step="1" inputMode="decimal" value={form.order_discount} placeholder="0" onChange={(event) => setForm((current) => ({ ...current, order_discount: event.target.value }))} /></label>
                            </div>}
                            <section className="sales-order-final-review">
                                <header><strong>Final review</strong><span>{selectedCustomer?.shop_name || form.recipient_name}</span></header>
                                <dl><div><dt>Destination</dt><dd>{form.delivery_address}</dd></div><div><dt>Products</dt><dd>{selectedProductRows.length}</dd></div><div><dt>Sale quantity</dt><dd>{Number(preview.saleQuantity || selectedProductRows.reduce((sum, item) => sum + Number(item.quantity || 0), 0)).toLocaleString()}</dd></div>{meta.app === 'sales' && <div><dt>FOC quantity</dt><dd>{Number(preview.focQuantity || 0).toLocaleString()}</dd></div>}<div><dt>Subtotal</dt><dd>{money(preview.subtotal)}</dd></div>{meta.app === 'sales' && <div><dt>Discount</dt><dd>-{money(preview.discount)}</dd></div>}<div className="total"><dt>Order total</dt><dd>{money(preview.total)}</dd></div></dl>
                            </section>
                            <div className="sales-order-wizard-actions split two"><button className="button" type="submit" value="draft" disabled={saving || meta.loading}><Save size={16} /> {t(locale, 'saveDraft')}</button><button className="button primary" type="submit" value="pending" disabled={saving || meta.loading}><Save size={16} /> {editOrderId ? t(locale, 'updateOrder') : t(locale, 'saveMobileOrder')}</button></div>
                        </section>}
                    </>


                </form>
            ) : (
                <section className="mobile-master-section">
                    <div className="mobile-order-filters">
                        <label className="mobile-search"><Search size={16} /><input value={filters.search} placeholder={t(locale, 'searchOrders')} onChange={(event) => setFilters((current) => ({ ...current, search: event.target.value }))} /></label>
                        <label className="mobile-order-filter-field"><span>Status</span><select aria-label={t(locale, 'status')} value={filters.status} onChange={(event) => setFilters((current) => ({ ...current, status: event.target.value }))}><option value="">{t(locale, 'allStatuses')}</option>{['draft', 'pending', 'confirmed', 'invoiced', 'assigned', 'delivering', 'delivered', 'cancelled'].map((status) => <option value={status} key={status}>{t(locale, status)}</option>)}</select></label>
                        <label className="mobile-order-filter-field"><span>Payment type</span><select aria-label="Payment type" value={filters.payment_type} onChange={(event) => setFilters((current) => ({ ...current, payment_type: event.target.value }))}><option value="">All payment types</option><option value="unsettled">Set at delivery</option><option value="cash">Cash</option><option value="credit">Credit</option></select></label>
                        <label className="mobile-order-filter-field"><span>Order date from</span><input aria-label="Order date from" type="date" value={filters.date_from} max={filters.date_to} onChange={(event) => setFilters((current) => ({ ...current, date_from: event.target.value }))} /></label>
                        <label className="mobile-order-filter-field"><span>Order date through</span><input aria-label="Order date through" type="date" value={filters.date_to} min={filters.date_from} onChange={(event) => setFilters((current) => ({ ...current, date_to: event.target.value }))} /></label>
                        <label className="mobile-order-filter-field"><span>Delivery date from</span><input aria-label="Requested delivery date from" type="date" value={filters.delivery_from} max={filters.delivery_to} onChange={(event) => setFilters((current) => ({ ...current, delivery_from: event.target.value }))} /></label>
                        <label className="mobile-order-filter-field"><span>Delivery date through</span><input aria-label="Requested delivery date through" type="date" value={filters.delivery_to} min={filters.delivery_from} onChange={(event) => setFilters((current) => ({ ...current, delivery_to: event.target.value }))} /></label>
                        <label className="mobile-order-filter-field"><span>Minimum total (MMK)</span><input aria-label="Minimum order total" type="number" min="0" step="1" inputMode="numeric" value={filters.min_total} onChange={(event) => setFilters((current) => ({ ...current, min_total: event.target.value }))} /></label>
                        <label className="mobile-order-filter-field"><span>Maximum total (MMK)</span><input aria-label="Maximum order total" type="number" min={filters.min_total || 0} step="1" inputMode="numeric" value={filters.max_total} onChange={(event) => setFilters((current) => ({ ...current, max_total: event.target.value }))} /></label>
                        <button className="button" type="button" onClick={() => setFilters(emptyFilters)}>Clear filters</button>
                    </div>
                    {state.loading ? <WorkspaceState icon={RefreshCw} title={t(locale, 'loading')} loading compact /> : state.items.length === 0 ? <WorkspaceState icon={ShoppingCart} title={t(locale, 'empty')} compact /> : (
                        <div className="mobile-order-list">
                            {state.items.map((order) => (
                                <button type="button" key={order.id} onClick={() => onViewOrder?.(order.id)}>
                                    <span className="mobile-order-icon"><ShoppingCart size={17} /></span>
                                    <span><strong>{order.code} · {money(order.total)}</strong><small>{order.shop_name}</small><small>{order.delivery ? `${order.delivery.code} · ${order.delivery.planned_date || 'Date pending'}${order.delivery.driver_name ? ` · ${order.delivery.driver_name}` : ''}` : `${order.order_date} · ${t(locale, order.payment_type)}`}</small></span>
                                    <StatusBadge status={order.delivery?.status || order.status} locale={locale} />
                                </button>
                            ))}
                        </div>
                    )}
                </section>
            )}

            {['client', 'sales'].includes(meta.app) && mode !== 'form' && (
                <button className="mobile-order-fab" type="button" title={t(locale, 'newOrder')} aria-label={t(locale, 'newOrder')} onClick={toggleMode}>
                    <Plus size={24} aria-hidden="true" />
                </button>
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
                        <MobileOrderTimeline order={state.order} delivery={state.order.delivery} locale={locale} />
                        <MobileOrderItemsTable items={state.items} order={state.order} locale={locale} />
                        <dl className="mobile-info-list">
                            <div><dt>{t(locale, 'area')} / {t(locale, 'route')}</dt><dd>{state.order.area || '-'} / {state.order.route || '-'}</dd></div>
                            <div><dt>{t(locale, 'address')}</dt><dd>{state.order.delivery_address || '-'}</dd></div>
                            <div><dt>{t(locale, 'recipientPhone')}</dt><dd>{state.order.recipient_phone || '-'}</dd></div>
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

export function MobileOrderDetailPage({ appId, orderId, locale, onBack, onEdit, onRepeat }) {
    const [state, setState] = useState({ loading: true, order: null, items: [], error: '' });
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        let mounted = true;
        setState({ loading: true, order: null, items: [], error: '' });
        window.axios.get(`${apiBase('mobileOrders')}/${orderId}`)
            .then(({ data }) => mounted && setState({ loading: false, order: data.data.order, items: data.data.items, error: '' }))
            .catch((error) => mounted && setState({ loading: false, order: null, items: [], error: requestMessage(error, locale) }));
        return () => { mounted = false; };
    }, [locale, orderId]);

    const cancelMobileOrder = async () => {
        if (!window.confirm(t(locale, 'cancelOrderPrompt'))) return;
        setProcessing(true);
        try {
            const { data } = await window.axios.post(`${apiBase('mobileOrders')}/${orderId}/cancel`);
            setState((current) => ({ ...current, order: { ...current.order, ...data.data.order }, error: '' }));
        } catch (error) {
            setState((current) => ({ ...current, error: requestMessage(error, locale) }));
        } finally {
            setProcessing(false);
        }
    };

    return (
        <div className="mobile-master-stack mobile-order-detail-page">
            <ShellBackButton onClick={onBack} label={t(locale, 'cancel')} />
            {state.order && <ShellPageActions className="mobile-order-detail-bar-actions">
                <button className="icon-button" type="button" title={t(locale, 'repeatOrder')} aria-label={t(locale, 'repeatOrder')} onClick={() => onRepeat?.(state.order.id)}><Copy size={16} /></button>
                <button className="icon-button" type="button" title={t(locale, 'printOrder')} aria-label={t(locale, 'printOrder')} onClick={() => window.print()}><Printer size={16} /></button>
            </ShellPageActions>}
            <div className="mobile-master-heading">
                <div><p className="eyebrow">{t(locale, 'details')}</p><h1>{state.order?.code || t(locale, 'order')}</h1></div>
            </div>
            <section className="mobile-master-section mobile-order-detail-content">
                {state.error ? <WorkspaceState icon={AlertCircle} title={state.error} action={onBack} actionLabel={t(locale, 'cancel')} compact /> : state.loading ? <WorkspaceState icon={RefreshCw} title={t(locale, 'loading')} loading compact /> : (
                    <div>
                        <section className="mobile-order-detail-total">{appId !== 'client' && <small>{state.order.shop_name}</small>}<strong>{money(state.order.total)}</strong><span>{state.order.order_date} · <StatusBadge status={state.order.status} locale={locale} /></span></section>
                        {['draft', 'pending'].includes(state.order.status) && state.order.source_app === 'sales' && <div className="mobile-order-detail-actions"><button className="button" type="button" onClick={() => onEdit?.(state.order.id)}><Pencil size={14} />{t(locale, 'editOrder')}</button><button className="button danger" type="button" disabled={processing} onClick={cancelMobileOrder}><Ban size={14} />{t(locale, 'cancelOrder')}</button></div>}
                        <MobileOrderTimeline order={state.order} delivery={appId === 'client' ? state.order.delivery : null} locale={locale} />
                        <MobileOrderItemsTable items={state.items} order={state.order} locale={locale} />
                        <dl className="mobile-info-list">
                            <div><dt>{t(locale, 'area')} / {t(locale, 'route')}</dt><dd>{state.order.area || '-'} / {state.order.route || '-'}</dd></div>
                            <div><dt>{t(locale, 'address')}</dt><dd>{state.order.delivery_address || '-'}</dd></div>
                            <div><dt>{t(locale, 'recipientPhone')}</dt><dd>{state.order.recipient_phone || '-'}</dd></div>
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

function MobileOrderTimeline({ order, delivery, locale }) {
    const steps = [
        { key: 'pending', label: t(locale, 'submitted'), meta: order.order_date },
        { key: 'confirmed', label: t(locale, 'confirmed'), meta: order.confirmed_at ? formatDateTime(order.confirmed_at) : null },
        { key: 'loading', label: t(locale, 'loadingDelivery'), meta: delivery?.loaded_at ? formatDateTime(delivery.loaded_at) : null },
        { key: 'on_route', label: t(locale, 'on_route'), meta: delivery?.departed_at ? formatDateTime(delivery.departed_at) : null },
        { key: 'delivered', label: t(locale, 'delivered'), meta: delivery?.completed_at ? formatDateTime(delivery.completed_at) : null },
    ];
    const deliveryStage = { planned: 1, assigned: 1, loading: 2, on_route: 3, delivered: 4, partially_delivered: 4, failed: 3 };
    const orderStage = { draft: 0, pending: 0, confirmed: 1, invoiced: 1, assigned: 1, delivering: 3, delivered: 4 };
    const currentIndex = delivery ? (deliveryStage[delivery.status] ?? orderStage[order.status] ?? 0) : (orderStage[order.status] ?? 0);
    const displayStatus = delivery?.status || order.status;

    return (
        <section className="mobile-order-timeline" aria-label={t(locale, 'statusTimeline')}>
            <div><strong>{t(locale, 'statusTimeline')}</strong><StatusBadge status={displayStatus} locale={locale} /></div>
            <ol>
                {steps.map((step, index) => {
                    const state = order.status === 'cancelled'
                        ? (index === 0 ? 'done' : 'pending')
                        : index < currentIndex ? 'done' : index === currentIndex ? 'current' : 'pending';
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

function MobileOrderItemsTable({ items, order, locale }) {
    const rows = [...items.reduce((grouped, item) => {
        const key = item.product_id || item.product_sku;
        if (!grouped.has(key)) grouped.set(key, { ...item, quantity: 0, foc_quantity: 0, discount_amount: 0, line_total: 0 });
        const row = grouped.get(key);
        if (item.item_type === 'foc') row.foc_quantity += Number(item.quantity || 0);
        else {
            row.quantity += Number(item.quantity || 0);
            row.unit_price = Number(item.unit_price || 0);
            row.discount_amount += Number(item.discount_amount || 0);
            row.line_total += Number(item.line_total || 0);
        }
        return grouped;
    }, new Map()).values()];

    return (
        <section className="mobile-order-items-invoice">
            <div className="mobile-order-items-scroll">
                <table>
                    <caption><span>Order items</span><small>Amounts in MMK</small></caption>
                    <colgroup><col className="mobile-item-product-col" /><col className="mobile-item-qty-col" /><col className="mobile-item-foc-col" /><col className="mobile-item-money-col" /><col className="mobile-item-money-col" /><col className="mobile-item-money-col" /></colgroup>
                    <thead><tr><th>{t(locale, 'product')}</th><th>Qty</th><th>{t(locale, 'foc')}</th><th>{t(locale, 'price')}</th><th>Disc.</th><th>{t(locale, 'total')}</th></tr></thead>
                    <tbody>{rows.map((item) => <tr key={item.product_id || item.product_sku}><td><strong>{item.product_name}</strong><small>{item.product_sku} · {item.unit}</small></td><td>{Number(item.quantity).toLocaleString()}</td><td className={Number(item.foc_quantity) > 0 ? 'is-foc-value' : ''}>{Number(item.foc_quantity).toLocaleString()}</td><td>{Number(item.unit_price || 0).toLocaleString()}</td><td>{Number(item.discount_amount || 0).toLocaleString()}</td><td>{Number(item.line_total || 0).toLocaleString()}</td></tr>)}</tbody>
                </table>
            </div>
            <dl className="mobile-order-invoice-totals">
                <div><dt>{t(locale, 'subtotal')}</dt><dd>{money(order.subtotal)}</dd></div>
                {Number(order.discount_total || 0) > 0 && <div><dt>Discount</dt><dd>-{money(order.discount_total)}</dd></div>}
                <div className="is-total"><dt>{t(locale, 'total')}</dt><dd>{money(order.total)}</dd></div>
            </dl>
        </section>
    );
}

export function SalesReturnScreen({ locale, canManage = false, creating = false, detailId = null, onNavigate }) {
    const listPath = `${window.ValleyRuntime?.routes?.office || '/office'}/returns`;
    if (creating) return <SalesReturnCreatePage locale={locale} canManage={canManage} onBack={() => onNavigate?.(listPath)} onSaved={(id) => onNavigate?.(`${listPath}/${id}`)} />;

    return (
        <AdjustmentScreen
            type="sales_return"
            title={t(locale, 'salesReturns')}
            hint={t(locale, 'returnsHint')}
            icon={RotateCcw}
            actionLabel={t(locale, 'newReturn')}
            locale={locale}
            canManage={canManage}
            detailId={detailId}
            onNavigate={onNavigate}
            listPath={listPath}
        />
    );
}

export function DamageEntryScreen({ locale, canManage = false, detailId = null, onNavigate }) {
    return (
        <AdjustmentScreen
            type="damage"
            title={t(locale, 'damageEntries')}
            hint={t(locale, 'damageHint')}
            icon={TriangleAlert}
            actionLabel={t(locale, 'newDamage')}
            locale={locale}
            canManage={canManage}
            detailId={detailId}
            onNavigate={onNavigate}
            listPath={`${window.ValleyRuntime?.routes?.office || '/office'}/damage`}
        />
    );
}

function ReturnOrderCombobox({ orders, value, onChange }) {
    const selected = orders.find((order) => String(order.id) === String(value));
    const displayValue = selected?.label || '';
    const [query, setQuery] = useState(displayValue);
    const [open, setOpen] = useState(false);
    const [activeIndex, setActiveIndex] = useState(0);
    const rootRef = useRef(null);
    const inputRef = useRef(null);
    const optionsId = useId();
    const normalizedQuery = query.trim().toLocaleLowerCase();
    const filtered = orders
        .filter((order) => {
            if (!normalizedQuery) return true;
            return [order.code, order.customer_code, order.shop_name, order.recipient_name, order.payment_type, order.order_date]
                .filter(Boolean)
                .join(' ')
                .toLocaleLowerCase()
                .includes(normalizedQuery);
        })
        .slice(0, 30);

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

    const choose = (order) => {
        onChange(String(order.id));
        setQuery(order.label);
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

    return <div className="customer-combobox return-order-combobox" ref={rootRef}>
        <div className="customer-combobox-input">
            <Search size={16} />
            <input
                ref={inputRef}
                role="combobox"
                aria-autocomplete="list"
                aria-controls={optionsId}
                aria-expanded={open}
                aria-activedescendant={open && filtered[activeIndex] ? `${optionsId}-${filtered[activeIndex].id}` : undefined}
                required
                value={query}
                placeholder="Search order number, customer, payment or date"
                onFocus={(event) => {
                    setActiveIndex(0);
                    setOpen(true);
                    event.currentTarget.select();
                }}
                onKeyDown={keyDown}
                onInvalid={(event) => event.currentTarget.setCustomValidity(value ? '' : 'Select a delivered order from the search results.')}
                onChange={(event) => {
                    event.currentTarget.setCustomValidity('Select a delivered order from the search results.');
                    setQuery(event.target.value);
                    onChange('');
                    setActiveIndex(0);
                    setOpen(true);
                }}
            />
        </div>
        {open && <div className="customer-combobox-options" id={optionsId} role="listbox">
            {filtered.length ? filtered.map((order, index) => <button
                id={`${optionsId}-${order.id}`}
                className={index === activeIndex ? 'is-active' : ''}
                type="button"
                role="option"
                aria-selected={String(order.id) === String(value)}
                key={order.id}
                onMouseDown={(event) => event.preventDefault()}
                onMouseEnter={() => setActiveIndex(index)}
                onClick={() => choose(order)}
            >
                <span>
                    <strong>{order.code}</strong>
                    <small>{order.shop_name || order.recipient_name}{order.customer_code ? ` · ${order.customer_code}` : ''}</small>
                    <small>{order.order_date} · {order.payment_type === 'credit' ? 'Credit sale' : 'Cash sale'}</small>
                </span>
                {String(order.id) === String(value) && <BadgeCheck size={16} />}
            </button>) : <p>No delivered orders match “{query}”.</p>}
            {orders.length > 30 && !normalizedQuery && <small className="customer-combobox-hint">Type to search {orders.length} delivered orders</small>}
        </div>}
    </div>;
}

function SalesReturnCreatePage({ locale, canManage, onBack, onSaved }) {
    const today = new Date().toISOString().slice(0, 10);
    const [meta, setMeta] = useState({ loading: true, warehouses: [], returnable_orders: [], error: '' });
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [form, setForm] = useState({ original_order_id: '', return_warehouse_id: '', return_settlement_method: 'customer_credit', entry_date: today, notes: '', items: [] });

    useEffect(() => {
        let mounted = true;
        window.axios.get(`${apiBase('orderAdjustments')}/meta`)
            .then(({ data }) => {
                if (!mounted) return;
                setMeta({ loading: false, warehouses: data.data.warehouses || [], returnable_orders: data.data.returnable_orders || [], error: '' });
            })
            .catch((requestError) => mounted && setMeta({ loading: false, warehouses: [], returnable_orders: [], error: requestMessage(requestError, locale) }));
        return () => { mounted = false; };
    }, [locale]);

    const selectedOrder = useMemo(() => meta.returnable_orders.find((order) => Number(order.id) === Number(form.original_order_id)), [form.original_order_id, meta.returnable_orders]);
    const preview = useMemo(() => form.items.reduce((total, item) => total + ((Number(item.good_quantity || 0) + Number(item.damaged_quantity || 0)) * Number(item.net_unit_price || 0)), 0), [form.items]);
    const outstanding = Number(selectedOrder?.outstanding_amount || 0);
    const balanceReduction = selectedOrder?.payment_type === 'credit' ? Math.min(preview, outstanding) : 0;
    const excessCredit = selectedOrder?.payment_type === 'credit' ? Math.max(preview - outstanding, 0) : (form.return_settlement_method === 'customer_credit' ? preview : 0);
    const refundAmount = form.return_settlement_method === 'customer_credit' ? 0 : (selectedOrder?.payment_type === 'credit' ? excessCredit : preview);

    const chooseOrder = (orderId) => {
        const order = meta.returnable_orders.find((candidate) => Number(candidate.id) === Number(orderId));
        setError('');
        setForm((current) => ({
            ...current,
            original_order_id: orderId,
            return_warehouse_id: order?.delivery_warehouse_id || meta.warehouses[0]?.id || '',
            return_settlement_method: order?.payment_type === 'cash' ? 'cash_refund' : 'customer_credit',
            items: (order?.items || []).map((item) => ({ ...item, original_order_item_id: item.id, good_quantity: 0, damaged_quantity: 0, remarks: '' })),
        }));
    };

    const updateItem = (index, field, value) => {
        setForm((current) => ({ ...current, items: current.items.map((item, itemIndex) => {
            if (itemIndex !== index) return item;
            const next = { ...item, [field]: value };
            const total = Number(next.good_quantity || 0) + Number(next.damaged_quantity || 0);
            if (total > Number(next.returnable_quantity || 0)) {
                const otherField = field === 'good_quantity' ? 'damaged_quantity' : 'good_quantity';
                next[field] = Math.max(Number(next.returnable_quantity || 0) - Number(next[otherField] || 0), 0);
            }
            return next;
        }) }));
    };

    const submit = async (event) => {
        event.preventDefault();
        if (!canManage || saving) return;
        setSaving(true);
        setError('');
        try {
            const { data } = await window.axios.post(apiBase('orderAdjustments'), {
                type: 'sales_return',
                original_order_id: Number(form.original_order_id),
                return_warehouse_id: Number(form.return_warehouse_id),
                return_settlement_method: form.return_settlement_method,
                entry_date: form.entry_date,
                notes: form.notes || null,
                items: form.items.map((item) => ({
                    original_order_item_id: Number(item.original_order_item_id),
                    good_quantity: Number(item.good_quantity || 0),
                    damaged_quantity: Number(item.damaged_quantity || 0),
                    remarks: item.remarks || null,
                })),
            });
            onSaved(data.data.record.id);
        } catch (requestError) {
            setError(requestMessage(requestError, locale));
            setSaving(false);
        }
    };

    return (
        <section className="master-workspace return-create-page">
            <ShellBackButton onClick={onBack} label="Back to sales returns" />
            <div className="master-heading">
                <div><p className="eyebrow">Sales return</p><h1>New sales return</h1><span className="muted">Return products from an original delivered order and settle the customer balance.</span></div>
            </div>

            {(error || meta.error) && <div className="inline-error"><AlertCircle size={15} /> {error || meta.error}</div>}
            {meta.loading ? <WorkspaceState icon={RefreshCw} title={t(locale, 'loading')} loading compact /> : meta.returnable_orders.length === 0 ? (
                <div className="master-panel"><WorkspaceState icon={RotateCcw} title="No delivered orders have returnable products." /></div>
            ) : (
                <form onSubmit={submit}>
                    <div className="master-panel return-source-panel">
                        <div className="master-panel-heading"><div><p className="eyebrow">Original sale</p><h2>Select delivered order</h2><span className="muted">Products and credited prices are taken from the original order.</span></div></div>
                        <div className="master-form-grid return-source-grid">
                            <label className="master-field wide"><span>Delivered order <b>*</b></span><ReturnOrderCombobox orders={meta.returnable_orders} value={form.original_order_id} onChange={chooseOrder} /></label>
                            <label className="master-field"><span>Return date <b>*</b></span><input required type="date" value={form.entry_date} onChange={(event) => setForm((current) => ({ ...current, entry_date: event.target.value }))} /></label>
                            <label className="master-field"><span>Return warehouse <b>*</b></span><select required value={form.return_warehouse_id} onChange={(event) => setForm((current) => ({ ...current, return_warehouse_id: event.target.value }))}><option value="">Select warehouse</option>{meta.warehouses.map((warehouse) => <option key={warehouse.id} value={warehouse.id}>{warehouse.code} - {warehouse.name}</option>)}</select></label>
                        </div>
                        {selectedOrder && <div className="return-order-strip"><span><small>Customer</small><strong>{selectedOrder.shop_name}</strong></span><span><small>Payment</small><strong>{selectedOrder.payment_type === 'credit' ? 'Credit sale' : 'Cash sale'}</strong></span><span><small>Order date</small><strong>{selectedOrder.order_date}</strong></span><span><small>Outstanding</small><strong>{money(outstanding)}</strong></span></div>}
                    </div>

                    {selectedOrder && <>
                        <div className="master-panel return-items-panel">
                            <div className="master-panel-heading"><div><p className="eyebrow">Returned products</p><h2>Quantity and condition</h2><span className="muted">Good items go back into saleable stock. Damaged items are recorded but do not increase stock.</span></div></div>
                            <div className="master-table-wrap">
                                <table className="master-table return-entry-table">
                                    <thead><tr><th>Product</th><th>Sold as</th><th>Available</th><th>Good qty</th><th>Damaged qty</th><th>Credit value</th></tr></thead>
                                    <tbody>{form.items.map((item, index) => {
                                        const rowQuantity = Number(item.good_quantity || 0) + Number(item.damaged_quantity || 0);
                                        return <tr key={item.original_order_item_id}>
                                            <td><strong>{item.product_name}</strong><span className="muted">{item.product_sku} · {item.unit}</span></td>
                                            <td><span className={`status ${item.item_type === 'foc' ? 'neutral' : 'info'}`}>{item.item_type === 'foc' ? 'FOC' : 'Sale'}</span></td>
                                            <td className="numeric">{Number(item.returnable_quantity).toLocaleString()}</td>
                                            <td className="editable-cell"><input aria-label={`Good quantity for ${item.product_name}`} type="number" inputMode="numeric" min="0" max={item.returnable_quantity} step="1" value={item.good_quantity} onChange={(event) => updateItem(index, 'good_quantity', event.target.value)} /></td>
                                            <td className="editable-cell"><input aria-label={`Damaged quantity for ${item.product_name}`} type="number" inputMode="numeric" min="0" max={item.returnable_quantity} step="1" value={item.damaged_quantity} onChange={(event) => updateItem(index, 'damaged_quantity', event.target.value)} /></td>
                                            <td className="numeric"><strong>{money(rowQuantity * Number(item.net_unit_price || 0))}</strong><span className="muted">{money(item.net_unit_price)} each</span></td>
                                        </tr>;
                                    })}</tbody>
                                </table>
                            </div>
                        </div>

                        <div className="return-settlement-layout">
                            <div className="master-panel">
                                <div className="master-panel-heading"><div><p className="eyebrow">Settlement</p><h2>How should the value be handled?</h2></div></div>
                                <div className="return-settlement-fields">
                                    <label className="master-field"><span>Settlement method <b>*</b></span><select required value={form.return_settlement_method} onChange={(event) => setForm((current) => ({ ...current, return_settlement_method: event.target.value }))}>
                                        <option value="customer_credit">{selectedOrder.payment_type === 'credit' ? 'Reduce outstanding / keep excess as credit' : 'Keep as customer credit'}</option>
                                        <option value="cash_refund">Refund by cash</option>
                                        <option value="bank_refund">Refund by bank</option>
                                    </select></label>
                                    <label className="master-field"><span>Notes</span><textarea rows="3" maxLength="500" value={form.notes} onChange={(event) => setForm((current) => ({ ...current, notes: event.target.value }))} /></label>
                                </div>
                            </div>
                            <aside className="master-panel return-settlement-summary">
                                <div className="master-panel-heading"><div><p className="eyebrow">Return summary</p><h2>{money(preview)}</h2></div></div>
                                <dl><div><dt>Outstanding reduced</dt><dd>{money(balanceReduction)}</dd></div><div><dt>Customer credit kept</dt><dd>{money(form.return_settlement_method === 'customer_credit' ? excessCredit : 0)}</dd></div><div><dt>Refund now</dt><dd>{money(refundAmount)}</dd></div><div><dt>Saleable stock</dt><dd>{form.items.reduce((sum, item) => sum + Number(item.good_quantity || 0), 0).toLocaleString()}</dd></div><div><dt>Damaged stock</dt><dd>{form.items.reduce((sum, item) => sum + Number(item.damaged_quantity || 0), 0).toLocaleString()}</dd></div></dl>
                            </aside>
                        </div>

                        <div className="return-form-actions"><button className="button" type="button" onClick={onBack}>Cancel</button><button className="button primary" type="submit" disabled={!canManage || saving || preview <= 0}><Save size={15} /> {saving ? 'Saving…' : 'Record return'}</button></div>
                    </>}
                </form>
            )}
        </section>
    );
}

function AdjustmentScreen({ type, title, hint, icon: Icon, actionLabel, locale, canManage, detailId, onNavigate, listPath }) {
    const today = new Date().toISOString().slice(0, 10);
    const blankAdjustmentItem = { product_id: '', quantity: 1, unit_price: '', remarks: '' };
    const [filters, setFilters] = useState({ search: '', date: '', customer_id: '' });
    const [meta, setMeta] = useState({ loading: true, customers: [], products: [], error: '' });
    const [state, setState] = useState({ loading: true, items: [], summary: {}, pageMeta: {}, error: '' });
    const [page, setPage] = useState(1);
    const [refreshKey, setRefreshKey] = useState(0);
    const [formOpen, setFormOpen] = useState(false);
    const [saving, setSaving] = useState(false);
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
        if (type === 'sales_return') {
            onNavigate?.(`${listPath}/new`);
            return;
        }
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
            const record = data.data.record;
            setState((current) => ({ ...current, items: [record, ...current.items], summary: { ...current.summary, records_count: Number(current.summary.records_count || 0) + 1, total_quantity: Number(current.summary.total_quantity || 0) + Number(record.total_quantity || 0), total_value: Number(current.summary.total_value || 0) + Number(record.total || 0) }, pageMeta: { ...current.pageMeta, total: Number(current.pageMeta.total || 0) + 1 } }));
            closeForm();
            onNavigate?.(`${listPath}/${data.data.record.id}`);
        } catch (error) {
            setState((current) => ({ ...current, error: requestMessage(error, locale) }));
            setSaving(false);
        }
    };

    if (detailId) return <AdjustmentDetailPage id={detailId} type={type} title={title} icon={Icon} locale={locale} onBack={() => onNavigate?.(listPath)} />;

    return (
        <section className="master-workspace adjustment-workspace">
            <div className="master-heading">
                <div>
                    <p className="eyebrow">{t(locale, type)}</p>
                    <h1>{title}</h1>
                    <span className="muted">{hint}</span>
                </div>
                <ShellPageActions>{canManage && <button className="button primary" type="button" disabled={meta.loading} onClick={openForm}><Plus size={16} /> {actionLabel}</button>}</ShellPageActions>
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
                            <thead><tr><th>{t(locale, 'adjustmentRecord')}</th><th>{t(locale, 'customer')}</th><th>{t(locale, 'entryDate')}</th><th>{t(locale, 'quantity')}</th><th>{t(locale, 'value')}</th><th>{t(locale, 'status')}</th></tr></thead>
                            <tbody>
                                {state.items.map((record) => (
                                    <tr className="clickable-row" key={record.id} tabIndex={0} onClick={() => onNavigate?.(`${listPath}/${record.id}`)} onKeyDown={(event) => { if (event.target === event.currentTarget && ['Enter', ' '].includes(event.key)) { event.preventDefault(); onNavigate?.(`${listPath}/${record.id}`); } }}>
                                        <td><strong>{record.code}</strong><span className="muted">{t(locale, 'updated')}: {formatDateTime(record.updated_at)}</span></td>
                                        <td><strong>{record.shop_name}</strong><span className="muted">{record.customer_code} · {record.route || '-'}</span></td>
                                        <td>{record.entry_date}</td>
                                        <td className="numeric">{Number(record.quantity_total || 0).toLocaleString()}</td>
                                        <td className="numeric">{money(record.total)}</td>
                                        <td><StatusBadge status={record.status} locale={locale} /></td>
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
                                        <label className="master-field"><span>{t(locale, 'quantity')}</span><input required type="number" inputMode="numeric" min="1" step="1" value={item.quantity} onChange={(event) => updateItem(index, { quantity: event.target.value })} /></label>
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

        </section>
    );
}

function AdjustmentDetailPage({ id, type, title, icon: Icon, locale, onBack }) {
    const [state, setState] = useState({ loading: true, record: {}, items: [], error: '' });

    useEffect(() => {
        let mounted = true;
        window.axios.get(`${apiBase('orderAdjustments')}/${id}`, { params: { type } })
            .then(({ data }) => mounted && setState({ loading: false, record: data.data.record, items: data.data.items, error: '' }))
            .catch((error) => mounted && setState((current) => ({ ...current, loading: false, error: requestMessage(error, locale) })));
        return () => { mounted = false; };
    }, [id, locale, type]);

    const itemsPanel = !state.loading && <DetailPanel eyebrow={t(locale, 'items')} title={`${state.items.length} ${t(locale, 'items')}`} className="adjustment-detail-items-panel">
        <div className="master-table-wrap adjustment-detail-items-wrap">
            <table className={`master-table adjustment-detail-item-table ${type === 'sales_return' ? 'has-condition' : ''}`}>
                <colgroup>
                    <col className="adjustment-item-product-column" />
                    {type === 'sales_return' && <col className="adjustment-item-condition-column" />}
                    <col className="adjustment-item-quantity-column" />
                    <col className="adjustment-item-price-column" />
                    <col className="adjustment-item-total-column" />
                    <col className="adjustment-item-reason-column" />
                </colgroup>
                <thead><tr><th scope="col">{t(locale, 'product')}</th>{type === 'sales_return' && <th scope="col">Condition</th>}<th scope="col" className="numeric">{t(locale, 'quantity')}</th><th scope="col" className="numeric">{t(locale, 'price')}</th><th scope="col" className="numeric">{t(locale, 'total')}</th><th scope="col">{t(locale, 'reason')}</th></tr></thead>
                <tbody>{state.items.map((item) => <tr key={item.id}><td><strong>{item.product_name}</strong><span className="muted">{item.product_sku} · {item.unit}</span></td>{type === 'sales_return' && <td><span className={`status ${item.return_condition === 'good' ? 'success' : 'danger'}`}>{item.return_condition || '-'}</span></td>}<td className="numeric">{Number(item.quantity).toLocaleString()}</td><td className="numeric">{money(item.unit_price)}</td><td className="numeric"><strong>{money(item.line_total)}</strong></td><td>{item.remarks || '-'}</td></tr>)}</tbody>
            </table>
        </div>
    </DetailPanel>;
    const printAdjustment = () => printConfiguredDocument(type === 'sales_return' ? 'sales_return' : 'stock_adjustment', {
        reference: state.record.code,
        facts: [
            ['Customer', `${state.record.customer_code || '-'} · ${state.record.shop_name || '-'}`],
            ['Date', state.record.entry_date || '-'],
            ['Route', state.record.route || '-'],
            ['Original order', state.record.original_order_code || '-'],
            ['Settlement', String(state.record.return_settlement_method || state.record.payment_type || '-').replaceAll('_', ' ')],
            ['Status', state.record.status || '-'],
        ],
        columns: type === 'sales_return' ? ['Item', 'Condition', 'Qty', 'Total'] : ['Item', 'Qty', 'Price', 'Total'],
        rows: state.items.map((item) => type === 'sales_return'
            ? [`${item.product_name} · ${item.product_sku}`, item.return_condition || '-', Number(item.quantity).toLocaleString(), money(item.line_total)]
            : [`${item.product_name} · ${item.product_sku}`, Number(item.quantity).toLocaleString(), money(item.unit_price), money(item.line_total)]),
        total: ['Total MMK', money(state.record.total)],
        notes: state.record.notes,
    });

    return (
        <DetailPage eyebrow={title} title={state.record.code || t(locale, 'loading')} subtitle={state.record.shop_name || t(locale, 'details')} onBack={onBack} wideContent={itemsPanel} actions={<button className="icon-button" type="button" disabled={state.loading} title={`Print ${title}`} aria-label={`Print ${title}`} onClick={printAdjustment}><Printer size={16} /></button>}
            aside={!state.loading && <DetailPanel eyebrow={t(locale, 'summary')}><section className="order-total-card adjustment-total-card record-page-summary"><Icon size={18} /><div><small>{state.record.shop_name}</small><strong>{money(state.record.total)}</strong><span>{state.record.entry_date} · {t(locale, state.record.payment_type)} · <StatusBadge status={state.record.status} locale={locale} /></span></div></section></DetailPanel>}
        >
            {state.error && <div className="inline-error"><AlertCircle size={15} /> {state.error}</div>}
            {state.loading ? <WorkspaceState icon={RefreshCw} title={t(locale, 'loading')} loading compact /> : <>
                    <DetailPanel eyebrow={t(locale, 'details')} title={title}>
                        <dl className="record-page-facts">
                            <div><dt>{t(locale, 'customer')}</dt><dd>{state.record.customer_code} · {state.record.shop_name}</dd></div>
                            <div><dt>{t(locale, 'route')}</dt><dd>{state.record.route || '-'}</dd></div>
                            {type === 'sales_return' && <div><dt>Original order</dt><dd>{state.record.original_order_code || '-'}</dd></div>}
                            {type === 'sales_return' && <div><dt>Return warehouse</dt><dd>{state.record.return_warehouse_code ? `${state.record.return_warehouse_code} / ${state.record.return_warehouse_name}` : '-'}</dd></div>}
                            {type === 'sales_return' && <div><dt>Settlement</dt><dd>{String(state.record.return_settlement_method || '-').replaceAll('_', ' ')}</dd></div>}
                            {type === 'sales_return' && <div><dt>Refunded</dt><dd>{money(state.record.refund_amount)}</dd></div>}
                            <div><dt>{t(locale, 'totalQuantity')}</dt><dd>{Number(state.record.quantity_total || 0).toLocaleString()}</dd></div>
                            <div><dt>{t(locale, 'notes')}</dt><dd>{state.record.notes || '-'}</dd></div>
                        </dl>
                    </DetailPanel>
                </>}
        </DetailPage>
    );
}

function WorkspaceState({ icon: Icon, title, action, actionLabel, loading = false, compact = false }) {
    return <div className={`workspace-state ${compact ? 'compact' : ''}`}><Icon className={loading ? 'spin' : ''} size={22} /><strong>{title}</strong>{action && <button className="button" type="button" onClick={action}>{actionLabel}</button>}</div>;
}

function StatusBadge({ status, locale }) {
    const family = ['confirmed', 'delivered', 'issued'].includes(status) ? 'success' : ['pending', 'draft', 'partially_delivered'].includes(status) ? 'warning' : ['cancelled', 'failed'].includes(status) ? 'danger' : ['invoiced', 'assigned', 'delivering', 'loading', 'on_route'].includes(status) ? 'info' : 'neutral';
    return <span className={`status ${family}`}>{status === 'loading' ? t(locale, 'loadingDelivery') : t(locale, status)}</span>;
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
    const selected = Number(product.prices?.find((item) => Number(item.price_type_id) === Number(priceTypeId))?.amount);
    if (Number.isFinite(selected) && selected > 0) return selected;
    const fallback = Number(product.default_price);
    return Number.isFinite(fallback) && fallback > 0 ? fallback : '';
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
        const quantity = Number(item.quantity || 0);
        const subtotal = quantity * price;
        const discount = item.item_type === 'foc' ? 0 : Number(item.discount_amount || 0);

        return {
            saleQuantity: carry.saleQuantity + (item.item_type === 'foc' ? 0 : quantity),
            focQuantity: carry.focQuantity + (item.item_type === 'foc' ? quantity : 0),
            subtotal: carry.subtotal + subtotal,
            discount: carry.discount + discount,
            total: carry.total + Math.max(subtotal - discount, 0),
        };
    }, { saleQuantity: 0, focQuantity: 0, subtotal: 0, discount: 0, total: 0 });
}

function calculateSalesWizardPreview(items, products, priceTypeId, orderDiscount = 0) {
    const totals = items.reduce((carry, item) => {
        const product = products.find((value) => Number(value.id) === Number(item.product_id));
        const saleQuantity = Number(item.quantity || 0);
        const focQuantity = Number(item.foc_quantity || 0);
        const subtotal = saleQuantity * Number(priceForProduct(product, priceTypeId) || 0);

        return {
            saleQuantity: carry.saleQuantity + saleQuantity,
            focQuantity: carry.focQuantity + focQuantity,
            subtotal: carry.subtotal + subtotal,
            discount: carry.discount,
            total: carry.total + subtotal,
        };
    }, { saleQuantity: 0, focQuantity: 0, subtotal: 0, discount: 0, total: 0 });
    totals.discount = Math.min(Math.max(Number(orderDiscount || 0), 0), totals.subtotal);
    totals.total = Math.max(totals.subtotal - totals.discount, 0);
    return totals;
}

function distributeOrderDiscount(items, products, priceTypeId, requestedDiscount) {
    const saleRows = items.map((item) => {
        const product = products.find((value) => Number(value.id) === Number(item.product_id));
        const subtotal = Number(item.quantity || 0) * Number(priceForProduct(product, priceTypeId) || 0);
        return { ...item, lineSubtotal: subtotal, allocated_discount: 0 };
    });
    const subtotal = saleRows.reduce((total, item) => total + item.lineSubtotal, 0);
    let remainingDiscount = Math.min(Math.max(Number(requestedDiscount || 0), 0), subtotal);
    let remainingSubtotal = subtotal;

    return saleRows.map((item, index) => {
        if (item.lineSubtotal <= 0 || remainingDiscount <= 0) return item;
        const discount = index === saleRows.length - 1 || remainingSubtotal <= 0
            ? remainingDiscount
            : Math.min(item.lineSubtotal, remainingDiscount * item.lineSubtotal / remainingSubtotal);
        item.allocated_discount = discount;
        remainingDiscount = Math.max(remainingDiscount - discount, 0);
        remainingSubtotal = Math.max(remainingSubtotal - item.lineSubtotal, 0);
        return item;
    });
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
