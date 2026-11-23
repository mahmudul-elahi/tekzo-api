<?php

namespace App\Models\Concerns;

use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    protected static ?int $resolvedTenantId = null;

    protected static function bootBelongsToTenant(): void
    {
        static::creating(function ($model) {
            if (! $model->tenant_id) {
                $model->tenant_id = static::currentTenantId();
            }
        });
    }

    protected static function currentTenantId(): ?int
    {
        static::$resolvedTenantId ??= Tenant::query()
            ->where('slug', config('services.tenant_slug'))
            ->value('id');

        return static::$resolvedTenantId;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }
}
