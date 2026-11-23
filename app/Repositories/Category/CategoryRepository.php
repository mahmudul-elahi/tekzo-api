<?php

namespace App\Repositories\Category;

use App\Models\Category\Category;
use App\Repositories\BaseRepository;

class CategoryRepository extends BaseRepository
{
    public function __construct(Category $category)
    {
        $this->model = $category;
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
