<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer\Auth;

use App\Support\PhoneNumber;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Customers log in with their email address or mobile number in the "login" field.
 * The older "email" field is still accepted for clients that send it.
 */
class LoginRequest extends FormRequest
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
            'login' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $isValid = $this->isEmail()
                        ? filter_var($value, FILTER_VALIDATE_EMAIL) !== false
                        : PhoneNumber::isValid((string) $value);

                    if (! $isValid) {
                        $fail('Enter a valid email address or mobile number.');
                    }
                },
            ],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Whether the customer logged in with an email address rather than a mobile number.
     */
    public function isEmail(): bool
    {
        return str_contains((string) $this->input('login'), '@');
    }

    protected function prepareForValidation(): void
    {
        $login = $this->input('login', $this->input('email'));

        if (! is_string($login)) {
            return;
        }

        $login = trim($login);

        $this->merge([
            'login' => str_contains($login, '@') ? $login : PhoneNumber::normalize($login),
        ]);
    }
}
