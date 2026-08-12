<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PriceBookItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'price_book' => ['id' => $this->priceBook->public_id, 'code' => $this->priceBook->code, 'name' => ['en' => $this->priceBook->name_en, 'my-MM' => $this->priceBook->name_my], 'type' => ['code' => $this->priceBook->priceType->code, 'name' => ['en' => $this->priceBook->priceType->name_en, 'my-MM' => $this->priceBook->priceType->name_my]], 'currency' => $this->priceBook->currency],
            'sku' => ['id' => $this->sku->public_id, 'code' => $this->sku->code, 'name' => ['en' => $this->sku->name_en, 'my-MM' => $this->sku->name_my]],
            'uom' => ['id' => $this->uom->public_id, 'code' => $this->uom->code, 'symbol' => $this->uom->symbol],
            'unit_price_minor' => $this->unit_price_minor,
            'minimum_quantity' => $this->minimum_quantity,
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'approval_status' => $this->approval_status,
            'status' => $this->status,
            'version' => $this->lock_version,
        ];
    }
}
