import './bootstrap';
import { createPortal } from 'react-dom';
import { createRoot } from 'react-dom/client';
import {
    Building2,
    CalendarDays,
    CheckCircle2,
    CircleGauge,
    ChevronDown,
    ChevronRight,
    ClipboardList,
    CreditCard,
    Droplets,
    Home,
    Languages,
    LayoutDashboard,
    ListFilter,
    LogOut,
    MapPinned,
    Menu,
    Moon,
    Package,
    PackageCheck,
    QrCode,
    ReceiptText,
    RotateCcw,
    Rows3,
    Settings,
    SlidersHorizontal,
    ShoppingCart,
    Sun,
    TriangleAlert,
    Truck,
    User,
    Users,
    WalletCards,
    X,
} from 'lucide-react';
import { useEffect, useId, useMemo, useRef, useState } from 'react';
import { ClientMasterScreen, CompanySettingsScreen, DriverMasterScreen, MasterDataWorkspace, SalesCustomerDetailPage, SalesMasterScreen, SalesRouteScreen } from './phaseOne';
import { InvoicesScreen, MobileOrderDetailPage, MobileOrdersScreen, OrdersScreen, SalesReturnScreen } from './phaseFour';
import { ClosingStockScreen, StockAdjustmentScreen, StockBalanceScreen, StockCardScreen, StockReceiveScreen, StockTransferScreen, StockValueScreen } from './phaseFive';
import { DeliveryLiveMapScreen, DeliveryPlanningScreen, MobileDeliveryStatusScreen, MobileDriverDeliveriesScreen, MobileDriverExecutionScreen, TripEditPage, TripWizardPage } from './phaseSix';
import { FinanceBookScreen, FinanceCollectionsScreen, FinanceExpensesScreen, FinanceReceivablesScreen, MobileFinanceScreen, ProfitLossScreen, SupplierLedgerScreen } from './phaseSeven';
import { MobileVehicleOperationsScreen, VehicleCostsScreen, VehicleMonthlyCostScreen, VehiclePerformanceScreen, VehicleRouteHistoryScreen } from './phaseEight';
import { MobileHomeDashboard, OfficeKpiDashboard } from './phaseTen';
import { UatReadinessScreen } from './phaseEleven';
import { MobilePayrollHistoryScreen, PayrollAdjustmentsScreen, PayrollDraftsScreen, SalaryHistoryScreen } from './phaseThree';
import { AttendanceLocationsScreen, AttendanceRecordsScreen, AttendanceSummaryScreen, MobileAttendanceHistoryScreen, PublicAttendanceScreen } from './phaseTwo';
import { ProfileSettingsScreen } from './ProfileSettings';

const copy = {
    en: {
        brand: 'Valley Water',
        office: 'Office App',
        client: 'Client App',
        sales: 'Sales App',
        salary: 'Salary',
        driver: 'Driver App',
        dashboard: 'Dashboard',
        login: 'Login',
        language: 'Language',
        theme: 'Theme',
        preview: 'Module preview',
        plannedPhase: 'This workflow is reserved for a later roadmap phase.',
        overview: 'Foundation and UI Shell',
        officeHint: 'Compact operations console for owner, admin, and office staff.',
        clientHint: 'Customer app shell for orders, delivery status, and account balance.',
        salesHint: 'Sales territory app for customer visits, customers, and field orders.',
        driverHint: 'Driver app shell for assigned loads, delivery route, and status updates.',
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
        attendance: 'Attendance',
        nextAction: 'Next Action',
        home: 'Home',
        orders: 'Orders',
        deliveries: 'Deliveries',
        profile: 'Profile',
        menu: 'Menu',
        moreOperations: 'More operations',
        close: 'Close',
        profileMenu: 'Profile menu',
        route: 'Route',
        visits: 'Visits',
        customers: 'Customers',
        collections: 'Collections',
        expenses: 'Expenses',
        ledger: 'Ledger',
        vehicle: 'Vehicle',
        load: 'Load',
        confirm: 'Confirm',
        clientWelcome: 'Morning delivery to Shwe Family Store is being prepared.',
        salesWelcome: 'Today’s customer visit plan is ready.',
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
        salesHint: 'ဖောက်သည်လည်ပတ်မှု၊ ဖောက်သည်စီမံမှုနှင့် အော်ဒါတင်ခြင်းအတွက် အရောင်း app။',
        driverHint: 'သတ်မှတ်ထားသော load၊ route နှင့် status update များအတွက် driver app shell။',
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
        visits: 'လည်ပတ်မှု',
        customers: 'ဖောက်သည်များ',
        collections: 'ငွေကောက်ခံမှုများ',
        load: 'Load',
        confirm: 'အတည်ပြု',
        clientWelcome: 'Shwe Family Store အတွက် မနက်ပိုင်းပို့ဆောင်မှု ပြင်ဆင်နေသည်။',
        salesWelcome: 'ယနေ့ဖောက်သည်လည်ပတ်မှုအစီအစဉ် အသင့်ဖြစ်သည်။',
        driverWelcome: 'Warehouse load WY-204 ကို ပို့ဆောင်ရန် သတ်မှတ်ထားသည်။',
        officeApiNote: 'API response format သည် app အားလုံးအတွက် error များကို တူညီစေသည်။',
    },
};

