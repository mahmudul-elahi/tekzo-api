<?php

declare(strict_types=1);

namespace App\Http\Requests\SellDeviceRequest;

use Illuminate\Foundation\Http\FormRequest;

class SellDeviceImageChunkRequest extends FormRequest
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
            'file' => ['required', 'file', 'max:2048'],
            'identifier' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'filename' => ['required', 'string'],
            'chunk_index' => ['required', 'integer', 'min:0'],
            'total_chunks' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }
}
