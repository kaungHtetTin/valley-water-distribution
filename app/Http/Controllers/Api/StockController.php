<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockBalance;
use App\Models\StockMovement;
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

        return ApiResponse::success('Stock setup loaded.', [
            'warehouses' => $warehouses,
            'products' => $products,
            'movement_types' => ['opening', 'receive', 'issue', 'damage', 'adjustment', 'transfer_out', 'transfer_in'],
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

        if ($request->filled('type')) {
            $query->where('stock_movements.movement_type', $request->query('type'));
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
                    ->orWhere('products.name', 'like', "%{$search}%");
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

    public function balances(Request $request)
    {
        $this->authorizePermission($request, self::VIEW_PERMISSION);

        $query = $this->balanceQuery()
            ->orderBy('warehouses.name')
            ->orderBy('products.name');

        if ($request->filled('warehouse_id')) {
            $query->where('stock_balances.warehouse_id', $request->query('warehouse_id'));
        }

        if ($request->filled('product_id')) {
            $query->where('stock_balances.product_id', $request->query('product_id'));
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($query) use ($search) {
                $query->where('warehouses.code', 'like', "%{$search}%")
                    ->orWhere('warehouses.name', 'like', "%{$search}%")
                    ->orWhere('products.sku', 'like', "%{$search}%")
                    ->orWhere('products.name', 'like', "%{$search}%");
            });
        }

        $summary = (clone $query)->reorder()
            ->selectRaw('COUNT(*) as products_count, COALESCE(SUM(stock_balances.quantity), 0) as total_quantity, COALESCE(SUM(stock_balances.stock_value), 0) as stock_value')
            ->first();

        $paginator = $query->select($this->balanceColumns())
            ->paginate(min(max((int) $request->query('per_page', 20), 1), 100));

        return ApiResponse::success('Stock balances loaded.', [
            'items' => collect($paginator->items())->map(fn ($balance) => $this->balancePayload($balance)),
            'summary' => [
                'products_count' => (int) ($summary->products_count ?? 0),
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

    public function stockValue(Request $request)
    {
        $this->authorizePermission($request, self::VIEW_PERMISSION);

        $query = $this->balanceQuery();

        if ($request->filled('warehouse_id')) {
            $query->where('stock_balances.warehouse_id', $request->query('warehouse_id'));
        }

        if ($request->filled('product_id')) {
            $query->where('stock_balances.product_id', $request->query('product_id'));
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($query) use ($search) {
                $query->where('warehouses.code', 'like', "%{$search}%")
                    ->orWhere('warehouses.name', 'like', "%{$search}%")
                    ->orWhere('products.sku', 'like', "%{$search}%")
                    ->orWhere('products.name', 'like', "%{$search}%");
            });
        }

        $summary = (clone $query)
            ->selectRaw('COUNT(*) as balance_lines, COUNT(DISTINCT stock_balances.product_id) as products_count, COUNT(DISTINCT stock_balances.warehouse_id) as warehouses_count, COALESCE(SUM(stock_balances.quantity), 0) as total_quantity, COALESCE(SUM(stock_balances.stock_value), 0) as stock_value')
            ->first();

        $warehouses = (clone $query)
            ->select([
                'warehouses.id',
                'warehouses.code',
                'warehouses.name',
            ])
            ->selectRaw('COUNT(DISTINCT stock_balances.product_id) as products_count, COALESCE(SUM(stock_balances.quantity), 0) as total_quantity, COALESCE(SUM(stock_balances.stock_value), 0) as stock_value')
            ->groupBy('warehouses.id', 'warehouses.code', 'warehouses.name')
            ->orderByDesc('stock_value')
            ->get()
            ->map(fn ($warehouse) => [
                'id' => $warehouse->id,
                'code' => $warehouse->code,
                'name' => $warehouse->name,
                'products_count' => (int) $warehouse->products_count,
                'total_quantity' => (float) $warehouse->total_quantity,
                'stock_value' => (float) $warehouse->stock_value,
            ]);

        $paginator = $query
            ->orderByDesc('stock_balances.stock_value')
            ->orderBy('products.name')
            ->select($this->balanceColumns())
            ->paginate(min(max((int) $request->query('per_page', 20), 1), 100));

        return ApiResponse::success('Stock value report loaded.', [
            'items' => collect($paginator->items())->map(fn ($balance) => $this->balancePayload($balance)),
            'warehouses' => $warehouses,
            'summary' => [
                'balance_lines' => (int) ($summary->balance_lines ?? 0),
                'products_count' => (int) ($summary->products_count ?? 0),
                'warehouses_count' => (int) ($summary->warehouses_count ?? 0),
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
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $warehouse = DB::table('warehouses')->where('is_active', true)->find($validated['warehouse_id']);
        $product = DB::table('products')->where('is_active', true)->find($validated['product_id']);
        abort_unless($warehouse && $product, 422, 'Active warehouse and product are required.');

        $movementDate = Carbon::parse($validated['movement_date'] ?? now());

        $result = DB::transaction(fn () => $this->applyMovement($validated, $movementDate, $request));

        [$movement, $balance] = $result;
        $movementPayload = $this->movementQuery()->select($this->movementColumns())->where('stock_movements.id', $movement->id)->first();
        $balancePayload = $this->balanceQuery()->select($this->balanceColumns())->where('stock_balances.id', $balance->id)->first();

        return ApiResponse::success('Stock movement recorded.', [
            'movement' => $this->movementPayload($movementPayload),
            'balance' => $this->balancePayload($balancePayload),
        ], 201);
    }

    public function storeTransfer(Request $request)
    {
        $this->authorizePermission($request, self::MANAGE_PERMISSION);

        $validated = $request->validate([
            'from_warehouse_id' => ['required', 'integer', 'exists:warehouses,id', 'different:to_warehouse_id'],
            'to_warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'movement_date' => ['nullable', 'date'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'reference_code' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $fromWarehouse = DB::table('warehouses')->where('is_active', true)->find($validated['from_warehouse_id']);
        $toWarehouse = DB::table('warehouses')->where('is_active', true)->find($validated['to_warehouse_id']);
        $product = DB::table('products')->where('is_active', true)->find($validated['product_id']);
        abort_unless($fromWarehouse && $toWarehouse && $product, 422, 'Active warehouses and product are required.');

        $movementDate = Carbon::parse($validated['movement_date'] ?? now());

        $result = DB::transaction(function () use ($validated, $movementDate, $request) {
            $transferCode = $this->nextTransferCode($movementDate);
            $sourceBalance = $this->balanceFor((int) $validated['from_warehouse_id'], (int) $validated['product_id']);
            $unitCost = (float) $sourceBalance->average_cost;
            abort_if((float) $sourceBalance->quantity < (float) $validated['quantity'], 409, 'Insufficient stock balance.');

            [$outMovement, $sourceBalance] = $this->applyMovement([
                'movement_type' => 'transfer_out',
                'warehouse_id' => $validated['from_warehouse_id'],
                'product_id' => $validated['product_id'],
                'quantity' => $validated['quantity'],
                'unit_cost' => $unitCost,
                'reference_code' => $transferCode,
                'notes' => $validated['notes'] ?? null,
            ], $movementDate, $request, "{$transferCode}-OUT");

            [$inMovement, $destinationBalance] = $this->applyMovement([
                'movement_type' => 'transfer_in',
                'warehouse_id' => $validated['to_warehouse_id'],
                'product_id' => $validated['product_id'],
                'quantity' => $validated['quantity'],
                'unit_cost' => $unitCost,
                'reference_code' => $transferCode,
                'notes' => $validated['notes'] ?? null,
            ], $movementDate, $request, "{$transferCode}-IN");

            return [$transferCode, $outMovement, $inMovement, $sourceBalance, $destinationBalance];
        });

        [$transferCode, $outMovement, $inMovement, $sourceBalance, $destinationBalance] = $result;

        return ApiResponse::success('Stock transfer recorded.', [
            'transfer_code' => $transferCode,
            'out_movement' => $this->movementPayload($this->movementQuery()->select($this->movementColumns())->where('stock_movements.id', $outMovement->id)->first()),
            'in_movement' => $this->movementPayload($this->movementQuery()->select($this->movementColumns())->where('stock_movements.id', $inMovement->id)->first()),
            'source_balance' => $this->balancePayload($this->balanceQuery()->select($this->balanceColumns())->where('stock_balances.id', $sourceBalance->id)->first()),
            'destination_balance' => $this->balancePayload($this->balanceQuery()->select($this->balanceColumns())->where('stock_balances.id', $destinationBalance->id)->first()),
        ], 201);
    }

    public function storeClosingCount(Request $request)
    {
        $this->authorizePermission($request, self::MANAGE_PERMISSION);

        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'movement_date' => ['nullable', 'date'],
            'counted_quantity' => ['required', 'numeric', 'min:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'reference_code' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $warehouse = DB::table('warehouses')->where('is_active', true)->find($validated['warehouse_id']);
        $product = DB::table('products')->where('is_active', true)->find($validated['product_id']);
        abort_unless($warehouse && $product, 422, 'Active warehouse and product are required.');

        $movementDate = Carbon::parse($validated['movement_date'] ?? now());

        [$movement, $balance] = DB::transaction(function () use ($validated, $movementDate, $request) {
            $currentBalance = $this->balanceFor((int) $validated['warehouse_id'], (int) $validated['product_id']);
            $currentQuantity = (float) $currentBalance->quantity;
            $countedQuantity = (float) $validated['counted_quantity'];
            $adjustment = $countedQuantity - $currentQuantity;
            $unitCost = (float) $currentBalance->average_cost > 0
                ? (float) $currentBalance->average_cost
                : (float) ($validated['unit_cost'] ?? 0);

            abort_if($adjustment > 0 && $unitCost <= 0, 422, 'Unit cost is required when the physical count adds unvalued stock.');

            return $this->applyMovement([
                'movement_type' => 'adjustment',
                'warehouse_id' => $validated['warehouse_id'],
                'product_id' => $validated['product_id'],
                'quantity' => $adjustment,
                'unit_cost' => $unitCost,
                'reference_code' => $validated['reference_code'] ?? null,
                'reference_type' => 'closing_count',
                'notes' => $validated['notes'] ?? null,
            ], $movementDate, $request);
        });

        $movementPayload = $this->movementQuery()->select($this->movementColumns())->where('stock_movements.id', $movement->id)->first();
        $balancePayload = $this->balanceQuery()->select($this->balanceColumns())->where('stock_balances.id', $balance->id)->first();

        return ApiResponse::success('Closing stock count recorded.', [
            'movement' => $this->movementPayload($movementPayload),
            'balance' => $this->balancePayload($balancePayload),
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
        $balance = $this->balanceFor((int) $validated['warehouse_id'], (int) $validated['product_id']);

        $quantity = abs((float) $validated['quantity']);
        $signedQuantity = $this->signedQuantity($validated['movement_type'], (float) $validated['quantity']);
        $oldQuantity = (float) $balance->quantity;
        $oldAverageCost = (float) $balance->average_cost;
        $requestedCost = array_key_exists('unit_cost', $validated) && $validated['unit_cost'] !== null
            ? (float) $validated['unit_cost']
            : $oldAverageCost;
        $unitCost = $requestedCost;

        if ($signedQuantity < 0) {
            abort_if($oldQuantity < abs($signedQuantity), 409, 'Insufficient stock balance.');
            $unitCost = $requestedCost > 0 ? $requestedCost : $oldAverageCost;
            $newQuantity = $oldQuantity + $signedQuantity;
            $newAverageCost = $oldAverageCost;
        } elseif ($signedQuantity > 0) {
            $newQuantity = $oldQuantity + $signedQuantity;
            $newAverageCost = $newQuantity > 0
                ? (($oldQuantity * $oldAverageCost) + ($signedQuantity * $unitCost)) / $newQuantity
                : $unitCost;
        } else {
            $newQuantity = $oldQuantity;
            $newAverageCost = $oldAverageCost;
        }

        $movement = StockMovement::create([
            'code' => $code ?? $this->nextCode($validated['movement_type'], $movementDate),
            'warehouse_id' => $validated['warehouse_id'],
            'product_id' => $validated['product_id'],
            'movement_type' => $validated['movement_type'],
            'movement_date' => $movementDate->toDateString(),
            'quantity' => $quantity,
            'signed_quantity' => $signedQuantity,
            'balance_before' => $oldQuantity,
            'balance_after' => $newQuantity,
            'unit_cost' => $unitCost,
            'total_cost' => abs($signedQuantity) * $unitCost,
            'reference_type' => $validated['reference_type'] ?? null,
            'reference_code' => $validated['reference_code'] ?? null,
            'created_by' => $request->user()?->id,
            'notes' => $validated['notes'] ?? null,
        ]);

        $balance->update([
            'quantity' => $newQuantity,
            'average_cost' => $newAverageCost,
            'stock_value' => $newQuantity * $newAverageCost,
            'last_movement_at' => now(),
        ]);

        return [$movement, $balance->refresh()];
    }

    private function balanceFor(int $warehouseId, int $productId): StockBalance
    {
        $balance = StockBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();

        if ($balance) {
            return $balance;
        }

        return StockBalance::create([
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'quantity' => 0,
            'average_cost' => 0,
            'stock_value' => 0,
        ]);
    }

    private function signedQuantity(string $movementType, float $quantity): float
    {
        return match ($movementType) {
            'issue', 'damage', 'transfer_out' => -abs($quantity),
            'transfer_in' => abs($quantity),
            'adjustment' => $quantity,
            default => abs($quantity),
        };
    }

    private function movementQuery()
    {
        return DB::table('stock_movements')
            ->join('warehouses', 'stock_movements.warehouse_id', '=', 'warehouses.id')
            ->join('products', 'stock_movements.product_id', '=', 'products.id');
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
            'warehouse_id' => $movement->warehouse_id,
            'warehouse_code' => $movement->warehouse_code,
            'warehouse_name' => $movement->warehouse_name,
            'product_id' => $movement->product_id,
            'product_sku' => $movement->product_sku,
            'product_name' => $movement->product_name,
            'unit' => $movement->unit,
            'movement_type' => $movement->movement_type,
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

    private function nextCode(string $movementType, Carbon $movementDate): string
    {
        $prefix = match ($movementType) {
            'opening' => 'OPN',
            'receive' => 'RCV',
            'issue' => 'ISS',
            'damage' => 'DMG',
            'adjustment' => 'ADJ',
            'transfer_out', 'transfer_in' => 'TRF',
            default => 'STK',
        };
        $prefix = $prefix.'-'.$movementDate->format('Ym').'-';
        $next = ((int) StockMovement::query()->where('code', 'like', "{$prefix}%")->count()) + 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function nextTransferCode(Carbon $movementDate): string
    {
        $prefix = 'TRF-'.$movementDate->format('Ym').'-';
        $next = ((int) floor(StockMovement::query()->where('code', 'like', "{$prefix}%")->count() / 2)) + 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless(in_array($permission, AppAccess::permissionsForRole($request->user()?->role), true), 403);
    }
}
