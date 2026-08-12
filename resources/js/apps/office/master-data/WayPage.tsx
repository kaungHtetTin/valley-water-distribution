import { useCallback, useEffect, useRef, useState, type FormEvent, type ReactNode } from 'react';
import type { AxiosError } from 'axios';
import { apiClient } from '../../../packages/api-client/client';
import { Icon } from '../../../packages/design-system/Icon';
import { useI18n } from '../../../packages/i18n/I18nProvider';
import { MasterDataNavigation } from './MasterDataNavigation';

type Day = 'mon' | 'tue' | 'wed' | 'thu' | 'fri' | 'sat' | 'sun';
type RecordStatus = 'active' | 'inactive' | 'archived';
interface Name { en: string; 'my-MM': string | null }
interface Reference { id: string; code: string; name: Name }
interface Way {
    id: string; code: string; name: Name; description: string | null; status: RecordStatus; version: number; updated_at: string;
    policy: { id: string; version: number; area: Reference; default_warehouse: Reference | null; boundary_description: string | null; service_days: Day[]; delivery_window_start: string | null; delivery_window_end: string | null; effective_from: string; effective_to: string | null; status: string };
}
interface OptionReference { public_id: string; code: string; name_en: string; name_my: string | null }
interface Options { areas: OptionReference[]; warehouses: OptionReference[] }
interface PageResponse { data: Way[]; meta: { current_page: number; last_page: number; total: number } }
interface ApiError { message?: string; code?: string; errors?: Record<string, string[]> }

const days: Day[] = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
const emptyForm = {
    code: '', name_en: '', name_my: '', description: '', status: 'active' as 'active' | 'inactive', area_public_id: '', default_warehouse_public_id: '',
    boundary_description: '', service_days: ['mon', 'wed', 'fri'] as Day[], delivery_window_start: '', delivery_window_end: '',
    effective_from: new Date().toISOString().slice(0, 10), change_reason: '', version: undefined as number | undefined,
};

