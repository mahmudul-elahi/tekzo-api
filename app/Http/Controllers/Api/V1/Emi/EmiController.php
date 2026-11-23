<?php

namespace App\Http\Controllers\Api\V1\Emi;

use App\Http\Controllers\Controller;
use App\Models\Emi\EmiBank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class EmiController extends Controller
{


    public function index(Request $request)
    {
        $amount = (float) $request->input('amount', 5000);

        return successResponse($this->buildEmiList($amount));
    }

    private function buildEmiList(float $amount): array
    {
        $banks = EmiBank::with(['tenures' => function ($query) {
            $query->where('is_active', 1)->orderBy('months');
        }])
            ->where('is_active', 1)
            ->orderBy('bank_name')
            ->get()
            ->toArray();

        $result = [];

        foreach ($banks as $bank) {
            $installments = [];

            foreach ($bank['tenures'] as $tenure) {
                $months     = (int) $tenure['months'];
                $rate       = (float) $tenure['charge_rate'];
                $totalPay   = round($amount + ($amount * $rate / 100));
                $monthlyEmi = round($totalPay / $months);

                $installments[] = [
                    'months'         => $months,
                    'charge_percent' => $rate,
                    'monthly_emi'    => $monthlyEmi,
                    'effective_cost' => $totalPay,
                ];
            }

            $result[] = [
                'bank_name'    => $bank['bank_name'],
                'gw'           => $bank['gw_key'],
                'logo'         => "https://securepay.sslcommerz.com/gwprocess/v4/image/gw/{$bank['gw_key']}.png",
                'installments' => $installments,
            ];
        }

        return $result;
    }
}
