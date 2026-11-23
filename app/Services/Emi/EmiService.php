<?php

declare(strict_types=1);

namespace App\Services\Emi;

use App\Repositories\Emi\EmiBankRepository;

class EmiService
{
    public function __construct(private readonly EmiBankRepository $banks) {}

    /**
     * Installment plans per active bank for the given amount. The bank's charge rate is added
     * to the amount once; the monthly EMI is that total split over the tenure, both rounded.
     *
     * @return array<int, array{bank_name: string, gw: string, logo: string, installments: array<int, array{months: int, charge_percent: float, monthly_emi: float, effective_cost: float}>}>
     */
    public function plansFor(float $amount): array
    {
        $banks = $this->banks->activeWithTenures()->toArray();

        $result = [];

        foreach ($banks as $bank) {
            $installments = [];

            foreach ($bank['tenures'] as $tenure) {
                $months = (int) $tenure['months'];
                $rate = (float) $tenure['charge_rate'];
                $totalPay = round($amount + ($amount * $rate / 100));
                $monthlyEmi = round($totalPay / $months);

                $installments[] = [
                    'months' => $months,
                    'charge_percent' => $rate,
                    'monthly_emi' => $monthlyEmi,
                    'effective_cost' => $totalPay,
                ];
            }

            $result[] = [
                'bank_name' => $bank['bank_name'],
                'gw' => $bank['gw_key'],
                'logo' => "https://securepay.sslcommerz.com/gwprocess/v4/image/gw/{$bank['gw_key']}.png",
                'installments' => $installments,
            ];
        }

        return $result;
    }
}
