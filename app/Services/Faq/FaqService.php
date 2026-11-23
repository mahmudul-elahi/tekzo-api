<?php

namespace App\Services\Faq;

use App\Repositories\Faq\FaqRepository;
use Illuminate\Support\Collection;

class FaqService
{
    public function __construct(private FaqRepository $faqs) {}

    /**
     * Active FAQs grouped by group_id (groups and items keep their order).
     *
     * @return Collection<int, Collection<int, array{id: int, order: mixed, lang: mixed, question: mixed, answer: mixed}>>
     */
    public function grouped(): Collection
    {
        return $this->faqs->activeOrdered()
            ->groupBy('group_id')
            ->map(fn ($group) => $group->map(fn ($faq) => [
                'id' => $faq->id,
                'order' => $faq->order,
                'lang' => $faq->lang,
                'question' => $faq->question,
                'answer' => $faq->answer,
            ])->values())
            ->values();
    }
}
