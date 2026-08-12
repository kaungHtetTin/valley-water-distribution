<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SkuResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'code' => $this->code,
            'name' => ['en' => $this->name_en, 'my-MM' => $this->name_my],
            'product' => ['id' => $this->product->public_id, 'code' => $this->product->code, 'name' => ['en' => $this->product->name_en, 'my-MM' => $this->product->name_my], 'brand' => ['code' => $this->product->brand->code, 'name' => ['en' => $this->product->brand->name_en, 'my-MM' => $this->product->brand->name_my]]],
            'size_label' => $this->size_label,
            'barcode' => $this->barcode,
            'volume_ml' => $this->volume_ml,
            'weight_grams' => $this->weight_grams,
            'shelf_life_days' => $this->shelf_life_days,
            'track_lot' => $this->track_lot,
            'track_expiry' => $this->track_expiry,
            'is_returnable' => $this->is_returnable,
            'minimum_order_quantity' => $this->minimum_order_quantity,
            'order_step_quantity' => $this->order_step_quantity,
            'minimum_delivery_quantity' => $this->minimum_delivery_quantity,
            'sale_status' => $this->sale_status,
            'base_uom' => ['id' => $this->baseUom->public_id, 'code' => $this->baseUom->code, 'symbol' => $this->baseUom->symbol, 'name' => ['en' => $this->baseUom->name_en, 'my-MM' => $this->baseUom->name_my]],
            'conversions' => $this->whenLoaded('conversions', fn () => $this->conversions->map(fn ($conversion) => ['id' => $conversion->public_id, 'uom' => ['id' => $conversion->uom->public_id, 'code' => $conversion->uom->code, 'symbol' => $conversion->uom->symbol], 'factor_to_base' => $conversion->factor_to_base, 'version' => $conversion->version, 'is_selling_unit' => $conversion->is_selling_unit, 'is_kpi_base' => $conversion->is_kpi_base])),
            'prices' => $this->whenLoaded('priceItems', fn () => $this->priceItems->map(fn ($item) => ['id' => $item->public_id, 'price_book' => ['id' => $item->priceBook->public_id, 'code' => $item->priceBook->code, 'type' => ['code' => $item->priceBook->priceType->code, 'name' => ['en' => $item->priceBook->priceType->name_en, 'my-MM' => $item->priceBook->priceType->name_my]]], 'uom' => ['id' => $item->uom->public_id, 'code' => $item->uom->code, 'symbol' => $item->uom->symbol], 'unit_price_minor' => $item->unit_price_minor, 'minimum_quantity' => $item->minimum_quantity, 'effective_from' => $item->effective_from?->toDateString(), 'effective_to' => $item->effective_to?->toDateString(), 'approval_status' => $item->approval_status, 'status' => $item->status, 'version' => $item->lock_version])),
            'active_from' => $this->active_from?->toDateString(),
            'active_to' => $this->active_to?->toDateString(),
            'status' => $this->status,
            'version' => $this->lock_version,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
