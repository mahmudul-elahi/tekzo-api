<?php

namespace App\Http\Resources\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ProductReviewCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection->map(fn ($item) => [
                'id' => $item->id,
                'rating' => $item->rating,
                'comment' => $item->comment,
                'created_at' => $item->created_at,
                'customer' => $item->contact ? [
                    'id' => $item->contact->id,
                    'name' => $item->contact->name,
                    'profile_picture' => asset('asset/banner/all-product-banner.webp'),
                    'attachments' => [],
                ] : [
                    'id' => null,
                    'name' => $item->customer_name,
                    'profile_picture' => null,
                    'attachments' => [],
                ],
            ]),

            'pagination' => [
                'total' => $this->resource->total(),
                'per_page' => $this->resource->perPage(),
                'current_page' => $this->resource->currentPage(),
                'last_page' => $this->resource->lastPage(),
                'from' => $this->resource->firstItem(),
                'to' => $this->resource->lastItem(),
                'next_page_url' => $this->resource->nextPageUrl(),
                'prev_page_url' => $this->resource->previousPageUrl(),
            ],
        ];
    }
}