copy.my.expenses = 'အသုံးစရိတ်';
copy.my.ledger = 'စာရင်း';
copy.my.vehicle = 'ယာဉ်';

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
    { id: 'uat-readiness', label: 'UAT & Security', myLabel: 'UAT နှင့် လုံခြုံရေး', icon: CheckCircle2, permission: 'office.uat.view', view: 'uat-readiness', path: '/uat' },
    { id: 'dashboard-sales', label: 'Sales KPI', myLabel: 'အရောင်း KPI', icon: CircleGauge, permission: 'office.dashboard.view', view: 'dashboard-sales', path: '/dashboards/sales' },
    { id: 'dashboard-stock', label: 'Stock KPI', myLabel: 'စတော့ KPI', icon: Package, permission: 'office.dashboard.view', view: 'dashboard-stock', path: '/dashboards/stock' },
    { id: 'dashboard-delivery', label: 'Delivery KPI', myLabel: 'ပို့ဆောင်ရေး KPI', icon: Truck, permission: 'office.dashboard.view', view: 'dashboard-delivery', path: '/dashboards/delivery' },
    { id: 'dashboard-finance', label: 'Finance KPI', myLabel: 'ဘဏ္ဍာရေး KPI', icon: WalletCards, permission: 'office.dashboard.view', view: 'dashboard-finance', path: '/dashboards/finance' },
    { id: 'dashboard', label: 'Dashboard', myLabel: 'ဒက်ရှ်ဘုတ်', icon: LayoutDashboard, permission: 'office.dashboard.view' },
    { id: 'areas', label: 'Areas', myLabel: 'ဧရိယာများ', icon: MapPinned, permission: 'office.master-data.view', resource: 'areas' },
    { id: 'routes', label: 'Routes', myLabel: 'Route များ', icon: MapPinned, permission: 'office.master-data.view', resource: 'routes' },
    { id: 'warehouses', label: 'Warehouses', myLabel: 'ဂိုဒေါင်များ', icon: Package, permission: 'office.master-data.view', resource: 'warehouses' },
    { id: 'brands', label: 'Brands', myLabel: 'Brand များ', icon: ShoppingCart, permission: 'office.master-data.view', resource: 'brands' },
    { id: 'products', label: 'Products', myLabel: 'ကုန်ပစ္စည်းများ', icon: Package, permission: 'office.master-data.view', resource: 'products' },
    { id: 'price-types', label: 'Price Types', myLabel: 'ဈေးနှုန်းအမျိုးအစား', icon: WalletCards, permission: 'office.master-data.view', resource: 'price-types' },
    { id: 'product-prices', label: 'Product Prices', myLabel: 'ကုန်ပစ္စည်းဈေးနှုန်း', icon: ReceiptText, permission: 'office.master-data.view', resource: 'product-prices' },
    { id: 'customers', label: 'Customers', myLabel: 'ဖောက်သည်များ', icon: User, permission: 'office.master-data.view', resource: 'customers' },
    { id: 'employees', label: 'Employees', myLabel: 'ဝန်ထမ်းများ', icon: Users, permission: 'office.master-data.view', resource: 'employees' },
    { id: 'vehicles', label: 'Vehicles', myLabel: 'ယာဉ်များ', icon: Truck, permission: 'office.master-data.view', resource: 'vehicles' },
    { id: 'roles', label: 'Roles', myLabel: 'Role နှင့် Permission', icon: Settings, permission: 'office.master-data.view', resource: 'roles' },
    { id: 'permissions', label: 'Permissions', myLabel: 'Permission များ', icon: CheckCircle2, permission: 'office.master-data.view', resource: 'permissions' },
    { id: 'company-settings', label: 'Company', myLabel: 'ကုမ္ပဏီအချက်အလက်', icon: Building2, permission: 'office.master-data.view', view: 'company-settings' },
    { id: 'orders', label: 'Orders', myLabel: 'အော်ဒါများ', icon: ShoppingCart, permission: 'office.orders.view', view: 'orders', path: '/orders' },
    { id: 'invoices', label: 'Invoices', myLabel: 'Invoice များ', icon: ReceiptText, permission: 'office.invoices.view', view: 'invoices', path: '/invoices' },
    { id: 'sales-returns', label: 'Sales Returns', myLabel: 'Sales Return', icon: RotateCcw, permission: 'office.orders.view', view: 'sales-returns', path: '/returns' },
    { id: 'stock-receive', label: 'Stock Receive', myLabel: 'Stock Receive', icon: PackageCheck, permission: 'office.inventory.view', view: 'stock-receive', path: '/stock/receive' },
    { id: 'stock-transfer', label: 'Stock Transfer', myLabel: 'Stock Transfer', icon: Truck, permission: 'office.inventory.view', view: 'stock-transfer', path: '/stock/transfers' },
    { id: 'stock-adjustments', label: 'Stock Adjustments', myLabel: 'Stock Adjustments', icon: SlidersHorizontal, permission: 'office.inventory.view', view: 'stock-adjustments', path: '/stock/adjustments' },
    { id: 'closing-stock', label: 'Closing Stock', myLabel: 'Closing Stock', icon: ClipboardList, permission: 'office.inventory.view', view: 'closing-stock', path: '/stock/closing' },
    { id: 'stock-balance', label: 'Stock Balance', myLabel: 'Stock Balance', icon: Package, permission: 'office.inventory.view', view: 'stock-balance', path: '/stock/balances' },
    { id: 'stock-value', label: 'Stock Value', myLabel: 'Stock Value', icon: WalletCards, permission: 'office.inventory.view', view: 'stock-value', path: '/stock/value' },
    { id: 'stock-card', label: 'Stock Card', myLabel: 'Stock Card', icon: ClipboardList, permission: 'office.inventory.view', view: 'stock-card', path: '/stock/card' },
    { id: 'delivery-planning', label: 'Trip Planning', myLabel: 'ပို့ဆောင်မှုစီစဉ်ခြင်း', icon: Truck, permission: 'office.deliveries.view', view: 'delivery-planning', path: '/deliveries' },
    { id: 'delivery-live-map', label: 'Driver Live Map', myLabel: 'ယာဉ်မောင်း တိုက်ရိုက်မြေပုံ', icon: MapPinned, permission: 'office.deliveries.view', view: 'delivery-live-map', path: '/deliveries/live-map' },
    { id: 'delivery-history', label: 'Delivery History', myLabel: 'ပို့ဆောင်မှုမှတ်တမ်း', icon: ClipboardList, permission: 'office.deliveries.view', view: 'delivery-history', path: '/deliveries/history' },
    { id: 'attendance-locations', label: 'QR Locations', myLabel: 'QR Locations', icon: QrCode, permission: 'office.attendance.view', view: 'attendance-locations', path: '/attendance/locations' },
    { id: 'attendance-records', label: 'Attendance Records', myLabel: 'Attendance Records', icon: CalendarDays, permission: 'office.attendance.view', view: 'attendance-records', path: '/attendance/records' },
    { id: 'attendance-summary', label: 'Attendance Summary', myLabel: 'Attendance Summary', icon: ClipboardList, permission: 'office.attendance.view', view: 'attendance-summary', path: '/attendance/summary' },
    { id: 'payroll-drafts', label: 'Payroll Drafts', myLabel: 'Payroll Drafts', icon: CreditCard, permission: 'office.payroll.view', view: 'payroll-drafts', path: '/payroll/drafts' },
    { id: 'payroll-adjustments', label: 'Adjustments', myLabel: 'Adjustments', icon: WalletCards, permission: 'office.payroll.view', view: 'payroll-adjustments', path: '/payroll/adjustments' },
    { id: 'salary-history', label: 'Salary History', myLabel: 'Salary History', icon: ReceiptText, permission: 'office.payroll.view', view: 'salary-history', path: '/payroll/salary-history' },
    { id: 'finance-collections', label: 'Payments', myLabel: 'ငွေပေးချေမှုများ', icon: WalletCards, permission: 'office.finance.view', view: 'finance-collections', path: '/finance/collections' },
    { id: 'finance-outdoor-collections', label: 'Outdoor Collections', myLabel: 'ပြင်ပ ငွေကောက်ခံမှုများ', icon: Users, permission: 'office.finance.view', view: 'finance-outdoor-collections', path: '/finance/outdoor-collections' },
    { id: 'finance-receivables', label: 'Customer Credit', myLabel: 'ဖောက်သည် အကြွေးစာရင်း', icon: WalletCards, permission: 'office.finance.view', view: 'finance-receivables', path: '/finance/receivables' },
    { id: 'finance-suppliers', label: 'Supplier Ledger', myLabel: 'ပေးသွင်းသူ စာရင်း', icon: ClipboardList, permission: 'office.finance.view', view: 'finance-suppliers', path: '/finance/suppliers' },
    { id: 'finance-cash-book', label: 'Cash Book', myLabel: 'ငွေသားစာရင်း', icon: WalletCards, permission: 'office.finance.view', view: 'finance-cash-book', path: '/finance/cash-book' },
    { id: 'finance-bank-book', label: 'Bank Book', myLabel: 'ဘဏ်စာရင်း', icon: CreditCard, permission: 'office.finance.view', view: 'finance-bank-book', path: '/finance/bank-book' },
    { id: 'finance-daily-expenses', label: 'Daily Expense', myLabel: 'နေ့စဉ်အသုံးစရိတ်', icon: ReceiptText, permission: 'office.finance.view', view: 'finance-daily-expenses', path: '/finance/daily-expenses' },
    { id: 'finance-outdoor-expenses', label: 'Outdoor Expense', myLabel: 'ပြင်ပအသုံးစရိတ်', icon: MapPinned, permission: 'office.finance.view', view: 'finance-outdoor-expenses', path: '/finance/outdoor-expenses' },
    { id: 'finance-profit-loss', label: 'Profit / Loss', myLabel: 'အမြတ် / အရှုံး', icon: Rows3, permission: 'office.finance.view', view: 'finance-profit-loss', path: '/finance/profit-loss' },
    { id: 'vehicle-fuel', label: 'Fuel', myLabel: 'ဆီဖြည့်မှတ်တမ်း', icon: Truck, permission: 'office.vehicle-costs.view', view: 'vehicle-fuel', path: '/vehicle-costs/fuel' },
    { id: 'vehicle-maintenance', label: 'Maintenance', myLabel: 'ပြုပြင်ထိန်းသိမ်းမှု', icon: Settings, permission: 'office.vehicle-costs.view', view: 'vehicle-maintenance', path: '/vehicle-costs/maintenance' },
    { id: 'vehicle-insurance', label: 'Insurance', myLabel: 'အာမခံ', icon: CheckCircle2, permission: 'office.vehicle-costs.view', view: 'vehicle-insurance', path: '/vehicle-costs/insurance' },
    { id: 'vehicle-license', label: 'License', myLabel: 'ယာဉ်လိုင်စင်', icon: ClipboardList, permission: 'office.vehicle-costs.view', view: 'vehicle-license', path: '/vehicle-costs/license' },
    { id: 'vehicle-engine-oil', label: 'Engine Oil', myLabel: 'အင်ဂျင်ဝိုင်', icon: Droplets, permission: 'office.vehicle-costs.view', view: 'vehicle-engine-oil', path: '/vehicle-costs/engine-oil' },
    { id: 'vehicle-tyre', label: 'Tyres', myLabel: 'တာယာ', icon: CircleGauge, permission: 'office.vehicle-costs.view', view: 'vehicle-tyre', path: '/vehicle-costs/tyre' },
    { id: 'vehicle-monthly-cost', label: 'Monthly Cost', myLabel: 'လစဉ်ယာဉ်ကုန်ကျစရိတ်', icon: CreditCard, permission: 'office.vehicle-costs.view', view: 'vehicle-monthly-cost', path: '/vehicle-reports/monthly-cost' },
    { id: 'vehicle-route-history', label: 'Route History', myLabel: 'လမ်းကြောင်းမှတ်တမ်း', icon: MapPinned, permission: 'office.vehicle-costs.view', view: 'vehicle-route-history', path: '/vehicle-reports/route-history' },
    { id: 'vehicle-cost-per-km', label: 'Cost / KM', myLabel: 'တစ်ကီလိုမီတာကုန်ကျစရိတ်', icon: WalletCards, permission: 'office.vehicle-costs.view', view: 'vehicle-cost-per-km', path: '/vehicle-reports/cost-per-km' },
    { id: 'vehicle-performance', label: 'Performance', myLabel: 'ယာဉ်စွမ်းဆောင်ရည်', icon: Rows3, permission: 'office.vehicle-costs.view', view: 'vehicle-performance', path: '/vehicle-reports/performance' },
];

