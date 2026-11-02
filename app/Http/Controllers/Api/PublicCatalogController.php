<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\ApiResponse;
use App\Support\OrderStockAvailability;

class PublicCatalogController extends Controller
{
    public function __invoke()
    {
        $products = Product::query()->where('is_active', true)->orderBy('name')
            ->select(['products.id', 'products.name', 'products.sku', 'products.unit'])
            ->selectSub(OrderStockAvailability::quantityQuery(), 'available_stock')
            ->get()->map(fn ($product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'unit' => $product->unit,
                'in_stock' => (float) $product->available_stock > 0,
            ]);

        return ApiResponse::success('Water products loaded.', ['products' => $products]);
    }
}
