<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Order Invoice</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@200;400;600;700&display=swap" rel="stylesheet">

    <style>
        @page {
            margin: 25mm 0;
        }

        body {
            font-family: 'Exo 2', sans-serif;
            font-weight: 200;
            margin: 0;
            padding: 0;
        }

        .invoice-container {
            font-size: 13px;
            width: 100%;
            margin: 0;
        }

        h1,
        h2 {
            color: #181818;
        }

        table {
            width: 100%;
            text-align: left;
            border-collapse: collapse;
            padding: 0 40px;
            line-height: inherit;
        }

        table th {
            padding: 2px 0 5px 0;
        }

        table td {
            padding: 12px 5px;
        }

        .items-table {
            margin-bottom: 10px;
        }

        .items-table-head {
            background-color: #f0f0f0;
        }

        .items-table thead th {
            font-weight: 400;
        }

        .items-table thead th:first-child {
            border-top-left-radius: 100%;
            border-bottom-left-radius: 100%;
        }

        .items-table thead th:last-child {
            border-top-right-radius: 100%;
            border-bottom-right-radius: 100%;
        }

        .items-table tbody td {
            border-bottom: 1px solid #a3a3a3;
            padding: 5px 0 10px 8px;
        }

        .table-footer {
            font-weight: 400;
        }

        .tk-sign {
            display: inline-block;
            font-family: 'Exo 2', sans-serif;
            font-weight: 400;
            font-size: 11px;
            margin-right: 3px;
            color: #181818;
        }

        .due-amount-section {
            page-break-inside: avoid;
        }

        .warranty-section {
            border: #04A98D 2px solid;
            border-radius: 20px;
            margin: 20px 40px;
            padding: 10px 20px 10px 0px;
            font-size: 11px;
        }

        /* Demo data styles */
        .demo-wrapper {
            padding: 20px;
            max-width: 900px;
            margin: 0 auto;
        }

        .variant-badge {
            display: inline-block;
            border: #414141 0.5px solid;
            border-radius: 100px;
            padding: 1px 5px;
            font-size: 10px;
            margin-right: 4px;
        }
    </style>
</head>

