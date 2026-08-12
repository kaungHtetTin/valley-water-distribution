<?php

namespace App\Http\Requests\Api\V1\MasterData;

use App\Support\Tenancy\OrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCatalogSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['code', 'currency'] as $field) {
            if ($this->has($field)) {
                $this->merge([$field => strtoupper(trim((string) $this->input($field)))]);
            }
        }
    }

    public function rules(): array
    {
        $organizationId = app(OrganizationContext::class)->id();
        $active = fn ($query) => $query->where('organization_id', $organizationId)->where('status', 'active');
        $version = [Rule::requiredIf($this->route('record') !== null), 'nullable', 'integer', 'min:1'];
        $commonStatus = ['required', Rule::in(['active', 'inactive'])];

        return match ($this->route('type')) {
            'categories' => [
                'code' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9_-]+$/'],
                'name_en' => ['required', 'string', 'max:120'],
                'name_my' => ['nullable', 'string', 'max:120'],
                'status' => $commonStatus,
                'version' => $version,
            ],
            'brands' => [
                'code' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9_-]+$/'],
                'name_en' => ['required', 'string', 'max:160'],
                'name_my' => ['nullable', 'string', 'max:160'],
                'status' => $commonStatus,
                'version' => $version,
            ],
            'products' => [
                'brand_public_id' => ['required', 'string', 'size:26', Rule::exists('brands', 'public_id')->where($active)],
                'category_public_id' => ['nullable', 'string', 'size:26', Rule::exists('product_categories', 'public_id')->where($active)],
                'code' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9_-]+$/'],
                'name_en' => ['required', 'string', 'max:160'],
                'name_my' => ['nullable', 'string', 'max:160'],
                'description' => ['nullable', 'string', 'max:2000'],
                'active_from' => ['nullable', 'date'],
                'active_to' => ['nullable', 'date', 'after_or_equal:active_from'],
                'status' => $commonStatus,
                'version' => $version,
            ],
            'units' => [
                'code' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9_-]+$/'],
                'name_en' => ['required', 'string', 'max:120'],
                'name_my' => ['nullable', 'string', 'max:120'],
                'symbol' => ['required', 'string', 'max:24'],
                'dimension' => ['required', Rule::in(['quantity', 'volume', 'weight', 'length'])],
                'decimal_places' => ['required', 'integer', 'min:0', 'max:4'],
                'status' => $commonStatus,
                'version' => $version,
            ],
            'price-types' => [
                'code' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9_-]+$/'],
                'name_en' => ['required', 'string', 'max:120'],
                'name_my' => ['nullable', 'string', 'max:120'],
                'precedence' => ['required', 'integer', 'min:0', 'max:65535'],
                'requires_approval' => ['required', 'boolean'],
                'status' => $commonStatus,
                'version' => $version,
            ],
            'price-books' => [
                'branch_public_id' => ['nullable', 'string', 'size:26', Rule::exists('branches', 'public_id')->where($active)],
                'price_type_public_id' => ['required', 'string', 'size:26', Rule::exists('price_types', 'public_id')->where($active)],
                'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9_-]+$/'],
                'name_en' => ['required', 'string', 'max:160'],
                'name_my' => ['nullable', 'string', 'max:160'],
                'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
                'scope_type' => ['required', Rule::in(['organization_default', 'branch_default', 'customer_segment', 'customer_specific'])],
                'effective_from' => ['required', 'date'],
                'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
                'status' => $commonStatus,
                'version' => $version,
            ],
            default => [],
        };
    }
}
