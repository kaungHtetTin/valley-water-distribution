<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DemoSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Demo data may only be seeded in local and testing environments.');
        }

        $this->call(SetupSeeder::class);

        $now = now();

        DB::table('companies')->updateOrInsert(['code' => 'VALLEY'], [
            'name' => 'Valley Water Distribution',
            'legal_name' => 'Valley Water Distribution Co., Ltd.',
            'phone' => '081 212 345',
            'email' => 'office@valley.test',
            'registration_no' => 'TGI-2026-001',
            'tax_no' => 'MM-VALLEY-01',
            'address' => 'East Circular Road, Taunggyi',
            'city' => 'Taunggyi',
            'state' => 'Shan State',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ([
            ['code' => 'TGI', 'name' => 'Taunggyi', 'description' => 'Taunggyi city service area'],
            ['code' => 'NSN', 'name' => 'Nam San', 'description' => 'Nam San township service area'],
        ] as $area) {
            DB::table('areas')->updateOrInsert(['code' => $area['code']], $area + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        $tgiAreaId = DB::table('areas')->where('code', 'TGI')->value('id');
        $namSanAreaId = DB::table('areas')->where('code', 'NSN')->value('id');

        foreach ([
            ['area_id' => $tgiAreaId, 'code' => 'TGI-N', 'name' => 'Taunggyi North', 'service_day' => 'Monday', 'description' => 'Northern Taunggyi reseller route'],
            ['area_id' => $tgiAreaId, 'code' => 'TGI-M', 'name' => 'Taunggyi Market', 'service_day' => 'Wednesday', 'description' => 'Central market and downtown route'],
            ['area_id' => $namSanAreaId, 'code' => 'NSN-01', 'name' => 'Nam San Main', 'service_day' => 'Friday', 'description' => 'Main Nam San delivery route'],
        ] as $route) {
            DB::table('routes')->updateOrInsert(['code' => $route['code']], $route + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        foreach ([
            ['area_id' => $tgiAreaId, 'code' => 'WH-TGI', 'name' => 'Taunggyi Main Warehouse', 'phone' => '081 220 001', 'address' => 'Industrial Zone, Taunggyi'],
            ['area_id' => $namSanAreaId, 'code' => 'WH-NSN', 'name' => 'Nam San Warehouse', 'phone' => '081 220 002', 'address' => 'Main Road, Nam San'],
        ] as $warehouse) {
            DB::table('warehouses')->updateOrInsert(['code' => $warehouse['code']], $warehouse + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        foreach ([
            ['code' => 'VAL', 'name' => 'Valley', 'description' => 'Valley purified drinking water'],
            ['code' => 'ALP', 'name' => 'Alpine', 'description' => 'Partner bottled water brand'],
        ] as $brand) {
            DB::table('brands')->updateOrInsert(['code' => $brand['code']], $brand + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        $valleyBrandId = DB::table('brands')->where('code', 'VAL')->value('id');
        foreach ([
            ['sku' => 'VAL-5G', 'name' => 'Valley 5 Gallon Water', 'unit' => 'bottle', 'size' => '5 gallon', 'description' => 'Returnable large bottle'],
            ['sku' => 'VAL-1L', 'name' => 'Valley Drinking Water 1L', 'unit' => 'case', 'size' => '12 x 1L', 'description' => 'One litre bottle case'],
            ['sku' => 'VAL-500', 'name' => 'Valley Drinking Water 500ml', 'unit' => 'case', 'size' => '24 x 500ml', 'description' => 'Half litre bottle case'],
        ] as $product) {
            DB::table('products')->updateOrInsert(['sku' => $product['sku']], $product + ['brand_id' => $valleyBrandId, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        foreach ([
            ['code' => 'RTL', 'name' => 'Retail', 'currency' => 'MMK', 'is_default' => false],
            ['code' => 'WSL', 'name' => 'Wholesale', 'currency' => 'MMK', 'is_default' => true],
            ['code' => 'SPC', 'name' => 'Special', 'currency' => 'MMK', 'is_default' => false],
        ] as $priceType) {
            DB::table('price_types')->updateOrInsert(['code' => $priceType['code']], $priceType + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        $prices = [
            'VAL-5G' => ['RTL' => 3500, 'WSL' => 3000, 'SPC' => 2800],
            'VAL-1L' => ['RTL' => 9000, 'WSL' => 8200, 'SPC' => 7800],
            'VAL-500' => ['RTL' => 10500, 'WSL' => 9600, 'SPC' => 9200],
        ];
        foreach ($prices as $sku => $typePrices) {
            $productId = DB::table('products')->where('sku', $sku)->value('id');
            foreach ($typePrices as $typeCode => $amount) {
                $priceTypeId = DB::table('price_types')->where('code', $typeCode)->value('id');
                DB::table('product_prices')->updateOrInsert(
                    ['product_id' => $productId, 'price_type_id' => $priceTypeId, 'effective_from' => '2026-08-01'],
                    ['amount' => $amount, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]
                );
            }
        }

        $mainWarehouseId = DB::table('warehouses')->where('code', 'WH-TGI')->value('id');
        $namSanWarehouseId = DB::table('warehouses')->where('code', 'WH-NSN')->value('id');
        $stockSeedRows = [
            ['code' => 'OPN-202608-0001', 'warehouse_id' => $mainWarehouseId, 'product_sku' => 'VAL-5G', 'movement_type' => 'opening', 'movement_date' => '2026-08-01', 'quantity' => 240, 'signed_quantity' => 240, 'unit_cost' => 1800, 'total_cost' => 432000, 'notes' => 'Seeded Phase 5 opening stock'],
            ['code' => 'RCV-202608-0001', 'warehouse_id' => $mainWarehouseId, 'product_sku' => 'VAL-1L', 'movement_type' => 'receive', 'movement_date' => '2026-08-15', 'quantity' => 80, 'signed_quantity' => 80, 'unit_cost' => 5200, 'total_cost' => 416000, 'notes' => 'Seeded Phase 5 stock receive'],
            ['code' => 'OPN-202608-0002', 'warehouse_id' => $namSanWarehouseId, 'product_sku' => 'VAL-5G', 'movement_type' => 'opening', 'movement_date' => '2026-08-01', 'quantity' => 120, 'signed_quantity' => 120, 'unit_cost' => 1850, 'total_cost' => 222000, 'notes' => 'Seeded Phase 5 opening stock'],
        ];

        foreach ($stockSeedRows as $stockMovement) {
            $productSku = $stockMovement['product_sku'];
            unset($stockMovement['product_sku']);
            $stockMovement['document_code'] = $stockMovement['code'];
            $stockMovement['product_id'] = DB::table('products')->where('sku', $productSku)->value('id');
            DB::table('stock_movements')->updateOrInsert(
                ['code' => $stockMovement['code']],
                $stockMovement + ['created_by' => null, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        foreach ([
            ['warehouse_id' => $mainWarehouseId, 'product_sku' => 'VAL-5G', 'quantity' => 240, 'average_cost' => 1800, 'stock_value' => 432000],
            ['warehouse_id' => $mainWarehouseId, 'product_sku' => 'VAL-1L', 'quantity' => 80, 'average_cost' => 5200, 'stock_value' => 416000],
            ['warehouse_id' => $namSanWarehouseId, 'product_sku' => 'VAL-5G', 'quantity' => 120, 'average_cost' => 1850, 'stock_value' => 222000],
        ] as $stockBalance) {
            $productSku = $stockBalance['product_sku'];
            unset($stockBalance['product_sku']);
            $stockBalance['product_id'] = DB::table('products')->where('sku', $productSku)->value('id');
            DB::table('stock_balances')->updateOrInsert(
                ['warehouse_id' => $stockBalance['warehouse_id'], 'product_id' => $stockBalance['product_id']],
                $stockBalance + ['last_movement_at' => '2026-08-15 09:00:00', 'created_at' => $now, 'updated_at' => $now]
            );
        }

        $tgiNorthRouteId = DB::table('routes')->where('code', 'TGI-N')->value('id');
        $tgiMarketRouteId = DB::table('routes')->where('code', 'TGI-M')->value('id');
        $wholesalePriceId = DB::table('price_types')->where('code', 'WSL')->value('id');

        foreach ([
            ['code' => 'EMP-001', 'name' => 'Office Demo', 'employee_type' => 'office', 'phone' => '09 420 000 002', 'email' => 'office@valley.test', 'assigned_route_id' => null, 'hire_date' => '2024-01-10', 'address' => 'Taunggyi'],
            ['code' => 'SAL-001', 'name' => 'Sales Demo', 'employee_type' => 'sales', 'phone' => '09 420 000 004', 'email' => 'sales@valley.test', 'assigned_route_id' => $tgiNorthRouteId, 'hire_date' => '2024-03-15', 'address' => 'Taunggyi'],
            ['code' => 'SAL-002', 'name' => 'Nandar Aye', 'employee_type' => 'sales', 'phone' => '09 420 100 004', 'email' => 'nandar@valley.test', 'assigned_route_id' => $tgiMarketRouteId, 'hire_date' => '2025-02-01', 'address' => 'Taunggyi'],
            ['code' => 'SUP-001', 'name' => 'Sales Supervisor Demo', 'employee_type' => 'sales_supervisor', 'phone' => '09 420 300 001', 'email' => 'sales.supervisor@valley.test', 'assigned_route_id' => null, 'hire_date' => '2024-02-01', 'address' => 'Taunggyi'],
            ['code' => 'DRV-001', 'name' => 'Driver Demo', 'employee_type' => 'driver', 'phone' => '09 420 000 005', 'email' => 'driver@valley.test', 'assigned_route_id' => $tgiNorthRouteId, 'hire_date' => '2024-06-01', 'address' => 'Taunggyi'],
            ['code' => 'WH-001', 'name' => 'Ko Min Thu', 'employee_type' => 'warehouse', 'phone' => '09 420 200 001', 'email' => 'warehouse@valley.test', 'assigned_route_id' => null, 'hire_date' => '2023-11-10', 'address' => 'Taunggyi'],
        ] as $employee) {
            DB::table('employees')->updateOrInsert(['code' => $employee['code']], $employee + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        $salesSupervisorId = DB::table('employees')->where('code', 'SUP-001')->value('id');
        DB::table('employees')
            ->whereIn('code', ['SAL-001', 'SAL-002'])
            ->update(['supervisor_id' => $salesSupervisorId, 'updated_at' => $now]);

        DB::table('attendance_locations')->updateOrInsert(['code' => 'ATT-OFFICE'], [
            'warehouse_id' => $mainWarehouseId,
            'name' => 'Taunggyi Office',
            'address' => 'East Circular Road, Taunggyi',
            'latitude' => 20.7892000,
            'longitude' => 97.0378000,
            'allowed_radius_m' => 20,
            'public_token' => 'demo-taunggyi-office-attendance-token-2026',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $attendanceLocationId = DB::table('attendance_locations')->where('code', 'ATT-OFFICE')->value('id');
        $salesEmployeeId = DB::table('employees')->where('code', 'SAL-001')->value('id');

        $driverEmployeeId = DB::table('employees')->where('code', 'DRV-001')->value('id');
        DB::table('attendance_records')->updateOrInsert([
            'attendance_location_id' => $attendanceLocationId,
            'employee_id' => $salesEmployeeId,
            'attendance_at' => '2026-08-17 08:25:00',
        ], [
            'entered_employee_code' => 'SAL-001',
            'submitted_token' => 'demo-taunggyi-office-attendance-token-2026',
            'latitude' => 20.7892000,
            'longitude' => 97.0378000,
            'distance_m' => 0,
            'status' => 'accepted',
            'rejection_reason' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('attendance_records')->updateOrInsert([
            'attendance_location_id' => $attendanceLocationId,
            'employee_id' => $driverEmployeeId,
            'attendance_at' => '2026-08-17 08:32:00',
        ], [
            'entered_employee_code' => 'DRV-001',
            'submitted_token' => 'demo-taunggyi-office-attendance-token-2026',
            'latitude' => null,
            'longitude' => null,
            'distance_m' => null,
            'status' => 'rejected',
            'rejection_reason' => 'gps_denied',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ([
            ['employee_id' => $salesEmployeeId, 'adjustment_type' => 'allowance', 'title' => 'Route allowance', 'amount' => 50000, 'effective_date' => '2026-08-17', 'notes' => 'Monthly route allowance'],
            ['employee_id' => $salesEmployeeId, 'adjustment_type' => 'incentive', 'title' => 'Collection incentive', 'amount' => 20000, 'effective_date' => '2026-08-17', 'notes' => 'Demo collection incentive'],
            ['employee_id' => $driverEmployeeId, 'adjustment_type' => 'advance', 'title' => 'Fuel advance recovery', 'amount' => 30000, 'effective_date' => '2026-08-17', 'notes' => 'Deduct from monthly payroll'],
            ['employee_id' => $driverEmployeeId, 'adjustment_type' => 'ot', 'title' => 'Weekend delivery OT', 'amount' => 15000, 'effective_date' => '2026-08-17', 'notes' => 'Demo overtime'],
        ] as $adjustment) {
            DB::table('payroll_adjustments')->updateOrInsert(
                [
                    'employee_id' => $adjustment['employee_id'],
                    'adjustment_type' => $adjustment['adjustment_type'],
                    'title' => $adjustment['title'],
                    'effective_date' => $adjustment['effective_date'],
                ],
                $adjustment + ['status' => 'active', 'created_at' => $now, 'updated_at' => $now]
            );
        }

        foreach ([
            [
                'payroll' => ['code' => 'PAY-202607-SALES', 'month' => '2026-07', 'period_start' => '2026-07-01', 'period_end' => '2026-07-31', 'employee_type' => 'sales', 'total_gross' => 425000, 'total_deductions' => 0, 'total_net' => 425000, 'payment_reference' => 'KBZ-202607-SALES'],
                'item' => ['employee_id' => $salesEmployeeId, 'employee_code' => 'SAL-001', 'employee_name' => 'Sales Demo', 'employee_type' => 'sales', 'accepted_count' => 24, 'rejected_count' => 1, 'gps_denied_count' => 0, 'outside_radius_count' => 1, 'base_salary' => 380000, 'allowance_amount' => 30000, 'incentive_amount' => 15000, 'ot_amount' => 0, 'advance_deduction' => 0, 'other_deduction' => 0, 'gross_pay' => 425000, 'net_pay' => 425000],
            ],
            [
                'payroll' => ['code' => 'PAY-202607-DRIVER', 'month' => '2026-07', 'period_start' => '2026-07-01', 'period_end' => '2026-07-31', 'employee_type' => 'driver', 'total_gross' => 375000, 'total_deductions' => 25000, 'total_net' => 350000, 'payment_reference' => 'CASH-202607-DRIVER'],
                'item' => ['employee_id' => $driverEmployeeId, 'employee_code' => 'DRV-001', 'employee_name' => 'Driver Demo', 'employee_type' => 'driver', 'accepted_count' => 25, 'rejected_count' => 0, 'gps_denied_count' => 0, 'outside_radius_count' => 0, 'base_salary' => 360000, 'allowance_amount' => 0, 'incentive_amount' => 0, 'ot_amount' => 15000, 'advance_deduction' => 25000, 'other_deduction' => 0, 'gross_pay' => 375000, 'net_pay' => 350000],
            ],
        ] as $salaryHistory) {
            DB::table('payrolls')->updateOrInsert(
                ['code' => $salaryHistory['payroll']['code']],
                $salaryHistory['payroll'] + [
                    'status' => 'paid',
                    'generated_by' => null,
                    'approved_by' => null,
                    'approved_at' => '2026-07-31 16:00:00',
                    'paid_by' => null,
                    'paid_at' => '2026-08-01 10:00:00',
                    'notes' => 'Seeded mobile salary history',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $payrollId = DB::table('payrolls')->where('code', $salaryHistory['payroll']['code'])->value('id');
            DB::table('payroll_items')->updateOrInsert(
                ['payroll_id' => $payrollId, 'employee_id' => $salaryHistory['item']['employee_id']],
                $salaryHistory['item'] + [
                    'first_attendance_at' => $salaryHistory['payroll']['period_start'].' 08:30:00',
                    'last_attendance_at' => $salaryHistory['payroll']['period_end'].' 17:30:00',
                    'remarks' => 'Seeded mobile salary history',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        foreach ([
            ['code' => 'CUS-0001', 'shop_name' => 'Shwe Family Store', 'contact_name' => 'Daw Mya Mya', 'phone' => '09 450 001 001', 'email' => 'client@valley.test', 'address' => 'North Market Road', 'route_id' => $tgiNorthRouteId, 'area_id' => $tgiAreaId, 'credit_limit' => 500000],
            ['code' => 'CUS-0002', 'shop_name' => 'Cherry Mini Mart', 'contact_name' => 'Ma Cherry', 'phone' => '09 450 001 002', 'email' => 'cherry@example.test', 'address' => 'Yadanar Street', 'route_id' => $tgiNorthRouteId, 'area_id' => $tgiAreaId, 'credit_limit' => 300000],
            ['code' => 'CUS-0003', 'shop_name' => 'Golden Hill Shop', 'contact_name' => 'U Aung Win', 'phone' => '09 450 001 003', 'email' => null, 'address' => 'Hill View Quarter', 'route_id' => $tgiNorthRouteId, 'area_id' => $tgiAreaId, 'credit_limit' => 250000],
            ['code' => 'CUS-0004', 'shop_name' => 'Central Grocery', 'contact_name' => 'Daw Nilar', 'phone' => '09 450 001 004', 'email' => null, 'address' => 'Central Market', 'route_id' => $tgiMarketRouteId, 'area_id' => $tgiAreaId, 'credit_limit' => 400000],
        ] as $customer) {
            DB::table('customers')->updateOrInsert(['code' => $customer['code']], $customer + ['price_type_id' => $wholesalePriceId, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        $demoCustomerId = DB::table('customers')->where('code', 'CUS-0001')->value('id');
        $demoProductId = DB::table('products')->where('sku', 'VAL-5G')->value('id');
        DB::table('orders')->updateOrInsert(
            ['code' => 'ORD-202608-0001'],
            [
                'customer_id' => $demoCustomerId,
                'route_id' => $tgiNorthRouteId,
                'price_type_id' => $wholesalePriceId,
                'source_app' => 'office',
                'order_date' => '2026-08-17',
                'requested_delivery_date' => '2026-08-18',
                'payment_type' => 'credit',
                'status' => 'pending',
                'subtotal' => 72000,
                'discount_total' => 0,
                'tax_total' => 0,
                'total' => 72000,
                'created_by' => null,
                'confirmed_by' => null,
                'confirmed_at' => null,
                'notes' => 'Seeded Phase 4 demo order',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
        $demoOrderId = DB::table('orders')->where('code', 'ORD-202608-0001')->value('id');
        DB::table('order_items')->updateOrInsert(
            ['order_id' => $demoOrderId, 'product_id' => $demoProductId],
            [
                'product_sku' => 'VAL-5G',
                'product_name' => 'Valley 5 Gallon Water',
                'unit' => 'bottle',
                'item_type' => 'sale',
                'quantity' => 24,
                'unit_price' => 3000,
                'discount_amount' => 0,
                'line_total' => 72000,
                'remarks' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        DB::table('orders')->updateOrInsert(
            ['code' => 'ORD-202608-0002'],
            [
                'customer_id' => $demoCustomerId,
                'route_id' => $tgiNorthRouteId,
                'price_type_id' => $wholesalePriceId,
                'source_app' => 'office',
                'order_date' => '2026-08-16',
                'requested_delivery_date' => '2026-08-17',
                'payment_type' => 'credit',
                'credit_due_date' => '2026-08-24',
                'status' => 'invoiced',
                'subtotal' => 36000,
                'discount_total' => 0,
                'tax_total' => 0,
                'total' => 36000,
                'created_by' => null,
                'confirmed_by' => null,
                'confirmed_at' => '2026-08-16 09:30:00',
                'notes' => 'Seeded Phase 4 invoiced order',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
        $demoInvoiceOrderId = DB::table('orders')->where('code', 'ORD-202608-0002')->value('id');
        DB::table('order_items')->updateOrInsert(
            ['order_id' => $demoInvoiceOrderId, 'product_id' => $demoProductId],
            [
                'product_sku' => 'VAL-5G',
                'product_name' => 'Valley 5 Gallon Water',
                'unit' => 'bottle',
                'item_type' => 'sale',
                'quantity' => 12,
                'unit_price' => 3000,
                'discount_amount' => 0,
                'line_total' => 36000,
                'remarks' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
        DB::table('invoices')->updateOrInsert(
            ['code' => 'INV-202608-0001'],
            [
                'order_id' => $demoInvoiceOrderId,
                'customer_id' => $demoCustomerId,
                'invoice_date' => '2026-08-17',
                'due_date' => '2026-08-24',
                'status' => 'draft',
                'subtotal' => 36000,
                'discount_total' => 0,
                'tax_total' => 0,
                'total' => 36000,
                'created_by' => null,
                'notes' => 'Seeded Phase 4 demo invoice',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
        $demoInvoiceId = DB::table('invoices')->where('code', 'INV-202608-0001')->value('id');
        DB::table('invoice_items')->updateOrInsert(
            ['invoice_id' => $demoInvoiceId, 'product_id' => $demoProductId],
            [
                'product_sku' => 'VAL-5G',
                'product_name' => 'Valley 5 Gallon Water',
                'unit' => 'bottle',
                'quantity' => 12,
                'unit_price' => 3000,
                'discount_amount' => 0,
                'line_total' => 36000,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        foreach ([
            [
                'code' => 'RET-202608-0001',
                'type' => 'sales_return',
                'date' => '2026-08-18',
                'quantity' => 2,
                'total' => 6000,
                'notes' => 'Seeded Phase 4 returned bottles from customer',
                'remarks' => 'Customer returned unopened bottles',
            ],
            [
                'code' => 'DMG-202608-0001',
                'type' => 'damage',
                'date' => '2026-08-18',
                'quantity' => 1,
                'total' => 3000,
                'notes' => 'Seeded Phase 4 damaged goods entry',
                'remarks' => 'Bottle damaged during handling',
            ],
        ] as $adjustment) {
            DB::table('orders')->updateOrInsert(
                ['code' => $adjustment['code']],
                [
                    'customer_id' => $demoCustomerId,
                    'route_id' => $tgiNorthRouteId,
                    'price_type_id' => $wholesalePriceId,
                    'source_app' => 'office',
                    'order_date' => $adjustment['date'],
                    'requested_delivery_date' => null,
                    'payment_type' => 'credit',
                    'status' => 'confirmed',
                    'subtotal' => $adjustment['total'],
                    'discount_total' => 0,
                    'tax_total' => 0,
                    'total' => $adjustment['total'],
                    'created_by' => null,
                    'confirmed_by' => null,
                    'confirmed_at' => $adjustment['date'].' 10:00:00',
                    'notes' => $adjustment['notes'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $adjustmentOrderId = DB::table('orders')->where('code', $adjustment['code'])->value('id');
            DB::table('order_items')->updateOrInsert(
                ['order_id' => $adjustmentOrderId, 'product_id' => $demoProductId],
                [
                    'product_sku' => 'VAL-5G',
                    'product_name' => 'Valley 5 Gallon Water',
                    'unit' => 'bottle',
                    'item_type' => $adjustment['type'],
                    'quantity' => $adjustment['quantity'],
                    'unit_price' => 3000,
                    'discount_amount' => 0,
                    'line_total' => $adjustment['total'],
                    'remarks' => $adjustment['remarks'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        foreach ([
            ['code' => 'VEH-001', 'plate_no' => 'SHN-7K/2040', 'vehicle_type' => 'truck', 'make' => 'Isuzu', 'model' => 'NPR', 'capacity' => 240, 'assigned_driver_id' => $driverEmployeeId],
            ['code' => 'VEH-002', 'plate_no' => 'SHN-5M/1188', 'vehicle_type' => 'van', 'make' => 'Toyota', 'model' => 'Hiace', 'capacity' => 100, 'assigned_driver_id' => null],
        ] as $vehicle) {
            DB::table('vehicles')->updateOrInsert(['code' => $vehicle['code']], $vehicle + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        $deliveryCustomerId = DB::table('customers')->where('code', 'CUS-0002')->value('id');
        DB::table('orders')->updateOrInsert(['code' => 'ORD-202608-0003'], [
            'customer_id' => $deliveryCustomerId, 'route_id' => $tgiNorthRouteId, 'price_type_id' => $wholesalePriceId,
            'source_app' => 'office', 'order_date' => '2026-08-15', 'requested_delivery_date' => '2026-08-18',
            'payment_type' => 'credit', 'status' => 'assigned', 'subtotal' => 54000, 'discount_total' => 0,
            'tax_total' => 0, 'total' => 54000, 'notes' => 'Seeded Phase 6 delivery order', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $deliveryOrderId = DB::table('orders')->where('code', 'ORD-202608-0003')->value('id');
        DB::table('order_items')->updateOrInsert(['order_id' => $deliveryOrderId, 'product_id' => $demoProductId], [
            'product_sku' => 'VAL-5G', 'product_name' => 'Valley 5 Gallon Water', 'unit' => 'bottle', 'item_type' => 'sale',
            'quantity' => 18, 'unit_price' => 3000, 'discount_amount' => 0, 'line_total' => 54000, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('invoices')->updateOrInsert(['code' => 'INV-202608-0002'], [
            'order_id' => $deliveryOrderId, 'customer_id' => $deliveryCustomerId, 'invoice_date' => '2026-08-15', 'due_date' => '2026-08-22',
            'status' => 'issued', 'subtotal' => 54000, 'discount_total' => 0, 'tax_total' => 0, 'total' => 54000,
            'notes' => 'Seeded Phase 6 issued invoice', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $deliveryInvoiceId = DB::table('invoices')->where('code', 'INV-202608-0002')->value('id');
        DB::table('invoice_items')->updateOrInsert(['invoice_id' => $deliveryInvoiceId, 'product_id' => $demoProductId], [
            'product_sku' => 'VAL-5G', 'product_name' => 'Valley 5 Gallon Water', 'unit' => 'bottle', 'quantity' => 18,
            'unit_price' => 3000, 'discount_amount' => 0, 'line_total' => 54000, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('deliveries')->updateOrInsert(['code' => 'DEL-202608-0001'], [
            'invoice_id' => $deliveryInvoiceId, 'order_id' => $deliveryOrderId, 'customer_id' => $deliveryCustomerId,
            'warehouse_id' => $mainWarehouseId, 'route_id' => $tgiNorthRouteId, 'driver_id' => $driverEmployeeId,
            'vehicle_id' => DB::table('vehicles')->where('code', 'VEH-001')->value('id'), 'planned_date' => '2026-08-18',
            'status' => 'assigned', 'total_quantity' => 18, 'assigned_at' => '2026-08-17 13:00:00',
            'delivery_address' => 'North Route, Taunggyi', 'notes' => 'Seeded Phase 6 delivery assignment', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $deliveryId = DB::table('deliveries')->where('code', 'DEL-202608-0001')->value('id');
        DB::table('delivery_items')->updateOrInsert(['delivery_id' => $deliveryId, 'invoice_item_id' => DB::table('invoice_items')->where('invoice_id', $deliveryInvoiceId)->value('id')], [
            'product_id' => $demoProductId, 'product_sku' => 'VAL-5G', 'product_name' => 'Valley 5 Gallon Water',
            'unit' => 'bottle', 'planned_quantity' => 18, 'loaded_quantity' => 0, 'delivered_quantity' => 0,
            'returned_quantity' => 0, 'damaged_quantity' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);

        foreach ([
            [
                'order_code' => 'ORD-202608-0004', 'invoice_code' => 'INV-202608-0003', 'delivery_code' => 'DEL-202608-0002',
                'customer_code' => 'CUS-0001', 'planned_date' => '2026-08-17', 'quantity' => 6, 'unit_price' => 0,
                'order_status' => 'delivering', 'invoice_status' => 'issued', 'delivery_status' => 'on_route',
                'loaded_quantity' => 6, 'delivered_quantity' => 0, 'departed_at' => '2026-08-17 13:30:00', 'completed_at' => null,
                'notes' => 'Seeded client delivery with live driver tracking',
            ],
            [
                'order_code' => 'ORD-202608-0005', 'invoice_code' => 'INV-202608-0004', 'delivery_code' => 'DEL-202608-0003',
                'customer_code' => 'CUS-0003', 'planned_date' => '2026-08-16', 'quantity' => 9, 'unit_price' => 3000,
                'order_status' => 'delivered', 'invoice_status' => 'delivered', 'delivery_status' => 'delivered',
                'loaded_quantity' => 9, 'delivered_quantity' => 9, 'departed_at' => '2026-08-16 09:00:00', 'completed_at' => '2026-08-16 10:15:00',
                'notes' => 'Seeded completed delivery history',
            ],
        ] as $deliveryDemo) {
            $customerId = DB::table('customers')->where('code', $deliveryDemo['customer_code'])->value('id');
            $total = $deliveryDemo['quantity'] * $deliveryDemo['unit_price'];
            DB::table('orders')->updateOrInsert(['code' => $deliveryDemo['order_code']], [
                'customer_id' => $customerId, 'route_id' => $tgiNorthRouteId, 'price_type_id' => $wholesalePriceId,
                'source_app' => 'office', 'order_date' => $deliveryDemo['planned_date'], 'requested_delivery_date' => $deliveryDemo['planned_date'],
                'payment_type' => $total > 0 ? 'credit' : 'cash', 'status' => $deliveryDemo['order_status'], 'subtotal' => $total,
                'discount_total' => 0, 'tax_total' => 0, 'total' => $total, 'notes' => $deliveryDemo['notes'], 'created_at' => $now, 'updated_at' => $now,
            ]);
            $orderId = DB::table('orders')->where('code', $deliveryDemo['order_code'])->value('id');
            DB::table('order_items')->updateOrInsert(['order_id' => $orderId, 'product_id' => $demoProductId], [
                'product_sku' => 'VAL-5G', 'product_name' => 'Valley 5 Gallon Water', 'unit' => 'bottle', 'item_type' => $total > 0 ? 'sale' : 'foc',
                'quantity' => $deliveryDemo['quantity'], 'unit_price' => $deliveryDemo['unit_price'], 'discount_amount' => 0,
                'line_total' => $total, 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('invoices')->updateOrInsert(['code' => $deliveryDemo['invoice_code']], [
                'order_id' => $orderId, 'customer_id' => $customerId, 'invoice_date' => $deliveryDemo['planned_date'],
                'due_date' => $deliveryDemo['planned_date'], 'status' => $deliveryDemo['invoice_status'], 'subtotal' => $total,
                'discount_total' => 0, 'tax_total' => 0, 'total' => $total, 'notes' => $deliveryDemo['notes'], 'created_at' => $now, 'updated_at' => $now,
            ]);
            $invoiceId = DB::table('invoices')->where('code', $deliveryDemo['invoice_code'])->value('id');
            DB::table('invoice_items')->updateOrInsert(['invoice_id' => $invoiceId, 'product_id' => $demoProductId], [
                'product_sku' => 'VAL-5G', 'product_name' => 'Valley 5 Gallon Water', 'unit' => 'bottle',
                'quantity' => $deliveryDemo['quantity'], 'unit_price' => $deliveryDemo['unit_price'], 'discount_amount' => 0,
                'line_total' => $total, 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('deliveries')->updateOrInsert(['code' => $deliveryDemo['delivery_code']], [
                'invoice_id' => $invoiceId, 'order_id' => $orderId, 'customer_id' => $customerId, 'warehouse_id' => $mainWarehouseId,
                'route_id' => $tgiNorthRouteId, 'driver_id' => $driverEmployeeId, 'vehicle_id' => DB::table('vehicles')->where('code', 'VEH-001')->value('id'),
                'planned_date' => $deliveryDemo['planned_date'], 'status' => $deliveryDemo['delivery_status'], 'total_quantity' => $deliveryDemo['quantity'],
                'loaded_quantity' => $deliveryDemo['loaded_quantity'], 'delivered_quantity' => $deliveryDemo['delivered_quantity'],
                'assigned_at' => $deliveryDemo['planned_date'].' 08:30:00', 'loaded_at' => $deliveryDemo['planned_date'].' 08:45:00',
                'departed_at' => $deliveryDemo['departed_at'], 'completed_at' => $deliveryDemo['completed_at'],
                'delivery_address' => 'North Route, Taunggyi', 'notes' => $deliveryDemo['notes'], 'created_at' => $now, 'updated_at' => $now,
            ]);
            $demoDeliveryId = DB::table('deliveries')->where('code', $deliveryDemo['delivery_code'])->value('id');
            DB::table('delivery_items')->updateOrInsert(['delivery_id' => $demoDeliveryId, 'invoice_item_id' => DB::table('invoice_items')->where('invoice_id', $invoiceId)->value('id')], [
                'product_id' => $demoProductId, 'product_sku' => 'VAL-5G', 'product_name' => 'Valley 5 Gallon Water', 'unit' => 'bottle',
                'planned_quantity' => $deliveryDemo['quantity'], 'loaded_quantity' => $deliveryDemo['loaded_quantity'],
                'delivered_quantity' => $deliveryDemo['delivered_quantity'], 'returned_quantity' => 0, 'damaged_quantity' => 0,
                'created_at' => $now, 'updated_at' => $now,
            ]);

            if ($deliveryDemo['delivery_status'] === 'on_route') {
                DB::table('delivery_locations')->updateOrInsert(['delivery_id' => $demoDeliveryId], [
                    'driver_id' => $driverEmployeeId, 'latitude' => 20.7892000, 'longitude' => 97.0378000,
                    'accuracy_m' => 7.5, 'heading' => 32, 'speed_kmh' => 18, 'recorded_at' => $now,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        $users = [
            ['name' => 'Office Demo', 'email' => 'office@valley.test', 'phone' => '09 420 000 002', 'role' => 'Office Staff', 'employee_id' => DB::table('employees')->where('code', 'EMP-001')->value('id')],
            ['name' => 'Customer Demo', 'email' => 'client@valley.test', 'phone' => '09 420 000 003', 'role' => 'Customer', 'customer_id' => DB::table('customers')->where('code', 'CUS-0001')->value('id')],
            ['name' => 'Sales Demo', 'email' => 'sales@valley.test', 'phone' => '09 420 000 004', 'role' => 'Sales Representative', 'employee_id' => DB::table('employees')->where('code', 'SAL-001')->value('id')],
            ['name' => 'Sales Supervisor Demo', 'email' => 'sales.supervisor@valley.test', 'phone' => '09 420 300 001', 'role' => 'Sales Supervisor', 'employee_id' => DB::table('employees')->where('code', 'SUP-001')->value('id')],
            ['name' => 'Driver Demo', 'email' => 'driver@valley.test', 'phone' => '09 420 000 005', 'role' => 'Driver', 'employee_id' => $driverEmployeeId],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                $user + ['locale' => 'en', 'password' => Hash::make('password')]
            );
        }

        $officeUserId = User::where('email', 'office@valley.test')->value('id');
        $salesUserId = User::where('email', 'sales@valley.test')->value('id');
        $driverUserId = User::where('email', 'driver@valley.test')->value('id');

        // KPI profile creation cannot rely on the migration because a clean
        // install runs migrations before demo employees are seeded. Assign the
        // workbook defaults after employees exist so fresh installs and test
        // databases have the same usable KPI setup as upgraded databases.
        foreach ([
            'sales' => 'SALES-REP-V1',
            'sales_supervisor' => 'SALES-SUPERVISOR-V1',
            'driver' => 'DRIVER-V1',
            'warehouse' => 'STOREKEEPER-V1',
        ] as $employeeType => $templateCode) {
            $template = DB::table('kpi_templates')->where('code', $templateCode)->first();
            if (! $template) {
                continue;
            }

            $metrics = DB::table('kpi_template_metrics')
                ->where('kpi_template_id', $template->id)
                ->where('calculation_type', '!=', 'manual')
                ->whereNotNull('default_target')
                ->get(['id', 'default_target']);

            $employees = DB::table('employees')
                ->where('employee_type', $employeeType)
                ->where('is_active', true)
                ->get(['id']);

            foreach ($employees as $employee) {
                DB::table('kpi_staff_profiles')->updateOrInsert(
                    ['employee_id' => $employee->id],
                    [
                        'kpi_template_id' => $template->id,
                        'target_bonus' => $template->target_bonus,
                        'updated_by' => $officeUserId,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );

                $profileId = DB::table('kpi_staff_profiles')
                    ->where('employee_id', $employee->id)
                    ->value('id');

                foreach ($metrics as $metric) {
                    DB::table('kpi_staff_target_items')->updateOrInsert(
                        [
                            'kpi_staff_profile_id' => $profileId,
                            'kpi_template_metric_id' => $metric->id,
                        ],
                        [
                            'target_value' => $metric->default_target,
                            'updated_at' => $now,
                            'created_at' => $now,
                        ]
                    );
                }
            }
        }

        DB::table('uat_issues')->updateOrInsert(['code' => 'UAT-202608-0001'], [
            'module' => 'delivery',
            'title' => 'Verify GPS sharing on a business Android phone',
            'steps' => 'Start the assigned route, share location points, complete delivery, and confirm tracking stops.',
            'severity' => 'medium',
            'status' => 'open',
            'assigned_to' => $officeUserId,
            'resolution' => null,
            'created_by' => $officeUserId,
            'updated_by' => $officeUserId,
            'resolved_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('audit_logs')->updateOrInsert(
            ['path' => '/api/orders/1/confirm', 'entity_type' => 'orders', 'entity_id' => 1],
            ['user_id' => $officeUserId, 'user_name' => 'Office Demo', 'app' => 'office', 'action' => 'workflow', 'method' => 'POST', 'status_code' => 200, 'changes' => json_encode(['status' => 'confirmed']), 'ip_address' => '127.0.0.1', 'user_agent' => 'Seeded UAT event', 'duration_ms' => 42, 'created_at' => $now]
        );
        $clientCustomerId = DB::table('customers')->where('code', 'CUS-0001')->value('id');
        $cherryCustomerId = DB::table('customers')->where('code', 'CUS-0002')->value('id');
        $salesEmployeeId = DB::table('employees')->where('code', 'SAL-001')->value('id');

        DB::table('sales_targets')->updateOrInsert(
            ['employee_id' => $salesEmployeeId, 'target_month' => '2026-08-01'],
            ['target_amount' => 150000, 'created_by' => $officeUserId, 'created_at' => $now, 'updated_at' => $now]
        );

        foreach ([
            ['code' => 'COL-202608-0001', 'customer_id' => $clientCustomerId, 'invoice_code' => 'INV-202608-0001', 'delivery_code' => null, 'employee_id' => null, 'collection_date' => '2026-08-17', 'amount' => 6000, 'payment_method' => 'cash', 'reference_no' => 'RCPT-0001', 'source_app' => 'office', 'status' => 'approved', 'notes' => 'Seeded client payment history', 'submitted_by' => $officeUserId, 'reviewed_by' => $officeUserId],
            ['code' => 'COL-202608-0002', 'customer_id' => $cherryCustomerId, 'invoice_code' => 'INV-202608-0002', 'delivery_code' => null, 'employee_id' => $salesEmployeeId, 'collection_date' => '2026-08-17', 'amount' => 12000, 'payment_method' => 'cash', 'reference_no' => 'FIELD-SALES-01', 'source_app' => 'sales', 'status' => 'submitted', 'notes' => 'Seeded sales field collection awaiting review', 'submitted_by' => $salesUserId, 'reviewed_by' => null],
            ['code' => 'COL-202608-0003', 'customer_id' => $cherryCustomerId, 'invoice_code' => 'INV-202608-0002', 'delivery_code' => 'DEL-202608-0001', 'employee_id' => $driverEmployeeId, 'collection_date' => '2026-08-17', 'amount' => 5000, 'payment_method' => 'cash', 'reference_no' => 'FIELD-DRIVER-01', 'source_app' => 'driver', 'status' => 'submitted', 'notes' => 'Seeded delivery collection awaiting review', 'submitted_by' => $driverUserId, 'reviewed_by' => null],
        ] as $collection) {
            $invoiceCode = $collection['invoice_code'];
            $deliveryCode = $collection['delivery_code'];
            unset($collection['invoice_code'], $collection['delivery_code']);
            $collection['invoice_id'] = $invoiceCode ? DB::table('invoices')->where('code', $invoiceCode)->value('id') : null;
            $collection['delivery_id'] = $deliveryCode ? DB::table('deliveries')->where('code', $deliveryCode)->value('id') : null;
            $collection['reviewed_at'] = $collection['status'] === 'approved' ? '2026-08-17 10:00:00' : null;
            DB::table('collections')->updateOrInsert(['code' => $collection['code']], $collection + ['created_at' => $now, 'updated_at' => $now]);
        }

        foreach ([
            ['code' => 'EXP-202608-0001', 'employee_id' => null, 'expense_date' => '2026-08-17', 'expense_type' => 'daily', 'category' => 'utilities', 'description' => 'Office electricity and water', 'amount' => 8000, 'payment_method' => 'cash', 'reference_no' => 'UTIL-0817', 'source_app' => 'office', 'status' => 'approved', 'notes' => 'Seeded daily expense', 'submitted_by' => $officeUserId, 'reviewed_by' => $officeUserId],
            ['code' => 'EXP-202608-0002', 'employee_id' => $salesEmployeeId, 'expense_date' => '2026-08-17', 'expense_type' => 'outdoor', 'category' => 'meals', 'description' => 'North route field lunch', 'amount' => 4500, 'payment_method' => 'cash', 'reference_no' => null, 'source_app' => 'sales', 'status' => 'submitted', 'notes' => 'Seeded sales expense awaiting review', 'submitted_by' => $salesUserId, 'reviewed_by' => null],
            ['code' => 'EXP-202608-0003', 'employee_id' => $driverEmployeeId, 'expense_date' => '2026-08-17', 'expense_type' => 'outdoor', 'category' => 'fuel', 'description' => 'Delivery route parking and fuel', 'amount' => 3000, 'payment_method' => 'cash', 'reference_no' => 'DRV-FUEL-01', 'source_app' => 'driver', 'status' => 'approved', 'notes' => 'Seeded approved driver expense', 'submitted_by' => $driverUserId, 'reviewed_by' => $officeUserId],
            ['code' => 'EXP-202608-0004', 'employee_id' => null, 'expense_date' => '2026-08-16', 'expense_type' => 'daily', 'category' => 'vehicle_cost', 'description' => 'Truck routine service', 'amount' => 25000, 'payment_method' => 'bank', 'reference_no' => 'KBZ-VEH-0816', 'source_app' => 'office', 'status' => 'approved', 'notes' => 'Seeded vehicle cost for profit and loss', 'submitted_by' => $officeUserId, 'reviewed_by' => $officeUserId],
        ] as $expense) {
            $expense['reviewed_at'] = $expense['status'] === 'approved' ? '2026-08-17 11:00:00' : null;
            DB::table('expenses')->updateOrInsert(['code' => $expense['code']], $expense + ['created_at' => $now, 'updated_at' => $now]);
        }

        foreach ([
            ['code' => 'TXN-0000001', 'transaction_date' => '2026-08-17', 'book_type' => 'cash', 'direction' => 'in', 'category' => 'collection', 'amount' => 6000, 'reference_type' => 'collection', 'reference_code' => 'COL-202608-0001', 'description' => 'Customer collection COL-202608-0001'],
            ['code' => 'TXN-0000002', 'transaction_date' => '2026-08-17', 'book_type' => 'cash', 'direction' => 'out', 'category' => 'utilities', 'amount' => 8000, 'reference_type' => 'expense', 'reference_code' => 'EXP-202608-0001', 'description' => 'Office electricity and water'],
            ['code' => 'TXN-0000003', 'transaction_date' => '2026-08-17', 'book_type' => 'cash', 'direction' => 'out', 'category' => 'fuel', 'amount' => 3000, 'reference_type' => 'expense', 'reference_code' => 'EXP-202608-0003', 'description' => 'Delivery route parking and fuel'],
            ['code' => 'TXN-0000004', 'transaction_date' => '2026-08-16', 'book_type' => 'bank', 'direction' => 'out', 'category' => 'vehicle_cost', 'amount' => 25000, 'reference_type' => 'expense', 'reference_code' => 'EXP-202608-0004', 'description' => 'Truck routine service'],
        ] as $transaction) {
            $referenceId = DB::table($transaction['reference_type'] === 'collection' ? 'collections' : 'expenses')->where('code', $transaction['reference_code'])->value('id');
            DB::table('financial_transactions')->updateOrInsert(['reference_type' => $transaction['reference_type'], 'reference_id' => $referenceId], $transaction + ['reference_id' => $referenceId, 'created_by' => $officeUserId, 'created_at' => $now, 'updated_at' => $now]);
        }

        $primaryVehicleId = DB::table('vehicles')->where('code', 'VEH-001')->value('id');
        DB::table('deliveries')->where('code', 'DEL-202608-0002')->update(['start_odometer_km' => 12480, 'end_odometer_km' => 12502, 'distance_km' => 22, 'updated_at' => $now]);
        DB::table('deliveries')->where('code', 'DEL-202608-0003')->update(['start_odometer_km' => 12440, 'end_odometer_km' => 12478.5, 'distance_km' => 38.5, 'updated_at' => $now]);

        foreach ([
            ['code' => 'VHC-202608-0001', 'cost_date' => '2026-08-03', 'cost_type' => 'fuel', 'description' => 'August diesel refill', 'vendor' => 'Taunggyi Fuel Station', 'odometer_km' => 12440, 'quantity' => 110, 'unit_price' => 772.73, 'amount' => 85000, 'payment_method' => 'cash', 'reference_no' => 'FUEL-0803', 'delivery_code' => null],
            ['code' => 'VHC-202608-0002', 'cost_date' => '2026-08-06', 'cost_type' => 'maintenance', 'description' => 'Brake inspection and adjustment', 'vendor' => 'Shan Auto Service', 'odometer_km' => 12455, 'quantity' => null, 'unit_price' => null, 'amount' => 45000, 'payment_method' => 'bank', 'reference_no' => 'SAS-0806', 'delivery_code' => null],
            ['code' => 'VHC-202608-0003', 'cost_date' => '2026-08-09', 'cost_type' => 'engine_oil', 'description' => 'Engine oil and filter change', 'vendor' => 'Shan Auto Service', 'odometer_km' => 12470, 'quantity' => 6, 'unit_price' => 3000, 'amount' => 18000, 'payment_method' => 'cash', 'reference_no' => 'OIL-0809', 'delivery_code' => null],
            ['code' => 'VHC-202608-0004', 'cost_date' => '2026-08-10', 'cost_type' => 'insurance', 'description' => 'Annual commercial vehicle insurance', 'vendor' => 'Secure Myanmar Insurance', 'odometer_km' => null, 'quantity' => null, 'unit_price' => null, 'amount' => 240000, 'payment_method' => 'bank', 'reference_no' => 'INS-2026-2040', 'delivery_code' => null],
            ['code' => 'VHC-202608-0005', 'cost_date' => '2026-08-11', 'cost_type' => 'license', 'description' => 'Vehicle license renewal', 'vendor' => 'Road Transport Administration', 'odometer_km' => null, 'quantity' => null, 'unit_price' => null, 'amount' => 60000, 'payment_method' => 'bank', 'reference_no' => 'LIC-2026-2040', 'delivery_code' => null],
            ['code' => 'VHC-202608-0006', 'cost_date' => '2026-08-13', 'cost_type' => 'tyre', 'description' => 'Two rear commercial tyres', 'vendor' => 'Golden Tyre Shop', 'odometer_km' => 12475, 'quantity' => 2, 'unit_price' => 80000, 'amount' => 160000, 'payment_method' => 'bank', 'reference_no' => 'TYRE-0813', 'delivery_code' => null],
            ['code' => 'VHC-202608-0007', 'cost_date' => '2026-08-16', 'cost_type' => 'other', 'description' => 'Route tolls and parking', 'vendor' => null, 'odometer_km' => 12478.5, 'quantity' => null, 'unit_price' => null, 'amount' => 12000, 'payment_method' => 'cash', 'reference_no' => 'TOLL-0816', 'delivery_code' => 'DEL-202608-0003'],
        ] as $vehicleCost) {
            $deliveryCode = $vehicleCost['delivery_code'];
            unset($vehicleCost['delivery_code']);
            $vehicleCost['delivery_id'] = $deliveryCode ? DB::table('deliveries')->where('code', $deliveryCode)->value('id') : null;
            DB::table('vehicle_costs')->updateOrInsert(['code' => $vehicleCost['code']], $vehicleCost + ['vehicle_id' => $primaryVehicleId, 'employee_id' => null, 'record_type' => 'cost', 'source_app' => 'office', 'status' => 'approved', 'submitted_by' => $officeUserId, 'reviewed_by' => $officeUserId, 'reviewed_at' => '2026-08-17 14:00:00', 'notes' => 'Seeded Phase 8 vehicle cost', 'created_at' => $now, 'updated_at' => $now]);
        }

        foreach ([
            ['code' => 'VHC-202608-0008', 'record_type' => 'issue', 'cost_type' => 'maintenance', 'description' => 'Rear brake makes noise under load', 'amount' => 0, 'issue_severity' => 'high', 'reference_no' => null],
            ['code' => 'VHC-202608-0009', 'record_type' => 'cost', 'cost_type' => 'fuel', 'description' => 'Emergency route fuel top-up', 'amount' => 18000, 'issue_severity' => null, 'reference_no' => 'DRV-FUEL-0817'],
        ] as $driverRecord) {
            DB::table('vehicle_costs')->updateOrInsert(['code' => $driverRecord['code']], $driverRecord + ['vehicle_id' => $primaryVehicleId, 'delivery_id' => DB::table('deliveries')->where('code', 'DEL-202608-0002')->value('id'), 'employee_id' => $driverEmployeeId, 'cost_date' => '2026-08-17', 'vendor' => null, 'odometer_km' => 12502, 'quantity' => null, 'unit_price' => null, 'payment_method' => 'cash', 'source_app' => 'driver', 'status' => 'submitted', 'submitted_by' => $driverUserId, 'reviewed_by' => null, 'reviewed_at' => null, 'notes' => 'Seeded Driver submission for Office review', 'created_at' => $now, 'updated_at' => $now]);
        }

        foreach (DB::table('vehicle_costs')->where('status', 'approved')->get() as $index => $vehicleCost) {
            DB::table('financial_transactions')->updateOrInsert(['reference_type' => 'vehicle_cost', 'reference_id' => $vehicleCost->id], [
                'code' => 'TXN-'.str_pad((string) ($index + 5), 7, '0', STR_PAD_LEFT), 'transaction_date' => $vehicleCost->cost_date,
                'book_type' => $vehicleCost->payment_method, 'direction' => 'out', 'category' => 'vehicle_cost', 'amount' => $vehicleCost->amount,
                'reference_code' => $vehicleCost->code, 'description' => $vehicleCost->description, 'created_by' => $officeUserId, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        DB::table('suppliers')->updateOrInsert(['code' => 'SUP-001'], ['name' => 'Shan Bottle Supply', 'contact_name' => 'U Kyaw Min', 'phone' => '09 430 500 001', 'email' => 'supply@example.test', 'address' => 'Industrial Zone, Taunggyi', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        $supplierId = DB::table('suppliers')->where('code', 'SUP-001')->value('id');
        foreach ([
            ['entry_date' => '2026-08-05', 'entry_type' => 'purchase', 'reference_no' => 'PO-202608-001', 'description' => 'Returnable bottle purchase', 'debit' => 0, 'credit' => 150000],
            ['entry_date' => '2026-08-12', 'entry_type' => 'payment', 'reference_no' => 'KBZ-SUP-0812', 'description' => 'Partial supplier payment', 'debit' => 50000, 'credit' => 0],
        ] as $entry) {
            DB::table('supplier_ledger_entries')->updateOrInsert(['supplier_id' => $supplierId, 'reference_no' => $entry['reference_no']], $entry + ['supplier_id' => $supplierId, 'created_by' => $officeUserId, 'created_at' => $now, 'updated_at' => $now]);
        }
    }
}
