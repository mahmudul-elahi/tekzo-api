<?php

namespace App\Services\Warranty;

use App\Enums\StatusEnum;
use App\Exceptions\GeneralException;
use App\Repositories\Warranty\WarrantyClaimRepository;
use Carbon\Carbon;

class WarrantyClaimService
{
    public function __construct(private WarrantyClaimRepository $claims) {}

    /**
     * Verify a serial (IMEI/SL) and log a warranty claim for it.
     *
     * @return array{id: int, product_name: ?string, invoice_number: ?string, warranty_day: mixed, is_within_warranty: bool, warranty_end_date: ?string}
     *
     * @throws GeneralException when the serial is unknown, already claimed or was returned
     */
    public function submit(string $serialNumber, string $reason): array
    {
        $productSerial = $this->claims->findSerialWithProduct($serialNumber);

        if (! $productSerial) {
            throw new GeneralException('Invalid IMEI/SL number. Please check and try again.', 404);
        }

        // Only one claim per device: block re-submission once a claim already exists
        // for this serial number, regardless of its current status.
        if ($this->claims->existsForSerial($serialNumber)) {
            throw new GeneralException('A warranty claim has already been submitted for this IMEI/SL number.', 422);
        }

        // A returned unit is no longer the customer's to claim against.
        if ($this->claims->serialWasReturned($serialNumber)) {
            throw new GeneralException('This product has been returned and is not eligible for a warranty claim.', 422);
        }

        // The serial registry (product_serials) doesn't carry a sale date, so
        // the actual sale (and therefore the warranty start date) is looked
        // up via the sale_items row that was created when it was sold.
        $saleItem = $this->claims->findSaleItemForSerial($serialNumber);

        $warrantyEndDate = null;
        $isWithinWarranty = false;

        if ($saleItem?->sale && $productSerial->product?->warranty_day) {
            $warrantyEndDate = Carbon::parse($saleItem->sale->date)
                ->addDays($productSerial->product->warranty_day);
            $isWithinWarranty = Carbon::now()->lte($warrantyEndDate);
        }

        $claim = $this->claims->create([
            'serial_number' => $serialNumber,
            'product_id' => $productSerial->product_id,
            'product_variant_id' => $productSerial->product_variant_id,
            'sale_id' => $saleItem?->sale_id,
            'reason' => $reason,
            'warranty_end_date' => $warrantyEndDate,
            'is_within_warranty' => $isWithinWarranty,
            'status' => StatusEnum::Pending->value,
        ]);

        return [
            'id' => $claim->id,
            'product_name' => $productSerial->product?->product_name,
            'invoice_number' => $saleItem?->sale?->full_invoice_number,
            'warranty_day' => $productSerial->product?->warranty_day,
            'is_within_warranty' => $isWithinWarranty,
            'warranty_end_date' => $warrantyEndDate?->toDateString(),
        ];
    }
}
