<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Valley Water Distribution</title>
        <script>
            window.ValleyRuntime = {
                routes: {
                    office: @json(url('/office')),
                    client: @json(url('/client')),
                    sales: @json(url('/sales')),
                    driver: @json(url('/driver')),
                    attendance: @json(url('/attendance')),
                },
                auth: {
                    user: @json(url('/api/auth/user')),
                    login: @json(url('/api/auth/login')),
                    logout: @json(url('/api/auth/logout')),
                    preferences: @json(url('/api/auth/preferences')),
                    profile: @json(url('/api/auth/profile')),
                    password: @json(url('/api/auth/password')),
                },
                api: {
                    actionAlerts: @json(url('/api/action-alerts')),
                    companySettings: @json(url('/api/settings/company')),
                    masterData: @json(url('/api/master-data')),
                    mobileMaster: @json(url('/api/mobile/master')),
                    mobileAttendance: @json(url('/api/mobile/attendance')),
                    mobilePayroll: @json(url('/api/mobile/payroll')),
                    mobileOrders: @json(url('/api/mobile/orders')),
                    mobileDeliveries: @json(url('/api/mobile/deliveries')),
                    mobileDeliveryStatus: @json(url('/api/mobile/delivery-status')),
                    deliveryLiveMap: @json(url('/api/deliveries/live-map')),
                    finance: @json(url('/api/finance')),
                    mobileFinance: @json(url('/api/mobile/finance')),
                    vehicleCosts: @json(url('/api/vehicle-costs')),
                    vehicleReports: @json(url('/api/vehicle-reports')),
                    mobileVehicleOperations: @json(url('/api/mobile/vehicle-operations')),
                    dashboards: @json(url('/api/dashboards')),
                    dashboardCharts: @json(url('/api/dashboard-charts')),
                    mobileDashboard: @json(url('/api/mobile/dashboard')),
                    uat: @json(url('/api/uat')),
                    orders: @json(url('/api/orders')),
                    orderAdjustments: @json(url('/api/order-adjustments')),
                    invoices: @json(url('/api/invoices')),
                    stock: @json(url('/api/stock')),
                    deliveries: @json(url('/api/deliveries')),
                    attendanceLocations: @json(url('/api/attendance/locations')),
                    attendanceRecords: @json(url('/api/attendance/records')),
                    attendanceSummary: @json(url('/api/attendance/summary')),
                    payrollHistory: @json(url('/api/payroll-history')),
                    payrolls: @json(url('/api/payrolls')),
                    payrollAdjustments: @json(url('/api/payroll-adjustments')),
                    kpiReviews: @json(url('/api/kpi-reviews')),
                    kpiTargets: @json(url('/api/kpi-targets')),
                    kpiReports: @json(url('/api/kpi-reports')),
                    operationsReports: @json(url('/api/reports/operations')),
                    mobileKpi: @json(url('/api/mobile/kpi')),
                    publicAttendance: @json(url('/api/public/attendance')),
                },
            };
        </script>
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div id="root"></div>
    </body>
</html>
