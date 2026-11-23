<?php

namespace App\Http\Resources\Order;

use App\Models\ProductVerify\ProductVerify;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductVerify
 */
class ProductVerifyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'phone' => $this->phone,
            'name' => $this->name,
            'barcode_number' => $this->barcode_number,
            'product_id' => $this->product_id,
            'created_at' => $this->created_at,
            'status' => $this->status,
            'claimed' => $this->claimed,
            'product' => $this->product ? [
                'id' => $this->product->id,
                'product_name' => $this->product->product_name,
                'product_image' => $this->product->product_image,
            ] : null,
        ];
    }
}
