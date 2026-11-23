<?php

namespace App\Repositories\Category;

use App\Enums\ProductStatus;
use App\Enums\StatusEnum;
use App\Models\Category\Category;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CategoryRepository extends BaseRepository
{
    public function __construct(Category $category)
    {
        $this->model = $category;
    }

    /**
     * Up to 8 active categories that have visible products, by serial (unset serials last).
     *
     * @return Collection<int, Category>
     */
    public function homeCategories(): Collection
    {
        return $this->query()
            ->select('id', 'name', 'slug', 'logo')
            ->where('status', StatusEnum::Active->value)
            ->whereHas('products', function ($query) {
                $query->whereIn('status', [
                    ProductStatus::Active->value,
                    ProductStatus::Comming->value,
                ]);
            })
            ->orderByRaw('CASE WHEN serial = 0 OR serial IS NULL THEN 1 ELSE 0 END')
            ->orderBy('serial', 'asc')
            ->limit(8)
            ->get();
    }

    /**
     * Every category's id, parent_id and order, keyed by id, for walking the tree in memory.
     *
     * @return Collection<int, Category>
     */
    public function orderMapKeyedById(): Collection
    {
        return $this->query()
            ->select(['id', 'parent_id', 'order'])
            ->get()
            ->keyBy('id');
    }

    /**
     * Active leaf categories (no children) that have visible products.
     *
     * @return Collection<int, Category>
     */
    public function activeLeavesWithVisibleProducts(): Collection
    {
        return $this->query()
            ->select(['id', 'name', 'title', 'slug', 'logo', 'parent_id', 'order', 'updated_at'])
            ->where('status', StatusEnum::Active->value)
            ->whereHas('products', function ($query) {
                $query->whereIn('status', [ProductStatus::Active->value, ProductStatus::Comming->value]);
            })
            ->whereDoesntHave('children')
            ->get();
    }

    /**
     * Active root categories with two levels of active children, each level by serial (unset serials last).
     *
     * @return Collection<int, Category>
     */
    public function activeTree(): Collection
    {
        return $this->query()
            ->select(['id', 'parent_id', 'name', 'slug', 'logo'])
            ->whereNull('parent_id')
            ->where('status', StatusEnum::Active->value)
            ->with(['children' => function ($query) {
                $query->select(['id', 'parent_id', 'name', 'slug', 'logo'])
                    ->where('status', StatusEnum::Active->value)
                    ->with(['children' => function ($query) {
                        $query->select(['id', 'parent_id', 'name', 'slug', 'logo'])
                            ->where('status', StatusEnum::Active->value);
                    }])
                    ->orderByRaw('CASE WHEN serial = 0 OR serial IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('serial', 'asc');
            }])
            ->orderByRaw('CASE WHEN serial = 0 OR serial IS NULL THEN 1 ELSE 0 END')
            ->orderBy('serial', 'asc')
            ->get();
    }

    /**
     * Active categories with visible products from active brands, each with its top 2 products
     * (in stock first, then highest display price, then newest).
     *
     * @return Collection<int, Category>
     */
    public function activeWithTopProducts(): Collection
    {
        return $this->query()
            ->select(['id', 'name', 'title', 'slug', 'logo', 'banner', 'parent_id', 'order'])
            ->where('status', StatusEnum::Active->value)
            ->whereHas('products', function ($query) {
                $query->whereIn('status', [
                    ProductStatus::Active->value,
                    ProductStatus::Comming->value,
                ])->whereHas('brand', function ($q) {
                    $q->where('status', true);
                });
            })
            ->with([
                'products' => function ($query) {
                    // Written as a raw SQL string (not an addSelect() alias) because this
                    // relation gets wrapped in a ROW_NUMBER() OVER (...) window function
                    // for the per-category take(2) limit below, and MySQL's window ORDER
                    // BY can't reference a SELECT-list alias from the same query — it
                    // needs the full expression, same as the has_stock ORDER BY already does.
                    $displayPriceSql = "(
                        SELECT MIN(
                            CASE
                                WHEN variant_prices.discount_percent > 0 THEN variant_prices.max_retail_price - (variant_prices.max_retail_price * variant_prices.discount_percent / 100)
                                WHEN variant_prices.discount_fixed > 0   THEN variant_prices.max_retail_price - variant_prices.discount_fixed
                                ELSE variant_prices.max_retail_price
                            END
                        )
                        FROM product_variants
                        INNER JOIN variant_prices ON variant_prices.product_variant_id = product_variants.id
                        WHERE product_variants.product_id = products.id
                        AND product_variants.status = '1'
                        AND product_variants.deleted_at IS NULL
                    )";

                    $query->select([
                        'id',
                        'category_id',
                        'brand_id',
                        'product_name',
                        'slug',
                        'product_image',
                        'status',
                        'has_official_warranty',
                        'is_chinese',
                        'is_unofficial',
                        'is_refurbished',
                        'purchase_point',
                        'booking_amount',
                    ])
                        ->addSelect([
                            'display_price' => DB::raw($displayPriceSql),
                        ])
                        ->whereIn('status', [
                            ProductStatus::Active->value,
                            ProductStatus::Comming->value,
                        ])
                        ->whereHas('brand', function ($q) {
                            $q->where('status', true);
                        })
                        ->with([
                            'brand:id,name,slug,primary_logo',
                            'offerProduct',
                            'stockVariant' => function ($q) {
                                $q->where('status', '1')
                                    ->orderByLowestPrice()
                                    ->with('price');
                            },
                        ])
                        ->withCount(['giftProduct', 'cares'])
                        ->withAvg('reviews', 'rating')
                        ->orderByRaw('(SELECT IF(MAX(stock) > 0, 1, 0) FROM product_variants WHERE product_id = products.id AND status = "1" AND deleted_at IS NULL) DESC')
                        ->orderByRaw("$displayPriceSql DESC")
                        ->orderByDesc('products.created_at')
                        ->take(2);
                },
            ])
            ->get();
    }

    /**
     * Just the name and banner, for the category hero.
     */
    public function findBannerBySlug(string $slug): ?Category
    {
        return $this->query()
            ->whereSlug($slug)
            ->select(['name', 'banner'])
            ->first();
    }

    /**
     * A category with its long description and two levels of children.
     */
    public function findWithChildrenBySlug(string $slug): ?Category
    {
        return $this->query()
            ->whereSlug($slug)
            ->with(['longDescription:id,model_id,description', 'children.children'])
            ->first();
    }
}
