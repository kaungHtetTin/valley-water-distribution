import {
    AlertCircle,
    CalendarDays,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Copy,
    Crosshair,
    Download,
    Droplets,
    ExternalLink,
    Filter,
    Languages,
    MapPinned,
    Pencil,
    Plus,
    Printer,
    QrCode,
    RefreshCw,
    RotateCcw,
    Save,
    Search,
    Trash2,
    User,
    X,
} from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { DetailPage, DetailPanel } from './components/DetailPage';
import { ShellPageActions } from './components/ShellPageActions';

const copy = {
    en: {
        attendance: 'QR Attendance',
        employeeCode: 'Employee ID',
        employeePlaceholder: 'Example: SAL-001',
        checking: 'Checking attendance location',
        locationUnavailable: 'This attendance link is unavailable.',
        inactive: 'This attendance location is inactive.',
        gpsNote: 'Your current location is used only to verify that you are within the allowed attendance radius.',
        submit: 'Check in now',
        locating: 'Getting your location',
        accepted: 'Attendance recorded',
        rejected: 'Attendance rejected',
        retry: 'Try again',
        distance: 'Distance',
        radius: 'Allowed radius',
        invalid_employee_id: 'The employee ID is invalid or inactive.',
        gps_denied: 'Location permission was denied.',
        outside_allowed_radius: 'You are outside the allowed attendance radius.',
        inactive_qr_token: 'This attendance QR code is inactive.',
        invalid_qr_token: 'This attendance QR code is invalid.',
    },
    my: {
        attendance: 'QR တက်ရောက်မှု',
        employeeCode: 'ဝန်ထမ်းအမှတ်',
        employeePlaceholder: 'ဥပမာ - SAL-001',
        checking: 'တက်ရောက်မှုနေရာကို စစ်ဆေးနေသည်',
        locationUnavailable: 'ဤတက်ရောက်မှုလင့်ခ်ကို အသုံးပြု၍မရပါ။',
        inactive: 'ဤတက်ရောက်မှုနေရာကို ပိတ်ထားသည်။',
        gpsNote: 'သတ်မှတ်ထားသော အကွာအဝေးအတွင်း ရှိမရှိစစ်ဆေးရန် လက်ရှိတည်နေရာကိုသာ အသုံးပြုပါသည်။',
        submit: 'ယခု တက်ရောက်မည်',
        locating: 'တည်နေရာ ရယူနေသည်',
        accepted: 'တက်ရောက်မှု မှတ်တမ်းတင်ပြီးပါပြီ',
        rejected: 'တက်ရောက်မှု ငြင်းပယ်ခံရသည်',
        retry: 'ပြန်ကြိုးစားမည်',
        distance: 'အကွာအဝေး',
        radius: 'ခွင့်ပြုအကွာအဝေး',
        invalid_employee_id: 'ဝန်ထမ်းအမှတ် မမှန်ပါ သို့မဟုတ် ပိတ်ထားသည်။',
        gps_denied: 'တည်နေရာအသုံးပြုခွင့် ငြင်းပယ်ထားသည်။',
        outside_allowed_radius: 'သတ်မှတ်ထားသော အကွာအဝေးပြင်ပတွင် ရှိနေသည်။',
        inactive_qr_token: 'ဤ QR ကုဒ်ကို ပိတ်ထားသည်။',
        invalid_qr_token: 'ဤ QR ကုဒ် မမှန်ပါ။',
    },
};

function endpoint(token) {
    return `${window.ValleyRuntime?.api?.publicAttendance || '/api/public/attendance'}/${token}`;
}

function apiBase(name) {
    const fallback = {
        attendanceLocations: '/api/attendance/locations',
        attendanceRecords: '/api/attendance/records',
        attendanceSummary: '/api/attendance/summary',
        mobileAttendance: '/api/mobile/attendance',
    };
    return window.ValleyRuntime?.api?.[name] || fallback[name];
}

const officeCopy = {
    en: {
        actions: 'Actions',
        active: 'Active',
        addLocation: 'Add Location',
        address: 'Address',
        allLocations: 'All locations',
        allReasons: 'All reasons',
        allStatuses: 'All statuses',
        allowedRadius: 'Allowed radius',
        attendance: 'Attendance',
        attendanceHistory: 'Attendance History',
        attendanceHistoryHint: 'Your latest accepted and rejected check-ins.',
        attendanceLocations: 'Attendance Locations',
        attendanceLocationsHint: 'QR check-in points with GPS radius controls.',
        attendanceRecords: 'Attendance Records',
        attendanceRecordsHint: 'Accepted and rejected check-ins from public QR scans.',
        attendanceSummary: 'Attendance Summary',
        attendanceSummaryHint: 'Monthly employee attendance totals for payroll preparation.',
        cancel: 'Cancel',
        checkedAt: 'Checked at',
        code: 'Code',
        copy: 'Copy link',
        copied: 'Copied to clipboard.',
        date: 'Date',
        delete: 'Delete',
        deleteConfirm: 'Delete this location? Locations with attendance records must be set inactive instead.',
        details: 'Details',
        distance: 'Distance',
        download: 'Download QR',
        edit: 'Edit',
        employee: 'Employee',
        employeeCode: 'Employee ID',
        employeeType: 'Employee type',
        empty: 'No records match this view.',
        gpsDenied: 'GPS denied',
        gpsLocating: 'Getting live GPS location...',
        gpsReady: 'Live GPS ready',
        gpsUnavailable: 'GPS is unavailable in this browser.',
        gpsSecureContext: 'Chrome requires HTTPS or localhost to use GPS.',
        useCurrentGps: 'Use current GPS',
        locationMap: 'Location map',
        accuracy: 'Accuracy',
        history: 'History',
        inactive: 'Inactive',
        latitude: 'Latitude',
        link: 'Public link',
        loading: 'Loading attendance history',
        loadError: 'Unable to load attendance data.',
        location: 'Location',
        warehouse: 'Warehouse',
        selectWarehouse: 'Select warehouse',
        longitude: 'Longitude',
        name: 'Name',
        open: 'Open',
        page: 'Page',
        print: 'Print',
        qrCode: 'QR code',
        reason: 'Reason',
        refresh: 'Refresh',
        rejected: 'Rejected',
        retry: 'Retry',
        rotate: 'Rotate token',
        rotateConfirm: 'Rotate this QR token? Existing printed QR codes for this location will stop working.',
        save: 'Save changes',
        saveError: 'Unable to save attendance location.',
        searchLocations: 'Search code, name, or address',
        searchRecords: 'Search employee, code, or location',
        searchSummary: 'Search employee or type',
        showAll: 'Show all',
        status: 'Status',
        total: 'Total',
        totalEmployees: 'Employees',
        totalRecords: 'Records',
        accepted: 'Accepted',
        outside_allowed_radius: 'Outside allowed radius',
        gps_denied: 'GPS denied',
        invalid_employee_id: 'Invalid employee ID',
        inactive_qr_token: 'Inactive QR token',
        invalid_qr_token: 'Invalid QR token',
    },
    my: {},
};

function t(locale, key) {
    return officeCopy[locale]?.[key] || officeCopy.en[key] || key;
}

function requestMessage(error, locale, fallbackKey = 'loadError') {
    return locale === 'my' ? t(locale, fallbackKey) : error.response?.data?.message || t(locale, fallbackKey);
}

