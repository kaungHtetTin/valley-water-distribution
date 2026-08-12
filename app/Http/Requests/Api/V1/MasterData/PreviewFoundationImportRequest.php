<?php

namespace App\Http\Requests\Api\V1\MasterData;

use Illuminate\Foundation\Http\FormRequest;

class PreviewFoundationImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_name' => ['required', 'string', 'max:255'], 'rows' => ['required', 'array', 'min:1', 'max:1000'],
            'rows.*.code' => ['required', 'string', 'max:40'], 'rows.*.name_en' => ['required', 'string', 'max:180'],
            'rows.*.name_my' => ['nullable', 'string', 'max:180'], 'rows.*.classification' => ['nullable', 'string', 'max:60'],
            'rows.*.status' => ['nullable', 'in:active,inactive'],
        ];
    }
}
