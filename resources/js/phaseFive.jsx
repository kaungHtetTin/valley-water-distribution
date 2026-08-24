import { Archive, CalendarDays, Package, Plus, RefreshCw, Save, Search, X } from 'lucide-react';
import { useEffect, useState } from 'react';

const copy = {
    en: {
        actions: 'Actions',
        allProducts: 'All products',
        allWarehouses: 'All warehouses',
        balanceHint: 'Live stock by warehouse and product, maintained from the movement ledger.',
        balances: 'Stock balances',
        cancel: 'Cancel',
        cardHint: 'Product movement history with opening, in, out, and running balance.',
        cost: 'Cost',
        damage: 'Damage',
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
        issueHint: 'Issue stock out of a warehouse for loading, internal use, or operational consumption.',
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
        receiveHint: 'Record opening stock and warehouse receives before issue, transfer, and delivery workflows consume stock.',
        reference: 'Reference',
        refresh: 'Refresh',
        retry: 'Retry',
        save: 'Record stock',
        saveTransfer: 'Record transfer',
        saveCount: 'Record count',
        searchBalances: 'Search warehouse, SKU, product',
        searchCard: 'Filter by product or warehouse first',
        searchDamage: 'Search damage, reference, warehouse, product',
        searchIssues: 'Search issue, reference, warehouse, product',
        searchMovements: 'Search movement, reference, warehouse, product',
        searchTransfers: 'Search transfer, warehouse, product',
        searchValues: 'Search warehouse, SKU, product',
        source: 'Source',
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
    },
    my: {},
};

const blankForm = { movement_type: 'receive', warehouse_id: '', product_id: '', movement_date: new Date().toISOString().slice(0, 10), quantity: 1, unit_cost: '', reference_code: '', notes: '' };
const blankTransferForm = { from_warehouse_id: '', to_warehouse_id: '', product_id: '', movement_date: new Date().toISOString().slice(0, 10), quantity: 1, reference_code: '', notes: '' };
const blankClosingForm = { warehouse_id: '', product_id: '', movement_date: new Date().toISOString().slice(0, 10), counted_quantity: '', unit_cost: '', reference_code: '', notes: '' };
const movementTypes = ['opening', 'receive', 'issue', 'damage'];
const movementFilterTypes = ['opening', 'receive', 'issue', 'damage', 'transfer_out', 'transfer_in', 'adjustment'];
const movementModeConfig = {
    receive: { title: 'movements', hint: 'receiveHint', action: 'newReceive', panel: 'newReceive', loading: 'loadingMovements', empty: 'emptyMovements', search: 'searchMovements', formTypes: ['opening', 'receive'], defaultType: 'receive', filterTypes: movementFilterTypes, fixedType: '' },
    issue: { title: 'issues', hint: 'issueHint', action: 'newIssue', panel: 'newIssue', loading: 'loadingIssues', empty: 'emptyIssues', search: 'searchIssues', formTypes: ['issue'], defaultType: 'issue', filterTypes: ['issue'], fixedType: 'issue' },
    damage: { title: 'damageStock', hint: 'damageStockHint', action: 'newDamageStock', panel: 'newDamageStock', loading: 'loadingDamage', empty: 'emptyDamage', search: 'searchDamage', formTypes: ['damage'], defaultType: 'damage', filterTypes: ['damage'], fixedType: 'damage' },
};

function t(locale, key) {
    return copy[locale]?.[key] || copy.en[key] || key;
}

function apiBase() {
    return window.ValleyRuntime?.api?.stock || '/api/stock';
}

function requestMessage(error) {
    return error.response?.data?.message || 'The stock request could not be completed.';
}

