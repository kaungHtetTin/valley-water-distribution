<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialTransaction;
use App\Models\Invoice;
use App\Models\Order;
use App\Support\ApiResponse;
use App\Support\AppAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
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
                'customers.contact_name',
                'customers.phone',
                'customers.address',
                'customers.area_id',
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

        return ApiResponse::success('Order setup loaded.', [
            'customers' => $customers,
            'products' => $products,
            'price_types' => DB::table('price_types')->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'areas' => DB::table('areas')->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'routes' => DB::table('routes')->where('is_active', true)->orderBy('name')->get(['id', 'area_id', 'code', 'name']),
        ]);
    }

    public function index(Request $request)
    {
        $this->authorizePermission($request, 'office.orders.view');

        $query = $this->baseQuery()->latest('orders.order_date')->latest('orders.id');

        if ($request->filled('status')) {
            $query->where('orders.status', $request->query('status'));
        }

        if ($request->filled('customer_id')) {
            $query->where('orders.customer_id', $request->query('customer_id'));
        }

        if ($request->filled('date')) {
            $query->whereDate('orders.order_date', $request->query('date'));
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($query) use ($search) {
                $query->where('orders.code', 'like', "%{$search}%")
                    ->orWhere('orders.recipient_name', 'like', "%{$search}%")
                    ->orWhere('orders.recipient_phone', 'like', "%{$search}%")
                    ->orWhere('orders.delivery_address', 'like', "%{$search}%")
                    ->orWhere('customers.shop_name', 'like', "%{$search}%")
                    ->orWhere('customers.code', 'like', "%{$search}%")
                    ->orWhere('areas.name', 'like', "%{$search}%")
                    ->orWhere('routes.name', 'like', "%{$search}%");
            });
        }

        $summary = (clone $query)->reorder()
            ->selectRaw("COUNT(*) as orders_count, COALESCE(SUM(orders.total), 0) as total_amount, SUM(CASE WHEN orders.status = 'pending' THEN 1 ELSE 0 END) as pending_count, SUM(CASE WHEN orders.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_count, SUM(CASE WHEN orders.status = 'invoiced' THEN 1 ELSE 0 END) as ready_count")
            ->first();

        $paginator = $query->select($this->orderColumns())
            ->paginate(min(max((int) $request->query('per_page', 20), 1), 100));

        return ApiResponse::success('Orders loaded.', [
            'items' => collect($paginator->items())->map(fn ($order) => $this->payload($order)),
            'summary' => [
                'orders_count' => (int) ($summary->orders_count ?? 0),
                'total_amount' => (float) ($summary->total_amount ?? 0),
                'pending_count' => (int) ($summary->pending_count ?? 0),
                'confirmed_count' => (int) ($summary->confirmed_count ?? 0),
                'ready_count' => (int) ($summary->ready_count ?? 0),
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
        $orderPayload = $this->baseQuery()->select($this->orderColumns())->where('orders.id', $order->id)->first();
        abort_unless($orderPayload, 404);

        return ApiResponse::success('Order loaded.', [
            'order' => $this->payload($orderPayload),
            'items' => $order->items()->orderBy('id')->get()->map(fn ($item) => $this->itemPayload($item)),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizePermission($request, 'office.orders.manage');

        $validated = $request->validate($this->rules());
        $customer = ! empty($validated['customer_id'])
            ? DB::table('customers')->where('is_active', true)->find($validated['customer_id'])
            : null;
        abort_if(! empty($validated['customer_id']) && ! $customer, 422, 'The selected customer is not active.');
        abort_if(! $customer && $validated['payment_type'] === 'credit', 422, 'Credit orders require a registered customer.');
        $destination = $this->resolveDestination($validated, $customer);
        $orderDate = Carbon::parse($validated['order_date'] ?? now());
        $priceTypeId = $validated['price_type_id'] ?? $customer->price_type_id ?? DB::table('price_types')->where('is_default', true)->value('id');
        $items = $this->normalizeItems($validated['items'], $priceTypeId, $orderDate);

        $order = DB::transaction(function () use ($customer, $destination, $items, $orderDate, $priceTypeId, $request, $validated) {
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
                'source_app' => 'office',
                'order_date' => $orderDate->toDateString(),
                'requested_delivery_date' => $validated['requested_delivery_date'] ?? null,
                'credit_due_date' => $validated['payment_type'] === 'credit' ? ($validated['credit_due_date'] ?? $orderDate->copy()->addDays(7)->toDateString()) : null,
                'payment_type' => $validated['payment_type'],
                'status' => 'pending',
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

        $orderPayload = $this->baseQuery()->select($this->orderColumns())->where('orders.id', $order->id)->first();
        abort_unless($orderPayload, 404);

        return ApiResponse::success('Order created.', [
            'order' => $this->payload($orderPayload),
            'items' => $order->items()->orderBy('id')->get()->map(fn ($item) => $this->itemPayload($item)),
        ], 201);
    }

    public function confirm(Request $request, Order $order)
    {
        $this->authorizePermission($request, 'office.orders.manage');
        abort_unless($order->status === 'pending', 409, 'Only pending orders can be confirmed.');

        $invoice = DB::transaction(function () use ($order, $request) {
            $lockedOrder = Order::with('items')->lockForUpdate()->findOrFail($order->id);
            abort_unless($lockedOrder->status === 'pending', 409, 'Only pending orders can be confirmed.');
            $lockedOrder->update([
                'status' => 'confirmed',
                'confirmed_by' => $request->user()?->id,
                'confirmed_at' => now(),
            ]);

            return $this->issueFinancialRecord($lockedOrder, $request->user()?->id);
        });

        $orderPayload = $this->baseQuery()->select($this->orderColumns())->where('orders.id', $order->id)->first();
        abort_unless($orderPayload, 404);

        return ApiResponse::success('Order confirmed and ready for delivery.', [
            'order' => $this->payload($orderPayload),
            'financial_record' => ['id' => $invoice->id, 'code' => $invoice->code, 'status' => $invoice->status],
        ]);
    }

    public function cancel(Request $request, Order $order)
    {
        $this->authorizePermission($request, 'office.orders.manage');
        abort_unless(in_array($order->status, ['pending', 'confirmed', 'invoiced'], true), 409, 'Only unassigned orders can be cancelled.');

        DB::transaction(function () use ($order) {
            $invoice = Invoice::query()->where('order_id', $order->id)->where('status', '!=', 'cancelled')->first();
            if ($invoice) {
                abort_if(DB::table('collections')->where('invoice_id', $invoice->id)->where('status', 'approved')->exists(), 409, 'An order with recorded payments cannot be cancelled.');

                if ($order->payment_type === 'credit' && $order->customer_id) {
                    $otherCreditSales = (float) DB::table('invoices')
                        ->join('orders', 'invoices.order_id', '=', 'orders.id')
                        ->where('invoices.customer_id', $order->customer_id)
                        ->where('invoices.id', '!=', $invoice->id)
                        ->where('invoices.status', '!=', 'cancelled')
                        ->where('orders.payment_type', 'credit')
                        ->sum('invoices.total');
                    $approvedPayments = (float) DB::table('collections')
                        ->where('customer_id', $order->customer_id)
                        ->where('status', 'approved')
                        ->sum('amount');

                    abort_if(
                        $approvedPayments > $otherCreditSales,
                        409,
                        'This order cannot be cancelled because customer payments have already been applied to its balance.'
                    );
                }

                $invoice->update(['status' => 'cancelled']);
                FinancialTransaction::query()->where('reference_type', 'invoice')->where('reference_id', $invoice->id)->delete();
            }
            $order->update(['status' => 'cancelled']);
        });

        $orderPayload = $this->baseQuery()->select($this->orderColumns())->where('orders.id', $order->id)->first();
        abort_unless($orderPayload, 404);

        return ApiResponse::success('Order cancelled.', [
            'order' => $this->payload($orderPayload),
        ]);
    }

    private function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'route_id' => ['nullable', 'integer', 'exists:routes,id'],
            'recipient_name' => ['nullable', 'string', 'max:150'],
            'recipient_phone' => ['nullable', 'string', 'max:50'],
            'delivery_address' => ['nullable', 'string', 'max:500'],
            'price_type_id' => ['nullable', 'integer', 'exists:price_types,id'],
            'order_date' => ['nullable', 'date'],
            'requested_delivery_date' => ['nullable', 'date'],
            'credit_due_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'payment_type' => ['required', Rule::in(['cash', 'credit'])],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.item_type' => ['required', Rule::in(['sale', 'foc'])],
            'items.*.remarks' => ['nullable', 'string', 'max:150'],
        ];
    }

    private function normalizeItems(array $items, ?int $priceTypeId, Carbon $orderDate): array
    {
        return collect($items)->map(function ($item) use ($orderDate, $priceTypeId) {
            $product = DB::table('products')->where('is_active', true)->find($item['product_id']);
            abort_unless($product, 422, 'Active product is required.');
            $quantity = (float) $item['quantity'];
            $itemType = $item['item_type'];
            $unitPrice = array_key_exists('unit_price', $item) && $item['unit_price'] !== null
                ? (float) $item['unit_price']
                : $this->priceForProduct($product->id, $priceTypeId, $orderDate);
            $unitPrice = $itemType === 'foc' ? 0 : $unitPrice;
            $discount = (float) ($item['discount_amount'] ?? 0);
            $lineTotal = max(($quantity * $unitPrice) - $discount, 0);

            return [
                'product_id' => $product->id,
                'product_sku' => $product->sku,
                'product_name' => $product->name,
                'unit' => $product->unit,
                'item_type' => $itemType,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_amount' => $discount,
                'line_total' => $lineTotal,
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

    private function baseQuery()
    {
        return DB::table('orders')
            ->leftJoin('customers', 'orders.customer_id', '=', 'customers.id')
            ->leftJoin('routes', 'orders.route_id', '=', 'routes.id')
            ->leftJoin('areas', 'routes.area_id', '=', 'areas.id')
            ->leftJoin('price_types', 'orders.price_type_id', '=', 'price_types.id')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('order_items')
                    ->whereColumn('order_items.order_id', 'orders.id')
                    ->whereIn('order_items.item_type', ['sale', 'foc']);
            });
    }

    private function orderColumns(): array
    {
        return [
            'orders.*',
            'customers.code as customer_code',
            DB::raw('COALESCE(orders.recipient_name, customers.shop_name) as recipient_name_display'),
            'customers.contact_name',
            'areas.name as area',
            'routes.name as route',
            'price_types.name as price_type',
        ];
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
            'contact_name' => $order->contact_name,
            'route' => $order->route,
            'price_type' => $order->price_type,
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

    private function issueFinancialRecord(Order $order, ?int $userId): Invoice
    {
        abort_unless($order->items->isNotEmpty(), 422, 'Order items are required.');
        abort_unless($order->items->every(fn ($item) => in_array($item->item_type, ['sale', 'foc'], true)), 422, 'Only sale and FOC orders can be confirmed.');
        $invoiceDate = Carbon::parse($order->order_date);
        $creditDueDate = $order->payment_type === 'credit'
            ? ($order->credit_due_date?->toDateString() ?? $invoiceDate->copy()->addDays(7)->toDateString())
            : null;
        $prefix = 'INV-'.$invoiceDate->format('Ym').'-';
        $invoice = Invoice::create([
            'code' => $prefix.str_pad((string) (((int) Invoice::query()->where('code', 'like', "{$prefix}%")->count()) + 1), 4, '0', STR_PAD_LEFT),
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'area_id' => $order->area_id,
            'route_id' => $order->route_id,
            'recipient_name' => $order->recipient_name,
            'recipient_phone' => $order->recipient_phone,
            'delivery_address' => $order->delivery_address,
            'invoice_date' => $invoiceDate->toDateString(),
            'due_date' => $creditDueDate,
            'status' => 'issued',
            'subtotal' => $order->subtotal,
            'discount_total' => $order->discount_total,
            'tax_total' => $order->tax_total,
            'total' => $order->total,
            'created_by' => $userId,
            'notes' => $order->notes,
        ]);

        foreach ($order->items as $item) {
            $invoice->items()->create([
                'product_id' => $item->product_id,
                'product_sku' => $item->product_sku,
                'product_name' => $item->product_name,
                'unit' => $item->unit,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount_amount' => $item->discount_amount,
                'line_total' => $item->line_total,
            ]);
        }

        if ($order->payment_type === 'cash' && (float) $order->total > 0) {
            FinancialTransaction::create([
                'code' => 'TXN-'.str_pad((string) (FinancialTransaction::max('id') + 1), 7, '0', STR_PAD_LEFT),
                'transaction_date' => $invoiceDate->toDateString(),
                'book_type' => 'cash',
                'direction' => 'in',
                'category' => 'cash_sale',
                'amount' => $order->total,
                'reference_type' => 'invoice',
                'reference_id' => $invoice->id,
                'reference_code' => $order->code,
                'description' => "Cash sale {$order->code}",
                'created_by' => $userId,
            ]);
        }

        $order->update(['status' => 'invoiced', 'credit_due_date' => $creditDueDate]);

        return $invoice;
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
        abort_unless(in_array($permission, AppAccess::permissionsForRole($request->user()?->role), true), 403);
    }
}
