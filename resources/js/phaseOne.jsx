import {
    AlertCircle,
    Building2,
    Check,
    ChevronLeft,
    ChevronRight,
    Eye,
    MapPinned,
    Pencil,
    Plus,
    RefreshCw,
    Save,
    Search,
    Store,
    Trash2,
    Truck,
    User,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

const uiCopy = {
    en: {
        add: 'Add', edit: 'Edit', save: 'Save changes', cancel: 'Cancel', search: 'Search master data',
        allStatuses: 'All statuses', active: 'Active', inactive: 'Inactive', actions: 'Actions', details: 'Details',
        loading: 'Loading records', empty: 'No records match this view.', retry: 'Retry', delete: 'Delete',
        deleteConfirm: 'Delete this record? Records used elsewhere should be set inactive instead.', page: 'Page', of: 'of',
        assignedCustomers: 'Assigned Customers', assignedRoute: 'Assigned route', registerCustomer: 'Register Customer',
        customerProfile: 'Customer Profile', driverProfile: 'Driver Profile', assignedVehicle: 'Assigned Vehicle',
        noVehicle: 'No active vehicle is assigned.', noProfile: 'No profile record is linked to this account.',
        registrationSuccess: 'Customer registered successfully.', contact: 'Contact', shop: 'Shop information',
        companySettings: 'Company Settings', companySettingsHint: 'Business identity and contact details.',
        businessIdentity: 'Business Identity', settings: 'Settings', saved: 'Company settings saved.',
        masterData: 'Master Data', select: 'Select...', yes: 'Yes', no: 'No',
        loadSetupError: 'Unable to load master data setup.', loadRecordsError: 'Unable to load records.',
        screenNotFound: 'Master data screen not found.', deleteError: 'Unable to delete record.', saveError: 'Unable to save record.',
        loadCompanyError: 'Unable to load company settings.', saveCompanyError: 'Unable to save company settings.',
        loadCustomersError: 'Unable to load customers.', registerCustomerError: 'Unable to register customer.',
        loadDataError: 'Unable to load data.', previousPage: 'Previous page', nextPage: 'Next page',
    },
    my: {
        add: 'အသစ်ထည့်ရန်', edit: 'ပြင်ဆင်ရန်', save: 'သိမ်းမည်', cancel: 'မလုပ်တော့ပါ', search: 'Master data ရှာရန်',
        allStatuses: 'အခြေအနေအားလုံး', active: 'အသုံးပြုနေသည်', inactive: 'ပိတ်ထားသည်', actions: 'လုပ်ဆောင်ချက်', details: 'အသေးစိတ်',
        loading: 'အချက်အလက် တင်နေသည်', empty: 'ဤစာရင်းတွင် အချက်အလက်မရှိပါ။', retry: 'ပြန်ကြိုးစားရန်', delete: 'ဖျက်ရန်',
        deleteConfirm: 'ဤအချက်အလက်ကို ဖျက်မည်လား။ အခြားနေရာတွင် အသုံးပြုထားပါက ပိတ်ထားသည့်အခြေအနေသို့ ပြောင်းသင့်သည်။', page: 'စာမျက်နှာ', of: '/',
        assignedCustomers: 'သတ်မှတ်ထားသော ဖောက်သည်များ', assignedRoute: 'သတ်မှတ် route', registerCustomer: 'ဖောက်သည်အသစ်စာရင်းသွင်းရန်',
        customerProfile: 'ဖောက်သည်ပရိုဖိုင်', driverProfile: 'ယာဉ်မောင်းပရိုဖိုင်', assignedVehicle: 'သတ်မှတ်ယာဉ်',
        noVehicle: 'အသုံးပြုနေသောယာဉ် မသတ်မှတ်ရသေးပါ။', noProfile: 'ဤအကောင့်နှင့် ချိတ်ထားသော ပရိုဖိုင်မရှိပါ။',
        registrationSuccess: 'ဖောက်သည်အသစ် စာရင်းသွင်းပြီးပါပြီ။', contact: 'ဆက်သွယ်ရန်', shop: 'ဆိုင်အချက်အလက်',
        companySettings: 'ကုမ္ပဏီ ဆက်တင်များ', companySettingsHint: 'လုပ်ငန်းနှင့် ဆက်သွယ်ရန် အချက်အလက်များ။',
        businessIdentity: 'လုပ်ငန်းအချက်အလက်', settings: 'ဆက်တင်များ', saved: 'ကုမ္ပဏီအချက်အလက် သိမ်းပြီးပါပြီ။',
        masterData: 'အခြေခံအချက်အလက်', select: 'ရွေးချယ်ပါ...', yes: 'ဟုတ်သည်', no: 'မဟုတ်ပါ',
        loadSetupError: 'အခြေခံအချက်အလက် စနစ်ကို ဖွင့်၍မရပါ။', loadRecordsError: 'အချက်အလက်များကို ဖွင့်၍မရပါ။',
        screenNotFound: 'အခြေခံအချက်အလက် စာမျက်နှာကို မတွေ့ပါ။', deleteError: 'အချက်အလက်ကို ဖျက်၍မရပါ။', saveError: 'အချက်အလက်ကို သိမ်း၍မရပါ။',
        loadCompanyError: 'ကုမ္ပဏီအချက်အလက်ကို ဖွင့်၍မရပါ။', saveCompanyError: 'ကုမ္ပဏီအချက်အလက်ကို သိမ်း၍မရပါ။',
        loadCustomersError: 'ဖောက်သည်များကို ဖွင့်၍မရပါ။', registerCustomerError: 'ဖောက်သည်အသစ်ကို စာရင်းသွင်း၍မရပါ။',
        loadDataError: 'အချက်အလက်ကို ဖွင့်၍မရပါ။', previousPage: 'ယခင်စာမျက်နှာ', nextPage: 'နောက်စာမျက်နှာ',
    },
};

const myResourceLabels = {
    areas: 'ဧရိယာများ', routes: 'Route များ', warehouses: 'ဂိုဒေါင်များ',
    brands: 'Brand များ', products: 'ကုန်ပစ္စည်းများ', 'price-types': 'ဈေးနှုန်းအမျိုးအစား',
    'product-prices': 'ကုန်ပစ္စည်းဈေးနှုန်း', customers: 'ဖောက်သည်များ', employees: 'ဝန်ထမ်းများ',
    vehicles: 'ယာဉ်များ', roles: 'Role နှင့် Permission', permissions: 'Permission များ',
};

const myResourceDescriptions = {
    areas: 'ပို့ဆောင်ရေးနှင့် ဖောက်သည်များအတွက် ဝန်ဆောင်မှုဧရိယာများ။',
    routes: 'အရောင်းနှင့် ပို့ဆောင်ရေး ဝန်ဆောင်မှုလမ်းကြောင်းများ။',
    warehouses: 'ကုန်ပစ္စည်းသိုလှောင်ရာ နေရာများ။',
    brands: 'ကုန်ပစ္စည်း Brand သတ်မှတ်ချက်များ။',
    products: 'ရောင်းချနိုင်သော ရေနှင့် ဆက်စပ်ကုန်ပစ္စည်းများ။',
    'price-types': 'လက်လီ၊ လက်ကားနှင့် အထူးဈေးနှုန်းအဆင့်များ။',
    'product-prices': 'ကုန်ပစ္စည်းနှင့် ဖောက်သည်ဈေးနှုန်းအမျိုးအစားအလိုက် ဈေးနှုန်းများ။',
    customers: 'ပြန်လည်ရောင်းချသူဆိုင်၊ ဆက်သွယ်သူ၊ လမ်းကြောင်းနှင့် အကြွေးသတ်မှတ်ချက်များ။',
    employees: 'ရုံး၊ ဂိုဒေါင်၊ အရောင်းနှင့် ယာဉ်မောင်း ဝန်ထမ်းအချက်အလက်များ။',
    vehicles: 'ပို့ဆောင်ရေးယာဉ်နှင့် သတ်မှတ်ယာဉ်မောင်းများ။',
    roles: 'App Role နှင့် အသုံးပြုခွင့်သတ်မှတ်ချက်များ။',
    permissions: 'Role များတွင် အသုံးပြုသည့် Permission စာရင်း။',
};

const myOptionLabels = {
    Monday: 'တနင်္လာ', Tuesday: 'အင်္ဂါ', Wednesday: 'ဗုဒ္ဓဟူး', Thursday: 'ကြာသပတေး',
    Friday: 'သောကြာ', Saturday: 'စနေ', Sunday: 'တနင်္ဂနွေ',
    office: 'ရုံးဝန်ထမ်း', sales: 'အရောင်းဝန်ထမ်း', driver: 'ယာဉ်မောင်း', warehouse: 'ဂိုဒေါင်ဝန်ထမ်း',
    truck: 'ကုန်တင်ယာဉ်', van: 'ဗန်ကား', motorbike: 'ဆိုင်ကယ်', other: 'အခြား',
};

const myFieldLabels = {
    allowed_apps: 'အသုံးပြုနိုင်သော App များ',
    code: 'ကုဒ်', name: 'အမည်', legal_name: 'တရားဝင်အမည်', phone: 'ဖုန်း', email: 'အီးမေးလ်',
    password: 'စကားဝှက်', password_confirmation: 'စကားဝှက် အတည်ပြုရန်',
    registration_no: 'မှတ်ပုံတင်အမှတ်', tax_no: 'အခွန်အမှတ်', address: 'လိပ်စာ', city: 'မြို့', state: 'ပြည်နယ်',
    is_active: 'အခြေအနေ', description: 'ဖော်ပြချက်', area_id: 'ဧရိယာ', service_day: 'ဝန်ဆောင်မှုနေ့',
    brand_id: 'Brand', sku: 'SKU', unit: 'ယူနစ်', size: 'အရွယ်အစား', currency: 'ငွေကြေး', is_default: 'မူလဈေးနှုန်း',
    product_id: 'ကုန်ပစ္စည်း', price_type_id: 'ဈေးနှုန်းအမျိုးအစား', amount: 'ငွေပမာဏ', effective_from: 'စတင်သက်ရောက်ရက်',
    route_id: 'Route', shop_name: 'ဆိုင်အမည်', contact_name: 'ဆက်သွယ်သူ', credit_limit: 'အကြွေးကန့်သတ်ချက်',
    assigned_route_id: 'သတ်မှတ် Route', employee_type: 'ဝန်ထမ်းအမျိုးအစား', hire_date: 'အလုပ်ဝင်ရက်',
    assigned_driver_id: 'သတ်မှတ်ယာဉ်မောင်း', plate_no: 'ယာဉ်နံပါတ်', vehicle_type: 'ယာဉ်အမျိုးအစား',
    make: 'ထုတ်လုပ်သူ', model: 'မော်ဒယ်', capacity: 'တင်ဆောင်နိုင်မှု', permission_ids: 'Permission များ', group: 'အုပ်စု', guard_name: 'Guard',
    area: 'ဧရိယာ', route: 'Route', price_type: 'ဈေးနှုန်းအမျိုးအစား', brand: 'Brand', product: 'ကုန်ပစ္စည်း',
    assigned_route: 'သတ်မှတ် Route', assigned_driver: 'သတ်မှတ်ယာဉ်မောင်း',
};

function apiBase(name) {
    const fallback = {
        companySettings: '/api/settings/company',
        masterData: '/api/master-data',
        mobileMaster: '/api/mobile/master',
    };
    return window.ValleyRuntime?.api?.[name] || fallback[name];
}

const companyFields = [
    { name: 'code', label: 'Company code', type: 'text' },
    { name: 'name', label: 'Company name', type: 'text', required: true },
    { name: 'legal_name', label: 'Legal name', type: 'text' },
    { name: 'phone', label: 'Phone', type: 'text' },
    { name: 'email', label: 'Email', type: 'email' },
    { name: 'registration_no', label: 'Registration number', type: 'text' },
    { name: 'tax_no', label: 'Tax number', type: 'text' },
    { name: 'address', label: 'Address', type: 'textarea' },
    { name: 'city', label: 'City', type: 'text' },
    { name: 'state', label: 'State', type: 'text' },
];

function text(locale, key) {
    return uiCopy[locale]?.[key] || uiCopy.en[key] || key;
}

function fieldLabel(field, locale) {
    return locale === 'my' ? myFieldLabels[field.name] || field.label : field.label;
}

function resourceLabel(resource, definition, locale) {
    return locale === 'my' ? myResourceLabels[resource] || definition?.label : definition?.label;
}

function resourceDescription(resource, definition, locale) {
    return locale === 'my' ? myResourceDescriptions[resource] || definition?.description : definition?.description;
}

function optionLabel(value, locale) {
    return locale === 'my' ? myOptionLabels[value] || titleCase(value) : titleCase(value);
}

function requestError(error, locale, fallbackKey) {
    return locale === 'my' ? text(locale, fallbackKey) : error.response?.data?.message || text(locale, fallbackKey);
}

function validationError(fieldName, errors, locale) {
    const message = errors[fieldName]?.[0];
    if (!message) return '';
    return locale === 'my' ? `${myFieldLabels[fieldName] || titleCase(fieldName)} အချက်အလက်ကို စစ်ဆေးပါ။` : message;
}

export function MasterDataWorkspace({ resourceKey, locale, canManage = true }) {
    const [setup, setSetup] = useState({ loading: true, resources: [], options: {}, error: '' });
    const [records, setRecords] = useState({ loading: true, items: [], meta: {}, error: '' });
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('');
    const [page, setPage] = useState(1);
    const [editing, setEditing] = useState(null);
    const [viewing, setViewing] = useState(null);
    const [refreshKey, setRefreshKey] = useState(0);

    useEffect(() => {
        let mounted = true;
        window.axios.get(`${apiBase('masterData')}/meta`)
            .then(({ data }) => mounted && setSetup({ loading: false, resources: data.data.resources, options: data.data.options, error: '' }))
            .catch((error) => mounted && setSetup({ loading: false, resources: [], options: {}, error: requestError(error, locale, 'loadSetupError') }));
        return () => { mounted = false; };
    }, [locale]);

    const definition = useMemo(() => setup.resources.find((item) => item.key === resourceKey), [resourceKey, setup.resources]);

    useEffect(() => {
        if (!definition) return undefined;
        let mounted = true;
        const timer = window.setTimeout(() => {
            setRecords((current) => ({ ...current, loading: true, error: '' }));
            window.axios.get(`${apiBase('masterData')}/${resourceKey}`, { params: { search, is_active: status || undefined, page, per_page: 10 } })
                .then(({ data }) => mounted && setRecords({ loading: false, items: data.data.items, meta: data.data.meta, error: '' }))
                .catch((error) => mounted && setRecords({ loading: false, items: [], meta: {}, error: requestError(error, locale, 'loadRecordsError') }));
        }, 220);
        return () => { mounted = false; window.clearTimeout(timer); };
    }, [definition, locale, page, refreshKey, resourceKey, search, status]);

    useEffect(() => {
        setSearch('');
        setStatus('');
        setPage(1);
        setEditing(null);
        setViewing(null);
    }, [resourceKey]);

    if (setup.loading) return <WorkspaceState icon={RefreshCw} title={text(locale, 'loading')} loading />;
    if (setup.error) return <WorkspaceState icon={AlertCircle} title={setup.error} action={() => window.location.reload()} actionLabel={text(locale, 'retry')} />;
    if (!definition) return <WorkspaceState icon={AlertCircle} title={text(locale, 'screenNotFound')} />;

    const openCreate = () => {
        const initial = Object.fromEntries(definition.fields.map((field) => [field.name, field.default ?? (field.type === 'boolean' ? field.name === 'is_active' : field.type === 'multiselect' ? [] : '')]));
        setEditing({ mode: 'create', values: initial });
    };

    const remove = async (record) => {
        if (!window.confirm(text(locale, 'deleteConfirm'))) return;
        try {
            await window.axios.delete(`${apiBase('masterData')}/${resourceKey}/${record.id}`);
            setViewing(null);
            setRefreshKey((key) => key + 1);
        } catch (error) {
            setRecords((current) => ({ ...current, error: requestError(error, locale, 'deleteError') }));
        }
    };

    return (
        <section className="master-workspace">
            <div className="master-heading">
                <div>
                    <p className="eyebrow">{text(locale, 'masterData')}</p>
                    <h1>{resourceLabel(resourceKey, definition, locale)}</h1>
                    <span className="muted">{resourceDescription(resourceKey, definition, locale)}</span>
                </div>
                {canManage && (
                    <button className="button primary" type="button" onClick={openCreate}>
                        <Plus size={16} /> {text(locale, 'add')} {resourceLabel(resourceKey, definition, locale)}
                    </button>
                )}
            </div>

            <div className="master-panel">
                <div className="master-toolbar">
                    <label className="master-search">
                        <Search size={15} />
                        <input value={search} onChange={(event) => { setSearch(event.target.value); setPage(1); }} placeholder={text(locale, 'search')} />
                    </label>
                    <select aria-label={text(locale, 'allStatuses')} value={status} onChange={(event) => { setStatus(event.target.value); setPage(1); }}>
                        <option value="">{text(locale, 'allStatuses')}</option>
                        <option value="1">{text(locale, 'active')}</option>
                        <option value="0">{text(locale, 'inactive')}</option>
                    </select>
                    <button className="icon-button" type="button" aria-label={text(locale, 'retry')} title={text(locale, 'retry')} onClick={() => setRefreshKey((key) => key + 1)}>
                        <RefreshCw size={15} />
                    </button>
                </div>

                {records.error && <div className="inline-error"><AlertCircle size={15} /> {records.error}</div>}
                {records.loading ? (
                    <TableLoading columns={definition.list.length + 1} />
                ) : records.items.length === 0 ? (
                    <WorkspaceState icon={Search} title={text(locale, 'empty')} compact />
                ) : (
                    <div className="master-table-wrap">
                        <table className="master-table">
                            <thead><tr>{definition.list.map((column) => <th key={column}>{columnLabel(column, definition, locale)}</th>)}<th>{text(locale, 'actions')}</th></tr></thead>
                            <tbody>
                                {records.items.map((record) => (
                                    <tr key={record.id}>
                                        {definition.list.map((column) => <td key={column}>{renderValue(column, record[column], locale)}</td>)}
                                        <td className="row-actions">
                                            <button type="button" aria-label={text(locale, 'details')} title={text(locale, 'details')} onClick={() => setViewing(record)}><Eye size={15} /></button>
                                            {canManage && <button type="button" aria-label={text(locale, 'edit')} title={text(locale, 'edit')} onClick={() => setEditing({ mode: 'edit', values: { ...record } })}><Pencil size={15} /></button>}
                                            {canManage && <button className="danger" type="button" aria-label={text(locale, 'delete')} title={text(locale, 'delete')} onClick={() => remove(record)}><Trash2 size={15} /></button>}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination meta={records.meta} page={page} setPage={setPage} locale={locale} />
            </div>

            {editing && (
                <MasterForm
                    definition={definition}
                    resourceKey={resourceKey}
                    options={setup.options}
                    editing={editing}
                    locale={locale}
                    onClose={() => setEditing(null)}
                    onSaved={() => { setEditing(null); setRefreshKey((key) => key + 1); }}
                />
            )}
            {viewing && <RecordDrawer record={viewing} definition={definition} locale={locale} onClose={() => setViewing(null)} onEdit={canManage ? () => { setEditing({ mode: 'edit', values: { ...viewing } }); setViewing(null); } : null} />}
        </section>
    );
}

function MasterForm({ definition, resourceKey, options, editing, locale, onClose, onSaved }) {
    const [values, setValues] = useState(editing.values);
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState('');
    const [saving, setSaving] = useState(false);
    const dialogRef = useRef(null);

    useEffect(() => {
        const previousOverflow = document.body.style.overflow;
        const handleKeyDown = (event) => {
            if (event.key === 'Escape') onClose();
        };
        const focusFrame = window.requestAnimationFrame(() => {
            dialogRef.current?.querySelector('.master-form-body input:not([type="hidden"]), .master-form-body select, .master-form-body textarea')?.focus();
        });

        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', handleKeyDown);

        return () => {
            window.cancelAnimationFrame(focusFrame);
            document.body.style.overflow = previousOverflow;
            document.removeEventListener('keydown', handleKeyDown);
        };
    }, [onClose]);

    const submit = async (event) => {
        event.preventDefault();
        setSaving(true);
        setErrors({});
        setMessage('');
        const payload = normalizePayload(values, definition.fields);
        try {
            if (editing.mode === 'create') await window.axios.post(`${apiBase('masterData')}/${resourceKey}`, payload);
            else await window.axios.put(`${apiBase('masterData')}/${resourceKey}/${values.id}`, payload);
            onSaved();
        } catch (error) {
            const validationErrors = error.response?.data?.errors || {};
            setErrors(validationErrors);
            setMessage(requestError(error, locale, 'saveError'));
            const firstInvalidField = Object.keys(validationErrors)[0];
            if (firstInvalidField) {
                window.requestAnimationFrame(() => {
                    dialogRef.current?.querySelector(`[data-field="${firstInvalidField}"]`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                });
            }
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="modal-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
            <form ref={dialogRef} className="master-dialog" role="dialog" aria-modal="true" aria-labelledby="master-form-title" onSubmit={submit}>
                <header>
                    <div><p className="eyebrow">{editing.mode === 'create' ? text(locale, 'add') : text(locale, 'edit')}</p><h2 id="master-form-title">{resourceLabel(resourceKey, definition, locale)}</h2></div>
                    <button className="icon-button" type="button" aria-label={text(locale, 'cancel')} onClick={onClose}><X size={17} /></button>
                </header>
                <div className="master-form-body">
                    <div className="master-form-grid">
                        {definition.fields.map((field) => (
                            <MasterField key={field.name} field={field} value={values[field.name]} options={options} locale={locale} error={validationError(field.name, errors, locale)} onChange={(value) => setValues((current) => ({ ...current, [field.name]: value }))} />
                        ))}
                    </div>
                    {message && <div className="inline-error"><AlertCircle size={15} /> {message}</div>}
                </div>
                <footer>
                    <button className="button" type="button" onClick={onClose}>{text(locale, 'cancel')}</button>
                    <button className="button primary" type="submit" disabled={saving}><Save size={15} /> {text(locale, 'save')}</button>
                </footer>
            </form>
        </div>
    );
}

function MasterField({ field, value, options = {}, locale, error, onChange, disabled = false }) {
    if (field.type === 'hidden') return <input type="hidden" value={value || field.default || ''} />;
    if (field.type === 'boolean') {
        return <label className="toggle-field" data-field={field.name}><input type="checkbox" checked={Boolean(value)} disabled={disabled} onChange={(event) => onChange(event.target.checked)} /><span><Check size={13} /></span>{fieldLabel(field, locale)}{error && <small>{error}</small>}</label>;
    }
    if (field.type === 'multiselect') {
        return (
            <fieldset className="permission-field" data-field={field.name} disabled={disabled}>
                <legend>{fieldLabel(field, locale)}</legend>
                <div>{(options[field.source] || []).map((option) => <label key={option.id}><input type="checkbox" checked={(value || []).map(String).includes(String(option.id))} onChange={(event) => onChange(event.target.checked ? [...(value || []), option.id] : (value || []).filter((id) => String(id) !== String(option.id)))} /> {option.label}</label>)}</div>
                {error && <small>{error}</small>}
            </fieldset>
        );
    }

    const label = <span>{fieldLabel(field, locale)}{field.required && <b aria-hidden="true"> *</b>}</span>;
    const sourceOptions = field.source ? options[field.source] || [] : (field.options || []).map((option) => ({ id: option, label: optionLabel(option, locale) }));
    return (
        <label className={`master-field ${field.type === 'textarea' ? 'wide' : ''}`} data-field={field.name}>
            {label}
            {field.type === 'textarea' ? <textarea value={value ?? ''} disabled={disabled} onChange={(event) => onChange(event.target.value)} rows="3" /> : field.type === 'select' ? (
                <select value={value ?? ''} disabled={disabled} onChange={(event) => onChange(event.target.value)}><option value="">{text(locale, 'select')}</option>{sourceOptions.map((option) => <option value={option.id} key={option.id}>{option.label}</option>)}</select>
            ) : <input type={field.type || 'text'} autoComplete={field.autocomplete} step={field.type === 'number' ? '0.01' : undefined} value={value ?? ''} disabled={disabled} onChange={(event) => onChange(event.target.value)} />}
            {error && <small>{error}</small>}
        </label>
    );
}

export function CompanySettingsScreen({ locale, canManage = true }) {
    const [attempt, setAttempt] = useState(0);
    const [state, setState] = useState({ loading: true, error: '' });
    const [values, setValues] = useState({});
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState('');
    const [messageType, setMessageType] = useState('');
    const [saving, setSaving] = useState(false);
    const formRef = useRef(null);

    useEffect(() => {
        let mounted = true;
        setState({ loading: true, error: '' });
        window.axios.get(apiBase('companySettings'))
            .then(({ data }) => {
                if (!mounted) return;
                setValues(data.data.company);
                setState({ loading: false, error: '' });
            })
            .catch((error) => mounted && setState({ loading: false, error: requestError(error, locale, 'loadCompanyError') }));
        return () => { mounted = false; };
    }, [attempt, locale]);

    const submit = async (event) => {
        event.preventDefault();
        if (!canManage) return;
        setSaving(true);
        setErrors({});
        setMessage('');
        setMessageType('');
        try {
            const payload = normalizePayload(values, companyFields);
            const { data } = await window.axios.put(apiBase('companySettings'), payload);
            setValues(data.data.company);
            setMessage(text(locale, 'saved'));
            setMessageType('success');
        } catch (error) {
            const validationErrors = error.response?.data?.errors || {};
            setErrors(validationErrors);
            setMessage(requestError(error, locale, 'saveCompanyError'));
            setMessageType('error');
            const firstInvalidField = Object.keys(validationErrors)[0];
            if (firstInvalidField) {
                window.requestAnimationFrame(() => formRef.current?.querySelector(`[data-field="${firstInvalidField}"]`)?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
            }
        } finally {
            setSaving(false);
        }
    };

    if (state.loading) return <WorkspaceState icon={RefreshCw} title={text(locale, 'loading')} loading />;
    if (state.error) return <WorkspaceState icon={AlertCircle} title={state.error} action={() => setAttempt((value) => value + 1)} actionLabel={text(locale, 'retry')} />;

    return (
        <section className="company-settings-page">
            <div className="master-heading">
                <div>
                    <p className="eyebrow">{text(locale, 'settings')}</p>
                    <h1>{text(locale, 'companySettings')}</h1>
                    <span className="muted">{text(locale, 'companySettingsHint')}</span>
                </div>
            </div>
            <form ref={formRef} className="company-settings-form" onSubmit={submit}>
                <header>
                    <span className="company-settings-icon"><Building2 size={18} /></span>
                    <div><p className="eyebrow">{text(locale, 'businessIdentity')}</p><strong>{values.name}</strong></div>
                </header>
                <div className="master-form-grid">
                    {companyFields.map((field) => (
                        <MasterField
                            key={field.name}
                            field={field}
                            value={values[field.name]}
                            locale={locale}
                            error={validationError(field.name, errors, locale)}
                            disabled={!canManage}
                            onChange={(value) => setValues((current) => ({ ...current, [field.name]: value }))}
                        />
                    ))}
                </div>
                <footer>
                    <span className={messageType === 'error' ? 'inline-error' : messageType === 'success' ? 'inline-success' : 'muted'}>
                        {message && (messageType === 'error' ? <AlertCircle size={15} /> : <Check size={15} />)}
                        {message}
                    </span>
                    {canManage && <button className="button primary" type="submit" disabled={saving}><Save size={15} /> {text(locale, 'save')}</button>}
                </footer>
            </form>
        </section>
    );
}

function RecordDrawer({ record, definition, locale, onClose, onEdit }) {
    return (
        <div className="drawer-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
            <aside className="record-drawer" role="dialog" aria-modal="true" aria-label={text(locale, 'details')}>
                <header><div><p className="eyebrow">{text(locale, 'details')}</p><h2>{record.name || record.shop_name || record.sku || record.code}</h2></div><button className="icon-button" type="button" aria-label={text(locale, 'cancel')} onClick={onClose}><X size={17} /></button></header>
                <dl>{definition.fields.filter((field) => !['hidden', 'multiselect', 'password'].includes(field.type)).map((field) => <div key={field.name}><dt>{fieldLabel(field, locale)}</dt><dd>{renderValue(field.name, record[relationAlias(field.name)] ?? record[field.name], locale)}</dd></div>)}</dl>
                {onEdit && <footer><button className="button primary" type="button" onClick={onEdit}><Pencil size={15} /> {text(locale, 'edit')}</button></footer>}
            </aside>
        </div>
    );
}

export function ClientMasterScreen({ locale }) {
    const { loading, data, error, retry } = useEndpoint(`${apiBase('mobileMaster')}/profile`, 'profile', locale);
    return <MobileProfileView locale={locale} loading={loading} error={error} retry={retry} title={text(locale, 'customerProfile')} icon={Store} profile={data} fields={['code', 'shop_name', 'contact_name', 'phone', 'email', 'address', 'area', 'route', 'service_day', 'price_type', 'credit_limit']} />;
}

export function DriverMasterScreen({ locale }) {
    const profileState = useEndpoint(`${apiBase('mobileMaster')}/profile`, 'profile', locale);
    const vehicleState = useEndpoint(`${apiBase('mobileMaster')}/vehicle`, 'vehicle', locale);
    if (profileState.loading || vehicleState.loading) return <WorkspaceState icon={RefreshCw} title={text(locale, 'loading')} loading compact />;
    if (profileState.error) return <WorkspaceState icon={AlertCircle} title={profileState.error} action={profileState.retry} actionLabel={text(locale, 'retry')} compact />;
    if (vehicleState.error) return <WorkspaceState icon={AlertCircle} title={vehicleState.error} action={vehicleState.retry} actionLabel={text(locale, 'retry')} compact />;
    return (
        <div className="mobile-master-stack">
            <MobileProfileView locale={locale} title={text(locale, 'driverProfile')} icon={User} profile={profileState.data} fields={['code', 'name', 'employee_type', 'phone', 'email', 'hire_date', 'assigned_route', 'service_day']} />
            <section className="mobile-master-section">
                <div className="mobile-section-heading"><Truck size={18} /><div><small>{text(locale, 'assignedVehicle')}</small><h2>{vehicleState.data?.plate_no || text(locale, 'noVehicle')}</h2></div></div>
                {vehicleState.data && <InfoList record={vehicleState.data} fields={['code', 'vehicle_type', 'make', 'model', 'capacity']} locale={locale} />}
            </section>
        </div>
    );
}

export function SalesMasterScreen({ locale }) {
    const [mode, setMode] = useState('list');
    const [search, setSearch] = useState('');
    const [state, setState] = useState({ loading: true, items: [], route: null, error: '' });
    const [selected, setSelected] = useState(null);
    const [refreshKey, setRefreshKey] = useState(0);

    useEffect(() => {
        let mounted = true;
        const timer = window.setTimeout(() => {
            setState((current) => ({ ...current, loading: true, error: '' }));
            window.axios.get(`${apiBase('mobileMaster')}/customers`, { params: { search } })
                .then(({ data }) => mounted && setState({ loading: false, items: data.data.items, route: data.data.route, error: '' }))
                .catch((error) => mounted && setState({ loading: false, items: [], route: null, error: requestError(error, locale, 'loadCustomersError') }));
        }, 220);
        return () => { mounted = false; window.clearTimeout(timer); };
    }, [locale, refreshKey, search]);

    return (
        <div className="mobile-master-stack">
            <div className="mobile-master-heading"><div><p className="eyebrow">{text(locale, 'assignedRoute')}</p><h1>{state.route?.name || text(locale, 'assignedCustomers')}</h1></div><button className="icon-button primary-icon" type="button" aria-label={text(locale, 'registerCustomer')} onClick={() => setMode(mode === 'form' ? 'list' : 'form')}>{mode === 'form' ? <X size={18} /> : <Plus size={18} />}</button></div>
            {mode === 'form' ? <SalesCustomerForm locale={locale} onSaved={() => { setMode('list'); setRefreshKey((key) => key + 1); }} /> : (
                <>
                    <label className="mobile-search"><Search size={16} /><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder={text(locale, 'search')} /></label>
                    {state.error ? <WorkspaceState icon={AlertCircle} title={state.error} action={() => setRefreshKey((key) => key + 1)} actionLabel={text(locale, 'retry')} compact /> : state.loading ? <WorkspaceState icon={RefreshCw} title={text(locale, 'loading')} loading compact /> : state.items.length === 0 ? <WorkspaceState icon={Search} title={text(locale, 'empty')} compact /> : (
                        <div className="customer-mobile-list">{state.items.map((customer) => <button type="button" key={customer.id} onClick={() => setSelected(customer)}><span className="customer-initial">{customer.shop_name.slice(0, 1)}</span><span><strong>{customer.shop_name}</strong><small>{customer.code} · {customer.contact_name}</small><small>{customer.phone}</small></span><ChevronRight size={17} /></button>)}</div>
                    )}
                </>
            )}
            {selected && <MobileDetailSheet customer={selected} locale={locale} onClose={() => setSelected(null)} />}
        </div>
    );
}

function SalesCustomerForm({ locale, onSaved }) {
    const [values, setValues] = useState({ shop_name: '', contact_name: '', phone: '', email: '', address: '' });
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState('');
    const [saving, setSaving] = useState(false);
    const fields = [
        ['shop_name', locale === 'my' ? 'ဆိုင်အမည်' : 'Shop name'], ['contact_name', locale === 'my' ? 'ဆက်သွယ်သူ' : 'Contact name'],
        ['phone', locale === 'my' ? 'ဖုန်း' : 'Phone'], ['email', locale === 'my' ? 'အီးမေးလ်' : 'Email'], ['address', locale === 'my' ? 'လိပ်စာ' : 'Address'],
    ];
    const submit = async (event) => {
        event.preventDefault(); setSaving(true); setErrors({}); setMessage('');
        try { await window.axios.post(`${apiBase('mobileMaster')}/customers`, values); setMessage(text(locale, 'registrationSuccess')); window.setTimeout(onSaved, 500); }
        catch (error) { setErrors(error.response?.data?.errors || {}); setMessage(requestError(error, locale, 'registerCustomerError')); }
        finally { setSaving(false); }
    };
    return (
        <form className="mobile-customer-form" onSubmit={submit}>
            <div><p className="eyebrow">{text(locale, 'masterData')}</p><h2>{text(locale, 'registerCustomer')}</h2></div>
            {fields.map(([name, label]) => <label key={name}>{label}{name === 'address' ? <textarea rows="3" value={values[name]} onChange={(event) => setValues((current) => ({ ...current, [name]: event.target.value }))} /> : <input type={name === 'email' ? 'email' : 'text'} value={values[name]} onChange={(event) => setValues((current) => ({ ...current, [name]: event.target.value }))} />}{errors[name] && <small>{validationError(name, errors, locale)}</small>}</label>)}
            {message && <div className={Object.keys(errors).length ? 'inline-error' : 'inline-success'}>{message}</div>}
            <button className="button primary" type="submit" disabled={saving}><Save size={16} /> {text(locale, 'save')}</button>
        </form>
    );
}

function MobileProfileView({ locale, loading, error, retry, title, icon: Icon, profile, fields }) {
    if (loading) return <WorkspaceState icon={RefreshCw} title={text(locale, 'loading')} loading compact />;
    if (error) return <WorkspaceState icon={AlertCircle} title={error} action={retry} actionLabel={text(locale, 'retry')} compact />;
    if (!profile) return <WorkspaceState icon={User} title={text(locale, 'noProfile')} compact />;
    return <section className="mobile-master-section"><div className="mobile-profile-identity"><span><Icon size={22} /></span><div><small>{title}</small><h1>{profile.shop_name || profile.name}</h1><p>{profile.code}</p></div><span className={`status ${profile.is_active ? 'success' : 'neutral'}`}>{profile.is_active ? text(locale, 'active') : text(locale, 'inactive')}</span></div><InfoList record={profile} fields={fields.filter((field) => !['code', 'shop_name', 'name', 'is_active'].includes(field))} locale={locale} /></section>;
}

function InfoList({ record, fields, locale }) {
    return <dl className="mobile-info-list">{fields.map((field) => <div key={field}><dt>{myFieldLabels[field] && locale === 'my' ? myFieldLabels[field] : titleCase(field)}</dt><dd>{renderValue(field, record[field], locale)}</dd></div>)}</dl>;
}

function MobileDetailSheet({ customer, locale, onClose }) {
    return <div className="drawer-backdrop mobile" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && onClose()}><aside className="mobile-detail-sheet" role="dialog" aria-modal="true"><header><div><p className="eyebrow">{text(locale, 'details')}</p><h2>{customer.shop_name}</h2></div><button className="icon-button" type="button" onClick={onClose} aria-label={text(locale, 'cancel')}><X size={17} /></button></header><InfoList record={customer} fields={['code', 'contact_name', 'phone', 'email', 'address', 'area', 'route', 'credit_limit']} locale={locale} /><a className="button primary call-action" href={`tel:${customer.phone}`}>{text(locale, 'contact')}</a></aside></div>;
}

function useEndpoint(url, key, locale) {
    const [attempt, setAttempt] = useState(0);
    const [state, setState] = useState({ loading: true, data: null, error: '' });
    useEffect(() => { let mounted = true; setState((current) => ({ ...current, loading: true, error: '' })); window.axios.get(url).then(({ data }) => mounted && setState({ loading: false, data: data.data[key], error: '' })).catch((error) => mounted && setState({ loading: false, data: null, error: requestError(error, locale, 'loadDataError') })); return () => { mounted = false; }; }, [attempt, key, locale, url]);
    return { ...state, retry: () => setAttempt((value) => value + 1) };
}

function WorkspaceState({ icon: Icon, title, action, actionLabel, loading = false, compact = false }) {
    return <div className={`workspace-state ${compact ? 'compact' : ''}`}><Icon className={loading ? 'spin' : ''} size={22} /><strong>{title}</strong>{action && <button className="button" type="button" onClick={action}>{actionLabel}</button>}</div>;
}

function TableLoading({ columns }) {
    return <div className="master-table-wrap"><table className="master-table"><tbody>{[0, 1, 2, 3, 4].map((row) => <tr key={row}>{Array.from({ length: columns }).map((_, column) => <td key={column}><span className="table-skeleton" /></td>)}</tr>)}</tbody></table></div>;
}

function Pagination({ meta = {}, page, setPage, locale }) {
    if (!meta.last_page || meta.last_page <= 1) return null;
    return <div className="master-pagination"><span>{text(locale, 'page')} {meta.current_page} {text(locale, 'of')} {meta.last_page} · {meta.total}</span><div><button type="button" disabled={page <= 1} onClick={() => setPage((value) => value - 1)} aria-label={text(locale, 'previousPage')} title={text(locale, 'previousPage')}><ChevronLeft size={16} /></button><button type="button" disabled={page >= meta.last_page} onClick={() => setPage((value) => value + 1)} aria-label={text(locale, 'nextPage')} title={text(locale, 'nextPage')}><ChevronRight size={16} /></button></div></div>;
}

function normalizePayload(values, fields) {
    return Object.fromEntries(fields.map((field) => {
        let value = values[field.name];
        if (field.type === 'number' && value !== '' && value !== null) value = Number(value);
        if (field.type === 'select' && field.source && value !== '' && value !== null) value = Number(value);
        if (field.type === 'boolean') value = Boolean(value);
        if (value === '') value = null;
        return [field.name, value];
    }));
}

function relationAlias(field) {
    return ({ area_id: 'area', route_id: 'route', price_type_id: 'price_type', brand_id: 'brand', product_id: 'product', assigned_route_id: 'assigned_route', assigned_driver_id: 'assigned_driver' })[field] || field;
}

function columnLabel(column, definition, locale) {
    const field = definition.fields.find((item) => item.name === column || relationAlias(item.name) === column);
    return field ? fieldLabel(field, locale) : titleCase(column);
}

function renderValue(column, value, locale) {
    if (['is_active', 'is_default'].includes(column)) return <span className={`status ${value ? 'success' : 'neutral'}`}>{value ? text(locale, column === 'is_active' ? 'active' : 'yes') : text(locale, column === 'is_active' ? 'inactive' : 'no')}</span>;
    if (value === null || value === undefined || value === '') return <span className="muted">—</span>;
    if (['amount', 'credit_limit'].includes(column)) return `${Number(value).toLocaleString()} MMK`;
    if (locale === 'my' && myOptionLabels[value]) return myOptionLabels[value];
    return String(value);
}

function titleCase(value) {
    return String(value || '').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}
