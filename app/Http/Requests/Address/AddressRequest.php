<?php

namespace App\Http\Requests\Address;

use App\Models\Address\CustomerAddress;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AddressRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $address = $this->route('address');
        if ($address instanceof CustomerAddress) {
            return (int) $address->contact_id === (int) auth()->guard('customer')->id();
        }

        return true;
    }

    protected function failedAuthorization(): void
    {
        abort(responseMessage('Address not found', 404));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'new_district_id' => ['required', 'exists:new_districts,id'],
            'area_id' => ['required', 'exists:areas,id'],
            'street' => ['required', 'string', 'max:255'],
            'label' => ['required', 'in:home,office,others'],
            'is_default_shipping' => ['nullable', 'boolean'],
            'is_default_billing' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'new_district_id.required' => 'The district is required',
            'new_district_id.exists' => 'The district does not exist',
            'area_id.required' => 'The area is required',
            'area_id.exists' => 'The area does not exist',
        ];
    }

    public function attributes(): array
    {
        return [
            'new_district_id' => 'District',
            'area_id' => 'Area',
        ];
    }
}
