<x-mail::message>
# 🧾 Order Invoice

Hello {{ $order->contact->name }},

Thank you for your order. Your order has been confirmed successfully.

<x-mail::panel>
**Order ID:** {{ $order->invoice_number }}<br>
**Date:** {{ $order->created_at->format('d M Y') }}<br>
**Total:** {{ number_format($order->grand_total, 2) }} ৳
</x-mail::panel>


Thanks,<br>
{{ config('app.name') }}
</x-mail::message>