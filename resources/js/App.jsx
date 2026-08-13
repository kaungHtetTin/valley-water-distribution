import './bootstrap';
import { createRoot } from 'react-dom/client';
import {
    Bell,
    Building2,
    CalendarDays,
    CheckCircle2,
    ChevronDown,
    ChevronRight,
    ClipboardList,
    CreditCard,
    Droplets,
    Home,
    Languages,
    LayoutDashboard,
    LogOut,
    MapPinned,
    Menu,
    Moon,
    Package,
    PackageCheck,
    ReceiptText,
    Search,
    Settings,
    ShoppingCart,
    Sun,
    Truck,
    User,
    Users,
    WalletCards,
} from 'lucide-react';
import { useEffect, useId, useMemo, useState } from 'react';

const copy = {
    en: {
        brand: 'Valley Water',
        office: 'Office App',
        client: 'Client App',
        sales: 'Sales App',
        driver: 'Driver App',
        dashboard: 'Dashboard',
        login: 'Login',
        language: 'Language',
        theme: 'Theme',
        overview: 'Foundation and UI Shell',
        officeHint: 'Compact operations console for owner, admin, and office staff.',
        clientHint: 'Customer app shell for orders, delivery status, and account balance.',
        salesHint: 'Sales route app shell for daily shop visits and field orders.',
        driverHint: 'Driver app shell for assigned loads, delivery route, and status updates.',
        search: 'Search screens, customers, routes',
        newDemoOrder: 'New demo order',
        demoNav: 'Demo Navigation',
        visibleStates: 'Visible States',
        loading: 'Loading route assignments',
        empty: 'No alerts for this demo shift',
        error: 'Sample API error state',
        roleNav: 'Role-aware access',
        roleAwareMenu: 'Role-aware menu',
        permissionsReady: 'Permissions ready',
        visibleMenus: 'Visible menus',
        signIn: 'Sign in',
        email: 'Email or phone',
        password: 'Password',
        status: 'Status',
        ready: 'Ready',
        currentRoute: 'Current route',
        assignedToday: 'Assigned Today',
        nextAction: 'Next Action',
        home: 'Home',
        orders: 'Orders',
        deliveries: 'Deliveries',
        profile: 'Profile',
        profileMenu: 'Profile menu',
        route: 'Route',
        customers: 'Customers',
        collections: 'Collections',
        load: 'Load',
        confirm: 'Confirm',
        clientWelcome: 'Morning delivery to Shwe Family Store is being prepared.',
        salesWelcome: 'Route A-03 is ready with today customer visits.',
        driverWelcome: 'Warehouse load WY-204 is assigned for delivery.',
        officeApiNote: 'The API response format keeps errors predictable for all apps.',
        authLoading: 'Checking sign in',
        signOut: 'Sign out',
        signedInAs: 'Signed in as',
        account: 'Account',
        demoPassword: 'Demo password: password',
        authRequired: 'Sign in to continue',
        wrongApp: 'This user belongs to another app.',
    },
    my: {
        brand: 'Valley Water',
        office: 'ရုံးအက်ပ်',
        client: 'ဖောက်သည်အက်ပ်',
        sales: 'အရောင်းအက်ပ်',
        driver: 'ယာဉ်မောင်းအက်ပ်',
        dashboard: 'ဒက်ရှ်ဘုတ်',
        login: 'ဝင်ရန်',
        language: 'ဘာသာစကား',
        theme: 'အပြင်အဆင်',
        overview: 'အခြေခံနှင့် UI Shell',
        officeHint: 'ပိုင်ရှင်၊ အက်ဒမင်နှင့် ရုံးဝန်ထမ်းများအတွက် console။',
        clientHint: 'အော်ဒါ၊ ပို့ဆောင်မှုအခြေအနေ နှင့် ငွေလက်ကျန်အတွက် ဖောက်သည် app shell။',
        salesHint: 'နေ့စဉ်ဆိုင်လည်ပတ်မှုနှင့် field order များအတွက် အရောင်း route app shell။',
        driverHint: 'သတ်မှတ်ထားသော load၊ route နှင့် status update များအတွက် driver app shell။',
        search: 'စာမျက်နှာ၊ ဖောက်သည်၊ route ရှာရန်',
        newDemoOrder: 'Demo order အသစ်',
        demoNav: 'Demo Menu',
        visibleStates: 'မြင်နိုင်သော state များ',
        loading: 'Route assignment တင်နေသည်',
        empty: 'ဒီ demo shift တွင် alert မရှိပါ',
        error: 'Sample API error state',
        roleNav: 'Role-aware access',
        signIn: 'ဝင်ရန်',
        email: 'Email သို့မဟုတ် ဖုန်း',
        password: 'Password',
        status: 'အခြေအနေ',
        ready: 'အသင့်',
        currentRoute: 'လက်ရှိ route',
        assignedToday: 'ယနေ့သတ်မှတ်ထားသည်',
        nextAction: 'နောက်လုပ်ဆောင်ရန်',
        home: 'မူလ',
        orders: 'အော်ဒါများ',
        deliveries: 'ပို့ဆောင်မှုများ',
        profile: 'ပရိုဖိုင်',
        profileMenu: 'Profile menu',
        route: 'Route',
        customers: 'ဖောက်သည်များ',
        collections: 'ငွေကောက်ခံမှုများ',
        load: 'Load',
        confirm: 'အတည်ပြု',
        clientWelcome: 'Shwe Family Store အတွက် မနက်ပိုင်းပို့ဆောင်မှု ပြင်ဆင်နေသည်။',
        salesWelcome: 'Route A-03 အတွက် ယနေ့ဖောက်သည်လည်ပတ်မှုများ အသင့်ဖြစ်သည်။',
        driverWelcome: 'Warehouse load WY-204 ကို ပို့ဆောင်ရန် သတ်မှတ်ထားသည်။',
        officeApiNote: 'API response format သည် app အားလုံးအတွက် error များကို တူညီစေသည်။',
    },
};

