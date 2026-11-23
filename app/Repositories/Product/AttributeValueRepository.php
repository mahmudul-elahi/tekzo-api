<?php

namespace App\Repositories\Product;

use App\Models\Product\AttributeValue;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

class AttributeValueRepository extends BaseRepository
{
    public const CONDITION_ATTRIBUTE_ID = 4;

    public const STORAGE_ATTRIBUTE_ID = 3;

    public function __construct(AttributeValue $attributeValue)
    {
        $this->model = $attributeValue;
    }

    /**
     * Id and value (as "name") of every value of an attribute.
     *
     * @return Collection<int, AttributeValue>
     */
    public function namesOfAttribute(int $attributeId): Collection
    {
        return $this->query()
            ->where('attribute_id', $attributeId)
            ->select(['id', 'value as name'])
            ->get();
    }
}
