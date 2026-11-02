<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitors_can_browse_active_products_without_private_prices_or_stock_counts(): void
    {
        $this->seed();
        $id = DB::table('products')->insertGetId(['sku' => 'PUBLIC-WATER', 'name' => 'Public water', 'unit' => 'bottle', 'is_active' => true]);
        DB::table('products')->insert(['sku' => 'HIDDEN-WATER', 'name' => 'Hidden water', 'is_active' => false]);
        DB::table('products')->insert(['sku' => 'DELETED-WATER', 'name' => 'Deleted water', 'is_active' => true, 'deleted_at' => now()]);

        $response = $this->getJson('/api/public/products')->assertOk();
        $products = collect($response->json('data.products'));
        $this->assertSame(['id', 'name', 'sku', 'unit', 'in_stock'], array_keys($products->firstWhere('id', $id)));
        $this->assertFalse($products->firstWhere('id', $id)['in_stock']);
        $this->assertFalse($products->contains('sku', 'HIDDEN-WATER'));
        $this->assertFalse($products->contains('sku', 'DELETED-WATER'));
        $this->getJson('/api/mobile/orders/meta')->assertUnauthorized();
    }
}