const appConfig = {
    office: {
        id: 'office',
        path: runtimeRoute('office', '/office'),
        icon: Building2,
        accent: '#0b84a5',
        email: 'office@valley.test',
    },
    client: {
        id: 'client',
        path: runtimeRoute('client', '/client'),
        icon: ShoppingCart,
        accent: '#168255',
        email: 'client@valley.test',
    },
    sales: {
        id: 'sales',
        path: runtimeRoute('sales', '/sales'),
        icon: Users,
        accent: '#2874bc',
        email: 'sales@valley.test',
    },
    driver: {
        id: 'driver',
        path: runtimeRoute('driver', '/driver'),
        icon: Truck,
        accent: '#b77700',
        email: 'driver@valley.test',
    },
};

const appOrder = ['office', 'client', 'sales', 'driver'];

const officeNavItems = [
    { label: 'Dashboard', icon: LayoutDashboard, active: true, permission: 'office.dashboard.view' },
    { label: 'Users & Roles', icon: Users, permission: 'office.users.view' },
    { label: 'Customers', icon: User, permission: 'office.customers.view' },
    { label: 'Orders', icon: ClipboardList, permission: 'office.orders.view' },
    { label: 'Inventory', icon: Package, permission: 'office.inventory.view' },
    { label: 'Deliveries', icon: Truck, permission: 'office.deliveries.view' },
    { label: 'Settings', icon: Settings, permission: 'office.settings.view' },
];

