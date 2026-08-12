<?php

namespace App\Http\Requests\Api\V1\CustomerSales;

use App\Models\ClientAccount;
use App\Support\Tenancy\OrganizationContext;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends StoreCustomerRequest
{
    public function rules(): array
    {
        $organizationId = app(OrganizationContext::class)->id();
        $account = ClientAccount::query()->with('primaryOutlet')->where('organization_id', $organizationId)->where('public_id', (string) $this->route('customer'))->first();
        $rules = $this->rulesFor($organizationId,
            Rule::unique('client_accounts', 'code')->where('organization_id', $organizationId)->ignore($account?->id),
            Rule::unique('client_outlets', 'code')->where('organization_id', $organizationId)->ignore($account?->primaryOutlet?->id),
        );
        $rules['version'] = ['required', 'integer', 'min:1'];
        $rules['change_reason'] = ['required', 'string', 'min:5', 'max:500'];

        return $rules;
    }
}