export function WayPage() {
    const { locale, t } = useI18n();
    const [ways, setWays] = useState<Way[]>([]);
    const [options, setOptions] = useState<Options>({ areas: [], warehouses: [] });
    const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
    const [page, setPage] = useState(1);
    const [searchDraft, setSearchDraft] = useState('');
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('');
    const [area, setArea] = useState('');
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState(false);
    const [refreshKey, setRefreshKey] = useState(0);
    const [dialog, setDialog] = useState<'form' | 'archive' | null>(null);
    const [selected, setSelected] = useState<Way | null>(null);
    const [form, setForm] = useState(emptyForm);
    const [archiveReason, setArchiveReason] = useState('');
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [submitting, setSubmitting] = useState(false);
    const [flash, setFlash] = useState<string | null>(null);
    const firstInput = useRef<HTMLInputElement | HTMLTextAreaElement | null>(null);
    const trigger = useRef<HTMLElement | null>(null);

    const load = useCallback(async (signal?: AbortSignal) => {
        setLoading(true); setLoadError(false);
        try {
            const [wayResponse, optionResponse] = await Promise.all([
                apiClient.get<PageResponse>('/master-data/ways', { params: { page, per_page: 20, search: search || undefined, status: status || undefined, area: area || undefined }, signal }),
                apiClient.get<{ data: Options }>('/master-data/ways/options', { signal }),
            ]);
            setWays(wayResponse.data.data); setMeta(wayResponse.data.meta); setOptions(optionResponse.data.data);
        } catch { if (!signal?.aborted) setLoadError(true); }
        finally { if (!signal?.aborted) setLoading(false); }
    }, [page, search, status, area]);

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
    const beginCreate = (event: React.MouseEvent<HTMLElement>) => {
        trigger.current = event.currentTarget; setSelected(null); setErrors({});
        setForm({ ...emptyForm, area_public_id: options.areas[0]?.public_id ?? '', default_warehouse_public_id: options.warehouses[0]?.public_id ?? '' }); setDialog('form');
    };
    const beginEdit = (way: Way, event: React.MouseEvent<HTMLElement>) => {
        trigger.current = event.currentTarget; setSelected(way); setErrors({});
        setForm({ code: way.code, name_en: way.name.en, name_my: way.name['my-MM'] ?? '', description: way.description ?? '', status: way.status === 'inactive' ? 'inactive' : 'active',
            area_public_id: way.policy.area.id, default_warehouse_public_id: way.policy.default_warehouse?.id ?? '', boundary_description: way.policy.boundary_description ?? '',
            service_days: way.policy.service_days, delivery_window_start: way.policy.delivery_window_start ?? '', delivery_window_end: way.policy.delivery_window_end ?? '',
            effective_from: way.policy.effective_from, change_reason: '', version: way.version }); setDialog('form');
    };
    const beginArchive = (way: Way, event: React.MouseEvent<HTMLElement>) => { trigger.current = event.currentTarget; setSelected(way); setArchiveReason(''); setErrors({}); setDialog('archive'); };

    function toggleDay(day: Day) {
        setForm({ ...form, service_days: form.service_days.includes(day) ? form.service_days.filter((value) => value !== day) : [...form.service_days, day] });
    }
    function handleError(error: unknown) {
        const response = (error as AxiosError<ApiError>).response;
        const message = response?.data.code === 'way_version_date_conflict' ? t('wayVersionDateConflict') : response?.status === 409 ? t('wayConflict') : response?.data.message ?? t('errorLoadingWays');
        setErrors(response?.data.errors ?? { form: [message] });
    }
    async function submitForm(event: FormEvent) {
        event.preventDefault(); setSubmitting(true); setErrors({});
        const payload = { ...form, name_my: form.name_my || null, description: form.description || null, default_warehouse_public_id: form.default_warehouse_public_id || null,
            boundary_description: form.boundary_description || null, delivery_window_start: form.delivery_window_start || null, delivery_window_end: form.delivery_window_end || null, change_reason: form.change_reason || null };
        try {
            if (selected) await apiClient.put(`/master-data/ways/${selected.id}`, payload); else await apiClient.post('/master-data/ways', payload);
            setFlash(selected ? t('wayUpdated') : t('wayCreated')); setDialog(null); setRefreshKey((value) => value + 1);
        } catch (error) { handleError(error); } finally { setSubmitting(false); }
    }
    async function submitArchive(event: FormEvent) {
        event.preventDefault(); if (!selected) return; setSubmitting(true); setErrors({});
        try { await apiClient.patch(`/master-data/ways/${selected.id}/archive`, { version: selected.version, reason: archiveReason }); setFlash(t('wayArchived')); setDialog(null); setRefreshKey((value) => value + 1); }
        catch (error) { handleError(error); } finally { setSubmitting(false); }
    }

    return <>
        <section className="page-heading"><div><p className="eyebrow">{t('phaseOne')}</p><h1>{t('wayRegister')}</h1><p>{t('wayDescription')}</p></div><button className="button button--primary" type="button" onClick={beginCreate}><Icon name="plus" size={16} />{t('addWay')}</button></section>
        <MasterDataNavigation />
        {flash && <div className="flash-message flash-message--success" role="status"><span className="status-dot status-dot--success" />{flash}</div>}
        <article className="panel master-register">
            <div className="panel-heading"><div><p className="eyebrow">{t('territoryPolicy')}</p><h2>{t('wayRegister')}</h2></div><span className="status-badge status-badge--info"><span />{t('totalRecords')}: {meta.total}</span></div>
            <form className="filter-toolbar way-filters" onSubmit={(event) => { event.preventDefault(); setPage(1); setSearch(searchDraft.trim()); }}>
                <label className="search-field"><span className="sr-only">{t('searchWays')}</span><Icon name="activity" size={15} /><input value={searchDraft} onChange={(event) => setSearchDraft(event.target.value)} placeholder={t('searchWays')} /></label>
                <label><span className="sr-only">{t('area')}</span><select value={area} onChange={(event) => { setArea(event.target.value); setPage(1); }}><option value="">{t('allAreas')}</option>{options.areas.map((item) => <option key={item.public_id} value={item.public_id}>{optionName(item)}</option>)}</select></label>
                <label><span className="sr-only">{t('status')}</span><select value={status} onChange={(event) => { setStatus(event.target.value); setPage(1); }}><option value="">{t('allStatuses')}</option><option value="active">{t('active')}</option><option value="inactive">{t('inactive')}</option><option value="archived">{t('archived')}</option></select></label>
                <button className="button button--secondary" type="submit">{t('search')}</button><button className="icon-button" type="button" onClick={() => setRefreshKey((value) => value + 1)} aria-label={t('refresh')}><Icon name="refresh" size={16} /></button>
            </form>
            {loadError ? <div className="empty-state register-state" role="alert"><strong>{t('errorLoadingWays')}</strong><button className="button button--secondary" onClick={() => setRefreshKey((value) => value + 1)}>{t('retry')}</button></div> :
                <div className="table-region master-table way-table" tabIndex={0} aria-busy={loading}><table><thead><tr><th>{t('way')}</th><th>{t('area')}</th><th>{t('serviceDays')}</th><th>{t('defaultWarehouse')}</th><th>{t('deliveryWindow')}</th><th>{t('effectiveVersion')}</th><th>{t('status')}</th><th className="actions-column">{t('actions')}</th></tr></thead><tbody>
                    {loading && Array.from({ length: 5 }, (_, index) => <tr className="skeleton-row" key={index} aria-hidden="true">{Array.from({ length: 8 }, (__, cell) => <td key={cell}><span /></td>)}</tr>)}
                    {!loading && !ways.length && <tr><td colSpan={8} className="table-empty">{t('noWays')}</td></tr>}
                    {!loading && ways.map((way) => <tr key={way.id}><td className="identity-cell"><strong>{way.code}</strong><small>{localized(way.name)}</small></td><td><strong>{localized(way.policy.area.name)}</strong><small>{way.policy.area.code}</small></td><td><div className="day-strip">{way.policy.service_days.map((day) => <span key={day}>{t(day)}</span>)}</div></td><td>{way.policy.default_warehouse ? <><strong>{localized(way.policy.default_warehouse.name)}</strong><small>{way.policy.default_warehouse.code}</small></> : '—'}</td><td>{way.policy.delivery_window_start ? `${way.policy.delivery_window_start}–${way.policy.delivery_window_end}` : '—'}</td><td><strong>v{way.policy.version}</strong><small>{way.policy.effective_from}</small></td><td><Status value={way.status} label={t(way.status)} /></td><td className="actions-column"><div className="row-actions"><button className="icon-button icon-button--small" type="button" onClick={(event) => beginEdit(way, event)} aria-label={`${t('edit')} ${localized(way.name)}`}><Icon name="edit" size={15} /></button>{way.status !== 'archived' && <button className="icon-button icon-button--small icon-button--danger" type="button" onClick={(event) => beginArchive(way, event)} aria-label={`${t('archive')} ${localized(way.name)}`}><Icon name="archive" size={15} /></button>}</div></td></tr>)}</tbody></table></div>}
            <div className="pagination-bar"><span>{t('totalRecords')}: {meta.total}</span><div><button disabled={page <= 1 || loading} onClick={() => setPage((value) => value - 1)}>{t('previous')}</button><strong>{meta.current_page} / {meta.last_page}</strong><button disabled={page >= meta.last_page || loading} onClick={() => setPage((value) => value + 1)}>{t('next')}</button></div></div>
        </article>

        {dialog === 'form' && <div className="modal-backdrop" role="presentation"><section className="dialog" role="dialog" aria-modal="true" aria-label={selected ? t('editWay') : t('addWay')}><header className="dialog-header"><h2>{selected ? t('editWay') : t('addWay')}</h2><button className="icon-button" type="button" onClick={closeDialog} aria-label={t('closeDialog')}><Icon name="close" /></button></header><form onSubmit={submitForm}><div className="dialog-body form-grid">
            {errors.form && <p className="form-error form-error--wide" role="alert">{errors.form[0]}</p>}
            <Field label={t('code')} error={errors.code?.[0]}><input ref={firstInput as React.RefObject<HTMLInputElement>} value={form.code} onChange={(event) => setForm({ ...form, code: event.target.value.toUpperCase() })} required /></Field>
            <Field label={t('area')} error={errors.area_public_id?.[0]}><select value={form.area_public_id} onChange={(event) => setForm({ ...form, area_public_id: event.target.value })} required>{options.areas.map((item) => <option key={item.public_id} value={item.public_id}>{item.code} · {optionName(item)}</option>)}</select></Field>
            <Field label={t('englishName')} error={errors.name_en?.[0]}><input value={form.name_en} onChange={(event) => setForm({ ...form, name_en: event.target.value })} required /></Field>
            <Field label={t('myanmarName')} error={errors.name_my?.[0]}><input lang="my" value={form.name_my} onChange={(event) => setForm({ ...form, name_my: event.target.value })} /></Field>
            <Field label={t('defaultWarehouse')} error={errors.default_warehouse_public_id?.[0]}><select value={form.default_warehouse_public_id} onChange={(event) => setForm({ ...form, default_warehouse_public_id: event.target.value })}><option value="">{t('noDefaultWarehouse')}</option>{options.warehouses.map((item) => <option key={item.public_id} value={item.public_id}>{item.code} · {optionName(item)}</option>)}</select></Field>
            <Field label={t('status')} error={errors.status?.[0]}><select value={form.status} onChange={(event) => setForm({ ...form, status: event.target.value as 'active' | 'inactive' })}><option value="active">{t('active')}</option><option value="inactive">{t('inactive')}</option></select></Field>
            <Field label={t('serviceDays')} error={errors.service_days?.[0]} wide><div className="day-picker">{days.map((day) => <label key={day}><input type="checkbox" checked={form.service_days.includes(day)} onChange={() => toggleDay(day)} />{t(day)}</label>)}</div></Field>
            <Field label={t('deliveryWindowStart')} error={errors.delivery_window_start?.[0]}><input type="time" value={form.delivery_window_start} onChange={(event) => setForm({ ...form, delivery_window_start: event.target.value })} /></Field>
            <Field label={t('deliveryWindowEnd')} error={errors.delivery_window_end?.[0]}><input type="time" value={form.delivery_window_end} onChange={(event) => setForm({ ...form, delivery_window_end: event.target.value })} /></Field>
            <Field label={t('effectiveFrom')} error={errors.effective_from?.[0]}><input type="date" value={form.effective_from} onChange={(event) => setForm({ ...form, effective_from: event.target.value })} required /></Field>
            {selected && <Field label={t('changeReason')} error={errors.change_reason?.[0]}><input value={form.change_reason} onChange={(event) => setForm({ ...form, change_reason: event.target.value })} required minLength={3} /></Field>}
            <Field label={t('boundaryDescription')} error={errors.boundary_description?.[0]} wide><textarea rows={3} value={form.boundary_description} onChange={(event) => setForm({ ...form, boundary_description: event.target.value })} /></Field>
            <Field label={t('description')} error={errors.description?.[0]} wide><textarea rows={3} value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} /></Field>
            {selected && <p className="form-note form-field--wide">{t('wayVersionNote')}</p>}
        </div><footer className="dialog-footer"><button className="button button--secondary" type="button" onClick={closeDialog} disabled={submitting}>{t('cancel')}</button><button className="button button--primary" type="submit" disabled={submitting}>{submitting ? t('saving') : t('save')}</button></footer></form></section></div>}
        {dialog === 'archive' && selected && <div className="modal-backdrop" role="presentation"><section className="dialog dialog--compact" role="dialog" aria-modal="true" aria-label={t('archiveWay')}><header className="dialog-header"><h2>{t('archiveWay')}: {localized(selected.name)}</h2><button className="icon-button" type="button" onClick={closeDialog} aria-label={t('closeDialog')}><Icon name="close" /></button></header><form onSubmit={submitArchive}><div className="dialog-body"><p className="warning-copy">{t('archiveWayWarning')}</p>{errors.form && <p className="form-error" role="alert">{errors.form[0]}</p>}<Field label={t('archiveReason')} error={errors.reason?.[0]} wide><textarea ref={firstInput as React.RefObject<HTMLTextAreaElement>} rows={4} value={archiveReason} onChange={(event) => setArchiveReason(event.target.value)} required minLength={3} /></Field></div><footer className="dialog-footer"><button className="button button--secondary" type="button" onClick={closeDialog} disabled={submitting}>{t('cancel')}</button><button className="button button--danger" type="submit" disabled={submitting}>{submitting ? t('saving') : t('archive')}</button></footer></form></section></div>}
    </>;
}

function Field({ label, error, wide, children }: { label: string; error?: string; wide?: boolean; children: ReactNode }) { return <label className={wide ? 'form-field form-field--wide' : 'form-field'}><span>{label}</span>{children}{error && <small className="form-error">{error}</small>}</label>; }
function Status({ value, label }: { value: RecordStatus; label: string }) { const semantic = value === 'active' ? 'success' : value === 'inactive' ? 'warning' : 'neutral'; return <span className={`status-badge status-badge--${semantic}`}><span />{label}</span>; }
