<?php

namespace App\Http\Requests\Customer\Review;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
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
            'order_detail_id' => 'required|integer|exists:order_lists,id',
            'rating' => 'required|numeric|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
            'review_image' => ['nullable', 'file', 'mimes:jpeg,png,jpg,svg', 'max:1024'],
        ];
    }
}
