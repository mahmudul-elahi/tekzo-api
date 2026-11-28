<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer\Profile;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The token the app got from the provider: a Google or Facebook access token, or an Apple ID token.
 */
class SocialAccountRequest extends FormRequest
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
        ];
    }
}
