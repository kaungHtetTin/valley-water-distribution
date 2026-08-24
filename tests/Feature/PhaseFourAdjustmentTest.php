<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PhaseFourAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_office_can_create_sales_return_and_damage_entries()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());

        $customerId = DB::table('customers')->where('code', 'CUS-0001')->value('id');
        $productId = DB::table('products')->where('sku', 'VAL-5G')->value('id');

        $return = $this->postJson('/api/order-adjustments', [
            'type' => 'sales_return',
            'customer_id' => $customerId,
            'entry_date' => '2026-08-25',
            'payment_type' => 'credit',
            'notes' => 'Feature test returned goods',
            'items' => [
                [
                    'product_id' => $productId,
                    'quantity' => 3,
                    'remarks' => 'Returned from shop',
                ],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.record.status', 'confirmed')
            ->assertJsonPath('data.record.total', 9000)
            ->assertJsonPath('data.items.0.item_type', 'sales_return')
            ->assertJsonPath('data.items.0.unit_price', 3000);

        $returnId = $return->json('data.record.id');
        $returnCode = $return->json('data.record.code');

        $this->assertStringStartsWith('RET-202608-', $returnCode);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $returnId,
            'product_id' => $productId,
            'item_type' => 'sales_return',
            'line_total' => 9000,
        ]);

        $this->getJson("/api/order-adjustments?type=sales_return&search={$returnCode}")
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.code', $returnCode)
            ->assertJsonPath('data.summary.total_quantity', 3)
            ->assertJsonPath('data.summary.total_amount', 9000);

        $this->getJson("/api/order-adjustments/{$returnId}?type=sales_return")
            ->assertOk()
            ->assertJsonPath('data.record.code', $returnCode)
            ->assertJsonPath('data.items.0.quantity', 3);

        $damage = $this->postJson('/api/order-adjustments', [
            'type' => 'damage',
            'customer_id' => $customerId,
            'entry_date' => '2026-08-25',
            'payment_type' => 'credit',
            'notes' => 'Feature test damaged goods',
            'items' => [
                [
                    'product_id' => $productId,
                    'quantity' => 2,
                    'remarks' => 'Broken on return',
                ],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.record.total', 6000)
            ->assertJsonPath('data.items.0.item_type', 'damage');

        $damageCode = $damage->json('data.record.code');
        $this->assertStringStartsWith('DMG-202608-', $damageCode);

        $this->getJson("/api/order-adjustments?type=damage&search={$damageCode}")
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.summary.total_quantity', 2)
            ->assertJsonPath('data.summary.total_amount', 6000);
    }

    public function test_adjustment_api_requires_office_order_permission()
    {
        $this->seed();

        $this->getJson('/api/order-adjustments')->assertUnauthorized();
        $this->getJson('/api/order-adjustments/meta')->assertUnauthorized();

        $this->actingAs(User::where('email', 'sales@valley.test')->firstOrFail())
            ->getJson('/api/order-adjustments')
            ->assertForbidden();

        $this->postJson('/api/order-adjustments', [])->assertForbidden();
    }
}
