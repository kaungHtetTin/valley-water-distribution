<?php

namespace App\Http\Requests\Api\V1\MasterData;

use App\Support\Tenancy\OrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePriceBookItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $organizationId = app(OrganizationContext::class)->id();

        return [
            'price_book_public_id' => ['required', 'string', 'size:26', Rule::exists('price_books', 'public_id')->where(fn ($query) => $query->where('organization_id', $organizationId)->where('status', 'active'))],
            'sku_public_id' => ['required', 'string', 'size:26', Rule::exists('skus', 'public_id')->where(fn ($query) => $query->where('organization_id', $organizationId)->where('status', 'active'))],
            'uom_public_id' => ['required', 'string', 'size:26', Rule::exists('units_of_measure', 'public_id')->where(fn ($query) => $query->where('organization_id', $organizationId)->where('status', 'active'))],
            'unit_price_minor' => ['required', 'integer', 'min:0', 'max:999999999999999'],
            'minimum_quantity' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}
