<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class CustomerVisitReport
{
    public function forEmployee(object $employee, string $date): array
    {
        $customers = $employee->assigned_route_id
            ? DB::table('customers')
                ->leftJoin('sales_route_visits as visits', function ($join) use ($employee, $date) {
                    $join->on('customers.id', '=', 'visits.customer_id')
                        ->where('visits.employee_id', $employee->id)
                        ->where('visits.route_id', $employee->assigned_route_id)
                        ->where('visits.visit_date', $date);
                })
                ->where('customers.route_id', $employee->assigned_route_id)
                ->where('customers.is_active', true)
                ->whereNull('customers.deleted_at')
                ->orderBy('customers.shop_name')
                ->get([
                    'customers.id', 'customers.code', 'customers.shop_name', 'customers.contact_name',
                    'customers.phone', 'customers.address', 'customers.created_at', 'visits.status as visit_status',
                    'visits.started_at', 'visits.completed_at', 'visits.notes',
                ])
                ->map(function ($customer) {
                    $customer->visit_status ??= 'planned';

                    return $customer;
                })
            : collect();
        $visited = $customers->where('visit_status', 'completed')->count();

        return [
            'date' => $date,
            'customers' => $customers,
            'summary' => ['total' => $customers->count(), 'visited' => $visited, 'not_visited' => $customers->count() - $visited],
        ];
    }
}
