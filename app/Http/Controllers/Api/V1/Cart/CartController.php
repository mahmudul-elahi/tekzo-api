<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Cart;

use App\Exceptions\GeneralException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\AddToCartRequest;
use App\Http\Requests\Cart\ToggleCartItemRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Http\Resources\Cart\CartResource;
use App\Models\Cart\Cart;
use App\Services\Cart\CartService;
use Illuminate\Http\JsonResponse;
use Throwable;

class CartController extends Controller
{
    public function __construct(private readonly CartService $cartService) {}

    public function index(): JsonResponse
    {
        $cart = $this->cartService->findForDisplay($this->customerId());

        return successResponse($cart instanceof Cart ? new CartResource($cart) : []);
    }

    /**
     * Add a pre-owned unit (when pre_owned_id is given) or a product variant to the cart.
     *
     * @throws Throwable
     */
    public function store(AddToCartRequest $request): JsonResponse
    {

        $quantity = (int) $request->quantity;
        $customerId = $this->customerId();

        if (! $customerId) {
            throw new GeneralException('Unauthorized', 401);
        }

        if ($request->filled('pre_owned_id')) {
            $this->cartService->addPreOwned($customerId, $request->pre_owned_id, $request->variant_id, $quantity, $request->gift_id, $request->care_id);
        } else {
            $this->cartService->addVariant($customerId, $request->variant_id, $quantity, $request['gift_id'], $request['care_id']);
        }

        return responseMessage('Cart added');
    }

    /**
     * @throws Throwable
     */
    public function update(UpdateCartItemRequest $request, string $id): JsonResponse
    {

        $this->cartService->updateQuantity($this->customerId(), $id, (int) $request->quantity);

        return responseMessage('Cart item updated');
    }

    /**
     * @throws Throwable
     */
    public function destroy(string $id): JsonResponse
    {
        $this->cartService->removeItem($this->customerId(), $id);

        return responseMessage('Cart item removed');
    }

    /**
     * @throws Throwable
     */
    public function toggleSelect(ToggleCartItemRequest $request): JsonResponse
    {

        $this->cartService->setSelected($this->customerId(), $request->cart_item_id, $request->is_select);

        return responseMessage('Cart item updated successfully');
    }

    private function customerId(): mixed
    {
        return auth()->guard('customer')->id();
    }
}
