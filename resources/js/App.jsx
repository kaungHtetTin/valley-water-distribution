import './bootstrap';
import { createPortal } from 'react-dom';
import { createRoot } from 'react-dom/client';
import {
    Building2,
    Bell,
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
    Search,
    Settings,
    SlidersHorizontal,
    ShoppingCart,
    Sun,
    Target,
    TriangleAlert,
    Truck,
    User,
    UserCheck,
    Users,
    WalletCards,
    X,
} from 'lucide-react';
import { lazy, Suspense, useEffect, useId, useMemo, useRef, useState } from 'react';
import { ShellPageActions, ShellPageSearch } from './components/ShellPageActions';

const lazyNamed = (loader, name) => lazy(() => loader().then((module) => ({ default: module[name] })));
const phaseOne = () => import('./phaseOne');
const phaseTwo = () => import('./phaseTwo');
const phaseThree = () => import('./phaseThree');
const phaseFour = () => import('./phaseFour');
const phaseFive = () => import('./phaseFive');
const phaseSix = () => import('./phaseSix');
const phaseSeven = () => import('./phaseSeven');
const phaseEight = () => import('./phaseEight');
const phaseNine = () => import('./phaseNine');
const phaseTen = () => import('./phaseTen');
const phaseEleven = () => import('./phaseEleven');
const phaseKpi = () => import('./phaseKpi');

const ClientMasterScreen = lazyNamed(phaseOne, 'ClientMasterScreen');
const BusinessSetupScreen = lazyNamed(phaseOne, 'BusinessSetupScreen');
const CompanySettingsScreen = lazyNamed(phaseOne, 'CompanySettingsScreen');
const DriverMasterScreen = lazyNamed(phaseOne, 'DriverMasterScreen');
const MasterDataWorkspace = lazyNamed(phaseOne, 'MasterDataWorkspace');
const SalesCustomerDetailPage = lazyNamed(phaseOne, 'SalesCustomerDetailPage');
const SalesMasterScreen = lazyNamed(phaseOne, 'SalesMasterScreen');
const SalesVisitsScreen = lazyNamed(phaseOne, 'SalesVisitsScreen');
const AttendanceLocationsScreen = lazyNamed(phaseTwo, 'AttendanceLocationsScreen');
const AttendanceRecordsScreen = lazyNamed(phaseTwo, 'AttendanceRecordsScreen');
const AttendanceSummaryScreen = lazyNamed(phaseTwo, 'AttendanceSummaryScreen');
const MobileAttendanceHistoryScreen = lazyNamed(phaseTwo, 'MobileAttendanceHistoryScreen');
const PublicAttendanceScreen = lazyNamed(phaseTwo, 'PublicAttendanceScreen');
const MobilePayrollHistoryScreen = lazyNamed(phaseThree, 'MobilePayrollHistoryScreen');
const PayrollAdjustmentsScreen = lazyNamed(phaseThree, 'PayrollAdjustmentsScreen');
const PayrollDraftsScreen = lazyNamed(phaseThree, 'PayrollDraftsScreen');
const SalaryHistoryScreen = lazyNamed(phaseThree, 'SalaryHistoryScreen');
const InvoicesScreen = lazyNamed(phaseFour, 'InvoicesScreen');
const MobileOrderDetailPage = lazyNamed(phaseFour, 'MobileOrderDetailPage');
const MobileOrdersScreen = lazyNamed(phaseFour, 'MobileOrdersScreen');
const OrdersScreen = lazyNamed(phaseFour, 'OrdersScreen');
const SalesReturnScreen = lazyNamed(phaseFour, 'SalesReturnScreen');
const ClosingStockScreen = lazyNamed(phaseFive, 'ClosingStockScreen');
const StockAdjustmentScreen = lazyNamed(phaseFive, 'StockAdjustmentScreen');
const StockOverviewScreen = lazyNamed(phaseFive, 'StockOverviewScreen');
const StockCardScreen = lazyNamed(phaseFive, 'StockCardScreen');
const StockReceiveScreen = lazyNamed(phaseFive, 'StockReceiveScreen');
const StockTransferScreen = lazyNamed(phaseFive, 'StockTransferScreen');
const DeliveryLiveMapScreen = lazyNamed(phaseSix, 'DeliveryLiveMapScreen');
const DeliveryPlanningScreen = lazyNamed(phaseSix, 'DeliveryPlanningScreen');
const MobileDriverExecutionScreen = lazyNamed(phaseSix, 'MobileDriverExecutionScreen');
const MobileDriverGpsScreen = lazyNamed(phaseSix, 'MobileDriverGpsScreen');
const TripEditPage = lazyNamed(phaseSix, 'TripEditPage');
const TripWizardPage = lazyNamed(phaseSix, 'TripWizardPage');
const FinanceBookScreen = lazyNamed(phaseSeven, 'FinanceBookScreen');
const FinanceCollectionsScreen = lazyNamed(phaseSeven, 'FinanceCollectionsScreen');
const FinanceExpensesScreen = lazyNamed(phaseSeven, 'FinanceExpensesScreen');
const FinanceReceivablesScreen = lazyNamed(phaseSeven, 'FinanceReceivablesScreen');
const MobileFinanceScreen = lazyNamed(phaseSeven, 'MobileFinanceScreen');
const ProfitLossScreen = lazyNamed(phaseSeven, 'ProfitLossScreen');
const SupplierLedgerScreen = lazyNamed(phaseSeven, 'SupplierLedgerScreen');
const MobileVehicleOperationsScreen = lazyNamed(phaseEight, 'MobileVehicleOperationsScreen');
const VehicleCostsScreen = lazyNamed(phaseEight, 'VehicleCostsScreen');
const VehicleMonthlyCostScreen = lazyNamed(phaseEight, 'VehicleMonthlyCostScreen');
const VehiclePerformanceScreen = lazyNamed(phaseEight, 'VehiclePerformanceScreen');
const VehicleRouteHistoryScreen = lazyNamed(phaseEight, 'VehicleRouteHistoryScreen');
const OperationsReportScreen = lazyNamed(phaseNine, 'OperationsReportScreen');
const MobileHomeDashboard = lazyNamed(phaseTen, 'MobileHomeDashboard');
const OfficeKpiDashboard = lazyNamed(phaseTen, 'OfficeKpiDashboard');
const UatReadinessScreen = lazyNamed(phaseEleven, 'UatReadinessScreen');
const KpiReviewsScreen = lazyNamed(phaseKpi, 'KpiReviewsScreen');
const KpiTargetsScreen = lazyNamed(phaseKpi, 'KpiTargetsScreen');
const MobileKpiScreen = lazyNamed(phaseKpi, 'MobileKpiScreen');
const KpiReportsScreen = lazyNamed(() => import('./phaseKpiReport'), 'KpiReportsScreen');
const ProfileSettingsScreen = lazyNamed(() => import('./ProfileSettings'), 'ProfileSettingsScreen');
const SupervisorHomeScreen = lazyNamed(() => import('./SupervisorApp'), 'SupervisorHomeScreen');
const SupervisorTeamScreen = lazyNamed(() => import('./SupervisorApp'), 'SupervisorTeamScreen');
const SupervisorRepresentativeDetailScreen = lazyNamed(() => import('./SupervisorApp'), 'SupervisorRepresentativeDetailScreen');

