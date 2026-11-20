<?php

namespace App\Http\Resources\Reels;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReelsVideoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'video_link' => $this->video_link,
            'product' => new ReelsVideoProductResource($this->product),
        ];
    }
}
