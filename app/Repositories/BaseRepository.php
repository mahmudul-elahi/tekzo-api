<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Base class for repositories. Calls to undefined methods are forwarded to the model,
 * so `$repository->where(...)` behaves like `Model::where(...)`.
 */
class BaseRepository
{
    protected Model $model;

    /**
     * Start a fresh query on the repository's model.
     */
    public function query(): Builder
    {
        return $this->model->newQuery();
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        return $this->model->{$name}(...$arguments);
    }
}
