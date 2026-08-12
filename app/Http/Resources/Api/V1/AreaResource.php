<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AreaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'code' => $this->code,
            'name' => [
                'en' => $this->name_en,
                'my-MM' => $this->name_my,
            ],
            'description' => $this->description,
            'parent' => $this->whenLoaded('parent', fn (): ?array => $this->parent ? [
                'id' => $this->parent->public_id,
                'name' => ['en' => $this->parent->name_en, 'my-MM' => $this->parent->name_my],
            ] : null),
            'sort_order' => $this->sort_order,
            'status' => $this->status,
            'version' => $this->lock_version,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
