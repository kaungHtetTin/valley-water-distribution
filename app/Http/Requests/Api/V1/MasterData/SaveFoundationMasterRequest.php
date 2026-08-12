<?php

namespace App\Http\Requests\Api\V1\MasterData;

use App\Support\Tenancy\OrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveFoundationMasterRequest extends FormRequest
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
        $active = fn ($query) => $query->where('organization_id', $organizationId)->where('status', 'active');

        return [
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9_-]+$/'],
            'name_en' => ['required', 'string', 'max:180'],
            'name_my' => ['nullable', 'string', 'max:180'],
            'classification' => ['nullable', 'string', 'max:60'],
            'branch_public_id' => ['nullable', 'string', 'size:26', Rule::exists('branches', 'public_id')->where($active)],
            'area_public_id' => ['nullable', 'string', 'size:26', Rule::exists('areas', 'public_id')->where($active)],
            'way_public_id' => ['nullable', 'string', 'size:26', Rule::exists('ways', 'public_id')->where($active)],
            'price_book_public_id' => ['nullable', 'string', 'size:26', Rule::exists('price_books', 'public_id')->where($active)],
            'parent_public_id' => ['nullable', 'string', 'size:26', Rule::exists('foundation_master_records', 'public_id')->where(fn ($query) => $query->where('organization_id', $organizationId)->where('type', $this->route('type'))->where('status', 'active'))],
            'phone' => ['nullable', 'string', 'max:40'], 'email' => ['nullable', 'email', 'max:180'],
            'address' => ['nullable', 'string', 'max:2000'], 'registration_number' => ['nullable', 'string', 'max:100'],
            'metadata' => ['nullable', 'array'], 'sort_order' => ['required', 'integer', 'min:0', 'max:999999'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'version' => [Rule::requiredIf($this->route('record') !== null), 'nullable', 'integer', 'min:1'],
        ];
    }
}
