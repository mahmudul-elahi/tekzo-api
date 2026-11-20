<?php

namespace App\Services\Search;

use App\Enums\ProductStatus;
use App\Enums\StatusEnum;
use App\Models\Brand\Brand;
use App\Models\Category\Category;
use App\Models\Product\Product;

class MultiEntitySearchService
{

    public function search(string $query, int $limitCategories = 5, int $limitProducts = 12, int $limitBrands = 5): array
    {
        $expandedQuery = $query;

        // Search categories
        $categories = Category::search($expandedQuery)
            ->where('status', StatusEnum::Active->value)
            ->take($limitCategories)->get();

        // Search products
        $products = Product::search($expandedQuery)
            ->where('status', ProductStatus::Active->value)
            ->take($limitProducts)->get();

        // Search brands
        $brands = Brand::search($expandedQuery)
            ->where('status', StatusEnum::Active->value)
            ->take($limitBrands)->get();

        // Ensure categories & brands from products are also included
        foreach ($products as $product) {
            if ($product->category && !$categories->contains('id', $product->category->id) && $product->category->status == StatusEnum::Active->value) {
                $categories->push($product->category);
            }
            if ($product->brand && !$brands->contains('id', $product->brand->id) && $product->brand->status == StatusEnum::Active->value) {
                $brands->push($product->brand);
            }
        }

        // Apply priority sorting
        $categories = $categories->sortByDesc(function ($c) use ($query) {
            return $c->name === $query ? 3 : (str_contains(strtolower($c->slug), strtolower($query)) ? 2 : 1);
        })->values();

        $brands = $brands->sortByDesc(function ($b) use ($query) {
            return $b->name === $query ? 3 : (str_contains(strtolower($b->slug), strtolower($query)) ? 2 : 1);
        })->values();

        $products = $products->sortByDesc(function ($p) use ($query) {
            if (stripos($p->name, $query) !== false || stripos($p->slug, $query) !== false) {
                return 5;
            }
            if (stripos($p->headline, $query) !== false) {
                return 4;
            }
            if ($p->tags && stripos($p->tags, $query) !== false) {
                return 3;
            }
            if ($p->category && stripos($p->category->name, $query) !== false) {
                return 2;
            }
            if ($p->brand && stripos($p->brand->name, $query) !== false) {
                return 1;
            }
            return 0;
        })->values();


        return [
            'categories' => $categories,
            'products'   => $products,
            'brands'     => $brands,
        ];
    }
}
