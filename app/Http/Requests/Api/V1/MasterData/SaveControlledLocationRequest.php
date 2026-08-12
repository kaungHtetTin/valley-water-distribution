<?php

namespace App\Http\Requests\Api\V1\MasterData;

use App\Support\Tenancy\OrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveControlledLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['code', 'document_type', 'currency'] as $field) {
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

        return match ($this->route('type')) {
            'zones' => [
                'warehouse_public_id' => ['required', 'string', 'size:26', Rule::exists('warehouses', 'public_id')->where($active)],
                'code' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9_-]+$/'], 'name_en' => ['required', 'string', 'max:160'], 'name_my' => ['nullable', 'string', 'max:160'],
                'zone_type' => ['required', Rule::in(['storage', 'staging', 'quarantine', 'returns', 'damage'])], 'temperature_class' => ['required', Rule::in(['ambient', 'cool', 'frozen'])],
                'sort_order' => ['required', 'integer', 'min:0', 'max:999999'], 'status' => ['required', Rule::in(['active', 'inactive'])], 'version' => $version,
            ],
            'bins' => [
                'zone_public_id' => ['required', 'string', 'size:26', Rule::exists('warehouse_zones', 'public_id')->where($active)],
                'code' => ['required', 'string', 'max:48', 'regex:/^[A-Z0-9_-]+$/'], 'label' => ['required', 'string', 'max:160'],
                'bin_type' => ['required', Rule::in(['bulk', 'pick', 'hold'])], 'capacity_units' => ['nullable', 'numeric', 'min:0', 'max:99999999999999'],
                'sort_order' => ['required', 'integer', 'min:0', 'max:999999'], 'status' => ['required', Rule::in(['active', 'inactive'])], 'version' => $version,
            ],
            'replenishment' => [
                'warehouse_public_id' => ['required', 'string', 'size:26', Rule::exists('warehouses', 'public_id')->where($active)],
                'sku_public_id' => ['required', 'string', 'size:26', Rule::exists('skus', 'public_id')->where($active)],
                'safety_stock' => ['required', 'numeric', 'min:0', 'lte:reorder_point'], 'reorder_point' => ['required', 'numeric', 'min:0', 'lte:target_stock'],
                'target_stock' => ['required', 'numeric', 'min:0'], 'replenishment_lead_days' => ['required', 'integer', 'min:0', 'max:365'],
                'status' => ['required', Rule::in(['active', 'inactive'])], 'version' => $version,
            ],
            'cash' => [
                'branch_public_id' => ['nullable', 'string', 'size:26', Rule::exists('branches', 'public_id')->where($active)],
                'code' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9_-]+$/'], 'name_en' => ['required', 'string', 'max:160'], 'name_my' => ['nullable', 'string', 'max:160'],
                'location_type' => ['required', Rule::in(['cashier', 'safe', 'change_float', 'cash_in_transit'])], 'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
                'description' => ['nullable', 'string', 'max:2000'], 'status' => ['required', Rule::in(['active', 'inactive'])], 'version' => $version,
            ],
            default => [],
        };
    }
}
