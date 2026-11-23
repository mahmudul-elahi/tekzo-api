<?php

namespace App\Http\Controllers\Api\V1\Customer\Review;

use App\Exceptions\GeneralException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\Review\CustomerReviewResource;
use App\Http\Resources\Customer\Review\DeliveredLineDetailResource;
use App\Http\Resources\Customer\Review\DeliveredLineResource;
use App\Http\Resources\Customer\Review\ReviewableOrderLineResource;
use App\Services\Customer\CustomerReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class CustomerReviewController extends Controller
{
    public function __construct(private CustomerReviewService $reviewService) {}

    public function index(Request $request): JsonResponse
    {
        $reviews = $this->reviewService
            ->paginateForCustomer($this->customerId(), clampPerPage($request->input('per_page'), 10, 48))
            ->through(fn ($review) => (new CustomerReviewResource($review))->resolve($request));

        return successResponse($reviews);
    }

    /**
     * Store or update a product review.
     *
     * @throws GeneralException|Throwable
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'order_detail_id' => 'required|integer|exists:order_lists,id',
            'rating' => 'required|numeric|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
            'review_image' => ['nullable', 'file', 'mimes:jpeg,png,jpg,svg', 'max:1024'],
        ]);

        $this->reviewService->submit(
            $this->customerId(),
            $request->order_detail_id,
            $request->rating,
            $request->comment,
            $request->hasFile('review_image') ? $request->file('review_image') : null,
        );

        return responseMessage(message: 'Review submitted successfully');
    }

    public function orderProduct(string $orderId, Request $request): JsonResponse
    {
        $order = $this->reviewService->findCompletedOrder($orderId, $this->customerId());

        if (! $order) {
            return responseMessage(message: 'Order not found', code: 404);
        }

        return successResponse(
            $order->orderLists->map(fn ($item) => (new ReviewableOrderLineResource($item))->resolve($request))
        );
    }

    public function deliveredProduct(Request $request): JsonResponse
    {
        return successResponse(
            $this->reviewService->deliveredLines($this->customerId())
                ->map(fn ($orderList) => (new DeliveredLineResource($orderList))->resolve($request))
        );
    }

    public function deliveredProductDetail(string $orderListId): JsonResponse
    {
        $orderList = $this->reviewService->findDeliveredLine($orderListId, $this->customerId());

        if (! $orderList) {
            return responseMessage(message: 'Product not found', code: 404);
        }

        return successResponse(new DeliveredLineDetailResource($orderList));
    }

    private function customerId(): mixed
    {
        return auth()->guard('customer')->id();
    }
}
