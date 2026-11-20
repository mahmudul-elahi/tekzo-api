<?php

namespace App\Http\Resources\SellDeviceRequest;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class SellDeviceRequestResource extends JsonResource
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
            'ad_title' => $this->ad_title,
            'category' => $this->whenLoaded('category', fn() => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),
            'brand' => $this->whenLoaded('brand', fn() => $this->brand ? [
                'id' => $this->brand->id,
                'name' => $this->brand->name,
                'slug' => $this->brand->slug,
            ] : null),
            'district' => $this->whenLoaded('newDistrict', fn() => $this->newDistrict ? [
                'id' => $this->newDistrict->id,
                'name' => $this->newDistrict->name,
            ] : null),
            'model' => $this->model,
            'condition' => $this->condition,
            'ram' => $this->ram,
            'storage' => $this->storage,
            'color' => $this->color,
            'is_exchange_possible' => $this->is_exchange_possible,
            'asking_price' => (float) $this->asking_price,
            'is_negotiable' => $this->is_negotiable,
            'video_url' => $this->video_url,
            'description' => $this->description,
            'phone_number' => $this->phone_number,
            'status' => $this->status,
            'images' => $this->whenLoaded('images', fn() => $this->images->map(fn($image) => [
                'id' => $image->id,
                'url' => self::imageUrl($image->image),
                'is_primary' => $image->is_primary,
            ])),
            'features' => $this->whenLoaded('features', fn() => $this->features->map(fn($feature) => [
                'id' => $feature->id,
                'feature_name' => $feature->feature_name,
                'feature_value' => $feature->feature_value,
            ])),
            'created_at' => $this->created_at,
        ];
    }

    private static function imageUrl(string $path): string
    {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk(config('filesystems.default'));

        return to_cdn_url($disk->url($path));
    }
}
