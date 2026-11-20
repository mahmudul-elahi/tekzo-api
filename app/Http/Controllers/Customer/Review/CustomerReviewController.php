<?php

namespace App\Http\Controllers\Customer\Review;

use App\Enums\StatusEnum;
use App\Exceptions\GeneralException;
use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use App\Models\Order\OrderList;
use App\Models\Product\ProductImage;
use App\Models\Product\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class CustomerReviewController extends Controller
{
    public function index()
    {
        $customerId = auth()->guard('customer')->id();
        $perPage = clampPerPage(request('per_page'), 10, 48);
        $reviews = Review::query()
            ->with(['product:id,product_name', 'orderList' => function ($query) {
                $query->with(['variant' => function ($query) {
                    $query->with(['attributeValues']);
                }]);
            }])
            ->where('contact_id', $customerId)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(function ($review) {
                $attributeValueId = $review?->orderList?->variant?->attributeValues->pluck('id')->first();

                return [
                    'id' => $review->id,
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'created_at' => $review->created_at,
                    'review_image' => $review->getImageUrlAttribute(),
                    'product' => $review->product ? [
                        'id' => $review->product->id,
                        'name' => $review->product->product_name,
                        'attribute' => $review?->orderList?->variant?->attributeValues->pluck('value')->implode('/'),
                        'image' => $this->getFeaturedImage($review->product_id, $attributeValueId),
                    ] : null,
                ];
            });

        return successResponse($reviews);
    }

    /**
     * Store or update product reviews
     *
     * @throws GeneralException|Throwable
     */
    public function store(Request $request)
    {
        $customer = auth()->guard('customer')->id();

        $request->validate([
            'order_detail_id' => 'required|integer|exists:order_lists,id',
            'rating' => 'required|numeric|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
            'review_image' => ['nullable', 'file', 'mimes:jpeg,png,jpg,svg', 'max:1024'],
            // 'review_images.*' => 'file|mimes:jpeg,png,jpg,svg|max:5120',
        ]);

        $orderList = OrderList::query()
            ->whereHas('order', fn ($q) => $q->where('contact_id', $customer)->where('status', StatusEnum::Complete->value))
            ->find($request->order_detail_id);

        if (! $orderList) {
            throw new GeneralException('Order not found', 404);
        }

        try {
            DB::beginTransaction();

            $review = Review::query()->updateOrCreate(
                [
                    'contact_id' => $customer,
                    'product_id' => $orderList->product_id,
                    'order_detail_id' => $request->order_detail_id,
                ],
                [
                    'rating' => $request->rating,
                    'comment' => $request->comment ?? null,
                ]
            );

            if ($request->hasFile('review_image')) {
                $reviewImages = $request->file('review_image');
                $review->addMedia($reviewImages)->toMediaCollection('review_images');
                //                if (is_array($reviewImages)) {
                //                    foreach ($reviewImages as $image) {
                //                        $review->addMedia($image)->toMediaCollection('review_images');
                //                    }
                //                } else {
                //                    // Single file fallback
                //
                //                }
            }

            DB::commit();

            return responseMessage(message: 'Review submitted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            throw new GeneralException(
                app()->isProduction() ? 'Failed to submit review' : $e->getMessage(),
                500
            );
        }
    }

    public function orderProduct($orderId)
    {
        $order = Order::with(['orderLists' => function ($query) {
            $query->select('id', 'order_id', 'product_id', 'product_variant_id')
                ->with([
                    'variant' => function ($query) {
                        $query->with(['attributeValues']);
                    },
                    'product:id,product_name',
                ]);
        }])
            ->where('id', $orderId)
            ->where('status', StatusEnum::Complete->value)
            ->where('contact_id', auth()->guard('customer')->id())
            ->select(['id', 'status'])
            ->first();

        if (! $order) {
            return responseMessage(message: 'Order not found', code: 404);
        }

        return successResponse(
            $order->orderLists->map(function ($item) {
                $attributeValueId = $item->variant->attributeValues->pluck('id')->first();

                return [
                    'id' => $item->id,
                    'product' => [
                        'id' => $item->product->id,
                        'product_variant_id' => $item->product_variant_id,
                        'name' => $item->product->product_name,
                        'attribute_value' => $item->variant->attributeValues,
                        'image' => $this->getFeaturedImage($item->product_id, $attributeValueId),
                    ],
                ];
            })
        );
    }

    private function getFeaturedImage($productId, $attributeValueId)
    {
        $images = ProductImage::query()
            ->where('product_id', $productId)
            ->where('attribute_value_id', $attributeValueId)
            ->where('is_primary', true)
            ->first();

        return $images?->image_path;
    }

    public function deliveredProduct()
    {
        $customerId = auth()->guard('customer')->id();

        $orderLists = OrderList::query()
            ->whereHas('order', function ($query) use ($customerId) {
                $query->where('contact_id', $customerId)
                    ->where('status', StatusEnum::Complete->value);
            })
            ->with(['product', 'variant.attributeValues', 'order'])
            ->withCount(['review' => function ($query) {
                $query->where('contact_id', auth()->guard('customer')->id());
            }])
            ->get()
            ->map(function ($orderList) {
                $attributeValueId = $orderList?->variant?->attributeValues->pluck('id')->first();

                return [
                    'id' => $orderList->id,
                    'order_id' => $orderList->order_id,
                    'invoice_number' => $orderList->order->invoice_number,
                    'product_name' => $orderList->product->product_name,
                    'attributes' => $orderList?->variant?->attributeValues ? $orderList->variant->attributeValues->map(function ($attributeValue) {
                        return [
                            'name' => $attributeValue->value,
                        ];
                    })->toArray() : [],
                    'image' => $this->getFeaturedImage($orderList->product_id, $attributeValueId),
                    'delivered_at' => $orderList->order->updated_at,
                    'has_review' => $orderList->review_count,
                ];
            });

        return successResponse($orderLists);
    }

    public function deliveredProductDetail($orderListId)
    {
        $customerId = auth()->guard('customer')->id();

        $orderList = OrderList::query()
            ->where('id', $orderListId)
            ->whereHas('order', function ($query) use ($customerId) {
                $query->where('contact_id', $customerId)
                    ->where('status', StatusEnum::Complete->value);
            })
            ->with(['product', 'variant.attributeValues', 'order'])
            ->first();
        if (! $orderList) {
            return responseMessage(message: 'Product not found', code: 404);
        }

        $attributeValueId = $orderList?->variant?->attributeValues->pluck('id')->first();

        return successResponse([
            'id' => $orderList->id,
            'order_id' => $orderList->order_id,
            'product_name' => $orderList->product?->product_name,
            'attributes' => $orderList?->variant?->attributeValues ? $orderList->variant->attributeValues->map(function ($attributeValue) {
                return [
                    'name' => $attributeValue->value,
                ];
            })->toArray() : [],
            'image' => $this->getFeaturedImage($orderList->product_id, $attributeValueId),
            'delivered_at' => $orderList->order->updated_at,
        ]);
    }
}