export function PublicAttendanceScreen({ token, locale, setLocale }) {
    const t = { ...copy.en, ...copy[locale] };
    const [state, setState] = useState({ loading: true, location: null, error: '' });
    const [employeeCode, setEmployeeCode] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [result, setResult] = useState(null);
    const [formError, setFormError] = useState('');

    useEffect(() => {
        let mounted = true;
        window.axios.get(endpoint(token))
            .then(({ data }) => mounted && setState({ loading: false, location: data.data.location, error: '' }))
            .catch(() => mounted && setState({ loading: false, location: null, error: t.locationUnavailable }));
        return () => { mounted = false; };
    }, [token, locale]);

    const recordAttendance = async (coordinates) => {
        try {
            const { data } = await window.axios.post(endpoint(token), { employee_code: employeeCode, ...coordinates });
            setResult(data.data.result);
        } catch (error) {
            setFormError(error.response?.data?.errors?.employee_code?.[0] || error.response?.data?.message || t.locationUnavailable);
        } finally {
            setSubmitting(false);
        }
    };

    const submit = (event) => {
        event.preventDefault();
        setFormError('');
        setSubmitting(true);

        if (!navigator.geolocation) {
            recordAttendance({ gps_denied: true });
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => recordAttendance({
                latitude: position.coords.latitude,
                longitude: position.coords.longitude,
                gps_denied: false,
            }),
            () => recordAttendance({ gps_denied: true }),
            { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 }
        );
    };

    if (state.loading) {
        return <main className="public-attendance"><section className="attendance-card state"><RefreshCw className="spin" size={24} /><strong>{t.checking}</strong></section></main>;
    }

    if (state.error) {
        return <main className="public-attendance"><section className="attendance-card state error"><AlertCircle size={24} /><strong>{state.error}</strong></section></main>;
    }

    return (
        <main className="public-attendance">
            <header className="attendance-header">
                <div className="attendance-brand"><span><Droplets size={20} /></span><strong>Valley Water</strong></div>
                <div className="segmented" aria-label="Language"><Languages size={14} />{['en', 'my'].map((option) => <button className={locale === option ? 'is-selected' : ''} type="button" onClick={() => setLocale(option)} key={option}>{option.toUpperCase()}</button>)}</div>
            </header>

            <section className="attendance-card">
                <div className="attendance-location"><span><MapPinned size={22} /></span><div><p>{t.attendance}</p><h1>{state.location.name}</h1><small>{state.location.address}</small></div></div>

                {!state.location.is_active ? (
                    <div className="attendance-result rejected"><AlertCircle size={28} /><h2>{t.rejected}</h2><p>{t.inactive}</p></div>
                ) : result ? (
                    <div className={`attendance-result ${result.status}`}>
                        {result.status === 'accepted' ? <CheckCircle2 size={30} /> : <AlertCircle size={30} />}
                        <h2>{result.status === 'accepted' ? t.accepted : t.rejected}</h2>
                        {result.rejection_reason && <p>{t[result.rejection_reason] || result.rejection_reason}</p>}
                        {result.employee_name && <strong>{result.employee_name}</strong>}
                        <dl><div><dt>{t.distance}</dt><dd>{result.distance_m === null ? '—' : `${result.distance_m} m`}</dd></div><div><dt>{t.radius}</dt><dd>{result.allowed_radius_m} m</dd></div></dl>
                        <button className="button" type="button" onClick={() => setResult(null)}>{t.retry}</button>
                    </div>
                ) : (
                    <form className="attendance-form" onSubmit={submit}>
                        <label>{t.employeeCode}<input value={employeeCode} onChange={(event) => setEmployeeCode(event.target.value)} placeholder={t.employeePlaceholder} required /></label>
                        <p><Crosshair size={16} />{t.gpsNote}</p>
                        {formError && <div className="inline-error"><AlertCircle size={16} />{formError}</div>}
                        <button className="button primary" type="submit" disabled={submitting}><Save size={16} />{submitting ? t.locating : t.submit}</button>
                    </form>
                )}
            </section>
        </main>
    );
}

