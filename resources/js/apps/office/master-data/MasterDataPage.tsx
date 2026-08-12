import { useCallback, useEffect, useRef, useState, type FormEvent } from 'react';
import type { AxiosError } from 'axios';
import { apiClient } from '../../../packages/api-client/client';
import { Icon } from '../../../packages/design-system/Icon';
import { useI18n } from '../../../packages/i18n/I18nProvider';
import { MasterDataNavigation } from './MasterDataNavigation';

interface Area {
    id: string;
    code: string;
    name: { en: string; 'my-MM': string | null };
    description: string | null;
    parent: { id: string; name: { en: string; 'my-MM': string | null } } | null;
    sort_order: number;
    status: 'active' | 'inactive' | 'archived';
    version: number;
    updated_at: string;
}

interface AreaListResponse {
    data: Area[];
    meta: { current_page: number; last_page: number; per_page: number; total: number };
}

interface ApiErrorResponse {
    message?: string;
    code?: string;
    errors?: Record<string, string[]>;
}

interface AreaForm {
    code: string;
    name_en: string;
    name_my: string;
    parent_area_public_id: string;
    sort_order: number;
    status: 'active' | 'inactive';
    description: string;
    version?: number;
}

const emptyForm: AreaForm = {
    code: '',
    name_en: '',
    name_my: '',
    parent_area_public_id: '',
    sort_order: 0,
    status: 'active',
    description: '',
};

