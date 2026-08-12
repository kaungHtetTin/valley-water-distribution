<?php

namespace App\Http\Requests\Api\V1\CustomerSales;

use App\Support\Tenancy\OrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $outlet = $this->input('outlet', []);
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'outlet' => [...$outlet, 'code' => strtoupper(trim((string) ($outlet['code'] ?? '')))],
        ]);
    }

    public function rules(): array
    {
        $organizationId = app(OrganizationContext::class)->id();

        return $this->rulesFor($organizationId,
            Rule::unique('client_accounts', 'code')->where('organization_id', $organizationId),
            Rule::unique('client_outlets', 'code')->where('organization_id', $organizationId),
        );
    }

    protected function rulesFor(int $organizationId, mixed $accountCodeRule, mixed $outletCodeRule): array
    {
        return [
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9_-]+$/', $accountCodeRule],
            'name_en' => ['required', 'string', 'max:180'],
            'name_my' => ['nullable', 'string', 'max:180'],
            'legal_name' => ['nullable', 'string', 'max:180'],
            'searchable_alias' => ['nullable', 'string', 'max:180'],
            'category' => ['nullable', 'string', 'max:80'],
            'preferred_language' => ['required', Rule::in(['en', 'my-MM'])],
            'acquisition_source' => ['nullable', 'string', 'max:80'],
            'lifecycle_status' => ['required', Rule::in(['prospect', 'pending_verification', 'active', 'suspended'])],
            'price_book_id' => ['nullable', 'string', 'size:26', Rule::exists('price_books', 'public_id')->where(fn ($query) => $query->where('organization_id', $organizationId)->where('status', 'active'))],
            'acquiring_sales_profile_id' => ['nullable', 'string', 'size:26', Rule::exists('foundation_master_records', 'public_id')->where(fn ($query) => $query->where('organization_id', $organizationId)->where('type', 'sales-profiles')->where('status', 'active'))],
            'outlet' => ['required', 'array'],
            'outlet.code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9_-]+$/', $outletCodeRule],
            'outlet.name_en' => ['required', 'string', 'max:180'],
            'outlet.name_my' => ['nullable', 'string', 'max:180'],
            'contact' => ['required', 'array'],
            'contact.name' => ['required', 'string', 'max:180'],
            'contact.phone' => ['required', 'string', 'max:40', 'regex:/^[+()0-9\s-]{6,40}$/'],
            'contact.email' => ['nullable', 'email:rfc', 'max:180'],
            'address' => ['required', 'array'],
            'address.area_id' => ['required', 'string', 'size:26', Rule::exists('areas', 'public_id')->where(fn ($query) => $query->where('organization_id', $organizationId)->where('status', 'active'))],
            'address.label' => ['nullable', 'string', 'max:80'],
            'address.township' => ['nullable', 'string', 'max:120'],
            'address.ward_village' => ['nullable', 'string', 'max:160'],
            'address.street_address' => ['required', 'string', 'max:2000'],
            'address.landmark' => ['nullable', 'string', 'max:255'],
            'address.delivery_note' => ['nullable', 'string', 'max:2000'],
            'address.latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:address.longitude'],
            'address.longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:address.latitude'],
            'address.service_window_start' => ['nullable', 'date_format:H:i', 'required_with:address.service_window_end'],
            'address.service_window_end' => ['nullable', 'date_format:H:i', 'required_with:address.service_window_start', 'after:address.service_window_start'],
            'way_id' => ['required', 'string', 'size:26', Rule::exists('ways', 'public_id')->where(fn ($query) => $query->where('organization_id', $organizationId)->where('status', 'active'))],
            'way_effective_from' => ['required', 'date'],
            'change_reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
