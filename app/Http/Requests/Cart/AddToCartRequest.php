<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddToCartRequest extends FormRequest
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
            'variant_id' => ['required', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'pre_owned_id' => ['nullable', 'exists:pre_owneds,id'],
            'gift_id' => ['nullable', Rule::exists('gift_product_variants', 'id')->whereNull('deleted_at')],
            'care_id' => ! empty($this->input('pre_owned_id'))
                ? ['nullable', Rule::exists('pre_owned_cares', 'id')->whereNull('deleted_at')]
                : ['nullable', Rule::exists('product_cares', 'id')->whereNull('deleted_at')],
        ];
    }
}
