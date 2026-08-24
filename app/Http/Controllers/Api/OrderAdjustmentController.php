<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\ApiResponse;
use App\Support\AppAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderAdjustmentController extends Controller
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
        $type = $validated['type'];
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
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'entry_date' => ['nullable', 'date'],
            'payment_type' => ['nullable', Rule::in(['cash', 'credit'])],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
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

    private function baseQuery(string $type)
    {
        return DB::table('orders')
            ->leftJoin('customers', 'orders.customer_id', '=', 'customers.id')
            ->leftJoin('routes', 'orders.route_id', '=', 'routes.id')
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
            'shop_name' => $record->shop_name,
            'contact_name' => $record->contact_name,
            'route' => $record->route,
            'entry_date' => Carbon::parse($record->order_date)->toDateString(),
            'payment_type' => $record->payment_type,
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