export function MasterDataPage() {
    const { locale, t } = useI18n();
    const [areas, setAreas] = useState<Area[]>([]);
    const [meta, setMeta] = useState<AreaListResponse['meta']>({ current_page: 1, last_page: 1, per_page: 20, total: 0 });
    const [page, setPage] = useState(1);
    const [searchDraft, setSearchDraft] = useState('');
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('');
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState<'error' | 'permission' | null>(null);
    const [refreshKey, setRefreshKey] = useState(0);
    const [dialog, setDialog] = useState<'form' | 'archive' | null>(null);
    const [selected, setSelected] = useState<Area | null>(null);
    const [form, setForm] = useState<AreaForm>(emptyForm);
    const [archiveReason, setArchiveReason] = useState('');
    const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
    const [submitting, setSubmitting] = useState(false);
    const [flash, setFlash] = useState<string | null>(null);
    const dialogTrigger = useRef<HTMLElement | null>(null);
    const codeInput = useRef<HTMLInputElement | null>(null);
    const archiveReasonInput = useRef<HTMLTextAreaElement | null>(null);

    const loadAreas = useCallback(async (signal?: AbortSignal) => {
        setLoading(true);
        setLoadError(null);
        try {
            const response = await apiClient.get<AreaListResponse>('/master-data/areas', {
                params: { page, per_page: 20, search: search || undefined, status: status || undefined },
                signal,
            });
            setAreas(response.data.data);
            setMeta(response.data.meta);
        } catch (error) {
            if (signal?.aborted) return;
            const statusCode = (error as AxiosError).response?.status;
            setLoadError(statusCode === 401 || statusCode === 403 ? 'permission' : 'error');
        } finally {
            if (!signal?.aborted) setLoading(false);
        }
    }, [page, search, status]);

    useEffect(() => {
        const controller = new AbortController();
        void loadAreas(controller.signal);
        return () => controller.abort();
    }, [loadAreas, refreshKey]);

    useEffect(() => {
        if (!dialog) return;
        const handleEscape = (event: KeyboardEvent) => {
            if (event.key === 'Escape') closeDialog();
        };
        document.addEventListener('keydown', handleEscape);
        window.setTimeout(() => {
            if (dialog === 'form') codeInput.current?.focus();
            if (dialog === 'archive') archiveReasonInput.current?.focus();
        }, 0);
        return () => document.removeEventListener('keydown', handleEscape);
    }, [dialog]);

    function beginCreate(event: React.MouseEvent<HTMLElement>) {
        dialogTrigger.current = event.currentTarget;
        setSelected(null);
        setForm({ ...emptyForm, sort_order: (meta.total + 1) * 10 });
        setFieldErrors({});
        setDialog('form');
    }

    function beginEdit(area: Area, event: React.MouseEvent<HTMLElement>) {
        dialogTrigger.current = event.currentTarget;
        setSelected(area);
        setForm({
            code: area.code,
            name_en: area.name.en,
            name_my: area.name['my-MM'] ?? '',
            parent_area_public_id: area.parent?.id ?? '',
            sort_order: area.sort_order,
            status: area.status === 'inactive' ? 'inactive' : 'active',
            description: area.description ?? '',
            version: area.version,
        });
        setFieldErrors({});
        setDialog('form');
    }

    function beginArchive(area: Area, event: React.MouseEvent<HTMLElement>) {
        dialogTrigger.current = event.currentTarget;
        setSelected(area);
        setArchiveReason('');
        setFieldErrors({});
        setDialog('archive');
    }

    function closeDialog() {
        if (submitting) return;
        setDialog(null);
        setFieldErrors({});
        window.setTimeout(() => dialogTrigger.current?.focus(), 0);
    }

    async function submitForm(event: FormEvent) {
        event.preventDefault();
        setSubmitting(true);
        setFieldErrors({});
        try {
            const payload = {
                ...form,
                name_my: form.name_my || null,
                parent_area_public_id: form.parent_area_public_id || null,
                description: form.description || null,
            };
            if (selected) {
                await apiClient.put(`/master-data/areas/${selected.id}`, payload);
                setFlash(t('areaUpdated'));
            } else {
                await apiClient.post('/master-data/areas', payload);
                setFlash(t('areaCreated'));
            }
            setDialog(null);
            setRefreshKey((value) => value + 1);
            window.setTimeout(() => dialogTrigger.current?.focus(), 0);
        } catch (error) {
            const response = (error as AxiosError<ApiErrorResponse>).response;
            if (response?.status === 409) {
                setFieldErrors(response.data.code === 'invalid_parent_cycle'
                    ? { parent_area_public_id: [t('invalidParentCycle')] }
                    : { form: [t('staleConflict')] });
            } else {
                setFieldErrors(response?.data.errors ?? { form: [response?.data.message ?? t('errorLoadingAreas')] });
            }
        } finally {
            setSubmitting(false);
        }
    }

    async function submitArchive(event: FormEvent) {
        event.preventDefault();
        if (!selected) return;
        setSubmitting(true);
        setFieldErrors({});
        try {
            await apiClient.patch(`/master-data/areas/${selected.id}/archive`, {
                version: selected.version,
                reason: archiveReason,
            });
            setFlash(t('areaArchived'));
            setDialog(null);
            setRefreshKey((value) => value + 1);
            window.setTimeout(() => dialogTrigger.current?.focus(), 0);
        } catch (error) {
            const response = (error as AxiosError<ApiErrorResponse>).response;
            setFieldErrors(response?.status === 409
                ? { form: [response.data.code === 'active_children' ? response.data.message ?? t('staleConflict') : t('staleConflict')] }
                : response?.data.errors ?? { form: [response?.data.message ?? t('errorLoadingAreas')] });
        } finally {
            setSubmitting(false);
        }
    }

    function submitSearch(event: FormEvent) {
        event.preventDefault();
        setPage(1);
        setSearch(searchDraft.trim());
    }

    const localizedName = (area: Area) => area.name[locale] || area.name.en;
    const alternativeName = (area: Area) => locale === 'en' ? area.name['my-MM'] : area.name.en;

    return (
        <>
            <section className="page-heading">
                <div>
                    <p className="eyebrow">{t('phaseOne')}</p>
                    <h1>{t('areaRegister')}</h1>
                    <p>{t('areaRegisterDescription')}</p>
                </div>
                <button className="button button--primary" type="button" onClick={beginCreate}>
                    <Icon name="plus" size={16} /> {t('addArea')}
                </button>
            </section>

            <MasterDataNavigation />

            {flash && <div className="flash-message flash-message--success" role="status"><span className="status-dot status-dot--success" />{flash}</div>}

            <article className="panel master-register">
                <div className="panel-heading">
                    <div><p className="eyebrow">{t('masterData')}</p><h2>{t('areaRegister')}</h2></div>
                    <span className="status-badge status-badge--info"><span />{t('totalRecords')}: {meta.total}</span>
                </div>

                <form className="filter-toolbar" onSubmit={submitSearch}>
                    <label className="search-field">
                        <span className="sr-only">{t('searchAreas')}</span>
                        <Icon name="activity" size={15} />
                        <input value={searchDraft} onChange={(event) => setSearchDraft(event.target.value)} placeholder={t('searchAreas')} />
                    </label>
                    <label>
                        <span className="sr-only">{t('status')}</span>
                        <select value={status} onChange={(event) => { setStatus(event.target.value); setPage(1); }}>
                            <option value="">{t('allStatuses')}</option>
                            <option value="active">{t('active')}</option>
                            <option value="inactive">{t('inactive')}</option>
                            <option value="archived">{t('archived')}</option>
                        </select>
                    </label>
                    <button className="button button--secondary" type="submit">{t('searchAreas')}</button>
                    <button className="icon-button" type="button" onClick={() => setRefreshKey((value) => value + 1)} aria-label={t('refresh')} title={t('refresh')}>
                        <Icon name="refresh" size={16} />
                    </button>
                </form>

                {loadError ? (
                    <div className="empty-state register-state" role="alert">
                        <span className="empty-icon"><Icon name="shield" /></span>
                        <strong>{loadError === 'permission' ? t('permissionDenied') : t('errorLoadingAreas')}</strong>
                        <button className="button button--secondary" type="button" onClick={() => setRefreshKey((value) => value + 1)}>{t('retry')}</button>
                    </div>
                ) : (
                    <div className="table-region master-table" tabIndex={0} aria-busy={loading}>
                        <table>
                            <thead><tr><th>{t('code')}</th><th>{t('englishName')}</th><th>{t('parentArea')}</th><th>{t('sortOrder')}</th><th>{t('status')}</th><th>{t('updated')}</th><th className="actions-column">{t('actions')}</th></tr></thead>
                            <tbody>
                                {loading && Array.from({ length: 5 }, (_, index) => <SkeletonRow key={index} />)}
                                {!loading && areas.length === 0 && <tr><td colSpan={7} className="table-empty">{t('noAreas')}</td></tr>}
                                {!loading && areas.map((area) => (
                                    <tr key={area.id}>
                                        <td className="identity-cell"><strong>{area.code}</strong><small>{area.id}</small></td>
                                        <td><strong>{localizedName(area)}</strong>{alternativeName(area) && <small>{alternativeName(area)}</small>}</td>
                                        <td>{area.parent ? (area.parent.name[locale] || area.parent.name.en) : '—'}</td>
                                        <td className="numeric-cell">{area.sort_order}</td>
                                        <td><StatusBadge status={area.status} /></td>
                                        <td>{new Intl.DateTimeFormat(locale, { dateStyle: 'medium' }).format(new Date(area.updated_at))}</td>
                                        <td className="actions-column">
                                            <div className="row-actions">
                                                <button className="icon-button icon-button--small" type="button" onClick={(event) => beginEdit(area, event)} aria-label={`${t('edit')} ${localizedName(area)}`} title={t('edit')}>
                                                    <Icon name="edit" size={15} />
                                                </button>
                                                {area.status !== 'archived' && <button className="icon-button icon-button--small icon-button--danger" type="button" onClick={(event) => beginArchive(area, event)} aria-label={`${t('archive')} ${localizedName(area)}`} title={t('archive')}>
                                                    <Icon name="archive" size={15} />
                                                </button>}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <div className="pagination-bar">
                    <span>{t('totalRecords')}: {meta.total}</span>
                    <div>
                        <button type="button" disabled={page <= 1 || loading} onClick={() => setPage((value) => value - 1)}>{t('previous')}</button>
                        <strong>{meta.current_page} / {meta.last_page}</strong>
                        <button type="button" disabled={page >= meta.last_page || loading} onClick={() => setPage((value) => value + 1)}>{t('next')}</button>
                    </div>
                </div>
            </article>

            {dialog === 'form' && (
                <div className="modal-backdrop" role="presentation">
                    <section className="dialog" role="dialog" aria-modal="true" aria-labelledby="area-dialog-title">
                        <header className="dialog-header">
                            <div><p className="eyebrow">{t('masterData')}</p><h2 id="area-dialog-title">{selected ? t('editArea') : t('addArea')}</h2></div>
                            <button className="icon-button" type="button" onClick={closeDialog} aria-label={t('closeDialog')}><Icon name="close" /></button>
                        </header>
                        <form onSubmit={submitForm}>
                            <div className="dialog-body form-grid">
                                {fieldErrors.form && <p className="form-error form-error--wide" role="alert">{fieldErrors.form[0]}</p>}
                                <FormField label={t('code')} error={fieldErrors.code?.[0]}>
                                    <input ref={codeInput} value={form.code} onChange={(event) => setForm({ ...form, code: event.target.value.toUpperCase() })} required maxLength={32} />
                                </FormField>
                                <FormField label={t('sortOrder')} error={fieldErrors.sort_order?.[0]}>
                                    <input type="number" min="0" value={form.sort_order} onChange={(event) => setForm({ ...form, sort_order: Number(event.target.value) })} required />
                                </FormField>
                                <FormField label={t('englishName')} error={fieldErrors.name_en?.[0]}>
                                    <input value={form.name_en} onChange={(event) => setForm({ ...form, name_en: event.target.value })} required maxLength={160} />
                                </FormField>
                                <FormField label={t('myanmarName')} error={fieldErrors.name_my?.[0]}>
                                    <input value={form.name_my} onChange={(event) => setForm({ ...form, name_my: event.target.value })} maxLength={160} lang="my" />
                                </FormField>
                                <FormField label={t('parentArea')} error={fieldErrors.parent_area_public_id?.[0]}>
                                    <select value={form.parent_area_public_id} onChange={(event) => setForm({ ...form, parent_area_public_id: event.target.value })}>
                                        <option value="">{t('noParentArea')}</option>
                                        {areas.filter((area) => area.id !== selected?.id && area.status !== 'archived').map((area) => <option key={area.id} value={area.id}>{localizedName(area)} ({area.code})</option>)}
                                    </select>
                                </FormField>
                                <FormField label={t('status')} error={fieldErrors.status?.[0]}>
                                    <select value={form.status} onChange={(event) => setForm({ ...form, status: event.target.value as AreaForm['status'] })}>
                                        <option value="active">{t('active')}</option>
                                        <option value="inactive">{t('inactive')}</option>
                                    </select>
                                </FormField>
                                <FormField label={t('description')} error={fieldErrors.description?.[0]} wide>
                                    <textarea rows={4} value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} maxLength={2000} />
                                </FormField>
                            </div>
                            <footer className="dialog-footer">
                                <button className="button button--secondary" type="button" onClick={closeDialog} disabled={submitting}>{t('cancel')}</button>
                                <button className="button button--primary" type="submit" disabled={submitting}>{submitting ? t('saving') : t('save')}</button>
                            </footer>
                        </form>
                    </section>
                </div>
            )}

            {dialog === 'archive' && selected && (
                <div className="modal-backdrop" role="presentation">
                    <section className="dialog dialog--compact" role="dialog" aria-modal="true" aria-labelledby="archive-dialog-title">
                        <header className="dialog-header">
                            <div><p className="eyebrow">{selected.code}</p><h2 id="archive-dialog-title">{t('archiveArea')}: {localizedName(selected)}</h2></div>
                            <button className="icon-button" type="button" onClick={closeDialog} aria-label={t('closeDialog')}><Icon name="close" /></button>
                        </header>
                        <form onSubmit={submitArchive}>
                            <div className="dialog-body">
                                <p className="warning-copy">{t('archiveWarning')}</p>
                                {fieldErrors.form && <p className="form-error" role="alert">{fieldErrors.form[0]}</p>}
                                <FormField label={t('archiveReason')} error={fieldErrors.reason?.[0]} wide>
                                    <textarea ref={archiveReasonInput} rows={4} value={archiveReason} onChange={(event) => setArchiveReason(event.target.value)} required minLength={3} maxLength={500} />
                                </FormField>
                            </div>
                            <footer className="dialog-footer">
                                <button className="button button--secondary" type="button" onClick={closeDialog} disabled={submitting}>{t('cancel')}</button>
                                <button className="button button--danger" type="submit" disabled={submitting}>{submitting ? t('saving') : t('archive')}</button>
                            </footer>
                        </form>
                    </section>
                </div>
            )}
        </>
    );
}

function FormField({ label, error, wide = false, children }: { label: string; error?: string; wide?: boolean; children: React.ReactNode }) {
    return <label className={wide ? 'form-field form-field--wide' : 'form-field'}><span>{label}</span>{children}{error && <small className="form-error">{error}</small>}</label>;
}

function StatusBadge({ status }: { status: Area['status'] }) {
    const { t } = useI18n();
    const semantic = status === 'active' ? 'success' : status === 'inactive' ? 'warning' : 'neutral';
    return <span className={`status-badge status-badge--${semantic}`}><span />{t(status)}</span>;
}

function SkeletonRow() {
    return <tr className="skeleton-row" aria-hidden="true"><td><span /></td><td><span /></td><td><span /></td><td><span /></td><td><span /></td><td><span /></td><td><span /></td></tr>;
}
