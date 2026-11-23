<?php

namespace App\Http\Resources\Elite;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EliteResource extends JsonResource
{
    public function __construct($resource, private readonly ?int $customerPoints = null)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $available = $this->customerPoints !== null
            ? $this->customerPoints >= $this->point
            : null;

        return [
            'id' => $this->id,
            'status_name' => $this->status_name,
            'tagline' => $this->tagline,
            'logo' => $this->logo,
            'banner' => $this->banner,
            'point' => $this->point,
            'benefits' => collect($this->benefits ?? [])->map(fn ($b) => [
                'title' => $b,
                'available' => $available,
            ])->values(),
        ];
    }
}
