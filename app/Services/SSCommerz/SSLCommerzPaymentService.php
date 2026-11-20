<?php

namespace App\Services\SSCommerz;

use App\Enums\OnlinePaymentStatusEnum;
use App\Library\SslCommerz\SslCommerzNotification;
use App\Models\Order\Order;
use App\Models\Order\OrderPaymentHistory;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class SSLCommerzPaymentService
{
    /**
     * Validates and applies a payment callback (browser redirect or IPN) to the order.
     * Safe to call more than once for the same order/transaction — a callback for an
     * order that's already been resolved (by whichever channel got there first) is a
     * no-op, so the browser "success" redirect and the SSLCommerz IPN never double-process.
     *
     * @return bool whether the order ends up successful/partially paid after this call
     */
    public function processCallback(Order $order, array $postData): bool
    {
        $paymentStatus = (int) $order->payment_status;
        if ($paymentStatus !== OnlinePaymentStatusEnum::Initiated->value) {
            return in_array($paymentStatus, [
                OnlinePaymentStatusEnum::Successful->value,
                OnlinePaymentStatusEnum::PartiallyPaid->value,
            ], true);
        }


        $orderTotal = $order->total_booking_amount > 0 ? $order->total_booking_amount : $order->grand_total;


        $sslcommerz = new SslCommerzNotification();
        $isValid = $sslcommerz->orderValidate($postData, $order->transaction_id, $orderTotal, 'BDT');

        DB::transaction(function () use ($order, $postData, $isValid) {
            if ($isValid) {
                $this->markAsSuccess($order, $postData);
            } else {
                $this->markAsFailed($order, $postData);
            }
        });

        return $isValid;
    }

    private function markAsSuccess(Order $order, array $postData): void
    {
        $paidAmount = $order->total_booking_amount > 0 ? $order->total_booking_amount : $order->grand_total;

        $order->payable_amount   = $order->payable_amount + $paidAmount;
        $order->due_amount       = max(0, $order->due_amount - $paidAmount);
        $order->payment_status   = $order->due_amount <= 0
            ? OnlinePaymentStatusEnum::Successful->value
            : OnlinePaymentStatusEnum::PartiallyPaid->value;
        $order->additional_value = json_encode(['payment_info' => $postData]);
        $order->save();

        OrderPaymentHistory::create([
            'order_id'         => $order->id,
            'payment_method'   => 'online',
            'amount'           => $paidAmount,
            'transaction_id'   => $order->transaction_id,
            'status'           => OnlinePaymentStatusEnum::Successful->value,
            'gateway_response' => $postData,
        ]);

        Log::info('SSLCommerz: payment success', [
            'tran_id'       => $order->transaction_id,
            'paid'          => $paidAmount,
            'due_remaining' => $order->due_amount,
        ]);
    }

    private function markAsFailed(Order $order, array $postData): void
    {
        $order->payment_status   = OnlinePaymentStatusEnum::Failed->value;
        $order->additional_value = json_encode(['payment_info' => $postData]);
        $order->save();

        Log::warning('SSLCommerz: Payment validation failed', ['tran_id' => $order->transaction_id]);
    }

    /**
     * @throws Exception
     */
    public function initSSLCommerz($order, $amount): array
    {

        $tranId = uniqid() . $order->id; // unique tran_id

        $order->transaction_id = $tranId;
        $order->payment_status = OnlinePaymentStatusEnum::Initiated->value;
        $order->save();

        $contact = $order->contact;


        # Here you have to receive all the order data to initiate the payment.
        # Let's say, your oder transaction information are saving in a table called "order"
        $post_data['total_amount'] = $amount;
        $post_data['currency'] = "BDT";
        $post_data['tran_id'] = $tranId; // tran_id must be unique

        # CUSTOMER INFORMATION
        $post_data['cus_name'] = $contact->name;
        $post_data['cus_email'] = $contact->email;
        $post_data['cus_add1'] = $contact->address ?? 'N/A';
        $post_data['cus_add2'] = "";
        $post_data['cus_city'] = $order->district?->name ?? 'N/A';
        $post_data['cus_state'] = $order->area?->name ?? 'N/A';
        $post_data['cus_postcode'] = "";
        $post_data['cus_country'] = "Bangladesh";
        $post_data['cus_phone'] = $contact->phone;
        $post_data['cus_fax'] = "";

        # SHIPMENT INFORMATION
        $post_data['ship_name'] = $contact->name ?? 'N/A';
        $post_data['ship_add1'] = $contact->address ?? 'N/A';
        $post_data['ship_add2'] = 'N/A';
        $post_data['ship_city'] = $order->area?->name ?? 'N/A';
        $post_data['ship_state'] = $order->district?->name ?? 'N/A';
        $post_data['ship_postcode'] = $order->area?->postcode ?? 'N/A';
        $post_data['ship_phone'] = $contact->phone;
        $post_data['ship_country'] = "Bangladesh";

        # PRODUCT INFORMATION

        $post_data['shipping_method'] = "NO";
        $post_data['product_name'] = "Computer";
        $post_data['product_category'] = "Goods";
        $post_data['product_profile'] = "physical-goods";

        # OPTIONAL PARAMETERS
        $post_data['value_a'] = "order_" . $order->id;
        $post_data['value_b'] = $order->contact_id ?? "";
        $post_data['value_c'] = "ref003";
        $post_data['value_d'] = "ref004";


        $sslcommerz = new SslCommerzNotification();

        $response = $sslcommerz->makePayment($post_data, 'checkout', 'json');

        if (isset($response['GatewayPageURL']) && $response['GatewayPageURL'] != "") {
            return [
                'message' => 'success',
                'url' => $response['GatewayPageURL']
            ];
        }

        throw new Exception('Payment initiation failed: ' . ($response['failedreason'] ?? 'Unknown error'), 500);
    }

    public function checkTransactionStatus(Order $order): array
    {
        if (empty($order->transaction_id)) {
            return [
                'success' => false,
                'message' => 'Transaction ID not found.',
            ];
        }

        try {

            $response = Http::timeout(20)
                ->retry(3, 1000)
                ->asForm()
                ->get(
                    config('sslcommerz.apiDomain') . config('sslcommerz.apiUrl.transaction_status'),
                    [
                        'store_id'      => config('sslcommerz.apiCredentials.store_id'),
                        'store_passwd'  => config('sslcommerz.apiCredentials.store_password'),
                        'tran_id'       => $order->transaction_id,
                        'format'        => 'json',
                    ]
                );

            if (!$response->successful()) {

                Log::error('SSLCommerz transaction status request failed', [
                    'order_id' => $order->id,
                    'response' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Unable to contact SSLCommerz.',
                ];
            }

            $data = $response->json();

            Log::info('SSLCommerz transaction status', [
                'order_id' => $order->id,
                'response' => $data,
            ]);

            if (!in_array($data['status'] ?? null, [
                'VALID',
                'VALIDATED',
            ], true)) {

                return [
                    'success' => false,
                    'message' => $data['status'] ?? 'Payment not completed.',
                    'data' => $data,
                ];
            }

            //$this->processCallback($order, $data);

            return [
                'success' => true,
                'message' => 'Payment verified successfully.',
                'data' => $data,
            ];
        } catch (\Throwable $e) {

            Log::error('SSLCommerz transaction check failed', [
                'order_id' => $order->id,
                'tran_id' => $order->transaction_id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
