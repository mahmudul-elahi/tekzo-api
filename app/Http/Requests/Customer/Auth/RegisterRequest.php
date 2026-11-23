<?php

namespace App\Http\Requests\Customer\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'indisposable', 'unique:contacts,email'],
            'phone' => [
                'required',
                'string',
                'regex:/^\+8801[3-9]\d{8}$/',
                'phone:BD',
                'unique:contacts,phone',
            ],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'This email is already registered.',
            'phone.unique' => 'This phone number is already registered.',
            'password.confirmed' => 'Password confirmation does not match.',
            'phone' => 'The :attribute field must be a valid phone number.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->phone) {

            // remove everything except numbers
            $phone = preg_replace('/[^0-9]/', '', $this->phone);

            // 017xxxxxxxx
            if (str_starts_with($phone, '01')) {
                $phone = '+88'.$phone;
            }

            // 8801xxxxxxxx
            elseif (str_starts_with($phone, '8801')) {
                $phone = '+'.$phone;
            }

            // 801xxxxxxxx (missing one 8)
            elseif (str_starts_with($phone, '801')) {
                $phone = '+8'.$phone;
            }

            $this->merge([
                'phone' => $phone,
            ]);
        }
    }
}
