<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\DeliveryLocation;
use App\Models\DeliveryTrip;
use App\Models\Collection;
use App\Models\FinancialTransaction;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\StockMovement;
use App\Services\CustomerCreditService;
use App\Services\InventoryService;
use App\Support\ApiResponse;
use App\Support\AppAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DeliveryController extends Controller
{
    private const VIEW_PERMISSION = 'office.deliveries.view';

    private const MANAGE_PERMISSION = 'office.deliveries.manage';

    public function __construct(
        private InventoryService $inventory,
        private CustomerCreditService $customerCredit,
    ) {}

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
                $column = $filter === 'status' ? DB::raw('COALESCE(delivery_trips.status, deliveries.status)') : "deliveries.{$filter}";
                $query->where($column, $request->query($filter));
            }
        }
        if ($request->filled('date')) {
            $query->whereDate('deliveries.planned_date', $request->query('date'));
        }
        if ($request->boolean('history')) {
            $query->whereIn(DB::raw('COALESCE(delivery_trips.status, deliveries.status)'), ['delivered', 'partially_delivered', 'failed', 'cancelled']);
        }
        $search = trim((string) $request->query('search'));
        $trips = $this->tripCards($query->select($this->columns())->get(), $search);
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $page = max((int) $request->query('page', 1), 1);
        $paginator = new LengthAwarePaginator($trips->forPage($page, $perPage)->values(), $trips->count(), $perPage, $page);

        return ApiResponse::success('Delivery trips loaded.', ['items' => collect($paginator->items()), 'summary' => ['deliveries_count' => $trips->count(), 'total_quantity' => (float) $trips->sum('total_quantity'), 'pending_count' => $trips->whereIn('status', ['planned', 'assigned'])->count(), 'delivered_count' => $trips->where('status', 'delivered')->count()], 'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total()]]);
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
        $invoicesById = Invoice::with('items')->whereIn('id', $invoiceIds)->get()->keyBy('id');
        $invoices = $invoiceIds->map(fn ($id) => $invoicesById->get($id))->filter()->values();
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
            foreach ($invoices as $stopIndex => $invoice) {
                $order = $invoice->order_id ? Order::find($invoice->order_id) : null;
                $customer = $invoice->customer_id ? DB::table('customers')->find($invoice->customer_id) : null;
                $deliveryAddress = $validated['delivery_address'] ?? $invoice->delivery_address ?? $order?->delivery_address ?? $customer?->address;
                $delivery = Delivery::create([
                    'code' => $this->nextCode($plannedDate),
                    'trip_id' => $trip->id,
                    'stop_sequence' => $stopIndex + 1,
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
                    $delivery->items()->create(['invoice_item_id' => $item->id, 'product_id' => $item->product_id, 'product_sku' => $item->product_sku, 'product_name' => $item->product_name, 'unit' => $item->unit, 'item_type' => $item->item_type ?? ((float) $item->unit_price > 0 ? 'sale' : 'foc'), 'unit_price' => $item->unit_price, 'discount_amount' => $item->discount_amount, 'planned_quantity' => $item->quantity]);
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

        $records = $this->baseQuery()
            ->where('deliveries.driver_id', $driverId)
            ->orderByDesc('deliveries.planned_date')
            ->orderByDesc('deliveries.id')
            ->select($this->columns())
            ->get();
        $scope = $request->query('scope', 'tasks');
        abort_unless(in_array($scope, ['tasks', 'history'], true), 422, 'The delivery scope must be tasks or history.');
        $taskStatuses = ['assigned', 'loading', 'on_route'];
        $historyStatuses = ['delivered', 'partially_delivered', 'failed'];
        $items = $this->tripCards($records)
            ->whereIn('status', $scope === 'history' ? $historyStatuses : $taskStatuses)
            ->values();
        if ($scope === 'tasks') {
            $items = $items->sortBy(function ($item) {
                $priority = ['on_route' => 0, 'loading' => 1, 'assigned' => 2][$item['status']];

                return sprintf('%d-%s-%010d', $priority, $item['planned_date'], $item['id']);
            })->values();
        }

        return ApiResponse::success($scope === 'history' ? 'Delivery history loaded.' : 'Driver tasks loaded.', [
            'items' => $items,
            'summary' => [
                'deliveries_count' => $items->count(),
                'assigned_count' => $items->where('status', 'assigned')->count(),
                'loading_count' => $items->where('status', 'loading')->count(),
                'on_route_count' => $items->where('status', 'on_route')->count(),
                'completed_count' => $items->whereIn('status', ['delivered', 'partially_delivered', 'failed'])->count(),
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
            $active = $lockedDelivery->trip_id
                ? DeliveryTrip::whereKey($lockedDelivery->trip_id)->where('status', 'on_route')->exists()
                : $lockedDelivery->status === 'on_route';
            abort_unless($active, 409, 'Location sharing is only available during an active route.');

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
        $deliveries = $this->baseQuery()
            ->where(DB::raw('COALESCE(delivery_trips.status, deliveries.status)'), 'on_route')
            ->select($this->columns())
            ->get();
        $deliveryIds = $deliveries->pluck('id');
        $locations = DeliveryLocation::whereIn('delivery_id', $deliveryIds)
            ->latest('recorded_at')->latest('id')->get();

        $items = $deliveries
            ->groupBy(fn ($delivery) => $delivery->trip_id ? 'trip-'.$delivery->trip_id : 'delivery-'.$delivery->id)
            ->map(function ($tripDeliveries) use ($locations) {
                $deliveryIds = $tripDeliveries->pluck('id');
                $location = $locations->first(fn ($candidate) => $deliveryIds->contains($candidate->delivery_id));
                $trackingState = ! $location ? 'unreported' : ($location->recorded_at->gte(now()->subMinutes(5)) ? 'live' : 'stale');
                $card = $this->tripCards($tripDeliveries)->first();

                return $card + [
                    'shop_name' => $card['customer_summary'],
                    'delivery_address' => $tripDeliveries->pluck('delivery_address')->filter()->unique()->implode(' · '),
                    'tracking_state' => $trackingState,
                    'location' => $location ? $this->locationPayload($location) : null,
                ];
            })->values();

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
        $deliveryIds = $delivery->trip_id
            ? Delivery::where('trip_id', $delivery->trip_id)->pluck('id')
            : collect([$delivery->id]);

        return ApiResponse::success('Delivery location history loaded.', [
            'delivery' => $this->payload($this->baseQuery()->select($this->columns())->where('deliveries.id', $delivery->id)->first()),
            'locations' => DeliveryLocation::whereIn('delivery_id', $deliveryIds)
                ->latest('recorded_at')->latest('id')->limit(100)->get()
                ->map(fn ($location) => $this->locationPayload($location)),
        ]);
    }

    public function confirmLoading(Request $request, Delivery $delivery)
    {
        $this->authorizeDriverDelivery($request, $delivery, 'driver.confirm.update');
        $tripStatus = $delivery->trip_id ? DeliveryTrip::whereKey($delivery->trip_id)->value('status') : $delivery->status;
        abort_unless(in_array($tripStatus, ['assigned', 'loading'], true), 409, 'Only assigned trips can confirm loading.');

        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:500']]);

        $userId = $request->user()?->id;
        DB::transaction(function () use ($delivery, $validated, $userId) {
            $anchor = Delivery::query()->lockForUpdate()->findOrFail($delivery->id);
            $deliveries = $anchor->trip_id
                ? Delivery::query()->where('trip_id', $anchor->trip_id)->orderBy('stop_sequence')->lockForUpdate()->get()
                : collect([$anchor]);
            abort_unless($deliveries->every(fn ($item) => in_array($item->status, ['assigned', 'loading'], true)), 409, 'This trip is no longer ready for loading changes.');
            $deliveryItems = DeliveryItem::query()->whereIn('delivery_id', $deliveries->pluck('id'))->lockForUpdate()->get();
            abort_unless($deliveryItems->isNotEmpty(), 422, 'The trip does not contain any load items.');

            foreach ($deliveries as $tripDelivery) {
                $loadedTotal = 0;
                $loadedByProduct = [];
                foreach ($deliveryItems->where('delivery_id', $tripDelivery->id) as $item) {
                    abort_unless($item->product_id, 422, 'Every delivery item must reference a product before loading.');
                    $loadedQuantity = (float) $item->planned_quantity;
                    $item->update(['loaded_quantity' => $loadedQuantity]);
                    $loadedTotal += $loadedQuantity;
                    $loadedByProduct[$item->product_id] = ($loadedByProduct[$item->product_id] ?? 0) + $loadedQuantity;
                }
                $this->reconcileDeliveryIssues($tripDelivery, $loadedByProduct, $userId, Carbon::now());
                $tripDelivery->update(['loaded_quantity' => $loadedTotal, 'status' => 'loading', 'loaded_at' => now(), 'notes' => $validated['notes'] ?? $tripDelivery->notes]);
                if ($tripDelivery->order_id) {
                    DB::table('orders')->where('id', $tripDelivery->order_id)->update(['status' => 'loading', 'updated_at' => now()]);
                }
            }
            if ($anchor->trip_id) {
                DeliveryTrip::whereKey($anchor->trip_id)->update(['status' => 'loading']);
            }
        });

        return $this->showResponse($delivery->refresh(), 200, 'Trip loading confirmed.');
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
            $otherActiveTrip = $lockedDelivery->trip_id && DeliveryTrip::query()
                ->where('driver_id', $lockedDelivery->driver_id)
                ->where('status', 'on_route')
                ->whereKeyNot($lockedDelivery->trip_id)
                ->exists();
            abort_if($otherActiveTrip, 409, 'Complete the active trip before starting another trip.');
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

        $finalSaleRequest = (bool) $delivery->order_id && $request->boolean('final_sale');
        $requiresSettlement = $finalSaleRequest;

        $validated = $request->validate([
            'final_sale' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(['delivered', 'partially_delivered', 'failed'])],
            'items' => ['required', 'array'],
            'items.*.id' => ['nullable', 'integer', 'distinct'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.item_type' => ['nullable', Rule::in(['sale', 'foc'])],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'order_discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.delivered_quantity' => ['required', 'numeric', 'min:0'],
            'items.*.returned_quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.damaged_quantity' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
            'modification_note' => ['nullable', 'string', 'max:500'],
            'settlement_method' => [$requiresSettlement ? 'required_unless:status,failed' : 'nullable', Rule::in(['credit', 'cash_driver', 'cash_office', 'bank_office'])],
            'payment_reference' => ['nullable', 'string', 'max:100', 'required_if:settlement_method,bank_office'],
        ]);

        $userId = $request->user()?->id;
        DB::transaction(function () use ($delivery, $validated, $userId, $finalSaleRequest) {
            $lockedDelivery = Delivery::query()->lockForUpdate()->findOrFail($delivery->id);
            abort_unless($lockedDelivery->status === 'on_route', 409, 'This delivery is no longer active.');
            $deliveryItems = $lockedDelivery->items()->lockForUpdate()->get()->keyBy('id');
            $finalSaleMode = $finalSaleRequest;
            $orderModified = false;
            if ($finalSaleMode) {
                [$deliveryItems, $loadedByProduct, $returnedByProduct, $damagedByProduct, $orderModified] = $this->synchronizeFinalSaleItems($lockedDelivery, $deliveryItems, $validated['items'], array_key_exists('order_discount', $validated) ? (float) $validated['order_discount'] : null);
                $deliveredTotal = (float) $deliveryItems->sum('delivered_quantity');
                $returnedTotal = (float) $deliveryItems->sum('returned_quantity');
                $damagedTotal = (float) $deliveryItems->sum('damaged_quantity');
                if ($validated['status'] === 'failed') {
                    abort_unless(abs($deliveredTotal) <= 0.001, 422, 'Failed delivery cannot contain final sale quantities.');
                } else {
                    abort_unless($deliveredTotal > 0.001, 422, 'A completed final sale requires at least one Sale or FOC item.');
                }
            } else {
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
                    $returnedQuantity = (float) ($submitted['returned_quantity'] ?? 0);
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
            }

            if ($finalSaleMode) {
                $this->finalizeDeliverySale($lockedDelivery, $deliveryItems, $validated, $userId, $orderModified);
            }

            $movementDate = Carbon::now();
            if (! $lockedDelivery->trip_id) {
                $this->reconcileDeliveryIssues($lockedDelivery, $loadedByProduct, $userId, $movementDate);
            }
            $stockReferenceCode = $lockedDelivery->trip_id
                ? DeliveryTrip::where('id', $lockedDelivery->trip_id)->value('code')
                : $lockedDelivery->code;
            if (! $lockedDelivery->trip_id) {
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
                DeliveryTrip::where('id', $lockedDelivery->trip_id)->update(['status' => 'on_route']);
            }
        });

        return $this->showResponse($delivery->refresh(), 200, 'Delivery completion recorded.');
    }

    public function completeTrip(Request $request, Delivery $delivery)
    {
        $this->authorizeDriverDelivery($request, $delivery, 'driver.confirm.update');
        abort_unless($delivery->trip_id, 409, 'This delivery does not belong to a trip.');

        $userId = $request->user()?->id;
        DB::transaction(function () use ($delivery, $userId) {
            $trip = DeliveryTrip::query()->lockForUpdate()->findOrFail($delivery->trip_id);
            abort_unless($trip->status === 'on_route', 409, 'Only the active trip can be completed.');
            $stops = Delivery::query()->where('trip_id', $trip->id)->lockForUpdate()->get();
            abort_unless($stops->isNotEmpty() && $stops->every(fn ($stop) => in_array($stop->status, ['delivered', 'partially_delivered', 'failed'], true)), 409, 'Complete every customer stop before completing the trip.');

            $returnItems = DeliveryItem::query()
                ->whereIn('delivery_id', $stops->pluck('id'))
                ->where('returned_quantity', '>', 0)
                ->selectRaw('product_id, SUM(returned_quantity) as return_quantity')
                ->groupBy('product_id')
                ->get();
            $movementDate = Carbon::now();
            foreach ($returnItems as $item) {
                $quantity = (float) $item->return_quantity;
                if ($quantity <= 0.001) continue;

                $this->inventory->applyMovement([
                    'movement_type' => 'delivery_return',
                    'warehouse_id' => $trip->warehouse_id,
                    'product_id' => $item->product_id,
                    'quantity' => $quantity,
                    'reference_type' => 'delivery_trip',
                    'reference_id' => $trip->id,
                    'reference_code' => $trip->code,
                    'notes' => 'Driver confirmed trip buffer stock returned to the office warehouse.',
                ], $movementDate, $userId);
            }

            $status = $stops->every(fn ($stop) => $stop->status === 'delivered')
                ? 'delivered'
                : ($stops->every(fn ($stop) => $stop->status === 'failed') ? 'failed' : 'partially_delivered');
            $trip->update(['status' => $status]);
        });

        return $this->showResponse($delivery->refresh(), 200, 'Trip completed.');
    }

    private function synchronizeFinalSaleItems(Delivery $delivery, $currentItems, array $submittedItems, ?float $orderDiscount = null): array
    {
        $planned = $currentItems->groupBy(fn ($item) => $item->product_id.':'.$item->item_type)
            ->map(fn ($items) => round((float) $items->sum('planned_quantity'), 2))
            ->sortKeys()->all();
        $currentLoaded = $currentItems->groupBy('product_id')
            ->map(fn ($items) => (float) $items->sum('loaded_quantity'))->all();
        $normalized = collect();
        $usedIds = [];

        foreach ($submittedItems as $submitted) {
            $existing = ! empty($submitted['id']) ? $currentItems->get((int) $submitted['id']) : null;
            abort_if(! empty($submitted['id']) && ! $existing, 422, 'A final-sale item does not belong to this delivery.');
            $productId = (int) ($submitted['product_id'] ?? $existing?->product_id);
            $product = DB::table('products')->where('id', $productId)->where('is_active', true)->first();
            abort_unless($product, 422, 'Every final-sale item requires an active product.');
            abort_if($existing && (int) $existing->product_id !== $productId, 422, 'Change a product by removing its line and adding a new one.');
            $itemType = $submitted['item_type'] ?? $existing?->item_type ?? 'sale';
            abort_unless(in_array($itemType, ['sale', 'foc'], true), 422, 'Final-sale items must be Sale or FOC.');
            $delivered = (float) $submitted['delivered_quantity'];
            $damaged = (float) $submitted['damaged_quantity'];
            $unitPrice = $itemType === 'foc'
                ? 0.0
                : (array_key_exists('unit_price', $submitted)
                    ? (float) $submitted['unit_price']
                    : ((float) ($existing?->unit_price ?? 0) > 0
                        ? (float) $existing->unit_price
                        : $this->deliveryUnitPrice($delivery, $productId)));
            $discount = $itemType === 'sale' ? (float) ($submitted['discount_amount'] ?? $existing?->discount_amount ?? 0) : 0.0;
            abort_if($discount - ($delivered * $unitPrice) > 0.001, 422, 'A line discount cannot exceed its Sale amount.');
            if ($delivered <= 0.001 && $damaged <= 0.001) {
                continue;
            }
            $key = $productId.':'.$itemType;
            abort_if($normalized->contains('key', $key), 422, 'Use only one line for each product and Sale/FOC type.');
            if ($existing) {
                abort_if(in_array($existing->id, $usedIds, true), 422, 'A final-sale line can only be used once.');
                $usedIds[] = $existing->id;
            }
            $normalized->push(compact('key', 'existing', 'product', 'productId', 'itemType', 'unitPrice', 'discount', 'delivered', 'damaged'));
        }

        $originalOrderDiscount = (float) $currentItems->where('item_type', 'sale')->sum('discount_amount');
        if ($orderDiscount !== null) {
            $saleSubtotal = (float) $normalized->where('itemType', 'sale')->sum(fn ($line) => $line['delivered'] * $line['unitPrice']);
            abort_if($orderDiscount - $saleSubtotal > 0.001, 422, 'The order discount cannot exceed the final Sale amount.');
            $remainingDiscount = min(max($orderDiscount, 0), $saleSubtotal);
            $remainingSubtotal = $saleSubtotal;
            foreach ($normalized as $index => $line) {
                $lineSubtotal = $line['itemType'] === 'sale' ? $line['delivered'] * $line['unitPrice'] : 0.0;
                if ($lineSubtotal > 0 && $remainingDiscount > 0) {
                    $discount = $remainingSubtotal <= 0.001
                        ? $remainingDiscount
                        : min($lineSubtotal, round($remainingDiscount * $lineSubtotal / $remainingSubtotal, 2));
                    $line['discount'] = $discount;
                    $remainingDiscount = max($remainingDiscount - $discount, 0);
                    $remainingSubtotal = max($remainingSubtotal - $lineSubtotal, 0);
                } else {
                    $line['discount'] = 0.0;
                }
                $normalized->put($index, $line);
            }
        }

        $consumedByProduct = $normalized->groupBy('productId')
            ->map(fn ($items) => (float) $items->sum(fn ($item) => $item['delivered'] + $item['damaged']))->all();
        $availableByProduct = $currentLoaded;
        foreach ($consumedByProduct as $productId => $required) {
            $extra = $required - ($availableByProduct[$productId] ?? 0);
            if ($extra <= 0.001) {
                continue;
            }
            abort_unless($delivery->trip_id, 422, 'This product is not available in the loaded vehicle stock.');
            $donorDeliveryIds = Delivery::query()->where('trip_id', $delivery->trip_id)->where('id', '!=', $delivery->id)
                ->where('status', 'on_route')->orderBy('stop_sequence')->pluck('id');
            $donors = DeliveryItem::query()->whereIn('delivery_id', $donorDeliveryIds)->where('product_id', $productId)
                ->where('loaded_quantity', '>', 0)->orderBy('delivery_id')->orderBy('id')->lockForUpdate()->get();
            foreach ($donors as $donor) {
                if ($extra <= 0.001) break;
                $take = min($extra, (float) $donor->loaded_quantity);
                $donor->update(['loaded_quantity' => (float) $donor->loaded_quantity - $take]);
                Delivery::whereKey($donor->delivery_id)->decrement('loaded_quantity', $take);
                $extra -= $take;
                $availableByProduct[$productId] = ($availableByProduct[$productId] ?? 0) + $take;
            }
            abort_if($extra > 0.001, 422, 'The final sale exceeds the remaining product stock loaded on this vehicle.');
        }

        foreach ($currentItems as $item) {
            $item->update(['loaded_quantity' => 0, 'delivered_quantity' => 0, 'returned_quantity' => 0, 'damaged_quantity' => 0]);
        }

        $finalLines = collect();
        foreach ($normalized as $line) {
            $item = $line['existing'];
            $attributes = [
                'delivery_id' => $delivery->id,
                'product_id' => $line['productId'],
                'product_sku' => $line['product']->sku,
                'product_name' => $line['product']->name,
                'unit' => $line['product']->unit,
                'item_type' => $line['itemType'],
                'unit_price' => $line['unitPrice'],
                'discount_amount' => $line['discount'],
                'loaded_quantity' => $line['delivered'] + $line['damaged'],
                'delivered_quantity' => $line['delivered'],
                'returned_quantity' => 0,
                'damaged_quantity' => $line['damaged'],
            ];
            if ($item) {
                $item->update($attributes);
            } else {
                $item = DeliveryItem::create($attributes + ['planned_quantity' => 0]);
            }
            $finalLines->push($item);
        }

        foreach ($availableByProduct as $productId => $available) {
            $leftover = $available - ($consumedByProduct[$productId] ?? 0);
            if ($leftover <= 0.001) continue;
            $nextStop = $delivery->trip_id
                ? Delivery::query()->where('trip_id', $delivery->trip_id)->where('id', '!=', $delivery->id)
                    ->where('status', 'on_route')->orderBy('stop_sequence')->lockForUpdate()->first()
                : null;
            if ($nextStop) {
                $carrier = DeliveryItem::query()->where('delivery_id', $nextStop->id)->where('product_id', $productId)
                    ->orderByRaw("CASE WHEN item_type = 'sale' THEN 0 ELSE 1 END")->lockForUpdate()->first();
                if ($carrier) {
                    $carrier->increment('loaded_quantity', $leftover);
                } else {
                    $product = DB::table('products')->find($productId);
                    DeliveryItem::create([
                        'delivery_id' => $nextStop->id,
                        'product_id' => $productId,
                        'product_sku' => $product->sku,
                        'product_name' => $product->name,
                        'unit' => $product->unit,
                        'item_type' => 'sale',
                        'unit_price' => $this->deliveryUnitPrice($nextStop, (int) $productId),
                        'discount_amount' => 0,
                        'planned_quantity' => 0,
                        'loaded_quantity' => $leftover,
                    ]);
                }
                $nextStop->increment('loaded_quantity', $leftover);
                continue;
            }

            $carrier = $currentItems->firstWhere('product_id', (int) $productId)
                ?? $finalLines->firstWhere('product_id', (int) $productId);
            abort_unless($carrier, 422, 'Loaded stock could not be reconciled with the final sale.');
            $carrier->update([
                'loaded_quantity' => (float) $carrier->loaded_quantity + $leftover,
                'returned_quantity' => (float) $carrier->returned_quantity + $leftover,
            ]);
        }

        $items = $delivery->items()->lockForUpdate()->get()->keyBy('id');
        $delivery->update(['loaded_quantity' => (float) $items->sum('loaded_quantity')]);
        $final = $items->where('delivered_quantity', '>', 0)->groupBy(fn ($item) => $item->product_id.':'.$item->item_type)
            ->map(fn ($rows) => round((float) $rows->sum('delivered_quantity'), 2))->sortKeys()->all();
        $loadedByProduct = $items->groupBy('product_id')->map(fn ($rows) => (float) $rows->sum('loaded_quantity'))->all();
        $returnedByProduct = $items->groupBy('product_id')->map(fn ($rows) => (float) $rows->sum('returned_quantity'))->all();
        $damagedByProduct = $items->groupBy('product_id')->map(fn ($rows) => (float) $rows->sum('damaged_quantity'))->all();

        $finalOrderDiscount = (float) $normalized->where('itemType', 'sale')->sum('discount');
        return [$items, $loadedByProduct, $returnedByProduct, $damagedByProduct, $planned !== $final || ($orderDiscount !== null && abs($originalOrderDiscount - $finalOrderDiscount) > 0.001)];
    }

    private function deliveryUnitPrice(Delivery $delivery, int $productId): float
    {
        $price = $this->findDeliveryUnitPrice($delivery, $productId);
        abort_if($price === null, 422, 'No active price is configured for an added Sale item.');

        return $price;
    }

    private function findDeliveryUnitPrice(Delivery $delivery, int $productId): ?float
    {
        $priceTypeId = DB::table('orders')->where('id', $delivery->order_id)->value('price_type_id')
            ?: DB::table('price_types')->where('is_default', true)->value('id');
        $issueDate = Carbon::parse($delivery->loaded_at ?: now())->toDateString();
        $price = DB::table('product_prices')->where('product_id', $productId)->where('price_type_id', $priceTypeId)
            ->where('is_active', true)->where(fn ($query) => $query->whereNull('effective_from')->orWhereDate('effective_from', '<=', $issueDate))
            ->orderByRaw('effective_from IS NULL')->orderByDesc('effective_from')->orderByDesc('id')->value('amount');

        return $price === null ? null : (float) $price;
    }

    private function finalizeDeliverySale(Delivery $delivery, $deliveryItems, array $validated, ?int $userId, bool $orderModified): void
    {
        $order = Order::with('items')->lockForUpdate()->findOrFail($delivery->order_id);
        $invoice = Invoice::with('items')->lockForUpdate()->findOrFail($delivery->invoice_id);
        FinancialTransaction::query()
            ->where('reference_type', 'invoice')
            ->where('reference_id', $invoice->id)
            ->where('category', 'cash_sale')
            ->delete();
        $company = DB::table('companies')->oldest('id')->first();
        $defaultCreditLimit = (float) ($company?->default_customer_credit_limit ?? 500000);
        $creditDueDays = (int) ($company?->delivery_credit_due_days ?? 14);

        $customerId = $delivery->customer_id ?: $order->customer_id ?: $invoice->customer_id;
        if (! $customerId) {
            $lastCustomerId = (int) (DB::table('customers')->lockForUpdate()->max('id') ?? 0);
            $routeAreaId = DB::table('routes')->where('id', $delivery->route_id)->value('area_id');
            $customerName = $delivery->recipient_name ?: $order->recipient_name ?: $invoice->recipient_name ?: 'Delivery customer';
            $customerId = DB::table('customers')->insertGetId([
                'code' => 'CUS-'.str_pad((string) ($lastCustomerId + 1), 4, '0', STR_PAD_LEFT),
                'area_id' => $delivery->area_id ?: $order->area_id ?: $invoice->area_id ?: $routeAreaId,
                'route_id' => $delivery->route_id,
                'price_type_id' => $order->price_type_id ?: DB::table('price_types')->where('is_default', true)->value('id'),
                'shop_name' => $customerName,
                'contact_name' => $customerName,
                'phone' => $delivery->recipient_phone ?: $order->recipient_phone ?: $invoice->recipient_phone ?: 'Not provided',
                'address' => $delivery->delivery_address ?: $order->delivery_address ?: $invoice->delivery_address,
                'credit_limit' => $defaultCreditLimit,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $issueDate = Carbon::parse($delivery->loaded_at ?: now())->startOfDay();
        $existingInvoiceOutstanding = $order->payment_type === 'credit'
            ? max((float) $invoice->total - (float) DB::table('collections')->where('invoice_id', $invoice->id)->where('status', 'approved')->sum('amount'), 0)
            : 0.0;
        if ($validated['status'] === 'failed') {
            $order->update(['customer_id' => $customerId]);
            $invoice->update(['customer_id' => $customerId]);
            $delivery->update(['customer_id' => $customerId, 'settlement_method' => null, 'settlement_amount' => 0, 'settled_at' => null]);

            return;
        }

        $originalInvoiceItems = $invoice->items->keyBy('id');
        $usedInvoiceItemIds = [];
        $originalSubtotal = (float) $invoice->subtotal;
        $originalTaxTotal = (float) $invoice->tax_total;
        $order->items()->delete();
        $subtotal = 0.0;
        $discountTotal = 0.0;
        $lineTotal = 0.0;
        foreach ($deliveryItems->where('delivered_quantity', '>', 0) as $deliveryItem) {
            $quantity = (float) $deliveryItem->delivered_quantity;
            $sourceInvoiceItem = $deliveryItem->invoice_item_id
                ? $originalInvoiceItems->get((int) $deliveryItem->invoice_item_id)
                : null;
            $itemType = $deliveryItem->item_type === 'foc' ? 'foc' : 'sale';
            $unitPrice = $itemType === 'foc'
                ? 0
                : ((float) $deliveryItem->unit_price > 0
                    ? (float) $deliveryItem->unit_price
                    : ((float) ($sourceInvoiceItem?->unit_price ?? 0) > 0
                        ? (float) $sourceInvoiceItem->unit_price
                        : $this->deliveryUnitPrice($delivery, (int) $deliveryItem->product_id)));
            $itemSubtotal = round($quantity * $unitPrice, 2);
            $discount = $itemType === 'sale'
                ? min(round((float) $deliveryItem->discount_amount, 2), $itemSubtotal)
                : 0.0;
            $itemTotal = max(round($itemSubtotal - $discount, 2), 0);
            $order->items()->create([
                'product_id' => $deliveryItem->product_id,
                'product_sku' => $deliveryItem->product_sku,
                'product_name' => $deliveryItem->product_name,
                'unit' => $deliveryItem->unit,
                'item_type' => $itemType,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_amount' => $discount,
                'line_total' => $itemTotal,
            ]);
            $invoiceAttributes = [
                'product_id' => $deliveryItem->product_id,
                'product_sku' => $deliveryItem->product_sku,
                'product_name' => $deliveryItem->product_name,
                'unit' => $deliveryItem->unit,
                'item_type' => $itemType,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_amount' => $discount,
                'line_total' => $itemTotal,
            ];
            if ($sourceInvoiceItem) {
                $sourceInvoiceItem->update($invoiceAttributes);
                $invoiceItem = $sourceInvoiceItem;
            } else {
                $invoiceItem = $invoice->items()->create($invoiceAttributes);
            }
            $usedInvoiceItemIds[] = $invoiceItem->id;
            $deliveryItem->update(['invoice_item_id' => $invoiceItem->id]);
            $subtotal += $itemSubtotal;
            $discountTotal += $discount;
            $lineTotal += $itemTotal;
        }
        $invoice->items()->whereNotIn('id', $usedInvoiceItemIds)->delete();

        $taxTotal = $originalSubtotal > 0
            ? round($originalTaxTotal * ($subtotal / $originalSubtotal), 2)
            : 0.0;
        $saleTotal = round($lineTotal + $taxTotal, 2);
        $settlementMethod = $validated['status'] === 'failed' ? null : $validated['settlement_method'];
        $paymentType = $settlementMethod === 'credit' ? 'credit' : ($settlementMethod ? 'cash' : 'unsettled');
        $creditDueDate = $paymentType === 'credit'
            ? $issueDate->copy()->addDays($creditDueDays)->toDateString()
            : null;

        if ($paymentType === 'credit') {
            $customer = DB::table('customers')->where('id', $customerId)->lockForUpdate()->first();
            $outstanding = max($this->customerCredit->outstanding((int) $customerId) - $existingInvoiceOutstanding, 0);
            abort_if(
                $outstanding + $saleTotal > (float) $customer->credit_limit + 0.001,
                422,
                'Credit sale exceeds the customer credit limit.'
            );
        }

        $order->update([
            'customer_id' => $customerId,
            'payment_type' => $paymentType,
            'credit_due_date' => $creditDueDate,
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'tax_total' => $taxTotal,
            'total' => $saleTotal,
            'driver_modified' => $orderModified,
            'driver_modification_note' => $orderModified ? ($validated['modification_note'] ?? null) : null,
            'driver_modified_by' => $orderModified ? $userId : null,
            'driver_modified_at' => $orderModified ? now() : null,
        ]);
        $invoice->update([
            'customer_id' => $customerId,
            'invoice_date' => $issueDate->toDateString(),
            'due_date' => $creditDueDate,
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'tax_total' => $taxTotal,
            'total' => $saleTotal,
        ]);
        $delivery->update([
            'customer_id' => $customerId,
            'settlement_method' => $settlementMethod,
            'settlement_amount' => $saleTotal,
            'settled_at' => $settlementMethod ? now() : null,
            'order_modified' => $orderModified,
            'order_modification_note' => $orderModified ? ($validated['modification_note'] ?? null) : null,
            'order_modified_by' => $orderModified ? $userId : null,
            'order_modified_at' => $orderModified ? now() : null,
        ]);

        if ($settlementMethod && $settlementMethod !== 'credit' && $saleTotal > 0) {
            $heldByDriver = $settlementMethod === 'cash_driver';
            $paymentMethod = $settlementMethod === 'bank_office' ? 'bank' : 'cash';
            $collection = Collection::create([
                'code' => $this->nextFinanceCode('COL', 'collections', 'collection_date', now()),
                'customer_id' => $customerId,
                'invoice_id' => $invoice->id,
                'delivery_id' => $delivery->id,
                'employee_id' => $heldByDriver ? $delivery->driver_id : null,
                'collection_date' => now()->toDateString(),
                'amount' => $saleTotal,
                'payment_method' => $paymentMethod,
                'reference_no' => $validated['payment_reference'] ?? null,
                'source_app' => 'driver',
                'status' => $heldByDriver ? 'submitted' : 'approved',
                'notes' => $heldByDriver ? 'Cash sale held by driver for Office handover.' : 'Payment received directly by Office.',
                'submitted_by' => $userId,
                'reviewed_at' => $heldByDriver ? null : now(),
            ]);

            if (! $heldByDriver) {
                FinancialTransaction::create([
                    'code' => 'TXN-'.str_pad((string) (FinancialTransaction::max('id') + 1), 7, '0', STR_PAD_LEFT),
                    'transaction_date' => now()->toDateString(),
                    'book_type' => $paymentMethod,
                    'direction' => 'in',
                    'category' => 'cash_sale',
                    'amount' => $saleTotal,
                    'reference_type' => 'collection',
                    'reference_id' => $collection->id,
                    'reference_code' => $collection->code,
                    'description' => "Delivery sale {$delivery->code}",
                    'created_by' => $userId,
                ]);
            }
        }
    }

    private function nextFinanceCode(string $prefix, string $table, string $dateColumn, Carbon $date): string
    {
        $codePrefix = $prefix.'-'.$date->format('Ym').'-';
        $next = ((int) DB::table($table)->where($dateColumn, 'like', $date->format('Y-m').'%')->count()) + 1;

        return $codePrefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
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
            ? $this->baseQuery()->select($this->columns())->where('deliveries.trip_id', $record->trip_id)->orderBy('deliveries.stop_sequence')->orderBy('deliveries.id')->get()
            : collect([$record]);
        $deliveryModels = Delivery::with('items')->whereIn('id', $stopRecords->pluck('id'))->get()->keyBy('id');
        $stops = $stopRecords->map(function ($stop) use ($deliveryModels) {
            $items = $deliveryModels->get($stop->id)?->items ?? collect();

            return array_merge($this->payload($stop), [
                'order_code' => $stop->order_code,
                'order_modified' => (bool) $stop->order_modified,
                'order_modification_note' => $stop->order_modification_note,
                'items' => $items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_sku' => $item->product_sku,
                    'product_name' => $item->product_name,
                    'unit' => $item->unit,
                    'item_type' => $item->item_type,
                    'unit_price' => (float) $item->unit_price,
                    'discount_amount' => (float) $item->discount_amount,
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
        $availableProducts = $stops->where('status', 'on_route')->flatMap(fn ($stop) => $stop['items'])
            ->groupBy('product_id')->map(function ($items) use ($record) {
                $first = $items->first();

                return [
                    'id' => (int) $first['product_id'],
                    'sku' => $first['product_sku'],
                    'name' => $first['product_name'],
                    'unit' => $first['unit'],
                    'loaded_quantity' => (float) $items->sum('loaded_quantity'),
                    'unit_price' => $this->findDeliveryUnitPrice(Delivery::find($record->id), (int) $first['product_id']) ?? 0,
                ];
        })->filter(fn ($item) => $item['loaded_quantity'] > 0)->values();
        $trip = $record->trip_id ? DeliveryTrip::find($record->trip_id) : null;
        $cashHoldCollections = DB::table('collections')
            ->join('deliveries', 'collections.delivery_id', '=', 'deliveries.id')
            ->leftJoin('customers', 'collections.customer_id', '=', 'customers.id')
            ->whereIn('collections.delivery_id', $stopRecords->pluck('id'))
            ->where('collections.employee_id', $record->driver_id)
            ->where('collections.source_app', 'driver')
            ->where('collections.payment_method', 'cash')
            ->where('collections.status', 'submitted')
            ->orderBy('collections.id')
            ->get(['collections.id', 'collections.code', 'collections.amount', 'customers.shop_name as customer_name', 'deliveries.code as delivery_code'])
            ->map(fn ($collection) => [
                'id' => (int) $collection->id,
                'code' => $collection->code,
                'amount' => (float) $collection->amount,
                'customer_name' => $collection->customer_name,
                'delivery_code' => $collection->delivery_code,
            ]);

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
                'cash_hold_amount' => (float) $cashHoldCollections->sum('amount'),
                'cash_hold_collections' => $cashHoldCollections->values(),
            ],
            'stops' => $stops,
            'stock' => $stock,
            'available_products' => $availableProducts,
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
        return ['deliveries.*', 'delivery_trips.code as trip_code', 'delivery_trips.status as trip_status', 'delivery_trips.orders_count as trip_orders_count', 'delivery_trips.total_quantity as trip_total_quantity', 'orders.code as order_code', 'orders.payment_type as order_payment_type', 'orders.credit_due_date', 'invoices.code as invoice_code', 'invoices.total as invoice_total', 'customers.code as customer_code', 'customers.credit_limit as customer_credit_limit', DB::raw('COALESCE(deliveries.recipient_name, customers.shop_name) as recipient_name_display'), 'warehouses.code as warehouse_code', 'warehouses.name as warehouse_name', 'routes.code as route_code', 'routes.name as route_name', 'employees.code as driver_code', 'employees.name as driver_name', 'vehicles.code as vehicle_code', 'vehicles.plate_no'];
    }

    private function payload($d): array
    {
        return ['id' => $d->id, 'code' => $d->code, 'trip_id' => $d->trip_id, 'trip_code' => $d->trip_code, 'trip_status' => $d->trip_status ?? null, 'stop_sequence' => (int) ($d->stop_sequence ?? 1), 'trip_orders_count' => (int) ($d->trip_orders_count ?? 1), 'trip_total_quantity' => (float) ($d->trip_total_quantity ?? $d->total_quantity), 'invoice_id' => $d->invoice_id, 'invoice_code' => $d->invoice_code, 'invoice_total' => (float) $d->invoice_total, 'customer_id' => $d->customer_id, 'customer_code' => $d->customer_code, 'customer_credit_limit' => $d->customer_credit_limit !== null ? (float) $d->customer_credit_limit : null, 'shop_name' => $d->recipient_name_display, 'recipient_name' => $d->recipient_name_display, 'recipient_phone' => $d->recipient_phone, 'area_id' => $d->area_id, 'warehouse_id' => $d->warehouse_id, 'warehouse_code' => $d->warehouse_code, 'warehouse_name' => $d->warehouse_name, 'route_id' => $d->route_id, 'route_code' => $d->route_code, 'route_name' => $d->route_name, 'driver_id' => $d->driver_id, 'driver_code' => $d->driver_code, 'driver_name' => $d->driver_name, 'vehicle_id' => $d->vehicle_id, 'vehicle_code' => $d->vehicle_code, 'plate_no' => $d->plate_no, 'planned_date' => Carbon::parse($d->planned_date)->toDateString(), 'status' => $d->status, 'order_payment_type' => $d->order_payment_type, 'credit_due_date' => $d->credit_due_date, 'settlement_method' => $d->settlement_method, 'settlement_amount' => (float) $d->settlement_amount, 'total_quantity' => (float) $d->total_quantity, 'loaded_quantity' => (float) $d->loaded_quantity, 'delivered_quantity' => (float) $d->delivered_quantity, 'returned_quantity' => (float) $d->returned_quantity, 'damaged_quantity' => (float) $d->damaged_quantity, 'loaded_at' => $d->loaded_at ? Carbon::parse($d->loaded_at)->toDateTimeString() : null, 'departed_at' => $d->departed_at ? Carbon::parse($d->departed_at)->toDateTimeString() : null, 'completed_at' => $d->completed_at ? Carbon::parse($d->completed_at)->toDateTimeString() : null, 'delivery_address' => $d->delivery_address, 'notes' => $d->notes];
    }

    private function tripCards($records, string $search = '')
    {
        return collect($records)
            ->groupBy(fn ($record) => $record->trip_id ? 'trip-'.$record->trip_id : 'delivery-'.$record->id)
            ->filter(function ($group) use ($search) {
                if ($search === '') {
                    return true;
                }
                $needle = mb_strtolower($search);

                return $group->contains(function ($record) use ($needle) {
                    foreach ([$record->code, $record->trip_code, $record->invoice_code, $record->order_code, $record->recipient_name_display, $record->driver_name, $record->plate_no, $record->route_name] as $value) {
                        if (str_contains(mb_strtolower((string) $value), $needle)) {
                            return true;
                        }
                    }

                    return false;
                });
            })
            ->map(function ($group) {
                $stops = $group->sortBy(fn ($record) => sprintf('%08d-%08d', $record->stop_sequence ?? 1, $record->id));
                $first = $stops->first();
                $payload = $this->payload($first);
                $customers = $stops->pluck('recipient_name_display')->filter()->unique()->values();

                return array_merge($payload, [
                    'id' => $first->id,
                    'code' => $first->trip_code ?: $first->code,
                    'status' => $first->trip_status ?: $first->status,
                    'orders_count' => $stops->count(),
                    'stops_count' => $stops->count(),
                    'customer_summary' => $customers->count() > 1 ? $customers->first().' +'.($customers->count() - 1) : ($customers->first() ?: '-'),
                    'total_quantity' => (float) $stops->sum('total_quantity'),
                    'loaded_quantity' => (float) $stops->sum('loaded_quantity'),
                    'delivered_quantity' => (float) $stops->sum('delivered_quantity'),
                    'completed_stops' => $stops->filter(fn ($stop) => in_array($stop->status, ['delivered', 'partially_delivered', 'failed'], true))->count(),
                ]);
            })
            ->sortByDesc(fn ($trip) => $trip['planned_date'].'-'.str_pad((string) $trip['id'], 10, '0', STR_PAD_LEFT))
            ->values();
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
