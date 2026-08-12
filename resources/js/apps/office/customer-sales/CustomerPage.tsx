import { useCallback, useEffect, useState, type FormEvent, type ReactNode } from 'react';
import type { AxiosError } from 'axios';
import { apiClient } from '../../../packages/api-client/client';
import { Icon } from '../../../packages/design-system/Icon';
import { useI18n } from '../../../packages/i18n/I18nProvider';

interface Name { en: string; 'my-MM': string | null }
interface Reference { id: string; code: string; name: Name }
interface Option { public_id?: string; id?: string; code: string; name_en: string; name_my: string | null; area_id?: string }
interface Customer {
    id: string; code: string; name: Name; category: string | null; preferred_language: 'en' | 'my-MM'; acquisition_source: string | null;
    lifecycle_status: 'prospect' | 'pending_verification' | 'active' | 'suspended' | 'closed'; settlement_policy: 'COD_CASH' | 'APPROVED_CREDIT'; version: number;
    primary_outlet: { id: string; code: string; name: Name; status: string }; primary_contact: { id: string; name: string; phone: string; email: string | null };
    primary_address: { id: string; area: Reference; township: string | null; ward_village: string | null; street_address: string; landmark: string | null; latitude: string | null; longitude: string | null };
    way_membership: { id: string; way: Reference; effective_from: string; effective_to: string | null; status: string };
    price_book: Reference | null;
}
interface Options { areas: Option[]; ways: Option[]; price_books: Option[]; sales_profiles: Option[] }
interface PageResponse { data: Customer[]; meta: { current_page: number; last_page: number; total: number } }
interface ApiError { message?: string; code?: string; errors?: Record<string, string[]> }

const today = new Date().toISOString().slice(0, 10);
const blank = {
    code: '', name_en: '', name_my: '', category: '', preferred_language: 'my-MM', acquisition_source: '', lifecycle_status: 'pending_verification', price_book_id: '', acquiring_sales_profile_id: '',
    outlet_code: '', outlet_name_en: '', outlet_name_my: '', contact_name: '', phone: '', email: '', area_id: '', township: '', ward_village: '', street_address: '', landmark: '', latitude: '', longitude: '',
    way_id: '', way_effective_from: today, change_reason: '', version: undefined as number | undefined,
};