const mobileNav = {
    client: [
        { labelKey: 'home', icon: Home, permission: 'client.home.view' },
        { labelKey: 'orders', icon: ReceiptText, permission: 'client.orders.view' },
        { labelKey: 'deliveries', icon: Truck, permission: 'client.deliveries.view' },
        { labelKey: 'profile', icon: User, permission: 'client.profile.view' },
    ],
    sales: [
        { labelKey: 'home', icon: Home, permission: 'sales.home.view' },
        { labelKey: 'route', icon: MapPinned, permission: 'sales.route.view' },
        { labelKey: 'customers', icon: Users, permission: 'sales.customers.view' },
        { labelKey: 'collections', icon: WalletCards, permission: 'sales.collections.view' },
    ],
    driver: [
        { labelKey: 'home', icon: Home, permission: 'driver.home.view' },
        { labelKey: 'load', icon: PackageCheck, permission: 'driver.load.view' },
        { labelKey: 'route', icon: MapPinned, permission: 'driver.route.view' },
        { labelKey: 'confirm', icon: CheckCircle2, permission: 'driver.confirm.view' },
    ],
};

const metrics = [
    ['Apps Online', '4', 'Office, Client, Sales, Driver'],
    ['Demo Users', '5', 'Seeded role accounts'],
    ['Locales', 'EN / MY', 'Visible label switching'],
    ['API Base', 'Ready', '/api/phase-zero'],
];

const rows = [
    ['Office Staff', 'Office dashboard', 'Ready', 'Dashboard shell'],
    ['Customer', 'Client home', 'Ready', 'Mobile client shell'],
    ['Sales Representative', 'Sales route', 'Ready', 'Route placeholder'],
    ['Driver', 'Driver delivery', 'Ready', 'Delivery placeholder'],
];

const mobileData = {
    client: {
        hero: 'Current order',
        title: '5 Gallon Water x 24',
        meta: 'Delivery window: 9:00 AM - 11:00 AM',
        welcomeKey: 'clientWelcome',
        action: 'Open order',
        items: [
            ['Last order', 'Processing'],
            ['Outstanding balance', '128,000 MMK'],
            ['Assigned route', 'TGI North'],
        ],
    },
    sales: {
        hero: 'Today route',
        title: 'Route A-03',
        meta: '12 shops remaining',
        welcomeKey: 'salesWelcome',
        action: 'Visit customer',
        items: [
            ['Next customer', 'Shwe Family Store'],
            ['Orders submitted', '7'],
            ['Collections target', '420,000 MMK'],
        ],
    },
    driver: {
        hero: 'Assigned delivery',
        title: 'Truck WY-204',
        meta: '8 invoices loaded',
        welcomeKey: 'driverWelcome',
        action: 'Start delivery',
        items: [
            ['Warehouse', 'Taunggyi Main'],
            ['Next stop', 'Market Street'],
            ['Delivery status', 'Assigned'],
        ],
    },
};

