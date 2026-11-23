<?php

namespace App\Repositories\Slider;

use App\Enums\StatusEnum;
use App\Models\Slider\Slider;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class SliderRepository extends BaseRepository
{
    public function __construct(Slider $slider)
    {
        $this->model = $slider;
    }

    /**
     * @return Collection<int, Slider>
     */
    public function activeSlides(): Collection
    {
        return $this->activeForTenant()
            ->where('status', StatusEnum::Active->value)
            ->orderBy('numbering')
            ->get();
    }

    /**
     * Plain banners (no banner_feature).
     *
     * @return Collection<int, Slider>
     */
    public function plainBanners(): Collection
    {
        return $this->activeForTenant()
            ->where('status', StatusEnum::Bannar->value)
            ->whereNull('banner_feature')
            ->get();
    }

    /**
     * @param  array<int, string>  $features
     * @return Collection<int, Slider>
     */
    public function withBannerFeatures(array $features): Collection
    {
        return $this->activeForTenant()
            ->whereIn('banner_feature', $features)
            ->get();
    }

    /**
     * Active sliders of the current tenant with the public columns.
     */
    private function activeForTenant(): Builder
    {
        return $this->query()
            ->whereHas('tenant', fn ($query) => $query->where('slug', config('services.tenant_slug')))
            ->where('is_active', StatusEnum::Active->value)
            ->select([
                'id',
                'heading',
                'url',
                'image',
                'banner_feature',
            ]);
    }
}
