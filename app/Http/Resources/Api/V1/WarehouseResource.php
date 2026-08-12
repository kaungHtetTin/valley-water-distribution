<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'code' => $this->code,
            'name' => ['en' => $this->name_en, 'my-MM' => $this->name_my],
            'branch' => ['id' => $this->branch->public_id, 'code' => $this->branch->code, 'name' => ['en' => $this->branch->name_en, 'my-MM' => $this->branch->name_my]],
            'area' => $this->area ? ['id' => $this->area->public_id, 'code' => $this->area->code, 'name' => ['en' => $this->area->name_en, 'my-MM' => $this->area->name_my]] : null,
            'kind' => $this->kind,
            'address' => $this->address,
            'contact_name' => $this->contact_name,
            'phone' => $this->phone,
            'map_position' => $this->latitude !== null ? ['latitude' => (string) $this->latitude, 'longitude' => (string) $this->longitude] : null,
            'order_cutoff_time' => $this->order_cutoff_time ? substr($this->order_cutoff_time, 0, 5) : null,
            'service_area_note' => $this->service_area_note,
            'status' => $this->status,
            'version' => $this->lock_version,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