export function StockReceiveScreen({ locale, canManage = false, mode = 'receive' }) {
    const movementConfig = movementModeConfig[mode] || movementModeConfig.receive;
    const [meta, setMeta] = useState({ loading: true, warehouses: [], products: [], error: '' });
    const [filters, setFilters] = useState({ search: '', warehouse_id: '', product_id: '', type: movementConfig.fixedType || '', date: '' });
    const [state, setState] = useState({ loading: true, items: [], summary: {}, pageMeta: {}, error: '' });
    const [page, setPage] = useState(1);
    const [refreshKey, setRefreshKey] = useState(0);
    const [formOpen, setFormOpen] = useState(false);
    const [saving, setSaving] = useState(false);
    const [formError, setFormError] = useState('');
    const [form, setForm] = useState(blankForm);

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
            window.axios.get(`${apiBase()}/movements`, {
                params: {
                    search: filters.search || undefined,
                    warehouse_id: filters.warehouse_id || undefined,
                    product_id: filters.product_id || undefined,
                    type: movementConfig.fixedType || filters.type || undefined,
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
    }, [filters, page, refreshKey, movementConfig.fixedType]);

    useEffect(() => {
        setPage(1);
    }, [filters.search, filters.warehouse_id, filters.product_id, filters.type, filters.date]);

    const openForm = () => {
        const firstWarehouse = meta.warehouses[0];
        const firstProduct = meta.products[0];
        setForm({
            ...blankForm,
            movement_type: movementConfig.defaultType,
            warehouse_id: firstWarehouse?.id || '',
            product_id: firstProduct?.id || '',
            unit_cost: firstProduct?.latest_cost || '',
            movement_date: new Date().toISOString().slice(0, 10),
        });
        setFormError('');
        setFormOpen(true);
    };

    const closeForm = () => {
        setFormOpen(false);
        setSaving(false);
        setFormError('');
    };

    const saveMovement = (event) => {
        event.preventDefault();
        setSaving(true);
        setFormError('');
        window.axios.post(`${apiBase()}/movements`, form)
            .then(() => {
                closeForm();
                setRefreshKey((value) => value + 1);
            })
            .catch((error) => {
                setSaving(false);
                setFormError(requestMessage(error));
            });
    };

    return (
        <section className="page stock-workspace">
            <div className="master-heading">
                <div>
                    <p className="eyebrow">Phase 5 · Warehouse Stock</p>
                    <h1>{t(locale, movementConfig.title)}</h1>
                    <span className="muted">{t(locale, movementConfig.hint)}</span>
                </div>
                {canManage && <button className="button primary" type="button" onClick={openForm} disabled={meta.loading || meta.error}><Plus size={16} />{t(locale, movementConfig.action)}</button>}
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
                            <table className="master-table stock-table">
                                <thead>
                                    <tr>
                                        <th>{t(locale, 'reference')}</th>
                                        <th>{t(locale, 'warehouse')}</th>
                                        <th>{t(locale, 'product')}</th>
                                        <th>{t(locale, 'movementType')}</th>
                                        <th>{t(locale, 'date')}</th>
                                        <th>{t(locale, 'quantity')}</th>
                                        <th>{t(locale, 'cost')}</th>
                                        <th>{t(locale, 'stockValue')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {state.items.map((movement) => (
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
            </section>

            {formOpen && (
                <div className="modal-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && closeForm()}>
                    <form className="master-dialog stock-dialog" onSubmit={saveMovement}>
                        <header><h2>{t(locale, movementConfig.action)}</h2><button className="icon-button" type="button" aria-label={t(locale, 'cancel')} onClick={closeForm}><X size={16} /></button></header>
                        <div className="master-form-body">
                            {formError && <p className="form-alert">{formError}</p>}
                            <div className="master-form-grid">
                                <label>{t(locale, 'movementType')}<select value={form.movement_type} onChange={(event) => setForm((current) => ({ ...current, movement_type: event.target.value }))}>{movementConfig.formTypes.map((type) => <option value={type} key={type}>{t(locale, type)}</option>)}</select></label>
                                <label>{t(locale, 'date')}<input type="date" value={form.movement_date} onChange={(event) => setForm((current) => ({ ...current, movement_date: event.target.value }))} /></label>
                                <label>{t(locale, 'warehouse')}<select required value={form.warehouse_id} onChange={(event) => setForm((current) => ({ ...current, warehouse_id: event.target.value }))}>{meta.warehouses.map((warehouse) => <option value={warehouse.id} key={warehouse.id}>{warehouse.label}</option>)}</select></label>
                                <label>{t(locale, 'product')}<select required value={form.product_id} onChange={(event) => setForm((current) => ({ ...current, product_id: event.target.value, unit_cost: meta.products.find((product) => Number(product.id) === Number(event.target.value))?.latest_cost || current.unit_cost }))}>{meta.products.map((product) => <option value={product.id} key={product.id}>{product.label}</option>)}</select></label>
                                <label>{t(locale, 'quantity')}<input required min="0.01" step="0.01" type="number" value={form.quantity} onChange={(event) => setForm((current) => ({ ...current, quantity: event.target.value }))} /></label>
                                <label>{t(locale, 'cost')}<input min="0" step="0.01" type="number" value={form.unit_cost} onChange={(event) => setForm((current) => ({ ...current, unit_cost: event.target.value }))} /></label>
                                <label>{t(locale, 'reference')}<input value={form.reference_code} onChange={(event) => setForm((current) => ({ ...current, reference_code: event.target.value }))} /></label>
                                <label className="span-2">{t(locale, 'notes')}<textarea value={form.notes} onChange={(event) => setForm((current) => ({ ...current, notes: event.target.value }))} /></label>
                            </div>
                        </div>
                        <footer><button className="button" type="button" onClick={closeForm}>{t(locale, 'cancel')}</button><button className="button primary" type="submit" disabled={saving}><Save size={15} />{t(locale, 'save')}</button></footer>
                    </form>
                </div>
            )}
        </section>
    );
}

export function StockIssueScreen({ locale, canManage = false }) {
    return <StockReceiveScreen locale={locale} canManage={canManage} mode="issue" />;
}

export function StockDamageScreen({ locale, canManage = false }) {
    return <StockReceiveScreen locale={locale} canManage={canManage} mode="damage" />;
}

export function ClosingStockScreen({ locale, canManage = false }) {
    const [meta, setMeta] = useState({ loading: true, warehouses: [], products: [], error: '' });
    const [filters, setFilters] = useState({ search: '', warehouse_id: '', product_id: '', date: '' });
    const [state, setState] = useState({ loading: true, items: [], summary: {}, pageMeta: {}, error: '' });
    const [page, setPage] = useState(1);
    const [refreshKey, setRefreshKey] = useState(0);
    const [formOpen, setFormOpen] = useState(false);
    const [saving, setSaving] = useState(false);
    const [formError, setFormError] = useState('');
    const [form, setForm] = useState(blankClosingForm);
    const [preview, setPreview] = useState({ loading: false, quantity: 0, averageCost: 0, error: '' });

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
            window.axios.get(`${apiBase()}/movements`, {
                params: {
                    search: filters.search || undefined,
                    warehouse_id: filters.warehouse_id || undefined,
                    product_id: filters.product_id || undefined,
                    type: 'adjustment',
                    reference_type: 'closing_count',
                    date: filters.date || undefined,
                    page,
                    per_page: 20,
                },
            }).then(({ data }) => mounted && setState({ loading: false, items: data.data.items, summary: data.data.summary, pageMeta: data.data.meta, error: '' }))
                .catch((error) => mounted && setState({ loading: false, items: [], summary: {}, pageMeta: {}, error: requestMessage(error) }));
        }, 220);
        return () => { mounted = false; window.clearTimeout(timer); };
    }, [filters, page, refreshKey]);

    useEffect(() => {
        setPage(1);
    }, [filters.search, filters.warehouse_id, filters.product_id, filters.date]);

    useEffect(() => {
        if (!formOpen || !form.warehouse_id || !form.product_id) return undefined;
        let mounted = true;
        setPreview((current) => ({ ...current, loading: true, error: '' }));
        window.axios.get(`${apiBase()}/balances`, { params: { warehouse_id: form.warehouse_id, product_id: form.product_id, per_page: 1 } })
            .then(({ data }) => {
                if (!mounted) return;
                const balance = data.data.items[0];
                const selectedProduct = meta.products.find((product) => Number(product.id) === Number(form.product_id));
                const quantity = Number(balance?.quantity || 0);
                const averageCost = Number(balance?.average_cost || selectedProduct?.latest_cost || 0);
                setPreview({ loading: false, quantity, averageCost, error: '' });
                setForm((current) => ({ ...current, counted_quantity: quantity, unit_cost: averageCost || '' }));
            })
            .catch((error) => mounted && setPreview({ loading: false, quantity: 0, averageCost: 0, error: requestMessage(error) }));
        return () => { mounted = false; };
    }, [formOpen, form.warehouse_id, form.product_id, meta.products]);

    const openForm = () => {
        setForm({ ...blankClosingForm, warehouse_id: meta.warehouses[0]?.id || '', product_id: meta.products[0]?.id || '', movement_date: new Date().toISOString().slice(0, 10) });
        setPreview({ loading: true, quantity: 0, averageCost: 0, error: '' });
        setFormError('');
        setFormOpen(true);
    };

    const closeForm = () => {
        setFormOpen(false);
        setSaving(false);
        setFormError('');
    };

    const saveCount = (event) => {
        event.preventDefault();
        setSaving(true);
        setFormError('');
        window.axios.post(`${apiBase()}/closing-counts`, form)
            .then(() => { closeForm(); setRefreshKey((value) => value + 1); })
            .catch((error) => { setSaving(false); setFormError(requestMessage(error)); });
    };

    const variance = Number(form.counted_quantity || 0) - Number(preview.quantity || 0);

    return (
        <section className="page stock-workspace">
            <div className="master-heading">
                <div><p className="eyebrow">Phase 5 · Warehouse Stock</p><h1>{t(locale, 'closingStock')}</h1><span className="muted">{t(locale, 'closingStockHint')}</span></div>
                {canManage && <button className="button primary" type="button" onClick={openForm} disabled={meta.loading || Boolean(meta.error)}><Plus size={16} />{t(locale, 'newClosingCount')}</button>}
            </div>

            <div className="metrics stock-metrics">
                <Metric label={t(locale, 'closingStock')} value={number(state.summary.records_count)} hint={t(locale, 'balanceLines')} icon={Archive} />
                <Metric label={t(locale, 'increaseQuantity')} value={number(state.summary.in_quantity)} hint={t(locale, 'quantity')} icon={Package} />
                <Metric label={t(locale, 'decreaseQuantity')} value={number(state.summary.out_quantity)} hint={t(locale, 'quantity')} icon={Package} />
                <Metric label={t(locale, 'variance')} value={money(state.summary.stock_value)} hint="MMK" icon={Archive} />
            </div>

            <section className="master-panel">
                <div className="master-panel-heading"><div><p className="eyebrow">{t(locale, 'movements')}</p><h2>{t(locale, 'closingStock')}</h2></div></div>
                <div className="master-toolbar stock-toolbar">
                    <label className="master-search"><Search size={14} /><input value={filters.search} placeholder={t(locale, 'searchClosing')} onChange={(event) => setFilters((current) => ({ ...current, search: event.target.value }))} /></label>
                    <select value={filters.warehouse_id} onChange={(event) => setFilters((current) => ({ ...current, warehouse_id: event.target.value }))}><option value="">{t(locale, 'allWarehouses')}</option>{meta.warehouses.map((warehouse) => <option value={warehouse.id} key={warehouse.id}>{warehouse.label}</option>)}</select>
                    <select value={filters.product_id} onChange={(event) => setFilters((current) => ({ ...current, product_id: event.target.value }))}><option value="">{t(locale, 'allProducts')}</option>{meta.products.map((product) => <option value={product.id} key={product.id}>{product.label}</option>)}</select>
                    <label className="date-filter"><CalendarDays size={14} /><input type="date" value={filters.date} onChange={(event) => setFilters((current) => ({ ...current, date: event.target.value }))} /></label>
                    <button className="button" type="button" onClick={() => setRefreshKey((value) => value + 1)}><RefreshCw size={15} />{t(locale, 'refresh')}</button>
                </div>
                {state.loading ? <WorkspaceState icon={RefreshCw} title={t(locale, 'loadingClosing')} loading /> : state.error || meta.error ? <WorkspaceState icon={RefreshCw} title={state.error || meta.error} action={() => setRefreshKey((value) => value + 1)} actionLabel={t(locale, 'retry')} /> : state.items.length === 0 ? <WorkspaceState icon={Package} title={t(locale, 'emptyClosing')} /> : (
                    <><div className="master-table-wrap"><table className="master-table stock-table closing-stock-table"><thead><tr><th>{t(locale, 'reference')}</th><th>{t(locale, 'warehouse')}</th><th>{t(locale, 'product')}</th><th>{t(locale, 'date')}</th><th>{t(locale, 'systemQuantity')}</th><th>{t(locale, 'countedQuantity')}</th><th>{t(locale, 'variance')}</th><th>{t(locale, 'stockValue')}</th></tr></thead><tbody>{state.items.map((movement) => <tr key={movement.id}><td><strong>{movement.reference_code || movement.code}</strong><span className="muted">{movement.code}</span></td><td><strong>{movement.warehouse_code}</strong><span className="muted">{movement.warehouse_name}</span></td><td><strong>{movement.product_name}</strong><span className="muted">{movement.product_sku} · {movement.unit}</span></td><td>{formatDate(movement.movement_date)}</td><td className="numeric">{number(movement.balance_before)}</td><td className="numeric">{number(movement.balance_after)}</td><td className={`numeric ${Number(movement.signed_quantity) < 0 ? 'text-danger' : 'text-success'}`}>{Number(movement.signed_quantity) > 0 ? '+' : ''}{number(movement.signed_quantity)}</td><td className="numeric">{money(movement.total_cost)}</td></tr>)}</tbody></table></div><Pagination meta={state.pageMeta} page={page} setPage={setPage} /></>
                )}
            </section>

            {formOpen && <div className="modal-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && closeForm()}><form className="master-dialog stock-dialog" onSubmit={saveCount}><header><h2>{t(locale, 'newClosingCount')}</h2><button className="icon-button" type="button" aria-label={t(locale, 'cancel')} onClick={closeForm}><X size={16} /></button></header><div className="master-form-body">{formError && <p className="form-alert">{formError}</p>}<div className="master-form-grid"><label>{t(locale, 'warehouse')}<select required value={form.warehouse_id} onChange={(event) => setForm((current) => ({ ...current, warehouse_id: event.target.value }))}>{meta.warehouses.map((warehouse) => <option value={warehouse.id} key={warehouse.id}>{warehouse.label}</option>)}</select></label><label>{t(locale, 'product')}<select required value={form.product_id} onChange={(event) => setForm((current) => ({ ...current, product_id: event.target.value }))}>{meta.products.map((product) => <option value={product.id} key={product.id}>{product.label}</option>)}</select></label><label>{t(locale, 'date')}<input required type="date" value={form.movement_date} onChange={(event) => setForm((current) => ({ ...current, movement_date: event.target.value }))} /></label><label>{t(locale, 'reference')}<input value={form.reference_code} onChange={(event) => setForm((current) => ({ ...current, reference_code: event.target.value }))} /></label><label>{t(locale, 'countedQuantity')}<input required min="0" step="0.01" type="number" value={form.counted_quantity} onChange={(event) => setForm((current) => ({ ...current, counted_quantity: event.target.value }))} /></label><label>{t(locale, 'cost')}<input min="0" step="0.01" type="number" readOnly={preview.averageCost > 0} value={form.unit_cost} onChange={(event) => setForm((current) => ({ ...current, unit_cost: event.target.value }))} /></label><div className="span-2 closing-count-preview" aria-live="polite"><div><span>{t(locale, 'countPreview')}</span><small>{preview.loading ? t(locale, 'loadingBalances') : preview.error || t(locale, 'countPreviewHint')}</small></div><dl><div><dt>{t(locale, 'systemQuantity')}</dt><dd>{number(preview.quantity)}</dd></div><div><dt>{t(locale, 'countedQuantity')}</dt><dd>{number(form.counted_quantity)}</dd></div><div><dt>{t(locale, 'variance')}</dt><dd className={variance < 0 ? 'text-danger' : 'text-success'}>{variance > 0 ? '+' : ''}{number(variance)}</dd></div><div><dt>{t(locale, 'currentValue')}</dt><dd>{money(Number(form.counted_quantity || 0) * Number(form.unit_cost || preview.averageCost || 0))}</dd></div></dl></div><label className="span-2">{t(locale, 'notes')}<textarea value={form.notes} onChange={(event) => setForm((current) => ({ ...current, notes: event.target.value }))} /></label></div></div><footer><button className="button" type="button" onClick={closeForm}>{t(locale, 'cancel')}</button><button className="button primary" type="submit" disabled={saving || preview.loading || Boolean(preview.error)}><Save size={15} />{t(locale, 'saveCount')}</button></footer></form></div>}
        </section>
    );
}

export function StockBalanceScreen({ locale }) {
    const [meta, setMeta] = useState({ loading: true, warehouses: [], products: [], error: '' });
    const [filters, setFilters] = useState({ search: '', warehouse_id: '', product_id: '' });
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
        let mounted = true;
        setState((current) => ({ ...current, loading: true, error: '' }));
        const timer = window.setTimeout(() => {
            window.axios.get(`${apiBase()}/balances`, {
                params: {
                    search: filters.search || undefined,
                    warehouse_id: filters.warehouse_id || undefined,
                    product_id: filters.product_id || undefined,
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
    }, [filters.search, filters.warehouse_id, filters.product_id]);

    return (
        <section className="page stock-workspace">
            <div className="master-heading">
                <div>
                    <p className="eyebrow">Phase 5 · Warehouse Stock</p>
                    <h1>{t(locale, 'balances')}</h1>
                    <span className="muted">{t(locale, 'balanceHint')}</span>
                </div>
                <button className="button" type="button" onClick={() => setRefreshKey((value) => value + 1)}><RefreshCw size={15} />{t(locale, 'refresh')}</button>
            </div>

            <div className="metrics stock-metrics">
                <Metric label={t(locale, 'products')} value={number(state.summary.products_count)} hint={t(locale, 'balances')} icon={Package} />
                <Metric label={t(locale, 'totalQuantity')} value={number(state.summary.total_quantity)} hint={t(locale, 'unit')} icon={Archive} />
                <Metric label={t(locale, 'stockValue')} value={money(state.summary.stock_value)} hint="MMK" icon={Archive} />
            </div>

            <section className="master-panel">
                <div className="master-panel-heading">
                    <div>
                        <p className="eyebrow">{t(locale, 'balances')}</p>
                        <h2>{t(locale, 'warehouse')}</h2>
                    </div>
                </div>
                <div className="master-toolbar stock-toolbar">
                    <label className="master-search"><Search size={14} /><input value={filters.search} placeholder={t(locale, 'searchBalances')} onChange={(event) => setFilters((current) => ({ ...current, search: event.target.value }))} /></label>
                    <select value={filters.warehouse_id} onChange={(event) => setFilters((current) => ({ ...current, warehouse_id: event.target.value }))}>
                        <option value="">{t(locale, 'allWarehouses')}</option>
                        {meta.warehouses.map((warehouse) => <option value={warehouse.id} key={warehouse.id}>{warehouse.label}</option>)}
                    </select>
                    <select value={filters.product_id} onChange={(event) => setFilters((current) => ({ ...current, product_id: event.target.value }))}>
                        <option value="">{t(locale, 'allProducts')}</option>
                        {meta.products.map((product) => <option value={product.id} key={product.id}>{product.label}</option>)}
                    </select>
                    <button className="button" type="button" onClick={() => setRefreshKey((value) => value + 1)}><RefreshCw size={15} />{t(locale, 'refresh')}</button>
                </div>

                {state.loading ? (
                    <WorkspaceState icon={RefreshCw} title={t(locale, 'loadingBalances')} loading />
                ) : state.error ? (
                    <WorkspaceState icon={RefreshCw} title={state.error} action={() => setRefreshKey((value) => value + 1)} actionLabel={t(locale, 'retry')} />
                ) : state.items.length === 0 ? (
                    <WorkspaceState icon={Package} title={t(locale, 'emptyBalances')} />
                ) : (
                    <>
                        <div className="master-table-wrap">
                            <table className="master-table stock-table">
                                <thead>
                                    <tr>
                                        <th>{t(locale, 'warehouse')}</th>
                                        <th>{t(locale, 'product')}</th>
                                        <th>{t(locale, 'quantity')}</th>
                                        <th>{t(locale, 'cost')}</th>
                                        <th>{t(locale, 'stockValue')}</th>
                                        <th>{t(locale, 'lastMovement')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {state.items.map((balance) => (
                                        <tr key={balance.id}>
                                            <td><strong>{balance.warehouse_code}</strong><span className="muted">{balance.warehouse_name}</span></td>
                                            <td><strong>{balance.product_name}</strong><span className="muted">{balance.product_sku} · {balance.unit}</span></td>
                                            <td className="numeric">{number(balance.quantity)}</td>
                                            <td className="numeric">{money(balance.average_cost)}</td>
                                            <td className="numeric">{money(balance.stock_value)}</td>
                                            <td>{formatDateTime(balance.last_movement_at)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <Pagination meta={state.pageMeta} page={page} setPage={setPage} />
                    </>
                )}
            </section>
        </section>
    );
}

export function StockValueScreen({ locale }) {
    const [meta, setMeta] = useState({ loading: true, warehouses: [], products: [], error: '' });
    const [filters, setFilters] = useState({ search: '', warehouse_id: '', product_id: '' });
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

    useEffect(() => {
        let mounted = true;
        setState((current) => ({ ...current, loading: true, error: '' }));
        const timer = window.setTimeout(() => {
            window.axios.get(`${apiBase()}/value`, {
                params: {
                    search: filters.search || undefined,
                    warehouse_id: filters.warehouse_id || undefined,
                    product_id: filters.product_id || undefined,
                    page,
                    per_page: 20,
                },
            }).then(({ data }) => {
                if (!mounted) return;
                setState({ loading: false, items: data.data.items, warehouses: data.data.warehouses, summary: data.data.summary, pageMeta: data.data.meta, error: '' });
            }).catch((error) => mounted && setState({ loading: false, items: [], warehouses: [], summary: {}, pageMeta: {}, error: requestMessage(error) }));
        }, 220);

        return () => { mounted = false; window.clearTimeout(timer); };
    }, [filters, page, refreshKey]);

    useEffect(() => {
        setPage(1);
    }, [filters.search, filters.warehouse_id, filters.product_id]);

    const totalValue = Number(state.summary.stock_value || 0);

    return (
        <section className="page stock-workspace">
            <div className="master-heading">
                <div>
                    <p className="eyebrow">Phase 5 · Warehouse Stock</p>
                    <h1>{t(locale, 'stockValueReport')}</h1>
                    <span className="muted">{t(locale, 'stockValueHint')}</span>
                </div>
                <button className="button" type="button" onClick={() => setRefreshKey((value) => value + 1)}><RefreshCw size={15} />{t(locale, 'refresh')}</button>
            </div>

            <div className="metrics stock-metrics">
                <Metric label={t(locale, 'stockValue')} value={money(totalValue)} hint="MMK" icon={Archive} />
                <Metric label={t(locale, 'warehouses')} value={number(state.summary.warehouses_count)} hint={t(locale, 'warehouseValue')} icon={Archive} />
                <Metric label={t(locale, 'products')} value={number(state.summary.products_count)} hint={t(locale, 'rankedValue')} icon={Package} />
                <Metric label={t(locale, 'totalQuantity')} value={number(state.summary.total_quantity)} hint={t(locale, 'unit')} icon={Package} />
            </div>

            <section className="master-panel stock-value-filter-panel">
                <div className="master-toolbar stock-toolbar">
                    <label className="master-search"><Search size={14} /><input value={filters.search} placeholder={t(locale, 'searchValues')} onChange={(event) => setFilters((current) => ({ ...current, search: event.target.value }))} /></label>
                    <select value={filters.warehouse_id} onChange={(event) => setFilters((current) => ({ ...current, warehouse_id: event.target.value }))}>
                        <option value="">{t(locale, 'allWarehouses')}</option>
                        {meta.warehouses.map((warehouse) => <option value={warehouse.id} key={warehouse.id}>{warehouse.label}</option>)}
                    </select>
                    <select value={filters.product_id} onChange={(event) => setFilters((current) => ({ ...current, product_id: event.target.value }))}>
                        <option value="">{t(locale, 'allProducts')}</option>
                        {meta.products.map((product) => <option value={product.id} key={product.id}>{product.label}</option>)}
                    </select>
                    <button className="button" type="button" onClick={() => setRefreshKey((value) => value + 1)}><RefreshCw size={15} />{t(locale, 'refresh')}</button>
                </div>
            </section>

            {state.loading ? (
                <section className="master-panel"><WorkspaceState icon={RefreshCw} title={t(locale, 'loadingValues')} loading /></section>
            ) : state.error || meta.error ? (
                <section className="master-panel"><WorkspaceState icon={RefreshCw} title={state.error || meta.error} action={() => setRefreshKey((value) => value + 1)} actionLabel={t(locale, 'retry')} /></section>
            ) : state.items.length === 0 ? (
                <section className="master-panel"><WorkspaceState icon={Package} title={t(locale, 'emptyValues')} /></section>
            ) : (
                <div className="stock-value-layout">
                    <section className="master-panel stock-value-breakdown">
                        <div className="master-panel-heading">
                            <div><p className="eyebrow">{t(locale, 'stockValue')}</p><h2>{t(locale, 'warehouseValue')}</h2></div>
                        </div>
                        <div className="stock-value-warehouses">
                            {state.warehouses.map((warehouse) => {
                                const share = totalValue > 0 ? (Number(warehouse.stock_value) / totalValue) * 100 : 0;
                                return (
                                    <article key={warehouse.id}>
                                        <header><span><strong>{warehouse.code}</strong><small>{warehouse.name}</small></span><strong>{money(warehouse.stock_value)}</strong></header>
                                        <div className="stock-value-track" role="progressbar" aria-label={`${warehouse.name} ${t(locale, 'shareOfValue')}`} aria-valuemin="0" aria-valuemax="100" aria-valuenow={Math.round(share)}><span style={{ width: `${Math.min(share, 100)}%` }} /></div>
                                        <footer><span>{number(warehouse.products_count)} {t(locale, 'products')}</span><span>{number(warehouse.total_quantity)} {t(locale, 'unit')}</span><strong>{share.toFixed(1)}%</strong></footer>
                                    </article>
                                );
                            })}
                        </div>
                    </section>

                    <section className="master-panel stock-value-ranking">
                        <div className="master-panel-heading">
                            <div><p className="eyebrow">{t(locale, 'stockValueReport')}</p><h2>{t(locale, 'rankedValue')}</h2></div>
                            <span className="muted">{number(state.summary.balance_lines)} {t(locale, 'balanceLines')}</span>
                        </div>
                        <div className="master-table-wrap">
                            <table className="master-table stock-table stock-value-table">
                                <thead><tr><th>#</th><th>{t(locale, 'product')}</th><th>{t(locale, 'warehouse')}</th><th>{t(locale, 'quantity')}</th><th>{t(locale, 'cost')}</th><th>{t(locale, 'stockValue')}</th><th>{t(locale, 'shareOfValue')}</th></tr></thead>
                                <tbody>
                                    {state.items.map((balance, index) => (
                                        <tr key={balance.id}>
                                            <td className="numeric">{((page - 1) * (state.pageMeta.per_page || 20)) + index + 1}</td>
                                            <td><strong>{balance.product_name}</strong><span className="muted">{balance.product_sku} · {balance.unit}</span></td>
                                            <td><strong>{balance.warehouse_code}</strong><span className="muted">{balance.warehouse_name}</span></td>
                                            <td className="numeric">{number(balance.quantity)}</td>
                                            <td className="numeric">{money(balance.average_cost)}</td>
                                            <td className="numeric"><strong>{money(balance.stock_value)}</strong></td>
                                            <td className="numeric">{totalValue > 0 ? `${((Number(balance.stock_value) / totalValue) * 100).toFixed(1)}%` : '0.0%'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <Pagination meta={state.pageMeta} page={page} setPage={setPage} />
                    </section>
                </div>
            )}
        </section>
    );
}

export function StockTransferScreen({ locale, canManage = false }) {
    const [meta, setMeta] = useState({ loading: true, warehouses: [], products: [], error: '' });
    const [filters, setFilters] = useState({ search: '', product_id: '', date: '' });
    const [state, setState] = useState({ loading: true, items: [], summary: {}, pageMeta: {}, error: '' });
    const [page, setPage] = useState(1);
    const [refreshKey, setRefreshKey] = useState(0);
    const [formOpen, setFormOpen] = useState(false);
    const [saving, setSaving] = useState(false);
    const [formError, setFormError] = useState('');
    const [form, setForm] = useState(blankTransferForm);

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
            window.axios.get(`${apiBase()}/movements`, {
                params: {
                    search: filters.search || undefined,
                    product_id: filters.product_id || undefined,
                    type: 'transfer_out',
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
    }, [filters.search, filters.product_id, filters.date]);

    const openForm = () => {
        const firstWarehouse = meta.warehouses[0];
        const secondWarehouse = meta.warehouses.find((warehouse) => Number(warehouse.id) !== Number(firstWarehouse?.id));
        setForm({
            ...blankTransferForm,
            from_warehouse_id: firstWarehouse?.id || '',
            to_warehouse_id: secondWarehouse?.id || '',
            product_id: meta.products[0]?.id || '',
            movement_date: new Date().toISOString().slice(0, 10),
        });
        setFormError('');
        setFormOpen(true);
    };

    const closeForm = () => {
        setFormOpen(false);
        setSaving(false);
        setFormError('');
    };

    const saveTransfer = (event) => {
        event.preventDefault();
        setSaving(true);
        setFormError('');
        window.axios.post(`${apiBase()}/transfers`, form)
            .then(() => {
                closeForm();
                setRefreshKey((value) => value + 1);
            })
            .catch((error) => {
                setSaving(false);
                setFormError(requestMessage(error));
            });
    };

    return (
        <section className="page stock-workspace">
            <div className="master-heading">
                <div>
                    <p className="eyebrow">Phase 5 · Warehouse Stock</p>
                    <h1>{t(locale, 'transfer')}</h1>
                    <span className="muted">{t(locale, 'transferHint')}</span>
                </div>
                {canManage && <button className="button primary" type="button" onClick={openForm} disabled={meta.loading || meta.warehouses.length < 2 || meta.error}><Plus size={16} />{t(locale, 'newTransfer')}</button>}
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
                <div className="master-toolbar stock-toolbar">
                    <label className="master-search"><Search size={14} /><input value={filters.search} placeholder={t(locale, 'searchTransfers')} onChange={(event) => setFilters((current) => ({ ...current, search: event.target.value }))} /></label>
                    <select value={filters.product_id} onChange={(event) => setFilters((current) => ({ ...current, product_id: event.target.value }))}>
                        <option value="">{t(locale, 'allProducts')}</option>
                        {meta.products.map((product) => <option value={product.id} key={product.id}>{product.label}</option>)}
                    </select>
                    <label className="date-filter"><CalendarDays size={14} /><input type="date" value={filters.date} onChange={(event) => setFilters((current) => ({ ...current, date: event.target.value }))} /></label>
                    <button className="button" type="button" onClick={() => setRefreshKey((value) => value + 1)}><RefreshCw size={15} />{t(locale, 'refresh')}</button>
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
                                        <th>{t(locale, 'product')}</th>
                                        <th>{t(locale, 'date')}</th>
                                        <th>{t(locale, 'quantity')}</th>
                                        <th>{t(locale, 'cost')}</th>
                                        <th>{t(locale, 'notes')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {state.items.map((movement) => (
                                        <tr key={movement.id}>
                                            <td><strong>{movement.reference_code || movement.code}</strong><span className="muted">{movement.code}</span></td>
                                            <td><strong>{movement.warehouse_code}</strong><span className="muted">{movement.warehouse_name}</span></td>
                                            <td><strong>{movement.product_name}</strong><span className="muted">{movement.product_sku} · {movement.unit}</span></td>
                                            <td>{formatDate(movement.movement_date)}</td>
                                            <td className="numeric">{number(movement.quantity)}</td>
                                            <td className="numeric">{money(movement.unit_cost)}</td>
                                            <td>{movement.notes || '-'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <Pagination meta={state.pageMeta} page={page} setPage={setPage} />
                    </>
                )}
            </section>

            {formOpen && (
                <div className="modal-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && closeForm()}>
                    <form className="master-dialog stock-dialog" onSubmit={saveTransfer}>
                        <header><h2>{t(locale, 'newTransfer')}</h2><button className="icon-button" type="button" aria-label={t(locale, 'cancel')} onClick={closeForm}><X size={16} /></button></header>
                        <div className="master-form-body">
                            {formError && <p className="form-alert">{formError}</p>}
                            <div className="master-form-grid">
                                <label>{t(locale, 'fromWarehouse')}<select required value={form.from_warehouse_id} onChange={(event) => setForm((current) => ({ ...current, from_warehouse_id: event.target.value }))}>{meta.warehouses.map((warehouse) => <option value={warehouse.id} key={warehouse.id}>{warehouse.label}</option>)}</select></label>
                                <label>{t(locale, 'toWarehouse')}<select required value={form.to_warehouse_id} onChange={(event) => setForm((current) => ({ ...current, to_warehouse_id: event.target.value }))}>{meta.warehouses.map((warehouse) => <option value={warehouse.id} key={warehouse.id}>{warehouse.label}</option>)}</select></label>
                                <label>{t(locale, 'product')}<select required value={form.product_id} onChange={(event) => setForm((current) => ({ ...current, product_id: event.target.value }))}>{meta.products.map((product) => <option value={product.id} key={product.id}>{product.label}</option>)}</select></label>
                                <label>{t(locale, 'date')}<input type="date" value={form.movement_date} onChange={(event) => setForm((current) => ({ ...current, movement_date: event.target.value }))} /></label>
                                <label>{t(locale, 'quantity')}<input required min="0.01" step="0.01" type="number" value={form.quantity} onChange={(event) => setForm((current) => ({ ...current, quantity: event.target.value }))} /></label>
                                <label>{t(locale, 'reference')}<input value={form.reference_code} onChange={(event) => setForm((current) => ({ ...current, reference_code: event.target.value }))} /></label>
                                <label className="span-2">{t(locale, 'notes')}<textarea value={form.notes} onChange={(event) => setForm((current) => ({ ...current, notes: event.target.value }))} /></label>
                            </div>
                        </div>
                        <footer><button className="button" type="button" onClick={closeForm}>{t(locale, 'cancel')}</button><button className="button primary" type="submit" disabled={saving}><Save size={15} />{t(locale, 'saveTransfer')}</button></footer>
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
                <button className="button" type="button" onClick={() => setRefreshKey((value) => value + 1)}><RefreshCw size={15} />{t(locale, 'refresh')}</button>
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
    const family = ['opening', 'receive', 'transfer_in'].includes(status) ? 'success' : ['issue', 'transfer_out'].includes(status) ? 'info' : ['damage'].includes(status) ? 'danger' : 'neutral';
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
