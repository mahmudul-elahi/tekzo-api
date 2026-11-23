<?php

namespace App\Repositories\Tenant;

use App\Models\Tenant\Tenant;
use App\Repositories\BaseRepository;

class TenantRepository extends BaseRepository
{
    public function __construct(Tenant $tenant)
    {
        $this->model = $tenant;
    }

    /**
     * The tenant this storefront serves (services.tenant_slug).
     */
    public function current(): ?Tenant
    {
        return $this->query()
            ->where('slug', config('services.tenant_slug'))
            ->first();
    }
}
