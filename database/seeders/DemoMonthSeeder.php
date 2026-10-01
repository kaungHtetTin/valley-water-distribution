<?php

namespace Database\Seeders;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoMonthSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedMonth(CarbonImmutable::now()->subMonthNoOverflow()->format('Y-m'));
    }

    public function seedMonth(string $month): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo month seeding is limited to local and testing environments.');
        }
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) || $month < '2026-09' || $month >= CarbonImmutable::now()->format('Y-m')) {
            throw new \InvalidArgumentException('Choose a completed month from 2026-09 onward.');
        }
        foreach (['employees', 'customers', 'orders', 'invoices', 'deliveries', 'collections', 'expenses', 'supplier_invoices', 'payrolls'] as $table) {
            if (DB::table($table)->exists()) {
                throw new \RuntimeException("{$table} already contains data. Use a fresh disposable database for demo seeding.");
            }
        }

        DB::transaction(function () use ($month) {
            $this->call(DemoSeeder::class);
            $context = $this->seedOfficeAccounts();
            $this->seedAttendance($month, $context);
            $this->seedCustomerLocationsAndVisits($month, $context);
            $this->seedOrdersAndDeliveries($month, $context);
            $this->seedExpenses($month, $context);
            $this->seedVehicleCosts($month, $context);
            $this->seedSupplierPurchases($month, $context);
            $this->seedKpiAndPayroll($month, $context);
        });
    }

    private function seedOfficeAccounts(): array
    {
        $now = now();
        $staff = [
            ['EMP-HR-DEMO', 'May Hnin', 'hr@valley.test', 'HR'],
            ['EMP-ACC-DEMO', 'Ko Aung', 'accountant@valley.test', 'Accountant'],
            ['EMP-FM-DEMO', 'Daw Thida', 'finance.manager@valley.test', 'Finance Manager'],
        ];
        foreach ($staff as [$code, $name, $email, $role]) {
            DB::table('employees')->insert([
                'code' => $code, 'name' => $name, 'employee_type' => 'office',
                'email' => $email, 'hire_date' => '2025-01-10', 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            User::create([
                'name' => $name, 'email' => $email, 'role' => $role,
                'employee_id' => DB::table('employees')->where('code', $code)->value('id'),
                'locale' => 'en', 'password' => Hash::make('password'),
            ]);
        }

        return [
            'warehouse' => DB::table('warehouses')->where('code', 'WH-TGI')->value('id'),
            'route' => DB::table('routes')->where('code', 'TGI-N')->value('id'),
            'vehicle' => DB::table('vehicles')->where('code', 'VEH-001')->value('id'),
            'product' => DB::table('products')->where('sku', 'VAL-5G')->first(),
            'price_type' => DB::table('price_types')->where('code', 'WSL')->value('id'),
            'driver' => DB::table('employees')->where('code', 'DRV-001')->value('id'),
            'sales' => DB::table('employees')->where('code', 'SAL-001')->value('id'),
            'office_user' => User::where('email', 'office@valley.test')->value('id'),
            'sales_user' => User::where('email', 'sales@valley.test')->value('id'),
            'driver_user' => User::where('email', 'driver@valley.test')->value('id'),
            'hr_user' => User::where('email', 'hr@valley.test')->value('id'),
            'accountant_user' => User::where('email', 'accountant@valley.test')->value('id'),
            'manager_user' => User::where('email', 'finance.manager@valley.test')->value('id'),
        ];
    }

    private function seedAttendance(string $month, array $context): void
    {
        $location = DB::table('attendance_locations')->where('code', 'ATT-OFFICE')->first();
        $employees = DB::table('employees')->where('is_active', true)->get(['id', 'code']);
        $start = CarbonImmutable::parse("{$month}-01");
        $rows = [];
        for ($date = $start; $date->month === $start->month; $date = $date->addDay()) {
            if ($date->isWeekend()) {
                continue;
            }
            foreach ($employees as $employee) {
                $late = $date->day === 12 && $employee->code === 'DRV-001';
                $rows[] = [
                    'attendance_location_id' => $location->id, 'employee_id' => $employee->id,
                    'entered_employee_code' => $employee->code, 'submitted_token' => $location->public_token,
                    'attendance_at' => $date->format('Y-m-d').' '.($late ? '09:12:00' : '08:25:00'),
                    'latitude' => $location->latitude, 'longitude' => $location->longitude,
                    'distance_m' => 0, 'status' => 'accepted', 'rejection_reason' => null,
                    'created_at' => now(), 'updated_at' => now(),
                ];
            }
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('attendance_records')->insert($chunk);
        }
    }

    private function seedOrdersAndDeliveries(string $month, array $context): void
    {
        $start = CarbonImmutable::parse("{$month}-01");
        $customers = DB::table('customers')->whereIn('code', ['CUS-0001', 'CUS-0002', 'CUS-0003', 'CUS-0004'])->orderBy('code')->get();
        $product = $context['product'];
        $soldQuantity = 0;
        $prefix = str_replace('-', '', $month);

        for ($index = 1; $index <= 16; $index++) {
            $date = $start->addDays(min(($index - 1) * 2, $start->daysInMonth - 1));
            $deliveryDate = $date->addDay();
            $customer = $customers[($index - 1) % $customers->count()];
            $quantity = 5 + ($index % 4) * 2;
            $unitPrice = 3000;
            $total = $quantity * $unitPrice;
            $isPending = $index > 14;
            $isAssigned = $index > 12 && ! $isPending;
            $orderCode = 'DORD-'.$prefix.'-'.str_pad($index, 4, '0', STR_PAD_LEFT);
            $invoiceCode = 'DINV-'.$prefix.'-'.str_pad($index, 4, '0', STR_PAD_LEFT);
            $orderId = DB::table('orders')->insertGetId([
                'code' => $orderCode, 'customer_id' => $customer->id, 'area_id' => $customer->area_id,
                'route_id' => $customer->route_id, 'price_type_id' => $context['price_type'],
                'source_app' => $index % 3 === 0 ? 'sales' : 'office',
                'order_date' => $date->toDateString(), 'requested_delivery_date' => $deliveryDate->toDateString(),
                'payment_type' => $index % 3 === 0 ? 'cash' : 'credit',
                'credit_due_date' => $index % 3 === 0 ? null : $date->addDays(14)->toDateString(),
                'status' => $isPending ? 'pending' : ($isAssigned ? 'assigned' : 'delivered'),
                'subtotal' => $total, 'discount_total' => 0, 'tax_total' => 0, 'total' => $total,
                'created_by' => $index % 3 === 0 ? $context['sales_user'] : $context['office_user'],
                'confirmed_by' => $isPending ? null : $context['office_user'],
                'confirmed_at' => $isPending ? null : $date->format('Y-m-d 10:00:00'),
                'notes' => 'One-month demo order', 'created_at' => $date, 'updated_at' => $date,
            ]);
            DB::table('order_items')->insert([
                'order_id' => $orderId, 'product_id' => $product->id,
                'product_sku' => $product->sku, 'product_name' => $product->name, 'unit' => $product->unit,
                'item_type' => 'sale', 'quantity' => $quantity, 'unit_price' => $unitPrice,
                'discount_amount' => 0, 'line_total' => $total, 'created_at' => $date, 'updated_at' => $date,
            ]);
            if ($isPending) {
                continue;
            }

            $invoiceId = DB::table('invoices')->insertGetId([
                'code' => $invoiceCode, 'order_id' => $orderId, 'customer_id' => $customer->id,
                'area_id' => $customer->area_id, 'route_id' => $customer->route_id,
                'delivery_address' => $customer->address, 'invoice_date' => $date->toDateString(),
                'due_date' => $date->addDays(14)->toDateString(), 'status' => 'issued',
                'subtotal' => $total, 'discount_total' => 0, 'tax_total' => 0, 'total' => $total,
                'created_by' => $context['office_user'], 'created_at' => $date, 'updated_at' => $date,
            ]);
            $invoiceItemId = DB::table('invoice_items')->insertGetId([
                'invoice_id' => $invoiceId, 'product_id' => $product->id,
                'product_sku' => $product->sku, 'product_name' => $product->name, 'unit' => $product->unit,
                'quantity' => $quantity, 'unit_price' => $unitPrice, 'discount_amount' => 0,
                'line_total' => $total, 'created_at' => $date, 'updated_at' => $date,
            ]);

            $tripCode = 'DTRIP-'.$prefix.'-'.str_pad($index, 4, '0', STR_PAD_LEFT);
            $tripStatus = $isAssigned ? 'assigned' : 'delivered';
            $tripId = DB::table('delivery_trips')->insertGetId([
                'code' => $tripCode, 'warehouse_id' => $context['warehouse'], 'route_id' => $customer->route_id,
                'driver_id' => $context['driver'], 'vehicle_id' => $context['vehicle'],
                'planned_date' => $deliveryDate->toDateString(), 'status' => $tripStatus,
                'orders_count' => 1, 'total_quantity' => $quantity, 'notes' => 'One-month demo trip',
                'created_by' => $context['office_user'], 'created_at' => $date, 'updated_at' => $date,
            ]);
            $deliveryCode = 'DDEL-'.$prefix.'-'.str_pad($index, 4, '0', STR_PAD_LEFT);
            $deliveryId = DB::table('deliveries')->insertGetId([
                'code' => $deliveryCode, 'trip_id' => $tripId, 'stop_sequence' => 1,
                'invoice_id' => $invoiceId, 'order_id' => $orderId, 'customer_id' => $customer->id,
                'area_id' => $customer->area_id, 'warehouse_id' => $context['warehouse'],
                'route_id' => $customer->route_id, 'driver_id' => $context['driver'],
                'vehicle_id' => $context['vehicle'], 'planned_date' => $deliveryDate->toDateString(),
                'status' => $tripStatus, 'total_quantity' => $quantity,
                'loaded_quantity' => $isAssigned ? 0 : $quantity,
                'delivered_quantity' => $isAssigned ? 0 : $quantity, 'returned_quantity' => 0,
                'damaged_quantity' => 0, 'assigned_at' => $date->format('Y-m-d 11:00:00'),
                'settlement_method' => $isAssigned ? null : ($index % 3 === 0 ? 'cash_driver' : 'credit'),
                'settlement_amount' => $isAssigned ? 0 : $total,
                'settled_at' => $isAssigned ? null : $deliveryDate->format('Y-m-d 17:00:00'),
                'loaded_at' => $isAssigned ? null : $deliveryDate->format('Y-m-d 08:00:00'),
                'departed_at' => $isAssigned ? null : $deliveryDate->format('Y-m-d 09:00:00'),
                'completed_at' => $isAssigned ? null : $deliveryDate->format('Y-m-d 17:00:00'),
                'delivery_address' => $customer->address, 'created_by' => $context['office_user'],
                'created_at' => $date, 'updated_at' => $date,
            ]);
            DB::table('delivery_items')->insert([
                'delivery_id' => $deliveryId, 'invoice_item_id' => $invoiceItemId, 'product_id' => $product->id,
                'product_sku' => $product->sku, 'product_name' => $product->name, 'unit' => $product->unit,
                'item_type' => 'sale', 'unit_price' => $unitPrice, 'discount_amount' => 0,
                'planned_quantity' => $quantity, 'loaded_quantity' => $isAssigned ? 0 : $quantity,
                'delivered_quantity' => $isAssigned ? 0 : $quantity,
                'returned_quantity' => 0, 'damaged_quantity' => 0,
                'created_at' => $date, 'updated_at' => $date,
            ]);
            if ($isAssigned) {
                continue;
            }

            $soldQuantity += $quantity;
            DB::table('stock_movements')->insert([
                'code' => 'DISS-'.$prefix.'-'.str_pad($index, 4, '0', STR_PAD_LEFT),
                'document_code' => $deliveryCode, 'warehouse_id' => $context['warehouse'],
                'product_id' => $product->id, 'movement_type' => 'issue',
                'movement_date' => $deliveryDate->toDateString(), 'quantity' => $quantity,
                'signed_quantity' => -$quantity, 'unit_cost' => 1800, 'total_cost' => $quantity * 1800,
                'reference_type' => 'delivery', 'reference_id' => $deliveryId,
                'reference_code' => $deliveryCode, 'created_by' => $context['office_user'],
                'notes' => 'Demo delivery stock issue', 'created_at' => $deliveryDate, 'updated_at' => $deliveryDate,
            ]);

            $cashSale = $index % 3 === 0;
            $paid = $cashSale || $index % 4 === 0 ? $total : ($index % 4 === 1 ? (int) ($total / 2) : 0);
            if ($paid > 0) {
                $code = 'DCOL-'.$prefix.'-'.str_pad($index, 4, '0', STR_PAD_LEFT);
                $collectionId = DB::table('collections')->insertGetId([
                    'code' => $code, 'customer_id' => $customer->id, 'invoice_id' => $invoiceId,
                    'delivery_id' => $deliveryId, 'employee_id' => $cashSale ? $context['driver'] : null,
                    'collection_date' => $deliveryDate->toDateString(), 'amount' => $paid,
                    'payment_method' => $cashSale ? 'cash' : 'bank',
                    'reference_no' => 'DEMO-'.$code, 'source_app' => $cashSale ? 'driver' : 'office',
                    'status' => 'approved', 'notes' => $cashSale ? 'Demo driver cash handover received by Accountant' : 'Demo collection approved by Finance Manager',
                    'submitted_by' => $cashSale ? $context['driver_user'] : $context['accountant_user'],
                    'reviewed_by' => $cashSale ? $context['accountant_user'] : $context['manager_user'],
                    'reviewed_at' => $deliveryDate->format('Y-m-d 18:00:00'),
                    'created_at' => $deliveryDate, 'updated_at' => $deliveryDate,
                ]);
                $this->postBook($code, 'collection', $collectionId, $deliveryDate, $cashSale ? 'cash' : 'bank', 'in', 'collection', $paid, $context['accountant_user']);
            }
        }

        DB::table('stock_balances')->where('warehouse_id', $context['warehouse'])->where('product_id', $product->id)->update([
            'quantity' => 240 - $soldQuantity, 'stock_value' => (240 - $soldQuantity) * 1800,
            'last_movement_at' => $start->endOfMonth()->format('Y-m-d 17:00:00'), 'updated_at' => now(),
        ]);
    }

    private function seedCustomerLocationsAndVisits(string $month, array $context): void
    {
        $start = CarbonImmutable::parse("{$month}-01");
        $locations = [
            'CUS-0001' => [20.7901000, 97.0369000],
            'CUS-0002' => [20.7924000, 97.0413000],
            'CUS-0003' => [20.7867000, 97.0391000],
        ];
        foreach ($locations as $code => [$latitude, $longitude]) {
            DB::table('customers')->where('code', $code)->update([
                'latitude' => $latitude, 'longitude' => $longitude,
                'gps_accuracy_m' => 8, 'gps_captured_at' => $start->format('Y-m-d 10:00:00'),
                'updated_at' => now(),
            ]);
        }
        DB::table('sales_targets')->insert([
            'employee_id' => $context['sales'], 'target_month' => $start->toDateString(),
            'target_amount' => 300000, 'created_by' => $context['manager_user'],
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $customers = DB::table('customers')->whereIn('code', array_keys($locations))->get();
        foreach ([2, 5, 9, 12, 16, 19, 23, 26] as $day) {
            $date = $start->addDays(min($day - 1, $start->daysInMonth - 1));
            foreach ($customers as $customer) {
                DB::table('sales_route_visits')->insert([
                    'employee_id' => $context['sales'], 'route_id' => $customer->route_id,
                    'customer_id' => $customer->id, 'visit_date' => $date->toDateString(),
                    'status' => 'completed', 'started_at' => $date->format('Y-m-d 09:00:00'),
                    'started_latitude' => $customer->latitude, 'started_longitude' => $customer->longitude,
                    'started_accuracy_m' => 8, 'completed_at' => $date->format('Y-m-d 09:20:00'),
                    'completed_latitude' => $customer->latitude, 'completed_longitude' => $customer->longitude,
                    'completed_accuracy_m' => 8, 'notes' => 'Demo route visit',
                    'created_at' => $date, 'updated_at' => $date,
                ]);
            }
        }
    }

    private function seedExpenses(string $month, array $context): void
    {
        $prefix = str_replace('-', '', $month);
        $start = CarbonImmutable::parse("{$month}-01");
        $expenses = [
            [3, 'daily', 'utilities', 42000, 'bank', null, 'Office utilities', 'approved'],
            [8, 'outdoor', 'fuel', 18000, 'cash', $context['driver'], 'North route fuel', 'approved'],
            [12, 'daily', 'office_supplies', 16000, 'cash', null, 'Office supplies', 'approved'],
            [17, 'outdoor', 'meals', 9000, 'cash', $context['sales'], 'Sales route lunch', 'approved'],
            [23, 'outdoor', 'travel', 12000, 'cash', $context['driver'], 'Route parking and tolls', 'submitted'],
        ];
        foreach ($expenses as $index => [$day, $type, $category, $amount, $method, $employeeId, $description, $status]) {
            $date = $start->addDays(min($day - 1, $start->daysInMonth - 1));
            $code = 'DEXP-'.$prefix.'-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT);
            $submitter = $type === 'daily' ? $context['accountant_user'] : ($employeeId === $context['driver'] ? $context['driver_user'] : $context['sales_user']);
            $expenseId = DB::table('expenses')->insertGetId([
                'code' => $code, 'employee_id' => $employeeId, 'expense_date' => $date->toDateString(),
                'expense_type' => $type, 'category' => $category, 'description' => $description,
                'amount' => $amount, 'payment_method' => $method, 'source_app' => $type === 'daily' ? 'office' : ($employeeId === $context['driver'] ? 'driver' : 'sales'),
                'status' => $status, 'submitted_by' => $submitter,
                'reviewed_by' => $status === 'approved' ? $context['manager_user'] : null,
                'reviewed_at' => $status === 'approved' ? $date->format('Y-m-d 16:00:00') : null,
                'created_at' => $date, 'updated_at' => $date,
            ]);
            if ($status === 'approved') {
                $this->postBook($code, 'expense', $expenseId, $date, $method, 'out', $category, $amount, $context['accountant_user']);
            }
        }
    }

    private function seedVehicleCosts(string $month, array $context): void
    {
        $prefix = str_replace('-', '', $month);
        $start = CarbonImmutable::parse("{$month}-01");
        foreach ([[4, 'fuel', 55000, 'cash', 'approved'], [15, 'maintenance', 38000, 'bank', 'approved'], [24, 'fuel', 22000, 'cash', 'submitted']] as $index => [$day, $type, $amount, $method, $status]) {
            $date = $start->addDays(min($day - 1, $start->daysInMonth - 1));
            $code = 'DVHC-'.$prefix.'-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT);
            $costId = DB::table('vehicle_costs')->insertGetId([
                'code' => $code, 'vehicle_id' => $context['vehicle'], 'employee_id' => $context['driver'],
                'cost_date' => $date->toDateString(), 'record_type' => 'cost',
                'cost_type' => $type, 'description' => 'Demo vehicle '.$type,
                'vendor' => $type === 'fuel' ? 'Taunggyi Fuel Station' : 'Shan Auto Service',
                'amount' => $amount, 'payment_method' => $method, 'source_app' => 'driver',
                'status' => $status, 'submitted_by' => $context['driver_user'],
                'reviewed_by' => $status === 'approved' ? $context['manager_user'] : null,
                'reviewed_at' => $status === 'approved' ? $date->format('Y-m-d 16:00:00') : null,
                'created_at' => $date, 'updated_at' => $date,
            ]);
            if ($status === 'approved') {
                $this->postBook($code, 'vehicle_cost', $costId, $date, $method, 'out', 'vehicle_cost', $amount, $context['accountant_user']);
            }
        }
    }

    private function seedSupplierPurchases(string $month, array $context): void
    {
        $prefix = str_replace('-', '', $month);
        $start = CarbonImmutable::parse("{$month}-01");
        $supplierId = DB::table('suppliers')->where('code', 'SUP-001')->value('id');
        // Match the older demo ledger's opening 150,000 purchase and 50,000 payment
        // with payable documents so invoice totals and the account ledger agree.
        $openingInvoiceId = DB::table('supplier_invoices')->insertGetId([
            'code' => 'DPIN-202608-BASE', 'supplier_id' => $supplierId,
            'invoice_no' => 'PO-202608-001', 'invoice_date' => '2026-08-05',
            'due_date' => '2026-09-04', 'payment_terms_days' => 30,
            'total' => 150000, 'paid_amount' => 50000, 'status' => 'partial',
            'notes' => 'Opening demo payable matching the August ledger',
            'created_by' => $context['office_user'], 'created_at' => now(), 'updated_at' => now(),
        ]);
        $openingPaymentId = DB::table('supplier_payments')->insertGetId([
            'code' => 'DSPY-202608-BASE', 'supplier_id' => $supplierId,
            'supplier_invoice_id' => $openingInvoiceId, 'payment_date' => '2026-08-12',
            'amount' => 50000, 'payment_method' => 'bank', 'reference_no' => 'KBZ-SUP-0812',
            'created_by' => $context['accountant_user'], 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->postBook('DSPY-202608-BASE', 'supplier_payment', $openingPaymentId, CarbonImmutable::parse('2026-08-12'), 'bank', 'out', 'supplier_payment', 50000, $context['accountant_user']);
        foreach ([[5, 40, 1800, 36000], [19, 30, 1800, 0]] as $index => [$day, $quantity, $cost, $paid]) {
            $date = $start->addDays(min($day - 1, $start->daysInMonth - 1));
            $document = 'DREC-'.$prefix.'-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT);
            $total = $quantity * $cost;
            DB::table('stock_movements')->insert([
                'code' => $document, 'document_code' => $document, 'supplier_id' => $supplierId,
                'warehouse_id' => $context['warehouse'], 'product_id' => $context['product']->id,
                'movement_type' => 'receive', 'movement_date' => $date->toDateString(),
                'quantity' => $quantity, 'signed_quantity' => $quantity, 'unit_cost' => $cost,
                'total_cost' => $total, 'created_by' => $context['office_user'],
                'notes' => 'Demo supplier purchase', 'created_at' => $date, 'updated_at' => $date,
            ]);
            $invoiceCode = 'DPIN-'.$prefix.'-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT);
            $invoiceId = DB::table('supplier_invoices')->insertGetId([
                'code' => $invoiceCode, 'supplier_id' => $supplierId, 'stock_document_code' => $document,
                'invoice_no' => 'DEMO-'.$invoiceCode, 'invoice_date' => $date->toDateString(),
                'due_date' => $date->addDays(30)->toDateString(), 'payment_terms_days' => 30,
                'total' => $total, 'paid_amount' => $paid,
                'status' => $paid === 0 ? 'unpaid' : 'partial', 'created_by' => $context['office_user'],
                'notes' => 'One-month demo supplier invoice', 'created_at' => $date, 'updated_at' => $date,
            ]);
            DB::table('supplier_ledger_entries')->insert([
                'supplier_id' => $supplierId, 'entry_date' => $date->toDateString(),
                'entry_type' => 'purchase', 'source_type' => 'stock_receipt', 'source_key' => $document,
                'reference_no' => $invoiceCode, 'description' => 'Purchase invoice '.$invoiceCode,
                'debit' => 0, 'credit' => $total, 'created_by' => $context['office_user'],
                'created_at' => $date, 'updated_at' => $date,
            ]);
            if ($paid > 0) {
                $paymentCode = 'DSPY-'.$prefix.'-0001';
                $paymentDate = $date->addDays(7);
                $paymentId = DB::table('supplier_payments')->insertGetId([
                    'code' => $paymentCode, 'supplier_id' => $supplierId, 'supplier_invoice_id' => $invoiceId,
                    'payment_date' => $paymentDate->toDateString(), 'amount' => $paid,
                    'payment_method' => 'bank', 'reference_no' => 'DEMO-BANK-'.$prefix,
                    'created_by' => $context['accountant_user'], 'created_at' => $paymentDate, 'updated_at' => $paymentDate,
                ]);
                DB::table('supplier_ledger_entries')->insert([
                    'supplier_id' => $supplierId, 'entry_date' => $paymentDate->toDateString(),
                    'entry_type' => 'payment', 'source_type' => 'supplier_payment', 'source_key' => $paymentCode,
                    'reference_no' => 'DEMO-BANK-'.$prefix, 'description' => 'Payment for '.$invoiceCode,
                    'debit' => $paid, 'credit' => 0, 'created_by' => $context['accountant_user'],
                    'created_at' => $paymentDate, 'updated_at' => $paymentDate,
                ]);
                $this->postBook($paymentCode, 'supplier_payment', $paymentId, $paymentDate, 'bank', 'out', 'supplier_payment', $paid, $context['accountant_user']);
            }
        }
        $balance = DB::table('stock_balances')->where('warehouse_id', $context['warehouse'])->where('product_id', $context['product']->id)->first();
        DB::table('stock_balances')->where('id', $balance->id)->update([
            'quantity' => (float) $balance->quantity + 70,
            'stock_value' => ((float) $balance->quantity + 70) * 1800,
            'updated_at' => now(),
        ]);
    }

    private function seedKpiAndPayroll(string $month, array $context): void
    {
        $start = CarbonImmutable::parse("{$month}-01");
        $end = $start->endOfMonth();
        $periodId = DB::table('kpi_periods')->insertGetId([
            'month' => $month, 'period_start' => $start->toDateString(), 'period_end' => $end->toDateString(),
            'status' => 'open', 'created_by' => $context['hr_user'], 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([['DRV-001', 'DRIVER-V1', 'approved', 78, 20000], ['SAL-001', 'SALES-REP-V1', 'submitted', 85, 32000], ['SUP-001', 'SALES-SUPERVISOR-V1', 'submitted', 80, 32000]] as [$employeeCode, $templateCode, $status, $score, $bonus]) {
            $employeeId = DB::table('employees')->where('code', $employeeCode)->value('id');
            $templateId = DB::table('kpi_templates')->where('code', $templateCode)->value('id');
            $resultId = DB::table('kpi_results')->insertGetId([
                'kpi_period_id' => $periodId, 'kpi_template_id' => $templateId,
                'employee_id' => $employeeId, 'status' => $status, 'overall_score' => $score,
                'target_bonus' => 40000, 'bonus_amount' => $bonus,
                'notes' => 'Demo monthly review', 'submitted_by' => $context['hr_user'],
                'submitted_at' => $end->format('Y-m-d 12:00:00'),
                'approved_by' => $status === 'approved' ? $context['manager_user'] : null,
                'approved_at' => $status === 'approved' ? $end->format('Y-m-d 14:00:00') : null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach (DB::table('kpi_template_metrics')->where('kpi_template_id', $templateId)->get() as $metric) {
                DB::table('kpi_result_items')->insert([
                    'kpi_result_id' => $resultId, 'kpi_template_metric_id' => $metric->id,
                    'target_value' => $metric->default_target, 'actual_value' => $metric->calculation_type === 'manual' ? null : ($metric->default_target ?: 80),
                    'manual_score' => $metric->calculation_type === 'manual' ? $score : null,
                    'achievement_percent' => $score, 'weighted_score' => round($score * $metric->weight / 100, 2),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            if ($status === 'approved') {
                $adjustmentId = DB::table('payroll_adjustments')->insertGetId([
                    'employee_id' => $employeeId, 'adjustment_type' => 'incentive',
                    'title' => 'KPI bonus '.$month, 'amount' => $bonus,
                    'effective_date' => $end->toDateString(), 'status' => 'active',
                    'created_by' => $context['manager_user'], 'notes' => 'Approved KPI bonus',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('kpi_results')->where('id', $resultId)->update([
                    'payroll_adjustment_id' => $adjustmentId, 'bonus_posted_by' => $context['manager_user'],
                    'bonus_posted_at' => $end->format('Y-m-d 15:00:00'),
                ]);
            }
        }

        $payrollId = DB::table('payrolls')->insertGetId([
            'code' => 'DPAY-'.str_replace('-', '', $month).'-ALL', 'month' => $month,
            'period_start' => $start->toDateString(), 'period_end' => $end->toDateString(),
            'employee_type' => null, 'status' => 'draft', 'generated_by' => $context['hr_user'],
            'notes' => 'Demo draft: Finance Manager can approve, then Accountant can mark paid.',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $salaryByType = ['office' => 450000, 'sales' => 380000, 'sales_supervisor' => 450000, 'driver' => 360000, 'warehouse' => 330000];
        $totals = ['gross' => 0, 'deductions' => 0, 'net' => 0];
        foreach (DB::table('employees')->where('is_active', true)->get(['id', 'code', 'name', 'employee_type']) as $employee) {
            $attendance = DB::table('attendance_records')->where('employee_id', $employee->id)
                ->whereBetween('attendance_at', [$start->startOfDay(), $end->endOfDay()]);
            $accepted = (clone $attendance)->where('status', 'accepted')->count();
            $rejected = (clone $attendance)->where('status', 'rejected')->count();
            $bonus = (float) DB::table('payroll_adjustments')->where('employee_id', $employee->id)
                ->where('adjustment_type', 'incentive')->where('status', 'active')
                ->whereBetween('effective_date', [$start->toDateString(), $end->toDateString()])->sum('amount');
            $base = $salaryByType[$employee->employee_type] ?? 300000;
            $gross = $base + $bonus;
            $totals['gross'] += $gross;
            $totals['net'] += $gross;
            DB::table('payroll_items')->insert([
                'payroll_id' => $payrollId, 'employee_id' => $employee->id,
                'employee_code' => $employee->code, 'employee_name' => $employee->name,
                'employee_type' => $employee->employee_type,
                'accepted_count' => $accepted, 'rejected_count' => $rejected,
                'gps_denied_count' => 0, 'outside_radius_count' => 0,
                'first_attendance_at' => (clone $attendance)->min('attendance_at'),
                'last_attendance_at' => (clone $attendance)->max('attendance_at'),
                'base_salary' => $base, 'allowance_amount' => 0, 'incentive_amount' => $bonus,
                'ot_amount' => 0, 'advance_deduction' => 0, 'other_deduction' => 0,
                'gross_pay' => $gross, 'net_pay' => $gross, 'remarks' => 'Demo monthly payroll',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('payrolls')->where('id', $payrollId)->update([
            'total_gross' => $totals['gross'], 'total_deductions' => $totals['deductions'],
            'total_net' => $totals['net'], 'updated_at' => now(),
        ]);
    }

    private function postBook(string $code, string $referenceType, int $referenceId, CarbonImmutable $date, string $book, string $direction, string $category, float $amount, int $createdBy): void
    {
        DB::table('financial_transactions')->insert([
            'code' => 'DTXN-'.$code, 'transaction_date' => $date->toDateString(),
            'book_type' => $book, 'direction' => $direction, 'category' => $category,
            'amount' => $amount, 'reference_type' => $referenceType,
            'reference_id' => $referenceId, 'reference_code' => $code,
            'description' => 'Demo '.$referenceType.' '.$code, 'created_by' => $createdBy,
            'created_at' => $date, 'updated_at' => $date,
        ]);
    }
}
