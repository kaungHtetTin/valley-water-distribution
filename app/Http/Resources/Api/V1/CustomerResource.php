<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $outlet = $this->primaryOutlet;
        $address = $outlet?->primaryAddress;
        $assignment = $outlet?->currentWayAssignment;

        return [
            'id' => $this->public_id,
            'code' => $this->code,
            'name' => ['en' => $this->name_en, 'my-MM' => $this->name_my],
            'legal_name' => $this->legal_name,
            'searchable_alias' => $this->searchable_alias,
            'category' => $this->category,
            'preferred_language' => $this->preferred_language,
            'acquisition_source' => $this->acquisition_source,
            'lifecycle_status' => $this->lifecycle_status,
            'settlement_policy' => $this->settlement_policy,
            'credit_hold' => $this->credit_hold,
            'price_book' => $this->priceBook ? ['id' => $this->priceBook->public_id, 'code' => $this->priceBook->code, 'name' => ['en' => $this->priceBook->name_en, 'my-MM' => $this->priceBook->name_my]] : null,
            'acquiring_sales_profile' => $this->acquiringSalesProfile ? ['id' => $this->acquiringSalesProfile->public_id, 'code' => $this->acquiringSalesProfile->code, 'name' => ['en' => $this->acquiringSalesProfile->name_en, 'my-MM' => $this->acquiringSalesProfile->name_my]] : null,
            'primary_outlet' => $outlet ? ['id' => $outlet->public_id, 'code' => $outlet->code, 'name' => ['en' => $outlet->name_en, 'my-MM' => $outlet->name_my], 'status' => $outlet->status] : null,
            'primary_contact' => $this->primaryContact ? ['id' => $this->primaryContact->public_id, 'name' => $this->primaryContact->name, 'phone' => $this->primaryContact->phone, 'email' => $this->primaryContact->email] : null,
            'primary_address' => $address ? ['id' => $address->public_id, 'label' => $address->label, 'area' => ['id' => $address->area->public_id, 'code' => $address->area->code, 'name' => ['en' => $address->area->name_en, 'my-MM' => $address->area->name_my]], 'township' => $address->township, 'ward_village' => $address->ward_village, 'street_address' => $address->street_address, 'landmark' => $address->landmark, 'delivery_note' => $address->delivery_note, 'latitude' => $address->latitude, 'longitude' => $address->longitude, 'service_window_start' => $address->service_window_start ? substr($address->service_window_start, 0, 5) : null, 'service_window_end' => $address->service_window_end ? substr($address->service_window_end, 0, 5) : null] : null,
            'way_membership' => $assignment ? ['id' => $assignment->public_id, 'way' => ['id' => $assignment->way->public_id, 'code' => $assignment->way->code, 'name' => ['en' => $assignment->way->name_en, 'my-MM' => $assignment->way->name_my]], 'effective_from' => $assignment->effective_from?->toDateString(), 'effective_to' => $assignment->effective_to?->toDateString(), 'status' => $assignment->status] : null,
            'version' => $this->lock_version,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
