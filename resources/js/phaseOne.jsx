import {
    AlertCircle,
    Building2,
    Check,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    MapPinned,
    Pencil,
    Plus,
    RefreshCw,
    Save,
    Search,
    Store,
    Target,
    Trash2,
    TrendingUp,
    Truck,
    User,
    X,
    ImagePlus,
    Palette,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { DetailPage, DetailPanel } from './components/DetailPage';
import { ShellBackButton } from './components/ShellBackButton';

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
        effectiveFrom: 'Effective from', savePrices: 'Save prices', pricesSaved: 'Prices updated.',
        priceMatrixHint: 'Edit every customer price tier for one product in the same row.',
        unsavedChanges: 'unsaved rows', noUnsavedChanges: 'No unsaved changes', saveAllPrices: 'Save all changes',
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

export function MasterDataWorkspace({ resourceKey, locale, canManage = true, detailId = null, onNavigate }) {
    const [setup, setSetup] = useState({ loading: true, resources: [], options: {}, error: '' });
    const [records, setRecords] = useState({ loading: true, items: [], meta: {}, error: '' });
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('');
    const [page, setPage] = useState(1);
    const [editing, setEditing] = useState(null);
    const [viewing, setViewing] = useState(null);
    const listPath = `${window.ValleyRuntime?.routes?.office || '/office'}/master/${resourceKey}`;
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
        if (!definition || resourceKey === 'product-prices') return undefined;
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

    useEffect(() => {
        if (!detailId) {
            setViewing(null);
            return undefined;
        }
        let mounted = true;
        window.axios.get(`${apiBase('masterData')}/${resourceKey}/${detailId}`)
            .then(({ data }) => mounted && setViewing(data.data.item))
            .catch((error) => mounted && setRecords((current) => ({ ...current, error: requestError(error, locale, 'loadRecordsError') })));
        return () => { mounted = false; };
    }, [detailId, locale, resourceKey]);

    if (setup.loading) return <WorkspaceState icon={RefreshCw} title={text(locale, 'loading')} loading />;
    if (setup.error) return <WorkspaceState icon={AlertCircle} title={setup.error} action={() => window.location.reload()} actionLabel={text(locale, 'retry')} />;
    if (!definition) return <WorkspaceState icon={AlertCircle} title={text(locale, 'screenNotFound')} />;
    if (resourceKey === 'product-prices') return <ProductPriceMatrix definition={definition} locale={locale} canManage={canManage} />;
    if (detailId) return viewing ? <>
        <RecordDetailPage record={viewing} definition={definition} resourceKey={resourceKey} locale={locale} canManage={canManage} onBack={() => onNavigate?.(listPath)} onEdit={canManage ? () => setEditing({ mode: 'edit', values: { ...viewing } }) : null} />
        {editing && <MasterForm definition={definition} resourceKey={resourceKey} options={setup.options} editing={editing} locale={locale} onClose={() => setEditing(null)} onSaved={() => { setEditing(null); onNavigate?.(listPath); }} />}
    </> : <WorkspaceState icon={records.error ? AlertCircle : RefreshCw} title={records.error || text(locale, 'loading')} loading={!records.error} />;

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
                    <TableLoading columns={definition.list.length + (canManage ? 1 : 0)} />
                ) : records.items.length === 0 ? (
                    <WorkspaceState icon={Search} title={text(locale, 'empty')} compact />
                ) : (
                    <div className="master-table-wrap">
                        <table className="master-table">
                            <thead><tr>{definition.list.map((column) => <th key={column}>{columnLabel(column, definition, locale)}</th>)}{canManage && <th className="table-actions-header">{text(locale, 'actions')}</th>}</tr></thead>
                            <tbody>
                                {records.items.map((record) => (
                                    <tr className="clickable-row" key={record.id} tabIndex={0} onClick={() => onNavigate?.(`${listPath}/${record.id}`)} onKeyDown={(event) => { if (event.target === event.currentTarget && ['Enter', ' '].includes(event.key)) { event.preventDefault(); onNavigate?.(`${listPath}/${record.id}`); } }}>
                                        {definition.list.map((column) => <td key={column}>{renderValue(column, record[column], locale)}</td>)}
                                        {canManage && <td className="table-actions-cell" onClick={(event) => event.stopPropagation()}><div className="row-actions">
                                            <button type="button" aria-label={text(locale, 'edit')} title={text(locale, 'edit')} onClick={() => setEditing({ mode: 'edit', values: { ...record } })}><Pencil size={15} /></button>
                                            <button className="danger" type="button" aria-label={text(locale, 'delete')} title={text(locale, 'delete')} onClick={() => remove(record)}><Trash2 size={15} /></button>
                                        </div></td>}
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
        </section>
    );
}

function ProductPriceMatrix({ definition, locale, canManage }) {
    const [state, setState] = useState({ loading: true, items: [], priceTypes: [], meta: {}, error: '' });
    const [rows, setRows] = useState({});
    const [dirtyRows, setDirtyRows] = useState({});
    const [dirtyCells, setDirtyCells] = useState({});
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [refreshKey, setRefreshKey] = useState(0);
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState('');

    useEffect(() => {
        let mounted = true;
        const timer = window.setTimeout(() => {
            setState((current) => ({ ...current, loading: true, error: '' }));
            window.axios.get(`${apiBase('masterData')}/product-prices/matrix`, { params: { search, page, per_page: 25 } })
                .then(({ data }) => {
                    if (!mounted) return;
                    const items = data.data.items;
                    const priceTypes = data.data.price_types;
                    setRows(Object.fromEntries(items.map((product) => {
                        const latestDate = product.prices.reduce((date, price) => price.effective_from > date ? price.effective_from : date, '');
                        const prices = Object.fromEntries(priceTypes.map((priceType) => {
                            const price = product.prices.find((item) => Number(item.price_type_id) === Number(priceType.id));
                            return [priceType.id, price ? String(price.amount) : ''];
                        }));
                        return [product.id, { effective_from: latestDate || new Date().toISOString().slice(0, 10), prices }];
                    })));
                    setDirtyRows({});
                    setDirtyCells({});
                    setMessage('');
                    setState({ loading: false, items, priceTypes, meta: data.data.meta, error: '' });
                })
                .catch((error) => mounted && setState((current) => ({ ...current, loading: false, items: [], error: requestError(error, locale, 'loadRecordsError') })));
        }, 220);
        return () => { mounted = false; window.clearTimeout(timer); };
    }, [locale, page, refreshKey, search]);

    const setPrice = (productId, priceTypeId, amount) => {
        setMessage('');
        setDirtyRows((current) => ({ ...current, [productId]: true }));
        setDirtyCells((current) => ({ ...current, [`${productId}:price:${priceTypeId}`]: true }));
        setRows((current) => ({
            ...current,
            [productId]: { ...current[productId], prices: { ...current[productId].prices, [priceTypeId]: amount } },
        }));
    };

    const saveAll = async () => {
        const productIds = Object.keys(dirtyRows).filter((productId) => dirtyRows[productId]);
        if (!productIds.length) return;
        setSaving(true);
        setMessage('');
        setState((current) => ({ ...current, error: '' }));
        try {
            await Promise.all(productIds.map((productId) => {
                const row = rows[productId];
                return window.axios.put(`${apiBase('masterData')}/product-prices/matrix`, {
                    product_id: Number(productId),
                    effective_from: row.effective_from,
                    prices: state.priceTypes.map((priceType) => ({ price_type_id: priceType.id, amount: row.prices[priceType.id] })),
                });
            }));
            setDirtyRows({});
            setDirtyCells({});
            setMessage(text(locale, 'pricesSaved'));
        } catch (error) {
            setState((current) => ({ ...current, error: requestError(error, locale, 'saveError') }));
        } finally {
            setSaving(false);
        }
    };

    const moveVertically = (event, rowIndex, columnIndex) => {
        if (event.key !== 'Enter') return;
        event.preventDefault();
        const nextRow = rowIndex + (event.shiftKey ? -1 : 1);
        const target = event.currentTarget.closest('table')?.querySelector(`[data-grid-row="${nextRow}"][data-grid-column="${columnIndex}"]`);
        target?.focus();
        if (target?.type === 'number') target.select();
    };

    const pasteGrid = (event, startRow, startColumn) => {
        const pastedRows = event.clipboardData.getData('text').trimEnd().split(/\r?\n/).map((line) => line.split('\t'));
        if (pastedRows.length === 1 && pastedRows[0].length === 1) return;
        event.preventDefault();

        const changedRows = {};
        const changedCells = {};
        const updates = [];
        pastedRows.forEach((values, rowOffset) => {
            const product = state.items[startRow + rowOffset];
            if (!product) return;
            const priceUpdates = {};
            values.forEach((value, columnOffset) => {
                const column = startColumn + columnOffset;
                if (column < state.priceTypes.length) {
                    const priceTypeId = state.priceTypes[column].id;
                    priceUpdates[priceTypeId] = value.replaceAll(',', '').trim();
                    changedCells[`${product.id}:price:${priceTypeId}`] = true;
                }
            });
            updates.push({ productId: product.id, priceUpdates });
            changedRows[product.id] = true;
        });

        setRows((current) => {
            const next = { ...current };
            updates.forEach(({ productId, priceUpdates }) => {
                next[productId] = { ...next[productId], prices: { ...next[productId].prices, ...priceUpdates } };
            });
            return next;
        });
        setDirtyRows((current) => ({ ...current, ...changedRows }));
        setDirtyCells((current) => ({ ...current, ...changedCells }));
        setMessage('');
    };

    const dirtyCount = Object.values(dirtyRows).filter(Boolean).length;

    return (
        <section className="master-workspace price-matrix-workspace">
            <div className="master-heading">
                <div><p className="eyebrow">{text(locale, 'masterData')}</p><h1>{resourceLabel('product-prices', definition, locale)}</h1><span className="muted">{text(locale, 'priceMatrixHint')}</span></div>
            </div>
            <div className="master-panel price-matrix-panel">
                <div className="master-toolbar">
                    <label className="master-search"><Search size={15} /><input disabled={Boolean(dirtyCount) || saving} value={search} onChange={(event) => { setSearch(event.target.value); setPage(1); }} placeholder={text(locale, 'search')} /></label>
                    {canManage && <span className={`price-matrix-change-count ${dirtyCount ? 'has-changes' : ''}`}>{dirtyCount ? `${dirtyCount} ${text(locale, 'unsavedChanges')}` : text(locale, 'noUnsavedChanges')}</span>}
                    <button className="icon-button" type="button" disabled={Boolean(dirtyCount) || saving} aria-label={text(locale, 'retry')} title={text(locale, 'retry')} onClick={() => setRefreshKey((key) => key + 1)}><RefreshCw size={15} /></button>
                    {canManage && <button className="button primary" type="button" disabled={!dirtyCount || saving} onClick={saveAll}><Save size={15} />{saving ? 'Saving…' : text(locale, 'saveAllPrices')}</button>}
                </div>
                {state.error && <div className="inline-error"><AlertCircle size={15} /> {state.error}</div>}
                {message && <div className="inline-success"><CheckCircle2 size={15} /> {message}</div>}
                {state.loading ? <TableLoading columns={state.priceTypes.length + 1} /> : state.items.length === 0 ? <WorkspaceState icon={Search} title={text(locale, 'empty')} compact /> : (
                    <div className="master-table-wrap price-matrix-wrap">
                        <table className="master-table price-matrix-table">
                            <thead><tr><th>Product</th>{state.priceTypes.map((priceType) => <th key={priceType.id}>{priceType.name}<small>{priceType.currency}</small></th>)}</tr></thead>
                            <tbody>{state.items.map((product, rowIndex) => {
                                const row = rows[product.id];
                                return <tr className={dirtyRows[product.id] ? 'is-dirty' : ''} key={product.id}>
                                    <td><strong>{product.name}</strong><span className="muted">{product.sku} · {product.unit}</span></td>
                                    {state.priceTypes.map((priceType, columnIndex) => <td className={`price-matrix-cell ${dirtyCells[`${product.id}:price:${priceType.id}`] ? 'is-dirty' : ''}`} key={priceType.id}><input aria-label={`${product.name} ${priceType.name}`} data-grid-column={columnIndex} data-grid-row={rowIndex} disabled={!canManage || saving} min="0" step="1" type="number" value={row?.prices[priceType.id] ?? ''} onFocus={(event) => event.currentTarget.select()} onKeyDown={(event) => moveVertically(event, rowIndex, columnIndex)} onPaste={(event) => pasteGrid(event, rowIndex, columnIndex)} onChange={(event) => setPrice(product.id, priceType.id, event.target.value)} /></td>)}
                                </tr>;
                            })}</tbody>
                        </table>
                    </div>
                )}
                <Pagination meta={state.meta} page={page} setPage={setPage} locale={locale} disabled={Boolean(dirtyCount) || saving} />
            </div>
        </section>
    );
}

function MasterForm({ definition, resourceKey, options, editing, locale, onClose, onSaved }) {
    const [values, setValues] = useState(editing.values);
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState('');
    const [saving, setSaving] = useState(false);
    const dialogRef = useRef(null);
    const visibleFields = definition.fields.filter((field) => !field.show_when || values[field.depends_on] === field.show_when);

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
                        {visibleFields.map((field) => (
                            <MasterField key={field.name} field={field} value={values[field.name]} options={options} locale={locale} error={validationError(field.name, errors, locale)} onChange={(value) => setValues((current) => ({ ...current, [field.name]: value, ...(resourceKey === 'employees' && field.name === 'employee_type' && value !== 'driver' ? { assigned_vehicle_id: '' } : {}) }))} />
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

export function CompanySettingsScreen({ locale, canManage = true, onBrandingUpdated }) {
    const [attempt, setAttempt] = useState(0);
    const [state, setState] = useState({ loading: true, error: '' });
    const [values, setValues] = useState({});
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState('');
    const [messageType, setMessageType] = useState('');
    const [saving, setSaving] = useState(false);
    const [logo, setLogo] = useState(null);
    const [logoPreview, setLogoPreview] = useState('');
    const [removeLogo, setRemoveLogo] = useState(false);
    const formRef = useRef(null);

    useEffect(() => {
        let mounted = true;
        setState({ loading: true, error: '' });
        window.axios.get(apiBase('companySettings'))
            .then(({ data }) => {
                if (!mounted) return;
                setValues(data.data.company);
                setLogoPreview(data.data.company.logo_url || '');
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
            const formData = new FormData();
            Object.entries(payload).forEach(([key, value]) => value !== null && formData.append(key, value));
            formData.append('primary_color', values.primary_color || '#0b84a5');
            formData.append('default_theme', values.default_theme || 'light');
            formData.append('default_customer_credit_limit', values.default_customer_credit_limit ?? 500000);
            formData.append('delivery_credit_due_days', values.delivery_credit_due_days ?? 14);
            formData.append('remove_logo', removeLogo ? '1' : '0');
            if (logo) formData.append('logo', logo);
            formData.append('_method', 'PUT');
            const { data } = await window.axios.post(apiBase('companySettings'), formData);
            setValues(data.data.company);
            setLogo(null);
            setRemoveLogo(false);
            setLogoPreview(data.data.company.logo_url || '');
            onBrandingUpdated?.(data.data.company);
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
                <section className="branding-settings" aria-labelledby="branding-settings-title">
                    <div className="branding-settings-heading">
                        <span className="company-settings-icon"><Palette size={17} /></span>
                        <div><p className="eyebrow">Branding</p><strong id="branding-settings-title">App identity and theme</strong></div>
                    </div>
                    <div className="branding-settings-grid">
                        <div className="branding-logo-field">
                            <span>Business logo</span>
                            <div className="branding-logo-row">
                                <span className="branding-logo-preview">
                                    {logoPreview && !removeLogo ? <img src={logoPreview} alt="Business logo preview" /> : <Building2 size={24} />}
                                </span>
                                {canManage && <div className="branding-logo-actions">
                                    <label className="button" htmlFor="company-logo"><ImagePlus size={15} />Choose logo</label>
                                    <input id="company-logo" className="visually-hidden" type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml" onChange={(event) => {
                                        const file = event.target.files?.[0] || null;
                                        setLogo(file);
                                        setRemoveLogo(false);
                                        if (file) setLogoPreview(URL.createObjectURL(file));
                                    }} />
                                    {(logoPreview || values.logo_url) && <button className="button danger-text" type="button" onClick={() => { setLogo(null); setLogoPreview(''); setRemoveLogo(true); }}><Trash2 size={15} />Remove</button>}
                                    <small>PNG, JPG, WebP or SVG. Maximum 2 MB.</small>
                                </div>}
                            </div>
                            {errors.logo?.[0] && <small className="field-error">{errors.logo[0]}</small>}
                        </div>
                        <label className="master-field branding-color-field">
                            <span>App color</span>
                            <div><input type="color" disabled={!canManage} value={values.primary_color || '#0b84a5'} onChange={(event) => setValues((current) => ({ ...current, primary_color: event.target.value }))} /><input aria-label="App color hex value" disabled={!canManage} pattern="^#[0-9A-Fa-f]{6}$" value={values.primary_color || '#0b84a5'} onChange={(event) => setValues((current) => ({ ...current, primary_color: event.target.value }))} /></div>
                            {errors.primary_color?.[0] && <small>{errors.primary_color[0]}</small>}
                        </label>
                        <label className="master-field">
                            <span>Default app theme</span>
                            <select disabled={!canManage} value={values.default_theme || 'light'} onChange={(event) => setValues((current) => ({ ...current, default_theme: event.target.value }))}>
                                <option value="light">Light</option>
                                <option value="dark">Dark</option>
                            </select>
                            <small className="muted">Applied when branding settings are saved.</small>
                        </label>
                    </div>
                </section>
                <section className="branding-settings" aria-labelledby="delivery-settings-title">
                    <div className="branding-settings-heading">
                        <span className="company-settings-icon"><Truck size={17} /></span>
                        <div><p className="eyebrow">Delivery sales</p><strong id="delivery-settings-title">Customer credit defaults</strong></div>
                    </div>
                    <div className="branding-settings-grid">
                        <label className="master-field" data-field="default_customer_credit_limit">
                            <span>Default credit limit (MMK)</span>
                            <input type="number" min="0" step="1000" disabled={!canManage} value={values.default_customer_credit_limit ?? 500000} onChange={(event) => setValues((current) => ({ ...current, default_customer_credit_limit: event.target.value }))} />
                            <small className="muted">Applied automatically when a new customer is created.</small>
                            {errors.default_customer_credit_limit?.[0] && <small>{errors.default_customer_credit_limit[0]}</small>}
                        </label>
                        <label className="master-field" data-field="delivery_credit_due_days">
                            <span>Credit due after stock issue (days)</span>
                            <input type="number" min="1" max="365" step="1" disabled={!canManage} value={values.delivery_credit_due_days ?? 14} onChange={(event) => setValues((current) => ({ ...current, delivery_credit_due_days: event.target.value }))} />
                            <small className="muted">The standard value is 14 days from driver load confirmation.</small>
                            {errors.delivery_credit_due_days?.[0] && <small>{errors.delivery_credit_due_days[0]}</small>}
                        </label>
                    </div>
                </section>
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

function EmployeeKpiPanel({ employee, canManage }) {
    const [state, setState] = useState({ loading: true, employee: null, templates: [], report: null, error: '', success: '' });
    const [form, setForm] = useState({ template_id: '', target_bonus: 40000, targets: {} });
    const [saving, setSaving] = useState(false);
    const [refreshKey, setRefreshKey] = useState(0);

    useEffect(() => {
        let mounted = true;
        setState((current) => ({ ...current, loading: true, error: '' }));
        Promise.all([
            window.axios.get(`${apiBase('kpiTargets') || '/api/kpi-targets'}/employees/${employee.id}`),
            window.axios.get(apiBase('kpiReports') || '/api/kpi-reports', { params: { employee_id: employee.id, period: 'year', year: new Date().getFullYear() } }),
        ]).then(([targetResponse, reportResponse]) => {
            if (!mounted) return;
            const targetData = targetResponse.data.data;
            const profile = targetData.employee;
            setState((current) => ({ ...current, loading: false, employee: profile, templates: targetData.templates || [], report: reportResponse.data.data, error: '' }));
            setForm({
                template_id: profile.template_id ? String(profile.template_id) : '',
                target_bonus: profile.target_bonus ?? 40000,
                targets: Object.fromEntries((profile.targets || []).map((metric) => [metric.id, metric.target_value ?? ''])),
            });
        }).catch((error) => mounted && setState((current) => ({ ...current, loading: false, error: error.response?.data?.message || 'Unable to load employee KPI information.' })));
        return () => { mounted = false; };
    }, [employee.id, refreshKey]);

    const templates = state.templates.filter((template) => template.employee_type === employee.employee_type);
    const selectedTemplate = state.templates.find((template) => String(template.id) === String(form.template_id));
    const selectTemplate = (templateId) => {
        const template = state.templates.find((item) => String(item.id) === String(templateId));
        setForm({
            template_id: templateId,
            target_bonus: template?.target_bonus ?? 40000,
            targets: Object.fromEntries((template?.metrics || []).map((metric) => [metric.id, metric.default_target ?? ''])),
        });
    };
    const save = async (event) => {
        event.preventDefault();
        if (!canManage || saving) return;
        setSaving(true);
        setState((current) => ({ ...current, error: '', success: '' }));
        try {
            const { data } = await window.axios.put(`${apiBase('kpiTargets') || '/api/kpi-targets'}/${employee.id}`, {
                template_id: form.template_id || null,
                target_bonus: form.template_id ? form.target_bonus : null,
                targets: (selectedTemplate?.metrics || []).filter((metric) => metric.calculation_type !== 'manual').map((metric) => ({ metric_id: metric.id, target_value: form.targets[metric.id] === '' ? null : form.targets[metric.id] })),
            });
            setState((current) => ({ ...current, success: data.message, error: '' }));
            setRefreshKey((key) => key + 1);
        } catch (error) {
            setState((current) => ({ ...current, error: error.response?.data?.message || 'Unable to save employee KPI target.' }));
        } finally {
            setSaving(false);
        }
    };
    const summary = state.report?.summary || {};
    const reviews = state.report?.reviews || [];
    const formatMoney = (value) => `${Number(value || 0).toLocaleString()} MMK`;

    if (state.loading) return <DetailPanel eyebrow="Performance" title="Employee KPI"><div className="employee-kpi-state"><RefreshCw size={18} className="spin" /> Loading KPI setup and report…</div></DetailPanel>;
    if (state.error && !state.employee) return <DetailPanel eyebrow="Performance" title="Employee KPI"><div className="inline-error"><AlertCircle size={15} /> {state.error}</div></DetailPanel>;

    return (
        <div className="employee-kpi-layout">
                <form className="master-panel employee-kpi-editor" onSubmit={save}>
                    <div className="employee-kpi-section-heading"><div><strong>Individual target</strong><span>Overrides the shared role target for this employee.</span></div>{state.employee?.template_name && <span className="status info">{state.employee.template_name}</span>}</div>
                    {state.error && <div className="inline-error"><AlertCircle size={15} /> {state.error}</div>}
                    {state.success && <div className="inline-success"><Check size={15} /> {state.success}</div>}
                    <div className="employee-kpi-fields">
                        <label className="master-field"><span>KPI role</span><select disabled={!canManage} value={form.template_id} onChange={(event) => selectTemplate(event.target.value)}><option value="">Not assigned</option>{templates.map((template) => <option key={template.id} value={template.id}>{template.name}</option>)}</select></label>
                        <label className="master-field"><span>Monthly target bonus</span><div className="kpi-target-money"><input type="number" min="0" step="1000" disabled={!canManage || !form.template_id} value={form.target_bonus} onChange={(event) => setForm((current) => ({ ...current, target_bonus: event.target.value }))} /><b>MMK</b></div></label>
                    </div>
                    {selectedTemplate && <div className="employee-kpi-target-grid">{selectedTemplate.metrics.map((metric) => <label key={metric.id} className="employee-kpi-target"><span><strong>{metric.name}</strong><small>{metric.weight}% · {metric.unit}</small></span>{metric.calculation_type === 'manual' ? <em>Monthly manager score</em> : <input type="number" min="0" step="0.01" disabled={!canManage} value={form.targets[metric.id] ?? ''} onChange={(event) => setForm((current) => ({ ...current, targets: { ...current.targets, [metric.id]: event.target.value } }))} />}</label>)}</div>}
                    {canManage && <div className="employee-kpi-actions"><button className="button primary" type="submit" disabled={saving}><Save size={15} /> {saving ? 'Saving…' : 'Save individual target'}</button></div>}
                </form>

                <section className="master-panel employee-kpi-report">
                    <div className="employee-kpi-section-heading"><div><strong>{new Date().getFullYear()} KPI report</strong><span>Monthly review performance for this employee.</span></div><TrendingUp size={18} /></div>
                    <div className="employee-kpi-summary">
                        <div><span>Reviews</span><strong>{summary.reviews || 0}</strong></div>
                        <div><span>Average score</span><strong>{Number(summary.average_score || 0).toLocaleString(undefined, { maximumFractionDigits: 2 })}%</strong></div>
                        <div><span>Approved</span><strong>{summary.approved || 0}</strong></div>
                        <div><span>Earned bonus</span><strong>{formatMoney(summary.bonus_total)}</strong></div>
                    </div>
                    {reviews.length ? <div className="employee-kpi-history"><div className="employee-kpi-history-head"><span>Month</span><span>Score</span><span>Status</span><span>Bonus</span></div>{reviews.slice(0, 12).map((review) => <div key={review.id}><strong>{review.month}</strong><span>{Number(review.overall_score || 0).toLocaleString(undefined, { maximumFractionDigits: 2 })}%</span><span className={`status ${review.status}`}>{titleCase(review.status)}</span><strong>{formatMoney(review.bonus_amount)}</strong></div>)}</div> : <div className="employee-kpi-empty"><Target size={20} /><span>No KPI reviews have been created for this employee yet.</span></div>}
                </section>
        </div>
    );
}

function RecordDetailPage({ record, definition, resourceKey, locale, canManage, onBack, onEdit }) {
    const title = record.name || record.shop_name || record.sku || record.code;
    const fields = definition.fields.filter((field) => !['hidden', 'multiselect', 'password'].includes(field.type));
    const employeeProfileFields = fields.filter((field) => !['name', 'code', 'employee_type', 'is_active'].includes(field.name));
    const informationPanel = resourceKey === 'employees' ? (
        <section className="master-panel employee-profile-card">
            <header className="employee-profile-header">
                <span className="employee-profile-avatar"><User size={22} /></span>
                <div><p className="eyebrow">Employment profile</p><strong>{record.name}</strong><small>{record.code} · {optionLabel(record.employee_type, locale)}</small></div>
                <span className={`status ${record.is_active ? 'success' : 'neutral'}`}>{record.is_active ? text(locale, 'active') : text(locale, 'inactive')}</span>
            </header>
            <div className="employee-profile-details">
                {employeeProfileFields.map((field) => <article key={field.name}><span>{fieldLabel(field, locale)}</span><strong>{renderValue(field.name, record[relationAlias(field.name)] ?? record[field.name], locale)}</strong></article>)}
            </div>
        </section>
    ) : (
        <DetailPanel eyebrow={text(locale, 'details')} title="Record information">
            <dl className="record-page-facts">
                {fields.map((field) => <div key={field.name}><dt>{fieldLabel(field, locale)}</dt><dd>{renderValue(field.name, record[relationAlias(field.name)] ?? record[field.name], locale)}</dd></div>)}
            </dl>
        </DetailPanel>
    );
    return (
        <DetailPage
            eyebrow={definition.label || text(locale, 'details')}
            title={title}
            subtitle={record.code && record.code !== title ? record.code : text(locale, 'details')}
            onBack={onBack}
            actions={resourceKey !== 'employees' && onEdit && <button className="button primary" type="button" onClick={onEdit}><Pencil size={15} /> {text(locale, 'edit')}</button>}
        >
            {resourceKey === 'employees' ? <div className="employee-detail-layout">{informationPanel}<EmployeeKpiPanel employee={record} canManage={canManage} /></div> : informationPanel}
        </DetailPage>
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

/* Sales visit workflow removed. Historical implementation retained in source comments
   so existing installations can keep their visit records without an active feature.
export function SalesRouteScreen({ locale = 'en', onViewCustomer, onOrders }) {
    const [refreshKey, setRefreshKey] = useState(0);
    const [search, setSearch] = useState('');
    const [filter, setFilter] = useState('all');
    const [updating, setUpdating] = useState(null);
    const [state, setState] = useState({ loading: true, route: null, date: '', customers: [], summary: {}, error: '' });
    const routeUrl = window.ValleyRuntime?.api?.mobileSalesRoute || '/api/mobile/sales-route';
    const localized = (en, my) => locale === 'my' ? my : en;

    useEffect(() => {
        let mounted = true;
        setState((current) => ({ ...current, loading: true, error: '' }));
        window.axios.get(routeUrl)
            .then(({ data }) => mounted && setState({ loading: false, ...data.data, error: '' }))
            .catch((error) => mounted && setState({ loading: false, route: null, date: '', customers: [], summary: {}, error: requestError(error, locale, 'loadDataError') }));
        return () => { mounted = false; };
    }, [locale, refreshKey, routeUrl]);

    const updateVisit = async (customerId, status) => {
        setUpdating(customerId);
        try {
            await window.axios.post(`${routeUrl}/customers/${customerId}/visit`, { status });
            setRefreshKey((key) => key + 1);
        } catch (error) {
            setState((current) => ({ ...current, error: requestError(error, locale, 'saveError') }));
        } finally {
            setUpdating(null);
        }
    };

    const query = search.trim().toLocaleLowerCase();
    const customers = state.customers.filter((customer) => {
        const matchesSearch = !query || `${customer.code} ${customer.shop_name} ${customer.contact_name || ''} ${customer.address || ''}`.toLocaleLowerCase().includes(query);
        const matchesStatus = filter === 'all' || (filter === 'pending' ? ['planned', 'in_progress'].includes(customer.visit_status) : customer.visit_status === filter);
        return matchesSearch && matchesStatus;
    });
    const total = Number(state.summary.total || 0);
    const completed = Number(state.summary.completed || 0);
    const progress = total ? Math.round((completed / total) * 100) : 0;
    const visitLabel = (status) => ({ planned: localized('Planned', 'စီစဉ်ထား'), in_progress: localized('In progress', 'လုပ်ဆောင်နေ'), completed: localized('Completed', 'ပြီးဆုံး'), skipped: localized('Skipped', 'ကျော်ခဲ့') })[status] || status;
    const visitFamily = (status) => status === 'completed' ? 'success' : status === 'in_progress' ? 'warning' : status === 'skipped' ? 'danger' : 'neutral';

    if (state.loading && !state.route) return <WorkspaceState icon={RefreshCw} title={localized('Loading today’s customer visits', 'ယနေ့ ဖောက်သည်လည်ပတ်မှုများကို ရယူနေသည်')} loading compact />;
    if (state.error && !state.route) return <WorkspaceState icon={AlertCircle} title={state.error} action={() => setRefreshKey((key) => key + 1)} actionLabel={text(locale, 'retry')} compact />;

    return <div className="mobile-master-stack sales-route-page">
        <div className="mobile-master-heading"><div><p className="eyebrow">{localized('Sales territory · Customer visits', 'အရောင်းနယ်မြေ · ဖောက်သည်လည်ပတ်မှု')}</p><h1>{state.route?.name || localized('No territory assigned', 'အရောင်းနယ်မြေ သတ်မှတ်ထားခြင်းမရှိ')}</h1><span className="muted">{state.route?.code} · {state.route?.service_day} · {state.date}</span></div><button className="icon-button" type="button" aria-label="Refresh customer visits" onClick={() => setRefreshKey((key) => key + 1)}><RefreshCw size={17} /></button></div>
        {state.error && <p className="inline-error"><AlertCircle size={15} />{state.error}</p>}
        <section className="sales-route-progress">
            <div><span>{localized('Customer visit progress', 'ဖောက်သည်လည်ပတ်မှု')}</span><strong>{completed} / {total}</strong></div>
            <div className="sales-route-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow={progress}><span style={{ width: `${progress}%` }} /></div>
            <small>{progress}% {localized('completed', 'ပြီးဆုံး')} · {Number(state.summary.in_progress || 0)} {localized('in progress', 'လုပ်ဆောင်နေ')}</small>
        </section>
        <section className="mobile-finance-summary sales-route-summary">
            <article><span>{localized('Orders today', 'ယနေ့ အော်ဒါ')}</span><strong>{Number(state.summary.orders_count || 0)}</strong><small>{money(state.summary.order_amount)}</small></article>
            <article><span>{localized('Customers', 'ဖောက်သည်များ')}</span><strong>{total}</strong><small>{Number(state.summary.skipped || 0)} {localized('skipped visits', 'ကျော်ခဲ့')}</small></article>
        </section>
        <div className="sales-route-quick-actions"><button className="button" type="button" onClick={onOrders}><ShoppingCart size={15} />{localized('Orders', 'အော်ဒါ')}</button></div>
        <section className="mobile-master-section sales-route-stops">
            <div className="sales-route-tools"><label className="mobile-search"><Search size={16} /><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder={localized('Search customer or address', 'ဖောက်သည် သို့မဟုတ် လိပ်စာရှာရန်')} /></label><div className="sales-route-tabs" role="tablist" aria-label="Visit status"><button className={filter === 'all' ? 'is-active' : ''} role="tab" aria-selected={filter === 'all'} type="button" onClick={() => setFilter('all')}><span>{localized('All', 'အားလုံး')}</span><small>{total}</small></button><button className={filter === 'pending' ? 'is-active' : ''} role="tab" aria-selected={filter === 'pending'} type="button" onClick={() => setFilter('pending')}><span>{localized('Pending', 'ကျန်ရှိ')}</span><small>{Math.max(total - completed - Number(state.summary.skipped || 0), 0)}</small></button><button className={filter === 'completed' ? 'is-active' : ''} role="tab" aria-selected={filter === 'completed'} type="button" onClick={() => setFilter('completed')}><span>{localized('Done', 'ပြီး')}</span><small>{completed}</small></button></div></div>
            <div className="mobile-section-heading"><div><p className="eyebrow">{localized('Visit list', 'လည်ပတ်မှုစာရင်း')}</p><h2>{customers.length} {localized('customers', 'ဖောက်သည်')}</h2></div></div>
            {customers.length ? <div className="sales-route-stop-list">{customers.map((customer, index) => <article key={customer.id}>
                <button className="sales-route-customer" type="button" onClick={() => onViewCustomer?.(customer.id)}><span className="sales-route-sequence">{index + 1}</span><span><strong>{customer.shop_name}</strong><small>{customer.code} · {customer.contact_name || customer.phone}</small><small>{customer.address || customer.area || localized('No address', 'လိပ်စာမရှိ')}</small></span><span className={`status ${visitFamily(customer.visit_status)}`}>{visitLabel(customer.visit_status)}</span><ChevronRight size={16} /></button>
                {customer.orders_count > 0 && <div className="sales-route-activity"><span>{customer.orders_count} {localized('orders', 'အော်ဒါ')} · {money(customer.order_amount)}</span></div>}
                <div className="sales-route-stop-actions">{customer.phone && <a className="button route-call-action" href={`tel:${customer.phone}`}><Phone size={14} />{localized('Call', 'ဖုန်းခေါ်')}</a>}{['planned', 'skipped'].includes(customer.visit_status) && <button className="button primary route-primary-action" disabled={updating === customer.id} type="button" onClick={() => updateVisit(customer.id, 'in_progress')}><Play size={14} />{customer.visit_status === 'skipped' ? localized('Resume visit', 'ပြန်စတင်') : localized('Start visit', 'စတင်')}</button>}{customer.visit_status === 'in_progress' && <button className="button primary route-primary-action" disabled={updating === customer.id} type="button" onClick={() => updateVisit(customer.id, 'completed')}><CheckCircle2 size={14} />{localized('Complete visit', 'ပြီးဆုံး')}</button>}{customer.visit_status === 'completed' && <span className="route-action-complete"><CheckCircle2 size={14} />{localized('Visit completed', 'ပြီးဆုံးခဲ့')}</span>}{['planned', 'in_progress'].includes(customer.visit_status) && <button className="icon-button route-skip-action" aria-label={localized(`Skip ${customer.shop_name}`, `${customer.shop_name} ကျော်ရန်`)} title={localized('Skip this stop', 'ဤနေရာကို ကျော်ရန်')} disabled={updating === customer.id} type="button" onClick={() => updateVisit(customer.id, 'skipped')}><SkipForward size={15} /></button>}</div>
            </article>)}</div> : <WorkspaceState icon={Search} title={localized('No customer visits match this view.', 'ကိုက်ညီသော ဖောက်သည်မရှိပါ။')} compact />}
        </section>
    </div>;
}

*/
export function SalesMasterScreen({ locale, onViewCustomer }) {
    const [mode, setMode] = useState('list');
    const [search, setSearch] = useState('');
    const [state, setState] = useState({ loading: true, items: [], route: null, error: '' });
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
                        <div className="customer-mobile-list">{state.items.map((customer) => <button type="button" key={customer.id} onClick={() => onViewCustomer?.(customer.id)}><span className="customer-initial">{customer.shop_name.slice(0, 1)}</span><span><strong>{customer.shop_name}</strong><small>{customer.code} · {customer.contact_name}</small><small>{customer.phone}</small></span><ChevronRight size={17} /></button>)}</div>
                    )}
                </>
            )}
        </div>
    );
}

export function SalesCustomerDetailPage({ customerId, locale, onBack, onViewOrder }) {
    const [attempt, setAttempt] = useState(0);
    const [editing, setEditing] = useState(false);
    const [state, setState] = useState({ loading: true, customer: null, orders: [], summary: {}, topProducts: [], error: '' });

    useEffect(() => {
        let mounted = true;
        setState((current) => ({ ...current, loading: true, error: '' }));
        window.axios.get(`${apiBase('mobileMaster')}/customers/${customerId}`)
            .then(({ data }) => mounted && setState({ loading: false, customer: data.data.customer, orders: data.data.orders || [], summary: data.data.order_summary || {}, topProducts: data.data.top_products || [], error: '' }))
            .catch((error) => mounted && setState({ loading: false, customer: null, orders: [], summary: {}, topProducts: [], error: requestError(error, locale, 'loadCustomersError') }));
        return () => { mounted = false; };
    }, [attempt, customerId, locale]);

    if (state.loading) return <WorkspaceState icon={RefreshCw} title={text(locale, 'loading')} loading compact />;
    if (state.error) return <WorkspaceState icon={AlertCircle} title={state.error} action={() => setAttempt((value) => value + 1)} actionLabel={text(locale, 'retry')} compact />;

    const customer = state.customer;
    return <div className="mobile-master-stack sales-customer-detail-page">
        <ShellBackButton onClick={onBack} label={text(locale, 'cancel')} />
        <div className="mobile-master-heading customer-detail-heading">
            <div><p className="eyebrow">{text(locale, 'details')}</p><h1>{customer.shop_name}</h1><span className="muted">{customer.code} · {customer.route}</span></div>
            <button className="icon-button" type="button" onClick={() => setEditing((value) => !value)} aria-label={text(locale, 'edit')}>{editing ? <X size={18} /> : <Pencil size={18} />}</button>
        </div>
        {editing ? <SalesCustomerForm locale={locale} customer={customer} customerId={customerId} onSaved={() => { setEditing(false); setAttempt((value) => value + 1); }} /> : <section className="mobile-master-section">
            <div className="mobile-profile-identity"><span><Store size={22} /></span><div><small>{text(locale, 'shop')}</small><h2>{customer.contact_name}</h2><p>{customer.phone}</p></div><span className={`status ${customer.is_active ? 'success' : 'neutral'}`}>{customer.is_active ? text(locale, 'active') : text(locale, 'inactive')}</span></div>
            <InfoList record={customer} fields={['email', 'address', 'area', 'route', 'price_type', 'credit_limit']} locale={locale} />
            {customer.phone && <a className="button primary call-action" href={`tel:${customer.phone}`}>{text(locale, 'contact')}</a>}
        </section>}
        <section className="customer-sales-summary">
            <article><span>This month</span><strong>{money(state.summary.monthly_sales)}</strong></article>
            <article><span>This year</span><strong>{money(state.summary.yearly_sales)}</strong></article>
            <article><span>Average order</span><strong>{money(state.summary.average_order_value)}</strong></article>
            <article><span>Outstanding</span><strong>{money(state.summary.outstanding_balance)}</strong><small>{state.summary.available_credit === null ? 'No credit limit' : `${money(state.summary.available_credit)} available`}</small></article>
        </section>
        {state.topProducts.length > 0 && <section className="mobile-master-section customer-top-products"><div className="mobile-section-heading"><div><p className="eyebrow">Buying pattern</p><h2>Top products</h2></div></div><div>{state.topProducts.map((product, index) => <article key={product.product_id || product.sku}><span className="customer-product-rank">{index + 1}</span><span><strong>{product.name}</strong><small>{product.sku} · {Number(product.quantity || 0).toLocaleString()} units</small></span><strong>{money(product.sales)}</strong></article>)}</div></section>}
        <section className="mobile-master-section customer-order-history">
            <div className="mobile-section-heading"><div><p className="eyebrow">Order history</p><h2>{Number(state.summary.orders_count || 0)} orders</h2><small>{Number(state.summary.pending_count || 0)} pending · {money(state.summary.total_amount)}</small></div></div>
            {state.orders.length ? <div className="customer-order-list">{state.orders.map((order) => <button type="button" key={order.id} onClick={() => onViewOrder?.(order.id)}><span><strong>{order.code}</strong><small>{order.order_date} · {order.invoice_code || 'Not invoiced'}</small></span><span><strong>{money(order.total)}</strong><small className={`customer-order-status ${order.status}`}>{titleCase(order.status)}</small></span><ChevronRight size={16} /></button>)}</div> : <WorkspaceState icon={Store} title="No orders for this customer yet." compact />}
        </section>
    </div>;
}

function SalesCustomerForm({ locale, onSaved, customer = null, customerId = null }) {
    const [values, setValues] = useState({ shop_name: customer?.shop_name || '', contact_name: customer?.contact_name || '', phone: customer?.phone || '', email: customer?.email || '', address: customer?.address || '' });
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState('');
    const [saving, setSaving] = useState(false);
    const fields = [
        ['shop_name', locale === 'my' ? 'ဆိုင်အမည်' : 'Shop name'], ['contact_name', locale === 'my' ? 'ဆက်သွယ်သူ' : 'Contact name'],
        ['phone', locale === 'my' ? 'ဖုန်း' : 'Phone'], ['email', locale === 'my' ? 'အီးမေးလ်' : 'Email'], ['address', locale === 'my' ? 'လိပ်စာ' : 'Address'],
    ];
    const submit = async (event) => {
        event.preventDefault(); setSaving(true); setErrors({}); setMessage('');
        try {
            if (customerId) await window.axios.put(`${apiBase('mobileMaster')}/customers/${customerId}`, values);
            else await window.axios.post(`${apiBase('mobileMaster')}/customers`, values);
            setMessage(customerId ? 'Customer updated successfully.' : text(locale, 'registrationSuccess'));
            window.setTimeout(onSaved, 500);
        }
        catch (error) { setErrors(error.response?.data?.errors || {}); setMessage(requestError(error, locale, 'registerCustomerError')); }
        finally { setSaving(false); }
    };
    return (
        <form className="mobile-customer-form" onSubmit={submit}>
            <div><p className="eyebrow">{text(locale, 'masterData')}</p><h2>{customerId ? 'Edit customer' : text(locale, 'registerCustomer')}</h2></div>
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

function money(value) { return `${Number(value || 0).toLocaleString()} MMK`; }

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

function Pagination({ meta = {}, page, setPage, locale, disabled = false }) {
    if (!meta.last_page || meta.last_page <= 1) return null;
    return <div className="master-pagination"><span>{text(locale, 'page')} {meta.current_page} {text(locale, 'of')} {meta.last_page} · {meta.total}</span><div><button type="button" disabled={disabled || page <= 1} onClick={() => setPage((value) => value - 1)} aria-label={text(locale, 'previousPage')} title={text(locale, 'previousPage')}><ChevronLeft size={16} /></button><button type="button" disabled={disabled || page >= meta.last_page} onClick={() => setPage((value) => value + 1)} aria-label={text(locale, 'nextPage')} title={text(locale, 'nextPage')}><ChevronRight size={16} /></button></div></div>;
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
