<?php

namespace App\Http\Requests\Api\V1\MasterData;

use App\Models\DocumentSequence;
use App\Support\Tenancy\OrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveDocumentSequenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('document_type')) {
            $this->merge(['document_type' => strtoupper(trim((string) $this->input('document_type')))]);
        }
    }

    public function rules(): array
    {
        $organizationId = app(OrganizationContext::class)->id();
        $sequenceId = DocumentSequence::query()->where('organization_id', $organizationId)->where('public_id', $this->route('sequence'))->value('id');

        return [
            'branch_public_id' => ['nullable', 'string', 'size:26', Rule::exists('branches', 'public_id')->where(fn ($query) => $query->where('organization_id', $organizationId)->where('status', 'active'))],
            'document_type' => ['required', 'string', 'max:48', 'regex:/^[A-Z0-9_-]+$/'],
            'name' => ['required', 'string', 'max:160'],
            'prefix' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9_\/-]+$/'],
            'suffix' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9_\/-]+$/'],
            'padding' => ['required', 'integer', 'min:3', 'max:12'],
            'next_number' => ['required', 'integer', 'min:1'],
            'reset_policy' => ['required', Rule::in(['never', 'yearly', 'monthly'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'version' => [Rule::requiredIf($sequenceId !== null), 'nullable', 'integer', 'min:1'],
        ];
    }
}
