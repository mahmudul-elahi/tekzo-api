<?php

namespace App\Http\Controllers\Warranty;

use App\Enums\StatusEnum;
use App\Exceptions\GeneralException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Warranty\WarrantyClaimRequest;
use App\Models\Product\ProductSerial;
use App\Models\Sale\SaleItem;
use App\Models\Sale\SaleReturnItem;
use App\Models\Warranty\WarrantyClaim;
use Carbon\Carbon;

class WarrantyClaimController extends Controller
{
    /**
     * Public endpoint: a customer enters their IMEI/SL number + a reason and
     * this both verifies the serial is real and logs the claim in one step.
     *
     * @throws GeneralException
     */
    public function store(WarrantyClaimRequest $request)
    {
        $serialNumber = $request->serial_number;

        $productSerial = ProductSerial::query()
            ->where('serial_number', $serialNumber)
            ->with('product')
            ->first();

        if (!$productSerial) {
            throw new GeneralException('Invalid IMEI/SL number. Please check and try again.', 404);
        }

        // Only one claim per device: block re-submission once a claim already exists
        // for this serial number, regardless of its current status.
        if (WarrantyClaim::query()->where('serial_number', $serialNumber)->exists()) {
            throw new GeneralException('A warranty claim has already been submitted for this IMEI/SL number.', 422);
        }

        // A returned unit is no longer the customer's to claim against.
        if (SaleReturnItem::query()->where('serial_number', $serialNumber)->exists()) {
            throw new GeneralException('This product has been returned and is not eligible for a warranty claim.', 422);
        }

        // The serial registry (product_serials) doesn't carry a sale date, so
        // the actual sale (and therefore the warranty start date) is looked
        // up via the sale_items row that was created when it was sold.
        $saleItem = SaleItem::query()
            ->where('serial_number', $serialNumber)
            ->with('sale')
            ->first();

        $warrantyEndDate = null;
        $isWithinWarranty = false;

        if ($saleItem?->sale && $productSerial->product?->warranty_day) {
            $warrantyEndDate = Carbon::parse($saleItem->sale->date)
                ->addDays($productSerial->product->warranty_day);
            $isWithinWarranty = Carbon::now()->lte($warrantyEndDate);
        }

        $claim = WarrantyClaim::create([
            'serial_number' => $serialNumber,
            'product_id' => $productSerial->product_id,
            'product_variant_id' => $productSerial->product_variant_id,
            'sale_id' => $saleItem?->sale_id,
            'reason' => $request->reason,
            'warranty_end_date' => $warrantyEndDate,
            'is_within_warranty' => $isWithinWarranty,
            'status' => StatusEnum::Pending->value,
        ]);

        return responseMessage('Your warranty claim has been submitted successfully.', 201, [
            'id' => $claim->id,
            'product_name' => $productSerial->product?->product_name,
            'invoice_number' => $saleItem?->sale?->full_invoice_number,
            'warranty_day' => $productSerial->product?->warranty_day,
            'is_within_warranty' => $isWithinWarranty,
            'warranty_end_date' => $warrantyEndDate?->toDateString(),
        ]);
    }
}
