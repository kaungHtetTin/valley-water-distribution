<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class NetSalesQuantity
{
    public static function forEmployee(int $employeeId, string $from, string $to): array
    {
        $invoices = DB::table('invoices')
            ->join('orders', 'invoices.order_id', '=', 'orders.id')
            ->join('users', 'orders.created_by', '=', 'users.id')
            ->where('users.employee_id', $employeeId)
            ->whereIn('invoices.status', ['issued', 'delivered', 'partially_delivered'])
            ->whereBetween('invoices.invoice_date', [$from, $to]);
        $invoiceCount = (clone $invoices)->count('invoices.id');
        $sold = (float) (clone $invoices)->join('invoice_items', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->where('invoice_items.item_type', 'sale')->sum('invoice_items.quantity');

        $returns = DB::table('orders as returns')
            ->join('orders as original_orders', 'returns.original_order_id', '=', 'original_orders.id')
            ->join('users', 'original_orders.created_by', '=', 'users.id')
            ->where('users.employee_id', $employeeId)
            ->where('returns.status', 'confirmed')
            ->whereBetween('returns.order_date', [$from, $to]);
        $returnCount = (clone $returns)->count('returns.id');
        $returned = (float) (clone $returns)->join('order_items', 'order_items.order_id', '=', 'returns.id')
            ->leftJoin('order_items as original_items', 'order_items.original_order_item_id', '=', 'original_items.id')
            ->where('order_items.item_type', 'sales_return')
            ->where(fn ($query) => $query->where('original_items.item_type', 'sale')->orWhereNull('order_items.original_order_item_id'))
            ->sum('order_items.quantity');

        return [
            'quantity' => max($sold - $returned, 0),
            'count' => $invoiceCount + $returnCount,
            'note' => "{$sold} units sold − {$returned} units returned; {$invoiceCount} eligible invoice(s), {$returnCount} confirmed return(s). FOC excluded.",
        ];
    }
}
