import { useCallback, useEffect, useRef, useState, type FormEvent, type ReactNode } from 'react';
import type { AxiosError } from 'axios';
import { apiClient } from '../../../packages/api-client/client';
import { Icon } from '../../../packages/design-system/Icon';
import { useI18n } from '../../../packages/i18n/I18nProvider';
import { MasterDataNavigation } from './MasterDataNavigation';

interface LocalizedName { en: string; 'my-MM': string | null }
interface Uom { id: string; code: string; symbol: string; name?: LocalizedName }
interface Price {
    id: string; price_book: { id: string; code: string; type: { code: string; name: LocalizedName } };
    uom: Uom; unit_price_minor: number; minimum_quantity: string; effective_from: string; effective_to: string | null;
    approval_status: 'approved' | 'pending'; status: string; version: number;
}
interface Sku {
    id: string; code: string; name: LocalizedName; product: { id: string; code: string; name: LocalizedName; brand: { code: string; name: LocalizedName } };
    size_label: string | null; barcode: string | null; volume_ml: string | null; weight_grams: string | null; shelf_life_days: number | null;
    track_lot: boolean; track_expiry: boolean; is_returnable: boolean; minimum_order_quantity: string; order_step_quantity: string;
    minimum_delivery_quantity: string; sale_status: 'saleable' | 'temporarily_unavailable' | 'not_for_sale'; base_uom: Uom;
    conversions: Array<{ id: string; uom: Uom; factor_to_base: string; version: number }>;
    prices: Price[]; active_from: string | null; active_to: string | null; status: 'active' | 'inactive' | 'archived'; version: number; updated_at: string;
}
interface OptionProduct { public_id: string; code: string; name_en: string; name_my: string | null; brand: { code: string; name_en: string; name_my: string | null } }
interface OptionUnit { public_id: string; code: string; name_en: string; name_my: string | null; symbol: string }
interface OptionBook { public_id: string; code: string; name_en: string; name_my: string | null; currency: string; price_type: { code: string; name_en: string; name_my: string | null } }
interface Options { products: OptionProduct[]; units: OptionUnit[]; price_books: OptionBook[] }
interface ApiError { message?: string; code?: string; errors?: Record<string, string[]> }
interface PageResponse { data: Sku[]; meta: { current_page: number; last_page: number; total: number } }

const emptySkuForm = {
    product_public_id: '', base_uom_public_id: '', code: '', name_en: '', name_my: '', size_label: '', barcode: '', volume_ml: '', weight_grams: '',
    shelf_life_days: '', track_lot: false, track_expiry: false, is_returnable: false, minimum_order_quantity: '1', order_step_quantity: '1',
    minimum_delivery_quantity: '1', sale_status: 'saleable' as Sku['sale_status'], active_from: '', active_to: '', status: 'active' as 'active' | 'inactive', version: undefined as number | undefined,
};

