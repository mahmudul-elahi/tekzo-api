<?php

namespace App\Http\Requests\SellDeviceRequest;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SellDeviceRequestRequest extends FormRequest
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
            'ad_title' => ['required', 'string', 'max:120'],
            'category_id' => ['required', 'exists:categories,id'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'new_district_id' => ['nullable', 'exists:new_districts,id'],
            'model' => ['required', 'string', 'max:255'],
            'condition' => ['required', Rule::in(['brand_new', 'like_new', 'good', 'fair', 'poor'])],
            'ram' => ['nullable', 'string', 'max:50'],
            'storage' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:50'],
            'is_exchange_possible' => ['nullable', 'boolean'],
            'asking_price' => ['required', 'numeric', 'min:0'],
            'is_negotiable' => ['nullable', 'boolean'],
            'description' => ['required', 'string', 'max:2000'],
            'phone_number' => ['required', 'string', 'max:20'],
            'video_url' => ['nullable', 'string', 'url', 'max:255'],

            'images' => ['nullable', 'array'],
            'images.*.path' => ['required_with:images', 'string'],
            'images.*.is_primary' => ['nullable', 'boolean'],
            'remove_image_ids' => ['nullable', 'array'],
            'remove_image_ids.*' => ['integer', 'exists:sell_device_request_images,id'],

            'features' => ['nullable', 'array'],
            'features.*.feature_name' => ['required_with:features', 'string', 'max:100'],
            'features.*.feature_value' => ['required_with:features', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'ad_title' => 'Ad title',
            'category_id' => 'Category',
            'brand_id' => 'Brand',
            'new_district_id' => 'District',
            'asking_price' => 'Asking price',
            'phone_number' => 'Phone number',
            'video_url' => 'Video link',
        ];
    }
}
