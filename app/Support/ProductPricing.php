<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ProductPricing
{
    public static function amount(int $productId, ?int $priceTypeId, Carbon $date): ?float
    {
        $defaultId = DB::table('price_types')->where('is_active', true)->where('is_default', true)->value('id');
        $query = DB::table('product_prices')->where('product_id', $productId)->where('is_active', true)
            ->where(function ($query) use ($date) {
                $query->whereNull('effective_from')->orWhereDate('effective_from', '<=', $date->toDateString());
            })->orderByDesc('effective_from')->orderByDesc('id');
        $selected = $priceTypeId ? (clone $query)->where('price_type_id', $priceTypeId)->value('amount') : null;
        if ($selected !== null && (float) $selected > 0) return (float) $selected;
        $fallback = $defaultId ? (clone $query)->where('price_type_id', $defaultId)->value('amount') : null;
        return $fallback !== null && (float) $fallback > 0 ? (float) $fallback : null;
    }
}
