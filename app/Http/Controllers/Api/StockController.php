<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialTransaction;
use App\Models\StockMovement;
use App\Models\SupplierInvoice;
use App\Models\SupplierLedgerEntry;
use App\Models\SupplierPayment;
use App\Services\InventoryService;
use App\Support\ApiResponse;
use App\Support\AppAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StockController extends Controller
{
    private const VIEW_PERMISSION = 'office.inventory.view';

    private const MANAGE_PERMISSION = 'office.inventory.manage';

    private const ADJUSTMENT_REASONS = ['damage', 'expired', 'loss', 'internal_use', 'count_correction', 'found_stock', 'other'];

    public function __construct(private InventoryService $inventory) {}

    public function meta(Request $request)
    {
        $this->authorizePermission($request, self::VIEW_PERMISSION);

        $warehouses = DB::table('warehouses')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(function ($warehouse) {
                $warehouse->label = "{$warehouse->code} - {$warehouse->name}";

                return $warehouse;
            });

        $products = DB::table('products')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'unit'])
            ->map(function ($product) {
                $product->label = "{$product->sku} - {$product->name}";
                $product->latest_cost = (float) DB::table('stock_balances')
                    ->where('product_id', $product->id)
                    ->where('average_cost', '>', 0)
                    ->orderByDesc('last_movement_at')
                    ->value('average_cost');

                return $product;
            });

        $suppliers = DB::table('suppliers')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(function ($supplier) {
                $supplier->label = "{$supplier->code} - {$supplier->name}";

                return $supplier;
            });

        return ApiResponse::success('Stock setup loaded.', [
            'warehouses' => $warehouses,
            'products' => $products,
            'suppliers' => $suppliers,
            'movement_types' => ['opening', 'receive', 'issue', 'damage', 'adjustment', 'transfer_out', 'transfer_in', 'delivery_issue', 'delivery_issue_reversal', 'delivery_return', 'delivery_damage'],
        ]);
    }

    public function movements(Request $request)
    {
        $this->authorizePermission($request, self::VIEW_PERMISSION);

        $query = $this->movementQuery()
            ->latest('stock_movements.movement_date')
            ->latest('stock_movements.id');

        if ($request->filled('warehouse_id')) {
            $query->where('stock_movements.warehouse_id', $request->query('warehouse_id'));
        }

        if ($request->filled('product_id')) {
            $query->where('stock_movements.product_id', $request->query('product_id'));
        }

        if ($request->filled('supplier_id')) {
            $query->where('stock_movements.supplier_id', $request->query('supplier_id'));
        }

        if ($request->query('type_group') === 'issue' && ! $request->filled('type')) {
            $query->whereIn('stock_movements.movement_type', ['issue', 'delivery_issue', 'delivery_issue_reversal']);
        } elseif ($request->query('type_group') === 'adjustment' && ! $request->filled('type')) {
            $query->whereIn('stock_movements.movement_type', ['damage', 'adjustment']);
        } elseif ($request->filled('type')) {
            $query->where('stock_movements.movement_type', $request->query('type'));
        }

        if ($request->filled('adjustment_reason')) {
            $reason = $request->query('adjustment_reason');
            $query->where(function ($query) use ($reason) {
                $query->where('stock_movements.adjustment_reason', $reason);
                if ($reason === 'damage') {
                    $query->orWhere(function ($legacy) {
                        $legacy->where('stock_movements.movement_type', 'damage')
                            ->whereNull('stock_movements.adjustment_reason');
                    });
                }
            });
        }

        if ($request->filled('reference_type')) {
            $query->where('stock_movements.reference_type', $request->query('reference_type'));
        }

        if ($request->filled('date')) {
            $query->whereDate('stock_movements.movement_date', $request->query('date'));
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($query) use ($search) {
                $query->where('stock_movements.code', 'like', "%{$search}%")
                    ->orWhere('stock_movements.reference_code', 'like', "%{$search}%")
                    ->orWhere('warehouses.code', 'like', "%{$search}%")
                    ->orWhere('warehouses.name', 'like', "%{$search}%")
                    ->orWhere('products.sku', 'like', "%{$search}%")
                    ->orWhere('products.name', 'like', "%{$search}%")
                    ->orWhere('stock_movements.adjustment_reason', 'like', "%{$search}%");
            });
        }

        $summary = (clone $query)->reorder()
            ->selectRaw('COUNT(*) as records_count, COALESCE(SUM(CASE WHEN stock_movements.signed_quantity > 0 THEN stock_movements.signed_quantity ELSE 0 END), 0) as in_quantity, COALESCE(SUM(CASE WHEN stock_movements.signed_quantity < 0 THEN ABS(stock_movements.signed_quantity) ELSE 0 END), 0) as out_quantity, COALESCE(SUM(CASE WHEN stock_movements.signed_quantity > 0 THEN stock_movements.total_cost ELSE -stock_movements.total_cost END), 0) as stock_value')
            ->first();

        $paginator = $query->select($this->movementColumns())
            ->paginate(min(max((int) $request->query('per_page', 20), 1), 100));

        return ApiResponse::success('Stock movements loaded.', [
            'items' => collect($paginator->items())->map(fn ($movement) => $this->movementPayload($movement)),
            'summary' => [
                'records_count' => (int) ($summary->records_count ?? 0),
                'in_quantity' => (float) ($summary->in_quantity ?? 0),
                'out_quantity' => (float) ($summary->out_quantity ?? 0),
                'stock_value' => (float) ($summary->stock_value ?? 0),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function receipts(Request $request)
    {
        $this->authorizePermission($request, self::VIEW_PERMISSION);

        $query = $this->movementQuery()
            ->whereIn('stock_movements.movement_type', ['opening', 'receive'])
            ->whereNotNull('stock_movements.document_code');

        if ($request->filled('warehouse_id')) {
            $query->where('stock_movements.warehouse_id', $request->query('warehouse_id'));
        }

        if ($request->filled('product_id')) {
            $query->where('stock_movements.product_id', $request->query('product_id'));
        }

        if ($request->filled('supplier_id')) {
            $query->where('stock_movements.supplier_id', $request->query('supplier_id'));
        }

        if ($request->filled('type')) {
            $query->where('stock_movements.movement_type', $request->query('type'));
        }

        if ($request->filled('date')) {
            $query->whereDate('stock_movements.movement_date', $request->query('date'));
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($query) use ($search) {
                $query->where('stock_movements.document_code', 'like', "%{$search}%")
                    ->orWhere('stock_movements.reference_code', 'like', "%{$search}%")
                    ->orWhere('warehouses.code', 'like', "%{$search}%")
                    ->orWhere('warehouses.name', 'like', "%{$search}%")
                    ->orWhere('products.sku', 'like', "%{$search}%")
                    ->orWhere('products.name', 'like', "%{$search}%")
                    ->orWhere('suppliers.code', 'like', "%{$search}%")
                    ->orWhere('suppliers.name', 'like', "%{$search}%");
            });
        }

        $grouped = $query
            ->groupBy([
                'stock_movements.document_code',
                'stock_movements.supplier_id',
                'suppliers.code',
                'suppliers.name',
                'stock_movements.warehouse_id',
                'warehouses.code',
                'warehouses.name',
                'stock_movements.movement_type',
                'stock_movements.movement_date',
                'stock_movements.reference_code',
                'stock_movements.notes',
            ])
            ->select([
                'stock_movements.document_code',
                'stock_movements.supplier_id',
                'suppliers.code as supplier_code',
                'suppliers.name as supplier_name',
                'stock_movements.warehouse_id',
                'warehouses.code as warehouse_code',
                'warehouses.name as warehouse_name',
                'stock_movements.movement_type',
                'stock_movements.movement_date',
                'stock_movements.reference_code',
                'stock_movements.notes',
            ])
            ->selectRaw('MAX(stock_movements.id) as latest_id, COUNT(DISTINCT stock_movements.product_id) as products_count, SUM(stock_movements.signed_quantity) as total_quantity, SUM(stock_movements.total_cost) as total_value');

        $summary = DB::query()->fromSub(clone $grouped, 'receipt_rows')
            ->selectRaw('COUNT(*) as records_count, COALESCE(SUM(total_quantity), 0) as in_quantity, COALESCE(SUM(total_value), 0) as stock_value')
            ->first();

        $paginator = $grouped
            ->orderByDesc('stock_movements.movement_date')
            ->orderByDesc('latest_id')
            ->paginate(min(max((int) $request->query('per_page', 20), 1), 100));

        return ApiResponse::success('Stock receipts loaded.', [
            'items' => collect($paginator->items())->map(fn ($receipt) => [
                'document_code' => $receipt->document_code,
                'supplier_id' => $receipt->supplier_id,
                'supplier_code' => $receipt->supplier_code,
                'supplier_name' => $receipt->supplier_name,
                'warehouse_id' => $receipt->warehouse_id,
                'warehouse_code' => $receipt->warehouse_code,
                'warehouse_name' => $receipt->warehouse_name,
                'movement_type' => $receipt->movement_type,
                'movement_date' => Carbon::parse($receipt->movement_date)->toDateString(),
                'reference_code' => $receipt->reference_code,
                'notes' => $receipt->notes,
                'products_count' => (int) $receipt->products_count,
                'total_quantity' => (float) $receipt->total_quantity,
                'total_value' => (float) $receipt->total_value,
            ]),
            'summary' => [
                'records_count' => (int) ($summary->records_count ?? 0),
                'in_quantity' => (float) ($summary->in_quantity ?? 0),
                'out_quantity' => 0,
                'stock_value' => (float) ($summary->stock_value ?? 0),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function transfers(Request $request)
    {
        $this->authorizePermission($request, self::VIEW_PERMISSION);

        $query = $this->movementQuery()
            ->leftJoin('stock_movements as transfer_in', function ($join) {
                $join->on('transfer_in.document_code', '=', 'stock_movements.document_code')
                    ->on('transfer_in.product_id', '=', 'stock_movements.product_id')
                    ->where('transfer_in.movement_type', 'transfer_in');
            })
            ->leftJoin('warehouses as destination_warehouses', 'destination_warehouses.id', '=', 'transfer_in.warehouse_id')
            ->where('stock_movements.movement_type', 'transfer_out')
            ->whereNotNull('stock_movements.document_code');

        if ($request->filled('from_warehouse_id')) {
            $query->where('stock_movements.warehouse_id', $request->query('from_warehouse_id'));
        }
        if ($request->filled('to_warehouse_id')) {
            $query->where('transfer_in.warehouse_id', $request->query('to_warehouse_id'));
        }
        if ($request->filled('product_id')) {
            $query->where('stock_movements.product_id', $request->query('product_id'));
        }
        if ($request->filled('date')) {
            $query->whereDate('stock_movements.movement_date', $request->query('date'));
        }
        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($query) use ($search) {
                $query->where('stock_movements.document_code', 'like', "%{$search}%")
                    ->orWhere('stock_movements.reference_code', 'like', "%{$search}%")
                    ->orWhere('warehouses.code', 'like', "%{$search}%")
                    ->orWhere('warehouses.name', 'like', "%{$search}%")
                    ->orWhere('destination_warehouses.code', 'like', "%{$search}%")
                    ->orWhere('destination_warehouses.name', 'like', "%{$search}%")
                    ->orWhere('products.sku', 'like', "%{$search}%")
                    ->orWhere('products.name', 'like', "%{$search}%");
            });
        }

        $grouped = $query->groupBy([
            'stock_movements.document_code', 'stock_movements.warehouse_id', 'warehouses.code', 'warehouses.name',
            'transfer_in.warehouse_id', 'destination_warehouses.code', 'destination_warehouses.name',
            'stock_movements.movement_date', 'stock_movements.reference_code', 'stock_movements.notes',
        ])->select([
            'stock_movements.document_code', 'stock_movements.warehouse_id as from_warehouse_id',
            'warehouses.code as from_warehouse_code', 'warehouses.name as from_warehouse_name',
            'transfer_in.warehouse_id as to_warehouse_id', 'destination_warehouses.code as to_warehouse_code',
            'destination_warehouses.name as to_warehouse_name', 'stock_movements.movement_date',
            'stock_movements.reference_code', 'stock_movements.notes',
        ])->selectRaw('MAX(stock_movements.id) as latest_id, COUNT(DISTINCT stock_movements.product_id) as products_count, SUM(ABS(stock_movements.signed_quantity)) as total_quantity, SUM(stock_movements.total_cost) as total_value');

        $summary = DB::query()->fromSub(clone $grouped, 'transfer_rows')
            ->selectRaw('COUNT(*) as records_count, COALESCE(SUM(total_quantity), 0) as out_quantity, COALESCE(SUM(total_value), 0) as stock_value')->first();
        $paginator = $grouped->orderByDesc('stock_movements.movement_date')->orderByDesc('latest_id')
            ->paginate(min(max((int) $request->query('per_page', 20), 1), 100));

        return ApiResponse::success('Stock transfers loaded.', [
            'items' => collect($paginator->items())->map(fn ($transfer) => [
                'document_code' => $transfer->document_code,
                'from_warehouse_code' => $transfer->from_warehouse_code,
                'from_warehouse_name' => $transfer->from_warehouse_name,
                'to_warehouse_code' => $transfer->to_warehouse_code,
                'to_warehouse_name' => $transfer->to_warehouse_name,
                'movement_date' => Carbon::parse($transfer->movement_date)->toDateString(),
                'reference_code' => $transfer->reference_code,
                'notes' => $transfer->notes,
                'products_count' => (int) $transfer->products_count,
                'total_quantity' => (float) $transfer->total_quantity,
                'total_value' => (float) $transfer->total_value,
            ]),
            'summary' => ['records_count' => (int) ($summary->records_count ?? 0), 'in_quantity' => 0, 'out_quantity' => (float) ($summary->out_quantity ?? 0), 'stock_value' => (float) ($summary->stock_value ?? 0)],
            'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total()],
        ]);
    }

    public function document(Request $request, string $documentCode)
    {
        $this->authorizePermission($request, self::VIEW_PERMISSION);

        $movements = $this->movementQuery()
            ->where('stock_movements.document_code', $documentCode)
            ->orderBy('stock_movements.id')
            ->get($this->movementColumns());

        abort_if($movements->isEmpty(), 404, 'Stock document not found.');

        $primary = $movements->first(fn ($movement) => $movement->movement_type !== 'transfer_in') ?: $movements->first();
        $destination = $movements->first(fn ($movement) => $movement->movement_type === 'transfer_in');
        $items = $movements
            ->filter(fn ($movement) => $movement->movement_type !== 'transfer_in')
            ->map(fn ($movement) => $this->movementPayload($movement))
            ->values();

        return ApiResponse::success('Stock document loaded.', [
            'document' => [
                'code' => $documentCode,
                'movement_type' => $primary->movement_type,
                'movement_date' => Carbon::parse($primary->movement_date)->toDateString(),
                'reference_code' => $primary->reference_code,
                'notes' => $primary->notes,
                'supplier_id' => $primary->supplier_id,
                'supplier_code' => $primary->supplier_code,
                'supplier_name' => $primary->supplier_name,
                'warehouse_code' => $primary->warehouse_code,
                'warehouse_name' => $primary->warehouse_name,
                'destination_warehouse_code' => $destination?->warehouse_code,
                'destination_warehouse_name' => $destination?->warehouse_name,
                'products_count' => $items->count(),
                'total_quantity' => (float) $items->sum(fn ($item) => abs($item['signed_quantity'])),
                'total_value' => (float) $items->sum('total_cost'),
            ],
            'items' => $items,
        ]);
    }

    public function balances(Request $request)
    {
        $this->authorizePermission($request, self::VIEW_PERMISSION);

        // Inventory permission grants the active warehouse set. Inactive warehouses
        // stay outside both the matrix columns and every quantity calculation.
        $warehouseQuery = DB::table('warehouses')
            ->where('is_active', true)
            ->orderBy('name');

        if ($request->filled('warehouse_id')) {
            $warehouseQuery->where('id', $request->integer('warehouse_id'));
        }

        $warehouses = $warehouseQuery->get(['id', 'code', 'name']);
        $warehouseIds = $warehouses->pluck('id')->map(fn ($id) => (int) $id)->all();

        $productQuery = DB::table('products')
            ->where('is_active', true)
            ->orderBy('name');

        if ($request->filled('product_id')) {
            $productQuery->where('id', $request->integer('product_id'));
        }

        if ($search = trim((string) $request->query('search'))) {
            $productQuery->where(function ($query) use ($search) {
                $query->where('sku', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $filteredProductIds = (clone $productQuery)->reorder()->select('products.id');
        $totalQuantity = DB::table('stock_balances')
            ->whereIn('warehouse_id', $warehouseIds)
            ->whereIn('product_id', $filteredProductIds)
            ->sum('quantity');

        $warehouseTotals = DB::table('stock_balances')
            ->whereIn('warehouse_id', $warehouseIds)
            ->whereIn('product_id', (clone $filteredProductIds))
            ->select('warehouse_id')
            ->selectRaw('COALESCE(SUM(quantity), 0) as total_quantity')
            ->groupBy('warehouse_id')
            ->pluck('total_quantity', 'warehouse_id');

        $paginator = $productQuery
            ->select(['id', 'sku', 'name', 'unit'])
            ->paginate(min(max((int) $request->query('per_page', 20), 1), 100));

        $pageProductIds = collect($paginator->items())->pluck('id')->map(fn ($id) => (int) $id)->all();
        $balances = DB::table('stock_balances')
            ->whereIn('warehouse_id', $warehouseIds)
            ->whereIn('product_id', $pageProductIds)
            ->get(['warehouse_id', 'product_id', 'quantity'])
            ->keyBy(fn ($balance) => "{$balance->product_id}:{$balance->warehouse_id}");

        return ApiResponse::success('Stock balances loaded.', [
            'warehouses' => $warehouses->map(fn ($warehouse) => [
                'id' => (int) $warehouse->id,
                'code' => $warehouse->code,
                'name' => $warehouse->name,
                'total_quantity' => (float) ($warehouseTotals[$warehouse->id] ?? 0),
            ]),
            'items' => collect($paginator->items())->map(function ($product) use ($warehouses, $balances) {
                $quantities = $warehouses->mapWithKeys(function ($warehouse) use ($product, $balances) {
                    $balance = $balances->get("{$product->id}:{$warehouse->id}");

                    return [(string) $warehouse->id => (float) ($balance->quantity ?? 0)];
                })->all();

                return [
                    'product_id' => (int) $product->id,
                    'product_sku' => $product->sku,
                    'product_name' => $product->name,
                    'unit' => $product->unit,
                    'quantities' => $quantities,
                    'quantity' => (float) array_sum($quantities),
                ];
            }),
            'summary' => [
                'products_count' => $paginator->total(),
                'warehouses_count' => $warehouses->count(),
                'total_quantity' => (float) $totalQuantity,
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function stockValue(Request $request)
    {
        $this->authorizePermission($request, self::VIEW_PERMISSION);

        // Use the same active, permission-protected warehouse scope as Stock Balance.
        $warehouseQuery = DB::table('warehouses')
            ->where('is_active', true)
            ->orderBy('name');

        if ($request->filled('warehouse_id')) {
            $warehouseQuery->where('id', $request->integer('warehouse_id'));
        }

        $warehouses = $warehouseQuery->get(['id', 'code', 'name']);
        $warehouseIds = $warehouses->pluck('id')->map(fn ($id) => (int) $id)->all();

        $productQuery = DB::table('products')
            ->where('is_active', true)
            ->orderBy('name');

        if ($request->filled('product_id')) {
            $productQuery->where('id', $request->integer('product_id'));
        }

        if ($search = trim((string) $request->query('search'))) {
            $productQuery->where(function ($query) use ($search) {
                $query->where('sku', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $filteredProductIds = (clone $productQuery)->reorder()->select('products.id');
        $balanceScope = DB::table('stock_balances')
            ->whereIn('warehouse_id', $warehouseIds)
            ->whereIn('product_id', $filteredProductIds);
        $summary = (clone $balanceScope)
            ->selectRaw('COUNT(*) as balance_lines, COALESCE(SUM(quantity), 0) as total_quantity, COALESCE(SUM(stock_value), 0) as stock_value')
            ->first();
        $warehouseTotals = (clone $balanceScope)
            ->select('warehouse_id')
            ->selectRaw('COUNT(DISTINCT product_id) as products_count, COALESCE(SUM(quantity), 0) as total_quantity, COALESCE(SUM(stock_value), 0) as stock_value')
            ->groupBy('warehouse_id')
            ->get()
            ->keyBy('warehouse_id');

        $paginator = $productQuery
            ->select(['id', 'sku', 'name', 'unit'])
            ->paginate(min(max((int) $request->query('per_page', 20), 1), 100));
        $pageProductIds = collect($paginator->items())->pluck('id')->map(fn ($id) => (int) $id)->all();
        $balances = DB::table('stock_balances')
            ->whereIn('warehouse_id', $warehouseIds)
            ->whereIn('product_id', $pageProductIds)
            ->get(['warehouse_id', 'product_id', 'stock_value'])
            ->keyBy(fn ($balance) => "{$balance->product_id}:{$balance->warehouse_id}");

        return ApiResponse::success('Stock value report loaded.', [
            'items' => collect($paginator->items())->map(function ($product) use ($warehouses, $balances) {
                $values = $warehouses->mapWithKeys(function ($warehouse) use ($product, $balances) {
                    $balance = $balances->get("{$product->id}:{$warehouse->id}");

                    return [(string) $warehouse->id => (float) ($balance->stock_value ?? 0)];
                })->all();

                return [
                    'product_id' => (int) $product->id,
                    'product_sku' => $product->sku,
                    'product_name' => $product->name,
                    'unit' => $product->unit,
                    'values' => $values,
                    'stock_value' => (float) array_sum($values),
                ];
            }),
            'warehouses' => $warehouses->map(function ($warehouse) use ($warehouseTotals) {
                $totals = $warehouseTotals->get($warehouse->id);

                return [
                    'id' => (int) $warehouse->id,
                    'code' => $warehouse->code,
                    'name' => $warehouse->name,
                    'products_count' => (int) ($totals->products_count ?? 0),
                    'total_quantity' => (float) ($totals->total_quantity ?? 0),
                    'stock_value' => (float) ($totals->stock_value ?? 0),
                ];
            }),
            'summary' => [
                'balance_lines' => (int) ($summary->balance_lines ?? 0),
                'products_count' => $paginator->total(),
                'warehouses_count' => $warehouses->count(),
                'total_quantity' => (float) ($summary->total_quantity ?? 0),
                'stock_value' => (float) ($summary->stock_value ?? 0),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function storeMovement(Request $request)
    {
        $this->authorizePermission($request, self::MANAGE_PERMISSION);

        $validated = $request->validate([
            'movement_type' => ['required', Rule::in(['opening', 'receive', 'issue', 'damage', 'adjustment'])],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'movement_date' => ['nullable', 'date'],
            'quantity' => ['required', 'numeric', 'not_in:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'reference_code' => ['nullable', 'string', 'max:80'],
            'settlement_method' => ['nullable', Rule::in(['credit', 'cash', 'bank'])],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'due_date' => ['nullable', 'date', 'after_or_equal:movement_date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'adjustment_reason' => ['nullable', Rule::requiredIf(fn () => $request->input('movement_type') === 'adjustment'), Rule::in(self::ADJUSTMENT_REASONS)],
        ]);

        $warehouse = DB::table('warehouses')->where('is_active', true)->find($validated['warehouse_id']);
        $product = DB::table('products')->where('is_active', true)->find($validated['product_id']);
        abort_unless($warehouse && $product, 422, 'Active warehouse and product are required.');

        $movementDate = Carbon::parse($validated['movement_date'] ?? now());

        if (in_array($validated['movement_type'], ['opening', 'receive'], true)) {
            $validated['document_code'] = $this->nextReceiptCode($movementDate);
            $validated['reference_type'] = 'stock_receipt';
        }

        if ($validated['movement_type'] === 'adjustment') {
            $validated['reference_type'] = 'stock_adjustment';
        }

        $result = DB::transaction(fn () => $this->applyMovement($validated, $movementDate, $request));

        [$movement, $balance] = $result;
        $movementPayload = $this->movementQuery()->select($this->movementColumns())->where('stock_movements.id', $movement->id)->first();
        $balancePayload = $this->balanceQuery()->select($this->balanceColumns())->where('stock_balances.id', $balance->id)->first();

        return ApiResponse::success('Stock movement recorded.', [
            'movement' => $this->movementPayload($movementPayload),
            'balance' => $this->balancePayload($balancePayload),
        ], 201);
    }

    public function storeReceipt(Request $request)
    {
        $this->authorizePermission($request, self::MANAGE_PERMISSION);

        $validated = $request->validate([
            'movement_type' => ['required', Rule::in(['opening', 'receive'])],
            'supplier_id' => ['nullable', Rule::requiredIf(fn () => $request->input('movement_type') === 'receive'), 'integer', 'exists:suppliers,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'movement_date' => ['nullable', 'date'],
            'reference_code' => ['nullable', 'string', 'max:80'],
            'settlement_method' => ['nullable', Rule::requiredIf(fn () => $request->input('movement_type') === 'receive'), Rule::in(['credit', 'cash', 'bank'])],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'due_date' => ['nullable', 'date', 'after_or_equal:movement_date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:9999999999'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
        ]);

        $warehouse = DB::table('warehouses')->where('is_active', true)->find($validated['warehouse_id']);
        abort_unless($warehouse, 422, 'An active warehouse is required.');

        $supplier = isset($validated['supplier_id'])
            ? DB::table('suppliers')->where('is_active', true)->find($validated['supplier_id'])
            : null;
        abort_if($validated['movement_type'] === 'receive' && ! $supplier, 422, 'An active supplier is required for a stock purchase.');

        $productIds = collect($validated['items'])->pluck('product_id');
        $activeProductIds = DB::table('products')->where('is_active', true)->whereIn('id', $productIds)->pluck('id');
        abort_unless($activeProductIds->count() === $productIds->count(), 422, 'Every receipt line requires an active product.');

        $movementDate = Carbon::parse($validated['movement_date'] ?? now());
        $settlementMethod = $validated['movement_type'] === 'receive'
            ? ($validated['settlement_method'] ?? 'credit')
            : null;
        $paymentTermsDays = $settlementMethod === 'credit'
            ? (int) ($validated['payment_terms_days'] ?? 30)
            : 0;
        $receiptDueDate = $settlementMethod === 'credit' && isset($validated['due_date'])
            ? Carbon::parse($validated['due_date'])->toDateString()
            : $movementDate->copy()->addDays($paymentTermsDays)->toDateString();

        $result = DB::transaction(function () use ($validated, $movementDate, $settlementMethod, $paymentTermsDays, $receiptDueDate, $request) {
            $documentCode = $this->nextReceiptCode($movementDate);
            $movements = [];
            $totalQuantity = 0;
            $totalValue = 0;

            foreach ($validated['items'] as $index => $item) {
                [$movement] = $this->applyMovement([
                    'movement_type' => $validated['movement_type'],
                    'warehouse_id' => $validated['warehouse_id'],
                    'supplier_id' => $validated['movement_type'] === 'receive' ? $validated['supplier_id'] : null,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'] ?? null,
                    'reference_type' => 'stock_receipt',
                    'reference_code' => $validated['reference_code'] ?? null,
                    'document_code' => $documentCode,
                    'notes' => $validated['notes'] ?? null,
                ], $movementDate, $request, $documentCode.'-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT));

                $movements[] = $this->movementPayload($this->movementQuery()->select($this->movementColumns())->where('stock_movements.id', $movement->id)->first());
                $totalQuantity += (float) $movement->signed_quantity;
                $totalValue += (float) $movement->total_cost;
            }

            if ($validated['movement_type'] === 'receive') {
                $invoice = SupplierInvoice::create([
                    'code' => $this->nextSupplierInvoiceCode($movementDate),
                    'supplier_id' => $validated['supplier_id'],
                    'stock_document_code' => $documentCode,
                    'invoice_no' => $validated['reference_code'] ?? null,
                    'invoice_date' => $movementDate->toDateString(),
                    'due_date' => $receiptDueDate,
                    'payment_terms_days' => $paymentTermsDays,
                    'total' => $totalValue,
                    'paid_amount' => $settlementMethod === 'credit' ? 0 : $totalValue,
                    'status' => $settlementMethod === 'credit' ? 'unpaid' : 'paid',
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => $request->user()?->id,
                ]);
                SupplierLedgerEntry::create([
                    'supplier_id' => $validated['supplier_id'],
                    'entry_date' => $movementDate->toDateString(),
                    'entry_type' => 'purchase',
                    'source_type' => 'stock_receipt',
                    'source_key' => $documentCode,
                    'reference_no' => $validated['reference_code'] ?? $documentCode,
                    'description' => "Stock receipt {$documentCode}",
                    'debit' => 0,
                    'credit' => $totalValue,
                    'created_by' => $request->user()?->id,
                ]);

                if ($settlementMethod !== 'credit') {
                    $payment = SupplierPayment::create([
                        'code' => $this->nextSupplierPaymentCode($movementDate),
                        'supplier_id' => $validated['supplier_id'],
                        'supplier_invoice_id' => $invoice->id,
                        'payment_date' => $movementDate->toDateString(),
                        'amount' => $totalValue,
                        'payment_method' => $settlementMethod,
                        'reference_no' => $validated['reference_code'] ?? null,
                        'notes' => 'Paid when stock receipt was recorded.',
                        'created_by' => $request->user()?->id,
                    ]);
                    SupplierLedgerEntry::create([
                        'supplier_id' => $validated['supplier_id'],
                        'entry_date' => $movementDate->toDateString(),
                        'entry_type' => 'payment',
                        'source_type' => 'supplier_payment',
                        'source_key' => $payment->code,
                        'reference_no' => $payment->reference_no ?: $payment->code,
                        'description' => "Immediate payment for {$invoice->code}",
                        'debit' => $totalValue,
                        'credit' => 0,
                        'created_by' => $request->user()?->id,
                    ]);
                    FinancialTransaction::create([
                        'code' => 'TXN-'.str_pad((string) (FinancialTransaction::max('id') + 1), 7, '0', STR_PAD_LEFT),
                        'transaction_date' => $movementDate->toDateString(),
                        'book_type' => $settlementMethod,
                        'direction' => 'out',
                        'category' => 'supplier_payment',
                        'amount' => $totalValue,
                        'reference_type' => 'supplier_payment',
                        'reference_id' => $payment->id,
                        'reference_code' => $payment->code,
                        'description' => "Supplier payment {$payment->code}",
                        'created_by' => $request->user()?->id,
                    ]);
                }
            }

            return compact('documentCode', 'movements', 'totalQuantity', 'totalValue');
        }, 3);

        return ApiResponse::success('Stock receipt recorded.', [
            'receipt' => [
                'document_code' => $result['documentCode'],
                'supplier_id' => $supplier?->id,
                'supplier_code' => $supplier?->code,
                'supplier_name' => $supplier?->name,
                'warehouse_id' => (int) $validated['warehouse_id'],
                'movement_type' => $validated['movement_type'],
                'movement_date' => $movementDate->toDateString(),
                'reference_code' => $validated['reference_code'] ?? null,
                'settlement_method' => $settlementMethod,
                'payment_terms_days' => $validated['movement_type'] === 'receive' ? $paymentTermsDays : null,
                'due_date' => $validated['movement_type'] === 'receive' ? $receiptDueDate : null,
                'products_count' => count($result['movements']),
                'total_quantity' => $result['totalQuantity'],
                'total_value' => $result['totalValue'],
                'items' => $result['movements'],
            ],
        ], 201);
    }

    public function storeTransfer(Request $request)
    {
        $this->authorizePermission($request, self::MANAGE_PERMISSION);

        $validated = $request->validate([
            'from_warehouse_id' => ['required', 'integer', 'exists:warehouses,id', 'different:to_warehouse_id'],
            'to_warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'product_id' => ['required_without:items', 'integer', 'exists:products,id'],
            'movement_date' => ['nullable', 'date'],
            'quantity' => ['required_without:items', 'numeric', 'min:0.01'],
            'items' => ['required_without:product_id', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:9999999999'],
            'reference_code' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $validated['items'] = $validated['items'] ?? [['product_id' => $validated['product_id'], 'quantity' => $validated['quantity']]];

        $fromWarehouse = DB::table('warehouses')->where('is_active', true)->find($validated['from_warehouse_id']);
        $toWarehouse = DB::table('warehouses')->where('is_active', true)->find($validated['to_warehouse_id']);
        $productIds = collect($validated['items'])->pluck('product_id');
        $activeProductIds = DB::table('products')->where('is_active', true)->whereIn('id', $productIds)->pluck('id');
        abort_unless($fromWarehouse && $toWarehouse && $activeProductIds->count() === $productIds->count(), 422, 'Active warehouses and products are required.');

        $movementDate = Carbon::parse($validated['movement_date'] ?? now());

        $result = DB::transaction(function () use ($validated, $movementDate, $request) {
            $transferCode = $this->nextTransferCode($movementDate);
            $lines = [];
            foreach ($validated['items'] as $index => $item) {
                $sourceBalance = $this->inventory->balanceFor((int) $validated['from_warehouse_id'], (int) $item['product_id']);
                $unitCost = (float) $sourceBalance->average_cost;
                abort_if((float) $sourceBalance->quantity < (float) $item['quantity'], 409, 'Insufficient stock balance.');
                $line = count($validated['items']) > 1 ? '-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT) : '';
                $common = ['product_id' => $item['product_id'], 'quantity' => $item['quantity'], 'unit_cost' => $unitCost, 'reference_type' => 'stock_transfer', 'reference_code' => $validated['reference_code'] ?? $transferCode, 'document_code' => $transferCode, 'notes' => $validated['notes'] ?? null];
                [$outMovement, $sourceBalance] = $this->applyMovement($common + ['movement_type' => 'transfer_out', 'warehouse_id' => $validated['from_warehouse_id']], $movementDate, $request, "{$transferCode}{$line}-OUT");
                [$inMovement, $destinationBalance] = $this->applyMovement($common + ['movement_type' => 'transfer_in', 'warehouse_id' => $validated['to_warehouse_id']], $movementDate, $request, "{$transferCode}{$line}-IN");
                $lines[] = compact('outMovement', 'inMovement', 'sourceBalance', 'destinationBalance');
            }

            return [$transferCode, $lines];
        });

        [$transferCode, $lines] = $result;
        $first = $lines[0];

        return ApiResponse::success('Stock transfer recorded.', [
            'transfer_code' => $transferCode,
            'products_count' => count($lines),
            'items' => collect($lines)->map(fn ($line) => ['out_movement' => $this->movementPayload($this->movementQuery()->select($this->movementColumns())->where('stock_movements.id', $line['outMovement']->id)->first()), 'in_movement' => $this->movementPayload($this->movementQuery()->select($this->movementColumns())->where('stock_movements.id', $line['inMovement']->id)->first())]),
            'out_movement' => $this->movementPayload($this->movementQuery()->select($this->movementColumns())->where('stock_movements.id', $first['outMovement']->id)->first()),
            'in_movement' => $this->movementPayload($this->movementQuery()->select($this->movementColumns())->where('stock_movements.id', $first['inMovement']->id)->first()),
            'source_balance' => $this->balancePayload($this->balanceQuery()->select($this->balanceColumns())->where('stock_balances.id', $first['sourceBalance']->id)->first()),
            'destination_balance' => $this->balancePayload($this->balanceQuery()->select($this->balanceColumns())->where('stock_balances.id', $first['destinationBalance']->id)->first()),
        ], 201);
    }

    public function closingCounts(Request $request)
    {
        $this->authorizePermission($request, self::VIEW_PERMISSION);

        $query = DB::table('stock_counts')
            ->join('warehouses', 'stock_counts.warehouse_id', '=', 'warehouses.id')
            ->latest('stock_counts.count_date')
            ->latest('stock_counts.id');

        if ($request->filled('warehouse_id')) {
            $query->where('stock_counts.warehouse_id', $request->query('warehouse_id'));
        }

        if ($request->filled('product_id')) {
            $productId = (int) $request->query('product_id');
            $query->whereExists(function ($items) use ($productId) {
                $items->selectRaw('1')
                    ->from('stock_count_items')
                    ->whereColumn('stock_count_items.stock_count_id', 'stock_counts.id')
                    ->where('stock_count_items.product_id', $productId);
            });
        }

        if ($request->filled('date')) {
            $query->whereDate('stock_counts.count_date', $request->query('date'));
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('stock_counts.code', 'like', "%{$search}%")
                    ->orWhere('stock_counts.reference_code', 'like', "%{$search}%")
                    ->orWhere('warehouses.code', 'like', "%{$search}%")
                    ->orWhere('warehouses.name', 'like', "%{$search}%")
                    ->orWhereExists(function ($items) use ($search) {
                        $items->selectRaw('1')
                            ->from('stock_count_items')
                            ->join('products', 'stock_count_items.product_id', '=', 'products.id')
                            ->whereColumn('stock_count_items.stock_count_id', 'stock_counts.id')
                            ->where(function ($products) use ($search) {
                                $products->where('products.sku', 'like', "%{$search}%")
                                    ->orWhere('products.name', 'like', "%{$search}%");
                            });
                    });
            });
        }

        $summary = (clone $query)->reorder()->selectRaw(
            'COUNT(*) as records_count, COALESCE(SUM(stock_counts.quantity_added), 0) as in_quantity, COALESCE(SUM(stock_counts.quantity_removed), 0) as out_quantity, COALESCE(SUM(stock_counts.variance_value), 0) as stock_value'
        )->first();

        $paginator = $query->select([
            'stock_counts.*',
            'warehouses.code as warehouse_code',
            'warehouses.name as warehouse_name',
        ])->paginate(min(max((int) $request->query('per_page', 20), 1), 100));

        return ApiResponse::success('Stock counts loaded.', [
            'items' => collect($paginator->items())->map(fn ($count) => $this->stockCountPayload($count)),
            'summary' => [
                'records_count' => (int) ($summary->records_count ?? 0),
                'in_quantity' => (float) ($summary->in_quantity ?? 0),
                'out_quantity' => (float) ($summary->out_quantity ?? 0),
                'stock_value' => (float) ($summary->stock_value ?? 0),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function closingCountPreview(Request $request)
    {
        $this->authorizePermission($request, self::VIEW_PERMISSION);

        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
        ]);

        $warehouse = DB::table('warehouses')->where('is_active', true)->find($validated['warehouse_id']);
        abort_unless($warehouse, 422, 'An active warehouse is required.');

        $products = DB::table('products')
            ->leftJoin('stock_balances', function ($join) use ($validated) {
                $join->on('products.id', '=', 'stock_balances.product_id')
                    ->where('stock_balances.warehouse_id', '=', $validated['warehouse_id']);
            })
            ->where('products.is_active', true)
            ->orderBy('products.name')
            ->get([
                'products.id as product_id',
                'products.sku',
                'products.name',
                'products.unit',
                DB::raw('COALESCE(stock_balances.quantity, 0) as system_quantity'),
                DB::raw('COALESCE(stock_balances.average_cost, 0) as unit_cost'),
            ])
            ->map(function ($product) {
                $unitCost = (float) $product->unit_cost;
                if ($unitCost <= 0) {
                    $unitCost = (float) DB::table('stock_balances')
                        ->where('product_id', $product->product_id)
                        ->where('average_cost', '>', 0)
                        ->orderByDesc('last_movement_at')
                        ->value('average_cost');
                }

                return [
                    'product_id' => (int) $product->product_id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'unit' => $product->unit,
                    'system_quantity' => (float) $product->system_quantity,
                    'unit_cost' => $unitCost,
                ];
            });

        return ApiResponse::success('Stock count worksheet loaded.', [
            'warehouse' => ['id' => (int) $warehouse->id, 'code' => $warehouse->code, 'name' => $warehouse->name],
            'items' => $products,
        ]);
    }

    public function showClosingCount(Request $request, int $count)
    {
        $this->authorizePermission($request, self::VIEW_PERMISSION);

        $stockCount = DB::table('stock_counts')
            ->join('warehouses', 'stock_counts.warehouse_id', '=', 'warehouses.id')
            ->leftJoin('users', 'stock_counts.created_by', '=', 'users.id')
            ->where('stock_counts.id', $count)
            ->first([
                'stock_counts.*',
                'warehouses.code as warehouse_code',
                'warehouses.name as warehouse_name',
                'users.name as created_by_name',
            ]);
        abort_unless($stockCount, 404, 'Stock count not found.');

        $items = DB::table('stock_count_items')
            ->join('products', 'stock_count_items.product_id', '=', 'products.id')
            ->leftJoin('stock_movements', 'stock_count_items.stock_movement_id', '=', 'stock_movements.id')
            ->where('stock_count_items.stock_count_id', $count)
            ->orderBy('products.name')
            ->get([
                'stock_count_items.id',
                'stock_count_items.product_id',
                'products.sku as product_sku',
                'products.name as product_name',
                'products.unit',
                'stock_count_items.system_quantity',
                'stock_count_items.counted_quantity',
                'stock_count_items.variance_quantity',
                'stock_count_items.unit_cost',
                'stock_count_items.variance_value',
                'stock_count_items.stock_movement_id',
                'stock_movements.code as movement_code',
            ])
            ->map(fn ($item) => [
                'id' => (int) $item->id,
                'product_id' => (int) $item->product_id,
                'product_sku' => $item->product_sku,
                'product_name' => $item->product_name,
                'unit' => $item->unit,
                'system_quantity' => (float) $item->system_quantity,
                'counted_quantity' => (float) $item->counted_quantity,
                'variance_quantity' => (float) $item->variance_quantity,
                'unit_cost' => (float) $item->unit_cost,
                'variance_value' => (float) $item->variance_value,
                'stock_movement_id' => $item->stock_movement_id ? (int) $item->stock_movement_id : null,
                'movement_code' => $item->movement_code,
            ]);

        $payload = $this->stockCountPayload($stockCount);
        $payload['created_by_name'] = $stockCount->created_by_name;
        $payload['created_at'] = Carbon::parse($stockCount->created_at)->toDateTimeString();

        return ApiResponse::success('Stock count loaded.', [
            'count' => $payload,
            'items' => $items,
        ]);
    }

    public function storeClosingCount(Request $request)
    {
        $this->authorizePermission($request, self::MANAGE_PERMISSION);

        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'movement_date' => ['nullable', 'date'],
            'reference_code' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:1000'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.system_quantity' => ['required', 'numeric', 'min:0'],
            'items.*.counted_quantity' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
        ]);

        $warehouse = DB::table('warehouses')->where('is_active', true)->find($validated['warehouse_id']);
        abort_unless($warehouse, 422, 'An active warehouse is required.');

        $activeProductIds = DB::table('products')->where('is_active', true)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $submittedProductIds = collect($validated['items'])->pluck('product_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $expectedProductIds = collect($activeProductIds)->sort()->values()->all();
        abort_unless($submittedProductIds === $expectedProductIds, 422, 'The stock count must include every active product exactly once.');

        $movementDate = Carbon::parse($validated['movement_date'] ?? now());

        $count = DB::transaction(function () use ($validated, $movementDate, $request) {
            $code = $this->nextStockCountCode($movementDate);
            $countId = DB::table('stock_counts')->insertGetId([
                'code' => $code,
                'warehouse_id' => $validated['warehouse_id'],
                'count_date' => $movementDate->toDateString(),
                'reference_code' => $validated['reference_code'] ?? null,
                'status' => 'completed',
                'created_by' => $request->user()?->id,
                'notes' => $validated['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $totals = ['system' => 0.0, 'counted' => 0.0, 'added' => 0.0, 'removed' => 0.0, 'value' => 0.0];

            foreach ($validated['items'] as $index => $item) {
                $balance = $this->inventory->balanceFor((int) $validated['warehouse_id'], (int) $item['product_id']);
                $systemQuantity = (float) $balance->quantity;
                abort_if(abs($systemQuantity - (float) $item['system_quantity']) > 0.0001, 409, 'Stock changed while this count was open. Reload the worksheet and count again.');

                $countedQuantity = (float) $item['counted_quantity'];
                $variance = $countedQuantity - $systemQuantity;
                $unitCost = (float) $balance->average_cost > 0 ? (float) $balance->average_cost : (float) ($item['unit_cost'] ?? 0);
                abort_if($variance > 0 && $unitCost <= 0, 422, 'Unit cost is required when found stock is added.');

                $movementId = null;
                if (abs($variance) > 0.0001) {
                    [$movement] = $this->applyMovement([
                        'movement_type' => 'adjustment',
                        'warehouse_id' => $validated['warehouse_id'],
                        'product_id' => $item['product_id'],
                        'quantity' => $variance,
                        'unit_cost' => $unitCost,
                        'document_code' => $code,
                        'reference_code' => $validated['reference_code'] ?? null,
                        'reference_type' => 'closing_count',
                        'reference_id' => $countId,
                        'adjustment_reason' => 'count_correction',
                        'notes' => $validated['notes'] ?? null,
                    ], $movementDate, $request, $code.'-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT));
                    $movementId = $movement->id;
                }

                $varianceValue = $variance * $unitCost;
                DB::table('stock_count_items')->insert([
                    'stock_count_id' => $countId,
                    'product_id' => $item['product_id'],
                    'system_quantity' => $systemQuantity,
                    'counted_quantity' => $countedQuantity,
                    'variance_quantity' => $variance,
                    'unit_cost' => $unitCost,
                    'variance_value' => $varianceValue,
                    'stock_movement_id' => $movementId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $totals['system'] += $systemQuantity;
                $totals['counted'] += $countedQuantity;
                $totals['added'] += max($variance, 0);
                $totals['removed'] += abs(min($variance, 0));
                $totals['value'] += $varianceValue;
            }

            DB::table('stock_counts')->where('id', $countId)->update([
                'products_count' => count($validated['items']),
                'system_quantity' => $totals['system'],
                'counted_quantity' => $totals['counted'],
                'quantity_added' => $totals['added'],
                'quantity_removed' => $totals['removed'],
                'variance_value' => $totals['value'],
                'updated_at' => now(),
            ]);

            return DB::table('stock_counts')
                ->join('warehouses', 'stock_counts.warehouse_id', '=', 'warehouses.id')
                ->where('stock_counts.id', $countId)
                ->first(['stock_counts.*', 'warehouses.code as warehouse_code', 'warehouses.name as warehouse_name']);
        }, 3);

        return ApiResponse::success('Closing stock count recorded.', [
            'count' => $this->stockCountPayload($count),
        ], 201);
    }

    public function stockCard(Request $request)
    {
        $this->authorizePermission($request, self::VIEW_PERMISSION);

        $validated = $request->validate([
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $dateFrom = isset($validated['date_from']) ? Carbon::parse($validated['date_from'])->toDateString() : null;
        $dateTo = isset($validated['date_to']) ? Carbon::parse($validated['date_to'])->toDateString() : null;

        $product = DB::table('products')->find($validated['product_id']);
        $warehouse = isset($validated['warehouse_id']) ? DB::table('warehouses')->find($validated['warehouse_id']) : null;

        $openingBalance = $dateFrom ? (float) StockMovement::query()
            ->where('product_id', $validated['product_id'])
            ->when(isset($validated['warehouse_id']), fn ($query) => $query->where('warehouse_id', $validated['warehouse_id']))
            ->whereDate('movement_date', '<', $dateFrom)
            ->sum('signed_quantity') : 0.0;

        $query = $this->movementQuery()
            ->where('stock_movements.product_id', $validated['product_id'])
            ->when(isset($validated['warehouse_id']), fn ($query) => $query->where('stock_movements.warehouse_id', $validated['warehouse_id']))
            ->when($dateFrom, fn ($query) => $query->whereDate('stock_movements.movement_date', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('stock_movements.movement_date', '<=', $dateTo))
            ->orderBy('stock_movements.movement_date')
            ->orderBy('stock_movements.id');

        $runningBalance = $openingBalance;
        $items = $query->select($this->movementColumns())->get()->map(function ($movement) use (&$runningBalance) {
            $runningBalance += (float) $movement->signed_quantity;
            $payload = $this->movementPayload($movement);
            $payload['running_balance'] = $runningBalance;

            return $payload;
        });

        return ApiResponse::success('Stock card loaded.', [
            'product' => [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'unit' => $product->unit,
            ],
            'warehouse' => $warehouse ? [
                'id' => $warehouse->id,
                'code' => $warehouse->code,
                'name' => $warehouse->name,
            ] : null,
            'summary' => [
                'opening_balance' => $openingBalance,
                'in_quantity' => (float) $items->where('signed_quantity', '>', 0)->sum('signed_quantity'),
                'out_quantity' => abs((float) $items->where('signed_quantity', '<', 0)->sum('signed_quantity')),
                'closing_balance' => $runningBalance,
                'records_count' => $items->count(),
            ],
            'items' => $items,
        ]);
    }

    private function applyMovement(array $validated, Carbon $movementDate, Request $request, ?string $code = null): array
    {
        return $this->inventory->applyMovement($validated, $movementDate, $request->user()?->id, $code);
    }

    private function nextSupplierInvoiceCode(Carbon $date): string
    {
        $prefix = 'PIN-'.$date->format('Ym').'-';
        $next = SupplierInvoice::where('code', 'like', "{$prefix}%")->count() + 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function nextSupplierPaymentCode(Carbon $date): string
    {
        $prefix = 'SPY-'.$date->format('Ym').'-';
        $next = SupplierPayment::where('code', 'like', "{$prefix}%")->count() + 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function movementQuery()
    {
        return DB::table('stock_movements')
            ->join('warehouses', 'stock_movements.warehouse_id', '=', 'warehouses.id')
            ->join('products', 'stock_movements.product_id', '=', 'products.id')
            ->leftJoin('suppliers', 'stock_movements.supplier_id', '=', 'suppliers.id');
    }

    private function balanceQuery()
    {
        return DB::table('stock_balances')
            ->join('warehouses', 'stock_balances.warehouse_id', '=', 'warehouses.id')
            ->join('products', 'stock_balances.product_id', '=', 'products.id');
    }

    private function movementColumns(): array
    {
        return [
            'stock_movements.*',
            'warehouses.code as warehouse_code',
            'warehouses.name as warehouse_name',
            'products.sku as product_sku',
            'products.name as product_name',
            'products.unit',
            'suppliers.code as supplier_code',
            'suppliers.name as supplier_name',
        ];
    }

    private function balanceColumns(): array
    {
        return [
            'stock_balances.*',
            'warehouses.code as warehouse_code',
            'warehouses.name as warehouse_name',
            'products.sku as product_sku',
            'products.name as product_name',
            'products.unit',
        ];
    }

    private function movementPayload($movement): array
    {
        return [
            'id' => $movement->id,
            'code' => $movement->code,
            'document_code' => $movement->document_code,
            'supplier_id' => $movement->supplier_id,
            'supplier_code' => $movement->supplier_code,
            'supplier_name' => $movement->supplier_name,
            'warehouse_id' => $movement->warehouse_id,
            'warehouse_code' => $movement->warehouse_code,
            'warehouse_name' => $movement->warehouse_name,
            'product_id' => $movement->product_id,
            'product_sku' => $movement->product_sku,
            'product_name' => $movement->product_name,
            'unit' => $movement->unit,
            'movement_type' => $movement->movement_type,
            'adjustment_reason' => $movement->adjustment_reason ?: ($movement->movement_type === 'damage' ? 'damage' : null),
            'movement_date' => Carbon::parse($movement->movement_date)->toDateString(),
            'quantity' => (float) $movement->quantity,
            'signed_quantity' => (float) $movement->signed_quantity,
            'balance_before' => $movement->balance_before !== null ? (float) $movement->balance_before : null,
            'balance_after' => $movement->balance_after !== null ? (float) $movement->balance_after : null,
            'unit_cost' => (float) $movement->unit_cost,
            'total_cost' => (float) $movement->total_cost,
            'reference_type' => $movement->reference_type,
            'reference_code' => $movement->reference_code,
            'notes' => $movement->notes,
            'updated_at' => Carbon::parse($movement->updated_at)->toDateTimeString(),
        ];
    }

    private function balancePayload($balance): array
    {
        return [
            'id' => $balance->id,
            'warehouse_id' => $balance->warehouse_id,
            'warehouse_code' => $balance->warehouse_code,
            'warehouse_name' => $balance->warehouse_name,
            'product_id' => $balance->product_id,
            'product_sku' => $balance->product_sku,
            'product_name' => $balance->product_name,
            'unit' => $balance->unit,
            'quantity' => (float) $balance->quantity,
            'average_cost' => (float) $balance->average_cost,
            'stock_value' => (float) $balance->stock_value,
            'last_movement_at' => $balance->last_movement_at ? Carbon::parse($balance->last_movement_at)->toDateTimeString() : null,
            'updated_at' => Carbon::parse($balance->updated_at)->toDateTimeString(),
        ];
    }

    private function stockCountPayload($count): array
    {
        return [
            'id' => (int) $count->id,
            'code' => $count->code,
            'warehouse_id' => (int) $count->warehouse_id,
            'warehouse_code' => $count->warehouse_code,
            'warehouse_name' => $count->warehouse_name,
            'count_date' => Carbon::parse($count->count_date)->toDateString(),
            'reference_code' => $count->reference_code,
            'status' => $count->status,
            'products_count' => (int) $count->products_count,
            'system_quantity' => (float) $count->system_quantity,
            'counted_quantity' => (float) $count->counted_quantity,
            'quantity_added' => (float) $count->quantity_added,
            'quantity_removed' => (float) $count->quantity_removed,
            'variance_value' => (float) $count->variance_value,
            'notes' => $count->notes,
            'updated_at' => Carbon::parse($count->updated_at)->toDateTimeString(),
        ];
    }

    private function nextTransferCode(Carbon $movementDate): string
    {
        $prefix = 'TRF-'.$movementDate->format('Ym').'-';
        $next = ((int) floor(StockMovement::query()->where('code', 'like', "{$prefix}%")->count() / 2)) + 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function nextReceiptCode(Carbon $movementDate): string
    {
        $prefix = 'REC-'.$movementDate->format('Ym').'-';
        $next = StockMovement::query()
            ->where('document_code', 'like', "{$prefix}%")
            ->distinct()
            ->count('document_code') + 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function nextStockCountCode(Carbon $movementDate): string
    {
        $prefix = 'CNT-'.$movementDate->format('Ym').'-';
        $next = DB::table('stock_counts')->where('code', 'like', "{$prefix}%")->count() + 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless(in_array($permission, AppAccess::permissionsForRole($request->user()?->role), true), 403);
    }
}