function App() {
    const [theme, setTheme] = useState('light');
    const [locale, setLocale] = useState('en');
    const [auth, setAuth] = useState({ loading: true, user: null, errors: {}, message: '' });
    const t = { ...copy.en, ...copy[locale] };
    const activeApp = resolveAppFromPath();
    const selectedApp = appConfig[activeApp];
    const rootStyle = useMemo(() => ({ '--color-primary': selectedApp.accent }), [selectedApp]);

    useEffect(() => {
        let isMounted = true;

        window.axios
            .get(authRoute('user', '/api/auth/user'))
            .then((response) => {
                if (isMounted) {
                    setAuth({ loading: false, user: response.data.data.user, errors: {}, message: '' });
                }
            })
            .catch(() => {
                if (isMounted) {
                    setAuth({ loading: false, user: null, errors: {}, message: '' });
                }
            });

        return () => {
            isMounted = false;
        };
    }, []);

    useEffect(() => {
        if (!auth.user || auth.user.allowed_apps.includes(activeApp)) {
            return;
        }

        const redirectApp = auth.user.default_app || 'office';
        window.location.assign(appConfig[redirectApp].path);
    }, [activeApp, auth.user]);

    const handleLogin = async (payload) => {
        setAuth((current) => ({ ...current, errors: {}, message: '', submitting: true }));

        try {
            const response = await window.axios.post(authRoute('login', '/api/auth/login'), {
                ...payload,
                app: activeApp,
            });
            const user = response.data.data.user;
            setAuth({ loading: false, user, errors: {}, message: '', submitting: false });

            if (user.default_app && user.default_app !== activeApp) {
                window.location.assign(appConfig[user.default_app].path);
            }
        } catch (error) {
            setAuth({
                loading: false,
                user: null,
                errors: error.response?.data?.errors || {},
                message: error.response?.data?.message || 'Unable to sign in.',
                submitting: false,
            });
        }
    };

    const handleLogout = async () => {
        await window.axios.post(authRoute('logout', '/api/auth/logout'));
        setAuth({ loading: false, user: null, errors: {}, message: '' });
    };

    return (
        <div className="app-root" data-theme={theme} data-app={activeApp} style={rootStyle}>
            {auth.loading ? (
                <AppLoading t={t} />
            ) : !auth.user ? (
                <AuthScreen
                    app={selectedApp}
                    t={t}
                    locale={locale}
                    setLocale={setLocale}
                    theme={theme}
                    setTheme={setTheme}
                    auth={auth}
                    onLogin={handleLogin}
                />
            ) : activeApp === 'office' ? (
                <OfficeApp
                    t={t}
                    user={auth.user}
                    onLogout={handleLogout}
                    locale={locale}
                    setLocale={setLocale}
                    theme={theme}
                    setTheme={setTheme}
                />
            ) : (
                <MobileApp
                    app={selectedApp}
                    t={t}
                    user={auth.user}
                    onLogout={handleLogout}
                    locale={locale}
                    setLocale={setLocale}
                    theme={theme}
                    setTheme={setTheme}
                />
            )}
        </div>
    );
}

function runtimeRoute(appId, fallback) {
    return window.ValleyRuntime?.routes?.[appId] || fallback;
}

function authRoute(action, fallback) {
    return window.ValleyRuntime?.auth?.[action] || fallback;
}

function resolveAppFromPath() {
    const currentPath = normalizePath(window.location.pathname);
    const matchedApp = appOrder.find((id) => {
        const routePath = normalizePath(new URL(appConfig[id].path, window.location.origin).pathname);
        return currentPath === routePath || currentPath.startsWith(`${routePath}/`);
    });

    if (matchedApp) {
        return matchedApp;
    }

    const matchedSegment = [...window.location.pathname.split('/').filter(Boolean)]
        .reverse()
        .find((segment) => appConfig[segment]);

    return matchedSegment || 'office';
}

function normalizePath(path) {
    const normalized = `/${path.split('/').filter(Boolean).join('/')}`;
    return normalized === '/' ? normalized : normalized.replace(/\/$/, '');
}

function OfficeApp({ t, user, onLogout, locale, setLocale, theme, setTheme }) {
    const [drawerOpen, setDrawerOpen] = useState(false);
    const visibleOfficeNav = officeNavItems.filter((item) => hasPermission(user, item.permission));

    return (
        <>
            <aside className={`sidebar ${drawerOpen ? 'is-open' : ''}`}>
                <Brand t={t} />
                <nav aria-label={t.demoNav}>
                    <p className="nav-section">{t.roleAwareMenu}</p>
                    {visibleOfficeNav.map((item) => (
                        <a className={`nav-item ${item.active ? 'is-active' : ''}`} href={appConfig.office.path} key={item.label}>
                            <item.icon size={16} />
                            <span>{item.label}</span>
                        </a>
                    ))}
                </nav>
            </aside>

            {drawerOpen && <button className="mobile-scrim" type="button" aria-label="Close navigation" onClick={() => setDrawerOpen(false)} />}

            <main className="workspace">
                <header className="topbar">
                    <button className="icon-button mobile-only" type="button" aria-label="Open navigation" title="Open navigation" onClick={() => setDrawerOpen(true)}>
                        <Menu size={18} />
                    </button>
                    <div className="searchbox">
                        <Search size={15} />
                        <input aria-label={t.search} placeholder={t.search} />
                    </div>
                    <AppearanceControls
                        t={t}
                        user={user}
                        onLogout={onLogout}
                        locale={locale}
                        setLocale={setLocale}
                        theme={theme}
                        setTheme={setTheme}
                    />
                </header>

                <section className="page">
                    <PageHeading title={t.overview} hint={t.officeHint} actionLabel={t.newDemoOrder} actionIcon={ClipboardList} />
                    <OfficeDashboard t={t} user={user} visibleMenus={visibleOfficeNav} />
                </section>
            </main>
        </>
    );
}

