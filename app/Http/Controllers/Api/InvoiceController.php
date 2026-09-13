<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use App\Support\ApiResponse;
use App\Support\AppAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function meta(Request $request)
    {
        $this->authorizePermission($request, 'office.invoices.view');

        $customers = DB::table('customers')
            ->where('is_active', true)
            ->orderBy('shop_name')
            ->get(['id', 'code', 'shop_name'])
            ->map(function ($customer) {
                $customer->label = "{$customer->code} - {$customer->shop_name}";

                return $customer;
            });

        return ApiResponse::success('Invoice setup loaded.', [
            'customers' => $customers,
        ]);
    }

    public function index(Request $request)
    {
        $this->authorizePermission($request, 'office.invoices.view');

        $query = $this->baseQuery()->latest('invoices.invoice_date')->latest('invoices.id');

        if ($request->filled('status')) {
            $query->where('invoices.status', $request->query('status'));
        }

        if ($request->filled('customer_id')) {
            $query->where('invoices.customer_id', $request->query('customer_id'));
        }

        if ($request->filled('date')) {
            $query->whereDate('invoices.invoice_date', $request->query('date'));
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($query) use ($search) {
                $query->where('invoices.code', 'like', "%{$search}%")
                    ->orWhere('orders.code', 'like', "%{$search}%")
                    ->orWhere('invoices.recipient_name', 'like', "%{$search}%")
                    ->orWhere('invoices.delivery_address', 'like', "%{$search}%")
                    ->orWhere('customers.shop_name', 'like', "%{$search}%")
                    ->orWhere('customers.code', 'like', "%{$search}%")
                    ->orWhere('routes.name', 'like', "%{$search}%");
            });
        }

        $summary = (clone $query)->reorder()
            ->selectRaw("COUNT(*) as invoices_count, COALESCE(SUM(invoices.total), 0) as total_amount, SUM(CASE WHEN invoices.status = 'draft' THEN 1 ELSE 0 END) as draft_count, SUM(CASE WHEN invoices.status = 'issued' THEN 1 ELSE 0 END) as issued_count")
            ->first();

        $paginator = $query->select($this->invoiceColumns())
            ->paginate(min(max((int) $request->query('per_page', 20), 1), 100));

        return ApiResponse::success('Invoices loaded.', [
            'items' => collect($paginator->items())->map(fn ($invoice) => $this->payload($invoice)),
            'summary' => [
                'invoices_count' => (int) ($summary->invoices_count ?? 0),
                'total_amount' => (float) ($summary->total_amount ?? 0),
                'draft_count' => (int) ($summary->draft_count ?? 0),
                'issued_count' => (int) ($summary->issued_count ?? 0),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Request $request, Invoice $invoice)
    {
        $this->authorizePermission($request, 'office.invoices.view');
        $invoicePayload = $this->baseQuery()->select($this->invoiceColumns())->where('invoices.id', $invoice->id)->first();
        abort_unless($invoicePayload, 404);

        return ApiResponse::success('Invoice loaded.', [
            'invoice' => $this->payload($invoicePayload),
            'items' => $invoice->items()->orderBy('id')->get()->map(fn ($item) => $this->itemPayload($item)),
        ]);
    }

    public function storeFromOrder(Request $request)
    {
        $this->authorizePermission($request, 'office.invoices.manage');

        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'invoice_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $order = Order::with('items')->findOrFail($validated['order_id']);
        abort_unless($order->status === 'confirmed', 409, 'Only confirmed orders can be invoiced.');
        abort_if(Invoice::where('order_id', $order->id)->where('status', '!=', 'cancelled')->exists(), 409, 'This order already has an invoice.');
        abort_unless($order->items->isNotEmpty(), 422, 'Order items are required.');
        $invoiceableItems = $order->items->filter(fn ($item) => in_array($item->item_type, ['sale', 'foc'], true))->values();
        abort_unless($invoiceableItems->count() === $order->items->count(), 422, 'Only sale and FOC orders can be invoiced.');

        $invoiceDate = Carbon::parse($validated['invoice_date'] ?? now());
        $invoice = DB::transaction(function () use ($invoiceDate, $invoiceableItems, $order, $request, $validated) {
            $invoice = Invoice::create([
                'code' => $this->nextCode($invoiceDate),
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'area_id' => $order->area_id,
                'route_id' => $order->route_id,
                'recipient_name' => $order->recipient_name,
                'recipient_phone' => $order->recipient_phone,
                'delivery_address' => $order->delivery_address,
                'invoice_date' => $invoiceDate->toDateString(),
                'due_date' => $validated['due_date'] ?? null,
                'status' => 'draft',
                'subtotal' => $order->subtotal,
                'discount_total' => $order->discount_total,
                'tax_total' => $order->tax_total,
                'total' => $order->total,
                'created_by' => $request->user()?->id,
                'notes' => $validated['notes'] ?? $order->notes,
            ]);

            foreach ($invoiceableItems as $item) {
                $invoice->items()->create([
                    'product_id' => $item->product_id,
                    'product_sku' => $item->product_sku,
                    'product_name' => $item->product_name,
                    'unit' => $item->unit,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount_amount' => $item->discount_amount,
                    'line_total' => $item->line_total,
                ]);
            }

            $order->update(['status' => 'invoiced']);

            return $invoice;
        });

        $invoicePayload = $this->baseQuery()->select($this->invoiceColumns())->where('invoices.id', $invoice->id)->first();
        abort_unless($invoicePayload, 404);

        return ApiResponse::success('Invoice created from order.', [
            'invoice' => $this->payload($invoicePayload),
            'items' => $invoice->items()->orderBy('id')->get()->map(fn ($item) => $this->itemPayload($item)),
        ], 201);
    }

    public function issue(Request $request, Invoice $invoice)
    {
        $this->authorizePermission($request, 'office.invoices.manage');
        abort_unless($invoice->status === 'draft', 409, 'Only draft invoices can be issued.');

        $invoice->update(['status' => 'issued']);

        $invoicePayload = $this->baseQuery()->select($this->invoiceColumns())->where('invoices.id', $invoice->id)->first();
        abort_unless($invoicePayload, 404);

        return ApiResponse::success('Invoice issued.', [
            'invoice' => $this->payload($invoicePayload),
        ]);
    }

    public function cancel(Request $request, Invoice $invoice)
    {
        $this->authorizePermission($request, 'office.invoices.manage');
        abort_if($invoice->status === 'cancelled', 409, 'This invoice is already cancelled.');

        DB::transaction(function () use ($invoice) {
            $invoice->update(['status' => 'cancelled']);

            if ($invoice->order_id) {
                Order::query()
                    ->whereKey($invoice->order_id)
                    ->where('status', 'invoiced')
                    ->update(['status' => 'confirmed']);
            }
        });

        $invoicePayload = $this->baseQuery()->select($this->invoiceColumns())->where('invoices.id', $invoice->id)->first();
        abort_unless($invoicePayload, 404);

        return ApiResponse::success('Invoice cancelled.', [
            'invoice' => $this->payload($invoicePayload),
        ]);
    }

    private function baseQuery()
    {
        return DB::table('invoices')
            ->leftJoin('orders', 'invoices.order_id', '=', 'orders.id')
            ->leftJoin('customers', 'invoices.customer_id', '=', 'customers.id')
            ->leftJoin('routes', function ($join) {
                $join->on('routes.id', '=', DB::raw('COALESCE(invoices.route_id, orders.route_id)'));
            })
            ->leftJoin('areas', 'routes.area_id', '=', 'areas.id');
    }

    private function invoiceColumns(): array
    {
        return [
            'invoices.*',
            'orders.code as order_code',
            'orders.payment_type',
            'customers.code as customer_code',
            DB::raw('COALESCE(invoices.recipient_name, customers.shop_name) as recipient_name_display'),
            'customers.contact_name',
            'areas.name as area',
            'routes.name as route',
        ];
    }

    private function payload($invoice): array
    {
        return [
            'id' => $invoice->id,
            'code' => $invoice->code,
            'order_id' => $invoice->order_id,
            'order_code' => $invoice->order_code,
            'customer_id' => $invoice->customer_id,
            'customer_code' => $invoice->customer_code,
            'shop_name' => $invoice->recipient_name_display,
            'recipient_name' => $invoice->recipient_name_display,
            'recipient_phone' => $invoice->recipient_phone,
            'area_id' => $invoice->area_id,
            'area' => $invoice->area,
            'route_id' => $invoice->route_id,
            'delivery_address' => $invoice->delivery_address,
            'contact_name' => $invoice->contact_name,
            'route' => $invoice->route,
            'payment_type' => $invoice->payment_type,
            'invoice_date' => Carbon::parse($invoice->invoice_date)->toDateString(),
            'due_date' => $invoice->due_date ? Carbon::parse($invoice->due_date)->toDateString() : null,
            'status' => $invoice->status,
            'subtotal' => (float) $invoice->subtotal,
            'discount_total' => (float) $invoice->discount_total,
            'tax_total' => (float) $invoice->tax_total,
            'total' => (float) $invoice->total,
            'notes' => $invoice->notes,
            'updated_at' => Carbon::parse($invoice->updated_at)->toDateTimeString(),
        ];
    }

    private function itemPayload($item): array
    {
        return [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'product_sku' => $item->product_sku,
            'product_name' => $item->product_name,
            'unit' => $item->unit,
            'quantity' => (float) $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'discount_amount' => (float) $item->discount_amount,
            'line_total' => (float) $item->line_total,
        ];
    }

    private function nextCode(Carbon $invoiceDate): string
    {
        $prefix = 'INV-'.$invoiceDate->format('Ym').'-';
        $next = ((int) Invoice::query()->where('code', 'like', "{$prefix}%")->count()) + 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless(in_array($permission, AppAccess::permissionsForRole($request->user()?->role), true), 403);
    }
}
