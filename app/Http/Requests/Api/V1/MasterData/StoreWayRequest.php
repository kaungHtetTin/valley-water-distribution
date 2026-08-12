<?php

namespace App\Http\Requests\Api\V1\MasterData;

use App\Support\Tenancy\OrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWayRequest extends FormRequest
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

        return [
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('ways', 'code')->where('organization_id', $organizationId)],
            'name_en' => ['required', 'string', 'max:160'],
            'name_my' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'area_public_id' => ['required', 'string', 'size:26', Rule::exists('areas', 'public_id')->where(fn ($query) => $query->where('organization_id', $organizationId)->where('status', 'active'))],
            'default_warehouse_public_id' => ['nullable', 'string', 'size:26', Rule::exists('warehouses', 'public_id')->where(fn ($query) => $query->where('organization_id', $organizationId)->where('status', 'active'))],
            'boundary_description' => ['nullable', 'string', 'max:3000'],
            'service_days' => ['required', 'array', 'min:1', 'max:7'],
            'service_days.*' => ['required', 'distinct', Rule::in(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'])],
            'delivery_window_start' => ['nullable', 'date_format:H:i', 'required_with:delivery_window_end'],
            'delivery_window_end' => ['nullable', 'date_format:H:i', 'required_with:delivery_window_start', 'after:delivery_window_start'],
            'effective_from' => ['required', 'date'],
            'change_reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
