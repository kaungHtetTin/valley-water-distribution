<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\DeliveryLocation;
use App\Models\Invoice;
use App\Support\ApiResponse;
use App\Support\AppAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DeliveryController extends Controller
{
    private const VIEW_PERMISSION = 'office.deliveries.view';

    private const MANAGE_PERMISSION = 'office.deliveries.manage';

    public function meta(Request $request)
    {
        $this->authorizePermission($request, self::VIEW_PERMISSION);

        $invoices = DB::table('invoices')->join('customers', 'invoices.customer_id', '=', 'customers.id')
            ->leftJoin('deliveries', 'invoices.id', '=', 'deliveries.invoice_id')
            ->where('invoices.status', 'issued')->whereNull('deliveries.id')
            ->orderByDesc('invoices.invoice_date')->get(['invoices.id', 'invoices.code', 'invoices.total', 'customers.shop_name'])
            ->map(function ($invoice) {
                $invoice->label = "{$invoice->code} - {$invoice->shop_name}";

                return $invoice;
            });

        return ApiResponse::success('Delivery setup loaded.', [
            'invoices' => $invoices,
            'warehouses' => DB::table('warehouses')->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name'])->map(function ($item) {
                $item->label = "{$item->code} - {$item->name}";

                return $item;
            }),
            'routes' => DB::table('routes')->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name'])->map(function ($item) {
                $item->label = "{$item->code} - {$item->name}";

                return $item;
            }),
            'drivers' => DB::table('employees')->where('is_active', true)->where('employee_type', 'driver')->orderBy('name')->get(['id', 'code', 'name'])->map(function ($item) {
                $item->label = "{$item->code} - {$item->name}";

                return $item;
            }),
            'vehicles' => DB::table('vehicles')->where('is_active', true)->orderBy('code')->get(['id', 'code', 'plate_no', 'capacity', 'assigned_driver_id'])->map(function ($item) {
                $item->label = "{$item->code} - {$item->plate_no}";

                return $item;
            }),
            'statuses' => ['planned', 'assigned', 'loading', 'on_route', 'delivered', 'partially_delivered', 'failed', 'cancelled'],
        ]);
    }

    public function index(Request $request)
    {
        $this->authorizePermission($request, self::VIEW_PERMISSION);
        $query = $this->baseQuery()->latest('deliveries.planned_date')->latest('deliveries.id');
        foreach (['status', 'warehouse_id', 'route_id', 'driver_id', 'vehicle_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where("deliveries.{$filter}", $request->query($filter));
            }
        }
        if ($request->filled('date')) {
            $query->whereDate('deliveries.planned_date', $request->query('date'));
        }
        if ($request->boolean('history')) {
            $query->whereIn('deliveries.status', ['delivered', 'partially_delivered', 'failed', 'cancelled']);
        }
        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($query) use ($search) {
                $query->where('deliveries.code', 'like', "%{$search}%")->orWhere('invoices.code', 'like', "%{$search}%")
                    ->orWhere('customers.shop_name', 'like', "%{$search}%")->orWhere('employees.name', 'like', "%{$search}%")
                    ->orWhere('vehicles.plate_no', 'like', "%{$search}%")->orWhere('routes.name', 'like', "%{$search}%");
            });
        }
        $summary = (clone $query)->reorder()->selectRaw("COUNT(*) as deliveries_count, COALESCE(SUM(deliveries.total_quantity), 0) as total_quantity, SUM(CASE WHEN deliveries.status IN ('planned','assigned') THEN 1 ELSE 0 END) as pending_count, SUM(CASE WHEN deliveries.status = 'delivered' THEN 1 ELSE 0 END) as delivered_count")->first();
        $paginator = $query->select($this->columns())->paginate(min(max((int) $request->query('per_page', 20), 1), 100));

        return ApiResponse::success('Deliveries loaded.', ['items' => collect($paginator->items())->map(fn ($delivery) => $this->payload($delivery)), 'summary' => ['deliveries_count' => (int) ($summary->deliveries_count ?? 0), 'total_quantity' => (float) ($summary->total_quantity ?? 0), 'pending_count' => (int) ($summary->pending_count ?? 0), 'delivered_count' => (int) ($summary->delivered_count ?? 0)], 'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total()]]);
    }

    public function store(Request $request)
    {
        $this->authorizePermission($request, self::MANAGE_PERMISSION);
        $validated = $request->validate([
            'invoice_id' => ['required', 'integer', 'exists:invoices,id', Rule::unique('deliveries', 'invoice_id')],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'], 'route_id' => ['required', 'integer', 'exists:routes,id'],
            'driver_id' => ['required', 'integer', 'exists:employees,id'], 'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'planned_date' => ['required', 'date'], 'delivery_address' => ['nullable', 'string', 'max:500'], 'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $invoice = Invoice::with('items')->findOrFail($validated['invoice_id']);
        abort_unless($invoice->status === 'issued', 409, 'Only issued invoices can be assigned for delivery.');
        abort_unless($invoice->items->isNotEmpty(), 422, 'Invoice items are required.');
        abort_unless(DB::table('warehouses')->where('id', $validated['warehouse_id'])->where('is_active', true)->exists(), 422, 'Active warehouse is required.');
        abort_unless(DB::table('routes')->where('id', $validated['route_id'])->where('is_active', true)->exists(), 422, 'Active route is required.');
        abort_unless(DB::table('employees')->where('id', $validated['driver_id'])->where('employee_type', 'driver')->where('is_active', true)->exists(), 422, 'Active driver is required.');
        abort_unless(DB::table('vehicles')->where('id', $validated['vehicle_id'])->where('is_active', true)->exists(), 422, 'Active vehicle is required.');
        $plannedDate = Carbon::parse($validated['planned_date']);
        $delivery = DB::transaction(function () use ($invoice, $plannedDate, $request, $validated) {
            $delivery = Delivery::create($validated + ['code' => $this->nextCode($plannedDate), 'order_id' => $invoice->order_id, 'customer_id' => $invoice->customer_id, 'status' => 'assigned', 'total_quantity' => $invoice->items->sum('quantity'), 'assigned_at' => now(), 'created_by' => $request->user()?->id]);
            foreach ($invoice->items as $item) {
                $delivery->items()->create(['invoice_item_id' => $item->id, 'product_id' => $item->product_id, 'product_sku' => $item->product_sku, 'product_name' => $item->product_name, 'unit' => $item->unit, 'planned_quantity' => $item->quantity]);
            }
            if ($invoice->order_id) {
                DB::table('orders')->where('id', $invoice->order_id)->update(['status' => 'assigned', 'updated_at' => now()]);
            }

            return $delivery;
        });

        return $this->showResponse($delivery, 201, 'Delivery assigned.');
    }

    public function show(Request $request, Delivery $delivery)
    {
        $this->authorizePermission($request, self::VIEW_PERMISSION);

        return $this->showResponse($delivery);
    }

    public function driverIndex(Request $request)
    {
        $this->authorizePermission($request, 'driver.load.view');
        $driverId = $request->user()?->employee_id;
        abort_unless($driverId, 403, 'A driver employee profile is required.');

        $items = $this->baseQuery()
            ->where('deliveries.driver_id', $driverId)
            ->orderByDesc('deliveries.planned_date')
            ->orderByDesc('deliveries.id')
            ->select($this->columns())
            ->get()
            ->map(fn ($delivery) => $this->payload($delivery));

        return ApiResponse::success('Assigned deliveries loaded.', [
            'items' => $items,
            'summary' => [
                'deliveries_count' => $items->count(),
                'assigned_count' => $items->where('status', 'assigned')->count(),
                'loading_count' => $items->where('status', 'loading')->count(),
                'total_quantity' => (float) $items->sum('total_quantity'),
            ],
        ]);
    }

    public function driverShow(Request $request, Delivery $delivery)
    {
        $this->authorizeDriverDelivery($request, $delivery, 'driver.load.view');

        return $this->showResponse($delivery);
    }

    public function mobileStatusIndex(Request $request)
    {
        $scope = $this->mobileDeliveryScope($request);
        $query = $this->scopedMobileQuery($scope)
            ->orderByDesc('deliveries.planned_date')
            ->orderByDesc('deliveries.id');

        if ($request->filled('status')) {
            $query->where('deliveries.status', $request->query('status'));
        }

        $summary = (clone $query)->reorder()->selectRaw("COUNT(*) as deliveries_count, COALESCE(SUM(deliveries.total_quantity), 0) as total_quantity, SUM(CASE WHEN deliveries.status IN ('planned','assigned','loading','on_route') THEN 1 ELSE 0 END) as active_count, SUM(CASE WHEN deliveries.status IN ('delivered','partially_delivered','failed') THEN 1 ELSE 0 END) as completed_count")->first();
        $items = $query->select($this->columns())->get()->map(fn ($delivery) => $this->payload($delivery));

        return ApiResponse::success('Delivery status loaded.', [
            'app' => $scope['app'],
            'items' => $items,
            'summary' => [
                'deliveries_count' => (int) ($summary->deliveries_count ?? 0),
                'active_count' => (int) ($summary->active_count ?? 0),
                'completed_count' => (int) ($summary->completed_count ?? 0),
                'total_quantity' => (float) ($summary->total_quantity ?? 0),
            ],
        ]);
    }

    public function mobileStatusShow(Request $request, Delivery $delivery)
    {
        $scope = $this->mobileDeliveryScope($request);
        abort_unless($this->scopedMobileQuery($scope)->where('deliveries.id', $delivery->id)->exists(), 404);

        return $this->showResponse($delivery);
    }

    public function storeLocation(Request $request, Delivery $delivery)
    {
        $this->authorizeDriverDelivery($request, $delivery, 'driver.route.update');

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['nullable', 'numeric', 'between:0,10000'],
            'heading' => ['nullable', 'numeric', 'between:0,360'],
            'speed_kmh' => ['nullable', 'numeric', 'between:0,500'],
            'recorded_at' => ['nullable', 'date', 'before_or_equal:now'],
        ]);

        $location = DB::transaction(function () use ($delivery, $validated) {
            $lockedDelivery = Delivery::query()->lockForUpdate()->findOrFail($delivery->id);
            abort_unless($lockedDelivery->status === 'on_route', 409, 'Location sharing is only available during an active route.');

            return DeliveryLocation::create($validated + [
                'delivery_id' => $lockedDelivery->id,
                'driver_id' => $lockedDelivery->driver_id,
                'recorded_at' => $validated['recorded_at'] ?? now(),
            ]);
        });

        return ApiResponse::success('Driver location recorded.', [
            'location' => $this->locationPayload($location),
        ], 201);
    }

    public function liveMap(Request $request)
    {
        $this->authorizePermission($request, self::VIEW_PERMISSION);
        $deliveries = $this->baseQuery()->where('deliveries.status', 'on_route')->select($this->columns())->get();
        $deliveryIds = $deliveries->pluck('id');
        $locations = DeliveryLocation::whereIn('delivery_id', $deliveryIds)
            ->latest('recorded_at')->latest('id')->get()->unique('delivery_id')->keyBy('delivery_id');

        $items = $deliveries->map(function ($delivery) use ($locations) {
            $location = $locations->get($delivery->id);
            $trackingState = ! $location ? 'unreported' : ($location->recorded_at->gte(now()->subMinutes(5)) ? 'live' : 'stale');

            return $this->payload($delivery) + [
                'tracking_state' => $trackingState,
                'location' => $location ? $this->locationPayload($location) : null,
            ];
        });

        return ApiResponse::success('Live delivery map loaded.', [
            'items' => $items,
            'summary' => [
                'active_count' => $items->count(),
                'live_count' => $items->where('tracking_state', 'live')->count(),
                'stale_count' => $items->where('tracking_state', 'stale')->count(),
                'unreported_count' => $items->where('tracking_state', 'unreported')->count(),
            ],
            'refreshed_at' => now()->toDateTimeString(),
        ]);
    }

    public function locationHistory(Request $request, Delivery $delivery)
    {
        $this->authorizePermission($request, self::VIEW_PERMISSION);

        return ApiResponse::success('Delivery location history loaded.', [
            'delivery' => $this->payload($this->baseQuery()->select($this->columns())->where('deliveries.id', $delivery->id)->first()),
            'locations' => $delivery->locations()->latest('recorded_at')->limit(100)->get()->map(fn ($location) => $this->locationPayload($location)),
        ]);
    }

    public function confirmLoading(Request $request, Delivery $delivery)
    {
        $this->authorizeDriverDelivery($request, $delivery, 'driver.confirm.update');
        abort_unless(in_array($delivery->status, ['assigned', 'loading'], true), 409, 'Only assigned deliveries can confirm loading.');

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'distinct'],
            'items.*.loaded_quantity' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($delivery, $validated) {
            $lockedDelivery = Delivery::query()->lockForUpdate()->findOrFail($delivery->id);
            $deliveryItems = $lockedDelivery->items()->lockForUpdate()->get()->keyBy('id');
            abort_unless(count($validated['items']) === $deliveryItems->count(), 422, 'Loading quantities are required for every delivery item.');

            $loadedTotal = 0;
            foreach ($validated['items'] as $submitted) {
                $item = $deliveryItems->get((int) $submitted['id']);
                abort_unless($item, 422, 'A loading item does not belong to this delivery.');
                $loadedQuantity = (float) $submitted['loaded_quantity'];
                abort_if($loadedQuantity > (float) $item->planned_quantity, 422, 'Loaded quantity cannot exceed planned quantity.');
                $item->update(['loaded_quantity' => $loadedQuantity]);
                $loadedTotal += $loadedQuantity;
            }

            $lockedDelivery->update([
                'loaded_quantity' => $loadedTotal,
                'status' => 'loading',
                'loaded_at' => now(),
                'notes' => $validated['notes'] ?? $lockedDelivery->notes,
            ]);
            if ($lockedDelivery->order_id) {
                DB::table('orders')->where('id', $lockedDelivery->order_id)->update(['status' => 'loading', 'updated_at' => now()]);
            }
        });

        return $this->showResponse($delivery->refresh(), 200, 'Loading confirmed.');
    }

    public function startRoute(Request $request, Delivery $delivery)
    {
        $this->authorizeDriverDelivery($request, $delivery, 'driver.confirm.update');
        abort_unless($delivery->status === 'loading', 409, 'Loading must be confirmed before starting the route.');
        abort_unless((float) $delivery->loaded_quantity > 0, 409, 'At least one item must be loaded before starting the route.');

        DB::transaction(function () use ($delivery) {
            $lockedDelivery = Delivery::query()->lockForUpdate()->findOrFail($delivery->id);
            abort_unless($lockedDelivery->status === 'loading', 409, 'This delivery is no longer ready to depart.');
            $lockedDelivery->update(['status' => 'on_route', 'departed_at' => now()]);
            if ($lockedDelivery->order_id) {
                DB::table('orders')->where('id', $lockedDelivery->order_id)->update(['status' => 'delivering', 'updated_at' => now()]);
            }
        });

        return $this->showResponse($delivery->refresh(), 200, 'Delivery route started.');
    }

    public function completeDelivery(Request $request, Delivery $delivery)
    {
        $this->authorizeDriverDelivery($request, $delivery, 'driver.confirm.update');
        abort_unless($delivery->status === 'on_route', 409, 'Only an active route can be completed.');

        $validated = $request->validate([
            'status' => ['required', Rule::in(['delivered', 'partially_delivered', 'failed'])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'distinct'],
            'items.*.delivered_quantity' => ['required', 'numeric', 'min:0'],
            'items.*.returned_quantity' => ['required', 'numeric', 'min:0'],
            'items.*.damaged_quantity' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($delivery, $validated) {
            $lockedDelivery = Delivery::query()->lockForUpdate()->findOrFail($delivery->id);
            abort_unless($lockedDelivery->status === 'on_route', 409, 'This delivery is no longer active.');
            $deliveryItems = $lockedDelivery->items()->lockForUpdate()->get()->keyBy('id');
            abort_unless(count($validated['items']) === $deliveryItems->count(), 422, 'Completion quantities are required for every delivery item.');

            $deliveredTotal = 0;
            $returnedTotal = 0;
            $damagedTotal = 0;
            foreach ($validated['items'] as $submitted) {
                $item = $deliveryItems->get((int) $submitted['id']);
                abort_unless($item, 422, 'A completion item does not belong to this delivery.');
                $deliveredQuantity = (float) $submitted['delivered_quantity'];
                $returnedQuantity = (float) $submitted['returned_quantity'];
                $damagedQuantity = (float) $submitted['damaged_quantity'];
                abort_if(abs(($deliveredQuantity + $returnedQuantity + $damagedQuantity) - (float) $item->loaded_quantity) > 0.001, 422, 'Delivered, returned, and damaged quantities must equal the loaded quantity.');
                $item->update(['delivered_quantity' => $deliveredQuantity, 'returned_quantity' => $returnedQuantity, 'damaged_quantity' => $damagedQuantity]);
                $deliveredTotal += $deliveredQuantity;
                $returnedTotal += $returnedQuantity;
                $damagedTotal += $damagedQuantity;
            }

            if ($validated['status'] === 'delivered') {
                abort_unless(abs($deliveredTotal - (float) $lockedDelivery->loaded_quantity) <= 0.001 && abs($returnedTotal) <= 0.001 && abs($damagedTotal) <= 0.001, 422, 'Delivered status requires every loaded unit to be delivered.');
            } elseif ($validated['status'] === 'partially_delivered') {
                abort_unless($deliveredTotal > 0 && $deliveredTotal < (float) $lockedDelivery->loaded_quantity, 422, 'Partial delivery requires both delivered and undelivered quantities.');
            } else {
                abort_unless(abs($deliveredTotal) <= 0.001, 422, 'Failed delivery cannot contain delivered quantities.');
            }

            $lockedDelivery->update(['status' => $validated['status'], 'delivered_quantity' => $deliveredTotal, 'returned_quantity' => $returnedTotal, 'damaged_quantity' => $damagedTotal, 'completed_at' => now(), 'notes' => $validated['notes'] ?? $lockedDelivery->notes]);
            if ($lockedDelivery->order_id) {
                DB::table('orders')->where('id', $lockedDelivery->order_id)->update(['status' => $validated['status'], 'updated_at' => now()]);
            }
            DB::table('invoices')->where('id', $lockedDelivery->invoice_id)->update(['status' => $validated['status'], 'updated_at' => now()]);
        });

        return $this->showResponse($delivery->refresh(), 200, 'Delivery completion recorded.');
    }

    private function showResponse(Delivery $delivery, int $status = 200, string $message = 'Delivery loaded.')
    {
        $record = $this->baseQuery()->select($this->columns())->where('deliveries.id', $delivery->id)->first();

        return ApiResponse::success($message, ['delivery' => $this->payload($record), 'items' => $delivery->items()->orderBy('id')->get()->map(fn ($item) => ['id' => $item->id, 'product_sku' => $item->product_sku, 'product_name' => $item->product_name, 'unit' => $item->unit, 'planned_quantity' => (float) $item->planned_quantity, 'loaded_quantity' => (float) $item->loaded_quantity, 'delivered_quantity' => (float) $item->delivered_quantity, 'returned_quantity' => (float) $item->returned_quantity, 'damaged_quantity' => (float) $item->damaged_quantity])], $status);
    }

    private function baseQuery()
    {
        return DB::table('deliveries')->join('invoices', 'deliveries.invoice_id', '=', 'invoices.id')->join('customers', 'deliveries.customer_id', '=', 'customers.id')->join('warehouses', 'deliveries.warehouse_id', '=', 'warehouses.id')->join('routes', 'deliveries.route_id', '=', 'routes.id')->join('employees', 'deliveries.driver_id', '=', 'employees.id')->join('vehicles', 'deliveries.vehicle_id', '=', 'vehicles.id');
    }

    private function mobileDeliveryScope(Request $request): array
    {
        $user = $request->user();

        if ($user->role === 'Customer') {
            $this->authorizePermission($request, 'client.deliveries.view');
            abort_unless($user->customer_id, 404);

            return ['app' => 'client', 'customer_id' => $user->customer_id, 'route_id' => null];
        }

        $this->authorizePermission($request, 'sales.deliveries.view');
        $routeId = DB::table('employees')->where('id', $user->employee_id)->value('assigned_route_id');
        abort_unless($routeId, 422, 'A route must be assigned before viewing deliveries.');

        return ['app' => 'sales', 'customer_id' => null, 'route_id' => $routeId];
    }

    private function scopedMobileQuery(array $scope)
    {
        return $this->baseQuery()
            ->when($scope['app'] === 'client', fn ($query) => $query->where('deliveries.customer_id', $scope['customer_id']))
            ->when($scope['app'] === 'sales', fn ($query) => $query->where('deliveries.route_id', $scope['route_id']));
    }

    private function columns(): array
    {
        return ['deliveries.*', 'invoices.code as invoice_code', 'invoices.total as invoice_total', 'customers.code as customer_code', 'customers.shop_name', 'warehouses.code as warehouse_code', 'warehouses.name as warehouse_name', 'routes.code as route_code', 'routes.name as route_name', 'employees.code as driver_code', 'employees.name as driver_name', 'vehicles.code as vehicle_code', 'vehicles.plate_no'];
    }

    private function payload($d): array
    {
        return ['id' => $d->id, 'code' => $d->code, 'invoice_id' => $d->invoice_id, 'invoice_code' => $d->invoice_code, 'invoice_total' => (float) $d->invoice_total, 'customer_code' => $d->customer_code, 'shop_name' => $d->shop_name, 'warehouse_id' => $d->warehouse_id, 'warehouse_code' => $d->warehouse_code, 'warehouse_name' => $d->warehouse_name, 'route_id' => $d->route_id, 'route_code' => $d->route_code, 'route_name' => $d->route_name, 'driver_id' => $d->driver_id, 'driver_code' => $d->driver_code, 'driver_name' => $d->driver_name, 'vehicle_id' => $d->vehicle_id, 'vehicle_code' => $d->vehicle_code, 'plate_no' => $d->plate_no, 'planned_date' => Carbon::parse($d->planned_date)->toDateString(), 'status' => $d->status, 'total_quantity' => (float) $d->total_quantity, 'loaded_quantity' => (float) $d->loaded_quantity, 'delivered_quantity' => (float) $d->delivered_quantity, 'returned_quantity' => (float) $d->returned_quantity, 'damaged_quantity' => (float) $d->damaged_quantity, 'departed_at' => $d->departed_at ? Carbon::parse($d->departed_at)->toDateTimeString() : null, 'completed_at' => $d->completed_at ? Carbon::parse($d->completed_at)->toDateTimeString() : null, 'delivery_address' => $d->delivery_address, 'notes' => $d->notes];
    }

    private function locationPayload(DeliveryLocation $location): array
    {
        return [
            'id' => $location->id,
            'latitude' => (float) $location->latitude,
            'longitude' => (float) $location->longitude,
            'accuracy_m' => $location->accuracy_m !== null ? (float) $location->accuracy_m : null,
            'heading' => $location->heading !== null ? (float) $location->heading : null,
            'speed_kmh' => $location->speed_kmh !== null ? (float) $location->speed_kmh : null,
            'recorded_at' => $location->recorded_at->toDateTimeString(),
        ];
    }

    private function nextCode(Carbon $date): string
    {
        $prefix = 'DEL-'.$date->format('Ym').'-';
        $next = Delivery::where('code', 'like', "{$prefix}%")->count() + 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless(in_array($permission, AppAccess::permissionsForRole($request->user()?->role), true), 403);
    }

    private function authorizeDriverDelivery(Request $request, Delivery $delivery, string $permission): void
    {
        $this->authorizePermission($request, $permission);
        abort_unless($request->user()?->employee_id && (int) $request->user()->employee_id === (int) $delivery->driver_id, 404);
    }
}
