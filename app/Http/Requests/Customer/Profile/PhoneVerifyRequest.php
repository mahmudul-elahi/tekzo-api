<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer\Profile;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

class PhoneVerifyRequest extends FormRequest
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
            'phone_number' => ['required', 'string'],
            'code' => ['required'],
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
