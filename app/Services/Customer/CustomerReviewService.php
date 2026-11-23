<?php

namespace App\Services\Customer;

use App\Exceptions\GeneralException;
use App\Models\Order\Order;
use App\Models\Order\OrderList;
use App\Repositories\Order\OrderListRepository;
use App\Repositories\Product\ReviewRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

class CustomerReviewService
{
    public function __construct(
        private ReviewRepository $reviews,
        private OrderListRepository $orderLists,
    ) {}

    public function paginateForCustomer(mixed $contactId, int $perPage): LengthAwarePaginator
    {
        return $this->reviews->paginateForContact($contactId, $perPage);
    }

    /**
     * Review (or re-review) a line of one of the customer's completed orders, optionally with an image.
     *
     * @throws GeneralException when the line isn't from the customer's completed order, or saving fails
     * @throws Throwable
     */
    public function submit(mixed $contactId, mixed $orderDetailId, mixed $rating, ?string $comment, ?UploadedFile $image): void
    {
        $orderList = $this->orderLists->findCompletedForContact($orderDetailId, $contactId);

        if (! $orderList) {
            throw new GeneralException('Order not found', 404);
        }

        try {
            DB::beginTransaction();

            $review = $this->reviews->upsertForOrderLine($contactId, $orderList->product_id, $orderDetailId, $rating, $comment);

            if ($image) {
                $review->addMedia($image)->toMediaCollection('review_images');
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw new GeneralException(
                app()->isProduction() ? 'Failed to submit review' : $e->getMessage(),
                500
            );
        }
    }

    public function findCompletedOrder(mixed $orderId, mixed $contactId): ?Order
    {
        return $this->orderLists->findCompletedOrderWithLines($orderId, $contactId);
    }

    /**
     * @return Collection<int, OrderList>
     */
    public function deliveredLines(mixed $contactId): Collection
    {
        return $this->orderLists->completedForContactWithReviewCount($contactId);
    }

    public function findDeliveredLine(mixed $orderListId, mixed $contactId): ?OrderList
    {
        return $this->orderLists->findCompletedWithDetailsForContact($orderListId, $contactId);
    }

    public function primaryImagePath(mixed $productId, mixed $attributeValueId): ?string
    {
        return $this->orderLists->primaryImagePath($productId, $attributeValueId);
    }
}
