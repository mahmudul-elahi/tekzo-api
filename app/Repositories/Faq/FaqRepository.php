<?php

namespace App\Repositories\Faq;

use App\Models\Faq\Faq;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

class FaqRepository extends BaseRepository
{
    public function __construct(Faq $faq)
    {
        $this->model = $faq;
    }

    /**
     * Active FAQs of the current tenant, by order.
     *
     * @return Collection<int, Faq>
     */
    public function activeOrdered(): Collection
    {
        return $this->query()
            ->select('id', 'group_id', 'lang', 'question', 'answer', 'order', 'updated_at')
            ->where('status', 1)
            ->whereHas('tenant', fn ($q) => $q->where('slug', config('services.tenant_slug')))
            ->orderBy('order', 'asc')
            ->get();
    }
}
