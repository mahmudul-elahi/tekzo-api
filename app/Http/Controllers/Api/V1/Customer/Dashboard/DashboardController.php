<?php

namespace App\Http\Controllers\Api\V1\Customer\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\Profile\ProfileResource;
use App\Http\Resources\Order\RecentOrderResource;
use App\Models\Contact\Contact;
use App\Services\Address\AddressService;
use App\Services\Customer\ProfileService;
use App\Services\Order\CustomerOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Everything the customer dashboard's overview needs, in one request.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly ProfileService $profileService,
        private readonly CustomerOrderService $orderService,
        private readonly AddressService $addressService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var Contact $customer */
        $customer = auth()->guard('customer')->user();

        return successResponse([
            'profile' => ProfileResource::make($this->profileService->withEliteDetails($customer))->resolve($request),
            'statistics' => $this->orderService->statistics($customer->id),
            'recent_orders' => $this->orderService->recent($customer->id)
                ->map(fn ($order) => (new RecentOrderResource($order))->resolve($request)),
            'default_addresses' => $this->addressService->defaults($customer->id),
        ]);
    }
}
