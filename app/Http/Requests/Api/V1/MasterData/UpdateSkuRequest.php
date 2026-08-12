<?php

namespace App\Http\Requests\Api\V1\MasterData;

use App\Models\Sku;
use App\Support\Tenancy\OrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSkuRequest extends FormRequest
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
        if ($this->has('barcode')) {
            $this->merge(['barcode' => trim((string) $this->input('barcode')) ?: null]);
        }
    }

    public function rules(): array
    {
        $organizationId = app(OrganizationContext::class)->id();
        $skuId = Sku::query()->where('organization_id', $organizationId)->where('public_id', $this->route('sku'))->value('id');

        return [
            'version' => ['required', 'integer', 'min:1'],
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('skus', 'code')->where('organization_id', $organizationId)->ignore($skuId)],
            'name_en' => ['required', 'string', 'max:180'],
            'name_my' => ['nullable', 'string', 'max:180'],
            'size_label' => ['nullable', 'string', 'max:60'],
            'barcode' => ['nullable', 'string', 'max:80', Rule::unique('skus', 'barcode')->where('organization_id', $organizationId)->ignore($skuId)],
            'volume_ml' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
            'weight_grams' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
            'shelf_life_days' => ['nullable', 'integer', 'min:1', 'max:36500'],
            'track_lot' => ['required', 'boolean'],
            'track_expiry' => ['required', 'boolean'],
            'is_returnable' => ['required', 'boolean'],
            'minimum_order_quantity' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'order_step_quantity' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'minimum_delivery_quantity' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'sale_status' => ['required', Rule::in(['saleable', 'temporarily_unavailable', 'not_for_sale'])],
            'active_from' => ['nullable', 'date'],
            'active_to' => ['nullable', 'date', 'after_or_equal:active_from'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
