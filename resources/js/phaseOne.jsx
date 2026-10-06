import {
    AlertCircle,
    Building2,
    CalendarDays,
    Check,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Eye,
    Mail,
    MapPin,
    MapPinned,
    MessageCircle,
    Pencil,
    Phone,
    Plus,
    QrCode,
    RefreshCw,
    Save,
    Search,
    ShoppingCart,
    Store,
    Target,
    Trash2,
    TrendingUp,
    Truck,
    User,
    Users,
    WalletCards,
    X,
    ImagePlus,
    Package,
    Palette,
    Printer,
    ReceiptText,
    Tags,
} from 'lucide-react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { useEffect, useMemo, useRef, useState } from 'react';
import { DetailPage, DetailPanel } from './components/DetailPage';
import { ShellBackButton } from './components/ShellBackButton';
import { ShellPageActions, ShellPageSearch } from './components/ShellPageActions';
import { MonthSelect } from './components/MonthSelect';

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
        allAreas: 'All areas', allRoutes: 'All routes', allPriceTypes: 'All price types',
        noArea: 'No area assigned', noRoute: 'No route assigned', noPriceType: 'No price type assigned', clearFilters: 'Clear filters',
        allEmployeeTypes: 'All employee types', allVehicles: 'All vehicles', noVehicleAssigned: 'No vehicle assigned',
        hireDateFrom: 'Hired from', hireDateTo: 'Hired through',
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
        allAreas: 'ဧရိယာအားလုံး', allRoutes: 'Route အားလုံး', allPriceTypes: 'ဈေးနှုန်းအမျိုးအစားအားလုံး',
        noArea: 'ဧရိယာ မသတ်မှတ်ရသေး', noRoute: 'Route မသတ်မှတ်ရသေး', noPriceType: 'ဈေးနှုန်းအမျိုးအစား မသတ်မှတ်ရသေး', clearFilters: 'Filter များရှင်းရန်',
        allEmployeeTypes: 'ဝန်ထမ်းအမျိုးအစားအားလုံး', allVehicles: 'ယာဉ်အားလုံး', noVehicleAssigned: 'ယာဉ် မသတ်မှတ်ရသေး',
        hireDateFrom: 'အလုပ်ဝင်ရက်မှ', hireDateTo: 'အလုပ်ဝင်ရက်အထိ',
    },
};

