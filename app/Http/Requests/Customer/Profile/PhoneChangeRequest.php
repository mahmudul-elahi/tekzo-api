<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer\Profile;

use App\Support\PhoneNumber;
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

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('phone_number'))) {
            $this->merge([
                'phone_number' => PhoneNumber::normalize($this->input('phone_number')),
            ]);
        }
    }
}
