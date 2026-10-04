<?php

namespace App\Http\Requests\Game;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseBusinessRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'business_id' => ['required', 'integer'],
        ];
    }
}
