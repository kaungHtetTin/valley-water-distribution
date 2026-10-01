<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\CustomerCreditService;
use App\Services\InventoryService;
use App\Support\ApiResponse;
use App\Support\AppAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderAdjustmentController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly CustomerCreditService $customerCredit,
    ) {}

    public function meta(Request $request)
    {
        $this->authorizePermission($request, 'office.orders.view');

        $customers = DB::table('customers')
            ->leftJoin('routes', 'customers.route_id', '=', 'routes.id')
            ->leftJoin('price_types', 'customers.price_type_id', '=', 'price_types.id')
            ->where('customers.is_active', true)
            ->orderBy('customers.shop_name')
            ->get([
                'customers.id',
                'customers.code',
                'customers.shop_name',
                'customers.route_id',
                'routes.name as route',
                'customers.price_type_id',
                'price_types.name as price_type',
            ])
            ->map(function ($customer) {
                $customer->label = "{$customer->code} - {$customer->shop_name}";

                return $customer;
            });

        $products = DB::table('products')
            ->where('products.is_active', true)
            ->orderBy('products.name')
            ->get(['products.id', 'products.sku', 'products.name', 'products.unit'])
            ->map(function ($product) {
                $product->label = "{$product->sku} - {$product->name}";
                $product->prices = DB::table('product_prices')
                    ->where('product_id', $product->id)
                    ->where('is_active', true)
                    ->orderByDesc('effective_from')
                    ->get(['price_type_id', 'amount']);

                return $product;
            });

        return ApiResponse::success('Adjustment setup loaded.', [
            'customers' => $customers,
            'products' => $products,
            'warehouses' => DB::table('warehouses')->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'returnable_orders' => $this->returnableOrders(),
        ]);
    }

    public function index(Request $request)
    {
        $this->authorizePermission($request, 'office.orders.view');
        $type = $this->type($request);
        $query = $this->baseQuery($type)->latest('orders.order_date')->latest('orders.id');

        if ($request->filled('customer_id')) {
            $query->where('orders.customer_id', $request->query('customer_id'));
        }

        if ($request->filled('date')) {
            $query->whereDate('orders.order_date', $request->query('date'));
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($query) use ($search) {
                $query->where('orders.code', 'like', "%{$search}%")
                    ->orWhere('original_orders.code', 'like', "%{$search}%")
                    ->orWhere('orders.recipient_name', 'like', "%{$search}%")
                    ->orWhere('customers.shop_name', 'like', "%{$search}%")
                    ->orWhere('customers.code', 'like', "%{$search}%")
                    ->orWhere('routes.name', 'like', "%{$search}%");
            });
        }

        $summary = (clone $query)->reorder()
            ->selectRaw('COUNT(*) as records_count, COALESCE(SUM(orders.total), 0) as total_amount')
            ->first();
        $recordIds = (clone $query)->pluck('orders.id');
        $totalQuantity = DB::table('order_items')
            ->whereIn('order_id', $recordIds)
            ->where('item_type', $type)
            ->sum('quantity');

        $paginator = $query->select($this->columns($type))
            ->paginate(min(max((int) $request->query('per_page', 20), 1), 100));

        return ApiResponse::success('Adjustment records loaded.', [
            'items' => collect($paginator->items())->map(fn ($record) => $this->payload($record)),
            'summary' => [
                'records_count' => (int) ($summary->records_count ?? 0),
                'total_quantity' => (float) $totalQuantity,
                'total_amount' => (float) ($summary->total_amount ?? 0),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Request $request, Order $order)
    {
        $this->authorizePermission($request, 'office.orders.view');
        $type = $this->type($request);
        abort_unless($this->hasType($order->id, $type), 404);
        $record = $this->baseQuery($type)->select($this->columns($type))->where('orders.id', $order->id)->first();
        abort_unless($record, 404);

        return ApiResponse::success('Adjustment record loaded.', [
            'record' => $this->payload($record),
            'items' => $order->items()->where('item_type', $type)->orderBy('id')->get()->map(fn ($item) => $this->itemPayload($item)),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizePermission($request, 'office.orders.manage');
        $validated = $request->validate($this->rules());

        if ($validated['type'] === 'sales_return') {
            return $this->storeSalesReturn($request, $validated);
        }

        $type = $validated['type'];
        abort_unless(! empty($validated['customer_id']), 422, 'Active customer is required.');
        foreach ($validated['items'] as $item) {
            abort_unless(! empty($item['product_id']) && (float) ($item['quantity'] ?? 0) > 0, 422, 'Every damage item requires a product and quantity.');
        }
        $customer = DB::table('customers')->where('is_active', true)->find($validated['customer_id']);
        abort_unless($customer, 422, 'Active customer is required.');
        $entryDate = Carbon::parse($validated['entry_date'] ?? now());
        $priceTypeId = $customer->price_type_id ?? DB::table('price_types')->where('is_default', true)->value('id');
        $items = $this->normalizeItems($validated['items'], $type, $priceTypeId, $entryDate);

        $order = DB::transaction(function () use ($customer, $entryDate, $items, $priceTypeId, $request, $type, $validated) {
            $total = collect($items)->sum('line_total');
            $order = Order::create([
                'code' => $this->nextCode($type, $entryDate),
                'customer_id' => $customer->id,
                'route_id' => $customer->route_id,
                'price_type_id' => $priceTypeId,
                'source_app' => 'office',
                'order_date' => $entryDate->toDateString(),
                'requested_delivery_date' => null,
                'payment_type' => $validated['payment_type'] ?? 'credit',
                'status' => 'confirmed',
                'subtotal' => $total,
                'discount_total' => 0,
                'tax_total' => 0,
                'total' => $total,
                'created_by' => $request->user()?->id,
                'confirmed_by' => $request->user()?->id,
                'confirmed_at' => now(),
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($items as $item) {
                $order->items()->create($item);
            }

            return $order;
        });

        $record = $this->baseQuery($type)->select($this->columns($type))->where('orders.id', $order->id)->first();
        abort_unless($record, 404);

        return ApiResponse::success('Adjustment record created.', [
            'record' => $this->payload($record),
            'items' => $order->items()->where('item_type', $type)->orderBy('id')->get()->map(fn ($item) => $this->itemPayload($item)),
        ], 201);
    }

    private function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['sales_return', 'damage'])],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'original_order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'return_warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'return_settlement_method' => ['nullable', Rule::in(['customer_credit', 'cash_refund', 'bank_refund'])],
            'entry_date' => ['nullable', 'date'],
            'payment_type' => ['nullable', Rule::in(['cash', 'credit'])],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.original_order_item_id' => ['nullable', 'integer', 'exists:order_items,id', 'distinct'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0.01'],
            'items.*.good_quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.damaged_quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.remarks' => ['nullable', 'string', 'max:150'],
        ];
    }

    private function type(Request $request): string
    {
        $type = $request->query('type', 'sales_return');
        abort_unless(in_array($type, ['sales_return', 'damage'], true), 422, 'Valid adjustment type is required.');

        return $type;
    }

    private function normalizeItems(array $items, string $type, ?int $priceTypeId, Carbon $entryDate): array
    {
        return collect($items)->map(function ($item) use ($entryDate, $priceTypeId, $type) {
            $product = DB::table('products')->where('is_active', true)->find($item['product_id']);
            abort_unless($product, 422, 'Active product is required.');
            $quantity = (float) $item['quantity'];
            $unitPrice = array_key_exists('unit_price', $item) && $item['unit_price'] !== null
                ? (float) $item['unit_price']
                : $this->priceForProduct($product->id, $priceTypeId, $entryDate);

            return [
                'product_id' => $product->id,
                'product_sku' => $product->sku,
                'product_name' => $product->name,
                'unit' => $product->unit,
                'item_type' => $type,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_amount' => 0,
                'line_total' => $quantity * $unitPrice,
                'remarks' => $item['remarks'] ?? null,
            ];
        })->all();
    }

    private function priceForProduct(int $productId, ?int $priceTypeId, Carbon $entryDate): float
    {
        $price = DB::table('product_prices')
            ->where('product_id', $productId)
            ->when($priceTypeId, fn ($query) => $query->where('price_type_id', $priceTypeId))
            ->where('is_active', true)
            ->where(function ($query) use ($entryDate) {
                $query->whereNull('effective_from')->orWhereDate('effective_from', '<=', $entryDate->toDateString());
            })
            ->orderByDesc('effective_from')
            ->value('amount');

        abort_unless($price !== null, 422, 'Product price is required.');

        return (float) $price;
    }

    private function returnableOrders(): array
    {
        return Order::query()
            ->with(['items' => fn ($query) => $query->whereIn('item_type', ['sale', 'foc'])->orderBy('id')])
            ->leftJoin('customers', 'orders.customer_id', '=', 'customers.id')
            ->where('orders.status', 'delivered')
            ->whereNull('orders.original_order_id')
            ->select([
                'orders.*',
                'customers.code as customer_code',
                'customers.shop_name',
                DB::raw('(select warehouse_id from deliveries where deliveries.order_id = orders.id order by deliveries.id desc limit 1) as delivery_warehouse_id'),
            ])
            ->latest('orders.order_date')
            ->latest('orders.id')
            ->limit(100)
            ->get()
            ->map(function ($order) {
                $items = $order->items->map(function ($item) {
                    $returned = (float) DB::table('order_items as returned_items')
                        ->join('orders as returns', 'returned_items.order_id', '=', 'returns.id')
                        ->where('returned_items.original_order_item_id', $item->id)
                        ->where('returns.status', 'confirmed')
                        ->sum('returned_items.quantity');
                    $returnable = max((float) $item->quantity - $returned, 0);

                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_sku' => $item->product_sku,
                        'product_name' => $item->product_name,
                        'unit' => $item->unit,
                        'item_type' => $item->item_type,
                        'sold_quantity' => (float) $item->quantity,
                        'returned_quantity' => $returned,
                        'returnable_quantity' => $returnable,
                        'net_unit_price' => (float) $item->quantity > 0 ? (float) $item->line_total / (float) $item->quantity : 0,
                    ];
                })->filter(fn ($item) => $item['returnable_quantity'] > 0)->values();

                return [
                    'id' => $order->id,
                    'code' => $order->code,
                    'customer_id' => $order->customer_id,
                    'customer_code' => $order->customer_code,
                    'shop_name' => $order->shop_name ?? $order->recipient_name,
                    'recipient_name' => $order->recipient_name,
                    'payment_type' => $order->payment_type,
                    'order_date' => $order->order_date?->toDateString(),
                    'delivery_warehouse_id' => $order->delivery_warehouse_id,
                    'outstanding_amount' => $order->customer_id ? $this->customerCredit->outstanding((int) $order->customer_id) : 0,
                    'customer_credit_amount' => $order->customer_id ? $this->customerCredit->customerCredit((int) $order->customer_id) : 0,
                    'label' => $order->code.' - '.($order->shop_name ?? $order->recipient_name),
                    'items' => $items,
                ];
            })
            ->filter(fn ($order) => $order['items']->isNotEmpty())
            ->values()
            ->all();
    }

    private function storeSalesReturn(Request $request, array $validated)
    {
        abort_unless(! empty($validated['original_order_id']), 422, 'Original delivered order is required.');
        abort_unless(! empty($validated['return_warehouse_id']), 422, 'Return warehouse is required.');
        abort_unless(! empty($validated['return_settlement_method']), 422, 'Return settlement is required.');

        $entryDate = Carbon::parse($validated['entry_date'] ?? now());
        $order = DB::transaction(function () use ($entryDate, $request, $validated) {
            $original = Order::query()->lockForUpdate()->find($validated['original_order_id']);
            abort_unless($original && $original->status === 'delivered' && ! $original->original_order_id, 422, 'Only an original delivered order can be returned.');
            abort_unless(DB::table('warehouses')->where('id', $validated['return_warehouse_id'])->where('is_active', true)->exists(), 422, 'Active return warehouse is required.');

            $returnItems = [];
            foreach ($validated['items'] as $submitted) {
                $goodQuantity = (float) ($submitted['good_quantity'] ?? 0);
                $damagedQuantity = (float) ($submitted['damaged_quantity'] ?? 0);
                $quantity = $goodQuantity + $damagedQuantity;
                if ($quantity <= 0) {
                    continue;
                }

                abort_unless(! empty($submitted['original_order_item_id']), 422, 'Every return item must come from the original order.');
                $originalItem = OrderItem::query()->lockForUpdate()->find($submitted['original_order_item_id']);
                abort_unless($originalItem && (int) $originalItem->order_id === (int) $original->id && in_array($originalItem->item_type, ['sale', 'foc'], true), 422, 'Invalid original order item.');

                $alreadyReturned = (float) DB::table('order_items as returned_items')
                    ->join('orders as returns', 'returned_items.order_id', '=', 'returns.id')
                    ->where('returned_items.original_order_item_id', $originalItem->id)
                    ->where('returns.status', 'confirmed')
                    ->sum('returned_items.quantity');
                $available = max((float) $originalItem->quantity - $alreadyReturned, 0);
                abort_if($quantity > $available + 0.00001, 422, "Return quantity for {$originalItem->product_name} exceeds {$available} available.");

                $unitPrice = (float) $originalItem->quantity > 0
                    ? (float) $originalItem->line_total / (float) $originalItem->quantity
                    : 0;
                foreach (['good' => $goodQuantity, 'damaged' => $damagedQuantity] as $condition => $conditionQuantity) {
                    if ($conditionQuantity <= 0) {
                        continue;
                    }
                    $returnItems[] = [
                        'original_order_item_id' => $originalItem->id,
                        'product_id' => $originalItem->product_id,
                        'product_sku' => $originalItem->product_sku,
                        'product_name' => $originalItem->product_name,
                        'unit' => $originalItem->unit,
                        'item_type' => 'sales_return',
                        'return_condition' => $condition,
                        'quantity' => $conditionQuantity,
                        'unit_price' => $unitPrice,
                        'discount_amount' => 0,
                        'line_total' => round($conditionQuantity * $unitPrice, 2),
                        'remarks' => $submitted['remarks'] ?? null,
                    ];
                }
            }

            abort_if($returnItems === [], 422, 'Enter a good or damaged return quantity for at least one product.');
            $total = (float) collect($returnItems)->sum('line_total');
            $settlement = $validated['return_settlement_method'];
            $outstandingBefore = $original->customer_id ? $this->customerCredit->outstanding((int) $original->customer_id) : 0;
            $refundAmount = 0;

            if ($original->payment_type === 'credit') {
                $refundableExcess = max($total - $outstandingBefore, 0);
                if (in_array($settlement, ['cash_refund', 'bank_refund'], true)) {
                    abort_if($refundableExcess <= 0, 422, 'This return reduces the customer outstanding balance; there is no refundable excess.');
                    $refundAmount = $refundableExcess;
                }
            } elseif ($settlement === 'customer_credit') {
                abort_unless($original->customer_id, 422, 'Customer credit requires a registered customer.');
            } else {
                $refundAmount = $total;
            }

            $return = Order::create([
                'code' => $this->nextCode('sales_return', $entryDate),
                'original_order_id' => $original->id,
                'return_warehouse_id' => $validated['return_warehouse_id'],
                'customer_id' => $original->customer_id,
                'area_id' => $original->area_id,
                'route_id' => $original->route_id,
                'price_type_id' => $original->price_type_id,
                'recipient_name' => $original->recipient_name,
                'recipient_phone' => $original->recipient_phone,
                'delivery_address' => $original->delivery_address,
                'source_app' => 'office',
                'order_date' => $entryDate->toDateString(),
                'payment_type' => $original->payment_type,
                'return_settlement_method' => $settlement,
                'refund_amount' => $refundAmount,
                'status' => 'confirmed',
                'subtotal' => $total,
                'discount_total' => 0,
                'tax_total' => 0,
                'total' => $total,
                'created_by' => $request->user()?->id,
                'confirmed_by' => $request->user()?->id,
                'confirmed_at' => now(),
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($returnItems as $itemData) {
                $item = $return->items()->create($itemData);
                $this->inventory->applyMovement([
                    'warehouse_id' => $validated['return_warehouse_id'],
                    'product_id' => $item->product_id,
                    'movement_type' => $item->return_condition === 'good' ? 'sales_return' : 'sales_return_damage',
                    'quantity' => $item->quantity,
                    'reference_type' => 'sales_return',
                    'reference_id' => $return->id,
                    'reference_code' => $return->code,
                    'document_code' => $original->code,
                    'notes' => ucfirst($item->return_condition)." return for {$original->code}",
                ], $entryDate, $request->user()?->id);
            }

            if ($refundAmount > 0) {
                FinancialTransaction::create([
                    'code' => 'TXN-'.str_pad((string) (FinancialTransaction::max('id') + 1), 7, '0', STR_PAD_LEFT),
                    'transaction_date' => $entryDate->toDateString(),
                    'book_type' => $settlement === 'bank_refund' ? 'bank' : 'cash',
                    'direction' => 'out',
                    'category' => 'sales_return_refund',
                    'amount' => $refundAmount,
                    'reference_type' => 'sales_return',
                    'reference_id' => $return->id,
                    'reference_code' => $return->code,
                    'description' => "Sales return refund {$return->code}",
                    'created_by' => $request->user()?->id,
                ]);
            }

            return $return;
        });

        $record = $this->baseQuery('sales_return')->select($this->columns('sales_return'))->where('orders.id', $order->id)->first();

        return ApiResponse::success('Sales return recorded.', [
            'record' => $this->payload($record),
            'items' => $order->items()->where('item_type', 'sales_return')->orderBy('id')->get()->map(fn ($item) => $this->itemPayload($item)),
            'credit' => $order->customer_id ? $this->customerCredit->summary((int) $order->customer_id) : null,
        ], 201);
    }

    private function baseQuery(string $type)
    {
        return DB::table('orders')
            ->leftJoin('customers', 'orders.customer_id', '=', 'customers.id')
            ->leftJoin('routes', 'orders.route_id', '=', 'routes.id')
            ->leftJoin('orders as original_orders', 'orders.original_order_id', '=', 'original_orders.id')
            ->leftJoin('warehouses as return_warehouses', 'orders.return_warehouse_id', '=', 'return_warehouses.id')
            ->whereExists(function ($query) use ($type) {
                $query->selectRaw('1')
                    ->from('order_items')
                    ->whereColumn('order_items.order_id', 'orders.id')
                    ->where('order_items.item_type', $type);
            });
    }

    private function columns(string $type): array
    {
        return [
            'orders.*',
            'customers.code as customer_code',
            'customers.shop_name',
            'customers.contact_name',
            'routes.name as route',
            'original_orders.code as original_order_code',
            'return_warehouses.code as return_warehouse_code',
            'return_warehouses.name as return_warehouse_name',
            DB::raw("(select coalesce(sum(quantity), 0) from order_items where order_items.order_id = orders.id and order_items.item_type = '{$type}') as quantity_total"),
        ];
    }

    private function hasType(int $orderId, string $type): bool
    {
        return DB::table('order_items')->where('order_id', $orderId)->where('item_type', $type)->exists();
    }

    private function payload($record): array
    {
        return [
            'id' => $record->id,
            'code' => $record->code,
            'customer_id' => $record->customer_id,
            'customer_code' => $record->customer_code,
            'shop_name' => $record->shop_name ?? $record->recipient_name,
            'contact_name' => $record->contact_name,
            'route' => $record->route,
            'entry_date' => Carbon::parse($record->order_date)->toDateString(),
            'payment_type' => $record->payment_type,
            'original_order_id' => $record->original_order_id,
            'original_order_code' => $record->original_order_code,
            'return_warehouse_id' => $record->return_warehouse_id,
            'return_warehouse_code' => $record->return_warehouse_code,
            'return_warehouse_name' => $record->return_warehouse_name,
            'return_settlement_method' => $record->return_settlement_method,
            'refund_amount' => (float) $record->refund_amount,
            'status' => $record->status,
            'quantity_total' => (float) $record->quantity_total,
            'total' => (float) $record->total,
            'notes' => $record->notes,
            'updated_at' => Carbon::parse($record->updated_at)->toDateTimeString(),
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
            'original_order_item_id' => $item->original_order_item_id,
            'return_condition' => $item->return_condition,
            'quantity' => (float) $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'line_total' => (float) $item->line_total,
            'remarks' => $item->remarks,
        ];
    }

    private function nextCode(string $type, Carbon $entryDate): string
    {
        $prefix = ($type === 'damage' ? 'DMG-' : 'RET-').$entryDate->format('Ym').'-';
        $next = ((int) Order::query()->where('code', 'like', "{$prefix}%")->count()) + 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless(in_array($permission, AppAccess::permissionsForRole($request->user()?->role), true), 403);
    }
}
