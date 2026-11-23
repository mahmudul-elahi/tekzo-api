<?php

namespace App\Services\Category;

use App\Models\Category\Category;
use App\Repositories\Category\CategoryRepository;
use Illuminate\Support\Collection;

class CategoryService
{
    public function __construct(private CategoryRepository $categories) {}

    /**
     * @return Collection<int, Category>
     */
    public function homeCategories(): Collection
    {
        return $this->categories->homeCategories();
    }

    /**
     * Active leaf categories with visible products, in true tree order.
     *
     * @return Collection<int, Category>
     */
    public function leafCategoriesInTreeOrder(): Collection
    {
        $orderMap = $this->categories->orderMapKeyedById();

        return $this->sortByTreeOrder($this->categories->activeLeavesWithVisibleProducts(), $orderMap);
    }

    /**
     * @return Collection<int, Category>
     */
    public function tree(): Collection
    {
        return $this->categories->activeTree();
    }

    /**
     * Active categories, each with its top 2 products, in true tree order.
     *
     * @return Collection<int, Category>
     */
    public function categoriesWithTopProductsInTreeOrder(): Collection
    {
        $orderMap = $this->categories->orderMapKeyedById();

        return $this->sortByTreeOrder($this->categories->activeWithTopProducts(), $orderMap);
    }

    /**
     * The category tree can be several levels deep with inconsistent depth per branch,
     * so ranking needs the full root->leaf chain of "order" values, not just the
     * immediate parent's. The order map is loaded once and walked in memory.
     *
     * @param  Collection<int, Category>  $categories
     * @param  Collection<int, Category>  $orderMap
     * @return Collection<int, Category>
     */
    private function sortByTreeOrder(Collection $categories, Collection $orderMap): Collection
    {
        return $categories
            ->sortBy(fn ($category) => $this->orderChainKey($category, $orderMap))
            ->values();
    }

    /**
     * Builds a lexicographically-sortable key from a category's full ancestor chain
     * (root -> ... -> leaf) of "order" values, so branches are ranked by their true
     * position in the tree instead of just their immediate parent's order.
     */
    private function orderChainKey(Category $category, Collection $orderMap): string
    {
        $chain = [];
        $current = $category;
        $visited = [];

        while ($current && ! isset($visited[$current->id])) {
            $visited[$current->id] = true;
            // Pair order with id at every level: two different branches that tie on
            // "order" at some ancestor level must still stay grouped separately by
            // branch, instead of their leaves interleaving on the next level's order.
            array_unshift($chain, str_pad((string) ($current->order ?? 0), 6, '0', STR_PAD_LEFT)
                .'_'.str_pad((string) $current->id, 8, '0', STR_PAD_LEFT));
            $current = $current->parent_id ? $orderMap->get($current->parent_id) : null;
        }

        $key = implode('-', $chain);

        // $current still being set means the loop stopped on a cycle (e.g. a category
        // whose parent_id points back to itself/an ancestor) rather than reaching a
        // real root. A short chain from a broken parent link would otherwise sort as a
        // string-prefix of longer, correct chains and jump ahead of them, so push it
        // to the very end instead.
        return $current ? '999999-'.$key : $key;
    }
}
