<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $policy = $this->currentVersion;

        return [
            'id' => $this->public_id,
            'code' => $this->code,
            'name' => ['en' => $this->name_en, 'my-MM' => $this->name_my],
            'description' => $this->description,
            'status' => $this->status,
            'version' => $this->lock_version,
            'policy' => $policy ? [
                'id' => $policy->public_id,
                'version' => $policy->version,
                'area' => ['id' => $policy->area->public_id, 'code' => $policy->area->code, 'name' => ['en' => $policy->area->name_en, 'my-MM' => $policy->area->name_my]],
                'default_warehouse' => $policy->defaultWarehouse ? ['id' => $policy->defaultWarehouse->public_id, 'code' => $policy->defaultWarehouse->code, 'name' => ['en' => $policy->defaultWarehouse->name_en, 'my-MM' => $policy->defaultWarehouse->name_my]] : null,
                'boundary_description' => $policy->boundary_description,
                'service_days' => $policy->service_days,
                'delivery_window_start' => $policy->delivery_window_start ? substr($policy->delivery_window_start, 0, 5) : null,
                'delivery_window_end' => $policy->delivery_window_end ? substr($policy->delivery_window_end, 0, 5) : null,
                'effective_from' => $policy->effective_from?->toDateString(),
                'effective_to' => $policy->effective_to?->toDateString(),
                'status' => $policy->status,
            ] : null,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
