<?php

namespace App\Http\Requests\Api\V1\MasterData;

use App\Support\Tenancy\OrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        if ($this->has('code')) {
            $values['code'] = strtoupper(trim((string) $this->input('code')));
        }
        if ($this->has('currency')) {
            $values['currency'] = strtoupper(trim((string) $this->input('currency')));
        }
        $this->merge($values);
    }

    public function rules(): array
    {
        $organizationId = app(OrganizationContext::class)->id();

        return [
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('branches', 'code')->where('organization_id', $organizationId)],
            'name_en' => ['required', 'string', 'max:160'],
            'name_my' => ['nullable', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:2000'],
            'timezone' => ['required', 'timezone:all'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'business_day_start' => ['required', 'date_format:H:i'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
