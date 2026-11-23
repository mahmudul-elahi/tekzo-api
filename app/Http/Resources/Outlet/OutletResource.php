<?php

declare(strict_types=1);

namespace App\Http\Resources\Outlet;

use App\Models\Outlet\Outlet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An outlet in the outlets list.
 *
 * @mixin Outlet
 */
class OutletResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'address' => $this->address,
            'google_map_url' => $this->google_map_url,
            'image' => $this->image,
            'opens' => $this->opens,
            'closes' => $this->closes,
            'close_day' => $this->close_day,
        ];
    }
}
