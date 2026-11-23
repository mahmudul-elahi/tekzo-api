<?php

declare(strict_types=1);

namespace App\Http\Requests\BuyNow;

use Illuminate\Foundation\Http\FormRequest;

class GuestCouponCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'coupon_code' => ['required', 'string'],
            'carts' => ['required', 'array'],
            'carts.*.product_id' => ['required', 'exists:products,id'],
            'carts.*.product_variant_id' => ['required', 'exists:product_variants,id'],
            'carts.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
