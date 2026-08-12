import { useCallback, useEffect, useRef, useState, type FormEvent, type ReactNode } from 'react';
import type { AxiosError } from 'axios';
import { apiClient } from '../../../packages/api-client/client';
import { Icon } from '../../../packages/design-system/Icon';
import { useI18n } from '../../../packages/i18n/I18nProvider';
import { MasterDataNavigation } from './MasterDataNavigation';

type RecordStatus = 'active' | 'inactive' | 'archived';
type Register = 'branches' | 'warehouses';
interface Name { en: string; 'my-MM': string | null }
interface Reference { id: string; code: string; name: Name }
interface OptionReference { public_id: string; code: string; name_en: string; name_my: string | null }
interface Branch { id: string; code: string; name: Name; phone: string | null; address: string | null; timezone: string; currency: string; business_day_start: string; status: RecordStatus; version: number; warehouses_count: number }
interface Warehouse { id: string; code: string; name: Name; branch: Reference; area: Reference | null; kind: string; address: string | null; contact_name: string | null; phone: string | null; map_position: { latitude: string; longitude: string } | null; order_cutoff_time: string | null; service_area_note: string | null; status: RecordStatus; version: number }
interface PageResponse<T> { data: T[]; meta: { total: number } }
interface Options { branches: OptionReference[]; areas: OptionReference[] }
interface ApiError { message?: string; code?: string; errors?: Record<string, string[]> }

const emptyBranch = { code: '', name_en: '', name_my: '', phone: '', address: '', timezone: 'Asia/Yangon', currency: 'MMK', business_day_start: '06:00', status: 'active', version: undefined as number | undefined };
const emptyWarehouse = { branch_public_id: '', area_public_id: '', code: '', name_en: '', name_my: '', kind: 'distribution', address: '', contact_name: '', phone: '', latitude: '', longitude: '', order_cutoff_time: '', service_area_note: '', status: 'active', version: undefined as number | undefined };

