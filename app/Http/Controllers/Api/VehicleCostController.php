<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialTransaction;
use App\Models\VehicleCost;
use App\Support\ApiResponse;
use App\Support\AppAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class VehicleCostController extends Controller
{
    private const TYPES = ['fuel', 'maintenance', 'insurance', 'license', 'engine_oil', 'tyre', 'other'];

    public function meta(Request $request)
    {
        $this->authorizePermission($request, 'office.vehicle-costs.view');

        return ApiResponse::success('Vehicle cost setup loaded.', [
            'vehicles' => DB::table('vehicles')->orderBy('code')->get(['id', 'code', 'plate_no', 'make', 'model', 'is_active'])->map(function ($item) {
                $item->label = "{$item->code} - {$item->plate_no}".($item->is_active ? '' : ' (Inactive)');

                return $item;
            }),
            'deliveries' => DB::table('deliveries')->join('vehicles', 'deliveries.vehicle_id', '=', 'vehicles.id')->orderByDesc('planned_date')->limit(500)->get(['deliveries.id', 'deliveries.code', 'deliveries.vehicle_id', 'deliveries.planned_date', 'vehicles.code as vehicle_code'])->map(function ($item) {
                $item->label = "{$item->code} - {$item->vehicle_code}";

                return $item;
            }),
            'routes' => DB::table('routes')->orderBy('name')->get(['id', 'code', 'name'])->map(function ($item) {
                $item->label = "{$item->code} - {$item->name}";

                return $item;
            }),
            'drivers' => DB::table('employees')->where('employee_type', 'driver')->orderBy('name')->get(['id', 'code', 'name'])->map(function ($item) {
                $item->label = "{$item->code} - {$item->name}";

                return $item;
            }),
            'customers' => DB::table('customers')->orderBy('shop_name')->get(['id', 'code', 'shop_name'])->map(function ($item) {
                $item->label = "{$item->code} - {$item->shop_name}";

                return $item;
            }),
            'employees' => DB::table('employees')->orderBy('name')->get(['id', 'code', 'name'])->map(function ($item) {
                $item->label = "{$item->code} - {$item->name}";

                return $item;
            }),
            'cost_types' => self::TYPES,
            'delivery_statuses' => ['planned', 'assigned', 'loading', 'on_route', 'delivered', 'partially_delivered', 'failed', 'cancelled'],
        ]);
    }

    public function index(Request $request)
    {
        $this->authorizePermission($request, 'office.vehicle-costs.view');
        $query = $this->query()->latest('vehicle_costs.cost_date')->latest('vehicle_costs.id');
        foreach (['vehicle_id', 'cost_type', 'record_type', 'status', 'payment_method', 'source_app', 'delivery_id', 'employee_id', 'issue_severity'] as $filter) {
            if ($request->filled($filter)) {
                $query->where("vehicle_costs.{$filter}", $request->query($filter));
            }
        }
        if ($request->query('has_delivery') === 'yes') {
            $query->whereNotNull('vehicle_costs.delivery_id');
        } elseif ($request->query('has_delivery') === 'no') {
            $query->whereNull('vehicle_costs.delivery_id');
        }
        if ($request->filled('date_from')) {
            $query->whereDate('vehicle_costs.cost_date', '>=', $request->query('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('vehicle_costs.cost_date', '<=', $request->query('date_to'));
        }
        if ($request->filled('amount_min')) {
            $query->where('vehicle_costs.amount', '>=', $request->query('amount_min'));
        }
        if ($request->filled('amount_max')) {
            $query->where('vehicle_costs.amount', '<=', $request->query('amount_max'));
        }
        if ($request->filled('odometer_min')) {
            $query->where('vehicle_costs.odometer_km', '>=', $request->query('odometer_min'));
        }
        if ($request->filled('odometer_max')) {
            $query->where('vehicle_costs.odometer_km', '<=', $request->query('odometer_max'));
        }
        if ($request->filled('vendor')) {
            $query->where('vehicle_costs.vendor', 'like', '%'.trim((string) $request->query('vendor')).'%');
        }
        if ($request->filled('reference_no')) {
            $query->where('vehicle_costs.reference_no', 'like', '%'.trim((string) $request->query('reference_no')).'%');
        }
        if ($search = trim((string) $request->query('search'))) {
            $query->where(fn ($q) => $q->where('vehicle_costs.code', 'like', "%{$search}%")
                ->orWhere('vehicle_costs.description', 'like', "%{$search}%")
                ->orWhere('vehicle_costs.vendor', 'like', "%{$search}%")
                ->orWhere('vehicle_costs.reference_no', 'like', "%{$search}%")
                ->orWhere('vehicles.code', 'like', "%{$search}%")
                ->orWhere('vehicles.plate_no', 'like', "%{$search}%")
                ->orWhere('vehicles.make', 'like', "%{$search}%")
                ->orWhere('vehicles.model', 'like', "%{$search}%")
                ->orWhere('deliveries.code', 'like', "%{$search}%")
                ->orWhere('employees.name', 'like', "%{$search}%"));
        }
        $summary = (clone $query)->reorder()->selectRaw("COUNT(*) records_count, COALESCE(SUM(vehicle_costs.amount),0) total_amount, COALESCE(SUM(CASE WHEN vehicle_costs.status='approved' THEN vehicle_costs.amount ELSE 0 END),0) approved_amount, COALESCE(SUM(CASE WHEN vehicle_costs.status='submitted' THEN 1 ELSE 0 END),0) submitted_count")->first();
        $paginator = $query->select($this->columns())->paginate(min(max((int) $request->query('per_page', 20), 1), 100));

        return ApiResponse::success('Vehicle costs loaded.', [
            'items' => collect($paginator->items())->map(fn ($item) => $this->payload($item)),
            'summary' => ['records_count' => (int) $summary->records_count, 'total_amount' => (float) $summary->total_amount, 'approved_amount' => (float) $summary->approved_amount, 'submitted_count' => (int) $summary->submitted_count],
            'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'total' => $paginator->total()],
        ]);
    }

    public function show(Request $request, VehicleCost $vehicleCost)
    {
        $this->authorizePermission($request, 'office.vehicle-costs.view');

        return ApiResponse::success('Vehicle cost loaded.', ['item' => $this->payload($this->query()->select($this->columns())->where('vehicle_costs.id', $vehicleCost->id)->first())]);
    }

    public function store(Request $request)
    {
        $this->authorizePermission($request, 'office.vehicle-costs.manage');
        $validated = $this->rules($request);
        $cost = DB::transaction(function () use ($request, $validated) {
            $cost = VehicleCost::create($validated + ['code' => $this->nextCode(), 'record_type' => 'cost', 'source_app' => 'office', 'status' => 'approved', 'submitted_by' => $request->user()->id, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            $this->syncTransaction($cost, $request->user()->id);

            return $cost;
        });

        return ApiResponse::success('Vehicle cost recorded.', ['id' => $cost->id, 'code' => $cost->code, 'item' => $this->payload($this->query()->select($this->columns())->where('vehicle_costs.id', $cost->id)->first())], 201);
    }

    public function update(Request $request, VehicleCost $vehicleCost)
    {
        $this->authorizePermission($request, 'office.vehicle-costs.manage');
        $validated = $this->rules($request);
        DB::transaction(function () use ($request, $vehicleCost, $validated) {
            $vehicleCost->update($validated);
            if ($vehicleCost->status === 'approved') {
                $this->syncTransaction($vehicleCost->fresh(), $request->user()->id);
            }
        });

        return ApiResponse::success('Vehicle cost updated.', ['item' => $this->payload($this->query()->select($this->columns())->where('vehicle_costs.id', $vehicleCost->id)->first())]);
    }

    public function destroy(Request $request, VehicleCost $vehicleCost)
    {
        $this->authorizePermission($request, 'office.vehicle-costs.manage');
        DB::transaction(function () use ($vehicleCost) {
            FinancialTransaction::where('reference_type', 'vehicle_cost')->where('reference_id', $vehicleCost->id)->delete();
            $vehicleCost->delete();
        });

        return ApiResponse::success('Vehicle cost deleted.');
    }

    public function review(Request $request, VehicleCost $vehicleCost)
    {
        $this->authorizePermission($request, 'office.vehicle-costs.manage');
        abort_unless($vehicleCost->status === 'submitted', 409, 'Only submitted vehicle records can be reviewed.');
        $validated = $request->validate(['status' => ['required', Rule::in(['approved', 'rejected'])], 'notes' => ['nullable', 'string', 'max:500']]);
        DB::transaction(function () use ($request, $vehicleCost, $validated) {
            $vehicleCost->update(['status' => $validated['status'], 'notes' => $validated['notes'] ?? $vehicleCost->notes, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            if ($validated['status'] === 'approved' && $vehicleCost->amount > 0) {
                $this->syncTransaction($vehicleCost->fresh(), $request->user()->id);
            }
        });

        return ApiResponse::success('Vehicle record review saved.', ['item' => $this->payload($this->query()->select($this->columns())->where('vehicle_costs.id', $vehicleCost->id)->first())]);
    }

    public function routeHistory(Request $request)
    {
        $this->authorizePermission($request, 'office.vehicle-costs.view');
        $query = DB::table('deliveries')->join('vehicles', 'deliveries.vehicle_id', '=', 'vehicles.id')->join('routes', 'deliveries.route_id', '=', 'routes.id')->join('employees', 'deliveries.driver_id', '=', 'employees.id')->leftJoin('customers', 'deliveries.customer_id', '=', 'customers.id');
        foreach (['vehicle_id', 'route_id', 'driver_id', 'customer_id', 'status'] as $filter) {
            if ($request->filled($filter)) {
                $query->where("deliveries.{$filter}", $request->query($filter));
            }
        }
        if ($request->filled('date_from')) {
            $query->whereDate('deliveries.planned_date', '>=', $request->query('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('deliveries.planned_date', '<=', $request->query('date_to'));
        }
        if ($request->query('distance_status') === 'recorded') {
            $query->whereNotNull('deliveries.distance_km');
        } elseif ($request->query('distance_status') === 'missing') {
            $query->whereNull('deliveries.distance_km');
        }
        if ($request->filled('distance_min')) {
            $query->where('deliveries.distance_km', '>=', $request->query('distance_min'));
        }
        if ($request->filled('distance_max')) {
            $query->where('deliveries.distance_km', '<=', $request->query('distance_max'));
        }
        if ($request->filled('delivered_min')) {
            $query->where('deliveries.delivered_quantity', '>=', $request->query('delivered_min'));
        }
        if ($request->filled('delivered_max')) {
            $query->where('deliveries.delivered_quantity', '<=', $request->query('delivered_max'));
        }
        if ($search = trim((string) $request->query('search'))) {
            $query->where(fn ($q) => $q->where('deliveries.code', 'like', "%{$search}%")
                ->orWhere('vehicles.code', 'like', "%{$search}%")
                ->orWhere('vehicles.plate_no', 'like', "%{$search}%")
                ->orWhere('routes.code', 'like', "%{$search}%")
                ->orWhere('routes.name', 'like', "%{$search}%")
                ->orWhere('employees.code', 'like', "%{$search}%")
                ->orWhere('employees.name', 'like', "%{$search}%")
                ->orWhere('customers.code', 'like', "%{$search}%")
                ->orWhere('customers.shop_name', 'like', "%{$search}%")
                ->orWhere('deliveries.recipient_name', 'like', "%{$search}%"));
        }
        $items = $query->orderByDesc('deliveries.planned_date')->get(['deliveries.id', 'deliveries.code', 'deliveries.planned_date', 'deliveries.status', 'deliveries.total_quantity', 'deliveries.delivered_quantity', 'deliveries.start_odometer_km', 'deliveries.end_odometer_km', 'deliveries.distance_km', 'vehicles.id as vehicle_id', 'vehicles.code as vehicle_code', 'vehicles.plate_no', 'routes.name as route_name', 'employees.name as driver_name', DB::raw('COALESCE(deliveries.recipient_name, customers.shop_name) as shop_name')])->map(function ($item) {
            foreach (['total_quantity', 'delivered_quantity', 'start_odometer_km', 'end_odometer_km', 'distance_km'] as $field) {
                $item->{$field} = $item->{$field} === null ? null : (float) $item->{$field};
            }

            return $item;
        });

        return ApiResponse::success('Vehicle route history loaded.', ['items' => $items, 'summary' => ['routes_count' => $items->count(), 'completed_count' => $items->whereIn('status', ['delivered', 'partially_delivered'])->count(), 'distance_km' => (float) $items->sum('distance_km')]]);
    }

    public function updateDistance(Request $request, int $deliveryId)
    {
        $this->authorizePermission($request, 'office.vehicle-costs.manage');
        abort_unless(DB::table('deliveries')->where('id', $deliveryId)->exists(), 404);
        $validated = $request->validate(['start_odometer_km' => ['nullable', 'numeric', 'min:0'], 'end_odometer_km' => ['nullable', 'numeric', 'gte:start_odometer_km'], 'distance_km' => ['nullable', 'numeric', 'min:0']]);
        $distance = $validated['distance_km'] ?? null;
        if ($distance === null && isset($validated['start_odometer_km'], $validated['end_odometer_km'])) {
            $distance = $validated['end_odometer_km'] - $validated['start_odometer_km'];
        }
        DB::table('deliveries')->where('id', $deliveryId)->update(array_merge($validated, ['distance_km' => $distance, 'updated_at' => now()]));

        return ApiResponse::success('Route distance updated.', ['distance_km' => $distance]);
    }

    public function monthlyCosts(Request $request)
    {
        $this->authorizePermission($request, 'office.vehicle-costs.view');
        $year = (int) $request->query('year', now()->year);
        $costQuery = $this->approvedCostQuery($request)->whereYear('cost_date', $year);
        if ($request->filled('month_from')) {
            $costQuery->whereMonth('cost_date', '>=', (int) $request->query('month_from'));
        }
        if ($request->filled('month_to')) {
            $costQuery->whereMonth('cost_date', '<=', (int) $request->query('month_to'));
        }
        $costs = $costQuery->get(['vehicle_costs.vehicle_id', 'vehicle_costs.cost_date', 'vehicle_costs.cost_type', 'vehicle_costs.amount', 'vehicles.code as vehicle_code', 'vehicles.plate_no']);
        $items = $costs->groupBy(fn ($item) => Carbon::parse($item->cost_date)->format('Y-m'))->map(function ($monthCosts, $month) {
            $types = collect(self::TYPES)->mapWithKeys(fn ($type) => [$type => (float) $monthCosts->where('cost_type', $type)->sum('amount')]);

            return ['month' => $month, 'total' => (float) $monthCosts->sum('amount'), 'types' => $types, 'vehicles_count' => $monthCosts->pluck('vehicle_id')->unique()->count()];
        })->when($request->filled('total_min'), fn ($rows) => $rows->where('total', '>=', (float) $request->query('total_min')))
            ->when($request->filled('total_max'), fn ($rows) => $rows->where('total', '<=', (float) $request->query('total_max')))
            ->when($request->filled('vehicles_min'), fn ($rows) => $rows->where('vehicles_count', '>=', (int) $request->query('vehicles_min')))
            ->sortByDesc('month')->values();

        return ApiResponse::success('Monthly vehicle costs loaded.', ['items' => $items, 'summary' => ['year' => $year, 'total' => (float) $costs->sum('amount'), 'records_count' => $costs->count()]]);
    }

    public function costPerKm(Request $request)
    {
        $this->authorizePermission($request, 'office.vehicle-costs.view');

        return ApiResponse::success('Vehicle cost per kilometre loaded.', $this->performanceData($request));
    }

    public function performance(Request $request)
    {
        $this->authorizePermission($request, 'office.vehicle-costs.view');

        return ApiResponse::success('Vehicle performance loaded.', $this->performanceData($request));
    }

    public function mobileIndex(Request $request)
    {
        $scope = $this->driverScope($request, 'view');
        $vehicle = DB::table('vehicles')->where('assigned_driver_id', $scope['employee_id'])->where('is_active', true)->first(['id', 'code', 'plate_no', 'vehicle_type', 'make', 'model', 'capacity']);
        abort_unless($vehicle, 404, 'No active vehicle is assigned.');
        $records = $this->query()->where('vehicle_costs.employee_id', $scope['employee_id'])->latest('vehicle_costs.cost_date')->select($this->columns())->get()->map(fn ($item) => $this->payload($item));
        $deliveries = DB::table('deliveries')->join('routes', 'deliveries.route_id', '=', 'routes.id')->where('deliveries.driver_id', $scope['employee_id'])->where('deliveries.vehicle_id', $vehicle->id)->orderByDesc('planned_date')->limit(20)->get(['deliveries.id', 'deliveries.code', 'deliveries.planned_date', 'deliveries.status', 'routes.name as route_name']);

        return ApiResponse::success('Driver vehicle operations loaded.', ['vehicle' => $vehicle, 'records' => $records, 'deliveries' => $deliveries, 'cost_types' => self::TYPES, 'summary' => ['submitted_count' => $records->where('status', 'submitted')->count(), 'approved_amount' => (float) $records->where('status', 'approved')->sum('amount')]]);
    }

    public function mobileStore(Request $request)
    {
        $scope = $this->driverScope($request, 'create');
        $vehicleId = DB::table('vehicles')->where('assigned_driver_id', $scope['employee_id'])->where('is_active', true)->value('id');
        abort_unless($vehicleId, 404, 'No active vehicle is assigned.');
        $validated = $request->validate([
            'delivery_id' => ['nullable', 'integer', 'exists:deliveries,id'], 'cost_date' => ['required', 'date'], 'record_type' => ['required', Rule::in(['cost', 'issue'])],
            'cost_type' => ['required', Rule::in(self::TYPES)], 'description' => ['required', 'string', 'max:255'], 'vendor' => ['nullable', 'string', 'max:120'],
            'odometer_km' => ['nullable', 'numeric', 'min:0'], 'quantity' => ['nullable', 'numeric', 'gt:0'], 'unit_price' => ['nullable', 'numeric', 'gte:0'],
            'amount' => ['nullable', 'numeric', 'gte:0'], 'payment_method' => ['required', Rule::in(['cash', 'bank'])], 'reference_no' => ['nullable', 'string', 'max:100'],
            'issue_severity' => ['nullable', Rule::in(['low', 'medium', 'high'])], 'notes' => ['nullable', 'string', 'max:500'],
        ]);
        if ($validated['record_type'] === 'cost') {
            abort_unless((float) ($validated['amount'] ?? 0) > 0, 422, 'Amount is required for a vehicle cost.');
        }
        if (! empty($validated['delivery_id'])) {
            abort_unless(DB::table('deliveries')->where('id', $validated['delivery_id'])->where('driver_id', $scope['employee_id'])->where('vehicle_id', $vehicleId)->exists(), 403, 'Delivery is outside your assignment.');
        }
        $cost = VehicleCost::create($validated + ['code' => $this->nextCode(), 'vehicle_id' => $vehicleId, 'employee_id' => $scope['employee_id'], 'amount' => $validated['amount'] ?? 0, 'source_app' => 'driver', 'status' => 'submitted', 'submitted_by' => $request->user()->id]);

        return ApiResponse::success('Vehicle record submitted for Office review.', ['id' => $cost->id, 'code' => $cost->code, 'item' => $this->payload($this->query()->select($this->columns())->where('vehicle_costs.id', $cost->id)->first())], 201);
    }

    private function rules(Request $request): array
    {
        return $request->validate([
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'], 'delivery_id' => ['nullable', 'integer', 'exists:deliveries,id'], 'cost_date' => ['required', 'date'],
            'cost_type' => ['required', Rule::in(self::TYPES)], 'description' => ['required', 'string', 'max:255'], 'vendor' => ['nullable', 'string', 'max:120'],
            'odometer_km' => ['nullable', 'numeric', 'min:0'], 'quantity' => ['nullable', 'numeric', 'gt:0'], 'unit_price' => ['nullable', 'numeric', 'gte:0'],
            'amount' => ['required', 'numeric', 'gt:0'], 'payment_method' => ['required', Rule::in(['cash', 'bank'])], 'reference_no' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:500'],
        ]);
    }

    private function performanceData(Request $request): array
    {
        $from = $request->query('date_from', now()->startOfYear()->toDateString());
        $to = $request->query('date_to', now()->toDateString());
        $vehiclesQuery = DB::table('vehicles')
            ->when($request->filled('vehicle_id'), fn ($q) => $q->where('id', $request->query('vehicle_id')))
            ->when(! $request->filled('vehicle_id'), fn ($q) => $q->where('is_active', true));
        if ($search = trim((string) $request->query('search'))) {
            $vehiclesQuery->where(fn ($q) => $q->where('code', 'like', "%{$search}%")->orWhere('plate_no', 'like', "%{$search}%")->orWhere('make', 'like', "%{$search}%")->orWhere('model', 'like', "%{$search}%"));
        }
        $vehicles = $vehiclesQuery->orderBy('code')->get(['id', 'code', 'plate_no', 'make', 'model']);
        $items = $vehicles->map(function ($vehicle) use ($from, $to, $request) {
            $deliveryQuery = DB::table('deliveries')->where('vehicle_id', $vehicle->id)->whereBetween('planned_date', [$from, $to]);
            foreach (['route_id', 'driver_id', 'customer_id', 'status'] as $filter) {
                if ($request->filled($filter)) {
                    $deliveryQuery->where($filter, $request->query($filter));
                }
            }
            if ($request->query('distance_status') === 'recorded') {
                $deliveryQuery->whereNotNull('distance_km');
            } elseif ($request->query('distance_status') === 'missing') {
                $deliveryQuery->whereNull('distance_km');
            }
            $deliveries = $deliveryQuery->get();
            $costQuery = VehicleCost::where('vehicle_id', $vehicle->id)->where('status', 'approved')->whereBetween('cost_date', [$from, $to]);
            if (collect(['route_id', 'driver_id', 'customer_id', 'status', 'distance_status'])->contains(fn ($filter) => $request->filled($filter))) {
                $costQuery->whereIn('delivery_id', $deliveries->pluck('id'));
            }
            foreach (['cost_type', 'payment_method', 'source_app'] as $filter) {
                if ($request->filled($filter)) {
                    $costQuery->where($filter, $request->query($filter));
                }
            }
            $cost = (float) $costQuery->sum('amount');
            $distance = (float) $deliveries->sum('distance_km');
            $completed = $deliveries->whereIn('status', ['delivered', 'partially_delivered'])->count();

            return ['vehicle_id' => $vehicle->id, 'vehicle_code' => $vehicle->code, 'plate_no' => $vehicle->plate_no, 'make_model' => trim("{$vehicle->make} {$vehicle->model}"), 'deliveries_count' => $deliveries->count(), 'completed_count' => $completed, 'completion_rate' => $deliveries->count() ? round($completed / $deliveries->count() * 100, 1) : 0, 'delivered_quantity' => (float) $deliveries->sum('delivered_quantity'), 'distance_km' => $distance, 'total_cost' => $cost, 'cost_per_km' => $distance > 0 ? round($cost / $distance, 2) : null];
        })->when(collect(['route_id', 'driver_id', 'customer_id', 'status', 'distance_status'])->contains(fn ($filter) => $request->filled($filter)), fn ($rows) => $rows->where('deliveries_count', '>', 0))
            ->when($request->filled('completion_min'), fn ($rows) => $rows->where('completion_rate', '>=', (float) $request->query('completion_min')))
            ->when($request->filled('completion_max'), fn ($rows) => $rows->where('completion_rate', '<=', (float) $request->query('completion_max')))
            ->when($request->filled('deliveries_min'), fn ($rows) => $rows->where('deliveries_count', '>=', (int) $request->query('deliveries_min')))
            ->when($request->filled('deliveries_max'), fn ($rows) => $rows->where('deliveries_count', '<=', (int) $request->query('deliveries_max')))
            ->when($request->filled('delivered_min'), fn ($rows) => $rows->where('delivered_quantity', '>=', (float) $request->query('delivered_min')))
            ->when($request->filled('delivered_max'), fn ($rows) => $rows->where('delivered_quantity', '<=', (float) $request->query('delivered_max')))
            ->when($request->filled('distance_min'), fn ($rows) => $rows->where('distance_km', '>=', (float) $request->query('distance_min')))
            ->when($request->filled('distance_max'), fn ($rows) => $rows->where('distance_km', '<=', (float) $request->query('distance_max')))
            ->when($request->filled('cost_min'), fn ($rows) => $rows->where('total_cost', '>=', (float) $request->query('cost_min')))
            ->when($request->filled('cost_max'), fn ($rows) => $rows->where('total_cost', '<=', (float) $request->query('cost_max')))
            ->when($request->filled('cost_per_km_min'), fn ($rows) => $rows->where('cost_per_km', '>=', (float) $request->query('cost_per_km_min')))
            ->when($request->filled('cost_per_km_max'), fn ($rows) => $rows->where('cost_per_km', '<=', (float) $request->query('cost_per_km_max')))
            ->values();

        return ['period' => ['date_from' => $from, 'date_to' => $to], 'items' => $items, 'summary' => ['vehicles_count' => $items->count(), 'deliveries_count' => $items->sum('deliveries_count'), 'distance_km' => (float) $items->sum('distance_km'), 'total_cost' => (float) $items->sum('total_cost'), 'cost_per_km' => $items->sum('distance_km') > 0 ? round($items->sum('total_cost') / $items->sum('distance_km'), 2) : null]];
    }

    private function approvedCostQuery(Request $request)
    {
        $query = DB::table('vehicle_costs')->join('vehicles', 'vehicle_costs.vehicle_id', '=', 'vehicles.id')->where('vehicle_costs.status', 'approved')->where('vehicle_costs.record_type', 'cost')->when($request->filled('vehicle_id'), fn ($q) => $q->where('vehicle_costs.vehicle_id', $request->query('vehicle_id')));
        foreach (['cost_type', 'payment_method', 'source_app'] as $filter) {
            if ($request->filled($filter)) {
                $query->where("vehicle_costs.{$filter}", $request->query($filter));
            }
        }

        return $query;
    }

    private function syncTransaction(VehicleCost $cost, ?int $userId): void
    {
        $existing = FinancialTransaction::where('reference_type', 'vehicle_cost')->where('reference_id', $cost->id)->first();
        FinancialTransaction::updateOrCreate(['reference_type' => 'vehicle_cost', 'reference_id' => $cost->id], [
            'code' => $existing?->code ?? 'TXN-'.str_pad((string) (FinancialTransaction::max('id') + 1), 7, '0', STR_PAD_LEFT), 'transaction_date' => $cost->cost_date,
            'book_type' => $cost->payment_method, 'direction' => 'out', 'category' => 'vehicle_cost', 'amount' => $cost->amount,
            'reference_code' => $cost->code, 'description' => $cost->description, 'created_by' => $userId,
        ]);
    }

    private function driverScope(Request $request, string $action): array
    {
        abort_unless($request->user()->role === 'Driver', 403);
        $this->authorizePermission($request, "driver.vehicle-costs.{$action}");
        abort_unless($request->user()->employee_id, 403);

        return ['employee_id' => $request->user()->employee_id];
    }

    private function query()
    {
        return DB::table('vehicle_costs')->join('vehicles', 'vehicle_costs.vehicle_id', '=', 'vehicles.id')->leftJoin('deliveries', 'vehicle_costs.delivery_id', '=', 'deliveries.id')->leftJoin('employees', 'vehicle_costs.employee_id', '=', 'employees.id');
    }

    private function columns(): array
    {
        return ['vehicle_costs.*', 'vehicles.code as vehicle_code', 'vehicles.plate_no', 'vehicles.make', 'vehicles.model', 'deliveries.code as delivery_code', 'employees.name as employee_name'];
    }

    private function payload($item): array
    {
        return ['id' => $item->id, 'code' => $item->code, 'vehicle_id' => $item->vehicle_id, 'vehicle_code' => $item->vehicle_code, 'plate_no' => $item->plate_no, 'make_model' => trim("{$item->make} {$item->model}"), 'delivery_id' => $item->delivery_id, 'delivery_code' => $item->delivery_code, 'employee_id' => $item->employee_id, 'employee_name' => $item->employee_name, 'cost_date' => Carbon::parse($item->cost_date)->toDateString(), 'record_type' => $item->record_type, 'cost_type' => $item->cost_type, 'description' => $item->description, 'vendor' => $item->vendor, 'odometer_km' => $item->odometer_km === null ? null : (float) $item->odometer_km, 'quantity' => $item->quantity === null ? null : (float) $item->quantity, 'unit_price' => $item->unit_price === null ? null : (float) $item->unit_price, 'amount' => (float) $item->amount, 'payment_method' => $item->payment_method, 'reference_no' => $item->reference_no, 'issue_severity' => $item->issue_severity, 'source_app' => $item->source_app, 'status' => $item->status, 'notes' => $item->notes];
    }

    private function nextCode(): string
    {
        return 'VHC-'.now()->format('Ym').'-'.str_pad((string) (VehicleCost::max('id') + 1), 4, '0', STR_PAD_LEFT);
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless(in_array($permission, AppAccess::permissionsForRole($request->user()?->role), true), 403);
    }
}
