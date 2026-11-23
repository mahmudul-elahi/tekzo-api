<?php

namespace App\Repositories\Policy;

use App\Models\Widget\Widget;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * Policies are stored as tenant widgets keyed by type.
 */
class PolicyRepository extends BaseRepository
{
    public function __construct(Widget $widget)
    {
        $this->model = $widget;
    }

    /**
     * Name and type of every policy except the return/refund one.
     *
     * @return Collection<int, Widget>
     */
    public function listed(): Collection
    {
        return $this->query()
            ->select(['name', 'type'])
            ->whereNotIn('type', ['return-refund-policy'])
            ->whereHas('tenant', fn ($query) => $query->where('slug', config('services.tenant_slug')))
            ->get();
    }

    public function findByType(string $type): ?Widget
    {
        return $this->query()
            ->where('type', $type)
            ->whereHas('tenant', fn ($query) => $query->where('slug', config('services.tenant_slug')))
            ->select(['name', 'value'])
            ->first();
    }
}
