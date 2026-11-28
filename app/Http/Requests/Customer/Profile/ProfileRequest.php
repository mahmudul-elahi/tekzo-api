<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer\Profile;

use App\Support\PhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'gender' => ['nullable', 'in:male,female,other'],
            'date_of_birth' => ['nullable', 'date'],
            'secondary_phone' => ['nullable', 'string', 'regex:'.PhoneNumber::PATTERN],
            'address' => ['nullable', 'string', 'max:191'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'secondary_phone.regex' => 'Enter a valid Bangladeshi mobile number.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('secondary_phone')) && trim($this->input('secondary_phone')) !== '') {
            $this->merge([
                'secondary_phone' => PhoneNumber::normalize($this->input('secondary_phone')),
            ]);
        }
    }
}
