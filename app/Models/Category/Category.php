<?php

namespace App\Models\Category;

use App\Models\Product\LongDescription;
use App\Models\Product\Product;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

#[Fillable([
    'parent_id',
    'name',
    'logo',
    'secondary_logo',
    'banner',
    'title',
    'status',
    'slug',
    'serial',
    'tags',
    'description',
])]
class Category extends Model
{
    use Searchable, SoftDeletes;

    public $casts = [
        'status' => 'boolean',
    ];

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
        ];
    }

    public function getAllDescendantIds(array &$visited = []): array
    {
        if (isset($visited[$this->id])) {
            return [];
        }
        $visited[$this->id] = true;

        $ids = [$this->id];

        foreach ($this->children as $child) {
            $ids = array_merge($ids, $child->getAllDescendantIds($visited));
        }

        return $ids;
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id', 'id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function ancestors(): array
    {
        $ancestors = [];
        $category = $this;
        $visited = [];

        while ($category && ! isset($visited[$category->id])) {
            $visited[$category->id] = true;
            array_unshift($ancestors, $category);
            $category = $category->parent;
        }

        return $ancestors;
    }

    public function longDescription()
    {
        return $this->hasOne(LongDescription::class, 'model_id')
            ->where('model_type', 'App\Models\Landlord\Category\Category')
            ->whereHas('tenant', function ($query) {
                $query->where('slug', config('services.tenant_slug'));
            });
    }
}
