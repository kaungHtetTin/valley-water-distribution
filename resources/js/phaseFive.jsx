import { Archive, CalendarDays, Check, Package, Plus, Printer, RefreshCw, Save, Search, SlidersHorizontal, X } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import { DetailPage, DetailPanel } from './components/DetailPage';
import { ShellBackButton } from './components/ShellBackButton';
import { ShellPageActions } from './components/ShellPageActions';
import { printConfiguredDocument } from './printDocuments';

const copy = {
    en: {
        actions: 'Actions',
        allProducts: 'All products',
        allWarehouses: 'All warehouses',
        balanceHint: 'Live stock by warehouse and product, maintained from the movement ledger.',
        balances: 'Stock balances',
        cancel: 'Cancel',
        clearFilters: 'Clear',
        cardHint: 'Product movement history with opening, in, out, and running balance.',
        cost: 'Cost',
        damage: 'Damage',
        expired: 'Expired',
        loss: 'Loss / theft',
        internal_use: 'Internal use',
        count_correction: 'Count correction',
        found_stock: 'Found stock',
        other: 'Other',
        adjustments: 'Stock adjustments',
        adjustmentHint: 'Correct warehouse balances with a clear reason and a complete stock ledger trail.',
        newAdjustment: 'New adjustment',
        adjustmentHistory: 'Adjustment history',
        adjustmentReason: 'Reason',
        adjustmentDirection: 'Direction',
        addStock: 'Add stock',
        removeStock: 'Remove stock',
        adjustedValue: 'Adjusted value',
        loadingAdjustments: 'Loading stock adjustments',
        emptyAdjustments: 'No stock adjustments match this view.',
        searchAdjustments: 'Search adjustment, reference, warehouse, product, reason',
        recordAdjustment: 'Record adjustment',
        delivery_damage: 'Delivery damage',
        delivery_issue: 'Delivery issue',
        delivery_issue_reversal: 'Loading reversal',
        delivery_return: 'Delivery return',
        date: 'Date',
        dateFrom: 'From',
        dateTo: 'To',
        destination: 'Destination',
        emptyBalances: 'No stock balances match this view.',
        emptyCard: 'Select a product to view its stock card.',
        emptyMovements: 'No stock movements match this view.',
        emptyTransfers: 'No transfers match this view.',
        fromWarehouse: 'From warehouse',
        inQuantity: 'Stock in',
        issue: 'Issue',
        issues: 'Stock issue',
        issueHint: 'Review automatic delivery loading issues and record separate issues for internal or operational use.',
        lastMovement: 'Last movement',
        loadingBalances: 'Loading stock balances',
        loadingCard: 'Loading stock card',
        loadingDamage: 'Loading damage stock',
        loadingIssues: 'Loading stock issues',
        loadingMovements: 'Loading stock movements',
        loadingTransfers: 'Loading transfers',
        loadingClosing: 'Loading closing stock counts',
        movementType: 'Movement type',
        movements: 'Stock movements',
        newDamageStock: 'New damage',
        newIssue: 'New issue',
        newReceive: 'New receive',
        newTransfer: 'New transfer',
        notes: 'Notes',
        opening: 'Opening',
        openingBalance: 'Opening',
        outQuantity: 'Stock out',
        product: 'Product',
        products: 'Products',
        quantity: 'Quantity',
        receive: 'Receive',
        receipts: 'Stock receipts',
        receiveHint: 'Record opening stock and warehouse receives before issue, transfer, and delivery workflows consume stock.',
        reference: 'Reference',
        refresh: 'Refresh',
        retry: 'Retry',
        save: 'Record stock',
        saveTransfer: 'Record transfer',
        saveCount: 'Record count',
        search: 'Search',
        searchBalances: 'Search warehouse, SKU, product',
        searchCard: 'Filter by product or warehouse first',
        searchDamage: 'Search damage, reference, warehouse, product',
        searchIssues: 'Search issue, reference, warehouse, product',
        searchMovements: 'Search movement, reference, warehouse, product',
        searchTransfers: 'Search transfer or reference code, warehouse, product',
        searchValues: 'Search warehouse, SKU, product',
        source: 'Source',
        supplier: 'Supplier',
        stockValue: 'Stock value',
        stockValueHint: 'Current inventory valuation by warehouse and product using weighted average cost.',
        stockValueReport: 'Stock value report',
        warehouseValue: 'Value by warehouse',
        rankedValue: 'Inventory value ranking',
        shareOfValue: 'Share of total value',
        warehouses: 'Warehouses',
        balanceLines: 'Balance lines',
        loadingValues: 'Loading stock value report',
        emptyValues: 'No valued stock balances match this view.',
        stockCard: 'Stock card',
        totalQuantity: 'Total qty',
        total: 'Total',
        toWarehouse: 'To warehouse',
        transfer: 'Transfer',
        transferHint: 'Move stock between warehouses while preserving linked outbound and inbound ledger records.',
        transfer_in: 'Transfer in',
        transfer_out: 'Transfer out',
        unit: 'Unit',
        warehouse: 'Warehouse',
        damageStock: 'Damage stock',
        damageStockHint: 'Record damaged stock out of warehouse balances with a visible ledger trail.',
        emptyDamage: 'No damage stock entries match this view.',
        emptyIssues: 'No stock issues match this view.',
        closingStock: 'Closing stock',
        closingStockHint: 'Enter a physical count and let the system record the exact variance against the current balance.',
        newClosingCount: 'New physical count',
        emptyClosing: 'No closing stock counts match this view.',
        searchClosing: 'Search count, reference, warehouse, product',
        systemQuantity: 'System quantity',
        countedQuantity: 'Counted quantity',
        variance: 'Variance',
        resultingQuantity: 'Resulting quantity',
        currentValue: 'Current value',
        countPreview: 'Count preview',
        countPreviewHint: 'The adjustment is calculated when the count is saved.',
        increaseQuantity: 'Quantity added',
        decreaseQuantity: 'Quantity removed',
        basic: 'Basic',
        basicHint: 'Warehouse, date and reference',
        selectProduct: 'Select product',
        productHint: 'Choose products to receive',
        quantities: 'Quantity & cost',
        quantityHint: 'Enter received quantity and cost',
        review: 'Review',
        reviewHint: 'Confirm before posting',
        previous: 'Previous',
        next: 'Next',
        receiptDetails: 'Receipt details',
        receiptDetailsHint: 'Set the warehouse and document information for this receipt.',
        productSelection: 'Product selection',
        productSelectionHint: 'Search and choose every product included in this receipt.',
        searchProducts: 'Search SKU or product name',
        noProductsFound: 'No products match this search.',
        quantityAndCost: 'Quantity and cost',
        quantityAndCostHint: 'Enter the received quantity and buying cost for every product.',
        receiptReview: 'Receipt review',
        receiptReviewHint: 'Check the stock impact before recording this receipt.',
        selectedProduct: 'Selected product',
        estimatedValue: 'Estimated value',
        recordReceive: 'Record receipt',
        optional: 'Optional',
    },
    my: {},
};

const blankForm = { movement_type: 'receive', supplier_id: '', warehouse_id: '', product_id: '', movement_date: new Date().toISOString().slice(0, 10), quantity: 1, unit_cost: '', settlement_method: 'credit', payment_terms_days: 30, due_date: '', reference_code: '', notes: '', items: [] };
const blankTransferForm = { from_warehouse_id: '', to_warehouse_id: '', movement_date: new Date().toISOString().slice(0, 10), reference_code: '', notes: '', items: [] };
const blankClosingForm = { warehouse_id: '', movement_date: new Date().toISOString().slice(0, 10), reference_code: '', notes: '', items: [] };
const movementTypes = ['opening', 'receive', 'issue', 'damage'];
const movementFilterTypes = ['opening', 'receive', 'issue', 'damage', 'transfer_out', 'transfer_in', 'adjustment', 'delivery_issue', 'delivery_issue_reversal', 'delivery_return', 'delivery_damage'];
const movementModeConfig = {
    receive: { title: 'receipts', hint: 'receiveHint', action: 'newReceive', panel: 'receipts', loading: 'loadingMovements', empty: 'emptyMovements', search: 'searchMovements', formTypes: ['opening', 'receive'], defaultType: 'receive', filterTypes: ['opening', 'receive'], fixedType: '' },
    issue: { title: 'issues', hint: 'issueHint', action: 'newIssue', panel: 'newIssue', loading: 'loadingIssues', empty: 'emptyIssues', search: 'searchIssues', formTypes: ['issue'], defaultType: 'issue', filterTypes: ['issue', 'delivery_issue', 'delivery_issue_reversal'], fixedType: '', typeGroup: 'issue' },
    damage: { title: 'damageStock', hint: 'damageStockHint', action: 'newDamageStock', panel: 'newDamageStock', loading: 'loadingDamage', empty: 'emptyDamage', search: 'searchDamage', formTypes: ['damage'], defaultType: 'damage', filterTypes: ['damage'], fixedType: 'damage' },
};

const adjustmentReasons = ['damage', 'expired', 'loss', 'internal_use', 'count_correction', 'found_stock', 'other'];

function t(locale, key) {
    return copy[locale]?.[key] || copy.en[key] || key;
}

function apiBase() {
    return window.ValleyRuntime?.api?.stock || '/api/stock';
}

function requestMessage(error) {
    return error.response?.data?.message || 'The stock request could not be completed.';
}

function SupplierCombobox({ suppliers, value, onChange }) {
    const selected = suppliers.find((supplier) => String(supplier.id) === String(value));
    const [query, setQuery] = useState(selected?.label || '');
    const [open, setOpen] = useState(false);
    const [activeIndex, setActiveIndex] = useState(0);
    const rootRef = useRef(null);
    const inputRef = useRef(null);
    const optionsId = useId();
    const normalizedQuery = query.trim().toLocaleLowerCase();
    const filtered = suppliers
        .filter((supplier) => !normalizedQuery || `${supplier.code} ${supplier.name} ${supplier.label}`.toLocaleLowerCase().includes(normalizedQuery))
        .slice(0, 20);

    useEffect(() => {
        const close = (event) => {
            if (!rootRef.current?.contains(event.target)) setOpen(false);
        };
        document.addEventListener('mousedown', close);
        return () => document.removeEventListener('mousedown', close);
    }, []);

    const choose = (supplier) => {
        onChange(String(supplier.id));
        setQuery(supplier.label || `${supplier.code} - ${supplier.name}`);
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
            setOpen(false);
        }
    };

    return <div className="customer-combobox supplier-combobox" ref={rootRef}>
        <div className="customer-combobox-input">
            <Search size={15} />
            <input
                ref={inputRef}
                role="combobox"
                aria-autocomplete="list"
                aria-controls={optionsId}
                aria-expanded={open}
                required
                value={query}
                placeholder="Search supplier code or name"
                onFocus={() => setOpen(true)}
                onKeyDown={keyDown}
                onInvalid={(event) => event.currentTarget.setCustomValidity(value ? '' : 'Select a supplier from the results.')}
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
            {filtered.length ? filtered.map((supplier, index) => <button className={index === activeIndex ? 'is-active' : ''} type="button" role="option" aria-selected={String(supplier.id) === String(value)} key={supplier.id} onMouseDown={(event) => event.preventDefault()} onClick={() => choose(supplier)}><span><strong>{supplier.name}</strong><small>{supplier.code}</small></span>{String(supplier.id) === String(value) && <Check size={16} />}</button>) : <p>No suppliers match “{query}”.</p>}
            {suppliers.length > 20 && !normalizedQuery && <small className="customer-combobox-hint">Type to search {suppliers.length} suppliers</small>}
        </div>}
    </div>;
}

function printStockDocument(type, documentCode) {
    return printConfiguredDocument(type, async () => {
        const { data } = await window.axios.get(`${apiBase()}/documents/${encodeURIComponent(documentCode)}`);
        const document = data.data.document;
        return {
            reference: document.code,
            facts: [
                ['Date', document.movement_date],
                ['Movement', String(document.movement_type || '').replaceAll('_', ' ')],
                ...(document.supplier_name ? [['Supplier', `${document.supplier_code || '-'} · ${document.supplier_name}`]] : []),
                ['From warehouse', `${document.warehouse_code || '-'} · ${document.warehouse_name || '-'}`],
                ...(document.destination_warehouse_code ? [['To warehouse', `${document.destination_warehouse_code} · ${document.destination_warehouse_name}`]] : []),
                ['Reference', document.reference_code || '-'],
                ['Products / quantity', `${document.products_count} / ${number(document.total_quantity)}`],
            ],
            columns: ['Product', 'Qty', 'Unit cost', 'Value'],
            rows: data.data.items.map((item) => [`${item.product_name} · ${item.product_sku}`, number(Math.abs(item.signed_quantity)), money(item.unit_cost), money(item.total_cost)]),
            total: ['Total value', money(document.total_value)],
            notes: document.notes,
        };
    });
}

