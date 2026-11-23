<?php

namespace App\Http\Requests\Customer\Profile;

use Illuminate\Foundation\Http\FormRequest;

class PhoneChangeRequest extends FormRequest
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
            'phone_number' => [
                'required',
                'string',
                'regex:/^(\+?88)?01[0-9]{9}$/',
                'unique:contacts,phone,'.auth()->guard('customer')->id(),
            ],
        ];
    }
}
