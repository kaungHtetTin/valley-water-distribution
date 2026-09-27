<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CustomerCreditService;
use App\Support\ApiResponse;
use App\Support\AppAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MobileOrderController extends Controller
{
    public function __construct(private readonly CustomerCreditService $customerCredit)
    {
    }

    public function meta(Request $request)
    {
        $scope = $this->scope($request, 'view');

        $customers = $scope['app'] === 'client'
            ? DB::table('customers')->where('id', $scope['customer_id'])->get(['id', 'code', 'shop_name', 'contact_name', 'phone', 'address', 'area_id', 'route_id', 'price_type_id', 'credit_limit'])
            : DB::table('customers')
                ->where('route_id', $scope['route_id'])
                ->where('is_active', true)
                ->orderBy('shop_name')
                ->get(['id', 'code', 'shop_name', 'contact_name', 'phone', 'address', 'area_id', 'route_id', 'price_type_id', 'credit_limit']);

        $customers = $customers->map(function ($customer) {
            $customer->label = "{$customer->code} - {$customer->shop_name}";
            $customer->credit_limit = (float) ($customer->credit_limit ?? 0);
            $customer->outstanding_balance = $this->outstandingBalance((int) $customer->id);
            $customer->available_credit = $customer->credit_limit > 0
                ? $customer->credit_limit - $customer->outstanding_balance
                : null;
            $customer->credit_status = $customer->available_credit !== null && $customer->available_credit < 0 ? 'over_limit' : 'ok';

            return $customer;
        });

        $products = DB::table('products')
            ->where('products.is_active', true)
            ->orderBy('products.name')
            ->get(['products.id', 'products.sku', 'products.name', 'products.unit'])
            ->map(function ($product) {
                $product->available_stock = (float) DB::table('stock_balances')->where('product_id', $product->id)->sum('quantity');
                $product->label = "{$product->sku} - {$product->name} ({$product->available_stock} {$product->unit})";
                $product->prices = DB::table('product_prices')
                    ->where('product_id', $product->id)
                    ->where('is_active', true)
                    ->orderByDesc('effective_from')
                    ->get(['price_type_id', 'amount']);

                return $product;
            });

        return ApiResponse::success('Mobile order setup loaded.', [
            'app' => $scope['app'],
            'customers' => $customers,
            'products' => $products,
            'price_types' => DB::table('price_types')->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'routes' => DB::table('routes')->where('is_active', true)
                ->when($scope['app'] === 'sales', fn ($query) => $query->where('id', $scope['route_id']))
                ->orderBy('name')->get(['id', 'area_id', 'code', 'name']),
            'areas' => DB::table('areas')->where('is_active', true)
                ->when($scope['app'] === 'sales', function ($query) use ($scope) {
                    $query->whereIn('id', DB::table('routes')->where('id', $scope['route_id'])->select('area_id'));
                })->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function index(Request $request)
    {
        $scope = $this->scope($request, 'view');
        $query = $this->scopedQuery($scope)->latest('orders.order_date')->latest('orders.id');

        if ($request->filled('status')) {
            $query->where('orders.status', $request->query('status'));
        }

        if ($request->filled('customer_id') && $scope['app'] === 'sales') {
            $query->where('orders.customer_id', $request->query('customer_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('orders.order_date', '>=', $request->query('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('orders.order_date', '<=', $request->query('date_to'));
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($query) use ($search) {
                $query->where('orders.code', 'like', "%{$search}%")
                    ->orWhere('orders.recipient_name', 'like', "%{$search}%")
                    ->orWhere('orders.delivery_address', 'like', "%{$search}%")
                    ->orWhere('customers.shop_name', 'like', "%{$search}%")
                    ->orWhere('customers.code', 'like', "%{$search}%");
            });
        }

        $summary = (clone $query)->reorder()
            ->selectRaw("COUNT(*) as orders_count, COALESCE(SUM(orders.total), 0) as total_amount, SUM(CASE WHEN orders.status = 'pending' THEN 1 ELSE 0 END) as pending_count")
            ->first();

        $paginator = $query->select($this->columns())
            ->paginate(min(max((int) $request->query('per_page', 12), 1), 50));

        return ApiResponse::success('Mobile orders loaded.', [
            'items' => collect($paginator->items())->map(fn ($order) => $this->payload($order)),
            'summary' => [
                'orders_count' => (int) ($summary->orders_count ?? 0),
                'pending_count' => (int) ($summary->pending_count ?? 0),
                'total_amount' => (float) ($summary->total_amount ?? 0),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Request $request, Order $order)
    {
        $scope = $this->scope($request, 'view');
        abort_unless($this->orderInScope($order, $scope), 404);

        $orderPayload = $this->baseQuery()->select($this->columns())->where('orders.id', $order->id)->first();
        abort_unless($orderPayload, 404);

        return ApiResponse::success('Mobile order loaded.', [
            'order' => $this->payload($orderPayload),
            'items' => $order->items()->orderBy('id')->get()->map(fn ($item) => $this->itemPayload($item)),
        ]);
    }

    public function store(Request $request)
    {
        $scope = $this->scope($request, 'create');
        $validated = $request->validate($this->rules($scope['app']));
        $customerId = $scope['app'] === 'client' ? $scope['customer_id'] : ($validated['customer_id'] ?? null);
        $customer = $customerId ? DB::table('customers')->where('is_active', true)->find($customerId) : null;
        abort_if($customerId && ! $customer, 422, 'The selected customer is not active.');
        abort_if($scope['app'] === 'sales' && $customer && (int) $customer->route_id !== (int) $scope['route_id'], 403, 'Customer is outside the assigned route.');
        $destination = $this->resolveDestination($validated, $customer);
        abort_if($scope['app'] === 'sales' && (int) $destination['route_id'] !== (int) $scope['route_id'], 403, 'Orders must stay inside the assigned sales route.');

        $orderDate = Carbon::parse($validated['order_date'] ?? now());
        $priceTypeId = $validated['price_type_id'] ?? $customer?->price_type_id ?? DB::table('price_types')->where('is_default', true)->value('id');
        $items = $this->normalizeItems($validated['items'], $priceTypeId, $orderDate);

        $order = DB::transaction(function () use ($customer, $destination, $items, $orderDate, $priceTypeId, $request, $scope, $validated) {
            $totals = $this->totals($items);
            $order = Order::create([
                'code' => $this->nextCode($orderDate),
                'customer_id' => $customer?->id,
                'area_id' => $destination['area_id'],
                'route_id' => $destination['route_id'],
                'price_type_id' => $priceTypeId,
                'recipient_name' => $destination['recipient_name'],
                'recipient_phone' => $destination['recipient_phone'],
                'delivery_address' => $destination['delivery_address'],
                'source_app' => $scope['app'],
                'order_date' => $orderDate->toDateString(),
                'requested_delivery_date' => $validated['requested_delivery_date'] ?? null,
                'credit_due_date' => null,
                'payment_type' => 'unsettled',
                'status' => ($validated['save_as'] ?? 'pending') === 'draft' ? 'draft' : 'pending',
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount_total'],
                'tax_total' => 0,
                'total' => $totals['total'],
                'created_by' => $request->user()?->id,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($items as $item) {
                $order->items()->create($item);
            }

            return $order;
        });

        $orderPayload = $this->baseQuery()->select($this->columns())->where('orders.id', $order->id)->first();
        abort_unless($orderPayload, 404);

        return ApiResponse::success('Mobile order created.', [
            'order' => $this->payload($orderPayload),
            'items' => $order->items()->orderBy('id')->get()->map(fn ($item) => $this->itemPayload($item)),
        ], 201);
    }

    public function update(Request $request, Order $order)
    {
        $scope = $this->scope($request, 'create');
        abort_unless($this->orderInScope($order, $scope), 404);
        abort_unless(in_array($order->status, ['draft', 'pending'], true), 422, 'Only draft or pending orders can be edited.');
        abort_if($scope['app'] === 'sales' && $order->source_app !== 'sales', 403, 'Only sales-app orders can be edited here.');

        $validated = $request->validate($this->rules($scope['app']));
        $customerId = $scope['app'] === 'client' ? $scope['customer_id'] : ($validated['customer_id'] ?? null);
        $customer = $customerId ? DB::table('customers')->where('is_active', true)->find($customerId) : null;
        abort_if($customerId && ! $customer, 422, 'The selected customer is not active.');
        abort_if($scope['app'] === 'sales' && $customer && (int) $customer->route_id !== (int) $scope['route_id'], 403, 'Customer is outside the assigned route.');
        $destination = $this->resolveDestination($validated, $customer);
        abort_if($scope['app'] === 'sales' && (int) $destination['route_id'] !== (int) $scope['route_id'], 403, 'Orders must stay inside the assigned sales route.');
        $orderDate = Carbon::parse($validated['order_date'] ?? $order->order_date);
        $priceTypeId = $validated['price_type_id'] ?? $customer?->price_type_id ?? DB::table('price_types')->where('is_default', true)->value('id');
        $items = $this->normalizeItems($validated['items'], $priceTypeId, $orderDate);

        DB::transaction(function () use ($customer, $destination, $items, $orderDate, $priceTypeId, $order, $validated) {
            $totals = $this->totals($items);
            $order->update([
                'customer_id' => $customer?->id,
                'area_id' => $destination['area_id'],
                'route_id' => $destination['route_id'],
                'price_type_id' => $priceTypeId,
                'recipient_name' => $destination['recipient_name'],
                'recipient_phone' => $destination['recipient_phone'],
                'delivery_address' => $destination['delivery_address'],
                'order_date' => $orderDate->toDateString(),
                'requested_delivery_date' => $validated['requested_delivery_date'] ?? null,
                'credit_due_date' => null,
                'payment_type' => 'unsettled',
                'status' => ($validated['save_as'] ?? 'pending') === 'draft' ? 'draft' : 'pending',
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount_total'],
                'total' => $totals['total'],
                'notes' => $validated['notes'] ?? null,
            ]);
            $order->items()->delete();
            foreach ($items as $item) $order->items()->create($item);
        });

        $orderPayload = $this->baseQuery()->select($this->columns())->where('orders.id', $order->id)->first();

        return ApiResponse::success('Mobile order updated.', [
            'order' => $this->payload($orderPayload),
            'items' => $order->items()->orderBy('id')->get()->map(fn ($item) => $this->itemPayload($item)),
        ]);
    }

    public function cancel(Request $request, Order $order)
    {
        $scope = $this->scope($request, 'create');
        abort_unless($this->orderInScope($order, $scope), 404);
        abort_unless(in_array($order->status, ['draft', 'pending'], true), 422, 'Only draft or pending orders can be cancelled.');
        abort_if($scope['app'] === 'sales' && $order->source_app !== 'sales', 403, 'Only sales-app orders can be cancelled here.');
        $order->update(['status' => 'cancelled']);

        return ApiResponse::success('Order cancelled.', ['order' => $this->payload($this->baseQuery()->select($this->columns())->where('orders.id', $order->id)->first())]);
    }

    private function scope(Request $request, string $action): array
    {
        $user = $request->user();

        if ($user->role === 'Customer') {
            $this->authorizePermission($request, "client.orders.{$action}");
            abort_unless($user->customer_id, 404);

            return ['app' => 'client', 'customer_id' => $user->customer_id, 'route_id' => null];
        }

        $this->authorizePermission($request, "sales.orders.{$action}");
        $routeId = DB::table('employees')->where('id', $user->employee_id)->value('assigned_route_id');
        abort_unless($routeId, 422, 'A route must be assigned before creating orders.');

        return ['app' => 'sales', 'customer_id' => null, 'route_id' => $routeId];
    }

    private function rules(string $app): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'route_id' => ['nullable', 'integer', 'exists:routes,id'],
            'price_type_id' => ['nullable', 'integer', 'exists:price_types,id'],
            'recipient_name' => ['nullable', 'string', 'max:150'],
            'recipient_phone' => ['nullable', 'string', 'max:50'],
            'delivery_address' => ['nullable', 'string', 'max:500'],
            'order_date' => ['nullable', 'date'],
            'requested_delivery_date' => ['nullable', 'date'],
            'credit_due_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'payment_type' => ['nullable', Rule::in(['cash', 'credit', 'unsettled'])],
            'notes' => ['nullable', 'string', 'max:500'],
            'save_as' => ['nullable', Rule::in(['draft', 'pending'])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.item_type' => ['nullable', Rule::in(['sale', 'foc'])],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.remarks' => ['nullable', 'string', 'max:150'],
        ];
    }

    private function normalizeItems(array $items, ?int $priceTypeId, Carbon $orderDate): array
    {
        return collect($items)->map(function ($item) use ($orderDate, $priceTypeId) {
            $product = DB::table('products')->where('is_active', true)->find($item['product_id']);
            abort_unless($product, 422, 'Active product is required.');
            $quantity = (float) $item['quantity'];
            $itemType = $item['item_type'] ?? 'sale';
            $unitPrice = $itemType === 'foc' ? 0 : $this->priceForProduct($product->id, $priceTypeId, $orderDate);
            $lineTotal = $quantity * $unitPrice;
            $discount = $itemType === 'foc' ? 0 : (float) ($item['discount_amount'] ?? 0);
            abort_if($discount > $lineTotal, 422, 'Item discount cannot exceed its subtotal.');

            return [
                'product_id' => $product->id,
                'product_sku' => $product->sku,
                'product_name' => $product->name,
                'unit' => $product->unit,
                'item_type' => $itemType,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_amount' => $discount,
                'line_total' => max($lineTotal - $discount, 0),
                'remarks' => $item['remarks'] ?? null,
            ];
        })->all();
    }

    private function priceForProduct(int $productId, ?int $priceTypeId, Carbon $orderDate): float
    {
        $price = DB::table('product_prices')
            ->where('product_id', $productId)
            ->when($priceTypeId, fn ($query) => $query->where('price_type_id', $priceTypeId))
            ->where('is_active', true)
            ->where(function ($query) use ($orderDate) {
                $query->whereNull('effective_from')->orWhereDate('effective_from', '<=', $orderDate->toDateString());
            })
            ->orderByDesc('effective_from')
            ->value('amount');

        abort_unless($price !== null, 422, 'Product price is required.');

        return (float) $price;
    }

    private function outstandingBalance(int $customerId): float
    {
        return $this->customerCredit->outstanding($customerId);
    }

    private function totals(array $items): array
    {
        $subtotal = collect($items)->sum(fn ($item) => $item['quantity'] * $item['unit_price']);
        $discount = collect($items)->sum('discount_amount');

        return [
            'subtotal' => $subtotal,
            'discount_total' => $discount,
            'total' => max($subtotal - $discount, 0),
        ];
    }

    private function scopedQuery(array $scope)
    {
        return $this->baseQuery()
            ->when($scope['app'] === 'client', fn ($query) => $query->where('orders.customer_id', $scope['customer_id']))
            ->when($scope['app'] === 'sales', fn ($query) => $query->where('orders.route_id', $scope['route_id']));
    }

    private function baseQuery()
    {
        return DB::table('orders')
            ->leftJoin('customers', 'orders.customer_id', '=', 'customers.id')
            ->leftJoin('routes', 'orders.route_id', '=', 'routes.id')
            ->leftJoin('areas', 'routes.area_id', '=', 'areas.id')
            ->leftJoin('invoices', function ($join) {
                $join->on('orders.id', '=', 'invoices.order_id')
                    ->where('invoices.status', '!=', 'cancelled');
            })
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('order_items')
                    ->whereColumn('order_items.order_id', 'orders.id')
                    ->whereIn('order_items.item_type', ['sale', 'foc']);
            });
    }

    private function columns(): array
    {
        return [
            'orders.*',
            'customers.code as customer_code',
            DB::raw('COALESCE(orders.recipient_name, customers.shop_name) as recipient_name_display'),
            'areas.name as area',
            'routes.name as route',
            'invoices.code as invoice_code',
            'invoices.invoice_date',
            'invoices.due_date',
            'invoices.status as invoice_status',
        ];
    }

    private function orderInScope(Order $order, array $scope): bool
    {
        if ($scope['app'] === 'client') {
            return (int) $order->customer_id === (int) $scope['customer_id'];
        }

        return (int) $order->route_id === (int) $scope['route_id'];
    }

    private function payload($order): array
    {
        return [
            'id' => $order->id,
            'code' => $order->code,
            'customer_id' => $order->customer_id,
            'customer_code' => $order->customer_code,
            'shop_name' => $order->recipient_name_display,
            'recipient_name' => $order->recipient_name_display,
            'recipient_phone' => $order->recipient_phone,
            'area_id' => $order->area_id,
            'area' => $order->area,
            'route_id' => $order->route_id,
            'delivery_address' => $order->delivery_address,
            'route' => $order->route,
            'source_app' => $order->source_app,
            'order_date' => Carbon::parse($order->order_date)->toDateString(),
            'requested_delivery_date' => $order->requested_delivery_date ? Carbon::parse($order->requested_delivery_date)->toDateString() : null,
            'credit_due_date' => $order->credit_due_date ? Carbon::parse($order->credit_due_date)->toDateString() : null,
            'payment_type' => $order->payment_type,
            'status' => $order->status,
            'subtotal' => (float) $order->subtotal,
            'discount_total' => (float) $order->discount_total,
            'tax_total' => (float) $order->tax_total,
            'total' => (float) $order->total,
            'confirmed_at' => $order->confirmed_at ? Carbon::parse($order->confirmed_at)->toDateTimeString() : null,
            'invoice_code' => $order->invoice_code,
            'invoice_date' => $order->invoice_date ? Carbon::parse($order->invoice_date)->toDateString() : null,
            'due_date' => $order->due_date ? Carbon::parse($order->due_date)->toDateString() : null,
            'invoice_status' => $order->invoice_status,
            'notes' => $order->notes,
            'updated_at' => Carbon::parse($order->updated_at)->toDateTimeString(),
        ];
    }

    private function itemPayload($item): array
    {
        return [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'product_sku' => $item->product_sku,
            'product_name' => $item->product_name,
            'unit' => $item->unit,
            'item_type' => $item->item_type,
            'quantity' => (float) $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'discount_amount' => (float) $item->discount_amount,
            'line_total' => (float) $item->line_total,
            'remarks' => $item->remarks,
        ];
    }

    private function nextCode(Carbon $orderDate): string
    {
        $prefix = 'ORD-'.$orderDate->format('Ym').'-';
        $next = ((int) Order::query()->where('code', 'like', "{$prefix}%")->count()) + 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function resolveDestination(array $validated, ?object $customer): array
    {
        $routeId = $validated['route_id'] ?? $customer?->route_id;
        $route = $routeId ? DB::table('routes')->where('is_active', true)->find($routeId) : null;
        abort_unless($route, 422, 'An active delivery route is required.');

        $areaId = $validated['area_id'] ?? $customer?->area_id ?? $route->area_id;
        abort_unless($areaId && (int) $route->area_id === (int) $areaId, 422, 'The selected route must belong to the selected area.');
        abort_unless(DB::table('areas')->where('id', $areaId)->where('is_active', true)->exists(), 422, 'An active delivery area is required.');

        $recipientName = trim((string) ($validated['recipient_name'] ?? $customer?->shop_name));
        $deliveryAddress = trim((string) ($validated['delivery_address'] ?? $customer?->address));
        abort_if($recipientName === '', 422, 'Recipient or shop name is required.');
        abort_if($deliveryAddress === '', 422, 'Delivery address is required.');

        return [
            'area_id' => (int) $areaId,
            'route_id' => (int) $route->id,
            'recipient_name' => $recipientName,
            'recipient_phone' => trim((string) ($validated['recipient_phone'] ?? $customer?->phone)) ?: null,
            'delivery_address' => $deliveryAddress,
        ];
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless(in_array($permission, AppAccess::permissionsForRole($request->user()->role), true), 403);
    }
}
