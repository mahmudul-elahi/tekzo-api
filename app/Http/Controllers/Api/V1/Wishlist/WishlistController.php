<?php

namespace App\Http\Controllers\Api\V1\Wishlist;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wishlist\AddToWishlistRequest;
use App\Http\Requests\Wishlist\ToggleWishlistItemRequest;
use App\Http\Requests\Wishlist\UpdateWishlistItemRequest;
use App\Http\Resources\Wishlist\WishlistResource;
use App\Services\Wishlist\WishlistService;
use Illuminate\Http\JsonResponse;
use Throwable;

class WishlistController extends Controller
{
    public function __construct(private WishlistService $wishlistService) {}

    public function index(): JsonResponse
    {
        $wishlist = $this->wishlistService->findForDisplay($this->customerId());

        return successResponse($wishlist ? new WishlistResource($wishlist) : []);
    }

    /**
     * @throws Throwable
     */
    public function store(AddToWishlistRequest $request): JsonResponse
    {

        $this->wishlistService->addVariant($this->customerId(), $request->variant_id, (int) $request->quantity);

        return responseMessage('Wishlist added successfully');
    }

    /**
     * @throws Throwable
     */
    public function update(UpdateWishlistItemRequest $request, string $id): JsonResponse
    {

        $this->wishlistService->updateQuantity($this->customerId(), $id, (int) $request->quantity);

        return responseMessage('Wishlist item updated');
    }

    /**
     * @throws Throwable
     */
    public function destroy(string $id): JsonResponse
    {
        $this->wishlistService->removeItem($this->customerId(), $id);

        return responseMessage('Cart item removed');
    }

    /**
     * @throws Throwable
     */
    public function toggleSelect(ToggleWishlistItemRequest $request): JsonResponse
    {

        $this->wishlistService->setSelected($this->customerId(), $request->cart_item_id, $request->is_select);

        return responseMessage('Wishlist item updated successfully');
    }

    private function customerId(): mixed
    {
        return auth()->guard('customer')->id();
    }
}
