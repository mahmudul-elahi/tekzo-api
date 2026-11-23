<?php

namespace App\Services\Product;

use App\Models\PreOwned\PreOwned;
use App\Models\Product\Product;
use App\Repositories\Coupon\CouponRepository;
use App\Repositories\PreOwned\PreOwnedRepository;
use App\Repositories\Product\ProductRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ProductService
{
    private const RELATED_PRODUCT_COUNT = 4;

    private const RELATED_PRE_OWNED_COUNT = 5;

    public function __construct(
        private ProductRepository $products,
        private PreOwnedRepository $preOwneds,
        private CouponRepository $coupons,
    ) {}

    /**
     * A visible product for the detail page, with its best public coupon attached.
     * Records a view for the visitor.
     */
    public function findProductDetail(string $slug, Request $request): ?Product
    {
        $product = $this->products->findVisibleBySlugForDetail($slug);

        if (! $product) {
            return null;
        }

        $product->setAttribute(
            'resolved_coupon',
            $this->coupons->findBestPublicForProduct($product->id, $product->brand_id, $product->category_id)
        );

        $this->recordView($product->id, $request);

        return $product;
    }

    /**
     * A pre-owned item for the detail page. Records a view on its product.
     */
    public function findPreOwnedDetail(mixed $preOwnedId, Request $request): ?PreOwned
    {
        $preOwned = $this->preOwneds->findForDetail($preOwnedId);

        if (! $preOwned) {
            return null;
        }

        if ($preOwned->product) {
            $this->recordView($preOwned->product->id, $request);
        }

        return $preOwned;
    }

    /**
     * Up to 4 random sellable products from the same category, topped up from other categories.
     *
     * @return Collection<int, Product>|null null when the product doesn't exist
     */
    public function relatedProducts(string $slug): ?Collection
    {
        $product = $this->products->findCategorizedBySlug($slug);

        if (! $product) {
            return null;
        }

        $matched = $this->products->randomSellableInCategory($product, self::RELATED_PRODUCT_COUNT);
        $needed = self::RELATED_PRODUCT_COUNT - $matched->count();

        if ($needed <= 0) {
            return $matched;
        }

        $excludeIds = $matched->pluck('id')->push($product->id)->toArray();

        return $matched->concat($this->products->randomSellableExcept($excludeIds, $needed));
    }

    /**
     * Up to 5 random unsold pre-owned items of the same product, topped up with any other pre-owned items.
     *
     * @return Collection<int, PreOwned>|null null when the current pre-owned item doesn't exist
     */
    public function relatedPreOwneds(string $productSlug, mixed $preOwnedId): ?Collection
    {
        if (! $this->preOwneds->existsById($preOwnedId)) {
            return null;
        }

        $matched = $this->preOwneds->randomUnsoldOfProduct($productSlug, $preOwnedId, self::RELATED_PRE_OWNED_COUNT);
        $needed = self::RELATED_PRE_OWNED_COUNT - $matched->count();

        if ($needed <= 0) {
            return $matched;
        }

        $excludeIds = $matched->pluck('id')->push($preOwnedId)->toArray();

        return $matched->concat($this->preOwneds->randomExcept($excludeIds, $needed));
    }

    /**
     * One view per visitor, identified by client IP (first X-Forwarded-For entry when present) and user agent.
     */
    private function recordView(int $productId, Request $request): void
    {
        $forwardedFor = $request->header('X-Forwarded-For');

        $ip = $forwardedFor
            ? trim(explode(',', $forwardedFor)[0])
            : $request->ip();

        $this->products->recordView($productId, md5($ip.$request->userAgent()));
    }
}
