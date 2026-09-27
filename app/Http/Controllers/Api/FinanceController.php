<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Expense;
use App\Models\FinancialTransaction;
use App\Models\SupplierLedgerEntry;
use App\Services\CustomerCreditService;
use App\Support\ApiResponse;
use App\Support\AppAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FinanceController extends Controller
{
    private const VIEW_PERMISSION = 'office.finance.view';

    private const MANAGE_PERMISSION = 'office.finance.manage';

    public function __construct(private readonly CustomerCreditService $customerCredit)
    {
    }

    public function meta(Request $request)
    {
        $this->authorizePermission($request, self::VIEW_PERMISSION);

        return ApiResponse::success('Finance setup loaded.', [
            'customers' => DB::table('customers')->where('is_active', true)->orderBy('shop_name')->get(['id', 'code', 'shop_name'])->map(fn ($item) => $this->customerMeta($item)),
            'invoices' => DB::table('invoices')->join('orders', 'invoices.order_id', '=', 'orders.id')->join('customers', 'invoices.customer_id', '=', 'customers.id')->where('orders.payment_type', 'credit')->where('invoices.status', '!=', 'cancelled')->orderByDesc('invoice_date')->get(['invoices.id', 'invoices.code', 'invoices.customer_id', 'invoices.total', 'customers.shop_name'])->map(function ($invoice) {
                $invoice->outstanding = $this->invoiceOutstanding((int) $invoice->id);
                $invoice->label = "{$invoice->code} - {$invoice->shop_name}";

                return $invoice;
            })->filter(fn ($invoice) => $invoice->outstanding > 0)->values(),
            'employees' => DB::table('employees')->where('is_active', true)->whereIn('employee_type', ['sales', 'driver'])->orderBy('name')->get(['id', 'code', 'name', 'employee_type'])->map(function ($item) {
                $item->label = "{$item->code} - {$item->name}";

                return $item;
            }),
            'suppliers' => DB::table('suppliers')->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name'])->map(function ($item) {
                $item->label = "{$item->code} - {$item->name}";

                return $item;
            }),
            'expense_categories' => ['utilities', 'office_supplies', 'meals', 'travel', 'fuel', 'vehicle_cost', 'communication', 'other'],
        ]);
    }

    public function collections(Request $request)
    {
        $this->authorizePermission($request, self::VIEW_PERMISSION);
        $query = $this->collectionQuery()->latest('collections.collection_date')->latest('collections.id');
        $this->applyCommonFilters($query, $request, 'collections', 'collection_date');
        foreach (['customer_id', 'employee_id', 'status', 'payment_method'] as $filter) {
            if ($request->filled($filter)) {
                $query->where("collections.{$filter}", $request->query($filter));
            }
        }
        if ($request->boolean('outdoor')) {
            $query->whereNotNull('collections.employee_id');
        }
        if ($search = trim((string) $request->query('search'))) {
            $query->where(fn ($q) => $q->where('collections.code', 'like', "%{$search}%")->orWhere('customers.shop_name', 'like', "%{$search}%")->orWhere('employees.name', 'like', "%{$search}%"));
        }
        $summary = (clone $query)->reorder()->selectRaw("COUNT(*) records_count, COALESCE(SUM(collections.amount),0) total_amount, COALESCE(SUM(CASE WHEN collections.status='submitted' THEN collections.amount ELSE 0 END),0) submitted_amount, COALESCE(SUM(CASE WHEN collections.status='approved' THEN collections.amount ELSE 0 END),0) approved_amount")->first();
        $paginator = $query->select($this->collectionColumns())->paginate(min(max((int) $request->query('per_page', 20), 1), 100));
        $cashHandovers = $request->boolean('outdoor') ? $this->pendingDriverCashHandovers() : collect();

        return ApiResponse::success('Collections loaded.', ['items' => collect($paginator->items())->map(fn ($item) => $this->collectionPayload($item)), 'cash_handovers' => $cashHandovers, 'summary' => $this->amountSummary($summary), 'meta' => $this->pagination($paginator)]);
    }

    public function storeCollection(Request $request)
    {
        $this->authorizePermission($request, self::MANAGE_PERMISSION);
        $validated = $this->collectionRules($request);
        $this->validateCollectionScope($validated);
        $collection = DB::transaction(function () use ($request, $validated) {
            $collection = Collection::create($validated + ['code' => $this->nextCode('COL', 'collections', 'collection_date', Carbon::parse($validated['collection_date'])), 'source_app' => 'office', 'status' => 'approved', 'submitted_by' => $request->user()->id, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            $this->postCollection($collection, $request->user()->id);

            return $collection;
        });

        return ApiResponse::success('Collection recorded.', ['collection' => $this->collectionPayload($this->collectionQuery()->select($this->collectionColumns())->where('collections.id', $collection->id)->first())], 201);
    }

    public function reviewCollection(Request $request, Collection $collection)
    {
        $this->authorizePermission($request, self::MANAGE_PERMISSION);
        abort_unless($collection->status === 'submitted', 409, 'Only submitted collections can be reviewed.');
        $validated = $request->validate(['status' => ['required', Rule::in(['approved', 'rejected'])], 'notes' => ['nullable', 'string', 'max:500']]);
        abort_if(
            $validated['status'] === 'approved'
            && $collection->source_app === 'driver'
            && $collection->payment_method === 'cash'
            && $collection->employee_id,
            409,
            'Receive driver cash through the driver cash handover action.'
        );
        DB::transaction(function () use ($collection, $request, $validated) {
            $locked = Collection::lockForUpdate()->findOrFail($collection->id);
            abort_unless($locked->status === 'submitted', 409, 'This collection was already reviewed.');
            if ($validated['status'] === 'approved') {
                $this->validateCollectionScope($locked->toArray());
            }
            $locked->update(['status' => $validated['status'], 'notes' => $validated['notes'] ?? $locked->notes, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            if ($validated['status'] === 'approved') {
                $this->postCollection($locked, $request->user()->id);
            }
        });

        return ApiResponse::success('Collection review saved.', ['collection' => $this->collectionPayload($this->collectionQuery()->select($this->collectionColumns())->where('collections.id', $collection->id)->first())]);
    }

    public function receiveCashHandover(Request $request, int $employeeId)
    {
        $this->authorizePermission($request, self::MANAGE_PERMISSION);
        abort_unless(DB::table('employees')->where('id', $employeeId)->where('employee_type', 'driver')->exists(), 404, 'Driver not found.');
        $validated = $request->validate([
            'collection_ids' => ['required', 'array', 'min:1'],
            'collection_ids.*' => ['required', 'integer', 'distinct', 'exists:collections,id'],
            'received_amount' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $result = DB::transaction(function () use ($employeeId, $request, $validated) {
            $collections = Collection::query()
                ->where('employee_id', $employeeId)
                ->where('source_app', 'driver')
                ->where('payment_method', 'cash')
                ->where('status', 'submitted')
                ->whereIn('id', $validated['collection_ids'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            abort_unless($collections->count() === count($validated['collection_ids']), 409, 'Some cash records were already received or do not belong to this driver. Refresh and try again.');
            $expectedAmount = round((float) $collections->sum('amount'), 2);
            abort_if(abs($expectedAmount - (float) $validated['received_amount']) > 0.001, 422, 'Cash received must match the selected driver cash hold.');

            foreach ($collections as $collection) {
                $this->validateCollectionScope($collection->toArray());
                $collection->update([
                    'status' => 'approved',
                    'notes' => $validated['notes'] ?? $collection->notes,
                    'reviewed_by' => $request->user()->id,
                    'reviewed_at' => now(),
                ]);
                $this->postCollection($collection, $request->user()->id);
            }

            return ['employee_id' => $employeeId, 'collections_count' => $collections->count(), 'received_amount' => $expectedAmount];
        });

        return ApiResponse::success('Driver cash handover received and posted to the cash book.', ['handover' => $result]);
    }

    public function receivables(Request $request)
    {
        $this->authorizePermission($request, self::VIEW_PERMISSION);
        $invoiceTotals = DB::table('invoices')->join('orders', 'invoices.order_id', '=', 'orders.id')->where('orders.payment_type', 'credit')->where('invoices.status', '!=', 'cancelled')->groupBy('invoices.customer_id')->selectRaw('invoices.customer_id, SUM(invoices.total) invoiced_amount');
        $collectionTotals = DB::table('collections')->where('status', 'approved')->where(function ($query) {
            $query->whereNull('invoice_id')->orWhereExists(function ($subquery) {
                $subquery->selectRaw('1')->from('invoices')->join('orders', 'invoices.order_id', '=', 'orders.id')
                    ->whereColumn('invoices.id', 'collections.invoice_id')->where('orders.payment_type', 'credit');
            });
        })->groupBy('customer_id')->selectRaw('customer_id, SUM(amount) collected_amount');
        $query = DB::table('customers')->leftJoinSub($invoiceTotals, 'invoice_totals', 'customers.id', '=', 'invoice_totals.customer_id')->leftJoinSub($collectionTotals, 'collection_totals', 'customers.id', '=', 'collection_totals.customer_id')->leftJoin('routes', 'customers.route_id', '=', 'routes.id')->where('customers.is_active', true);
        if ($search = trim((string) $request->query('search'))) {
            $query->where(fn ($q) => $q->where('customers.code', 'like', "%{$search}%")->orWhere('customers.shop_name', 'like', "%{$search}%"));
        }
        $items = $query->orderBy('customers.shop_name')->get(['customers.id', 'customers.code', 'customers.shop_name', 'customers.credit_limit', 'routes.name as route_name', DB::raw('COALESCE(invoice_totals.invoiced_amount,0) invoiced_amount'), DB::raw('COALESCE(collection_totals.collected_amount,0) collected_amount')])->map(function ($item) {
            $credit = $this->customerCredit->summary((int) $item->id);
            $item->credit_limit = (float) $item->credit_limit;
            $item->invoiced_amount = $credit['credit_sales_amount'];
            $item->collected_amount = $credit['payments_amount'];
            $item->return_credits_amount = $credit['return_credits_amount'];
            $item->refunds_amount = $credit['refunds_amount'];
            $item->outstanding_amount = $credit['outstanding_amount'];
            $item->customer_credit_amount = $credit['customer_credit_amount'];
            $item->available_credit = $item->credit_limit > 0 ? $item->credit_limit - $item->outstanding_amount + $item->customer_credit_amount : null;
            $due = $this->customerDueSummary((int) $item->id, $this->customerCredit->settlementCredits((int) $item->id));
            $item->overdue_amount = $due['overdue_amount'];
            $item->next_due_date = $due['next_due_date'];

            return $item;
        });

        return ApiResponse::success('Customer credit loaded.', ['items' => $items, 'summary' => ['customers_count' => $items->count(), 'credit_sales_amount' => (float) $items->sum('invoiced_amount'), 'invoiced_amount' => (float) $items->sum('invoiced_amount'), 'collected_amount' => (float) $items->sum('collected_amount'), 'return_credits_amount' => (float) $items->sum('return_credits_amount'), 'customer_credit_amount' => (float) $items->sum('customer_credit_amount'), 'outstanding_amount' => (float) $items->sum('outstanding_amount'), 'overdue_amount' => (float) $items->sum('overdue_amount')]]);
    }

    public function customerLedger(Request $request, int $customerId)
    {
        $this->authorizePermission($request, self::VIEW_PERMISSION);
        abort_unless(DB::table('customers')->where('id', $customerId)->exists(), 404);

        return ApiResponse::success('Customer ledger loaded.', $this->customerLedgerData($customerId));
    }

    public function expenses(Request $request)
    {
        $this->authorizePermission($request, self::VIEW_PERMISSION);
        $query = $this->expenseQuery()->latest('expenses.expense_date')->latest('expenses.id');
        $this->applyCommonFilters($query, $request, 'expenses', 'expense_date');
        foreach (['employee_id', 'status', 'expense_type', 'category', 'payment_method'] as $filter) {
            if ($request->filled($filter)) {
                $query->where("expenses.{$filter}", $request->query($filter));
            }
        }
        if ($search = trim((string) $request->query('search'))) {
            $query->where(fn ($q) => $q->where('expenses.code', 'like', "%{$search}%")->orWhere('expenses.description', 'like', "%{$search}%")->orWhere('employees.name', 'like', "%{$search}%"));
        }
        $summary = (clone $query)->reorder()->selectRaw("COUNT(*) records_count, COALESCE(SUM(expenses.amount),0) total_amount, COALESCE(SUM(CASE WHEN expenses.status='submitted' THEN expenses.amount ELSE 0 END),0) submitted_amount, COALESCE(SUM(CASE WHEN expenses.status='approved' THEN expenses.amount ELSE 0 END),0) approved_amount")->first();
        $paginator = $query->select($this->expenseColumns())->paginate(min(max((int) $request->query('per_page', 20), 1), 100));

        return ApiResponse::success('Expenses loaded.', ['items' => collect($paginator->items())->map(fn ($item) => $this->expensePayload($item)), 'summary' => $this->amountSummary($summary), 'meta' => $this->pagination($paginator)]);
    }

    public function storeExpense(Request $request)
    {
        $this->authorizePermission($request, self::MANAGE_PERMISSION);
        $validated = $this->expenseRules($request, true);
        $expense = DB::transaction(function () use ($request, $validated) {
            $expense = Expense::create($validated + ['code' => $this->nextCode('EXP', 'expenses', 'expense_date', Carbon::parse($validated['expense_date'])), 'source_app' => 'office', 'status' => 'approved', 'submitted_by' => $request->user()->id, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            $this->postExpense($expense, $request->user()->id);

            return $expense;
        });

        return ApiResponse::success('Expense recorded.', ['expense' => $this->expensePayload($this->expenseQuery()->select($this->expenseColumns())->where('expenses.id', $expense->id)->first())], 201);
    }

    public function reviewExpense(Request $request, Expense $expense)
    {
        $this->authorizePermission($request, self::MANAGE_PERMISSION);
        abort_unless($expense->status === 'submitted', 409, 'Only submitted expenses can be reviewed.');
        $validated = $request->validate(['status' => ['required', Rule::in(['approved', 'rejected'])], 'notes' => ['nullable', 'string', 'max:500']]);
        DB::transaction(function () use ($expense, $request, $validated) {
            $locked = Expense::lockForUpdate()->findOrFail($expense->id);
            abort_unless($locked->status === 'submitted', 409, 'This expense was already reviewed.');
            $locked->update(['status' => $validated['status'], 'notes' => $validated['notes'] ?? $locked->notes, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            if ($validated['status'] === 'approved') {
                $this->postExpense($locked, $request->user()->id);
            }
        });

        return ApiResponse::success('Expense review saved.', ['expense' => $this->expensePayload($this->expenseQuery()->select($this->expenseColumns())->where('expenses.id', $expense->id)->first())]);
    }

    public function book(Request $request, string $book)
    {
        $this->authorizePermission($request, self::VIEW_PERMISSION);
        abort_unless(in_array($book, ['cash', 'bank'], true), 404);
        $query = FinancialTransaction::where('book_type', $book)->orderBy('transaction_date')->orderBy('id');
        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->query('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->query('date_to'));
        }
        $items = $query->get()->map(fn ($item) => ['id' => $item->id, 'code' => $item->code, 'transaction_date' => $item->transaction_date->toDateString(), 'direction' => $item->direction, 'category' => $item->category, 'amount' => (float) $item->amount, 'reference_code' => $item->reference_code, 'description' => $item->description]);
        $balance = 0;
        $items = $items->map(function ($item) use (&$balance) {
            $balance += $item['direction'] === 'in' ? $item['amount'] : -$item['amount'];
            $item['balance'] = $balance;

            return $item;
        });

        return ApiResponse::success(ucfirst($book).' book loaded.', ['items' => $items->reverse()->values(), 'summary' => ['inflow' => (float) $items->where('direction', 'in')->sum('amount'), 'outflow' => (float) $items->where('direction', 'out')->sum('amount'), 'balance' => (float) $balance]]);
    }

    public function suppliers(Request $request)
    {
        $this->authorizePermission($request, self::VIEW_PERMISSION);
        $items = DB::table('suppliers')->leftJoin('supplier_ledger_entries', 'suppliers.id', '=', 'supplier_ledger_entries.supplier_id')->groupBy('suppliers.id', 'suppliers.code', 'suppliers.name', 'suppliers.phone')->orderBy('suppliers.name')->get(['suppliers.id', 'suppliers.code', 'suppliers.name', 'suppliers.phone', DB::raw('COALESCE(SUM(supplier_ledger_entries.credit - supplier_ledger_entries.debit),0) balance')])->map(function ($item) {
            $item->balance = (float) $item->balance;

            return $item;
        });

        return ApiResponse::success('Supplier balances loaded.', ['items' => $items, 'summary' => ['suppliers_count' => $items->count(), 'payable_amount' => (float) $items->sum('balance')]]);
    }

    public function supplierLedger(Request $request, int $supplierId)
    {
        $this->authorizePermission($request, self::VIEW_PERMISSION);
        $supplier = DB::table('suppliers')->find($supplierId);
        abort_unless($supplier, 404);
        $balance = 0;
        $items = SupplierLedgerEntry::where('supplier_id', $supplierId)->orderBy('entry_date')->orderBy('id')->get()->map(function ($item) use (&$balance) {
            $balance += (float) $item->credit - (float) $item->debit;

            return ['id' => $item->id, 'entry_date' => $item->entry_date->toDateString(), 'entry_type' => $item->entry_type, 'reference_no' => $item->reference_no, 'description' => $item->description, 'debit' => (float) $item->debit, 'credit' => (float) $item->credit, 'balance' => $balance];
        });

        return ApiResponse::success('Supplier ledger loaded.', ['supplier' => $supplier, 'items' => $items->reverse()->values(), 'summary' => ['balance' => (float) $balance]]);
    }

    public function storeSupplierEntry(Request $request, int $supplierId)
    {
        $this->authorizePermission($request, self::MANAGE_PERMISSION);
        abort_unless(DB::table('suppliers')->where('id', $supplierId)->exists(), 404);
        $validated = $request->validate(['entry_date' => ['required', 'date'], 'entry_type' => ['required', Rule::in(['purchase', 'payment', 'adjustment'])], 'reference_no' => ['nullable', 'string', 'max:100'], 'description' => ['required', 'string', 'max:255'], 'amount' => ['required', 'numeric', 'gt:0']]);
        $purchase = $validated['entry_type'] === 'purchase';
        $entry = SupplierLedgerEntry::create(['supplier_id' => $supplierId, 'entry_date' => $validated['entry_date'], 'entry_type' => $validated['entry_type'], 'reference_no' => $validated['reference_no'] ?? null, 'description' => $validated['description'], 'debit' => $purchase ? 0 : $validated['amount'], 'credit' => $purchase ? $validated['amount'] : 0, 'created_by' => $request->user()->id]);

        return ApiResponse::success('Supplier ledger entry recorded.', ['entry_id' => $entry->id], 201);
    }

    public function profitLoss(Request $request)
    {
        $this->authorizePermission($request, self::VIEW_PERMISSION);
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'granularity' => ['nullable', Rule::in(['day', 'month'])],
        ]);
        $fromDate = Carbon::parse($validated['date_from'] ?? now()->startOfYear()->toDateString())->startOfDay();
        $toDate = Carbon::parse($validated['date_to'] ?? now()->toDateString())->endOfDay();
        $from = $fromDate->toDateString();
        $to = $toDate->toDateString();
        $granularity = $validated['granularity'] ?? ($fromDate->diffInDays($toDate) <= 62 ? 'day' : 'month');

        $trend = [];
        $cursor = $granularity === 'day' ? $fromDate->copy() : $fromDate->copy()->startOfMonth();
        while ($cursor->lte($toDate)) {
            $key = $granularity === 'day' ? $cursor->format('Y-m-d') : $cursor->format('Y-m');
            $trend[$key] = [
                'key' => $key,
                'label' => $granularity === 'day' ? $cursor->format('M j') : $cursor->format('M'),
                'revenue' => 0.0,
                'payroll' => 0.0,
                'vehicle' => 0.0,
                'outdoor_employee' => 0.0,
                'daily_expense' => 0.0,
            ];
            $granularity === 'day' ? $cursor->addDay() : $cursor->addMonth();
        }

        $bucketKey = function ($date) use ($fromDate, $granularity, $toDate) {
            $value = Carbon::parse($date);
            if ($value->lt($fromDate)) $value = $fromDate->copy();
            if ($value->gt($toDate)) $value = $toDate->copy();

            return $granularity === 'day' ? $value->format('Y-m-d') : $value->format('Y-m');
        };
        $addTrend = function ($date, string $field, float $amount) use (&$trend, $bucketKey) {
            $key = $bucketKey($date);
            if (isset($trend[$key])) $trend[$key][$field] += $amount;
        };

        $invoiceRows = DB::table('invoices')
            ->join('orders', 'invoices.order_id', '=', 'orders.id')
            ->whereIn('orders.payment_type', ['cash', 'credit'])
            ->where('invoices.status', '!=', 'cancelled')
            ->whereBetween('invoices.invoice_date', [$from, $to])
            ->get(['invoices.invoice_date', 'invoices.total']);
        foreach ($invoiceRows as $row) $addTrend($row->invoice_date, 'revenue', (float) $row->total);

        $payrollRows = DB::table('payrolls')
            ->whereIn('status', ['approved', 'paid'])
            ->whereDate('period_end', '>=', $from)
            ->whereDate('period_start', '<=', $to)
            ->get(['period_end', 'total_net']);
        foreach ($payrollRows as $row) $addTrend($row->period_end, 'payroll', (float) $row->total_net);

        $expenseRows = DB::table('expenses')
            ->where('status', 'approved')
            ->whereBetween('expense_date', [$from, $to])
            ->get(['expense_date', 'expense_type', 'category', 'amount']);
        $vehicleRows = DB::table('vehicle_costs')
            ->where('status', 'approved')
            ->where('record_type', 'cost')
            ->whereBetween('cost_date', [$from, $to])
            ->get(['cost_date', 'cost_type', 'amount']);

        $costCategories = ['Payroll' => (float) $payrollRows->sum('total_net')];
        foreach ($expenseRows as $row) {
            $amount = (float) $row->amount;
            if ($row->category === 'vehicle_cost') {
                $field = 'vehicle';
                $category = 'Vehicle · Legacy expense';
            } elseif ($row->expense_type === 'outdoor') {
                $field = 'outdoor_employee';
                $category = 'Outdoor · '.Str::headline($row->category);
            } else {
                $field = 'daily_expense';
                $category = 'Daily · '.Str::headline($row->category);
            }
            $addTrend($row->expense_date, $field, $amount);
            $costCategories[$category] = ($costCategories[$category] ?? 0) + $amount;
        }
        foreach ($vehicleRows as $row) {
            $amount = (float) $row->amount;
            $addTrend($row->cost_date, 'vehicle', $amount);
            $category = 'Vehicle · '.Str::headline($row->cost_type);
            $costCategories[$category] = ($costCategories[$category] ?? 0) + $amount;
        }

        $trend = collect($trend)->map(function ($item) {
            $item['cost'] = $item['payroll'] + $item['vehicle'] + $item['outdoor_employee'] + $item['daily_expense'];
            $item['net_profit'] = $item['revenue'] - $item['cost'];

            return $item;
        })->values();
        $revenue = (float) $trend->sum('revenue');
        $costs = [
            'payroll' => (float) $trend->sum('payroll'),
            'vehicle' => (float) $trend->sum('vehicle'),
            'outdoor_employee' => (float) $trend->sum('outdoor_employee'),
            'daily_expense' => (float) $trend->sum('daily_expense'),
        ];
        $costs['total'] = array_sum($costs);
        $netProfit = $revenue - $costs['total'];
        arsort($costCategories);
        $activePeriods = $trend->filter(fn ($item) => $item['revenue'] > 0 || $item['cost'] > 0);

        return ApiResponse::success('Profit and loss loaded.', [
            'period' => ['date_from' => $from, 'date_to' => $to, 'granularity' => $granularity],
            'revenue' => $revenue,
            'costs' => $costs,
            'net_profit' => $netProfit,
            'margin_percent' => $revenue > 0 ? round($netProfit / $revenue * 100, 2) : 0,
            'trend' => $trend,
            'expense_breakdown' => collect([
                ['key' => 'payroll', 'label' => 'Payroll', 'amount' => $costs['payroll']],
                ['key' => 'vehicle', 'label' => 'Vehicle costs', 'amount' => $costs['vehicle']],
                ['key' => 'outdoor_employee', 'label' => 'Outdoor employee', 'amount' => $costs['outdoor_employee']],
                ['key' => 'daily_expense', 'label' => 'Daily expenses', 'amount' => $costs['daily_expense']],
            ])->filter(fn ($item) => $item['amount'] > 0)->values(),
            'cost_categories' => collect($costCategories)->map(fn ($amount, $category) => ['label' => $category, 'amount' => (float) $amount])->values(),
            'analysis' => [
                'average_revenue' => $activePeriods->count() ? (float) $activePeriods->avg('revenue') : 0,
                'average_cost' => $activePeriods->count() ? (float) $activePeriods->avg('cost') : 0,
                'profitable_periods' => $activePeriods->where('net_profit', '>=', 0)->count(),
                'loss_periods' => $activePeriods->where('net_profit', '<', 0)->count(),
            ],
        ]);
    }

    public function mobileIndex(Request $request)
    {
        $scope = $this->mobileScope($request, 'view');
        if ($scope['app'] === 'client') {
            return ApiResponse::success('Customer finance loaded.', $this->customerLedgerData($scope['customer_id']));
        }
        $collections = $this->collectionQuery()->where('collections.employee_id', $scope['employee_id'])->latest('collections.collection_date')->select($this->collectionColumns())->get()->map(fn ($item) => $this->collectionPayload($item));
        $expenses = $this->expenseQuery()->where('expenses.employee_id', $scope['employee_id'])->latest('expenses.expense_date')->select($this->expenseColumns())->get()->map(fn ($item) => $this->expensePayload($item));

        return ApiResponse::success('Field finance loaded.', ['app' => $scope['app'], 'collections' => $collections, 'expenses' => $expenses, 'summary' => ['collections_amount' => (float) $collections->sum('amount'), 'expenses_amount' => (float) $expenses->sum('amount'), 'pending_count' => $collections->where('status', 'submitted')->count() + $expenses->where('status', 'submitted')->count()]]);
    }

    public function mobileMeta(Request $request)
    {
        $scope = $this->mobileScope($request, 'view');
        abort_if($scope['app'] === 'client', 403);
        $deliveries = $scope['app'] === 'driver'
            ? DB::table('deliveries')->join('customers', 'deliveries.customer_id', '=', 'customers.id')->where('deliveries.driver_id', $scope['employee_id'])->whereIn('deliveries.status', ['assigned', 'loading', 'on_route'])->orderByDesc('deliveries.id')->get(['deliveries.id', 'deliveries.code', 'deliveries.customer_id', 'customers.shop_name'])
            : collect();
        $deliveryByCustomer = $deliveries->keyBy('customer_id');
        $customerQuery = DB::table('customers')->where('customers.is_active', true);
        if ($scope['route_id']) {
            $customerQuery->where('customers.route_id', $scope['route_id']);
        } elseif ($scope['app'] === 'driver') {
            $customerQuery->whereIn('customers.id', $deliveries->pluck('customer_id'));
        } else {
            $customerQuery->whereRaw('1 = 0');
        }
        $customers = $customerQuery->orderBy('customers.shop_name')->get(['customers.id', 'customers.code', 'customers.shop_name', 'customers.contact_name', 'customers.phone', 'customers.address'])->map(function ($customer) use ($deliveryByCustomer) {
            $delivery = $deliveryByCustomer->get($customer->id);
            $customer->outstanding = $this->customerOutstanding((int) $customer->id);
            $customer->pending_collection = (float) DB::table('collections')->where('customer_id', $customer->id)->where('status', 'submitted')->sum('amount');
            $customer->collectible = max($customer->outstanding - $customer->pending_collection, 0);
            $customer->active_delivery_id = $delivery?->id;
            $customer->active_delivery_code = $delivery?->code;

            return $customer;
        })->filter(fn ($customer) => $customer->collectible > 0)->values();

        return ApiResponse::success('Field finance setup loaded.', ['app' => $scope['app'], 'customers' => $customers, 'deliveries' => $deliveries, 'expense_categories' => ['meals', 'travel', 'fuel', 'communication', 'other']]);
    }

    public function mobileStoreCollection(Request $request)
    {
        abort_unless($request->user()?->role === 'Driver', 403, 'Cash collection is a Driver operation.');
        $scope = $this->mobileScope($request, 'create', 'collections');
        $validated = $request->validate(['customer_id' => ['required', 'integer', 'exists:customers,id'], 'delivery_id' => ['nullable', 'integer', 'exists:deliveries,id'], 'collection_date' => ['required', 'date'], 'amount' => ['required', 'numeric', 'gt:0'], 'payment_method' => ['nullable', Rule::in(['cash'])], 'reference_no' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:500']]);
        $assignedCustomer = $scope['route_id'] && DB::table('customers')->where('id', $validated['customer_id'])->where('route_id', $scope['route_id'])->where('is_active', true)->exists();
        $activeDelivery = $scope['app'] === 'driver'
            ? DB::table('deliveries')->where('driver_id', $scope['employee_id'])->where('customer_id', $validated['customer_id'])->whereIn('status', ['assigned', 'loading', 'on_route'])->latest('id')->first()
            : null;
        abort_unless($assignedCustomer || $activeDelivery, 403, 'Customer is outside the assigned route.');
        if (! empty($validated['delivery_id'])) {
            abort_unless($scope['app'] === 'driver' && $activeDelivery && (int) $activeDelivery->id === (int) $validated['delivery_id'], 403, 'Delivery is outside the assigned route.');
        } elseif ($activeDelivery) {
            $validated['delivery_id'] = $activeDelivery->id;
        }
        $validated['payment_method'] = 'cash';
        $collection = DB::transaction(function () use ($request, $scope, $validated) {
            DB::table('customers')->where('id', $validated['customer_id'])->lockForUpdate()->first();
            $outstanding = $this->customerOutstanding((int) $validated['customer_id']);
            $pending = (float) DB::table('collections')->where('customer_id', $validated['customer_id'])->where('status', 'submitted')->sum('amount');
            abort_if((float) $validated['amount'] > max($outstanding - $pending, 0), 422, 'Collection exceeds the customer balance available to collect.');

            return Collection::create($validated + ['code' => $this->nextCode('COL', 'collections', 'collection_date', Carbon::parse($validated['collection_date'])), 'employee_id' => $scope['employee_id'], 'source_app' => $scope['app'], 'status' => 'submitted', 'submitted_by' => $request->user()->id]);
        });

        return ApiResponse::success('Cash collection submitted for Office review.', ['collection_id' => $collection->id, 'code' => $collection->code, 'outstanding_after_approval' => max($this->customerOutstanding((int) $validated['customer_id']) - (float) $collection->amount, 0)], 201);
    }

    public function mobileStoreExpense(Request $request)
    {
        $scope = $this->mobileScope($request, 'create', 'expenses');
        $validated = $this->expenseRules($request, false);
        $expense = Expense::create($validated + ['code' => $this->nextCode('EXP', 'expenses', 'expense_date', Carbon::parse($validated['expense_date'])), 'expense_type' => 'outdoor', 'employee_id' => $scope['employee_id'], 'source_app' => $scope['app'], 'status' => 'submitted', 'submitted_by' => $request->user()->id]);

        return ApiResponse::success('Outdoor expense submitted for review.', ['expense_id' => $expense->id, 'code' => $expense->code], 201);
    }

    private function collectionRules(Request $request): array
    {
        return $request->validate(['customer_id' => ['required', 'integer', 'exists:customers,id'], 'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'], 'delivery_id' => ['nullable', 'integer', 'exists:deliveries,id'], 'employee_id' => ['nullable', 'integer', 'exists:employees,id'], 'collection_date' => ['required', 'date'], 'amount' => ['required', 'numeric', 'gt:0'], 'payment_method' => ['required', Rule::in(['cash', 'bank'])], 'reference_no' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:500']]);
    }

    private function expenseRules(Request $request, bool $office): array
    {
        return $request->validate(['employee_id' => [$office ? 'nullable' : 'prohibited', 'integer', 'exists:employees,id'], 'expense_date' => ['required', 'date'], 'expense_type' => [$office ? 'required' : 'nullable', Rule::in(['daily', 'outdoor'])], 'category' => ['required', Rule::in(['utilities', 'office_supplies', 'meals', 'travel', 'fuel', 'vehicle_cost', 'communication', 'other'])], 'description' => ['required', 'string', 'max:255'], 'amount' => ['required', 'numeric', 'gt:0'], 'payment_method' => ['required', Rule::in(['cash', 'bank'])], 'reference_no' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:500']]);
    }

    private function validateCollectionScope(array $data): void
    {
        if (! empty($data['invoice_id'])) {
            abort_unless(DB::table('invoices')->where('id', $data['invoice_id'])->where('customer_id', $data['customer_id'])->exists(), 422, 'Invoice does not belong to the selected customer.');

            $driverCashSale = ! empty($data['delivery_id'])
                ? DB::table('deliveries')
                    ->join('invoices', 'deliveries.invoice_id', '=', 'invoices.id')
                    ->join('orders', 'invoices.order_id', '=', 'orders.id')
                    ->where('deliveries.id', $data['delivery_id'])
                    ->where('deliveries.invoice_id', $data['invoice_id'])
                    ->where('deliveries.customer_id', $data['customer_id'])
                    ->where('deliveries.settlement_method', 'cash_driver')
                    ->where('orders.payment_type', 'cash')
                    ->first(['deliveries.settlement_amount'])
                : null;
            if ($driverCashSale) {
                abort_if((float) $data['amount'] > (float) $driverCashSale->settlement_amount + 0.001, 422, 'Collection exceeds the delivery cash amount.');

                return;
            }
        }
        $available = ! empty($data['invoice_id']) ? $this->invoiceOutstanding((int) $data['invoice_id']) : $this->customerOutstanding((int) $data['customer_id']);
        abort_if((float) $data['amount'] > $available, 422, 'Collection cannot exceed outstanding balance.');
    }

    private function postCollection(Collection $collection, ?int $userId): void
    {
        $this->postTransaction('collection', $collection->id, $collection->code, $collection->collection_date, $collection->payment_method, 'in', 'collection', $collection->amount, "Customer collection {$collection->code}", $userId);
    }

    private function postExpense(Expense $expense, ?int $userId): void
    {
        $this->postTransaction('expense', $expense->id, $expense->code, $expense->expense_date, $expense->payment_method, 'out', $expense->category, $expense->amount, $expense->description, $userId);
    }

    private function postTransaction(string $type, int $id, string $referenceCode, $date, string $book, string $direction, string $category, $amount, string $description, ?int $userId): void
    {
        FinancialTransaction::updateOrCreate(['reference_type' => $type, 'reference_id' => $id], ['code' => 'TXN-'.str_pad((string) (FinancialTransaction::max('id') + 1), 7, '0', STR_PAD_LEFT), 'transaction_date' => $date, 'book_type' => $book, 'direction' => $direction, 'category' => $category, 'amount' => $amount, 'reference_code' => $referenceCode, 'description' => $description, 'created_by' => $userId]);
    }

    private function customerLedgerData(int $customerId): array
    {
        $customer = DB::table('customers')->find($customerId);
        abort_unless($customer, 404);
        $entries = collect();
        DB::table('invoices')->join('orders', 'invoices.order_id', '=', 'orders.id')->where('invoices.customer_id', $customerId)->where('orders.payment_type', 'credit')->where('invoices.status', '!=', 'cancelled')->get(['invoices.*'])->each(fn ($item) => $entries->push(['key' => "I{$item->id}", 'date' => $item->invoice_date, 'due_date' => $item->due_date, 'type' => 'invoice', 'reference' => $item->code, 'description' => 'Credit sale', 'debit' => (float) $item->total, 'credit' => 0]));
        DB::table('collections')->where('customer_id', $customerId)->where('status', 'approved')->where(function ($query) {
            $query->whereNull('invoice_id')->orWhereExists(function ($subquery) {
                $subquery->selectRaw('1')->from('invoices')->join('orders', 'invoices.order_id', '=', 'orders.id')
                    ->whereColumn('invoices.id', 'collections.invoice_id')->where('orders.payment_type', 'credit');
            });
        })->get()->each(fn ($item) => $entries->push(['key' => "C{$item->id}", 'date' => $item->collection_date, 'type' => 'collection', 'reference' => $item->code, 'description' => 'Payment received', 'debit' => 0, 'credit' => (float) $item->amount]));
        DB::table('orders as returns')
            ->join('orders as originals', 'returns.original_order_id', '=', 'originals.id')
            ->where('returns.customer_id', $customerId)
            ->where('returns.status', 'confirmed')
            ->where(fn ($query) => $query->where('originals.payment_type', 'credit')->orWhere('returns.return_settlement_method', 'customer_credit'))
            ->get(['returns.id', 'returns.code', 'returns.order_date', 'returns.total', 'returns.refund_amount'])
            ->each(function ($item) use ($entries) {
                $entries->push(['key' => "R{$item->id}", 'date' => $item->order_date, 'type' => 'sales_return', 'reference' => $item->code, 'description' => 'Sales return credit', 'debit' => 0, 'credit' => (float) $item->total]);
                if ((float) $item->refund_amount > 0) {
                    $entries->push(['key' => "F{$item->id}", 'date' => $item->order_date, 'type' => 'return_refund', 'reference' => $item->code, 'description' => 'Return credit refunded', 'debit' => (float) $item->refund_amount, 'credit' => 0]);
                }
            });
        $balance = 0;
        $entries = $entries->sortBy(fn ($item) => $item['date'].($item['type'] === 'invoice' ? '0' : '1').str_pad(substr($item['key'], 1), 10, '0', STR_PAD_LEFT))->values()->map(function ($item) use (&$balance) {
            $balance += $item['debit'] - $item['credit'];
            $item['balance'] = $balance;

            return $item;
        });

        $due = $this->customerDueSummary($customerId, $this->customerCredit->settlementCredits($customerId));
        $credit = $this->customerCredit->summary($customerId);

        return ['customer' => ['id' => $customer->id, 'code' => $customer->code, 'shop_name' => $customer->shop_name, 'credit_limit' => (float) $customer->credit_limit], 'entries' => $entries->reverse()->values(), 'summary' => ['credit_sales_amount' => $credit['credit_sales_amount'], 'collected_amount' => $credit['payments_amount'], 'return_credits_amount' => $credit['return_credits_amount'], 'refunds_amount' => $credit['refunds_amount'], 'customer_credit_amount' => $credit['customer_credit_amount'], 'outstanding_amount' => $credit['outstanding_amount'], 'overdue_amount' => $due['overdue_amount'], 'next_due_date' => $due['next_due_date']]];
    }

    private function customerOutstanding(int $customerId): float
    {
        return $this->customerCredit->outstanding($customerId);
    }

    private function invoiceOutstanding(int $invoiceId): float
    {
        $invoice = DB::table('invoices')->join('orders', 'invoices.order_id', '=', 'orders.id')->where('invoices.id', $invoiceId)->where('orders.payment_type', 'credit')->where('invoices.status', '!=', 'cancelled')->first(['invoices.total', 'orders.id as order_id']);
        if (! $invoice) {
            return 0;
        }

        $returned = (float) DB::table('orders')->where('original_order_id', $invoice->order_id)->where('status', 'confirmed')->sum('total');
        $refunded = (float) DB::table('orders')->where('original_order_id', $invoice->order_id)->where('status', 'confirmed')->sum('refund_amount');

        return max((float) $invoice->total + $refunded - (float) DB::table('collections')->where('invoice_id', $invoiceId)->where('status', 'approved')->sum('amount') - $returned, 0);
    }

    private function customerDueSummary(int $customerId, float $collected): array
    {
        $paymentsRemaining = $collected;
        $overdue = 0;
        $nextDueDate = null;
        $invoices = DB::table('invoices')
            ->join('orders', 'invoices.order_id', '=', 'orders.id')
            ->where('invoices.customer_id', $customerId)
            ->where('orders.payment_type', 'credit')
            ->where('invoices.status', '!=', 'cancelled')
            ->orderByRaw('COALESCE(invoices.due_date, invoices.invoice_date)')
            ->orderBy('invoices.id')
            ->get(['invoices.total', 'invoices.invoice_date', 'invoices.due_date']);

        foreach ($invoices as $invoice) {
            $amount = (float) $invoice->total;
            $settled = min($amount, $paymentsRemaining);
            $paymentsRemaining -= $settled;
            $outstanding = $amount - $settled;
            if ($outstanding <= 0) {
                continue;
            }
            $dueDate = $invoice->due_date ?: $invoice->invoice_date;
            if (Carbon::parse($dueDate)->isBefore(today())) {
                $overdue += $outstanding;
            } elseif ($nextDueDate === null || $dueDate < $nextDueDate) {
                $nextDueDate = $dueDate;
            }
        }

        return ['overdue_amount' => $overdue, 'next_due_date' => $nextDueDate];
    }

    private function customerMeta($item)
    {
        $item->outstanding = $this->customerOutstanding((int) $item->id);
        $item->label = "{$item->code} - {$item->shop_name}";

        return $item;
    }

    private function mobileScope(Request $request, string $action, ?string $resource = null): array
    {
        $user = $request->user();
        if ($user->role === 'Customer') {
            $this->authorizePermission($request, 'client.finance.view');
            abort_unless($action === 'view' && $resource === null, 403);
            abort_unless($user->customer_id, 404);

            return ['app' => 'client', 'customer_id' => $user->customer_id, 'employee_id' => null, 'route_id' => null];
        }
        $app = $user->role === 'Sales Representative' ? 'sales' : 'driver';
        $permissionResource = $resource ?? 'finance';
        $this->authorizePermission($request, "{$app}.{$permissionResource}.{$action}");
        abort_unless($user->employee_id, 403);
        $routeId = DB::table('employees')->where('id', $user->employee_id)->value('assigned_route_id');

        return ['app' => $app, 'customer_id' => null, 'employee_id' => $user->employee_id, 'route_id' => $routeId];
    }

    private function collectionQuery()
    {
        return DB::table('collections')->join('customers', 'collections.customer_id', '=', 'customers.id')->leftJoin('invoices', 'collections.invoice_id', '=', 'invoices.id')->leftJoin('employees', 'collections.employee_id', '=', 'employees.id');
    }

    private function collectionColumns(): array
    {
        return ['collections.*', 'customers.code as customer_code', 'customers.shop_name', 'invoices.code as invoice_code', 'employees.code as employee_code', 'employees.name as employee_name'];
    }

    private function collectionPayload($item): array
    {
        return ['id' => $item->id, 'code' => $item->code, 'customer_id' => $item->customer_id, 'customer_code' => $item->customer_code, 'shop_name' => $item->shop_name, 'invoice_id' => $item->invoice_id, 'invoice_code' => $item->invoice_code, 'delivery_id' => $item->delivery_id, 'employee_id' => $item->employee_id, 'employee_code' => $item->employee_code, 'employee_name' => $item->employee_name, 'collection_date' => Carbon::parse($item->collection_date)->toDateString(), 'amount' => (float) $item->amount, 'payment_method' => $item->payment_method, 'reference_no' => $item->reference_no, 'source_app' => $item->source_app, 'status' => $item->status, 'notes' => $item->notes];
    }

    private function pendingDriverCashHandovers()
    {
        return DB::table('collections')
            ->join('employees', 'collections.employee_id', '=', 'employees.id')
            ->where('employees.employee_type', 'driver')
            ->where('collections.source_app', 'driver')
            ->where('collections.payment_method', 'cash')
            ->where('collections.status', 'submitted')
            ->orderBy('employees.name')
            ->orderBy('collections.id')
            ->get([
                'collections.id',
                'collections.employee_id',
                'collections.amount',
                'collections.collection_date',
                'employees.code as employee_code',
                'employees.name as employee_name',
            ])
            ->groupBy('employee_id')
            ->map(function ($collections) {
                $first = $collections->first();

                return [
                    'employee_id' => (int) $first->employee_id,
                    'employee_code' => $first->employee_code,
                    'employee_name' => $first->employee_name,
                    'collections_count' => $collections->count(),
                    'amount' => (float) $collections->sum('amount'),
                    'oldest_collection_date' => $collections->min('collection_date'),
                    'latest_collection_date' => $collections->max('collection_date'),
                    'collection_ids' => $collections->pluck('id')->map(fn ($id) => (int) $id)->values(),
                ];
            })
            ->values();
    }

    private function expenseQuery()
    {
        return DB::table('expenses')->leftJoin('employees', 'expenses.employee_id', '=', 'employees.id');
    }

    private function expenseColumns(): array
    {
        return ['expenses.*', 'employees.code as employee_code', 'employees.name as employee_name'];
    }

    private function expensePayload($item): array
    {
        return ['id' => $item->id, 'code' => $item->code, 'employee_id' => $item->employee_id, 'employee_code' => $item->employee_code, 'employee_name' => $item->employee_name, 'expense_date' => Carbon::parse($item->expense_date)->toDateString(), 'expense_type' => $item->expense_type, 'category' => $item->category, 'description' => $item->description, 'amount' => (float) $item->amount, 'payment_method' => $item->payment_method, 'reference_no' => $item->reference_no, 'source_app' => $item->source_app, 'status' => $item->status, 'notes' => $item->notes];
    }

    private function applyCommonFilters($query, Request $request, string $table, string $dateColumn): void
    {
        if ($request->filled('date_from')) {
            $query->whereDate("{$table}.{$dateColumn}", '>=', $request->query('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate("{$table}.{$dateColumn}", '<=', $request->query('date_to'));
        }
    }

    private function amountSummary($summary): array
    {
        return ['records_count' => (int) ($summary->records_count ?? 0), 'total_amount' => (float) ($summary->total_amount ?? 0), 'submitted_amount' => (float) ($summary->submitted_amount ?? 0), 'approved_amount' => (float) ($summary->approved_amount ?? 0)];
    }

    private function pagination($paginator): array
    {
        return ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total()];
    }

    private function nextCode(string $prefix, string $table, string $dateColumn, Carbon $date): string
    {
        $base = $prefix.'-'.$date->format('Ym').'-';
        $next = DB::table($table)->where($dateColumn, '>=', $date->copy()->startOfMonth())->where($dateColumn, '<=', $date->copy()->endOfMonth())->count() + 1;

        return $base.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless(in_array($permission, AppAccess::permissionsForRole($request->user()?->role), true), 403);
    }
}
