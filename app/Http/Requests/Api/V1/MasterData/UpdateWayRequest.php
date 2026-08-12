<?php

namespace App\Http\Requests\Api\V1\MasterData;

use App\Models\Way;
use App\Support\Tenancy\OrganizationContext;
use Illuminate\Validation\Rule;

class UpdateWayRequest extends StoreWayRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $organizationId = app(OrganizationContext::class)->id();
        $wayId = Way::query()->where('organization_id', $organizationId)->where('public_id', $this->route('way'))->value('id');
        $rules['version'] = ['required', 'integer', 'min:1'];
        $rules['code'] = ['required', 'string', 'max:32', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('ways', 'code')->where('organization_id', $organizationId)->ignore($wayId)];
        $rules['change_reason'] = ['required', 'string', 'min:3', 'max:500'];

        return $rules;
    }
}