export function CustomerPage() {
    const { locale, t } = useI18n();
    const [customers, setCustomers] = useState<Customer[]>([]);
    const [options, setOptions] = useState<Options>({ areas: [], ways: [], price_books: [], sales_profiles: [] });
    const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
    const [page, setPage] = useState(1); const [searchDraft, setSearchDraft] = useState(''); const [search, setSearch] = useState(''); const [status, setStatus] = useState(''); const [way, setWay] = useState('');
    const [loading, setLoading] = useState(true); const [loadError, setLoadError] = useState(false); const [refreshKey, setRefreshKey] = useState(0);
    const [dialog, setDialog] = useState<'form' | 'archive' | null>(null); const [selected, setSelected] = useState<Customer | null>(null); const [form, setForm] = useState(blank);
    const [archiveReason, setArchiveReason] = useState(''); const [errors, setErrors] = useState<Record<string, string[]>>({}); const [submitting, setSubmitting] = useState(false); const [flash, setFlash] = useState('');

    const load = useCallback(async (signal?: AbortSignal) => {
        setLoading(true); setLoadError(false);
        try {
            const [records, refs] = await Promise.all([
                apiClient.get<PageResponse>('/customer-sales/customers', { params: { page, per_page: 20, search: search || undefined, status: status || undefined, way: way || undefined }, signal }),
                apiClient.get<{ data: Options }>('/customer-sales/customers/options', { signal }),
            ]);
            setCustomers(records.data.data); setMeta(records.data.meta); setOptions(refs.data.data);
        } catch { if (!signal?.aborted) setLoadError(true); }
        finally { if (!signal?.aborted) setLoading(false); }
    }, [page, search, status, way]);
    useEffect(() => { const controller = new AbortController(); void load(controller.signal); return () => controller.abort(); }, [load, refreshKey]);

    const localName = (name: Name) => name[locale] || name.en;
    const optionName = (item: Option) => locale === 'my-MM' ? item.name_my || item.name_en : item.name_en;
    const optionId = (item: Option) => item.public_id || item.id || '';
    const eligibleWays = options.ways.filter((item) => !form.area_id || item.area_id === form.area_id);
    const close = () => { if (!submitting) { setDialog(null); setErrors({}); } };
    const beginCreate = () => {
        const area = options.areas[0]; const areaId = area ? optionId(area) : ''; const firstWay = options.ways.find((item) => item.area_id === areaId);
        setSelected(null); setErrors({}); setForm({ ...blank, area_id: areaId, way_id: firstWay ? optionId(firstWay) : '', price_book_id: options.price_books[0] ? optionId(options.price_books[0]) : '' }); setDialog('form');
    };
    const beginEdit = (customer: Customer) => {
        setSelected(customer); setErrors({}); setForm({
            ...blank, code: customer.code, name_en: customer.name.en, name_my: customer.name['my-MM'] || '', category: customer.category || '', preferred_language: customer.preferred_language,
            acquisition_source: customer.acquisition_source || '', lifecycle_status: customer.lifecycle_status === 'closed' ? 'suspended' : customer.lifecycle_status, price_book_id: customer.price_book?.id || '',
            outlet_code: customer.primary_outlet.code, outlet_name_en: customer.primary_outlet.name.en, outlet_name_my: customer.primary_outlet.name['my-MM'] || '', contact_name: customer.primary_contact.name,
            phone: customer.primary_contact.phone, email: customer.primary_contact.email || '', area_id: customer.primary_address.area.id, township: customer.primary_address.township || '', ward_village: customer.primary_address.ward_village || '',
            street_address: customer.primary_address.street_address, landmark: customer.primary_address.landmark || '', latitude: customer.primary_address.latitude || '', longitude: customer.primary_address.longitude || '',
            way_id: customer.way_membership.way.id, way_effective_from: customer.way_membership.effective_from, change_reason: '', version: customer.version,
        }); setDialog('form');
    };
    function handleError(error: unknown) {
        const response = (error as AxiosError<ApiError>).response;
        const message = response?.data.code === 'outlet_way_area_mismatch' ? t('customerWayAreaMismatch') : response?.status === 409 ? t('customerConflict') : response?.data.message || t('errorLoadingCustomers');
        setErrors(response?.data.errors || { form: [message] });
    }
    async function submit(event: FormEvent) {
        event.preventDefault(); setSubmitting(true); setErrors({});
        const payload = {
            code: form.code, name_en: form.name_en, name_my: form.name_my || null, legal_name: null, searchable_alias: null, category: form.category || null, preferred_language: form.preferred_language,
            acquisition_source: form.acquisition_source || null, lifecycle_status: form.lifecycle_status, price_book_id: form.price_book_id || null, acquiring_sales_profile_id: form.acquiring_sales_profile_id || null,
            outlet: { code: form.outlet_code, name_en: form.outlet_name_en, name_my: form.outlet_name_my || null }, contact: { name: form.contact_name, phone: form.phone, email: form.email || null },
            address: { area_id: form.area_id, label: 'Primary delivery', township: form.township || null, ward_village: form.ward_village || null, street_address: form.street_address, landmark: form.landmark || null, delivery_note: null, latitude: form.latitude || null, longitude: form.longitude || null, service_window_start: null, service_window_end: null },
            way_id: form.way_id, way_effective_from: form.way_effective_from, change_reason: selected ? form.change_reason : null, version: form.version,
        };
        try { if (selected) await apiClient.put(`/customer-sales/customers/${selected.id}`, payload); else await apiClient.post('/customer-sales/customers', payload); setFlash(selected ? t('customerUpdated') : t('customerCreated')); setDialog(null); setRefreshKey((value) => value + 1); }
        catch (error) { handleError(error); } finally { setSubmitting(false); }
    }
    async function archive(event: FormEvent) {
        event.preventDefault(); if (!selected) return; setSubmitting(true); setErrors({});
        try { await apiClient.patch(`/customer-sales/customers/${selected.id}/archive`, { version: selected.version, reason: archiveReason }); setFlash(t('customerClosed')); setDialog(null); setRefreshKey((value) => value + 1); }
        catch (error) { handleError(error); } finally { setSubmitting(false); }
    }

    return <>
        <section className="page-heading"><div><p className="eyebrow">{t('phaseTwo')}</p><h1>{t('customerRegister')}</h1><p>{t('customerRegisterDescription')}</p></div><button className="button button--primary" type="button" onClick={beginCreate}><Icon name="plus" size={16} />{t('addCustomer')}</button></section>
        {flash && <div className="flash-message flash-message--success" role="status"><span className="status-dot status-dot--success" />{flash}</div>}
        <article className="panel master-register">
            <div className="panel-heading"><div><p className="eyebrow">{t('customerAndTerritory')}</p><h2>{t('customerRegister')}</h2></div><span className="status-badge status-badge--info"><span />{t('totalRecords')}: {meta.total}</span></div>
            <form className="filter-toolbar" onSubmit={(event) => { event.preventDefault(); setPage(1); setSearch(searchDraft.trim()); }}>
                <label className="search-field"><span className="sr-only">{t('searchCustomers')}</span><Icon name="activity" size={15} /><input value={searchDraft} onChange={(event) => setSearchDraft(event.target.value)} placeholder={t('searchCustomers')} /></label>
                <label><span className="sr-only">{t('way')}</span><select value={way} onChange={(event) => { setWay(event.target.value); setPage(1); }}><option value="">{t('allWays')}</option>{options.ways.map((item) => <option key={optionId(item)} value={optionId(item)}>{item.code} · {optionName(item)}</option>)}</select></label>
                <label><span className="sr-only">{t('status')}</span><select value={status} onChange={(event) => { setStatus(event.target.value); setPage(1); }}><option value="">{t('allLifecycleStatuses')}</option>{['prospect', 'pending_verification', 'active', 'suspended', 'closed'].map((value) => <option key={value} value={value}>{t(value as 'prospect')}</option>)}</select></label>
                <button className="button button--secondary" type="submit">{t('search')}</button><button className="icon-button" type="button" onClick={() => setRefreshKey((value) => value + 1)} aria-label={t('refresh')}><Icon name="refresh" size={16} /></button>
            </form>
            {loadError ? <div className="empty-state register-state" role="alert"><strong>{t('errorLoadingCustomers')}</strong><button className="button button--secondary" onClick={() => setRefreshKey((value) => value + 1)}>{t('retry')}</button></div> :
            <div className="table-region master-table" tabIndex={0} aria-busy={loading}><table><thead><tr><th>{t('customerAccount')}</th><th>{t('primaryShop')}</th><th>{t('orderingContact')}</th><th>{t('territory')}</th><th>{t('settlementPolicy')}</th><th>{t('lifecycleStatus')}</th><th className="actions-column">{t('actions')}</th></tr></thead><tbody>
                {loading && Array.from({ length: 5 }, (_, row) => <tr className="skeleton-row" key={row}>{Array.from({ length: 7 }, (__, cell) => <td key={cell}><span /></td>)}</tr>)}
                {!loading && !customers.length && <tr><td colSpan={7} className="table-empty">{t('noCustomers')}</td></tr>}
                {!loading && customers.map((customer) => <tr key={customer.id}><td className="identity-cell"><strong>{customer.code}</strong><small>{localName(customer.name)}</small></td><td><strong>{localName(customer.primary_outlet.name)}</strong><small>{customer.primary_outlet.code}</small></td><td><strong>{customer.primary_contact.name}</strong><small>{customer.primary_contact.phone}</small></td><td><strong>{localName(customer.way_membership.way.name)}</strong><small>{customer.primary_address.area.code} · {customer.way_membership.effective_from}</small></td><td><span className="status-badge status-badge--info"><span />{customer.settlement_policy === 'COD_CASH' ? t('codCash') : t('approvedCredit')}</span></td><td><Lifecycle value={customer.lifecycle_status} label={t(customer.lifecycle_status)} /></td><td className="actions-column"><div className="row-actions"><button className="icon-button icon-button--small" onClick={() => beginEdit(customer)} aria-label={`${t('edit')} ${localName(customer.name)}`}><Icon name="edit" size={15} /></button>{customer.lifecycle_status !== 'closed' && <button className="icon-button icon-button--small icon-button--danger" onClick={() => { setSelected(customer); setArchiveReason(''); setErrors({}); setDialog('archive'); }} aria-label={`${t('closeCustomer')} ${localName(customer.name)}`}><Icon name="archive" size={15} /></button>}</div></td></tr>)}</tbody></table></div>}
            <div className="pagination-bar"><span>{t('totalRecords')}: {meta.total}</span><div><button disabled={page <= 1 || loading} onClick={() => setPage((value) => value - 1)}>{t('previous')}</button><strong>{meta.current_page} / {meta.last_page}</strong><button disabled={page >= meta.last_page || loading} onClick={() => setPage((value) => value + 1)}>{t('next')}</button></div></div>
        </article>

        {dialog === 'form' && <div className="modal-backdrop"><section className="dialog" role="dialog" aria-modal="true" aria-label={selected ? t('editCustomer') : t('addCustomer')}><header className="dialog-header"><h2>{selected ? t('editCustomer') : t('addCustomer')}</h2><button className="icon-button" type="button" onClick={close} aria-label={t('closeDialog')}><Icon name="close" /></button></header><form onSubmit={submit}><div className="dialog-body form-grid">
            {errors.form && <p className="form-error form-error--wide" role="alert">{errors.form[0]}</p>}
            <Section>{t('customerAccount')}</Section><Field label={t('code')} error={errors.code?.[0]}><input value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value.toUpperCase() })} required /></Field><Field label={t('englishName')} error={errors.name_en?.[0]}><input value={form.name_en} onChange={(e) => setForm({ ...form, name_en: e.target.value })} required /></Field><Field label={t('myanmarName')}><input lang="my" value={form.name_my} onChange={(e) => setForm({ ...form, name_my: e.target.value })} /></Field><Field label={t('customerCategory')}><input value={form.category} onChange={(e) => setForm({ ...form, category: e.target.value })} /></Field><Field label={t('preferredLanguage')}><select value={form.preferred_language} onChange={(e) => setForm({ ...form, preferred_language: e.target.value })}><option value="my-MM">မြန်မာ</option><option value="en">English</option></select></Field><Field label={t('lifecycleStatus')}><select value={form.lifecycle_status} onChange={(e) => setForm({ ...form, lifecycle_status: e.target.value })}>{['prospect', 'pending_verification', 'active', 'suspended'].map((value) => <option key={value} value={value}>{t(value as 'prospect')}</option>)}</select></Field>
            <Section>{t('primaryShop')}</Section><Field label={t('shopCode')} error={errors['outlet.code']?.[0]}><input value={form.outlet_code} onChange={(e) => setForm({ ...form, outlet_code: e.target.value.toUpperCase() })} required /></Field><Field label={t('shopName')} error={errors['outlet.name_en']?.[0]}><input value={form.outlet_name_en} onChange={(e) => setForm({ ...form, outlet_name_en: e.target.value })} required /></Field><Field label={t('contactName')} error={errors['contact.name']?.[0]}><input value={form.contact_name} onChange={(e) => setForm({ ...form, contact_name: e.target.value })} required /></Field><Field label={t('phone')} error={errors['contact.phone']?.[0]}><input inputMode="tel" value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} required /></Field>
            <Section>{t('deliveryAddress')}</Section><Field label={t('area')} error={errors['address.area_id']?.[0]}><select value={form.area_id} onChange={(e) => { const areaId = e.target.value; const firstWay = options.ways.find((item) => item.area_id === areaId); setForm({ ...form, area_id: areaId, way_id: firstWay ? optionId(firstWay) : '' }); }}>{options.areas.map((item) => <option key={optionId(item)} value={optionId(item)}>{item.code} · {optionName(item)}</option>)}</select></Field><Field label={t('way')} error={errors.way_id?.[0]}><select value={form.way_id} onChange={(e) => setForm({ ...form, way_id: e.target.value })}>{eligibleWays.map((item) => <option key={optionId(item)} value={optionId(item)}>{item.code} · {optionName(item)}</option>)}</select></Field><Field label={t('township')}><input value={form.township} onChange={(e) => setForm({ ...form, township: e.target.value })} /></Field><Field label={t('wardVillage')}><input value={form.ward_village} onChange={(e) => setForm({ ...form, ward_village: e.target.value })} /></Field><Field label={t('streetAddress')} error={errors['address.street_address']?.[0]} wide><textarea rows={2} value={form.street_address} onChange={(e) => setForm({ ...form, street_address: e.target.value })} required /></Field><Field label={t('landmark')}><input value={form.landmark} onChange={(e) => setForm({ ...form, landmark: e.target.value })} /></Field><Field label={t('wayEffectiveFrom')}><input type="date" value={form.way_effective_from} onChange={(e) => setForm({ ...form, way_effective_from: e.target.value })} required /></Field>{selected && <Field label={t('changeReason')} error={errors.change_reason?.[0]} wide><textarea rows={2} value={form.change_reason} onChange={(e) => setForm({ ...form, change_reason: e.target.value })} required /></Field>}
            <p className="warning-copy form-field--wide">{t('codFoundationNotice')}</p>
        </div><footer className="dialog-footer"><button className="button button--secondary" type="button" onClick={close}>{t('cancel')}</button><button className="button button--primary" type="submit" disabled={submitting}>{submitting ? t('saving') : t('save')}</button></footer></form></section></div>}
        {dialog === 'archive' && selected && <div className="modal-backdrop"><section className="dialog dialog--compact" role="dialog" aria-modal="true" aria-label={t('closeCustomer')}><header className="dialog-header"><h2>{t('closeCustomer')}: {localName(selected.name)}</h2><button className="icon-button" onClick={close} aria-label={t('closeDialog')}><Icon name="close" /></button></header><form onSubmit={archive}><div className="dialog-body"><p className="warning-copy">{t('closeCustomerWarning')}</p>{errors.form && <p className="form-error">{errors.form[0]}</p>}<Field label={t('archiveReason')} error={errors.reason?.[0]} wide><textarea rows={4} value={archiveReason} onChange={(e) => setArchiveReason(e.target.value)} minLength={10} required /></Field></div><footer className="dialog-footer"><button className="button button--secondary" type="button" onClick={close}>{t('cancel')}</button><button className="button button--danger" type="submit" disabled={submitting}>{t('closeCustomer')}</button></footer></form></section></div>}
    </>;
}

function Section({ children }: { children: ReactNode }) { return <p className="eyebrow form-field--wide">{children}</p>; }
function Field({ label, error, wide, children }: { label: string; error?: string; wide?: boolean; children: ReactNode }) { return <label className={wide ? 'form-field form-field--wide' : 'form-field'}><span>{label}</span>{children}{error && <small className="form-error">{error}</small>}</label>; }
function Lifecycle({ value, label }: { value: Customer['lifecycle_status']; label: string }) { const semantic = value === 'active' ? 'success' : value === 'pending_verification' || value === 'prospect' ? 'warning' : 'neutral'; return <span className={`status-badge status-badge--${semantic}`}><span />{label}</span>; }