export function CatalogPage() {
    const { locale, t } = useI18n();
    const [skus, setSkus] = useState<Sku[]>([]);
    const [options, setOptions] = useState<Options>({ products: [], units: [], price_books: [] });
    const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
    const [page, setPage] = useState(1);
    const [searchDraft, setSearchDraft] = useState('');
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('');
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState(false);
    const [refreshKey, setRefreshKey] = useState(0);
    const [dialog, setDialog] = useState<'sku' | 'conversion' | 'price' | 'archive' | null>(null);
    const [selected, setSelected] = useState<Sku | null>(null);
    const [skuForm, setSkuForm] = useState(emptySkuForm);
    const [priceForm, setPriceForm] = useState({ price_book_public_id: '', uom_public_id: '', unit_price_minor: '', minimum_quantity: '1', effective_from: new Date().toISOString().slice(0, 10), effective_to: '' });
    const [conversionForm, setConversionForm] = useState({ uom_public_id: '', factor_to_base: '', effective_from: new Date().toISOString().slice(0, 10), is_selling_unit: true, is_kpi_base: false });
    const [archiveReason, setArchiveReason] = useState('');
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [submitting, setSubmitting] = useState(false);
    const [flash, setFlash] = useState<string | null>(null);
    const firstInput = useRef<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement | null>(null);
    const trigger = useRef<HTMLElement | null>(null);

    const load = useCallback(async (signal?: AbortSignal) => {
        setLoading(true); setLoadError(false);
        try {
            const [skuResponse, optionResponse] = await Promise.all([
                apiClient.get<PageResponse>('/master-data/skus', { params: { page, per_page: 20, search: search || undefined, status: status || undefined }, signal }),
                apiClient.get<{ data: Options }>('/master-data/catalog/options', { signal }),
            ]);
            setSkus(skuResponse.data.data); setMeta(skuResponse.data.meta); setOptions(optionResponse.data.data);
        } catch {
            if (!signal?.aborted) setLoadError(true);
        } finally {
            if (!signal?.aborted) setLoading(false);
        }
    }, [page, search, status]);

    useEffect(() => { const controller = new AbortController(); void load(controller.signal); return () => controller.abort(); }, [load, refreshKey]);
    useEffect(() => {
        if (!dialog) return;
        const escape = (event: KeyboardEvent) => { if (event.key === 'Escape') closeDialog(); };
        document.addEventListener('keydown', escape); window.setTimeout(() => firstInput.current?.focus(), 0);
        return () => document.removeEventListener('keydown', escape);
    }, [dialog]);

    const localized = (name: LocalizedName) => name[locale] || name.en;
    const closeDialog = () => { if (submitting) return; setDialog(null); setErrors({}); window.setTimeout(() => trigger.current?.focus(), 0); };
    const openCreate = (event: React.MouseEvent<HTMLElement>) => {
        trigger.current = event.currentTarget; setSelected(null); setErrors({});
        setSkuForm({ ...emptySkuForm, product_public_id: options.products[0]?.public_id ?? '', base_uom_public_id: options.units.find((unit) => unit.code === 'BTL')?.public_id ?? options.units[0]?.public_id ?? '' });
        setDialog('sku');
    };
    const openEdit = (sku: Sku, event: React.MouseEvent<HTMLElement>) => {
        trigger.current = event.currentTarget; setSelected(sku); setErrors({});
        setSkuForm({
            ...emptySkuForm, code: sku.code, name_en: sku.name.en, name_my: sku.name['my-MM'] ?? '', size_label: sku.size_label ?? '', barcode: sku.barcode ?? '',
            volume_ml: sku.volume_ml ?? '', weight_grams: sku.weight_grams ?? '', shelf_life_days: sku.shelf_life_days?.toString() ?? '', track_lot: sku.track_lot,
            track_expiry: sku.track_expiry, is_returnable: sku.is_returnable, minimum_order_quantity: sku.minimum_order_quantity, order_step_quantity: sku.order_step_quantity,
            minimum_delivery_quantity: sku.minimum_delivery_quantity, sale_status: sku.sale_status, active_from: sku.active_from ?? '', active_to: sku.active_to ?? '',
            status: sku.status === 'inactive' ? 'inactive' : 'active', version: sku.version,
        }); setDialog('sku');
    };
    const openPrice = (sku: Sku, event: React.MouseEvent<HTMLElement>) => {
        trigger.current = event.currentTarget; setSelected(sku); setErrors({});
        setPriceForm({ price_book_public_id: options.price_books[0]?.public_id ?? '', uom_public_id: sku.conversions[0]?.uom.id ?? '', unit_price_minor: '', minimum_quantity: '1', effective_from: new Date().toISOString().slice(0, 10), effective_to: '' });
        setDialog('price');
    };
    const openConversion = (sku: Sku, event: React.MouseEvent<HTMLElement>) => {
        trigger.current = event.currentTarget; setSelected(sku); setErrors({});
        const availableUnit = options.units.find((unit) => !sku.conversions.some((conversion) => conversion.uom.id === unit.public_id)) ?? options.units[0];
        setConversionForm({ uom_public_id: availableUnit?.public_id ?? '', factor_to_base: '', effective_from: new Date().toISOString().slice(0, 10), is_selling_unit: true, is_kpi_base: false });
        setDialog('conversion');
    };
    const openArchive = (sku: Sku, event: React.MouseEvent<HTMLElement>) => { trigger.current = event.currentTarget; setSelected(sku); setArchiveReason(''); setErrors({}); setDialog('archive'); };

    async function submitSku(event: FormEvent) {
        event.preventDefault(); setSubmitting(true); setErrors({});
        const payload = { ...skuForm, name_my: skuForm.name_my || null, barcode: skuForm.barcode || null, volume_ml: skuForm.volume_ml || null, weight_grams: skuForm.weight_grams || null, shelf_life_days: skuForm.shelf_life_days || null, active_from: skuForm.active_from || null, active_to: skuForm.active_to || null };
        try {
            if (selected) await apiClient.put(`/master-data/skus/${selected.id}`, payload);
            else await apiClient.post('/master-data/skus', payload);
            setFlash(selected ? t('skuUpdated') : t('skuCreated')); setDialog(null); setRefreshKey((value) => value + 1);
        } catch (error) { handleError(error); } finally { setSubmitting(false); }
    }
    async function submitPrice(event: FormEvent) {
        event.preventDefault(); if (!selected) return; setSubmitting(true); setErrors({});
        try {
            await apiClient.post('/master-data/prices', { ...priceForm, sku_public_id: selected.id, unit_price_minor: Number(priceForm.unit_price_minor), effective_to: priceForm.effective_to || null, status: 'active' });
            setFlash(t('priceCreated')); setDialog(null); setRefreshKey((value) => value + 1);
        } catch (error) { handleError(error); } finally { setSubmitting(false); }
    }
    async function submitConversion(event: FormEvent) {
        event.preventDefault(); if (!selected) return; setSubmitting(true); setErrors({});
        try {
            await apiClient.post(`/master-data/skus/${selected.id}/conversions`, { ...conversionForm, version: selected.version, factor_to_base: Number(conversionForm.factor_to_base) });
            setFlash(t('conversionSaved')); setDialog(null); setRefreshKey((value) => value + 1);
        } catch (error) { handleError(error); } finally { setSubmitting(false); }
    }
    async function submitArchive(event: FormEvent) {
        event.preventDefault(); if (!selected) return; setSubmitting(true); setErrors({});
        try {
            await apiClient.patch(`/master-data/skus/${selected.id}/archive`, { version: selected.version, reason: archiveReason });
            setFlash(t('skuArchived')); setDialog(null); setRefreshKey((value) => value + 1);
        } catch (error) { handleError(error); } finally { setSubmitting(false); }
    }
    function handleError(error: unknown) {
        const response = (error as AxiosError<ApiError>).response;
        const conflict = response?.data.code === 'price_date_overlap' ? t('priceOverlap')
            : response?.data.code === 'missing_unit_conversion' ? t('missingConversion')
                : response?.data.code === 'invalid_base_conversion' ? t('invalidBaseConversion')
                    : response?.data.code === 'conversion_date_conflict' ? t('conversionDateConflict')
                        : response?.data.code === 'missing_kpi_base' ? t('missingKpiBase') : t('catalogConflict');
        setErrors(response?.status === 409 ? { form: [conflict] } : response?.data.errors ?? { form: [response?.data.message ?? t('errorLoadingCatalog')] });
    }

    return <>
        <section className="page-heading">
            <div><p className="eyebrow">{t('phaseOne')}</p><h1>{t('catalogAndPricing')}</h1><p>{t('catalogDescription')}</p></div>
            <button className="button button--primary" type="button" onClick={openCreate}><Icon name="plus" size={16} />{t('addSku')}</button>
        </section>
        <MasterDataNavigation />
        {flash && <div className="flash-message flash-message--success" role="status"><span className="status-dot status-dot--success" />{flash}</div>}
        <article className="panel master-register">
            <div className="panel-heading"><div><p className="eyebrow">{t('productCatalog')}</p><h2>{t('skuRegister')}</h2></div><span className="status-badge status-badge--info"><span />{t('totalRecords')}: {meta.total}</span></div>
            <form className="filter-toolbar" onSubmit={(event) => { event.preventDefault(); setPage(1); setSearch(searchDraft.trim()); }}>
                <label className="search-field"><span className="sr-only">{t('searchCatalog')}</span><Icon name="activity" size={15} /><input value={searchDraft} onChange={(event) => setSearchDraft(event.target.value)} placeholder={t('searchCatalog')} /></label>
                <label><span className="sr-only">{t('status')}</span><select value={status} onChange={(event) => { setStatus(event.target.value); setPage(1); }}><option value="">{t('allStatuses')}</option><option value="active">{t('active')}</option><option value="inactive">{t('inactive')}</option><option value="archived">{t('archived')}</option></select></label>
                <button className="button button--secondary" type="submit">{t('search')}</button>
                <button className="icon-button" type="button" onClick={() => setRefreshKey((value) => value + 1)} aria-label={t('refresh')}><Icon name="refresh" size={16} /></button>
            </form>
            {loadError ? <div className="empty-state register-state" role="alert"><strong>{t('errorLoadingCatalog')}</strong><button className="button button--secondary" onClick={() => setRefreshKey((value) => value + 1)}>{t('retry')}</button></div> :
                <div className="table-region master-table catalog-table" tabIndex={0} aria-busy={loading}><table><thead><tr><th>{t('sku')}</th><th>{t('product')}</th><th>{t('baseUnit')}</th><th>{t('orderRules')}</th><th>{t('activePrices')}</th><th>{t('status')}</th><th className="actions-column">{t('actions')}</th></tr></thead><tbody>
                    {loading && Array.from({ length: 5 }, (_, index) => <tr className="skeleton-row" key={index} aria-hidden="true">{Array.from({ length: 7 }, (__, cell) => <td key={cell}><span /></td>)}</tr>)}
                    {!loading && !skus.length && <tr><td colSpan={7} className="table-empty">{t('noSkus')}</td></tr>}
                    {!loading && skus.map((sku) => <tr key={sku.id}>
                        <td className="identity-cell"><strong>{sku.code}</strong><small>{localized(sku.name)} · {sku.size_label || '—'}</small></td>
                        <td><strong>{localized(sku.product.name)}</strong><small>{localized(sku.product.brand.name)}</small></td>
                        <td><strong>{sku.base_uom.code}</strong><small>{t('conversionCount')}: {sku.conversions.length}</small></td>
                        <td><strong>{sku.minimum_order_quantity} / {sku.order_step_quantity}</strong><small>{t('minimumStep')}</small></td>
                        <td>{sku.prices.length ? <div className="price-stack">{sku.prices.slice(0, 3).map((price) => <span key={price.id}>{price.price_book.type.code}: {Number(price.unit_price_minor).toLocaleString()} {price.uom.symbol}{price.approval_status === 'pending' ? ` · ${t('pendingApproval')}` : ''}</span>)}</div> : <span className="muted-copy">{t('notPriced')}</span>}</td>
                        <td><Status value={sku.status} label={t(sku.status)} /></td>
                        <td className="actions-column"><div className="row-actions"><button className="icon-button icon-button--small" type="button" onClick={(event) => openConversion(sku, event)} aria-label={`${t('configureConversion')} ${localized(sku.name)}`} title={t('configureConversion')}><Icon name="box" size={15} /></button><button className="icon-button icon-button--small" type="button" onClick={(event) => openPrice(sku, event)} aria-label={`${t('setPrice')} ${localized(sku.name)}`} title={t('setPrice')}><Icon name="cash" size={15} /></button><button className="icon-button icon-button--small" type="button" onClick={(event) => openEdit(sku, event)} aria-label={`${t('edit')} ${localized(sku.name)}`}><Icon name="edit" size={15} /></button>{sku.status !== 'archived' && <button className="icon-button icon-button--small icon-button--danger" type="button" onClick={(event) => openArchive(sku, event)} aria-label={`${t('archive')} ${localized(sku.name)}`}><Icon name="archive" size={15} /></button>}</div></td>
                    </tr>)}</tbody></table></div>}
            <div className="pagination-bar"><span>{t('totalRecords')}: {meta.total}</span><div><button disabled={page <= 1 || loading} onClick={() => setPage((value) => value - 1)}>{t('previous')}</button><strong>{meta.current_page} / {meta.last_page}</strong><button disabled={page >= meta.last_page || loading} onClick={() => setPage((value) => value + 1)}>{t('next')}</button></div></div>
        </article>

        {dialog === 'sku' && <Dialog title={selected ? t('editSku') : t('addSku')} close={closeDialog} submitting={submitting} onSubmit={submitSku} action={t('save')}>
            {errors.form && <p className="form-error form-error--wide" role="alert">{errors.form[0]}</p>}
            {!selected && <Field label={t('product')} error={errors.product_public_id?.[0]}><select ref={(node) => { firstInput.current = node; }} value={skuForm.product_public_id} onChange={(event) => setSkuForm({ ...skuForm, product_public_id: event.target.value })} required>{options.products.map((product) => <option value={product.public_id} key={product.public_id}>{locale === 'my-MM' ? product.name_my || product.name_en : product.name_en}</option>)}</select></Field>}
            {!selected && <Field label={t('baseUnit')} error={errors.base_uom_public_id?.[0]}><select value={skuForm.base_uom_public_id} onChange={(event) => setSkuForm({ ...skuForm, base_uom_public_id: event.target.value })} required>{options.units.map((unit) => <option value={unit.public_id} key={unit.public_id}>{unit.code} · {unit.symbol}</option>)}</select></Field>}
            <Field label={t('code')} error={errors.code?.[0]}><input ref={selected ? (node) => { firstInput.current = node; } : undefined} value={skuForm.code} onChange={(event) => setSkuForm({ ...skuForm, code: event.target.value.toUpperCase() })} required /></Field>
            <Field label={t('sizeLabel')} error={errors.size_label?.[0]}><input value={skuForm.size_label} onChange={(event) => setSkuForm({ ...skuForm, size_label: event.target.value })} /></Field>
            <Field label={t('englishName')} error={errors.name_en?.[0]}><input value={skuForm.name_en} onChange={(event) => setSkuForm({ ...skuForm, name_en: event.target.value })} required /></Field>
            <Field label={t('myanmarName')} error={errors.name_my?.[0]}><input lang="my" value={skuForm.name_my} onChange={(event) => setSkuForm({ ...skuForm, name_my: event.target.value })} /></Field>
            <Field label={t('barcode')} error={errors.barcode?.[0]}><input value={skuForm.barcode} onChange={(event) => setSkuForm({ ...skuForm, barcode: event.target.value })} /></Field>
            <Field label={t('volumeMl')} error={errors.volume_ml?.[0]}><input type="number" min="0.001" step="0.001" value={skuForm.volume_ml} onChange={(event) => setSkuForm({ ...skuForm, volume_ml: event.target.value })} /></Field>
            <Field label={t('minimumOrder')} error={errors.minimum_order_quantity?.[0]}><input type="number" min="0.001" step="0.001" value={skuForm.minimum_order_quantity} onChange={(event) => setSkuForm({ ...skuForm, minimum_order_quantity: event.target.value })} required /></Field>
            <Field label={t('orderStep')} error={errors.order_step_quantity?.[0]}><input type="number" min="0.001" step="0.001" value={skuForm.order_step_quantity} onChange={(event) => setSkuForm({ ...skuForm, order_step_quantity: event.target.value })} required /></Field>
            <Field label={t('saleStatus')} error={errors.sale_status?.[0]}><select value={skuForm.sale_status} onChange={(event) => setSkuForm({ ...skuForm, sale_status: event.target.value as Sku['sale_status'] })}><option value="saleable">{t('saleable')}</option><option value="temporarily_unavailable">{t('temporarilyUnavailable')}</option><option value="not_for_sale">{t('notForSale')}</option></select></Field>
            <Field label={t('status')} error={errors.status?.[0]}><select value={skuForm.status} onChange={(event) => setSkuForm({ ...skuForm, status: event.target.value as 'active' | 'inactive' })}><option value="active">{t('active')}</option><option value="inactive">{t('inactive')}</option></select></Field>
            <Field label={t('trackingOptions')} wide><div className="check-grid"><label><input type="checkbox" checked={skuForm.track_lot} onChange={(event) => setSkuForm({ ...skuForm, track_lot: event.target.checked })} />{t('lotTracking')}</label><label><input type="checkbox" checked={skuForm.track_expiry} onChange={(event) => setSkuForm({ ...skuForm, track_expiry: event.target.checked })} />{t('expiryTracking')}</label><label><input type="checkbox" checked={skuForm.is_returnable} onChange={(event) => setSkuForm({ ...skuForm, is_returnable: event.target.checked })} />{t('returnable')}</label></div></Field>
        </Dialog>}
        {dialog === 'price' && selected && <Dialog title={`${t('setPrice')}: ${localized(selected.name)}`} close={closeDialog} submitting={submitting} onSubmit={submitPrice} action={t('savePrice')} compact>
            {errors.form && <p className="form-error form-error--wide" role="alert">{errors.form[0]}</p>}
            <Field label={t('priceBook')} error={errors.price_book_public_id?.[0]} wide><select ref={(node) => { firstInput.current = node; }} value={priceForm.price_book_public_id} onChange={(event) => setPriceForm({ ...priceForm, price_book_public_id: event.target.value })} required>{options.price_books.map((book) => <option value={book.public_id} key={book.public_id}>{book.code} · {locale === 'my-MM' ? book.name_my || book.name_en : book.name_en}</option>)}</select></Field>
            <Field label={t('sellingUnit')} error={errors.uom_public_id?.[0]}><select value={priceForm.uom_public_id} onChange={(event) => setPriceForm({ ...priceForm, uom_public_id: event.target.value })}>{selected.conversions.map((conversion) => <option value={conversion.uom.id} key={conversion.id}>{conversion.uom.code} · ×{conversion.factor_to_base}</option>)}</select></Field>
            <Field label={t('unitPriceMmk')} error={errors.unit_price_minor?.[0]}><input type="number" min="0" step="1" value={priceForm.unit_price_minor} onChange={(event) => setPriceForm({ ...priceForm, unit_price_minor: event.target.value })} required /></Field>
            <Field label={t('minimumQuantity')} error={errors.minimum_quantity?.[0]}><input type="number" min="0.001" step="0.001" value={priceForm.minimum_quantity} onChange={(event) => setPriceForm({ ...priceForm, minimum_quantity: event.target.value })} required /></Field>
            <Field label={t('effectiveFrom')} error={errors.effective_from?.[0]}><input type="date" value={priceForm.effective_from} onChange={(event) => setPriceForm({ ...priceForm, effective_from: event.target.value })} required /></Field>
            <Field label={t('effectiveTo')} error={errors.effective_to?.[0]}><input type="date" value={priceForm.effective_to} onChange={(event) => setPriceForm({ ...priceForm, effective_to: event.target.value })} /></Field>
            <p className="form-note form-field--wide">{t('specialPriceApprovalNote')}</p>
        </Dialog>}
        {dialog === 'conversion' && selected && <Dialog title={`${t('configureConversion')}: ${localized(selected.name)}`} close={closeDialog} submitting={submitting} onSubmit={submitConversion} action={t('saveConversion')} compact>
            {errors.form && <p className="form-error form-error--wide" role="alert">{errors.form[0]}</p>}
            <Field label={t('sellingUnit')} error={errors.uom_public_id?.[0]}><select ref={(node) => { firstInput.current = node; }} value={conversionForm.uom_public_id} onChange={(event) => setConversionForm({ ...conversionForm, uom_public_id: event.target.value })} required>{options.units.map((unit) => <option value={unit.public_id} key={unit.public_id}>{unit.code} · {unit.symbol}</option>)}</select></Field>
            <Field label={t('factorToBase')} error={errors.factor_to_base?.[0]}><input type="number" min="0.000001" step="0.000001" value={conversionForm.factor_to_base} onChange={(event) => setConversionForm({ ...conversionForm, factor_to_base: event.target.value })} required /></Field>
            <Field label={t('effectiveFrom')} error={errors.effective_from?.[0]}><input type="date" value={conversionForm.effective_from} onChange={(event) => setConversionForm({ ...conversionForm, effective_from: event.target.value })} required /></Field>
            <Field label={t('conversionFlags')}><div className="check-grid"><label><input type="checkbox" checked={conversionForm.is_selling_unit} onChange={(event) => setConversionForm({ ...conversionForm, is_selling_unit: event.target.checked })} />{t('sellingUnit')}</label><label><input type="checkbox" checked={conversionForm.is_kpi_base} onChange={(event) => setConversionForm({ ...conversionForm, is_kpi_base: event.target.checked })} />{t('kpiBaseUnit')}</label></div></Field>
            <p className="form-note form-field--wide">{t('conversionVersionNote')}</p>
        </Dialog>}
        {dialog === 'archive' && selected && <Dialog title={`${t('archiveSku')}: ${localized(selected.name)}`} close={closeDialog} submitting={submitting} onSubmit={submitArchive} action={t('archive')} danger compact>
            {errors.form && <p className="form-error form-error--wide" role="alert">{errors.form[0]}</p>}
            <p className="warning-copy form-field--wide">{t('archiveSkuWarning')}</p>
            <Field label={t('archiveReason')} error={errors.reason?.[0]} wide><textarea ref={(node) => { firstInput.current = node; }} rows={4} value={archiveReason} onChange={(event) => setArchiveReason(event.target.value)} required minLength={3} /></Field>
        </Dialog>}
    </>;
}

