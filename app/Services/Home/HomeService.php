<?php

namespace App\Services\Home;

use App\Models\Brand\Brand;
use App\Models\News\News;
use App\Models\Offer\Offer;
use App\Models\PreOwned\PreOwned;
use App\Models\Product\Product;
use App\Models\Reels\ReelsVideo;
use App\Models\Slider\Slider;
use App\Repositories\Brand\BrandRepository;
use App\Repositories\News\NewsRepository;
use App\Repositories\Offer\OfferRepository;
use App\Repositories\PreOwned\PreOwnedRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Reels\ReelsVideoRepository;
use App\Repositories\Slider\SliderRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class HomeService
{
    public function __construct(
        private readonly SliderRepository $sliders,
        private readonly ProductRepository $products,
        private readonly OfferRepository $offers,
        private readonly BrandRepository $brands,
        private readonly NewsRepository $news,
        private readonly ReelsVideoRepository $reels,
        private readonly PreOwnedRepository $preOwneds,
    ) {}

    /**
     * Every slider and banner group shown on the home page.
     *
     * @return array{sliders: Collection<int, Slider>, banners: Collection<int, Slider>, flashSellBanner: ?Slider, newFeatureBanner: ?Slider, comingSoonBanner: ?Slider, fourCardBanners: Collection<int, Slider>, threeCardBanners: Collection<int, Slider>, homePageLargeBanner: ?Slider}
     */
    public function slidersAndBanners(): array
    {
        $sliders = $this->sliders->activeSlides();
        $banners = $this->sliders->plainBanners();

        $bannerFeatures = $this->sliders
            ->withBannerFeatures(['flash_sell', 'coming_soon', 'new_feature', 'home_large_banner'])
            ->keyBy('banner_feature');

        $fourCardBanners = $this->sliders->withBannerFeatures(['four_card']);
        $threeCardBanners = $this->sliders->withBannerFeatures(['three_card']);

        return [
            'sliders' => $sliders,
            'banners' => $banners,
            'flashSellBanner' => $bannerFeatures->get('flash_sell'),
            'newFeatureBanner' => $bannerFeatures->get('new_feature'),
            'comingSoonBanner' => $bannerFeatures->get('coming_soon'),
            'fourCardBanners' => $fourCardBanners,
            'threeCardBanners' => $threeCardBanners,
            'homePageLargeBanner' => $bannerFeatures->get('home_large_banner'),
        ];
    }

    /**
     * @param  array<int, string>  $columns
     * @return Collection<int, Brand>
     */
    public function activeBrands(array $columns): Collection
    {
        return $this->brands->activeOrdered($columns);
    }

    /**
     * @return Collection<int, Product>
     */
    public function flashSaleBlockProducts(): Collection
    {
        return $this->products->firstActiveForFlashSaleBlock();
    }

    /**
     * The latest active flash sale and its products, or null when there is none.
     *
     * @return array{offer: Offer, products: Collection<int, Product>}|null
     */
    public function currentFlashSale(): ?array
    {
        $offer = $this->offers->latestActiveFlashSale();

        if (! $offer instanceof Offer) {
            return null;
        }

        return [
            'offer' => $offer,
            'products' => $this->products->activeInOffer($offer->id),
        ];
    }

    /**
     * The public fields of a flash sale offer.
     *
     * @return array{id: int, slug: ?string, title: ?string, banner: ?string, start_at: mixed, end_at: mixed}
     */
    public function flashSaleSummary(Offer $offer): array
    {
        return [
            'id' => $offer->id,
            'slug' => $offer->slug,
            'title' => $offer->title,
            'banner' => $offer->banner,
            'start_at' => $offer->start_at,
            'end_at' => $offer->end_at,
        ];
    }

    /**
     * @return Collection<int, Product>
     */
    public function featuredProducts(): Collection
    {
        return $this->products->featured();
    }

    /**
     * @return Collection<int, Product>
     */
    public function latestArrivals(): Collection
    {
        return $this->products->latestArrivals();
    }

    public function paginateNewArrivals(int $perPage): LengthAwarePaginator
    {
        return $this->products->paginateNewArrivals($perPage);
    }

    /**
     * @return Collection<int, News>
     */
    public function news(): Collection
    {
        return $this->news->activeLatestFirst();
    }

    public function paginateNews(int $perPage): LengthAwarePaginator
    {
        return $this->news->paginateActiveLatestFirst($perPage);
    }

    /**
     * @return Collection<int, ReelsVideo>
     */
    public function reels(): Collection
    {
        return $this->reels->activeWithProducts();
    }

    /**
     * @return Collection<int, PreOwned>
     */
    public function latestPreOwneds(): Collection
    {
        return $this->preOwneds->latestUnsold();
    }
}
