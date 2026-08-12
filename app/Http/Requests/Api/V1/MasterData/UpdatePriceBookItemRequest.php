<?php

namespace App\Http\Requests\Api\V1\MasterData;

class UpdatePriceBookItemRequest extends StorePriceBookItemRequest
{
    public function rules(): array
    {
        return ['version' => ['required', 'integer', 'min:1'], ...parent::rules()];
    }
}
