<?php

namespace App\Repositories\News;

use App\Enums\StatusEnum;
use App\Models\News\News;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class NewsRepository extends BaseRepository
{
    public function __construct(News $news)
    {
        $this->model = $news;
    }

    /**
     * @return Collection<int, News>
     */
    public function activeLatestFirst(): Collection
    {
        return $this->activeForTenant()->get();
    }

    public function paginateActiveLatestFirst(int $perPage): LengthAwarePaginator
    {
        return $this->activeForTenant()->paginate($perPage);
    }

    private function activeForTenant(): Builder
    {
        return $this->query()
            ->whereHas('tenant', fn ($query) => $query->where('slug', config('services.tenant_slug')))
            ->where('status', StatusEnum::Active)
            ->select(['id', 'heading', 'name', 'slug', 'image', 'description', 'created_at'])
            ->orderByDesc('id');
    }
}
