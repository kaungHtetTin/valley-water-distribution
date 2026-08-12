<?php

namespace App\Http\Requests\Api\V1\MasterData;

use App\Support\Tenancy\OrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviseSkuConversionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $organizationId = app(OrganizationContext::class)->id();

        return [
            'version' => ['required', 'integer', 'min:1'],
            'uom_public_id' => ['required', 'string', 'size:26', Rule::exists('units_of_measure', 'public_id')->where(fn ($query) => $query->where('organization_id', $organizationId)->where('status', 'active'))],
            'factor_to_base' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'effective_from' => ['required', 'date'],
            'is_selling_unit' => ['required', 'boolean'],
            'is_kpi_base' => ['required', 'boolean'],
        ];
    }
}
