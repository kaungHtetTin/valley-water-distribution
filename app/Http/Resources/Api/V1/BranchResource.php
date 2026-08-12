<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'code' => $this->code,
            'name' => ['en' => $this->name_en, 'my-MM' => $this->name_my],
            'phone' => $this->phone,
            'address' => $this->address,
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'business_day_start' => $this->business_day_start ? substr($this->business_day_start, 0, 5) : null,
            'status' => $this->status,
            'version' => $this->lock_version,
            'warehouses_count' => $this->whenCounted('warehouses'),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
