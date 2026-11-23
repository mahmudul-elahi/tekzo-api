<?php

namespace App\Http\Requests\Wishlist;

use Illuminate\Foundation\Http\FormRequest;

class ToggleWishlistItemRequest extends FormRequest
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
            'cart_item_id' => ['required', 'exists:wishlist_items,id'],
            'is_select' => ['required', 'boolean'],
        ];
    }
}