const myResourceLabels = {
    areas: 'ဧရိယာများ', routes: 'Route များ', warehouses: 'ဂိုဒေါင်များ',
    brands: 'Brand များ', products: 'ကုန်ပစ္စည်းများ', 'price-types': 'ဈေးနှုန်းအမျိုးအစား',
    'product-prices': 'ကုန်ပစ္စည်းဈေးနှုန်း', customers: 'ဖောက်သည်များ', employees: 'ဝန်ထမ်းများ', suppliers: 'ပေးသွင်းသူများ',
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
    suppliers: 'ဝယ်ယူမှုနှင့် ပေးသွင်းသူစာရင်းအတွက် ကုမ္ပဏီနှင့် ဆက်သွယ်ရန်အချက်အလက်များ။',
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
    assigned_route_id: 'သတ်မှတ် Route', employee_type: 'ဝန်ထမ်းအမျိုးအစား', hire_date: 'အလုပ်ဝင်ရက်', base_salary: 'အခြေခံလစာ (ကျပ်)',
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

const currentMonthValue = () => {
    const date = new Date();
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
};

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

const contactChannelFields = [
    { name: 'phone', label: 'Customer service phone', type: 'tel', placeholder: '09 000 000 000' },
    { name: 'email', label: 'Customer service email', type: 'email', placeholder: 'support@example.com' },
    { name: 'website', label: 'Website', type: 'url', placeholder: 'https://example.com' },
    { name: 'facebook', label: 'Facebook page', type: 'url', placeholder: 'https://facebook.com/your-page' },
    { name: 'viber', label: 'Viber number', type: 'tel', placeholder: '959000000000' },
    { name: 'telegram', label: 'Telegram username', type: 'text', placeholder: '@yourbusiness' },
    { name: 'whatsapp', label: 'WhatsApp number', type: 'tel', placeholder: '959000000000' },
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

export function MasterDataWorkspace({ resourceKey, locale, canManage = true, detailId = null, onNavigate, embedded = false }) {
    const [setup, setSetup] = useState({ loading: true, resources: [], options: {}, error: '' });
    const [setupRetry, setSetupRetry] = useState(0);
    const [records, setRecords] = useState({ loading: true, items: [], meta: {}, error: '' });
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('');
    const [customerFilters, setCustomerFilters] = useState({ area_id: '', route_id: '', price_type_id: '' });
    const [employeeFilters, setEmployeeFilters] = useState({ employee_type: '', assigned_route_id: '', assigned_vehicle_id: '', hire_date_from: '', hire_date_to: '' });
    const [page, setPage] = useState(1);
    const [editing, setEditing] = useState(null);
    const [viewing, setViewing] = useState(null);
    const [detailRevision, setDetailRevision] = useState(0);
    const listPath = `${window.ValleyRuntime?.routes?.office || '/office'}/master/${resourceKey}`;
    const hasDetailPage = !['areas', 'routes', 'warehouses', 'brands', 'products', 'price-types', 'vehicles'].includes(resourceKey);
    const hideListFilters = ['areas', 'routes', 'warehouses'].includes(resourceKey);
    const [refreshKey, setRefreshKey] = useState(0);

    useEffect(() => {
        let mounted = true;
        setSetup((current) => ({ ...current, loading: true, error: '' }));
        window.axios.get(`${apiBase('masterData')}/meta`)
            .then(({ data }) => mounted && setSetup({ loading: false, resources: data.data.resources, options: data.data.options, error: '' }))
            .catch((error) => mounted && setSetup({ loading: false, resources: [], options: {}, error: requestError(error, locale, 'loadSetupError') }));
        return () => { mounted = false; };
    }, [locale, resourceKey, setupRetry]);

    const definition = useMemo(() => setup.resources.find((item) => item.key === resourceKey), [resourceKey, setup.resources]);

    useEffect(() => {
        if (!definition || resourceKey === 'product-prices') return undefined;
        let mounted = true;
        const timer = window.setTimeout(() => {
            setRecords((current) => ({ ...current, loading: true, error: '' }));
            window.axios.get(`${apiBase('masterData')}/${resourceKey}`, {
                params: {
                    search,
                    is_active: status || undefined,
                    ...(resourceKey === 'customers' ? {
                        area_id: customerFilters.area_id || undefined,
                        route_id: customerFilters.route_id || undefined,
                        price_type_id: customerFilters.price_type_id || undefined,
                    } : {}),
                    ...(resourceKey === 'employees' ? Object.fromEntries(
                        Object.entries(employeeFilters).map(([key, value]) => [key, value || undefined]),
                    ) : {}),
                    page,
                    per_page: 10,
                },
            })
                .then(({ data }) => mounted && setRecords({ loading: false, items: data.data.items, meta: data.data.meta, error: '' }))
                .catch((error) => mounted && setRecords({ loading: false, items: [], meta: {}, error: requestError(error, locale, 'loadRecordsError') }));
        }, 220);
        return () => { mounted = false; window.clearTimeout(timer); };
    }, [customerFilters.area_id, customerFilters.price_type_id, customerFilters.route_id, definition, employeeFilters.assigned_route_id, employeeFilters.assigned_vehicle_id, employeeFilters.employee_type, employeeFilters.hire_date_from, employeeFilters.hire_date_to, locale, page, refreshKey, resourceKey, search, status]);

    useEffect(() => {
        setSearch('');
        setStatus('');
        setCustomerFilters({ area_id: '', route_id: '', price_type_id: '' });
        setEmployeeFilters({ employee_type: '', assigned_route_id: '', assigned_vehicle_id: '', hire_date_from: '', hire_date_to: '' });
        setPage(1);
        setEditing(null);
        setViewing(null);
    }, [resourceKey]);

    useEffect(() => {
        if (!detailId) {
            setViewing(null);
            return undefined;
        }
        if (['customers', 'employees'].includes(resourceKey)) {
            setViewing(null);
            return undefined;
        }
        if (!hasDetailPage) {
            setViewing(null);
            onNavigate?.(listPath);
            return undefined;
        }
        let mounted = true;
        window.axios.get(`${apiBase('masterData')}/${resourceKey}/${detailId}`)
            .then(({ data }) => mounted && setViewing(data.data.item))
            .catch((error) => mounted && setRecords((current) => ({ ...current, error: requestError(error, locale, 'loadRecordsError') })));
        return () => { mounted = false; };
    }, [detailId, hasDetailPage, listPath, locale, onNavigate, resourceKey]);

    if (setup.loading) return <WorkspaceState icon={RefreshCw} title={text(locale, 'loading')} loading />;
    if (setup.error) return <WorkspaceState icon={AlertCircle} title={setup.error} action={() => setSetupRetry((value) => value + 1)} actionLabel={text(locale, 'retry')} />;
    if (!definition) return <WorkspaceState icon={AlertCircle} title={text(locale, 'screenNotFound')} />;
    if (resourceKey === 'product-prices') return <ProductPriceMatrix definition={definition} locale={locale} canManage={canManage} embedded={embedded} />;
    if (detailId && resourceKey === 'customers') return <>
        <OfficeCustomerDetailPage
            customerId={detailId}
            locale={locale}
            canManage={canManage}
            onBack={() => onNavigate?.(listPath)}
            onNavigate={onNavigate}
            onEdit={(customer) => setEditing({ mode: 'edit', values: { ...customer } })}
            revision={detailRevision}
        />
        {editing && <MasterForm definition={definition} resourceKey={resourceKey} options={setup.options} editing={editing} locale={locale} onClose={() => setEditing(null)} onSaved={() => { setEditing(null); setDetailRevision((value) => value + 1); }} />}
    </>;
    if (detailId && resourceKey === 'employees') return <>
        <OfficeEmployeeDetailPage
            employeeId={detailId}
            locale={locale}
            canManage={canManage}
            onBack={() => onNavigate?.(listPath)}
            onEdit={(employee) => setEditing({ mode: 'edit', values: { ...employee } })}
            revision={detailRevision}
        />
        {editing && <MasterForm definition={definition} resourceKey={resourceKey} options={setup.options} editing={editing} locale={locale} onClose={() => setEditing(null)} onSaved={() => { setEditing(null); setDetailRevision((value) => value + 1); }} />}
    </>;
    if (detailId && hasDetailPage) return viewing ? <>
        <RecordDetailPage record={viewing} definition={definition} resourceKey={resourceKey} locale={locale} canManage={canManage} onBack={() => onNavigate?.(listPath)} onEdit={canManage ? () => setEditing({ mode: 'edit', values: { ...viewing } }) : null} />
        {editing && <MasterForm definition={definition} resourceKey={resourceKey} options={setup.options} editing={editing} locale={locale} onClose={() => setEditing(null)} onSaved={(item) => { setEditing(null); setViewing(item); }} />}
    </> : <WorkspaceState icon={records.error ? AlertCircle : RefreshCw} title={records.error || text(locale, 'loading')} loading={!records.error} />;

    const openCreate = () => {
        const initial = Object.fromEntries(definition.fields.map((field) => [field.name, field.default ?? (field.type === 'boolean' ? field.name === 'is_active' : field.type === 'multiselect' ? [] : '')]));
        setEditing({ mode: 'create', values: initial });
    };

    const customerRoutes = (setup.options.routes || []).filter((route) => (
        !customerFilters.area_id
        || customerFilters.area_id === '__none__'
        || String(route.area_id || '') === String(customerFilters.area_id)
    ));
    const hasCustomerFilters = resourceKey === 'customers' && (status || Object.values(customerFilters).some(Boolean));
    const hasEmployeeFilters = resourceKey === 'employees' && (status || Object.values(employeeFilters).some(Boolean));

    const remove = async (record) => {
        if (!window.confirm(text(locale, 'deleteConfirm'))) return;
        try {
            await window.axios.delete(`${apiBase('masterData')}/${resourceKey}/${record.id}`);
            setViewing(null);
            setRecords((current) => ({ ...current, items: current.items.filter((item) => item.id !== record.id), meta: { ...current.meta, total: Math.max(0, Number(current.meta.total || 1) - 1) } }));
        } catch (error) {
            setRecords((current) => ({ ...current, error: requestError(error, locale, 'deleteError') }));
        }
    };

    return (
        <section className={`master-workspace ${embedded ? 'is-embedded' : ''}`}>
            {!embedded && <div className="master-heading">
                <div>
                    <p className="eyebrow">{text(locale, 'masterData')}</p>
                    <h1>{resourceLabel(resourceKey, definition, locale)}</h1>
                    <span className="muted">{resourceDescription(resourceKey, definition, locale)}</span>
                </div>
                <ShellPageActions>{canManage && (
                    <button className="button primary" type="button" onClick={openCreate}>
                        <Plus size={16} /> {text(locale, 'add')} {resourceLabel(resourceKey, definition, locale)}
                    </button>
                )}</ShellPageActions>
            </div>}
            {embedded && <ShellPageActions>{canManage && (
                <button className="button primary" type="button" onClick={openCreate}>
                    <Plus size={16} /> {text(locale, 'add')} {resourceLabel(resourceKey, definition, locale)}
                </button>
            )}</ShellPageActions>}

            <div className="master-panel">
                {!hideListFilters && <div className="master-toolbar">
                    <label className="master-search">
                        <Search size={15} />
                        <input value={search} onChange={(event) => { setSearch(event.target.value); setPage(1); }} placeholder={text(locale, 'search')} />
                    </label>
                    <select aria-label={text(locale, 'allStatuses')} value={status} onChange={(event) => { setStatus(event.target.value); setPage(1); }}>
                        <option value="">{text(locale, 'allStatuses')}</option>
                        <option value="1">{text(locale, 'active')}</option>
                        <option value="0">{text(locale, 'inactive')}</option>
                    </select>
                    {resourceKey === 'customers' && <>
                        <select aria-label={text(locale, 'allAreas')} value={customerFilters.area_id} onChange={(event) => { setCustomerFilters((current) => ({ ...current, area_id: event.target.value, route_id: '' })); setPage(1); }}>
                            <option value="">{text(locale, 'allAreas')}</option>
                            <option value="__none__">{text(locale, 'noArea')}</option>
                            {(setup.options.areas || []).map((area) => <option value={area.id} key={area.id}>{area.label}</option>)}
                        </select>
                        <select aria-label={text(locale, 'allRoutes')} value={customerFilters.route_id} onChange={(event) => { setCustomerFilters((current) => ({ ...current, route_id: event.target.value })); setPage(1); }}>
                            <option value="">{text(locale, 'allRoutes')}</option>
                            <option value="__none__">{text(locale, 'noRoute')}</option>
                            {customerRoutes.map((route) => <option value={route.id} key={route.id}>{route.label}</option>)}
                        </select>
                        <select aria-label={text(locale, 'allPriceTypes')} value={customerFilters.price_type_id} onChange={(event) => { setCustomerFilters((current) => ({ ...current, price_type_id: event.target.value })); setPage(1); }}>
                            <option value="">{text(locale, 'allPriceTypes')}</option>
                            <option value="__none__">{text(locale, 'noPriceType')}</option>
                            {(setup.options['price-types'] || []).map((priceType) => <option value={priceType.id} key={priceType.id}>{priceType.label}</option>)}
                        </select>
                        {hasCustomerFilters && <button className="button" type="button" onClick={() => { setStatus(''); setCustomerFilters({ area_id: '', route_id: '', price_type_id: '' }); setPage(1); }}>{text(locale, 'clearFilters')}</button>}
                    </>}
                    {resourceKey === 'employees' && <>
                        <select aria-label={text(locale, 'allEmployeeTypes')} value={employeeFilters.employee_type} onChange={(event) => { setEmployeeFilters((current) => ({ ...current, employee_type: event.target.value })); setPage(1); }}>
                            <option value="">{text(locale, 'allEmployeeTypes')}</option>
                            {['office', 'sales', 'sales_supervisor', 'driver', 'warehouse'].map((type) => <option value={type} key={type}>{optionLabel(type, locale)}</option>)}
                        </select>
                        <select aria-label={text(locale, 'allRoutes')} value={employeeFilters.assigned_route_id} onChange={(event) => { setEmployeeFilters((current) => ({ ...current, assigned_route_id: event.target.value })); setPage(1); }}>
                            <option value="">{text(locale, 'allRoutes')}</option>
                            <option value="__none__">{text(locale, 'noRoute')}</option>
                            {(setup.options.routes || []).map((route) => <option value={route.id} key={route.id}>{route.label}</option>)}
                        </select>
                        <select aria-label={text(locale, 'allVehicles')} value={employeeFilters.assigned_vehicle_id} onChange={(event) => { setEmployeeFilters((current) => ({ ...current, assigned_vehicle_id: event.target.value })); setPage(1); }}>
                            <option value="">{text(locale, 'allVehicles')}</option>
                            <option value="__none__">{text(locale, 'noVehicleAssigned')}</option>
                            {(setup.options.vehicles || []).map((vehicle) => <option value={vehicle.id} key={vehicle.id}>{vehicle.label}</option>)}
                        </select>
                        <label className="master-field"><span>{text(locale, 'hireDateFrom')}</span><input type="date" max={employeeFilters.hire_date_to || undefined} value={employeeFilters.hire_date_from} onChange={(event) => { setEmployeeFilters((current) => ({ ...current, hire_date_from: event.target.value })); setPage(1); }} /></label>
                        <label className="master-field"><span>{text(locale, 'hireDateTo')}</span><input type="date" min={employeeFilters.hire_date_from || undefined} value={employeeFilters.hire_date_to} onChange={(event) => { setEmployeeFilters((current) => ({ ...current, hire_date_to: event.target.value })); setPage(1); }} /></label>
                        {hasEmployeeFilters && <button className="button" type="button" onClick={() => { setStatus(''); setEmployeeFilters({ employee_type: '', assigned_route_id: '', assigned_vehicle_id: '', hire_date_from: '', hire_date_to: '' }); setPage(1); }}>{text(locale, 'clearFilters')}</button>}
                    </>}
                    <button className="icon-button" type="button" aria-label={text(locale, 'retry')} title={text(locale, 'retry')} onClick={() => setRefreshKey((key) => key + 1)}>
                        <RefreshCw size={15} />
                    </button>
                </div>}

                {records.error && <div className="inline-error"><AlertCircle size={15} /> {records.error}</div>}
                {records.loading ? (
                    <TableLoading columns={definition.list.length + (canManage ? 1 : 0)} />
                ) : records.items.length === 0 ? (
                    <WorkspaceState icon={Search} title={text(locale, 'empty')} compact />
                ) : (
                    <div className={`master-table-wrap master-table-wrap-${resourceKey}`}>
                        <table className={`master-table master-data-table master-data-table-${resourceKey}`}>
                            <thead><tr>{definition.list.map((column) => <th key={column}>{columnLabel(column, definition, locale)}</th>)}{canManage && <th className="table-actions-header">{text(locale, 'actions')}</th>}</tr></thead>
                            <tbody>
                                {records.items.map((record) => (
                                    <tr className={hasDetailPage ? 'clickable-row' : undefined} key={record.id} tabIndex={hasDetailPage ? 0 : undefined} onClick={hasDetailPage ? () => onNavigate?.(`${listPath}/${record.id}`) : undefined} onKeyDown={hasDetailPage ? (event) => { if (event.target === event.currentTarget && ['Enter', ' '].includes(event.key)) { event.preventDefault(); onNavigate?.(`${listPath}/${record.id}`); } } : undefined}>
                                        {definition.list.map((column) => <td key={column}>{renderValue(column, record[column], locale)}</td>)}
                                        {canManage && <td className="table-actions-cell" onClick={(event) => event.stopPropagation()}><div className="row-actions">
                                            {resourceKey === 'employees' && <button type="button" aria-label={`Manager score for ${record.name}`} title="Manager score" onClick={() => onNavigate?.(`${listPath}/${record.id}?manager-score=1`)}><CheckCircle2 size={15} /></button>}
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
                    onSaved={(item) => {
                        setEditing(null);
                        setRecords((current) => {
                            const exists = current.items.some((record) => record.id === item.id);
                            return { ...current, items: exists ? current.items.map((record) => record.id === item.id ? item : record) : [item, ...current.items], meta: { ...current.meta, total: Number(current.meta.total || 0) + (exists ? 0 : 1) } };
                        });
                    }}
                />
            )}
        </section>
    );
}

function ProductPriceMatrix({ definition, locale, canManage, embedded = false }) {
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
        <section className={`master-workspace price-matrix-workspace ${embedded ? 'is-embedded' : ''}`}>
            {!embedded && <div className="master-heading">
                <div><p className="eyebrow">{text(locale, 'masterData')}</p><h1>{resourceLabel('product-prices', definition, locale)}</h1><span className="muted">{text(locale, 'priceMatrixHint')}</span></div>
            </div>}
            <ShellPageActions className="price-matrix-heading-actions">{canManage && <>
                <span className={`price-matrix-change-count ${dirtyCount ? 'has-changes' : ''}`}>{dirtyCount ? `${dirtyCount} ${text(locale, 'unsavedChanges')}` : text(locale, 'noUnsavedChanges')}</span>
                <button className="button primary" type="button" disabled={!dirtyCount || saving} onClick={saveAll}><Save size={15} />{saving ? 'Saving…' : text(locale, 'saveAllPrices')}</button>
            </>}</ShellPageActions>
            <div className="master-panel price-matrix-panel">
                <div className="master-toolbar">
                    <label className="master-search"><Search size={15} /><input disabled={Boolean(dirtyCount) || saving} value={search} onChange={(event) => { setSearch(event.target.value); setPage(1); }} placeholder={text(locale, 'search')} /></label>
                    <button className="icon-button" type="button" disabled={Boolean(dirtyCount) || saving} aria-label={text(locale, 'retry')} title={text(locale, 'retry')} onClick={() => setRefreshKey((key) => key + 1)}><RefreshCw size={15} /></button>
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
        const payload = normalizePayload(values, visibleFields);
        try {
            const { data } = editing.mode === 'create'
                ? await window.axios.post(`${apiBase('masterData')}/${resourceKey}`, payload)
                : await window.axios.put(`${apiBase('masterData')}/${resourceKey}/${values.id}`, payload);
            onSaved(data.data.item);
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
                            <MasterField key={field.name} field={field} value={values[field.name]} options={options} locale={locale} error={validationError(field.name, errors, locale)} onChange={(value) => setValues((current) => ({ ...current, [field.name]: value, ...(resourceKey === 'employees' && field.name === 'employee_type' && value !== 'driver' ? { assigned_vehicle_id: '' } : {}), ...(resourceKey === 'employees' && field.name === 'employee_type' ? { access_role: '' } : {}) }))} />
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
            ) : <input type={field.type || 'text'} autoComplete={field.autocomplete} min={field.min} max={field.max} step={field.type === 'number' ? '0.01' : undefined} value={value ?? ''} disabled={disabled} onChange={(event) => onChange(event.target.value)} />}
            {field.name === 'base_salary' && <small>{locale === 'my' ? 'လစဉ်အခြေခံလစာ။ ကွက်လပ်ထားပါက ဝန်ထမ်းအမျိုးအစားအလိုက် မူလလစာကို သုံးပါမည်။' : 'Monthly base salary. Leave blank to use the employee type default.'}</small>}
            {error && <small>{error}</small>}
        </label>
    );
}

export function CompanySettingsScreen({ locale, canManage = true, onBrandingUpdated, embedded = false, section = 'all' }) {
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
            Object.entries(values.contact_channels || {}).forEach(([key, value]) => formData.append(`contact_channels[${key}]`, value || ''));
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
        <section className={`company-settings-page ${embedded ? 'is-embedded' : ''}`}>
            {!embedded && <div className="master-heading">
                <div>
                    <p className="eyebrow">{text(locale, 'settings')}</p>
                    <h1>{text(locale, 'companySettings')}</h1>
                    <span className="muted">{text(locale, 'companySettingsHint')}</span>
                </div>
            </div>}
            <form ref={formRef} className="company-settings-form" onSubmit={submit}>
                {['all', 'company'].includes(section) && <><header>
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
                </div></>}
                {['all', 'branding'].includes(section) && <section className="branding-settings" aria-labelledby="branding-settings-title">
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
                </section>}
                {['all', 'contact-channels'].includes(section) && <section className="branding-settings" aria-labelledby="contact-channel-settings-title">
                    <div className="branding-settings-heading">
                        <span className="company-settings-icon"><MessageCircle size={17} /></span>
                        <div><p className="eyebrow">Customer contact</p><strong id="contact-channel-settings-title">Contact channels</strong><small className="muted">Configured channels appear as contact shortcuts on the customer home page.</small></div>
                    </div>
                    <div className="branding-settings-grid contact-channel-settings-grid">
                        {contactChannelFields.map((field) => <label className="master-field" data-field={`contact_channels.${field.name}`} key={field.name}>
                            <span>{field.label}</span>
                            <input type={field.type} placeholder={field.placeholder} disabled={!canManage} value={values.contact_channels?.[field.name] || ''} onChange={(event) => setValues((current) => ({ ...current, contact_channels: { ...(current.contact_channels || {}), [field.name]: event.target.value } }))} />
                            {validationError(`contact_channels.${field.name}`, errors, locale) && <small>{validationError(`contact_channels.${field.name}`, errors, locale)}</small>}
                        </label>)}
                    </div>
                </section>}
                {['all', 'delivery'].includes(section) && <section className="branding-settings" aria-labelledby="delivery-settings-title">
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
                </section>}
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

const printingApi = () => window.ValleyRuntime?.api?.printSettings || '/api/settings/printing';
const printingPreviewSamples = {
    sales_order: { facts: [['Customer', 'Golden Hill Shop'], ['Date', 'Sep 28, 2026'], ['Route', 'Yangon / Mingalardon'], ['Payment', 'Credit']], columns: ['Item', 'Qty', 'Price', 'Total'], rows: [['Valley Water 1 Liter', '10', '1,000', '10,000'], ['Valley Water 3 Liter', '5', '1,500', '7,500']], total: ['Order total', '17,500'] },
    sales_invoice: { facts: [['Customer', 'Golden Hill Shop'], ['Invoice date', 'Sep 28, 2026'], ['Order', 'ORD-202609-0001'], ['Due date', 'Oct 12, 2026']], columns: ['Item', 'Qty', 'Price', 'Amount'], rows: [['Valley Water 1 Liter', '10', '1,000', '10,000'], ['Valley Water 3 Liter', '5', '1,500', '7,500']], total: ['Invoice total', '17,500'] },
    payment_receipt: { facts: [['Received from', 'Golden Hill Shop'], ['Date', 'Sep 28, 2026'], ['Method', 'Cash'], ['Invoice', 'INV-202609-0001']], columns: ['Description', 'Reference', 'Paid', 'Balance'], rows: [['Invoice payment', 'PAY-0001', '15,000', '2,500']], total: ['Amount received', '15,000'] },
    delivery_voucher: { facts: [['Customer', 'Golden Hill Shop'], ['Planned date', 'Sep 28, 2026'], ['Route', 'Mingalardon'], ['Vehicle', 'A001']], columns: ['Item', 'Loaded', 'Delivered', 'Return'], rows: [['Valley Water 1 Liter', '10', '10', '0'], ['Valley Water 3 Liter', '5', '5', '0']], total: ['Delivered units', '15'] },
    sales_return: { facts: [['Customer', 'Golden Hill Shop'], ['Date', 'Sep 28, 2026'], ['Invoice', 'INV-202609-0001'], ['Reason', 'Returned goods']], columns: ['Item', 'Qty', 'Price', 'Credit'], rows: [['Valley Water 1 Liter', '2', '1,000', '2,000']], total: ['Credit total', '2,000'] },
    stock_receipt: { facts: [['Warehouse', 'Yangon Warehouse'], ['Date', 'Sep 28, 2026'], ['Movement', 'Stock receive'], ['Reference', 'GRN-0001']], columns: ['Product', 'Qty', 'Cost', 'Value'], rows: [['Valley Water 1 Liter', '100', '700', '70,000'], ['Valley Water 3 Liter', '50', '900', '45,000']], total: ['Stock value', '115,000'] },
    stock_transfer: { facts: [['From', 'Yangon Warehouse'], ['To', 'Mandalay Warehouse'], ['Date', 'Sep 28, 2026'], ['Reference', 'TRF-0001']], columns: ['Product', 'Requested', 'Issued', 'Received'], rows: [['Valley Water 1 Liter', '100', '100', '100'], ['Valley Water 3 Liter', '50', '50', '50']], total: ['Transferred units', '150'] },
    stock_adjustment: { facts: [['Warehouse', 'Yangon Warehouse'], ['Date', 'Sep 28, 2026'], ['Reason', 'Closing count'], ['Reference', 'ADJ-0001']], columns: ['Product', 'System', 'Counted', 'Variance'], rows: [['Valley Water 1 Liter', '100', '98', '-2'], ['Valley Water 3 Liter', '50', '51', '+1']], total: ['Net variance', '-1'] },
    expense_payment: { facts: [['Paid to', 'ABC Fuel Station'], ['Date', 'Sep 28, 2026'], ['Category', 'Vehicle fuel'], ['Method', 'Cash']], columns: ['Description', 'Vehicle', 'Reference', 'Amount'], rows: [['Diesel fuel', 'A001', 'EXP-0001', '120,000']], total: ['Payment total', '120,000'] },
    employee_payslip: { facts: [['Employee', 'Sales Demo'], ['Month', 'September 2026'], ['Role', 'Sales Representative'], ['Payment', 'Bank']], columns: ['Earning', 'Amount', 'Deduction', 'Amount'], rows: [['Basic salary', '350,000', 'Attendance', '10,000'], ['KPI bonus', '40,000', 'Advance', '20,000']], total: ['Net pay', '360,000'] },
};

function PrintingSettingsScreen({ canManage = true }) {
    const [state, setState] = useState({ loading: true, error: '', company: null, documents: {} });
    const [selected, setSelected] = useState('sales_order');
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState('');
    const [previewOpen, setPreviewOpen] = useState(false);

    useEffect(() => {
        let mounted = true;
        window.axios.get(printingApi())
            .then(({ data }) => mounted && setState({ loading: false, error: '', company: data.data.company, documents: data.data.documents }))
            .catch((error) => mounted && setState({ loading: false, error: error.response?.data?.message || 'Unable to load printing settings.', company: null, documents: {} }));
        return () => { mounted = false; };
    }, []);

    useEffect(() => {
        if (!previewOpen) return undefined;
        const closeOnEscape = (event) => event.key === 'Escape' && setPreviewOpen(false);
        document.body.classList.add('printing-preview-open');
        window.addEventListener('keydown', closeOnEscape);
        return () => {
            document.body.classList.remove('printing-preview-open');
            window.removeEventListener('keydown', closeOnEscape);
        };
    }, [previewOpen]);

    const setting = state.documents[selected];
    const update = (name, value) => setState((current) => ({
        ...current,
        documents: { ...current.documents, [selected]: { ...current.documents[selected], [name]: value } },
    }));
    const save = async () => {
        if (!canManage || saving) return;
        setSaving(true);
        setMessage('');
        try {
            const { data } = await window.axios.put(printingApi(), { documents: state.documents });
            setState((current) => ({ ...current, documents: data.data.documents }));
            setMessage('Printing settings saved.');
        } catch (error) {
            setMessage(error.response?.data?.message || 'Unable to save printing settings.');
        } finally {
            setSaving(false);
        }
    };

    if (state.loading) return <WorkspaceState icon={Printer} title="Loading printing settings" loading />;
    if (state.error || !setting) return <WorkspaceState icon={AlertCircle} title={state.error || 'Printing settings are unavailable.'} />;

    const contact = [state.company?.phone, state.company?.email].filter(Boolean).join(' · ');
    const address = [state.company?.address, state.company?.city, state.company?.state].filter(Boolean).join(', ');
    const compactPaper = ['80mm', '58mm'].includes(setting.paper_size);
    const preview = printingPreviewSamples[selected] || printingPreviewSamples.sales_order;

    return <section className="printing-settings-page">
        <header className="printing-settings-heading">
            <div><p className="eyebrow">Document printing</p><h2>Voucher and receipt layouts</h2><span className="muted">Set the paper and visible content independently for every document.</span></div>
            <div className="printing-settings-actions">
                <button className="button" type="button" onClick={() => setPreviewOpen(true)}><Eye size={15} />Preview</button>
                {canManage && <button className="button primary" type="button" disabled={saving} onClick={save}><Save size={15} />{saving ? 'Saving…' : 'Save settings'}</button>}
            </div>
        </header>
        {message && <p className={message === 'Printing settings saved.' ? 'inline-success' : 'inline-error'}>{message}</p>}
        <div className="printing-settings-layout">
            <nav className="printing-document-list" aria-label="Printable documents">
                {Object.entries(state.documents).map(([key, document]) => <button className={selected === key ? 'is-active' : ''} type="button" key={key} onClick={() => { setSelected(key); setMessage(''); }}>
                    <span><Printer size={16} /></span><span><strong>{document.label}</strong><small>{document.description}</small></span><small>{document.paper_size}</small>
                </button>)}
            </nav>
            <div className="printing-editor">
                <section className="printing-controls">
                    <header><div><p className="eyebrow">Selected document</p><h3>{setting.label}</h3></div><span>{setting.paper_size} · {setting.orientation}</span></header>
                    <div className="printing-control-grid">
                        <label><span>Paper size</span><select disabled={!canManage} value={setting.paper_size} onChange={(event) => update('paper_size', event.target.value)}><option>A4</option><option>A5</option><option>Letter</option><option>80mm</option><option>58mm</option></select></label>
                        <label><span>Orientation</span><select disabled={!canManage || compactPaper} value={setting.orientation} onChange={(event) => update('orientation', event.target.value)}><option value="portrait">Portrait</option><option value="landscape">Landscape</option></select></label>
                        <label><span>Margin (mm)</span><input disabled={!canManage} type="text" inputMode="numeric" pattern="[0-9]*" value={setting.margin_mm ?? ''} onChange={(event) => update('margin_mm', event.target.value.replace(/\D/g, ''))} /></label>
                        <label><span>Design</span><select disabled={!canManage} value={setting.design} onChange={(event) => update('design', event.target.value)}><option value="classic">Classic</option><option value="compact">Compact</option><option value="minimal">Minimal</option></select></label>
                        <label><span>Accent color</span><div className="printing-color-control"><input disabled={!canManage} type="color" value={setting.accent_color} onChange={(event) => update('accent_color', event.target.value)} /><input disabled={!canManage} value={setting.accent_color} onChange={(event) => update('accent_color', event.target.value)} /></div></label>
                        <label><span>Copies</span><select disabled={!canManage} value={setting.copies} onChange={(event) => update('copies', Number(event.target.value))}><option value="1">1 copy</option><option value="2">2 copies</option><option value="3">3 copies</option></select></label>
                    </div>
                    <div className="printing-toggle-grid">
                        {[['show_logo', 'Company logo'], ['show_address', 'Address'], ['show_contact', 'Contact'], ['show_tax_number', 'Tax number'], ['show_notes', 'Notes'], ['show_signatures', 'Signatures']].map(([key, label]) => <label key={key}><input disabled={!canManage} type="checkbox" checked={Boolean(setting[key])} onChange={(event) => update(key, event.target.checked)} /><span><Check size={12} /></span>{label}</label>)}
                    </div>
                    <label className="printing-text-field"><span>Header note</span><input disabled={!canManage} maxLength="160" placeholder="Optional text below the company heading" value={setting.header_text || ''} onChange={(event) => update('header_text', event.target.value)} /></label>
                    <label className="printing-text-field"><span>Footer note</span><input disabled={!canManage} maxLength="240" placeholder="Optional footer message" value={setting.footer_text || ''} onChange={(event) => update('footer_text', event.target.value)} /></label>
                </section>
            </div>
        </div>
        {previewOpen && <div className="printing-preview-modal-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && setPreviewOpen(false)}>
            <section className="printing-preview-modal" role="dialog" aria-modal="true" aria-labelledby="printing-preview-title">
                <header>
                    <div><p className="eyebrow">Print preview</p><h2 id="printing-preview-title">{setting.label}</h2><span>{setting.paper_size}{compactPaper ? '' : ` · ${setting.orientation}`} · {setting.design}</span></div>
                    <button className="icon-button" type="button" aria-label="Close preview" title="Close preview" onClick={() => setPreviewOpen(false)}><X size={20} /></button>
                </header>
                <div className="printing-preview-modal-body">
                    <div className="printing-preview-stage">
                        <article className={`printing-paper paper-${setting.paper_size.toLowerCase()} is-${setting.orientation} design-${setting.design}`} style={{ '--print-accent': setting.accent_color, '--preview-margin': `${Math.max(4, Number(setting.margin_mm))}px` }}>
                            <header>
                                {setting.show_logo && <span className="printing-preview-logo">{state.company?.logo_url ? <img src={state.company.logo_url} alt="" /> : 'VW'}</span>}
                                <div><strong>{state.company?.name || 'Valley Water Distribution'}</strong>{setting.header_text && <small>{setting.header_text}</small>}{setting.show_address && address && <small>{address}</small>}{setting.show_contact && contact && <small>{contact}</small>}{setting.show_tax_number && state.company?.tax_no && <small>Tax: {state.company.tax_no}</small>}</div>
                            </header>
                            <section className="printing-preview-title"><div><small>DOCUMENT</small><strong>{setting.label}</strong></div><div><small>REFERENCE</small><strong>DOC-202609-0001</strong></div></section>
                            <dl>{preview.facts.map(([label, value]) => <div key={label}><dt>{label}</dt><dd>{value}</dd></div>)}</dl>
                            <table><thead><tr>{preview.columns.map((column) => <th key={column}>{column}</th>)}</tr></thead><tbody>{preview.rows.map((row, rowIndex) => <tr key={rowIndex}>{row.map((value, index) => <td key={`${index}-${value}`}>{value}</td>)}</tr>)}</tbody><tfoot><tr><th colSpan={preview.columns.length - 1}>{preview.total[0]}</th><th>{preview.total[1]}</th></tr></tfoot></table>
                            {setting.show_notes && <p className="printing-preview-notes"><strong>Notes</strong><span>Sample document note</span></p>}
                            {setting.show_signatures && <footer className="printing-preview-signatures"><span>Prepared by</span><span>Approved by</span><span>Received by</span></footer>}
                            {setting.footer_text && <p className="printing-preview-footer">{setting.footer_text}</p>}
                        </article>
                    </div>
                </div>
            </section>
        </div>}
    </section>;
}

export function BusinessSetupScreen({ locale, canManage = true, section = 'company', onNavigate, onBrandingUpdated, qrLocationsContent = null, kpiTargetsContent = null }) {
    const officePath = window.ValleyRuntime?.routes?.office || '/office';
    const groups = [
        { id: 'company', label: 'Company', myLabel: 'ကုမ္ပဏီ', tabs: [
            { id: 'company', label: 'Company information', myLabel: 'ကုမ္ပဏီအချက်အလက်', description: 'Business identity and contacts', myDescription: 'လုပ်ငန်းအချက်အလက်နှင့် ဆက်သွယ်ရန်', icon: Building2 },
            { id: 'contact-channels', label: 'Contact channels', myLabel: 'ဆက်သွယ်ရန်လမ်းကြောင်းများ', description: 'Customer phone, social and web links', myDescription: 'ဖောက်သည်ဖုန်း၊ လူမှုကွန်ရက်နှင့် ဝဘ်လင့်ခ်များ', icon: MessageCircle },
            { id: 'branding', label: 'Branding', myLabel: 'အမှတ်တံဆိပ်', description: 'Logo, app color and theme', myDescription: 'လိုဂို၊ အရောင်နှင့် အပြင်အဆင်', icon: Palette },
            { id: 'delivery', label: 'Delivery defaults', myLabel: 'ပို့ဆောင်ရေးမူလတန်ဖိုး', description: 'Credit limits and due days', myDescription: 'အကြွေးကန့်သတ်ချက်နှင့် ရက်များ', icon: Truck },
        ] },
        { id: 'catalog', label: 'Catalog', myLabel: 'ကုန်ပစ္စည်းစာရင်း', tabs: [
            { id: 'brands', label: 'Brands', myLabel: 'အမှတ်တံဆိပ်များ', description: 'Product brand definitions', myDescription: 'ကုန်ပစ္စည်း အမှတ်တံဆိပ်များ', icon: Store },
            { id: 'products', label: 'Products', myLabel: 'ကုန်ပစ္စည်းများ', description: 'Products and units', myDescription: 'ကုန်ပစ္စည်းနှင့် ယူနစ်များ', icon: Package },
            { id: 'price-types', label: 'Price types', myLabel: 'ဈေးနှုန်းအမျိုးအစား', description: 'Customer pricing tiers', myDescription: 'ဖောက်သည် ဈေးနှုန်းအဆင့်များ', icon: Tags },
            { id: 'product-prices', label: 'Product prices', myLabel: 'ကုန်ပစ္စည်းဈေးနှုန်း', description: 'Price matrix by tier', myDescription: 'အဆင့်အလိုက် ဈေးနှုန်းဇယား', icon: ReceiptText },
        ] },
        { id: 'operations', label: 'Operations', myLabel: 'လုပ်ငန်းလည်ပတ်မှု', tabs: [
            { id: 'areas', label: 'Areas', myLabel: 'ဧရိယာများ', description: 'Service territories', myDescription: 'ဝန်ဆောင်မှုပေးသည့် နယ်မြေများ', icon: MapPinned },
            { id: 'routes', label: 'Routes', myLabel: 'လမ်းကြောင်းများ', description: 'Delivery route definitions', myDescription: 'ပို့ဆောင်ရေး လမ်းကြောင်းများ', icon: MapPinned },
            { id: 'warehouses', label: 'Warehouses', myLabel: 'ဂိုဒေါင်များ', description: 'Stock locations', myDescription: 'ကုန်ပစ္စည်းသိုလှောင်ရာ နေရာများ', icon: Store },
            { id: 'vehicles', label: 'Vehicles', myLabel: 'ယာဉ်များ', description: 'Delivery vehicle records', myDescription: 'ပို့ဆောင်ရေးယာဉ် အချက်အလက်များ', icon: Truck },
        ] },
        { id: 'access-attendance', label: 'Access & Attendance', myLabel: 'အသုံးပြုခွင့်နှင့် တက်ရောက်မှု', tabs: [
            { id: 'roles', label: 'Roles', myLabel: 'ရာထူးနှင့် လုပ်ပိုင်ခွင့်', description: 'App access roles', myDescription: 'အက်ပ်အသုံးပြုခွင့် ရာထူးများ', icon: Target },
            { id: 'permissions', label: 'Permissions', myLabel: 'လုပ်ပိုင်ခွင့်များ', description: 'Permission definitions', myDescription: 'လုပ်ပိုင်ခွင့် သတ်မှတ်ချက်များ', icon: CheckCircle2 },
            { id: 'qr-locations', label: 'QR locations', myLabel: 'QR နေရာများ', description: 'Attendance check-in points', myDescription: 'တက်ရောက်မှု မှတ်တမ်းတင်ရာ နေရာများ', icon: QrCode },
        ] },
        { id: 'people-performance', label: 'People & Performance', myLabel: 'ဝန်ထမ်းနှင့် စွမ်းဆောင်ရည်', tabs: [
            { id: 'kpi-targets', label: 'KPI targets', myLabel: 'KPI ရည်မှန်းချက်များ', description: 'Role targets and staff defaults', myDescription: 'ရာထူးအလိုက် ရည်မှန်းချက်နှင့် ဝန်ထမ်းမူလတန်ဖိုးများ', icon: Target },
        ] },
    ];
    groups[0].tabs.push({
        id: 'printing',
        label: 'Printing',
        myLabel: 'Printing',
        description: 'Paper sizes and document layouts',
        myDescription: 'Paper sizes and document layouts',
        icon: Printer,
    });
    const tabs = groups.flatMap((group) => group.tabs);
    const activeSection = tabs.some((tab) => tab.id === section) ? section : 'company';

    return (
        <section className="page business-setup-page">
            <div className="master-heading business-setup-heading">
                <div>
                    <p className="eyebrow">{locale === 'my' ? 'လုပ်ငန်းစတင်ပြင်ဆင်မှု' : 'Initial setup'}</p>
                    <h1>{locale === 'my' ? 'လုပ်ငန်းအခြေခံအချက်အလက်' : 'Business setup'}</h1>
                    <span className="muted">{locale === 'my' ? 'ကုမ္ပဏီ၊ ကုန်ပစ္စည်း၊ ဈေးနှုန်းနှင့် လုပ်ငန်းလည်ပတ်မှု အခြေခံအချက်အလက်များကို တစ်နေရာတည်းတွင် ပြင်ဆင်ပါ။' : 'Configure company, catalog, pricing, and operational setup in one place.'}</span>
                </div>
            </div>

            <div className="business-setup-layout">
                <nav className="business-setup-tabs" aria-label="Business setup sections" aria-orientation="vertical" role="tablist">
                    {groups.map((group) => <div className="business-setup-tab-group" role="presentation" key={group.id}>
                        <span className="business-setup-tab-group-label">{locale === 'my' ? group.myLabel : group.label}</span>
                        {group.tabs.map((tab) => {
                            const Icon = tab.icon;
                            const href = `${officePath}/setup/${tab.id}`;
                            const selected = activeSection === tab.id;
                            return <a className={selected ? 'is-active' : ''} href={href} role="tab" aria-selected={selected} aria-current={selected ? 'page' : undefined} key={tab.id} onClick={(event) => {
                                if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
                                event.preventDefault();
                                onNavigate?.(href);
                            }}><span className="business-setup-tab-icon"><Icon size={18} /></span><span><strong>{locale === 'my' ? tab.myLabel : tab.label}</strong><small>{locale === 'my' ? tab.myDescription : tab.description}</small></span></a>;
                        })}
                    </div>)}
                </nav>

                <div className="business-setup-content" role="tabpanel">
                    {['company', 'contact-channels', 'branding', 'delivery'].includes(activeSection)
                        ? <CompanySettingsScreen locale={locale} canManage={canManage} onBrandingUpdated={onBrandingUpdated} section={activeSection} embedded />
                        : activeSection === 'printing'
                            ? <PrintingSettingsScreen canManage={canManage} />
                        : activeSection === 'qr-locations'
                            ? qrLocationsContent
                        : activeSection === 'kpi-targets'
                            ? kpiTargetsContent
                            : <MasterDataWorkspace resourceKey={activeSection} locale={locale} canManage={canManage} onNavigate={onNavigate} embedded />}
                </div>
            </div>
        </section>
    );
}

function EmployeeKpiPanel({ employee, canManage, targetOpen = false, onCloseTarget, managerOpen = false, onCloseManager }) {
    const [state, setState] = useState({ loading: true, employee: null, templates: [], report: null, error: '' });
    const [form, setForm] = useState({ template_id: '', target_bonus: 40000, targets: {} });
    const [month, setMonth] = useState(currentMonthValue);
    const activeSection = 'report';
    const [saving, setSaving] = useState(false);
    const [managerLoading, setManagerLoading] = useState(false);
    const [managerSaving, setManagerSaving] = useState(false);
    const [managerError, setManagerError] = useState('');
    const [managerForm, setManagerForm] = useState({ result: null, notes: '', items: [] });
    const [refreshKey, setRefreshKey] = useState(0);

    useEffect(() => {
        let mounted = true;
        setState((current) => ({ ...current, loading: true, error: '' }));
        Promise.all([
            window.axios.get(`${apiBase('kpiTargets') || '/api/kpi-targets'}/employees/${employee.id}`),
            window.axios.get(apiBase('kpiReports') || '/api/kpi-reports', { params: { employee_id: employee.id, period: 'month', month, year: Number(month.slice(0, 4)) } }),
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
    }, [employee.id, month, refreshKey]);

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
        setState((current) => ({ ...current, error: '' }));
        try {
            await window.axios.put(`${apiBase('kpiTargets') || '/api/kpi-targets'}/${employee.id}`, {
                template_id: form.template_id || null,
                target_bonus: form.template_id ? form.target_bonus : null,
                targets: (selectedTemplate?.metrics || []).filter((metric) => metric.calculation_type !== 'manual').map((metric) => ({ metric_id: metric.id, target_value: form.targets[metric.id] === '' ? null : form.targets[metric.id] })),
            });
            setState((current) => ({ ...current, employee: form.template_id ? { ...current.employee, template_id: Number(form.template_id), template_code: selectedTemplate.code, template_name: selectedTemplate.name, target_bonus: Number(form.target_bonus || 0), targets: selectedTemplate.metrics.map((metric) => ({ ...metric, target_value: metric.calculation_type === 'manual' ? metric.default_target : (form.targets[metric.id] === '' ? null : Number(form.targets[metric.id])) })) } : { ...current.employee, template_id: null, template_code: null, template_name: null, target_bonus: null, targets: [] }, error: '' }));
            onCloseTarget?.();
        } catch (error) {
            setState((current) => ({ ...current, error: error.response?.data?.message || 'Unable to save employee KPI target.' }));
        } finally {
            setSaving(false);
        }
    };
    const summary = state.report?.summary || {};
    const reviews = state.report?.reviews || [];
    const review = reviews[0] || null;
    const metrics = state.report?.metric_breakdown || [];
    const history = [...(state.report?.monthly_trend || [])].reverse();
    const previous = history.find((item) => item.label < month) || null;
    const scoreChange = review && previous ? Number(review.overall_score || 0) - Number(previous.average_score || 0) : null;
    const formatMoney = (value) => `${Number(value || 0).toLocaleString()} MMK`;
    const formatMetric = (value, unit) => {
        if (value === null || value === undefined) return '—';
        return String(unit || '').toUpperCase() === 'MMK'
            ? formatMoney(value)
            : `${Number(value).toLocaleString(undefined, { maximumFractionDigits: 2 })}${unit ? ` ${unit}` : ''}`;
    };

    const openManagerScores = async () => {
        if (!review || managerLoading) return;
        setManagerLoading(true);
        setManagerError('');
        try {
            const { data } = await window.axios.get(`${apiBase('kpiReviews') || '/api/kpi-reviews'}/${review.id}`);
            setManagerForm({
                result: data.data.result,
                notes: data.data.result.notes || '',
                items: (data.data.items || []).filter((item) => item.calculation_type === 'manual').map((item) => ({ ...item })),
            });
        } catch (error) {
            setManagerError(error.response?.data?.message || 'Unable to load monthly manager scores.');
        } finally {
            setManagerLoading(false);
        }
    };

    useEffect(() => {
        if (managerOpen && review) openManagerScores();
    }, [managerOpen, review?.id]);

    const updateManagerItem = (id, field, value) => {
        setManagerForm((current) => ({ ...current, items: current.items.map((item) => item.id === id ? { ...item, [field]: value } : item) }));
    };

    const saveManagerScores = async (event) => {
        event.preventDefault();
        if (!canManage || managerSaving || managerForm.result?.status !== 'draft') return;
        setManagerSaving(true);
        setManagerError('');
        try {
            await window.axios.put(`${apiBase('kpiReviews') || '/api/kpi-reviews'}/${managerForm.result.id}`, {
                notes: managerForm.notes || null,
                items: managerForm.items.map((item) => ({
                    id: item.id,
                    target_value: item.target_value,
                    actual_value: item.actual_value,
                    manual_score: item.manual_score === '' ? null : item.manual_score,
                    notes: item.notes || null,
                })),
            });
            onCloseManager?.();
            setRefreshKey((key) => key + 1);
        } catch (error) {
            const validation = error.response?.data?.errors ? Object.values(error.response.data.errors).flat()[0] : null;
            setManagerError(validation || error.response?.data?.message || 'Unable to save monthly manager scores.');
        } finally {
            setManagerSaving(false);
        }
    };

    if (state.loading) return <DetailPanel eyebrow="Performance" title="Employee KPI"><div className="employee-kpi-state"><RefreshCw size={18} className="spin" /> Loading KPI setup and report…</div></DetailPanel>;
    if (state.error && !state.employee) return <DetailPanel eyebrow="Performance" title="Employee KPI"><div className="inline-error"><AlertCircle size={15} /> {state.error}</div></DetailPanel>;

    return (
        <DetailPanel eyebrow="Performance" title="Employee KPI" className="employee-kpi-panel">
            {targetOpen && (
                <div className="modal-backdrop" onMouseDown={(event) => event.target === event.currentTarget && onCloseTarget?.()}>
                <form className="master-dialog employee-kpi-target-dialog" onSubmit={save}>
                    <header><div><p className="eyebrow">Employee KPI</p><h2>Individual target</h2><span className="muted">{employee.name} · {employee.code}</span></div><button className="icon-button" type="button" aria-label="Close individual target" title="Close individual target" onClick={onCloseTarget}><X size={18} /></button></header>
                    <div className="master-form-body employee-kpi-editor">
                    <div className="employee-kpi-section-heading"><div><strong>Individual target</strong><span>Overrides the shared role target for this employee.</span></div>{state.employee?.template_name && <span className="status info">{state.employee.template_name}</span>}</div>
                    {state.error && <div className="inline-error"><AlertCircle size={15} /> {state.error}</div>}
                    <div className="employee-kpi-fields">
                        <label className="master-field"><span>KPI role</span><select disabled={!canManage} value={form.template_id} onChange={(event) => selectTemplate(event.target.value)}><option value="">Not assigned</option>{templates.map((template) => <option key={template.id} value={template.id}>{template.name}</option>)}</select></label>
                        <label className="master-field"><span>Monthly target bonus</span><div className="kpi-target-money"><input type="number" min="0" step="1000" disabled={!canManage || !form.template_id} value={form.target_bonus} onChange={(event) => setForm((current) => ({ ...current, target_bonus: event.target.value }))} /><b>MMK</b></div></label>
                    </div>
                    {selectedTemplate && <div className="employee-kpi-target-grid">{selectedTemplate.metrics.map((metric) => <label key={metric.id} className="employee-kpi-target"><span><strong>{metric.name}</strong><small>{metric.weight}% · {metric.unit}</small></span>{metric.calculation_type === 'manual' ? <em>Monthly manager score</em> : <input type="number" min="0" step="0.01" disabled={!canManage} value={form.targets[metric.id] ?? ''} onChange={(event) => setForm((current) => ({ ...current, targets: { ...current.targets, [metric.id]: event.target.value } }))} />}</label>)}</div>}
                    </div>
                    <footer><button className="button" type="button" onClick={onCloseTarget}>Cancel</button>{canManage && <button className="button primary" type="submit" disabled={saving}><Save size={15} /> {saving ? 'Saving…' : 'Save individual target'}</button>}</footer>
                </form>
                </div>
            )}

            {managerOpen && (
                <div className="modal-backdrop" onMouseDown={(event) => event.target === event.currentTarget && onCloseManager?.()}>
                    <form className="master-dialog employee-manager-score-dialog" onSubmit={saveManagerScores}>
                        <header><div><p className="eyebrow">Monthly manager assessment</p><h2>{employee.name}</h2><span className="muted">{month} · {managerForm.result?.template_name || 'KPI review'}</span></div><button className="icon-button" type="button" aria-label="Close manager scores" title="Close manager scores" onClick={onCloseManager}><X size={18} /></button></header>
                        <div className="master-form-body employee-manager-score-body">
                            {managerError && <div className="inline-error"><AlertCircle size={15} /> {managerError}</div>}
                            {managerLoading ? <div className="employee-kpi-state"><RefreshCw size={18} className="spin" /> Loading manager score form…</div> : <>
                                <div className="employee-kpi-section-heading"><div><strong>Manager scored metrics</strong><span>Enter a score from 0 to 100 for each assessment.</span></div><span className={`status ${managerForm.result?.status || 'neutral'}`}>{titleCase(managerForm.result?.status || 'draft')}</span></div>
                                {managerForm.items.length ? <div className="employee-manager-score-table-wrap"><table className="employee-manager-score-table">
                                    <thead><tr><th>Metric</th><th>Weight</th><th>Score</th><th>Assessment note</th></tr></thead>
                                    <tbody>{managerForm.items.map((item) => <tr key={item.id}>
                                        <td><strong>{item.name}</strong><small>{item.code}</small></td>
                                        <td className="numeric"><strong>{Number(item.weight || 0).toLocaleString(undefined, { maximumFractionDigits: 2 })}%</strong></td>
                                        <td className="editable score"><input type="number" min="0" max="100" step="1" inputMode="numeric" disabled={!canManage || managerForm.result?.status !== 'draft'} value={item.manual_score ?? ''} placeholder="0–100" aria-label={`${item.name} manager score`} onChange={(event) => updateManagerItem(item.id, 'manual_score', event.target.value)} /></td>
                                        <td className="editable note"><input type="text" maxLength="500" disabled={!canManage || managerForm.result?.status !== 'draft'} value={item.notes || ''} placeholder="Optional note" aria-label={`${item.name} assessment note`} onChange={(event) => updateManagerItem(item.id, 'notes', event.target.value)} /></td>
                                    </tr>)}</tbody>
                                </table></div> : <div className="employee-kpi-empty"><Target size={20} /><span>This KPI role has no manager scored metrics.</span></div>}
                                <label className="master-field employee-manager-summary"><span>Monthly review note</span><textarea rows="2" maxLength="1000" disabled={!canManage || managerForm.result?.status !== 'draft'} value={managerForm.notes} placeholder="Optional summary for this employee" onChange={(event) => setManagerForm((current) => ({ ...current, notes: event.target.value }))} /></label>
                            </>}
                        </div>
                        <footer><button className="button" type="button" onClick={onCloseManager}>Cancel</button>{canManage && managerForm.result?.status === 'draft' && <button className="button primary" type="submit" disabled={managerSaving || managerLoading || !managerForm.items.length}><Save size={15} /> {managerSaving ? 'Saving…' : 'Save manager scores'}</button>}</footer>
                    </form>
                </div>
            )}

            {activeSection === 'report' && (
                <section className="employee-kpi-report" role="tabpanel">
                    <div className="employee-kpi-report-toolbar">
                        <div><strong>Monthly KPI report</strong><span>{employee.employee_type === 'sales' || employee.employee_type === 'driver' ? `Matches the KPI report visible in the ${employee.employee_type} app.` : 'Monthly KPI result for this employee.'}</span></div>
                        <div className="employee-kpi-report-actions"><label><CalendarDays size={15} /><span>Report month</span><MonthSelect value={month} aria-label="Employee KPI report month" onChange={(event) => setMonth(event.target.value)} /></label></div>
                    </div>

                    {review ? <>
                        <div className={`employee-kpi-current ${employee.employee_type}`}>
                            <div className="employee-kpi-score" style={{ '--employee-kpi-score': `${Math.min(Math.max(Number(review.overall_score || 0), 0), 100) * 3.6}deg` }}><span><strong>{Number(review.overall_score || 0).toLocaleString(undefined, { maximumFractionDigits: 2 })}</strong><small>Score</small></span></div>
                            <div className="employee-kpi-current-copy">
                                <div><strong>{review.template_name}</strong><span className={`status ${review.status}`}>{titleCase(review.status)}</span></div>
                                <span>{review.month} · {employee.name}</span>
                                <p><small>{review.payroll_adjustment_id ? 'Posted to payroll' : review.status === 'approved' ? 'Approved bonus' : 'Bonus preview'}</small><strong>{formatMoney(review.bonus_amount)}</strong></p>
                                {scoreChange !== null && <em className={scoreChange >= 0 ? 'is-positive' : 'is-negative'}><TrendingUp size={13} /> {scoreChange >= 0 ? '+' : ''}{Number(scoreChange).toLocaleString(undefined, { maximumFractionDigits: 2 })} points from {previous.label}</em>}
                            </div>
                        </div>

                        <div className="employee-kpi-summary">
                            <div><span>Metrics</span><strong>{metrics.length}</strong></div>
                            <div><span>Overall score</span><strong>{Number(summary.average_score || 0).toLocaleString(undefined, { maximumFractionDigits: 2 })}%</strong></div>
                            <div><span>Approval</span><strong>{summary.approved ? 'Approved' : titleCase(review.status)}</strong></div>
                            <div><span>Bonus</span><strong>{formatMoney(summary.bonus_total)}</strong></div>
                        </div>

                        <div className="employee-kpi-metric-grid">
                            {metrics.map((metric) => {
                                const points = metric.points_average === null ? 0 : Number(metric.points_average);
                                const progress = Math.min(Math.max(points / Math.max(Number(metric.weight || 0), 1) * 100, 0), 100);
                                const manual = metric.calculation_type === 'manual';
                                return <article key={`${metric.template_code}-${metric.code}`}>
                                    <header><span><strong>{metric.name}</strong><small>{manual ? 'Manager assessment' : metric.code}</small></span><b>{metric.points_average === null ? 'Pending' : `${Number(metric.points_average).toLocaleString(undefined, { maximumFractionDigits: 2 })} pts`}</b></header>
                                    <div className="employee-kpi-progress"><i style={{ width: `${progress}%` }} /></div>
                                    <dl>
                                        {!manual && <div><dt>Target</dt><dd>{formatMetric(metric.target_average, metric.unit)}</dd></div>}
                                        <div><dt>{manual ? 'Score' : 'Actual'}</dt><dd>{formatMetric(metric.actual_average, metric.unit)}</dd></div>
                                        <div><dt>Achievement</dt><dd>{metric.achievement_average === null ? '—' : `${Number(metric.achievement_average).toLocaleString(undefined, { maximumFractionDigits: 2 })}%`}</dd></div>
                                        <div><dt>Weight</dt><dd>{Number(metric.weight || 0).toLocaleString(undefined, { maximumFractionDigits: 2 })}%</dd></div>
                                    </dl>
                                </article>;
                            })}
                        </div>
                    </> : <div className="employee-kpi-empty"><Target size={20} /><span>No KPI review is available for {month}.</span></div>}

                    {history.length > 0 && <div className="employee-kpi-history"><div className="employee-kpi-history-head"><span>Month</span><span>Score</span><span>Reviews</span><span>Bonus</span></div>{history.slice(0, 12).map((item) => <button className={item.label === month ? 'is-active' : ''} type="button" key={item.label} onClick={() => setMonth(item.label)}><strong>{item.label}</strong><span>{Number(item.average_score || 0).toLocaleString(undefined, { maximumFractionDigits: 2 })}%</span><span>{item.reviews}</span><strong>{formatMoney(item.bonus_total)}</strong></button>)}</div>}
                </section>
            )}
        </DetailPanel>
    );
}

function VehicleAssignmentDialog({ employee, vehicle, vehicles, locale, onClose, onSaved }) {
    const [vehicleId, setVehicleId] = useState(vehicle?.id ? String(vehicle.id) : '');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        const handleKeyDown = (event) => event.key === 'Escape' && !saving && onClose();
        document.addEventListener('keydown', handleKeyDown);
        return () => document.removeEventListener('keydown', handleKeyDown);
    }, [onClose, saving]);

    const selectedVehicle = vehicles.find((item) => String(item.id) === vehicleId);
    const submit = async (event) => {
        event.preventDefault();
        setSaving(true);
        setError('');
        try {
            const { data } = await window.axios.put(`${apiBase('masterData')}/employees/${employee.id}/vehicle`, {
                assigned_vehicle_id: vehicleId || null,
            });
            onSaved(data.data);
        } catch (requestFailure) {
            setError(requestFailure.response?.data?.errors?.assigned_vehicle_id?.[0] || requestError(requestFailure, locale, 'saveError'));
        } finally {
            setSaving(false);
        }
    };

    return <div className="modal-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && !saving && onClose()}>
        <form className="master-dialog vehicle-assignment-dialog" role="dialog" aria-modal="true" aria-labelledby="vehicle-assignment-title" onSubmit={submit}>
            <header>
                <div><p className="eyebrow">Driver assignment</p><h2 id="vehicle-assignment-title">Assign vehicle</h2></div>
                <button className="icon-button" type="button" aria-label="Close vehicle assignment" onClick={onClose} disabled={saving}><X size={17} /></button>
            </header>
            <div className="vehicle-assignment-body">
                <p>Choose the active vehicle operated by <strong>{employee.name}</strong>. Saving a new choice releases the current vehicle.</p>
                <label className="master-field">
                    Vehicle
                    <select value={vehicleId} onChange={(event) => setVehicleId(event.target.value)} autoFocus>
                        <option value="">No vehicle assigned</option>
                        {vehicles.map((item) => <option value={item.id} key={item.id}>{item.code} · {item.plate_no}</option>)}
                    </select>
                </label>
                {selectedVehicle && <div className="vehicle-assignment-summary"><Truck size={18} /><div><strong>{selectedVehicle.code} · {selectedVehicle.plate_no}</strong><span>{titleCase(selectedVehicle.vehicle_type)}{[selectedVehicle.make, selectedVehicle.model].filter(Boolean).length ? ` · ${[selectedVehicle.make, selectedVehicle.model].filter(Boolean).join(' ')}` : ''}</span></div></div>}
                {error && <div className="inline-error"><AlertCircle size={15} /> {error}</div>}
            </div>
            <footer>
                <button className="button" type="button" onClick={onClose} disabled={saving}>Cancel</button>
                <button className="button primary" type="submit" disabled={saving}><Save size={15} /> Save assignment</button>
            </footer>
        </form>
    </div>;
}

function SupervisorTeamDialog({ supervisor, representatives, teamMembers, locale, onClose, onSaved }) {
    const [selectedIds, setSelectedIds] = useState(() => teamMembers.filter((member) => member.is_active).map((member) => String(member.id)));
    const [search, setSearch] = useState('');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        const handleKeyDown = (event) => event.key === 'Escape' && !saving && onClose();
        document.addEventListener('keydown', handleKeyDown);
        return () => document.removeEventListener('keydown', handleKeyDown);
    }, [onClose, saving]);

    const visibleRepresentatives = representatives.filter((representative) => {
        const term = search.trim().toLowerCase();
        return !term || [representative.code, representative.name, representative.assigned_route, representative.phone]
            .some((value) => String(value || '').toLowerCase().includes(term));
    });
    const toggle = (id) => setSelectedIds((current) => current.includes(String(id))
        ? current.filter((selectedId) => selectedId !== String(id))
        : [...current, String(id)]);
    const submit = async (event) => {
        event.preventDefault();
        setSaving(true);
        setError('');
        try {
            const { data } = await window.axios.put(`${apiBase('masterData')}/employees/${supervisor.id}/sales-team`, {
                sales_representative_ids: selectedIds.map(Number),
            });
            onSaved(data.data);
        } catch (requestFailure) {
            setError(requestFailure.response?.data?.errors?.sales_representative_ids?.[0] || requestError(requestFailure, locale, 'saveError'));
        } finally {
            setSaving(false);
        }
    };

    return <div className="modal-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && !saving && onClose()}>
        <form className="master-dialog supervisor-team-dialog" role="dialog" aria-modal="true" aria-labelledby="supervisor-team-title" onSubmit={submit}>
            <header>
                <div><p className="eyebrow">Sales team</p><h2 id="supervisor-team-title">Assign sales representatives</h2></div>
                <button className="icon-button" type="button" aria-label="Close team assignment" onClick={onClose} disabled={saving}><X size={17} /></button>
            </header>
            <div className="supervisor-team-dialog-body">
                <p>Select the representatives managed by <strong>{supervisor.name}</strong>. Each representative can belong to one supervisor only.</p>
                <label className="supervisor-team-search"><Search size={16} /><input type="search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search representative, code, route, or phone" autoFocus /></label>
                <div className="supervisor-team-selection" role="group" aria-label="Available sales representatives">
                    {visibleRepresentatives.length ? visibleRepresentatives.map((representative) => <label key={representative.id} className={selectedIds.includes(String(representative.id)) ? 'is-selected' : ''}>
                        <input type="checkbox" checked={selectedIds.includes(String(representative.id))} onChange={() => toggle(representative.id)} />
                        <span><strong>{representative.name}</strong><small>{representative.code} · {representative.assigned_route || 'Route not assigned'}</small></span>
                        <Check size={16} />
                    </label>) : <div className="supervisor-team-empty">No available sales representatives match this search.</div>}
                </div>
                <div className="supervisor-team-selection-count"><Users size={16} /><strong>{selectedIds.length}</strong> representative{selectedIds.length === 1 ? '' : 's'} selected</div>
                {error && <div className="inline-error"><AlertCircle size={15} /> {error}</div>}
            </div>
            <footer>
                <button className="button" type="button" onClick={onClose} disabled={saving}>Cancel</button>
                <button className="button primary" type="submit" disabled={saving}><Save size={15} /> Save team</button>
            </footer>
        </form>
    </div>;
}

function OfficeEmployeeDetailPage({ employeeId, locale = 'en', canManage, onBack, onEdit, revision = 0 }) {
    const managerScoreRequested = new URLSearchParams(window.location.search).get('manager-score') === '1';
    const [activeTab, setActiveTab] = useState(managerScoreRequested ? 'performance' : 'activity');
    const [attendanceMonth, setAttendanceMonth] = useState(() => {
        const today = new Date();
        return `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}`;
    });
    const [selectedAttendanceDate, setSelectedAttendanceDate] = useState('');
    const [targetOpen, setTargetOpen] = useState(false);
    const [managerScoreOpen, setManagerScoreOpen] = useState(managerScoreRequested);
    const [vehicleAssignmentOpen, setVehicleAssignmentOpen] = useState(false);
    const [teamAssignmentOpen, setTeamAssignmentOpen] = useState(false);
    const [teamRevision, setTeamRevision] = useState(0);
    const [state, setState] = useState({ loading: true, attendanceLoading: true, employee: null, vehicle: null, availableVehicles: [], teamMembers: [], availableSalesRepresentatives: [], attendanceSummary: {}, attendance: [], payroll: [], roleMetrics: [], activity: [], error: '', attendanceError: '' });

    useEffect(() => {
        let mounted = true;
        setState((current) => ({
            ...current,
            loading: !current.employee,
            attendanceLoading: true,
            attendanceSummary: current.employee ? {} : current.attendanceSummary,
            attendance: current.employee ? [] : current.attendance,
            error: current.employee ? current.error : '',
            attendanceError: '',
        }));
        window.axios.get(`${apiBase('masterData')}/employees/${employeeId}/detail`, { params: { attendance_month: attendanceMonth } })
            .then(({ data }) => mounted && setState({
                loading: false,
                attendanceLoading: false,
                employee: data.data.employee,
                vehicle: data.data.vehicle,
                availableVehicles: data.data.available_vehicles || [],
                teamMembers: data.data.team_members || [],
                availableSalesRepresentatives: data.data.available_sales_representatives || [],
                attendanceSummary: data.data.attendance_summary || {},
                attendance: data.data.attendance || [],
                payroll: data.data.payroll || [],
                roleMetrics: data.data.role_metrics || [],
                activity: data.data.activity || [],
                error: '',
                attendanceError: '',
            }))
            .catch((error) => mounted && setState((current) => current.employee
                ? { ...current, loading: false, attendanceLoading: false, attendanceError: requestError(error, locale, 'loadRecordsError') }
                : { ...current, loading: false, attendanceLoading: false, error: requestError(error, locale, 'loadRecordsError') }));
        return () => { mounted = false; };
    }, [attendanceMonth, employeeId, locale, revision, teamRevision]);

    useEffect(() => {
        if (state.employee && state.employee.employee_type !== 'sales_supervisor' && activeTab === 'team') {
            setActiveTab('activity');
        }
    }, [activeTab, state.employee]);

    if (state.loading) return <WorkspaceState icon={RefreshCw} title="Loading employee detail" loading />;
    if (state.error || !state.employee) return <WorkspaceState icon={AlertCircle} title={state.error || 'Employee not found.'} action={onBack} actionLabel="Back to employees" />;

    const employee = state.employee;
    const vehicle = state.vehicle;
    const roleName = titleCase(employee.employee_type);
    const initials = String(employee.name || 'E').split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
    const dateLabel = (value, withTime = false) => {
        if (!value) return '—';
        const source = String(value);
        const parsed = new Date(source.length > 10 ? source.replace(' ', 'T') : `${source}T00:00:00`);
        return new Intl.DateTimeFormat(locale === 'my' ? 'my-MM' : 'en-GB', { day: '2-digit', month: 'short', year: 'numeric', ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}) }).format(parsed);
    };
    const statusTone = (value) => ({ accepted: 'success', approved: 'success', completed: 'success', delivered: 'success', submitted: 'warning', draft: 'neutral', rejected: 'danger', cancelled: 'danger' })[value] || 'info';
    const metricValue = (metric) => metric.format === 'money' ? money(metric.value) : Number(metric.value || 0).toLocaleString();
    const [attendanceYear, attendanceMonthNumber] = attendanceMonth.split('-').map(Number);
    const attendanceMonthDate = new Date(attendanceYear, attendanceMonthNumber - 1, 1);
    const attendanceDaysInMonth = new Date(attendanceYear, attendanceMonthNumber, 0).getDate();
    const attendanceLeadingDays = attendanceMonthDate.getDay();
    const attendanceByDate = state.attendance.reduce((days, record) => {
        const key = String(record.attendance_at || '').slice(0, 10);
        if (!days[key]) days[key] = [];
        days[key].push(record);
        return days;
    }, {});
    const attendanceCalendarCells = Array.from({ length: Math.ceil((attendanceLeadingDays + attendanceDaysInMonth) / 7) * 7 }, (_, index) => {
        const day = index - attendanceLeadingDays + 1;
        if (day < 1 || day > attendanceDaysInMonth) return null;
        const date = `${attendanceMonth}-${String(day).padStart(2, '0')}`;
        const records = attendanceByDate[date] || [];
        return { day, date, records, accepted: records.some((record) => record.status === 'accepted'), rejected: records.some((record) => record.status !== 'accepted') };
    });
    const visibleAttendance = selectedAttendanceDate ? (attendanceByDate[selectedAttendanceDate] || []) : state.attendance;
    const changeAttendanceMonth = (offset) => {
        const next = new Date(attendanceYear, attendanceMonthNumber - 1 + offset, 1);
        setAttendanceMonth(`${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, '0')}`);
        setSelectedAttendanceDate('');
    };
    const scopeDetails = {
        sales: employee.assigned_route ? `Sales route · ${employee.assigned_route}` : 'Sales route pending',
        driver: employee.assigned_route ? `Delivery route · ${employee.assigned_route}` : 'Delivery route pending',
        sales_supervisor: 'Company sales oversight and coaching',
        warehouse: 'Warehouse and inventory operations',
        office: 'Office administration and operations',
    };
    const closeManagerScores = () => {
        setManagerScoreOpen(false);
        const url = new URL(window.location.href);
        if (url.searchParams.has('manager-score')) {
            url.searchParams.delete('manager-score');
            window.history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`);
        }
    };

    return <>
    <DetailPage
        eyebrow="Employees"
        title={employee.name}
        subtitle={`${employee.code} · ${roleName}`}
        onBack={onBack}
        actions={<>
            {canManage && employee.employee_type === 'driver' && <button className="icon-button" type="button" aria-label="Assign vehicle" title="Assign vehicle" onClick={() => setVehicleAssignmentOpen(true)}><Truck size={17} /></button>}
            {canManage && <button className="icon-button" type="button" aria-label="Monthly manager scores" title="Monthly manager scores" onClick={() => { setActiveTab('performance'); setTargetOpen(false); setManagerScoreOpen(true); }}><CheckCircle2 size={17} /></button>}
            {canManage && <button className="icon-button" type="button" aria-label="Individual KPI target" title="Individual KPI target" onClick={() => { setActiveTab('performance'); setManagerScoreOpen(false); setTargetOpen(true); }}><Target size={17} /></button>}
            {canManage && <button className="icon-button" type="button" aria-label="Edit employee" title="Edit employee" onClick={() => onEdit?.(employee)}><Pencil size={17} /></button>}
        </>}
    >
        <section className="customer-detail-hero employee-detail-hero">
            <div className="customer-detail-identity">
                <span className="customer-detail-avatar" aria-hidden="true">{initials}</span>
                <div>
                    <div className="customer-detail-name-row"><h2>{employee.name}</h2><span className={`status ${employee.is_active ? 'success' : 'neutral'}`}>{employee.is_active ? 'Active' : 'Inactive'}</span></div>
                    <strong>{roleName}</strong>
                    <span>{scopeDetails[employee.employee_type] || roleName}</span>
                </div>
            </div>
            <div className="customer-detail-metrics employee-role-metrics">
                {state.roleMetrics.slice(0, 4).map((metric, index) => <article key={metric.label}>{index === 0 ? <TrendingUp size={17} /> : index === 1 ? <CheckCircle2 size={17} /> : index === 2 ? <Package size={17} /> : <WalletCards size={17} />}<span>{metric.label}</span><strong>{metricValue(metric)}</strong></article>)}
            </div>
        </section>

        <div className="customer-detail-frame employee-detail-frame">
            <aside className="customer-profile-panel employee-profile-panel">
                <div className="customer-section-heading"><div><p className="eyebrow">Profile</p><h2>Employment information</h2></div></div>
                <dl className="customer-profile-list">
                    <div><dt>Employee code</dt><dd>{employee.code}</dd></div>
                    <div><dt>Employee type</dt><dd>{roleName}</dd></div>
                    <div><dt>Phone</dt><dd>{employee.phone || '—'}</dd></div>
                    <div><dt>Email</dt><dd>{employee.email || '—'}</dd></div>
                    <div className="employee-profile-wide"><dt>Address</dt><dd>{employee.address || '—'}</dd></div>
                    <div><dt>Hire date</dt><dd>{dateLabel(employee.hire_date)}</dd></div>
                    <div><dt>Base salary</dt><dd>{employee.base_salary == null ? 'Employee type default' : money(employee.base_salary)}</dd></div>
                    {['sales', 'driver'].includes(employee.employee_type) && <div><dt>Assigned route</dt><dd>{employee.assigned_route || 'Not assigned'}</dd></div>}
                    {employee.employee_type === 'sales' && <div className="employee-profile-wide"><dt>Sales supervisor</dt><dd>{employee.supervisor_name ? `${employee.supervisor_code} · ${employee.supervisor_name}` : 'Not assigned'}</dd></div>}
                    {employee.employee_type === 'driver' && <div className="employee-profile-wide"><dt>Assigned vehicle</dt><dd>{employee.assigned_vehicle || 'Not assigned'}</dd></div>}
                    {employee.employee_type === 'driver' && vehicle && <div className="employee-profile-wide"><dt>Vehicle details</dt><dd>{titleCase(vehicle.vehicle_type)} · {[vehicle.make, vehicle.model].filter(Boolean).join(' ') || vehicle.plate_no}{vehicle.capacity ? ` · ${Number(vehicle.capacity).toLocaleString()} capacity` : ''}</dd></div>}
                </dl>
            </aside>

            <section className="customer-history-panel employee-history-panel">
                <div className="customer-history-tabs employee-history-tabs" role="tablist" aria-label="Employee information">
                    {[['activity', 'Role activity'], ...(employee.employee_type === 'sales_supervisor' ? [['team', 'Sales representatives']] : []), ['attendance', 'Attendance'], ['payroll', 'Payroll'], ['performance', 'KPI performance']].map(([key, label]) => <button type="button" role="tab" aria-selected={activeTab === key} className={activeTab === key ? 'is-active' : ''} onClick={() => { setTargetOpen(false); closeManagerScores(); setActiveTab(key); }} key={key}>{label}</button>)}
                </div>

                {activeTab === 'activity' && <div className="customer-history-content" role="tabpanel">
                    {state.roleMetrics.length > 4 && <div className="employee-secondary-metrics">{state.roleMetrics.slice(4).map((metric) => <span key={metric.label}><small>{metric.label}</small><strong>{metricValue(metric)}</strong></span>)}</div>}
                    <div className="customer-section-heading"><div><p className="eyebrow">{roleName} operations</p><h2>Recent activity</h2></div><span>Latest 30 records</span></div>
                    {state.activity.length ? <div className="customer-history-table-wrap"><table className="customer-history-table employee-activity-table"><thead><tr><th>Reference</th><th>Type</th><th>Date</th><th>Status</th><th className="numeric">Value</th></tr></thead><tbody>{state.activity.map((item) => <tr key={`${item.kind}-${item.id}`}><td><strong>{item.reference}</strong></td><td>{item.kind}</td><td>{dateLabel(item.date)}</td><td><span className={`status ${statusTone(item.status)}`}>{titleCase(item.status)}</span></td><td className="numeric"><strong>{item.unit ? `${Number(item.amount || 0).toLocaleString()} ${item.unit}` : money(item.amount)}</strong></td></tr>)}</tbody></table></div> : <WorkspaceState icon={TrendingUp} title={`No ${roleName.toLowerCase()} activity has been recorded.`} compact />}
                </div>}

                {activeTab === 'attendance' && <div className="customer-history-content" role="tabpanel">
                    <div className="customer-credit-strip employee-attendance-strip"><article><span>Accepted in month</span><strong>{state.attendanceLoading ? '…' : Number(state.attendanceSummary.accepted_this_month || 0).toLocaleString()}</strong><small>Valid attendance records</small></article><article><span>Rejected in month</span><strong>{state.attendanceLoading ? '…' : Number(state.attendanceSummary.rejected_this_month || 0).toLocaleString()}</strong><small>Rejected check-ins</small></article><article><span>Last attendance</span><strong>{state.attendanceLoading ? '…' : dateLabel(state.attendanceSummary.last_attendance_at)}</strong><small>Most recent submission</small></article></div>
                    <div className="employee-attendance-calendar-layout">
                        <section className="employee-attendance-calendar-card" aria-label="Employee attendance calendar">
                            <header>
                                <button className="icon-button" type="button" aria-label="Previous month" title="Previous month" onClick={() => changeAttendanceMonth(-1)}><ChevronLeft size={17} /></button>
                                <div><p className="eyebrow">Attendance calendar</p><strong>{new Intl.DateTimeFormat(locale === 'my' ? 'my-MM' : 'en-US', { month: 'long', year: 'numeric' }).format(attendanceMonthDate)}</strong></div>
                                <button className="icon-button" type="button" aria-label="Next month" title="Next month" onClick={() => changeAttendanceMonth(1)}><ChevronRight size={17} /></button>
                            </header>
                            <div className="employee-attendance-calendar" role="grid">
                                {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((day) => <span className="employee-attendance-weekday" key={day}>{day}</span>)}
                                {attendanceCalendarCells.map((cell, index) => cell ? <button key={cell.date} type="button" className={`${cell.accepted ? 'is-accepted' : cell.rejected ? 'is-rejected' : ''} ${selectedAttendanceDate === cell.date ? 'is-selected' : ''}`} onClick={() => setSelectedAttendanceDate((current) => current === cell.date ? '' : cell.date)} aria-label={`${cell.date}, ${cell.records.length} attendance records`}><span>{cell.day}</span>{cell.records.length > 0 && <small>{cell.records.length}</small>}</button> : <span className="employee-attendance-empty-day" key={`empty-${index}`} />)}
                            </div>
                            <footer><span><i className="accepted" />Accepted</span><span><i className="rejected" />Rejected only</span><span><i />No record</span></footer>
                        </section>

                        <section className="employee-attendance-day-card">
                            <div className="customer-section-heading"><div><p className="eyebrow">{selectedAttendanceDate || attendanceMonth}</p><h2>{selectedAttendanceDate ? 'Daily attendance' : 'Monthly records'}</h2></div>{selectedAttendanceDate && <button className="button" type="button" onClick={() => setSelectedAttendanceDate('')}>Show month</button>}</div>
                            {state.attendanceLoading ? <WorkspaceState icon={RefreshCw} title="Loading attendance calendar" loading compact /> : state.attendanceError ? <WorkspaceState icon={AlertCircle} title={state.attendanceError} compact /> : visibleAttendance.length ? <div className="employee-attendance-record-list">{visibleAttendance.map((item) => <article key={item.id}><span className={`employee-attendance-status-dot ${item.status}`} /><div><strong>{item.location_name || 'Unknown location'}</strong><small>{dateLabel(item.attendance_at, true)}</small><small>{item.rejection_reason || (item.distance_m === null ? 'Distance not recorded' : `${Number(item.distance_m).toLocaleString()} m from location`)}</small></div><span className={`status ${statusTone(item.status)}`}>{titleCase(item.status)}</span></article>)}</div> : <WorkspaceState icon={CalendarDays} title="No attendance record for this period." compact />}
                        </section>
                    </div>
                </div>}

                {activeTab === 'team' && employee.employee_type === 'sales_supervisor' && <div className="customer-history-content supervisor-team-tab" role="tabpanel">
                    <div className="customer-section-heading"><div><p className="eyebrow">Assigned team</p><h2>Sales representatives</h2></div><div className="supervisor-team-heading-actions"><span>{state.teamMembers.length} representative{state.teamMembers.length === 1 ? '' : 's'}</span>{canManage && <button className="button primary" type="button" onClick={() => setTeamAssignmentOpen(true)}><Users size={16} /> Manage team</button>}</div></div>
                    {state.teamMembers.length ? <div className="customer-history-table-wrap supervisor-team-table-wrap"><table className="customer-history-table supervisor-team-table"><thead><tr><th>Representative</th><th>Route</th><th>Phone</th><th>Email</th><th>Status</th></tr></thead><tbody>{state.teamMembers.map((member) => <tr key={member.id}><td><strong>{member.name}</strong><small>{member.code}</small></td><td>{member.assigned_route || 'Not assigned'}</td><td>{member.phone || '—'}</td><td>{member.email || '—'}</td><td><span className={`status ${member.is_active ? 'success' : 'neutral'}`}>{member.is_active ? 'Active' : 'Inactive'}</span></td></tr>)}</tbody></table></div> : <WorkspaceState icon={Users} title="No sales representatives are assigned to this supervisor." compact />}
                </div>}

                {activeTab === 'payroll' && <div className="customer-history-content" role="tabpanel">
                    <div className="customer-section-heading"><div><p className="eyebrow">Payroll</p><h2>Salary history</h2></div><span>Latest 24 payroll periods</span></div>
                    {state.payroll.length ? <div className="customer-history-table-wrap"><table className="customer-history-table employee-payroll-table"><thead><tr><th>Payroll</th><th>Month</th><th>Status</th><th className="numeric">Base salary</th><th className="numeric">Gross pay</th><th className="numeric">Deductions</th><th className="numeric">Net pay</th></tr></thead><tbody>{state.payroll.map((item) => <tr key={item.id}><td><strong>{item.code}</strong></td><td>{item.month}</td><td><span className={`status ${statusTone(item.status)}`}>{titleCase(item.status)}</span></td><td className="numeric">{money(item.base_salary)}</td><td className="numeric">{money(item.gross_pay)}</td><td className="numeric">{money(Number(item.advance_deduction || 0) + Number(item.other_deduction || 0))}</td><td className="numeric"><strong>{money(item.net_pay)}</strong></td></tr>)}</tbody></table></div> : <WorkspaceState icon={WalletCards} title="No payroll history is available for this employee." compact />}
                </div>}

                {activeTab === 'performance' && <div className="employee-kpi-tab" role="tabpanel"><EmployeeKpiPanel employee={employee} canManage={canManage} targetOpen={targetOpen} onCloseTarget={() => setTargetOpen(false)} managerOpen={managerScoreOpen} onCloseManager={closeManagerScores} /></div>}
            </section>
        </div>
    </DetailPage>
    {vehicleAssignmentOpen && <VehicleAssignmentDialog employee={employee} vehicle={vehicle} vehicles={state.availableVehicles} locale={locale} onClose={() => setVehicleAssignmentOpen(false)} onSaved={(assignment) => {
        setState((current) => ({
            ...current,
            vehicle: assignment.vehicle,
            availableVehicles: assignment.available_vehicles || current.availableVehicles,
            employee: { ...current.employee, assigned_vehicle_id: assignment.assigned_vehicle_id, assigned_vehicle: assignment.assigned_vehicle },
        }));
        setVehicleAssignmentOpen(false);
    }} />}
    {teamAssignmentOpen && <SupervisorTeamDialog supervisor={employee} representatives={state.availableSalesRepresentatives} teamMembers={state.teamMembers} locale={locale} onClose={() => setTeamAssignmentOpen(false)} onSaved={(assignment) => {
        setState((current) => ({ ...current, teamMembers: assignment.team_members || [], availableSalesRepresentatives: assignment.available_sales_representatives || [] }));
        setTeamAssignmentOpen(false);
        setTeamRevision((current) => current + 1);
    }} />}
    </>;
}

function OfficeCustomerDetailPage({ customerId, locale = 'en', canManage, onBack, onNavigate, onEdit, revision = 0 }) {
    const [activeTab, setActiveTab] = useState('sales');
    const [state, setState] = useState({ loading: true, customer: null, summary: {}, sales: [], payments: [], error: '' });

    useEffect(() => {
        let mounted = true;
        setState((current) => ({ ...current, loading: true, error: '' }));
        window.axios.get(`${apiBase('masterData')}/customers/${customerId}/detail`)
            .then(({ data }) => mounted && setState({
                loading: false,
                customer: data.data.customer,
                summary: data.data.summary || {},
                sales: data.data.sales || [],
                payments: data.data.payments || [],
                error: '',
            }))
            .catch((error) => mounted && setState((current) => ({ ...current, loading: false, error: requestError(error, locale, 'loadCustomersError') })));
        return () => { mounted = false; };
    }, [customerId, locale, revision]);

    if (state.loading) return <WorkspaceState icon={RefreshCw} title="Loading customer detail" loading />;
    if (state.error || !state.customer) return <WorkspaceState icon={AlertCircle} title={state.error || 'Customer not found.'} action={onBack} actionLabel="Back to customers" />;

    const customer = state.customer;
    const summary = state.summary;
    const initials = String(customer.shop_name || customer.contact_name || 'C').split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
    const dateLabel = (value) => value ? new Intl.DateTimeFormat(locale === 'my' ? 'my-MM' : 'en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(`${value}T00:00:00`)) : '—';
    const statusTone = (value) => ({ approved: 'success', delivered: 'success', confirmed: 'info', submitted: 'warning', pending: 'warning', rejected: 'danger', cancelled: 'danger' })[value] || 'neutral';
    const officePath = window.ValleyRuntime?.routes?.office || '/office';
    const latitude = Number(customer.latitude);
    const longitude = Number(customer.longitude);
    const hasGpsPosition = customer.latitude !== null
        && customer.latitude !== ''
        && customer.longitude !== null
        && customer.longitude !== ''
        && Number.isFinite(latitude)
        && Number.isFinite(longitude)
        && latitude >= -90
        && latitude <= 90
        && longitude >= -180
        && longitude <= 180;
    const openStreetMapHref = hasGpsPosition
        ? `https://www.openstreetmap.org/?mlat=${latitude}&mlon=${longitude}#map=16/${latitude}/${longitude}`
        : null;

    return <DetailPage
        eyebrow="Customers"
        title={customer.shop_name}
        subtitle={`${customer.code} · ${customer.contact_name}`}
        onBack={onBack}
        actions={canManage && <button className="icon-button" type="button" aria-label="Edit customer" title="Edit customer" onClick={() => onEdit?.(customer)}><Pencil size={17} /></button>}
    >
        <section className="customer-detail-hero">
            <div className="customer-detail-identity">
                <span className="customer-detail-avatar" aria-hidden="true">{initials}</span>
                <div>
                    <div className="customer-detail-name-row"><h2>{customer.shop_name}</h2><span className={`status ${customer.is_active ? 'success' : 'neutral'}`}>{customer.is_active ? 'Active' : 'Inactive'}</span></div>
                    <strong>{customer.contact_name}</strong>
                    <span>{customer.price_type || 'No price type'} · {customer.area || 'Area pending'} / {customer.route || 'Route pending'}</span>
                </div>
            </div>
            <div className="customer-detail-metrics">
                <article><ShoppingCart size={17} /><span>Total orders</span><strong>{Number(summary.orders_count || 0).toLocaleString()}</strong></article>
                <article><WalletCards size={17} /><span>Total sales</span><strong>{money(summary.total_sales)}</strong></article>
                <article><TrendingUp size={17} /><span>Average order</span><strong>{money(summary.average_order)}</strong></article>
                <article><CalendarDays size={17} /><span>Last purchase</span><strong>{dateLabel(summary.last_order_date)}</strong></article>
            </div>
        </section>

        <div className="customer-detail-frame">
            <aside className="customer-profile-panel">
                <div className="customer-section-heading"><div><p className="eyebrow">Profile</p><h2>Customer information</h2></div></div>
                <dl className="customer-profile-list">
                    <div><dt><User size={15} /> Contact</dt><dd>{customer.contact_name}</dd></div>
                    <div><dt><Phone size={15} /> Phone</dt><dd>{customer.phone || '—'}</dd></div>
                    <div><dt><Mail size={15} /> Email</dt><dd>{customer.email || '—'}</dd></div>
                    <div><dt><MapPin size={15} /> Delivery address</dt><dd>{customer.address || '—'}</dd></div>
                    <div><dt>Area / route</dt><dd>{customer.area || 'Pending'} / {customer.route || 'Pending'}</dd></div>
                    <div><dt>Price type</dt><dd>{customer.price_type || '—'}</dd></div>
                    <div><dt>Credit limit</dt><dd>{money(customer.credit_limit)}</dd></div>
                    <div><dt>Customer since</dt><dd>{dateLabel(String(customer.created_at || '').slice(0, 10))}</dd></div>
                </dl>
            </aside>

            <section className="customer-history-panel">
                <div className="customer-history-tabs" role="tablist" aria-label="Customer activity">
                    <button type="button" role="tab" aria-selected={activeTab === 'sales'} className={activeTab === 'sales' ? 'is-active' : ''} onClick={() => setActiveTab('sales')}>Sales history <span>{state.sales.length}</span></button>
                    <button type="button" role="tab" aria-selected={activeTab === 'payments'} className={activeTab === 'payments' ? 'is-active' : ''} onClick={() => setActiveTab('payments')}>Credit payments <span>{state.payments.length}</span></button>
                    {hasGpsPosition && <button type="button" role="tab" aria-selected={activeTab === 'location'} className={activeTab === 'location' ? 'is-active' : ''} onClick={() => setActiveTab('location')}>GPS location <MapPinned size={14} /></button>}
                </div>

                {activeTab === 'sales' && <div className="customer-history-content" role="tabpanel">
                    <div className="customer-section-heading"><div><p className="eyebrow">Sales</p><h2>Order history</h2></div><span>Latest 50 orders</span></div>
                    {state.sales.length ? <div className="customer-history-table-wrap"><table className="customer-history-table"><thead><tr><th>Order</th><th>Date</th><th>Items</th><th>Payment</th><th>Status</th><th className="numeric">Total</th><th aria-label="Actions" /></tr></thead><tbody>{state.sales.map((order) => <tr key={order.id}><td><strong>{order.code}</strong><small>{order.invoice_code || 'Not invoiced'}</small></td><td>{dateLabel(order.order_date)}</td><td>{order.items_count}</td><td>{titleCase(order.payment_type)}</td><td><span className={`status ${statusTone(order.status)}`}>{titleCase(order.status)}</span></td><td className="numeric"><strong>{money(order.total)}</strong></td><td className="row-action"><button className="icon-button" type="button" aria-label={`Open ${order.code}`} title="Open order" onClick={() => onNavigate?.(`${officePath}/orders/${order.id}`)}><Eye size={16} /></button></td></tr>)}</tbody></table></div> : <WorkspaceState icon={ShoppingCart} title="No sales have been recorded for this customer." compact />}
                </div>}

                {activeTab === 'payments' && <div className="customer-history-content" role="tabpanel">
                    <div className="customer-credit-strip">
                        <article><span>Outstanding</span><strong>{money(summary.outstanding_amount)}</strong><small>Approved balance</small></article>
                        <article><span>Collected</span><strong>{money(summary.collected_amount)}</strong><small>Approved payments</small></article>
                        <article><span>Pending review</span><strong>{money(summary.pending_collection_amount)}</strong><small>Submitted payments</small></article>
                        <article><span>Available credit</span><strong>{summary.available_credit === null ? 'Unlimited' : money(summary.available_credit)}</strong><small>Credit limit {money(customer.credit_limit)}</small></article>
                    </div>
                    <div className="customer-section-heading"><div><p className="eyebrow">Credit account</p><h2>Payment history</h2></div><span>Latest 50 payments</span></div>
                    {state.payments.length ? <div className="customer-history-table-wrap"><table className="customer-history-table payment-table"><thead><tr><th>Receipt</th><th>Date</th><th>Invoice</th><th>Method</th><th>Collected by</th><th>Status</th><th className="numeric">Amount</th></tr></thead><tbody>{state.payments.map((payment) => <tr key={payment.id}><td><strong>{payment.code}</strong><small>{payment.reference_no || payment.source_app}</small></td><td>{dateLabel(payment.collection_date)}</td><td>{payment.invoice_code || 'Account payment'}</td><td>{titleCase(payment.payment_method)}</td><td>{payment.employee_name || 'Office'}</td><td><span className={`status ${statusTone(payment.status)}`}>{titleCase(payment.status)}</span></td><td className="numeric"><strong>{money(payment.amount)}</strong></td></tr>)}</tbody></table></div> : <WorkspaceState icon={WalletCards} title="No credit payments have been recorded." compact />}
                </div>}

                {activeTab === 'location' && hasGpsPosition && <div className="customer-history-content customer-location-tab" role="tabpanel">
                    <div className="customer-section-heading">
                        <div><p className="eyebrow">Delivery location</p><h2>Saved GPS position</h2></div>
                        <a className="button" href={openStreetMapHref} target="_blank" rel="noreferrer"><MapPinned size={15} /> Open map</a>
                    </div>
                    <SalesCustomerLocationMap latitude={latitude} longitude={longitude} label={customer.shop_name} />
                    <dl className="customer-location-facts">
                        <div><dt>Latitude</dt><dd>{latitude.toFixed(7)}</dd></div>
                        <div><dt>Longitude</dt><dd>{longitude.toFixed(7)}</dd></div>
                        <div><dt>Accuracy</dt><dd>{customer.gps_accuracy_m === null || customer.gps_accuracy_m === '' ? 'Not recorded' : `Approximately ${Number(customer.gps_accuracy_m).toLocaleString()} m`}</dd></div>
                        <div><dt>Last recorded</dt><dd>{customer.gps_captured_at ? new Date(customer.gps_captured_at).toLocaleString(locale === 'my' ? 'my-MM' : 'en-GB') : 'Not recorded'}</dd></div>
                    </dl>
                </div>}
            </section>
        </div>
    </DetailPage>;
}

function RecordDetailPage({ record, definition, resourceKey, locale, canManage, onBack, onEdit }) {
    const [individualTargetOpen, setIndividualTargetOpen] = useState(false);
    const title = record.name || record.shop_name || record.sku || record.code;
    const fields = definition.fields.filter((field) => !['hidden', 'multiselect', 'password'].includes(field.type));
    const employeeProfileFields = fields.filter((field) => !['name', 'code', 'employee_type', 'is_active'].includes(field.name));
    const informationPanel = resourceKey === 'employees' ? (
        <DetailPanel eyebrow="Profile" title="Employment information" className="employee-profile-card">
            <div className="employee-profile-header">
                <span className="employee-profile-avatar"><User size={22} /></span>
                <div><strong>{record.name}</strong><small>{record.code} · {optionLabel(record.employee_type, locale)}</small></div>
                <span className={`status ${record.is_active ? 'success' : 'neutral'}`}>{record.is_active ? text(locale, 'active') : text(locale, 'inactive')}</span>
            </div>
            <dl className="record-page-facts employee-profile-details">
                {employeeProfileFields.map((field) => <div key={field.name}><dt>{fieldLabel(field, locale)}</dt><dd>{renderValue(field.name, record[relationAlias(field.name)] ?? record[field.name], locale)}</dd></div>)}
            </dl>
        </DetailPanel>
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
            actions={resourceKey === 'employees'
                ? canManage && <button className="button primary" type="button" aria-label="Individual KPI target" title="Individual KPI target" onClick={() => setIndividualTargetOpen(true)}><Target size={16} /> Individual target</button>
                : onEdit && <button className="button primary" type="button" onClick={onEdit}><Pencil size={15} /> {text(locale, 'edit')}</button>}
        >
            {resourceKey === 'employees' ? <div className="employee-detail-layout">{informationPanel}<EmployeeKpiPanel employee={record} canManage={canManage} targetOpen={individualTargetOpen} onCloseTarget={() => setIndividualTargetOpen(false)} /></div> : informationPanel}
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
        <div className="mobile-master-heading"><div><p className="eyebrow">{localized('Sales territory · Customer visits', 'အရောင်းနယ်မြေ · ဖောက်သည်လည်ပတ်မှု')}</p><h1>{state.route?.name || localized('No territory assigned', 'အရောင်းနယ်မြေ သတ်မှတ်ထားခြင်းမရှိ')}</h1><span className="muted">{state.route?.code} · {state.route?.service_day} · {state.date}</span></div><ShellPageActions><button className="icon-button" type="button" aria-label="Refresh customer visits" onClick={() => setRefreshKey((key) => key + 1)}><RefreshCw size={17} /></button></ShellPageActions></div>
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
export function SalesVisitsScreen({ locale = 'en', onViewCustomer }) {
    const [refreshKey, setRefreshKey] = useState(0);
    const [search, setSearch] = useState('');
    const [filter, setFilter] = useState('all');
    const [updating, setUpdating] = useState(null);
    const [gpsMessage, setGpsMessage] = useState('');
    const [state, setState] = useState({ loading: true, route: null, date: '', customers: [], summary: {}, error: '' });
    const visitsUrl = window.ValleyRuntime?.api?.mobileSalesVisits || '/api/mobile/sales-visits';
    const localized = (en, my) => locale === 'my' ? my : en;

    useEffect(() => {
        let mounted = true;
        setState((current) => ({ ...current, loading: true, error: '' }));
        window.axios.get(visitsUrl)
            .then(({ data }) => mounted && setState({ loading: false, ...data.data, error: '' }))
            .catch((error) => mounted && setState((current) => ({ ...current, loading: false, error: requestError(error, locale, 'loadDataError') })));
        return () => { mounted = false; };
    }, [locale, refreshKey, visitsUrl]);

    const readCurrentPosition = () => new Promise((resolve, reject) => {
        if (!window.isSecureContext || !navigator.geolocation) {
            reject(new Error(localized('GPS is unavailable. Open the app through HTTPS or localhost and enable location access.', 'GPS အသုံးပြု၍မရပါ။ Location access ကိုဖွင့်ပြီး app ကို HTTPS သို့မဟုတ် localhost မှ အသုံးပြုပါ။')));
            return;
        }

        navigator.geolocation.getCurrentPosition(
            ({ coords }) => resolve({
                latitude: coords.latitude,
                longitude: coords.longitude,
                accuracy_m: Number.isFinite(coords.accuracy) ? coords.accuracy : null,
            }),
            (error) => reject(new Error(error.code === 1
                ? localized('Location permission is required to record this visit.', 'ဤလည်ပတ်မှုကို မှတ်တမ်းတင်ရန် Location permission လိုအပ်ပါသည်။')
                : localized('Unable to get a fresh GPS position. Move to an open area and try again.', 'လက်ရှိ GPS တည်နေရာ မရရှိပါ။ နေရာပွင့်တွင် ထပ်မံကြိုးစားပါ။'))),
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
        );
    });

    const updateVisit = async (customerId, status) => {
        setUpdating(customerId);
        setGpsMessage(status === 'skipped' ? '' : localized('Getting current GPS position…', 'လက်ရှိ GPS တည်နေရာ ရယူနေသည်…'));
        setState((current) => ({ ...current, error: '' }));
        try {
            const position = status === 'skipped' ? {} : await readCurrentPosition();
            const { data } = await window.axios.post(`${visitsUrl}/customers/${customerId}`, { status, ...position });
            const visit = data.data.visit;
            setState((current) => {
                const previous = current.customers.find((customer) => customer.id === customerId)?.visit_status || 'planned';
                const summary = { ...current.summary, [previous]: Math.max(0, Number(current.summary?.[previous] || 0) - 1), [status]: Number(current.summary?.[status] || 0) + 1 };
                const customers = current.customers.map((customer) => customer.id === customerId ? { ...customer, visit_status: status, visit_latitude: visit.completed_latitude || visit.started_latitude || customer.visit_latitude, visit_longitude: visit.completed_longitude || visit.started_longitude || customer.visit_longitude, visit_accuracy_m: visit.completed_accuracy_m || visit.started_accuracy_m || customer.visit_accuracy_m } : customer);
                return { ...current, customers, summary };
            });
            setGpsMessage(status === 'completed'
                ? localized('Visit completed and customer GPS saved.', 'လည်ပတ်မှု ပြီးဆုံးပြီး ဖောက်သည် GPS တည်နေရာ သိမ်းပြီးပါပြီ။')
                : status === 'in_progress'
                    ? localized('Visit started with GPS evidence.', 'GPS အထောက်အထားဖြင့် လည်ပတ်မှု စတင်ပြီးပါပြီ။')
                    : localized('Visit skipped.', 'လည်ပတ်မှု ကျော်ထားပါသည်။'));
        } catch (error) {
            setGpsMessage('');
            setState((current) => ({ ...current, error: error.response ? requestError(error, locale, 'saveError') : error.message }));
        } finally {
            setUpdating(null);
        }
    };

    const query = search.trim().toLocaleLowerCase();
    const customers = (state.customers || []).filter((customer) => {
        const matchesSearch = !query || `${customer.code} ${customer.shop_name} ${customer.contact_name || ''} ${customer.address || ''}`.toLocaleLowerCase().includes(query);
        const matchesStatus = filter === 'all'
            || (filter === 'pending' ? ['planned', 'in_progress'].includes(customer.visit_status) : customer.visit_status === filter);
        return matchesSearch && matchesStatus;
    });
    const total = Number(state.summary?.total || 0);
    const completed = Number(state.summary?.completed || 0);
    const pending = Number(state.summary?.planned || 0) + Number(state.summary?.in_progress || 0);
    const progress = total ? Math.round((completed / total) * 100) : 0;
    const visitLabel = (status) => ({
        planned: localized('Not started', 'မစတင်ရသေး'),
        in_progress: localized('In progress', 'လုပ်ဆောင်နေ'),
        completed: localized('Completed', 'ပြီးဆုံး'),
        skipped: localized('Skipped', 'ကျော်ထား'),
    })[status] || status;
    const visitFamily = (status) => status === 'completed' ? 'success' : status === 'in_progress' ? 'warning' : status === 'skipped' ? 'danger' : 'neutral';
    const positionText = (customer) => customer.visit_latitude && customer.visit_longitude
        ? `${Number(customer.visit_latitude).toFixed(6)}, ${Number(customer.visit_longitude).toFixed(6)}${customer.visit_accuracy_m ? ` · ±${Math.round(Number(customer.visit_accuracy_m))} m` : ''}`
        : '';

    if (state.loading && !state.route) return <WorkspaceState icon={RefreshCw} title={localized('Loading customer visits', 'ဖောက်သည်လည်ပတ်မှုများ ရယူနေသည်')} loading compact />;
    if (state.error && !state.route) return <WorkspaceState icon={AlertCircle} title={state.error} action={() => setRefreshKey((key) => key + 1)} actionLabel={text(locale, 'retry')} compact />;

    return <div className="mobile-master-stack sales-route-page">
        <div className="mobile-master-heading">
            <div><p className="eyebrow">{localized('Customer visits', 'ဖောက်သည်လည်ပတ်မှု')}</p><h1>{state.route?.name || localized('No route assigned', 'Route သတ်မှတ်မထားပါ')}</h1><span className="muted">{state.route?.code ? `${state.route.code} · ` : ''}{state.date}</span></div>
            <ShellPageActions><button className="icon-button" type="button" aria-label={localized('Refresh visits', 'ပြန်လည်ရယူရန်')} disabled={Boolean(updating)} onClick={() => setRefreshKey((key) => key + 1)}><RefreshCw size={17} /></button></ShellPageActions>
        </div>
        {state.error && <div className="inline-error"><AlertCircle size={15} /> {state.error}</div>}
        {gpsMessage && <div className="sales-visit-gps-note"><MapPinned size={15} /> {gpsMessage}</div>}
        <section className="sales-route-progress">
            <div><span>{localized('Today’s progress', 'ယနေ့ လုပ်ဆောင်မှု')}</span><strong>{completed} / {total}</strong></div>
            <div className="sales-route-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow={progress}><span style={{ width: `${progress}%` }} /></div>
            <small>{progress}% {localized('completed', 'ပြီးဆုံး')} · {Number(state.summary?.in_progress || 0)} {localized('in progress', 'လုပ်ဆောင်နေ')}</small>
        </section>
        <section className="mobile-master-section sales-route-stops">
            <div className="sales-route-tools">
                <label className="mobile-search"><Search size={16} /><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder={localized('Search customer or address', 'ဖောက်သည် သို့မဟုတ် လိပ်စာရှာရန်')} /></label>
                <div className="sales-route-tabs" role="tablist" aria-label="Visit status">
                    <button className={filter === 'all' ? 'is-active' : ''} role="tab" aria-selected={filter === 'all'} type="button" onClick={() => setFilter('all')}><span>{localized('All', 'အားလုံး')}</span><small>{total}</small></button>
                    <button className={filter === 'pending' ? 'is-active' : ''} role="tab" aria-selected={filter === 'pending'} type="button" onClick={() => setFilter('pending')}><span>{localized('Pending', 'ကျန်ရှိ')}</span><small>{pending}</small></button>
                    <button className={filter === 'completed' ? 'is-active' : ''} role="tab" aria-selected={filter === 'completed'} type="button" onClick={() => setFilter('completed')}><span>{localized('Done', 'ပြီး')}</span><small>{completed}</small></button>
                </div>
            </div>
            <div className="mobile-section-heading"><div><p className="eyebrow">{localized('Assigned customers', 'သတ်မှတ်ထားသော ဖောက်သည်များ')}</p><h2>{customers.length} {localized('customers', 'ဖောက်သည်')}</h2></div></div>
            {customers.length ? <div className="sales-route-stop-list">{customers.map((customer, index) => <article key={customer.id}>
                <button className="sales-route-customer" type="button" onClick={() => onViewCustomer?.(customer.id)}>
                    <span className="sales-route-sequence">{index + 1}</span>
                    <span><strong>{customer.shop_name}</strong><small>{customer.code} · {customer.contact_name || customer.phone || '—'}</small><small>{customer.address || customer.area || localized('No address', 'လိပ်စာမရှိ')}</small></span>
                    <span className={`status ${visitFamily(customer.visit_status)}`}>{visitLabel(customer.visit_status)}</span><ChevronRight size={16} />
                </button>
                {positionText(customer) && <div className="sales-route-stop-location"><MapPinned size={13} /><span>{localized('Visit GPS', 'လည်ပတ်မှု GPS')}: {positionText(customer)}</span></div>}
                <div className="sales-route-stop-actions">
                    {['planned', 'skipped'].includes(customer.visit_status) && <button className="button primary route-primary-action" disabled={updating === customer.id} type="button" onClick={() => updateVisit(customer.id, 'in_progress')}><MapPinned size={14} />{updating === customer.id ? localized('Getting GPS…', 'GPS ရယူနေသည်…') : localized('Start visit', 'လည်ပတ်မှု စတင်')}</button>}
                    {customer.visit_status === 'in_progress' && <button className="button primary route-primary-action" disabled={updating === customer.id} type="button" onClick={() => updateVisit(customer.id, 'completed')}><CheckCircle2 size={14} />{updating === customer.id ? localized('Getting GPS…', 'GPS ရယူနေသည်…') : localized('Complete & save GPS', 'ပြီးဆုံးပြီး GPS သိမ်းမည်')}</button>}
                    {customer.visit_status === 'completed' && <span className="route-action-complete"><CheckCircle2 size={14} />{localized('Customer GPS saved', 'ဖောက်သည် GPS သိမ်းပြီး')}</span>}
                    {['planned', 'in_progress'].includes(customer.visit_status) && <button className="button route-secondary-action" disabled={updating === customer.id} type="button" onClick={() => updateVisit(customer.id, 'skipped')}>{localized('Skip', 'ကျော်မည်')}</button>}
                </div>
            </article>)}</div> : <WorkspaceState icon={Search} title={localized('No customer visits match this view.', 'ကိုက်ညီသော ဖောက်သည်လည်ပတ်မှု မရှိပါ။')} compact />}
        </section>
    </div>;
}

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
            {mode === 'form' && <ShellBackButton onClick={() => setMode('list')} label={text(locale, 'cancel')} />}
            {mode !== 'form' && <ShellPageSearch><label className="shell-search-control"><Search size={15} aria-hidden="true" /><input type="search" value={search} aria-label={text(locale, 'search')} placeholder={text(locale, 'search')} onChange={(event) => setSearch(event.target.value)} /></label></ShellPageSearch>}
            <div className="mobile-master-heading"><div><p className="eyebrow">{text(locale, 'assignedRoute')}</p><h1>{state.route?.name || text(locale, 'assignedCustomers')}</h1></div></div>
            {mode === 'form' ? <SalesCustomerForm locale={locale} onSaved={(customer) => { setState((current) => ({ ...current, items: [customer, ...current.items] })); setMode('list'); }} /> : (
                <>
                    {state.error ? <WorkspaceState icon={AlertCircle} title={state.error} action={() => setRefreshKey((key) => key + 1)} actionLabel={text(locale, 'retry')} compact /> : state.loading ? <WorkspaceState icon={RefreshCw} title={text(locale, 'loading')} loading compact /> : state.items.length === 0 ? <WorkspaceState icon={Search} title={text(locale, 'empty')} compact /> : (
                        <div className="customer-mobile-list">{state.items.map((customer) => <button type="button" key={customer.id} onClick={() => onViewCustomer?.(customer.id)}><span className="customer-initial">{customer.shop_name.slice(0, 1)}</span><span><strong>{customer.shop_name}</strong><small>{customer.code} · {customer.contact_name}</small><small>{customer.phone}</small></span><ChevronRight size={17} /></button>)}</div>
                    )}
                </>
            )}
            {mode !== 'form' && <button className="mobile-order-fab" type="button" title={text(locale, 'registerCustomer')} aria-label={text(locale, 'registerCustomer')} onClick={() => setMode('form')}><Plus size={24} aria-hidden="true" /></button>}
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
    const initials = (customer.shop_name || customer.contact_name || 'C').split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
    const hasLocation = customer.latitude !== null && customer.latitude !== undefined && customer.longitude !== null && customer.longitude !== undefined;
    const mapHref = hasLocation ? `https://www.google.com/maps?q=${customer.latitude},${customer.longitude}` : customer.address ? `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(customer.address)}` : null;
    const joinedAt = customer.created_at ? new Date(customer.created_at).toLocaleDateString(locale === 'my' ? 'my-MM' : 'en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
    const lastPurchase = state.summary.last_order_date ? new Date(`${state.summary.last_order_date}T00:00:00`).toLocaleDateString(locale === 'my' ? 'my-MM' : 'en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : 'No purchases';
    const recentOrders = state.orders.slice(0, 5);

    return <div className="mobile-master-stack sales-customer-detail-page">
        <ShellBackButton onClick={onBack} label={text(locale, 'cancel')} />
        <ShellPageActions><button className="icon-button" type="button" onClick={() => setEditing((value) => !value)} aria-label={editing ? text(locale, 'cancel') : text(locale, 'edit')} title={editing ? text(locale, 'cancel') : text(locale, 'edit')}>{editing ? <X size={18} /> : <Pencil size={18} />}</button></ShellPageActions>

        {editing ? <SalesCustomerForm locale={locale} customer={customer} customerId={customerId} onSaved={(updated) => { setState((current) => ({ ...current, customer: { ...current.customer, ...updated } })); setEditing(false); }} /> : <>
            <section className="sales-customer-profile-card">
                <span className="sales-customer-avatar" aria-hidden="true">{initials}</span>
                <div className="sales-customer-profile-copy">
                    <h1>{customer.shop_name}</h1>
                    <p>{customer.code}</p>
                    <div className="sales-customer-profile-badges"><span className={`status ${customer.is_active ? 'success' : 'neutral'}`}>{customer.is_active ? text(locale, 'active') : text(locale, 'inactive')}</span>{customer.price_type && <span className="sales-customer-group">{customer.price_type}</span>}</div>
                    <small>{customer.contact_name || 'Customer account'}{customer.route ? ` · ${customer.route}` : ''}</small>
                </div>
            </section>

            <section className="mobile-master-section sales-customer-contact-card">
                <div className="sales-customer-card-heading"><div><User size={17} /><h2>Contact information</h2></div></div>
                <dl className="sales-customer-contact-list">
                    <div><dt><Phone size={16} /><span>Phone</span></dt><dd>{customer.phone || '—'}{customer.phone && <a href={`tel:${customer.phone}`} aria-label={`Call ${customer.phone}`}><Phone size={15} /></a>}</dd></div>
                    <div><dt><Mail size={16} /><span>Email</span></dt><dd>{customer.email || '—'}</dd></div>
                    <div><dt><MapPin size={16} /><span>Address</span></dt><dd>{customer.address || '—'}</dd></div>
                    <div><dt><MapPinned size={16} /><span>Area / route</span></dt><dd>{[customer.area, customer.route].filter(Boolean).join(' / ') || '—'}</dd></div>
                    <div><dt><Tags size={16} /><span>Customer group</span></dt><dd>{customer.price_type || 'Default'}</dd></div>
                    <div><dt><CalendarDays size={16} /><span>Date joined</span></dt><dd>{joinedAt}</dd></div>
                </dl>
            </section>

            <section className="mobile-master-section sales-customer-purchase-card">
                <div className="sales-customer-card-heading"><div><TrendingUp size={17} /><h2>Purchase summary</h2></div><span>This account</span></div>
                <div className="sales-customer-summary-grid">
                    <article><span className="sales-customer-summary-icon"><ShoppingCart size={17} /></span><small>Total orders</small><strong>{Number(state.summary.orders_count || 0).toLocaleString()}</strong></article>
                    <article><span className="sales-customer-summary-icon"><WalletCards size={17} /></span><small>Total spent</small><strong>{money(state.summary.total_amount)}</strong></article>
                    <article><span className="sales-customer-summary-icon"><CalendarDays size={17} /></span><small>Last purchase</small><strong>{lastPurchase}</strong></article>
                    <article><span className="sales-customer-summary-icon"><TrendingUp size={17} /></span><small>Average order</small><strong>{money(state.summary.average_order_value)}</strong></article>
                </div>
                <div className="sales-customer-credit-summary"><span><small>Outstanding credit</small><strong>{money(state.summary.outstanding_balance)}</strong></span><span><small>Available credit</small><strong>{state.summary.available_credit === null ? 'No limit' : money(state.summary.available_credit)}</strong></span></div>
            </section>

            <section className="mobile-master-section sales-customer-location-card">
                <div className="sales-customer-card-heading"><div><MapPinned size={17} /><h2>Location</h2></div>{mapHref && <a className="sales-customer-text-action" href={mapHref} target="_blank" rel="noreferrer">View location <ChevronRight size={14} /></a>}</div>
                {hasLocation ? <SalesCustomerLocationMap latitude={customer.latitude} longitude={customer.longitude} label={customer.shop_name} /> : <div className="sales-customer-map-empty"><MapPin size={22} /><strong>GPS position not saved</strong><small>Edit this customer and capture the current position.</small></div>}
                <div className="sales-customer-location-copy"><MapPin size={16} /><span><strong>{customer.address || 'Saved customer position'}</strong><small>{[customer.area, customer.route].filter(Boolean).join(' · ') || (hasLocation ? `${customer.latitude}, ${customer.longitude}` : '')}</small></span></div>
            </section>

            {state.topProducts.length > 0 && <section className="mobile-master-section customer-top-products sales-customer-products-card"><div className="sales-customer-card-heading"><div><Package size={17} /><h2>Top products</h2></div><span>Buying pattern</span></div><div>{state.topProducts.slice(0, 3).map((product, index) => <article key={product.product_id || product.sku}><span className="customer-product-rank">{index + 1}</span><span><strong>{product.name}</strong><small>{product.sku} · {Number(product.quantity || 0).toLocaleString()} units</small></span><strong>{money(product.sales)}</strong></article>)}</div></section>}

            <section className="mobile-master-section customer-order-history sales-customer-orders-card">
                <div className="sales-customer-card-heading"><div><ShoppingCart size={17} /><h2>Recent orders</h2></div><span>{Number(state.summary.orders_count || 0)} total</span></div>
                {recentOrders.length ? <div className="customer-order-list">{recentOrders.map((order) => <button type="button" key={order.id} onClick={() => onViewOrder?.(order.id)}><span><strong>{order.code}</strong><small>{order.order_date} · {order.invoice_code || 'Not invoiced'}</small></span><span><strong>{money(order.total)}</strong><small className={`customer-order-status ${order.status}`}>{titleCase(order.status)}</small></span><ChevronRight size={16} /></button>)}</div> : <WorkspaceState icon={Store} title="No orders for this customer yet." compact />}
            </section>
        </>}
    </div>;
}

function SalesCustomerLocationMap({ latitude, longitude, label }) {
    const containerRef = useRef(null);

    useEffect(() => {
        if (!containerRef.current) return undefined;
        const lat = Number(latitude);
        const lng = Number(longitude);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return undefined;

        const markerColor = window.getComputedStyle(containerRef.current).getPropertyValue('--color-primary').trim() || '#2874bc';
        const map = L.map(containerRef.current, { zoomControl: true, attributionControl: true }).setView([lat, lng], 16);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);
        L.circleMarker([lat, lng], { radius: 9, color: '#fff', weight: 3, fillColor: markerColor, fillOpacity: 1 }).addTo(map).bindPopup(label || 'Customer location');
        window.setTimeout(() => map.invalidateSize(), 0);
        return () => map.remove();
    }, [label, latitude, longitude]);

    return <div ref={containerRef} className="sales-customer-map" aria-label={`Map showing ${label || 'customer'} location`} />;
}

function SalesCustomerForm({ locale, onSaved, customer = null, customerId = null }) {
    const [values, setValues] = useState({ shop_name: customer?.shop_name || '', contact_name: customer?.contact_name || '', phone: customer?.phone || '', email: customer?.email || '', address: customer?.address || '', latitude: customer?.latitude ?? '', longitude: customer?.longitude ?? '', gps_accuracy_m: customer?.gps_accuracy_m ?? '' });
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState('');
    const [saving, setSaving] = useState(false);
    const [locating, setLocating] = useState(false);
    const fields = [
        ['shop_name', locale === 'my' ? 'ဆိုင်အမည်' : 'Shop name'], ['contact_name', locale === 'my' ? 'ဆက်သွယ်သူ' : 'Contact name'],
        ['phone', locale === 'my' ? 'ဖုန်း' : 'Phone'], ['email', locale === 'my' ? 'အီးမေးလ်' : 'Email'], ['address', locale === 'my' ? 'လိပ်စာ' : 'Address'],
    ];
    const capturePosition = () => {
        if (!navigator.geolocation) { setMessage('GPS is not available on this device.'); return; }
        setLocating(true); setMessage('');
        navigator.geolocation.getCurrentPosition(
            (position) => {
                setValues((current) => ({ ...current, latitude: position.coords.latitude.toFixed(7), longitude: position.coords.longitude.toFixed(7), gps_accuracy_m: Math.round(position.coords.accuracy) }));
                setMessage('Current GPS position captured. Save changes to update the customer.');
                setLocating(false);
            },
            (error) => { setMessage(error.message || 'Unable to capture the current GPS position.'); setLocating(false); },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
        );
    };
    const submit = async (event) => {
        event.preventDefault(); setSaving(true); setErrors({}); setMessage('');
        try {
            const { data } = customerId
                ? await window.axios.put(`${apiBase('mobileMaster')}/customers/${customerId}`, values)
                : await window.axios.post(`${apiBase('mobileMaster')}/customers`, values);
            setMessage(customerId ? 'Customer updated successfully.' : text(locale, 'registrationSuccess'));
            window.setTimeout(() => onSaved(data.data.customer), 500);
        }
        catch (error) { setErrors(error.response?.data?.errors || {}); setMessage(requestError(error, locale, 'registerCustomerError')); }
        finally { setSaving(false); }
    };
    return (
        <form className="mobile-customer-form" onSubmit={submit}>
            <div><p className="eyebrow">{text(locale, 'masterData')}</p><h2>{customerId ? 'Edit customer' : text(locale, 'registerCustomer')}</h2></div>
            {fields.map(([name, label]) => <label key={name}>{label}{name === 'address' ? <textarea rows="3" value={values[name]} onChange={(event) => setValues((current) => ({ ...current, [name]: event.target.value }))} /> : <input type={name === 'email' ? 'email' : 'text'} value={values[name]} onChange={(event) => setValues((current) => ({ ...current, [name]: event.target.value }))} />}{errors[name] && <small>{validationError(name, errors, locale)}</small>}</label>)}
            {customerId && <fieldset className="sales-customer-gps-fields"><legend>Customer GPS position</legend><div><label>Latitude<input type="text" inputMode="decimal" value={values.latitude} onChange={(event) => setValues((current) => ({ ...current, latitude: event.target.value }))} />{errors.latitude && <small>{validationError('latitude', errors, locale)}</small>}</label><label>Longitude<input type="text" inputMode="decimal" value={values.longitude} onChange={(event) => setValues((current) => ({ ...current, longitude: event.target.value }))} />{errors.longitude && <small>{validationError('longitude', errors, locale)}</small>}</label></div><button className="button" type="button" disabled={locating} onClick={capturePosition}><MapPinned size={15} />{locating ? 'Capturing GPS…' : 'Use current position'}</button>{values.gps_accuracy_m !== '' && <small>Accuracy: approximately {Number(values.gps_accuracy_m).toLocaleString()} m</small>}</fieldset>}
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
    if (['amount', 'credit_limit', 'base_salary'].includes(column)) return `${Number(value).toLocaleString()} MMK`;
    if (locale === 'my' && myOptionLabels[value]) return myOptionLabels[value];
    return String(value);
}

function titleCase(value) {
    return String(value || '').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}
