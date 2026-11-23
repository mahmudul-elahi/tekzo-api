<?php

namespace App\Models\Product;

use App\Models\Contact\Contact;
use App\Models\Order\OrderList;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable([
    'product_id',
    'contact_id',
    'order_detail_id',
    'rating',
    'comment',
    'status',
    'is_admin_review',
    'customer_name',
])]
class Review extends Model implements HasMedia
{
    use InteractsWithMedia, SoftDeletes;

    public $casts = [
        'rating' => 'integer',
        'is_admin_review' => 'boolean',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function orderList(): BelongsTo
    {
        return $this->belongsTo(OrderList::class, 'order_detail_id');
    }

    public function getImageUrlAttribute(): ?string
    {
        $media = $this->getFirstMedia('review_images');

        return $media?->getFullUrl();
    }

    /**
     * Register media collections for review images
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('review_images');
    }
}
