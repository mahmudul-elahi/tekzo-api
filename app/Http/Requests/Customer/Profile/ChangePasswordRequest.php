<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer\Profile;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
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
            'current_password' => ['required'],
            'password' => ['required', 'min:8'],
            'password_confirmation' => ['required_with:password', 'same:password'],
        ];
    }
}
