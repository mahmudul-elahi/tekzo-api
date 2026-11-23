<?php

namespace App\Http\Controllers\Api\V1\Cart;

use App\Exceptions\GeneralException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Cart\CartResource;
use App\Services\Cart\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class CartController extends Controller
{
    public function __construct(private CartService $cartService) {}

    public function index(): JsonResponse
    {
        $cart = $this->cartService->findForDisplay($this->customerId());

        return successResponse($cart ? new CartResource($cart) : []);
    }

    /**
     * Add a pre-owned unit (when pre_owned_id is given) or a product variant to the cart.
     *
     * @throws Throwable
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'variant_id' => ['required', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'pre_owned_id' => ['nullable', 'exists:pre_owneds,id'],
            'gift_id' => ['nullable', Rule::exists('gift_product_variants', 'id')->whereNull('deleted_at')],
            'care_id' => ! empty($request->input('pre_owned_id'))
                ? ['nullable', Rule::exists('pre_owned_cares', 'id')->whereNull('deleted_at')]
                : ['nullable', Rule::exists('product_cares', 'id')->whereNull('deleted_at')],
        ]);

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
    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

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
    public function toggleSelect(Request $request): JsonResponse
    {
        $request->validate([
            'cart_item_id' => ['required', 'exists:cart_items,id'],
            'is_select' => ['required', 'boolean'],
        ]);

        $this->cartService->setSelected($this->customerId(), $request->cart_item_id, $request->is_select);

        return responseMessage('Cart item updated successfully');
    }

    private function customerId(): mixed
    {
        return auth()->guard('customer')->id();
    }
}
