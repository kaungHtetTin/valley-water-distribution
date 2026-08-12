<?php

namespace App\Http\Requests\Api\V1\MasterData;

use App\Models\Area;
use App\Support\Tenancy\OrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAreaRequest extends FormRequest
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
        $areaId = Area::query()
            ->where('organization_id', $organizationId)
            ->where('public_id', $this->route('area'))
            ->value('id');

        return [
            'version' => ['required', 'integer', 'min:1'],
            'code' => [
                'required', 'string', 'max:32', 'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('areas', 'code')->where('organization_id', $organizationId)->ignore($areaId),
            ],
            'name_en' => ['required', 'string', 'max:160'],
            'name_my' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'parent_area_public_id' => [
                'nullable', 'string', 'size:26',
                Rule::exists('areas', 'public_id')->where(fn ($query) => $query
                    ->where('organization_id', $organizationId)
                    ->where('status', '!=', 'archived')),
            ],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999999'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
