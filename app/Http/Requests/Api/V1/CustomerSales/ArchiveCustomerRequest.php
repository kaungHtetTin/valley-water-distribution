<?php

namespace App\Http\Requests\Api\V1\CustomerSales;

use Illuminate\Foundation\Http\FormRequest;

class ArchiveCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['version' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'min:10', 'max:500']];
    }
}
