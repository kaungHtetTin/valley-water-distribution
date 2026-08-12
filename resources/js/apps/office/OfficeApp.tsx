import { useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { Icon, type IconName } from '../../packages/design-system/Icon';
import { usePreferences } from '../../packages/design-system/usePreferences';
import { useI18n } from '../../packages/i18n/I18nProvider';
import type { MessageKey } from '../../packages/i18n/messages';
import { MasterDataPage } from './master-data/MasterDataPage';
import { CatalogPage } from './master-data/CatalogPage';
import { CatalogSetupPage } from './master-data/CatalogSetupPage';
import { WayPage } from './master-data/WayPage';
import { LocationPage } from './master-data/LocationPage';
import { OrganizationControlsPage } from './master-data/OrganizationControlsPage';
import { ControlledLocationsPage } from './master-data/ControlledLocationsPage';
import { RouteTemplatePage } from './master-data/RouteTemplatePage';
import { FoundationMastersPage } from './master-data/FoundationMastersPage';
import { GovernancePage } from './master-data/GovernancePage';
import { CustomerPage } from './customer-sales/CustomerPage';

interface NavigationItem {
    label: MessageKey;
    path: string;
    icon: IconName;
    permission: string;
}

const navigation: NavigationItem[] = [
    { label: 'dashboard', path: '/office', icon: 'activity', permission: 'dashboard.view' },
    { label: 'customers', path: '/office/customers', icon: 'users', permission: 'customers.view' },
    { label: 'salesManagement', path: '/office/sales', icon: 'activity', permission: 'sales.view' },
    { label: 'warehouse', path: '/office/warehouse', icon: 'box', permission: 'inventory.view' },
    { label: 'delivery', path: '/office/delivery', icon: 'truck', permission: 'delivery.view' },
    { label: 'finance', path: '/office/finance', icon: 'cash', permission: 'finance.view' },
    { label: 'people', path: '/office/people', icon: 'users', permission: 'payroll.view' },
    { label: 'fleet', path: '/office/fleet', icon: 'route', permission: 'fleet.view' },
    { label: 'reports', path: '/office/reports', icon: 'activity', permission: 'reports.view' },
    { label: 'masterData', path: '/office/master-data', icon: 'shield', permission: 'administration.view' },
];

const foundationPermissions = new Set(navigation.map((item) => item.permission));

export function OfficeApp() {
    const { locale, setLocale, t } = useI18n();
    const { theme, density, toggleTheme, toggleDensity } = usePreferences();
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [rail, setRail] = useState(false);
    const location = useLocation();
    const isMasterData = location.pathname.startsWith('/office/master-data');
    const isCustomers = location.pathname.startsWith('/office/customers');
    const isCatalogSetup = location.pathname.startsWith('/office/master-data/catalog-setup');
    const isCatalog = location.pathname.startsWith('/office/master-data/catalog') && !isCatalogSetup;
    const isWays = location.pathname.startsWith('/office/master-data/ways');
    const isLocations = location.pathname.startsWith('/office/master-data/locations');
    const isControls = location.pathname.startsWith('/office/master-data/controls');
    const isStorage = location.pathname.startsWith('/office/master-data/storage');
    const isRouteTemplates = location.pathname.startsWith('/office/master-data/route-templates');
    const isFoundation = location.pathname.startsWith('/office/master-data/foundation');
    const isGovernance = location.pathname.startsWith('/office/master-data/governance');

    const visibleNavigation = navigation.filter((item) => foundationPermissions.has(item.permission));
    const isActive = (path: string) => path === '/office'
        ? location.pathname === '/office' || location.pathname === '/office/'
        : location.pathname.startsWith(path);

    return (
        <div className="admin-root" data-theme={theme} data-density={density} data-rail={rail ? 'true' : 'false'}>
            {drawerOpen && <button className="nav-overlay" aria-label={t('closeMenu')} onClick={() => setDrawerOpen(false)} />}

            <aside className={`sidebar ${drawerOpen ? 'is-open' : ''}`} aria-label={t('office')}>
                <div className="brand-lockup">
                    <span className="brand-mark" aria-hidden="true">V</span>
                    <span className="brand-copy"><strong>{t('appName')}</strong><small>{t('office')}</small></span>
                    <button className="icon-button sidebar-close" type="button" aria-label={t('closeMenu')} onClick={() => setDrawerOpen(false)}>
                        <Icon name="close" />
                    </button>
                </div>

                <nav className="sidebar-nav">
                    <p className="nav-eyebrow">{t('currentWorkspace')}</p>
                    {visibleNavigation.map((item) => (
                        <Link
                            key={item.path}
                            className={`nav-item ${isActive(item.path) ? 'is-active' : ''}`}
                            to={item.path}
                            title={rail ? t(item.label) : undefined}
                            aria-current={isActive(item.path) ? 'page' : undefined}
                            onClick={() => setDrawerOpen(false)}
                        >
                            <Icon name={item.icon} />
                            <span>{t(item.label)}</span>
                        </Link>
                    ))}
                </nav>

                <div className="sidebar-footer">
                    <span className="status-dot status-dot--success" />
                    <span>{isCustomers ? t('phaseTwo') : t('phaseOne')}</span>
                </div>
            </aside>

            <div className="admin-frame">
                <header className="topbar">
                    <div className="topbar-start">
                        <button className="icon-button mobile-menu" type="button" aria-label={t('openMenu')} onClick={() => setDrawerOpen(true)}>
                            <Icon name="menu" />
                        </button>
                        <button className="icon-button rail-toggle" type="button" aria-label={rail ? t('expandMenu') : t('collapseMenu')} onClick={() => setRail((current) => !current)}>
                            <Icon name="chevron" />
                        </button>
                        <span className="workspace-label">{isCustomers ? t('customerRegister') : isGovernance ? t('governanceAndPricing') : isFoundation ? t('foundationMasters') : isCatalogSetup ? t('catalogSetup') : isCatalog ? t('catalogAndPricing') : isWays ? t('wayRegister') : isRouteTemplates ? t('routeTemplateRegister') : isLocations ? t('locationRegister') : isControls ? t('organizationControls') : isStorage ? t('storageAndCash') : isMasterData ? t('areaRegister') : t('commandDashboard')}</span>
                    </div>

                    <div className="topbar-actions">
                        <button className="topbar-control" type="button" onClick={toggleDensity} aria-label={t('toggleDensity')}>
                            <span>{density === 'compact' ? t('compact') : t('comfortable')}</span>
                        </button>
                        <button className="icon-button" type="button" onClick={toggleTheme} aria-label={t('toggleTheme')} title={theme === 'light' ? t('dark') : t('light')}>
                            <Icon name={theme === 'light' ? 'moon' : 'sun'} />
                        </button>
                        <button className="topbar-control" type="button" onClick={() => setLocale(locale === 'en' ? 'my-MM' : 'en')} aria-label={t('switchLanguage')}>
                            <Icon name="globe" size={15} />
                            <span>{locale === 'en' ? 'မြန်မာ' : 'EN'}</span>
                        </button>
                        <span className="avatar" aria-label={t('phaseAdministrator')}>P1</span>
                    </div>
                </header>

                <main className="admin-content">
                    {isCustomers ? <CustomerPage /> : isMasterData ? (isGovernance ? <GovernancePage /> : isFoundation ? <FoundationMastersPage /> : isCatalogSetup ? <CatalogSetupPage /> : isCatalog ? <CatalogPage /> : isWays ? <WayPage /> : isRouteTemplates ? <RouteTemplatePage /> : isLocations ? <LocationPage /> : isControls ? <OrganizationControlsPage /> : isStorage ? <ControlledLocationsPage /> : <MasterDataPage />) : <>
                    <section className="page-heading">
                        <div>
                            <p className="eyebrow">{t('phaseZero')}</p>
                            <h1>{t('operationalFoundation')}</h1>
                            <p>{t('foundationDescription')}</p>
                        </div>
                        <button className="button button--primary" type="button" disabled title={t('notActivated')}>
                            {t('newOrder')}
                        </button>
                    </section>

                    <section className="metric-grid" aria-label={t('commandDashboard')}>
                        <MetricCard icon="activity" label={t('pendingOrders')} hint={t('notActivated')} />
                        <MetricCard icon="truck" label={t('activeTrips')} hint={t('notActivated')} />
                        <MetricCard icon="box" label={t('availableStock')} hint={t('notActivated')} />
                        <MetricCard icon="cash" label={t('cashInCustody')} hint={t('notActivated')} />
                    </section>

                    <section className="dashboard-grid">
                        <article className="panel">
                            <div className="panel-heading">
                                <div><p className="eyebrow">{t('phaseZero')}</p><h2>{t('platformReadiness')}</h2></div>
                                <span className="status-badge status-badge--info"><span />3 / 4</span>
                            </div>
                            <div className="readiness-list">
                                <ReadinessRow label={t('apiFoundation')} state="ready" />
                                <ReadinessRow label={t('localizationReady')} state="ready" />
                                <ReadinessRow label={t('featureFlagsReady')} state="ready" />
                                <ReadinessRow label={t('policyPending')} state="pending" />
                            </div>
                        </article>

                        <article className="panel attention-panel">
                            <div className="panel-heading">
                                <div><p className="eyebrow">{t('today')}</p><h2>{t('attentionQueue')}</h2></div>
                            </div>
                            <div className="empty-state">
                                <span className="empty-icon"><Icon name="shield" size={22} /></span>
                                <strong>{t('awaitingDomainData')}</strong>
                                <p>{t('noOperationalData')}</p>
                            </div>
                        </article>
                    </section>

                    <article className="panel activity-panel">
                        <div className="panel-heading">
                            <div><p className="eyebrow">{t('office')}</p><h2>{t('recentActivity')}</h2></div>
                        </div>
                        <div className="table-region" tabIndex={0}>
                            <table>
                                <thead><tr><th>{t('recentActivity')}</th><th>{t('currentWorkspace')}</th><th>{t('platformReadiness')}</th></tr></thead>
                                <tbody><tr><td colSpan={3} className="table-empty">{t('noOperationalData')}</td></tr></tbody>
                            </table>
                        </div>
                    </article>
                    </>}
                </main>
            </div>
        </div>
    );
}

function MetricCard({ icon, label, hint }: { icon: IconName; label: string; hint: string }) {
    return (
        <article className="metric-card">
            <span className="metric-icon"><Icon name={icon} size={15} /></span>
            <p>{label}</p>
            <strong>—</strong>
            <small>{hint}</small>
        </article>
    );
}

function ReadinessRow({ label, state }: { label: string; state: 'ready' | 'pending' }) {
    const { t } = useI18n();

    return (
        <div className="readiness-row">
            <span className={`status-dot status-dot--${state === 'ready' ? 'success' : 'warning'}`} />
            <span>{label}</span>
            <span className={`status-badge status-badge--${state === 'ready' ? 'success' : 'warning'}`}><span />{state === 'ready' ? t('ready') : t('decision')}</span>
        </div>
    );
}
