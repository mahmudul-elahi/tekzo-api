<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class ToggleCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cart_item_id' => ['required', 'exists:cart_items,id'],
            'is_select' => ['required', 'boolean'],
        ];
    }
}