export function LocationPage() {
    const { locale, t } = useI18n();
    const [register, setRegister] = useState<Register>('branches');
    const [branches, setBranches] = useState<Branch[]>([]);
    const [warehouses, setWarehouses] = useState<Warehouse[]>([]);
    const [options, setOptions] = useState<Options>({ branches: [], areas: [] });
    const [searchDraft, setSearchDraft] = useState('');
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('');
    const [branchFilter, setBranchFilter] = useState('');
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState(false);
    const [refreshKey, setRefreshKey] = useState(0);
    const [dialog, setDialog] = useState<'form' | 'archive' | null>(null);
    const [selected, setSelected] = useState<Branch | Warehouse | null>(null);
    const [branchForm, setBranchForm] = useState(emptyBranch);
    const [warehouseForm, setWarehouseForm] = useState(emptyWarehouse);
    const [archiveReason, setArchiveReason] = useState('');
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [submitting, setSubmitting] = useState(false);
    const [flash, setFlash] = useState<string | null>(null);
    const firstInput = useRef<HTMLInputElement | HTMLTextAreaElement | null>(null);
    const trigger = useRef<HTMLElement | null>(null);

    const load = useCallback(async (signal?: AbortSignal) => {
        setLoading(true); setLoadError(false);
        try {
            const [branchResponse, warehouseResponse, optionResponse] = await Promise.all([
                apiClient.get<PageResponse<Branch>>('/master-data/branches', { params: { per_page: 100, search: register === 'branches' ? search || undefined : undefined, status: status || undefined }, signal }),
                apiClient.get<PageResponse<Warehouse>>('/master-data/warehouses', { params: { per_page: 100, search: register === 'warehouses' ? search || undefined : undefined, status: status || undefined, branch: branchFilter || undefined }, signal }),
                apiClient.get<{ data: Options }>('/master-data/locations/options', { signal }),
            ]);
            setBranches(branchResponse.data.data); setWarehouses(warehouseResponse.data.data); setOptions(optionResponse.data.data);
        } catch { if (!signal?.aborted) setLoadError(true); }
        finally { if (!signal?.aborted) setLoading(false); }
    }, [register, search, status, branchFilter]);

    useEffect(() => { const controller = new AbortController(); void load(controller.signal); return () => controller.abort(); }, [load, refreshKey]);
    useEffect(() => {
        if (!dialog) return;
        const escape = (event: KeyboardEvent) => { if (event.key === 'Escape') closeDialog(); };
        document.addEventListener('keydown', escape); window.setTimeout(() => firstInput.current?.focus(), 0);
        return () => document.removeEventListener('keydown', escape);
    }, [dialog]);

    const localized = (name: Name) => name[locale] || name.en;
    const optionName = (item: OptionReference) => locale === 'my-MM' ? item.name_my || item.name_en : item.name_en;
    const closeDialog = () => { if (submitting) return; setDialog(null); setErrors({}); window.setTimeout(() => trigger.current?.focus(), 0); };
    const changeRegister = (next: Register) => { setRegister(next); setSearchDraft(''); setSearch(''); setStatus(''); setBranchFilter(''); setFlash(null); };
    const beginCreate = (event: React.MouseEvent<HTMLElement>) => {
        trigger.current = event.currentTarget; setSelected(null); setErrors({});
        if (register === 'branches') setBranchForm(emptyBranch);
        else setWarehouseForm({ ...emptyWarehouse, branch_public_id: options.branches[0]?.public_id ?? '', area_public_id: options.areas[0]?.public_id ?? '' });
        setDialog('form');
    };
    const beginEdit = (record: Branch | Warehouse, event: React.MouseEvent<HTMLElement>) => {
        trigger.current = event.currentTarget; setSelected(record); setErrors({});
        if (register === 'branches') {
            const item = record as Branch;
            setBranchForm({ code: item.code, name_en: item.name.en, name_my: item.name['my-MM'] ?? '', phone: item.phone ?? '', address: item.address ?? '', timezone: item.timezone, currency: item.currency, business_day_start: item.business_day_start, status: item.status === 'inactive' ? 'inactive' : 'active', version: item.version });
        } else {
            const item = record as Warehouse;
            setWarehouseForm({ branch_public_id: item.branch.id, area_public_id: item.area?.id ?? '', code: item.code, name_en: item.name.en, name_my: item.name['my-MM'] ?? '', kind: item.kind, address: item.address ?? '', contact_name: item.contact_name ?? '', phone: item.phone ?? '', latitude: item.map_position?.latitude ?? '', longitude: item.map_position?.longitude ?? '', order_cutoff_time: item.order_cutoff_time ?? '', service_area_note: item.service_area_note ?? '', status: item.status === 'inactive' ? 'inactive' : 'active', version: item.version });
        }
        setDialog('form');
    };
    const beginArchive = (record: Branch | Warehouse, event: React.MouseEvent<HTMLElement>) => { trigger.current = event.currentTarget; setSelected(record); setArchiveReason(''); setErrors({}); setDialog('archive'); };
    function handleError(error: unknown) {
        const response = (error as AxiosError<ApiError>).response;
        const conflict = response?.data.code === 'branch_has_warehouses' ? t('branchHasWarehouses') : response?.data.code === 'warehouse_has_active_ways' ? t('warehouseHasActiveWays') : t('locationConflict');
        setErrors(response?.data.errors ?? { form: [response?.status === 409 ? conflict : response?.data.message ?? t('errorLoadingLocations')] });
    }
    async function submitForm(event: FormEvent) {
        event.preventDefault(); setSubmitting(true); setErrors({});
        const endpoint = register === 'branches' ? 'branches' : 'warehouses';
        const payload = register === 'branches' ? { ...branchForm, name_my: branchForm.name_my || null, phone: branchForm.phone || null, address: branchForm.address || null }
            : { ...warehouseForm, name_my: warehouseForm.name_my || null, area_public_id: warehouseForm.area_public_id || null, address: warehouseForm.address || null, contact_name: warehouseForm.contact_name || null, phone: warehouseForm.phone || null, latitude: warehouseForm.latitude || null, longitude: warehouseForm.longitude || null, order_cutoff_time: warehouseForm.order_cutoff_time || null, service_area_note: warehouseForm.service_area_note || null };
        try {
            if (selected) await apiClient.put(`/master-data/${endpoint}/${selected.id}`, payload); else await apiClient.post(`/master-data/${endpoint}`, payload);
            setFlash(register === 'branches' ? (selected ? t('branchUpdated') : t('branchCreated')) : (selected ? t('warehouseUpdated') : t('warehouseCreated'))); setDialog(null); setRefreshKey((value) => value + 1);
        } catch (error) { handleError(error); } finally { setSubmitting(false); }
    }
    async function submitArchive(event: FormEvent) {
        event.preventDefault(); if (!selected) return; setSubmitting(true); setErrors({});
        try { await apiClient.patch(`/master-data/${register}/${selected.id}/archive`, { version: selected.version, reason: archiveReason }); setFlash(register === 'branches' ? t('branchArchived') : t('warehouseArchived')); setDialog(null); setRefreshKey((value) => value + 1); }
        catch (error) { handleError(error); } finally { setSubmitting(false); }
    }

    const records = register === 'branches' ? branches : warehouses;
    return <>
        <section className="page-heading"><div><p className="eyebrow">{t('phaseOne')}</p><h1>{t('locationRegister')}</h1><p>{t('locationDescription')}</p></div><button className="button button--primary" type="button" onClick={beginCreate}><Icon name="plus" size={16} />{register === 'branches' ? t('addBranch') : t('addWarehouse')}</button></section>
        <MasterDataNavigation />
        <nav className="location-register-tabs" aria-label={t('locationRegisters')}><button className={register === 'branches' ? 'is-active' : ''} onClick={() => changeRegister('branches')}>{t('branches')}</button><button className={register === 'warehouses' ? 'is-active' : ''} onClick={() => changeRegister('warehouses')}>{t('warehouses')}</button></nav>
        {flash && <div className="flash-message flash-message--success" role="status"><span className="status-dot status-dot--success" />{flash}</div>}
        <article className="panel master-register">
            <div className="panel-heading"><div><p className="eyebrow">{t('operationalLocations')}</p><h2>{register === 'branches' ? t('branchRegister') : t('warehouseRegister')}</h2></div><span className="status-badge status-badge--info"><span />{t('totalRecords')}: {records.length}</span></div>
            <form className="filter-toolbar location-filters" onSubmit={(event) => { event.preventDefault(); setSearch(searchDraft.trim()); }}><label className="search-field"><span className="sr-only">{t('searchLocations')}</span><Icon name="activity" size={15} /><input value={searchDraft} onChange={(event) => setSearchDraft(event.target.value)} placeholder={t('searchLocations')} /></label>{register === 'warehouses' && <label><span className="sr-only">{t('branch')}</span><select value={branchFilter} onChange={(event) => setBranchFilter(event.target.value)}><option value="">{t('allBranches')}</option>{options.branches.map((item) => <option key={item.public_id} value={item.public_id}>{optionName(item)}</option>)}</select></label>}<label><span className="sr-only">{t('status')}</span><select value={status} onChange={(event) => setStatus(event.target.value)}><option value="">{t('allStatuses')}</option><option value="active">{t('active')}</option><option value="inactive">{t('inactive')}</option><option value="archived">{t('archived')}</option></select></label><button className="button button--secondary" type="submit">{t('search')}</button><button className="icon-button" type="button" onClick={() => setRefreshKey((value) => value + 1)} aria-label={t('refresh')}><Icon name="refresh" size={16} /></button></form>
            {loadError ? <div className="empty-state register-state" role="alert"><strong>{t('errorLoadingLocations')}</strong><button className="button button--secondary" onClick={() => setRefreshKey((value) => value + 1)}>{t('retry')}</button></div> : register === 'branches' ? <BranchTable records={branches} loading={loading} localized={localized} edit={beginEdit} archive={beginArchive} /> : <WarehouseTable records={warehouses} loading={loading} localized={localized} edit={beginEdit} archive={beginArchive} />}
        </article>

        {dialog === 'form' && <div className="modal-backdrop" role="presentation"><section className="dialog" role="dialog" aria-modal="true" aria-label={register === 'branches' ? t('branch') : t('warehouse')}><header className="dialog-header"><h2>{selected ? t('edit') : register === 'branches' ? t('addBranch') : t('addWarehouse')}</h2><button className="icon-button" type="button" onClick={closeDialog} aria-label={t('closeDialog')}><Icon name="close" /></button></header><form onSubmit={submitForm}><div className="dialog-body form-grid">{errors.form && <p className="form-error form-error--wide" role="alert">{errors.form[0]}</p>}{register === 'branches' ? <BranchFields form={branchForm} setForm={setBranchForm} errors={errors} firstInput={firstInput} /> : <WarehouseFields form={warehouseForm} setForm={setWarehouseForm} errors={errors} options={options} optionName={optionName} firstInput={firstInput} />}</div><footer className="dialog-footer"><button className="button button--secondary" type="button" onClick={closeDialog} disabled={submitting}>{t('cancel')}</button><button className="button button--primary" type="submit" disabled={submitting}>{submitting ? t('saving') : t('save')}</button></footer></form></section></div>}
        {dialog === 'archive' && selected && <div className="modal-backdrop" role="presentation"><section className="dialog dialog--compact" role="dialog" aria-modal="true" aria-label={t('archive')}><header className="dialog-header"><h2>{t('archive')}: {localized(selected.name)}</h2><button className="icon-button" type="button" onClick={closeDialog} aria-label={t('closeDialog')}><Icon name="close" /></button></header><form onSubmit={submitArchive}><div className="dialog-body"><p className="warning-copy">{register === 'branches' ? t('archiveBranchWarning') : t('archiveWarehouseWarning')}</p>{errors.form && <p className="form-error" role="alert">{errors.form[0]}</p>}<Field label={t('archiveReason')} error={errors.reason?.[0]} wide><textarea ref={firstInput as React.RefObject<HTMLTextAreaElement>} rows={4} value={archiveReason} onChange={(event) => setArchiveReason(event.target.value)} required minLength={3} /></Field></div><footer className="dialog-footer"><button className="button button--secondary" type="button" onClick={closeDialog} disabled={submitting}>{t('cancel')}</button><button className="button button--danger" type="submit" disabled={submitting}>{submitting ? t('saving') : t('archive')}</button></footer></form></section></div>}
    </>;
}

