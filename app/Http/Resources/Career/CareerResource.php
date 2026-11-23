<?php

namespace App\Http\Resources\Career;

use App\Models\Career\Career;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A job opening in the careers list.
 *
 * @mixin Career
 */
class CareerResource extends JsonResource
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
            'qualification' => $this->qualification,
            'deadline' => $this->deadline,
            'description' => $this->description,
            'benefits' => self::htmlToLines($this->benefits),
            'keywords' => $this->keywords,
        ];
    }

    /**
     * Rich text from the CMS as a list of non-empty plain-text lines.
     *
     * @return array<int, string>
     */
    public static function htmlToLines(?string $html): array
    {
        return array_values(
            array_filter(
                array_map(fn ($item) => html_entity_decode(trim($item)), explode("\n", strip_tags($html)))
            )
        );
    }
}
