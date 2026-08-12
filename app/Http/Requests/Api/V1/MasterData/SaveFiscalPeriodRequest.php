<?php

namespace App\Http\Requests\Api\V1\MasterData;

use App\Models\FiscalPeriod;
use App\Support\Tenancy\OrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveFiscalPeriodRequest extends FormRequest
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
        $periodId = FiscalPeriod::query()->where('organization_id', $organizationId)->where('public_id', $this->route('period'))->value('id');

        return [
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('fiscal_periods', 'code')->where('organization_id', $organizationId)->ignore($periodId)],
            'name' => ['required', 'string', 'max:160'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'status' => ['required', Rule::in(['open', 'inactive'])],
            'version' => [Rule::requiredIf($this->route('period') !== null), 'nullable', 'integer', 'min:1'],
        ];
    }
}
