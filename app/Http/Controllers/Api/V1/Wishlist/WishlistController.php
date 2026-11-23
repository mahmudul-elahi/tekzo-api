<?php

namespace App\Http\Controllers\Api\V1\Wishlist;

use App\Http\Controllers\Controller;
use App\Http\Resources\Wishlist\WishlistResource;
use App\Services\Wishlist\WishlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'variant_id' => ['required', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $this->wishlistService->addVariant($this->customerId(), $request->variant_id, (int) $request->quantity);

        return responseMessage('Wishlist added successfully');
    }

    /**
     * @throws Throwable
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

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
    public function toggleSelect(Request $request): JsonResponse
    {
        $request->validate([
            'cart_item_id' => ['required', 'exists:wishlist_items,id'],
            'is_select' => ['required', 'boolean'],
        ]);

        $this->wishlistService->setSelected($this->customerId(), $request->cart_item_id, $request->is_select);

        return responseMessage('Wishlist item updated successfully');
    }

    private function customerId(): mixed
    {
        return auth()->guard('customer')->id();
    }
}
