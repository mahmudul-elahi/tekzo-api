<?php

namespace App\Http\Requests\BuyNow;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuestOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A line's care_id must belong to the matching care table: pre-owned lines use
     * pre_owned_cares, product lines use product_cares.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'payment_method' => ['required', 'in:cod,online,bkash'],
            'booking_amount_only' => ['nullable', 'in:0,1'],
            'email' => ['required', 'indisposable', 'email', 'max:100'],
            'phone' => ['required', 'max:13'],
            'full_name' => ['required', 'max:100'],
            'district_id' => ['required', 'exists:new_districts,id'],
            'area_id' => ['required', 'exists:areas,id'],
            'address' => ['required', 'max:500'],
            'note' => ['nullable', 'max:500'],
            'is_agree' => ['required', 'in:1'],
            'carts' => ['required', 'array'],
            'carts.*.product_id' => ['required', 'exists:products,id'],
            'carts.*.product_variant_id' => ['required', 'exists:product_variants,id'],
            'carts.*.pre_owned_id' => ['nullable', 'exists:pre_owneds,id'],
            'carts.*.quantity' => ['required', 'integer', 'min:1'],
            'carts.*.gift_id' => ['nullable', Rule::exists('gift_product_variants', 'id')->whereNull('deleted_at')],
            'coupon_code' => ['nullable', 'string'],
        ];

        foreach ($this->input('carts', []) as $i => $cart) {
            $rules["carts.$i.care_id"] = ! empty($cart['pre_owned_id'])
                ? ['nullable', Rule::exists('pre_owned_cares', 'id')->whereNull('deleted_at')]
                : ['nullable', Rule::exists('product_cares', 'id')->whereNull('deleted_at')];
        }

        return $rules;
    }
}
