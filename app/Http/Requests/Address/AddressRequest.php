<?php

declare(strict_types=1);

namespace App\Http\Requests\Address;

use App\Models\Address\CustomerAddress;
use App\Support\PhoneNumber;
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
            'recipient_name' => ['nullable', 'required_with:recipient_phone', 'string', 'max:150'],
            'recipient_phone' => ['nullable', 'required_with:recipient_name', 'string', 'regex:'.PhoneNumber::PATTERN],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('recipient_phone')) && trim($this->input('recipient_phone')) !== '') {
            $this->merge([
                'recipient_phone' => PhoneNumber::normalize($this->input('recipient_phone')),
            ]);
        }
    }

    public function messages(): array
    {
        return [
            'new_district_id.required' => 'The district is required',
            'new_district_id.exists' => 'The district does not exist',
            'area_id.required' => 'The area is required',
            'area_id.exists' => 'The area does not exist',
            'recipient_name.required_with' => 'Enter the name of the person receiving the order',
            'recipient_phone.required_with' => 'Enter the phone number of the person receiving the order',
            'recipient_phone.regex' => 'Enter a valid Bangladeshi mobile number for the recipient',
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