const officeNavGroups = [
    {
        id: 'overview',
        label: 'Overview',
        myLabel: 'အနှစ်ချုပ်',
        items: ['dashboard', 'dashboard-sales', 'dashboard-stock', 'dashboard-delivery', 'dashboard-finance'],
    },
    {
        id: 'business-setup',
        label: 'Business Setup',
        myLabel: 'လုပ်ငန်းအခြေခံ',
        items: ['areas', 'routes', 'warehouses'],
    },
    {
        id: 'catalog-pricing',
        label: 'Catalog & Pricing',
        myLabel: 'ကုန်ပစ္စည်းနှင့် ဈေးနှုန်း',
        items: ['brands', 'products', 'price-types', 'product-prices'],
    },
    {
        id: 'people-assets',
        label: 'People & Assets',
        myLabel: 'လူနှင့် ပိုင်ဆိုင်မှု',
        items: ['customers', 'employees', 'vehicles'],
    },
    {
        id: 'sales-ops',
        label: 'Sales Ops',
        myLabel: 'အရောင်းလုပ်ငန်း',
        items: ['orders', 'sales-returns'],
    },
    {
        id: 'warehouse-stock',
        label: 'Warehouse Stock',
        myLabel: 'Warehouse Stock',
        items: ['stock-receive', 'stock-transfer', 'stock-adjustments', 'closing-stock', 'stock-balance', 'stock-value', 'stock-card'],
    },
    {
        id: 'delivery-operations',
        label: 'Delivery Operations',
        myLabel: 'ပို့ဆောင်ရေးလုပ်ငန်း',
        items: ['delivery-planning', 'delivery-live-map', 'delivery-history'],
    },
    {
        id: 'attendance',
        label: 'Attendance',
        myLabel: 'Attendance',
        items: ['attendance-locations', 'attendance-records', 'attendance-summary'],
    },
    {
        id: 'payroll',
        label: 'Payroll',
        myLabel: 'Payroll',
        items: ['payroll-drafts', 'payroll-adjustments', 'salary-history'],
    },
    {
        id: 'finance',
        label: 'Finance & Accounts',
        myLabel: 'ဘဏ္ဍာရေးနှင့် စာရင်းများ',
        items: ['finance-collections', 'finance-outdoor-collections', 'finance-receivables', 'finance-suppliers', 'finance-cash-book', 'finance-bank-book', 'finance-daily-expenses', 'finance-outdoor-expenses', 'finance-profit-loss'],
    },
    {
        id: 'vehicle-operations',
        label: 'Vehicle Operations',
        myLabel: 'ယာဉ်လုပ်ငန်း',
        items: ['vehicle-fuel', 'vehicle-maintenance', 'vehicle-insurance', 'vehicle-license', 'vehicle-engine-oil', 'vehicle-tyre', 'vehicle-monthly-cost', 'vehicle-route-history', 'vehicle-cost-per-km', 'vehicle-performance'],
    },
    {
        id: 'access-control',
        label: 'Access Control',
        myLabel: 'အသုံးပြုခွင့်',
        items: ['roles', 'permissions'],
    },
    {
        id: 'launch-readiness',
        label: 'Launch Readiness',
        myLabel: 'စတင်အသုံးပြုရန် အသင့်',
        items: ['uat-readiness'],
    },
    {
        id: 'settings',
        label: 'Settings',
        myLabel: 'ဆက်တင်များ',
        items: ['company-settings'],
    },
];

