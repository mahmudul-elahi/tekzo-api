<?php

namespace App\Repositories\Career;

use App\Enums\StatusEnum;
use App\Models\Career\Career;
use App\Models\Career\CareerApplie;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

class CareerRepository extends BaseRepository
{
    public function __construct(Career $career)
    {
        $this->model = $career;
    }

    /**
     * @return Collection<int, Career>
     */
    public function activeLatestFirst(): Collection
    {
        return $this->query()
            ->where('status', StatusEnum::Active->value)
            ->orderBy('created_at', 'desc')
            ->select([
                'id',
                'heading',
                'slug',
                'vacancies',
                'type',
                'salary',
                'image',
                'qualification',
                'deadline',
                'description',
                'benefits',
                'keywords',
            ])

            ->get();
    }

    public function findActiveBySlug(string $slug): ?Career
    {
        return $this->query()
            ->where('slug', $slug)
            ->where('status', StatusEnum::Active->value)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createApplication(array $attributes): CareerApplie
    {
        return CareerApplie::query()->create($attributes);
    }
}
