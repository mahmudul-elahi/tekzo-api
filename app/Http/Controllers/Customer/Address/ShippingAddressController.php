<?php

namespace App\Http\Controllers\Customer\Address;

use App\Http\Controllers\Controller;
use App\Models\Address\CustomerAddress;
use Illuminate\Http\Request;

class ShippingAddressController extends Controller
{
    public function index(Request $request)
    {
        $addresses = CustomerAddress::query()
            ->with(['district', 'area'])
            ->where('contact_id', auth()->guard('customer')->id())
            ->orderByDesc('id')
            ->get();

        return successResponse($addresses);
    }
}