function Field({ label, error, wide, children }: { label: string; error?: string; wide?: boolean; children: ReactNode }) {
    return <label className={wide ? 'form-field form-field--wide' : 'form-field'}><span>{label}</span>{children}{error && <small className="form-error">{error}</small>}</label>;
}
function Dialog({ title, close, submitting, onSubmit, action, compact, danger, children }: { title: string; close: () => void; submitting: boolean; onSubmit: (event: FormEvent) => void; action: string; compact?: boolean; danger?: boolean; children: ReactNode }) {
    const { t } = useI18n();
    return <div className="modal-backdrop" role="presentation"><section className={`dialog ${compact ? 'dialog--compact' : ''}`} role="dialog" aria-modal="true" aria-label={title}><header className="dialog-header"><h2>{title}</h2><button className="icon-button" type="button" onClick={close} aria-label={t('closeDialog')}><Icon name="close" /></button></header><form onSubmit={onSubmit}><div className="dialog-body form-grid">{children}</div><footer className="dialog-footer"><button className="button button--secondary" type="button" onClick={close} disabled={submitting}>{t('cancel')}</button><button className={`button ${danger ? 'button--danger' : 'button--primary'}`} type="submit" disabled={submitting}>{submitting ? t('saving') : action}</button></footer></form></section></div>;
}
function Status({ value, label }: { value: string; label: string }) {
    const semantic = value === 'active' ? 'success' : value === 'inactive' ? 'warning' : 'neutral';
    return <span className={`status-badge status-badge--${semantic}`}><span />{label}</span>;
}
