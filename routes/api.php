<?php

use App\Http\Controllers\Api\V1\CustomerSales\CustomerController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MasterData\AccessControlController;
use App\Http\Controllers\Api\V1\MasterData\AreaController;
use App\Http\Controllers\Api\V1\MasterData\AuditHistoryController;
use App\Http\Controllers\Api\V1\MasterData\BranchController;
use App\Http\Controllers\Api\V1\MasterData\CatalogSetupController;
use App\Http\Controllers\Api\V1\MasterData\ControlledLocationController;
use App\Http\Controllers\Api\V1\MasterData\FoundationMasterController;
use App\Http\Controllers\Api\V1\MasterData\OrganizationControlController;
use App\Http\Controllers\Api\V1\MasterData\PriceBookItemController;
use App\Http\Controllers\Api\V1\MasterData\PricingControlController;
use App\Http\Controllers\Api\V1\MasterData\RouteTemplateController;
use App\Http\Controllers\Api\V1\MasterData\SkuController;
use App\Http\Controllers\Api\V1\MasterData\WarehouseController;
use App\Http\Controllers\Api\V1\MasterData\WayController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', HealthController::class)->name('api.v1.health');

    Route::middleware(['feature:customer_sales', 'organization'])
        ->prefix('customer-sales')
        ->name('api.v1.customer-sales.')
        ->group(function (): void {
            Route::middleware('master.permission:customers.view')->get('customers/options', [CustomerController::class, 'options'])->name('customers.options');
            Route::middleware('master.permission:customers.view')->get('customers', [CustomerController::class, 'index'])->name('customers.index');
            Route::middleware('master.permission:customers.manage')->group(function (): void {
                Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
                Route::put('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
                Route::patch('customers/{customer}/archive', [CustomerController::class, 'archive'])->name('customers.archive');
            });
        });

    Route::middleware(['feature:master_data', 'organization', 'master.access'])
        ->prefix('master-data')
        ->name('api.v1.master-data.')
        ->group(function (): void {
            Route::apiResource('areas', AreaController::class)->only(['index', 'store', 'show', 'update']);
            Route::patch('areas/{area}/archive', [AreaController::class, 'archive'])->name('areas.archive');
            Route::get('locations/options', [WarehouseController::class, 'options'])->name('locations.options');
            Route::apiResource('branches', BranchController::class)->only(['index', 'store', 'update']);
            Route::patch('branches/{branch}/archive', [BranchController::class, 'archive'])->name('branches.archive');
            Route::apiResource('warehouses', WarehouseController::class)->only(['index', 'store', 'update']);
            Route::patch('warehouses/{warehouse}/archive', [WarehouseController::class, 'archive'])->name('warehouses.archive');
            Route::get('organization-controls', [OrganizationControlController::class, 'index'])->name('organization-controls.index');
            Route::put('organization-controls/organization', [OrganizationControlController::class, 'updateOrganization'])->name('organization-controls.organization.update');
            Route::post('organization-controls/calendars', [OrganizationControlController::class, 'storeCalendar'])->name('organization-controls.calendars.store');
            Route::put('organization-controls/calendars/{calendar}', [OrganizationControlController::class, 'updateCalendar'])->name('organization-controls.calendars.update');
            Route::post('organization-controls/periods', [OrganizationControlController::class, 'storePeriod'])->name('organization-controls.periods.store');
            Route::put('organization-controls/periods/{period}', [OrganizationControlController::class, 'updatePeriod'])->name('organization-controls.periods.update');
            Route::post('organization-controls/sequences', [OrganizationControlController::class, 'storeSequence'])->name('organization-controls.sequences.store');
            Route::put('organization-controls/sequences/{sequence}', [OrganizationControlController::class, 'updateSequence'])->name('organization-controls.sequences.update');
            Route::patch('organization-controls/{type}/{record}/archive', [OrganizationControlController::class, 'archive'])->whereIn('type', ['calendars', 'periods', 'sequences'])->name('organization-controls.archive');
            Route::get('controlled-locations', [ControlledLocationController::class, 'index'])->name('controlled-locations.index');
            Route::post('controlled-locations/{type}', [ControlledLocationController::class, 'store'])->whereIn('type', ['zones', 'bins', 'replenishment', 'cash'])->name('controlled-locations.store');
            Route::put('controlled-locations/{type}/{record}', [ControlledLocationController::class, 'update'])->whereIn('type', ['zones', 'bins', 'replenishment', 'cash'])->name('controlled-locations.update');
            Route::patch('controlled-locations/{type}/{record}/archive', [ControlledLocationController::class, 'archive'])->whereIn('type', ['zones', 'bins', 'replenishment', 'cash'])->name('controlled-locations.archive');
            Route::get('catalog-setup', [CatalogSetupController::class, 'index'])->name('catalog-setup.index');
            Route::post('catalog-setup/{type}', [CatalogSetupController::class, 'store'])->whereIn('type', ['categories', 'brands', 'products', 'units', 'price-types', 'price-books'])->name('catalog-setup.store');
            Route::put('catalog-setup/{type}/{record}', [CatalogSetupController::class, 'update'])->whereIn('type', ['categories', 'brands', 'products', 'units', 'price-types', 'price-books'])->name('catalog-setup.update');
            Route::patch('catalog-setup/{type}/{record}/archive', [CatalogSetupController::class, 'archive'])->whereIn('type', ['categories', 'brands', 'products', 'units', 'price-types', 'price-books'])->name('catalog-setup.archive');
            Route::middleware('master.permission:master_data.export')->get('foundation-masters/{type}/export', [FoundationMasterController::class, 'export'])->name('foundation-masters.export');
            Route::middleware('master.permission:master_data.import')->group(function (): void {
                Route::post('foundation-masters/{type}/imports/preview', [FoundationMasterController::class, 'previewImport'])->name('foundation-masters.imports.preview');
                Route::post('foundation-masters/imports/{batch}/commit', [FoundationMasterController::class, 'commitImport'])->name('foundation-masters.imports.commit');
            });
            Route::middleware('master.permission:master_data.view')->get('foundation-masters/{type}', [FoundationMasterController::class, 'index'])->name('foundation-masters.index');
            Route::middleware('master.permission:master_data.manage')->group(function (): void {
                Route::post('foundation-masters/{type}', [FoundationMasterController::class, 'store'])->name('foundation-masters.store');
                Route::put('foundation-masters/{type}/{record}', [FoundationMasterController::class, 'update'])->name('foundation-masters.update');
                Route::patch('foundation-masters/{type}/{record}/archive', [FoundationMasterController::class, 'archive'])->name('foundation-masters.archive');
            });
            Route::middleware('master.permission:master_data.view')->get('access-controls', [AccessControlController::class, 'index'])->name('access-controls.index');
            Route::middleware('master.permission:master_data.view')->get('audit-history', AuditHistoryController::class)->name('audit-history.index');
            Route::middleware('master.permission:master_data.access.manage')->group(function (): void {
                Route::post('access-controls/users', [AccessControlController::class, 'storeUser'])->name('access-controls.users.store');
                Route::post('access-controls/roles', [AccessControlController::class, 'storeRole'])->name('access-controls.roles.store');
                Route::put('access-controls/roles/{role}', [AccessControlController::class, 'updateRole'])->name('access-controls.roles.update');
                Route::patch('access-controls/roles/{role}/archive', [AccessControlController::class, 'archiveRole'])->name('access-controls.roles.archive');
                Route::post('access-controls/assignments', [AccessControlController::class, 'assign'])->name('access-controls.assignments.store');
                Route::patch('access-controls/assignments/{assignment}/revoke', [AccessControlController::class, 'revoke'])->name('access-controls.assignments.revoke');
            });
            Route::get('route-templates', [RouteTemplateController::class, 'index'])->name('route-templates.index');
            Route::post('route-templates', [RouteTemplateController::class, 'store'])->name('route-templates.store');
            Route::put('route-templates/{template}', [RouteTemplateController::class, 'update'])->name('route-templates.update');
            Route::patch('route-templates/{template}/archive', [RouteTemplateController::class, 'archive'])->name('route-templates.archive');
            Route::get('ways/options', [WayController::class, 'options'])->name('ways.options');
            Route::apiResource('ways', WayController::class)->only(['index', 'store', 'update']);
            Route::patch('ways/{way}/archive', [WayController::class, 'archive'])->name('ways.archive');
            Route::get('catalog/options', [SkuController::class, 'options'])->name('catalog.options');
            Route::apiResource('skus', SkuController::class)->only(['index', 'store', 'update']);
            Route::post('skus/{sku}/conversions', [SkuController::class, 'reviseConversion'])->name('skus.conversions.revise');
            Route::patch('skus/{sku}/archive', [SkuController::class, 'archive'])->name('skus.archive');
            Route::apiResource('prices', PriceBookItemController::class)->only(['index', 'store', 'update']);
            Route::patch('prices/{price}/archive', [PriceBookItemController::class, 'archive'])->name('prices.archive');
            Route::middleware('master.permission:master_data.prices.approve')->patch('prices/{price}/approve', [PriceBookItemController::class, 'approve'])->name('prices.approve');
            Route::middleware('master.permission:master_data.view')->get('pricing-controls', [PricingControlController::class, 'index'])->name('pricing-controls.index');
            Route::middleware('master.permission:master_data.view')->get('pricing-controls/resolve', [PricingControlController::class, 'resolve'])->name('pricing-controls.resolve');
            Route::middleware('master.permission:master_data.manage')->group(function (): void {
                Route::post('pricing-controls/assignments', [PricingControlController::class, 'storeAssignment'])->name('pricing-controls.assignments.store');
                Route::put('pricing-controls/assignments/{assignment}', [PricingControlController::class, 'updateAssignment'])->name('pricing-controls.assignments.update');
                Route::patch('pricing-controls/assignments/{assignment}/archive', [PricingControlController::class, 'archiveAssignment'])->name('pricing-controls.assignments.archive');
                Route::post('pricing-controls/costs', [PricingControlController::class, 'storeCost'])->name('pricing-controls.costs.store');
            });
            Route::middleware('master.permission:master_data.costs.approve')->patch('pricing-controls/costs/{cost}/approve', [PricingControlController::class, 'approveCost'])->name('pricing-controls.costs.approve');
        });
});