const mobileNav = {
    client: [
        { labelKey: 'home', view: 'home', icon: Home, permission: 'client.home.view' },
        { labelKey: 'orders', view: 'orders', icon: ReceiptText, permission: 'client.orders.view' },
        { labelKey: 'deliveries', view: 'deliveries', icon: Truck, permission: 'client.deliveries.view' },
        { labelKey: 'ledger', view: 'ledger', icon: WalletCards, permission: 'client.finance.view' },
        { labelKey: 'profile', view: 'profile', icon: User, permission: 'client.profile.view' },
    ],
    sales: [
        { labelKey: 'home', view: 'home', icon: Home, permission: 'sales.home.view' },
        { labelKey: 'visits', view: 'route', icon: MapPinned, permission: 'sales.route.view' },
        { labelKey: 'orders', view: 'orders', icon: ReceiptText, permission: 'sales.orders.view' },
        { labelKey: 'customers', view: 'customers', icon: Users, permission: 'sales.customers.view' },
    ],
    driver: [
        { labelKey: 'home', view: 'home', icon: Home, permission: 'driver.home.view' },
        { labelKey: 'profile', view: 'profile', icon: User, permission: 'driver.profile.view' },
        { labelKey: 'attendance', view: 'attendance', icon: CalendarDays, permission: 'driver.attendance.view' },
        { labelKey: 'salary', view: 'salary', icon: CreditCard, permission: 'driver.payroll.view' },
        { labelKey: 'vehicle', view: 'vehicle', icon: Truck, permission: 'driver.vehicle-costs.view' },
        { labelKey: 'load', view: 'load', icon: PackageCheck, permission: 'driver.load.view' },
        { labelKey: 'route', view: 'route', icon: MapPinned, permission: 'driver.route.view' },
        { labelKey: 'confirm', view: 'confirm', icon: CheckCircle2, permission: 'driver.confirm.view' },
        { labelKey: 'collections', view: 'collections', icon: WalletCards, permission: 'driver.collections.view' },
        { labelKey: 'expenses', view: 'expenses', icon: CreditCard, permission: 'driver.expenses.view' },
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
        hero: 'Today’s visits',
        title: 'Customer visit plan',
        meta: '12 customers remaining',
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
    const [pathname, setPathname] = useState(() => window.location.pathname);
    const [online, setOnline] = useState(() => window.navigator.onLine);
    const [theme, setTheme] = useState(() => window.localStorage.getItem('valley-theme') || 'light');
    const [locale, setLocale] = useState(() => window.localStorage.getItem('valley-locale') || 'en');
    const [auth, setAuth] = useState({ loading: true, user: null, errors: {}, message: '' });
    const [branding, setBranding] = useState({ name: 'Valley Water', logo_url: null, primary_color: '#0b84a5', default_theme: 'light' });
    const t = { ...copy.en, ...copy[locale] };
    const activeApp = resolveAppFromPath(pathname);
    const attendanceToken = resolveAttendanceToken(pathname);
    const selectedApp = appConfig[activeApp];
    const rootStyle = useMemo(() => ({ '--color-primary': activeApp === 'office' ? branding.primary_color : selectedApp.accent }), [activeApp, branding.primary_color, selectedApp.accent]);

    useEffect(() => window.localStorage.setItem('valley-theme', theme), [theme]);
    useEffect(() => window.localStorage.setItem('valley-locale', locale), [locale]);
    useEffect(() => {
        const handlePopState = () => setPathname(window.location.pathname);
        window.addEventListener('popstate', handlePopState);
        return () => window.removeEventListener('popstate', handlePopState);
    }, []);

    useEffect(() => {
        if (!auth.user || activeApp !== 'office' || !auth.user.permissions.includes('office.master-data.view')) return undefined;
        let mounted = true;

        window.axios.get(window.ValleyRuntime?.api?.companySettings || '/api/settings/company')
            .then(({ data }) => {
                if (!mounted) return;
                const company = data.data.company;
                setBranding(company);
                if (!window.localStorage.getItem('valley-theme') && company.default_theme) setTheme(company.default_theme);
            })
            .catch(() => {});

        return () => { mounted = false; };
    }, [activeApp, auth.user]);
    useEffect(() => {
        const markOnline = () => setOnline(true);
        const markOffline = () => setOnline(false);
        window.addEventListener('online', markOnline);
        window.addEventListener('offline', markOffline);

        return () => {
            window.removeEventListener('online', markOnline);
            window.removeEventListener('offline', markOffline);
        };
    }, []);

    useEffect(() => {
        let isMounted = true;

        window.axios
            .get(authRoute('user', '/api/auth/user'))
            .then((response) => {
                if (isMounted) {
                    const currentUser = response.data.data.user;
                    if (!window.localStorage.getItem('valley-locale') && currentUser?.locale) {
                        setLocale(currentUser.locale);
                    }
                    setAuth({ loading: false, user: currentUser, errors: {}, message: '' });
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

    const handleLocaleChange = async (nextLocale) => {
        setLocale(nextLocale);
        if (!auth.user) return;

        try {
            const response = await window.axios.put(authRoute('preferences', '/api/auth/preferences'), { locale: nextLocale });
            setAuth((current) => ({ ...current, user: response.data.data.user }));
        } catch {
            // The local preference still keeps the interface usable if the request fails.
        }
    };

    const navigate = (href) => {
        const destination = new URL(href, window.location.origin);
        const nextPath = `${destination.pathname}${destination.search}${destination.hash}`;
        const currentPath = `${window.location.pathname}${window.location.search}${window.location.hash}`;

        if (destination.origin !== window.location.origin) {
            window.location.assign(destination.href);
            return;
        }

        if (nextPath !== currentPath) {
            window.history.pushState({}, '', nextPath);
            setPathname(destination.pathname);
            window.scrollTo({ top: 0, behavior: 'auto' });
        }
    };

    return (
        <div className="app-root" data-theme={theme} data-density="compact" data-app={activeApp} data-locale={locale} style={rootStyle}>
            {!online && <div className="offline-banner" role="status"><TriangleAlert size={15} />You are offline. Existing screen data remains visible; saving is paused until the connection returns.</div>}
            {attendanceToken ? (
                <PublicAttendanceScreen token={attendanceToken} locale={locale} setLocale={handleLocaleChange} />
            ) : auth.loading ? (
                <AppLoading t={t} />
            ) : !auth.user ? (
                <AuthScreen
                    app={selectedApp}
                    t={t}
                    locale={locale}
                    setLocale={handleLocaleChange}
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
                    setLocale={handleLocaleChange}
                    theme={theme}
                    setTheme={setTheme}
                    pathname={pathname}
                    navigate={navigate}
                    onUserUpdated={(user) => setAuth((current) => ({ ...current, user }))}
                    branding={branding}
                    onBrandingUpdated={(company) => { setBranding(company); setTheme(company.default_theme); }}
                />
            ) : (
                <MobileApp
                    app={selectedApp}
                    t={t}
                    user={auth.user}
                    onLogout={handleLogout}
                    locale={locale}
                    setLocale={handleLocaleChange}
                    theme={theme}
                    setTheme={setTheme}
                    pathname={pathname}
                    navigate={navigate}
                    onUserUpdated={(user) => setAuth((current) => ({ ...current, user }))}
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

function resolveAttendanceToken(pathname = window.location.pathname) {
    const currentPath = normalizePath(pathname);
    const attendancePath = normalizePath(new URL(runtimeRoute('attendance', '/attendance'), window.location.origin).pathname);

    if (!currentPath.startsWith(`${attendancePath}/`)) return null;
    return currentPath.slice(attendancePath.length).split('/').filter(Boolean)[0] || null;
}

function resolveAppFromPath(pathname = window.location.pathname) {
    const currentPath = normalizePath(pathname);
    const matchedApp = appOrder.find((id) => {
        const routePath = normalizePath(new URL(appConfig[id].path, window.location.origin).pathname);
        return currentPath === routePath || currentPath.startsWith(`${routePath}/`);
    });

    if (matchedApp) {
        return matchedApp;
    }

    const matchedSegment = [...pathname.split('/').filter(Boolean)]
        .reverse()
        .find((segment) => appConfig[segment]);

    return matchedSegment || 'office';
}

function normalizePath(path) {
    const normalized = `/${path.split('/').filter(Boolean).join('/')}`;
    return normalized === '/' ? normalized : normalized.replace(/\/$/, '');
}

function OfficeApp({ t, user, onLogout, locale, setLocale, theme, setTheme, pathname, navigate, onUserUpdated, branding, onBrandingUpdated }) {
    const [drawerOpen, setDrawerOpen] = useState(false);
    const visibleOfficeNavGroups = officeNavGroups
        .map((group) => ({
            ...group,
            items: group.items.map((id) => officeNavItems.find((item) => item.id === id)).filter((item) => item && hasPermission(user, item.permission)),
        }))
        .filter((group) => group.items.length > 0);
    const visibleOfficeNav = visibleOfficeNavGroups.flatMap((group) => group.items);
    const activeResource = resolveOfficeResource(pathname);
    const activeView = resolveOfficeView(pathname);
    const canManageMasterData = hasPermission(user, 'office.master-data.manage');
    const canManageAttendance = hasPermission(user, 'office.attendance.manage');
    const canManagePayroll = hasPermission(user, 'office.payroll.manage');
    const canManageOrders = hasPermission(user, 'office.orders.manage');
    const canManageInvoices = hasPermission(user, 'office.invoices.manage');
    const canManageInventory = hasPermission(user, 'office.inventory.manage');
    const canManageDeliveries = hasPermission(user, 'office.deliveries.manage');
    const canManageFinance = hasPermission(user, 'office.finance.manage');
    const canManageVehicleCosts = hasPermission(user, 'office.vehicle-costs.manage');
    const canManageUat = hasPermission(user, 'office.uat.manage');

    return (
        <>
            <aside className={`sidebar ${drawerOpen ? 'is-open' : ''}`}>
                <Brand t={t} branding={branding} />
                <nav className="sidebar-nav" aria-label={t.demoNav}>
                    {visibleOfficeNavGroups.map((group) => (
                        <section className="nav-group" aria-labelledby={`nav-group-${group.id}`} key={group.id}>
                            <p className="nav-section" id={`nav-group-${group.id}`}>{locale === 'my' ? group.myLabel : group.label}</p>
                            {group.items.map((item) => {
                                const isActive = item.resource
                                    ? activeResource === item.resource
                                    : item.view
                                        ? activeView === item.view
                                        : !activeResource && !activeView;
                                const href = item.resource
                                    ? `${appConfig.office.path}/master/${item.resource}`
                                    : item.path
                                        ? `${appConfig.office.path}${item.path}`
                                    : item.view === 'company-settings'
                                        ? `${appConfig.office.path}/settings/company`
                                        : appConfig.office.path;
                                return (
                                    <a
                                        className={`nav-item ${isActive ? 'is-active' : ''}`}
                                        href={href}
                                        onClick={(event) => {
                                            if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
                                            event.preventDefault();
                                            navigate(href);
                                            setDrawerOpen(false);
                                        }}
                                        aria-current={isActive ? 'page' : undefined}
                                        key={item.label}
                                    >
                                        <item.icon size={16} />
                                        <span>{locale === 'my' ? item.myLabel : item.label}</span>
                                    </a>
                                );
                            })}
                        </section>
                    ))}
                </nav>
            </aside>

            {drawerOpen && <button className="mobile-scrim" type="button" aria-label="Close navigation" onClick={() => setDrawerOpen(false)} />}

            <main className="workspace">
                <header className="topbar">
                    <button className="icon-button mobile-only" type="button" aria-label="Open navigation" title="Open navigation" onClick={() => setDrawerOpen(true)}>
                        <Menu size={18} />
                    </button>
                    <ShellFilterControl pathname={pathname} />
                    <AppearanceControls
                        t={t}
                        user={user}
                        onLogout={onLogout}
                        locale={locale}
                        setLocale={setLocale}
                        theme={theme}
                        setTheme={setTheme}
                        onProfile={() => navigate(`${appConfig.office.path}/profile`)}
                    />
                </header>

                {activeResource ? (
                    <MasterDataWorkspace resourceKey={activeResource} locale={locale} canManage={canManageMasterData} detailId={resolveOfficeMasterDetailId(pathname, activeResource)} onNavigate={navigate} />
                ) : activeView === 'profile-settings' ? (
                    <ProfileSettingsScreen user={user} onUserUpdated={onUserUpdated} />
                ) : activeView === 'dashboard-sales' ? (
                    <OfficeKpiDashboard kind="sales" locale={locale} />
                ) : activeView === 'dashboard-stock' ? (
                    <OfficeKpiDashboard kind="stock" locale={locale} />
                ) : activeView === 'dashboard-delivery' ? (
                    <OfficeKpiDashboard kind="delivery" locale={locale} />
                ) : activeView === 'dashboard-finance' ? (
                    <OfficeKpiDashboard kind="finance" locale={locale} />
                ) : activeView === 'uat-readiness' ? (
                    <UatReadinessScreen locale={locale} canManage={canManageUat} />
                ) : activeView === 'company-settings' ? (
                    <CompanySettingsScreen locale={locale} canManage={canManageMasterData} onBrandingUpdated={onBrandingUpdated} />
                ) : activeView === 'orders' ? (
                    <OrdersScreen locale={locale} canManage={canManageOrders} creating={isOfficeRoute(pathname, '/orders/new')} detailId={resolveOfficeDetailId(pathname, '/orders')} onNavigate={navigate} />
                ) : activeView === 'invoices' ? (
                    <InvoicesScreen locale={locale} canManage={canManageInvoices} detailId={resolveOfficeDetailId(pathname, '/invoices')} onNavigate={navigate} />
                ) : activeView === 'sales-returns' ? (
                    <SalesReturnScreen locale={locale} canManage={canManageOrders} creating={isOfficeRoute(pathname, '/returns/new')} detailId={resolveOfficeDetailId(pathname, '/returns')} onNavigate={navigate} />
                ) : activeView === 'stock-receive' ? (
                    <StockReceiveScreen
                        locale={locale}
                        canManage={canManageInventory}
                        receiveForm={normalizePath(pathname).endsWith('/stock/receive/new')}
                        onNavigate={navigate}
                    />
                ) : activeView === 'stock-transfer' ? (
                    <StockTransferScreen
                        locale={locale}
                        canManage={canManageInventory}
                        transferForm={normalizePath(pathname).endsWith('/stock/transfers/new')}
                        onNavigate={navigate}
                    />
                ) : activeView === 'stock-adjustments' ? (
                    <StockAdjustmentScreen
                        locale={locale}
                        canManage={canManageInventory}
                        creating={normalizePath(pathname).endsWith('/stock/adjustments/new')}
                        onNavigate={navigate}
                    />
                ) : activeView === 'closing-stock' ? (
                    <ClosingStockScreen
                        locale={locale}
                        canManage={canManageInventory}
                        closingForm={normalizePath(pathname).endsWith('/stock/closing/new')}
                        detailId={resolveOfficeDetailId(pathname, '/stock/closing')}
                        onNavigate={navigate}
                    />
                ) : activeView === 'stock-balance' ? (
                    <StockBalanceScreen locale={locale} />
                ) : activeView === 'stock-value' ? (
                    <StockValueScreen locale={locale} />
                ) : activeView === 'stock-card' ? (
                    <StockCardScreen locale={locale} />
                ) : activeView === 'delivery-planning' ? (
                    normalizePath(pathname).endsWith('/deliveries/new')
                        ? <TripWizardPage locale={locale} navigate={navigate} />
                        : normalizePath(pathname).endsWith('/edit') && resolveOfficeDetailId(pathname, '/deliveries')
                            ? <TripEditPage deliveryId={resolveOfficeDetailId(pathname, '/deliveries')} locale={locale} navigate={navigate} />
                        : <DeliveryPlanningScreen canManage={canManageDeliveries} locale={locale} navigate={navigate} detailId={resolveOfficeDetailId(pathname, '/deliveries')} />
                ) : activeView === 'delivery-live-map' ? (
                    <DeliveryLiveMapScreen locale={locale} />
                ) : activeView === 'delivery-history' ? (
                    <DeliveryPlanningScreen historyOnly locale={locale} navigate={navigate} detailId={resolveOfficeDetailId(pathname, '/deliveries/history')} />
                ) : activeView === 'attendance-locations' ? (
                    <AttendanceLocationsScreen locale={locale} canManage={canManageAttendance} detailId={resolveOfficeDetailId(pathname, '/attendance/locations')} onNavigate={navigate} />
                ) : activeView === 'attendance-records' ? (
                    <AttendanceRecordsScreen locale={locale} detailId={resolveOfficeDetailId(pathname, '/attendance/records')} onNavigate={navigate} />
                ) : activeView === 'attendance-summary' ? (
                    <AttendanceSummaryScreen locale={locale} />
                ) : activeView === 'payroll-drafts' ? (
                    <PayrollDraftsScreen locale={locale} canManage={canManagePayroll} detailId={resolveOfficeDetailId(pathname, '/payroll/drafts')} onNavigate={navigate} />
                ) : activeView === 'payroll-adjustments' ? (
                    <PayrollAdjustmentsScreen locale={locale} canManage={canManagePayroll} />
                ) : activeView === 'salary-history' ? (
                    <SalaryHistoryScreen locale={locale} detailId={resolveOfficeDetailId(pathname, '/payroll/salary-history')} onNavigate={navigate} />
                ) : activeView === 'finance-collections' ? (
                    <FinanceCollectionsScreen canManage={canManageFinance} locale={locale} />
                ) : activeView === 'finance-outdoor-collections' ? (
                    <FinanceCollectionsScreen outdoor canManage={canManageFinance} locale={locale} />
                ) : activeView === 'finance-receivables' ? (
                    <FinanceReceivablesScreen locale={locale} canManage={canManageFinance} detailId={resolveOfficeDetailId(pathname, '/finance/receivables')} onNavigate={navigate} />
                ) : activeView === 'finance-suppliers' ? (
                    <SupplierLedgerScreen canManage={canManageFinance} locale={locale} />
                ) : activeView === 'finance-cash-book' ? (
                    <FinanceBookScreen book="cash" locale={locale} />
                ) : activeView === 'finance-bank-book' ? (
                    <FinanceBookScreen book="bank" locale={locale} />
                ) : activeView === 'finance-daily-expenses' ? (
                    <FinanceExpensesScreen canManage={canManageFinance} locale={locale} />
                ) : activeView === 'finance-outdoor-expenses' ? (
                    <FinanceExpensesScreen outdoor canManage={canManageFinance} locale={locale} />
                ) : activeView === 'finance-profit-loss' ? (
                    <ProfitLossScreen locale={locale} />
                ) : activeView === 'vehicle-fuel' ? (
                    <VehicleCostsScreen type="fuel" locale={locale} canManage={canManageVehicleCosts} />
                ) : activeView === 'vehicle-maintenance' ? (
                    <VehicleCostsScreen type="maintenance" locale={locale} canManage={canManageVehicleCosts} />
                ) : activeView === 'vehicle-insurance' ? (
                    <VehicleCostsScreen type="insurance" locale={locale} canManage={canManageVehicleCosts} />
                ) : activeView === 'vehicle-license' ? (
                    <VehicleCostsScreen type="license" locale={locale} canManage={canManageVehicleCosts} />
                ) : activeView === 'vehicle-engine-oil' ? (
                    <VehicleCostsScreen type="engine_oil" locale={locale} canManage={canManageVehicleCosts} />
                ) : activeView === 'vehicle-tyre' ? (
                    <VehicleCostsScreen type="tyre" locale={locale} canManage={canManageVehicleCosts} />
                ) : activeView === 'vehicle-monthly-cost' ? (
                    <VehicleMonthlyCostScreen locale={locale} />
                ) : activeView === 'vehicle-route-history' ? (
                    <VehicleRouteHistoryScreen locale={locale} canManage={canManageVehicleCosts} />
                ) : activeView === 'vehicle-cost-per-km' ? (
                    <VehiclePerformanceScreen locale={locale} costOnly />
                ) : activeView === 'vehicle-performance' ? (
                    <VehiclePerformanceScreen locale={locale} />
                ) : (
                    <OfficeKpiDashboard kind="owner" locale={locale} />
                )}
            </main>
        </>
    );
}

const shellFilterSelector = [
    '.master-toolbar',
    '.transfer-filter-toolbar',
    '.mobile-order-filters',
    '.mobile-attendance-filters',
    '.mobile-payroll-filters',
    '.sales-route-tools',
    '.trip-order-toolbar',
    '.phase10-filter',
    '.status-tabs',
].join(', ');

function ShellFilterControl({ pathname }) {
    const [available, setAvailable] = useState(false);
    const [open, setOpen] = useState(false);
    const [triggerHost, setTriggerHost] = useState(null);
    const triggerRef = useRef(null);
    const closeRef = useRef(null);
    const wasOpen = useRef(false);

    useEffect(() => {
        setOpen(false);
    }, [pathname]);

    useEffect(() => {
        const workspace = document.querySelector('.app-root');
        if (!workspace) return undefined;

        const sync = () => {
            const toolbars = [...workspace.querySelectorAll(shellFilterSelector)];
            const pageHeading = workspace.querySelector('.page > .master-heading, .page > .mobile-master-heading, .mobile-master-stack > .mobile-master-heading');
            workspace.querySelectorAll('.master-heading > button, .mobile-master-heading > button').forEach((button) => {
                if (!button.querySelector('.lucide-plus')) return;
                const label = button.textContent.trim() || button.getAttribute('aria-label') || 'Add';
                if (!button.getAttribute('aria-label')) button.setAttribute('aria-label', label);
                if (!button.getAttribute('title')) button.setAttribute('title', label);
            });
            toolbars.forEach((toolbar, index) => {
                toolbar.dataset.shellFilter = 'true';
                toolbar.id = `shell-filter-drawer-${index + 1}`;
                toolbar.setAttribute('aria-hidden', open ? 'false' : 'true');
                toolbar.inert = !open;
            });
            setAvailable(toolbars.length > 0);
            setTriggerHost((current) => current === pageHeading ? current : pageHeading);
            if (toolbars.length === 0) setOpen(false);
        };

        sync();
        const observer = new MutationObserver(sync);
        observer.observe(workspace, { childList: true, subtree: true });
        return () => observer.disconnect();
    }, [pathname, open]);

    useEffect(() => {
        document.body.classList.toggle('filter-drawer-open', available && open);
        return () => document.body.classList.remove('filter-drawer-open');
    }, [available, open]);

    useEffect(() => {
        if (!open) return undefined;
        window.requestAnimationFrame(() => closeRef.current?.focus());
        const closeOnEscape = (event) => event.key === 'Escape' && setOpen(false);
        window.addEventListener('keydown', closeOnEscape);
        return () => window.removeEventListener('keydown', closeOnEscape);
    }, [open]);

    useEffect(() => {
        if (wasOpen.current && !open) triggerRef.current?.focus();
        wasOpen.current = open;
    }, [open]);

    if (!available || !triggerHost) return null;

    return (
        <>
            {createPortal(
                <button ref={triggerRef} className={`icon-button shell-filter-trigger ${open ? 'is-active' : ''}`} type="button" aria-label="Filters" title="Filters" aria-expanded={open} aria-controls="shell-filter-drawer-1" onClick={() => setOpen((value) => !value)}>
                    <ListFilter size={16} />
                </button>,
                triggerHost,
            )}
            {open && <button className="filter-drawer-scrim" type="button" aria-label="Close filters" onClick={() => setOpen(false)} />}
            <div className="filter-drawer-header" aria-hidden={!open}>
                <div>
                    <span>View options</span>
                    <strong>Filters</strong>
                </div>
                <button ref={closeRef} className="icon-button" type="button" aria-label="Close filters" title="Close filters" onClick={() => setOpen(false)}>
                    <X size={18} />
                </button>
            </div>
        </>
    );
}

function MobileApp({ app, t, user, onLogout, locale, setLocale, theme, setTheme, pathname, navigate, onUserUpdated }) {
    const visibleNav = mobileNav[app.id].filter((item) => hasPermission(user, item.permission));
    const activeView = resolveMobileView(app.id, pathname);
    const compactPrimaryViews = {
        sales: ['home', 'route', 'orders', 'customers'],
        driver: ['home', 'load', 'route', 'confirm'],
    };
    const supportsMenuNav = Object.hasOwn(compactPrimaryViews, app.id);
    const primaryViews = compactPrimaryViews[app.id] || [];
    const menuNav = supportsMenuNav ? visibleNav.filter((item) => !primaryViews.includes(item.view)) : [];
    const usesMenuNav = supportsMenuNav && menuNav.length > 0;
    const primaryNav = supportsMenuNav ? visibleNav.filter((item) => primaryViews.includes(item.view)) : visibleNav;
    const menuActive = activeView === 'menu' || activeView === 'account' || menuNav.some((item) => item.view === activeView);
    const mobileOrderId = ['client', 'sales'].includes(app.id) ? resolveMobileOrderId(app.path, pathname) : null;
    const salesCustomerId = app.id === 'sales' ? resolveSalesCustomerId(app.path, pathname) : null;

    return (
        <main className="mobile-app-shell">
            <header className="mobile-app-topbar">
                <Brand t={t} compact />
                <div className="mobile-top-actions">
                    <ShellFilterControl pathname={pathname} />
                    <button className="icon-button" type="button" aria-label={t.theme} title={t.theme} onClick={() => setTheme(theme === 'light' ? 'dark' : 'light')}>
                        {theme === 'light' ? <Moon size={16} /> : <Sun size={16} />}
                    </button>
                    <ProfileMenu
                        user={user}
                        t={t}
                        locale={locale}
                        setLocale={setLocale}
                        onLogout={onLogout}
                        onProfile={() => navigate(`${app.path}/account`)}
                        compact
                    />
                </div>
            </header>

            <section className="mobile-page">
                {['client', 'sales'].includes(app.id) && activeView === 'orders' && (mobileOrderId
                    ? <MobileOrderDetailPage orderId={mobileOrderId} locale={locale} onBack={() => navigate(`${app.path}/orders`)} />
                    : <MobileOrdersScreen appId={app.id} locale={locale} onViewOrder={(orderId) => navigate(`${app.path}/orders/${orderId}`)} />)}
                {app.id === 'client' && activeView === 'deliveries' && <MobileDeliveryStatusScreen appId={app.id} locale={locale} />}
                {app.id === 'client' && activeView === 'profile' && <ClientMasterScreen locale={locale} />}
                {app.id === 'sales' && activeView === 'customers' && (salesCustomerId
                    ? <SalesCustomerDetailPage customerId={salesCustomerId} locale={locale} onBack={() => navigate(`${app.path}/customers`)} onViewOrder={(orderId) => navigate(`${app.path}/orders/${orderId}`)} />
                    : <SalesMasterScreen locale={locale} onViewCustomer={(customerId) => navigate(`${app.path}/customers/${customerId}`)} />)}
                {app.id === 'sales' && activeView === 'route' && <SalesRouteScreen locale={locale} onViewCustomer={(customerId) => navigate(`${app.path}/customers/${customerId}`)} onOrders={() => navigate(`${app.path}/orders`)} />}
                {usesMenuNav && activeView === 'menu' && <MobileMenuScreen app={app} items={menuNav} t={t} navigate={navigate} />}
                {activeView === 'account' && <ProfileSettingsScreen user={user} onUserUpdated={onUserUpdated} />}
                {['sales', 'driver'].includes(app.id) && activeView === 'attendance' && <MobileAttendanceHistoryScreen locale={locale} />}
                {['sales', 'driver'].includes(app.id) && activeView === 'salary' && <MobilePayrollHistoryScreen locale={locale} />}
                {app.id === 'driver' && activeView === 'profile' && <DriverMasterScreen locale={locale} />}
                {app.id === 'driver' && activeView === 'load' && <MobileDriverDeliveriesScreen locale={locale} />}
                {app.id === 'driver' && activeView === 'route' && <MobileDriverExecutionScreen mode="route" locale={locale} />}
                {app.id === 'driver' && activeView === 'confirm' && <MobileDriverExecutionScreen mode="confirm" locale={locale} />}
                {app.id === 'client' && activeView === 'ledger' && <MobileFinanceScreen appId={app.id} mode="ledger" locale={locale} />}
                {app.id === 'driver' && activeView === 'collections' && <MobileFinanceScreen appId={app.id} mode="collections" locale={locale} />}
                {['sales', 'driver'].includes(app.id) && activeView === 'expenses' && <MobileFinanceScreen appId={app.id} mode="expenses" locale={locale} />}
                {app.id === 'driver' && activeView === 'vehicle' && <MobileVehicleOperationsScreen locale={locale} />}
                {activeView === 'home' && <MobileHomeDashboard
                    appId={app.id}
                    locale={locale}
                    quickLinks={[]}
                    onNavigate={(event, href) => navigateAppPage(event, href, navigate)}
                />}
                {!((activeView === 'home') || ['account', 'menu'].includes(activeView) || (app.id === 'client' && ['orders', 'deliveries', 'profile', 'ledger'].includes(activeView)) || (app.id === 'sales' && ['orders', 'route', 'customers'].includes(activeView)) || (app.id === 'driver' && ['profile', 'load', 'route', 'confirm', 'attendance', 'salary', 'collections', 'expenses', 'vehicle'].includes(activeView))) && (
                    <MobilePlaceholderScreen app={app} view={activeView} t={t} />
                )}
            </section>

            <nav className="bottom-nav" aria-label={t.currentRoute} style={{ '--mobile-nav-count': usesMenuNav ? primaryNav.length + 1 : primaryNav.length }}>
                {primaryNav.map(({ labelKey, view, icon: Icon }) => (
                    <a className={view === activeView ? 'is-active' : ''} href={`${app.path}/${view}`} onClick={(event) => navigateAppPage(event, `${app.path}/${view}`, navigate)} aria-current={view === activeView ? 'page' : undefined} key={labelKey}>
                        <Icon size={17} />
                        <span>{t[labelKey]}</span>
                    </a>
                ))}
                {usesMenuNav && (
                    <a className={menuActive ? 'is-active' : ''} href={`${app.path}/menu`} onClick={(event) => navigateAppPage(event, `${app.path}/menu`, navigate)} aria-current={activeView === 'menu' ? 'page' : undefined}>
                        <Menu size={17} />
                        <span>{t.menu}</span>
                    </a>
                )}
            </nav>
        </main>
    );
}

function MobileMenuScreen({ app, items, t, navigate }) {
    return (
        <div className="mobile-master-stack mobile-menu-page">
            <div className="mobile-master-heading">
                <div><p className="eyebrow">{t[app.id]}</p><h1>{t.menu}</h1><span className="muted">{t.moreOperations}</span></div>
            </div>
            <nav className="mobile-master-section mobile-menu-list" aria-label={t.moreOperations}>
                {items.map(({ labelKey, view, icon: Icon }) => (
                    <a href={`${app.path}/${view}`} onClick={(event) => navigateAppPage(event, `${app.path}/${view}`, navigate)} key={labelKey}>
                        <span><Icon size={18} /></span><strong>{t[labelKey]}</strong><ChevronRight size={17} />
                    </a>
                ))}
            </nav>
        </div>
    );
}

function navigateAppPage(event, href, navigate) {
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    navigate(href);
}

function resolveMobileOrderId(appPath, pathname) {
    const currentPath = normalizePath(pathname);
    const rootPath = normalizePath(new URL(appPath, window.location.origin).pathname);
    const segments = currentPath.slice(rootPath.length).split('/').filter(Boolean);
    return segments[0] === 'orders' && /^\d+$/.test(segments[1] || '') ? segments[1] : null;
}

function resolveSalesCustomerId(appPath, pathname) {
    const currentPath = normalizePath(pathname);
    const rootPath = normalizePath(new URL(appPath, window.location.origin).pathname);
    const segments = currentPath.slice(rootPath.length).split('/').filter(Boolean);
    return segments[0] === 'customers' && /^\d+$/.test(segments[1] || '') ? segments[1] : null;
}

function resolveMobileView(appId, pathname = window.location.pathname) {
    const currentPath = normalizePath(pathname);
    const appPath = normalizePath(new URL(appConfig[appId].path, window.location.origin).pathname);
    const requestedView = currentPath.slice(appPath.length).split('/').filter(Boolean)[0];
    const defaultViews = { client: 'home', sales: 'home', driver: 'home' };

    if (requestedView === 'account' || (['sales', 'driver'].includes(appId) && requestedView === 'menu')) return requestedView;
    return mobileNav[appId].some((item) => item.view === requestedView) ? requestedView : defaultViews[appId];
}

function MobilePlaceholderScreen({ app, view, t }) {
    const data = mobileData[app.id];
    const ViewIcon = mobileNav[app.id].find((item) => item.view === view)?.icon || Home;

    return (
        <div className="mobile-placeholder-stack">
            <div className="mobile-hero">
                <div>
                    <p className="eyebrow">{t.preview}</p>
                    <h1>{t[view]}</h1>
                    <span className="muted">{t.plannedPhase}</span>
                </div>
                <span className="mobile-placeholder-icon"><ViewIcon size={22} /></span>
            </div>
            <section className="mobile-status-card">
                <span className="muted">{data.hero}</span>
                <h2>{data.title}</h2>
                <p>{data.meta}</p>
                <span className="status neutral">{t.ready}</span>
            </section>
            <div className="mobile-list">
                {data.items.map(([label, value]) => (
                    <button type="button" key={label}>
                        <span><strong>{label}</strong><small>{value}</small></span>
                        <ChevronRight size={17} />
                    </button>
                ))}
            </div>
        </div>
    );
}

function resolveOfficeResource(pathname = window.location.pathname) {
    const currentPath = normalizePath(pathname);
    const officePath = normalizePath(new URL(appConfig.office.path, window.location.origin).pathname);
    const relativePath = currentPath.slice(officePath.length).split('/').filter(Boolean);
    return relativePath[0] === 'master' ? relativePath[1] || null : null;
}

function resolveOfficeView(pathname = window.location.pathname) {
    const currentPath = normalizePath(pathname);
    const officePath = normalizePath(new URL(appConfig.office.path, window.location.origin).pathname);
    const legacyAdjustmentPaths = [
        normalizePath(`${officePath}/damage`),
        normalizePath(`${officePath}/stock/damage`),
    ];
    if (legacyAdjustmentPaths.some((path) => currentPath === path || currentPath.startsWith(`${path}/`))) {
        return 'stock-adjustments';
    }
    const configuredViews = officeNavItems
        .filter((item) => item.view && item.path)
        .map((item) => ({ ...item, routePath: normalizePath(`${officePath}${item.path}`) }))
        .sort((left, right) => right.routePath.length - left.routePath.length);
    const matchedItem = configuredViews.find((item) => (
        currentPath === item.routePath || currentPath.startsWith(`${item.routePath}/`)
    ));

    if (matchedItem) return matchedItem.view;
    if (currentPath === normalizePath(`${officePath}/profile`)) return 'profile-settings';
    if (currentPath === normalizePath(`${officePath}/settings/company`)) return 'company-settings';
    return null;
}

function resolveOfficeDetailId(pathname, routeSuffix) {
    const currentPath = normalizePath(pathname);
    const officePath = normalizePath(new URL(appConfig.office.path, window.location.origin).pathname);
    const basePath = normalizePath(`${officePath}${routeSuffix}`);
    if (!currentPath.startsWith(`${basePath}/`)) return null;
    const id = currentPath.slice(basePath.length).split('/').filter(Boolean)[0];

    return /^\d+$/.test(id || '') ? Number(id) : null;
}

function isOfficeRoute(pathname, routeSuffix) {
    const currentPath = normalizePath(pathname);
    const officePath = normalizePath(new URL(appConfig.office.path, window.location.origin).pathname);
    return currentPath === normalizePath(`${officePath}${routeSuffix}`);
}

function resolveOfficeMasterDetailId(pathname, resource) {
    if (!resource) return null;
    return resolveOfficeDetailId(pathname, `/master/${resource}`);
}

function Brand({ t, compact = false, branding }) {
    const businessName = branding?.name || t.brand;
    return (
        <div className={`brand ${compact ? 'compact' : ''}`}>
            <div className="brand-mark">
                {branding?.logo_url ? <img src={branding.logo_url} alt="" /> : <Droplets size={20} />}
            </div>
            <div>
                <strong>{businessName}</strong>
                {!compact && <span>{t.roleNav}</span>}
            </div>
        </div>
    );
}

function AppearanceControls({ t, user, onLogout, locale, setLocale, theme, setTheme, onProfile }) {
    return (
        <div className="topbar-actions">
            <button className="icon-button" type="button" aria-label={t.theme} title={t.theme} onClick={() => setTheme(theme === 'light' ? 'dark' : 'light')}>
                {theme === 'light' ? <Moon size={16} /> : <Sun size={16} />}
            </button>
            <ProfileMenu
                user={user}
                t={t}
                locale={locale}
                setLocale={setLocale}
                onLogout={onLogout}
                onProfile={onProfile}
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

function ProfileMenu({ user, t, locale, setLocale, onLogout, onProfile, compact = false }) {
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
                <span className="profile-avatar" aria-hidden="true">{user.profile_photo_url ? <img src={user.profile_photo_url} alt="" /> : initials}</span>
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
                        <span className="profile-avatar large" aria-hidden="true">{user.profile_photo_url ? <img src={user.profile_photo_url} alt="" /> : initials}</span>
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
                        <button className="profile-action" type="button" onClick={() => { setOpen(false); onProfile?.(); }}>
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