export function AttendanceLocationsScreen({ locale, canManage = false, detailId = null, onNavigate, embedded = false, basePath = null }) {
    const [filters, setFilters] = useState({ search: '', is_active: '' });
    const [page, setPage] = useState(1);
    const [state, setState] = useState({ loading: true, items: [], warehouses: [], meta: {}, error: '' });
    const [editing, setEditing] = useState(null);
    const [viewing, setViewing] = useState(null);
    const [message, setMessage] = useState('');
    const [refreshKey, setRefreshKey] = useState(0);
    const listPath = basePath || `${window.ValleyRuntime?.routes?.office || '/office'}/attendance/locations`;

    useEffect(() => {
        if (!detailId) {
            setViewing(null);
            return undefined;
        }
        let mounted = true;
        window.axios.get(`${apiBase('attendanceLocations')}/${detailId}`)
            .then(({ data }) => mounted && setViewing(data.data.location))
            .catch((error) => mounted && setState((current) => ({ ...current, error: requestMessage(error, locale) })));
        return () => { mounted = false; };
    }, [detailId, locale]);

    useEffect(() => {
        let mounted = true;
        const timer = window.setTimeout(() => {
            setState((current) => ({ ...current, loading: true, error: '' }));
            window.axios.get(apiBase('attendanceLocations'), {
                params: {
                    search: filters.search,
                    is_active: filters.is_active || undefined,
                    page,
                    per_page: 10,
                },
            }).then(({ data }) => {
                if (!mounted) return;
                setState({ loading: false, items: data.data.items, warehouses: data.data.warehouses || [], meta: data.data.meta, error: '' });
            }).catch((error) => mounted && setState({ loading: false, items: [], warehouses: [], meta: {}, error: requestMessage(error, locale) }));
        }, 220);
        return () => { mounted = false; window.clearTimeout(timer); };
    }, [filters, locale, page, refreshKey]);

    const openCreate = () => setEditing({
        mode: 'create',
        values: { warehouse_id: '', code: '', name: '', address: '', latitude: '', longitude: '', allowed_radius_m: 20, is_active: true },
    });

    const remove = async (location) => {
        if (!window.confirm(t(locale, 'deleteConfirm'))) return;
        try {
            await window.axios.delete(`${apiBase('attendanceLocations')}/${location.id}`);
            setViewing(null);
            setState((current) => ({ ...current, items: current.items.filter((item) => item.id !== location.id), meta: { ...current.meta, total: Math.max(0, Number(current.meta.total || 1) - 1) } }));
        } catch (error) {
            setState((current) => ({ ...current, error: requestMessage(error, locale) }));
        }
    };

    const rotate = async (location) => {
        if (!window.confirm(t(locale, 'rotateConfirm'))) return;
        try {
            const { data } = await window.axios.post(`${apiBase('attendanceLocations')}/${location.id}/rotate-token`);
            const updated = data.data.location;
            if (detailId) setViewing(updated);
            setState((current) => ({ ...current, items: current.items.map((item) => item.id === updated.id ? updated : item) }));
        } catch (error) {
            setState((current) => ({ ...current, error: requestMessage(error, locale) }));
        }
    };

    if (detailId) return viewing ? <><LocationDetailPage location={viewing} locale={locale} canManage={canManage} onClose={() => onNavigate?.(listPath)} onCopy={() => setMessage(t(locale, 'copied'))} onEdit={() => setEditing({ mode: 'edit', values: { ...viewing } })} onRotate={() => rotate(viewing)} />{editing && <LocationDialog editing={editing} warehouses={state.warehouses} locale={locale} onClose={() => setEditing(null)} onSaved={(location) => { setEditing(null); setViewing(location); setState((current) => ({ ...current, items: current.items.map((item) => item.id === location.id ? location : item) })); }} />}</> : <WorkspaceState icon={state.error ? AlertCircle : RefreshCw} title={state.error || t(locale, 'loading')} loading={!state.error} />;

    return (
        <section className={`master-workspace attendance-workspace ${embedded ? 'is-embedded' : ''}`}>
            {!embedded && <div className="master-heading">
                <div>
                    <p className="eyebrow">{t(locale, 'attendance')}</p>
                    <h1>{t(locale, 'attendanceLocations')}</h1>
                    <span className="muted">{t(locale, 'attendanceLocationsHint')}</span>
                </div>
            </div>}
            <ShellPageActions>{canManage && <button className="button primary" type="button" onClick={openCreate}><Plus size={16} /> {t(locale, 'addLocation')}</button>}</ShellPageActions>

            <div className="master-panel">
                <div className="master-toolbar">
                    <label className="master-search">
                        <Search size={15} />
                        <input value={filters.search} onChange={(event) => { setFilters((current) => ({ ...current, search: event.target.value })); setPage(1); }} placeholder={t(locale, 'searchLocations')} />
                    </label>
                    <select aria-label={t(locale, 'allStatuses')} value={filters.is_active} onChange={(event) => { setFilters((current) => ({ ...current, is_active: event.target.value })); setPage(1); }}>
                        <option value="">{t(locale, 'allStatuses')}</option>
                        <option value="1">{t(locale, 'active')}</option>
                        <option value="0">{t(locale, 'inactive')}</option>
                    </select>
                    <button className="icon-button" type="button" aria-label={t(locale, 'refresh')} title={t(locale, 'refresh')} onClick={() => setRefreshKey((key) => key + 1)}><RefreshCw size={15} /></button>
                </div>

                {message && <div className="inline-success"><CheckCircle2 size={15} /> {message}</div>}
                {state.error && <div className="inline-error"><AlertCircle size={15} /> {state.error}</div>}
                {state.loading ? <TableLoading columns={6 + (canManage ? 1 : 0)} /> : state.items.length === 0 ? <WorkspaceState icon={Search} title={t(locale, 'empty')} compact /> : (
                    <div className="master-table-wrap attendance-location-table-wrap">
                        <table className="master-table attendance-table attendance-locations-table">
                            <thead>
                                <tr>
                                    <th>{t(locale, 'location')}</th>
                                    <th>{t(locale, 'address')}</th>
                                    <th>{t(locale, 'allowedRadius')}</th>
                                    <th>{t(locale, 'status')}</th>
                                    <th>{t(locale, 'link')}</th>
                                    {canManage && <th className="table-actions-header">{t(locale, 'actions')}</th>}
                                </tr>
                            </thead>
                            <tbody>
                                {state.items.map((location) => (
                                    <tr className="clickable-row" key={location.id} tabIndex={0} onClick={() => onNavigate?.(`${listPath}/${location.id}`)} onKeyDown={(event) => { if (event.target === event.currentTarget && ['Enter', ' '].includes(event.key)) { event.preventDefault(); onNavigate?.(`${listPath}/${location.id}`); } }}>
                                        <td><strong>{location.name}</strong><span className="muted">{location.warehouse?.name || t(locale, 'warehouse')} · {location.code}</span></td>
                                        <td>{location.address || <span className="muted">-</span>}</td>
                                        <td>{meters(location.allowed_radius_m)}</td>
                                        <td><StatusBadge status={location.is_active ? 'active' : 'inactive'} locale={locale} /></td>
                                        <td><a className="text-link" href={location.public_url} target="_blank" rel="noreferrer">{t(locale, 'open')}</a></td>
                                        {canManage && <td className="table-actions-cell" onClick={(event) => event.stopPropagation()}><div className="row-actions">
                                            <button type="button" aria-label={t(locale, 'edit')} title={t(locale, 'edit')} onClick={() => setEditing({ mode: 'edit', values: { ...location } })}><Pencil size={15} /></button>
                                            <button type="button" aria-label={t(locale, 'rotate')} title={t(locale, 'rotate')} onClick={() => rotate(location)}><RotateCcw size={15} /></button>
                                            <button className="danger" type="button" aria-label={t(locale, 'delete')} title={t(locale, 'delete')} onClick={() => remove(location)}><Trash2 size={15} /></button>
                                        </div></td>}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination meta={state.meta} page={page} setPage={setPage} locale={locale} />
            </div>

            {editing && <LocationDialog editing={editing} warehouses={state.warehouses} locale={locale} onClose={() => setEditing(null)} onSaved={(location) => { setEditing(null); setState((current) => { const exists = current.items.some((item) => item.id === location.id); return { ...current, items: exists ? current.items.map((item) => item.id === location.id ? location : item) : [location, ...current.items], meta: { ...current.meta, total: Number(current.meta.total || 0) + (exists ? 0 : 1) } }; }); }} />}
        </section>
    );
}

export function AttendanceRecordsScreen({ locale, detailId = null, onNavigate }) {
    const [filters, setFilters] = useState({ search: '', status: '', rejection_reason: '', attendance_location_id: '', date: '' });
    const [page, setPage] = useState(1);
    const [state, setState] = useState({ loading: true, items: [], meta: {}, error: '' });
    const [locations, setLocations] = useState([]);
    const [viewing, setViewing] = useState(null);
    const [refreshKey, setRefreshKey] = useState(0);
    const listPath = `${window.ValleyRuntime?.routes?.office || '/office'}/attendance/records`;

    useEffect(() => {
        if (!detailId) {
            setViewing(null);
            return undefined;
        }
        let mounted = true;
        window.axios.get(`${apiBase('attendanceRecords')}/${detailId}`)
            .then(({ data }) => mounted && setViewing(data.data.record))
            .catch((error) => mounted && setState((current) => ({ ...current, error: requestMessage(error, locale) })));
        return () => { mounted = false; };
    }, [detailId, locale]);

    useEffect(() => {
        let mounted = true;
        window.axios.get(apiBase('attendanceLocations'), { params: { per_page: 100 } })
            .then(({ data }) => mounted && setLocations(data.data.items))
            .catch(() => mounted && setLocations([]));
        return () => { mounted = false; };
    }, []);

    useEffect(() => {
        let mounted = true;
        const timer = window.setTimeout(() => {
            setState((current) => ({ ...current, loading: true, error: '' }));
            window.axios.get(apiBase('attendanceRecords'), {
                params: {
                    ...filters,
                    search: filters.search || undefined,
                    status: filters.status || undefined,
                    rejection_reason: filters.rejection_reason || undefined,
                    attendance_location_id: filters.attendance_location_id || undefined,
                    date: filters.date || undefined,
                    page,
                    per_page: 20,
                },
            }).then(({ data }) => {
                if (!mounted) return;
                setState({ loading: false, items: data.data.items, meta: data.data.meta, error: '' });
            }).catch((error) => mounted && setState({ loading: false, items: [], meta: {}, error: requestMessage(error, locale) }));
        }, 220);
        return () => { mounted = false; window.clearTimeout(timer); };
    }, [filters, locale, page, refreshKey]);

    const summary = useMemo(() => {
        const accepted = state.items.filter((item) => item.status === 'accepted').length;
        const rejected = state.items.filter((item) => item.status === 'rejected').length;
        const gpsDenied = state.items.filter((item) => item.rejection_reason === 'gps_denied').length;
        return [
            [t(locale, 'total'), state.meta.total ?? state.items.length, t(locale, 'attendanceRecords')],
            [t(locale, 'accepted'), accepted, t(locale, 'status')],
            [t(locale, 'rejected'), rejected, t(locale, 'status')],
            [t(locale, 'gpsDenied'), gpsDenied, t(locale, 'reason')],
        ];
    }, [locale, state.items, state.meta.total]);

    if (detailId) return viewing ? <AttendanceRecordDetailPage record={viewing} locale={locale} onClose={() => onNavigate?.(listPath)} /> : <WorkspaceState icon={state.error ? AlertCircle : RefreshCw} title={state.error || t(locale, 'loading')} loading={!state.error} />;

    return (
        <section className="master-workspace attendance-workspace">
            <div className="master-heading">
                <div>
                    <p className="eyebrow">{t(locale, 'attendance')}</p>
                    <h1>{t(locale, 'attendanceRecords')}</h1>
                    <span className="muted">{t(locale, 'attendanceRecordsHint')}</span>
                </div>
                <ShellPageActions><button className="button" type="button" onClick={() => setRefreshKey((key) => key + 1)}><RefreshCw size={15} /> {t(locale, 'refresh')}</button></ShellPageActions>
            </div>

            <div className="metrics attendance-metrics">
                {summary.map(([label, value, hint]) => <div className="metric" key={label}><span>{label}</span><strong>{value}</strong><small>{hint}</small></div>)}
            </div>

            <div className="master-panel">
                <div className="master-toolbar attendance-record-toolbar">
                    <label className="master-search">
                        <Search size={15} />
                        <input value={filters.search} onChange={(event) => { setFilters((current) => ({ ...current, search: event.target.value })); setPage(1); }} placeholder={t(locale, 'searchRecords')} />
                    </label>
                    <select aria-label={t(locale, 'location')} value={filters.attendance_location_id} onChange={(event) => { setFilters((current) => ({ ...current, attendance_location_id: event.target.value })); setPage(1); }}>
                        <option value="">{t(locale, 'allLocations')}</option>
                        {locations.map((location) => <option value={location.id} key={location.id}>{location.name}</option>)}
                    </select>
                    <select aria-label={t(locale, 'status')} value={filters.status} onChange={(event) => { setFilters((current) => ({ ...current, status: event.target.value })); setPage(1); }}>
                        <option value="">{t(locale, 'allStatuses')}</option>
                        <option value="accepted">{t(locale, 'accepted')}</option>
                        <option value="rejected">{t(locale, 'rejected')}</option>
                    </select>
                    <select aria-label={t(locale, 'reason')} value={filters.rejection_reason} onChange={(event) => { setFilters((current) => ({ ...current, rejection_reason: event.target.value })); setPage(1); }}>
                        <option value="">{t(locale, 'allReasons')}</option>
                        {['outside_allowed_radius', 'gps_denied', 'invalid_employee_id', 'inactive_qr_token', 'invalid_qr_token'].map((reason) => <option value={reason} key={reason}>{t(locale, reason)}</option>)}
                    </select>
                    <label className="date-filter" title={t(locale, 'date')}>
                        <CalendarDays size={14} />
                        <input type="date" value={filters.date} onChange={(event) => { setFilters((current) => ({ ...current, date: event.target.value })); setPage(1); }} />
                    </label>
                    <button className="icon-button" type="button" aria-label={t(locale, 'refresh')} title={t(locale, 'refresh')} onClick={() => setRefreshKey((key) => key + 1)}><Filter size={15} /></button>
                </div>

                {state.error && <div className="inline-error"><AlertCircle size={15} /> {state.error}</div>}
                {state.loading ? <TableLoading columns={6} /> : state.items.length === 0 ? <WorkspaceState icon={Search} title={t(locale, 'empty')} compact /> : (
                    <div className="master-table-wrap">
                        <table className="master-table attendance-table">
                            <thead>
                                <tr>
                                    <th>{t(locale, 'employee')}</th>
                                    <th>{t(locale, 'location')}</th>
                                    <th>{t(locale, 'checkedAt')}</th>
                                    <th>{t(locale, 'distance')}</th>
                                    <th>{t(locale, 'status')}</th>
                                    <th>{t(locale, 'reason')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {state.items.map((record) => (
                                    <tr className="clickable-row" key={record.id} tabIndex={0} onClick={() => onNavigate?.(`${listPath}/${record.id}`)} onKeyDown={(event) => { if (event.target === event.currentTarget && ['Enter', ' '].includes(event.key)) { event.preventDefault(); onNavigate?.(`${listPath}/${record.id}`); } }}>
                                        <td><strong>{record.employee_name || record.entered_employee_code}</strong><span className="muted">{record.employee_code || record.entered_employee_code}</span></td>
                                        <td>{record.location_name || <span className="muted">-</span>}</td>
                                        <td>{formatDateTime(record.attendance_at)}</td>
                                        <td>{meters(record.distance_m)}</td>
                                        <td><StatusBadge status={record.status} locale={locale} /></td>
                                        <td>{record.rejection_reason ? t(locale, record.rejection_reason) : <span className="muted">-</span>}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination meta={state.meta} page={page} setPage={setPage} locale={locale} />
            </div>

        </section>
    );
}

export function AttendanceSummaryScreen({ locale }) {
    const currentMonth = new Date().toISOString().slice(0, 7);
    const [filters, setFilters] = useState({ month: currentMonth, date_from: '', date_to: '', employee_type: '', search: '' });
    const [page, setPage] = useState(1);
    const [state, setState] = useState({ loading: true, items: [], period: {}, totals: {}, meta: {}, error: '' });
    const [refreshKey, setRefreshKey] = useState(0);

    useEffect(() => {
        let mounted = true;
        const timer = window.setTimeout(() => {
            setState((current) => ({ ...current, loading: true, error: '' }));
            window.axios.get(apiBase('attendanceSummary'), {
                params: {
                    month: filters.date_from || filters.date_to ? undefined : filters.month,
                    date_from: filters.date_from || undefined,
                    date_to: filters.date_to || undefined,
                    employee_type: filters.employee_type || undefined,
                    search: filters.search || undefined,
                    page,
                    per_page: 20,
                },
            }).then(({ data }) => {
                if (!mounted) return;
                setState({ loading: false, items: data.data.items, period: data.data.period, totals: data.data.totals, meta: data.data.meta, error: '' });
            }).catch((error) => mounted && setState({ loading: false, items: [], period: {}, totals: {}, meta: {}, error: requestMessage(error, locale) }));
        }, 220);

        return () => { mounted = false; window.clearTimeout(timer); };
    }, [filters, locale, page, refreshKey]);

    const resetDateRange = () => {
        setFilters((current) => ({ ...current, date_from: '', date_to: '' }));
        setPage(1);
    };

    const kpis = [
        [t(locale, 'totalEmployees'), state.totals.employees ?? 0, periodLabel(state.period)],
        [t(locale, 'totalRecords'), state.totals.records ?? 0, t(locale, 'attendanceSummary')],
        [t(locale, 'accepted'), state.totals.accepted ?? 0, t(locale, 'status')],
        [t(locale, 'rejected'), state.totals.rejected ?? 0, t(locale, 'status')],
    ];

    return (
        <section className="master-workspace attendance-workspace">
            <div className="master-heading">
                <div>
                    <p className="eyebrow">{t(locale, 'attendance')}</p>
                    <h1>{t(locale, 'attendanceSummary')}</h1>
                    <span className="muted">{t(locale, 'attendanceSummaryHint')}</span>
                </div>
                <ShellPageActions><button className="button" type="button" onClick={() => setRefreshKey((key) => key + 1)}><RefreshCw size={15} /> {t(locale, 'refresh')}</button></ShellPageActions>
            </div>

            <div className="metrics attendance-metrics">
                {kpis.map(([label, value, hint]) => <div className="metric" key={label}><span>{label}</span><strong>{value}</strong><small>{hint}</small></div>)}
            </div>

            <div className="master-panel">
                <div className="master-toolbar attendance-summary-toolbar">
                    <label className="master-search">
                        <Search size={15} />
                        <input value={filters.search} onChange={(event) => { setFilters((current) => ({ ...current, search: event.target.value })); setPage(1); }} placeholder={t(locale, 'searchSummary')} />
                    </label>
                    <input className="summary-month" aria-label="Month" type="month" value={filters.month} onChange={(event) => { setFilters((current) => ({ ...current, month: event.target.value })); setPage(1); }} disabled={Boolean(filters.date_from || filters.date_to)} />
                    <select aria-label={t(locale, 'employeeType')} value={filters.employee_type} onChange={(event) => { setFilters((current) => ({ ...current, employee_type: event.target.value })); setPage(1); }}>
                        <option value="">{t(locale, 'showAll')}</option>
                        {['office', 'sales', 'sales_supervisor', 'driver', 'warehouse'].map((type) => <option value={type} key={type}>{titleCase(type)}</option>)}
                    </select>
                    <label className="date-filter" title="From"><CalendarDays size={14} /><input type="date" value={filters.date_from} onChange={(event) => { setFilters((current) => ({ ...current, date_from: event.target.value })); setPage(1); }} /></label>
                    <label className="date-filter" title="To"><CalendarDays size={14} /><input type="date" value={filters.date_to} onChange={(event) => { setFilters((current) => ({ ...current, date_to: event.target.value })); setPage(1); }} /></label>
                    {(filters.date_from || filters.date_to) && <button className="button" type="button" onClick={resetDateRange}>{t(locale, 'showAll')}</button>}
                </div>

                {state.error && <div className="inline-error"><AlertCircle size={15} /> {state.error}</div>}
                {state.loading ? <TableLoading columns={8} /> : state.items.length === 0 ? <WorkspaceState icon={Search} title={t(locale, 'empty')} compact /> : (
                    <div className="master-table-wrap">
                        <table className="master-table attendance-table attendance-summary-table">
                            <thead>
                                <tr>
                                    <th>{t(locale, 'employee')}</th>
                                    <th>{t(locale, 'employeeType')}</th>
                                    <th>{t(locale, 'totalRecords')}</th>
                                    <th>{t(locale, 'accepted')}</th>
                                    <th>{t(locale, 'rejected')}</th>
                                    <th>{t(locale, 'gpsDenied')}</th>
                                    <th>{t(locale, 'outside_allowed_radius')}</th>
                                    <th>{t(locale, 'checkedAt')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {state.items.map((row) => (
                                    <tr key={row.employee_id}>
                                        <td><strong>{row.employee_name}</strong><span className="muted">{row.employee_code}</span></td>
                                        <td>{titleCase(row.employee_type)}</td>
                                        <td>{row.total_records}</td>
                                        <td>{row.accepted_count}</td>
                                        <td>{row.rejected_count}</td>
                                        <td>{row.gps_denied_count}</td>
                                        <td>{row.outside_radius_count}</td>
                                        <td><strong>{formatDateTime(row.first_attendance_at)}</strong><span className="muted">{formatDateTime(row.last_attendance_at)}</span></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination meta={state.meta} page={page} setPage={setPage} locale={locale} />
            </div>
        </section>
    );
}

function MobileAttendanceCheckInDialog({ locale, onClose, onRecorded }) {
    const [state, setState] = useState({ loading: true, locations: [], todayRecord: null, error: '' });
    const [locationId, setLocationId] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [result, setResult] = useState(null);

    useEffect(() => {
        const previousOverflow = document.body.style.overflow;
        const handleKeyDown = (event) => event.key === 'Escape' && onClose();
        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', handleKeyDown);

        let mounted = true;
        window.axios.get(`${apiBase('mobileAttendance')}/locations`)
            .then(({ data }) => {
                if (!mounted) return;
                const locations = data.data.locations || [];
                setState({ loading: false, locations, todayRecord: data.data.today_record || null, error: '' });
                if (locations.length === 1) setLocationId(String(locations[0].id));
            })
            .catch((error) => mounted && setState({ loading: false, locations: [], todayRecord: null, error: requestMessage(error, locale) }));

        return () => {
            mounted = false;
            document.body.style.overflow = previousOverflow;
            document.removeEventListener('keydown', handleKeyDown);
        };
    }, [locale]);

    const selectedLocation = state.locations.find((location) => String(location.id) === String(locationId));

    const submitPosition = async (positionPayload) => {
        try {
            const { data } = await window.axios.post(`${apiBase('mobileAttendance')}/check-in`, {
                attendance_location_id: Number(locationId),
                ...positionPayload,
            });
            setResult(data.data.result);
            onRecorded(data.data.result);
        } catch (error) {
            setState((current) => ({ ...current, error: requestMessage(error, locale) }));
        } finally {
            setSubmitting(false);
        }
    };

    const submit = (event) => {
        event.preventDefault();
        if (!locationId || submitting) return;
        setSubmitting(true);
        setState((current) => ({ ...current, error: '' }));

        if (!window.isSecureContext || !navigator.geolocation) {
            submitPosition({ gps_denied: true });
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => submitPosition({
                latitude: Number(position.coords.latitude.toFixed(7)),
                longitude: Number(position.coords.longitude.toFixed(7)),
            }),
            () => submitPosition({ gps_denied: true }),
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
        );
    };

    return (
        <div className="modal-backdrop mobile-attendance-checkin-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && !submitting && onClose()}>
            <form className="master-dialog mobile-attendance-checkin-dialog" role="dialog" aria-modal="true" aria-labelledby="mobile-attendance-title" onSubmit={submit}>
                <header>
                    <div><p className="eyebrow">{t(locale, 'attendance')}</p><h2 id="mobile-attendance-title">Record attendance</h2></div>
                    <button className="icon-button" type="button" aria-label={t(locale, 'cancel')} onClick={onClose} disabled={submitting}><X size={17} /></button>
                </header>
                <div className="master-form-body mobile-attendance-checkin-body">
                    {result ? (
                        <div className={`mobile-attendance-checkin-result ${result.status}`}>
                            {result.status === 'accepted' ? <CheckCircle2 size={32} /> : <AlertCircle size={32} />}
                            <div><h3>{result.already_recorded ? 'Already checked in today' : result.status === 'accepted' ? 'Attendance recorded' : 'Attendance rejected'}</h3><p>{result.warehouse_name} · {formatDateTime(result.attendance_at)}</p></div>
                            {result.rejection_reason && <small>{t(locale, result.rejection_reason)}</small>}
                        </div>
                    ) : state.loading ? (
                        <WorkspaceState icon={RefreshCw} title="Loading warehouses" loading compact />
                    ) : (
                        <>
                            {state.todayRecord && <div className="mobile-attendance-today"><CheckCircle2 size={16} /><span><strong>Today’s attendance is recorded</strong><small>{state.todayRecord.warehouse_name} · {formatDateTime(state.todayRecord.attendance_at)}</small></span></div>}
                            <label className="master-field wide">
                                <span>Warehouse</span>
                                <select required value={locationId} onChange={(event) => setLocationId(event.target.value)} disabled={Boolean(state.todayRecord)}>
                                    <option value="">Select warehouse</option>
                                    {state.locations.map((location) => <option value={location.id} key={location.id}>{location.warehouse_name} · {location.warehouse_code}</option>)}
                                </select>
                            </label>
                            {selectedLocation && <div className="mobile-attendance-location-preview"><MapPinned size={18} /><span><strong>{selectedLocation.location_name}</strong><small>{selectedLocation.location_address || selectedLocation.warehouse_address || 'No address'} · within {selectedLocation.allowed_radius_m} m</small></span></div>}
                            {!state.todayRecord && <p className="mobile-attendance-gps-note"><Crosshair size={16} />Your current GPS position will be checked against this warehouse attendance point.</p>}
                            {state.locations.length === 0 && !state.error && <div className="inline-error"><AlertCircle size={15} />No warehouse attendance point is available. Ask Office to configure one.</div>}
                            {state.error && <div className="inline-error"><AlertCircle size={15} />{state.error}</div>}
                        </>
                    )}
                </div>
                <footer>
                    <button className="button" type="button" onClick={onClose} disabled={submitting}>{result || state.todayRecord ? 'Close' : t(locale, 'cancel')}</button>
                    {!result && !state.todayRecord && <button className="button primary" type="submit" disabled={submitting || state.loading || !locationId}><Crosshair size={15} />{submitting ? 'Getting GPS…' : 'Check in now'}</button>}
                </footer>
            </form>
        </div>
    );
}

export function MobileAttendanceHistoryScreen({ locale }) {
    const today = new Date();
    const [month, setMonth] = useState(`${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}`);
    const [selectedDate, setSelectedDate] = useState('');
    const [state, setState] = useState({ loading: true, items: [], summary: { accepted: 0, rejected: 0, total: 0 }, error: '' });
    const [selected, setSelected] = useState(null);
    const [checkInOpen, setCheckInOpen] = useState(false);
    const [refreshKey, setRefreshKey] = useState(0);

    useEffect(() => {
        let mounted = true;
        setState((current) => ({ ...current, loading: true, error: '' }));
        window.axios.get(`${apiBase('mobileAttendance')}/records`, {
            params: {
                month,
                per_page: 100,
            },
        }).then(({ data }) => {
            if (!mounted) return;
            setState({ loading: false, items: data.data.items, summary: data.data.summary, error: '' });
        }).catch((error) => mounted && setState({ loading: false, items: [], summary: { accepted: 0, rejected: 0, total: 0 }, error: requestMessage(error, locale) }));

        return () => { mounted = false; };
    }, [locale, month, refreshKey]);

    const [year, monthNumber] = month.split('-').map(Number);
    const monthDate = new Date(year, monthNumber - 1, 1);
    const daysInMonth = new Date(year, monthNumber, 0).getDate();
    const leadingDays = monthDate.getDay();
    const recordsByDate = useMemo(() => state.items.reduce((days, record) => {
        const key = String(record.attendance_at || '').slice(0, 10);
        if (!days[key]) days[key] = [];
        days[key].push(record);
        return days;
    }, {}), [state.items]);
    const calendarCells = Array.from({ length: Math.ceil((leadingDays + daysInMonth) / 7) * 7 }, (_, index) => {
        const day = index - leadingDays + 1;
        if (day < 1 || day > daysInMonth) return null;
        const date = `${month}-${String(day).padStart(2, '0')}`;
        const records = recordsByDate[date] || [];
        return { day, date, records, accepted: records.some((record) => record.status === 'accepted'), rejected: records.some((record) => record.status === 'rejected') };
    });
    const visibleRecords = selectedDate ? (recordsByDate[selectedDate] || []) : state.items;
    const recordedDays = Object.keys(recordsByDate).length;
    const acceptedDays = Object.values(recordsByDate).filter((records) => records.some((record) => record.status === 'accepted')).length;
    const changeMonth = (offset) => {
        const next = new Date(year, monthNumber - 1 + offset, 1);
        setMonth(`${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, '0')}`);
        setSelectedDate('');
    };

    return (
        <div className="mobile-master-stack mobile-attendance-history">
            <div className="mobile-master-heading">
                <div>
                    <p className="eyebrow">{t(locale, 'attendance')}</p>
                    <h1>{t(locale, 'attendanceHistory')}</h1>
                    <span className="muted">Monthly check-in calendar and history.</span>
                </div>
                <ShellPageActions className="mobile-attendance-heading-actions">
                    <button className="button primary" type="button" onClick={() => setCheckInOpen(true)}><Crosshair size={16} />Check in</button>
                    <button className="icon-button" type="button" aria-label={t(locale, 'refresh')} title={t(locale, 'refresh')} onClick={() => setRefreshKey((key) => key + 1)}><RefreshCw size={18} /></button>
                </ShellPageActions>
            </div>

            <section className="mobile-attendance-summary" aria-label={t(locale, 'attendanceHistory')}>
                <div><small>Recorded days</small><strong>{recordedDays}</strong></div>
                <div><small>Present days</small><strong>{acceptedDays}</strong></div>
                <div><small>{t(locale, 'rejected')}</small><strong>{state.summary.rejected}</strong></div>
            </section>

            <section className="mobile-master-section mobile-attendance-calendar-section">
                <div className="mobile-calendar-heading">
                    <button className="icon-button" type="button" aria-label="Previous month" onClick={() => changeMonth(-1)}><ChevronLeft size={18} /></button>
                    <strong>{new Intl.DateTimeFormat(locale === 'my' ? 'my-MM' : 'en-US', { month: 'long', year: 'numeric' }).format(monthDate)}</strong>
                    <button className="icon-button" type="button" aria-label="Next month" onClick={() => changeMonth(1)}><ChevronRight size={18} /></button>
                </div>
                <div className="mobile-attendance-calendar" role="grid" aria-label="Attendance calendar">
                    {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((day) => <span className="mobile-calendar-weekday" key={day}>{day}</span>)}
                    {calendarCells.map((cell, index) => cell ? <button key={cell.date} type="button" className={`${cell.accepted ? 'is-accepted' : cell.rejected ? 'is-rejected' : ''} ${selectedDate === cell.date ? 'is-selected' : ''}`} onClick={() => setSelectedDate((value) => value === cell.date ? '' : cell.date)} aria-label={`${cell.date}, ${cell.records.length} attendance records`}><span>{cell.day}</span>{cell.records.length > 0 && <small>{cell.records.length}</small>}</button> : <span className="mobile-calendar-empty" key={`empty-${index}`} />)}
                </div>
                <div className="mobile-calendar-legend"><span><i className="accepted" />Present</span><span><i className="rejected" />Rejected only</span><span><i />No record</span></div>
            </section>

            <section className="mobile-master-section">
                <div className="mobile-section-heading"><div><p className="eyebrow">{selectedDate || month}</p><h2>{selectedDate ? 'Daily check-ins' : 'Monthly records'}</h2></div>{selectedDate && <button className="button" type="button" onClick={() => setSelectedDate('')}>{t(locale, 'showAll')}</button>}</div>

                {state.error ? <WorkspaceState icon={AlertCircle} title={state.error} action={() => setRefreshKey((key) => key + 1)} actionLabel={t(locale, 'retry')} compact /> : state.loading ? <WorkspaceState icon={RefreshCw} title={t(locale, 'loading')} loading compact /> : visibleRecords.length === 0 ? <WorkspaceState icon={CalendarDays} title="No attendance record for this period." compact /> : (
                    <div className="mobile-attendance-list">
                        {visibleRecords.map((record) => (
                            <button type="button" key={record.id} onClick={() => setSelected(record)}>
                                <span className={`attendance-dot ${record.status}`} />
                                <span>
                                    <strong>{record.warehouse_name || record.location_name || t(locale, 'location')}</strong>
                                    <small>{formatDateTime(record.attendance_at)}</small>
                                    <small>{record.rejection_reason ? t(locale, record.rejection_reason) : `${t(locale, 'distance')}: ${metersText(record.distance_m ?? 0)}`}</small>
                                </span>
                                <StatusBadge status={record.status} locale={locale} />
                            </button>
                        ))}
                    </div>
                )}
            </section>

            {selected && <MobileAttendanceDetailSheet record={selected} locale={locale} onClose={() => setSelected(null)} />}
            {checkInOpen && <MobileAttendanceCheckInDialog locale={locale} onClose={() => setCheckInOpen(false)} onRecorded={(record) => setState((current) => { const exists = current.items.some((item) => item.id === record.id); const previous = current.items.find((item) => item.id === record.id); return { ...current, items: exists ? current.items.map((item) => item.id === record.id ? { ...item, ...record } : item) : [record, ...current.items], summary: { ...current.summary, total: Number(current.summary.total || 0) + (exists ? 0 : 1), accepted: Number(current.summary.accepted || 0) - (previous?.status === 'accepted' ? 1 : 0) + (record.status === 'accepted' ? 1 : 0), rejected: Number(current.summary.rejected || 0) - (previous?.status === 'rejected' ? 1 : 0) + (record.status === 'rejected' ? 1 : 0) } }; })} />}
        </div>
    );
}

function LocationDialog({ editing, warehouses = [], locale, onClose, onSaved }) {
    const [values, setValues] = useState(editing.values);
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState('');
    const [saving, setSaving] = useState(false);
    const [gps, setGps] = useState({ status: 'locating', latitude: null, longitude: null, accuracy: null, error: '' });
    const [gpsRequestKey, setGpsRequestKey] = useState(0);
    const dialogRef = useRef(null);

    useEffect(() => {
        const previousOverflow = document.body.style.overflow;
        const handleKeyDown = (event) => event.key === 'Escape' && onClose();
        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', handleKeyDown);
        window.requestAnimationFrame(() => dialogRef.current?.querySelector('input, textarea')?.focus());
        return () => {
            document.body.style.overflow = previousOverflow;
            document.removeEventListener('keydown', handleKeyDown);
        };
    }, [onClose]);

    useEffect(() => {
        setGps({ status: 'locating', latitude: null, longitude: null, accuracy: null, error: '' });
        if (!window.isSecureContext) {
            setGps({ status: 'error', latitude: null, longitude: null, accuracy: null, error: t(locale, 'gpsSecureContext') });
            return undefined;
        }
        if (!navigator.geolocation) {
            setGps({ status: 'error', latitude: null, longitude: null, accuracy: null, error: t(locale, 'gpsUnavailable') });
            return undefined;
        }

        const watchId = navigator.geolocation.watchPosition((position) => {
            const next = {
                status: 'ready',
                latitude: Number(position.coords.latitude.toFixed(7)),
                longitude: Number(position.coords.longitude.toFixed(7)),
                accuracy: Math.round(position.coords.accuracy),
                error: '',
            };
            setGps(next);
            if (editing.mode === 'create') {
                setValues((current) => ({ ...current, latitude: next.latitude, longitude: next.longitude }));
            }
        }, (error) => {
            const denied = error.code === error.PERMISSION_DENIED;
            setGps((current) => ({ ...current, status: 'error', error: denied ? t(locale, 'gpsDenied') : t(locale, 'gpsUnavailable') }));
        }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 5000 });

        return () => navigator.geolocation.clearWatch(watchId);
    }, [editing.mode, gpsRequestKey, locale]);

    const setField = (field, value) => setValues((current) => ({ ...current, [field]: value }));
    const fieldError = (field) => errors[field]?.[0] || '';

    const submit = async (event) => {
        event.preventDefault();
        setSaving(true);
        setErrors({});
        setMessage('');
        const payload = {
            warehouse_id: Number(values.warehouse_id),
            code: values.code || null,
            name: values.name,
            address: values.address || null,
            latitude: values.latitude === '' ? null : Number(values.latitude),
            longitude: values.longitude === '' ? null : Number(values.longitude),
            allowed_radius_m: Number(values.allowed_radius_m),
            is_active: Boolean(values.is_active),
        };

        try {
            const { data } = editing.mode === 'create'
                ? await window.axios.post(apiBase('attendanceLocations'), payload)
                : await window.axios.put(`${apiBase('attendanceLocations')}/${values.id}`, payload);
            onSaved(data.data.location);
        } catch (error) {
            setErrors(error.response?.data?.errors || {});
            setMessage(requestMessage(error, locale, 'saveError'));
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="modal-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
            <form ref={dialogRef} className="master-dialog attendance-location-dialog" role="dialog" aria-modal="true" aria-labelledby="location-dialog-title" onSubmit={submit}>
                <header>
                    <div><p className="eyebrow">{editing.mode === 'create' ? t(locale, 'addLocation') : t(locale, 'edit')}</p><h2 id="location-dialog-title">{t(locale, 'attendanceLocations')}</h2></div>
                    <button className="icon-button" type="button" aria-label={t(locale, 'cancel')} onClick={onClose}><X size={17} /></button>
                </header>
                <div className="master-form-body attendance-location-editor">
                    <div className="master-form-grid attendance-location-fields">
                        <label className="master-field wide">
                            <span>{t(locale, 'warehouse')}<b aria-hidden="true"> *</b></span>
                            <select required value={values.warehouse_id || ''} onChange={(event) => setField('warehouse_id', event.target.value)}>
                                <option value="">{t(locale, 'selectWarehouse')}</option>
                                {warehouses.map((warehouse) => <option value={warehouse.id} key={warehouse.id}>{warehouse.name} · {warehouse.code}</option>)}
                            </select>
                            {fieldError('warehouse_id') && <small>{fieldError('warehouse_id')}</small>}
                        </label>
                        <FormField label={t(locale, 'code')} value={values.code} error={fieldError('code')} onChange={(value) => setField('code', value)} />
                        <FormField label={t(locale, 'name')} value={values.name} error={fieldError('name')} required onChange={(value) => setField('name', value)} />
                        <FormField label={t(locale, 'address')} value={values.address} error={fieldError('address')} textarea onChange={(value) => setField('address', value)} />
                        <FormField label={t(locale, 'latitude')} type="number" value={values.latitude} error={fieldError('latitude')} required onChange={(value) => setField('latitude', value)} />
                        <FormField label={t(locale, 'longitude')} type="number" value={values.longitude} error={fieldError('longitude')} required onChange={(value) => setField('longitude', value)} />
                        <FormField label={t(locale, 'allowedRadius')} type="number" value={values.allowed_radius_m} error={fieldError('allowed_radius_m')} required onChange={(value) => setField('allowed_radius_m', value)} />
                        <label className="toggle-field"><input type="checkbox" checked={Boolean(values.is_active)} onChange={(event) => setField('is_active', event.target.checked)} /><span><CheckCircle2 size={13} /></span>{t(locale, 'active')}</label>
                    </div>
                    <aside className="attendance-gps-panel">
                        <div className="attendance-gps-status">
                            <span className={`attendance-gps-icon ${gps.status}`}><Crosshair size={16} /></span>
                            <span><strong>{gps.status === 'ready' ? t(locale, 'gpsReady') : gps.status === 'locating' ? t(locale, 'gpsLocating') : t(locale, 'gpsUnavailable')}</strong><small>{gps.status === 'ready' ? `${gps.latitude}, ${gps.longitude} | ${t(locale, 'accuracy')} ${gps.accuracy} m` : gps.error}</small></span>
                        </div>
                        {gps.status === 'ready' && editing.mode === 'edit' && <button className="button attendance-use-gps" type="button" onClick={() => setValues((current) => ({ ...current, latitude: gps.latitude, longitude: gps.longitude }))}><Crosshair size={14} />{t(locale, 'useCurrentGps')}</button>}
                        {gps.status === 'error' && <button className="button attendance-use-gps" type="button" onClick={() => setGpsRequestKey((current) => current + 1)}><RefreshCw size={14} />{t(locale, 'retry')}</button>}
                        <AttendanceLocationMap latitude={gps.latitude ?? values.latitude} longitude={gps.longitude ?? values.longitude} name={values.name || t(locale, 'locationMap')} compact />
                    </aside>
                    {message && <div className="inline-error"><AlertCircle size={15} /> {message}</div>}
                </div>
                <footer>
                    <button className="button" type="button" onClick={onClose}>{t(locale, 'cancel')}</button>
                    <button className="button primary" type="submit" disabled={saving}><Save size={15} /> {t(locale, 'save')}</button>
                </footer>
            </form>
        </div>
    );
}

function FormField({ label, value, error, onChange, type = 'text', textarea = false, required = false }) {
    return (
        <label className={`master-field ${textarea ? 'wide' : ''}`}>
            <span>{label}{required && <b aria-hidden="true"> *</b>}</span>
            {textarea ? <textarea rows="3" value={value ?? ''} onChange={(event) => onChange(event.target.value)} /> : <input type={type} step={type === 'number' ? 'any' : undefined} value={value ?? ''} onChange={(event) => onChange(event.target.value)} />}
            {error && <small>{error}</small>}
        </label>
    );
}

function AttendanceLocationMap({ latitude, longitude, name, compact = false }) {
    const lat = Number(latitude);
    const lng = Number(longitude);
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
        return <div className={`attendance-location-map is-empty ${compact ? 'compact' : ''}`}><MapPinned size={24} /><span>Waiting for GPS coordinates</span></div>;
    }
    const delta = 0.0035;
    const bbox = `${lng - delta},${lat - delta},${lng + delta},${lat + delta}`;
    const source = `https://www.openstreetmap.org/export/embed.html?bbox=${encodeURIComponent(bbox)}&layer=mapnik&marker=${encodeURIComponent(`${lat},${lng}`)}`;

    return <div className={`attendance-location-map ${compact ? 'compact' : ''}`}>
        <iframe title={`${name} map`} src={source} loading="lazy" referrerPolicy="no-referrer" />
        <span><MapPinned size={13} /><strong>{lat.toFixed(7)}, {lng.toFixed(7)}</strong></span>
    </div>;
}

function LocationDetailPage({ location, locale, canManage, onClose, onEdit, onRotate, onCopy }) {
    const qrRef = useRef(null);

    const copyLink = async () => {
        await copyText(location.public_url);
        onCopy();
    };

    return (
        <DetailPage eyebrow={t(locale, 'attendanceLocations')} title={location.name} subtitle={<span className="qr-location-heading-meta"><span>{location.code}</span><StatusBadge status={location.is_active ? 'active' : 'inactive'} locale={locale} /></span>} onBack={onClose}
        >
            <div className="qr-location-page-grid">
                <div className="qr-location-page-column">
                    <DetailPanel eyebrow={t(locale, 'qrCode')} title={location.name} className="qr-location-qr-panel">
                        <section className="qr-preview record-page-qr">
                            <div><span className="qr-location-section-label"><QrCode size={18} /> {t(locale, 'qrCode')}</span><strong>{location.code}</strong></div>
                            <span className="qr-art" ref={qrRef}>
                                <QRCodeSVG value={location.public_url} size={220} level="M" marginSize={4} title={`${location.name} ${t(locale, 'qrCode')}`} />
                            </span>
                            <p>{location.public_url}</p>
                        </section>
                    </DetailPanel>

                    <DetailPanel eyebrow={t(locale, 'details')} title={location.warehouse?.name || location.name} className="qr-location-info-panel">
                        <dl className="record-page-facts">
                            <InfoRow label={t(locale, 'warehouse')} value={location.warehouse?.name || '-'} />
                            <InfoRow label={t(locale, 'allowedRadius')} value={meters(location.allowed_radius_m)} />
                            <InfoRow label={t(locale, 'address')} value={location.address || '-'} />
                            <InfoRow label={t(locale, 'latitude')} value={location.latitude} />
                            <InfoRow label={t(locale, 'longitude')} value={location.longitude} />
                        </dl>
                    </DetailPanel>
                </div>

                <div className="qr-location-page-column">
                    <DetailPanel className="qr-location-map-panel">
                        <section className="attendance-location-detail-map"><div><p className="eyebrow">{t(locale, 'locationMap')}</p><strong>{location.address || location.name}</strong></div><AttendanceLocationMap latitude={location.latitude} longitude={location.longitude} name={location.name} /></section>
                    </DetailPanel>

                    <DetailPanel eyebrow={t(locale, 'actions')} className="qr-location-action-panel">
                        <div className="qr-location-actions">
                            <button className="button" type="button" onClick={copyLink}><Copy size={15} /> {t(locale, 'copy')}</button>
                            <button className="button" type="button" onClick={() => downloadQrSvg(location, qrRef.current)}><Download size={15} /> {t(locale, 'download')}</button>
                            <button className="button" type="button" onClick={() => printQr(location, qrRef.current?.querySelector('svg')?.outerHTML || '', locale)}><Printer size={15} /> {t(locale, 'print')}</button>
                            <a className="button" href={location.public_url} target="_blank" rel="noreferrer"><ExternalLink size={15} /> {t(locale, 'open')}</a>
                            {canManage && <button className="button primary" type="button" onClick={onEdit}><Pencil size={15} /> {t(locale, 'edit')}</button>}
                            {canManage && <button className="button" type="button" onClick={onRotate}><RotateCcw size={15} /> {t(locale, 'rotate')}</button>}
                        </div>
                    </DetailPanel>
                </div>
            </div>
        </DetailPage>
    );
}

function AttendanceRecordDetailPage({ record, locale, onClose }) {
    return (
        <DetailPage eyebrow={t(locale, 'attendanceRecords')} title={record.employee_name || record.entered_employee_code} subtitle={formatDateTime(record.attendance_at)} onBack={onClose}
            aside={<DetailPanel eyebrow={t(locale, 'status')}><div className="record-page-summary"><span>{t(locale, 'status')}</span><strong><StatusBadge status={record.status} locale={locale} /></strong><small className="muted">{record.location_name || '-'}</small></div></DetailPanel>}
        >
            <DetailPanel eyebrow={t(locale, 'details')} title={t(locale, 'attendance')}>
                <dl className="record-page-facts">
                    <InfoRow label={t(locale, 'employee')} value={record.employee_name || '-'} />
                    <InfoRow label={t(locale, 'employeeCode')} value={record.employee_code || record.entered_employee_code} />
                    <InfoRow label={t(locale, 'location')} value={record.location_name || '-'} />
                    <InfoRow label={t(locale, 'checkedAt')} value={formatDateTime(record.attendance_at)} />
                    <InfoRow label={t(locale, 'status')} value={<StatusBadge status={record.status} locale={locale} />} />
                    <InfoRow label={t(locale, 'reason')} value={record.rejection_reason ? t(locale, record.rejection_reason) : '-'} />
                    <InfoRow label={t(locale, 'distance')} value={meters(record.distance_m)} />
                    <InfoRow label={t(locale, 'latitude')} value={record.latitude ?? '-'} />
                    <InfoRow label={t(locale, 'longitude')} value={record.longitude ?? '-'} />
                </dl>
            </DetailPanel>
        </DetailPage>
    );
}

function MobileAttendanceDetailSheet({ record, locale, onClose }) {
    return (
        <div className="drawer-backdrop mobile" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
            <aside className="mobile-detail-sheet" role="dialog" aria-modal="true" aria-label={t(locale, 'details')}>
                <header><div><p className="eyebrow">{t(locale, 'details')}</p><h2>{record.warehouse_name || record.location_name || t(locale, 'attendance')}</h2></div><button className="icon-button" type="button" onClick={onClose} aria-label={t(locale, 'cancel')}><X size={17} /></button></header>
                <dl className="mobile-info-list">
                    <InfoRow label={t(locale, 'location')} value={record.location_name || '-'} />
                    <InfoRow label={t(locale, 'checkedAt')} value={formatDateTime(record.attendance_at)} />
                    <InfoRow label={t(locale, 'status')} value={<StatusBadge status={record.status} locale={locale} />} />
                    <InfoRow label={t(locale, 'reason')} value={record.rejection_reason ? t(locale, record.rejection_reason) : '-'} />
                    <InfoRow label={t(locale, 'distance')} value={meters(record.distance_m)} />
                    <InfoRow label={t(locale, 'latitude')} value={record.latitude ?? '-'} />
                    <InfoRow label={t(locale, 'longitude')} value={record.longitude ?? '-'} />
                </dl>
            </aside>
        </div>
    );
}

function InfoRow({ label, value }) {
    return <div><dt>{label}</dt><dd>{value}</dd></div>;
}

function StatusBadge({ status, locale }) {
    const normalized = status === 'accepted' || status === 'active' ? 'success' : status === 'rejected' ? 'danger' : 'neutral';
    const label = status === 'active' ? t(locale, 'active') : status === 'inactive' ? t(locale, 'inactive') : t(locale, status);
    return <span className={`status ${normalized}`}>{label}</span>;
}

function TableLoading({ columns }) {
    return <div className="master-table-wrap"><table className="master-table"><tbody>{[0, 1, 2, 3, 4].map((row) => <tr key={row}>{Array.from({ length: columns }).map((_, column) => <td key={column}><span className="table-skeleton" /></td>)}</tr>)}</tbody></table></div>;
}

function WorkspaceState({ icon: Icon, title, action, actionLabel, loading = false, compact = false }) {
    return <div className={`workspace-state ${compact ? 'compact' : ''}`}><Icon className={loading ? 'spin' : ''} size={22} /><strong>{title}</strong>{action && <button className="button" type="button" onClick={action}>{actionLabel}</button>}</div>;
}

function Pagination({ meta = {}, page, setPage, locale }) {
    if (!meta.last_page || meta.last_page <= 1) return null;
    return <div className="master-pagination"><span>{t(locale, 'page')} {meta.current_page} / {meta.last_page} - {meta.total}</span><div><button type="button" disabled={page <= 1} onClick={() => setPage((value) => value - 1)} aria-label="Previous page"><ChevronLeft size={16} /></button><button type="button" disabled={page >= meta.last_page} onClick={() => setPage((value) => value + 1)} aria-label="Next page"><ChevronRight size={16} /></button></div></div>;
}

function meters(value) {
    if (value === null || value === undefined || value === '') return <span className="muted">-</span>;
    return metersText(value);
}

function metersText(value) {
    return `${Number(value).toLocaleString()} m`;
}

function formatDateTime(value) {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return date.toLocaleString([], { year: 'numeric', month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit' });
}

function periodLabel(period = {}) {
    if (!period.start || !period.end) return '';
    return period.start === period.end ? period.start : `${period.start} - ${period.end}`;
}

function titleCase(value) {
    return String(value || '').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

async function copyText(value) {
    if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(value);
        return;
    }
    const textarea = document.createElement('textarea');
    textarea.value = value;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand('copy');
    document.body.removeChild(textarea);
}

function downloadQrSvg(location, wrapper) {
    const svg = wrapper?.querySelector('svg');
    if (!svg) return;
    const blob = new Blob([svg.outerHTML], { type: 'image/svg+xml;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = `${location.code || 'attendance'}-qr.svg`;
    document.body.appendChild(anchor);
    anchor.click();
    document.body.removeChild(anchor);
    URL.revokeObjectURL(url);
}

function printQr(location, qrMarkup, locale) {
    const printWindow = window.open('', '_blank', 'noopener,noreferrer,width=420,height=640');
    if (!printWindow) return;
    printWindow.document.write(`<!doctype html><html><head><title>${location.name}</title><style>body{font-family:Inter,Arial,sans-serif;margin:28px;text-align:center;color:#172033}svg{width:260px;height:260px}h1{font-size:22px;margin:16px 0 4px}p{overflow-wrap:anywhere;color:#69768a;font-size:12px}.meta{margin-top:8px;font-weight:700;color:#087f74}</style></head><body>${qrMarkup}<h1>${location.name}</h1><div class="meta">${location.code || ''} - ${metersText(location.allowed_radius_m)}</div><p>${location.public_url}</p></body></html>`);
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
}
