<?php

declare(strict_types=1);

namespace App\Http\Requests\Warranty;

use Illuminate\Foundation\Http\FormRequest;

class WarrantyClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'serial_number' => ['required', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