function BranchTable({ records, loading, localized, edit, archive }: { records: Branch[]; loading: boolean; localized: (name: Name) => string; edit: (record: Branch, event: React.MouseEvent<HTMLElement>) => void; archive: (record: Branch, event: React.MouseEvent<HTMLElement>) => void }) { const { t } = useI18n(); return <div className="table-region master-table" tabIndex={0} aria-busy={loading}><table><thead><tr><th>{t('branch')}</th><th>{t('contact')}</th><th>{t('operatingSettings')}</th><th>{t('warehouses')}</th><th>{t('status')}</th><th className="actions-column">{t('actions')}</th></tr></thead><tbody>{loading && <Skeleton columns={6} />}{!loading && !records.length && <tr><td colSpan={6} className="table-empty">{t('noBranches')}</td></tr>}{!loading && records.map((item) => <tr key={item.id}><td className="identity-cell"><strong>{item.code}</strong><small>{localized(item.name)}</small></td><td>{item.phone || '—'}<small>{item.address || '—'}</small></td><td><strong>{item.timezone}</strong><small>{item.currency} · {item.business_day_start}</small></td><td>{item.warehouses_count}</td><td><Status value={item.status} label={t(item.status)} /></td><td className="actions-column"><Actions item={item} edit={edit} archive={archive} label={localized(item.name)} /></td></tr>)}</tbody></table></div>; }
function WarehouseTable({ records, loading, localized, edit, archive }: { records: Warehouse[]; loading: boolean; localized: (name: Name) => string; edit: (record: Warehouse, event: React.MouseEvent<HTMLElement>) => void; archive: (record: Warehouse, event: React.MouseEvent<HTMLElement>) => void }) { const { t } = useI18n(); return <div className="table-region master-table" tabIndex={0} aria-busy={loading}><table><thead><tr><th>{t('warehouse')}</th><th>{t('branch')}</th><th>{t('area')}</th><th>{t('kind')}</th><th>{t('contact')}</th><th>{t('orderCutoff')}</th><th>{t('status')}</th><th className="actions-column">{t('actions')}</th></tr></thead><tbody>{loading && <Skeleton columns={8} />}{!loading && !records.length && <tr><td colSpan={8} className="table-empty">{t('noWarehouses')}</td></tr>}{!loading && records.map((item) => <tr key={item.id}><td className="identity-cell"><strong>{item.code}</strong><small>{localized(item.name)}</small></td><td>{localized(item.branch.name)}<small>{item.branch.code}</small></td><td>{item.area ? localized(item.area.name) : '—'}</td><td>{t(item.kind as 'distribution')}</td><td>{item.contact_name || '—'}<small>{item.phone || '—'}</small></td><td>{item.order_cutoff_time || '—'}</td><td><Status value={item.status} label={t(item.status)} /></td><td className="actions-column"><Actions item={item} edit={edit} archive={archive} label={localized(item.name)} /></td></tr>)}</tbody></table></div>; }
function BranchFields({ form, setForm, errors, firstInput }: { form: typeof emptyBranch; setForm: React.Dispatch<React.SetStateAction<typeof emptyBranch>>; errors: Record<string, string[]>; firstInput: React.RefObject<HTMLInputElement | HTMLTextAreaElement | null> }) { const { t } = useI18n(); return <><Field label={t('code')} error={errors.code?.[0]}><input ref={firstInput as React.RefObject<HTMLInputElement>} value={form.code} onChange={(event) => setForm({ ...form, code: event.target.value.toUpperCase() })} required /></Field><Field label={t('englishName')} error={errors.name_en?.[0]}><input value={form.name_en} onChange={(event) => setForm({ ...form, name_en: event.target.value })} required /></Field><Field label={t('myanmarName')} error={errors.name_my?.[0]}><input lang="my" value={form.name_my} onChange={(event) => setForm({ ...form, name_my: event.target.value })} /></Field><Field label={t('phone')} error={errors.phone?.[0]}><input value={form.phone} onChange={(event) => setForm({ ...form, phone: event.target.value })} /></Field><Field label={t('timezone')} error={errors.timezone?.[0]}><input value={form.timezone} onChange={(event) => setForm({ ...form, timezone: event.target.value })} required /></Field><Field label={t('currency')} error={errors.currency?.[0]}><input maxLength={3} value={form.currency} onChange={(event) => setForm({ ...form, currency: event.target.value.toUpperCase() })} required /></Field><Field label={t('businessDayStart')} error={errors.business_day_start?.[0]}><input type="time" value={form.business_day_start} onChange={(event) => setForm({ ...form, business_day_start: event.target.value })} required /></Field><StatusField value={form.status} change={(status) => setForm({ ...form, status })} /><Field label={t('address')} error={errors.address?.[0]} wide><textarea rows={3} value={form.address} onChange={(event) => setForm({ ...form, address: event.target.value })} /></Field></>; }
function WarehouseFields({ form, setForm, errors, options, optionName, firstInput }: { form: typeof emptyWarehouse; setForm: React.Dispatch<React.SetStateAction<typeof emptyWarehouse>>; errors: Record<string, string[]>; options: Options; optionName: (item: OptionReference) => string; firstInput: React.RefObject<HTMLInputElement | HTMLTextAreaElement | null> }) { const { t } = useI18n(); return <><Field label={t('code')} error={errors.code?.[0]}><input ref={firstInput as React.RefObject<HTMLInputElement>} value={form.code} onChange={(event) => setForm({ ...form, code: event.target.value.toUpperCase() })} required /></Field><Field label={t('branch')} error={errors.branch_public_id?.[0]}><select value={form.branch_public_id} onChange={(event) => setForm({ ...form, branch_public_id: event.target.value })} required>{options.branches.map((item) => <option key={item.public_id} value={item.public_id}>{item.code} · {optionName(item)}</option>)}</select></Field><Field label={t('englishName')} error={errors.name_en?.[0]}><input value={form.name_en} onChange={(event) => setForm({ ...form, name_en: event.target.value })} required /></Field><Field label={t('myanmarName')} error={errors.name_my?.[0]}><input lang="my" value={form.name_my} onChange={(event) => setForm({ ...form, name_my: event.target.value })} /></Field><Field label={t('area')} error={errors.area_public_id?.[0]}><select value={form.area_public_id} onChange={(event) => setForm({ ...form, area_public_id: event.target.value })}><option value="">{t('noArea')}</option>{options.areas.map((item) => <option key={item.public_id} value={item.public_id}>{item.code} · {optionName(item)}</option>)}</select></Field><Field label={t('kind')} error={errors.kind?.[0]}><select value={form.kind} onChange={(event) => setForm({ ...form, kind: event.target.value })}><option value="distribution">{t('distribution')}</option><option value="satellite">{t('satellite')}</option><option value="transit">{t('transit')}</option><option value="returns">{t('returns')}</option></select></Field><Field label={t('contactName')} error={errors.contact_name?.[0]}><input value={form.contact_name} onChange={(event) => setForm({ ...form, contact_name: event.target.value })} /></Field><Field label={t('phone')} error={errors.phone?.[0]}><input value={form.phone} onChange={(event) => setForm({ ...form, phone: event.target.value })} /></Field><Field label={t('latitude')} error={errors.latitude?.[0]}><input type="number" step="0.0000001" value={form.latitude} onChange={(event) => setForm({ ...form, latitude: event.target.value })} /></Field><Field label={t('longitude')} error={errors.longitude?.[0]}><input type="number" step="0.0000001" value={form.longitude} onChange={(event) => setForm({ ...form, longitude: event.target.value })} /></Field><Field label={t('orderCutoff')} error={errors.order_cutoff_time?.[0]}><input type="time" value={form.order_cutoff_time} onChange={(event) => setForm({ ...form, order_cutoff_time: event.target.value })} /></Field><StatusField value={form.status} change={(status) => setForm({ ...form, status })} /><Field label={t('address')} error={errors.address?.[0]} wide><textarea rows={2} value={form.address} onChange={(event) => setForm({ ...form, address: event.target.value })} /></Field><Field label={t('serviceAreaNote')} error={errors.service_area_note?.[0]} wide><textarea rows={2} value={form.service_area_note} onChange={(event) => setForm({ ...form, service_area_note: event.target.value })} /></Field></>; }
function StatusField({ value, change }: { value: string; change: (value: 'active' | 'inactive') => void }) { const { t } = useI18n(); return <Field label={t('status')}><select value={value} onChange={(event) => change(event.target.value as 'active' | 'inactive')}><option value="active">{t('active')}</option><option value="inactive">{t('inactive')}</option></select></Field>; }
function Actions<T extends Branch | Warehouse>({ item, edit, archive, label }: { item: T; edit: (record: T, event: React.MouseEvent<HTMLElement>) => void; archive: (record: T, event: React.MouseEvent<HTMLElement>) => void; label: string }) { const { t } = useI18n(); return <div className="row-actions"><button className="icon-button icon-button--small" type="button" onClick={(event) => edit(item, event)} aria-label={`${t('edit')} ${label}`}><Icon name="edit" size={15} /></button>{item.status !== 'archived' && <button className="icon-button icon-button--small icon-button--danger" type="button" onClick={(event) => archive(item, event)} aria-label={`${t('archive')} ${label}`}><Icon name="archive" size={15} /></button>}</div>; }
function Skeleton({ columns }: { columns: number }) { return <>{Array.from({ length: 4 }, (_, row) => <tr className="skeleton-row" key={row} aria-hidden="true">{Array.from({ length: columns }, (__, cell) => <td key={cell}><span /></td>)}</tr>)}</>; }
function Field({ label, error, wide, children }: { label: string; error?: string; wide?: boolean; children: ReactNode }) { return <label className={wide ? 'form-field form-field--wide' : 'form-field'}><span>{label}</span>{children}{error && <small className="form-error">{error}</small>}</label>; }
function Status({ value, label }: { value: RecordStatus; label: string }) { const semantic = value === 'active' ? 'success' : value === 'inactive' ? 'warning' : 'neutral'; return <span className={`status-badge status-badge--${semantic}`}><span />{label}</span>; }
