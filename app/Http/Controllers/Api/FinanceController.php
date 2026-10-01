<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Expense;
use App\Models\FinancialTransaction;
use App\Models\SupplierLedgerEntry;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
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
        $this->authorizePermission($request, ['office.finance.collections.view', self::VIEW_PERMISSION]);

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
        $this->authorizePermission($request, ['office.finance.collections.view', self::VIEW_PERMISSION]);
        $query = $this->collectionQuery()->latest('collections.collection_date')->latest('collections.id');
        $this->applyCommonFilters($query, $request, 'collections', 'collection_date');
        foreach (['customer_id', 'employee_id', 'status', 'payment_method'] as $filter) {
            if ($request->filled($filter)) {
                $query->where("collections.{$filter}", $request->query($filter));
            }
        }
        if ($request->filled('source_app')) {
            $query->where('collections.source_app', $request->query('source_app'));
        }
        if ($request->query('receiver') === 'office') {
            $query->whereNull('collections.employee_id');
        } elseif (str_starts_with((string) $request->query('receiver'), 'employee:')) {
            $query->where('collections.employee_id', (int) Str::after((string) $request->query('receiver'), 'employee:'));
        }
        if ($request->filled('min_amount')) {
            $query->where('collections.amount', '>=', (float) $request->query('min_amount'));
        }
        if ($request->filled('max_amount')) {
            $query->where('collections.amount', '<=', (float) $request->query('max_amount'));
        }
        if ($request->boolean('outdoor')) {
            $query->whereNotNull('collections.employee_id');
        }
        if ($search = trim((string) $request->query('search'))) {
            $query->where(fn ($q) => $q->where('collections.code', 'like', "%{$search}%")
                ->orWhere('collections.reference_no', 'like', "%{$search}%")
                ->orWhere('customers.code', 'like', "%{$search}%")
                ->orWhere('customers.shop_name', 'like', "%{$search}%")
                ->orWhere('invoices.code', 'like', "%{$search}%")
                ->orWhere('employees.code', 'like', "%{$search}%")
                ->orWhere('employees.name', 'like', "%{$search}%"));
        }
        $summary = (clone $query)->reorder()->selectRaw("COUNT(*) records_count, COALESCE(SUM(collections.amount),0) total_amount, COALESCE(SUM(CASE WHEN collections.status='submitted' THEN collections.amount ELSE 0 END),0) submitted_amount, COALESCE(SUM(CASE WHEN collections.status='approved' THEN collections.amount ELSE 0 END),0) approved_amount")->first();
        $paginator = $query->select($this->collectionColumns())->paginate(min(max((int) $request->query('per_page', 20), 1), 100));
        $cashHandovers = $request->boolean('outdoor') ? $this->pendingDriverCashHandovers() : collect();

        return ApiResponse::success('Collections loaded.', ['items' => collect($paginator->items())->map(fn ($item) => $this->collectionPayload($item)), 'cash_handovers' => $cashHandovers, 'summary' => $this->amountSummary($summary), 'meta' => $this->pagination($paginator)]);
    }

    public function storeCollection(Request $request)
    {
        $this->authorizePermission($request, ['office.finance.collections.create', self::MANAGE_PERMISSION]);
        $validated = $this->collectionRules($request);
        $this->validateCollectionScope($validated);
        $collection = DB::transaction(function () use ($request, $validated) {
            $maySelfPost = in_array(self::MANAGE_PERMISSION, AppAccess::permissionsForRole($request->user()->role), true);
            $collection = Collection::create($validated + ['code' => $this->nextCode('COL', 'collections', 'collection_date', Carbon::parse($validated['collection_date'])), 'source_app' => 'office', 'status' => $maySelfPost ? 'approved' : 'submitted', 'submitted_by' => $request->user()->id, 'reviewed_by' => $maySelfPost ? $request->user()->id : null, 'reviewed_at' => $maySelfPost ? now() : null]);
            if ($maySelfPost) {
                $this->postCollection($collection, $request->user()->id);
            }

            return $collection;
        });

        return ApiResponse::success('Collection recorded.', ['collection' => $this->collectionPayload($this->collectionQuery()->select($this->collectionColumns())->where('collections.id', $collection->id)->first())], 201);
    }

    public function reviewCollection(Request $request, Collection $collection)
    {
        $this->authorizePermission($request, ['office.finance.collections.review', self::MANAGE_PERMISSION]);
        abort_unless($collection->status === 'submitted', 409, 'Only submitted collections can be reviewed.');
        if ($request->user()?->role === 'Finance Manager') {
            abort_if($collection->submitted_by === $request->user()->id, 409, 'The collection submitter cannot review their own record.');
        }
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
        $this->authorizePermission($request, ['office.finance.collections.receive', self::MANAGE_PERMISSION]);
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
        $this->authorizePermission($request, ['office.finance.receivables.view', self::VIEW_PERMISSION]);
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
        if ($request->filled('route_id')) {
            $query->where('customers.route_id', $request->query('route_id'));
        }
        $items = $query->orderBy('customers.shop_name')->get(['customers.id', 'customers.code', 'customers.shop_name', 'customers.route_id', 'customers.credit_limit', 'routes.name as route_name', DB::raw('COALESCE(invoice_totals.invoiced_amount,0) invoiced_amount'), DB::raw('COALESCE(collection_totals.collected_amount,0) collected_amount')])->map(function ($item) {
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

        $scope = $request->query('credit_scope');
        $items = $items->filter(fn ($item) => match ($scope) {
            'outstanding' => $item->outstanding_amount > 0,
            'overdue' => $item->overdue_amount > 0,
            'customer_credit' => $item->customer_credit_amount > 0,
            'settled' => $item->outstanding_amount <= 0 && $item->customer_credit_amount <= 0,
            'activity' => $item->invoiced_amount > 0 || $item->collected_amount > 0 || $item->return_credits_amount > 0,
            default => true,
        });

        foreach ([
            'credit_limit' => 'credit_limit',
            'credit_sales' => 'invoiced_amount',
            'payments' => 'collected_amount',
            'returns' => 'return_credits_amount',
            'outstanding' => 'outstanding_amount',
            'customer_credit' => 'customer_credit_amount',
        ] as $filter => $property) {
            if ($request->filled("min_{$filter}")) {
                $minimum = (float) $request->query("min_{$filter}");
                $items = $items->filter(fn ($item) => $item->{$property} >= $minimum);
            }
            if ($request->filled("max_{$filter}")) {
                $maximum = (float) $request->query("max_{$filter}");
                $items = $items->filter(fn ($item) => $item->{$property} <= $maximum);
            }
        }

        $items = (match ($request->query('sort')) {
            'outstanding_desc' => $items->sortByDesc('outstanding_amount'),
            'credit_sales_desc' => $items->sortByDesc('invoiced_amount'),
            'payments_desc' => $items->sortByDesc('collected_amount'),
            'customer_credit_desc' => $items->sortByDesc('customer_credit_amount'),
            'credit_limit_desc' => $items->sortByDesc('credit_limit'),
            default => $items->sortBy('shop_name', SORT_NATURAL | SORT_FLAG_CASE),
        })->values();

        $routes = DB::table('routes')->where('is_active', true)->whereExists(fn ($query) => $query->selectRaw('1')->from('customers')->whereColumn('customers.route_id', 'routes.id')->where('customers.is_active', true))->orderBy('name')->get(['id', 'code', 'name']);

        return ApiResponse::success('Customer credit loaded.', ['items' => $items, 'filter_options' => ['routes' => $routes], 'summary' => ['customers_count' => $items->count(), 'credit_sales_amount' => (float) $items->sum('invoiced_amount'), 'invoiced_amount' => (float) $items->sum('invoiced_amount'), 'collected_amount' => (float) $items->sum('collected_amount'), 'return_credits_amount' => (float) $items->sum('return_credits_amount'), 'customer_credit_amount' => (float) $items->sum('customer_credit_amount'), 'outstanding_amount' => (float) $items->sum('outstanding_amount'), 'overdue_amount' => (float) $items->sum('overdue_amount')]]);
    }

    public function customerLedger(Request $request, int $customerId)
    {
        $this->authorizePermission($request, ['office.finance.receivables.view', self::VIEW_PERMISSION]);
        abort_unless(DB::table('customers')->where('id', $customerId)->exists(), 404);

        return ApiResponse::success('Customer ledger loaded.', $this->customerLedgerData($customerId));
    }

    public function expenses(Request $request)
    {
        $this->authorizePermission($request, ['office.finance.expenses.view', self::VIEW_PERMISSION]);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'expense_type' => ['nullable', Rule::in(['daily', 'outdoor'])],
            'status' => ['nullable', Rule::in(['submitted', 'approved', 'rejected'])],
            'category' => ['nullable', 'string', 'max:80'],
            'employee_scope' => ['nullable', Rule::in(['office', 'employee'])],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'payment_method' => ['nullable', Rule::in(['cash', 'bank'])],
            'source_app' => ['nullable', 'string', 'max:40'],
            'submitted_by' => ['nullable', 'integer', 'exists:users,id'],
            'reviewed_by' => ['nullable', 'integer', 'exists:users,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', Rule::when($request->filled('date_from'), ['after_or_equal:date_from'])],
            'reviewed_from' => ['nullable', 'date'],
            'reviewed_to' => ['nullable', 'date', Rule::when($request->filled('reviewed_from'), ['after_or_equal:reviewed_from'])],
            'amount_min' => ['nullable', 'numeric', 'min:0'],
            'amount_max' => ['nullable', 'numeric', 'min:0', Rule::when($request->filled('amount_min'), ['gte:amount_min'])],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'amount_desc', 'amount_asc'])],
        ]);
        $optionQuery = $this->expenseQuery();
        if (! empty($filters['expense_type'])) $optionQuery->where('expenses.expense_type', $filters['expense_type']);
        $filterOptions = [
            'categories' => (clone $optionQuery)->whereNotNull('expenses.category')->distinct()->orderBy('expenses.category')->pluck('expenses.category')->values(),
            'source_apps' => (clone $optionQuery)->whereNotNull('expenses.source_app')->distinct()->orderBy('expenses.source_app')->pluck('expenses.source_app')->values(),
            'employees' => (clone $optionQuery)->whereNotNull('expenses.employee_id')->distinct()->orderBy('employees.name')->get(['employees.id', 'employees.code', 'employees.name']),
            'submitters' => (clone $optionQuery)->whereNotNull('expenses.submitted_by')->distinct()->orderBy('submitters.name')->get(['submitters.id', 'submitters.name']),
            'reviewers' => (clone $optionQuery)->whereNotNull('expenses.reviewed_by')->distinct()->orderBy('reviewers.name')->get(['reviewers.id', 'reviewers.name']),
        ];
        $query = $this->expenseQuery();
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn ($q) => $q->where('expenses.code', 'like', "%{$search}%")
                ->orWhere('expenses.description', 'like', "%{$search}%")
                ->orWhere('expenses.reference_no', 'like', "%{$search}%")
                ->orWhere('expenses.notes', 'like', "%{$search}%")
                ->orWhere('expenses.category', 'like', "%{$search}%")
                ->orWhere('employees.code', 'like', "%{$search}%")
                ->orWhere('employees.name', 'like', "%{$search}%")
                ->orWhere('submitters.name', 'like', "%{$search}%")
                ->orWhere('reviewers.name', 'like', "%{$search}%"));
        }
        foreach (['expense_type', 'status', 'category', 'employee_id', 'payment_method', 'source_app', 'submitted_by', 'reviewed_by'] as $filter) {
            if (! empty($filters[$filter])) $query->where("expenses.{$filter}", $filters[$filter]);
        }
        if (($filters['employee_scope'] ?? null) === 'office') $query->whereNull('expenses.employee_id');
        if (($filters['employee_scope'] ?? null) === 'employee') $query->whereNotNull('expenses.employee_id');
        if (! empty($filters['date_from'])) $query->whereDate('expenses.expense_date', '>=', $filters['date_from']);
        if (! empty($filters['date_to'])) $query->whereDate('expenses.expense_date', '<=', $filters['date_to']);
        if (! empty($filters['reviewed_from'])) $query->whereDate('expenses.reviewed_at', '>=', $filters['reviewed_from']);
        if (! empty($filters['reviewed_to'])) $query->whereDate('expenses.reviewed_at', '<=', $filters['reviewed_to']);
        if (isset($filters['amount_min'])) $query->where('expenses.amount', '>=', $filters['amount_min']);
        if (isset($filters['amount_max'])) $query->where('expenses.amount', '<=', $filters['amount_max']);
        $summary = (clone $query)->reorder()->selectRaw("COUNT(*) records_count, COALESCE(SUM(expenses.amount),0) total_amount, COALESCE(SUM(CASE WHEN expenses.status='submitted' THEN expenses.amount ELSE 0 END),0) submitted_amount, COALESCE(SUM(CASE WHEN expenses.status='approved' THEN expenses.amount ELSE 0 END),0) approved_amount")->first();
        match ($filters['sort'] ?? 'newest') {
            'oldest' => $query->orderBy('expenses.expense_date')->orderBy('expenses.id'),
            'amount_desc' => $query->orderByDesc('expenses.amount')->orderByDesc('expenses.id'),
            'amount_asc' => $query->orderBy('expenses.amount')->orderBy('expenses.id'),
            default => $query->orderByDesc('expenses.expense_date')->orderByDesc('expenses.id'),
        };
        $paginator = $query->select($this->expenseColumns())->paginate(min(max((int) $request->query('per_page', 20), 1), 100));

        return ApiResponse::success('Expenses loaded.', ['items' => collect($paginator->items())->map(fn ($item) => $this->expensePayload($item)), 'summary' => $this->amountSummary($summary), 'meta' => $this->pagination($paginator), 'filter_options' => $filterOptions]);
    }

    public function storeExpense(Request $request)
    {
        $this->authorizePermission($request, ['office.finance.expenses.create', self::MANAGE_PERMISSION]);
        $validated = $this->expenseRules($request, true);
        $expense = DB::transaction(function () use ($request, $validated) {
            $maySelfPost = in_array(self::MANAGE_PERMISSION, AppAccess::permissionsForRole($request->user()->role), true);
            $expense = Expense::create($validated + ['code' => $this->nextCode('EXP', 'expenses', 'expense_date', Carbon::parse($validated['expense_date'])), 'source_app' => 'office', 'status' => $maySelfPost ? 'approved' : 'submitted', 'submitted_by' => $request->user()->id, 'reviewed_by' => $maySelfPost ? $request->user()->id : null, 'reviewed_at' => $maySelfPost ? now() : null]);
            if ($maySelfPost) {
                $this->postExpense($expense, $request->user()->id);
            }

            return $expense;
        });

        return ApiResponse::success('Expense recorded.', ['expense' => $this->expensePayload($this->expenseQuery()->select($this->expenseColumns())->where('expenses.id', $expense->id)->first())], 201);
    }

    public function reviewExpense(Request $request, Expense $expense)
    {
        $this->authorizePermission($request, ['office.finance.expenses.review', self::MANAGE_PERMISSION]);
        abort_unless($expense->status === 'submitted', 409, 'Only submitted expenses can be reviewed.');
        if ($request->user()?->role === 'Finance Manager') {
            abort_if($expense->submitted_by === $request->user()->id, 409, 'The expense submitter cannot review their own record.');
        }
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
        $this->authorizePermission($request, ['office.finance.books.view', self::VIEW_PERMISSION]);
        abort_unless(in_array($book, ['cash', 'bank'], true), 404);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'direction' => ['nullable', Rule::in(['in', 'out'])],
            'category' => ['nullable', 'string', 'max:80'],
            'reference_type' => ['nullable', 'string', 'max:80'],
            'created_by' => ['nullable', 'integer', 'exists:users,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', Rule::when($request->filled('date_from'), ['after_or_equal:date_from'])],
            'amount_min' => ['nullable', 'numeric', 'min:0'],
            'amount_max' => ['nullable', 'numeric', 'min:0', Rule::when($request->filled('amount_min'), ['gte:amount_min'])],
            'sort' => ['nullable', Rule::in(['newest', 'oldest'])],
        ]);
        $bookQuery = FinancialTransaction::where('book_type', $book);
        $filterOptions = [
            'categories' => (clone $bookQuery)->whereNotNull('category')->distinct()->orderBy('category')->pluck('category')->values(),
            'reference_types' => (clone $bookQuery)->whereNotNull('reference_type')->distinct()->orderBy('reference_type')->pluck('reference_type')->values(),
            'creators' => DB::table('users')
                ->whereIn('id', (clone $bookQuery)->whereNotNull('created_by')->select('created_by'))
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
        $query = clone $bookQuery;
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($builder) use ($search) {
                $builder->where('code', 'like', "%{$search}%")
                    ->orWhere('reference_code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }
        if (! empty($filters['direction'])) $query->where('direction', $filters['direction']);
        if (! empty($filters['category'])) $query->where('category', $filters['category']);
        if (! empty($filters['reference_type'])) $query->where('reference_type', $filters['reference_type']);
        if (! empty($filters['created_by'])) $query->where('created_by', $filters['created_by']);
        if (! empty($filters['date_from'])) $query->whereDate('transaction_date', '>=', $filters['date_from']);
        if (! empty($filters['date_to'])) $query->whereDate('transaction_date', '<=', $filters['date_to']);
        if (isset($filters['amount_min'])) $query->where('amount', '>=', $filters['amount_min']);
        if (isset($filters['amount_max'])) $query->where('amount', '<=', $filters['amount_max']);
        $query->orderBy('transaction_date')->orderBy('id');
        $items = $query->get()->map(fn ($item) => ['id' => $item->id, 'code' => $item->code, 'transaction_date' => $item->transaction_date->toDateString(), 'direction' => $item->direction, 'category' => $item->category, 'amount' => (float) $item->amount, 'reference_code' => $item->reference_code, 'description' => $item->description]);
        $balance = 0;
        $items = $items->map(function ($item) use (&$balance) {
            $balance += $item['direction'] === 'in' ? $item['amount'] : -$item['amount'];
            $item['balance'] = $balance;

            return $item;
        });

        if (($filters['sort'] ?? 'newest') === 'newest') $items = $items->reverse()->values();

        return ApiResponse::success(ucfirst($book).' book loaded.', ['items' => $items, 'summary' => ['inflow' => (float) $items->where('direction', 'in')->sum('amount'), 'outflow' => (float) $items->where('direction', 'out')->sum('amount'), 'balance' => (float) $balance], 'filter_options' => $filterOptions]);
    }

    public function suppliers(Request $request)
    {
        $this->authorizePermission($request, ['office.finance.suppliers.view', self::VIEW_PERMISSION]);
        $items = DB::table('suppliers')->leftJoin('supplier_ledger_entries', 'suppliers.id', '=', 'supplier_ledger_entries.supplier_id')->groupBy('suppliers.id', 'suppliers.code', 'suppliers.name', 'suppliers.phone')->orderBy('suppliers.name')->get(['suppliers.id', 'suppliers.code', 'suppliers.name', 'suppliers.phone', DB::raw('COALESCE(SUM(supplier_ledger_entries.credit - supplier_ledger_entries.debit),0) balance')])->map(function ($item) {
            $item->balance = (float) $item->balance;

            return $item;
        });

        return ApiResponse::success('Supplier balances loaded.', ['items' => $items, 'summary' => ['suppliers_count' => $items->count(), 'payable_amount' => (float) $items->sum('balance')]]);
    }

    public function supplierLedger(Request $request, int $supplierId)
    {
        $this->authorizePermission($request, ['office.finance.suppliers.view', self::VIEW_PERMISSION]);
        $supplier = DB::table('suppliers')->find($supplierId);
        abort_unless($supplier, 404);
        $invoicePerPage = min(max((int) $request->query('invoice_per_page', 10), 5), 50);
        $ledgerPerPage = min(max((int) $request->query('ledger_per_page', 15), 5), 50);
        $today = today()->toDateString();

        $ledgerQuery = SupplierLedgerEntry::where('supplier_id', $supplierId);
        $balance = (float) (clone $ledgerQuery)->selectRaw('COALESCE(SUM(credit - debit), 0) as balance')->first()->balance;
        $ledgerPaginator = (clone $ledgerQuery)
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate($ledgerPerPage, ['*'], 'ledger_page');
        $ledgerOffset = ($ledgerPaginator->currentPage() - 1) * $ledgerPaginator->perPage();
        $newerBalance = $ledgerOffset > 0
            ? (float) (clone $ledgerQuery)->orderByDesc('entry_date')->orderByDesc('id')->limit($ledgerOffset)->get()->sum(fn ($entry) => (float) $entry->credit - (float) $entry->debit)
            : 0;
        $pageBalance = $balance - $newerBalance;
        $items = collect($ledgerPaginator->items())->map(function ($item) use (&$pageBalance) {
            $rowBalance = $pageBalance;
            $pageBalance -= (float) $item->credit - (float) $item->debit;

            return ['id' => $item->id, 'entry_date' => $item->entry_date->toDateString(), 'entry_type' => $item->entry_type, 'reference_no' => $item->reference_no, 'description' => $item->description, 'debit' => (float) $item->debit, 'credit' => (float) $item->credit, 'balance' => $rowBalance];
        });

        $invoiceQuery = SupplierInvoice::where('supplier_id', $supplierId);
        $invoicePaginator = (clone $invoiceQuery)
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->paginate($invoicePerPage, ['*'], 'invoice_page');
        $invoices = collect($invoicePaginator->items())
            ->map(function ($invoice) {
                $outstanding = max(0, (float) $invoice->total - (float) $invoice->paid_amount);
                $overdue = $outstanding > 0 && $invoice->due_date?->isBefore(today());

                return [
                    'id' => $invoice->id,
                    'code' => $invoice->code,
                    'stock_document_code' => $invoice->stock_document_code,
                    'invoice_no' => $invoice->invoice_no,
                    'invoice_date' => $invoice->invoice_date->toDateString(),
                    'due_date' => $invoice->due_date?->toDateString(),
                    'payment_terms_days' => (int) $invoice->payment_terms_days,
                    'total' => (float) $invoice->total,
                    'paid_amount' => (float) $invoice->paid_amount,
                    'outstanding' => $outstanding,
                    'status' => $overdue ? 'overdue' : $invoice->status,
                    'notes' => $invoice->notes,
                ];
            });
        $invoiceSummary = (clone $invoiceQuery)->selectRaw(
            'COUNT(*) as invoice_count,
             COALESCE(SUM(total), 0) as invoice_total,
             COALESCE(SUM(paid_amount), 0) as paid_amount,
             COALESCE(SUM(GREATEST(total - paid_amount, 0)), 0) as outstanding_amount,
             COALESCE(SUM(CASE WHEN due_date < ? AND total > paid_amount THEN total - paid_amount ELSE 0 END), 0) as overdue_amount,
             SUM(CASE WHEN total > paid_amount THEN 1 ELSE 0 END) as open_invoice_count,
             SUM(CASE WHEN total <= paid_amount THEN 1 ELSE 0 END) as paid_invoice_count',
            [$today]
        )->first();

        return ApiResponse::success('Supplier ledger loaded.', [
            'supplier' => $supplier,
            'items' => $items,
            'ledger_meta' => $this->pagination($ledgerPaginator),
            'invoices' => $invoices,
            'invoice_meta' => $this->pagination($invoicePaginator),
            'summary' => [
                'balance' => (float) $balance,
                'invoice_count' => (int) $invoiceSummary->invoice_count,
                'invoice_total' => (float) $invoiceSummary->invoice_total,
                'paid_amount' => (float) $invoiceSummary->paid_amount,
                'outstanding_amount' => (float) $invoiceSummary->outstanding_amount,
                'overdue_amount' => (float) $invoiceSummary->overdue_amount,
                'open_invoice_count' => (int) $invoiceSummary->open_invoice_count,
                'paid_invoice_count' => (int) $invoiceSummary->paid_invoice_count,
                'ledger_count' => $ledgerPaginator->total(),
            ],
        ]);
    }

    public function storeSupplierEntry(Request $request, int $supplierId)
    {
        $this->authorizePermission($request, ['office.finance.suppliers.adjust', self::MANAGE_PERMISSION]);
        abort_unless(DB::table('suppliers')->where('id', $supplierId)->exists(), 404);
        $validated = $request->validate(['entry_date' => ['required', 'date'], 'entry_type' => ['required', Rule::in(['adjustment'])], 'reference_no' => ['nullable', 'string', 'max:100'], 'description' => ['required', 'string', 'max:255'], 'amount' => ['required', 'numeric', 'not_in:0']]);
        $entry = SupplierLedgerEntry::create(['supplier_id' => $supplierId, 'entry_date' => $validated['entry_date'], 'entry_type' => 'adjustment', 'reference_no' => $validated['reference_no'] ?? null, 'description' => $validated['description'], 'debit' => $validated['amount'] < 0 ? abs($validated['amount']) : 0, 'credit' => $validated['amount'] > 0 ? $validated['amount'] : 0, 'created_by' => $request->user()->id]);

        return ApiResponse::success('Supplier ledger entry recorded.', ['entry_id' => $entry->id, 'entry' => ['id' => $entry->id, 'entry_date' => $entry->entry_date->toDateString(), 'entry_type' => $entry->entry_type, 'reference_no' => $entry->reference_no, 'description' => $entry->description, 'debit' => (float) $entry->debit, 'credit' => (float) $entry->credit]], 201);
    }

    public function storeSupplierPayment(Request $request, int $supplierId)
    {
        $this->authorizePermission($request, ['office.finance.suppliers.pay', self::MANAGE_PERMISSION]);
        abort_unless(DB::table('suppliers')->where('id', $supplierId)->exists(), 404);
        $validated = $request->validate([
            'supplier_invoice_id' => ['required', 'integer', 'exists:supplier_invoices,id'],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', Rule::in(['cash', 'bank'])],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $result = DB::transaction(function () use ($validated, $supplierId, $request) {
            $invoice = SupplierInvoice::where('supplier_id', $supplierId)
                ->whereKey($validated['supplier_invoice_id'])
                ->lockForUpdate()
                ->first();
            abort_unless($invoice, 422, 'Select an invoice for this supplier.');
            $outstanding = max(0, (float) $invoice->total - (float) $invoice->paid_amount);
            abort_if((float) $validated['amount'] > $outstanding + 0.001, 422, 'Payment cannot exceed the invoice outstanding amount.');

            $payment = SupplierPayment::create([
                'code' => 'SPY-'.Carbon::parse($validated['payment_date'])->format('Ym').'-'.str_pad((string) (SupplierPayment::max('id') + 1), 4, '0', STR_PAD_LEFT),
                'supplier_id' => $supplierId,
                'supplier_invoice_id' => $invoice->id,
                'payment_date' => $validated['payment_date'],
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'reference_no' => $validated['reference_no'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_by' => $request->user()?->id,
            ]);

            $paidAmount = (float) $invoice->paid_amount + (float) $payment->amount;
            $invoice->update([
                'paid_amount' => $paidAmount,
                'status' => $paidAmount + 0.001 >= (float) $invoice->total ? 'paid' : 'partial',
            ]);

            SupplierLedgerEntry::create([
                'supplier_id' => $supplierId,
                'entry_date' => $payment->payment_date,
                'entry_type' => 'payment',
                'source_type' => 'supplier_payment',
                'source_key' => $payment->code,
                'reference_no' => $payment->reference_no ?: $payment->code,
                'description' => "Payment for {$invoice->code}",
                'debit' => $payment->amount,
                'credit' => 0,
                'created_by' => $request->user()?->id,
            ]);

            $this->postTransaction('supplier_payment', $payment->id, $payment->code, $payment->payment_date, $payment->payment_method, 'out', 'supplier_payment', $payment->amount, "Supplier payment {$payment->code}", $request->user()?->id);

            return [$payment, $invoice->refresh()];
        }, 3);

        [$payment, $invoice] = $result;

        return ApiResponse::success('Supplier payment recorded.', [
            'payment' => [
                'id' => $payment->id,
                'code' => $payment->code,
                'payment_date' => $payment->payment_date->toDateString(),
                'amount' => (float) $payment->amount,
                'payment_method' => $payment->payment_method,
                'reference_no' => $payment->reference_no,
            ],
            'invoice' => [
                'id' => $invoice->id,
                'status' => $invoice->status,
                'paid_amount' => (float) $invoice->paid_amount,
                'outstanding' => max(0, (float) $invoice->total - (float) $invoice->paid_amount),
            ],
        ], 201);
    }

    public function profitLoss(Request $request)
    {
        $this->authorizePermission($request, ['office.finance.profit-loss.view', self::VIEW_PERMISSION]);
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
            return ApiResponse::success('Customer finance loaded.', $this->customerLedgerData($scope['customer_id'], true));
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

        return ApiResponse::success('Cash collection submitted for Office review.', ['collection_id' => $collection->id, 'code' => $collection->code, 'collection' => $this->collectionPayload($this->collectionQuery()->select($this->collectionColumns())->where('collections.id', $collection->id)->first()), 'outstanding_after_approval' => max($this->customerOutstanding((int) $validated['customer_id']) - (float) $collection->amount, 0)], 201);
    }

    public function mobileStoreExpense(Request $request)
    {
        $scope = $this->mobileScope($request, 'create', 'expenses');
        $validated = $this->expenseRules($request, false);
        $expense = Expense::create($validated + ['code' => $this->nextCode('EXP', 'expenses', 'expense_date', Carbon::parse($validated['expense_date'])), 'expense_type' => 'outdoor', 'employee_id' => $scope['employee_id'], 'source_app' => $scope['app'], 'status' => 'submitted', 'submitted_by' => $request->user()->id]);

        return ApiResponse::success('Outdoor expense submitted for review.', ['expense_id' => $expense->id, 'code' => $expense->code, 'expense' => $this->expensePayload($this->expenseQuery()->select($this->expenseColumns())->where('expenses.id', $expense->id)->first())], 201);
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

    private function customerLedgerData(int $customerId, bool $includePendingBalance = false): array
    {
        $customer = DB::table('customers')->find($customerId);
        abort_unless($customer, 404);
        $entries = collect();
        DB::table('invoices')->join('orders', 'invoices.order_id', '=', 'orders.id')->where('invoices.customer_id', $customerId)->where('orders.payment_type', 'credit')->where('invoices.status', '!=', 'cancelled')->get(['invoices.*'])->each(fn ($item) => $entries->push(['key' => "I{$item->id}", 'date' => $item->invoice_date, 'due_date' => $item->due_date, 'type' => 'invoice', 'reference' => $item->code, 'description' => 'Credit sale', 'debit' => (float) $item->total, 'credit' => 0]));
        DB::table('collections')->where('customer_id', $customerId)->whereIn('status', ['submitted', 'approved'])->where(function ($query) {
            $query->whereNull('invoice_id')->orWhereExists(function ($subquery) {
                $subquery->selectRaw('1')->from('invoices')->join('orders', 'invoices.order_id', '=', 'orders.id')
                    ->whereColumn('invoices.id', 'collections.invoice_id')->where('orders.payment_type', 'credit');
            });
        })->get()->each(function ($item) use ($entries, $includePendingBalance) {
            $pending = $item->status === 'submitted';
            $entries->push([
                'key' => "C{$item->id}",
                'date' => $item->collection_date,
                'type' => 'collection',
                'status' => $item->status,
                'reference' => $item->code,
                'description' => $pending ? 'Payment submitted · Pending Office review' : 'Payment received',
                'debit' => 0,
                'credit' => $pending && ! $includePendingBalance ? 0 : (float) $item->amount,
                'pending_amount' => $pending ? (float) $item->amount : 0,
            ]);
        });
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

        $credit = $this->customerCredit->summary($customerId);

        $pendingCollections = DB::table('collections')->where('customer_id', $customerId)->where('status', 'submitted');
        $pendingAmount = (float) (clone $pendingCollections)->sum('amount');
        $displayOutstanding = max($credit['outstanding_amount'] - ($includePendingBalance ? $pendingAmount : 0), 0);
        $due = $this->customerDueSummary($customerId, $this->customerCredit->settlementCredits($customerId) + ($includePendingBalance ? $pendingAmount : 0));

        return ['customer' => ['id' => $customer->id, 'code' => $customer->code, 'shop_name' => $customer->shop_name, 'credit_limit' => (float) $customer->credit_limit], 'entries' => $entries->reverse()->values(), 'summary' => ['credit_sales_amount' => $credit['credit_sales_amount'], 'invoiced_amount' => $credit['credit_sales_amount'], 'collected_amount' => $credit['payments_amount'], 'pending_collection_amount' => $pendingAmount, 'pending_collection_count' => (clone $pendingCollections)->count(), 'return_credits_amount' => $credit['return_credits_amount'], 'refunds_amount' => $credit['refunds_amount'], 'customer_credit_amount' => $credit['customer_credit_amount'], 'outstanding_amount' => $displayOutstanding, 'overdue_amount' => $due['overdue_amount'], 'next_due_date' => $due['next_due_date']]];
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
        return DB::table('expenses')
            ->leftJoin('employees', 'expenses.employee_id', '=', 'employees.id')
            ->leftJoin('users as submitters', 'expenses.submitted_by', '=', 'submitters.id')
            ->leftJoin('users as reviewers', 'expenses.reviewed_by', '=', 'reviewers.id');
    }

    private function expenseColumns(): array
    {
        return ['expenses.*', 'employees.code as employee_code', 'employees.name as employee_name', 'submitters.name as submitted_by_name', 'reviewers.name as reviewed_by_name'];
    }

    private function expensePayload($item): array
    {
        return ['id' => $item->id, 'code' => $item->code, 'employee_id' => $item->employee_id, 'employee_code' => $item->employee_code, 'employee_name' => $item->employee_name, 'expense_date' => Carbon::parse($item->expense_date)->toDateString(), 'expense_type' => $item->expense_type, 'category' => $item->category, 'description' => $item->description, 'amount' => (float) $item->amount, 'payment_method' => $item->payment_method, 'reference_no' => $item->reference_no, 'source_app' => $item->source_app, 'status' => $item->status, 'notes' => $item->notes, 'submitted_by' => $item->submitted_by, 'submitted_by_name' => $item->submitted_by_name, 'reviewed_by' => $item->reviewed_by, 'reviewed_by_name' => $item->reviewed_by_name, 'reviewed_at' => $item->reviewed_at];
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

    private function authorizePermission(Request $request, string|array $permission): void
    {
        $permissions = AppAccess::permissionsForRole($request->user()?->role);
        abort_unless(array_intersect((array) $permission, $permissions), 403);
    }
}