const copy = {
    en: {
        brand: 'Valley Water',
        office: 'Office App',
        client: 'Client App',
        sales: 'Sales App',
        supervisor: 'Supervisor App',
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
        clientHint: 'Customer app shell for orders with delivery tracking and account balance.',
        salesHint: 'Sales performance, customer management, and field orders.',
        supervisorHint: 'Mobile team oversight for sales supervisors.',
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
        emailHint: 'Enter your email address or phone number',
        emailAddress: 'Email address',
        emailAddressHint: 'Enter your account email address',
        password: 'Password',
        passwordHint: 'Enter your password',
        forgotPassword: 'Forgot password?',
        forgotPasswordTitle: 'Reset your password',
        forgotPasswordHint: 'Enter your account email and we will send a secure reset link.',
        sendResetLink: 'Send reset link',
        backToSignIn: 'Back to sign in',
        resetPasswordTitle: 'Choose a new password',
        resetPasswordHint: 'Enter and confirm the new password for your account.',
        newPassword: 'New password',
        resetPasswordAction: 'Reset password',
        status: 'Status',
        ready: 'Ready',
        currentRoute: 'Current route',
        assignedToday: 'Assigned Today',
        attendance: 'Attendance',
        nextAction: 'Next Action',
        home: 'Home',
        kpiReport: 'KPI Report',
        newOrder: 'New Order',
        orders: 'Orders',
        deliveries: 'Deliveries',
        profile: 'Profile',
        menu: 'Menu',
        accountSettings: 'Account & settings',
        profileSettings: 'Profile & settings',
        profileSettingsHint: 'Edit profile, photo and password',
        appearance: 'Appearance',
        light: 'Light',
        dark: 'Dark',
        lightTheme: 'Light theme',
        darkTheme: 'Dark theme',
        signOutDevice: 'Sign out of this device',
        moreOperations: 'More operations',
        close: 'Close',
        profileMenu: 'Profile menu',
        route: 'Route',
        customers: 'Customers',
        team: 'Team',
        customerVisits: 'Visits',
        collections: 'Collections',
        expenses: 'Expenses',
        ledger: 'Ledger',
        vehicle: 'Vehicle',
        tasks: 'Tasks',
        gps: 'GPS',
        history: 'History',
        load: 'Load',
        confirm: 'Confirm',
        clientWelcome: 'Morning delivery to Shwe Family Store is being prepared.',
        salesWelcome: 'Your sales performance report is ready.',
        driverWelcome: 'Warehouse load WY-204 is assigned for delivery.',
        officeApiNote: 'The API response format keeps errors predictable for all apps.',
        authLoading: 'Checking sign in',
        signOut: 'Sign out',
        signedInAs: 'Signed in as',
        account: 'Account',
        authRequired: 'Sign in to continue',
        createAccount: 'Create account',
        createNewAccount: 'Create new account',
        customerRegistration: 'Create your customer account',
        customerRegistrationHint: 'Enter your delivery contact details. Area and route will be assigned by office staff.',
        contactName: 'Contact name',
        shopName: 'Shop or business name',
        phone: 'Phone',
        deliveryAddress: 'Delivery address',
        confirmPassword: 'Confirm password',
        continueWithGoogle: 'Continue with Google',
        existingAccount: 'Already have an account?',
        newCustomer: 'New customer?',
        wrongApp: 'This user belongs to another app.',
    },
    my: {
        brand: 'Valley Water',
        customerVisits: 'Visits',
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
        salesHint: 'အရောင်းစွမ်းဆောင်ရည်၊ ဖောက်သည်စီမံမှုနှင့် အော်ဒါတင်ခြင်းအတွက် အရောင်း app။',
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
        emailHint: 'Email သို့မဟုတ် ဖုန်းနံပါတ် ထည့်ပါ',
        emailAddress: 'Email လိပ်စာ',
        emailAddressHint: 'အကောင့် Email လိပ်စာ ထည့်ပါ',
        password: 'Password',
        passwordHint: 'Password ထည့်ပါ',
        forgotPassword: 'စကားဝှက် မေ့နေပါသလား',
        forgotPasswordTitle: 'စကားဝှက် ပြန်လည်သတ်မှတ်ရန်',
        forgotPasswordHint: 'အကောင့် Email ကို ထည့်ပါ။ လုံခြုံသော ပြန်လည်သတ်မှတ်ရေး link ပို့ပေးပါမည်။',
        sendResetLink: 'ပြန်လည်သတ်မှတ်ရေး link ပို့ရန်',
        backToSignIn: 'အကောင့်ဝင်ရန် ပြန်သွားမည်',
        resetPasswordTitle: 'စကားဝှက်အသစ် သတ်မှတ်ရန်',
        resetPasswordHint: 'စကားဝှက်အသစ်ကို နှစ်ကြိမ်မှန်ကန်စွာ ထည့်ပါ။',
        newPassword: 'စကားဝှက်အသစ်',
        resetPasswordAction: 'စကားဝှက် ပြန်သတ်မှတ်ရန်',
        status: 'အခြေအနေ',
        ready: 'အသင့်',
        currentRoute: 'လက်ရှိ route',
        assignedToday: 'ယနေ့သတ်မှတ်ထားသည်',
        nextAction: 'နောက်လုပ်ဆောင်ရန်',
        home: 'မူလ',
        kpiReport: 'KPI အစီရင်ခံစာ',
        newOrder: 'အော်ဒါအသစ်',
        orders: 'အော်ဒါများ',
        deliveries: 'ပို့ဆောင်မှုများ',
        profile: 'ပရိုဖိုင်',
        profileMenu: 'Profile menu',
        route: 'Route',
        customers: 'ဖောက်သည်များ',
        collections: 'ငွေကောက်ခံမှုများ',
        tasks: 'တာဝန်များ',
        history: 'မှတ်တမ်း',
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
copy.my.supervisor = 'အရောင်းကြီးကြပ်သူအက်ပ်';
copy.my.supervisorHint = 'အရောင်းအဖွဲ့ကို ကြီးကြပ်ရန် မိုဘိုင်းအက်ပ်။';
copy.my.team = 'အဖွဲ့';
copy.my.attendance = 'တက်ရောက်မှု';
copy.my.menu = 'မီနူး';
copy.my.accountSettings = 'အကောင့်နှင့် ဆက်တင်များ';
copy.my.profileSettings = 'ပရိုဖိုင်နှင့် ဆက်တင်များ';
copy.my.profileSettingsHint = 'ပရိုဖိုင်၊ ဓာတ်ပုံနှင့် စကားဝှက် ပြင်ဆင်ရန်';
copy.my.appearance = 'အသွင်အပြင်';
copy.my.light = 'အလင်း';
copy.my.dark = 'အမှောင်';
copy.my.lightTheme = 'အလင်းရောင် အပြင်အဆင်';
copy.my.darkTheme = 'အမှောင် အပြင်အဆင်';
copy.my.signOut = 'အကောင့်မှ ထွက်ရန်';
copy.my.signOutDevice = 'ဤစက်မှ ထွက်ရန်';

const appConfig = {
    office: {
        id: 'office',
        path: runtimeRoute('office', '/office'),
        icon: Building2,
        accent: '#0b84a5',
    },
    client: {
        id: 'client',
        path: runtimeRoute('client', '/client'),
        icon: ShoppingCart,
        accent: '#168255',
    },
    sales: {
        id: 'sales',
        path: runtimeRoute('sales', '/sales'),
        icon: Users,
        accent: '#2874bc',
    },
    supervisor: {
        id: 'supervisor',
        path: runtimeRoute('supervisor', '/supervisor'),
        icon: UserCheck,
        accent: '#6d5bd0',
    },
    driver: {
        id: 'driver',
        path: runtimeRoute('driver', '/driver'),
        icon: Truck,
        accent: '#b77700',
    },
};

const appOrder = ['office', 'client', 'sales', 'supervisor', 'driver'];

const officeNavItems = [
    { id: 'uat-readiness', label: 'UAT & Security', myLabel: 'UAT နှင့် လုံခြုံရေး', icon: CheckCircle2, permission: 'office.uat.view', view: 'uat-readiness', path: '/uat' },
    { id: 'dashboard-kpis', label: 'KPI Dashboards', myLabel: 'KPI Dashboards', icon: CircleGauge, permission: 'office.dashboard.view', view: 'dashboard-kpis', path: '/dashboards/kpis' },
    { id: 'dashboard', label: 'Dashboard', myLabel: 'ဒက်ရှ်ဘုတ်', icon: LayoutDashboard, permission: 'office.dashboard.view' },
    { id: 'business-setup', label: 'Business Setup', myLabel: 'လုပ်ငန်းအခြေခံပြင်ဆင်မှု', icon: Building2, permission: 'office.master-data.view', view: 'business-setup', path: '/setup' },
    { id: 'areas', label: 'Areas', myLabel: 'ဧရိယာများ', icon: MapPinned, permission: 'office.master-data.view', resource: 'areas' },
    { id: 'routes', label: 'Routes', myLabel: 'Route များ', icon: MapPinned, permission: 'office.master-data.view', resource: 'routes' },
    { id: 'warehouses', label: 'Warehouses', myLabel: 'ဂိုဒေါင်များ', icon: Package, permission: 'office.master-data.view', resource: 'warehouses' },
    { id: 'brands', label: 'Brands', myLabel: 'Brand များ', icon: ShoppingCart, permission: 'office.master-data.view', resource: 'brands' },
    { id: 'products', label: 'Products', myLabel: 'ကုန်ပစ္စည်းများ', icon: Package, permission: 'office.master-data.view', resource: 'products' },
    { id: 'price-types', label: 'Price Types', myLabel: 'ဈေးနှုန်းအမျိုးအစား', icon: WalletCards, permission: 'office.master-data.view', resource: 'price-types' },
    { id: 'product-prices', label: 'Product Prices', myLabel: 'ကုန်ပစ္စည်းဈေးနှုန်း', icon: ReceiptText, permission: 'office.master-data.view', resource: 'product-prices' },
    { id: 'customers', label: 'Customers', myLabel: 'ဖောက်သည်များ', icon: User, permission: 'office.master-data.view', resource: 'customers' },
    { id: 'employees', label: 'Employees', myLabel: 'ဝန်ထမ်းများ', icon: Users, permission: 'office.master-data.view', resource: 'employees' },
    { id: 'suppliers', label: 'Suppliers', myLabel: 'ပေးသွင်းသူများ', icon: ClipboardList, permission: 'office.master-data.view', resource: 'suppliers' },
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
    { id: 'stock-overview', label: 'Stock Overview', myLabel: 'Stock Overview', icon: Package, permission: 'office.inventory.view', view: 'stock-overview', path: '/stock/overview' },
    { id: 'stock-card', label: 'Stock Card', myLabel: 'Stock Card', icon: ClipboardList, permission: 'office.inventory.view', view: 'stock-card', path: '/stock/card' },
    { id: 'delivery-planning', label: 'Trip Planning', myLabel: 'ပို့ဆောင်မှုစီစဉ်ခြင်း', icon: Truck, permission: 'office.deliveries.view', view: 'delivery-planning', path: '/deliveries' },
    { id: 'delivery-live-map', label: 'Driver Live Map', myLabel: 'ယာဉ်မောင်း တိုက်ရိုက်မြေပုံ', icon: MapPinned, permission: 'office.deliveries.view', view: 'delivery-live-map', path: '/deliveries/live-map' },
    { id: 'attendance-locations', label: 'QR Locations', myLabel: 'QR Locations', icon: QrCode, permission: 'office.attendance.view', view: 'attendance-locations', path: '/attendance/locations' },
    { id: 'attendance-records', label: 'Attendance Records', myLabel: 'Attendance Records', icon: CalendarDays, permission: 'office.attendance.view', view: 'attendance-records', path: '/attendance/records' },
    { id: 'attendance-summary', label: 'Attendance Summary', myLabel: 'Attendance Summary', icon: ClipboardList, permission: 'office.attendance.view', view: 'attendance-summary', path: '/attendance/summary' },
    { id: 'payroll-drafts', label: 'Payroll Drafts', myLabel: 'Payroll Drafts', icon: CreditCard, permission: 'office.payroll.view', view: 'payroll-drafts', path: '/payroll/drafts' },
    { id: 'payroll-adjustments', label: 'Adjustments', myLabel: 'Adjustments', icon: WalletCards, permission: 'office.payroll.view', view: 'payroll-adjustments', path: '/payroll/adjustments' },
    { id: 'salary-history', label: 'Salary History', myLabel: 'Salary History', icon: ReceiptText, permission: 'office.payroll.view', view: 'salary-history', path: '/payroll/salary-history' },
    { id: 'kpi-targets', label: 'KPI Targets', myLabel: 'KPI Targets', icon: Target, permission: 'office.payroll.view', view: 'kpi-targets', path: '/payroll/kpi-targets' },
    { id: 'kpi-reviews', label: 'KPI Reviews', myLabel: 'KPI Reviews', icon: CircleGauge, permission: 'office.payroll.view', view: 'kpi-reviews', path: '/payroll/kpi-reviews' },
    { id: 'kpi-reports', label: 'KPI Reports', myLabel: 'KPI Reports', icon: Rows3, permission: 'office.payroll.view', view: 'kpi-reports', path: '/payroll/kpi-reports' },
    { id: 'finance-collections', label: 'Payments', myLabel: 'ငွေပေးချေမှုများ', icon: WalletCards, permission: 'office.finance.view', view: 'finance-collections', path: '/finance/collections' },
    { id: 'finance-outdoor-collections', label: 'Outdoor Collections', myLabel: 'ပြင်ပ ငွေကောက်ခံမှုများ', icon: Users, permission: 'office.finance.view', view: 'finance-outdoor-collections', path: '/finance/outdoor-collections' },
    { id: 'finance-receivables', label: 'Customer Credit', myLabel: 'ဖောက်သည် အကြွေးစာရင်း', icon: WalletCards, permission: 'office.finance.view', view: 'finance-receivables', path: '/finance/receivables' },
    { id: 'finance-suppliers', label: 'Supplier Ledger', myLabel: 'ပေးသွင်းသူ စာရင်း', icon: ClipboardList, permission: 'office.finance.view', view: 'finance-suppliers', path: '/finance/suppliers' },
    { id: 'finance-books', label: 'Finance Books', myLabel: 'ငွေစာရင်းများ', icon: WalletCards, permission: 'office.finance.view', view: 'finance-books', path: '/finance/books' },
    { id: 'finance-expenses', label: 'Expenses', myLabel: 'Expenses', icon: ReceiptText, permission: 'office.finance.view', view: 'finance-expenses', path: '/finance/expenses' },
    { id: 'finance-profit-loss', label: 'Profit / Loss', myLabel: 'အမြတ် / အရှုံး', icon: Rows3, permission: 'office.finance.view', view: 'finance-profit-loss', path: '/finance/profit-loss' },
    { id: 'vehicle-costs', label: 'Vehicle Costs', myLabel: 'ယာဉ်ကုန်ကျစရိတ်', icon: Truck, permission: 'office.vehicle-costs.view', view: 'vehicle-costs', path: '/vehicle-costs' },
    { id: 'vehicle-monthly-cost', label: 'Monthly Cost', myLabel: 'လစဉ်ယာဉ်ကုန်ကျစရိတ်', icon: CreditCard, permission: 'office.vehicle-costs.view', view: 'vehicle-monthly-cost', path: '/vehicle-reports/monthly-cost' },
    { id: 'vehicle-route-history', label: 'Route History', myLabel: 'လမ်းကြောင်းမှတ်တမ်း', icon: MapPinned, permission: 'office.vehicle-costs.view', view: 'vehicle-route-history', path: '/vehicle-reports/route-history' },
    { id: 'vehicle-cost-per-km', label: 'Cost / KM', myLabel: 'တစ်ကီလိုမီတာကုန်ကျစရိတ်', icon: WalletCards, permission: 'office.vehicle-costs.view', view: 'vehicle-cost-per-km', path: '/vehicle-reports/cost-per-km' },
    { id: 'vehicle-performance', label: 'Performance', myLabel: 'ယာဉ်စွမ်းဆောင်ရည်', icon: Rows3, permission: 'office.vehicle-costs.view', view: 'vehicle-performance', path: '/vehicle-reports/performance' },
    { id: 'operations-report', label: 'Operations Report', myLabel: 'လုပ်ငန်းအစီရင်ခံစာ', icon: Rows3, permission: 'office.dashboard.view', view: 'operations-report', path: '/reports/operations' },
];

const officeRolePermissions = {
    customers: 'office.customers.view',
    employees: 'office.employees.view',
    suppliers: 'office.suppliers.view',
    roles: 'office.access.roles.manage',
    permissions: 'office.access.roles.manage',
    'kpi-targets': 'office.kpi.view',
    'kpi-reviews': 'office.kpi.view',
    'kpi-reports': 'office.kpi.view',
    'finance-collections': 'office.finance.collections.view',
    'finance-outdoor-collections': 'office.finance.collections.view',
    'finance-receivables': 'office.finance.receivables.view',
    'finance-suppliers': 'office.finance.suppliers.view',
    'finance-books': 'office.finance.books.view',
    'finance-expenses': 'office.finance.expenses.view',
    'finance-profit-loss': 'office.finance.profit-loss.view',
    'operations-report': 'office.reports.operations.view',
};

const officeNavGroups = [
    {
        id: 'overview',
        label: 'Overview',
        myLabel: 'အနှစ်ချုပ်',
        items: ['dashboard', 'dashboard-kpis', 'operations-report'],
    },
    {
        id: 'business-setup',
        label: 'Business Setup',
        myLabel: 'လုပ်ငန်းအခြေခံ',
        items: ['business-setup'],
    },
    {
        id: 'people-assets',
        label: 'People & Assets',
        myLabel: 'လူနှင့် ပိုင်ဆိုင်မှု',
        items: ['customers', 'suppliers', 'employees'],
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
        items: ['stock-receive', 'stock-transfer', 'stock-adjustments', 'closing-stock', 'stock-overview', 'stock-card'],
    },
    {
        id: 'delivery-operations',
        label: 'Delivery Operations',
        myLabel: 'ပို့ဆောင်ရေးလုပ်ငန်း',
        items: ['delivery-planning', 'delivery-live-map'],
    },
    {
        id: 'attendance',
        label: 'Attendance',
        myLabel: 'Attendance',
        items: ['attendance-records', 'attendance-summary'],
    },
    {
        id: 'payroll',
        label: 'Payroll',
        myLabel: 'Payroll',
        items: ['kpi-reviews', 'kpi-reports', 'payroll-drafts', 'payroll-adjustments', 'salary-history'],
    },
    {
        id: 'finance',
        label: 'Finance & Accounts',
        myLabel: 'ဘဏ္ဍာရေးနှင့် စာရင်းများ',
        items: ['finance-collections', 'finance-outdoor-collections', 'finance-receivables', 'finance-suppliers', 'finance-books', 'finance-expenses', 'finance-profit-loss'],
    },
    {
        id: 'vehicle-operations',
        label: 'Vehicle Operations',
        myLabel: 'ယာဉ်လုပ်ငန်း',
        items: ['vehicle-costs', 'vehicle-monthly-cost', 'vehicle-route-history', 'vehicle-cost-per-km', 'vehicle-performance'],
    },
    {
        id: 'launch-readiness',
        label: 'Launch Readiness',
        myLabel: 'စတင်အသုံးပြုရန် အသင့်',
        items: ['uat-readiness'],
    },
];

const mobileNav = {
    client: [
        { labelKey: 'home', view: 'home', icon: Home, permission: 'client.home.view' },
        { labelKey: 'orders', view: 'orders', icon: ReceiptText, permission: 'client.orders.view' },
        { labelKey: 'ledger', view: 'ledger', icon: WalletCards, permission: 'client.finance.view' },
    ],
    sales: [
        { labelKey: 'home', view: 'home', icon: Home, permission: 'sales.home.view' },
        { labelKey: 'orders', view: 'orders', icon: ReceiptText, permission: 'sales.orders.view' },
        { labelKey: 'newOrder', view: 'new-order', icon: ClipboardList, permission: 'sales.orders.create' },
        { labelKey: 'customers', view: 'customers', icon: Users, permission: 'sales.customers.view' },
        { labelKey: 'customerVisits', view: 'visits', icon: MapPinned, permission: 'sales.customers.view' },
        { labelKey: 'attendance', view: 'attendance', icon: CalendarDays, permission: 'sales.attendance.view' },
        { labelKey: 'kpiReport', view: 'kpi', icon: CircleGauge, permission: 'sales.payroll.view' },
        { labelKey: 'salary', view: 'salary', icon: CreditCard, permission: 'sales.payroll.view' },
        { labelKey: 'expenses', view: 'expenses', icon: CreditCard, permission: 'sales.expenses.view' },
    ],
    supervisor: [
        { labelKey: 'home', view: 'home', icon: Home, permission: 'supervisor.home.view' },
        { labelKey: 'team', view: 'team', icon: Users, permission: 'supervisor.team.view' },
        { labelKey: 'attendance', view: 'attendance', icon: CalendarDays, permission: 'supervisor.attendance.view' },
        { labelKey: 'kpiReport', view: 'kpi', icon: CircleGauge, permission: 'supervisor.kpi.view' },
    ],
    driver: [
        { labelKey: 'home', view: 'home', icon: Home, permission: 'driver.home.view' },
        { labelKey: 'attendance', view: 'attendance', icon: CalendarDays, permission: 'driver.attendance.view' },
        { labelKey: 'kpiReport', view: 'kpi', icon: CircleGauge, permission: 'driver.payroll.view' },
        { labelKey: 'salary', view: 'salary', icon: CreditCard, permission: 'driver.payroll.view' },
        { labelKey: 'vehicle', view: 'vehicle', icon: Truck, permission: 'driver.vehicle-costs.view' },
        { labelKey: 'tasks', view: 'tasks', icon: ClipboardList, permission: 'driver.load.view' },
        { labelKey: 'gps', view: 'gps', icon: MapPinned, permission: 'driver.route.update' },
        { labelKey: 'history', view: 'history', icon: CheckCircle2, permission: 'driver.confirm.view' },
        { labelKey: 'collections', view: 'collections', icon: WalletCards, permission: 'driver.collections.view' },
        { labelKey: 'expenses', view: 'expenses', icon: CreditCard, permission: 'driver.expenses.view' },
    ],
};

const metrics = [
    ['Apps Online', '5', 'Office, Client, Sales, Supervisor, Driver'],
    ['Demo Users', '6', 'Seeded role accounts'],
    ['Locales', 'EN / MY', 'Visible label switching'],
    ['API Base', 'Ready', '/api/phase-zero'],
];

const rows = [
    ['Office Staff', 'Office dashboard', 'Ready', 'Dashboard shell'],
    ['Customer', 'Client home', 'Ready', 'Mobile client shell'],
    ['Sales Representative', 'Sales KPI report', 'Ready', 'Performance dashboard'],
    ['Sales Supervisor', 'Supervisor team', 'Ready', 'Mobile team workspace'],
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
        hero: 'Sales KPI report',
        title: 'Monthly performance',
        meta: 'Orders, sales, and customer growth',
        welcomeKey: 'salesWelcome',
        action: 'View report',
        items: [
            ['Orders submitted', '7'],
            ['New customers', '3'],
            ['Monthly sales', '420,000 MMK'],
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
    const callbackAuthError = new URLSearchParams(window.location.search).get('auth_error') || '';
    const [pathname, setPathname] = useState(() => window.location.pathname);
    const [online, setOnline] = useState(() => window.navigator.onLine);

    useEffect(() => {
        const numericInput = (target) => target instanceof HTMLInputElement && target.type === 'number' ? target : null;
        const allowsDecimal = (input) => {
            const step = input.getAttribute('step');
            return step === 'any' || (step !== null && Number.isFinite(Number(step)) && !Number.isInteger(Number(step)));
        };
        const validValue = (input, value) => allowsDecimal(input) ? /^\d*(?:\.\d*)?$/.test(value) : /^\d*$/.test(value);

        const blockInvalidNumericKey = (event) => {
            const input = numericInput(event.target);
            if (!input || event.ctrlKey || event.metaKey || event.altKey) return;
            if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
                event.preventDefault();
                return;
            }
            if (event.key.length !== 1) return;
            if (/\d/.test(event.key)) return;
            if (allowsDecimal(input) && event.key === '.' && !input.value.includes('.')) return;
            event.preventDefault();
        };

        const blockInvalidNumericPaste = (event) => {
            const input = numericInput(event.target);
            if (!input) return;
            const pasted = event.clipboardData?.getData('text') ?? '';
            const start = input.selectionStart ?? input.value.length;
            const end = input.selectionEnd ?? start;
            const candidate = `${input.value.slice(0, start)}${pasted.trim()}${input.value.slice(end)}`;
            if (!validValue(input, candidate)) event.preventDefault();
        };

        const preventNumericWheelStep = (event) => {
            if (numericInput(event.target) && document.activeElement === event.target) event.preventDefault();
        };

        document.addEventListener('keydown', blockInvalidNumericKey, true);
        document.addEventListener('paste', blockInvalidNumericPaste, true);
        document.addEventListener('wheel', preventNumericWheelStep, { capture: true, passive: false });
        return () => {
            document.removeEventListener('keydown', blockInvalidNumericKey, true);
            document.removeEventListener('paste', blockInvalidNumericPaste, true);
            document.removeEventListener('wheel', preventNumericWheelStep, true);
        };
    }, []);
    const [theme, setTheme] = useState(() => window.localStorage.getItem('valley-theme') || 'light');
    const [locale, setLocale] = useState(() => window.localStorage.getItem('valley-locale') || 'en');
    const [auth, setAuth] = useState({ loading: true, user: null, errors: {}, message: callbackAuthError });
    const [branding, setBranding] = useState({ name: 'Valley Water', logo_url: null, primary_color: '#0b84a5', default_theme: 'light' });
    const t = { ...copy.en, ...copy[locale] };
    const activeApp = resolveAppFromPath(pathname);
    const attendanceToken = resolveAttendanceToken(pathname);
    const selectedApp = appConfig[activeApp];
    const passwordResetting = normalizePath(pathname).includes('/reset-password/');
    const rootStyle = useMemo(() => ({ '--color-primary': activeApp === 'office' ? branding.primary_color : selectedApp.accent }), [activeApp, branding.primary_color, selectedApp.accent]);

    useEffect(() => window.localStorage.setItem('valley-theme', theme), [theme]);
    useEffect(() => window.localStorage.setItem('valley-locale', locale), [locale]);
    useEffect(() => {
        const handlePopState = () => setPathname(window.location.pathname);
        window.addEventListener('popstate', handlePopState);
        return () => window.removeEventListener('popstate', handlePopState);
    }, []);

    useEffect(() => {
        const officePath = normalizePath(new URL(appConfig.office.path, window.location.origin).pathname);
        const legacyHistoryPath = normalizePath(`${officePath}/deliveries/history`);
        const currentPath = normalizePath(pathname);
        if (currentPath !== legacyHistoryPath && !currentPath.startsWith(`${legacyHistoryPath}/`)) return;

        const suffix = currentPath.slice(legacyHistoryPath.length);
        const destination = `${officePath}/deliveries${suffix}`;
        window.history.replaceState({}, '', `${destination}${window.location.search}${window.location.hash}`);
        setPathname(destination);
    }, [pathname]);

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
                    setAuth({ loading: false, user: currentUser, errors: {}, message: currentUser ? '' : callbackAuthError });
                }
            })
            .catch(() => {
                if (isMounted) {
                    setAuth({ loading: false, user: null, errors: {}, message: callbackAuthError });
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

    useEffect(() => {
        const sessionExpired = () => setAuth({
            loading: false, user: null, errors: {},
            message: 'Your session changed or expired. Please sign in again.',
        });
        window.addEventListener('valley-session-expired', sessionExpired);
        return () => window.removeEventListener('valley-session-expired', sessionExpired);
    }, []);

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

    const handleRegister = async (payload) => {
        setAuth((current) => ({ ...current, errors: {}, message: '', submitting: true }));

        try {
            const response = await window.axios.post(authRoute('register', '/api/auth/register'), payload);
            setAuth({ loading: false, user: response.data.data.user, errors: {}, message: '', submitting: false });
            window.location.assign(response.data.data.redirect_to || appConfig.client.path);
        } catch (error) {
            setAuth({
                loading: false,
                user: null,
                errors: error.response?.data?.errors || {},
                message: error.response?.data?.message || 'Unable to create the customer account.',
                submitting: false,
            });
        }
    };

    const handleForgotPassword = async (payload) => {
        setAuth((current) => ({ ...current, errors: {}, message: '', submitting: true }));

        try {
            const response = await window.axios.post(authRoute('forgotPassword', '/api/auth/forgot-password'), payload);
            setAuth({ loading: false, user: null, errors: {}, message: response.data.message, submitting: false, success: true });
        } catch (error) {
            setAuth({
                loading: false,
                user: null,
                errors: error.response?.data?.errors || {},
                message: error.response?.data?.message || 'Unable to send the password reset link.',
                submitting: false,
            });
        }
    };

    const handleResetPassword = async (payload) => {
        setAuth((current) => ({ ...current, errors: {}, message: '', submitting: true }));

        try {
            const response = await window.axios.post(authRoute('resetPassword', '/api/auth/reset-password'), payload);
            window.location.assign(response.data.data.redirect_to || appConfig.office.path);
        } catch (error) {
            setAuth({
                loading: false,
                user: null,
                errors: error.response?.data?.errors || {},
                message: error.response?.data?.message || 'Unable to reset the password.',
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
            ) : !auth.user || passwordResetting ? (
                <AuthScreen
                    app={selectedApp}
                    t={t}
                    locale={locale}
                    setLocale={handleLocaleChange}
                    theme={theme}
                    setTheme={setTheme}
                    auth={auth}
                    onLogin={handleLogin}
                    onRegister={handleRegister}
                    onForgotPassword={handleForgotPassword}
                    onResetPassword={handleResetPassword}
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
            items: group.items.map((id) => officeNavItems.find((item) => item.id === id)).filter((item) => item && (hasPermission(user, officeRolePermissions[item.id] || item.permission) || (['Owner', 'Office Staff'].includes(user.role) && hasPermission(user, item.permission) && !['roles', 'permissions', 'employees', 'suppliers', 'operations-report'].includes(item.id)))),
        }))
        .filter((group) => group.items.length > 0);
    const visibleOfficeNav = visibleOfficeNavGroups.flatMap((group) => group.items);
    const activeResource = resolveOfficeResource(pathname);
    const activeView = resolveOfficeView(pathname);
    const canManageMasterData = ['employees', 'suppliers', 'roles', 'permissions'].includes(activeResource)
        ? hasPermission(user, activeResource === 'employees' ? 'office.employees.manage' : activeResource === 'suppliers' ? 'office.suppliers.manage' : 'office.access.roles.manage')
        : hasPermission(user, 'office.master-data.manage');
    const canManageAttendance = hasPermission(user, 'office.attendance.manage');
    const canManagePayroll = hasPermission(user, 'office.payroll.manage');
    const canPreparePayroll = canManagePayroll || hasPermission(user, 'office.payroll.drafts.prepare');
    const canApprovePayroll = canManagePayroll || hasPermission(user, 'office.payroll.drafts.approve');
    const canPayPayroll = canManagePayroll || hasPermission(user, 'office.payroll.drafts.pay');
    const canManageAdjustments = canManagePayroll || hasPermission(user, 'office.payroll.adjustments.manage');
    const canManageKpi = canManagePayroll || hasPermission(user, 'office.kpi.manage');
    const canManageOrders = hasPermission(user, 'office.orders.manage');
    const canManageInvoices = hasPermission(user, 'office.invoices.manage');
    const canManageInventory = hasPermission(user, 'office.inventory.manage');
    const canManageDeliveries = hasPermission(user, 'office.deliveries.manage');
    const canManageFinance = hasPermission(user, 'office.finance.manage');
    const canCreateCollections = canManageFinance || hasPermission(user, 'office.finance.collections.create');
    const canReviewCollections = canManageFinance || hasPermission(user, 'office.finance.collections.review');
    const canReceiveCollections = canManageFinance || hasPermission(user, 'office.finance.collections.receive');
    const canCreateExpenses = canManageFinance || hasPermission(user, 'office.finance.expenses.create');
    const canReviewExpenses = canManageFinance || hasPermission(user, 'office.finance.expenses.review');
    const canPaySuppliers = canManageFinance || hasPermission(user, 'office.finance.suppliers.pay');
    const canAdjustSuppliers = canManageFinance || hasPermission(user, 'office.finance.suppliers.adjust');
    const canManageVehicleCosts = hasPermission(user, 'office.vehicle-costs.manage');
    const canManageUat = hasPermission(user, 'office.uat.manage');

    useEffect(() => {
        if (activeResource || activeView || hasPermission(user, 'office.dashboard.view')) return;
        const first = visibleOfficeNav[0];
        if (!first || first.id === 'dashboard') return;
        navigate(first.resource ? `${appConfig.office.path}/master/${first.resource}` : `${appConfig.office.path}${first.path || ''}`);
    }, [activeResource, activeView, pathname, user]);

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
                    <div id="shell-back-slot" className="shell-back-slot" aria-live="polite" />
                    <button className="icon-button mobile-only" type="button" aria-label="Open navigation" title="Open navigation" onClick={() => setDrawerOpen(true)}>
                        <Menu size={18} />
                    </button>
                    <div id="shell-page-search" className="shell-page-search" aria-live="polite" />
                    <div id="shell-page-actions" className="shell-page-actions" aria-live="polite" />
                    <ShellFilterControl pathname={pathname} />
                    <AppearanceControls
                        appId="office"
                        t={t}
                        user={user}
                        onLogout={onLogout}
                        locale={locale}
                        setLocale={setLocale}
                        theme={theme}
                        setTheme={setTheme}
                        onProfile={() => navigate(`${appConfig.office.path}/profile`)}
                        navigate={navigate}
                        pathname={pathname}
                    />
                </header>

                {activeResource ? (
                    <MasterDataWorkspace resourceKey={activeResource} locale={locale} canManage={canManageMasterData} detailId={resolveOfficeMasterDetailId(pathname, activeResource)} onNavigate={navigate} />
                ) : activeView === 'profile-settings' ? (
                    <ProfileSettingsScreen user={user} onUserUpdated={onUserUpdated} />
                ) : activeView === 'dashboard-kpis' ? (
                    <OfficeKpiDashboard kind={hasPermission(user, 'office.finance.profit-loss.view') ? resolveKpiDashboardKind(pathname) : user.role === 'Sales Supervisor' ? 'sales' : 'stock'} canViewOperationsReport={hasPermission(user, 'office.reports.operations.view')} locale={locale} onNavigate={navigate} />
                ) : activeView === 'uat-readiness' ? (
                    <UatReadinessScreen locale={locale} canManage={canManageUat} />
                ) : activeView === 'business-setup' ? (
                    <BusinessSetupScreen
                        locale={locale}
                        canManage={canManageMasterData}
                        section={resolveBusinessSetupSection(pathname)}
                        onNavigate={navigate}
                        onBrandingUpdated={onBrandingUpdated}
                        qrLocationsContent={<AttendanceLocationsScreen
                            locale={locale}
                            canManage={canManageAttendance}
                            detailId={resolveOfficeDetailId(pathname, '/setup/qr-locations')}
                            onNavigate={navigate}
                            embedded
                            basePath={`${appConfig.office.path}/setup/qr-locations`}
                        />}
                        kpiTargetsContent={<KpiTargetsScreen locale={locale} canManage={canManageKpi} embedded />}
                    />
                ) : activeView === 'company-settings' ? (
                    <CompanySettingsScreen locale={locale} canManage={canManageMasterData} onBrandingUpdated={onBrandingUpdated} />
                ) : activeView === 'orders' ? (
                    <OrdersScreen
                        locale={locale}
                        canManage={canManageOrders}
                        creating={isOfficeRoute(pathname, '/orders/new')}
                        editId={normalizePath(pathname).endsWith('/edit') ? resolveOfficeDetailId(pathname, '/orders') : null}
                        detailId={normalizePath(pathname).endsWith('/edit') ? null : resolveOfficeDetailId(pathname, '/orders')}
                        onNavigate={navigate}
                    />
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
                ) : activeView === 'stock-overview' ? (
                    <StockOverviewScreen view={resolveStockOverviewView(pathname)} locale={locale} onNavigate={navigate} />
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
                ) : activeView === 'attendance-locations' ? (
                    <AttendanceLocationsScreen locale={locale} canManage={canManageAttendance} detailId={resolveOfficeDetailId(pathname, '/attendance/locations')} onNavigate={navigate} />
                ) : activeView === 'attendance-records' ? (
                    <AttendanceRecordsScreen locale={locale} user={user} detailId={resolveOfficeDetailId(pathname, '/attendance/records')} onNavigate={navigate} />
                ) : activeView === 'attendance-summary' ? (
                    <AttendanceSummaryScreen locale={locale} />
                ) : activeView === 'payroll-drafts' ? (
                    <PayrollDraftsScreen locale={locale} canPrepare={canPreparePayroll} canApprove={canApprovePayroll} canPay={canPayPayroll} detailId={resolveOfficeDetailId(pathname, '/payroll/drafts')} onNavigate={navigate} />
                ) : activeView === 'payroll-adjustments' ? (
                    <PayrollAdjustmentsScreen locale={locale} canManage={canManageAdjustments} />
                ) : activeView === 'salary-history' ? (
                    <SalaryHistoryScreen locale={locale} detailId={resolveOfficeDetailId(pathname, '/payroll/salary-history')} onNavigate={navigate} />
                ) : activeView === 'kpi-targets' ? (
                    <KpiTargetsScreen locale={locale} canManage={canManageKpi} />
                ) : activeView === 'kpi-reviews' ? (
                    <KpiReviewsScreen locale={locale} canManage={canManageKpi} canApprove={canManagePayroll || hasPermission(user, 'office.kpi.approve')} />
                ) : activeView === 'kpi-reports' ? (
                    <KpiReportsScreen locale={locale} />
                ) : activeView === 'finance-collections' ? (
                    <FinanceCollectionsScreen canCreate={canCreateCollections} canReview={canReviewCollections} locale={locale} />
                ) : activeView === 'finance-outdoor-collections' ? (
                    <FinanceCollectionsScreen outdoor canCreate={canCreateCollections} canReview={canReviewCollections} canReceive={canReceiveCollections} locale={locale} />
                ) : activeView === 'finance-receivables' ? (
                    <FinanceReceivablesScreen locale={locale} canManage={canCreateCollections} detailId={resolveOfficeDetailId(pathname, '/finance/receivables')} onNavigate={navigate} />
                ) : activeView === 'finance-suppliers' ? (
                    <SupplierLedgerScreen canPay={canPaySuppliers} canAdjust={canAdjustSuppliers} canViewSuppliers={hasPermission(user, 'office.suppliers.view')} locale={locale} onNavigate={navigate} />
                ) : activeView === 'finance-books' ? (
                    <FinanceBookScreen book={resolveFinanceBook(pathname)} locale={locale} onNavigate={navigate} />
                ) : activeView === 'finance-expenses' ? (
                    <FinanceExpensesScreen expenseType={resolveFinanceExpenseType(pathname)} canCreate={canCreateExpenses} canReview={canReviewExpenses} locale={locale} onNavigate={navigate} />
                ) : activeView === 'finance-profit-loss' ? (
                    <ProfitLossScreen locale={locale} />
                ) : activeView === 'vehicle-costs' ? (
                    <VehicleCostsScreen type={resolveVehicleCostCategory(pathname)} locale={locale} canManage={canManageVehicleCosts} onNavigate={navigate} />
                ) : activeView === 'vehicle-monthly-cost' ? (
                    <VehicleMonthlyCostScreen locale={locale} />
                ) : activeView === 'vehicle-route-history' ? (
                    <VehicleRouteHistoryScreen locale={locale} canManage={canManageVehicleCosts} />
                ) : activeView === 'vehicle-cost-per-km' ? (
                    <VehiclePerformanceScreen locale={locale} costOnly />
                ) : activeView === 'vehicle-performance' ? (
                    <VehiclePerformanceScreen locale={locale} />
                ) : activeView === 'operations-report' ? (
                    <OperationsReportScreen locale={locale} />
                ) : (
                    <OfficeKpiDashboard kind={hasPermission(user, 'office.finance.profit-loss.view') ? 'owner' : user.role === 'Sales Supervisor' ? 'sales' : 'stock'} canViewOperationsReport={hasPermission(user, 'office.reports.operations.view')} locale={locale} onNavigate={navigate} />
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
    '.trip-order-toolbar',
    '.report-filter-panel > form',
    '.kpi-report-filters',
    '.mobile-kpi-toolbar',
    '.status-tabs',
].join(', ');

function ShellFilterControl({ pathname }) {
    const [available, setAvailable] = useState(false);
    const [open, setOpen] = useState(false);
    const [refreshSources, setRefreshSources] = useState([]);
    const [searchSources, setSearchSources] = useState([]);
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
            const nextRefreshSources = [];
            const nextSearchSources = [];
            let filterIndex = 0;
            workspace.querySelectorAll('.master-heading > button, .mobile-master-heading > button').forEach((button) => {
                if (!button.querySelector('.lucide-plus')) return;
                const label = button.textContent.trim() || button.getAttribute('aria-label') || 'Add';
                if (!button.getAttribute('aria-label')) button.setAttribute('aria-label', label);
                if (!button.getAttribute('title')) button.setAttribute('title', label);
            });
            toolbars.forEach((toolbar) => {
                toolbar.querySelectorAll([
                    'input[type="search"]',
                    '.master-search input',
                    '.mobile-search input',
                    '.transfer-filter-search input',
                    'input[aria-label*="search" i]',
                    'input[placeholder*="search" i]',
                ].join(', ')).forEach((input) => {
                    if (nextSearchSources.includes(input)) return;
                    const container = input.closest('.transfer-filter-search-field, .master-search, .mobile-search') || input;
                    container.dataset.shellSearchSource = 'true';
                    nextSearchSources.push(input);
                });
                toolbar.querySelectorAll('button').forEach((button) => {
                    const label = [button.getAttribute('aria-label'), button.getAttribute('title'), button.textContent]
                        .filter(Boolean)
                        .join(' ')
                        .trim();
                    const normalizedLabel = label.toLowerCase();
                    const hasRefreshIcon = Boolean(button.querySelector('.lucide-refresh-cw'));
                    const isFilterSubmit = /\b(apply|run report|search)\b/i.test(normalizedLabel);
                    const isRefresh = !isFilterSubmit && (hasRefreshIcon || /\b(refresh|reload|retry)\b/i.test(normalizedLabel));

                    if (!isRefresh || nextRefreshSources.includes(button)) return;
                    button.dataset.shellRefreshSource = 'true';
                    nextRefreshSources.push(button);
                });

                const remainingControls = [...toolbar.querySelectorAll('input, select, textarea, button')].filter((control) => (
                    !control.closest('[data-shell-search-source="true"]')
                    && !control.matches('[data-shell-refresh-source="true"]')
                ));

                if (remainingControls.length > 0) {
                    filterIndex += 1;
                    toolbar.dataset.shellFilter = 'true';
                    delete toolbar.dataset.shellToolbarEmpty;
                    toolbar.id = `shell-filter-drawer-${filterIndex}`;
                    toolbar.setAttribute('aria-hidden', open ? 'false' : 'true');
                    toolbar.inert = !open;
                } else {
                    delete toolbar.dataset.shellFilter;
                    toolbar.dataset.shellToolbarEmpty = 'true';
                    if (toolbar.id.startsWith('shell-filter-drawer-')) toolbar.removeAttribute('id');
                    toolbar.removeAttribute('aria-hidden');
                    toolbar.inert = false;
                }
            });
            setSearchSources((current) => (
                current.length === nextSearchSources.length && current.every((source, index) => source === nextSearchSources[index])
                    ? current
                    : nextSearchSources
            ));
            setRefreshSources((current) => (
                current.length === nextRefreshSources.length && current.every((source, index) => source === nextRefreshSources[index])
                    ? current
                    : nextRefreshSources
            ));
            const filterToolbars = toolbars.filter((toolbar) => toolbar.dataset.shellFilter === 'true');
            setAvailable(filterToolbars.length > 0);
            if (filterToolbars.length === 0) setOpen(false);
        };

        sync();
        const observer = new MutationObserver(sync);
        observer.observe(workspace, { childList: true, subtree: true });
        return () => {
            observer.disconnect();
            workspace.querySelectorAll('[data-shell-refresh-source="true"]').forEach((button) => delete button.dataset.shellRefreshSource);
            workspace.querySelectorAll('[data-shell-search-source="true"]').forEach((container) => delete container.dataset.shellSearchSource);
            workspace.querySelectorAll('[data-shell-toolbar-empty="true"]').forEach((toolbar) => delete toolbar.dataset.shellToolbarEmpty);
        };
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

    return (
        <>
            {searchSources.length > 0 && (
                <ShellPageSearch>
                    <ShellSearchControl source={searchSources[0]} pathname={pathname} />
                </ShellPageSearch>
            )}
            {refreshSources.length > 0 && (
                <ShellPageActions className="shell-filter-refresh-actions">
                    {refreshSources.map((source, index) => {
                        const label = source.getAttribute('aria-label') || source.getAttribute('title') || source.textContent.trim() || 'Refresh';
                        return (
                            <button
                                className="icon-button"
                                type="button"
                                aria-label={label}
                                title={label}
                                disabled={source.disabled}
                                key={`${pathname}-refresh-${index}`}
                                onClick={() => source.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }))}
                            >
                                <RotateCcw size={16} />
                            </button>
                        );
                    })}
                </ShellPageActions>
            )}
            {available && <button ref={triggerRef} className={`icon-button shell-filter-trigger ${open ? 'is-active' : ''}`} type="button" aria-label="Filters" title="Filters" aria-expanded={open} aria-controls="shell-filter-drawer-1" onPointerDown={(event) => { if (event.currentTarget.closest('.mobile-app-topbar')?.querySelector('.shell-search-control:focus-within')) event.preventDefault(); }} onClick={() => setOpen((value) => !value)}>
                <ListFilter size={16} />
            </button>}
            {available && document.querySelector('.app-root') && createPortal(<>
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
            </>, document.querySelector('.app-root'))}
        </>
    );
}

function ShellSearchControl({ source, pathname }) {
    const [value, setValue] = useState(() => source?.value || '');
    const [disabled, setDisabled] = useState(() => Boolean(source?.disabled));

    useEffect(() => {
        if (!source) return undefined;
        const sync = () => {
            setValue((current) => current === source.value ? current : source.value);
            setDisabled(Boolean(source.disabled));
        };
        sync();
        source.addEventListener('input', sync);
        source.addEventListener('change', sync);
        const interval = window.setInterval(sync, 200);
        return () => {
            source.removeEventListener('input', sync);
            source.removeEventListener('change', sync);
            window.clearInterval(interval);
        };
    }, [source, pathname]);

    const update = (nextValue) => {
        setValue(nextValue);
        const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value')?.set;
        setter?.call(source, nextValue);
        source.dispatchEvent(new Event('input', { bubbles: true }));
    };

    const label = source.getAttribute('aria-label') || source.placeholder || 'Search';

    return (
        <label className="shell-search-control">
            <Search size={15} aria-hidden="true" />
            <input
                type="search"
                aria-label={label}
                placeholder={source.placeholder || label}
                value={value}
                disabled={disabled}
                onChange={(event) => update(event.target.value)}
                onKeyDown={(event) => {
                    if (event.key === 'Enter' && source.form) {
                        event.preventDefault();
                        source.form.requestSubmit();
                    }
                }}
            />
        </label>
    );
}

function MobileApp({ app, t, user, onLogout, locale, setLocale, theme, setTheme, pathname, navigate, onUserUpdated }) {
    const visibleNav = mobileNav[app.id].filter((item) => hasPermission(user, item.permission));
    const activeView = resolveMobileView(app.id, pathname);
    const compactPrimaryViews = {
        client: ['home', 'orders'],
        sales: ['home', 'orders', 'new-order', 'customers'],
        supervisor: ['home', 'team', 'attendance', 'kpi'],
        driver: ['home', 'tasks', 'gps', 'history'],
    };
    const supportsMenuNav = Object.hasOwn(compactPrimaryViews, app.id);
    const primaryViews = compactPrimaryViews[app.id] || [];
    const menuNav = supportsMenuNav ? visibleNav.filter((item) => !primaryViews.includes(item.view)) : [];
    const usesMenuNav = supportsMenuNav;
    const primaryNav = supportsMenuNav ? visibleNav.filter((item) => primaryViews.includes(item.view)) : visibleNav;
    const menuActive = activeView === 'menu' || activeView === 'account' || menuNav.some((item) => item.view === activeView);
    const mobileOrderId = ['client', 'sales'].includes(app.id) ? resolveMobileOrderId(app.path, pathname) : null;
    const salesCustomerId = app.id === 'sales' ? resolveSalesCustomerId(app.path, pathname) : null;
    const supervisorMemberId = app.id === 'supervisor' ? resolveSupervisorMemberId(app.path, pathname) : null;
    const orderActionParams = ['client', 'sales'].includes(app.id) && ['orders', 'new-order'].includes(activeView) ? new URLSearchParams(window.location.search) : null;

    return (
        <main className="mobile-app-shell">
            <header className="mobile-app-topbar">
                <div className="mobile-app-leading">
                    <div id="shell-back-slot" className="shell-back-slot" aria-live="polite" />
                    <Brand t={t} compact />
                </div>
                <div id="shell-page-search" className="shell-page-search" aria-live="polite" />
                <div className="mobile-top-actions">
                    <div id="shell-page-actions" className="shell-page-actions" aria-live="polite" />
                    <ShellFilterControl pathname={pathname} />
                    <ActionAlerts appId={app.id} user={user} navigate={navigate} pathname={pathname} />
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
                {['client', 'sales'].includes(app.id) && ['orders', 'new-order'].includes(activeView) && (mobileOrderId
                    ? <MobileOrderDetailPage appId={app.id} orderId={mobileOrderId} locale={locale} onBack={() => navigate(`${app.path}/orders`)} onEdit={(orderId) => navigate(`${app.path}/new-order?edit=${orderId}`)} onRepeat={(orderId) => navigate(`${app.path}/${app.id === 'sales' ? 'new-order' : 'orders'}?repeat=${orderId}`)} />
                    : <MobileOrdersScreen
                        appId={app.id}
                        locale={locale}
                        initialMode={activeView === 'new-order' ? 'form' : 'list'}
                        editOrderId={orderActionParams?.get('edit')}
                        repeatOrderId={orderActionParams?.get('repeat')}
                        onShowForm={app.id === 'sales' ? () => navigate(`${app.path}/new-order`) : undefined}
                        onShowList={app.id === 'sales' ? () => navigate(`${app.path}/orders`) : undefined}
                        onViewOrder={(orderId) => navigate(`${app.path}/orders/${orderId}`)}
                    />)}
                {app.id === 'client' && activeView === 'profile' && <ClientMasterScreen locale={locale} />}
                {app.id === 'sales' && activeView === 'customers' && (salesCustomerId
                    ? <SalesCustomerDetailPage customerId={salesCustomerId} locale={locale} onBack={() => navigate(`${app.path}/customers`)} onViewOrder={(orderId) => navigate(`${app.path}/orders/${orderId}`)} />
                    : <SalesMasterScreen locale={locale} onViewCustomer={(customerId) => navigate(`${app.path}/customers/${customerId}`)} />)}
                {app.id === 'sales' && activeView === 'visits' && <SalesVisitsScreen locale={locale} onViewCustomer={(customerId) => navigate(`${app.path}/customers/${customerId}`)} />}
                {usesMenuNav && activeView === 'menu' && <MobileMenuScreen app={app} items={menuNav} t={t} navigate={navigate} user={user} onLogout={onLogout} locale={locale} setLocale={setLocale} theme={theme} setTheme={setTheme} />}
                {activeView === 'account' && <ProfileSettingsScreen user={user} onUserUpdated={onUserUpdated} />}
                {['sales', 'supervisor', 'driver'].includes(app.id) && activeView === 'attendance' && <MobileAttendanceHistoryScreen locale={locale} />}
                {['sales', 'supervisor', 'driver'].includes(app.id) && activeView === 'kpi' && <MobileKpiScreen locale={locale} />}
                {['sales', 'driver'].includes(app.id) && activeView === 'salary' && <MobilePayrollHistoryScreen locale={locale} />}
                {app.id === 'supervisor' && activeView === 'team' && (supervisorMemberId
                    ? <SupervisorRepresentativeDetailScreen memberId={supervisorMemberId} locale={locale} onBack={() => navigate(`${app.path}/team`)} />
                    : <SupervisorTeamScreen locale={locale} onViewMember={(memberId) => navigate(`${app.path}/team/${memberId}`)} />)}
                {app.id === 'driver' && activeView === 'profile' && <DriverMasterScreen locale={locale} />}
                {app.id === 'driver' && activeView === 'tasks' && <MobileDriverExecutionScreen mode="tasks" locale={locale} />}
                {app.id === 'driver' && activeView === 'gps' && <MobileDriverGpsScreen locale={locale} />}
                {app.id === 'driver' && activeView === 'history' && <MobileDriverExecutionScreen mode="history" locale={locale} />}
                {app.id === 'client' && activeView === 'ledger' && <MobileFinanceScreen appId={app.id} mode="ledger" locale={locale} />}
                {app.id === 'driver' && activeView === 'collections' && <MobileFinanceScreen appId={app.id} mode="collections" locale={locale} />}
                {['sales', 'driver'].includes(app.id) && activeView === 'expenses' && <MobileFinanceScreen appId={app.id} mode="expenses" locale={locale} />}
                {app.id === 'driver' && activeView === 'vehicle' && <MobileVehicleOperationsScreen locale={locale} />}
                {app.id === 'supervisor' && activeView === 'home' && <SupervisorHomeScreen locale={locale} onViewTeam={() => navigate(`${app.path}/team`)} onViewAttendance={() => navigate(`${app.path}/attendance`)} onViewKpi={() => navigate(`${app.path}/kpi`)} />}
                {app.id !== 'supervisor' && activeView === 'home' && <MobileHomeDashboard
                    appId={app.id}
                    locale={locale}
                    quickLinks={[]}
                    onNavigate={(event, href) => navigateAppPage(event, href, navigate)}
                    onViewOrders={() => navigate(`${app.path}/orders`)}
                    onViewCustomer={(customerId) => navigate(`${app.path}/customers/${customerId}`)}
                    onViewTasks={() => navigate(`${app.path}/tasks`)}
                    onViewGps={() => navigate(`${app.path}/gps`)}
                    onViewAttendance={() => navigate(`${app.path}/attendance`)}
                    onViewKpi={() => navigate(`${app.path}/kpi`)}
                />}
                {!((activeView === 'home') || ['account', 'menu'].includes(activeView) || (app.id === 'client' && ['orders', 'profile', 'ledger'].includes(activeView)) || (app.id === 'sales' && ['orders', 'new-order', 'customers', 'visits', 'attendance', 'kpi', 'salary', 'expenses'].includes(activeView)) || (app.id === 'supervisor' && ['team', 'attendance', 'kpi'].includes(activeView)) || (app.id === 'driver' && ['profile', 'tasks', 'gps', 'history', 'attendance', 'kpi', 'salary', 'collections', 'expenses', 'vehicle'].includes(activeView))) && (
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

function MobileMenuScreen({ app, items, t, navigate, user, onLogout, locale, setLocale, theme, setTheme }) {
    return (
        <div className="mobile-master-stack mobile-menu-page">
            <div className="mobile-master-heading">
                <div><p className="eyebrow">{t[app.id]}</p><h1>{t.menu}</h1><span className="muted">{user?.name || t.moreOperations}</span></div>
            </div>
            {items.length > 0 && <>
                <p className="mobile-menu-section-label">Operations</p>
                <nav className="mobile-master-section mobile-menu-list" aria-label={t.moreOperations}>
                    {items.map(({ labelKey, view, icon: Icon }) => (
                        <a href={`${app.path}/${view}`} onClick={(event) => navigateAppPage(event, `${app.path}/${view}`, navigate)} key={labelKey}>
                            <span><Icon size={18} /></span><strong>{t[labelKey]}</strong><ChevronRight size={17} />
                        </a>
                    ))}
                </nav>
            </>}
            <p className="mobile-menu-section-label">{t.accountSettings}</p>
            <nav className="mobile-master-section mobile-menu-list mobile-settings-list" aria-label={t.accountSettings}>
                <a href={`${app.path}/account`} onClick={(event) => navigateAppPage(event, `${app.path}/account`, navigate)}>
                    <span><Settings size={18} /></span><strong>{t.profileSettings}<small>{t.profileSettingsHint}</small></strong><ChevronRight size={17} />
                </a>
                <button type="button" onClick={() => setLocale(locale === 'en' ? 'my' : 'en')}>
                    <span><Languages size={18} /></span><strong>{t.language}<small>{locale === 'en' ? 'English' : 'မြန်မာ'}</small></strong><span className="mobile-menu-value">{locale === 'en' ? 'EN' : 'MY'}</span>
                </button>
                <button type="button" onClick={() => setTheme(theme === 'light' ? 'dark' : 'light')}>
                    <span>{theme === 'light' ? <Moon size={18} /> : <Sun size={18} />}</span><strong>{t.appearance}<small>{theme === 'light' ? t.lightTheme : t.darkTheme}</small></strong><span className="mobile-menu-value">{theme === 'light' ? t.light : t.dark}</span>
                </button>
                <button className="mobile-menu-signout" type="button" onClick={onLogout}>
                    <span><LogOut size={18} /></span><strong>{t.signOut}<small>{t.signOutDevice}</small></strong><ChevronRight size={17} />
                </button>
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

function resolveSupervisorMemberId(appPath, pathname) {
    const currentPath = normalizePath(pathname);
    const rootPath = normalizePath(new URL(appPath, window.location.origin).pathname);
    const segments = currentPath.slice(rootPath.length).split('/').filter(Boolean);
    return segments[0] === 'team' && /^\d+$/.test(segments[1] || '') ? segments[1] : null;
}

function resolveMobileView(appId, pathname = window.location.pathname) {
    const currentPath = normalizePath(pathname);
    const appPath = normalizePath(new URL(appConfig[appId].path, window.location.origin).pathname);
    let requestedView = currentPath.slice(appPath.length).split('/').filter(Boolean)[0];
    const defaultViews = { client: 'home', sales: 'home', supervisor: 'home', driver: 'home' };

    if (appId === 'driver') {
        requestedView = { load: 'tasks', route: 'tasks', confirm: 'history' }[requestedView] || requestedView;
    }

    if (appId === 'client' && requestedView === 'deliveries') requestedView = 'orders';

    if (requestedView === 'account' || (['client', 'sales', 'supervisor', 'driver'].includes(appId) && requestedView === 'menu')) return requestedView;
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
    const legacyKpiDashboardPaths = ['sales', 'stock', 'delivery', 'finance']
        .map((kind) => normalizePath(`${officePath}/dashboards/${kind}`));
    if (legacyKpiDashboardPaths.some((path) => currentPath === path || currentPath.startsWith(`${path}/`))) {
        return 'dashboard-kpis';
    }
    const legacyStockOverviewPaths = [
        normalizePath(`${officePath}/stock/balances`),
        normalizePath(`${officePath}/stock/value`),
    ];
    if (legacyStockOverviewPaths.some((path) => currentPath === path || currentPath.startsWith(`${path}/`))) {
        return 'stock-overview';
    }
    const legacyBookPaths = [
        normalizePath(`${officePath}/finance/cash-book`),
        normalizePath(`${officePath}/finance/bank-book`),
    ];
    if (legacyBookPaths.some((path) => currentPath === path || currentPath.startsWith(`${path}/`))) {
        return 'finance-books';
    }
    const legacyExpensePaths = [
        normalizePath(`${officePath}/finance/daily-expenses`),
        normalizePath(`${officePath}/finance/outdoor-expenses`),
    ];
    if (legacyExpensePaths.some((path) => currentPath === path || currentPath.startsWith(`${path}/`))) {
        return 'finance-expenses';
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

function resolveKpiDashboardKind(pathname = window.location.pathname) {
    const currentPath = normalizePath(pathname);
    const kind = currentPath.split('/').filter(Boolean).at(-1);

    return ['sales', 'stock', 'delivery', 'finance'].includes(kind) ? kind : 'sales';
}

function resolveStockOverviewView(pathname = window.location.pathname) {
    const currentPath = normalizePath(pathname);

    return currentPath.endsWith('/value') ? 'value' : 'balance';
}

function resolveFinanceBook(pathname = window.location.pathname) {
    const currentPath = normalizePath(pathname);

    return currentPath.endsWith('/bank') || currentPath.includes('/finance/bank-book') ? 'bank' : 'cash';
}

function resolveFinanceExpenseType(pathname = window.location.pathname) {
    const currentPath = normalizePath(pathname);

    return currentPath.endsWith('/outdoor') || currentPath.includes('/finance/outdoor-expenses') ? 'outdoor' : 'daily';
}

function resolveVehicleCostCategory(pathname = window.location.pathname) {
    const currentPath = normalizePath(pathname);
    const officePath = normalizePath(new URL(appConfig.office.path, window.location.origin).pathname);
    const costPath = normalizePath(`${officePath}/vehicle-costs`);
    const category = currentPath.startsWith(`${costPath}/`) ? currentPath.slice(costPath.length + 1).split('/')[0] : '';

    return {
        fuel: 'fuel',
        maintenance: 'maintenance',
        insurance: 'insurance',
        license: 'license',
        'engine-oil': 'engine_oil',
        tyre: 'tyre',
        other: 'other',
    }[category] || '';
}

function resolveBusinessSetupSection(pathname = window.location.pathname) {
    const currentPath = normalizePath(pathname);
    const officePath = normalizePath(new URL(appConfig.office.path, window.location.origin).pathname);
    const setupPath = normalizePath(`${officePath}/setup`);
    const section = currentPath.startsWith(`${setupPath}/`) ? currentPath.slice(setupPath.length).split('/').filter(Boolean)[0] : '';
    return ['company', 'contact-channels', 'branding', 'delivery', 'attendance', 'salary', 'printing', 'brands', 'products', 'price-types', 'product-prices', 'areas', 'routes', 'warehouses', 'vehicles', 'roles', 'permissions', 'qr-locations', 'kpi-targets'].includes(section) ? section : 'company';
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

function AppearanceControls({ appId, t, user, onLogout, locale, setLocale, theme, setTheme, onProfile, navigate, pathname }) {
    return (
        <div className="topbar-actions">
            <ActionAlerts appId={appId} user={user} navigate={navigate} pathname={pathname} />
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

function ActionAlerts({ appId, user, navigate, pathname }) {
    const [state, setState] = useState({ loading: true, total: 0, items: [] });
    const [open, setOpen] = useState(false);
    const rootRef = useRef(null);

    useEffect(() => {
        let mounted = true;
        let timer;

        const load = () => window.axios.get(window.ValleyRuntime?.api?.actionAlerts || '/api/action-alerts', { params: { app: appId } })
            .then(({ data }) => {
                if (mounted) setState({ loading: false, total: Number(data.data.total || 0), items: data.data.items || [] });
            })
            .catch(() => {
                if (mounted) setState((current) => ({ ...current, loading: false }));
            });
        const refresh = () => load();

        load();
        timer = window.setInterval(load, 60000);
        window.addEventListener('focus', refresh);

        return () => {
            mounted = false;
            window.clearInterval(timer);
            window.removeEventListener('focus', refresh);
        };
    }, [appId, user.id, pathname]);

    useEffect(() => {
        if (!open) return undefined;
        const close = (event) => {
            if (!rootRef.current?.contains(event.target)) setOpen(false);
        };
        const closeOnEscape = (event) => event.key === 'Escape' && setOpen(false);
        document.addEventListener('pointerdown', close);
        window.addEventListener('keydown', closeOnEscape);
        return () => {
            document.removeEventListener('pointerdown', close);
            window.removeEventListener('keydown', closeOnEscape);
        };
    }, [open]);

    const openItem = (path) => {
        setOpen(false);
        navigate(`${appConfig[appId].path}${path}`);
    };

    return (
        <div className="action-alerts" ref={rootRef}>
            <button className={`icon-button action-alert-trigger ${open ? 'is-active' : ''}`} type="button" aria-label={`${state.total} action notifications`} title="Action notifications" aria-expanded={open} onClick={() => setOpen((value) => !value)}>
                <Bell size={17} />
                {state.total > 0 && <span className="action-alert-badge">{state.total > 99 ? '99+' : state.total}</span>}
            </button>
            {open && (
                <section className="action-alert-popover" aria-label="Action notifications">
                    <header><div><span>Notifications</span><strong>Action required</strong></div>{state.total > 0 && <b>{state.total}</b>}</header>
                    {state.loading ? <p className="action-alert-empty">Loading notifications…</p> : state.items.length === 0 ? <p className="action-alert-empty">You’re all caught up.</p> : (
                        <div className="action-alert-list">
                            {state.items.map((item) => (
                                <button type="button" key={item.id} onClick={() => openItem(item.path)}>
                                    <span><strong>{item.label}</strong><small>{item.detail}</small></span>
                                    <b>{item.count}</b>
                                </button>
                            ))}
                        </div>
                    )}
                </section>
            )}
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

function AuthScreen({ app, t, locale, setLocale, theme, setTheme, auth, onLogin, onRegister, onForgotPassword, onResetPassword }) {
    const authPath = normalizePath(window.location.pathname);
    const registering = app.id === 'client' && authPath.endsWith('/register');
    const forgetting = authPath.endsWith('/forgot-password');
    const resetting = authPath.includes('/reset-password/');
    const title = registering ? t.createNewAccount : forgetting ? t.forgotPasswordTitle : resetting ? t.resetPasswordTitle : t[app.id];
    const hint = registering ? t.customerRegistrationHint : forgetting ? t.forgotPasswordHint : resetting ? t.resetPasswordHint : t[`${app.id}Hint`];

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
                    <h1>{title}</h1>
                    <span className="muted">{hint}</span>
                </div>
                <LoginPanel app={app} t={t} auth={auth} onLogin={onLogin} onRegister={onRegister} onForgotPassword={onForgotPassword} onResetPassword={onResetPassword} />
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

function LoginPanel({ app, t, auth, onLogin, onRegister, onForgotPassword, onResetPassword }) {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [registration, setRegistration] = useState({ name: '', shop_name: '', phone: '', email: '', address: '', password: '', password_confirmation: '' });
    const authPath = normalizePath(window.location.pathname);
    const queryEmail = new URLSearchParams(window.location.search).get('email') || '';
    const [reset, setReset] = useState({ email: queryEmail, password: '', password_confirmation: '' });
    const forgetting = authPath.endsWith('/forgot-password');
    const resetting = authPath.includes('/reset-password/');
    const token = resetting ? decodeURIComponent(authPath.split('/').filter(Boolean).pop() || '') : '';

    const submit = (event) => {
        event.preventDefault();
        if (forgetting) onForgotPassword({ email });
        else if (resetting) onResetPassword({ ...reset, token });
        else if (registering) onRegister(registration);
        else onLogin({ email, password });
    };

    const updateRegistration = (field, value) => setRegistration((current) => ({ ...current, [field]: value }));
    const errorFor = (field) => auth.errors[field] && <span className="field-error">{auth.errors[field][0]}</span>;
    const canRegister = app.id === 'client';
    const registering = canRegister && normalizePath(window.location.pathname).endsWith('/register');
    const googleEnabled = canRegister && Boolean(window.ValleyRuntime?.auth?.googleEnabled);
    const clientBase = String(app.path).replace(/\/$/, '');
    const panelTitle = registering ? t.customerRegistration : forgetting ? t.forgotPasswordTitle : resetting ? t.resetPasswordTitle : t.authRequired;
    const submitLabel = registering ? t.createAccount : forgetting ? t.sendResetLink : resetting ? t.resetPasswordAction : t.signIn;

    return (
        <form className="login-card auth-card" autoComplete="off" onSubmit={submit}>
            <strong>{panelTitle}</strong>
            {(registering || forgetting || resetting) && <span className="muted auth-form-hint">{registering ? t.customerRegistrationHint : forgetting ? t.forgotPasswordHint : t.resetPasswordHint}</span>}
            {auth.message && <p className={`form-alert ${auth.success ? 'success' : ''}`}>{auth.message}</p>}
            {forgetting ? (
                <label>{t.emailAddress}<input required type="email" autoComplete="email" placeholder={t.emailAddressHint} value={email} onChange={(event) => setEmail(event.target.value)} />{errorFor('email')}</label>
            ) : resetting ? <>
                <label>{t.emailAddress}<input required readOnly type="email" autoComplete="email" value={reset.email} />{errorFor('email')}</label>
                <label>{t.newPassword}<input required minLength="8" maxLength="72" type="password" autoComplete="new-password" value={reset.password} onChange={(event) => setReset((current) => ({ ...current, password: event.target.value }))} />{errorFor('password')}</label>
                <label>{t.confirmPassword}<input required minLength="8" maxLength="72" type="password" autoComplete="new-password" value={reset.password_confirmation} onChange={(event) => setReset((current) => ({ ...current, password_confirmation: event.target.value }))} /></label>
            </> : !registering ? <>
                <label>{t.email}<input name="login-identifier" autoComplete="email" placeholder={t.emailHint} value={email} onChange={(event) => setEmail(event.target.value)} />{errorFor('email')}</label>
                <label>{t.password}<input name="login-secret" autoComplete="current-password" placeholder={t.passwordHint} value={password} onChange={(event) => setPassword(event.target.value)} type="password" />{errorFor('password')}</label>
            </> : <div className="customer-registration-fields">
                <label>{t.contactName}<input required autoComplete="name" value={registration.name} onChange={(event) => updateRegistration('name', event.target.value)} />{errorFor('name')}</label>
                <label>{t.shopName}<input required autoComplete="organization" value={registration.shop_name} onChange={(event) => updateRegistration('shop_name', event.target.value)} />{errorFor('shop_name')}</label>
                <label>{t.phone}<input required type="tel" autoComplete="tel" value={registration.phone} onChange={(event) => updateRegistration('phone', event.target.value)} />{errorFor('phone')}</label>
                <label>{t.email}<input required type="email" autoComplete="email" value={registration.email} onChange={(event) => updateRegistration('email', event.target.value)} />{errorFor('email')}</label>
                <label className="wide">{t.deliveryAddress}<textarea required rows="3" autoComplete="street-address" value={registration.address} onChange={(event) => updateRegistration('address', event.target.value)} />{errorFor('address')}</label>
                <label>{t.password}<input required minLength="8" type="password" autoComplete="new-password" value={registration.password} onChange={(event) => updateRegistration('password', event.target.value)} />{errorFor('password')}</label>
                <label>{t.confirmPassword}<input required minLength="8" type="password" autoComplete="new-password" value={registration.password_confirmation} onChange={(event) => updateRegistration('password_confirmation', event.target.value)} /></label>
            </div>}
            {auth.errors.app && <p className="form-alert">{auth.errors.app[0]}</p>}
            <button className="button primary" type="submit" disabled={auth.submitting}>
                <User size={16} />
                {submitLabel}
            </button>
            {!registering && !forgetting && !resetting && <a className="auth-text-action" href={`${clientBase}/forgot-password`}>{t.forgotPassword}</a>}
            {googleEnabled && !forgetting && !resetting && <><div className="auth-divider"><span>or</span></div><a className="button auth-google-button" href={authRoute('googleRedirect', '/api/auth/google/redirect')}><b aria-hidden="true">G</b>{t.continueWithGoogle}</a></>}
            {(forgetting || resetting) && <a className="auth-text-action" href={clientBase}>{t.backToSignIn}</a>}
            {canRegister && !forgetting && !resetting && <a className="auth-text-action" href={registering ? clientBase : `${clientBase}/register`}>{registering ? `${t.existingAccount} ${t.signIn}` : t.createNewAccount}</a>}
        </form>
    );
}

createRoot(document.getElementById('root')).render(
    <Suspense fallback={<div className="app-loading"><span className="spinner" />Loading workspace…</div>}>
        <App />
    </Suspense>,
);
