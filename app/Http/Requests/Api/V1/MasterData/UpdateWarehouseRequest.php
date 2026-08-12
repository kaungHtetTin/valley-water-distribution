<?php

namespace App\Http\Requests\Api\V1\MasterData;

use App\Models\Warehouse;
use App\Support\Tenancy\OrganizationContext;
use Illuminate\Validation\Rule;

class UpdateWarehouseRequest extends StoreWarehouseRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $organizationId = app(OrganizationContext::class)->id();
        $warehouseId = Warehouse::query()->where('organization_id', $organizationId)->where('public_id', $this->route('warehouse'))->value('id');
        $rules['code'] = ['required', 'string', 'max:32', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('warehouses', 'code')->where('organization_id', $organizationId)->ignore($warehouseId)];
        $rules['version'] = ['required', 'integer', 'min:1'];

        return $rules;
    }
}