export function StockReceiveScreen({ locale, canManage = false, mode = 'receive', receiveForm = false, onNavigate }) {
    const movementConfig = movementModeConfig[mode] || movementModeConfig.receive;
    const [meta, setMeta] = useState({ loading: true, warehouses: [], products: [], suppliers: [], error: '' });
    const [filters, setFilters] = useState({ search: '', warehouse_id: '', product_id: '', supplier_id: '', type: movementConfig.fixedType || '', date: '' });
    const [state, setState] = useState({ loading: true, items: [], summary: {}, pageMeta: {}, error: '' });
    const [page, setPage] = useState(1);
    const [refreshKey, setRefreshKey] = useState(0);
    const [formOpen, setFormOpen] = useState(false);
    const [saving, setSaving] = useState(false);
    const [formError, setFormError] = useState('');
    const [form, setForm] = useState(blankForm);
    const [wizardStep, setWizardStep] = useState(0);
    const [productSearch, setProductSearch] = useState('');
    const isSeparateReceiveForm = mode === 'receive' && receiveForm;

    useEffect(() => {
        let mounted = true;
        window.axios.get(`${apiBase()}/meta`)
            .then(({ data }) => mounted && setMeta({ loading: false, warehouses: data.data.warehouses, products: data.data.products, suppliers: data.data.suppliers || [], error: '' }))
            .catch((error) => mounted && setMeta({ loading: false, warehouses: [], products: [], suppliers: [], error: requestMessage(error) }));

        return () => { mounted = false; };
    }, []);

    useEffect(() => {
        let mounted = true;
        setState((current) => ({ ...current, loading: true, error: '' }));
        const timer = window.setTimeout(() => {
            window.axios.get(`${apiBase()}/${mode === 'receive' ? 'receipts' : 'movements'}`, {
                params: {
                    search: filters.search || undefined,
                    warehouse_id: filters.warehouse_id || undefined,
                    product_id: filters.product_id || undefined,
                    supplier_id: filters.supplier_id || undefined,
                    type: movementConfig.fixedType || filters.type || undefined,
                    type_group: !filters.type ? movementConfig.typeGroup || undefined : undefined,
                    date: filters.date || undefined,
                    page,
                    per_page: 20,
                },
            }).then(({ data }) => {
                if (!mounted) return;
                setState({ loading: false, items: data.data.items, summary: data.data.summary, pageMeta: data.data.meta, error: '' });
            }).catch((error) => mounted && setState({ loading: false, items: [], summary: {}, pageMeta: {}, error: requestMessage(error) }));
        }, 220);

        return () => { mounted = false; window.clearTimeout(timer); };
    }, [filters, page, refreshKey, movementConfig.fixedType, mode]);

    useEffect(() => {
        setPage(1);
    }, [filters.search, filters.warehouse_id, filters.product_id, filters.supplier_id, filters.type, filters.date]);

    const openForm = () => {
        if (mode === 'receive' && !isSeparateReceiveForm && onNavigate) {
            onNavigate(`${window.ValleyRuntime?.routes?.office || '/office'}/stock/receive/new`);
            return;
        }
        const firstWarehouse = meta.warehouses[0];
        const firstProduct = meta.products[0];
        setForm({
            ...blankForm,
            movement_type: movementConfig.defaultType,
            warehouse_id: firstWarehouse?.id || '',
            product_id: mode === 'receive' ? '' : firstProduct?.id || '',
            unit_cost: mode === 'receive' ? '' : firstProduct?.latest_cost || '',
            items: [],
            movement_date: new Date().toISOString().slice(0, 10),
        });
        setFormError('');
        setWizardStep(0);
        setProductSearch('');
        setFormOpen(true);
    };

    const closeForm = () => {
        setFormOpen(false);
        setSaving(false);
        setFormError('');
        setWizardStep(0);
        setProductSearch('');
        if (isSeparateReceiveForm && onNavigate) {
            onNavigate(`${window.ValleyRuntime?.routes?.office || '/office'}/stock/receive`);
        }
    };

    useEffect(() => {
        if (!isSeparateReceiveForm || meta.loading || meta.error || formOpen) return;

        const firstWarehouse = meta.warehouses[0];
        setForm({
            ...blankForm,
            movement_type: movementConfig.defaultType,
            warehouse_id: firstWarehouse?.id || '',
            items: [],
            movement_date: new Date().toISOString().slice(0, 10),
        });
        setFormError('');
        setWizardStep(0);
        setProductSearch('');
        setFormOpen(true);
    }, [isSeparateReceiveForm, meta.loading, meta.error, meta.warehouses, formOpen, movementConfig.defaultType]);

    useEffect(() => {
        if (mode !== 'receive' || receiveForm || !formOpen) return;
        setFormOpen(false);
        setSaving(false);
        setFormError('');
        setWizardStep(0);
        setProductSearch('');
    }, [mode, receiveForm, formOpen]);

    const saveMovement = (event) => {
        event?.preventDefault();
        setSaving(true);
        setFormError('');
        const endpoint = mode === 'receive' ? 'receipts' : 'movements';
        window.axios.post(`${apiBase()}/${endpoint}`, form)
            .then(({ data }) => {
                const saved = mode === 'receive'
                    ? { ...data.data.receipt, document_code: data.data.receipt.document_code, warehouse_code: selectedWarehouse?.code, warehouse_name: selectedWarehouse?.name }
                    : data.data.movement;
                setState((current) => ({ ...current, items: [saved, ...current.items], summary: { ...current.summary, records_count: Number(current.summary.records_count || 0) + 1, total_quantity: Number(current.summary.total_quantity || 0) + Math.abs(Number(saved.total_quantity ?? saved.signed_quantity ?? 0)), total_value: Number(current.summary.total_value || 0) + Math.abs(Number(saved.total_value ?? saved.total_cost ?? 0)) }, pageMeta: { ...current.pageMeta, total: Number(current.pageMeta.total || 0) + 1 } }));
                closeForm();
            })
            .catch((error) => {
                setSaving(false);
                setFormError(requestMessage(error));
            });
    };

    const isReceiveWizard = mode === 'receive';
    const selectedProducts = form.items.map((item) => ({ ...meta.products.find((product) => Number(product.id) === Number(item.product_id)), ...item }));
    const selectedWarehouse = meta.warehouses.find((warehouse) => Number(warehouse.id) === Number(form.warehouse_id));
    const selectedSupplier = meta.suppliers.find((supplier) => Number(supplier.id) === Number(form.supplier_id));
    const filteredProducts = meta.products.filter((product) => {
        const query = productSearch.trim().toLocaleLowerCase();
        return !query || [product.sku, product.name, product.label, product.unit].some((value) => String(value || '').toLocaleLowerCase().includes(query));
    });
    const toggleReceiptProduct = (product) => {
        const selected = form.items.some((item) => Number(item.product_id) === Number(product.id));
        setForm((current) => ({
            ...current,
            items: selected
                ? current.items.filter((item) => Number(item.product_id) !== Number(product.id))
                : [...current.items, { product_id: product.id, quantity: 1, unit_cost: product.latest_cost || '' }],
        }));
    };
    const updateReceiptItem = (productId, patch) => setForm((current) => ({
        ...current,
        items: current.items.map((item) => Number(item.product_id) === Number(productId) ? { ...item, ...patch } : item),
    }));
    const moveReceiptGrid = (event, rowIndex, columnIndex) => {
        if (event.key !== 'Enter') return;
        event.preventDefault();
        const nextRow = rowIndex + (event.shiftKey ? -1 : 1);
        const target = event.currentTarget.closest('table')?.querySelector(`[data-grid-row="${nextRow}"][data-grid-column="${columnIndex}"]`);
        target?.focus();
        target?.select();
    };
    const pasteReceiptGrid = (event, startRow, startColumn) => {
        const pastedRows = event.clipboardData.getData('text').trimEnd().split(/\r?\n/).map((line) => line.split('\t'));
        if (pastedRows.length === 1 && pastedRows[0].length === 1) return;
        event.preventDefault();
        setForm((current) => ({
            ...current,
            items: current.items.map((item, rowIndex) => {
                const pastedRow = pastedRows[rowIndex - startRow];
                if (!pastedRow) return item;
                const patch = {};
                pastedRow.forEach((value, columnOffset) => {
                    const column = startColumn + columnOffset;
                    if (column === 0) patch.quantity = value.replaceAll(',', '').trim();
                    if (column === 1) patch.unit_cost = value.replaceAll(',', '').trim();
                });
                return { ...item, ...patch };
            }),
        }));
    };
    const receiptTotalQuantity = form.items.reduce((sum, item) => sum + Number(item.quantity || 0), 0);
    const receiptTotalValue = form.items.reduce((sum, item) => sum + Number(item.quantity || 0) * Number(item.unit_cost || 0), 0);
    const calculatedDueDate = (() => {
        if (form.due_date) return form.due_date;
        const value = new Date(`${form.movement_date}T00:00:00`);
        value.setDate(value.getDate() + Number(form.payment_terms_days || 0));
        return Number.isNaN(value.getTime()) ? '' : value.toISOString().slice(0, 10);
    })();
    const wizardSteps = [['basic', 'basicHint'], ['selectProduct', 'productHint'], ['quantities', 'quantityHint'], ['review', 'reviewHint']];
    const basicComplete = Boolean(form.movement_type && form.warehouse_id && form.movement_date && (form.movement_type !== 'receive' || form.supplier_id));
    const productComplete = basicComplete && form.items.length > 0;
    const quantityComplete = productComplete && form.items.every((item) => Number(item.quantity) > 0 && (item.unit_cost === '' || Number(item.unit_cost) >= 0));
    const canOpenStep = (index) => index === 0 || (index === 1 && basicComplete) || (index === 2 && productComplete) || (index === 3 && quantityComplete);
    const canContinueWizard = wizardStep === 0 ? basicComplete : wizardStep === 1 ? productComplete : quantityComplete;
    const nextWizardStep = () => canContinueWizard && setWizardStep((current) => Math.min(3, current + 1));
    const handleMovementSubmit = (event) => {
        if (isReceiveWizard) {
            event.preventDefault();
            if (wizardStep < 3) nextWizardStep();
            return;
        }
        saveMovement(event);
    };

    return (
        <section className="page stock-workspace">
            {!isSeparateReceiveForm && <><div className="master-heading">
                <div>
                    <p className="eyebrow">Phase 5 · Warehouse Stock</p>
                    <h1>{t(locale, movementConfig.title)}</h1>
                    <span className="muted">{t(locale, movementConfig.hint)}</span>
                </div>
                <ShellPageActions>{canManage && <button className="button primary" type="button" onClick={openForm} disabled={meta.loading || meta.error}><Plus size={16} />{t(locale, movementConfig.action)}</button>}</ShellPageActions>
            </div>

            <div className="metrics stock-metrics">
                <Metric label={t(locale, movementConfig.title)} value={number(state.summary.records_count)} hint={t(locale, 'products')} icon={Archive} />
                <Metric label={t(locale, 'inQuantity')} value={number(state.summary.in_quantity)} hint={t(locale, 'quantity')} icon={Package} />
                <Metric label={t(locale, 'outQuantity')} value={number(state.summary.out_quantity)} hint={t(locale, 'quantity')} icon={Package} />
                <Metric label={t(locale, 'stockValue')} value={money(Math.abs(state.summary.stock_value || 0))} hint="MMK" icon={Archive} />
            </div>

            <section className="master-panel">
                <div className="master-panel-heading">
                    <div>
                        <p className="eyebrow">{t(locale, 'movements')}</p>
                        <h2>{t(locale, movementConfig.panel)}</h2>
                    </div>
                </div>
                <div className="master-toolbar stock-toolbar">
                    <label className="master-search"><Search size={14} /><input value={filters.search} placeholder={t(locale, movementConfig.search)} onChange={(event) => setFilters((current) => ({ ...current, search: event.target.value }))} /></label>
                    <select value={filters.warehouse_id} onChange={(event) => setFilters((current) => ({ ...current, warehouse_id: event.target.value }))}>
                        <option value="">{t(locale, 'allWarehouses')}</option>
                        {meta.warehouses.map((warehouse) => <option value={warehouse.id} key={warehouse.id}>{warehouse.label}</option>)}
                    </select>
                    <select value={filters.product_id} onChange={(event) => setFilters((current) => ({ ...current, product_id: event.target.value }))}>
                        <option value="">{t(locale, 'allProducts')}</option>
                        {meta.products.map((product) => <option value={product.id} key={product.id}>{product.label}</option>)}
                    </select>
                    {isReceiveWizard && <select value={filters.supplier_id} onChange={(event) => setFilters((current) => ({ ...current, supplier_id: event.target.value }))}>
                        <option value="">All suppliers</option>
                        {meta.suppliers.map((supplier) => <option value={supplier.id} key={supplier.id}>{supplier.label}</option>)}
                    </select>}
                    <select value={filters.type} onChange={(event) => setFilters((current) => ({ ...current, type: event.target.value }))}>
                        <option value="">{t(locale, 'movementType')}</option>
                        {movementConfig.filterTypes.map((type) => <option value={type} key={type}>{t(locale, type)}</option>)}
                    </select>
                    <label className="date-filter"><CalendarDays size={14} /><input type="date" value={filters.date} onChange={(event) => setFilters((current) => ({ ...current, date: event.target.value }))} /></label>
                    <button className="button" type="button" onClick={() => setRefreshKey((value) => value + 1)}><RefreshCw size={15} />{t(locale, 'refresh')}</button>
                </div>

                {state.loading ? (
                    <WorkspaceState icon={RefreshCw} title={t(locale, movementConfig.loading)} loading />
                ) : state.error ? (
                    <WorkspaceState icon={RefreshCw} title={state.error} action={() => setRefreshKey((value) => value + 1)} actionLabel={t(locale, 'retry')} />
                ) : state.items.length === 0 ? (
                    <WorkspaceState icon={Package} title={t(locale, movementConfig.empty)} />
                ) : (
                    <>
                        <div className="master-table-wrap">
                            <table className={`master-table stock-table${isReceiveWizard ? ' stock-receipt-table' : ''}`}>
                                <thead>
                                    {!isReceiveWizard && <tr>
                                        <th>{t(locale, 'reference')}</th>
                                        <th>{t(locale, 'warehouse')}</th>
                                        <th>{t(locale, 'toWarehouse')}</th>
                                        <th>{t(locale, 'movementType')}</th>
                                        <th>{t(locale, 'date')}</th>
                                        <th>{t(locale, 'quantity')}</th>
                                        <th>{t(locale, 'cost')}</th>
                                        <th>{t(locale, 'stockValue')}</th>
                                    </tr>}
                                    {isReceiveWizard && <tr><th>{t(locale, 'reference')}</th><th>{t(locale, 'supplier')}</th><th>{t(locale, 'warehouse')}</th><th>{t(locale, 'movementType')}</th><th>{t(locale, 'date')}</th><th>{t(locale, 'products')}</th><th>{t(locale, 'quantity')}</th><th>{t(locale, 'stockValue')}</th><th className="table-actions-header">{t(locale, 'actions')}</th></tr>}
                                </thead>
                                <tbody>
                                    {state.items.map((movement) => isReceiveWizard ? (
                                        <tr key={movement.document_code}>
                                            <td><strong>{movement.document_code}</strong><span className="muted">{movement.reference_code || '-'}</span></td>
                                            <td><strong>{movement.supplier_name || '-'}</strong><span className="muted">{movement.supplier_code || (movement.movement_type === 'opening' ? t(locale, 'openingBalance') : '-')}</span></td>
                                            <td><strong>{movement.warehouse_code}</strong><span className="muted">{movement.warehouse_name}</span></td>
                                            <td><StatusBadge status={movement.movement_type} locale={locale} /></td>
                                            <td>{formatDate(movement.movement_date)}</td>
                                            <td><strong>{number(movement.products_count)}</strong><span className="muted">{t(locale, 'products')}</span></td>
                                            <td className="numeric">{number(movement.total_quantity)}</td>
                                            <td className="numeric"><strong>{money(movement.total_value)}</strong></td>
                                            <td className="table-actions-cell"><div className="row-actions"><button className="icon-button" type="button" title="Print stock receipt" aria-label={`Print ${movement.document_code}`} onClick={() => printStockDocument('stock_receipt', movement.document_code)}><Printer size={16} /></button></div></td>
                                        </tr>
                                    ) : (
                                        <tr key={movement.id}>
                                            <td><strong>{movement.code}</strong><span className="muted">{movement.reference_code || '-'}</span></td>
                                            <td><strong>{movement.warehouse_code}</strong><span className="muted">{movement.warehouse_name}</span></td>
                                            <td><strong>{movement.product_name}</strong><span className="muted">{movement.product_sku} · {movement.unit}</span></td>
                                            <td><StatusBadge status={movement.movement_type} locale={locale} /></td>
                                            <td>{formatDate(movement.movement_date)}</td>
                                            <td className="numeric">{number(movement.signed_quantity)}</td>
                                            <td className="numeric">{money(movement.unit_cost)}</td>
                                            <td className="numeric">{money(movement.total_cost)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <Pagination meta={state.pageMeta} page={page} setPage={setPage} />
                    </>
                )}
            </section></>}

            {formOpen && (!isReceiveWizard || isSeparateReceiveForm) && (
                <div className={isSeparateReceiveForm ? 'stock-receive-form-page' : 'modal-backdrop'} role={isSeparateReceiveForm ? undefined : 'presentation'} onMouseDown={(event) => !isSeparateReceiveForm && event.target === event.currentTarget && closeForm()}>
                    <form className={`master-dialog stock-dialog${isReceiveWizard ? ' stock-wizard-dialog' : ''}${isSeparateReceiveForm ? ' stock-wizard-page' : ''}`} onSubmit={handleMovementSubmit}>
                        {isSeparateReceiveForm ? (
                            <><ShellBackButton onClick={() => wizardStep > 0 ? setWizardStep((current) => current - 1) : closeForm()} label={wizardStep > 0 ? "Previous step" : "Back to receipts"} /><header className="stock-wizard-page-heading">
                                <div><h1>{t(locale, movementConfig.action)}</h1><p>Create and review a warehouse stock receipt.</p></div>
                            </header></>
                        ) : (
                            <header><h2>{t(locale, movementConfig.action)}</h2><button className="icon-button" type="button" aria-label={t(locale, 'cancel')} onClick={closeForm}><X size={16} /></button></header>
                        )}
                        {isReceiveWizard ? <>
                            <div className="stock-wizard-stepper-wrap">
                                <ol className="trip-wizard-steps stock-receive-steps" aria-label={t(locale, 'newReceive')}>
                                    {wizardSteps.map(([labelKey, hintKey], index) => (
                                        <li key={labelKey} className={wizardStep === index ? 'is-current' : index < wizardStep ? 'is-complete' : ''}>
                                            <button type="button" disabled={!canOpenStep(index)} onClick={() => canOpenStep(index) && setWizardStep(index)} aria-current={wizardStep === index ? 'step' : undefined}>
                                                <span>{index < wizardStep ? <Check size={13} /> : index + 1}</span>
                                                <strong>{t(locale, labelKey)}</strong>
                                                <small>{t(locale, hintKey)}</small>
                                            </button>
                                        </li>
                                    ))}
                                </ol>
                            </div>
                            <div className="master-form-body stock-wizard-body">
                                {formError && <p className="form-alert">{formError}</p>}

                                {wizardStep === 0 && <section className="stock-wizard-stage">
                                    <div className="stock-wizard-stage-heading"><p className="eyebrow">1 / 4</p><h3>{t(locale, 'receiptDetails')}</h3><span>{t(locale, 'receiptDetailsHint')}</span></div>
                                    <div className="master-form-grid stock-wizard-basic">
                                        <label><span className="stock-field-label">{t(locale, 'movementType')}</span><select value={form.movement_type} onChange={(event) => setForm((current) => ({ ...current, movement_type: event.target.value }))}>{movementConfig.formTypes.map((type) => <option value={type} key={type}>{t(locale, type)}</option>)}</select></label>
                                        {form.movement_type === 'receive' && <label><span className="stock-field-label">{t(locale, 'supplier')}</span><SupplierCombobox suppliers={meta.suppliers} value={form.supplier_id} onChange={(supplierId) => setForm((current) => ({ ...current, supplier_id: supplierId }))} /></label>}
                                        <label><span className="stock-field-label">{t(locale, 'warehouse')}</span><select required value={form.warehouse_id} onChange={(event) => setForm((current) => ({ ...current, warehouse_id: event.target.value }))}>{meta.warehouses.map((warehouse) => <option value={warehouse.id} key={warehouse.id}>{warehouse.label}</option>)}</select></label>
                                        <label><span className="stock-field-label">{t(locale, 'date')}</span><input required type="date" value={form.movement_date} onChange={(event) => setForm((current) => ({ ...current, movement_date: event.target.value }))} /></label>
                                        <label><span className="stock-field-label">{form.movement_type === 'receive' ? 'Supplier invoice number' : t(locale, 'reference')} <small>{t(locale, 'optional')}</small></span><input value={form.reference_code} onChange={(event) => setForm((current) => ({ ...current, reference_code: event.target.value }))} /></label>
                                        {form.movement_type === 'receive' && <label><span className="stock-field-label">Settlement</span><select value={form.settlement_method} onChange={(event) => setForm((current) => ({ ...current, settlement_method: event.target.value }))}><option value="credit">Supplier credit</option><option value="cash">Paid from cash book</option><option value="bank">Paid from bank book</option></select></label>}
                                        {form.movement_type === 'receive' && form.settlement_method === 'credit' && <label><span className="stock-field-label">Payment terms <small>days</small></span><input min="0" max="3650" step="1" type="number" inputMode="numeric" value={form.payment_terms_days} onChange={(event) => setForm((current) => ({ ...current, payment_terms_days: event.target.value, due_date: '' }))} /></label>}
                                        {form.movement_type === 'receive' && form.settlement_method === 'credit' && <label><span className="stock-field-label">Due date <small>{calculatedDueDate && !form.due_date ? `Calculated ${calculatedDueDate}` : t(locale, 'optional')}</small></span><input min={form.movement_date} type="date" value={form.due_date} onChange={(event) => setForm((current) => ({ ...current, due_date: event.target.value }))} /></label>}
                                        <label className="span-2"><span className="stock-field-label">{t(locale, 'notes')} <small>{t(locale, 'optional')}</small></span><textarea rows="3" value={form.notes} onChange={(event) => setForm((current) => ({ ...current, notes: event.target.value }))} /></label>
                                    </div>
                                </section>}

                                {wizardStep === 1 && <section className="stock-wizard-stage">
                                    <div className="stock-wizard-stage-heading"><p className="eyebrow">2 / 4</p><h3>{t(locale, 'productSelection')}</h3><span>{t(locale, 'productSelectionHint')}</span></div>
                                    <label className="master-search stock-product-search"><Search size={14} /><input value={productSearch} onChange={(event) => setProductSearch(event.target.value)} placeholder={t(locale, 'searchProducts')} autoFocus /></label>
                                    <div className="stock-product-picker">
                                        {filteredProducts.length === 0 ? <p className="stock-wizard-empty">{t(locale, 'noProductsFound')}</p> : filteredProducts.map((product) => (
                                            <button type="button" key={product.id} className={form.items.some((item) => Number(item.product_id) === Number(product.id)) ? 'is-selected' : ''} onClick={() => toggleReceiptProduct(product)}>
                                                <span className="stock-product-icon"><Package size={16} /></span>
                                                <span><strong>{product.name}</strong><small>{product.sku} · {product.unit}</small></span>
                                                <span className="stock-product-cost"><small>{t(locale, 'cost')}</small><strong>{money(product.latest_cost || 0)}</strong></span>
                                                <span className="stock-product-check">{form.items.some((item) => Number(item.product_id) === Number(product.id)) && <Check size={14} />}</span>
                                            </button>
                                        ))}
                                    </div>
                                </section>}

                                {wizardStep === 2 && <section className="stock-wizard-stage">
                                    <div className="stock-wizard-stage-heading"><p className="eyebrow">3 / 4</p><h3>{t(locale, 'quantityAndCost')}</h3><span>{t(locale, 'quantityAndCostHint')}</span></div>
                                    <div className="stock-line-summary"><span>{form.items.length} {t(locale, 'products')}</span><span>{t(locale, 'totalQuantity')}: <strong>{number(receiptTotalQuantity)}</strong></span><span>{t(locale, 'estimatedValue')}: <strong>{money(receiptTotalValue)}</strong></span><button className="button" type="button" onClick={() => setWizardStep(1)}>{t(locale, 'selectProduct')}</button></div>
                                    <div className="master-table-wrap stock-receive-line-wrap"><table className="master-table price-matrix-table stock-receive-entry-table"><thead><tr><th>{t(locale, 'product')}</th><th>{t(locale, 'quantity')}</th><th>{t(locale, 'cost')}<small>MMK</small></th><th>{t(locale, 'estimatedValue')}<small>MMK</small></th><th>{t(locale, 'actions')}</th></tr></thead><tbody>{selectedProducts.map((product, rowIndex) => <tr key={product.product_id}><td><strong>{product.name}</strong><span className="muted">{product.sku} · {product.unit}</span></td><td className="price-matrix-cell"><input aria-label={`${product.name} ${t(locale, 'quantity')}`} data-grid-column="0" data-grid-row={rowIndex} required min="1" step="1" type="number" inputMode="numeric" value={product.quantity} onFocus={(event) => event.currentTarget.select()} onKeyDown={(event) => moveReceiptGrid(event, rowIndex, 0)} onPaste={(event) => pasteReceiptGrid(event, rowIndex, 0)} onChange={(event) => updateReceiptItem(product.product_id, { quantity: event.target.value })} /></td><td className="price-matrix-cell"><input aria-label={`${product.name} ${t(locale, 'cost')}`} data-grid-column="1" data-grid-row={rowIndex} min="0" step="0.01" type="number" value={product.unit_cost} onFocus={(event) => event.currentTarget.select()} onKeyDown={(event) => moveReceiptGrid(event, rowIndex, 1)} onPaste={(event) => pasteReceiptGrid(event, rowIndex, 1)} onChange={(event) => updateReceiptItem(product.product_id, { unit_cost: event.target.value })} /></td><td className="numeric stock-calculated-cell">{money(Number(product.quantity || 0) * Number(product.unit_cost || 0))}</td><td className="stock-entry-actions"><button className="icon-button danger" type="button" aria-label={`${t(locale, 'cancel')} ${product.name}`} onClick={() => toggleReceiptProduct(product)}><X size={14} /></button></td></tr>)}</tbody></table></div>
                                </section>}

                                {wizardStep === 3 && <section className="stock-wizard-stage">
                                    <div className="stock-wizard-stage-heading"><p className="eyebrow">4 / 4</p><h3>{t(locale, 'receiptReview')}</h3><span>{t(locale, 'receiptReviewHint')}</span></div>
                                    <dl className="stock-receive-review stock-receive-document-review stock-receipt-review-summary">
                                        <div><dt>{t(locale, 'movementType')}</dt><dd>{t(locale, form.movement_type)}</dd></div>
                                        <div><dt>{t(locale, 'supplier')}</dt><dd>{selectedSupplier?.label || (form.movement_type === 'opening' ? '-' : 'Not selected')}</dd></div>
                                        <div><dt>{t(locale, 'warehouse')}</dt><dd>{selectedWarehouse?.label || '-'}</dd></div>
                                        <div><dt>{t(locale, 'date')}</dt><dd>{formatDate(form.movement_date)}</dd></div>
                                        {form.movement_type === 'receive' && <div><dt>Settlement</dt><dd>{form.settlement_method === 'credit' ? 'Supplier credit' : `Paid from ${form.settlement_method} book`}</dd></div>}
                                        {form.movement_type === 'receive' && form.settlement_method === 'credit' && <div><dt>Payment terms</dt><dd>{number(form.payment_terms_days || 0)} days</dd></div>}
                                        {form.movement_type === 'receive' && form.settlement_method === 'credit' && <div><dt>Due date</dt><dd>{formatDate(calculatedDueDate)}</dd></div>}
                                        <div><dt>{t(locale, 'reference')}</dt><dd>{form.reference_code || '-'}</dd></div>
                                        <div><dt>{t(locale, 'products')}</dt><dd>{number(form.items.length)}</dd></div>
                                        <div><dt>{t(locale, 'totalQuantity')}</dt><dd>{number(receiptTotalQuantity)}</dd></div>
                                        <div><dt>{t(locale, 'estimatedValue')}</dt><dd>{money(receiptTotalValue)}</dd></div>
                                        {form.notes && <div className="stock-review-notes"><dt>{t(locale, 'notes')}</dt><dd>{form.notes}</dd></div>}
                                    </dl>
                                    <div className="master-table-wrap stock-receive-review-lines"><table className="master-table stock-receipt-review-table"><thead><tr><th>{t(locale, 'product')}</th><th>{t(locale, 'quantity')}</th><th>{t(locale, 'cost')}</th><th>{t(locale, 'stockValue')}</th></tr></thead><tbody>{selectedProducts.map((product) => <tr key={product.product_id}><td><strong>{product.name}</strong><span className="muted">{product.sku} · {product.unit}</span></td><td className="numeric">{number(product.quantity)}</td><td className="numeric">{money(product.unit_cost || 0)}</td><td className="numeric"><strong>{money(Number(product.quantity || 0) * Number(product.unit_cost || 0))}</strong></td></tr>)}</tbody></table></div>
                                </section>}
                            </div>
                            <footer className="stock-wizard-footer">
                                <span className="muted">Step {wizardStep + 1} of 4</span>
                                <div>
                                    <button className="button" type="button" onClick={closeForm}>{t(locale, 'cancel')}</button>
                                    {wizardStep < 3 ? <button className="button primary" type="button" disabled={!canContinueWizard} onClick={nextWizardStep}>{t(locale, 'next')}</button> : <button className="button primary" type="button" disabled={saving || !quantityComplete} onClick={saveMovement}><Save size={15} />{saving ? t(locale, 'loadingMovements') : t(locale, 'recordReceive')}</button>}
                                </div>
                            </footer>
                        </> : <>
                            <div className="master-form-body">
                                {formError && <p className="form-alert">{formError}</p>}
                                <div className="master-form-grid">
                                    <label>{t(locale, 'movementType')}<select value={form.movement_type} onChange={(event) => setForm((current) => ({ ...current, movement_type: event.target.value }))}>{movementConfig.formTypes.map((type) => <option value={type} key={type}>{t(locale, type)}</option>)}</select></label>
                                    <label>{t(locale, 'date')}<input type="date" value={form.movement_date} onChange={(event) => setForm((current) => ({ ...current, movement_date: event.target.value }))} /></label>
                                    <label>{t(locale, 'warehouse')}<select required value={form.warehouse_id} onChange={(event) => setForm((current) => ({ ...current, warehouse_id: event.target.value }))}>{meta.warehouses.map((warehouse) => <option value={warehouse.id} key={warehouse.id}>{warehouse.label}</option>)}</select></label>
                                    <label>{t(locale, 'product')}<select required value={form.product_id} onChange={(event) => setForm((current) => ({ ...current, product_id: event.target.value, unit_cost: meta.products.find((product) => Number(product.id) === Number(event.target.value))?.latest_cost || current.unit_cost }))}>{meta.products.map((product) => <option value={product.id} key={product.id}>{product.label}</option>)}</select></label>
                                    <label>{t(locale, 'quantity')}<input required min="1" step="1" type="number" inputMode="numeric" value={form.quantity} onChange={(event) => setForm((current) => ({ ...current, quantity: event.target.value }))} /></label>
                                    <label>{t(locale, 'cost')}<input min="0" step="0.01" type="number" value={form.unit_cost} onChange={(event) => setForm((current) => ({ ...current, unit_cost: event.target.value }))} /></label>
                                    <label>{t(locale, 'reference')}<input value={form.reference_code} onChange={(event) => setForm((current) => ({ ...current, reference_code: event.target.value }))} /></label>
                                    <label className="span-2">{t(locale, 'notes')}<textarea value={form.notes} onChange={(event) => setForm((current) => ({ ...current, notes: event.target.value }))} /></label>
                                </div>
                            </div>
                            <footer><button className="button" type="button" onClick={closeForm}>{t(locale, 'cancel')}</button><button className="button primary" type="submit" disabled={saving}><Save size={15} />{t(locale, 'save')}</button></footer>
                        </>}
                    </form>
                </div>
            )}
        </section>
    );
}

export function StockIssueScreen({ locale, canManage = false }) {
    return <StockReceiveScreen locale={locale} canManage={canManage} mode="issue" />;
}

export function StockAdjustmentScreen({ locale, canManage = false, creating = false, onNavigate }) {
    const listPath = `${window.ValleyRuntime?.routes?.office || '/office'}/stock/adjustments`;
    const [meta, setMeta] = useState({ loading: true, warehouses: [], products: [], error: '' });
    const [filters, setFilters] = useState({ search: '', warehouse_id: '', product_id: '', adjustment_reason: '', date: '' });
    const [state, setState] = useState({ loading: true, items: [], summary: {}, pageMeta: {}, error: '' });
    const [page, setPage] = useState(1);
    const [refreshKey, setRefreshKey] = useState(0);

    useEffect(() => {
        let mounted = true;
        window.axios.get(`${apiBase()}/meta`)
            .then(({ data }) => mounted && setMeta({ loading: false, warehouses: data.data.warehouses, products: data.data.products, error: '' }))
            .catch((error) => mounted && setMeta({ loading: false, warehouses: [], products: [], error: requestMessage(error) }));
        return () => { mounted = false; };
    }, []);

    useEffect(() => {
        if (creating) return undefined;
        let mounted = true;
        setState((current) => ({ ...current, loading: true, error: '' }));
        const timer = window.setTimeout(() => {
            window.axios.get(`${apiBase()}/movements`, {
                params: {
                    search: filters.search || undefined,
                    warehouse_id: filters.warehouse_id || undefined,
                    product_id: filters.product_id || undefined,
                    adjustment_reason: filters.adjustment_reason || undefined,
                    type_group: 'adjustment',
                    date: filters.date || undefined,
                    page,
                    per_page: 20,
                },
            }).then(({ data }) => {
                if (!mounted) return;
                setState({ loading: false, items: data.data.items, summary: data.data.summary, pageMeta: data.data.meta, error: '' });
            }).catch((error) => mounted && setState({ loading: false, items: [], summary: {}, pageMeta: {}, error: requestMessage(error) }));
        }, 220);
        return () => { mounted = false; window.clearTimeout(timer); };
    }, [creating, filters, page, refreshKey]);

    useEffect(() => setPage(1), [filters.search, filters.warehouse_id, filters.product_id, filters.adjustment_reason, filters.date]);

    if (creating) {
        return <StockAdjustmentFormPage locale={locale} canManage={canManage} meta={meta} onBack={() => onNavigate?.(listPath)} onSaved={(movement) => { setState((current) => ({ ...current, items: [movement, ...current.items], summary: { ...current.summary, records_count: Number(current.summary.records_count || 0) + 1, in_quantity: Number(current.summary.in_quantity || 0) + Math.max(Number(movement.signed_quantity || 0), 0), out_quantity: Number(current.summary.out_quantity || 0) + Math.abs(Math.min(Number(movement.signed_quantity || 0), 0)), stock_value: Number(current.summary.stock_value || 0) + Number(movement.total_cost || 0) }, pageMeta: { ...current.pageMeta, total: Number(current.pageMeta.total || 0) + 1 } })); onNavigate?.(listPath); }} />;
    }

    return (
        <section className="page stock-workspace stock-adjustment-workspace">
            <div className="master-heading">
                <div><p className="eyebrow">Warehouse Stock</p><h1>{t(locale, 'adjustments')}</h1><span className="muted">{t(locale, 'adjustmentHint')}</span></div>
                <ShellPageActions>{canManage && <button className="button primary" type="button" disabled={meta.loading || Boolean(meta.error)} onClick={() => onNavigate?.(`${listPath}/new`)}><Plus size={16} />{t(locale, 'newAdjustment')}</button>}</ShellPageActions>
            </div>

            {(meta.error || state.error) && <div className="inline-error">{meta.error || state.error}</div>}

            <div className="metrics stock-metrics">
                <Metric label={t(locale, 'adjustments')} value={number(state.summary.records_count)} hint={t(locale, 'movements')} icon={SlidersHorizontal} />
                <Metric label={t(locale, 'addStock')} value={number(state.summary.in_quantity)} hint={t(locale, 'quantity')} icon={Package} />
                <Metric label={t(locale, 'removeStock')} value={number(state.summary.out_quantity)} hint={t(locale, 'quantity')} icon={Package} />
                <Metric label={t(locale, 'adjustedValue')} value={money(Math.abs(state.summary.stock_value || 0))} hint="MMK" icon={Archive} />
            </div>

            <section className="master-panel">
                <div className="master-panel-heading"><div><p className="eyebrow">{t(locale, 'movements')}</p><h2>{t(locale, 'adjustmentHistory')}</h2></div></div>
                <div className="master-toolbar stock-toolbar stock-adjustment-toolbar">
                    <label className="master-search"><Search size={14} /><input value={filters.search} placeholder={t(locale, 'searchAdjustments')} onChange={(event) => setFilters((current) => ({ ...current, search: event.target.value }))} /></label>
                    <select aria-label={t(locale, 'warehouse')} value={filters.warehouse_id} onChange={(event) => setFilters((current) => ({ ...current, warehouse_id: event.target.value }))}><option value="">{t(locale, 'allWarehouses')}</option>{meta.warehouses.map((warehouse) => <option value={warehouse.id} key={warehouse.id}>{warehouse.label}</option>)}</select>
                    <select aria-label={t(locale, 'product')} value={filters.product_id} onChange={(event) => setFilters((current) => ({ ...current, product_id: event.target.value }))}><option value="">{t(locale, 'allProducts')}</option>{meta.products.map((product) => <option value={product.id} key={product.id}>{product.label}</option>)}</select>
                    <select aria-label={t(locale, 'adjustmentReason')} value={filters.adjustment_reason} onChange={(event) => setFilters((current) => ({ ...current, adjustment_reason: event.target.value }))}><option value="">All reasons</option>{adjustmentReasons.map((reason) => <option value={reason} key={reason}>{t(locale, reason)}</option>)}</select>
                    <label className="date-filter"><CalendarDays size={14} /><input aria-label={t(locale, 'date')} type="date" value={filters.date} onChange={(event) => setFilters((current) => ({ ...current, date: event.target.value }))} /></label>
                    <button className="button" type="button" onClick={() => setRefreshKey((value) => value + 1)}><RefreshCw size={15} />{t(locale, 'refresh')}</button>
                </div>

                {state.loading ? <WorkspaceState icon={RefreshCw} title={t(locale, 'loadingAdjustments')} loading /> : state.items.length === 0 ? <WorkspaceState icon={SlidersHorizontal} title={t(locale, 'emptyAdjustments')} /> : <>
                    <div className="master-table-wrap stock-adjustment-table-wrap">
                        <table className="master-table stock-adjustment-table">
                            <colgroup><col className="adjustment-reference-column" /><col className="adjustment-warehouse-column" /><col className="adjustment-product-column" /><col className="adjustment-reason-column" /><col className="adjustment-direction-column" /><col className="adjustment-date-column" /><col className="adjustment-quantity-column" /><col className="adjustment-value-column" /><col className="table-actions-column" /></colgroup>
                            <thead><tr><th>{t(locale, 'reference')}</th><th>{t(locale, 'warehouse')}</th><th>{t(locale, 'product')}</th><th>{t(locale, 'adjustmentReason')}</th><th>{t(locale, 'adjustmentDirection')}</th><th>{t(locale, 'date')}</th><th className="numeric">{t(locale, 'quantity')}</th><th className="numeric">{t(locale, 'adjustedValue')}</th><th className="table-actions-header">Actions</th></tr></thead>
                            <tbody>{state.items.map((movement) => {
                                const reason = movement.adjustment_reason || (movement.movement_type === 'damage' ? 'damage' : 'other');
                                const isIncrease = Number(movement.signed_quantity) > 0;
                                return <tr key={movement.id}>
                                    <td><strong>{movement.code}</strong><span className="muted">{movement.reference_code || '-'}</span></td>
                                    <td><strong>{movement.warehouse_code}</strong><span className="muted">{movement.warehouse_name}</span></td>
                                    <td><strong>{movement.product_name}</strong><span className="muted">{movement.product_sku} · {movement.unit}</span></td>
                                    <td><span className={`status ${['damage', 'expired', 'loss'].includes(reason) ? 'danger' : 'neutral'}`}>{t(locale, reason)}</span></td>
                                    <td><span className={`status ${isIncrease ? 'success' : 'warning'}`}>{isIncrease ? t(locale, 'addStock') : t(locale, 'removeStock')}</span></td>
                                    <td>{formatDate(movement.movement_date)}</td>
                                    <td className="numeric">{number(Math.abs(Number(movement.signed_quantity)))}</td>
                                    <td className="numeric"><strong>{money(movement.total_cost)}</strong></td>
                                    <td className="table-actions-cell"><div className="row-actions"><button className="icon-button" type="button" title="Print stock adjustment" aria-label={`Print ${movement.code}`} onClick={() => printConfiguredDocument('stock_adjustment', { reference: movement.code, facts: [['Warehouse', `${movement.warehouse_code} · ${movement.warehouse_name}`], ['Date', movement.movement_date], ['Reason', t(locale, reason)], ['Direction', isIncrease ? t(locale, 'addStock') : t(locale, 'removeStock')], ['Reference', movement.reference_code || '-']], columns: ['Product', 'Qty', 'Unit cost', 'Value'], rows: [[`${movement.product_name} · ${movement.product_sku}`, number(Math.abs(Number(movement.signed_quantity))), money(movement.unit_cost), money(movement.total_cost)]], total: ['Adjusted value', money(movement.total_cost)], notes: movement.notes })}><Printer size={14} /></button></div></td>
                                </tr>;
                            })}</tbody>
                        </table>
                    </div>
                    <Pagination meta={state.pageMeta} page={page} setPage={setPage} />
                </>}
            </section>
        </section>
    );
}

function StockAdjustmentFormPage({ locale, canManage, meta, onBack, onSaved }) {
    const today = new Date().toISOString().slice(0, 10);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [form, setForm] = useState({ movement_type: 'adjustment', warehouse_id: '', product_id: '', movement_date: today, adjustment_reason: 'damage', adjustment_direction: 'decrease', quantity: 1, unit_cost: '', reference_code: '', notes: '' });

    useEffect(() => {
        if (meta.loading || meta.error) return;
        setForm((current) => ({ ...current, warehouse_id: current.warehouse_id || meta.warehouses[0]?.id || '', product_id: current.product_id || meta.products[0]?.id || '', unit_cost: current.unit_cost || meta.products[0]?.latest_cost || '' }));
    }, [meta]);

    const selectReason = (reason) => setForm((current) => ({
        ...current,
        adjustment_reason: reason,
        adjustment_direction: ['found_stock'].includes(reason) ? 'increase' : ['damage', 'expired', 'loss', 'internal_use'].includes(reason) ? 'decrease' : current.adjustment_direction,
    }));
    const selectedWarehouse = meta.warehouses.find((warehouse) => Number(warehouse.id) === Number(form.warehouse_id));
    const selectedProduct = meta.products.find((product) => Number(product.id) === Number(form.product_id));
    const signedQuantity = Math.abs(Number(form.quantity || 0)) * (form.adjustment_direction === 'decrease' ? -1 : 1);
    const estimatedValue = Math.abs(Number(form.quantity || 0) * Number(form.unit_cost || 0));

    const submit = async (event) => {
        event.preventDefault();
        if (!canManage || saving) return;
        setSaving(true);
        setError('');
        try {
            const quantity = Math.abs(Number(form.quantity || 0)) * (form.adjustment_direction === 'decrease' ? -1 : 1);
            const { data } = await window.axios.post(`${apiBase()}/movements`, { ...form, quantity });
            onSaved(data.data.movement);
        } catch (requestError) {
            setSaving(false);
            setError(requestMessage(requestError));
        }
    };

    return (
        <section className="master-workspace stock-adjustment-form-page">
            <ShellBackButton onClick={onBack} label="Back to adjustments" />
            <div className="master-heading">
                <div><p className="eyebrow">Warehouse Stock</p><h1>{t(locale, 'newAdjustment')}</h1><span className="muted">Record why stock changed and whether the warehouse balance should increase or decrease.</span></div>
            </div>

            {(error || meta.error) && <div className="inline-error">{error || meta.error}</div>}
            {meta.loading ? <WorkspaceState icon={RefreshCw} title={t(locale, 'loadingAdjustments')} loading compact /> : (
                <form onSubmit={submit} className="master-panel stock-adjustment-form-panel">
                    <div className="master-panel-heading"><div><p className="eyebrow">Adjustment details</p><h2>Stock change</h2><span className="muted">Use this only to correct or explain a warehouse variance.</span></div></div>
                    <div className="master-form-body stock-adjustment-form-body">
                        <div className="master-form-grid stock-adjustment-fields">
                            <label><span className="stock-field-label">{t(locale, 'warehouse')}</span><select required value={form.warehouse_id} onChange={(event) => setForm((current) => ({ ...current, warehouse_id: event.target.value }))}><option value="">Select warehouse</option>{meta.warehouses.map((warehouse) => <option value={warehouse.id} key={warehouse.id}>{warehouse.label}</option>)}</select></label>
                            <label><span className="stock-field-label">{t(locale, 'product')}</span><select required value={form.product_id} onChange={(event) => { const product = meta.products.find((item) => Number(item.id) === Number(event.target.value)); setForm((current) => ({ ...current, product_id: event.target.value, unit_cost: product?.latest_cost || '' })); }}><option value="">Select product</option>{meta.products.map((product) => <option value={product.id} key={product.id}>{product.label}</option>)}</select></label>
                            <label><span className="stock-field-label">{t(locale, 'adjustmentReason')}</span><select required value={form.adjustment_reason} onChange={(event) => selectReason(event.target.value)}>{adjustmentReasons.map((reason) => <option value={reason} key={reason}>{t(locale, reason)}</option>)}</select></label>
                            <label><span className="stock-field-label">{t(locale, 'adjustmentDirection')}</span><select required value={form.adjustment_direction} onChange={(event) => setForm((current) => ({ ...current, adjustment_direction: event.target.value }))}><option value="decrease">{t(locale, 'removeStock')}</option><option value="increase">{t(locale, 'addStock')}</option></select></label>
                            <label><span className="stock-field-label">{t(locale, 'quantity')}</span><input required type="number" inputMode="numeric" min="1" step="1" value={form.quantity} onChange={(event) => setForm((current) => ({ ...current, quantity: event.target.value }))} /></label>
                            <label><span className="stock-field-label">{t(locale, 'cost')} <small>{t(locale, 'optional')}</small></span><input type="number" min="0" step="0.01" value={form.unit_cost} onChange={(event) => setForm((current) => ({ ...current, unit_cost: event.target.value }))} /></label>
                            <label><span className="stock-field-label">{t(locale, 'date')}</span><input required type="date" value={form.movement_date} onChange={(event) => setForm((current) => ({ ...current, movement_date: event.target.value }))} /></label>
                            <label><span className="stock-field-label">{t(locale, 'reference')} <small>{t(locale, 'optional')}</small></span><input maxLength="80" value={form.reference_code} onChange={(event) => setForm((current) => ({ ...current, reference_code: event.target.value }))} /></label>
                            <label className="span-2"><span className="stock-field-label">{t(locale, 'notes')} {form.adjustment_reason === 'other' ? '*' : <small>{t(locale, 'optional')}</small>}</span><textarea required={form.adjustment_reason === 'other'} rows="4" maxLength="500" value={form.notes} onChange={(event) => setForm((current) => ({ ...current, notes: event.target.value }))} /></label>
                        </div>
                        <aside className="stock-adjustment-summary" aria-label="Adjustment preview">
                            <div className={`stock-adjustment-impact ${form.adjustment_direction === 'increase' ? 'is-increase' : 'is-decrease'}`}><SlidersHorizontal size={18} /><div><small>Balance impact</small><strong>{signedQuantity > 0 ? '+' : ''}{number(signedQuantity)} {selectedProduct?.unit || ''}</strong><span>{t(locale, form.adjustment_reason)} · {t(locale, form.adjustment_direction === 'increase' ? 'addStock' : 'removeStock')}</span></div></div>
                            <dl>
                                <div><dt>{t(locale, 'warehouse')}</dt><dd>{selectedWarehouse?.code || '-'}</dd></div>
                                <div><dt>{t(locale, 'product')}</dt><dd>{selectedProduct?.name || '-'}</dd></div>
                                <div><dt>{t(locale, 'cost')}</dt><dd>{money(form.unit_cost || 0)}</dd></div>
                                <div><dt>{t(locale, 'adjustedValue')}</dt><dd>{money(estimatedValue)}</dd></div>
                            </dl>
                            <p>Receipts and transfers should be recorded in their own workflows so this ledger stays focused on exceptional stock changes.</p>
                        </aside>
                    </div>
                    <footer><button className="button" type="button" onClick={onBack}>{t(locale, 'cancel')}</button><button className="button primary" type="submit" disabled={!canManage || saving}><Save size={15} />{saving ? t(locale, 'loadingAdjustments') : t(locale, 'recordAdjustment')}</button></footer>
                </form>
            )}
        </section>
    );
}

export function ClosingStockScreen({ locale, canManage = false, closingForm = false, detailId = null, onNavigate }) {
    const [meta, setMeta] = useState({ loading: true, warehouses: [], products: [], error: '' });
    const [filters, setFilters] = useState({ search: '', warehouse_id: '', product_id: '', date: '' });
    const [state, setState] = useState({ loading: true, items: [], summary: {}, pageMeta: {}, error: '' });
    const [page, setPage] = useState(1);
    const [refreshKey, setRefreshKey] = useState(0);
    const [saving, setSaving] = useState(false);
    const [loadingWorksheet, setLoadingWorksheet] = useState(false);
    const [formError, setFormError] = useState('');
    const [form, setForm] = useState(blankClosingForm);
    const [wizardStep, setWizardStep] = useState(0);
    const [productSearch, setProductSearch] = useState('');
    const listPath = `${window.ValleyRuntime?.routes?.office || '/office'}/stock/closing`;

    useEffect(() => {
        let mounted = true;
        window.axios.get(`${apiBase()}/meta`).then(({ data }) => {
            if (!mounted) return;
            setMeta({ loading: false, warehouses: data.data.warehouses, products: data.data.products, error: '' });
            setForm((current) => ({ ...current, warehouse_id: current.warehouse_id || data.data.warehouses[0]?.id || '' }));
        }).catch((error) => mounted && setMeta({ loading: false, warehouses: [], products: [], error: requestMessage(error) }));
        return () => { mounted = false; };
    }, []);

    useEffect(() => {
        if (closingForm || detailId) return undefined;
        let mounted = true;
        setState((current) => ({ ...current, loading: true, error: '' }));
        const timer = window.setTimeout(() => {
            window.axios.get(`${apiBase()}/closing-counts`, { params: { search: filters.search || undefined, warehouse_id: filters.warehouse_id || undefined, product_id: filters.product_id || undefined, date: filters.date || undefined, page, per_page: 20 } })
                .then(({ data }) => mounted && setState({ loading: false, items: data.data.items, summary: data.data.summary, pageMeta: data.data.meta, error: '' }))
                .catch((error) => mounted && setState({ loading: false, items: [], summary: {}, pageMeta: {}, error: requestMessage(error) }));
        }, 220);
        return () => { mounted = false; window.clearTimeout(timer); };
    }, [closingForm, detailId, filters, page, refreshKey]);

    useEffect(() => { setPage(1); }, [filters.search, filters.warehouse_id, filters.product_id, filters.date]);

    useEffect(() => {
        if (!closingForm || meta.loading) return;
        setForm({ ...blankClosingForm, warehouse_id: meta.warehouses[0]?.id || '', movement_date: new Date().toISOString().slice(0, 10) });
        setWizardStep(0);
        setProductSearch('');
        setFormError('');
        setSaving(false);
    }, [closingForm, meta.loading]);

    const navigate = (path) => onNavigate ? onNavigate(path) : window.location.assign(path);
    const closeForm = () => navigate(listPath);
    const selectedWarehouse = meta.warehouses.find((warehouse) => Number(warehouse.id) === Number(form.warehouse_id));
    const completedItems = form.items.filter((item) => item.counted_quantity !== '');
    const changedItems = form.items.filter((item) => item.counted_quantity !== '' && Math.abs(Number(item.counted_quantity) - Number(item.system_quantity)) > 0.0001);
    const quantityAdded = changedItems.reduce((sum, item) => sum + Math.max(Number(item.counted_quantity) - Number(item.system_quantity), 0), 0);
    const quantityRemoved = changedItems.reduce((sum, item) => sum + Math.abs(Math.min(Number(item.counted_quantity) - Number(item.system_quantity), 0)), 0);
    const varianceValue = changedItems.reduce((sum, item) => sum + ((Number(item.counted_quantity) - Number(item.system_quantity)) * Number(item.unit_cost || 0)), 0);
    const allCounted = form.items.length > 0 && completedItems.length === form.items.length;
    const costsComplete = changedItems.every((item) => Number(item.counted_quantity) <= Number(item.system_quantity) || Number(item.unit_cost) > 0);
    const visibleItems = form.items.filter((item) => `${item.sku} ${item.name}`.toLowerCase().includes(productSearch.trim().toLowerCase()));

    const loadWorksheet = async () => {
        if (!form.warehouse_id || loadingWorksheet) return;
        setLoadingWorksheet(true);
        setFormError('');
        try {
            const { data } = await window.axios.get(`${apiBase()}/closing-counts/preview`, { params: { warehouse_id: form.warehouse_id } });
            setForm((current) => ({ ...current, items: data.data.items.map((item) => ({ ...item, counted_quantity: '' })) }));
            setWizardStep(1);
        } catch (error) {
            setFormError(requestMessage(error));
        } finally {
            setLoadingWorksheet(false);
        }
    };

    const updateCount = (productId, field, value) => setForm((current) => ({ ...current, items: current.items.map((item) => Number(item.product_id) === Number(productId) ? { ...item, [field]: value } : item) }));
    const fillSystemCounts = () => setForm((current) => ({ ...current, items: current.items.map((item) => ({ ...item, counted_quantity: String(item.system_quantity) })) }));

    const saveCount = async () => {
        if (wizardStep !== 3 || !allCounted || !costsComplete || saving) return;
        setSaving(true);
        setFormError('');
        try {
            const { data } = await window.axios.post(`${apiBase()}/closing-counts`, form);
            const count = data.data.count;
            setState((current) => ({ ...current, items: [count, ...current.items], summary: { ...current.summary, records_count: Number(current.summary.records_count || 0) + 1, in_quantity: Number(current.summary.in_quantity || 0) + Number(count.quantity_added || 0), out_quantity: Number(current.summary.out_quantity || 0) + Number(count.quantity_removed || 0), stock_value: Number(current.summary.stock_value || 0) + Number(count.variance_value || 0) }, pageMeta: { ...current.pageMeta, total: Number(current.pageMeta.total || 0) + 1 } }));
            closeForm();
        } catch (error) {
            setSaving(false);
            setFormError(requestMessage(error));
        }
    };

    if (detailId) return <ClosingStockDetailPage id={detailId} locale={locale} onBack={() => navigate(listPath)} />;

    if (closingForm) {
        const steps = [['Count details', 'Warehouse, date and reference'], ['Physical count', 'Count every active product'], ['Variance review', 'Check additions and shortages'], ['Confirm', 'Post the complete stock count']];
        const canOpenStep = (index) => index === 0 || (form.items.length > 0 && (index === 1 || allCounted));
        if (meta.loading) return <section className="page"><WorkspaceState icon={RefreshCw} title={t(locale, 'loadingClosing')} loading /></section>;

        return <section className="master-workspace stock-receive-form-page closing-count-workspace">
            <ShellBackButton onClick={() => wizardStep > 0 ? setWizardStep((current) => current - 1) : closeForm()} label={wizardStep > 0 ? "Previous step" : "Back to stock counts"} />
            <form className="master-dialog stock-dialog stock-wizard-dialog stock-wizard-page" onSubmit={(event) => event.preventDefault()}>
                <header className="stock-wizard-page-heading"><div><h1>New stock count</h1><p>Count every active product in one warehouse-level record.</p></div></header>
                <div className="stock-wizard-stepper-wrap"><ol className="trip-wizard-steps stock-receive-steps" aria-label="Stock count progress">{steps.map(([label, hint], index) => <li key={label} className={wizardStep === index ? 'is-current' : index < wizardStep ? 'is-complete' : ''}><button type="button" disabled={!canOpenStep(index)} onClick={() => canOpenStep(index) && setWizardStep(index)} aria-current={wizardStep === index ? 'step' : undefined}><span>{index < wizardStep ? <Check size={13} /> : index + 1}</span><strong>{label}</strong><small>{hint}</small></button></li>)}</ol></div>
                <div className="master-form-body stock-wizard-body">
                    {(formError || meta.error) && <p className="form-alert" role="alert">{formError || meta.error}</p>}
                    {wizardStep === 0 && <section className="stock-wizard-stage"><div className="stock-wizard-stage-heading"><p className="eyebrow">1 / 4</p><h3>Count details</h3><span>Choose the warehouse and document information for this physical count.</span></div><div className="master-form-grid stock-wizard-basic"><label><span className="stock-field-label">{t(locale, 'warehouse')}</span><select required value={form.warehouse_id} onChange={(event) => setForm((current) => ({ ...current, warehouse_id: event.target.value, items: [] }))}><option value="">Select warehouse</option>{meta.warehouses.map((warehouse) => <option key={warehouse.id} value={warehouse.id}>{warehouse.label}</option>)}</select></label><label><span className="stock-field-label">Count date</span><input required type="date" value={form.movement_date} onChange={(event) => setForm((current) => ({ ...current, movement_date: event.target.value }))} /></label><label><span className="stock-field-label">{t(locale, 'reference')} <small>{t(locale, 'optional')}</small></span><input maxLength="80" value={form.reference_code} onChange={(event) => setForm((current) => ({ ...current, reference_code: event.target.value }))} /></label><label className="span-2"><span className="stock-field-label">{t(locale, 'notes')} <small>{t(locale, 'optional')}</small></span><textarea rows="3" maxLength="500" value={form.notes} onChange={(event) => setForm((current) => ({ ...current, notes: event.target.value }))} /></label></div></section>}
                    {wizardStep === 1 && <section className="stock-wizard-stage"><div className="stock-wizard-stage-heading"><p className="eyebrow">2 / 4</p><h3>Physical count</h3><span>Enter the quantity physically found for every active product.</span></div><div className="stock-line-summary closing-count-progress"><span><strong>{completedItems.length} / {form.items.length}</strong> products counted</span><button className="button" type="button" onClick={fillSystemCounts}>Use system quantities</button></div><label className="master-search stock-product-search"><Search size={14} /><input value={productSearch} onChange={(event) => setProductSearch(event.target.value)} placeholder="Search products in this count" /></label><div className="master-table-wrap closing-count-entry-wrap"><table className="master-table price-matrix-table closing-count-entry-table"><thead><tr><th>{t(locale, 'product')}</th><th>System qty</th><th>Counted qty</th><th>Variance</th><th>Unit cost<small>MMK</small></th></tr></thead><tbody>{visibleItems.map((item) => { const variance = item.counted_quantity === '' ? null : Number(item.counted_quantity) - Number(item.system_quantity); return <tr key={item.product_id}><td><strong>{item.name}</strong><span className="muted">{item.sku} · {item.unit}</span></td><td className="numeric">{number(item.system_quantity)}</td><td className="price-matrix-cell"><input aria-label={`${item.name} counted quantity`} min="0" step="1" type="number" inputMode="numeric" value={item.counted_quantity} onFocus={(event) => event.currentTarget.select()} onChange={(event) => updateCount(item.product_id, 'counted_quantity', event.target.value)} /></td><td className={`numeric ${variance < 0 ? 'text-danger' : variance > 0 ? 'text-success' : ''}`}>{variance === null ? '—' : `${variance > 0 ? '+' : ''}${number(variance)}`}</td><td className="price-matrix-cell"><input aria-label={`${item.name} unit cost`} min="0" step="0.01" type="number" readOnly={Number(item.unit_cost) > 0 && Number(item.system_quantity) > 0} value={item.unit_cost || ''} onChange={(event) => updateCount(item.product_id, 'unit_cost', event.target.value)} /></td></tr>; })}</tbody></table></div></section>}
                    {wizardStep === 2 && <section className="stock-wizard-stage"><div className="stock-wizard-stage-heading"><p className="eyebrow">3 / 4</p><h3>Variance review</h3><span>Review differences before the stock balance is updated.</span></div><div className="closing-count-summary"><div><span>Products counted</span><strong>{number(form.items.length)}</strong></div><div><span>Changed products</span><strong>{number(changedItems.length)}</strong></div><div><span>Quantity added</span><strong className="text-success">+{number(quantityAdded)}</strong></div><div><span>Quantity removed</span><strong className="text-danger">-{number(quantityRemoved)}</strong></div><div><span>Net variance value</span><strong>{money(varianceValue)}</strong></div></div><div className="master-table-wrap closing-count-review-wrap"><table className="master-table closing-count-review-table"><thead><tr><th>{t(locale, 'product')}</th><th>System qty</th><th>Counted qty</th><th>Variance</th><th>Variance value</th></tr></thead><tbody>{changedItems.length === 0 ? <tr><td colSpan="5" className="closing-count-no-variance"><Check size={16} />All counted quantities match the system balance.</td></tr> : changedItems.map((item) => { const variance = Number(item.counted_quantity) - Number(item.system_quantity); return <tr key={item.product_id}><td><strong>{item.name}</strong><span className="muted">{item.sku} · {item.unit}</span></td><td className="numeric">{number(item.system_quantity)}</td><td className="numeric">{number(item.counted_quantity)}</td><td className={`numeric ${variance < 0 ? 'text-danger' : 'text-success'}`}>{variance > 0 ? '+' : ''}{number(variance)}</td><td className="numeric">{money(variance * Number(item.unit_cost || 0))}</td></tr>; })}</tbody></table></div></section>}
                    {wizardStep === 3 && <section className="stock-wizard-stage"><div className="stock-wizard-stage-heading"><p className="eyebrow">4 / 4</p><h3>Confirm stock count</h3><span>This saves every product line and adjusts only the recorded variances.</span></div><dl className="stock-receive-review stock-receive-document-review"><div><dt>{t(locale, 'warehouse')}</dt><dd>{selectedWarehouse?.label || '—'}</dd></div><div><dt>Count date</dt><dd>{formatDate(form.movement_date)}</dd></div><div><dt>{t(locale, 'products')}</dt><dd>{number(form.items.length)}</dd></div><div><dt>Changed products</dt><dd>{number(changedItems.length)}</dd></div><div><dt>Quantity added</dt><dd className="text-success">+{number(quantityAdded)}</dd></div><div><dt>Quantity removed</dt><dd className="text-danger">-{number(quantityRemoved)}</dd></div><div><dt>Net variance value</dt><dd>{money(varianceValue)}</dd></div><div><dt>{t(locale, 'reference')}</dt><dd>{form.reference_code || '—'}</dd></div>{form.notes && <div className="stock-review-notes"><dt>{t(locale, 'notes')}</dt><dd>{form.notes}</dd></div>}</dl><div className="closing-count-confirm-note"><Archive size={18} /><div><strong>One complete warehouse snapshot</strong><span>All {form.items.length} active products will be stored, including {form.items.length - changedItems.length} products with no variance.</span></div></div></section>}
                </div>
                <footer className="stock-wizard-footer"><span className="muted">Step {wizardStep + 1} of 4</span><div><button className="button" type="button" onClick={closeForm}>{t(locale, 'cancel')}</button>{wizardStep === 0 ? <button className="button primary" type="button" disabled={!form.warehouse_id || loadingWorksheet} onClick={loadWorksheet}>{loadingWorksheet ? 'Loading…' : t(locale, 'next')}</button> : wizardStep < 3 ? <button className="button primary" type="button" disabled={!allCounted || !costsComplete} onClick={() => setWizardStep((current) => current + 1)}>{t(locale, 'next')}</button> : <button className="button primary" type="button" disabled={saving || !allCounted || !costsComplete} onClick={saveCount}><Save size={15} />{saving ? 'Recording…' : 'Record stock count'}</button>}</div></footer>
            </form>
        </section>;
    }

    return <section className="page stock-workspace">
        <div className="master-heading"><div><p className="eyebrow">Phase 5 · Warehouse Stock</p><h1>{t(locale, 'closingStock')}</h1><span className="muted">Warehouse-level physical count snapshots and their resulting variances.</span></div><ShellPageActions>{canManage && <button className="button primary" type="button" onClick={() => navigate(`${listPath}/new`)} disabled={meta.loading || Boolean(meta.error)}><Plus size={16} />New stock count</button>}</ShellPageActions></div>
        <div className="metrics stock-metrics"><Metric label="Stock counts" value={number(state.summary.records_count)} hint="Completed sessions" icon={Archive} /><Metric label={t(locale, 'increaseQuantity')} value={number(state.summary.in_quantity)} hint={t(locale, 'quantity')} icon={Package} /><Metric label={t(locale, 'decreaseQuantity')} value={number(state.summary.out_quantity)} hint={t(locale, 'quantity')} icon={Package} /><Metric label="Net variance value" value={money(state.summary.stock_value)} hint="MMK" icon={Archive} /></div>
        <section className="master-panel"><div className="master-panel-heading"><div><p className="eyebrow">Physical counts</p><h2>Stock count history</h2></div></div><div className="master-toolbar stock-toolbar"><label className="master-search"><Search size={14} /><input value={filters.search} placeholder="Search count, warehouse or product" onChange={(event) => setFilters((current) => ({ ...current, search: event.target.value }))} /></label><select value={filters.warehouse_id} onChange={(event) => setFilters((current) => ({ ...current, warehouse_id: event.target.value }))}><option value="">{t(locale, 'allWarehouses')}</option>{meta.warehouses.map((warehouse) => <option value={warehouse.id} key={warehouse.id}>{warehouse.label}</option>)}</select><select value={filters.product_id} onChange={(event) => setFilters((current) => ({ ...current, product_id: event.target.value }))}><option value="">{t(locale, 'allProducts')}</option>{meta.products.map((product) => <option value={product.id} key={product.id}>{product.label}</option>)}</select><label className="date-filter"><CalendarDays size={14} /><input type="date" value={filters.date} onChange={(event) => setFilters((current) => ({ ...current, date: event.target.value }))} /></label><button className="button" type="button" onClick={() => setRefreshKey((value) => value + 1)}><RefreshCw size={15} />{t(locale, 'refresh')}</button></div>
            {state.loading ? <WorkspaceState icon={RefreshCw} title={t(locale, 'loadingClosing')} loading /> : state.error || meta.error ? <WorkspaceState icon={RefreshCw} title={state.error || meta.error} action={() => setRefreshKey((value) => value + 1)} actionLabel={t(locale, 'retry')} /> : state.items.length === 0 ? <WorkspaceState icon={Package} title={t(locale, 'emptyClosing')} /> : <><div className="master-table-wrap"><table className="master-table closing-stock-table"><thead><tr><th>Count</th><th>{t(locale, 'warehouse')}</th><th>{t(locale, 'date')}</th><th>{t(locale, 'products')}</th><th>System qty</th><th>Counted qty</th><th>Added</th><th>Removed</th><th>Variance value</th></tr></thead><tbody>{state.items.map((count) => <tr className="clickable-row" key={count.id} tabIndex={0} onClick={() => navigate(`${listPath}/${count.id}`)} onKeyDown={(event) => { if (event.target === event.currentTarget && ['Enter', ' '].includes(event.key)) { event.preventDefault(); navigate(`${listPath}/${count.id}`); } }}><td><strong>{count.code}</strong><span className="muted">{count.reference_code || '—'}</span></td><td><strong>{count.warehouse_code}</strong><span className="muted">{count.warehouse_name}</span></td><td>{formatDate(count.count_date)}</td><td className="numeric">{number(count.products_count)}</td><td className="numeric">{number(count.system_quantity)}</td><td className="numeric">{number(count.counted_quantity)}</td><td className="numeric text-success">{number(count.quantity_added)}</td><td className="numeric text-danger">{number(count.quantity_removed)}</td><td className="numeric"><strong>{money(count.variance_value)}</strong></td></tr>)}</tbody></table></div><Pagination meta={state.pageMeta} page={page} setPage={setPage} /></>}
        </section>
    </section>;
}

function ClosingStockDetailPage({ id, locale, onBack }) {
    const [state, setState] = useState({ loading: true, count: null, items: [], error: '' });

    useEffect(() => {
        let mounted = true;
        setState({ loading: true, count: null, items: [], error: '' });
        window.axios.get(`${apiBase()}/closing-counts/${id}`)
            .then(({ data }) => {
                if (!mounted) return;
                setState({ loading: false, count: data.data.count, items: data.data.items, error: '' });
            })
            .catch((error) => {
                if (!mounted) return;
                setState({ loading: false, count: null, items: [], error: requestMessage(error) });
            });

        return () => { mounted = false; };
    }, [id]);

    if (state.loading) {
        return <DetailPage eyebrow="Warehouse stock" title="Loading stock count" onBack={onBack}><DetailPanel><WorkspaceState icon={RefreshCw} title="Loading stock count" loading /></DetailPanel></DetailPage>;
    }

    if (state.error || !state.count) {
        return <DetailPage eyebrow="Warehouse stock" title="Stock count" onBack={onBack}><DetailPanel><WorkspaceState icon={Archive} title={state.error || 'Stock count not found.'} /></DetailPanel></DetailPage>;
    }

    const { count, items } = state;
    const changedProducts = items.filter((item) => Math.abs(Number(item.variance_quantity)) > 0.0001).length;
    const matchedProducts = items.length - changedProducts;

    const summary = (
        <DetailPanel eyebrow="Summary" className="closing-count-detail-summary">
            <div className="closing-count-detail-total">
                <Archive size={20} />
                <div><span>Counted quantity</span><strong>{number(count.counted_quantity)}</strong><small>{number(count.products_count)} products</small></div>
            </div>
            <dl>
                <div><dt>Changed products</dt><dd>{number(changedProducts)}</dd></div>
                <div><dt>Matched products</dt><dd>{number(matchedProducts)}</dd></div>
                <div><dt>Quantity added</dt><dd className="text-success">+{number(count.quantity_added)}</dd></div>
                <div><dt>Quantity removed</dt><dd className="text-danger">-{number(count.quantity_removed)}</dd></div>
                <div><dt>Net variance value</dt><dd>{money(count.variance_value)}</dd></div>
            </dl>
        </DetailPanel>
    );

    const itemTable = (
        <DetailPanel eyebrow="Products" title={`${number(items.length)} count lines`} className="closing-count-detail-items">
            <div className="master-table-wrap">
                <table className="master-table closing-count-detail-table">
                    <thead><tr><th>{t(locale, 'product')}</th><th>System qty</th><th>Counted qty</th><th>Variance</th><th>Unit cost<small>MMK</small></th><th>Variance value<small>MMK</small></th><th>Adjustment</th></tr></thead>
                    <tbody>{items.map((item) => {
                        const variance = Number(item.variance_quantity);
                        return <tr key={item.id}>
                            <td><strong>{item.product_name}</strong><span className="muted">{item.product_sku} · {item.unit}</span></td>
                            <td className="numeric">{number(item.system_quantity)}</td>
                            <td className="numeric">{number(item.counted_quantity)}</td>
                            <td className={`numeric ${variance < 0 ? 'text-danger' : variance > 0 ? 'text-success' : ''}`}>{variance > 0 ? '+' : ''}{number(variance)}</td>
                            <td className="numeric">{number(item.unit_cost)}</td>
                            <td className={`numeric ${Number(item.variance_value) < 0 ? 'text-danger' : Number(item.variance_value) > 0 ? 'text-success' : ''}`}>{number(item.variance_value)}</td>
                            <td>{item.movement_code ? <><strong>{item.movement_code}</strong><span className="muted">Stock adjusted</span></> : <span className="closing-count-match"><Check size={13} /> Balance matched</span>}</td>
                        </tr>;
                    })}</tbody>
                </table>
            </div>
        </DetailPanel>
    );

    return (
        <DetailPage
            eyebrow="Warehouse stock"
            title={count.code}
            subtitle={`${count.warehouse_code} · ${count.warehouse_name}`}
            onBack={onBack}
            aside={summary}
            wideContent={itemTable}
        >
            <DetailPanel eyebrow="Details" title="Closing stock">
                <dl className="record-page-facts closing-count-detail-facts">
                    <div><dt>{t(locale, 'warehouse')}</dt><dd>{count.warehouse_code} · {count.warehouse_name}</dd></div>
                    <div><dt>Count date</dt><dd>{formatDate(count.count_date)}</dd></div>
                    <div><dt>System quantity</dt><dd>{number(count.system_quantity)}</dd></div>
                    <div><dt>Counted quantity</dt><dd>{number(count.counted_quantity)}</dd></div>
                    <div><dt>{t(locale, 'reference')}</dt><dd>{count.reference_code || '—'}</dd></div>
                    <div><dt>Status</dt><dd><span className="status success">Completed</span></dd></div>
                    <div><dt>Recorded by</dt><dd>{count.created_by_name || 'System'}</dd></div>
                    <div><dt>Recorded at</dt><dd>{formatDateTime(count.created_at)}</dd></div>
                    <div className="closing-count-detail-notes"><dt>{t(locale, 'notes')}</dt><dd>{count.notes || '—'}</dd></div>
                </dl>
            </DetailPanel>
        </DetailPage>
    );
}

export function StockOverviewScreen({ view = 'balance', locale, onNavigate }) {
    const showingValue = view === 'value';
    const [meta, setMeta] = useState({ loading: true, warehouses: [], products: [], error: '' });
    const [filters, setFilters] = useState({ search: '', product_id: '' });
    const [state, setState] = useState({ loading: true, items: [], warehouses: [], summary: {}, pageMeta: {}, error: '' });
    const [page, setPage] = useState(1);
    const [refreshKey, setRefreshKey] = useState(0);

    useEffect(() => {
        let mounted = true;
        window.axios.get(`${apiBase()}/meta`)
            .then(({ data }) => mounted && setMeta({ loading: false, warehouses: data.data.warehouses, products: data.data.products, error: '' }))
            .catch((error) => mounted && setMeta({ loading: false, warehouses: [], products: [], error: requestMessage(error) }));
        return () => { mounted = false; };
    }, []);

    useEffect(() => { setPage(1); }, [view, filters.search, filters.product_id]);

    useEffect(() => {
        let mounted = true;
        setState((current) => ({ ...current, loading: true, error: '' }));
        const timer = window.setTimeout(() => {
            window.axios.get(`${apiBase()}/${showingValue ? 'value' : 'balances'}`, {
                params: { search: filters.search || undefined, product_id: filters.product_id || undefined, page, per_page: 20 },
            }).then(({ data }) => {
                if (!mounted) return;
                setState({ loading: false, items: data.data.items, warehouses: data.data.warehouses, summary: data.data.summary, pageMeta: data.data.meta, error: '' });
            }).catch((error) => mounted && setState({ loading: false, items: [], warehouses: [], summary: {}, pageMeta: {}, error: requestMessage(error) }));
        }, 220);
        return () => { mounted = false; window.clearTimeout(timer); };
    }, [showingValue, filters, page, refreshKey]);

    const openView = (nextView) => {
        const officePath = window.location.pathname.split('/stock/')[0];
        const path = `${officePath}/stock/overview/${nextView}`;
        if (onNavigate) onNavigate(path);
        else window.location.assign(path);
    };
    const totalValue = Number(state.summary.stock_value || 0);
    const loadingTitle = showingValue ? t(locale, 'loadingValues') : t(locale, 'loadingBalances');
    const emptyTitle = showingValue ? t(locale, 'emptyValues') : t(locale, 'emptyBalances');

    return (
        <section className="page stock-workspace">
            <div className="master-heading">
                <div>
                    <p className="eyebrow">Phase 5 {'\u00b7'} Warehouse Stock</p>
                    <h1>Stock overview</h1>
                    <span className="muted">Review current quantities and inventory value by product and warehouse.</span>
                </div>
                <ShellPageActions><button className="button" type="button" onClick={() => setRefreshKey((value) => value + 1)}><RefreshCw size={15} />{t(locale, 'refresh')}</button></ShellPageActions>
            </div>

            <div className="metrics stock-metrics">
                {showingValue && <Metric label={t(locale, 'stockValue')} value={money(totalValue)} hint="MMK" icon={Archive} />}
                <Metric label={t(locale, 'products')} value={number(state.summary.products_count)} hint={showingValue ? t(locale, 'rankedValue') : t(locale, 'balances')} icon={Package} />
                <Metric label={t(locale, 'warehouses')} value={number(state.summary.warehouses_count)} hint={showingValue ? t(locale, 'warehouseValue') : 'Visible locations'} icon={Archive} />
                <Metric label={t(locale, 'totalQuantity')} value={number(state.summary.total_quantity)} hint={t(locale, 'unit')} icon={Package} />
            </div>

            <section className="master-panel stock-overview-panel">
                <nav className="customer-history-tabs stock-overview-tabs" aria-label="Stock overview measure">
                    <button className={!showingValue ? 'is-active' : ''} type="button" onClick={() => openView('balance')} aria-current={!showingValue ? 'page' : undefined}><Package size={15} />Stock balance</button>
                    <button className={showingValue ? 'is-active' : ''} type="button" onClick={() => openView('value')} aria-current={showingValue ? 'page' : undefined}><Archive size={15} />Stock value</button>
                </nav>
                <div className="master-panel-heading">
                    <div><p className="eyebrow">{showingValue ? t(locale, 'stockValue') : t(locale, 'balances')}</p><h2>{showingValue ? 'Value by warehouse' : 'Stock by warehouse'}</h2></div>
                    {showingValue && <span className="muted">{number(state.summary.balance_lines)} {t(locale, 'balanceLines')}</span>}
                </div>
                <div className="master-toolbar stock-toolbar">
                    <label className="master-search"><Search size={14} /><input value={filters.search} placeholder={showingValue ? t(locale, 'searchValues') : t(locale, 'searchBalances')} onChange={(event) => setFilters((current) => ({ ...current, search: event.target.value }))} /></label>
                    <select value={filters.product_id} onChange={(event) => setFilters((current) => ({ ...current, product_id: event.target.value }))}>
                        <option value="">{t(locale, 'allProducts')}</option>
                        {meta.products.map((product) => <option value={product.id} key={product.id}>{product.label}</option>)}
                    </select>
                </div>

                {state.loading ? (
                    <WorkspaceState icon={RefreshCw} title={loadingTitle} loading />
                ) : state.error || meta.error ? (
                    <WorkspaceState icon={RefreshCw} title={state.error || meta.error} action={() => setRefreshKey((value) => value + 1)} actionLabel={t(locale, 'retry')} />
                ) : state.items.length === 0 ? (
                    <WorkspaceState icon={Package} title={emptyTitle} />
                ) : (
                    <>
                        <div className="master-table-wrap">
                            <table className={`master-table stock-balance-matrix ${showingValue ? 'stock-value-matrix' : ''}`} style={{ minWidth: `${Math.max(680, 320 + ((state.warehouses.length + 1) * (showingValue ? 160 : 150)))}px` }}>
                                <colgroup><col style={{ width: '32%' }} />{state.warehouses.map((warehouse) => <col key={warehouse.id} style={{ width: `${68 / (state.warehouses.length + 1)}%` }} />)}<col style={{ width: `${68 / (state.warehouses.length + 1)}%` }} /></colgroup>
                                <thead><tr><th>{t(locale, 'product')}</th>{state.warehouses.map((warehouse) => <th className="numeric" key={warehouse.id}><strong>{warehouse.code}</strong><span className="muted">{warehouse.name}</span>{showingValue && <small>MMK</small>}</th>)}<th className="numeric"><strong>{t(locale, 'total')}</strong>{showingValue && <small>MMK</small>}</th></tr></thead>
                                <tbody>{state.items.map((product) => <tr key={product.product_id}><td><strong>{product.product_name}</strong><span className="muted">{product.product_sku}{' \u00b7 '}{product.unit}</span></td>{state.warehouses.map((warehouse) => { const measure = Number((showingValue ? product.values : product.quantities)?.[warehouse.id] || 0); return <td className={`numeric ${measure === 0 ? 'stock-balance-zero' : ''}`} key={warehouse.id}>{number(measure)}</td>; })}<td className="numeric stock-balance-total"><strong>{number(showingValue ? product.stock_value : product.quantity)}</strong></td></tr>)}</tbody>
                                <tfoot><tr><th>{showingValue ? 'Total value' : 'Total'}</th>{state.warehouses.map((warehouse) => <td className="numeric" key={warehouse.id}><strong>{number(showingValue ? warehouse.stock_value : warehouse.total_quantity)}</strong></td>)}<td className="numeric stock-balance-total"><strong>{number(showingValue ? totalValue : state.summary.total_quantity)}</strong></td></tr></tfoot>
                            </table>
                        </div>
                        <Pagination meta={state.pageMeta} page={page} setPage={setPage} />
                    </>
                )}
            </section>
        </section>
    );
}

export function StockTransferScreen({ locale, canManage = false, transferForm = false, onNavigate }) {
    const [meta, setMeta] = useState({ loading: true, warehouses: [], products: [], error: '' });
    const emptyTransferFilters = { search: '', from_warehouse_id: '', to_warehouse_id: '', product_id: '', date: '' };
    const [filters, setFilters] = useState(emptyTransferFilters);
    const [state, setState] = useState({ loading: true, items: [], summary: {}, pageMeta: {}, error: '' });
    const [page, setPage] = useState(1);
    const [refreshKey, setRefreshKey] = useState(0);
    const [formOpen, setFormOpen] = useState(false);
    const [saving, setSaving] = useState(false);
    const [formError, setFormError] = useState('');
    const [form, setForm] = useState(blankTransferForm);
    const [wizardStep, setWizardStep] = useState(0);
    const [productSearch, setProductSearch] = useState('');

    useEffect(() => {
        let mounted = true;
        window.axios.get(`${apiBase()}/meta`)
            .then(({ data }) => mounted && setMeta({ loading: false, warehouses: data.data.warehouses, products: data.data.products, error: '' }))
            .catch((error) => mounted && setMeta({ loading: false, warehouses: [], products: [], error: requestMessage(error) }));

        return () => { mounted = false; };
    }, []);

    useEffect(() => {
        let mounted = true;
        setState((current) => ({ ...current, loading: true, error: '' }));
        const timer = window.setTimeout(() => {
            window.axios.get(`${apiBase()}/transfers`, {
                params: {
                    search: filters.search || undefined,
                    from_warehouse_id: filters.from_warehouse_id || undefined,
                    to_warehouse_id: filters.to_warehouse_id || undefined,
                    product_id: filters.product_id || undefined,
                    date: filters.date || undefined,
                    page,
                    per_page: 20,
                },
            }).then(({ data }) => {
                if (!mounted) return;
                setState({ loading: false, items: data.data.items, summary: data.data.summary, pageMeta: data.data.meta, error: '' });
            }).catch((error) => mounted && setState({ loading: false, items: [], summary: {}, pageMeta: {}, error: requestMessage(error) }));
        }, 220);

        return () => { mounted = false; window.clearTimeout(timer); };
    }, [filters, page, refreshKey]);

    useEffect(() => {
        setPage(1);
    }, [filters.search, filters.from_warehouse_id, filters.to_warehouse_id, filters.product_id, filters.date]);

    const openForm = () => {
        if (!transferForm && onNavigate) {
            onNavigate(`${window.ValleyRuntime?.routes?.office || '/office'}/stock/transfers/new`);
            return;
        }
        const firstWarehouse = meta.warehouses[0];
        const secondWarehouse = meta.warehouses.find((warehouse) => Number(warehouse.id) !== Number(firstWarehouse?.id));
        setForm({
            ...blankTransferForm,
            from_warehouse_id: firstWarehouse?.id || '',
            to_warehouse_id: secondWarehouse?.id || '',
            items: [],
            movement_date: new Date().toISOString().slice(0, 10),
        });
        setFormError('');
        setWizardStep(0);
        setProductSearch('');
        setFormOpen(true);
    };

    const closeForm = () => {
        setFormOpen(false);
        setSaving(false);
        setFormError('');
        setWizardStep(0);
        setProductSearch('');
        if (transferForm && onNavigate) onNavigate(`${window.ValleyRuntime?.routes?.office || '/office'}/stock/transfers`);
    };

    useEffect(() => {
        if (!transferForm || meta.loading || meta.error || formOpen) return;
        const firstWarehouse = meta.warehouses[0];
        const secondWarehouse = meta.warehouses.find((warehouse) => Number(warehouse.id) !== Number(firstWarehouse?.id));
        setForm({ ...blankTransferForm, from_warehouse_id: firstWarehouse?.id || '', to_warehouse_id: secondWarehouse?.id || '', items: [], movement_date: new Date().toISOString().slice(0, 10) });
        setWizardStep(0);
        setProductSearch('');
        setFormError('');
        setFormOpen(true);
    }, [transferForm, meta.loading, meta.error, meta.warehouses, formOpen]);

    useEffect(() => {
        if (transferForm || !formOpen) return;
        setFormOpen(false);
        setSaving(false);
        setFormError('');
        setWizardStep(0);
        setProductSearch('');
    }, [transferForm, formOpen]);

    const saveTransfer = (event) => {
        event?.preventDefault();
        setSaving(true);
        setFormError('');
        window.axios.post(`${apiBase()}/transfers`, form)
            .then(({ data }) => {
                const result = data.data;
                const lines = result.items || [];
                const totalQuantity = lines.reduce((sum, line) => sum + Math.abs(Number(line.out_movement?.signed_quantity || 0)), 0);
                const totalValue = lines.reduce((sum, line) => sum + Math.abs(Number(line.out_movement?.total_cost || 0)), 0);
                const transfer = { document_code: result.transfer_code, reference_code: form.reference_code || result.transfer_code, from_warehouse_code: fromWarehouse?.code, from_warehouse_name: fromWarehouse?.name, to_warehouse_code: toWarehouse?.code, to_warehouse_name: toWarehouse?.name, movement_date: form.movement_date, products_count: result.products_count, total_quantity: totalQuantity, total_value: totalValue };
                setState((current) => ({ ...current, items: [transfer, ...current.items], summary: { ...current.summary, records_count: Number(current.summary.records_count || 0) + 1, total_quantity: Number(current.summary.total_quantity || 0) + totalQuantity, stock_value: Number(current.summary.stock_value || 0) + totalValue }, pageMeta: { ...current.pageMeta, total: Number(current.pageMeta.total || 0) + 1 } }));
                closeForm();
            })
            .catch((error) => {
                setSaving(false);
                setFormError(requestMessage(error));
            });
    };

    const selectedProducts = form.items.map((item) => ({ ...meta.products.find((product) => Number(product.id) === Number(item.product_id)), ...item }));
    const fromWarehouse = meta.warehouses.find((warehouse) => Number(warehouse.id) === Number(form.from_warehouse_id));
    const toWarehouse = meta.warehouses.find((warehouse) => Number(warehouse.id) === Number(form.to_warehouse_id));
    const filteredProducts = meta.products.filter((product) => {
        const query = productSearch.trim().toLocaleLowerCase();
        return !query || [product.sku, product.name, product.label, product.unit].some((value) => String(value || '').toLocaleLowerCase().includes(query));
    });
    const transferSteps = [['basic', 'Warehouses, date and reference'], ['selectProduct', 'Choose products to transfer'], ['quantity', 'Enter transfer quantities'], ['review', 'Confirm before posting']];
    const detailsComplete = Boolean(form.from_warehouse_id && form.to_warehouse_id && form.movement_date && Number(form.from_warehouse_id) !== Number(form.to_warehouse_id));
    const productComplete = detailsComplete && form.items.length > 0;
    const quantityComplete = productComplete && form.items.every((item) => Number(item.quantity) > 0);
    const canOpenTransferStep = (index) => index === 0 || (index === 1 && detailsComplete) || (index === 2 && productComplete) || (index === 3 && quantityComplete);
    const canContinueTransfer = wizardStep === 0 ? detailsComplete : wizardStep === 1 ? productComplete : quantityComplete;
    const nextTransferStep = () => canContinueTransfer && setWizardStep((current) => Math.min(3, current + 1));
    const handleTransferSubmit = (event) => {
        event.preventDefault();
        if (wizardStep < 3) nextTransferStep();
    };
    const toggleTransferProduct = (product) => setForm((current) => ({ ...current, items: current.items.some((item) => Number(item.product_id) === Number(product.id)) ? current.items.filter((item) => Number(item.product_id) !== Number(product.id)) : [...current.items, { product_id: product.id, quantity: 1 }] }));
    const updateTransferItem = (productId, quantity) => setForm((current) => ({ ...current, items: current.items.map((item) => Number(item.product_id) === Number(productId) ? { ...item, quantity } : item) }));
    const transferTotalQuantity = form.items.reduce((sum, item) => sum + Number(item.quantity || 0), 0);
    const transferTotalValue = selectedProducts.reduce((sum, item) => sum + Number(item.quantity || 0) * Number(item.latest_cost || 0), 0);

    return (
        <section className="page stock-workspace">
            {!transferForm && <><div className="master-heading">
                <div>
                    <p className="eyebrow">Phase 5 · Warehouse Stock</p>
                    <h1>{t(locale, 'transfer')}</h1>
                    <span className="muted">{t(locale, 'transferHint')}</span>
                </div>
                <ShellPageActions>{canManage && <button className="button primary" type="button" onClick={openForm} disabled={meta.loading || meta.warehouses.length < 2 || meta.error}><Plus size={16} />{t(locale, 'newTransfer')}</button>}</ShellPageActions>
            </div>

            <div className="metrics stock-metrics">
                <Metric label={t(locale, 'transfer')} value={number(state.summary.records_count)} hint={t(locale, 'movements')} icon={Archive} />
                <Metric label={t(locale, 'outQuantity')} value={number(state.summary.out_quantity)} hint={t(locale, 'source')} icon={Package} />
                <Metric label={t(locale, 'stockValue')} value={money(Math.abs(state.summary.stock_value || 0))} hint="MMK" icon={Archive} />
            </div>

            <section className="master-panel">
                <div className="master-panel-heading">
                    <div>
                        <p className="eyebrow">{t(locale, 'transfer')}</p>
                        <h2>{t(locale, 'newTransfer')}</h2>
                    </div>
                </div>
                <div className="transfer-filter-toolbar" role="search" aria-label="Transfer filters">
                    <div className="transfer-filter-scroll">
                        <div className="transfer-filter-fields">
                            <label className="transfer-filter-field transfer-filter-search-field">
                                <span>{t(locale, 'search')}</span>
                                <span className="master-search transfer-filter-search">
                                    <Search size={14} />
                                    <input aria-label={t(locale, 'searchTransfers')} value={filters.search} placeholder={t(locale, 'searchTransfers')} onChange={(event) => setFilters((current) => ({ ...current, search: event.target.value }))} />
                                </span>
                            </label>
                            <label className="transfer-filter-field">
                                <span>{t(locale, 'fromWarehouse')}</span>
                                <select aria-label={t(locale, 'fromWarehouse')} value={filters.from_warehouse_id} onChange={(event) => setFilters((current) => ({ ...current, from_warehouse_id: event.target.value }))}>
                                    <option value="">{t(locale, 'allWarehouses')}</option>
                                    {meta.warehouses.map((warehouse) => <option value={warehouse.id} key={warehouse.id}>{warehouse.label}</option>)}
                                </select>
                            </label>
                            <label className="transfer-filter-field">
                                <span>{t(locale, 'toWarehouse')}</span>
                                <select aria-label={t(locale, 'toWarehouse')} value={filters.to_warehouse_id} onChange={(event) => setFilters((current) => ({ ...current, to_warehouse_id: event.target.value }))}>
                                    <option value="">{t(locale, 'allWarehouses')}</option>
                                    {meta.warehouses.map((warehouse) => <option value={warehouse.id} key={warehouse.id}>{warehouse.label}</option>)}
                                </select>
                            </label>
                            <label className="transfer-filter-field transfer-filter-product">
                                <span>{t(locale, 'product')}</span>
                                <select aria-label={t(locale, 'product')} value={filters.product_id} onChange={(event) => setFilters((current) => ({ ...current, product_id: event.target.value }))}>
                                    <option value="">{t(locale, 'allProducts')}</option>
                                    {meta.products.map((product) => <option value={product.id} key={product.id}>{product.label}</option>)}
                                </select>
                            </label>
                            <label className="transfer-filter-field transfer-filter-date">
                                <span>{t(locale, 'date')}</span>
                                <input aria-label={t(locale, 'date')} type="date" value={filters.date} onChange={(event) => setFilters((current) => ({ ...current, date: event.target.value }))} />
                            </label>
                        </div>
                    </div>
                    <div className="transfer-filter-actions">
                        <button className="button" type="button" disabled={!Object.values(filters).some(Boolean)} onClick={() => setFilters(emptyTransferFilters)}><X size={15} />{t(locale, 'clearFilters')}</button>
                        <button className="button" type="button" onClick={() => setRefreshKey((value) => value + 1)}><RefreshCw size={15} />{t(locale, 'refresh')}</button>
                    </div>
                </div>

                {state.loading ? (
                    <WorkspaceState icon={RefreshCw} title={t(locale, 'loadingTransfers')} loading />
                ) : state.error ? (
                    <WorkspaceState icon={RefreshCw} title={state.error} action={() => setRefreshKey((value) => value + 1)} actionLabel={t(locale, 'retry')} />
                ) : state.items.length === 0 ? (
                    <WorkspaceState icon={Package} title={t(locale, 'emptyTransfers')} />
                ) : (
                    <>
                        <div className="master-table-wrap">
                            <table className="master-table stock-table">
                                <thead>
                                    <tr>
                                        <th>{t(locale, 'reference')}</th>
                                        <th>{t(locale, 'fromWarehouse')}</th>
                                        <th>{t(locale, 'toWarehouse')}</th>
                                        <th>{t(locale, 'date')}</th>
                                        <th>{t(locale, 'products')}</th>
                                        <th>{t(locale, 'quantity')}</th>
                                        <th>{t(locale, 'stockValue')}</th>
                                        <th className="table-actions-header">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {state.items.map((transfer) => (
                                        <tr key={transfer.document_code}>
                                            <td><strong>{transfer.document_code}</strong><span className="muted">{transfer.reference_code === transfer.document_code ? '-' : transfer.reference_code || '-'}</span></td>
                                            <td><strong>{transfer.from_warehouse_code}</strong><span className="muted">{transfer.from_warehouse_name}</span></td>
                                            <td><strong>{transfer.to_warehouse_code}</strong><span className="muted">{transfer.to_warehouse_name}</span></td>
                                            <td>{formatDate(transfer.movement_date)}</td>
                                            <td><strong>{number(transfer.products_count)}</strong><span className="muted">{t(locale, 'products')}</span></td>
                                            <td className="numeric">{number(transfer.total_quantity)}</td>
                                            <td className="numeric"><strong>{money(transfer.total_value)}</strong></td>
                                            <td className="table-actions-cell"><div className="row-actions"><button className="icon-button" type="button" title="Print stock transfer" aria-label={`Print ${transfer.document_code}`} onClick={() => printStockDocument('stock_transfer', transfer.document_code)}><Printer size={14} /></button></div></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <Pagination meta={state.pageMeta} page={page} setPage={setPage} />
                    </>
                )}
            </section></>}

            {formOpen && transferForm && (
                <div className="stock-receive-form-page">
                    <ShellBackButton onClick={() => wizardStep > 0 ? setWizardStep((current) => current - 1) : closeForm()} label={wizardStep > 0 ? "Previous step" : "Back to transfers"} />
                    <form className="master-dialog stock-dialog stock-wizard-dialog stock-wizard-page" onSubmit={handleTransferSubmit}>
                        <header className="stock-wizard-page-heading"><div><h1>{t(locale, 'newTransfer')}</h1><p>Move stock safely between warehouse locations.</p></div></header>
                        <div className="stock-wizard-stepper-wrap">
                            <ol className="trip-wizard-steps stock-receive-steps" aria-label={t(locale, 'newTransfer')}>
                                {transferSteps.map(([labelKey, hint], index) => <li key={`${labelKey}-${index}`} className={wizardStep === index ? 'is-current' : index < wizardStep ? 'is-complete' : ''}><button type="button" disabled={!canOpenTransferStep(index)} onClick={() => canOpenTransferStep(index) && setWizardStep(index)} aria-current={wizardStep === index ? 'step' : undefined}><span>{index < wizardStep ? <Check size={13} /> : index + 1}</span><strong>{t(locale, labelKey)}</strong><small>{hint}</small></button></li>)}
                            </ol>
                        </div>
                        <div className="master-form-body stock-wizard-body">
                            {formError && <p className="form-alert">{formError}</p>}
                            {wizardStep === 0 && <section className="stock-wizard-stage"><div className="stock-wizard-stage-heading"><p className="eyebrow">1 / 4</p><h3>Transfer details</h3><span>Choose the source, destination, date, and document details.</span></div><div className="master-form-grid stock-wizard-basic">
                                <label><span className="stock-field-label">{t(locale, 'fromWarehouse')}</span><select required value={form.from_warehouse_id} onChange={(event) => setForm((current) => ({ ...current, from_warehouse_id: event.target.value }))}>{meta.warehouses.map((warehouse) => <option value={warehouse.id} key={warehouse.id}>{warehouse.label}</option>)}</select></label>
                                <label><span className="stock-field-label">{t(locale, 'toWarehouse')}</span><select required value={form.to_warehouse_id} onChange={(event) => setForm((current) => ({ ...current, to_warehouse_id: event.target.value }))}>{meta.warehouses.map((warehouse) => <option value={warehouse.id} key={warehouse.id}>{warehouse.label}</option>)}</select></label>
                                <label><span className="stock-field-label">{t(locale, 'date')}</span><input required type="date" value={form.movement_date} onChange={(event) => setForm((current) => ({ ...current, movement_date: event.target.value }))} /></label>
                                <label><span className="stock-field-label">{t(locale, 'reference')} <small>{t(locale, 'optional')}</small></span><input value={form.reference_code} onChange={(event) => setForm((current) => ({ ...current, reference_code: event.target.value }))} /></label>
                                <label className="span-2"><span className="stock-field-label">{t(locale, 'notes')} <small>{t(locale, 'optional')}</small></span><textarea rows="3" value={form.notes} onChange={(event) => setForm((current) => ({ ...current, notes: event.target.value }))} /></label>
                            </div>{form.from_warehouse_id && form.to_warehouse_id && Number(form.from_warehouse_id) === Number(form.to_warehouse_id) && <p className="form-alert">Source and destination warehouses must be different.</p>}</section>}
                            {wizardStep === 1 && <section className="stock-wizard-stage"><div className="stock-wizard-stage-heading"><p className="eyebrow">2 / 4</p><h3>{t(locale, 'productSelection')}</h3><span>Search and choose every product included in this transfer.</span></div><label className="master-search stock-product-search"><Search size={14} /><input value={productSearch} onChange={(event) => setProductSearch(event.target.value)} placeholder={t(locale, 'searchProducts')} autoFocus /></label><div className="stock-product-picker">{filteredProducts.map((product) => <button type="button" key={product.id} className={form.items.some((item) => Number(item.product_id) === Number(product.id)) ? 'is-selected' : ''} onClick={() => toggleTransferProduct(product)}><span className="stock-product-icon"><Package size={16} /></span><span><strong>{product.name}</strong><small>{product.sku} · {product.unit}</small></span><span className="stock-product-cost"><small>{t(locale, 'cost')}</small><strong>{money(product.latest_cost || 0)}</strong></span><span className="stock-product-check">{form.items.some((item) => Number(item.product_id) === Number(product.id)) && <Check size={14} />}</span></button>)}</div></section>}
                            {wizardStep === 2 && <section className="stock-wizard-stage"><div className="stock-wizard-stage-heading"><p className="eyebrow">3 / 4</p><h3>Transfer quantities</h3><span>Enter the quantity to move for every selected product.</span></div><div className="stock-line-summary"><span>{form.items.length} {t(locale, 'products')}</span><span>{t(locale, 'totalQuantity')}: <strong>{number(transferTotalQuantity)}</strong></span><span>{t(locale, 'estimatedValue')}: <strong>{money(transferTotalValue)}</strong></span><button className="button" type="button" onClick={() => setWizardStep(1)}>{t(locale, 'selectProduct')}</button></div><div className="master-table-wrap stock-receive-line-wrap"><table className="master-table price-matrix-table stock-receive-entry-table"><thead><tr><th>{t(locale, 'product')}</th><th>{t(locale, 'quantity')}</th><th>{t(locale, 'estimatedValue')}<small>MMK</small></th><th>{t(locale, 'actions')}</th></tr></thead><tbody>{selectedProducts.map((product) => <tr key={product.product_id}><td><strong>{product.name}</strong><span className="muted">{product.sku} · {product.unit}</span></td><td className="price-matrix-cell"><input aria-label={`${product.name} ${t(locale, 'quantity')}`} required min="1" step="1" type="number" inputMode="numeric" value={product.quantity} onFocus={(event) => event.currentTarget.select()} onChange={(event) => updateTransferItem(product.product_id, event.target.value)} /></td><td className="numeric stock-calculated-cell">{money(Number(product.quantity || 0) * Number(product.latest_cost || 0))}</td><td className="stock-entry-actions"><button className="icon-button danger" type="button" aria-label={`Remove ${product.name}`} onClick={() => toggleTransferProduct(product)}><X size={14} /></button></td></tr>)}</tbody></table></div></section>}
                            {wizardStep === 3 && <section className="stock-wizard-stage"><div className="stock-wizard-stage-heading"><p className="eyebrow">4 / 4</p><h3>Transfer review</h3><span>Confirm the stock movement before recording it.</span></div><dl className="stock-receive-review"><div><dt>{t(locale, 'fromWarehouse')}</dt><dd>{fromWarehouse?.label || '-'}</dd></div><div><dt>{t(locale, 'toWarehouse')}</dt><dd>{toWarehouse?.label || '-'}</dd></div><div><dt>{t(locale, 'products')}</dt><dd>{number(form.items.length)}</dd></div><div><dt>{t(locale, 'totalQuantity')}</dt><dd>{number(transferTotalQuantity)}</dd></div><div><dt>{t(locale, 'date')}</dt><dd>{formatDate(form.movement_date)}</dd></div><div><dt>{t(locale, 'reference')}</dt><dd>{form.reference_code || '-'}</dd></div>{form.notes && <div className="stock-review-notes"><dt>{t(locale, 'notes')}</dt><dd>{form.notes}</dd></div>}</dl><div className="master-table-wrap stock-receive-review-lines"><table className="master-table"><thead><tr><th>{t(locale, 'product')}</th><th>{t(locale, 'quantity')}</th><th>{t(locale, 'stockValue')}</th></tr></thead><tbody>{selectedProducts.map((product) => <tr key={product.product_id}><td><strong>{product.name}</strong><span className="muted">{product.sku} · {product.unit}</span></td><td className="numeric">{number(product.quantity)}</td><td className="numeric">{money(Number(product.quantity || 0) * Number(product.latest_cost || 0))}</td></tr>)}</tbody></table></div></section>}
                        </div>
                        <footer className="stock-wizard-footer"><span className="muted">Step {wizardStep + 1} of 4</span><div><button className="button" type="button" onClick={closeForm}>{t(locale, 'cancel')}</button>{wizardStep < 3 ? <button className="button primary" type="button" disabled={!canContinueTransfer} onClick={nextTransferStep}>{t(locale, 'next')}</button> : <button className="button primary" type="button" disabled={saving || !quantityComplete} onClick={saveTransfer}><Save size={15} />{saving ? 'Saving…' : t(locale, 'saveTransfer')}</button>}</div></footer>
                    </form>
                </div>
            )}
        </section>
    );
}

export function StockCardScreen({ locale }) {
    const [meta, setMeta] = useState({ loading: true, warehouses: [], products: [], error: '' });
    const [filters, setFilters] = useState({ warehouse_id: '', product_id: '', date_from: '', date_to: '' });
    const [state, setState] = useState({ loading: false, items: [], summary: {}, product: null, warehouse: null, error: '' });
    const [refreshKey, setRefreshKey] = useState(0);

    useEffect(() => {
        let mounted = true;
        window.axios.get(`${apiBase()}/meta`)
            .then(({ data }) => {
                if (!mounted) return;
                setMeta({ loading: false, warehouses: data.data.warehouses, products: data.data.products, error: '' });
                setFilters((current) => ({ ...current, product_id: current.product_id || data.data.products[0]?.id || '' }));
            })
            .catch((error) => mounted && setMeta({ loading: false, warehouses: [], products: [], error: requestMessage(error) }));

        return () => { mounted = false; };
    }, []);

    useEffect(() => {
        if (!filters.product_id) {
            setState({ loading: false, items: [], summary: {}, product: null, warehouse: null, error: '' });
            return undefined;
        }

        let mounted = true;
        setState((current) => ({ ...current, loading: true, error: '' }));
        const timer = window.setTimeout(() => {
            window.axios.get(`${apiBase()}/card`, {
                params: {
                    warehouse_id: filters.warehouse_id || undefined,
                    product_id: filters.product_id,
                    date_from: filters.date_from || undefined,
                    date_to: filters.date_to || undefined,
                },
            }).then(({ data }) => {
                if (!mounted) return;
                setState({ loading: false, items: data.data.items, summary: data.data.summary, product: data.data.product, warehouse: data.data.warehouse, error: '' });
            }).catch((error) => mounted && setState({ loading: false, items: [], summary: {}, product: null, warehouse: null, error: requestMessage(error) }));
        }, 220);

        return () => { mounted = false; window.clearTimeout(timer); };
    }, [filters, refreshKey]);

    return (
        <section className="page stock-workspace">
            <div className="master-heading">
                <div>
                    <p className="eyebrow">Phase 5 · Warehouse Stock</p>
                    <h1>{t(locale, 'stockCard')}</h1>
                    <span className="muted">{t(locale, 'cardHint')}</span>
                </div>
                <ShellPageActions><button className="button" type="button" onClick={() => setRefreshKey((value) => value + 1)}><RefreshCw size={15} />{t(locale, 'refresh')}</button></ShellPageActions>
            </div>

            <div className="metrics stock-metrics">
                <Metric label={t(locale, 'openingBalance')} value={number(state.summary.opening_balance)} hint={state.product?.unit || t(locale, 'unit')} icon={Archive} />
                <Metric label={t(locale, 'inQuantity')} value={number(state.summary.in_quantity)} hint={state.product?.unit || t(locale, 'unit')} icon={Package} />
                <Metric label={t(locale, 'outQuantity')} value={number(state.summary.out_quantity)} hint={state.product?.unit || t(locale, 'unit')} icon={Package} />
                <Metric label={t(locale, 'totalQuantity')} value={number(state.summary.closing_balance)} hint={state.product?.unit || t(locale, 'unit')} icon={Archive} />
            </div>

            <section className="master-panel">
                <div className="master-panel-heading">
                    <div>
                        <p className="eyebrow">{state.product?.sku || t(locale, 'stockCard')}</p>
                        <h2>{state.product?.name || t(locale, 'searchCard')}</h2>
                    </div>
                </div>
                <div className="master-toolbar stock-toolbar">
                    <select value={filters.product_id} onChange={(event) => setFilters((current) => ({ ...current, product_id: event.target.value }))}>
                        {meta.products.map((product) => <option value={product.id} key={product.id}>{product.label}</option>)}
                    </select>
                    <select value={filters.warehouse_id} onChange={(event) => setFilters((current) => ({ ...current, warehouse_id: event.target.value }))}>
                        <option value="">{t(locale, 'allWarehouses')}</option>
                        {meta.warehouses.map((warehouse) => <option value={warehouse.id} key={warehouse.id}>{warehouse.label}</option>)}
                    </select>
                    <label className="date-filter"><span>{t(locale, 'dateFrom')}</span><input type="date" value={filters.date_from} onChange={(event) => setFilters((current) => ({ ...current, date_from: event.target.value }))} /></label>
                    <label className="date-filter"><span>{t(locale, 'dateTo')}</span><input type="date" value={filters.date_to} onChange={(event) => setFilters((current) => ({ ...current, date_to: event.target.value }))} /></label>
                    <button className="button" type="button" onClick={() => setRefreshKey((value) => value + 1)}><RefreshCw size={15} />{t(locale, 'refresh')}</button>
                </div>

                {state.loading ? (
                    <WorkspaceState icon={RefreshCw} title={t(locale, 'loadingCard')} loading />
                ) : state.error ? (
                    <WorkspaceState icon={RefreshCw} title={state.error} action={() => setRefreshKey((value) => value + 1)} actionLabel={t(locale, 'retry')} />
                ) : state.items.length === 0 ? (
                    <WorkspaceState icon={Package} title={t(locale, 'emptyCard')} />
                ) : (
                    <div className="master-table-wrap">
                        <table className="master-table stock-table stock-card-table">
                            <thead>
                                <tr>
                                    <th>{t(locale, 'date')}</th>
                                    <th>{t(locale, 'reference')}</th>
                                    <th>{t(locale, 'warehouse')}</th>
                                    <th>{t(locale, 'movementType')}</th>
                                    <th>{t(locale, 'inQuantity')}</th>
                                    <th>{t(locale, 'outQuantity')}</th>
                                    <th>{t(locale, 'totalQuantity')}</th>
                                    <th>{t(locale, 'cost')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {state.items.map((movement) => (
                                    <tr key={movement.id}>
                                        <td>{formatDate(movement.movement_date)}</td>
                                        <td><strong>{movement.reference_code || movement.code}</strong><span className="muted">{movement.code}</span></td>
                                        <td><strong>{movement.warehouse_code}</strong><span className="muted">{movement.warehouse_name}</span></td>
                                        <td><StatusBadge status={movement.movement_type} locale={locale} /></td>
                                        <td className="numeric">{movement.signed_quantity > 0 ? number(movement.signed_quantity) : '-'}</td>
                                        <td className="numeric">{movement.signed_quantity < 0 ? number(Math.abs(movement.signed_quantity)) : '-'}</td>
                                        <td className="numeric">{number(movement.running_balance)}</td>
                                        <td className="numeric">{money(movement.unit_cost)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>
        </section>
    );
}

function Metric({ label, value, hint, icon: Icon }) {
    return <article className="metric"><span>{label}</span><strong>{value}</strong><small>{hint}</small><Icon size={18} /></article>;
}

function WorkspaceState({ icon: Icon, title, action, actionLabel, loading = false }) {
    return <div className="workspace-state"><Icon className={loading ? 'spin' : ''} size={22} /><strong>{title}</strong>{action && <button className="button" type="button" onClick={action}>{actionLabel}</button>}</div>;
}

function StatusBadge({ status, locale }) {
    const family = ['opening', 'receive', 'transfer_in', 'delivery_return', 'delivery_issue_reversal'].includes(status) ? 'success' : ['issue', 'transfer_out', 'delivery_issue'].includes(status) ? 'info' : ['damage', 'delivery_damage'].includes(status) ? 'danger' : 'neutral';
    return <span className={`status ${family}`}>{t(locale, status)}</span>;
}

function Pagination({ meta = {}, page, setPage }) {
    if (!meta.last_page || meta.last_page <= 1) return null;
    return <div className="master-pagination"><span>Page {meta.current_page} of {meta.last_page} · {meta.total}</span><div><button type="button" disabled={page <= 1} onClick={() => setPage((value) => value - 1)}>Previous</button><button type="button" disabled={page >= meta.last_page} onClick={() => setPage((value) => value + 1)}>Next</button></div></div>;
}

function money(value) {
    return `${Number(value || 0).toLocaleString()} MMK`;
}

function number(value) {
    return Number(value || 0).toLocaleString();
}

function formatDate(value) {
    if (!value) return '-';
    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) return String(value);
    return date.toLocaleDateString([], { year: 'numeric', month: 'short', day: '2-digit' });
}

function formatDateTime(value) {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return date.toLocaleString([], { year: 'numeric', month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit' });
}
