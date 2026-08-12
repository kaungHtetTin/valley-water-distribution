<?php

namespace App\Http\Requests\Api\V1\MasterData;

use App\Support\Tenancy\OrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
        }
    }

    public function rules(): array
    {
        $organizationId = app(OrganizationContext::class)->id();
        $activeForOrganization = fn ($query) => $query->where('organization_id', $organizationId)->where('status', 'active');

        return [
            'branch_public_id' => ['required', 'string', 'size:26', Rule::exists('branches', 'public_id')->where($activeForOrganization)],
            'area_public_id' => ['nullable', 'string', 'size:26', Rule::exists('areas', 'public_id')->where($activeForOrganization)],
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('warehouses', 'code')->where('organization_id', $organizationId)],
            'name_en' => ['required', 'string', 'max:160'],
            'name_my' => ['nullable', 'string', 'max:160'],
            'kind' => ['required', Rule::in(['distribution', 'satellite', 'transit', 'returns'])],
            'address' => ['nullable', 'string', 'max:2000'],
            'contact_name' => ['nullable', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'order_cutoff_time' => ['nullable', 'date_format:H:i'],
            'service_area_note' => ['nullable', 'string', 'max:3000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