<body>
    <div class="demo-wrapper">
        <div class="invoice-container">

            <!-- Header -->
            <table style="margin-bottom: 10px;">
                <tr>
                    <td style="width: 50%; vertical-align: top;">
                        <p style="font-weight: 400; margin: 0 0 6px 0;">Billed To:</p>

                        <span style="font-weight: 400;">Name :</span>
                        <span>{{ $order?->contact?->name }}</span><br />

                        <span style="font-weight: 400;">Phone :</span>
                        {{ $order?->contact?->phone }}<br>

                        <span style="font-weight: 400;">Email :</span>
                        {{ $order?->contact?->email }}<br>

                        <span style="font-weight: 400;">Address :</span>
                        <span>{{ $order?->getFullAddress() }}</span>
                    </td>
                    <td style="width: 50%; text-align: right; vertical-align: top;">
                        <h2 style="text-transform: uppercase; font-weight: 400; font-size: 2rem; margin: 0;">Invoice</h2>
                        <span style="font-weight: 400;">Date:</span> {{ $order?->created_at->format('F d, Y') }}<br>
                        <span style="font-weight: 400;">Invoice No:</span> {{ $order->invoice_number }}
                    </td>
                </tr>
            </table>

            <hr style="height:2px;border-width:0;color:gray;background-color:#b8b8b8a6;" />

            <!-- Items Table -->
            <table class="items-table">
                <thead class="items-table-head">
                    <tr>
                        <th style="width: 5%; text-align: center;">#</th>
                        <th style="width: 35%;">Product</th>
                        <th style="width: 15%; text-align: center;">Regular Price</th>
                        <th style="width: 5%; text-align: center;">Qty</th>
                        <th style="width: 15%; text-align: center;">Total Discount</th>
                        <th style="width: 15%; text-align: center;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                    $totalDiscount = 0;
                    @endphp
                    @foreach($order->orderLists as $index => $item)
                    @php
                    $totalDiscount += $item->discount_amount ?? 0;
                    @endphp
                    <tr>
                        <td style="text-align: center; padding: 0;">{{ $index + 1 }}</td>
                        <td>
                            <p style="margin: 0px">{{ $item->product?->product_name }}</p>
                            @if($item->variant && $item->variant?->attributeValues->isNotEmpty())
                            <div style="margin-top: 8px;">
                                <span class="variant-badge">{{$item->variant->attributeValues->pluck('value')->implode('/')}}</span>
                            </div>
                            @endif
                        </td>
                        <td style="text-align: center;">{{ $item->product_price }}</td>
                        <td style="text-align: center;">{{ $item->quantity }}</td>
                        <td style="text-align: center;">{{ $item->discount_amount ?? 0 }}</td>
                        <td style="text-align: left;">
                            {{ $item->quantity * $item->product_price }}
                        </td>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <!-- Footer totals -->
            <div style="page-break-inside: avoid;">
                <table>
                    <tfoot class="table-footer">
                        <tr style="height: 0; line-height: 0; font-size: 0; border: 0; padding: 0; margin: 0;">
                            <td style="width: 5%; border: 0; padding: 0; margin: 0;"></td>
                            <td style="width: 35%; border: 0; padding: 0; margin: 0;"></td>
                            <td style="width: 15%; border: 0; padding: 0; margin: 0;"></td>
                            <td style="width: 10%; border: 0; padding: 0; margin: 0;"></td>
                            <td style="width: 5%; border: 0; padding: 0; margin: 0;"></td>
                            <td style="width: 15%; border: 0; padding: 0; margin: 0;"></td>
                            <td style="width: 15%; border: 0; padding: 0; margin: 0;"></td>
                        </tr>
                        <tr>
                            <td style="padding: 0 0 8px 8px; vertical-align: top;" colspan="4" rowspan="6">
                                Note :
                                <p style="font-weight: 200; padding: 0;">Please handle with care. Fragile items included.</p>
                            </td>
                            <td style="padding: 0 0 8px 0; text-align: right;" colspan="2">Subtotal :</td>
                            <td style="padding: 0 0 8px 8px; text-align: left;"><span class="tk-sign">Tk</span>{{$order->sub_total}}</td>
                        </tr>
                        <tr>
                            <td style="padding: 0 0 8px 0; text-align: right;" colspan="2">Total Discount :</td>
                            <td style="padding: 0 0 8px 8px; text-align: left;"><span class="tk-sign">Tk</span>{{ $totalDiscount }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 0 0 8px 0; text-align: right;" colspan="2">Shipping Charge :</td>
                            <td style="padding: 0 0 8px 8px; text-align: left;"><span class="tk-sign">Tk</span>0</td>
                        </tr>
                        <tr>
                            <td style="padding: 0 0 8px 0; text-align: right;" colspan="2">Grand Total :</td>
                            <td style="padding: 0 0 8px 8px; text-align: left;"><span class="tk-sign">Tk</span>{{ $order->grand_total }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 0 0 8px 0; text-align: right;" colspan="2">Paid :</td>
                            <td style="padding: 0 0 8px 8px; text-align: left;"><span class="tk-sign">Tk</span>{{ $order->payable_amount ?? 0 }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 0 0 8px 0; text-align: right;" colspan="2">Due :</td>
                            <td style="padding: 0 0 8px 8px; text-align: left;"><span class="tk-sign">Tk</span>{{ $order->due_amount ?? 0 }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Bottom section -->
            <div class="due-amount-section">
                <hr style="height:2px;border-width:0;color:gray;background-color:#b8b8b8a6;" />

                <div class="warranty-section">
                    <ul>
                        <li>
                            Please scan the QR code within 7 days of purchase to activate your warranty. After this period,
                            the product will no longer be eligible for warranty coverage.
                        </li>
                        <li>
                            Before unboxing, carefully read and understand our Warranty Policy available at
                            <a href="https://tekzo.com.bd/policy/privacy-policy" style="text-decoration: none;">
                                https://tekzo.com.bd/policy/privacy-policy
                            </a>
                            Once unboxed, the product cannot be returned unless a valid warranty issue is confirmed.
                        </li>
                        <li>
                            During the warranty period, submit your warranty claim through our website and contact our
                            support number for assistance.
                        </li>
                    </ul>
                </div>

                <div style="width: 100%; padding: 50px 0;">
                    <center>Thank You For your Purchase!</center>
                </div>

                <table style="width: 100%; font-weight: 400;">
                    <tr>
                        <td style="width: 30%; text-align: left;">
                            <p style="border-top: 1px solid #414141; padding-top: 5px; text-align: center; width: 200px;">
                                Customer Signature
                            </p>
                        </td>
                        <td style="width: 40%;">
                            <center>
                                <div style="font-size: 18px; font-weight: 700; color: #04A98D; letter-spacing: 1px;">
                                    Salamat Innovation
                                </div>
                                <p style="font-size: 11px; font-weight: 200; margin: 4px 0 0 0;">Powered by Salamat Innovation</p>
                            </center>
                        </td>
                        <td style="width: 30%; text-align: right;">
                            <p style="border-top: 1px solid #414141; padding-top: 5px; text-align: center; width: 200px; display: inline-block;">
                                Authority Signature
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

        </div>
    </div>
</body>

</html>