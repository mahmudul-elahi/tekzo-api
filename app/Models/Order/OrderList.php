<?php

namespace App\Models\Order;

use App\Models\Care\PreOwnedCare;
use App\Models\Care\ProductCare;
use App\Models\Gift\GiftProductVariant;
use App\Models\PreOwned\PreOwned;
use App\Models\Product\Product;
use App\Models\Product\ProductVariant;
use App\Models\Product\Review;
use Illuminate\Database\Eloquent\Model;

class OrderList extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'pre_owned_id',
        'product_variant_id',
        'gift_product_variant_id',
        'product_care_id',
        'pre_owned_care_id',
        'product_price',
        'final_price',
        'discount_type',
        'discount_value',
        'discount_amount',
        'discount_total',
        'quantity',
        'purchase_point',
        'status',
    ];

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id', 'id');
    }

    public function review()
    {
        return $this->hasOne(Review::class, 'order_detail_id', 'id');
    }

    public function preOwned()
    {
        return $this->belongsTo(PreOwned::class, 'pre_owned_id', 'id');
    }

    public function giftProductVariant()
    {
        return $this->belongsTo(GiftProductVariant::class, 'gift_product_variant_id', 'id');
    }

    public function productCare()
    {
        return $this->belongsTo(ProductCare::class, 'product_care_id', 'id');
    }

    public function preOwnedCare()
    {
        return $this->belongsTo(PreOwnedCare::class, 'pre_owned_care_id', 'id');
    }
}
