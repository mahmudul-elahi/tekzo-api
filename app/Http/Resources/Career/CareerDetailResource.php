<?php

namespace App\Http\Resources\Career;

use App\Models\Career\Career;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One job opening with its text fields split into lines and its own SEO block.
 *
 * @mixin Career
 */
class CareerDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'heading' => $this->heading,
            'slug' => $this->slug,
            'vacancies' => $this->vacancies,
            'type' => ucfirst(str_replace('_', ' ', $this->type)),
            'salary' => $this->salary,
            'image' => $this->resource?->image,
            'qualification' => CareerResource::htmlToLines($this->qualification),
            'deadline' => $this->deadline,
            'description' => CareerResource::htmlToLines($this->description),
            'benefits' => CareerResource::htmlToLines($this->benefits),
            'keywords' => $this->keywords,

            'seoData' => [
                'title' => $this->heading,
                'description' => $this->heading,
                'tags' => $this->keywords,
                'siteName' => config('app.name'),
                'ogImage' => $this->resource?->image ?? null,
                'canonicalUrl' => config('services.front_base_url').'/careers/apply/'.$this->slug,
            ],
        ];
    }
}
