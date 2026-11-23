<?php

declare(strict_types=1);

namespace App\Services\Policy;

use App\Models\Widget\Widget;
use App\Repositories\Policy\PolicyRepository;
use Illuminate\Database\Eloquent\Collection;

class PolicyService
{
    public function __construct(private readonly PolicyRepository $policies) {}

    /**
     * @return Collection<int, Widget>
     */
    public function listed(): Collection
    {
        return $this->policies->listed();
    }

    public function findByType(string $type): ?Widget
    {
        return $this->policies->findByType($type);
    }
}
