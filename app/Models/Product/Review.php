<?php

namespace App\Models\Product;

use App\Models\Contact\Contact;
use App\Models\Order\OrderList;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Review extends Model implements HasMedia
{

    use SoftDeletes, InteractsWithMedia;

    protected $fillable = [
        'product_id',
        'contact_id',
        'order_detail_id',
        'rating',
        'comment',
        'status',
        'is_admin_review',
        'customer_name',
    ];

    public $casts = [
        'rating' => 'integer',
        'is_admin_review' => 'boolean',
    ];
    public function contact(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function orderList(): \Illuminate\Database\Eloquent\Relations\BelongsTo
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
