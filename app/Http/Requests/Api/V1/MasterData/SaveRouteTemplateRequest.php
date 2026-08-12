<?php

namespace App\Http\Requests\Api\V1\MasterData;

use App\Models\RouteTemplate;
use App\Support\Tenancy\OrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRouteTemplateRequest extends FormRequest
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
        $templateId = RouteTemplate::query()->where('organization_id', $organizationId)->where('public_id', $this->route('template'))->value('id');
        $active = fn ($query) => $query->where('organization_id', $organizationId)->where('status', 'active');

        return [
            'branch_public_id' => ['required', 'string', 'size:26', Rule::exists('branches', 'public_id')->where($active)],
            'source_warehouse_public_id' => ['required', 'string', 'size:26', Rule::exists('warehouses', 'public_id')->where($active)],
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('route_templates', 'code')->where('organization_id', $organizationId)->ignore($templateId)],
            'name_en' => ['required', 'string', 'max:160'],
            'name_my' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'service_days' => ['required', 'array', 'min:1', 'max:7'],
            'service_days.*' => ['required', 'distinct', Rule::in(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'])],
            'departure_time' => ['nullable', 'date_format:H:i'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'way_public_ids' => ['required', 'array', 'min:1', 'max:100'],
            'way_public_ids.*' => ['required', 'string', 'size:26', 'distinct', Rule::exists('ways', 'public_id')->where($active)],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'version' => [Rule::requiredIf($this->route('template') !== null), 'nullable', 'integer', 'min:1'],
        ];
    }
}
