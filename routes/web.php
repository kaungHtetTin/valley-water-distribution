<?php

use App\Http\Controllers\Api\AttendanceLocationController;
use App\Http\Controllers\Api\AttendanceRecordController;
use App\Http\Controllers\Api\ActionAlertController;
use App\Http\Controllers\Api\CompanySettingsController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\KpiReviewController;
use App\Http\Controllers\Api\MasterDataController;
use App\Http\Controllers\Api\MobileMasterDataController;
use App\Http\Controllers\Api\MobileOrderController;
use App\Http\Controllers\Api\OrderAdjustmentController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OperationsReportController;
use App\Http\Controllers\Api\PayrollAdjustmentController;
use App\Http\Controllers\Api\PayrollController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\UatController;
use App\Http\Controllers\Api\VehicleCostController;
use App\Http\Controllers\Auth\AppAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::redirect('/', url('/office'));

Route::prefix('api/auth')->group(function () {
    Route::get('/user', [AppAuthController::class, 'user']);
    Route::post('/login', [AppAuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/register', [AppAuthController::class, 'register'])->middleware('throttle:6,1');
    Route::get('/google/redirect', [AppAuthController::class, 'googleRedirect'])->middleware('throttle:20,1');
    Route::get('/google/callback', [AppAuthController::class, 'googleCallback'])->middleware('throttle:20,1');
    Route::post('/logout', [AppAuthController::class, 'logout']);
    Route::put('/preferences', [AppAuthController::class, 'preferences'])->middleware('auth');
    Route::put('/profile', [AppAuthController::class, 'profile'])->middleware(['auth', 'audit.api']);
    Route::put('/password', [AppAuthController::class, 'password'])->middleware(['auth', 'audit.api', 'throttle:6,1']);
});

Route::middleware(['auth', 'audit.api'])->prefix('api')->group(function () {
    Route::get('/action-alerts', [ActionAlertController::class, 'index']);
    Route::get('/settings/company', [CompanySettingsController::class, 'show']);
    Route::put('/settings/company', [CompanySettingsController::class, 'update']);
    Route::get('/settings/printing', [CompanySettingsController::class, 'printing']);
    Route::put('/settings/printing', [CompanySettingsController::class, 'updatePrinting']);

    Route::get('/master-data/meta', [MasterDataController::class, 'meta']);
    Route::get('/master-data/product-prices/matrix', [MasterDataController::class, 'productPriceMatrix']);
    Route::put('/master-data/product-prices/matrix', [MasterDataController::class, 'updateProductPriceMatrix']);
    Route::get('/master-data/customers/{id}/detail', [MasterDataController::class, 'customerDetail'])->whereNumber('id');
    Route::get('/master-data/employees/{id}/detail', [MasterDataController::class, 'employeeDetail'])->whereNumber('id');
    Route::put('/master-data/employees/{id}/vehicle', [MasterDataController::class, 'assignEmployeeVehicle'])->whereNumber('id');
    Route::get('/master-data/{resource}', [MasterDataController::class, 'index']);
    Route::post('/master-data/{resource}', [MasterDataController::class, 'store']);
    Route::get('/master-data/{resource}/{id}', [MasterDataController::class, 'show'])->whereNumber('id');
    Route::put('/master-data/{resource}/{id}', [MasterDataController::class, 'update'])->whereNumber('id');
    Route::delete('/master-data/{resource}/{id}', [MasterDataController::class, 'destroy'])->whereNumber('id');

    Route::get('/mobile/master/profile', [MobileMasterDataController::class, 'profile']);
    Route::get('/mobile/master/customers', [MobileMasterDataController::class, 'assignedCustomers']);
    Route::post('/mobile/master/customers', [MobileMasterDataController::class, 'storeCustomer']);
    Route::get('/mobile/master/customers/{id}', [MobileMasterDataController::class, 'customer'])->whereNumber('id');
    Route::put('/mobile/master/customers/{id}', [MobileMasterDataController::class, 'updateCustomer'])->whereNumber('id');
    Route::get('/mobile/master/vehicle', [MobileMasterDataController::class, 'assignedVehicle']);
    Route::get('/mobile/sales-visits', [MobileMasterDataController::class, 'salesVisits']);
    Route::post('/mobile/sales-visits/customers/{customerId}', [MobileMasterDataController::class, 'updateSalesVisit'])
        ->whereNumber('customerId')
        ->middleware('throttle:30,1');
    Route::get('/mobile/attendance/locations', [MobileMasterDataController::class, 'attendanceLocations']);
    Route::post('/mobile/attendance/check-in', [MobileMasterDataController::class, 'recordAttendance'])->middleware('throttle:10,1');
    Route::get('/mobile/attendance/records', [MobileMasterDataController::class, 'attendanceRecords']);
    Route::get('/mobile/payroll/history', [MobileMasterDataController::class, 'payrollHistory']);
    Route::get('/mobile/kpi', [KpiReviewController::class, 'mobile']);
    Route::get('/mobile/orders/meta', [MobileOrderController::class, 'meta']);
    Route::get('/mobile/orders', [MobileOrderController::class, 'index']);
    Route::post('/mobile/orders', [MobileOrderController::class, 'store']);
    Route::get('/mobile/orders/{order}', [MobileOrderController::class, 'show']);
    Route::put('/mobile/orders/{order}', [MobileOrderController::class, 'update']);
    Route::post('/mobile/orders/{order}/cancel', [MobileOrderController::class, 'cancel']);
    Route::get('/mobile/delivery-status', [DeliveryController::class, 'mobileStatusIndex']);
    Route::get('/mobile/delivery-status/{delivery}', [DeliveryController::class, 'mobileStatusShow']);
    Route::get('/mobile/deliveries', [DeliveryController::class, 'driverIndex']);
    Route::get('/mobile/deliveries/{delivery}', [DeliveryController::class, 'driverShow']);
    Route::post('/mobile/deliveries/{delivery}/confirm-loading', [DeliveryController::class, 'confirmLoading']);
    Route::post('/mobile/deliveries/{delivery}/start-route', [DeliveryController::class, 'startRoute']);
    Route::post('/mobile/deliveries/{delivery}/location', [DeliveryController::class, 'storeLocation']);
    Route::post('/mobile/deliveries/{delivery}/customer-location', [DeliveryController::class, 'updateCustomerLocation']);
    Route::post('/mobile/deliveries/{delivery}/complete', [DeliveryController::class, 'completeDelivery']);
    Route::post('/mobile/deliveries/{delivery}/complete-trip', [DeliveryController::class, 'completeTrip']);
    Route::get('/mobile/finance/meta', [FinanceController::class, 'mobileMeta']);
    Route::get('/mobile/finance', [FinanceController::class, 'mobileIndex']);
    Route::post('/mobile/finance/collections', [FinanceController::class, 'mobileStoreCollection']);
    Route::post('/mobile/finance/expenses', [FinanceController::class, 'mobileStoreExpense']);
    Route::get('/mobile/vehicle-operations', [VehicleCostController::class, 'mobileIndex']);
    Route::post('/mobile/vehicle-operations', [VehicleCostController::class, 'mobileStore']);
    Route::get('/mobile/dashboard', [DashboardController::class, 'mobile']);

    Route::get('/orders/meta', [OrderController::class, 'meta']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::put('/orders/{order}', [OrderController::class, 'update']);
    Route::post('/orders/{order}/confirm', [OrderController::class, 'confirm']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
    Route::get('/order-adjustments/meta', [OrderAdjustmentController::class, 'meta']);
    Route::get('/order-adjustments', [OrderAdjustmentController::class, 'index']);
    Route::post('/order-adjustments', [OrderAdjustmentController::class, 'store']);
    Route::get('/order-adjustments/{order}', [OrderAdjustmentController::class, 'show']);
    Route::get('/invoices/meta', [InvoiceController::class, 'meta']);
    Route::get('/invoices', [InvoiceController::class, 'index']);
    Route::post('/invoices/from-order', [InvoiceController::class, 'storeFromOrder']);
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
    Route::post('/invoices/{invoice}/issue', [InvoiceController::class, 'issue']);
    Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel']);

    Route::get('/stock/meta', [StockController::class, 'meta']);
    Route::get('/stock/movements', [StockController::class, 'movements']);
    Route::post('/stock/movements', [StockController::class, 'storeMovement']);
    Route::get('/stock/receipts', [StockController::class, 'receipts']);
    Route::get('/stock/transfers', [StockController::class, 'transfers']);
    Route::get('/stock/documents/{documentCode}', [StockController::class, 'document']);
    Route::post('/stock/receipts', [StockController::class, 'storeReceipt']);
    Route::post('/stock/transfers', [StockController::class, 'storeTransfer']);
    Route::get('/stock/closing-counts', [StockController::class, 'closingCounts']);
    Route::get('/stock/closing-counts/preview', [StockController::class, 'closingCountPreview']);
    Route::get('/stock/closing-counts/{count}', [StockController::class, 'showClosingCount'])->whereNumber('count');
    Route::post('/stock/closing-counts', [StockController::class, 'storeClosingCount']);
    Route::get('/stock/balances', [StockController::class, 'balances']);
    Route::get('/stock/value', [StockController::class, 'stockValue']);
    Route::get('/stock/card', [StockController::class, 'stockCard']);

    Route::get('/deliveries/meta', [DeliveryController::class, 'meta']);
    Route::get('/deliveries/live-map', [DeliveryController::class, 'liveMap']);
    Route::get('/deliveries/{delivery}/locations', [DeliveryController::class, 'locationHistory']);
    Route::get('/deliveries', [DeliveryController::class, 'index']);
    Route::post('/deliveries', [DeliveryController::class, 'store']);
    Route::get('/deliveries/{delivery}', [DeliveryController::class, 'show']);
    Route::patch('/deliveries/{delivery}/trip', [DeliveryController::class, 'updateTrip']);
    Route::post('/deliveries/{delivery}/cancel', [DeliveryController::class, 'cancelTrip']);

    Route::get('/finance/meta', [FinanceController::class, 'meta']);
    Route::get('/finance/collections', [FinanceController::class, 'collections']);
    Route::post('/finance/collections', [FinanceController::class, 'storeCollection']);
    Route::post('/finance/cash-handovers/{employeeId}/receive', [FinanceController::class, 'receiveCashHandover'])->whereNumber('employeeId');
    Route::post('/finance/collections/{collection}/review', [FinanceController::class, 'reviewCollection']);
    Route::get('/finance/receivables', [FinanceController::class, 'receivables']);
    Route::get('/finance/customers/{customerId}/ledger', [FinanceController::class, 'customerLedger'])->whereNumber('customerId');
    Route::get('/finance/expenses', [FinanceController::class, 'expenses']);
    Route::post('/finance/expenses', [FinanceController::class, 'storeExpense']);
    Route::post('/finance/expenses/{expense}/review', [FinanceController::class, 'reviewExpense']);
    Route::get('/finance/books/{book}', [FinanceController::class, 'book'])->whereIn('book', ['cash', 'bank']);
    Route::get('/finance/suppliers', [FinanceController::class, 'suppliers']);
    Route::get('/finance/suppliers/{supplierId}/ledger', [FinanceController::class, 'supplierLedger'])->whereNumber('supplierId');
    Route::post('/finance/suppliers/{supplierId}/ledger', [FinanceController::class, 'storeSupplierEntry'])->whereNumber('supplierId');
    Route::post('/finance/suppliers/{supplierId}/payments', [FinanceController::class, 'storeSupplierPayment'])->whereNumber('supplierId');
    Route::get('/finance/profit-loss', [FinanceController::class, 'profitLoss']);

    Route::get('/vehicle-costs/meta', [VehicleCostController::class, 'meta']);
    Route::get('/vehicle-costs', [VehicleCostController::class, 'index']);
    Route::post('/vehicle-costs', [VehicleCostController::class, 'store']);
    Route::get('/vehicle-costs/{vehicleCost}', [VehicleCostController::class, 'show']);
    Route::put('/vehicle-costs/{vehicleCost}', [VehicleCostController::class, 'update']);
    Route::delete('/vehicle-costs/{vehicleCost}', [VehicleCostController::class, 'destroy']);
    Route::post('/vehicle-costs/{vehicleCost}/review', [VehicleCostController::class, 'review']);
    Route::get('/vehicle-reports/monthly-costs', [VehicleCostController::class, 'monthlyCosts']);
    Route::get('/vehicle-reports/route-history', [VehicleCostController::class, 'routeHistory']);
    Route::put('/vehicle-reports/route-history/{deliveryId}/distance', [VehicleCostController::class, 'updateDistance'])->whereNumber('deliveryId');
    Route::get('/vehicle-reports/cost-per-km', [VehicleCostController::class, 'costPerKm']);
    Route::get('/vehicle-reports/performance', [VehicleCostController::class, 'performance']);
    Route::get('/dashboards/{dashboard}', [DashboardController::class, 'summary'])->whereIn('dashboard', ['owner', 'sales', 'stock', 'delivery', 'finance']);
    Route::get('/dashboard-charts/{dashboard}', [DashboardController::class, 'charts'])->whereIn('dashboard', ['owner', 'sales', 'stock', 'delivery', 'finance']);
    Route::get('/reports/operations', [OperationsReportController::class, 'index']);
    Route::get('/reports/operations/export', [OperationsReportController::class, 'export']);
    Route::get('/uat/overview', [UatController::class, 'overview']);
    Route::get('/uat/issues', [UatController::class, 'issues']);
    Route::post('/uat/issues', [UatController::class, 'storeIssue']);
    Route::put('/uat/issues/{id}', [UatController::class, 'updateIssue'])->whereNumber('id');
    Route::get('/uat/audit-logs', [UatController::class, 'auditLogs']);

    Route::get('/attendance/locations', [AttendanceLocationController::class, 'index']);
    Route::post('/attendance/locations', [AttendanceLocationController::class, 'store']);
    Route::get('/attendance/locations/{attendanceLocation}', [AttendanceLocationController::class, 'show']);
    Route::put('/attendance/locations/{attendanceLocation}', [AttendanceLocationController::class, 'update']);
    Route::delete('/attendance/locations/{attendanceLocation}', [AttendanceLocationController::class, 'destroy']);
    Route::post('/attendance/locations/{attendanceLocation}/rotate-token', [AttendanceLocationController::class, 'rotateToken']);
    Route::get('/attendance/summary', [AttendanceRecordController::class, 'summary']);
    Route::get('/attendance/records', [AttendanceRecordController::class, 'index']);
    Route::get('/attendance/records/{id}', [AttendanceRecordController::class, 'show'])->whereNumber('id');

    Route::get('/payroll-history', [PayrollController::class, 'salaryHistory']);
    Route::get('/payroll-history/{id}', [PayrollController::class, 'salaryHistoryShow'])->whereNumber('id');
    Route::get('/payrolls', [PayrollController::class, 'index']);
    Route::post('/payrolls/generate', [PayrollController::class, 'generate']);
    Route::get('/payrolls/{payroll}', [PayrollController::class, 'show']);
    Route::delete('/payrolls/{payroll}', [PayrollController::class, 'destroy']);
    Route::post('/payrolls/{payroll}/approve', [PayrollController::class, 'approve']);
    Route::post('/payrolls/{payroll}/mark-paid', [PayrollController::class, 'markPaid']);
    Route::get('/payroll-adjustments', [PayrollAdjustmentController::class, 'index']);
    Route::post('/payroll-adjustments', [PayrollAdjustmentController::class, 'store']);
    Route::put('/payroll-adjustments/{payrollAdjustment}', [PayrollAdjustmentController::class, 'update']);
    Route::delete('/payroll-adjustments/{payrollAdjustment}', [PayrollAdjustmentController::class, 'destroy']);
    Route::get('/kpi-targets', [KpiReviewController::class, 'targets']);
    Route::get('/kpi-targets/employees/{employeeId}', [KpiReviewController::class, 'employeeTarget'])->whereNumber('employeeId');
    Route::put('/kpi-targets/roles/{templateId}', [KpiReviewController::class, 'saveRoleTarget'])->whereNumber('templateId');
    Route::put('/kpi-targets/{employeeId}', [KpiReviewController::class, 'saveTarget'])->whereNumber('employeeId');
    Route::get('/kpi-reviews/meta', [KpiReviewController::class, 'meta']);
    Route::get('/kpi-reports', [KpiReviewController::class, 'report']);
    Route::get('/kpi-reviews', [KpiReviewController::class, 'index']);
    Route::post('/kpi-reviews/generate', [KpiReviewController::class, 'generate']);
    Route::get('/kpi-reviews/{id}', [KpiReviewController::class, 'show'])->whereNumber('id');
    Route::put('/kpi-reviews/{id}', [KpiReviewController::class, 'update'])->whereNumber('id');
    Route::post('/kpi-reviews/{id}/refresh', [KpiReviewController::class, 'refresh'])->whereNumber('id');
    Route::post('/kpi-reviews/{id}/submit', [KpiReviewController::class, 'submit'])->whereNumber('id');
    Route::post('/kpi-reviews/{id}/approve', [KpiReviewController::class, 'approve'])->whereNumber('id');
    Route::post('/kpi-reviews/{id}/post-bonus', [KpiReviewController::class, 'postBonus'])->whereNumber('id');
});

Route::view('/office', 'app')->name('office');
Route::view('/client', 'app')->name('client');
Route::view('/sales', 'app')->name('sales');
Route::view('/driver', 'app')->name('driver');
Route::view('/attendance/{token}', 'app')->name('attendance.public');

Route::view('/office/{any}', 'app')->where('any', '.*');
Route::view('/client/{any}', 'app')->where('any', '.*');
Route::view('/sales/{any}', 'app')->where('any', '.*');
Route::view('/driver/{any}', 'app')->where('any', '.*');
