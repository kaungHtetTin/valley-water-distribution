<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderStockAvailability
{
    public static function quantityQuery(): Builder
    {
        return DB::table('stock_balances')->selectRaw('COALESCE(SUM(quantity), 0)')
            ->whereColumn('stock_balances.product_id', 'products.id');
    }

    public static function validateItems(array $items): void
    {
        $products = DB::table('products')->whereIn('id', array_column($items, 'product_id'))
            ->select(['id', 'sku', 'name'])->selectSub(self::quantityQuery(), 'available_stock')
            ->get()->keyBy('id');
        $errors = [];
        foreach ($items as $index => $item) {
            $product = $products->get($item['product_id']);
            if ($product && (float) $product->available_stock <= 0) {
                $errors["items.{$index}.product_id"] = "{$product->name} ({$product->sku}) is out of stock. Remove this product or replenish stock before saving the order.";
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }
}
