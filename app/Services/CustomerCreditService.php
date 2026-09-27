<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class CustomerCreditService
{
    public function summary(int $customerId): array
    {
        $creditSales = (float) DB::table('invoices')
            ->join('orders', 'invoices.order_id', '=', 'orders.id')
            ->where('invoices.customer_id', $customerId)
            ->where('orders.payment_type', 'credit')
            ->where('invoices.status', '!=', 'cancelled')
            ->sum('invoices.total');
        $payments = (float) DB::table('collections')
            ->where('customer_id', $customerId)
            ->where('status', 'approved')
            ->where(function ($query) {
                $query->whereNull('invoice_id')->orWhereExists(function ($subquery) {
                    $subquery->selectRaw('1')
                        ->from('invoices')
                        ->join('orders', 'invoices.order_id', '=', 'orders.id')
                        ->whereColumn('invoices.id', 'collections.invoice_id')
                        ->where('orders.payment_type', 'credit');
                });
            })
            ->sum('amount');
        $returnCredits = (float) DB::table('orders as returns')
            ->join('orders as originals', 'returns.original_order_id', '=', 'originals.id')
            ->where('returns.customer_id', $customerId)
            ->where('returns.status', 'confirmed')
            ->where(function ($query) {
                $query->where('originals.payment_type', 'credit')
                    ->orWhere('returns.return_settlement_method', 'customer_credit');
            })
            ->sum('returns.total');
        $returnRefunds = (float) DB::table('orders as returns')
            ->join('orders as originals', 'returns.original_order_id', '=', 'originals.id')
            ->where('returns.customer_id', $customerId)
            ->where('returns.status', 'confirmed')
            ->where('originals.payment_type', 'credit')
            ->sum('returns.refund_amount');
        $balance = $creditSales + $returnRefunds - $payments - $returnCredits;

        return [
            'credit_sales_amount' => $creditSales,
            'payments_amount' => $payments,
            'return_credits_amount' => $returnCredits,
            'refunds_amount' => $returnRefunds,
            'balance' => $balance,
            'outstanding_amount' => max($balance, 0),
            'customer_credit_amount' => max(-$balance, 0),
        ];
    }

    public function outstanding(int $customerId): float
    {
        return $this->summary($customerId)['outstanding_amount'];
    }

    public function customerCredit(int $customerId): float
    {
        return $this->summary($customerId)['customer_credit_amount'];
    }

    public function settlementCredits(int $customerId): float
    {
        $summary = $this->summary($customerId);

        return max($summary['payments_amount'] + $summary['return_credits_amount'] - $summary['refunds_amount'], 0);
    }
}
