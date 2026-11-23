<?php

namespace App\Http\Requests\Checkout;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
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
            'payment_method' => ['required', 'in:cod,online,bkash'],
            'shipping_address_id' => [
                'required',
                Rule::exists('customer_addresses', 'id')->where('contact_id', auth()->guard('customer')->id()),
            ],
            'booking_amount_only' => ['nullable', 'in:0,1'],
        ];
    }
}
