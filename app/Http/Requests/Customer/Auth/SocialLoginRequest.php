<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The token the app got from the provider: a Google or Facebook access token, or an Apple ID token.
 */
class SocialLoginRequest extends FormRequest
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
            'token' => ['required', 'string', 'max:8192'],
            'name' => ['nullable', 'string', 'max:150'],
        ];
    }
}
