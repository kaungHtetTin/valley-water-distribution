<?php

namespace App\Http\Requests\Api\V1\MasterData;

use App\Models\Branch;
use App\Support\Tenancy\OrganizationContext;
use Illuminate\Validation\Rule;

class UpdateBranchRequest extends StoreBranchRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $organizationId = app(OrganizationContext::class)->id();
        $branchId = Branch::query()->where('organization_id', $organizationId)->where('public_id', $this->route('branch'))->value('id');
        $rules['code'] = ['required', 'string', 'max:32', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('branches', 'code')->where('organization_id', $organizationId)->ignore($branchId)];
        $rules['version'] = ['required', 'integer', 'min:1'];

        return $rules;
    }
}
