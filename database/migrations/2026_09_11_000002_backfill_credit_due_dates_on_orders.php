<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('orders')
            ->where('payment_type', 'credit')
            ->whereNull('credit_due_date')
            ->orderBy('id')
            ->select(['id', 'order_date'])
            ->chunkById(100, function ($orders) {
                foreach ($orders as $order) {
                    $invoiceDueDate = DB::table('invoices')
                        ->where('order_id', $order->id)
                        ->where('status', '!=', 'cancelled')
                        ->value('due_date');

                    DB::table('orders')->where('id', $order->id)->update([
                        'credit_due_date' => $invoiceDueDate
                            ? Carbon::parse($invoiceDueDate)->toDateString()
                            : Carbon::parse($order->order_date)->addDays(7)->toDateString(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Existing operational due dates are intentionally preserved.
    }
};
