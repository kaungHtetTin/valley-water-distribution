<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\DeliveryLocation;
use App\Models\DeliveryTrip;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\StockMovement;
use App\Services\InventoryService;
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

    public function __construct(private InventoryService $inventory) {}

    public function meta(Request $request)
    {
        $this->authorizePermission($request, self::VIEW_PERMISSION);

        $invoices = DB::table('invoices')->leftJoin('customers', 'invoices.customer_id', '=', 'customers.id')
            ->leftJoin('orders', 'invoices.order_id', '=', 'orders.id')
            ->leftJoin('deliveries', function ($join) {
                $join->on('invoices.id', '=', 'deliveries.invoice_id')->where('deliveries.status', '!=', 'cancelled');
            })
            ->where('invoices.status', 'issued')->whereNull('deliveries.id')
            ->orderByDesc('invoices.invoice_date')->get(['invoices.id', 'invoices.code', 'invoices.order_id', DB::raw('COALESCE(invoices.area_id, orders.area_id) as area_id'), DB::raw('COALESCE(invoices.route_id, orders.route_id) as route_id'), 'orders.code as order_code', 'invoices.total', DB::raw('COALESCE(invoices.recipient_name, orders.recipient_name, customers.shop_name) as shop_name'), DB::raw('COALESCE(invoices.recipient_phone, orders.recipient_phone, customers.phone) as recipient_phone'), DB::raw('COALESCE(invoices.delivery_address, orders.delivery_address, customers.address) as delivery_address')])
            ->map(function ($invoice) {
                $invoice->label = ($invoice->order_code ?: $invoice->code)." - {$invoice->shop_name}";
                $invoice->items = DB::table('invoice_items')->where('invoice_id', $invoice->id)
                    ->get(['product_id', 'product_sku', 'product_name', 'unit', 'quantity'])
                    ->map(fn ($item) => [
                        'product_id' => $item->product_id,
                        'product_sku' => $item->product_sku,
                        'product_name' => $item->product_name,
                        'unit' => $item->unit,
                        'quantity' => (float) $item->quantity,
                    ]);

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
                $query->where('deliveries.code', 'like', "%{$search}%")->orWhere('delivery_trips.code', 'like', "%{$search}%")->orWhere('invoices.code', 'like', "%{$search}%")
                    ->orWhere('deliveries.recipient_name', 'like', "%{$search}%")
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
            'invoice_id' => ['nullable', 'required_without:invoice_ids', 'integer', 'exists:invoices,id'],
            'invoice_ids' => ['nullable', 'required_without:invoice_id', 'array', 'min:1'],
            'invoice_ids.*' => ['required', 'integer', 'distinct', 'exists:invoices,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'], 'route_id' => ['required', 'integer', 'exists:routes,id'],
            'driver_id' => ['required', 'integer', 'exists:employees,id'], 'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'planned_date' => ['required', 'date'], 'delivery_address' => ['nullable', 'string', 'max:500'], 'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $invoiceIds = collect($validated['invoice_ids'] ?? [$validated['invoice_id']])->map(fn ($id) => (int) $id)->unique()->values();
        $invoices = Invoice::with('items')->whereIn('id', $invoiceIds)->get();
        abort_unless($invoices->count() === $invoiceIds->count(), 422, 'Every selected order must have an invoice.');
        abort_if(Delivery::whereIn('invoice_id', $invoiceIds)->where('status', '!=', 'cancelled')->exists(), 422, 'A selected order is already assigned to a delivery trip.');
        abort_unless($invoices->every(fn ($invoice) => $invoice->status === 'issued'), 409, 'Only issued orders can be assigned to a delivery trip.');
        abort_unless($invoices->every(fn ($invoice) => $invoice->items->isNotEmpty()), 422, 'Every selected order requires invoice items.');
        abort_unless(DB::table('warehouses')->where('id', $validated['warehouse_id'])->where('is_active', true)->exists(), 422, 'Active warehouse is required.');
        abort_unless(DB::table('routes')->where('id', $validated['route_id'])->where('is_active', true)->exists(), 422, 'Active route is required.');
        $invoiceRouteIds = DB::table('invoices')->leftJoin('orders', 'invoices.order_id', '=', 'orders.id')
            ->whereIn('invoices.id', $invoiceIds)->selectRaw('COALESCE(invoices.route_id, orders.route_id) as effective_route_id')->get()->pluck('effective_route_id');
        abort_unless($invoiceRouteIds->every(fn ($routeId) => (int) $routeId === (int) $validated['route_id']), 422, 'Every selected order must belong to the trip route.');
        abort_unless(DB::table('employees')->where('id', $validated['driver_id'])->where('employee_type', 'driver')->where('is_active', true)->exists(), 422, 'Active driver is required.');
        abort_unless(DB::table('vehicles')->where('id', $validated['vehicle_id'])->where('is_active', true)->exists(), 422, 'Active vehicle is required.');
        $plannedDate = Carbon::parse($validated['planned_date']);
        [$trip, $deliveries] = DB::transaction(function () use ($invoices, $plannedDate, $request, $validated) {
            $trip = DeliveryTrip::create([
                'code' => $this->nextTripCode($plannedDate),
                'warehouse_id' => $validated['warehouse_id'],
                'route_id' => $validated['route_id'],
                'driver_id' => $validated['driver_id'],
                'vehicle_id' => $validated['vehicle_id'],
                'planned_date' => $plannedDate,
                'status' => 'assigned',
                'orders_count' => $invoices->count(),
                'total_quantity' => $invoices->sum(fn ($invoice) => $invoice->items->sum('quantity')),
                'notes' => $validated['notes'] ?? null,
                'created_by' => $request->user()?->id,
            ]);
            $deliveries = collect();
            foreach ($invoices as $invoice) {
                $order = $invoice->order_id ? Order::find($invoice->order_id) : null;
                $customer = $invoice->customer_id ? DB::table('customers')->find($invoice->customer_id) : null;
                $deliveryAddress = $validated['delivery_address'] ?? $invoice->delivery_address ?? $order?->delivery_address ?? $customer?->address;
                $delivery = Delivery::create([
                    'code' => $this->nextCode($plannedDate),
                    'trip_id' => $trip->id,
                    'invoice_id' => $invoice->id,
                    'order_id' => $invoice->order_id,
                    'customer_id' => $invoice->customer_id,
                    'area_id' => $invoice->area_id ?? $order?->area_id ?? $customer?->area_id,
                    'warehouse_id' => $validated['warehouse_id'],
                    'route_id' => $validated['route_id'],
                    'driver_id' => $validated['driver_id'],
                    'vehicle_id' => $validated['vehicle_id'],
                    'recipient_name' => $invoice->recipient_name ?? $order?->recipient_name ?? $customer?->shop_name,
                    'recipient_phone' => $invoice->recipient_phone ?? $order?->recipient_phone ?? $customer?->phone,
                    'planned_date' => $plannedDate,
                    'status' => 'assigned',
                    'total_quantity' => $invoice->items->sum('quantity'),
                    'assigned_at' => now(),
                    'delivery_address' => $deliveryAddress,
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => $request->user()?->id,
                ]);
                foreach ($invoice->items as $item) {
                    $delivery->items()->create(['invoice_item_id' => $item->id, 'product_id' => $item->product_id, 'product_sku' => $item->product_sku, 'product_name' => $item->product_name, 'unit' => $item->unit, 'planned_quantity' => $item->quantity]);
                }
                if ($invoice->order_id) {
                    DB::table('orders')->where('id', $invoice->order_id)->update(['status' => 'assigned', 'updated_at' => now()]);
                }
                $deliveries->push($delivery);
            }

            return [$trip, $deliveries];
        });

        $response = $this->showResponse($deliveries->first(), 201, 'Delivery trip created.');
        $response->setData(array_replace_recursive($response->getData(true), ['data' => ['trip' => [
            'id' => $trip->id,
            'code' => $trip->code,
            'orders_count' => $trip->orders_count,
            'total_quantity' => (float) $trip->total_quantity,
        ]]]));

        return $response;
    }

    public function show(Request $request, Delivery $delivery)
    {
        $this->authorizePermission($request, self::VIEW_PERMISSION);

        return $this->showResponse($delivery);
    }

    public function updateTrip(Request $request, Delivery $delivery)
    {
        $this->authorizePermission($request, self::MANAGE_PERMISSION);
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'route_id' => ['required', 'integer', 'exists:routes,id'],
            'driver_id' => ['required', 'integer', 'exists:employees,id'],
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'planned_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        abort_unless(DB::table('warehouses')->where('id', $validated['warehouse_id'])->where('is_active', true)->exists(), 422, 'Active warehouse is required.');
        abort_unless(DB::table('routes')->where('id', $validated['route_id'])->where('is_active', true)->exists(), 422, 'Active route is required.');
        abort_unless(DB::table('employees')->where('id', $validated['driver_id'])->where('employee_type', 'driver')->where('is_active', true)->exists(), 422, 'Active driver is required.');
        abort_unless(DB::table('vehicles')->where('id', $validated['vehicle_id'])->where('is_active', true)->exists(), 422, 'Active vehicle is required.');

        DB::transaction(function () use ($delivery, $validated) {
            $deliveries = Delivery::query()
                ->when($delivery->trip_id, fn ($query) => $query->where('trip_id', $delivery->trip_id), fn ($query) => $query->whereKey($delivery->id))
                ->lockForUpdate()
                ->get();
            abort_unless($deliveries->isNotEmpty() && $deliveries->every(fn ($item) => in_array($item->status, ['planned', 'assigned'], true) && (float) $item->loaded_quantity === 0.0), 409, 'Only a trip that has not started loading can be edited.');

            $routeIds = DB::table('invoices')->leftJoin('orders', 'invoices.order_id', '=', 'orders.id')
                ->whereIn('invoices.id', $deliveries->pluck('invoice_id'))
                ->selectRaw('COALESCE(invoices.route_id, orders.route_id) as route_id')->pluck('route_id');
            abort_unless($routeIds->every(fn ($routeId) => (int) $routeId === (int) $validated['route_id']), 422, 'The selected route must match every order in this trip.');

            $shared = [
                'warehouse_id' => $validated['warehouse_id'],
                'route_id' => $validated['route_id'],
                'driver_id' => $validated['driver_id'],
                'vehicle_id' => $validated['vehicle_id'],
                'planned_date' => Carbon::parse($validated['planned_date'])->toDateString(),
                'notes' => $validated['notes'] ?? null,
                'updated_at' => now(),
            ];
            Delivery::whereIn('id', $deliveries->pluck('id'))->update($shared);
            if ($delivery->trip_id) {
                DeliveryTrip::whereKey($delivery->trip_id)->update($shared);
            }
        });

        return $this->showResponse($delivery->fresh(), 200, 'Delivery trip updated.');
    }

    public function cancelTrip(Request $request, Delivery $delivery)
    {
        $this->authorizePermission($request, self::MANAGE_PERMISSION);

        DB::transaction(function () use ($delivery) {
            $deliveries = Delivery::query()
                ->when($delivery->trip_id, fn ($query) => $query->where('trip_id', $delivery->trip_id), fn ($query) => $query->whereKey($delivery->id))
                ->lockForUpdate()
                ->get();
            abort_unless($deliveries->isNotEmpty() && $deliveries->every(fn ($item) => in_array($item->status, ['planned', 'assigned'], true) && (float) $item->loaded_quantity === 0.0), 409, 'Only a trip that has not started loading can be cancelled.');

            Delivery::whereIn('id', $deliveries->pluck('id'))->update(['status' => 'cancelled', 'completed_at' => now(), 'updated_at' => now()]);
            if ($delivery->trip_id) {
                DeliveryTrip::whereKey($delivery->trip_id)->update(['status' => 'cancelled', 'updated_at' => now()]);
            }
            DB::table('orders')->whereIn('id', $deliveries->pluck('order_id')->filter())->update(['status' => 'invoiced', 'updated_at' => now()]);
        });

        return $this->showResponse($delivery->fresh(), 200, 'Delivery trip cancelled.');
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

        $userId = $request->user()?->id;
        DB::transaction(function () use ($delivery, $validated, $userId) {
            $lockedDelivery = Delivery::query()->lockForUpdate()->findOrFail($delivery->id);
            abort_unless(in_array($lockedDelivery->status, ['assigned', 'loading'], true), 409, 'This delivery is no longer ready for loading changes.');
            $deliveryItems = $lockedDelivery->items()->lockForUpdate()->get()->keyBy('id');
            abort_unless(count($validated['items']) === $deliveryItems->count(), 422, 'Loading quantities are required for every delivery item.');

            $loadedTotal = 0;
            $loadedByProduct = [];
            foreach ($validated['items'] as $submitted) {
                $item = $deliveryItems->get((int) $submitted['id']);
                abort_unless($item, 422, 'A loading item does not belong to this delivery.');
                abort_unless($item->product_id, 422, 'Every delivery item must reference a product before loading.');
                $loadedQuantity = (float) $submitted['loaded_quantity'];
                abort_if($loadedQuantity > (float) $item->planned_quantity, 422, 'Loaded quantity cannot exceed planned quantity.');
                $item->update(['loaded_quantity' => $loadedQuantity]);
                $loadedTotal += $loadedQuantity;
                $loadedByProduct[$item->product_id] = ($loadedByProduct[$item->product_id] ?? 0) + $loadedQuantity;
            }

            $this->reconcileDeliveryIssues($lockedDelivery, $loadedByProduct, $userId, Carbon::now());

            $lockedDelivery->update([
                'loaded_quantity' => $loadedTotal,
                'status' => 'loading',
                'loaded_at' => now(),
                'notes' => $validated['notes'] ?? $lockedDelivery->notes,
            ]);
            if ($lockedDelivery->order_id) {
                DB::table('orders')->where('id', $lockedDelivery->order_id)->update(['status' => 'loading', 'updated_at' => now()]);
            }
            if ($lockedDelivery->trip_id) {
                DeliveryTrip::where('id', $lockedDelivery->trip_id)->update(['status' => 'loading']);
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
            $tripDeliveries = $lockedDelivery->trip_id
                ? Delivery::query()->where('trip_id', $lockedDelivery->trip_id)->lockForUpdate()->get()
                : collect([$lockedDelivery]);
            abort_unless($tripDeliveries->every(fn ($item) => $item->status === 'loading' && (float) $item->loaded_quantity > 0), 409, 'Every order in the trip must be loaded before departure.');
            foreach ($tripDeliveries as $tripDelivery) {
                $tripDelivery->update(['status' => 'on_route', 'departed_at' => now()]);
                if ($tripDelivery->order_id) {
                    DB::table('orders')->where('id', $tripDelivery->order_id)->update(['status' => 'delivering', 'updated_at' => now()]);
                }
            }
            if ($lockedDelivery->trip_id) {
                DeliveryTrip::where('id', $lockedDelivery->trip_id)->update(['status' => 'on_route']);
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

        $userId = $request->user()?->id;
        DB::transaction(function () use ($delivery, $validated, $userId) {
            $lockedDelivery = Delivery::query()->lockForUpdate()->findOrFail($delivery->id);
            abort_unless($lockedDelivery->status === 'on_route', 409, 'This delivery is no longer active.');
            $deliveryItems = $lockedDelivery->items()->lockForUpdate()->get()->keyBy('id');
            abort_unless(count($validated['items']) === $deliveryItems->count(), 422, 'Completion quantities are required for every delivery item.');

            $deliveredTotal = 0;
            $returnedTotal = 0;
            $damagedTotal = 0;
            $loadedByProduct = [];
            $returnedByProduct = [];
            $damagedByProduct = [];
            foreach ($validated['items'] as $submitted) {
                $item = $deliveryItems->get((int) $submitted['id']);
                abort_unless($item, 422, 'A completion item does not belong to this delivery.');
                abort_unless($item->product_id, 422, 'Every delivery item must reference a product before completion.');
                $deliveredQuantity = (float) $submitted['delivered_quantity'];
                $returnedQuantity = (float) $submitted['returned_quantity'];
                $damagedQuantity = (float) $submitted['damaged_quantity'];
                abort_if(abs(($deliveredQuantity + $returnedQuantity + $damagedQuantity) - (float) $item->loaded_quantity) > 0.001, 422, 'Delivered, returned, and damaged quantities must equal the loaded quantity.');
                $item->update(['delivered_quantity' => $deliveredQuantity, 'returned_quantity' => $returnedQuantity, 'damaged_quantity' => $damagedQuantity]);
                $deliveredTotal += $deliveredQuantity;
                $returnedTotal += $returnedQuantity;
                $damagedTotal += $damagedQuantity;
                $loadedByProduct[$item->product_id] = ($loadedByProduct[$item->product_id] ?? 0) + (float) $item->loaded_quantity;
                $returnedByProduct[$item->product_id] = ($returnedByProduct[$item->product_id] ?? 0) + $returnedQuantity;
                $damagedByProduct[$item->product_id] = ($damagedByProduct[$item->product_id] ?? 0) + $damagedQuantity;
            }

            if ($validated['status'] === 'delivered') {
                abort_unless(abs($deliveredTotal - (float) $lockedDelivery->loaded_quantity) <= 0.001 && abs($returnedTotal) <= 0.001 && abs($damagedTotal) <= 0.001, 422, 'Delivered status requires every loaded unit to be delivered.');
            } elseif ($validated['status'] === 'partially_delivered') {
                abort_unless($deliveredTotal > 0 && $deliveredTotal < (float) $lockedDelivery->loaded_quantity, 422, 'Partial delivery requires both delivered and undelivered quantities.');
            } else {
                abort_unless(abs($deliveredTotal) <= 0.001, 422, 'Failed delivery cannot contain delivered quantities.');
            }

            $movementDate = Carbon::now();
            $this->reconcileDeliveryIssues($lockedDelivery, $loadedByProduct, $userId, $movementDate);
            $stockReferenceCode = $lockedDelivery->trip_id
                ? DeliveryTrip::where('id', $lockedDelivery->trip_id)->value('code')
                : $lockedDelivery->code;
            foreach ($returnedByProduct as $productId => $quantity) {
                if ($quantity > 0.001) {
                    $this->inventory->applyMovement([
                        'movement_type' => 'delivery_return',
                        'warehouse_id' => $lockedDelivery->warehouse_id,
                        'product_id' => $productId,
                        'quantity' => $quantity,
                        'reference_type' => 'delivery',
                        'reference_id' => $lockedDelivery->id,
                        'reference_code' => $stockReferenceCode,
                        'notes' => 'Good stock returned after delivery completion.',
                    ], $movementDate, $userId);
                }
            }
            foreach ($damagedByProduct as $productId => $quantity) {
                if ($quantity > 0.001) {
                    $this->inventory->applyMovement([
                        'movement_type' => 'delivery_damage',
                        'warehouse_id' => $lockedDelivery->warehouse_id,
                        'product_id' => $productId,
                        'quantity' => $quantity,
                        'signed_quantity' => 0,
                        'reference_type' => 'delivery',
                        'reference_id' => $lockedDelivery->id,
                        'reference_code' => $stockReferenceCode,
                        'notes' => 'Damage reported at delivery completion; stock was issued during loading.',
                    ], $movementDate, $userId);
                }
            }

            $lockedDelivery->update(['status' => $validated['status'], 'delivered_quantity' => $deliveredTotal, 'returned_quantity' => $returnedTotal, 'damaged_quantity' => $damagedTotal, 'completed_at' => now(), 'notes' => $validated['notes'] ?? $lockedDelivery->notes]);
            if ($lockedDelivery->order_id) {
                DB::table('orders')->where('id', $lockedDelivery->order_id)->update(['status' => $validated['status'], 'updated_at' => now()]);
            }
            DB::table('invoices')->where('id', $lockedDelivery->invoice_id)->update(['status' => $validated['status'], 'updated_at' => now()]);
            if ($lockedDelivery->trip_id) {
                $tripDeliveries = Delivery::where('trip_id', $lockedDelivery->trip_id)->get();
                if ($tripDeliveries->every(fn ($item) => in_array($item->status, ['delivered', 'partially_delivered', 'failed', 'cancelled'], true))) {
                    $tripStatus = $tripDeliveries->every(fn ($item) => $item->status === 'delivered') ? 'delivered'
                        : ($tripDeliveries->every(fn ($item) => $item->status === 'failed') ? 'failed' : 'partially_delivered');
                    DeliveryTrip::where('id', $lockedDelivery->trip_id)->update(['status' => $tripStatus]);
                }
            }
        });

        return $this->showResponse($delivery->refresh(), 200, 'Delivery completion recorded.');
    }

    private function reconcileDeliveryIssues(Delivery $delivery, array $loadedByProduct, ?int $userId, Carbon $movementDate): void
    {
        $referenceCode = $delivery->trip_id
            ? DeliveryTrip::where('id', $delivery->trip_id)->value('code')
            : $delivery->code;
        foreach ($loadedByProduct as $productId => $loadedQuantity) {
            $netSignedQuantity = (float) StockMovement::query()
                ->where('reference_type', 'delivery')
                ->where('reference_id', $delivery->id)
                ->where('warehouse_id', $delivery->warehouse_id)
                ->where('product_id', $productId)
                ->whereIn('movement_type', ['delivery_issue', 'delivery_issue_reversal'])
                ->sum('signed_quantity');
            $currentlyIssued = -$netSignedQuantity;
            $difference = (float) $loadedQuantity - $currentlyIssued;

            if ($difference > 0.001) {
                $this->inventory->applyMovement([
                    'movement_type' => 'delivery_issue',
                    'warehouse_id' => $delivery->warehouse_id,
                    'product_id' => $productId,
                    'quantity' => $difference,
                    'reference_type' => 'delivery',
                    'reference_id' => $delivery->id,
                    'reference_code' => $referenceCode,
                    'notes' => 'Automatic stock issue for confirmed delivery loading.',
                ], $movementDate, $userId);
            } elseif ($difference < -0.001) {
                $this->inventory->applyMovement([
                    'movement_type' => 'delivery_issue_reversal',
                    'warehouse_id' => $delivery->warehouse_id,
                    'product_id' => $productId,
                    'quantity' => abs($difference),
                    'reference_type' => 'delivery',
                    'reference_id' => $delivery->id,
                    'reference_code' => $referenceCode,
                    'notes' => 'Automatic reversal after reducing a confirmed delivery load.',
                ], $movementDate, $userId);
            }
        }
    }

    private function showResponse(Delivery $delivery, int $status = 200, string $message = 'Delivery loaded.')
    {
        $record = $this->baseQuery()->select($this->columns())->where('deliveries.id', $delivery->id)->first();

        $stopRecords = $record->trip_id
            ? $this->baseQuery()->select($this->columns())->where('deliveries.trip_id', $record->trip_id)->orderBy('deliveries.id')->get()
            : collect([$record]);
        $deliveryModels = Delivery::with('items')->whereIn('id', $stopRecords->pluck('id'))->get()->keyBy('id');
        $stops = $stopRecords->map(function ($stop) use ($deliveryModels) {
            $items = $deliveryModels->get($stop->id)?->items ?? collect();

            return array_merge($this->payload($stop), [
                'order_code' => $stop->order_code,
                'items' => $items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_sku' => $item->product_sku,
                    'product_name' => $item->product_name,
                    'unit' => $item->unit,
                    'planned_quantity' => (float) $item->planned_quantity,
                    'loaded_quantity' => (float) $item->loaded_quantity,
                    'delivered_quantity' => (float) $item->delivered_quantity,
                    'returned_quantity' => (float) $item->returned_quantity,
                    'damaged_quantity' => (float) $item->damaged_quantity,
                ])->values(),
            ]);
        });
        $stock = $stops->flatMap(fn ($stop) => $stop['items'])->groupBy('product_id')->map(function ($items) {
            $first = $items->first();

            return [
                'product_id' => (int) $first['product_id'],
                'product_sku' => $first['product_sku'],
                'product_name' => $first['product_name'],
                'unit' => $first['unit'],
                'planned_quantity' => (float) $items->sum('planned_quantity'),
                'loaded_quantity' => (float) $items->sum('loaded_quantity'),
                'delivered_quantity' => (float) $items->sum('delivered_quantity'),
                'returned_quantity' => (float) $items->sum('returned_quantity'),
                'damaged_quantity' => (float) $items->sum('damaged_quantity'),
            ];
        })->values();
        $trip = $record->trip_id ? DeliveryTrip::find($record->trip_id) : null;

        return ApiResponse::success($message, [
            'delivery' => $this->payload($record),
            'items' => $stops->firstWhere('id', $delivery->id)['items'] ?? [],
            'trip' => [
                'id' => (int) ($trip?->id ?? $record->id),
                'code' => $trip?->code ?? $record->code,
                'status' => $trip?->status ?? $record->status,
                'orders_count' => (int) ($trip?->orders_count ?? $stops->count()),
                'total_quantity' => (float) ($trip?->total_quantity ?? $record->total_quantity),
                'planned_date' => Carbon::parse($trip?->planned_date ?? $record->planned_date)->toDateString(),
            ],
            'stops' => $stops,
            'stock' => $stock,
        ], $status);
    }

    private function baseQuery()
    {
        return DB::table('deliveries')->leftJoin('delivery_trips', 'deliveries.trip_id', '=', 'delivery_trips.id')->join('invoices', 'deliveries.invoice_id', '=', 'invoices.id')->leftJoin('orders', 'deliveries.order_id', '=', 'orders.id')->leftJoin('customers', 'deliveries.customer_id', '=', 'customers.id')->join('warehouses', 'deliveries.warehouse_id', '=', 'warehouses.id')->join('routes', 'deliveries.route_id', '=', 'routes.id')->join('employees', 'deliveries.driver_id', '=', 'employees.id')->join('vehicles', 'deliveries.vehicle_id', '=', 'vehicles.id');
    }

    private function mobileDeliveryScope(Request $request): array
    {
        $user = $request->user();
        abort_unless($user->role === 'Customer', 403);
        $this->authorizePermission($request, 'client.deliveries.view');
        abort_unless($user->customer_id, 404);

        return ['app' => 'client', 'customer_id' => $user->customer_id, 'route_id' => null];
    }

    private function scopedMobileQuery(array $scope)
    {
        return $this->baseQuery()
            ->where('deliveries.customer_id', $scope['customer_id']);
    }

    private function columns(): array
    {
        return ['deliveries.*', 'delivery_trips.code as trip_code', 'delivery_trips.orders_count as trip_orders_count', 'delivery_trips.total_quantity as trip_total_quantity', 'orders.code as order_code', 'invoices.code as invoice_code', 'invoices.total as invoice_total', 'customers.code as customer_code', DB::raw('COALESCE(deliveries.recipient_name, customers.shop_name) as recipient_name_display'), 'warehouses.code as warehouse_code', 'warehouses.name as warehouse_name', 'routes.code as route_code', 'routes.name as route_name', 'employees.code as driver_code', 'employees.name as driver_name', 'vehicles.code as vehicle_code', 'vehicles.plate_no'];
    }

    private function payload($d): array
    {
        return ['id' => $d->id, 'code' => $d->code, 'trip_id' => $d->trip_id, 'trip_code' => $d->trip_code, 'trip_orders_count' => (int) ($d->trip_orders_count ?? 1), 'trip_total_quantity' => (float) ($d->trip_total_quantity ?? $d->total_quantity), 'invoice_id' => $d->invoice_id, 'invoice_code' => $d->invoice_code, 'invoice_total' => (float) $d->invoice_total, 'customer_code' => $d->customer_code, 'shop_name' => $d->recipient_name_display, 'recipient_name' => $d->recipient_name_display, 'recipient_phone' => $d->recipient_phone, 'area_id' => $d->area_id, 'warehouse_id' => $d->warehouse_id, 'warehouse_code' => $d->warehouse_code, 'warehouse_name' => $d->warehouse_name, 'route_id' => $d->route_id, 'route_code' => $d->route_code, 'route_name' => $d->route_name, 'driver_id' => $d->driver_id, 'driver_code' => $d->driver_code, 'driver_name' => $d->driver_name, 'vehicle_id' => $d->vehicle_id, 'vehicle_code' => $d->vehicle_code, 'plate_no' => $d->plate_no, 'planned_date' => Carbon::parse($d->planned_date)->toDateString(), 'status' => $d->status, 'total_quantity' => (float) $d->total_quantity, 'loaded_quantity' => (float) $d->loaded_quantity, 'delivered_quantity' => (float) $d->delivered_quantity, 'returned_quantity' => (float) $d->returned_quantity, 'damaged_quantity' => (float) $d->damaged_quantity, 'departed_at' => $d->departed_at ? Carbon::parse($d->departed_at)->toDateTimeString() : null, 'completed_at' => $d->completed_at ? Carbon::parse($d->completed_at)->toDateTimeString() : null, 'delivery_address' => $d->delivery_address, 'notes' => $d->notes];
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

    private function nextTripCode(Carbon $date): string
    {
        $prefix = 'TRIP-'.$date->format('Ym').'-';
        $next = DeliveryTrip::where('code', 'like', "{$prefix}%")->count() + 1;

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
