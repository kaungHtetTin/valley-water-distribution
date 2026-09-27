<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\AppAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ActionAlertController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'app' => ['required', Rule::in(AppAccess::APPS)],
        ]);
        $app = $validated['app'];
        $user = $request->user();

        abort_unless($user && AppAccess::canAccess($user, $app), 403, 'This application is outside your access scope.');

        $items = match ($app) {
            'office' => $this->officeAlerts($user->role),
            'client' => $this->clientAlerts($user->customer_id),
            'sales' => $this->salesAlerts($user->id),
            'driver' => $this->driverAlerts($user->employee_id),
        };

        $items = collect($items)->filter(fn ($item) => $item['count'] > 0)->values();

        return ApiResponse::success('Action alerts loaded.', [
            'app' => $app,
            'total' => (int) $items->sum('count'),
            'items' => $items,
        ]);
    }

    private function officeAlerts(string $role): array
    {
        $permissions = AppAccess::permissionsForRole($role);
        $can = fn (string $permission) => in_array($permission, $permissions, true);
        $readyTrips = DB::table('invoices')
            ->leftJoin('deliveries', function ($join) {
                $join->on('invoices.id', '=', 'deliveries.invoice_id')
                    ->where('deliveries.status', '!=', 'cancelled');
            })
            ->where('invoices.status', 'issued')
            ->whereNull('deliveries.id')
            ->count();

        return [
            $this->item('pending-orders', 'Orders to confirm', $can('office.orders.manage') ? DB::table('orders')->where('status', 'pending')->count() : 0, '/orders', 'Pending customer and field orders'),
            $this->item('trip-planning', 'Orders to schedule', $can('office.deliveries.manage') ? $readyTrips : 0, '/deliveries', 'Issued orders without a delivery trip'),
            $this->item('collection-reviews', 'Collections to review', $can('office.finance.manage') ? DB::table('collections')->where('status', 'submitted')->count() : 0, '/finance/outdoor-collections', 'Submitted field collections'),
            $this->item('expense-reviews', 'Expenses to review', $can('office.finance.manage') ? DB::table('expenses')->where('status', 'submitted')->count() : 0, '/finance/outdoor-expenses', 'Submitted field expenses'),
            $this->item('vehicle-reviews', 'Vehicle records to review', $can('office.vehicle-costs.manage') ? DB::table('vehicle_costs')->where('status', 'submitted')->count() : 0, '/vehicle-costs/maintenance', 'Driver issues and costs'),
            $this->item('payroll-approvals', 'Payrolls to approve', $can('office.payroll.manage') ? DB::table('payrolls')->where('status', 'draft')->count() : 0, '/payroll/drafts', 'Generated payroll drafts'),
            $this->item('stock-alerts', 'Low stock alerts', $can('office.inventory.manage') ? DB::table('stock_balances')->where('quantity', '<=', 100)->count() : 0, '/stock/balances', 'Warehouse balances at or below 100 units'),
        ];
    }

    private function clientAlerts(?int $customerId): array
    {
        $pendingOrders = $customerId
            ? DB::table('orders')->where('customer_id', $customerId)->where('status', 'pending')->count()
            : 0;

        return [
            $this->item('pending-orders', 'Orders awaiting confirmation', $pendingOrders, '/orders', 'Orders that still need office confirmation'),
        ];
    }

    private function salesAlerts(int $userId): array
    {
        return [
            $this->item('draft-orders', 'Draft orders to finish', DB::table('orders')->where('created_by', $userId)->where('status', 'draft')->count(), '/orders', 'Saved orders that have not been submitted'),
        ];
    }

    private function driverAlerts(?int $employeeId): array
    {
        if (! $employeeId) {
            return [];
        }

        $count = DB::table('deliveries')
            ->leftJoin('delivery_trips', 'deliveries.trip_id', '=', 'delivery_trips.id')
            ->where('deliveries.driver_id', $employeeId)
            ->whereIn(DB::raw('COALESCE(delivery_trips.status, deliveries.status)'), ['assigned', 'loading', 'on_route'])
            ->selectRaw('COUNT(DISTINCT COALESCE(deliveries.trip_id, deliveries.id)) as aggregate')
            ->value('aggregate');

        return [
            $this->item('driver-tasks', 'Delivery tasks', (int) $count, '/tasks', 'Receive, start, and complete assigned routes'),
        ];
    }

    private function item(string $id, string $label, int $count, string $path, string $detail): array
    {
        return compact('id', 'label', 'count', 'path', 'detail');
    }
}