function MobileApp({ app, t, user, onLogout, locale, setLocale, theme, setTheme }) {
    const AppIcon = app.icon;
    const data = mobileData[app.id];
    const visibleNav = mobileNav[app.id].filter((item) => hasPermission(user, item.permission));

    return (
        <main className="mobile-app-shell">
            <header className="mobile-app-topbar">
                <Brand t={t} compact />
                <div className="mobile-top-actions">
                    <button className="icon-button" type="button" aria-label={t.theme} title={t.theme} onClick={() => setTheme(theme === 'light' ? 'dark' : 'light')}>
                        {theme === 'light' ? <Moon size={16} /> : <Sun size={16} />}
                    </button>
                    <ProfileMenu
                        user={user}
                        t={t}
                        locale={locale}
                        setLocale={setLocale}
                        onLogout={onLogout}
                        compact
                    />
                </div>
            </header>

            <section className="mobile-page">
                <section className="mobile-hero">
                    <div>
                        <h1>{t[app.id]}</h1>
                        <span className="muted">{t[`${app.id}Hint`]}</span>
                    </div>
                    <AppIcon size={24} />
                </section>

                <section className="mobile-status-card">
                    <p className="eyebrow">{data.hero}</p>
                    <h2>{data.title}</h2>
                    <p>{data.meta}</p>
                    <button className="button primary" type="button">
                        <ChevronRight size={16} />
                        {data.action}
                    </button>
                </section>

                <p className="mobile-copy">{t[data.welcomeKey]}</p>

                <div className="mobile-list">
                    {data.items.map(([label, value]) => (
                        <button type="button" key={label}>
                            <span>{label}</span>
                            <strong>{value}</strong>
                            <ChevronRight size={17} />
                        </button>
                    ))}
                </div>
            </section>

            <nav className="bottom-nav" aria-label={t.currentRoute}>
                {visibleNav.map(({ labelKey, icon: Icon }, index) => (
                    <a className={index === 0 ? 'is-active' : ''} href={app.path} key={labelKey}>
                        <Icon size={17} />
                        <span>{t[labelKey]}</span>
                    </a>
                ))}
            </nav>
        </main>
    );
}

function Brand({ t, compact = false }) {
    return (
        <div className={`brand ${compact ? 'compact' : ''}`}>
            <div className="brand-mark">
                <Droplets size={20} />
            </div>
            <div>
                <strong>{t.brand}</strong>
                {!compact && <span>{t.roleNav}</span>}
            </div>
        </div>
    );
}

function AppearanceControls({ t, user, onLogout, locale, setLocale, theme, setTheme }) {
    return (
        <div className="topbar-actions">
            <button className="icon-button" type="button" aria-label={t.theme} title={t.theme} onClick={() => setTheme(theme === 'light' ? 'dark' : 'light')}>
                {theme === 'light' ? <Moon size={16} /> : <Sun size={16} />}
            </button>
            <button className="icon-button" type="button" aria-label="Notifications" title="Notifications">
                <Bell size={16} />
            </button>
            <ProfileMenu
                user={user}
                t={t}
                locale={locale}
                setLocale={setLocale}
                onLogout={onLogout}
            />
        </div>
    );
}

function Segmented({ label, value, options, onChange, icon: Icon }) {
    return (
        <div className="segmented" aria-label={label}>
            {Icon && <Icon size={14} />}
            {options.map(([optionValue, optionLabel]) => (
                <button className={value === optionValue ? 'is-selected' : ''} type="button" key={optionValue} onClick={() => onChange(optionValue)}>
                    {optionLabel}
                </button>
            ))}
        </div>
    );
}

