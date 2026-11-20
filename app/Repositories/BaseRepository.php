<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Model;

class BaseRepository
{
    protected Model $model;


    public function __call($name, $arguments)
    {
        return $this->model->{$name}(...$arguments);
    }
}