function PageHeading({ eyebrow, title, hint, actionLabel, actionIcon: ActionIcon }) {
    return (
        <div className="page-heading">
            <div>
                {eyebrow && <p className="eyebrow">{eyebrow}</p>}
                <h1>{title}</h1>
                <span className="muted">{hint}</span>
            </div>
            <button className="button primary" type="button">
                <ActionIcon size={16} />
                {actionLabel}
            </button>
        </div>
    );
}

function OfficeDashboard({ t, user, visibleMenus }) {
    return (
        <>
            <div className="metrics">
                {metrics.map(([label, value, hint]) => (
                    <article className="metric" key={label}>
                        <span>{label}</span>
                        <strong>{value}</strong>
                        <small>{hint}</small>
                    </article>
                ))}
            </div>
            <div className="content-grid">
                <section className="panel">
                    <PanelHeading eyebrow="Office" title={t.dashboard} />
                    <div className="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Role</th>
                                    <th>Shell</th>
                                    <th>{t.status}</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((row) => (
                                    <tr key={row[0]}>
                                        <td>
                                            <strong>{row[0]}</strong>
                                            <span>Demo account ready</span>
                                        </td>
                                        <td>{row[1]}</td>
                                        <td>
                                            <span className="status success">{row[2]}</span>
                                        </td>
                                        <td>{row[3]}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
                <aside className="side-stack">
                    <RolePanel t={t} user={user} visibleMenus={visibleMenus} />
                    <StatePanel title={t.visibleStates} text={t.loading} state="loading" />
                    <StatePanel title={t.empty} text="Office alerts, approval queues, and failed syncs appear here." state="empty" />
                    <StatePanel title={t.error} text={t.officeApiNote} state="error" />
                </aside>
            </div>
        </>
    );
}

function RolePanel({ t, user, visibleMenus }) {
    return (
        <section className="panel role-panel">
            <PanelHeading eyebrow={t.roleAwareMenu} title={user.role} />
            <p>{t.permissionsReady}</p>
            <div className="permission-list" aria-label={t.visibleMenus}>
                {visibleMenus.map((item) => (
                    <span key={item.label}>{item.label}</span>
                ))}
            </div>
        </section>
    );
}

function hasPermission(user, permission) {
    return !permission || user.permissions.includes(permission);
}

function PanelHeading({ eyebrow, title }) {
    return (
        <div className="panel-heading">
            <p className="eyebrow">{eyebrow}</p>
            <h2>{title}</h2>
        </div>
    );
}

function StatePanel({ title, text, state }) {
    return (
        <section className={`panel state ${state}`}>
            <PanelHeading eyebrow="State" title={title} />
            <p>{text}</p>
            {state === 'loading' && (
                <div className="skeleton-lines" aria-hidden="true">
                    <span />
                    <span />
                    <span />
                </div>
            )}
        </section>
    );
}

function AppLoading({ t }) {
    return (
        <main className="auth-screen">
            <Brand t={t} />
            <section className="login-card auth-card">
                <strong>{t.authLoading}</strong>
                <div className="skeleton-lines" aria-hidden="true">
                    <span />
                    <span />
                    <span />
                </div>
            </section>
        </main>
    );
}

function AuthScreen({ app, t, locale, setLocale, theme, setTheme, auth, onLogin }) {
    return (
        <main className="auth-screen">
            <header className="auth-header">
                <Brand t={t} />
                <div className="mobile-top-actions">
                    <Segmented
                        label={t.language}
                        value={locale}
                        options={[
                            ['en', 'EN'],
                            ['my', 'MY'],
                        ]}
                        onChange={setLocale}
                        icon={Languages}
                    />
                    <button className="icon-button" type="button" aria-label={t.theme} title={t.theme} onClick={() => setTheme(theme === 'light' ? 'dark' : 'light')}>
                        {theme === 'light' ? <Moon size={16} /> : <Sun size={16} />}
                    </button>
                </div>
            </header>
            <section className="auth-card-wrap">
                <div>
                    <h1>{t[app.id]}</h1>
                    <span className="muted">{t[`${app.id}Hint`]}</span>
                </div>
                <LoginPanel app={app} t={t} auth={auth} onLogin={onLogin} />
            </section>
        </main>
    );
}

function ProfileMenu({ user, t, locale, setLocale, onLogout, compact = false }) {
    const [open, setOpen] = useState(false);
    const menuId = useId();
    const initials = user.name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();

    return (
        <div
            className={`profile-menu ${open ? 'is-open' : ''}`}
            onBlur={(event) => {
                if (!event.currentTarget.contains(event.relatedTarget)) {
                    setOpen(false);
                }
            }}
            onKeyDown={(event) => {
                if (event.key === 'Escape') {
                    setOpen(false);
                    event.currentTarget.querySelector('.profile-trigger')?.focus();
                }
            }}
        >
            <button
                className={compact ? 'profile-trigger compact' : 'profile-trigger'}
                type="button"
                aria-label={t.profileMenu}
                aria-haspopup="dialog"
                aria-expanded={open}
                aria-controls={menuId}
                onClick={() => setOpen((current) => !current)}
            >
                <span className="profile-avatar" aria-hidden="true">{initials}</span>
                {!compact && (
                    <span className="profile-trigger-copy">
                        <strong>{user.name}</strong>
                        <small>{user.role}</small>
                    </span>
                )}
                <ChevronDown className="profile-chevron" size={14} aria-hidden="true" />
            </button>

            {open && (
                <div className="profile-dropdown" id={menuId} role="dialog" aria-label={t.account}>
                    <div className="profile-summary">
                        <span className="profile-avatar large" aria-hidden="true">{initials}</span>
                        <div>
                            <strong>{user.name}</strong>
                            <span>{user.role}</span>
                            <small>{user.email}</small>
                        </div>
                    </div>
                    <div className="profile-setting">
                        <div className="profile-setting-label">
                            <Languages size={15} aria-hidden="true" />
                            <span>{t.language}</span>
                        </div>
                        <Segmented
                            label={t.language}
                            value={locale}
                            options={[
                                ['en', 'EN'],
                                ['my', 'MY'],
                            ]}
                            onChange={setLocale}
                        />
                    </div>
                    <div className="profile-actions">
                        <button className="profile-action" type="button" onClick={() => setOpen(false)}>
                            <User size={15} aria-hidden="true" />
                            <span>{t.profile}</span>
                            <ChevronRight size={14} aria-hidden="true" />
                        </button>
                        <button className="profile-action danger" type="button" onClick={onLogout}>
                            <LogOut size={15} aria-hidden="true" />
                            <span>{t.signOut}</span>
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}

function LoginPanel({ app, t, auth, onLogin }) {
    const [email, setEmail] = useState(app.email);
    const [password, setPassword] = useState('password');

    const submit = (event) => {
        event.preventDefault();
        onLogin({ email, password });
    };

    return (
        <form className="login-card auth-card" onSubmit={submit}>
            <strong>{t.authRequired}</strong>
            {auth.message && <p className="form-alert">{auth.message}</p>}
            <label>
                {t.email}
                <input value={email} onChange={(event) => setEmail(event.target.value)} />
                {auth.errors.email && <span className="field-error">{auth.errors.email[0]}</span>}
            </label>
            <label>
                {t.password}
                <input value={password} onChange={(event) => setPassword(event.target.value)} type="password" />
                {auth.errors.password && <span className="field-error">{auth.errors.password[0]}</span>}
            </label>
            {auth.errors.app && <p className="form-alert">{auth.errors.app[0]}</p>}
            <small className="muted">{t.demoPassword}</small>
            <button className="button primary" type="submit" disabled={auth.submitting}>
                <CreditCard size={16} />
                {t.signIn}
            </button>
        </form>
    );
}

createRoot(document.getElementById('root')).render(<App />);
